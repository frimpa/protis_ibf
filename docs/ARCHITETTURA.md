# Architettura di PROTIS IBF

## Componenti

PROTIS IBF è un pacchetto Joomla 6 (`pkg_protis`) con quattro estensioni:

| Estensione | Ruolo |
|---|---|
| `com_protis` | Componente: servizi di dominio, viste del sito (app), endpoint JSON, esportazioni, PWA, guida |
| `plg_authentication_protisldap` | Accesso con LDAP CNR, controllo della sede abilitata, sincronizzazione dell'anagrafica |
| `plg_console_protis` | Comandi `protis:epas-sync` (cron) e `protis:migrate-v2` |
| `tpl_protis` | Template essenziale a schermo intero per tutte le pagine PROTIS IBF (disattivabile dalle Opzioni) |

Tutta l'operatività è nel **sito**, non nell'amministrazione di Joomla: funziona allo stesso modo da computer,
smartphone e app installata. Nel backend restano Opzioni, permessi e un pannello informativo.

## Flusso dei dati

```
LDAP CNR  → plg_authentication_protisldap (login)     → #__users, #__protis_people
ePAS REST → EpasService::syncMonth (cron / pulsante)  → #__protis_days
Utente    → CalendarService (API JSON del calendario) → #__protis_hours
TimesheetService → LatexRenderer (PDF) | SpreadsheetRenderer (xlsx, ods, csv)
```

## Codice

Namespace `Cnr\Component\Protis`. La logica sta nei servizi (riusati da sito, console e migrazione), i controller
sono sottili.

| Cartella | Contenuto |
|---|---|
| `administrator/src/Service` | `CalendarService`, `EpasService`, `PeopleService`, `ProjectService`, `ReportService`, `SettingsService`, `TimesheetService`, `IntervalTimesheet`, `HeaderTemplate`, `LogoBanner`, `LatexRenderer`, `SpreadsheetRenderer`, `LdapDirectory`, `LegacyImporter` |
| `administrator/src/Helper` | `Acl` (ruoli), `Crypto` (libsodium), `Time` (minuti ⇄ hh:mm), `Holidays` (festività e patroni), `Markdown` (guida) |
| `administrator/sql` | Schema di installazione e script di aggiornamento per versione |
| `site/src/Controller` | `Display`, `Api` (JSON), `Manage` (moduli), `Export` (file), `Auth`, `Pwa` |
| `site/src/View`, `site/tmpl` | Viste: home, calendar, assignments, profile, guide, login; per i responsabili projects, project, people, person, report, codes; per gli amministratori settings, log |
| `site/guide` | Testo della Guida (Markdown, un file per livello di ruolo) |
| `media` | `protis.css`, `protis-app.js` (interfaccia comune), `protis-calendar.js`, icone PWA, logo |

## Schema dei dati

Ore e durate sono sempre **minuti interi**. Nessuna tabella o colonna viene creata a runtime; le modifiche di schema
sono negli script `administrator/sql/updates/mysql/X.Y.Z.sql`.

| Tabella | Contenuto |
|---|---|
| `#__protis_sites` | Sedi: codice sede (= `departmentNumber` LDAP), credenziali ePAS cifrate, `epas_only` per le sedi di altri istituti usate solo per ePAS |
| `#__protis_people` | Persone: uid, matricola, anagrafica, contratto, data di assunzione, sede, foto; `external` (altro istituto), `epas_off` (inserimento manuale), `epas_site_id`, `active` |
| `#__protis_projects` | Progetti: acronimo, CUP, Grant Agreement, date, ente, tipo di ricerca, loghi, righe dell'intestazione (JSON), lingua della tabella, P.IVA, SAL, stato |
| `#__protis_workpackages` | WP con ID propri |
| `#__protis_assignments` | Persona ↔ progetto: ore previste e monte ore annuo (minuti) |
| `#__protis_days` | Una riga per persona e giorno: lavoro, agile, telelavoro, missione, malattia, ferie, permessi, altro; codici di assenza; origine; JSON ePAS grezzo |
| `#__protis_hours` | Minuti per persona, giorno e WP |
| `#__protis_absence_codes` | Codici ePAS: categoria, orario convenzionale, esclusione da Riempi/Distribuisci |
| `#__protis_contract_types` | Tipi di contratto CNR |
| `#__protis_log` | Registro delle operazioni |

## Regole di calcolo

- Ore rendicontabili del giorno = presenza (`timeAtWork` ePAS, o timbrature − 30' di pasto) + lavoro agile + telelavoro + missione.
- Per i codici di assenza vale l'orario convenzionale del codice; i codici senza orario non sono conteggiati.
- La somma delle ore sui WP non può superare le ore rendicontabili del giorno (controllo lato server).
- *Attività ordinaria* nel timesheet = ore rendicontabili − ore su tutti i progetti.
- Monte ore annuo: quello dell'associazione; se assente, 1520 ore nei primi 3 anni dall'assunzione, poi 1506.
- Riempi e Distribuisci saltano i giorni con codici esclusi.
- Righe del timesheet: WP del progetto, una riga per ciascun altro progetto della persona, categorie, totale.

## Endpoint

Tutte le richieste passano da `index.php?option=com_protis&task=<controller>.<azione>`; ogni scrittura richiede il
token CSRF di sessione e i permessi sono verificati lato server.

| Controller | Azioni |
|---|---|
| `api` (JSON) | `month`, `saveDay`, `sync`, `fill`, `distribute`, `clear`, `people`, `toggleProject`, `togglePerson`, `togglePersonEpas` |
| `manage` | `saveProject`, `deleteProject`, `saveAssignment`, `deleteAssignment`, `addExternal`, `saveProfile`, `saveAvatar`, `savePerson`, `saveSite`, `deleteSite`, `testEpas`, `syncAll`, `saveCode(s)`, `downloadCodes` |
| `export` | `timesheet`, `project`, `interval`, `preview` |
| `auth`, `pwa` | `login`; `manifest`, `sw`, `offline` |

## Integrazioni

**LDAP CNR.** Bind con utente tecnico, ricerca per `uid`, verifica della password dell'utente. Con il controllo sede
attivo entrano solo le persone con `departmentNumber` di una sede attiva (non di altro istituto) o abilitate per
username come colleghi esterni.

**ePAS.** `GET /persondays/getmonthsituationbyperson?number=<matricola>&year=&month=` con Basic Auth del
gestore anagrafica della sede (o della sede ePAS dell'istituto del collega esterno). Anche i codici di assenza sono
letti da ePAS.

**PDF.** LaTeX compilato con `pdflatex` in una cartella temporanea privata (due passaggi per le tabelle lunghe).
Excel/ODS/CSV con PhpSpreadsheet.

## Sicurezza

- Credenziali ePAS cifrate con libsodium (`secretbox`), chiave derivata dal `secret` di `configuration.php`; mai mostrate.
- Credenziali LDAP tecniche nei parametri del plugin.
- Token CSRF su ogni scrittura; controlli di ruolo e di accesso alla persona lato server (un utente può modificare solo sé stesso).
- File di export generati in una cartella temporanea privata e inviati direttamente.
- Il service worker non mette mai in cache API, esportazioni o pagine con dati personali.
- Foto profilo ricodificate lato server (JPEG 320 px, senza metadati).
- Header di sicurezza nel virtual host creato da `install.sh` (HSTS con HTTPS).

## PWA

Manifest e service worker sono serviti da `index.php?option=com_protis&task=pwa.manifest|sw`: lo scope copre l'intero
sito senza file nella root. Richiede HTTPS (tranne `localhost`).
