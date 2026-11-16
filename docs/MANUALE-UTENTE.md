# Manuale utente di PROTIS IBF

Questo è lo stesso testo della **Guida** dentro l'applicazione (icona **?** in alto a destra), dove ogni ruolo vede
solo le sezioni che lo riguardano. Le prime sezioni sono per tutti gli utenti; da *Progetti* in poi per i
responsabili; *Impostazioni* per gli amministratori.

Il testo della Guida è nei file `src/com_protis/site/guide/*.md`: per personalizzarlo per il vostro istituto
modificate quei file e ricreate il pacchetto.

## Introduzione

PROTIS IBF serve a rendicontare le ore lavorate sui progetti e a produrre i timesheet mensili da firmare, per tutte le sedi dell'istituto.

Le presenze arrivano da ePAS per chi timbra; chi non usa ePAS (per esempio borsisti e colleghi di altri istituti) le inserisce a mano nel calendario. Le ore si attribuiscono ai work package (WP) dei progetti e da lì si genera il timesheet in PDF o Excel.

Quello che si vede dipende dal ruolo:

| Ruolo (gruppo Joomla) | Voci di menu | Cosa può fare |
| --- | --- | --- |
| Utente (Registered) | Home, Calendario, Associazioni, Scrivania PDF, Profilo, Guida | Rendiconta le proprie ore, si associa ai progetti, genera i propri timesheet |
| PROTIS Responsabili | in più Dashboard, Progetti, Persone, Codici assenza; Associazioni di tutti | Gestisce progetti, associazioni e calendari dei colleghi di tutte le sedi |
| PROTIS Amministratori | in più Impostazioni | Tutto, comprese sedi e credenziali ePAS |

## Accesso

Si entra con le credenziali istituzionali CNR, le stesse di SIPER: username nella forma nome.cognome e password.

1. Apri l'indirizzo di PROTIS IBF da computer, tablet o smartphone.
2. Inserisci username e password; l'icona a forma di occhio mostra la password mentre la scrivi.
3. Spunta "Ricordami su questo dispositivo" solo su un dispositivo personale.
4. Premi **Accedi**: si apre la Home.

Possono entrare il personale delle sedi abilitate e i colleghi di altri istituti CNR che un responsabile ha abilitato per username. Nome, email, matricola e contratto si aggiornano da LDAP a ogni accesso.

**Da smartphone e come app.** Il sito si adatta allo schermo: in verticale le voci principali sono nella barra in basso, le altre sotto "Altro". Si può installare come app: su iPhone, in Safari, Condividi → Aggiungi alla schermata Home; su Android, in Chrome, menu → Installa app. L'icona PROTIS IBF apre il sito a schermo intero.

**Problemi di accesso.** Se le credenziali sono rifiutate, verifica di accedere a SIPER con le stesse. Se SIPER funziona ma PROTIS IBF no, scrivi all'amministratore dal link in fondo alla pagina di accesso: probabilmente la tua sede o il tuo username non sono ancora abilitati.

## Home

La Home mostra a colpo d'occhio a che punto è la rendicontazione di un mese. All'apertura propone il **mese da rendicontare**, cioè il mese precedente.

**Cambiare mese.** Usa ‹ e › per scorrere un mese alla volta, oppure i menu mese e anno per saltare direttamente (per esempio ad Aprile 2025). Il pulsante **Oggi** riporta al mese in corso. "Apri il calendario" apre il calendario sullo stesso mese.

**Riepilogo del mese.**

- Ore rendicontabili: presenza + lavoro agile + telelavoro + missione.
- Ore rendicontate su progetti: le ore già attribuite ai WP.
- Attività Ordinaria residua: la parte delle ore rendicontabili non attribuita ai progetti.
- La barra indica la percentuale delle ore rendicontabili già attribuita ai progetti.

**Ore per progetto del mese.** Le ore del mese divise per progetto, con il totale.

**I miei progetti.** In fondo ci sono i progetti attivi a cui sei associato. L'interruttore "Mostra anche gli archiviati" aggiunge quelli chiusi. Se l'elenco è vuoto, il collegamento porta alla pagina Associazioni.

