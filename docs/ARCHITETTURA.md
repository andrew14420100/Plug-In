# Architettura tecnica proposta (da validare in Fase 0)

> Documento di design, non implementazione già funzionante. Claude deve produrre un ADR e motivare le scelte prima di creare il runtime.

## Confini di sistema

- **WordPress/TopHost:** plugin principale `bracelli-club`, PHP sul server, REST admin/frontend controllate, MariaDB/MySQL, asset React/TS precompilati. Niente Node in produzione.
- **PC Windows:** Club Manager commerciale con archivio condiviso su più computer; un connettore Windows separato, eventuale, da progettare SOLO dopo ispezione sicura della fonte. Nessun endpoint pubblico verso il database locale.
- **GitHub:** repository pubblico per codice/documentazione e workflow test, **mai** per dati reali, credenziali o backups.
- **LocalWP:** installazione WordPress locale per sviluppo/test con 5.000+ soci sintetici; backup locale isolato e toolchain ricostruibile.

## Layout repository suggerito

```text
Plug-In/
  README.md
  CLAUDE.md
  docs/
    SPECIFICA.md
    DECISIONI.md
    ARCHITETTURA.md
    CLUB_MANAGER.md
    ROADMAP.md
    TEST.md
    ADR-001-core.md       # da creare in Fase 0
  plugin/
    bracelli-club/
      bracelli-club.php
      src/
        Core/               # bootstrap, service container, moduli, permessi
        Infrastructure/     # DB, queue, mail, log, audit, API
        Modules/
          Members/
          Cards/
          Families/
          Courses/
          Attendance/
          Bookings/
          Billing/
          Documents/
          Forms/
          Media/
          SEO/
          Automations/
          Security/
          Backups/
          Analytics/
          Migration/
          Sync/
      assets/dist/          # build frontend precompilato
      templates/
      languages/
  frontend/
    src/
  tests/
    unit/
    integration/
    e2e/
    fixtures/
  tools/
    localwp/
  .github/workflows/
```

L'elenco non impone che tutte le cartelle esistano subito: aggiungere componenti soltanto quando funzionanti.

## Contratti core

- **ModuleManifest**: id univoco, versione semver, `min_wp`, `min_php`, requisiti, dipendenze, capabilities, migrazioni, attivabilità, feature flags, health checks, routes/assets. Moduli esterni sono **codice eseguibile**, trattarli come plugin privilegiati: origini consentite, checksum/firma dove disponibile, analisi, test e approvazione amministrativa. Evitare ZIP con path traversal e estrazione non sicura.
- **ModuleManager**: stato e dipendenze, operazioni idempotenti, cascata *su conferma*, registro operazioni. Disattivazione non elimina dati.
- **PermissionRegistry**: ruoli iniziali owner/admin, segreteria, istruttore, gestore corsi; capabilities atomiche (`bracelli_members_read`, `bracelli_members_edit`, `bracelli_finance_view`, `bracelli_finance_discount_apply`, `bracelli_sync_resolve` ecc.), controlli server-side e scopi per record/dato ove necessario.
- **AuditLog**: append-only ragionato, `actor_id`, `action`, `resource`, `diff_redacted`, `timestamp`, `request_id`; minimizzare dati personali nei log e limitare durata/accessi.
- **Settings**: impostazioni globali e individuali; versioni e migratori; import/export con dry-run/diff.
- **Queue/Scheduler**: stato persistente, idempotency key, lock, retry esponenziale e dead-letter; cron WP o server selezionato dopo verifica TopHost, niente affidamento a visite frontend per operazioni critiche senza verifiche.
- **Updater/Backup**: build testate, feed GitHub Release autenticato/verificato secondo scelta, controllo compatibilità, backup prima di migrazioni, manutenzione transazionale per fasi e opzioni di recovery; mai alterare dati irreversibilmente con rollback del solo ZIP.
- **Observability**: metriche leggere per modulo e diagnostica profonda solo su richiesta, redazione log e limite dei costi in query/I/O.

## Modello dati preliminare (da ADR)

Tabelle dedicate a entità con alta cardinalità e relazioni; WordPress postmeta/options solo se appropriato. Tutte le tabelle usano prefix WP; chiavi e indici dopo misure.

