# DriveIQ — Driving School Marketplace

Multi-tenant marketplace and school operations platform for driving schools in India.

Learners discover and compare schools; school owners manage leads, packages, instructors, schedules, payments, and analytics; instructors and learners use dedicated portals.

## Stack

| Layer | Tech |
|-------|------|
| Backend API | Laravel 13, PHP 8.3, Sanctum |
| Database | PostgreSQL 16 + PostGIS |
| Cache / queue | Redis 7 |
| Frontend | React 19, TypeScript, Vite, pnpm, shadcn/ui |
| Realtime (optional) | Laravel Reverb + Echo |
| Payments | Cash/manual MVP; Razorpay-ready stubs |

## Repository layout

```
.
├── backend/                 # Laravel API
├── frontend/                # React SPA
├── docs/                    # Plans, deploy checklist
├── docker-compose.yaml      # Local development
├── docker-compose.prod.yaml # Production-oriented compose
└── Makefile                 # Common docker / artisan commands
```

## Prerequisites

- Docker + Docker Compose v2
- (Optional for host-side work) PHP 8.3+, Composer 2, Node 22+, pnpm

## Quick start (Docker)

```bash
# 1. Backend env
cp backend/.env.example backend/.env
# Set DB to Postgres for Docker, e.g.:
#   DB_CONNECTION=pgsql
#   DB_HOST=postgres
#   DB_PORT=5432
#   DB_DATABASE=driveiq
#   DB_USERNAME=driveiq
#   DB_PASSWORD=driveiq
#   REDIS_HOST=redis
#   CACHE_STORE=redis
#   QUEUE_CONNECTION=redis

# 2. Frontend env (optional)
cp frontend/.env.example frontend/.env
# VITE_API_BASE_URL=http://localhost:8000

# 3. Start stack (builds images, runs migrate on backend start)
make up

# 4. Seed demo data (staging / local only)
make seed
```

| Service | URL |
|---------|-----|
| Frontend | http://localhost:5173 |
| API | http://localhost:8000 |
| Health | http://localhost:8000/api/healthz |
| Postgres | localhost:5432 |
| Redis | localhost:6379 |

Generate an app key if missing:

```bash
make artisan CMD="key:generate"
```

## Demo accounts (after `make seed`)

**Local / staging only — rotate or disable in production.**

| Email | Password | Portal |
|-------|----------|--------|
| `admin@driveiq.in` | `password123` | `/admin` |
| `info@skylinedrive.in` | `password123` | `/dashboard` (school) |
| `trainer.skyline@driveiq.in` | `password123` | `/instructor` |
| `learner.asha@driveiq.in` | `password123` | `/learner` |

## Make targets

All database and Artisan commands below run **inside the `backend` container** via Docker Compose (see `docker-compose.yaml`).

### Stack

| Command | Description |
|---------|-------------|
| `make up` | Build and start postgres, redis, backend, frontend |
| `make down` | Stop and remove containers |
| `make restart` | Restart all services |
| `make ps` | Show compose status |
| `make logs` | Follow logs (all services) |
| `make logs-backend` | Backend logs only |
| `make logs-frontend` | Frontend logs only |

### Database & Artisan

| Command | Description |
|---------|-------------|
| `make migrate` | Run migrations (`php artisan migrate --force`) |
| `make migrate-fresh` | Drop all tables and re-migrate (**destroys data**) |
| `make seed` | Run database seeders |
| `make migrate-seed` | Migrate then seed |
| `make fresh` | `migrate:fresh --seed` (**destroys data**) |
| `make artisan CMD="..."` | Run any artisan command, e.g. `make artisan CMD="route:list"` |
| `make tinker` | Interactive tinker shell |
| `make shell` | Bash shell in backend container |

### Tests & quality

| Command | Description |
|---------|-------------|
| `make test` | Backend PHPUnit inside container (creates the `driveiq_test` PostGIS DB if missing) |
| `make test-filter FILTER=PaymentTest` | Filtered PHPUnit run |
| `make frontend-check` | Host-side `pnpm typecheck` + `pnpm build` |

