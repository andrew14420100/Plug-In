# Area Segreteria — fase 2: archivio soci

**Stato:** implementazione iniziale su branch dedicato; da verificare con CI e test WordPress prima dell'approvazione.

## Funzioni incluse

- Anagrafiche indipendenti dagli account WordPress.
- Creazione e modifica di nome, cognome, contatti, data di nascita, indirizzo e note interne.
- Ricerca per nome, cognome, email e telefono, con filtro stato e paginazione.
- Stati attivo, sospeso e archiviato.
- Archiviazione non distruttiva: il record resta disponibile nello storico.
- Controllo lato server dei permessi, campi obbligatori, formato data, email e duplicati email.
- Tracciamento delle operazioni nel registro attività senza inserire nel log email, telefono, indirizzo o note.
- Migrazione database in avanti e capability dedicata per la segreteria.
- Collegamento dalla panoramica segreteria.

## Esclusioni confermate

Nessuna tessera, codice a barre, integrazione Club Manager o funzione social. Non sono ancora incluse le relazioni familiari/tutori, le iscrizioni ai corsi, i pagamenti o l'importazione/esportazione CSV: saranno fasi successive.

## Criteri di accettazione

1. Solo utenti con la capability bracelli_soci_manage o amministratori possono consultare e modificare l'archivio.
2. Un utente non autorizzato non può salvare né archiviare dati tramite POST diretto.
3. Nome e cognome sono obbligatori; le date e le email vengono validate.
4. Un indirizzo email non può essere duplicato; l'email vuota resta consentita.
5. I record archiviati non vengono cancellati.
6. Query parametrizzate, output escapato, nonce del portale e audit log sono utilizzati.
7. I test automatici e la verifica manuale su WordPress/LocalWP devono passare prima del rilascio.
