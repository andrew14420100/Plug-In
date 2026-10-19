# Roadmap e criteri di accettazione

## Principio

Architettura completa pianificata adesso, **sviluppo una fase alla volta**, senza dichiarare completato ciò che esiste solo nell'interfaccia. Il sito pubblico NON è un ambiente test. Tutte le funzionalità con dipendenze non ancora verificate rimangono disattivate, non simulate.

## Fase 0 — Inventario e ADR (iniziare qui)

**Deliverable:**
- ADR architettura `docs/ADR-001-core.md` con pro/contro, schema preliminare, matrice moduli e dipendenze, rischi TopHost.
- Verifica repo, versioni WordPress/PHP e struttura Elementor in ambiente sicuro, senza produzione (se non verificabili, segnalarlo senza inventare).
- Inventario dei plugin usati e piano di sostituzione. NON disattivarli.
- Piano LocalWP e fixture sintetiche con 5.000+ soci; piano migrazioni idempotenti e permessi.
- Ricognizione scritta di Club Manager: dati da acquisire per eventuale sincronizzazione (nessuna modifica al software reale).
- Gate: analisi approvata dall'utente prima di implementare.

## Fase 1 — Core WordPress e dashboard

**Deliverable software reali:**
- Plugin ZIP valido che si installa/attiva/disattiva senza fatal error e senza perdita dati su LocalWP.
- Dashboard Bracelli moderna con due contesti amministratore/dipendente, menu configurabile, pulsante WP e recovery accessibile anche se JS si guasta; mobile.
- Preferenze grafica individuali, temi chiaro/scuro, widget base genuini, preferiti, sistema modularità/dipendenze/manifest, schede e stato.
- Impostazioni centralizzate, migrazioni versionate, capabilities server-side, registrazione audit e diagnostica essenziale.
- Test automatici PHP/JS + smoke WordPress; packaging ZIP GitHub. **Non mostrare contatori soci/incassi inventati**: usare “nessun dato” quando moduli non pronti.
- Gate: test e verifica manuale della Fase 1, PR approvata.

## Fase 2 — Archivio soci e tessere su dataset sintetico

**Dipende da:** core, permessi, migratori, audit.
- Modello soci, famiglie, barcode come stringa, id origine, stati distinti tessera/iscrizione/corsi/attivazione.
- Ricerca tessera su **archivio WordPress di test già popolato con dati sintetici**, anteprima, deduplica e attivazione esplicita. Permessi per modifiche/categorie e informazioni riservate.
- Scheda completa/incompleta, errori, ricerca alternativa, rinnovi; import CSV/JSON da file di test simulati con anteprima/checkpoint/rollback dove possibile.
- Elenco sincronizzati vs attivati, test migliaia record e query indicizzate.
- **Non presentare come “integrazione Club Manager”** finché non esiste un collegamento reale verificato.
- Gate: test dei casi limite, accessi non autorizzati, 5.000+ records, ZIP verificato.

## Fase 3 — Connettore Club Manager (condizionale)

**NON partire finché:** l'utente abbia autorizzato analisi sul proprio PC e si sappia formato/locazione/accessi; requisiti `docs/CLUB_MANAGER.md`.

- Studio read-only e prove su copia autorizzata/anonimizzata.
- Implementare adapter Windows e ingestione WordPress one-way solo se supportati; altrimenti proporre import periodico documentato.
- Sicurezza, code, reprocessing, conflict resolution, audit, monitor e controllo impatto PC.
- Gate: confronto con fonte, prova offline/riavvio, test sicurezza e approvazione; nessuna promessa di tempo reale senza misura.

## Fase 4 — Corsi, istruttori, presenze e prenotazioni

> **Aggiornamento (ottobre 2026):** la parte «Corsi» è stata anticipata e ha un piano proprio, mostrato nella dashboard (widget «Avanzamento del progetto», fonte unica `src/Modules/Dashboard/Roadmap.php`):
>
> | Fase Corsi | Contenuto | Stato nella versione 0.4.1 |
> | --- | --- | --- |
> | 0 — Stabilizzazione e audit | correzioni di sicurezza, controlli e conservazione dei dati | completata (CI verde, PR #4) |
> | 1 — Catalogo e sale | corsi, modifica e archiviazione, duplicazione, sale e sovrapposizioni | in corso: sviluppata, in collaudo (PR #5) |
> | 2 — Calendario, eccezioni e stagioni | festività, chiusure, sospensioni, spostamenti, recuperi, lezioni straordinarie | pianificata |
> | 3 — Insegnanti, sostituzioni e presenze | più insegnanti, sostituzioni per data, stati delle lezioni, richieste e notifiche | pianificata |
> | 4 — Calendari e report | vista giornaliera e settimanale, filtri, stampa, registri, esportazioni | pianificata |
>
> Iscrizioni ai corsi, capienza e presenze degli iscritti restano fuori ambito finché non si apre la fase soci (Fase 2 della roadmap generale). Una fase diventa «completata» solo con funzioni implementate e verificate, mai perché compare in roadmap.

- Corsi/lezioni/istruttori/categorie, calendari ricorrenti, manuale presenze 3 stati con autosave e storico.
- Prenotazioni pubbliche/privati per risorse, disponibilità e conflitti, conferma email, cancellazioni e privacy; tariffe configurabili.
- Liste d'attesa Q208 ancora da decidere.
- Gate: conflitti concorrenziali, disponibilità, permessi e UX verificati.

## Fase 5 — Quote, incassi e documenti

- Listini storicizzati, quote, rate, acconti, sconti manuali, report, modelli di ricevute.
- Nessun provider pagamenti nella prima versione; solo registrazione incassi, permessi delicati da determinare.
- Privacy documenti e controllo per finalità.
- Gate: test calcoli/riconciliazione e per ruoli, conformità dei documenti secondo consulente competente.

## Fase 6 — Gestione sito, SEO, media e migrazione plugin

- Form builder, slider, banner, SEO tecnico, analytics, media; supporto Elementor senza sostituire subito Pro.
- Mappare ogni plugin attuale, importazione controllata Yoast, diff prima/dopo e test di regressione pagine.
- Sostituzione progressiva singolo componente dopo test+backup+approvazione; nessun “rimuovi tutti”.
- Gate: SEO preservato, pagine e moduli funzionanti in staging e rollback documentato.

## Fase 7 — Automazioni, newsletter, reporting e backup avanzato

- Code resistenti ai guasti, scheduler compatibile TopHost, newsletter con consenso/disiscrizione e limiti, report schedulati, privacy, backup verificabili isolati.
- Release e distribuzione automatiche da GitHub DOPO CI; aggiornamenti su live protetti secondo politica accettata.
- Gate: stress e resilienza, test restore, checklist privacy.

## Fase 8+ — estensioni

- AI integrata solo in futuro; nuovi connettori terzi facoltativi; sviluppo di un nuovo gestionale desktop Bracelli distinto da Club Manager; nuove funzioni in base a requisiti e verifica tecnica.

## Definition of Done per modulo

- Requisiti e scenari scritti.
- Capability/security/privacy review.
- Test unitari, integration, manuali per la parte critica, test con condizioni avverse.
- Migrazione schema forward e recupero dati verificabili.
- Metriche reali su performance e compatibilità PHP/WP.
- Accessibilità, mobile e UX senza dead buttons.
- Documentation/changelog.
- PR, check GitHub Actions verdi, approvazione e ZIP **installato in LocalWP** con prova di aggiornamento.
- Nessun aggiornamento del sito pubblico senza conferma esplicita dell'operazione necessaria.
