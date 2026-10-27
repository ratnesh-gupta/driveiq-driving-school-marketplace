#!/usr/bin/env bash
# Nightly backup (DIQ-1303): a Postgres dump plus the uploaded documents,
# copied to S3. The EC2 instance role grants access to the bucket, so no
# keys live on the server. Old backups expire through an S3 lifecycle rule
# (see docs/DEPLOY.md). Cron: 30 21 * * * (03:00 IST).
set -euo pipefail
source "$(dirname "$0")/lib.sh"

bucket="$(env_value BACKUP_S3_BUCKET)"
prefix="$(env_value BACKUP_S3_PREFIX)"; prefix="${prefix:-driveq}"
db="$(env_value DB_DATABASE)"; db="${db:-driveq}"
user="$(env_value DB_USERNAME)"; user="${user:-driveq}"
[ -n "$bucket" ] || { echo "Set BACKUP_S3_BUCKET in $ENV_FILE"; exit 1; }

stamp="$(date -u +%Y-%m-%dT%H%MZ)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

log "Dumping database $db"
"${COMPOSE[@]}" exec -T postgres pg_dump -U "$user" -d "$db" --format=custom --no-owner > "$work/db.dump"
# A dump that small means something went wrong; don't upload it as "good".
[ "$(stat -c %s "$work/db.dump")" -gt 1024 ] || { echo "Dump looks empty"; exit 1; }

log "Archiving documents"
"${COMPOSE[@]}" cp app:/var/www/html/storage/app - | gzip > "$work/storage.tar.gz"

log "Uploading to s3://$bucket/$prefix/$stamp/"
aws s3 cp "$work/db.dump" "s3://$bucket/$prefix/$stamp/db.dump" --only-show-errors
aws s3 cp "$work/storage.tar.gz" "s3://$bucket/$prefix/$stamp/storage.tar.gz" --only-show-errors
log "Backup $stamp done"
