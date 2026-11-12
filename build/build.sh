#!/usr/bin/env bash
# Crea il pacchetto installabile Joomla: dist/pkg_protis-<versione>.zip
# Uso: build/build.sh [--skip-composer]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/src"
DIST="$ROOT/dist"
TMP="$(mktemp -d)"
VERSION="$(grep -oP '(?<=<version>)[^<]+' "$SRC/pkg_protis.xml")"
trap 'rm -rf "$TMP"' EXIT

echo "PROTIS IBF $VERSION – build"

# 1) dipendenze PHP del componente (PhpSpreadsheet)
VENDOR="$SRC/com_protis/administrator/vendor"
if [[ "${1:-}" != "--skip-composer" ]]; then
  if command -v composer >/dev/null; then
    (cd "$SRC/com_protis/administrator" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --no-progress)
  else
    echo "ATTENZIONE: composer non trovato, uso la cartella vendor esistente" >&2
  fi
fi
[[ -f "$VENDOR/autoload.php" ]] || { echo "Manca $VENDOR/autoload.php: eseguire composer install" >&2; exit 1; }

# 2) zip delle singole estensioni
mkdir -p "$TMP/pkg/packages" "$DIST"
(cd "$SRC/com_protis" && zip -qr "$TMP/pkg/packages/com_protis.zip" . -x '*/.git/*' '*/tests/*' '*/docs/*' '*.md~' '*/.DS_Store')
(cd "$SRC/plg_authentication_protisldap" && zip -qr "$TMP/pkg/packages/plg_authentication_protisldap.zip" . -x '*/.DS_Store')
(cd "$SRC/plg_console_protis" && zip -qr "$TMP/pkg/packages/plg_console_protis.zip" . -x '*/.DS_Store')
(cd "$SRC/tpl_protis" && zip -qr "$TMP/pkg/packages/tpl_protis.zip" . -x '*/.DS_Store')
cp "$SRC/pkg_protis.xml" "$SRC/pkg_script.php" "$TMP/pkg/"

# 3) pacchetto
OUT="$DIST/pkg_protis-$VERSION.zip"
rm -f "$OUT"
(cd "$TMP/pkg" && zip -qr "$OUT" .)
echo "Creato $OUT ($(du -h "$OUT" | cut -f1))"
