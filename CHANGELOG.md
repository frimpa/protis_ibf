# Novità

## 3.27.2 – novembre 2026

- **Favicon** PROTIS IBF (il simbolo blu e giallo) nelle schede del browser e nei preferiti, anche nella pagina di accesso e nelle pagine di errore; all'installazione viene copiata anche nella radice del sito se lì non ce n'è già una.

## 3.27.1 – novembre 2026

- Calendario → Distribuisci: le ore di ogni giorno rispettano davvero il blocco scelto (prima, con totali piccoli o in modalità casuale, si potevano ottenere minuti sparsi). Nuovo blocco di **un'ora**, ora predefinito; se il totale non è un multiplo del blocco, i minuti in più vanno su un solo giorno.

## 3.27.0 – novembre 2026

- **Informativa sul trattamento dei dati**: link al PDF nella pagina di accesso ("Accedendo dichiari di aver letto l'informativa…") e a piè di pagina di tutte le pagine. Nuova opzione con il percorso del PDF; se vuota si usa l'unico PDF caricato in `images/informativa`.

## 3.26.4 – novembre 2026

- Persone: nella colonna Matricola, per chi non ce l'ha, compare **no matricola** invece di "manuale" (l'etichetta "manuale" resta accanto al nome, per chi compila a mano il calendario).

## 3.26.3 – novembre 2026

- Colleghi abilitati per username che non hanno ancora fatto il primo accesso: al posto del nominativo vuoto compare lo **username** (es. mario.rossi) in Associazioni, Persone, scheda persona, test ePAS e messaggi; dopo il primo accesso compare il nome da LDAP. Nell'elenco sono ordinati per username.

## 3.26.2 – novembre 2026

- Test connessione ePAS: il menu parte da *Scegli la persona…* (nessun nome preselezionato) e mostra sempre i due gruppi; se nessun collega esterno ha la sede ePAS impostata, il gruppo *Altri istituti* lo dice.

## 3.26.1 – novembre 2026

- Calendario (menu "Calendario di") e scelta delle persone nelle associazioni: accanto ai colleghi esterni compare **(Altro istituto)**.
- Impostazioni → Test connessione ePAS: persone divise tra *Personale dell'istituto* e *Altri istituti*, con la sede ePAS del collega esterno; nota su chi compare nell'elenco.

## 3.26.0 – novembre 2026

- **Elimina persona** solo per i colleghi di altri istituti (anche lato server); il personale dell'istituto si disattiva con l'interruttore Attivo.
- Una persona **disattivata non può più accedere**, nemmeno con le credenziali CNR (LDAP) o con un account locale, finché non viene riattivata; le sessioni già aperte si chiudono. La pagina di accesso mostra il motivo (profilo disattivato, sede non abilitata) invece del messaggio generico.

## 3.25.1 – novembre 2026

- Persone: solo le schede **Personale** e **Altri istituti**; gli account tecnici (amministratori senza matricola né progetti) non compaiono.

## 3.25.0 – novembre 2026

- Persone divisa in schede: **Personale** dell'istituto (predefinita, con la sincronizzazione ePAS per tutti), **Altri istituti** (con il modulo per abilitare i colleghi esterni) e **Account tecnici**: gli amministratori senza matricola né progetti (es. l'utente amministratore di Joomla) non compaiono più tra chi rendiconta.

## 3.24.1 – novembre 2026

- Scheda persona: per chi ha ore rendicontate il pulsante **Elimina persona** compare disattivato, con la spiegazione sotto (spegnere l'interruttore Attivo).

## 3.24.0 – novembre 2026

- Scheda persona: pulsante **Elimina persona** per chi è stato inserito per errore (es. un collega di altro istituto). Elimina scheda, associazioni, presenze e account Joomla, dopo conferma. Non è disponibile per sé stessi, per gli amministratori e per chi ha già ore rendicontate (che si può solo disattivare).

## 3.23.1 – novembre 2026

- Scheda persona: chi non ha ancora fatto il primo accesso compare con ruolo **Utente** (Registered, il ruolo predefinito) invece di "nessun account"; dopo il primo accesso un responsabile può renderlo Responsabile.

## 3.23.0 – novembre 2026

- Scheda persona: riquadro **Ruolo in PROTIS IBF** per scegliere *Utente* (Registered) o *Responsabile (amministrativo)* (PROTIS Responsabili), con salvataggio immediato: i responsabili possono abilitare altri colleghi alla gestione senza passare dall'amministrazione di Joomla. Non si possono cambiare il proprio ruolo né quello degli amministratori.
- Elenco Persone: etichetta *Responsabile* o *Amministratore* accanto al nome.

## 3.22.2 – novembre 2026

- Smartphone: il pulsante **Esci** in alto a destra non viene più tagliato sugli schermi più larghi (es. iPhone Pro Max); margine di sicurezza a destra su tutti gli iPhone.

## 3.22.1 – novembre 2026

- Tolta la voce **Registro** dal menu degli amministratori (le operazioni restano nel database, gli errori nei log del server). Menu più compatto: tutte le voci, Guida compresa, restano visibili anche sugli schermi dei portatili.

## 3.22.0 – novembre 2026

- Nuova voce **Scrivania PDF** per tutti gli utenti collegati: firmare, annotare, coprire, unire e dividere PDF nel browser, senza inviare i documenti al server. Si attiva indicando nelle Opzioni il percorso del file `index.html` di Scrivania PDF sul server; la pagina viene servita solo a chi ha effettuato l'accesso.

## 3.21.0 – novembre 2026 – prima versione pubblica

- Pubblicazione con licenza **EUPL-1.2**, per l'adozione da parte di altri istituti CNR, mantenendo il nome PROTIS IBF e i crediti all'autore (file NOTICE).
- Istituto, scritte, logo e referente locale configurabili dalle Opzioni; nessuna sede preinstallata.
- Gruppo dei responsabili rinominato **PROTIS Responsabili** (le installazioni esistenti lo aggiornano da sole).
- Santo patrono del calendario in base alla città della sede.
- Documentazione: installazione, configurazione per istituto, architettura, manuale utente, migrazione dalla V.2.

## 3.0 – 3.20 – novembre 2026 – sviluppo della V.3 all'Istituto di Biofisica

- **3.0** – Componente Joomla 6 con database normalizzato, migrazione dalla V.2 WordPress, installer per Ubuntu.
- **3.1 – 3.2** – Intestazione del timesheet configurabile per progetto e fascia loghi automatica; accesso LDAP CNR; pagina di accesso dedicata; colleghi di altri istituti abilitati per username.
- **3.3 – 3.5** – Calendario rivisto (mese da rendicontare, colori, strumenti Riempi/Distribuisci/Svuota); dashboard con filtri ed export per intervallo.
- **3.6 – 3.8** – Telelavoro, presenze manuali estese con pulsanti rapidi, codici di assenza scaricati da ePAS.
- **3.9 – 3.13** – CUP e Grant Agreement, Tipo di Ricerca, editor dell'intestazione con stampa per riga, anteprima della tabella, palette blu.
- **3.14 – 3.17** – Foto profilo, monte ore annuo per associazione, interruttori con salvataggio immediato, home con navigazione dei mesi.
- **3.18** – Interruttore presenze *Da ePAS / Inserimento manuale*, ePAS degli istituti dei colleghi esterni, interfaccia ottimizzata per smartphone.
- **3.19** – Gli utenti si associano da soli ai progetti; gruppi ridotti a tre.
- **3.20** – Guida integrata con "?" contestuale e stampa in PDF.
