#!/bin/bash
# ركائز (platt) — daily backup: MySQL dump + uploads volume + env files (APP_KEY!).
# Output: /opt/platt-backups/<YYYY-MM-DD_HHMM>/ ; retention: 14 daily, Sundays kept 60 days.
# Cron: 15 3 * * * /opt/platt/backup.sh   ·  Restore: /opt/platt/restore.sh <dir> [db]
set -euo pipefail
ROOT=/opt/platt; OUT=/opt/platt-backups; LOG=$OUT/backup.log
exec 9>/tmp/platt-backup.lock; flock -n 9 || { echo "$(date) another backup is running" >> "$LOG"; exit 0; }
mkdir -p "$OUT"; chmod 700 "$OUT"
# shellcheck disable=SC1091
set -a; . "$ROOT/.env"; set +a
STAMP=$(date +%F_%H%M); DIR="$OUT/$STAMP"; mkdir -p "$DIR"; chmod 700 "$DIR"
echo "$(date) start $STAMP" >> "$LOG"
# 1) database (consistent snapshot, with routines/triggers/events)
docker exec platt-db sh -c "mysqldump -uroot -p\"\$MYSQL_ROOT_PASSWORD\" --single-transaction --quick --routines --triggers --events --default-character-set=utf8mb4 platt" 2>/dev/null | gzip -9 > "$DIR/platt.sql.gz"
[ -s "$DIR/platt.sql.gz" ] && gzip -t "$DIR/platt.sql.gz" || { echo "$(date) ERROR: dump failed" >> "$LOG"; rm -rf "$DIR"; exit 1; }
# 2) uploaded files volume
docker run --rm -v platt_platt-api-storage:/data:ro -v "$DIR":/out alpine sh -c 'cd /data && tar czf /out/storage.tgz . 2>/dev/null || true'
# 3) secrets needed to read the data back (APP_KEY encrypts fields; DB passwords)
cp "$ROOT/api/.env" "$DIR/api.env"; cp "$ROOT/.env" "$DIR/compose.env"; chmod 600 "$DIR"/*.env
# 4) manifest
{ echo "created=$(date -Is)"; echo "git=$(git -C "$ROOT" rev-parse --short HEAD 2>/dev/null || echo ?)"; echo "db_bytes=$(stat -c%s "$DIR/platt.sql.gz")"; echo "tables=$(zcat "$DIR/platt.sql.gz" | grep -c '^CREATE TABLE' || true)"; } > "$DIR/MANIFEST"
# 5) retention: drop daily older than 14 days unless it was taken on a Sunday (kept 60 days)
find "$OUT" -mindepth 1 -maxdepth 1 -type d -name '20*' | while read -r d; do
  ts=$(basename "$d" | cut -d_ -f1); age=$(( ( $(date +%s) - $(date -d "$ts" +%s) ) / 86400 ))
  dow=$(date -d "$ts" +%u)
  if { [ "$age" -gt 14 ] && [ "$dow" != 7 ]; } || [ "$age" -gt 60 ]; then rm -rf "$d"; echo "$(date) pruned $(basename "$d")" >> "$LOG"; fi
done
echo "$(date) done $STAMP ($(du -sh "$DIR" | cut -f1), $(grep tables= "$DIR/MANIFEST"))" >> "$LOG"