Backend tests run against **PostgreSQL + PostGIS** (not SQLite), in a separate
`driveiq_test` database so `RefreshDatabase` never touches dev data. To run them
on the host instead of the container: `make up-infra`, create the database once
(`docker compose exec postgres createdb -U driveiq driveiq_test`), then
`cd backend && php artisan test --filter=GeoSearchTest`.

### Production compose

| Command | Description |
|---------|-------------|
| `make prod-up` | `docker compose -f docker-compose.prod.yaml up -d --build` |
| `make prod-down` | Tear down production compose stack |
| `make prod-migrate` | Migrate on prod backend service |
| `make prod-logs` | Follow prod compose logs |

Set `POSTGRES_PASSWORD` (and other secrets) before `make prod-up`. See [docs/DEPLOY.md](docs/DEPLOY.md).

## Local development without full Docker app

You can run only infra in Docker and apps on the host:

```bash
make up-infra          # postgres + redis only
# then in backend/
composer install && php artisan serve
# and in frontend/
pnpm install && pnpm dev
```

## Roles & portals

| Role | Path | How the account is created | Focus |
|------|------|----------------------------|-------|
| Platform admin | `/admin` | `make artisan CMD="driveiq:create-admin you@example.com"` (no self-registration) | Schools & verification, reviews, users, data requests, contact messages, analytics |
| School owner | `/dashboard` | Registers as "School" | Everything for their school, incl. inviting/removing managers and deleting packages/instructors |
| School manager | `/dashboard` | Invited by the owner (optional), accepts via email link | Leads, learners, instructors, schedules, vehicles, payments (not team management or deletes above) |
| Instructor | `/instructor` | Login created by their school | Assigned sessions, attendance, progress of assigned learners, own documents |
| Learner | `/learner` | Registers as "Learner"; linked when a school enrols them | Progress, sessions, documents, messages, review their school |
| Public | `/`, `/search` | — | Discover & compare schools, enquire, review via inquiry link |

## Configuration

Key settings beyond the database (see `backend/.env.example` and `frontend/.env.example`):

| Variable | Where | Purpose |
|----------|-------|---------|
| `FRONTEND_URL` | backend | Base URL used in password-reset, invite and review links |
| `SANCTUM_EXPIRATION` | backend | API token lifetime in minutes (default 10080 = 7 days) |
| `MAIL_MAILER`, `MAIL_*` | backend | Defaults to `log`; set a real mailer before launch (reset, invite and review emails) |
| `DOCUMENTS_DISK` | backend | Disk for private learner/instructor/vehicle documents (`local` = `storage/app/private`; use an S3/Spaces disk in production) |
| `RETENTION_EXECUTE` | backend | `true` lets the daily `driveiq:retention` job delete expired data; otherwise it only reports |
| `RETENTION_LEGAL_HOLD_SCHOOLS` | backend | Comma-separated school ids excluded from retention deletes |
| `VITE_API_BASE_URL` | frontend | API origin, with or without `/api` |
| `VITE_GOOGLE_MAPS_API_KEY` | frontend | Enables maps on school pages and the search map view; without it only a link to Google Maps is shown |

Two background processes are required outside tests (both are services in `docker-compose.yaml` and `docker-compose.prod.yaml`):

- **Queue worker** (`php artisan queue:work`): sends new-lead emails, enquiry confirmations and reminders. With `QUEUE_CONNECTION=sync` they are sent inline instead.
- **Scheduler** (`php artisan schedule:work`, or cron running `schedule:run` every minute):
  - unanswered-lead reminders (`driveiq:lead-reminders`), every 15 minutes
  - public reply-time badges (`driveiq:response-badges`), hourly
  - token pruning and the retention report, daily
  - plan lifecycle (`driveiq:subscriptions`): trial/plan ending reminders and expiry, daily
  - operations reminders (`driveiq:ops-reminders`): session reminders 24h and 2h before, learner licence and vehicle paper expiry, missing learner documents; hourly, each sent once

