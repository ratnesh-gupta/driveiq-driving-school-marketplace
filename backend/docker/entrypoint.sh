#!/bin/sh
# Container entrypoint for the DriveQ API image (DIQ-1301).
# Caches config/routes/views from the runtime environment, optionally runs
# migrations (only the "app" service sets RUN_MIGRATIONS=1), then runs the
# given command: php-fpm, queue:work or schedule:work.
set -e

export FPM_MAX_CHILDREN="${FPM_MAX_CHILDREN:-12}"

if [ -z "$APP_KEY" ]; then
  echo "APP_KEY is not set. Generate one with: docker compose run --rm app php artisan key:generate --show" >&2
  exit 1
fi

php artisan config:cache --no-interaction >/dev/null
php artisan route:cache --no-interaction >/dev/null
php artisan view:cache --no-interaction >/dev/null
php artisan event:cache --no-interaction >/dev/null

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
  php artisan migrate --force --no-interaction
fi

exec "$@"
