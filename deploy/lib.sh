# Shared helpers for the deploy scripts (DIQ-1303). Sourced, not run.
# Scripts run from the repository root on the server (default /opt/driveq).

DRIVEQ_DIR="${DRIVEQ_DIR:-/opt/driveq}"
ENV_FILE="$DRIVEQ_DIR/.env.production"
COMPOSE=(docker compose -f "$DRIVEQ_DIR/docker-compose.prod.yaml" --env-file "$ENV_FILE")

# Reads one KEY=value from .env.production without sourcing the whole file
# (values such as SITE_ADDRESS contain spaces and commas).
env_value() {
  grep -E "^$1=" "$ENV_FILE" | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'
}

log() { printf '[%s] %s\n' "$(date -u +%H:%M:%S)" "$*"; }
