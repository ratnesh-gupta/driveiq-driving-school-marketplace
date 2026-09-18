# DriveIQ — Production deploy checklist

## Stack

| Layer | Tech |
|-------|------|
| API | Laravel 11, PHP 8.3, Sanctum |
| DB | PostgreSQL 16 + PostGIS |
| Cache / queue | Redis 7 |
| Realtime (optional) | Laravel Reverb |
| Frontend | React + Vite + pnpm |
| Payments | Manual/cash MVP; Razorpay keys optional |

## 1. Prerequisites

- PHP 8.3+, Composer 2, Node 22+, pnpm
- Postgres with PostGIS extension enabled
- Redis
- TLS termination (Caddy / Nginx / cloud LB)

## 2. Backend

```bash
cd backend
cp .env.example .env
# Edit production values — see section 4
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

Run the HTTP process with a process manager, e.g. **FrankenPHP**, **Octane**, or `php-fpm` + Nginx. For a simple start:

```bash
php artisan serve --host=0.0.0.0 --port=8000   # not for real prod load
```

Queue worker (notifications, future jobs):

```bash
php artisan queue:work redis --sleep=1 --tries=3
```

Optional Reverb:

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

## 3. Frontend

```bash
cd frontend
cp .env.example .env   # set VITE_API_BASE_URL to public API origin
pnpm install --frozen-lockfile
pnpm build
# Serve dist/ via Nginx / CDN / static host
```

## 4. Critical env (backend)

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

DB_CONNECTION=pgsql
DB_HOST=...
DB_DATABASE=driveiq
DB_USERNAME=...
DB_PASSWORD=...

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=...

SANCTUM_STATEFUL_DOMAINS=yourdomain.com,www.yourdomain.com
FRONTEND_URL=https://yourdomain.com

# Optional payments
RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=

# Optional Reverb
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=
REVERB_PORT=443
REVERB_SCHEME=https
```

## 5. CORS / Sanctum

Ensure `config/cors.php` and Sanctum stateful domains include the SPA origin. API tokens (Bearer) work without cookies for mobile/SPA.

## 6. Health checks

- `GET /api/healthz` → `{ status: ok }`
- Postgres / Redis health via Docker or orchestrator probes

## 7. CI

GitHub Actions (`.github/workflows/ci.yml`) runs on every PR/push to `main`:

1. Backend: migrate + PHPUnit against PostGIS + Redis
2. Frontend: `pnpm typecheck` + `pnpm build`

## 8. Seed (staging only)

```bash
php artisan db:seed   # if DatabaseSeeder is configured
```

Do **not** seed production with demo users without rotating passwords.

## 9. Rollback

```bash
php artisan migrate:rollback --step=1
```

Prefer forward-fix migrations in production.

## 10. Post-deploy smoke

1. `GET /api/healthz`
2. Login as school / admin
3. Create package payment (cash + mark paid)
4. Open learner + instructor portals
5. Notifications list loads