- `members`: UUID/ID interno, stato scheda e provenienza; PII con accesso per capability. Account WP facoltativamente associabile ma mai creato automaticamente.
- `external_identities`: `source_system`, `external_key`, `internal_member_id`, fingerprint di origine, timestamp sync, idempotency key; vincolo univoco source+external_key quando affidabile.
- `cards` / `card_history`: codice barcode come **stringa**, stato, validità, sostituzioni, conflitti/autorizzazioni; evitare coercizione numerica e collisioni automatiche.
- `memberships`, `course_enrollments`, `course_sessions`, `attendance`: stati indipendenti e audit.
- `families` / `family_links`: tutori/minori con permessi/scopi.
- `site_member_activation`: elenco operativo attivato sul sito separato dall'archivio generale (nessun account WP creato per default).
- `source_snapshots`, `sync_jobs`, `sync_conflicts`: tracciamento ultime modifiche, fonte, differenze per campo, errori, retry e replay con limiti; non duplicare documenti indiscriminatamente.
- `documents`: storage protetto, versione, ownership, access policy, scadenza, download controllato.
- `bookings` e prezzi/listini/regole, `payments` e ricevute solo quando i moduli sono previsti nella fase.
- `audit_events`, `queue_jobs`, `module_settings`, `notifications`.

## Ricerca tessera — comportamento target

1. Input codice via lettore USB, campo testo o dispositivo supportato; trim controllato senza modificare zeri iniziali.
2. Normalizza secondo **formato confermato della tessera**, evita autocorrezioni che fondano codici diversi.
3. Cerca in copia locale WP *sincronizzata* e mostra provenienza/ultima modifica; niente chiamate dirette al PC a ogni ricerca.
4. Match unico → anteprima dati permessi, controlli duplicati, completamento dei campi mancanti, stato completo/incompleto.
5. Match multiplo → **nessun abbinamento automatico ambiguo**, flusso risoluzione con audit.
6. Match assente → ricerca alternativa, stato sincronizzazione e inserimento manuale autorizzato.
7. Conferma esplicita attivazione operativa, idempotente, aggiorna solo vista attivazioni e audit; non altera il DB Club Manager.
8. Stati indipendenti per scadenza tessera/iscrizione/corsi; rinnovo guidato.

## Sicurezza e gestione dei dati

- Protezione query parametriche, sanificazione, output escape, CSRF nonce + capability, REST validation/permission callback.
- Documenti riservati non serviti via percorsi pubblici; preferire storage non pubblico o endpoint protetti dopo verifica hosting, mai inserire PII nei log di CI.
- Per integrazioni dal PC chiavi revocabili/ruotabili con privilegi minimi; HTTPS verificato, rate limit, controllo replay, audit, limite dimensioni payload, idempotenza.
- Dati da Club Manager solo per categorie autorizzate/necessarie. Rivedere base giuridica, minimizzazione, finalità e tempi di conservazione prima di popolare produzione; gestione consensi marketing distinta dall'iscrizione sociale.
- Frontend React deve essere solo interfaccia: controlli, calcoli e validazioni critici devono esistere anche sul server.

## Performance e resilienza

- Caricamento asset solo pagine interessate; query paginabili, indici, elaborazione per batch, lazy charts, misurare tempo/memoria DB.
- Backup e restore reali su installazione isolata, compresi file/media e data migrations; non garantire rollback DB automatico per operazioni non reversibili.
- Monitorare separatamente stato del processo desktop, raggiungibilità archivio, ultima sync completata e freschezza dati; “Club Manager chiuso” non equivale a database indisponibile se il PC e l'archivio restano accessibili.
- Per uso locale senza connessione utilizzare ultima copia WordPress, avviso freschezza e catch-up al ritorno online.

## Compatibilità/integrazione

- PHP e WP versions rilevate in Fase 0 su test: NON assumere versioni non verificate.
- Elementor mantenuto finché inventario non chiarisce shortcode, templates e widgets utilizzati; no sostituzione cieca.
- Update URI GitHub/provider: aderire a hook ufficiali WordPress e feed affidabile, prevenire collisione di slug.
- Documentare limiti del piano TopHost (cron, spazio disco, mail invio, memoria, max execution), senza presumere privilegi server.
