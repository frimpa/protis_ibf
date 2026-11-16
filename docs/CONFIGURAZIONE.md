# Configurare PROTIS IBF per un istituto

Dopo l'installazione PROTIS IBF non contiene sedi, persone o progetti: si configurano in questo ordine. Servono un
account **Super User** di Joomla e, per l'app, l'appartenenza al gruppo *PROTIS Amministratori*.

## 1. Opzioni generali

Amministrazione Joomla → **Componenti → PROTIS IBF → Opzioni** (oppure in PROTIS IBF: *Impostazioni → Opzioni avanzate e permessi*).

| Opzione | Esempio | Note |
|---|---|---|
| Nome applicazione | `PROTIS IBF` | Titolo delle pagine e nome dell'app installata |
| Istituto | `CNR - Istituto di Biofisica` | Piè di pagina e valore predefinito di "Ente / Beneficiario" nei progetti |
| Scritte accanto al logo | `Consiglio Nazionale delle Ricerche` / `Istituto di Biofisica` | Intestazione e pagina di accesso |
| Logo dell'applicazione | `images/logo/mio-logo.png` | Vuoto = logo PROTIS IBF incluso. PNG con sfondo trasparente |
| Scrivania PDF: file sul server | `/var/www/protis_apps/scrivania_pdf/index.html` | Facoltativo. Percorso dell'`index.html` di [Scrivania PDF](https://github.com/frimpa/scrivaniaPDF), meglio fuori dalla cartella pubblica: PROTIS IBF lo mostra solo agli utenti collegati, con la voce di menu *Scrivania PDF* |
| Supporto: nome ed email | `Mario Rossi`, `mario.rossi@cnr.it` | **Referente locale** di PROTIS IBF nel vostro istituto: compare alla voce "Problemi di accesso?" della pagina di accesso |
| Colori | `#0b4f8a`, `#ffc300` | Colore principale e d'accento |
| URL REST ePAS | `https://epas4clients.amministrazione.cnr.it/rest/v3` | Da cambiare solo se il CNR lo modifica |
| Credenziali di altre sedi se mancanti | Sì | Se una sede non ha credenziali ePAS proprie usa quelle di un'altra sede dell'istituto |
| Sincronizza all'apertura del mese | No | Aggiorna da ePAS quando l'utente apre il mese corrente |
| Colori del timesheet | Blu | Blu istituzionale o lilla (stile V.2) |
| Blocca modifiche dopo N giorni | 0 | Chiude i mesi passati agli utenti (non ai responsabili) |

## 2. Accesso con LDAP CNR

Amministrazione → **Sistema → Plugin → "Autenticazione - LDAP CNR (PROTIS IBF)"**:

| Campo | Valore |
|---|---|
| Host / porta | `ldaps://hostingservice.src.cnr.it`, 636 (default) |
| Base DN | `o=cnr,c=it` |
| Bind DN e password | Utente tecnico LDAP fornito dal CNR al vostro istituto |
| Controllo sede | Attivo: entra solo chi ha un `departmentNumber` uguale al codice di una sede attiva |
| Ignora certificato / debug | Solo per diagnosi |

Per verificare dal server: `BIND_PW=… USER_PW=… php tools/test-ldap.php ldaps://… "<bind DN>" o=cnr,c=it nome.cognome`.

Al primo accesso PROTIS IBF crea l'utente Joomla e la scheda della persona con nome, email, matricola, contratto e sede.

## 3. Sedi e credenziali ePAS

In PROTIS IBF → **Impostazioni → Sedi dell'istituto e credenziali ePAS → + Aggiungi sede dell'istituto**:

- **Istituto, sigla, sede** (es. `Istituto di Biofisica`, `IBF`, `Palermo`);
- **Codice sede**: il codice ePAS/SIPER della sede, uguale al `departmentNumber` LDAP del personale;
- **Utente e password ePAS** con ruolo *gestore anagrafica* di quella sede (cifrati con libsodium, mai mostrati).

Solo il personale delle sedi **attive** può accedere. Dalla sezione *Test connessione ePAS* si sincronizza il mese
corrente di una persona per verificare le credenziali. Il nome della sede serve anche per il santo patrono nel
calendario (Genova, Milano, Palermo, Pisa, Roma, Trento e altre città principali).

### Colleghi di altri istituti

Un collega di un altro istituto CNR che lavora su un vostro progetto si abilita in **Persone → + Abilita colleghi di
altri istituti CNR** con il suo username: entra con le sue credenziali CNR e inserisce le ore a mano. Per leggere le sue
presenze dal **suo** ePAS:

1. *Impostazioni → ePAS di altri istituti → + Aggiungi sede di altro istituto*: codice sede e credenziali ePAS *gestore anagrafica* del suo istituto (le vostre credenziali vedono solo il vostro personale);
2. nella scheda del collega, riquadro *Presenze*: **Sede ePAS del suo istituto** → Salva.

Le sedi di altri istituti servono solo a leggere ePAS: non abilitano l'accesso del loro personale.

## 4. Codici di assenza

PROTIS IBF → **Codici assenza → Scarica codici da ePAS**. Per i codici nuovi (in verde) scegliere categoria
(Lavoro Agile, Telelavoro, Missione, Malattia, Ferie, Permessi, Altro), orario convenzionale (es. 07:12) ed eventuale
esclusione da *Riempi/Distribuisci* per le assenze di giornata intera.

## 5. Ruoli

Utente e Responsabile si assegnano direttamente in PROTIS IBF, nella scheda della persona (riquadro *Ruolo in PROTIS IBF*), da qualunque responsabile o amministratore. Gli amministratori si assegnano in Joomla → **Utenti → Gestione**. In entrambi i casi la persona deve aver fatto almeno un accesso:

| Gruppo | Permessi |
|---|---|
| Registered | Utente: proprio calendario, profilo, associazioni, timesheet |
| PROTIS Responsabili | Progetti, associazioni, persone, codici, calendari e dashboard di tutte le sedi |
| PROTIS Amministratori | Tutto, incluse impostazioni, credenziali ePAS e registro |

I gruppi sono creati all'installazione; i permessi (`protis.manage.all`, `core.admin`) si possono rivedere da
*Opzioni → Permessi*.

## 6. Progetti e associazioni

I responsabili creano i progetti (*Progetti → + Nuovo progetto*) con WP e intestazione del timesheet, poi associano le
persone (*Associazioni*). Gli utenti possono anche associarsi da soli ai progetti attivi dalla voce *Associazioni*.
Il dettaglio è nel [manuale utente](MANUALE-UTENTE.md).

## 7. Sincronizzazione ePAS

Il cron `protis:epas-sync` aggiorna il mese corrente (e, nei primi 7 giorni del mese, anche il precedente) per tutte
le persone attive con matricola e interruttore *Presenze* su "Da ePAS". Si può lanciare a mano:

```bash
sudo -u www-data php /var/www/protis/cli/joomla.php protis:epas-sync -n
```

## 8. Lista di controllo

- [ ] Opzioni: nome, istituto, logo, supporto
- [ ] Plugin LDAP con utente tecnico, accesso di prova riuscito
- [ ] Sedi con codice sede e credenziali ePAS, test di connessione riuscito
- [ ] Codici di assenza scaricati e classificati
- [ ] Responsabili e amministratori nei gruppi
- [ ] Cron ePAS e backup attivi (`/etc/cron.d/protis`)
- [ ] Lingua italiana di Joomla installata