## Calendario

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
- **Distribuisci ore mensili**: indichi il totale del mese per ciascun WP (es. 40:00) e PROTIS IBF lo ripartisce sui giorni utili, in modo uniforme o casuale, arrotondando a 1, 5, 15 o 30 minuti. I WP compilati vengono prima azzerati nel mese.
- **Svuota ore del mese**: cancella le ore rendicontate del mese, per tutti i progetti, un progetto o un solo WP. Le presenze ePAS non vengono toccate.

Sotto il calendario ci sono il Riepilogo del mese e le Ore per progetto del mese, come nella Home.

## Genera timesheet

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

## Le mie associazioni

**Le mie associazioni** (voce Associazioni) elenca i progetti a cui sei associato e permette di associarti da solo a un progetto esistente.

1. In "Associati a un progetto" scegli il progetto (quelli già tuoi sono disattivati).
2. Scrivi le **Ore previste sul progetto da rendicontare** e, se il progetto lo prevede, il **Monte ore annuo**.
3. Premi **Associa**: il progetto compare subito nel calendario.

Nell'elenco puoi correggere le ore nei campi (compare Salva) e vedere le ore già rendicontate. La × toglie un'associazione solo finché non hai rendicontato ore su quel progetto; dopo, rivolgiti al responsabile. Se il progetto che cerchi non c'è, chiedi al responsabile di crearlo.

## Profilo

Il **Profilo** contiene i tuoi dati per il timesheet:

- nominativo, email e matricola: arrivano da LDAP e non si modificano;
- codice fiscale, tipo di contratto e data di assunzione;
- sede di afferenza: una sede dell'istituto dal menu oppure "Altra sede (scrivi)…";
- per i colleghi di altri istituti: istituto e sede di appartenenza;
- foto: fino a 10 MB, viene ridotta e ritagliata in automatico e compare in alto al posto delle iniziali.

La data di assunzione serve per il monte ore annuo quando l'associazione non lo indica: 1520 ore nei primi 3 anni, poi 1506.

## Scrivania PDF

Se l'amministratore l'ha attivata, la voce **Scrivania PDF** (da smartphone sotto "Altro") apre uno strumento per lavorare sui PDF direttamente nel browser: firmare, aggiungere testo, evidenziare, coprire parti del documento, ruotare e riordinare le pagine, unire e dividere PDF. È utile, per esempio, per firmare il timesheet appena scaricato.

I documenti **non vengono inviati al server**: restano sul tuo dispositivo e il PDF modificato si salva con un nuovo nome, senza toccare l'originale. "Apri a tutto schermo" la apre in una scheda a parte. La firma inserita è un'immagine, non una firma digitale qualificata.

## Domande frequenti

**Le presenze ePAS di oggi non ci sono.** Premi "Aggiorna da ePAS" nel calendario. Se l'errore persiste, avvisa l'amministratore.

**Il giorno è rosso.** Le ore sui progetti superano le ore rendicontabili: riduci le ore sui WP di quel giorno.

**Non trovo il mio progetto nel calendario.** Associati al progetto dalla voce Associazioni. Se il progetto non è nell'elenco, chiedi al responsabile di crearlo.

**Non riesco a togliere un'associazione.** Ci sono già ore rendicontate su quel progetto: solo un responsabile può toglierla.

**Il timesheet non si genera.** Completa codice fiscale, tipo di contratto e sede nel Profilo.

## Progetti

La pagina Progetti elenca i progetti con CUP o Grant Agreement, periodo, numero di WP e di persone. In alto a destra l'interruttore **Attivi / Archiviati** cambia elenco; sotto ci sono il filtro e **+ Nuovo progetto**. Una riga si apre toccandola.

