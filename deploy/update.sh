#!/usr/bin/env bash
# PROTIS IBF – aggiornamento: backup + installazione della nuova versione del pacchetto.
# Uso: sudo deploy/update.sh [dist/pkg_protis-X.Y.Z.zip] [--dir /var/www/protis]
set -euo pipefail
INSTALL_DIR="/var/www/protis"
PKG=""
while [[ $# -gt 0 ]]; do
  case "$1" in --dir) INSTALL_DIR="$2"; shift 2;; *) PKG="$1"; shift;; esac
done
REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"
[[ -n "$PKG" ]] || PKG="$(ls -1t "$REPO_DIR"/dist/pkg_protis-*.zip | head -1)"
[[ -f "$PKG" ]] || { echo "Pacchetto non trovato"; exit 1; }
[[ -x /usr/local/sbin/protis-backup ]] && /usr/local/sbin/protis-backup
cp "$PKG" /tmp/pkg_protis.zip && chmod 644 /tmp/pkg_protis.zip
sudo -u www-data php "$INSTALL_DIR/cli/joomla.php" extension:install --path=/tmp/pkg_protis.zip -n
rm -f /tmp/pkg_protis.zip
sudo -u www-data php "$INSTALL_DIR/cli/joomla.php" cache:clean -n >/dev/null 2>&1 || true
echo "PROTIS IBF aggiornato a $(basename "$PKG")"
