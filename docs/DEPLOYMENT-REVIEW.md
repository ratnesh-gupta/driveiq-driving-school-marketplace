# DriveQ - Deployment Architecture Review (Pune pilot)

**Date:** 2026-10-31  **Scope:** review only; no application, compose, Docker, CI or env file was changed.
**Verified against:** `docker-compose.yaml`, `docker-compose.prod.yaml`, `backend/Dockerfile`, `frontend/Dockerfile`, `frontend/.dockerignore`, `Makefile`, `.github/workflows/ci.yml`, `backend/.env.example`, `backend/.env.docker.example`, `backend/bootstrap/app.php`, `backend/routes/{api,console}.php`, `backend/config/{queue,filesystems,logging,mail,broadcasting,app,database}.php`, `backend/composer.json`, `backend/app/Http/Middleware/SecurityHeaders.php`, `docs/{DEPLOY,MVP-LAUNCH-PLAN,ROADMAP,SYSTEM-FLOW}.md`, `docs/security/*`.
All prices below are approximate and unverified; confirm on the vendor pricing pages before committing.

---

## 1. Current state, as built

### 1.1 What exists

- Two compose files. `docker-compose.yaml` is local dev: bind-mounts source, `composer install` and `artisan serve` on start, `pnpm dev`. `docker-compose.prod.yaml` is "production-oriented" but is structurally the same thing minus the bind mount.
- Both backend and frontend Dockerfiles are dev images: `php:8.3-cli` with only `pdo pdo_pgsql`, and `node:24-bullseye` with only `.npmrc`/`.pnpmrc` copied in. **Neither image COPYs application source.**
- CI (`.github/workflows/ci.yml`) is test-only: PHPUnit on PostGIS + Redis, Pint, `composer audit`, frontend typecheck/build, `pnpm audit`. No image build, no registry push, no deploy job.
- Scheduler: `schedule:work` in its own container; 8 scheduled entries in `routes/console.php`, all `withoutOverlapping` except the daily prune (none use `onOneServer`).
- Health: `GET /api/healthz` is a static `{status: ok}` (`routes/api.php:43`). Laravel's built-in `GET /up` is also enabled (`bootstrap/app.php:19`) but is not a dependency check.
- Security headers on the JSON API only (`SecurityHeaders` middleware, `bootstrap/app.php:41`): nosniff, Referrer-Policy, X-Frame-Options, `frame-ancestors 'none'`, HSTS when `isSecure()`. Nothing on the SPA.
- Trusted proxies are env-driven (`TRUSTED_PROXIES`, `bootstrap/app.php:34`), default empty (trust nobody).
- Storage: `DOCUMENTS_DISK=local` default (container filesystem). `s3` disk is defined in `config/filesystems.php`, but `league/flysystem-aws-s3-v3` is **not** in `composer.json`.
- Time zone: app `UTC` (`config/app.php:71`); ops reminders use a per-school zone defaulting to `Asia/Kolkata`.
- Reverb: not installed (not in `composer.json`), `BROADCAST_CONNECTION=log`.

### 1.2 As-built topology (prod compose, if it were started)

```
 Internet (no TLS, no proxy, no firewall defined)
      |                         |
      | :5173 (HTTP)            | :8000 (HTTP)
      v                         v
+-------------+          +-------------------+      +-----------------+
| frontend    |          | backend           |      | worker          |
| node:24     |          | php:8.3-cli       |      | php:8.3-cli     |
| pnpm install|          | composer install  |      | composer install|
| pnpm build  |          | migrate --force   |      | queue:work      |
| vite preview|          | config/route:cache|      |  --tries=3      |
|  (DEV TOOL) |          | artisan serve     |      |  --max-time=3600|
+-------------+          |  (single thread)  |      +--------+--------+
 VITE_API_BASE_URL       +----+----------+---+               |
 baked in at runtime          |          |        +----------+--------+
                              |          |        | scheduler         |
                              v          v        | schedule:work     |
                       +-----------+ +--------+   | composer install  |
                       | postgres  | | redis  |<--+-------------------+
                       | postgis   | | 7      |   (all share one Docker
                       | 16-3.4    | | AOF on |    network; no source code
                       | vol: pg   | | vol    |    inside any PHP image)
                       +-----------+ +--------+
   Not present: reverse proxy, TLS, object storage, backups, mail, monitoring,
   log shipping, error tracker, registry, deploy job, Reverb, firewall.
```

---

## 2. Requirements for the Pune pilot

### 2.1 Load estimate (assumptions, to be corrected with real numbers)

| Item | Estimate |
|---|---|
| Schools | ~50 (10 to 20 in the first month); ~150 school-side users (owner, managers, trainers) |
| Visitors | low thousands per month, say 3,000 to 8,000 sessions; ~5 to 10 pageviews each |
| Average API rate | ~0.1 to 0.5 req/s; **peak ~5 to 10 req/s** (a WhatsApp or ad-campaign burst) |
| Hot path | `GET /api/schools` PostGIS radius search with ranking (SQL, paginated), then school detail, compare, localities |
| Writes | inquiries (tens to low hundreds per day), school-side CRUD, uploads (5 MB cap) |
| Background | 8 scheduler entries, ~100s of emails per day, outreach cap 150/day |
| Data | well under 5 GB for the first year; ID documents in object storage are the largest part |

This is far below the capacity of one small VM. The risks are operational (no restart, no backup, no TLS), not scale.

### 2.2 Targets

| Target | Value | Rationale |
|---|---|---|
| Availability | **99.5% monthly** (about 3.6 h/month), matches PROJECT-PLAN success metric | Single region, single app node; a restart or deploy blip is acceptable, a silent multi-hour outage is not |
| RPO (database) | **<= 1 h** with managed PITR, otherwise 24 h with nightly dump | Lead data and DPDP records; a day of lost inquiries/consents is painful but survivable at pilot |
| RPO (documents) | 0 (object storage, versioning on) | Identity documents must not live on a container disk |
| RTO | **<= 4 h** to a working site from a cold start, rehearsed once | Rebuild droplet from compose + env in Spaces/secret store, restore DB |
| Security | TLS everywhere, only 80/443 public, headers on SPA and API | BSEC-02, BSEC-15, BSEC-16, FSEC-02 |
| Cost ceiling | **~USD 40 to 60 per month** infrastructure | Cheap single-region setup per CLAUDE.md (DigitalOcean, Cloudflare, Spaces, Uptime Kuma) |

Non-goals at the pilot: multi-region, autoscaling, zero-downtime on every deploy (a 5 to 20 second blip is fine if migrations are expand-only), Kubernetes.

---

## 3. Findings (ranked)

Severity: **Blocker** = cannot launch; **High** = fix before real data; **Medium** = fix within the first weeks; **Low** = hygiene.

### Blockers

