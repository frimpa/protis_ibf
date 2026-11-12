#!/usr/bin/env bash
# =============================================================================
#  PROTIS IBF V.3 – installazione automatica su Ubuntu Server (24.04 / 26.04 LTS)
#
#  Installa: Apache + PHP + MariaDB + TeX Live (per i PDF) + Joomla 6 + PROTIS IBF,
#  configura vhost, cron di sincronizzazione ePAS, backup notturni e (opzionale) HTTPS.
#
#  Uso tipico:
#    sudo ./install.sh --domain timesheet.mioistituto.cnr.it --admin-email nome.cognome@cnr.it --https
#
#  Tutte le opzioni:  sudo ./install.sh --help
# =============================================================================
set -euo pipefail

# ---------------------------------------------------------------- default
DOMAIN=""
INSTALL_DIR="/var/www/protis"
DB_NAME="protis"
DB_USER="protis"
DB_PASS=""
DB_PREFIX="pt_"
ADMIN_USER="admin"
ADMIN_NAME="Amministratore PROTIS IBF"
ADMIN_EMAIL=""
ADMIN_PASS=""
SITE_NAME="PROTIS IBF"
JOOMLA_VERSION=""            # vuoto = ultima 6.x stabile da GitHub
PACKAGE=""                   # vuoto = dist/pkg_protis-*.zip del repository
HTTPS=0
LDAP_BIND_DN=""
LDAP_BIND_PASS=""
WITH_BACKUP=1
SKIP_TEX=0
ALIAS=""                     # es. /protis: pubblica PROTIS IBF come sottocartella del sito esistente
BACKUP_DIR="/var/backups/protis"

usage() {
  sed -n '2,14p' "$0" | sed 's/^# \{0,1\}//'
  cat <<EOF
Opzioni:
  --domain NOME            nome host del sito (obbligatorio, es. timesheet.mioistituto.cnr.it)
  --dir PERCORSO           cartella di installazione (default $INSTALL_DIR)
  --admin-email EMAIL      email del Super User Joomla (obbligatoria)
  --admin-user NOME        username Super User (default $ADMIN_USER)
  --admin-pass PASSWORD    password Super User (default: generata)
  --db-name / --db-user / --db-pass   database MariaDB (password generata se omessa)
  --joomla-version X.Y.Z   versione Joomla 6 da installare (default: ultima stabile)
  --package FILE           pacchetto pkg_protis-*.zip (default: dist/ del repository)
  --ldap-bind-dn DN        DN dell'utente tecnico LDAP CNR
  --ldap-bind-pass PWD     password dell'utente tecnico LDAP CNR
  --https                  ottiene il certificato Let's Encrypt con certbot
  --no-backup              non configura il backup notturno
  --skip-tex               non installa TeX Live (i PDF non funzioneranno)
  --alias /protis          NON crea un virtual host: pubblica PROTIS IBF come https://<sito esistente>/protis
                           (consigliato se sul server gira già la V.2 o un altro sito)
  --backup-dir PERCORSO    cartella dei backup notturni (default $BACKUP_DIR, es. /mnt/sdb/backup/protis)
EOF
  exit 0
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --domain) DOMAIN="$2"; shift 2;;
    --dir) INSTALL_DIR="$2"; shift 2;;
    --admin-email) ADMIN_EMAIL="$2"; shift 2;;
    --admin-user) ADMIN_USER="$2"; shift 2;;
    --admin-pass) ADMIN_PASS="$2"; shift 2;;
    --db-name) DB_NAME="$2"; shift 2;;
    --db-user) DB_USER="$2"; shift 2;;
    --db-pass) DB_PASS="$2"; shift 2;;
    --joomla-version) JOOMLA_VERSION="$2"; shift 2;;
    --package) PACKAGE="$2"; shift 2;;
    --ldap-bind-dn) LDAP_BIND_DN="$2"; shift 2;;
    --ldap-bind-pass) LDAP_BIND_PASS="$2"; shift 2;;
    --https) HTTPS=1; shift;;
    --no-backup) WITH_BACKUP=0; shift;;
    --skip-tex) SKIP_TEX=1; shift;;
    --alias) ALIAS="/${2#/}"; ALIAS="${ALIAS%/}"; shift 2;;
    --backup-dir) BACKUP_DIR="$2"; shift 2;;
    -h|--help) usage;;
    *) echo "Opzione sconosciuta: $1"; exit 1;;
  esac
done

log()  { echo -e "\n\033[1;34m==> $*\033[0m"; }
warn() { echo -e "\033[1;33m[!] $*\033[0m"; }
die()  { echo -e "\033[1;31m[x] $*\033[0m"; exit 1; }
rnd()  { (set +o pipefail; LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom 2>/dev/null | head -c "${1:-24}"); }

