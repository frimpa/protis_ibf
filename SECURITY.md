# Sicurezza

PROTIS IBF tratta dati personali dei dipendenti (presenze, assenze, codice fiscale) e credenziali di servizio (LDAP, ePAS).

## Segnalare una vulnerabilità

Non aprite una Issue pubblica. Usate **Security → Report a vulnerability** di questo repository
(segnalazione privata di GitHub), indicando versione, passi per riprodurre e impatto. Riceverete una risposta il prima
possibile; la correzione verrà rilasciata in una nuova versione e citata nel CHANGELOG.

## Buone pratiche per chi installa

- Usare sempre HTTPS (`install.sh --https`) e tenere aggiornati sistema operativo, PHP e Joomla.
- Non mettere mai credenziali nel repository: LDAP e ePAS si inseriscono solo dall'amministrazione e dall'app.
- Conservare `configuration.php` nei backup: il suo `secret` cifra le credenziali ePAS.
- Limitare i gruppi *PROTIS Amministratori* e *Super Users* alle persone che gestiscono il servizio.
- Usare l'opzione "Blocca modifiche dopo N giorni" se i mesi rendicontati devono restare chiusi.
- Proteggere la cartella dei backup e copiarla fuori dal server.
