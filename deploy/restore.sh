#!/usr/bin/env bash
# Restores a backup made by backup.sh (DIQ-1303).
#   deploy/restore.sh 2026-11-02T2130Z
# Replaces the current database and documents. The site shows errors while
# it runs; workers are stopped and started again around it.
set -euo pipefail
source "$(dirname "$0")/lib.sh"

stamp="${1:?Usage: deploy/restore.sh <backup timestamp, e.g. 2026-11-02T2130Z>}"
bucket="$(env_value BACKUP_S3_BUCKET)"
prefix="$(env_value BACKUP_S3_PREFIX)"; prefix="${prefix:-driveq}"
db="$(env_value DB_DATABASE)"; db="${db:-driveq}"
user="$(env_value DB_USERNAME)"; user="${user:-driveq}"

read -r -p "Replace the live database and documents with backup $stamp? Type RESTORE: " answer
[ "$answer" = "RESTORE" ] || { echo "Cancelled."; exit 1; }

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
aws s3 cp "s3://$bucket/$prefix/$stamp/db.dump" "$work/db.dump" --only-show-errors
aws s3 cp "s3://$bucket/$prefix/$stamp/storage.tar.gz" "$work/storage.tar.gz" --only-show-errors

log "Stopping worker and scheduler"
"${COMPOSE[@]}" stop worker scheduler

log "Restoring database"
"${COMPOSE[@]}" exec -T postgres pg_restore -U "$user" -d "$db" --clean --if-exists --no-owner < "$work/db.dump"

log "Restoring documents"
gunzip -c "$work/storage.tar.gz" | "${COMPOSE[@]}" cp - app:/var/www/html/storage

"${COMPOSE[@]}" start worker scheduler
log "Restored $stamp"