[[ $EUID -eq 0 ]] || die "Eseguire come root (sudo)."
[[ -n "$DOMAIN" ]] || die "Indicare --domain (nome con cui si raggiunge il server)"
[[ -n "$ADMIN_EMAIL" ]] || die "Indicare --admin-email"
. /etc/os-release
[[ "$ID" == "ubuntu" ]] || warn "Sistema non Ubuntu ($PRETTY_NAME): proseguo comunque."

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_DIR="$(dirname "$SCRIPT_DIR")"
DB_PASS="${DB_PASS:-$(rnd 28)}"
ADMIN_PASS="${ADMIN_PASS:-$(rnd 20)}"

if [[ -z "$PACKAGE" ]]; then
  PACKAGE="$(ls -1t "$REPO_DIR"/dist/pkg_protis-*.zip 2>/dev/null | head -1 || true)"
  if [[ -z "$PACKAGE" && -x "$REPO_DIR/build/build.sh" ]]; then
    log "Pacchetto non trovato: lo creo con build/build.sh"
    apt-get install -y -q zip composer >/dev/null 2>&1 || true
    "$REPO_DIR/build/build.sh"
    PACKAGE="$(ls -1t "$REPO_DIR"/dist/pkg_protis-*.zip | head -1)"
  fi
fi
[[ -f "$PACKAGE" ]] || die "Pacchetto PROTIS IBF non trovato (usa --package)."

# ---------------------------------------------------------------- pacchetti
log "Installazione pacchetti di sistema"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q
# database: se c'è già MySQL o MariaDB (es. quello della V.2) si usa quello, altrimenti si installa MariaDB
if systemctl list-unit-files | grep -q '^mysql\.service'; then
  DB_SERVICE=mysql
elif systemctl list-unit-files | grep -q '^mariadb\.service'; then
  DB_SERVICE=mariadb
else
  DB_SERVICE=mariadb
  apt-get install -y -q mariadb-server
fi
log "Database server: $DB_SERVICE"
apt-get install -y -q apache2 unzip curl ca-certificates cron rsync zip \
  php php-cli php-mysql php-xml php-zip php-gd php-intl php-mbstring php-curl php-ldap php-opcache libapache2-mod-php
if [[ $SKIP_TEX -eq 0 ]]; then
  apt-get install -y -q --no-install-recommends texlive-latex-base texlive-latex-recommended texlive-latex-extra texlive-fonts-recommended lmodern
fi
[[ $HTTPS -eq 1 ]] && apt-get install -y -q certbot python3-certbot-apache

PHPV="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' || die "Joomla 6 richiede PHP >= 8.3 (trovato $PHPV)."
php -m | grep -qi sodium || die "Estensione PHP sodium mancante."

# ---------------------------------------------------------------- PHP
log "Configurazione PHP $PHPV"
cat > "/etc/php/$PHPV/apache2/conf.d/90-protis.ini" <<EOF
memory_limit = 256M
upload_max_filesize = 20M
post_max_size = 24M
max_execution_time = 300
output_buffering = 4096
expose_php = Off
session.cookie_httponly = 1
session.use_strict_mode = 1
opcache.enable = 1
opcache.memory_consumption = 128
EOF
cp "/etc/php/$PHPV/apache2/conf.d/90-protis.ini" "/etc/php/$PHPV/cli/conf.d/90-protis.ini"

# ---------------------------------------------------------------- database
log "Database $DB_NAME"
systemctl enable --now "$DB_SERVICE"
mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
mysql -e "ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;"

# ---------------------------------------------------------------- Joomla
if [[ -f "$INSTALL_DIR/configuration.php" ]]; then
  warn "Joomla già presente in $INSTALL_DIR: salto l'installazione del CMS."
