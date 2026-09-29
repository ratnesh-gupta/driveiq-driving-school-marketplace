# DriveIQ — AWS Infrastructure Architecture (Pilot, cost-optimised)

**Status:** Proposed · **Region:** `ap-south-1` (Mumbai; closest to Pune, keeps personal data in India for DPDP)

This doc describes a low-cost way to run DriveIQ (React/Vite SPA + Laravel API + Postgres/PostGIS + Redis + queue worker + optional Reverb) on AWS for the Pune pilot. It supersedes the DigitalOcean plan in `CLAUDE.md` §3 if adopted.

**Design goal:** keep the pilot under roughly $60/month while still getting managed Postgres backups, TLS, a CDN and WAF-style rate limiting. Section 9 describes how to grow out of it.

Deliberately **not** used for the pilot:

| Removed | Replaced by |
|---|---|
| ElastiCache | Redis running on the app EC2 instance (Docker container) |
| Amazon SES | Laravel SMTP mailer pointed at an external provider (e.g. Zoho Mail / Brevo free tier), or `MAIL_MAILER=log` until notifications ship in Phase D |
| Secrets Manager | `.env` on the instance, populated at deploy time from **SSM Parameter Store** SecureString parameters (standard tier is free) |
| ECS Fargate, ALB, NAT Gateway | One EC2 instance running Docker Compose, with Caddy for TLS; instance sits in a public subnet so no NAT is needed |
| RDS Multi-AZ | Single-AZ RDS with automated backups (+ PITR) |

---

## 1. High-level diagram

```
                     Users (Pune / India, mobile-first)
                                    │
                          Route 53 (driveiq.in)
                                    │
                 ┌──────────── CloudFront ─────────────┐
                 │  ACM TLS cert (us-east-1)            │
                 │  AWS WAF: rate rule on /api/*        │
                 │  (optional, ~$6/mo)                  │
                 └───────┬──────────────────┬───────────┘
              /* static  │                  │ /api/*, /app/* (Reverb WS)
                         ▼                  ▼
               S3: frontend bucket   ┌──────────── VPC 10.0.0.0/16 ─────────────────────┐
               (private, OAC)        │  Public subnet (AZ-a)                             │
                                     │  ┌───────── EC2 t4g.medium (Docker Compose) ───┐ │
                                     │  │ caddy     :443  TLS, reverse proxy          │ │
                                     │  │ api       Laravel Octane/FrankenPHP :8000   │ │
                                     │  │ worker    php artisan queue:work redis      │ │
                                     │  │ scheduler php artisan schedule:work         │ │
                                     │  │ reverb    (optional) :8080                  │ │
                                     │  │ redis     :6379, bound to docker network,   │ │
                                     │  │           AOF on, maxmemory 256mb           │ │
                                     │  └─────────────────────────────────────────────┘ │
                                     │               │ 5432                              │
                                     │  Private subnets (AZ-a, AZ-b)                     │
                                     │   RDS PostgreSQL 16 + PostGIS, db.t4g.micro,      │
                                     │   single-AZ, gp3 20 GB, encrypted, 7-day PITR     │
                                     └───────────────────────────────────────────────────┘
                                                     │
                     S3: media bucket (school photos public via CloudFront /media/*;
                     learner/instructor docs private, presigned URLs)
```

## 2. Component choices

