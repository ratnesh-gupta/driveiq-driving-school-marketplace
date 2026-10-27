#!/usr/bin/env bash
# Builds and (re)starts the DriveQ stack, then waits for the health check
# (DIQ-1303). Run on the server from the checked-out commit; the GitHub
# "Deploy" workflow calls it over SSH. Migrations run when the app
# container starts.
set -euo pipefail
source "$(dirname "$0")/lib.sh"
cd "$DRIVEQ_DIR"

[ -f "$ENV_FILE" ] || { echo "Missing $ENV_FILE (copy deploy/.env.production.example)"; exit 1; }

log "Building images for $(git rev-parse --short HEAD)"
"${COMPOSE[@]}" build

log "Starting containers"
"${COMPOSE[@]}" up -d --remove-orphans

app_url="$(env_value APP_URL)"
host="${app_url#*://}"; host="${host%%/*}"
log "Waiting for https://$host/api/healthz"
for _ in $(seq 1 60); do
  if curl -fsS --max-time 5 --resolve "$host:443:127.0.0.1" "https://$host/api/healthz" >/dev/null 2>&1; then
    log "Healthy. Deployed $(git rev-parse --short HEAD)."
    docker image prune -f >/dev/null
    exit 0
  fi
  sleep 5
done

log "Health check failed. Recent app logs:"
"${COMPOSE[@]}" logs --tail=80 app
exit 1
