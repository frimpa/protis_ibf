# Installazione di PROTIS IBF

Questa guida è per chi gestisce il server. Al termine PROTIS IBF è raggiungibile via HTTPS, sincronizza ePAS tre volte al
giorno e fa un backup ogni notte. La configurazione per l'istituto (sedi, LDAP, ePAS, ruoli) è in
[CONFIGURAZIONE.md](CONFIGURAZIONE.md).

## 1. Cosa serve prima

- Un server **Ubuntu Server 24.04 o 26.04 LTS** (VM da 2 vCPU, 4 GB RAM e 20 GB di disco bastano per un istituto).
- Un nome DNS (es. `timesheet.mioistituto.cnr.it`) che punta al server e le porte 80/443 aperte, per HTTPS con Let's Encrypt.
- Dal server devono essere raggiungibili: l'LDAP CNR (`ldaps://…:636`) e le API ePAS
  (`https://epas4clients.amministrazione.cnr.it`).
- Dal CNR: un **utente tecnico LDAP** (DN e password) e, per ogni sede, un utente ePAS con ruolo **gestore anagrafica**.

## 2. Installazione automatica (consigliata)

```bash
sudo apt install -y git
git clone https://github.com/frimpa/protis_ibf.git
cd protis_ibf
sudo deploy/install.sh \
  --domain timesheet.mioistituto.cnr.it \
  --admin-email nome.cognome@cnr.it \
  --https
```

Lo script:

1. installa Apache, PHP, MariaDB, TeX Live e gli strumenti necessari;
2. crea il database e l'utente MariaDB (password generate);
3. scarica l'ultima Joomla 6 stabile e la installa da riga di comando;
4. crea il pacchetto PROTIS IBF (se `dist/` è vuota) e lo installa; imposta PROTIS IBF come pagina iniziale;
5. configura il virtual host con gli header di sicurezza e, con `--https`, il certificato Let's Encrypt;
6. crea il cron di sincronizzazione ePAS (7:15, 13:15, 20:15) e il backup notturno (1:40);
7. salva le credenziali generate in `/root/protis-credenziali.txt` (leggibile solo da root).

Opzioni principali (`sudo deploy/install.sh --help` per l'elenco completo):

| Opzione | Uso |
|---|---|
| `--domain` | Nome host del sito (obbligatorio) |
| `--admin-email` | Email del Super User Joomla (obbligatoria) |
| `--ldap-bind-dn`, `--ldap-bind-pass` | Utente tecnico LDAP (si possono inserire anche dopo, nel plugin) |
| `--alias /protis` | Pubblica PROTIS IBF come sottocartella di un sito Apache già esistente, senza creare un virtual host |
| `--dir` | Cartella di installazione (default `/var/www/protis`) |
| `--backup-dir` | Cartella dei backup (default `/var/backups/protis`) |
| `--package` | Usa un pacchetto già scaricato dalle Releases |
| `--no-backup`, `--skip-tex` | Salta il backup o TeX Live (senza TeX i PDF non funzionano) |

> Le password passate da riga di comando restano nella cronologia della shell: in alternativa inseritele dopo,
> dall'amministrazione di Joomla.

## 3. Installazione su un Joomla 6 esistente

1. Verificare i requisiti: PHP ≥ 8.3 con `mysqli`, `sodium`, `curl`, `ldap`, `zip`, `gd`, `intl`;
   MariaDB ≥ 10.4 o MySQL ≥ 8.0.13; `pdflatex` (`apt install texlive-latex-extra`).
2. Scaricare `pkg_protis-X.Y.Z.zip` dalle [Releases](https://github.com/frimpa/protis_ibf/releases)
   (oppure crearlo con `build/build.sh`).
3. Amministrazione Joomla → **Sistema → Installa estensioni** → caricare il pacchetto.
4. Aggiungere il cron dell'utente del web server:
   ```
   15 7,13,20 * * * php /percorso/joomla/cli/joomla.php protis:epas-sync -n
   ```
5. Facoltativo: copiare `deploy/backup.sh` in `/usr/local/sbin/protis-backup`, adattare le prime righe e programmarlo ogni notte.

Il limite di caricamento di PHP deve permettere i loghi dei progetti e le foto (es. `upload_max_filesize = 32M`,
`post_max_size = 40M` nel `php.ini` usato da Apache).

## 4. Dopo l'installazione

Seguire [CONFIGURAZIONE.md](CONFIGURAZIONE.md): sedi e credenziali ePAS, LDAP, opzioni, ruoli. In più:

- Amministrazione → **Sistema → Lingue**: installare l'italiano e impostarlo come predefinito.
- Provare l'accesso con un utente di una sede abilitata e la sincronizzazione con *Impostazioni → Test connessione ePAS*.

## 5. Aggiornamento

```bash
cd protis_ibf && git pull && build/build.sh && sudo deploy/update.sh
```

`update.sh` esegue prima il backup e poi installa il nuovo pacchetto. In alternativa si carica il nuovo
`pkg_protis-X.Y.Z.zip` da *Sistema → Installa estensioni*. Dati, impostazioni e personalizzazioni restano; lo
schema del database si aggiorna da solo.

## 6. Backup e ripristino

Il backup notturno salva in `/var/backups/protis` (30 giorni):

- `db-AAAAMMGG-HHMM.sql.gz` – dump del database;
- `files-AAAAMMGG-HHMM.tar.gz` – `configuration.php` e `images/protis` (loghi dei progetti, foto).

Copiate la cartella su un altro sistema (rsync, borg…). Per ripristinare: reinstallare Joomla e PROTIS IBF, importare il
dump con `gunzip -c db-….sql.gz | mysql protis`, ripristinare i file. **Conservate `configuration.php`**: il suo
`secret` è la chiave con cui sono cifrate le credenziali ePAS.

## 7. Log

| File | Contenuto |
|---|---|
| `/var/log/protis/epas-sync.log` | Esito delle sincronizzazioni ePAS |
| `/var/log/protis/backup.log` | Esito dei backup |
| `/var/log/apache2/error.log` | Errori di PHP e del sito |
