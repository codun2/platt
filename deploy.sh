#!/bin/bash
# Auto-deploy for platt/Rakeez (cron every minute). Pulls origin/main, rebuilds on change.
set -e
cd /opt/platt
exec 9>/tmp/platt-deploy.lock
flock -n 9 || exit 0

git fetch -q origin main
REMOTE=$(git rev-parse origin/main)
LOCAL=$(cat .last_deployed 2>/dev/null || echo none)
[ "$REMOTE" = "$LOCAL" ] && exit 0

echo "$(date) deploying $REMOTE" >> /opt/platt/deploy.log
git reset --hard origin/main
docker compose up -d --build >> /opt/platt/deploy.log 2>&1
echo "$REMOTE" > .last_deployed
echo "$(date) done" >> /opt/platt/deploy.log