| Concern | Service | Notes |
|---|---|---|
| DNS | Route 53 | Alias to CloudFront; `api` origin points at the EC2 Elastic IP |
| SPA hosting | S3 + CloudFront (OAC) | CloudFront Function rewrites unknown paths to `/index.html` |
| Rate limiting | Laravel `throttle` middleware backed by Redis (free) + optional WAF rate rule | Covers Phase A inquiry spam protection |
| Compute | 1 × EC2 `t4g.medium` (2 vCPU / 4 GB, ARM) | Runs everything except Postgres; start with `t4g.small` if load is tiny |
| TLS at origin | Caddy (auto Let's Encrypt) | CloudFront → Caddy over HTTPS; security group only allows CloudFront prefix list on 443 |
| Database | RDS PostgreSQL 16, single-AZ `db.t4g.micro` | Managed backups/patching; PostGIS supported (`CREATE EXTENSION postgis`) |
| Cache / queue / sessions | Redis 7 container on the EC2 instance | Persistent volume, AOF enabled, not exposed outside the Docker network |
| Object storage | S3 (media bucket) | SSE-S3 encryption; docs bucket blocks public access |
| Email | External SMTP provider | Laravel `smtp` mailer; no AWS service |
| Secrets | SSM Parameter Store (SecureString, free) → `.env` | Instance role can read `/driveiq/prod/*` only |
| Images | ECR (or GHCR) | ECR private repo ~cents/month at this size |
| Monitoring | CloudWatch agent (basic metrics + a few alarms), Route 53 health check on `/api/healthz` | |
| Access | SSM Session Manager | No SSH port open, no bastion |

## 3. Network & security

- One VPC, public subnet (EC2) + two private subnets (RDS requires a 2-AZ subnet group).
- No NAT Gateway: the EC2 instance has a public Elastic IP for outbound calls (Google Maps, WhatsApp, Razorpay, SMTP).
- `sg-app`: inbound 443 from the CloudFront managed prefix list only; nothing else.
- `sg-db`: inbound 5432 from `sg-app` only; `rds.force_ssl=1`.
- Redis is only reachable on the internal Docker network and requires a password (`requirepass`).
- EC2 IMDSv2 required, EBS encrypted, automatic OS patching via SSM Patch Manager.

## 4. Docker Compose on the instance

| Service | Command | Notes |
|---|---|---|
| caddy | `caddy run` | Reverse proxy `/api/*` → api:8000, `/app/*` → reverb:8080 |
| api | `php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000` | 2–4 Octane workers |
| worker | `php artisan queue:work redis --tries=3 --max-time=3600` | |
| scheduler | `php artisan schedule:work` | |
| reverb | `php artisan reverb:start --port=8080` | optional |
| redis | `redis-server --appendonly yes --maxmemory 256mb --requirepass $REDIS_PASSWORD` | volume `redis-data` |

Laravel settings: `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `FILESYSTEM_DISK=s3`, `MAIL_MAILER=smtp`, `LOG_CHANNEL=stderr`, `TRUSTED_PROXIES=*`.

The current `backend/Dockerfile` and `frontend/Dockerfile` are dev images. Production needs a multi-stage backend image; the frontend is built with `pnpm build` and uploaded to S3.

## 5. Data & backups

- RDS automated backups, 7-day point-in-time recovery; deletion protection on.
- Weekly manual snapshot kept 30 days.
- Redis holds only cache, sessions and queued jobs; losing it logs users out and may drop in-flight jobs, but no system-of-record data. AOF persistence limits that on restart.
- EC2: daily EBS snapshot via Data Lifecycle Manager (7 retained).
- **Recovery:** instance is disposable. Re-create from the launch template + user-data (installs Docker, pulls `.env` from Parameter Store, `docker compose up`). Target RTO ≈ 30 min, RPO ≈ 5 min for Postgres.
- **DPDP:** all data stays in `ap-south-1`.

## 6. Environments

- **prod:** as above.
- **staging:** optional; either a `t4g.small` spot instance with Postgres in a container, or run it only when needed.

## 7. CI/CD (GitHub Actions → AWS via OIDC)

1. PR: `php artisan test`, `pnpm typecheck`, `pnpm build`.
2. Merge to `main`:
   - Build ARM backend image → push to ECR tagged with git SHA.
   - `aws ssm send-command` on the instance: refresh `.env` from Parameter Store, `docker compose pull && php artisan migrate --force && docker compose up -d`.
   - `pnpm build` → `aws s3 sync` → CloudFront invalidation of `/index.html`.

Infrastructure defined in Terraform under `infra/` (modules: `network`, `edge`, `ec2-app`, `rds`, `storage`, `ci-oidc`).

## 8. Cost estimate (ap-south-1, on-demand, approximate USD/month)

Figures are rough; confirm with the AWS Pricing Calculator before committing.

| Item | Est. |
|---|---|
| EC2 t4g.medium + 30 GB gp3 + Elastic IP | 25 |
| RDS db.t4g.micro single-AZ + 20 GB gp3 + backups | 18 |
| CloudFront + S3 (low traffic) | 3 |
| Route 53 hosted zone + health check | 1.5 |
| ECR, CloudWatch basic, Parameter Store | 2 |
| WAF (optional: 1 web ACL + 1 rule) | 6 |
| **Total** | **≈ $50–55/mo** (≈ $45 without WAF) |

Further savings: 1-year Savings Plan / Reserved Instance on EC2 and RDS (~30–40% off), or `t4g.small` for EC2 while traffic is low. For comparison, the earlier Fargate + ALB + NAT + Multi-AZ design was ≈ $325/mo; removing only ElastiCache, SES and Secrets Manager from it would have saved about $55. Most of the saving comes from dropping NAT, ALB, Fargate and Multi-AZ RDS.

### Trade-offs accepted for the pilot

- **Single point of failure:** one EC2 instance and single-AZ RDS. An AZ outage means downtime until restore.
- **No horizontal scaling:** scale up the instance size first.
- **Manual-ish ops:** OS patching and Docker updates are our responsibility (automated via SSM).

## 9. Upgrade path (when traffic or revenue justifies it)

1. Move Redis to ElastiCache and put an ALB + Auto Scaling Group (2 instances) in front of the API.
2. Turn on RDS Multi-AZ; add RDS Proxy/read replica for search.
3. Move to ECS Fargate, SES for email, Secrets Manager with rotation.
4. Cross-region backup copies to `ap-south-2` for disaster recovery.

## 10. Next steps

- [ ] Production multi-stage backend Dockerfile (Octane/FrankenPHP, ARM)
- [ ] `docker-compose.prod.yml` + Caddyfile
- [ ] `infra/` Terraform skeleton (VPC, EC2, RDS, S3, CloudFront)
- [ ] GitHub OIDC role + deploy workflow
- [ ] Laravel config for S3 disk, Redis, SMTP, trusted proxies, throttling on inquiries
