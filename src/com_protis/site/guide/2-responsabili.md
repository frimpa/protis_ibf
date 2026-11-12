## Progetti {#progetti}

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

## Associazioni (responsabili) {#associazioni-responsabili}

**Associazioni** collega le persone ai progetti: una persona vede nel calendario solo i progetti a cui è associata. L'elenco è raggruppato per persona e si filtra per progetto o per nome.

1. Apri "Nuova associazione" e scegli il progetto.
2. Spunta le persone; per ciascuna indica le **Ore previste sul progetto da rendicontare** e, se serve, il **Monte ore annuo**.
3. Salva. Le associazioni già esistenti non vengono sovrascritte: un avviso le elenca.

Nell'elenco le ore si correggono nei campi (compare Salva), "Azzera" svuota il valore e la × toglie l'associazione. Togliere un'associazione non cancella le ore già registrate nel calendario.

## Persone {#persone}

**Persone** elenca il personale con foto, matricola, sede, contratto e numero di progetti; l'etichetta "ePAS" o "manuale" indica da dove arrivano le presenze. Una riga apre la scheda della persona:

- interruttore **Attivo / Disattivato** in alto a destra: una persona disattivata non accede e non viene sincronizzata;
- dati LDAP in sola lettura; codice fiscale, data di assunzione, contratto e sede modificabili;
- riquadro **Presenze**: interruttore **Da ePAS / Inserimento manuale**, che salva subito. Spento, la persona compila a mano il calendario anche se ha la matricola (es. un borsista); senza matricola resta sempre manuale;
- Progetti associati, con le ore modificabili, e "Aggiungi una nuova associazione".

**Colleghi di altri istituti CNR.** Per far rendicontare su PROTIS IBF un collega di un altro istituto:

1. In Persone premi **+ Abilita colleghi di altri istituti CNR** e scrivi il suo username (nome.cognome); se vuoi, scegli subito il progetto.
2. Il collega entra con le sue credenziali CNR e inserisce le ore a mano.
3. Se in seguito deve usare il suo ePAS: l'amministratore aggiunge la sede del suo istituto in Impostazioni → ePAS di altri istituti; poi nella scheda del collega, riquadro Presenze, scegli **Sede ePAS del suo istituto** e salva.

## Dashboard rendicontazione {#dashboard}

La **Dashboard rendicontazione** mostra per ogni progetto le ore rendicontate, le ore previste, il residuo e la percentuale di avanzamento. La scheda viene evidenziata oltre l'80% e in rosso oltre il 100%.

- In alto a destra l'interruttore **Progetti attivi / Progetti archiviati**.
- Filtri: un progetto e un periodo (Ore dal … al …). Senza date si contano tutte le ore dall'inizio del progetto a oggi. I risultati si aggiornano appena cambi un filtro; "Azzera filtri" torna all'inizio.
- Toccando una scheda si apre il **dettaglio per persona** e per WP; ogni persona apre il proprio calendario.
- **Esporta per intervallo**: per una persona, il timesheet dell'intera durata del progetto o di un periodo scelto, in PDF A3 o Excel.

## Codici di assenza {#codici}

I **Codici di assenza** sono i codici di ePAS e non si aggiungono né si eliminano a mano.

1. Premi **Scarica codici da ePAS**: i codici nuovi compaiono in verde in cima all'elenco. Accanto c'è la data dell'ultimo scaricamento.
2. Per ogni codice nuovo scegli la **Categoria** e l'**Orario** (le ore che vale il giorno, es. 07:12; vuoto = nessuna ora).
3. Spunta **Escludi da Riempi/Distribuisci** per le assenze di giornata intera (malattia, ferie, riposo compensativo…).
4. Premi **Salva modifiche**.

Lavoro Agile, Telelavoro e Missione contano come ore rendicontabili; Malattia, Ferie, Permessi e Altro sono assenze. Il campo di ricerca filtra per codice o descrizione.

## Domande frequenti (responsabili) {#faq-responsabili}

**Un progetto è finito: come lo archivio?** Nella scheda del progetto spegni l'interruttore Attivo. Non succede in automatico alla data di fine.

**Un borsista non timbra in ePAS: cosa vede?** Se non ha matricola il calendario è già manuale. Se ha la matricola ma non deve usare ePAS, spegni l'interruttore Presenze nella sua scheda.

**Come abilito un collega di un altro istituto?** Persone → + Abilita colleghi di altri istituti CNR, con il suo username, poi associalo al progetto (o lo fa lui da Associazioni).

**Come rendo qualcuno responsabile?** In Joomla, Utenti: aggiungi la persona al gruppo PROTIS Responsabili.
