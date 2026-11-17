## Introduzione {#introduzione}

PROTIS IBF serve a rendicontare le ore lavorate sui progetti e a produrre i timesheet mensili da firmare, per tutte le sedi dell'istituto.

Le presenze arrivano da ePAS per chi timbra; chi non usa ePAS (per esempio borsisti e colleghi di altri istituti) le inserisce a mano nel calendario. Le ore si attribuiscono ai work package (WP) dei progetti e da lì si genera il timesheet in PDF o Excel.

Quello che si vede dipende dal ruolo:

| Ruolo (gruppo Joomla) | Voci di menu | Cosa può fare |
| --- | --- | --- |
| Utente (Registered) | Home, Calendario, Associazioni, Scrivania PDF, Profilo, Guida | Rendiconta le proprie ore, si associa ai progetti, genera i propri timesheet |
| PROTIS Responsabili | in più Dashboard, Progetti, Persone, Codici assenza; Associazioni di tutti | Gestisce progetti, associazioni e calendari dei colleghi di tutte le sedi |
| PROTIS Amministratori | in più Impostazioni | Tutto, comprese sedi e credenziali ePAS |

## Accesso {#accesso}

Si entra con le credenziali istituzionali CNR, le stesse di SIPER: username nella forma nome.cognome e password.

1. Apri l'indirizzo di PROTIS IBF da computer, tablet o smartphone.
2. Inserisci username e password; l'icona a forma di occhio mostra la password mentre la scrivi.
3. Spunta "Ricordami su questo dispositivo" solo su un dispositivo personale.
4. Premi **Accedi**: si apre la Home.

Possono entrare il personale delle sedi abilitate e i colleghi di altri istituti CNR che un responsabile ha abilitato per username. Nome, email, matricola e contratto si aggiornano da LDAP a ogni accesso.

**Da smartphone e come app.** Il sito si adatta allo schermo: in verticale le voci principali sono nella barra in basso, le altre sotto "Altro". Si può installare come app: su iPhone, in Safari, Condividi → Aggiungi alla schermata Home; su Android, in Chrome, menu → Installa app. L'icona PROTIS IBF apre il sito a schermo intero.

**Problemi di accesso.** Se le credenziali sono rifiutate, verifica di accedere a SIPER con le stesse. Se SIPER funziona ma PROTIS IBF no, scrivi all'amministratore dal link in fondo alla pagina di accesso: probabilmente la tua sede o il tuo username non sono ancora abilitati.

## Home {#home}

La Home mostra a colpo d'occhio a che punto è la rendicontazione di un mese. All'apertura propone il **mese da rendicontare**, cioè il mese precedente.

**Cambiare mese.** Usa ‹ e › per scorrere un mese alla volta, oppure i menu mese e anno per saltare direttamente (per esempio ad Aprile 2025). Il pulsante **Oggi** riporta al mese in corso. "Apri il calendario" apre il calendario sullo stesso mese.

**Riepilogo del mese.**

- Ore rendicontabili: presenza + lavoro agile + telelavoro + missione.
- Ore rendicontate su progetti: le ore già attribuite ai WP.
- Attività Ordinaria residua: la parte delle ore rendicontabili non attribuita ai progetti.
- La barra indica la percentuale delle ore rendicontabili già attribuita ai progetti.

**Ore per progetto del mese.** Le ore del mese divise per progetto, con il totale.

**I miei progetti.** In fondo ci sono i progetti attivi a cui sei associato. L'interruttore "Mostra anche gli archiviati" aggiunge quelli chiusi. Se l'elenco è vuoto, il collegamento porta alla pagina Associazioni.

## Calendario {#calendario}

Nel calendario si attribuiscono le ore di ogni giorno ai WP dei progetti. Si apre sul mese da rendicontare; si cambia mese con ‹ ›, con i menu mese/anno o con **Oggi**. Su computer è una griglia mensile, su smartphone un elenco di giorni.

**Colori dei giorni.**

| Colore | Significato |
| --- | --- |
| Verde | Completo: tutte le ore rendicontabili sono attribuite |
| Giallo | Parziale: restano ore da attribuire |
| Rosso | Oltre il rendicontabile: ore sui progetti superiori a quelle disponibili |
| Viola | Assenza (ferie, malattia, permessi…) |
| Grigio | Non rendicontabile (festivo, weekend senza presenza) |

**Inserire le ore di un giorno.** Tocca il giorno: si apre una finestra con l'elenco dei tuoi WP. Scrivi le ore in formato hh:mm (es. 3:30) o decimale (es. 3,5) accanto a ciascun WP e premi **Salva**. La somma non può superare le ore rendicontabili del giorno.

**Presenze da ePAS.** Se hai la matricola e usi ePAS, presenze e assenze arrivano da sole (più volte al giorno). "Aggiorna da ePAS" le rilegge subito. Queste presenze non si modificano a mano: per correggerle si corregge ePAS.