else
  if [[ -z "$JOOMLA_VERSION" ]]; then
    JOOMLA_VERSION="$(curl -fsSL https://api.github.com/repos/joomla/joomla-cms/releases?per_page=30 \
      | grep -oP '"tag_name":\s*"\K6\.[0-9]+\.[0-9]+(?=")' | sort -V | tail -1 || true)"
    JOOMLA_VERSION="${JOOMLA_VERSION:-6.2.0}"
  fi
  log "Download Joomla $JOOMLA_VERSION"
  TMPZ="$(mktemp --suffix=.zip)"
  curl -fSL -o "$TMPZ" "https://github.com/joomla/joomla-cms/releases/download/$JOOMLA_VERSION/Joomla_$JOOMLA_VERSION-Stable-Full_Package.zip"
  mkdir -p "$INSTALL_DIR"
  unzip -q -o "$TMPZ" -d "$INSTALL_DIR"
  rm -f "$TMPZ"
  chown -R www-data:www-data "$INSTALL_DIR"

  log "Installazione Joomla"
  sudo -u www-data php "$INSTALL_DIR/installation/joomla.php" install -n \
    --site-name="$SITE_NAME" --admin-user="$ADMIN_NAME" --admin-username="$ADMIN_USER" \
    --admin-password="$ADMIN_PASS" --admin-email="$ADMIN_EMAIL" \
    --db-type=mysqli --db-host=localhost --db-user="$DB_USER" --db-pass="$DB_PASS" \
    --db-name="$DB_NAME" --db-prefix="$DB_PREFIX"
fi

# URL leggibili, sessione, lingua italiana
cp -n "$INSTALL_DIR/htaccess.txt" "$INSTALL_DIR/.htaccess" 2>/dev/null || true
CFG="$INSTALL_DIR/configuration.php"
sed -i "s/public \$sef_rewrite = .*/public \$sef_rewrite = true;/" "$CFG"
sed -i "s/public \$lifetime = .*/public \$lifetime = 60;/" "$CFG"
[[ $HTTPS -eq 1 ]] && sed -i "s/public \$force_ssl = .*/public \$force_ssl = 2;/" "$CFG"
sudo -u www-data php "$INSTALL_DIR/cli/joomla.php" extension:discover -n >/dev/null 2>&1 || true

# ---------------------------------------------------------------- PROTIS IBF
log "Installazione pacchetto PROTIS IBF ($(basename "$PACKAGE"))"
cp "$PACKAGE" /tmp/pkg_protis.zip && chmod 644 /tmp/pkg_protis.zip
sudo -u www-data php "$INSTALL_DIR/cli/joomla.php" extension:install --path=/tmp/pkg_protis.zip -n
rm -f /tmp/pkg_protis.zip

if [[ -n "$LDAP_BIND_DN" ]]; then
  log "Configurazione plugin LDAP CNR"
  php -r '
    $db = new mysqli("localhost", $argv[1], $argv[2], $argv[3]);
    $p  = json_encode(["host" => "ldaps://hostingservice.src.cnr.it", "base_dn" => "o=cnr,c=it", "bind_dn" => $argv[5],
                       "bind_password" => $argv[6], "uid_attr" => "uid", "check_site" => "1", "ignore_cert" => "0"]);
    $s  = $db->prepare("UPDATE " . $argv[4] . "extensions SET params = ?, enabled = 1 WHERE element = \"protisldap\" AND folder = \"authentication\"");
    $s->bind_param("s", $p);
    $s->execute();
  ' "$DB_USER" "$DB_PASS" "$DB_NAME" "$DB_PREFIX" "$LDAP_BIND_DN" "$LDAP_BIND_PASS"
fi

# PROTIS IBF come pagina iniziale del sito
mysql "$DB_NAME" -e "UPDATE ${DB_PREFIX}menu SET home = 0 WHERE client_id = 0 AND home = 1 AND language = '*';
UPDATE ${DB_PREFIX}menu SET home = 1 WHERE client_id = 0 AND link = 'index.php?option=com_protis&view=home' AND published = 1 LIMIT 1;"

chown -R www-data:www-data "$INSTALL_DIR"
find "$INSTALL_DIR" -type d -exec chmod 755 {} +
find "$INSTALL_DIR" -type f -exec chmod 644 {} +
chmod 640 "$CFG"

# ---------------------------------------------------------------- Apache
a2enmod rewrite headers expires >/dev/null

if [[ -n "$ALIAS" ]]; then
  log "Pubblicazione come $ALIAS sul sito Apache esistente (nessun virtual host nuovo)"
  cat > /etc/apache2/conf-available/protis.conf <<EOF
Alias $ALIAS $INSTALL_DIR
<Directory $INSTALL_DIR>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</Directory>
<DirectoryMatch "$INSTALL_DIR/(administrator/components/com_protis/vendor|administrator/logs|logs|tmp|cli)">
    Require all denied
</DirectoryMatch>
<FilesMatch "(^configuration\.php|\.(sql|log|ini|env|tex|aux)$|composer\.(json|lock)$)">
    Require all denied
</FilesMatch>
EOF
  # Joomla in sottocartella: RewriteBase nel .htaccess
  if grep -qE '^\s*#?\s*RewriteBase ' "$INSTALL_DIR/.htaccess"; then
    sed -i -E "0,/^\s*#?\s*RewriteBase .*/s||RewriteBase $ALIAS|" "$INSTALL_DIR/.htaccess"
  else
    sed -i "0,/RewriteEngine On/s||RewriteEngine On\nRewriteBase $ALIAS|" "$INSTALL_DIR/.htaccess"
  fi
  a2enconf protis >/dev/null
  apache2ctl configtest
  systemctl reload apache2
else
  log "Virtual host Apache per $DOMAIN"
  cat > /etc/apache2/sites-available/protis.conf <<EOF
<VirtualHost *:80>
    ServerName $DOMAIN
    DocumentRoot $INSTALL_DIR

    <Directory $INSTALL_DIR>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # file sensibili mai serviti
    <FilesMatch "(^configuration\.php|\.(sql|log|ini|env|tex|aux)$|composer\.(json|lock)$)">
        Require all denied
    </FilesMatch>
    <DirectoryMatch "$INSTALL_DIR/(administrator/components/com_protis/vendor|administrator/logs|logs|tmp|cli)">
        Require all denied
    </DirectoryMatch>

    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), camera=(), microphone=()"

    <IfModule mod_expires.c>
        ExpiresActive On
        ExpiresByType image/png "access plus 30 days"
        ExpiresByType text/css "access plus 7 days"
        ExpiresByType application/javascript "access plus 7 days"
    </IfModule>

    ErrorLog \${APACHE_LOG_DIR}/protis_error.log
    CustomLog \${APACHE_LOG_DIR}/protis_access.log combined