Schools choose who is alerted about new leads, and when to be reminded, at `/dashboard/settings`.

## WhatsApp / SMS

Lead alerts, lead reminders, enquiry confirmations and session reminders can also go out on WhatsApp (M8). Three things must all be true for a message to go out:

1. The school switched **On WhatsApp** on under `/dashboard/settings`.
2. The person opted in themselves:
   - staff, trainers and learners on the "My WhatsApp updates" card;
   - enquirers with the optional checkbox on the enquiry form.

   Each opt-in is recorded as a `whatsapp_updates` consent.
3. The number is a valid mobile number, stored as E.164.

Delivery goes through a provider-neutral driver:

| Env | Meaning |
|-----|---------|
| `MESSAGING_DRIVER` | `log` (default: writes the rendered text to the log), `null` (sends nothing), or a provider driver you register |
| `MESSAGING_SMS_FALLBACK` | Also try SMS when WhatsApp fails (needs DLT template ids) |
| `MSG_TPL_*` / `DLT_TPL_*` | Provider (WhatsApp) and TRAI DLT template ids, one pair per template in `config/messaging.php` |

**To add a provider** (MSG91, Gupshup, Twilio…):
1. Implement `App\Messaging\MessageSender`. `send()` receives the channel, the E.164 number, the template definition with its ids, the positional variables and the rendered text.
2. Register the class under `messaging.drivers` and set `MESSAGING_DRIVER`.
3. Register each template in `config/messaging.php` with WhatsApp Business and on the DLT portal, and put the approved ids in the env vars above.

**Guarantees:**
- Every attempt is logged in `outbound_messages`, with the number masked.
- Admins can see the log under Admin → Messages.
- The log is deleted after 90 days by `driveiq:retention`.
- The same message about the same thing is never sent to the same number twice.

## Plans & billing

| | Basic (free) | Featured Rs 1,999/mo | Premium Rs 4,999/mo | Enterprise Rs 14,999/mo |
|---|---|---|---|---|
| Listing, leads, lead engine, reviews, messages, team, settings | ✅ | ✅ | ✅ | ✅ |
| Learners, learner documents, lead → learner conversion | read-only | ✅ | ✅ | ✅ |
| Instructors, schedules, vehicles, payments, advanced analytics | read-only | read-only | ✅ | ✅ |
| Search visibility | standard | ranking boost, "Featured" card | "Sponsored" top slots (capped), homepage | as Premium |

- Every school gets a 30-day trial of Premium **features** (not paid visibility). When it ends, locked modules become read-only; no data is deleted.
- The owner requests an invoice at `/dashboard/billing` (GST added, optional GSTIN) and pays by UPI or bank transfer. An admin records the payment (UTR) in `/admin/billing`, which activates or extends the plan.
- Admins manage sponsored slot counts and campaign windows (top of search, homepage, one locality) in `/admin/billing`.
- Configuration (`backend/.env`):
  - `PLANS_ENFORCE`: `false` turns gating off.
  - `PLAN_TRIAL_DAYS`.
  - `BILLING_GST_RATE`, `BILLING_DUE_DAYS`.
  - `BILLING_SELLER_NAME`, `BILLING_SELLER_ADDRESS`, `BILLING_GSTIN`, `BILLING_EMAIL`.
  - `BILLING_UPI_ID`, `BILLING_BANK_NAME`, `BILLING_BANK_ACCOUNT_NAME`, `BILLING_BANK_ACCOUNT_NUMBER`, `BILLING_BANK_IFSC`.
- The tier matrix itself lives in `backend/config/plans.php`.

## Documentation

- [docs/DEPLOY.md](docs/DEPLOY.md) — production checklist, env, CI
- `docs/` — product plans, architecture, phase notes

## License

Private / proprietary unless otherwise stated by the repository owner.