**Dati del progetto.** Acronimo (obbligatorio), CUP, Grant Agreement, Codice del progetto, Titolo, Avviso / Call, Ente / Beneficiario, Sede proponente (una sede dell'istituto o "Altra sede" scritta a mano), date di Inizio, Fine ed eventuale Proroga.

**Work package.** Ogni progetto ha almeno un WP: le ore si attribuiscono ai WP. Si aggiungono, si rinominano e si possono disattivare.

**Intestazione del timesheet**, in quattro passi:

1. **Loghi**: si caricano anche più file insieme (PNG, JPG, GIF, WEBP) e si mettono in ordine da sinistra a destra; PROTIS IBF li compone in una fascia unica.
2. **Testo sotto i loghi**: facoltativo, solo se il progetto lo richiede. Qui si sceglie anche il **Tipo di Ricerca** (RI, SS o altro), stampato come prima riga dell'intestazione.
3. **Righe dell'intestazione**: per ogni riga un'etichetta e un valore, scelto tra i dati automatici (es. {cup}, {partita_iva}) o scritto come testo libero. La spunta **stampa** decide se la riga compare nel PDF. La spunta "Monte ore lavorativo annuo previsto" aggiunge la riga con il monte ore annuo della persona.
4. **Tabella delle ore**: righe in italiano o in inglese, con anteprima.

"Anteprima PDF" mostra il risultato con i dati salvati. Dalla scheda si può anche scaricare il **timesheet di tutte le persone del progetto** in un solo file.

**Archiviare un progetto.** Un progetto terminato non si archivia da solo alla data di fine: lo archivi tu spegnendo l'interruttore **Attivo** in alto a destra nella scheda (salva subito). Un progetto archiviato sparisce dai calendari e dalla Home, resta consultabile tra gli Archiviati e nella Dashboard, e si riattiva riaccendendo l'interruttore. Eliminare un progetto è possibile solo per il super utente.

## Associazioni (responsabili)

**Associazioni** collega le persone ai progetti: una persona vede nel calendario solo i progetti a cui è associata. L'elenco è raggruppato per persona e si filtra per progetto o per nome.

1. Apri "Nuova associazione" e scegli il progetto.
2. Spunta le persone; per ciascuna indica le **Ore previste sul progetto da rendicontare** e, se serve, il **Monte ore annuo**.
3. Salva. Le associazioni già esistenti non vengono sovrascritte: un avviso le elenca.

Nell'elenco le ore si correggono nei campi (compare Salva), "Azzera" svuota il valore e la × toglie l'associazione. Togliere un'associazione non cancella le ore già registrate nel calendario.

## Persone

**Persone** è divisa in schede: **Personale** dell'istituto (con la sincronizzazione ePAS per tutti), **Altri istituti** (i colleghi esterni abilitati per username, con il modulo per abilitarne altri). Gli account tecnici (amministratori senza matricola né progetti, come l'utente amministratore di Joomla) non compaiono perché non rendicontano. Ogni scheda elenca le persone con foto, matricola, sede, contratto e numero di progetti; l'etichetta "ePAS" o "manuale" indica da dove arrivano le presenze. Una riga apre la scheda della persona:

- interruttore **Attivo / Disattivato** in alto a destra: una persona disattivata non può accedere, nemmeno con le credenziali CNR, e non viene sincronizzata da ePAS; riaccendendo l'interruttore torna come prima;
- dati LDAP in sola lettura; codice fiscale, data di assunzione, contratto e sede modificabili;
- riquadro **Ruolo in PROTIS IBF**: *Utente* (gruppo Registered) oppure *Responsabile (amministrativo)* (gruppo PROTIS Responsabili), salvato subito. Così un responsabile può abilitare un collega alla gestione senza passare dall'amministrazione di Joomla. Ogni nuova persona entra come *Utente*; dopo il suo primo accesso la si può rendere *Responsabile*, e il cambio vale dal suo accesso successivo. Non si può cambiare il proprio ruolo né quello degli amministratori;
- riquadro **Presenze**: interruttore **Da ePAS / Inserimento manuale**, che salva subito. Spento, la persona compila a mano il calendario anche se ha la matricola (es. un borsista); senza matricola resta sempre manuale;
- Progetti associati, con le ore modificabili, e "Aggiungi una nuova associazione";
- solo per i colleghi di altri istituti, il pulsante **Elimina persona** (vedi le domande frequenti).

**Colleghi di altri istituti CNR.** Per far rendicontare su PROTIS IBF un collega di un altro istituto:

1. In Persone premi **+ Abilita colleghi di altri istituti CNR** e scrivi il suo username (nome.cognome); se vuoi, scegli subito il progetto.
2. Il collega entra con le sue credenziali CNR e inserisce le ore a mano.
3. Se in seguito deve usare il suo ePAS: l'amministratore aggiunge la sede del suo istituto in Impostazioni → ePAS di altri istituti; poi nella scheda del collega, riquadro Presenze, scegli **Sede ePAS del suo istituto** e salva.

## Dashboard rendicontazione

La **Dashboard rendicontazione** mostra per ogni progetto le ore rendicontate, le ore previste, il residuo e la percentuale di avanzamento. La scheda viene evidenziata oltre l'80% e in rosso oltre il 100%.