</VirtualHost>
EOF
  a2ensite protis >/dev/null
  apache2ctl configtest
  systemctl reload apache2
fi

if [[ $HTTPS -eq 1 && -z "$ALIAS" ]]; then
  log "Certificato HTTPS (Let's Encrypt)"
  certbot --apache -d "$DOMAIN" -m "$ADMIN_EMAIL" --agree-tos --redirect -n \
    || warn "certbot non riuscito: verificare DNS/porta 80. Per un certificato CNR/GARR configurare SSL a mano."
  sed -i '/<VirtualHost \*:443>/a\    Header always set Strict-Transport-Security "max-age=31536000"' /etc/apache2/sites-available/protis-le-ssl.conf 2>/dev/null || true
  systemctl reload apache2
fi

# ---------------------------------------------------------------- cron
log "Sincronizzazione ePAS automatica (cron)"
cat > /etc/cron.d/protis <<EOF
# PROTIS IBF – sincronizzazione timbrature ePAS (mese corrente; nei primi 7 giorni anche il precedente)
SHELL=/bin/bash
15 7,13,20 * * * www-data php $INSTALL_DIR/cli/joomla.php protis:epas-sync -n >> /var/log/protis/epas-sync.log 2>&1
EOF
mkdir -p /var/log/protis && chown www-data:www-data /var/log/protis
cat > /etc/logrotate.d/protis <<EOF
/var/log/protis/*.log {
    weekly
    rotate 8
    compress
    missingok
    notifempty
}
EOF

if [[ $WITH_BACKUP -eq 1 ]]; then
  log "Backup notturno (database + immagini) in $BACKUP_DIR"
  install -m 750 "$SCRIPT_DIR/backup.sh" /usr/local/sbin/protis-backup
  sed -i "s#^INSTALL_DIR=.*#INSTALL_DIR=\"$INSTALL_DIR\"#; s#^DB_NAME=.*#DB_NAME=\"$DB_NAME\"#; s#^DEST=.*#DEST=\"$BACKUP_DIR\"#" /usr/local/sbin/protis-backup
  echo "40 1 * * * root /usr/local/sbin/protis-backup >> /var/log/protis/backup.log 2>&1" >> /etc/cron.d/protis
fi

# ---------------------------------------------------------------- riepilogo
PROTO=http; [[ $HTTPS -eq 1 ]] && PROTO=https
BASEURL="${PROTO}://$DOMAIN${ALIAS}"
CRED=/root/protis-credenziali.txt
umask 077
cat > "$CRED" <<EOF
PROTIS IBF – installazione del $(date '+%d/%m/%Y %H:%M')
Sito:            $BASEURL/
Amministrazione: $BASEURL/administrator/
Super User:      $ADMIN_USER / $ADMIN_PASS
Database:        $DB_NAME  utente $DB_USER / $DB_PASS
Cartella:        $INSTALL_DIR
EOF

log "Installazione completata"
cat "$CRED"
cat <<EOF

Credenziali salvate in $CRED (solo root).

Prossimi passi:
  1. Amministrazione → Plugin → "Autenticazione - LDAP CNR (PROTIS IBF)": verificare bind DN e password.
  2. Sito → PROTIS IBF → Impostazioni: sedi dell'istituto (codice sede) e credenziali ePAS di ogni sede.
  3. Amministrazione → Sistema → Opzioni di PROTIS IBF: nome dell'istituto, logo, email di supporto.
  4. Assegnare i responsabili al gruppo "PROTIS Responsabili" (Utenti → Gestione).
  Guida completa: docs/ nel repository.
EOF
