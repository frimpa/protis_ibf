# Migrazione da PROTIS IBF V.2 (WordPress)

Serve solo a chi usava la versione 2 di PROTIS IBF, basata su WordPress. Le nuove installazioni possono ignorarla.

Il comando `protis:migrate-v2` importa dai due database della V.2: codici di assenza, tipi di contratto, sedi e
credenziali ePAS (decifrate con la chiave V.2 e ri-cifrate con quella della V.3), persone con account Joomla e ruoli,
progetti con WP e loghi, associazioni, calendari e ore. I JSON ePAS salvati dalla V.2 servono a ricostruire i codici di
assenza di ogni giorno. Il comando è ripetibile: aggiorna senza duplicare.

## Prima

- [ ] Backup dei due database V.2 (WordPress e progetti/calendari) e della cartella della V.2.
- [ ] Installare la V.3 su un nuovo host o in una nuova cartella ([INSTALLAZIONE.md](INSTALLAZIONE.md)).
- [ ] Rendere leggibile al server la cartella V.2: servono `admin/listofproject/*` (loghi),
      `admin/calendario/JSON/*` (codici di assenza) e `.env/chiave.env` (credenziali ePAS).

## Migrazione

```bash
sudo -u www-data php /var/www/protis/cli/joomla.php protis:migrate-v2 \
  --db-user=root --db-password='***' \
  --wp-db=<database WordPress> --proj-db=<database progetti> --wp-prefix=<prefisso>_ \
  --v2-path=/var/www/html/<cartella V.2>
```

La password si può passare anche con la variabile d'ambiente `PROTIS_V2_DB_PASSWORD`. Opzione utile:
`--no-users` (gli account Joomla saranno creati al primo accesso LDAP); `--sal=ACR1,ACR2` per i progetti che
nella V.2 avevano l'intestazione SAL scritta nel codice (P.IVA CNR, SAL n. 1, monte ore annuo 1506).

Il comando stampa un riepilogo (persone, progetti, WP, associazioni, giornate, ore) e gli avvisi da controllare, ad
esempio CUP duplicati tra progetti o persone senza matricola (che restano con presenze manuali).

## Dopo

- [ ] Confrontare a campione alcuni timesheet V.2 e V.3 (stesso mese, persona, progetto).
- [ ] Verificare sedi e credenziali ePAS (*Impostazioni → Test connessione ePAS*).
- [ ] Controllare i gruppi dei responsabili.
- [ ] Comunicare il nuovo indirizzo e la possibilità di installare l'app sullo smartphone.
- [ ] Mettere la V.2 in sola lettura, poi dismetterla.
