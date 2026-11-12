#!/usr/bin/env bash
# PROTIS IBF – backup notturno: dump del database + loghi progetti + configurazione.
# Conserva gli ultimi 30 giorni in /var/backups/protis (copiare altrove con rsync/borg per la replica).
set -euo pipefail
INSTALL_DIR="/var/www/protis"
DB_NAME="protis"
DEST="/var/backups/protis"
KEEP_DAYS=30

mkdir -p "$DEST" && chmod 700 "$DEST"
STAMP="$(date +%Y%m%d-%H%M)"
mysqldump --single-transaction --routines --default-character-set=utf8mb4 "$DB_NAME" | gzip -9 > "$DEST/db-$STAMP.sql.gz"
tar -czf "$DEST/files-$STAMP.tar.gz" -C "$INSTALL_DIR" configuration.php images/protis 2>/dev/null || true
find "$DEST" -type f -mtime +"$KEEP_DAYS" -delete
echo "$(date '+%F %T') backup ok: db-$STAMP.sql.gz"
