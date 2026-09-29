# DriveIQ — AWS Infrastructure Architecture

**Status:** Proposed · **Region:** `ap-south-1` (Mumbai; closest to Pune, keeps data in India for DPDP) · **DR region:** `ap-south-2` (Hyderabad)

This doc describes how to run DriveIQ (React/Vite SPA + Laravel API + Postgres/PostGIS + Redis + queue workers + optional Reverb) on AWS. It supersedes the DigitalOcean plan in `CLAUDE.md` §3 if adopted.

---

## 1. High-level diagram

```
                         Users (Pune / India, mobile-first)
                                        │
                              Route 53 (driveiq.in)
                                        │
                     ┌──────────── CloudFront ─────────────┐
                     │  + AWS WAF (rate limits, bot ctrl,   │
                     │    OWASP rules, /api/inquiries cap)  │
                     │  + ACM TLS cert (us-east-1)          │
                     └───────┬───────────────────┬──────────┘
                  /* (static)│                   │ /api/*, /broadcasting/*
                             ▼                   ▼
                   S3: frontend bucket    ALB (public subnets, ACM cert)
                   (OAC, private)                │
                                                 ▼
 ┌─────────────────────────── VPC 10.0.0.0/16, 2–3 AZs ─────────────────────────────┐
 │  Private app subnets                                                              │
 │   ┌───────────────────────── ECS Fargate cluster ───────────────────────────┐     │
 │   │  api service      (Laravel Octane/FrankenPHP, 2–6 tasks, autoscale CPU) │     │
 │   │  worker service   (php artisan queue:work, scale on queue depth)        │     │
 │   │  scheduler svc    (php artisan schedule:work, 1 task)                   │     │
 │   │  reverb service   (optional websockets, 1–2 tasks, sticky TG)           │     │
 │   └─────────────────────────────────────────────────────────────────────────┘     │
 │            │                     │                        │                       │
 │  Private data subnets            ▼                        ▼                       │
 │   RDS PostgreSQL 16 + PostGIS   ElastiCache Redis 7     (optional) SQS queue      │
 │   Multi-AZ, gp3, encrypted      cache/session/queue                               │
 │            │                                                                      │
 │   NAT Gateway (1 in pilot, 1/AZ in prod) → Google Maps, WhatsApp, Razorpay        │
 │   VPC endpoints: S3, ECR, Secrets Manager, CloudWatch Logs, SQS                   │
 └───────────────────────────────────────────────────────────────────────────────────┘
           │                                 │
     S3: media bucket (school photos,   Amazon SES (email) · SNS/Pinpoint (SMS/OTP, later)
     learner docs — private, presigned
     URLs, served via CloudFront /media/*)
```

## 2. Component choices

| Concern | AWS service | Why |
|---|---|---|
| DNS | Route 53 | Alias records to CloudFront; health checks for DR failover |
| SPA hosting | S3 + CloudFront (OAC) | Vite build is static; cheap, global edge, SPA fallback via CloudFront Function rewriting to `/index.html` |
| Edge security | AWS WAF on CloudFront | Covers Phase A "rate limiting + spam protection": rate-based rule on `POST /api/inquiries` and `/api/auth/*`, AWS managed rule sets, Bot Control |
| API compute | ECS on Fargate | Existing Dockerfiles; no servers to patch; per-service scaling. (EKS is overkill at this stage; App Runner lacks worker/scheduler patterns.) |
| Load balancer | ALB | Path routing: `/api/*` → api TG, `/app/*` (Reverb WS) → reverb TG; health check `/api/healthz` |
| Database | RDS for PostgreSQL 16, Multi-AZ | PostGIS is supported natively (`CREATE EXTENSION postgis`) for ST_DWithin radius search. Aurora PostgreSQL is an upgrade path when read replicas/traffic grow |
| Cache / queue / sessions | ElastiCache for Redis (Valkey) 7, replication group with 1 replica | Laravel cache, rate limiter, queue driver, Reverb pub/sub |
| Queue (alt.) | SQS | Optional swap for `QUEUE_CONNECTION=sqs` if Redis queue durability becomes a concern |
| Object storage | S3 (media bucket) | School photos public-via-CloudFront; learner/instructor/vehicle documents private with presigned URLs, SSE-KMS |
| Email | Amazon SES (Mumbai) | Lead notifications (Phase D) |
| SMS / OTP | SNS SMS or Pinpoint (DLT-registered sender for India) | Future OTP login |
| Secrets | Secrets Manager + SSM Parameter Store | `APP_KEY`, DB creds (auto-rotation), Razorpay/Maps keys injected as ECS task secrets |
| Images | ECR | Scan on push, immutable tags (git SHA) |
| Observability | CloudWatch Logs/Metrics/Alarms, Container Insights, X-Ray (optional) | Replaces Uptime Kuma; plus Route 53 health checks for uptime |
| Audit | CloudTrail, AWS Config, GuardDuty | Account-level audit; app-level `audit_logs` remain in Postgres |
| Encryption | KMS CMKs | RDS, S3 docs bucket, Secrets, EBS snapshots |

## 3. Network layout

| Subnet tier | CIDRs (per AZ) | Contents | Route |
|---|---|---|---|
| Public | 10.0.0.0/24, 10.0.1.0/24 | ALB, NAT GW | IGW |
| Private-app | 10.0.10.0/23, 10.0.12.0/23 | ECS tasks | NAT |
| Private-data | 10.0.20.0/24, 10.0.21.0/24 | RDS, ElastiCache | none (isolated) |