**D-01 Production compose cannot run: no application code in any image.** (`backend/Dockerfile`, `frontend/Dockerfile`, `docker-compose.prod.yaml:51-60,77-80,96-100,123-127`.)
The backend Dockerfile has no `COPY`, and prod compose has no source bind mount, so `composer install` fails with no `composer.json`. The frontend image only has `.npmrc`/`.pnpmrc`. `docker compose -f docker-compose.prod.yaml up` produces failing containers. This is the root of DIQ-1301.
Fix: multi-stage, code-baked, non-root images (section 4.3). Owner: infra. Ticket: DIQ-1301.

**D-02 `artisan serve` and `vite preview`/`pnpm serve` are the production servers, from dev-class root images.** (`docker-compose.prod.yaml:67,127`; FSEC-02.)
`artisan serve` is a single-process PHP built-in server (one request at a time; a slow PostGIS query or a 5 MB upload blocks everyone), `vite preview` is documented as not for production, and both run as root on a `node:24-bullseye` / full `php-cli` image with build tooling and `ignore-scripts false`. Prod also runs `composer install` and `pnpm install && pnpm build` **at every container start**: slow boot, needs outbound network at runtime, a registry outage becomes an app outage, and a half-finished build serves broken assets.
Fix: PHP-FPM (or FrankenPHP) behind a proxy; static SPA built at image build time. DIQ-1301.

**D-03 No TLS, no reverse proxy, app ports published directly.** (`docker-compose.prod.yaml:67-68,126-127` publish `8000` and `5173`; DEPLOY.md only lists "TLS termination (Caddy / Nginx / cloud LB)" as a prerequisite.)
Bearer tokens, passwords and ID documents would travel in cleartext; HSTS in `SecurityHeaders` only fires when `isSecure()`; no HTTP to HTTPS redirect; Reverb `wss` impossible. DIQ-1302.

