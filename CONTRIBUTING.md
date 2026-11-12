# Contribuire a PROTIS IBF

Segnalazioni, proposte e correzioni sono benvenute, in particolare da altri istituti CNR che adottano PROTIS IBF.

## Segnalare un problema

Aprite una **Issue** indicando versione di PROTIS IBF, Joomla e PHP, i passi per riprodurre il problema e cosa vi
aspettavate. **Non incollate dati personali, credenziali, log con nomi o codici fiscali.** Per problemi di sicurezza
seguite [SECURITY.md](SECURITY.md).

## Proporre modifiche

1. Fate un fork e create un ramo (`fix/…` o `feature/…`).
2. Preparate un ambiente di prova: Joomla 6 con PHP 8.3+, installate il pacchetto creato con `build/build.sh`.
3. Mantenete lo stile del codice: PHP con `declare`/namespace del componente, logica nei servizi
   (`administrator/src/Service`), controller sottili, testi dell'interfaccia in italiano.
4. Ogni modifica di schema va in un nuovo `administrator/sql/updates/mysql/X.Y.Z.sql` e in `install.sql`.
5. Aggiornate la Guida (`src/com_protis/site/guide/*.md`) e il CHANGELOG se cambia il comportamento visibile.
6. Provate su computer e su smartphone e aprite una Pull Request con la descrizione delle modifiche.

Contribuendo accettate che il vostro codice sia distribuito con la licenza EUPL-1.2 del progetto.
