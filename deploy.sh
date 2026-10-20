#!/bin/bash
# ركائز (platt) auto-deploy — cron every minute (flock-guarded).
# Pulls origin/main when it moves, rebuilds the stack (Nuxt build happens in
# the platt-web image), then runs migrations + idempotent seeders.
set -e
cd /opt/platt
exec 9>/tmp/platt-deploy.lock
flock -n 9 || exit 0

# Safety: if the working tree has uncommitted changes (a live session is editing),
# skip this cycle so `git reset --hard` never wipes in-progress work.
if ! git diff --quiet || ! git diff --cached --quiet; then
  exit 0
fi

git fetch -q origin main
REMOTE=$(git rev-parse origin/main)
LOCAL=$(cat .last_deployed 2>/dev/null || echo none)
[ "$REMOTE" = "$LOCAL" ] && exit 0

echo "$(date) deploying $REMOTE" >> /opt/platt/deploy.log
git reset --hard origin/main
docker compose up -d --build --remove-orphans >> /opt/platt/deploy.log 2>&1
sleep 10
docker compose exec -T platt-api php artisan migrate --force >> /opt/platt/deploy.log 2>&1 || true
docker compose exec -T platt-api php artisan db:seed --force >> /opt/platt/deploy.log 2>&1 || true
docker compose exec -T platt-api php artisan config:clear >> /opt/platt/deploy.log 2>&1 || true
docker image prune -f >/dev/null 2>&1 || true
echo "$REMOTE" > .last_deployed
echo "$(date) done" >> /opt/platt/deploy.log
