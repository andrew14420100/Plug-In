# Area Segreteria — roadmap di sviluppo

**Stato:** sviluppo per fasi su branch dedicato. Nessuna release e nessuna modifica al sito pubblico.

## Esclusioni confermate

Questa roadmap non include:
- tessere associative;
- codici a barre;
- integrazione con Club Manager;
- funzioni social.

L'archivio soci resta previsto, ma senza tessere e codici a barre. I pagamenti sono registrazioni manuali, senza gateway obbligatori.

## Fase 1 — Dashboard operativa della segreteria

**Obiettivo:** dare alla segreteria una pagina iniziale chiara, con dati realmente presenti e collegamenti alle funzioni già disponibili.

- Panoramica dedicata accessibile solo a chi può gestire i corsi.
- Conteggio reale dei corsi attivi, degli orari attivi e delle sale attive.
- Conteggio reale degli orari senza insegnante e senza sala/sede.
- Accessi rapidi a corsi e orari, creazione corso, presenze insegnanti, sale e calendario.
- Riquadri informativi per le aree future; nessun dato inventato e nessuna azione presentata come funzionante se il backend non esiste.
- Layout responsive, tastiera e indicatori accessibili.
- Riutilizzo del portale e dello stile Bracelli esistenti.

**Criteri di accettazione**
1. La panoramica è la prima vista per un account Segreteria autorizzato.
2. Un insegnante o un socio non può aprire la panoramica modificando manualmente il parametro vista.
3. I contatori sono derivati dal database, non da fixture o valori fissi.
4. Ogni accesso rapido conduce a una vista esistente e autorizzata.
5. Le aree non ancora sviluppate sono marcate chiaramente come pianificate.
6. Il portale resta utilizzabile su schermi stretti e senza dipendere da JavaScript.
7. PHPCS, test PHP, test d'integrazione, E2E e CI sono verdi prima di dichiarare la fase verificata.
8. Lo ZIP viene provato su un WordPress locale; il sito pubblico non viene modificato.

## Fase 2 — Archivio soci e nuclei familiari

- Archivio separato dagli account WordPress, ricerca e filtri.
- Anagrafiche, contatti, stato, note e storico delle modifiche.
- Famiglie, genitori/tutori e minorenni con relazioni verificabili.
- Schede complete/incomplete, importazione con anteprima, esportazione autorizzata e controlli duplicati.
- Nessuna tessera, nessun codice a barre, nessun collegamento con Club Manager.

## Fase 3 — Iscrizioni e gestione partecipanti

- Collegamento tra socio, corso e periodo d'iscrizione.
- Stato e scadenza dell'iscrizione al singolo corso.
- Capienza, disponibilità, trasferimenti e lista d'attesa configurabile.
- Presenze degli allievi separate da quelle degli insegnanti.
- Eccezioni, festività, sospensioni e recuperi.

## Fase 4 — Insegnanti e personale

- Anagrafiche e disponibilità degli insegnanti.
- Assegnazioni, sostituzioni e storico.
- Area personale dell'insegnante e richieste alla segreteria.
- Eventuali turni del personale come modulo separato e facoltativo.

## Fase 5 — Quote, tariffe e incassi registrati

- Listini con storico e regole tariffarie configurabili.
- Sconti manuali con permessi dedicati.
- Acconti, rate, saldi, scadenze e storico dei pagamenti registrati.
- Riepiloghi ed esportazioni; nessun gateway obbligatorio.
- I documenti non saranno chiamati fatture fiscali senza verifica dei requisiti applicabili.

## Fase 6 — Documenti, moduli e richieste

- Documenti protetti e accessibili solo ai ruoli autorizzati.
- Moduli online e campi condizionali.
- Documenti mancanti, scadenze e richieste da evadere.
- Registro delle modifiche e delle consultazioni sensibili, ove necessario.

## Fase 7 — Comunicazioni e notifiche ufficiali

- Avvisi mirati per ruolo o gruppo.
- Notifiche interne con stato di lettura.
- Promemoria per lezioni, iscrizioni e scadenze.
- Newsletter con consenso, disiscrizione e limiti di invio.
- Nessuna chat sociale, feed o interazione social.

## Fase 8 — Prenotazioni, sale e risorse

- Prenotazione di lezioni, prove e appuntamenti.
- Disponibilità, capienza, cancellazioni e prevenzione delle sovrapposizioni.
- Gestione di sale e attrezzature, guasti e manutenzioni.

## Fase 9 — Report e automazioni

- Statistiche su soci, iscrizioni, corsi e presenze.
- Report economici visibili solo a chi è autorizzato.
- Promemoria programmati, esportazioni e attività ricorrenti.
- Code, retry e registrazione degli errori compatibili con l'hosting condiviso.

## Fase 10 — Sicurezza e affidabilità

- Permessi granulari lato server e 2FA per gli amministratori.
- Registro delle operazioni, protezione dei dati e gestione delle richieste privacy.
- Backup, verifica d'integrità e prove di ripristino.
- Test di regressione, compatibilità, prestazioni e aggiornamenti.
- Rilascio solo dopo revisione della pull request e approvazione.

## Regole di sviluppo

- Una fase per volta; ogni fase ha una propria pull request e criteri di accettazione.
- Branch dedicati, nessun push diretto a main.
- Nessun dato reale dei soci nel repository pubblico.
- Nessuna funzione finta in produzione: se manca il backend, l'interfaccia lo dichiara.
- Nessuna modifica al sito pubblico senza autorizzazione esplicita.
- Riutilizzare i moduli esistenti per corsi, sale e presenze; non duplicarli.
