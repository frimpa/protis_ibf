# PROTIS IBF – Rendicontazione delle ore e timesheet di progetto

PROTIS IBF è un'applicazione web per gli istituti del **CNR** che serve a rendicontare le ore lavorate sui progetti di
ricerca e a produrre i **timesheet mensili** da firmare (PDF, Excel, ODS, CSV).

- Presenze e assenze lette automaticamente da **ePAS**; inserimento manuale per chi non timbra (borsisti, colleghi di altri istituti).
- Accesso con le **credenziali istituzionali CNR** (LDAP).
- Interfaccia responsive per computer, tablet e smartphone, installabile come **app** (PWA).
- Intestazione del timesheet configurabile per ogni progetto (loghi, righe, lingua), conforme ai principali bandi (Horizon, PNRR, PRIN, MISE…).
- Dashboard di rendicontazione per progetto e persona, export per intervallo.
- Guida d'uso integrata, filtrata per ruolo.

PROTIS IBF è un pacchetto per **Joomla 6** e si installa su un server Linux con un solo script.
È stato ideato e sviluppato all'**Istituto di Biofisica del CNR (IBF)** e può essere adottato da qualsiasi istituto CNR,
mantenendo il nome PROTIS IBF.

> **Autore e responsabile del software:** Ing. Francesco Impallari – CNR, Istituto di Biofisica.
> Licenza **EUPL-1.2** – vedi [LICENSE](LICENSE) e [NOTICE](NOTICE).

---

## Documentazione

| Documento | Per chi |
|---|---|
| [Manuale utente](docs/MANUALE-UTENTE.md) | Utenti, responsabili, amministratori (lo stesso testo della Guida dentro l'app) |
| [Installazione](docs/INSTALLAZIONE.md) | Chi gestisce il server |
| [Configurazione per un istituto](docs/CONFIGURAZIONE.md) | Amministratore PROTIS IBF: sedi, LDAP, ePAS, ruoli, opzioni |
| [Architettura](docs/ARCHITETTURA.md) | Sviluppatori: componenti, dati, API, sicurezza |
| [Migrazione dalla V.2](docs/MIGRAZIONE-V2.md) | Solo per chi usava PROTIS IBF V.2 su WordPress |
| [Contribuire](CONTRIBUTING.md) · [Sicurezza](SECURITY.md) · [Novità](CHANGELOG.md) | |

## Avvio rapido

Su un server **Ubuntu 24.04 / 26.04 LTS** pulito:

```bash
git clone https://github.com/frimpa/protis_ibf.git
cd protis_ibf
sudo deploy/install.sh --domain timesheet.mioistituto.cnr.it --admin-email nome.cognome@cnr.it --https
```

Lo script installa Apache, PHP, MariaDB, TeX Live, Joomla 6 e PROTIS IBF, configura HTTPS, la sincronizzazione ePAS
e i backup notturni. Poi, dall'app:

1. **Impostazioni → Sedi dell'istituto**: una riga per sede con il codice sede (departmentNumber LDAP) e le credenziali ePAS.
2. **Opzioni di PROTIS IBF** (amministrazione Joomla): nome dell'istituto, logo, referente locale (supporto).
3. **Plugin "Autenticazione – LDAP CNR (PROTIS IBF)"**: DN e password dell'utente tecnico LDAP fornito dal CNR.
4. **Utenti**: aggiungere i responsabili al gruppo *PROTIS Responsabili*.

Su un Joomla 6 esistente basta installare il pacchetto `pkg_protis-X.Y.Z.zip` dalle
[Releases](https://github.com/frimpa/protis_ibf/releases). Dettagli in [docs/INSTALLAZIONE.md](docs/INSTALLAZIONE.md).

## Requisiti

| Componente | Versione |
|---|---|
| PHP | ≥ 8.3 con `mysqli`, `sodium`, `curl`, `ldap`, `zip`, `gd`, `intl` |
| Database | MariaDB ≥ 10.4 o MySQL ≥ 8.0.13 |
| Joomla | 6.x |
| PDF | `pdflatex` (TeX Live, `texlive-latex-extra`) |
| Servizi CNR | LDAP CNR (utente tecnico), ePAS con utente "gestore anagrafica" per sede |

## Ruoli

| Gruppo Joomla | Cosa può fare |
|---|---|
| Registered | Proprio calendario, profilo, associazione ai progetti, timesheet |
| PROTIS Responsabili | Progetti, associazioni, persone, codici di assenza, calendari e dashboard di tutte le sedi |
| PROTIS Amministratori | Tutto, incluse sedi, credenziali ePAS, registro e opzioni |

## Struttura del repository

```
src/
  com_protis/                       componente (servizi, viste, API, export, PWA, guida)
  plg_authentication_protisldap/    accesso con LDAP CNR
  plg_console_protis/               comandi protis:epas-sync e protis:migrate-v2
  tpl_protis/                       template "app" a schermo intero
  pkg_protis.xml                    pacchetto
build/build.sh                      crea dist/pkg_protis-<versione>.zip
deploy/                             install.sh, update.sh, backup.sh
docs/                               documentazione
tools/test-ldap.php                 verifica della connessione LDAP dal server
```

## Build

```bash
build/build.sh            # composer install (PhpSpreadsheet) + zip del pacchetto in dist/
```

## Autore, responsabilità e licenza

**PROTIS IBF** è un software dell'Istituto di Biofisica del CNR, ideato e sviluppato dall'**Ing. Francesco Impallari**,
che ne è l'autore e il **responsabile del software** (evoluzione, rilasci, correzioni).

Gli istituti che adottano PROTIS IBF ne sono **utilizzatori**: ciascuno nomina un **referente locale** che gestisce la
propria installazione (server, configurazione, utenti, dati) e fa da tramite con il responsabile del software per
segnalazioni e richieste ([Issue](https://github.com/frimpa/protis_ibf/issues)). Il referente locale si indica nelle
Opzioni (*Supporto: nome ed email*) e compare nella pagina di accesso.

Il nome **PROTIS IBF**, i crediti all'autore e all'Istituto di Biofisica nelle pagine dell'applicazione e nel file
[NOTICE](NOTICE) vanno mantenuti in ogni installazione e versione modificata.

Copyright © 2026 Francesco Impallari / CNR – Istituto di Biofisica.
Distribuito con licenza **European Union Public Licence v. 1.2** ([LICENSE](LICENSE)).
Joomla! (GPL-2.0-or-later) viene scaricato in fase di installazione; PhpSpreadsheet e dipendenze sono MIT.
Ogni istituto che adotta PROTIS IBF è responsabile della propria installazione e dei dati che vi tratta.

Applicazione sviluppata con il supporto dell'assistente di intelligenza artificiale Claude (Anthropic).
