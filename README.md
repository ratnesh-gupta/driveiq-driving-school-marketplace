# DriveQ — Driving School Marketplace

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

For sales demos, `make demo` (`php artisan driveiq:demo --fresh`) wipes the local database and loads a fuller Pune showcase: 12 listings including two independent trainers, a busy Skyline dashboard and an admin outreach pipeline. It refuses to run in production. The same logins work.

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
| School owner | `/dashboard` | Registers as "Driving School", or claims a listing we prepared (`/claim/…`) | Everything for their school, incl. inviting/removing managers and deleting packages/instructors |
| Independent trainer | `/dashboard` | Registers as "Trainer" (or claims a prepared profile) | Their own trainer listing: enquiries, learners, sessions, reviews (no team or other trainers) |
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
  - outreach email (`driveiq:outreach`): every 15 minutes, only within sending hours and the daily cap

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

## School & trainer acquisition

New listings start as **drafts**. A draft goes live by itself once the owner has confirmed their email and added a phone number, locality and map location (`/dashboard` shows the checklist). Admins can hide or restore any listing in `/admin/schools`.

**Prospects** (`/admin/prospects`): schools and independent trainers we want on board.
- Add them by hand or import a CSV (headings are matched by name: `name` is required; `phone`, `email`, `locality`, `latitude`, `longitude`, `place id`, … are optional). The import shows a preview with duplicates (same phone, email or Google place id) before saving.
- "Create listing" prepares a hidden, **unclaimed** listing from the prospect. The owner claims it from a link (outreach email, or "Copy claim link" to send by WhatsApp): they prove the listing's email or phone with a 6-digit code (or sign in with the Google account that manages the business), create their account, and the listing goes live.
- "Do not contact", an unsubscribe or "Not my business" removes the unclaimed listing and adds the email/phone (as SHA-256 hashes) to the outreach suppression list.
- Prospects that never came on board are deleted after 12 months by `driveiq:retention`.

**Outreach email** (`/admin/outreach`): sequences of up to 3 emails per campaign.
- `driveiq:outreach` (every 15 minutes) sends due emails Mon–Sat 10:00–18:00 IST, at most `OUTREACH_DAILY_CAP` a day.
- Each email has one-click unsubscribe headers (RFC 8058) and a footer link with your postal address.
- A sequence stops when the prospect replies (mark them "Replied"), claims, signs up on their own, unsubscribes, bounces or complains.
- Mailbox setup:
  1. Create a mailbox on a subdomain (e.g. `partners@hello.driveq.in` in Google Workspace) so outreach can never hurt password-reset and lead-alert delivery.
  2. Publish SPF (`include:_spf.google.com`), DKIM (Workspace admin → Gmail → Authenticate email) and DMARC (`v=DMARC1; p=none; rua=mailto:…`, tighten later) for that subdomain.
  3. Use an app password (or the Workspace SMTP relay) in `OUTREACH_MAIL_*`, then set `OUTREACH_MAILER=outreach`. Until then emails go to the log and the admin page says so.
  4. Start with a small cap (50–150/day) and watch the bounce and complaint numbers in the campaign cards.
- Under India's DPDP rules, outreach to business contacts should stay relevant, identify DriveQ, and honour opt-outs immediately; all three are built in. Keep prospect notes factual.

**Google Ads lead forms**: in the lead form's *Webhook integration*, use `https://<api-host>/api/webhooks/google-ads/lead` and the key from `GOOGLE_ADS_WEBHOOK_KEY`. Each lead is filed once as a prospect (source "ads"); the person gets an onboarding email with the claim link or the sign-up page. Google's "Send test data" is recorded but not filed.

**Google Business Profile**: create an OAuth web client in Google Cloud with the redirect URI `https://<api-host>/api/google-business/callback`, request access to the Business Profile APIs (the project needs Google's approval), then set `GOOGLE_OAUTH_CLIENT_ID/SECRET`. Owners can then import their Google details on the profile page, and a Google-verified business gets the business-verified check. Without the variables the feature is hidden.

**Landing pages**: `/for-schools` and `/for-trainers`. UTM tags from the first visit are stored with the registration (source `outreach`, `ads` or `organic`). Funnel numbers and supply by locality are at the top of `/admin/prospects`.

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
- The tier matrix itself lives in `backend/config/plans.php`. Independent trainers can buy Basic and Featured only (`plans.listing_types`).

## Documentation

- [docs/DEPLOY.md](docs/DEPLOY.md) — production checklist, env, CI
- `docs/` — product plans, architecture, phase notes
- [marketing/README.md](marketing/README.md) — sales kit: demo videos, screenshots, outreach email copy, pitch decks

## License

Private / proprietary unless otherwise stated by the repository owner.