Security groups (least privilege):
- `sg-alb`: 443 from CloudFront managed prefix list only (+ custom origin header secret checked by ALB rule).
- `sg-app`: 8000/8080 from `sg-alb` only.
- `sg-db`: 5432 from `sg-app` (+ bastion-less access via SSM Session Manager port forwarding for ops).
- `sg-redis`: 6379 from `sg-app`, TLS + AUTH enabled.

## 4. Container services

Build one backend image (PHP 8.3 + pdo_pgsql + redis ext + Octane/FrankenPHP, `composer install --no-dev`, config cached at boot) and run it with different commands:

| Service | Command | Size (pilot) | Scaling |
|---|---|---|---|
| api | `php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000` | 0.5 vCPU / 1 GB × 2 | target CPU 60%, 2–6 tasks |
| worker | `php artisan queue:work redis --tries=3 --max-time=3600` | 0.25 vCPU / 0.5 GB × 1 | step on queue length metric, 1–4 |
| scheduler | `php artisan schedule:work` | 0.25 / 0.5 × 1 | fixed 1 |
| reverb (opt.) | `php artisan reverb:start --port=8080` | 0.25 / 0.5 × 1 | fixed 1–2 |
| migrate (one-off) | `php artisan migrate --force` | run as ECS RunTask in CI before deploy | — |

Note: the current `backend/Dockerfile` and `frontend/Dockerfile` are dev images (`php:8.3-cli`, Vite dev server). Production needs a multi-stage backend Dockerfile; the frontend is not containerized in prod — it's `pnpm build` → S3.

Stateless rules: `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, `FILESYSTEM_DISK=s3`, `LOG_CHANNEL=stderr`, `TRUSTED_PROXIES=*` (behind ALB/CloudFront).

## 5. Data layer

- **RDS PostgreSQL 16**, `db.t4g.medium` Multi-AZ for pilot → `db.r7g.large` at scale; gp3 100 GB autoscaling storage; 14-day PITR; deletion protection; Performance Insights on; `rds.force_ssl=1`.
- PostGIS enabled in a migration; GIST index on the schools geography column.
- Index `(school_id, created_at)` on operational tables per CLAUDE.md.
- RDS Proxy optional once task count × Octane workers approaches `max_connections`.
- **Backups/DR:** automated snapshots + AWS Backup copy to `ap-south-2` daily; RPO ≤ 24h (≤ 5 min in-region via PITR), RTO ≈ 2h by restoring stack via IaC in DR region.
- **DPDP:** all personal data (learner docs, phone numbers) stays in India regions; S3 docs bucket blocks public access, versioned, lifecycle to IA after 90 days; retention/deletion jobs run via scheduler.

## 6. Environments & accounts

AWS Organizations with separate accounts: `shared` (ECR, CI roles), `staging`, `prod`. Staging mirrors prod at minimum sizes (single-AZ RDS, 1 task each, no NAT per AZ).

## 7. CI/CD (GitHub Actions → AWS via OIDC, no long-lived keys)

1. PR: `php artisan test`, `pnpm typecheck`, `pnpm build`.
2. Merge to `main`:
   - Build backend image → push to ECR tagged with git SHA.
   - Run migrate task (ECS RunTask) against staging → deploy api/worker/scheduler services (rolling, circuit breaker with rollback).
   - `pnpm build` with `VITE_API_BASE_URL` → `aws s3 sync` → CloudFront invalidation of `/index.html`.
3. Promote same image SHA to prod via manual approval (GitHub environment protection).

## 8. Infrastructure as Code

Terraform (or AWS CDK in TypeScript) in `infra/`, modules: `network`, `edge` (CloudFront/WAF/Route53/ACM), `ecs`, `rds`, `redis`, `storage`, `observability`, `ci-oidc`. State in S3 + DynamoDB lock in the `shared` account.

## 9. Monitoring & alarms

- ALB 5xx rate, p95 latency > 1s, unhealthy hosts.
- ECS CPU/memory, running task count < desired.
- RDS CPU, free storage, connections, replica lag.
- Redis memory, evictions.
- Queue depth / oldest job age (custom metric pushed by scheduler).
- WAF blocked-request spikes on inquiry endpoint.
- Alerts → SNS → email/Slack (AWS Chatbot).

## 10. Cost estimate (pilot, ap-south-1, on-demand, approximate USD/month)

| Item | Est. |
|---|---|
| Fargate (api 2×, worker, scheduler) | 45 |
| ALB | 20 |
| NAT Gateway (1) + data | 40 |
| RDS t4g.medium Multi-AZ + 100 GB | 120 |
| ElastiCache t4g.small ×2 | 50 |
| CloudFront + S3 + WAF | 25 |
| Secrets, CloudWatch, Route 53, SES | 25 |
| **Total** | **≈ $325/mo** |

Cost levers for an early pilot: single-AZ RDS (~$60), single Redis node, Fargate Spot for workers, Compute Savings Plan (~20–30% off). A leaner "starter" option is Lightsail containers + Lightsail Postgres (~$60/mo) but it lacks WAF/Multi-AZ and is harder to grow out of.

## 11. Scaling path

1. Pilot (Pune): as above.
2. Growth: RDS read replica for search/stats endpoints; RDS Proxy; CloudFront caching of public GET endpoints (`/api/localities`, `/api/schools/featured`) with short TTLs.
3. Multi-city: Aurora PostgreSQL, OpenSearch (geo queries) if PostGIS search becomes the bottleneck, SQS + EventBridge for notification fan-out.

## 12. Next steps

- [ ] Production multi-stage backend Dockerfile (Octane/FrankenPHP)
- [ ] `infra/` Terraform skeleton (network + ECR + ECS + RDS)
- [ ] GitHub OIDC role + deploy workflow
- [ ] Configure Laravel for S3 disk, Redis, SES, trusted proxies
- [ ] WAF rate rules matching inquiry throttling requirements
