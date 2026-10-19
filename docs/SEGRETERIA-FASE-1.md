# Area Segreteria — Fase 1: dashboard operativa

**Branch:** feature/segreteria-fase-1  
**Stato:** implementazione iniziale, da verificare tramite CI e collaudo locale.  
**Ambito:** solo il portale dell'area riservata; nessuna modifica al sito pubblico.

## Implementazione

- Aggiunta una panoramica iniziale dedicata agli utenti autorizzati alla gestione dei corsi.
- Aggiunti indicatori derivati dal database per corsi attivi, orari attivi e sale attive.
- Aggiunti contatori per orari senza insegnante e senza collocazione.
- Aggiunti collegamenti alle viste esistenti di catalogo, orari, sale, calendario e presenze insegnanti.
- Le aree soci, iscrizioni dei partecipanti, pagamenti e documenti sono mostrate come pianificate, senza contatori fittizi.
- Aggiunto uno stile responsive coerente con il portale esistente.

## Controlli da eseguire

- [ ] PHPCS sul plugin.
- [ ] Test unitari e d'integrazione.
- [ ] E2E: la Segreteria vede la panoramica come vista iniziale.
- [ ] E2E: istruttore e socio non possono accedere alla panoramica modificando l'URL.
- [ ] Verifica dei conteggi con database vuoto, dati attivi, record archiviati e orari senza assegnazioni.
- [ ] Verifica di tutti i collegamenti rapidi.
- [ ] Verifica layout mobile, focus da tastiera e assenza di errori nel log WordPress.
- [ ] Creazione ZIP e installazione in LocalWP con dati sintetici.
- [ ] Approvazione prima di merge o release.

## Limiti noti

Questa fase non implementa ancora l'archivio soci, le iscrizioni, i pagamenti, i documenti o le notifiche. Non contiene tessere, codici a barre, Club Manager o funzioni social.