**D-04 Prod backend env is incomplete and unsafe by omission.** (`docker-compose.prod.yaml:35-50`.)
The backend gets a hand-listed `environment:` block with **no `APP_KEY`** (encrypted values such as the Google OAuth state, signed document URLs and signed email-verify links need it; boot fails or breaks), **no `TRUSTED_PROXIES`** (so BSEC-02 is wired in code but not in deployment: every rate limiter, `audit_logs.ip_address`, consent IPs and the honeypot would see the proxy's IP, or if someone sets `*` carelessly, can be spoofed), no `FRONTEND_URL`/`SANCTUM_STATEFUL_DOMAINS`/CORS origin, no `MAIL_*`, `OUTREACH_*`, `MESSAGING_DRIVER`, `DOCUMENTS_DISK`/`AWS_*`, `PLANS_ENFORCE`, `RETENTION_*`, `BILLING_*`, `LOG_*`. Worker and scheduler get a *different, shorter* list (no `APP_KEY`, no mail, no storage), so jobs that send mail, sign URLs or write to the documents disk run with different config than the web process. `APP_URL` defaults to `http://localhost:8000`, which feeds signed email links and the Google OAuth redirect URI. BSEC-16 already notes nothing asserts `APP_DEBUG=false` at boot.
Fix: one shared `env_file`/YAML anchor for all three PHP services; `.env.production.example`; `${VAR:?}` guards for secrets; boot guard (backend ticket). DIQ-1303 + BSEC-16 follow-up.

**D-05 PHP image lacks the Redis client; S3 package missing; no error tracker.** (`backend/Dockerfile:6`, `backend/composer.json:8-12`, `.env.example` `REDIS_CLIENT=phpredis`.)
The image installs only `pdo pdo_pgsql` (no `redis`, `bcmath`, `zip`, `intl`, `opcache`, `pcntl`), and `composer.json` has no `predis/predis`. With `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis` and the default `REDIS_CLIENT=phpredis`, the app fails on first cache or queue call. CI hides this: it installs the `redis` extension on the runner but uses `CACHE_STORE=array` and `QUEUE_CONNECTION=sync`, so **no CI job exercises Redis cache, queue or the real image**. Also `league/flysystem-aws-s3-v3` is absent, so `DOCUMENTS_DISK=s3` cannot work (DIQ-1305 is bigger than "set an env var"), and there is no Sentry/Bugsnag SDK (DIQ-1311).
Fix: image build installs `redis` (PECL), `bcmath`, `opcache`, `pcntl`, `intl`; backend adds `league/flysystem-aws-s3-v3` and an error-tracker package; add a CI job that builds the real image and boots it with Redis queue. DIQ-1301/1305/1311. Owner: infra + backend.

### High

**D-06 No backups, no restore test.** (Nothing in compose, Makefile or CI; ROADMAP DIQ-1306.)
Postgres is a single named volume on one host; Redis AOF is on a volume. One bad `docker volume rm`, disk failure, or a destructive migration loses leads, consents, invoices and the append-only audit log (a DPDP evidence set). Makefile has `migrate-fresh`/`fresh`/`seed` targets that destroy data; only the `prod-*` targets use the prod file, but `make fresh` against a mis-set `COMPOSE_FILE` is one typo from catastrophe.
Fix: managed Postgres PITR or `pg_dump` + WAL to Spaces; documents bucket versioned; a scripted restore into a scratch DB run once and recorded. DIQ-1306.

**D-07 Migrations run implicitly on web boot, racing the other services.** (`docker-compose.prod.yaml:63-67`.)
`php artisan migrate --force` is part of the backend start command. Consequences: every restart re-runs migrate; if a migration is slow (a PostGIS index build or a backfill) the HTTP server is not listening; worker and scheduler start in parallel running old or half-migrated code against a changing schema (`depends_on: backend` only waits for container *start*, not for migrate to finish); two web replicas would race migrations (use `--isolated` with a cache lock). The audit-log trigger migrations and `CREATE EXTENSION postgis` (`2026_09_16_100001_*.php`, `2026_09_29_000002_*.php`) need a role that may create extensions. On DigitalOcean managed Postgres the app user cannot `CREATE EXTENSION`; PostGIS must be enabled once by the admin user, otherwise the very first migrate fails.
DEPLOY.md's rollback (`migrate:rollback --step=1`) is dangerous with this codebase (data-destroying `down()`s, the append-only trigger) and contradicts its own advice to forward-fix.
Fix: migrate as an explicit one-shot step in the deploy script before the new app starts, with a pre-migrate snapshot; expand/contract rule (section 4.6); drop rollback-by-migration from DEPLOY.md. DIQ-1308.

**D-08 Worker and scheduler are not supervised for the failure modes that matter.** (`docker-compose.prod.yaml:72-114`; DIQ-1310.)
`restart: unless-stopped` covers crashes but not hangs: no container healthcheck, no `--timeout`, `retry_after` is 90 s (`config/queue.php:71`) while `queue:work` has no `--timeout`, so a job slower than 90 s can be re-delivered while still running (double email). No `--backoff`, no `--max-jobs/--memory`. Workers are not signalled on deploy (`queue:restart` is never called), so after a code deploy they run old code until `--max-time=3600` expires. A failed job lands silently in `failed_jobs` (`database-uuids`): nothing alerts. The scheduler is correctly a singleton today (one container, `schedule:work`), but nothing documents "never scale this to 2": with 2 replicas every reminder, outreach email and subscription transition runs twice (none use `->onOneServer()`; `withoutOverlapping` also needs the shared Redis cache, which is only true because `CACHE_STORE=redis`). If Redis is flushed, overlap locks vanish.
Fix: dedicated `php artisan schedule:run` loop or `schedule:work` stays singleton with a healthcheck on a heartbeat; worker `--timeout=60 --backoff=30,120 --max-jobs=500 --memory=256`; a scheduled `queue:monitor default:100` plus `queue:failed` count alert; `queue:restart` in deploy. DIQ-1310/1311.

**D-09 Documents on container disk, no encryption, no real object storage wiring.** (`.env.example` `DOCUMENTS_DISK=local`; `config/filesystems.php:34-42`; BSEC-15.)
Local disk is `storage/app/private` inside the container; with no volume in prod compose a redeploy deletes every learner Aadhaar/PAN/licence upload. For Spaces: set `AWS_ENDPOINT=https://<region>.digitaloceanspaces.com`, `AWS_USE_PATH_STYLE_ENDPOINT=false`, `AWS_DEFAULT_REGION=<spaces region slug>` (the `ap-south-1` in `.env.example` is an AWS name and is wrong for Spaces), private bucket, server-side encryption, versioning, lifecycle rules; signed URLs (`temporaryUrl`, 5 min) are generated by the S3 client and work against Spaces, but the URL host is the Spaces endpoint, so the **SPA CSP `connect-src`/`img-src`/`frame-src` must allow the Spaces host** (see D-12) and CORS must be set on the bucket if the SPA fetches documents via XHR. Public school photos (not yet built) should use a second, public bucket behind the CDN; do not mix.
DIQ-1305 plus BSEC-15.

**D-10 Postgres: unpinned minor, open port in dev, default creds in dev, same host as app.** (`docker-compose.yaml:7-11`, `docker-compose.prod.yaml:9`.)
`postgis/postgis:16-3.4` is a floating-patch image (the minor will move on `pull`; acceptable if pinned by digest). Dev publishes `5432` and `6379` on all interfaces with password `driveiq`/no Redis auth: fine on a laptop, a breach if that file is ever used on a server. Prod does not publish them (good) but Redis has **no `requirepass`** and no `maxmemory` policy; it is the cache, session store, rate-limit store, queue and scheduler lock, so `allkeys-lru` would silently evict queue jobs and locks. Postgres has no tuned settings for a 2 to 4 GB host (`shared_buffers`, `max_connections`) and no `pg_stat_statements`.
Fix: separate the concerns: either managed Postgres, or tuned container + backup sidecar; Redis `--requirepass`, `--maxmemory-policy noeviction`, AOF `everysec` (already `appendonly yes`), bound to the compose network only.

**D-11 No CD pipeline, no image registry, no rollback.** (`.github/workflows/ci.yml` ends at tests; DIQ-1308.)
Deploys would be "SSH in, `git pull`, `docker compose up --build`" on a production host with builds at runtime: non-reproducible, no previous image to return to, no way to know which commit is live. Env parity gap: CI runs PHP 8.3 on the runner and Node 22; the dev Dockerfile uses Node 24, the prod image is Node 24, CI builds with Node 22 (a build that passes CI is not the artifact that ships). No staging environment is defined.

**D-12 Security headers and CSP exist for the API only; SPA served with none.** (`SecurityHeaders` is `api` middleware; FSEC-02.)
The SPA HTML/JS is served by `vite preview` with no CSP, no HSTS, no `frame-ancestors`, no cache headers. The proxy that terminates TLS is the right place (one policy, header-per-route control, `Report-Only` rollout). The API's `Content-Security-Policy: frame-ancestors 'none'` is correct for JSON; do not merge the SPA CSP into it. DIQ-1304 is owned by "Backend" in ROADMAP but the work is in the proxy/static server config, so re-assign to infra with frontend input (Maps, fonts, Spaces host, `wss` host).

**D-13 Deep health check missing; `/api/healthz` always says ok.** (`routes/api.php:43`; DIQ-1307.)
It reports healthy with Postgres down, Redis down, queue stalled or disk unwritable. Docker/Uptime Kuma/Cloudflare would call a dead app healthy. Needs liveness (cheap, public, for load balancer) separate from readiness/deep (DB `select 1`, Redis ping, queue age/size and heartbeat of the scheduler, documents disk write/read, migration count), the deep one behind a secret header or IP allowlist so it is not an unauthenticated information leak and not an unauthenticated DB load vector.

### Medium

**D-14 Logging is local files in a container; no retention, no shipping.** (`.env.example`: `LOG_CHANNEL=stack`, `LOG_STACK=single`, `LOG_LEVEL=debug`.)
`storage/logs/laravel.log` in an ephemeral container disk, unrotated (`single`), at `debug` level in the shipped default. BSEC-16 notes the `log` messaging/mail drivers write rendered messages (one-time claim codes, lead names) to those logs: PII in logs with no retention policy (DPDP). Fix: `LOG_CHANNEL=stderr` (JSON or plain) so Docker captures it, Docker `json-file` with `max-size/max-file`, `LOG_LEVEL=warning` in prod, and an error tracker (Sentry free/low tier). Retention for infra logs must be set (14 to 30 days).

**D-15 No monitoring or alerting.** (DIQ-1311.) Needed minimum: external uptime on the SPA and `GET /api/healthz` plus deep health, TLS expiry, disk usage, queue age, failed job count, scheduler heartbeat (a missing heartbeat means `ops-reminders` and `subscriptions` silently stopped), backup freshness, and email bounce rate. Uptime Kuma must **not** live on the same droplet as the app (it would die with it); use a second small host, free tier of a hosted monitor, or run it on a different provider.

**D-16 Mail deliverability is unplanned.** (`MAIL_MAILER=log` default; `OUTREACH_MAILER=log`; `OUTREACH_FROM_ADDRESS=partners@hello.driveq.in`.)
Transactional mail (password reset, owner verification, lead alerts, review links) and cold outreach are correctly separated in config (`config/mail.php` `outreach` mailer), but the infrastructure must match: transactional from the apex or `mail.driveq.in` via a transactional provider (SES ap-south-1, Brevo, Postmark, Resend) with SPF, DKIM and DMARC (`p=none` then `quarantine`) and a bounce/complaint webhook or at least mailbox monitoring; outreach from `hello.driveq.in` on its own mailbox/IP reputation (the example uses `smtp.gmail.com`, which means a Google Workspace mailbox with a daily send limit; the 150/day cap is sized for that). Also `List-Unsubscribe` header and the suppression list exist in code (M9) but are only meaningful if the unsubscribe URL is publicly reachable over HTTPS (D-03). DO droplets block outbound port 25 and may restrict 465/587 on new accounts: use an HTTPS API or verified submission port; confirm with provider. Email send is queued, so a dead worker equals no email (D-08).

**D-17 Reverb: do not enable at the pilot.** (`routes/channels.php`, BSEC-05, DIQ-1404/1405, MVP-LAUNCH-PLAN M1.)
Reverb is a long-lived WebSocket server (single node, in-memory connections, needs `wss` via the proxy with `Upgrade`/`Connection` headers and long read timeouts, a separate subdomain or path, Redis pub/sub if scaled), it is not installed, and the broadcast channel leak (`school.{id}` admits trainers and learners and carries staff-only payloads) must be fixed first. Polling the notification list every 30 to 60 s is sufficient for 150 users and costs nothing. Keep `BROADCAST_CONNECTION=log` (or `null`). Revisit at DIQ-1404 with the plan in section 4.7.

**D-18 Cache headers for SPA and API are undefined.**
Needed: hashed Vite assets `Cache-Control: public, max-age=31536000, immutable`; `index.html` `no-cache` (or short max-age with revalidate) so deploys are picked up; `/api/*` default `no-store` for authenticated (already done by middleware when a bearer token is present). Public read endpoints (`/api/schools`, `/api/localities`, school detail) are the hot path and are anonymous: allow Cloudflare to cache selected GETs for 30 to 120 s via `Cache-Control: public, s-maxage=60, stale-while-revalidate=300` and a Cloudflare Cache Rule on `/api/schools*`, `/api/localities*`, with `Vary` handled (query string is part of the key, so geo searches on raw lat/lng will have a low hit rate; consider rounding coordinates to 3 decimals on the client for cacheability). Do not cache anything with `Authorization`. This is an optimisation, not a launch gate; at pilot load the origin does not need it. No `sitemap.xml` exists (only `robots.txt`); SEO pages are a SPA, so crawlability for locality pages (DIQ-1601) needs prerender or a sitemap and is a growth item, not infra.

**D-19 Time zone and schedule semantics.**
App zone is UTC (`config/app.php:71`); schedule times are in app zone, so `dailyAt('02:30')` is 08:00 IST and `dailyAt('03:15')` subscriptions is 08:45 IST, not off-peak and not "night". Outreach `OUTREACH_START_HOUR=10`/`END_HOUR=18` must be evaluated in `Asia/Kolkata` or emails go out at 15:30 to 23:30 IST (verify in `config/outreach.php`/`OutreachCommand`; not confirmed here). Recommended: keep storage UTC (correct), set `->timezone('Asia/Kolkata')` on schedule entries where local-time semantics matter, and set the container `TZ=Asia/Kolkata` only for log readability. Run the DB in UTC.

**D-20 Resource sizing not defined; no limits.** No `mem_limit`/`cpus` anywhere, `php.ini`/FPM pool/`opcache` not configured, no swap. On a small droplet an unconstrained `composer install` plus `pnpm build` at start (D-02) can OOM-kill Postgres. See sizing in section 4.

**D-21 Frontend API URL is baked at build time but injected at runtime.** (`docker-compose.prod.yaml:121-125`.)
`VITE_API_BASE_URL` is inlined by Vite at `pnpm build`; the prod compose sets it as a container env and builds at boot, which works only because build happens on start. In the target design, build once in CI with the production URL (same artifact for every host), or serve the SPA and API from the **same origin** (`/api` path via the proxy) so the build is host-independent, there is no CORS, and cookies/CSRF remain an option for FSEC-03. Recommended: same origin, `VITE_API_BASE_URL=/`.

### Low

**D-22 Hygiene.** `backend/.env` exists in the working tree (gitignored, good) but `backend/` has no `.dockerignore`: once a `COPY . .` is added, `.env`, `vendor/`, `storage/logs`, `.phpunit.result.cache` and tests would be baked into the image (the root-level-looking `.dockerignore` lives under `frontend/`). `docs/DEPLOY.md` says Laravel 11 (it is 13), omits worker/scheduler supervision, Spaces, backups. `node:24-bullseye` is an old Debian base; use `node:22-alpine`/`-slim` to match CI. `composer.lock` and `pnpm-lock.yaml` are modified in the working tree: image builds must use the committed lock files. Dev compose `ports` bind `0.0.0.0` (use `127.0.0.1:` on shared networks). Prod compose runs `config:cache`/`route:cache` only in the backend, not in worker/scheduler (they boot slower and read `.env` differently; with baked config this inconsistency vanishes). `view:cache` and `storage:link` in DEPLOY.md are unneeded for an API-only service.

### Things that are already right

- `BelongsToSchool` fail-closed scope, signed 5-minute document links, append-only audit trigger, named rate limiters, and the `TRUSTED_PROXIES` parser with a safe default.
- Separate `outreach` mailer and suppression list; per-school time zone in ops reminders; `withoutOverlapping` on scheduler entries.
- Redis AOF enabled in prod compose; healthchecks on Postgres and Redis.
- `queue:work --max-time=3600` limits memory growth.
- CI runs PostGIS and has dependency audits.

---

## 4. Recommended pilot architecture

### 4.1 Decision record: topology

**Decision: one DigitalOcean droplet (BLR1, Bangalore, nearest to Pune) running Docker Compose with a Caddy container as the only public listener; Cloudflare (free plan) in front in proxied mode; DigitalOcean managed PostgreSQL (smallest single-node plan, with PostGIS enabled by the admin user) for the database; DigitalOcean Spaces for documents and backups; Uptime Kuma on a separate host; images built in GitHub Actions, pushed to GHCR, pulled by tag on the droplet.**

```
                 Users / Google / WhatsApp links
                              |
                     +--------v---------+
                     |   Cloudflare     |  DNS, proxy, free DDoS/WAF basics,
                     |  (orange cloud,  |  static cache for /assets, TLS to origin
                     |  Full strict)    |  (Origin CA cert or Caddy ACME via DNS-01)
                     +--------+---------+
                              | 443 only, DO Cloud Firewall allows
                              | Cloudflare IP ranges only (+ SSH from admin IP)
   +--------------------------v-----------------------------------------+
   | DigitalOcean droplet, BLR1, 2 vCPU / 4 GB, Ubuntu LTS, swap 2 GB   |
   |                                                                    |
   |  +----------------+   unix/tcp   +------------------------------+  |
   |  | caddy (TLS,    +------------->| app: nginx? no. php-fpm      |  |
   |  |  headers, gzip,|  /api/*      | (or FrankenPHP) as non-root  |  |
   |  |  static SPA,   |              | opcache, config/route cached |  |
   |  |  /assets cache)|              +--------------+---------------+  |
   |  |  serves dist/  |                             |                  |
   |  +----------------+     +-------------------+   |   +------------+ |
   |                         | worker (queue:work|   |   | scheduler  | |
   |                         |  same image,      |   |   | (singleton)| |
   |                         |  supervised)      |   |   +------+-----+ |
   |                         +---------+---------+   |          |       |
   |                                   |             |          |       |
   |                            +------v-------------v----------v-----+ |
   |                            |  redis 7 (requirepass, AOF,         | |
   |                            |  noeviction, internal network only) | |
   |                            +-------------------------------------+ |
   +---------+------------------------------------+---------------------+
             | TLS, trusted-sources = droplet      | S3 API (HTTPS)
             v                                     v
   +--------------------+              +------------------------------+
   | DO Managed Postgres|              | DO Spaces                    |
   | 16 + PostGIS, 1 vCPU|             |  - documents (private, SSE,  |
   | 1 GB, daily backup |              |    versioned)                |
   | + PITR (7 days)    |              |  - backups (pg_dump copy,    |
   | private network    |              |    env bundle, lifecycle 30d)|
   +--------------------+              +------------------------------+

   Outside the droplet:  Uptime Kuma (separate small host / hosted monitor)
                         Sentry (hosted free/dev tier) for exceptions
                         Transactional mail provider (SPF/DKIM/DMARC) + outreach mailbox on hello.driveq.in
                         GitHub Actions -> GHCR (immutable images tagged by git SHA)
```

Why Caddy: automatic certificates with no cron, a 10-line config for TLS, security headers, static serving with `try_files` for SPA fallback, HTTP/2 and gzip/zstd, and a reverse proxy to PHP-FPM. Nginx is equivalent and fine if the team already knows it; choose one, not both. With Cloudflare proxied, use Cloudflare "Full (strict)" and either a Cloudflare Origin CA certificate mounted into Caddy (no renewal until 15 years) or Caddy ACME with the DNS-01 challenge; the plain HTTP-01 challenge breaks when only Cloudflare IPs are allowed through the firewall.

### 4.2 Trusted proxies (wiring check)

`TRUSTED_PROXIES` is parsed correctly in `bootstrap/app.php:34`, but **it is not set anywhere in `docker-compose.prod.yaml`** (finding D-04), so it would resolve to empty (trust nobody). Behind Cloudflare then Caddy then PHP-FPM there are two hops. Recommended wiring:

- Caddy `servers { trusted_proxies cloudflare_ranges }` (or a static list), and `header_up X-Forwarded-For {client_ip}` so Caddy **replaces** rather than appends the header with the Cloudflare-validated client IP.
- Laravel `TRUSTED_PROXIES=<docker network CIDR>` (the Caddy container only, for example `172.20.0.0/16` with a fixed compose network subnet). Do not use `*` unless the firewall guarantees only Caddy can reach PHP-FPM (it can, if FPM is not published; but a fixed CIDR is cleaner).
- Verification step in the checklist: `curl -H 'X-Forwarded-For: 1.2.3.4' https://api.../api/debug-ip` equivalent (use an admin-only or test route, or check `audit_logs.ip_address` after a login) must show the real client, not the spoofed value and not the proxy.

### 4.3 Images (DIQ-1301)

- **Backend:** one image for `app`, `worker`, `scheduler` (different `command:`), multi-stage: `composer:2` stage with `--no-dev --optimize-autoloader --classmap-authoritative`, final `php:8.3-fpm-alpine` (or FrankenPHP) with extensions `pdo_pgsql redis bcmath intl opcache pcntl zip`, non-root user, `COPY --chown`, `opcache.validate_timestamps=0`, `.dockerignore` for `.env`, `vendor`, `tests`, `storage/logs`. `config:cache`, `route:cache`, `event:cache` at **image build** is not safe (env is runtime); run them in the entrypoint after env is injected, once per container.
- **Frontend:** multi-stage `node:22-alpine` build then `FROM caddy:2-alpine` (or copy `dist` into the Caddy image), so the proxy and SPA are one artifact with the headers in its `Caddyfile`. Pin base images by digest.
- Tag by git SHA, never rely on `latest`; keep the last 3 tags on the host for rollback.
- Add a `/healthz` for FPM or use `php-fpm-healthcheck`; compose `healthcheck:` for app, worker (`pgrep -f queue:work`), scheduler (heartbeat file touched each minute via a scheduled closure).

### 4.4 Compose layout on the droplet

One `docker-compose.prod.yaml` (to be rewritten by infra) with: `caddy`, `app`, `worker`, `scheduler`, `redis`; an external `.env` file outside the repo (`/opt/driveiq/.env`, mode 600, root-owned) loaded by all three PHP services through a shared anchor; a one-shot `migrate` service (`profiles: ["deploy"]`) run explicitly; resource limits (`mem_limit`) so a runaway worker cannot starve Caddy; `logging: json-file` with `max-size: 10m`, `max-file: 5`; fixed network subnet (for `TRUSTED_PROXIES`); Postgres removed from compose when using managed (keep a `postgres` profile for local/staging).

### 4.5 Sizing (pilot)

| Component | Size | Notes |
|---|---|---|
| Droplet | 2 vCPU / 4 GB (about USD 24; a 2 GB / 1 vCPU at about USD 12 works for the pilot if Postgres is managed, but leaves little room for `composer`/builds, and builds happen in CI anyway) | Choose 4 GB: PHP-FPM 4 to 6 workers x ~80 MB, worker 1 to 2 x ~120 MB, Redis ~100 MB, Caddy ~50 MB, OS ~400 MB |
| Swap | 2 GB | Safety net, not capacity |
| FPM | `pm=dynamic`, `max_children=6`, `memory_limit=256M`, `upload_max_filesize` and `post_max_size` just over the 5 MB document cap (for example 8M), `max_execution_time=30` | Matches 5 to 10 req/s peak with short queries |
| Managed Postgres | 1 vCPU / 1 GB (about USD 15) | Dataset is tiny; make sure the GiST index on `schools.location` and the ranking SQL are `EXPLAIN`-checked with Pune-sized data (about 50 to 500 rows is trivial) |
| Redis | in-container, `maxmemory 256mb`, `noeviction` | Queue and locks must never be evicted; cache keys carry TTLs |

### 4.6 Deploy, migration and rollback procedure (DIQ-1308)

**Deploy (single script, run by GitHub Actions over SSH or manually):**

1. CI on `main` green, then `docker build` and push `ghcr.io/<org>/driveiq-app:<sha>` and `driveiq-web:<sha>`.
2. On droplet: `docker compose pull`.
3. Take a safety snapshot: managed Postgres on-demand backup or `pg_dump -Fc` to Spaces (skip only for migration-free releases).
4. `docker compose run --rm migrate` (`php artisan migrate --force --isolated`). Fails the deploy before any traffic switches.
5. `docker compose up -d app web worker scheduler` (recreate), then `artisan queue:restart`, `artisan cache:clear`? (no: do not clear Redis, it holds queues and locks; use targeted `config:cache`).
6. Smoke: `GET /api/healthz` and the deep health endpoint, load the SPA, one login, one search.
7. If smoke fails within 10 minutes, roll back the app.

**Expand/contract rule (what makes rollback safe):** a migration may only *add* (nullable columns, new tables, new indexes `CONCURRENTLY`, new enum values) in release N; the code that stops using a column ships in N+1; the `DROP` ships in N+2. Never rename or drop in the same release as the code change. Do not edit shipped migrations (project rule). Index builds on large tables use `CREATE INDEX CONCURRENTLY` (a migration with `$withinTransaction = false`). The audit-log trigger migration style is fine because the table is append-only.

**Rollback plan:**

| Situation | Action | Time |
|---|---|---|
| Bad app release, no migration or expand-only migration | Re-pin the previous SHA tag in the env file, `docker compose up -d app web worker scheduler`, `queue:restart`. Old code runs fine on an expanded schema | ~2 min |
| Release contained a destructive or breaking migration (should be prevented by the rule above) | Forward-fix preferred. Else stop the app, restore the pre-deploy snapshot to a new Postgres cluster / PITR to the timestamp before the migrate, repoint `DB_HOST`, start the previous image. Data written since is lost: note the number of inquiries/consents lost | 30 to 90 min |
| Droplet lost | New droplet from the same cloud-init/compose bundle, pull images, restore env file from the secret store, DB is unaffected if managed | <= 1 h (RTO target 4 h) |
| Cloudflare or DNS problem | Grey-cloud the record to go direct to origin (only works if the firewall rule is relaxed ahead of time, so document it) | 5 min |

Never run `migrate:rollback`, `migrate:fresh`, `db:seed` or `driveiq:demo` (it already refuses production) against production. Make `make fresh`/`migrate-fresh` refuse when `APP_ENV=production` (backend/infra follow-up). Production seed is DIQ-1309: localities, plan matrix, admin bootstrap via `driveiq:create-admin`; no demo users, no `password123`.

### 4.7 Secrets management

Pilot-appropriate: a root-owned `/opt/driveiq/.env` (600) not in git, a sealed copy in a password manager (the owner's) and an encrypted copy in a private Spaces object (`age`/`gpg`) for disaster recovery. GitHub Actions holds only deploy SSH key, GHCR token and the (non-secret) `VITE_*` public values; production secrets are not stored in GitHub. Rotate on: team change, suspected leak, and before launch (the `password123` seeds must not exist in prod). Keys that exist today and need values: `APP_KEY` (generate once, never rotate casually: it invalidates signed URLs and encrypted data), `DB_PASSWORD`, `REDIS_PASSWORD`, `AWS_*` (Spaces key restricted to one bucket if the plan allows, else a dedicated account), `MAIL_*`, `OUTREACH_MAIL_*`, `GOOGLE_*`, `GOOGLE_ADS_WEBHOOK_KEY` (enforce length per BSEC-22), `BILLING_*`, `SENTRY_DSN`, and `VITE_GOOGLE_MAPS_API_KEY` (public by design; restrict by HTTP referrer and API in Google Cloud, FSEC-14).

### 4.8 CDN and caching

Cloudflare proxied for the apex and `api` hostnames (or same-origin `/api`), "Full (strict)", "Always use HTTPS", minimum TLS 1.2, HSTS set at Caddy. Cache Rules: `/assets/*` Edge TTL 1 year; HTML bypass; `/api/*` bypass by default, then opt-in rule for `GET /api/schools*`, `/api/localities*` with 60 s edge TTL once measured. Cloudflare rate-limit/WAF rules on `/api/auth/*` and `/api/inquiries` as a second layer behind Laravel's limiters (BSEC-10/11). Turn on "Bot Fight Mode" cautiously: webhooks (Google Ads lead webhook, Google OAuth callback) must be exempt via a WAF skip rule on those paths.

### 4.9 Realtime (Reverb): not at pilot

Keep disabled. When DIQ-1404 is scheduled (after the channel fix DIQ-1901): run Reverb as a seventh compose service on the same droplet, on a `ws.` subdomain or `/app` path proxied by Caddy (`reverse_proxy` handles WebSocket upgrade automatically; Cloudflare supports WebSockets on the free plan, 100 s idle timeout, so the Pusher protocol ping at default 60 s is OK), `REVERB_SCALING_ENABLED=false` (single node, ~100s of connections), raise `ulimit -n`, add the `wss://` host to CSP `connect-src` and set `VITE_REVERB_*` at SPA build. Auth already goes over `POST /api/broadcasting/auth` with Sanctum bearer.

### 4.10 Trade-offs vs alternatives

| Option | Pros | Cons | Verdict |
|---|---|---|---|
| **A. 1 droplet + compose + managed Postgres (recommended)** | Cheapest credible setup (~USD 55 to 65/month all-in), simple mental model, only DB is outsourced, easy cold rebuild | Single point of failure for app tier; deploy blip of several seconds; you patch the OS | Best fit for 99.5% and a one-person team |
| B. 1 droplet, everything incl. Postgres in a container | Cheapest (~USD 40 to 45) | The one irreplaceable component is on the box you also restart and redeploy; you own backups, PITR, upgrades, PostGIS patching; weakest on the "tested restore" requirement | Acceptable only if the owner accepts 24 h RPO and will run the restore drill monthly; fallback if budget binds |
| C. 2 droplets (app and data separated) | Isolates DB from app resource spikes and deploys | Double the OS patching, still self-managed DB, still no PITR; costs almost the same as managed Postgres | Dominated by A |
| D. Managed PaaS (DO App Platform, Render, Railway, Fly.io) | No OS or TLS management, built-in deploy and rollback, zero-downtime deploys | Worker/scheduler/Reverb model per provider; PostGIS support and Indian region vary; per-component pricing (web + worker + scheduler + Redis + DB) often exceeds A; cold starts on cheap tiers; less control over WebSocket and cron singleton semantics | Reasonable if the team prefers to pay for ops; revisit if droplet toil becomes real |
| E. Kubernetes (DOKS) | Rolling deploys, HPA, standard ops | Control-plane + node cost (about USD 50+ before workloads), large complexity budget for 50 schools | Not justified; revisit only past ~10x and with a platform engineer |
| F. Serverless / Laravel Vapor / Cloud Run | Scales to zero | Queue worker, scheduler, PostGIS, Spaces, Indian-region constraints; cold starts on the search hot path | No |
| G. Static SPA on Cloudflare Pages/Workers, API on droplet | Free global SPA hosting, instant rollback of frontend, no static serving on the droplet | Two deploy targets, cross-origin API (CORS, no same-origin cookie option), headers configured in Cloudflare `_headers` | A good optimisation later; for the pilot keep the SPA with Caddy for one artifact and one place for headers |

### 4.11 Growth path (what to revisit at about 10x: 500 schools, tens of thousands of monthly visitors, ~50 req/s peak)

1. **Split app tier:** move to 2 app droplets behind a DO Load Balancer (about USD 12/month) or Cloudflare Load Balancing; make the scheduler explicitly singleton (`onOneServer()` on all entries, or run it only on one node); sessions already in Redis.
2. **Redis:** managed Redis/Valkey, or separate Redis for cache vs queue (queue: `noeviction`, cache: `allkeys-lru`).
3. **Postgres:** bigger managed plan, a read replica for the search hot path, PgBouncer, `pg_stat_statements` and slow-query alerting, verify the ranking SQL plan with realistic data.
4. **Search caching:** edge caching for anonymous `/api/schools` with rounded coordinates; consider Redis cache of ranked results per locality.
5. **Reverb** on its own node with Redis pub/sub scaling, or switch to a hosted Pusher/Ably plan.
6. **Public SEO:** prerender or SSR for locality pages; Cloudflare Pages for the SPA.
7. **Observability:** structured logs to a log service, metrics (Prometheus/Grafana or hosted), APM.
8. **Storage:** CDN in front of the public photos bucket; image resizing pipeline.
9. **CI/CD:** staging environment with anonymised data; blue/green via two compose projects behind Caddy.
10. **Compliance:** DPDP breach-notification runbook, access logging for document views (BSEC-15), periodic restore drills, secrets in a manager (Doppler/Vault).

---

## 5. Action list

Ordered by dependency. Effort: S (under half a day), M (1 to 2 days), L (3+ days). Ticket ids are from `docs/ROADMAP.md` section 4; new ids are proposals (marked NEW) for items the roadmap omits.

| # | Action | Ticket | Effort | Owner |
|---|---|---|---|---|
| 1 | Production images: multi-stage, code baked in, non-root, PHP-FPM, `redis`/`bcmath`/`opcache`/`pcntl`/`intl`, backend `.dockerignore`, SPA built at image build time; Node 22 to match CI | DIQ-1301 | M | infra |
| 2 | Backend deps needed for production: `league/flysystem-aws-s3-v3`, Redis client decision (phpredis in image), Sentry SDK; add a CI job that builds the real image and boots with Redis cache/queue so D-05 cannot regress | DIQ-1305 / DIQ-1311 / NEW DIQ-1314 (image smoke job) | M | backend + infra |
| 3 | Caddy: TLS (Cloudflare Origin cert or DNS-01), HTTP to HTTPS, only 80/443 published, DO Cloud Firewall limited to Cloudflare ranges, SSH key-only from admin IP, fixed Docker subnet | DIQ-1302 | M | infra |
| 4 | Single shared env file for all PHP services, `.env.production.example`, `${VAR:?}` guards, set `APP_KEY`, `APP_URL`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`, `TRUSTED_PROXIES`, mail, storage; verify real client IP end to end; boot guard refusing `APP_DEBUG=true` or `MESSAGING_DRIVER=log` in production | DIQ-1303 (+ BSEC-16) | S (env) + S (guard, backend) | infra + backend |
| 5 | Security headers and CSP at Caddy for the SPA (Report-Only first, then enforce after checking Maps, fonts, Spaces and, later, `wss` hosts); keep API headers in middleware | DIQ-1304 (re-owner: infra with frontend) | M | infra + frontend |
| 6 | Object storage: Spaces bucket (private, SSE, versioning), `DOCUMENTS_DISK=s3`, fix AWS region/endpoint values, bucket CORS if needed, restart-survival test, audit-log document views | DIQ-1305 (+ BSEC-15) | M | backend + infra |
| 7 | Managed Postgres: create cluster in BLR1, enable PostGIS as admin, create app role, trusted-sources = droplet, run migrate once; point `DB_*` and `sslmode=require`; make `make fresh`/`migrate-fresh`/seed targets refuse production | NEW DIQ-1315 (DB provisioning) | S | infra |
| 8 | Backups and restore drill: PITR or nightly `pg_dump -Fc` to Spaces with 30-day lifecycle, bucket versioning, encrypted copy of env bundle, written restore runbook, restore into scratch DB and run a sanity query set (schools count, PostGIS search, audit trigger present) | DIQ-1306 | M | infra |
| 9 | Deep health endpoint (DB, Redis, queue depth/age, scheduler heartbeat, documents disk, migration state), protected by secret header; keep `/api/healthz` shallow; add compose healthchecks | DIQ-1307 | S | backend (infra wires probes) |
| 10 | Deploy job: build and push to GHCR, SSH deploy script (pull, snapshot, one-shot `migrate --isolated`, recreate, `queue:restart`, smoke), previous-tag rollback exercised once; document expand/contract rule in DEPLOY.md (and remove `migrate:rollback` advice) | DIQ-1308 | L | infra |
| 11 | Queue and scheduler supervision: worker flags (`--timeout`, `--backoff`, `--max-jobs`, `--memory`), `retry_after` > timeout, `queue:monitor` + failed-job count alert, scheduler heartbeat, add `onOneServer()` to schedule entries as insurance, `schedule` timezone (`Asia/Kolkata`) where local time matters | DIQ-1310 (+ NEW DIQ-1316 schedule timezone, backend S) | M | infra + backend |
| 12 | Monitoring: Sentry with release tagging, Uptime Kuma (or hosted) on a **separate** host on SPA, `/api/healthz`, deep health, TLS expiry, plus disk/memory alert (DO monitoring), backup-freshness check, alert to WhatsApp/email/phone of the owner | DIQ-1311 | M | infra |
| 13 | Logging: `LOG_CHANNEL=stderr`, `LOG_LEVEL=warning`, Docker log rotation, retention policy documented for DPDP | NEW DIQ-1317 | S | infra + backend |
| 14 | Mail: transactional provider with SPF/DKIM/DMARC on the sending domain; outreach mailbox on `hello.driveq.in` with its own SPF/DKIM/DMARC; send a test of every transactional template to Gmail/Outlook/Yahoo; verify outbound ports and `List-Unsubscribe` over HTTPS | NEW DIQ-1318 | M | infra (owner supplies domain) |
| 15 | Production seed and admin bootstrap, never demo | DIQ-1309 | M | backend |
| 16 | Cloudflare: proxied DNS, Full (strict), cache rules for `/assets`, WAF skip rules for webhooks/OAuth callback, rate-limit rule on `/api/auth/*`; origin firewall allowing Cloudflare only | NEW DIQ-1319 | S | infra |
| 17 | Update CLAUDE.md, `docs/SYSTEM-FLOW.md`, `docs/DEPLOY.md` (Laravel 13, new topology, runbooks) | part of DIQ-1313 | S | architect + infra |
| 18 | Launch readiness gate: restore performed, rollback exercised, TLS renewal forced, CSP checked on live app, trusted-proxy IP verified, failed-job alert fired by a test job | DIQ-1313 | M | QA + infra |
| 19 | "Near me" entry point (not infra) | DIQ-1312 | S | frontend |

Deferred, not pilot: Reverb (DIQ-1404/1405, after DIQ-1901), real WhatsApp provider (DIQ-1401), Razorpay (DIQ-1501), staging environment, blue/green.

### 5.1 Critical path

D-01/D-05 (images + deps) then Caddy/TLS then env wiring then Postgres + Spaces then deep health then deploy job + rollback then monitoring and backups drill then gate. Items 6, 7, 8, 12, 14 can run in parallel once the images exist. Realistic effort: about 14 ideal days for M11 as in ROADMAP, plus about 3 to 4 days for the NEW items above (managed DB, image smoke job, logging, mail, Cloudflare). DIQ-1304 ownership should move from "Backend" to infra.

### 5.2 Pre-launch deployment checklist

**Infrastructure**
- [ ] Droplet in BLR1, OS patched, unattended-upgrades on, SSH key-only, root login off, fail2ban or equivalent, swap added, DO droplet backups/snapshot enabled
- [ ] DO Cloud Firewall: 80/443 from Cloudflare ranges only; 22 from admin IP only; nothing else (no 5432, 6379, 8000, 5173, 8080)
- [ ] Cloudflare proxied, SSL Full (strict), HSTS, min TLS 1.2, Always HTTPS; origin cert installed; **forced certificate renewal / expiry alert tested**
- [ ] Managed Postgres: PostGIS enabled, trusted source = droplet, `sslmode=require`, PITR on, migration run from the app role works
- [ ] Spaces: private documents bucket with versioning and encryption; separate backups bucket with lifecycle; restricted access key; upload survives a container recreate; signed 5-minute link works and expires
- [ ] Redis: `requirepass`, `noeviction`, AOF on, not published

**Application**
- [ ] Images built from `main` in CI by SHA, non-root, no dev dependencies, no `.env` baked in
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` set and backed up, `APP_URL` is the public https origin
- [ ] `TRUSTED_PROXIES` set; real client IP verified in `audit_logs.ip_address` and a rate-limit test shows per-client behaviour
- [ ] `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` = redis; Redis client present in the image; a test job runs end to end
- [ ] `MAIL_MAILER` real, `MESSAGING_DRIVER` decision recorded (log mode means no WhatsApp/SMS: owner accepted), `OUTREACH_MAILER=outreach` only after SPF/DKIM/DMARC pass
- [ ] `PLANS_ENFORCE=true`, `BILLING_*` filled (GSTIN, UPI/bank), `RETENTION_EXECUTE` decision recorded
- [ ] `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`, CORS origins match the public domain only
- [ ] Production seed ran (localities, plans, admin); no demo accounts; `password123` absent; admin password strong
- [ ] Google Maps key restricted by referrer and API; Google OAuth redirect URI registered with the production `APP_URL`
- [ ] Worker and scheduler running, supervised, healthchecks green; scheduler heartbeat visible; a deliberately failed job raises an alert
- [ ] Deep health green; Uptime monitor on a separate host alerting to the owner's phone
- [ ] Sentry receiving a test exception tagged with the release SHA
- [ ] CSP enforced (or Report-Only with zero violations on search, school detail, dashboard, admin, document download); security headers verified on SPA and API
- [ ] Cache headers: hashed assets immutable, `index.html` no-cache; `/api` authenticated responses `no-store`
- [ ] Log rotation configured; no PII in `LOG_LEVEL=warning` logs; log retention documented

**Data protection**
- [ ] Backup ran automatically at least 3 nights; **restore into a scratch DB performed and verified** (counts, geo query, audit trigger blocks DELETE)
- [ ] Env bundle backup restorable on a fresh droplet (cold rebuild rehearsal done once, time recorded against RTO)
- [ ] DPDP: privacy notice and consent text live, data-subject request inbox monitored, breach-response contact defined

**Release**
- [ ] Deploy script tested end to end; **rollback to the previous SHA exercised once on production-like infra**
- [ ] Post-deploy smoke list from DEPLOY.md updated (healthz and deep health, SPA load, search near Baner, inquiry submission, owner login, document upload and download, queue email received)
- [ ] Load sanity: 20 concurrent users on `/api/schools` for 2 minutes stays under 500 ms p95 and does not saturate FPM workers
- [ ] Go/no-go recorded under DIQ-1313

### 5.3 Monthly cost estimate (approximate, unverified; confirm in USD and add GST/FX)

| Item | Approx USD / month |
|---|---|
| Droplet 2 vCPU / 4 GB, BLR1 | 24 |
| Droplet backups (weekly snapshots, about 20% of droplet) | 5 |
| Managed Postgres, 1 vCPU / 1 GB, single node (PITR 7 days included) | 15 |
| Spaces (includes a base allowance of storage and egress) | 5 |
| Cloudflare free plan | 0 |
| Uptime Kuma on the smallest droplet (or hosted free monitor, which is 0) | 0 to 6 |
| Sentry (free/developer tier) | 0 |
| Transactional email (free tier of a provider, a few thousand mails) | 0 to 10 |
| Outreach mailbox (Google Workspace one seat) | 6 to 8 |
| Domain (annual, amortised) | 1 to 2 |
| GitHub Actions + GHCR (within free/private allowances for this volume) | 0 |
| **Total** | **about 55 to 75** (about 40 to 50 if Postgres stays in the container, per option B) |

Costs excluded: a WhatsApp/SMS provider (per-message, DLT registration in India), Razorpay fees, Google Maps usage beyond the free credit, Google Ads. A cheaper floor (about USD 25 to 30) exists with a 2 GB droplet and container Postgres but it is not recommended for data you cannot recreate.

---

## 6. Decisions needed from the owner

1. **Database placement:** managed Postgres (recommended, about USD 15/month) or container with nightly dumps (cheaper, RPO 24 h, you own restore)?
2. **Region and residency:** is DigitalOcean BLR1 acceptable, and does Spaces offer a bucket in a suitable region for ID documents (confirm current Spaces regions; fall back to SGP1 if not)? Any contractual or DPDP preference for India-only storage?
3. **Domain plan:** final domain, API same-origin (`/api`, recommended) or `api.` subdomain; which subdomain is used for transactional versus outreach mail (`hello.driveq.in` is the current example); who owns DNS/Cloudflare account.
4. **Messaging at launch:** accept email-only alerts (MESSAGING_DRIVER stays `log`, nothing sent over WhatsApp/SMS) for the pilot, or make a provider (DIQ-1401) a launch blocker? This changes the plan, since log mode also leaves PII in logs.
5. **Alerting channel and on-call:** who gets paged (phone/WhatsApp/email) and what hours; accept 99.5% with best-effort overnight response?
6. **Realtime:** confirm Reverb stays off for the pilot (polling), conditional on fixing the channel leak (DIQ-1901) first.
7. **Deploy trigger:** manual `workflow_dispatch` approval to production (recommended) or auto-deploy on merge to `main`; whether a staging environment is worth about USD 20/month before launch.
8. **Retention execution:** keep `RETENTION_EXECUTE=false` at launch (report only), or enable deletion with the legal-hold list defined?
9. **Cloud account hygiene:** who holds root credentials for DigitalOcean, Cloudflare, GitHub; recovery path if the owner is unavailable (a second admin, 2FA backups).