- In alto a destra l'interruttore **Progetti attivi / Progetti archiviati**.
- Filtri: un progetto e un periodo (Ore dal … al …). Senza date si contano tutte le ore dall'inizio del progetto a oggi. I risultati si aggiornano appena cambi un filtro; "Azzera filtri" torna all'inizio.
- Toccando una scheda si apre il **dettaglio per persona** e per WP; ogni persona apre il proprio calendario.
- **Esporta per intervallo**: per una persona, il timesheet dell'intera durata del progetto o di un periodo scelto, in PDF A3 o Excel.

## Codici di assenza

I **Codici di assenza** sono i codici di ePAS e non si aggiungono né si eliminano a mano.

1. Premi **Scarica codici da ePAS**: i codici nuovi compaiono in verde in cima all'elenco. Accanto c'è la data dell'ultimo scaricamento.
2. Per ogni codice nuovo scegli la **Categoria** e l'**Orario** (le ore che vale il giorno, es. 07:12; vuoto = nessuna ora).
3. Spunta **Escludi da Riempi/Distribuisci** per le assenze di giornata intera (malattia, ferie, riposo compensativo…).
4. Premi **Salva modifiche**.

Lavoro Agile, Telelavoro e Missione contano come ore rendicontabili; Malattia, Ferie, Permessi e Altro sono assenze. Il campo di ricerca filtra per codice o descrizione.

## Domande frequenti (responsabili)

**Un progetto è finito: come lo archivio?** Nella scheda del progetto spegni l'interruttore Attivo. Non succede in automatico alla data di fine.

**Un borsista non timbra in ePAS: cosa vede?** Se non ha matricola il calendario è già manuale. Se ha la matricola ma non deve usare ePAS, spegni l'interruttore Presenze nella sua scheda.

**Come abilito un collega di un altro istituto?** Persone → + Abilita colleghi di altri istituti CNR, con il suo username, poi associalo al progetto (o lo fa lui da Associazioni).

**Ho abilitato per errore un collega di un altro istituto: come lo tolgo?** Nella sua scheda premi **Elimina persona** (in basso a sinistra) e conferma: si cancellano scheda, associazioni, presenze e account. Si può fare solo per i colleghi di altri istituti e solo se non hanno ore rendicontate. In tutti gli altri casi spegni l'interruttore **Attivo**: la persona non può più accedere, nemmeno con le credenziali CNR, finché non la riattivi.

**Come rendo qualcuno responsabile?** Nella sua scheda in Persone, riquadro *Ruolo in PROTIS IBF*, scegli *Responsabile (amministrativo)*. Vale dal suo prossimo accesso.

## Impostazioni (amministratori)

**Impostazioni** ha quattro parti:

- **Verifica di sistema**: controlla pdflatex, PhpSpreadsheet, LDAP, cifratura e cURL.
- **Sedi dell'istituto e credenziali ePAS**: una riga per sede con codice sede (il departmentNumber LDAP) e credenziali ePAS del ruolo "gestore anagrafica". Solo il personale delle sedi attive può accedere. Le credenziali sono cifrate e non vengono mai mostrate: per cambiarle si riscrivono.
- **ePAS di altri istituti (colleghi esterni)**: le sedi degli istituti dei colleghi esterni, con il loro codice sede e le credenziali ePAS di quell'istituto. Servono solo a leggere le presenze di quei colleghi: non abilitano l'accesso del loro personale e non compaiono tra le sedi dell'istituto.
- **Test connessione ePAS**: sincronizza il mese corrente di una persona e mostra l'esito.

"Opzioni avanzate e permessi" apre le opzioni del componente in Joomla (ruoli, permessi, blocco dei mesi passati). Le operazioni (sincronizzazioni, modifiche) restano registrate nel database; gli errori del server sono nei log di Apache e in `/var/log/protis/`.

**Ruoli.** Si assegnano in Joomla, in Utenti, con i gruppi: Registered (utenti), PROTIS Responsabili, PROTIS Amministratori.

**Aggiornare PROTIS IBF.** In Joomla, Sistema → Installa estensioni, si carica il nuovo pacchetto pkg_protis-X.Y.Z.zip: dati e impostazioni restano. Anche questa guida si aggiorna con il pacchetto.
