# DriveQ: production deploy (AWS, Mumbai)

The pilot runs on **one server** in AWS Mumbai (`ap-south-1`) with Docker
Compose. Everything is in this repository:

| File | What it does |
|---|---|
| `docker-compose.prod.yaml` | The stack: Caddy (`web`), PHP-FPM API (`app`), queue `worker`, `scheduler`, PostgreSQL 16 + PostGIS, Redis |
| `backend/Dockerfile.prod` | API image: PHP 8.3-FPM, OPcache, dependencies baked in |
| `frontend/Dockerfile.prod`, `frontend/docker/Caddyfile` | Web image: the built React app, automatic HTTPS, security headers, `/api` → PHP-FPM |
| `deploy/.env.production.example` | Every setting, with notes. Copy to `.env.production` on the server |
| `deploy/server-setup.sh` | One-time server setup: swap, Docker, AWS CLI, firewall, code, backup cron |
| `deploy/deploy.sh` | Build, restart, wait for `/api/healthz` |
| `deploy/backup.sh`, `deploy/restore.sh` | Nightly database + documents backup to S3, and restore |
| `.github/workflows/deploy.yml` | One-click deploy from GitHub Actions |

```
Browser ──HTTPS──▶ web (Caddy :443) ── /api, /up, /sitemap.xml ──▶ app (PHP-FPM :9000)
                         │                                              │
                         └── React app (static files)       postgres · redis · worker · scheduler
```

## 1. AWS account (once)

1. Create the AWS account and turn on **MFA** for the root user. Do the
   rest as an IAM user with admin rights, not as root.
2. In **Billing → Budgets**, create a monthly budget of $10 with an email
   alert at 80%. Check which free credits and free-tier offers your
   account has under **Billing → Credits**.
3. Region: **Asia Pacific (Mumbai) ap-south-1** for everything below.

## 2. Server

1. **S3 bucket for backups:** `driveq-backups-<something-unique>`, block all
   public access (default). Add a lifecycle rule: expire objects after
   30 days.
2. **IAM role for the server:** EC2 role `driveq-server` with an inline policy
   allowing `s3:PutObject`, `s3:GetObject` and `s3:ListBucket` on that bucket
   only. The server then needs no AWS keys.
3. **EC2 instance:**
   - Ubuntu 24.04 LTS. `t3.small` (2 vCPU, 2 GB) is enough for the pilot;
     use `t3.medium` (4 GB) once there is real traffic. Free-tier
     `t3.micro` (1 GB) is too small for PostgreSQL plus builds.
   - 30 GB gp3 disk.
   - Attach the `driveq-server` IAM role.
   - Security group: inbound 22 (your IP only), 80 and 443 (anywhere).
4. **Elastic IP:** allocate one and associate it with the instance, so the
   address survives restarts.
5. SSH in and run the setup script:

   ```bash
   curl -fsSL https://raw.githubusercontent.com/ratnesh-gupta/driveiq-driving-school-marketplace/main/deploy/server-setup.sh -o setup.sh
   sudo bash setup.sh
   ```

   (For a private repository, clone with a deploy key first and run
   `sudo bash /opt/driveq/deploy/server-setup.sh`.)

## 3. Domain and mail

1. **DNS:** an `A` record for `driveq.in` and `www` → the Elastic IP. Caddy
   fetches HTTPS certificates by itself once DNS points at the server.
2. **Transactional mail (Amazon SES, ap-south-1):** verify the domain
   (SES adds DKIM records), add SPF and a DMARC record, request production
   access (new accounts start in the sandbox), then create SMTP credentials
   for `MAIL_USERNAME` / `MAIL_PASSWORD`.
3. **Outreach mail:** use a separate subdomain (for example
   `hello.driveq.in`) with its own SPF/DKIM/DMARC, so cold outreach can't
   hurt password resets and lead alerts. Keep `OUTREACH_MAILER=log` until
   it is set up.

## 4. First start

```bash
cd /opt/driveq
cp deploy/.env.production.example .env.production
nano .env.production          # fill in everything marked REQUIRED
docker compose -f docker-compose.prod.yaml --env-file .env.production run --rm --no-deps app php artisan key:generate --show
# paste the key into APP_KEY in .env.production
deploy/deploy.sh               # builds, starts, runs migrations, waits for health

# Reference data (Pune localities) and the first admin. Never run the demo seeders here.
make prod-seed
docker compose -f docker-compose.prod.yaml --env-file .env.production exec app php artisan driveiq:create-admin you@driveq.in
```

The demo seeders (`db:seed`, `driveiq:demo`) refuse to run in production.

## 5. Deploying updates

**From GitHub:** Actions → **Deploy** → Run workflow → choose `main` (or a
tag). It checks out that commit on the server and runs `deploy/deploy.sh`.
Repository secrets it needs:

| Secret | Value |
|---|---|
| `DEPLOY_HOST` | The Elastic IP |
| `DEPLOY_USER` | `ubuntu` |
| `DEPLOY_SSH_KEY` | A private key made for deploys (`ssh-keygen -t ed25519`); add its public key to `/home/ubuntu/.ssh/authorized_keys` |
| `DEPLOY_KNOWN_HOSTS` | Output of `ssh-keyscan <Elastic IP>` |

**By hand:** `cd /opt/driveq && git pull && deploy/deploy.sh`.

Migrations run when the `app` container starts. Prefer forward-fix
migrations over rollbacks in production.

## 6. Backups and restore

- `deploy/backup.sh` runs nightly at 03:00 IST (cron from the setup script).
  It uploads a PostgreSQL dump and the uploaded documents to
  `s3://<bucket>/driveq/<timestamp>/`. Log: `/var/log/driveq-backup.log`.
- Test a restore before launch, and monthly after that:
  `deploy/restore.sh <timestamp>` (asks you to type RESTORE first).

## 7. Monitoring

- `GET https://driveq.in/api/healthz` checks the database, cache, queue
  backend and document storage, and returns 503 naming the failed check.
  Point a free uptime monitor (UptimeRobot, Better Stack) at it, every 5
  minutes, alerting by email or WhatsApp.
- Logs: `docker compose -f docker-compose.prod.yaml --env-file .env.production logs -f app worker`.
- AWS: a CloudWatch alarm on EC2 `StatusCheckFailed` that recovers the
  instance.

## 8. Behind a CDN or load balancer (later)

The default setup has browsers talking to Caddy directly, so PHP sees each
visitor's real IP and per-IP rate limits work. If you put Cloudflare or an
AWS load balancer in front, set both `TRUSTED_PROXIES` (Laravel) and
`TRUSTED_PROXY_RANGES` (Caddy) to its IP ranges, or every visitor will share
one rate limit.

## 9. Security headers

Caddy sends HSTS, `nosniff`, `X-Frame-Options: DENY`, a referrer and
permissions policy, and a **report-only** Content-Security-Policy. After a
week with no violations in the browser console, change
`Content-Security-Policy-Report-Only` to `Content-Security-Policy` in
`frontend/docker/Caddyfile`.

## 10. Post-deploy smoke test

1. `https://driveq.in/api/healthz` shows every check `ok`.
2. Search, open a school, send an enquiry; the owner gets the email.
3. Log in as admin, school owner, trainer and learner.
4. Upload a learner document and open it from the dashboard.
5. Password reset email arrives.

## 11. Local test of the production stack

```bash
cp deploy/.env.production.example .env.production   # set APP_KEY, DB_PASSWORD, SITE_ADDRESS=:80
make prod-up        # http://localhost
```