**Presenze inserite a mano.** Chi non usa ePAS trova nella finestra del giorno anche la sezione "Presenza del giorno": Lavoro, Lavoro Agile, Telelavoro, Missione, Malattia, Ferie, Permessi, Altro. I pulsanti rapidi compilano 7:12 per Giornata, Lavoro Agile, Telelavoro, Missione, Ferie, Malattia o Permessi; Svuota azzera il giorno. Anche sabato e domenica si possono compilare, per esempio per una missione.

**Strumenti** (menu in alto):

- **Riempi il mese con un progetto**: assegna a un WP le ore libere di ogni giorno utile. Salta i giorni di malattia, ferie, permessi e i codici esclusi; le ore già messe su altri progetti restano.
- **Distribuisci ore mensili**: indichi il totale del mese per ciascun WP (es. 40:00) e PROTIS IBF lo ripartisce sui giorni utili, in modo uniforme o casuale, a blocchi di un'ora (predefinito), mezz'ora, un quarto d'ora, 5 minuti o un minuto: ogni giorno riceve un numero intero di blocchi. I WP compilati vengono prima azzerati nel mese.
- **Svuota ore del mese**: cancella le ore rendicontate del mese, per tutti i progetti, un progetto o un solo WP. Le presenze ePAS non vengono toccate.

Sotto il calendario ci sono il Riepilogo del mese e le Ore per progetto del mese, come nella Home.

## Genera timesheet {#timesheet}

Il timesheet si scarica dal riquadro "Genera timesheet" del calendario (da smartphone è in fondo alla pagina, dopo i riepiloghi).

1. Scegli il **Progetto**.
2. Indica **Dal mese** e **Al mese**: con più mesi il file contiene una pagina per mese.
3. Scegli il **Formato** e premi **Scarica timesheet**.

| Formato | Per cosa |
| --- | --- |
| PDF | Stampa e firma: intestazione, loghi e tabella ore impostati per quel progetto |
| Excel (.xlsx) | Controlli e rielaborazioni |
| LibreOffice (.ods) | Come Excel, per LibreOffice |
| CSV | Importazione in altri programmi |
| LaTeX (.tex) | Modifiche avanzate del PDF |

Per generare il timesheet servono codice fiscale, tipo di contratto e sede nel profilo: se mancano, la Home lo segnala con il collegamento "Completa il profilo". Prima di scaricare controlla che i giorni del mese siano verdi.

## Le mie associazioni {#associazioni}

**Le mie associazioni** (voce Associazioni) elenca i progetti a cui sei associato e permette di associarti da solo a un progetto esistente.

1. In "Associati a un progetto" scegli il progetto (quelli già tuoi sono disattivati).
2. Scrivi le **Ore previste sul progetto da rendicontare** e, se il progetto lo prevede, il **Monte ore annuo**.
3. Premi **Associa**: il progetto compare subito nel calendario.

Nell'elenco puoi correggere le ore nei campi (compare Salva) e vedere le ore già rendicontate. La × toglie un'associazione solo finché non hai rendicontato ore su quel progetto; dopo, rivolgiti al responsabile. Se il progetto che cerchi non c'è, chiedi al responsabile di crearlo.

## Profilo {#profilo}

Il **Profilo** contiene i tuoi dati per il timesheet:

- nominativo, email e matricola: arrivano da LDAP e non si modificano;
- codice fiscale, tipo di contratto e data di assunzione;
- sede di afferenza: una sede dell'istituto dal menu oppure "Altra sede (scrivi)…";
- per i colleghi di altri istituti: istituto e sede di appartenenza;
- foto: fino a 10 MB, viene ridotta e ritagliata in automatico e compare in alto al posto delle iniziali.

La data di assunzione serve per il monte ore annuo quando l'associazione non lo indica: 1520 ore nei primi 3 anni, poi 1506.

## Scrivania PDF {#scrivania}

Se l'amministratore l'ha attivata, la voce **Scrivania PDF** (da smartphone sotto "Altro") apre uno strumento per lavorare sui PDF direttamente nel browser: firmare, aggiungere testo, evidenziare, coprire parti del documento, ruotare e riordinare le pagine, unire e dividere PDF. È utile, per esempio, per firmare il timesheet appena scaricato.

I documenti **non vengono inviati al server**: restano sul tuo dispositivo e il PDF modificato si salva con un nuovo nome, senza toccare l'originale. "Apri a tutto schermo" la apre in una scheda a parte. La firma inserita è un'immagine, non una firma digitale qualificata.

## Domande frequenti {#faq}

**Le presenze ePAS di oggi non ci sono.** Premi "Aggiorna da ePAS" nel calendario. Se l'errore persiste, avvisa l'amministratore.

**Il giorno è rosso.** Le ore sui progetti superano le ore rendicontabili: riduci le ore sui WP di quel giorno.

**Non trovo il mio progetto nel calendario.** Associati al progetto dalla voce Associazioni. Se il progetto non è nell'elenco, chiedi al responsabile di crearlo.

**Non riesco a togliere un'associazione.** Ci sono già ore rendicontate su quel progetto: solo un responsabile può toglierla.

**Il timesheet non si genera.** Completa codice fiscale, tipo di contratto e sede nel Profilo.
