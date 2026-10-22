#!/usr/bin/env bash
# One-time setup of a fresh Ubuntu 24.04 server for DriveQ (DIQ-1303).
# Run as root (sudo) once, right after launching the instance:
#   curl -fsSL https://raw.githubusercontent.com/<owner>/<repo>/main/deploy/server-setup.sh | sudo REPO_URL=... bash
# or copy the file over and run it. Safe to run again.
set -euo pipefail

REPO_URL="${REPO_URL:-https://github.com/ratnesh-gupta/driveiq-driving-school-marketplace.git}"
DEPLOY_USER="${DEPLOY_USER:-ubuntu}"
DRIVEQ_DIR="${DRIVEQ_DIR:-/opt/driveq}"

echo "== Swap (2 GB): keeps image builds from running out of memory"
if ! swapon --show | grep -q /swapfile; then
  fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
  echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo "== Packages: Docker, AWS CLI, firewall"
apt-get update -q
apt-get install -y -q ca-certificates curl git ufw unzip
if ! command -v docker >/dev/null; then
  curl -fsSL https://get.docker.com | sh
fi
usermod -aG docker "$DEPLOY_USER"
if ! command -v aws >/dev/null; then
  curl -fsSL "https://awscli.amazonaws.com/awscli-exe-linux-$(uname -m).zip" -o /tmp/awscli.zip
  unzip -q -o /tmp/awscli.zip -d /tmp && /tmp/aws/install --update
fi

echo "== Firewall: SSH, HTTP, HTTPS only"
ufw allow OpenSSH && ufw allow 80/tcp && ufw allow 443/tcp && ufw allow 443/udp
ufw --force enable

echo "== Code in $DRIVEQ_DIR"
if [ ! -d "$DRIVEQ_DIR/.git" ]; then
  git clone "$REPO_URL" "$DRIVEQ_DIR"
fi
chown -R "$DEPLOY_USER:$DEPLOY_USER" "$DRIVEQ_DIR"

echo "== Nightly backup at 03:00 IST"
cat > /etc/cron.d/driveq-backup <<CRON
30 21 * * * $DEPLOY_USER $DRIVEQ_DIR/deploy/backup.sh >> /var/log/driveq-backup.log 2>&1
CRON
touch /var/log/driveq-backup.log && chown "$DEPLOY_USER" /var/log/driveq-backup.log

cat <<DONE

Server ready. Next (as $DEPLOY_USER, after logging out and in again):
  cd $DRIVEQ_DIR
  cp deploy/.env.production.example .env.production && nano .env.production
  deploy/deploy.sh
See docs/DEPLOY.md for the rest (admin account, DNS, mail).
DONE
