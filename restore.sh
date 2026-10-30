#!/bin/bash
# Restore a backup made by backup.sh.   usage: restore.sh /opt/platt-backups/<dir> [target_db]
# Default target is the live database `platt` (asks for confirmation). Use another name to test a restore.
set -euo pipefail
DIR=${1:?backup dir}; DB=${2:-platt}
# shellcheck disable=SC1091
set -a; . /opt/platt/.env; set +a
[ -f "$DIR/platt.sql.gz" ] || { echo "no platt.sql.gz in $DIR"; exit 1; }
if [ "$DB" = platt ]; then read -r -p "This OVERWRITES the live database. Type RESTORE to continue: " a; [ "$a" = RESTORE ] || exit 1; fi
docker exec platt-db sh -c "mysql -uroot -p\"\$MYSQL_ROOT_PASSWORD\" -e 'CREATE DATABASE IF NOT EXISTS \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'"
zcat "$DIR/platt.sql.gz" | docker exec -i platt-db sh -c "mysql -uroot -p\"\$MYSQL_ROOT_PASSWORD\" $DB"
echo "database restored into $DB"
if [ "$DB" = platt ] && [ -f "$DIR/storage.tgz" ]; then
  docker run --rm -v platt_platt-api-storage:/data -v "$DIR":/in:ro alpine sh -c 'cd /data && tar xzf /in/storage.tgz' && echo "uploads restored"
fi
