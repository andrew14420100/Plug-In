# Club Manager → WordPress — integrazione da verificare

**STATO: solo requisiti, nessun connettore implementato né compatibilità dimostrata.**

## Scopo effettivo

Club Manager è un software commerciale installato su almeno due PC Windows con archivio condiviso (posizione e struttura da verificare). Continua a gestire i soci; il plugin Bracelli conserva un archivio di lavoro indipendente su WordPress. **Direzione UNICA Club Manager → WordPress**. I soci creati su WordPress restano solo sul sito. Il nuovo software desktop “Bracelli” è un progetto futuro distinto.

Il processo desktop deve avviarsi insieme a Windows, operare senza dover tenere aperta la finestra di Club Manager **solo se il formato e le condizioni di accesso lo consentono**, e non rallentare il programma. Se il PC/archivio sorgente è spento WordPress mostra la copia già sincronizzata e la data dell'ultimo aggiornamento.

## Verifiche P0 (nessuna modifica alla sorgente)

1. Verificare **nome esatto prodotto, produttore, versione e licenza** di Club Manager. Il nome è generico: non assumere che due prodotti omonimi usino lo stesso database.
2. Con autorizzazione locale, localizzare un archivio condiviso, formato dati e meccanismi backup/export, API/documentazione ufficiali, eventuali funzioni di audit/change events; verificare prima gli strumenti ufficiali.
3. Eseguire solo letture compatibili e supportate, idealmente su copia del DB/backup di prova; non aprire il database in modalità che possa bloccare o corrompere scritture normali, non decompilare o aggirare licenze/protezioni.
4. Mappare in modo documentato entità e campi: soci/clienti/utenti del gestionale, ID, tessere e barcode, famiglia/tutori, iscrizioni, corsi, documenti, pagamenti (sui pagamenti la decisione resta aperta), timestamp.
5. Stabilire canale di aggiornamento possibile: API, export/backup schedulabile, viste/read replica o lettura DB supportata. Se non è possibile accesso incrementale sicuro, proporre processo di import periodico anziché garantire “live”.
6. Verificare se archivio accessibile a programma chiuso con PC acceso, limiti privacy e autorizzazioni, formato e zeri iniziali del codice.
7. Decidere un solo **agente attivo per archivio** (lock/lease/failover solo se progettato e collaudato), per evitare invii doppi dai PC multipli.

## Contratto dati e processi (proposta)

- Adapter sorgente **read-only**, espone record con `source`, `external_id`, `schema_version`, `updated_at` se disponibile, `fields` whitelisted e fingerprint; non esporre file sorgenti/DB su internet.
- Componente Windows crea un batch firmato/autenticato via HTTPS verso endpoint REST dedicato WordPress. Rotazione/revoca credenziali. In assenza di data di modifica o API, calcolare differenze per fingerprint con snapshot leggeri e throttling.
- Arrivo WordPress: autenticazione dedicata, valida schema/capabilities applicative/scopes, idempotency key, persistenza checkpoint, matching multicampo con ID origine privilegiato quando certo, mai fondere automaticamente match ambigui.
- Elenco generale soci sincronizzati e **attivazioni operative** distinti; modifiche WordPress possono coesistere e richiedere reconciler per campo. Salvare ultimo dato sorgente e override locale separati per evitare perdita silenziosa.
- Dato importato segnato con provenienza, timestamp e stato; archiviazioni/cancellazioni provenienti dalla fonte **aggiornano lo stato**, preservando gli audit nei limiti privacy. Non cancellare dati reali per una mancata risposta/sorgente offline.
- Coda persistente con retry esponenziale, backoff, rate-limit, limiti batch, checkpoint, idempotenza, ripresa quando PC o rete ritorna; eventi in dubbio sul DB originale richiedono indagine invece di avanzare il cursore.
- Le modifiche a documenti sanitari/PII possono richiedere criteri d'esclusione: minimizzazione campo per campo, storage privato, nessun dump in GitHub e log redatti.
- Dashboard con stato agente/ultima sync, numero record, conflitti, fallimenti, replay autorizzato, diagnostica e audit.
- Nessun sync inverso, nemmeno implicito, con metodi di update sorgente.

## Test da eseguire prima di dichiarare compatibile

- Dataset sintetico 5.000+ soci, decine di migliaia di relazioni e record diversi, dati invalidi e duplicati.
- Avvio/stop Club Manager con PC acceso, avvio Windows, PC spento/ripristino, internet caduto, errori server HTTP, permessi scaduti e rotazione chiave.
- Retry idempotente, concorrenza agenti, mismatch barcode, override WordPress, conflitti per campo, cancellazioni/archiviazioni, documenti autorizzati, minori/tutori.
- Assicurare che operazioni in sola lettura NON blocchino Club Manager né rallentino gli altri PC; misurare impatto.
- Reconciler massivo con report: conteggi, controlli per record, blocco fine migrazione se errori critici; verifica con dati anonimizzati solo su autorizzazione e ambiente isolato.

## Dati disponibili quando fonte è offline

L'applicazione Club Manager può essere **chiusa** con file database persistenti, ma l'accessibilità tecnica dipende da file locks, formato, servizio DB e permessi: verificare, non assumere.
Se il PC e il disco che custodiscono i dati sono **spenti**, non è possibile leggere nuovi record; il sito usa l'ultima copia già ricevuta. Nessuna “sincronizzazione immediata” promettibile prima dei test del programma reale.

## Decisioni bloccanti

- Q252, Q291–293: esportazione/versione/formato da scoprire.
- Q265: standard codice a barre.
- Q319: chiave di correlazione record.
- Q328: sincronizzazione pagamenti rinviata.
- Q208 e Q340: liste d'attesa e UX attivazioni massive aperte.

## Confini di consegna

La Fase 1 del **core WordPress** NON deve includere un connettore fittizio. Si possono consegnare contratti/API documentati, dati simulati e una pagina “integrazione non configurata” onesta. L'integrazione reale è un milestone separato, avviabile solo dopo analisi autorizzata.
