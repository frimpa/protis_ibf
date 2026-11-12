## Impostazioni (amministratori) {#impostazioni}

**Impostazioni** ha quattro parti:

- **Verifica di sistema**: controlla pdflatex, PhpSpreadsheet, LDAP, cifratura e cURL.
- **Sedi dell'istituto e credenziali ePAS**: una riga per sede con codice sede (il departmentNumber LDAP) e credenziali ePAS del ruolo "gestore anagrafica". Solo il personale delle sedi attive può accedere. Le credenziali sono cifrate e non vengono mai mostrate: per cambiarle si riscrivono.
- **ePAS di altri istituti (colleghi esterni)**: le sedi degli istituti dei colleghi esterni, con il loro codice sede e le credenziali ePAS di quell'istituto. Servono solo a leggere le presenze di quei colleghi: non abilitano l'accesso del loro personale e non compaiono tra le sedi dell'istituto.
- **Test connessione ePAS**: sincronizza il mese corrente di una persona e mostra l'esito.

"Opzioni avanzate e permessi" apre le opzioni del componente in Joomla (ruoli, permessi, blocco dei mesi passati). **Registro** elenca le operazioni fatte in PROTIS IBF (sincronizzazioni, modifiche, accessi).

**Ruoli.** Si assegnano in Joomla, in Utenti, con i gruppi: Registered (utenti), PROTIS Responsabili, PROTIS Amministratori.

**Aggiornare PROTIS IBF.** In Joomla, Sistema → Installa estensioni, si carica il nuovo pacchetto pkg_protis-X.Y.Z.zip: dati e impostazioni restano. Anche questa guida si aggiorna con il pacchetto.
