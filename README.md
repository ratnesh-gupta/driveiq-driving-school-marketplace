# DriveIQ — Driving School Marketplace

Multi-tenant marketplace and school operations platform for driving schools in India.

Learners discover and compare schools; school owners manage leads, packages, instructors, schedules, payments, and analytics; instructors and learners use dedicated portals.

## Stack

| Layer | Tech |
|-------|------|
| Backend API | Laravel 11, PHP 8.3, Sanctum |
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
| `make test` | Backend PHPUnit inside container |
| `make test-filter FILTER=PaymentTest` | Filtered PHPUnit run |
| `make frontend-check` | Host-side `pnpm typecheck` + `pnpm build` |

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

| Role | Path | Focus |
|------|------|--------|
| Platform admin | `/admin` | Schools, reviews, platform analytics |
| School owner/manager | `/dashboard` | Leads, learners, instructors, schedules, payments, packages |
| Instructor | `/instructor` | Sessions, attendance, messages |
| Learner | `/learner` | Progress, sessions, messages |
| Public | `/`, `/search` | Discover & compare schools |

## Documentation

- [docs/DEPLOY.md](docs/DEPLOY.md) — production checklist, env, CI
- `docs/` — product plans, architecture, phase notes

## License

Private / proprietary unless otherwise stated by the repository owner.
