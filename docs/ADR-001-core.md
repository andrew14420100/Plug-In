# ADR-001 — Architettura del core Bracelli Club Plug-In

| Campo | Valore |
| --- | --- |
| Stato | **Approvato** dall'utente al termine della Fase 0 (requisiti minimi PHP/WP provvisori fino alla verifica TopHost); emendamenti di Fase 1 in §15 |
| Data | 15/10/2026 |
| Ambito | Core WordPress, mappa moduli, modello dati preliminare, permessi, migrazioni, aggiornamenti, sincronizzatore futuro |
| Documenti vincolanti | `CLAUDE.md`, `docs/SPECIFICA.md`, `docs/DECISIONI.md`, `docs/ARCHITETTURA.md`, `docs/CLUB_MANAGER.md`, `docs/ROADMAP.md`, `docs/TEST.md` |
| Documenti collegati | `docs/FASE-0-INVENTARIO.md`, `docs/FASE-0-LOCALWP.md`, `docs/FASE-0-REPORT.md` |

> Questo ADR è una **proposta tecnica**. Non descrive software esistente: alla data di redazione il repository contiene solo documentazione. Nessuna parte è stata eseguita su WordPress, LocalWP, TopHost o Club Manager.

---

## 1. Contesto

- Un **unico plugin** WordPress per il solo sito Bracelli Club (Q1–10), installabile via ZIP, con core e moduli indipendenti (Q21–30, Q163).
- Hosting condiviso **TopHost** (Q31–40), nessun Node/Python in produzione, ~5 operatori contemporanei (Q142), 1.001–5.000 soci/anno (Q188).
- Club Manager resta operativo; dati **solo Club Manager → WordPress** (Q303, Q317), integrazione da verificare (Q252, Q291–293, Q319).
- Repository GitHub **pubblico** (Q152), nessuna pubblicazione su WordPress.org (Q158), costi obbligatori zero.
- Sicurezza, prestazioni e recupero hanno priorità (Q141, Q173, Q289).

### 1.1 Stato del repository verificato (15/10/2026)

| Elemento | Esito |
| --- | --- |
| Branch | `main` + `claude/plug-in-fase-0-dy10dq` (lavoro Fase 0) |
| Contenuto | `README.md`, `CLAUDE.md`, `.gitignore`, `docs/` (6 file). Nessun codice, nessun `composer.json`/`package.json`, nessun workflow CI |
| Storia | 9 commit di documentazione, nessun tag o release |
| `.gitignore` | Esclude `.env*`, `wp-config.php`, dump SQL/DB, CSV/XLSX/PDF, chiavi/certificati, log, upload, backup, `vendor/`, `node_modules/`, `dist/`, `*.zip` (eccetto `tests/fixtures/*.json`) |
| Dati personali/segreti nel repo | Nessuno rilevato (scansione con pattern per codici fiscali, email, token: vedi `FASE-0-REPORT.md`) |
| Licenza | **Assente.** Decisione dell'utente (SPECIFICA §10: "niente licenza decisa senza autorizzazione") — vedi §12 |

**Nota sul `.gitignore`:** `dist/` e `*.zip` sono esclusi: corretto per il repository, ma gli asset buildati andranno **nello ZIP** di release generato dalla CI, non in git (coerente con ARCHITETTURA: "asset React/TS precompilati" nello ZIP). `*.csv` escluso implica che le fixture sintetiche saranno JSON generati da script, non CSV committati.

### 1.2 Versioni di riferimento rilevate (fonti ufficiali, non il sito)

Rilevate il 15/10/2026 tramite `api.wordpress.org` e `php.net`. **Non** sono le versioni del sito Bracelli, che restano da verificare (§11, `FASE-0-INVENTARIO.md` §1).

| Componente | Ultima stabile | Altre informazioni |
| --- | --- | --- |
| WordPress | 7.1.3 | rami con patch di sicurezza: 7.0.7, 6.9.10, 6.8.11 … |
| PHP | 8.5.11 | rami supportati da php.net: 8.2, 8.3, 8.4, 8.5 |
| Elementor (free) | 4.3.5 | richiede WP 6.8, PHP 7.4 |
| Yoast SEO | 28.7 | richiede WP 6.9 |
| Contact Form 7 | 6.2.2 | richiede WP 7.1, PHP 8.3 |
| Amelia (free) | 2.4.11 | richiede WP 6.5 |

L'ambiente di questa sessione (container Linux) ha PHP 8.3.6 CLI, Node 22.22.0, npm 10.9.4; **non** ha WordPress, WP-CLI né MySQL/MariaDB. Non è rappresentativo di TopHost né di LocalWP.

---

## 2. Decisioni principali (sintesi)

| # | Decisione | Alternativa scartata | Motivo |
| --- | --- | --- | --- |
| D1 | Plugin unico `bracelli-club`, moduli **interni** allo ZIP con manifest; moduli ZIP esterni rinviati | Un plugin WP per modulo | Q21–30 chiede un pacchetto unico; un plugin per modulo moltiplica aggiornamenti e conflitti |
| D2 | PHP ≥ **8.2** proposto, WP ≥ **6.9** proposto (da confermare dopo rilievo TopHost) | Supporto PHP 7.4 | 7.4 è fuori supporto da anni; 8.2 è il più vecchio ramo ancora supportato |
| D3 | Tabelle custom `{$wpdb->prefix}bc_*` per entità ad alta cardinalità; options solo per impostazioni | CPT + postmeta per i soci | 5.000+ soci × relazioni: postmeta non è indicizzabile in modo selettivo e mescola PII con contenuti |
| D4 | Soci **separati** dagli utenti WP; collegamento opzionale `wp_user_id` | Un utente WP per socio | Q299, README principio 4 |
| D5 | Barcode come `VARCHAR` binario, mai numerico; unicità solo sulle tessere **attive** | `BIGINT` o unique globale | Zeri iniziali (CLAUDE.md), sostituzioni con storico (Q264), collisioni autorizzate (Q263) |
| D6 | Codice fiscale **non** unico; tabella identificatori con matching multiplo | Unique su CF | CLAUDE.md: CF non unico per tutti i tipi di contatto |
| D7 | Migratori versionati, idempotenti, solo forward, con registro e lock | `dbDelta` a ogni caricamento | Tracciabilità, niente lavoro su ogni richiesta |
| D8 | Capabilities atomiche + `map_meta_cap` per scope di record | Controlli solo per ruolo | Q167, Q278, Q300–301 |
| D9 | React/TS compilato con `@wordpress/scripts`, React fornito da WP (`wp-element`), bundle per modulo, solo nelle pagine admin dedicate | Bundle unico / SPA su tutto l'admin | CLAUDE.md workflow §4, prestazioni |
| D10 | Recovery mode PHP puro + costante `BRACELLI_CLUB_SAFE_MODE` | Recovery dentro React | Q172–173: deve funzionare con JS rotto |
| D11 | Coda propria minimale su tabella (`bc_jobs`) quando un modulo la richiede | Action Scheduler | Idempotency key, dead-letter e lock su misura; zero dipendenze. Action Scheduler resta alternativa valida (vedi §7.3) |
| D12 | Updater opzionale via header `Update URI` + filtro `update_plugins_github.com`, release GitHub con SHA-256 | Plugin di update di terzi | Hook ufficiale WP, nessun costo, Q165/Q248/Q270 |
| D13 | Sincronizzatore Windows **separato**, non incluso nel plugin, solo progettato | Lettura del DB Club Manager dal server | Nessun endpoint pubblico verso il DB locale (ARCHITETTURA) |
| D14 | Nessun modulo visibile senza backend reale; stato "non configurato" onesto | Schermate demo | CLAUDE.md, README principio 1 |

---

## 3. Struttura del plugin

```text
plugin/bracelli-club/
  bracelli-club.php           # header, costanti, guardie versione PHP/WP, bootstrap minimo
  uninstall.php               # NON cancella dati salvo opzione esplicita (Q186)
  composer.json               # PSR-4 Bracelli\Club\ → src/
  src/
    Core/
      Plugin.php              # bootstrap, container leggero
      Container.php
      Modules/                # ModuleManifest, ModuleManager, DependencyResolver
      Permissions/            # PermissionRegistry, Roles, MetaCaps
      Settings/               # SettingsRepository, schema, import/export dry-run
      Migrations/             # Migrator, MigrationInterface, registry
      Admin/                  # menu, dashboard shell PHP, recovery page
      Rest/                   # base controller, permission helpers
    Infrastructure/
      Database/               # TableNames, QueryHelpers (solo $wpdb->prepare)
      Audit/                  # AuditLogger, Redactor
      Diagnostics/            # health checks, metriche leggere
      Queue/                  # (quando richiesto da un modulo)
      Updater/                # (opzionale, Fase 1 o 7 secondo approvazione)
    Modules/<Nome>/
      module.php              # manifest (array PHP)
      src/…                   # servizi, REST, migrazioni del modulo
  assets/dist/<modulo>/       # build JS/CSS (solo nello ZIP, non in git)
  templates/
  languages/                  # text domain bracelli-club
frontend/
  src/core/…  src/modules/<nome>/…
  package.json, tsconfig.json, webpack via @wordpress/scripts
tests/unit/  tests/integration/  tests/e2e/  tests/fixtures/
tools/localwp/                # script setup/diagnostica/restore (Q268)
.github/workflows/
```

- **Namespace** `Bracelli\Club`, prefisso funzioni/hook/option `bracelli_club_`, prefisso tabelle `{$wpdb->prefix}bc_`, REST `bracelli/v1`, text domain `bracelli-club`.
- **Dipendenze Composer di runtime** ridotte al minimo; se necessarie, prefissate (Strauss o PHP-Scoper) per evitare conflitti con altri plugin che includono le stesse librerie. Le dipendenze di sviluppo (PHPUnit, PHPCS, PHPStan) non entrano nello ZIP.
- **Guardie di avvio:** se PHP/WP sotto i minimi, il plugin mostra un avviso admin e non carica nulla (niente fatal).
- Le cartelle si creano **solo quando contengono codice funzionante** (ARCHITETTURA).

---

## 4. Moduli: contratto, mappa e dipendenze

### 4.1 Manifest

```php
return [
    'id'           => 'members',            // univoco, [a-z0-9_]
    'version'      => '0.1.0',              // semver
    'name'         => __( 'Archivio soci', 'bracelli-club' ),
    'min_wp'       => '6.9',
    'min_php'      => '8.2',
    'requires'     => [ 'core' => '^1.0', 'permissions' => '^1.0' ],
    'optional'     => [ 'documents' ],
    'capabilities' => [ 'bracelli_members_read', /* … */ ],
    'migrations'   => [ Migrations\M2026_10_0001_CreateMembers::class ],
    'health'       => [ Health\MembersTableCheck::class ],
    'rest'         => [ Rest\MembersController::class ],
    'admin_pages'  => [ 'bracelli-members' => [ 'script' => 'members' ] ],
    'flags'        => [ 'experimental' => false ],
    'status_when_unconfigured' => 'not_configured', // mai dati demo
];
```

### 4.2 Regole del ModuleManager

- Stati: `inactive`, `active`, `degraded` (health check fallito), `blocked` (dipendenza mancante/versione), `not_configured`.
- Attivazione: verifica `min_wp`/`min_php`, dipendenze (vincoli semver), esegue migrazioni pendenti del modulo, registra capabilities, scrive audit. Idempotente.
- Disattivazione: elenca i moduli dipendenti; **cascata solo dopo conferma esplicita** (Q182); i dati restano (Q186).
- Errori di un modulo durante il bootstrap: il modulo passa a `degraded`, il core e gli altri moduli continuano; l'errore è registrato (senza PII).
- Moduli `experimental` attivabili anche in produzione solo con conferma (Q184), senza saltare backup o verifiche.
- **Moduli ZIP esterni** (Q179–180): fuori dalla Fase 1. Quando previsti: origine consentita (solo release GitHub del repository ufficiale), SHA-256 + verifica firma se disponibile, estrazione sicura (rifiuto path traversal, symlink, estensioni non ammesse), anteprima e approvazione amministrativa. Il checksum **non** garantisce la sicurezza del codice (TEST.md).

### 4.3 Mappa moduli

| ID | Modulo | Dipende da | Fase | Note |
| --- | --- | --- | --- | --- |
| `core` | Bootstrap, container, ModuleManager, Settings, Migrator | — | 1 | sempre attivo |
| `permissions` | Ruoli, capabilities, meta caps | core | 1 | sempre attivo |
| `audit` | Registro eventi, ricerca, redazione | core, permissions | 1 | sempre attivo |
| `dashboard` | Shell admin, contesti admin/dipendente, menu, preferenze, temi, preferiti, widget | core, permissions | 1 | widget solo con dati reali |
| `diagnostics` | Health check, metriche leggere, diagnostica su richiesta | core, audit | 1 | Q183, Q246 |
| `recovery` | Pagina recovery PHP, safe mode | core | 1 | non disattivabile |
| `updater` | Aggiornamenti da GitHub Release | core, diagnostics | 1 (opz.) o 7 | vedi §9 |
| `queue` | Coda persistente, scheduler | core, audit | 2 (quando serve) | |
| `members` | Archivio soci, identificatori, completezza | core, permissions, audit | 2 | |
| `cards` | Tessere, barcode, storico, ricerca tessera | members | 2 | |
| `families` | Nuclei, tutori/minori | members | 2 | |
| `memberships` | Iscrizioni associative/tesseramenti stagionali | members | 2 | stato distinto |
| `activations` | Elenco soci attivati sul sito | members, cards | 2 | Q339; UX massiva Q340 aperta |
| `import` | Import file con anteprima/checkpoint | members, queue | 2 | dati sintetici |
| `sync` | Ingestione Club Manager (lato WP) | import, queue, members, cards | 3 (condizionale) | solo dopo P0 |
| `courses` | Corsi, categorie, istruttori, calendario | members | 4 | |
| `attendance` | Presenze P/A/G | courses | 4 | |
| `bookings` | Prenotazioni risorse, pubbliche | core, (members opz.) | 4 | Q208 aperta |
| `billing` | Listini, quote, incassi, sconti manuali | members, (courses, bookings opz.) | 5 | nessun gateway |
| `documents` | Archivio documenti protetto | members, permissions | 5 | sanitari per finalità |
| `forms` | Form builder | core, (documents opz.) | 6 | |
| `media`, `sliders` | Media avanzati, slider/banner | core | 6 | |
| `seo`, `redirects` | SEO tecnico, migrazione Yoast | core | 6 | |
| `analytics` | Statistiche interne | core, queue | 6–7 | |
| `newsletter` | Newsletter con consensi | members, queue | 7 | |
| `automations` | Flussi leggeri | queue, audit | 7 | |
| `security` | 2FA, blocco accessi, conferme sensibili | core, audit | 7 (2FA admin valutabile prima) | Q235–237 |
| `backups` | Backup/restore verificati | core, queue, diagnostics | 7 | |
| `elementor` | Widget/tag dinamici Elementor | modulo sorgente dei dati | 4–6 | solo se Elementor attivo |

Grafo (archi = "richiede"):

```text
core ─┬─ permissions ─┬─ audit ─┬─ diagnostics ── updater
      │               │         └─ queue ─┬─ import ── sync
      │               ├─ dashboard        ├─ newsletter / automations / analytics / backups
      │               └─ members ─┬─ cards ── activations
      │                           ├─ families
      │                           ├─ memberships
      │                           ├─ documents
      │                           ├─ courses ── attendance
      │                           └─ billing
      └─ recovery
```

Il resolver rifiuta cicli in fase di build (test unitario sul grafo dei manifest).

---

## 5. Modello dati preliminare

Principi: tabelle InnoDB, `utf8mb4`; ID interni `BIGINT UNSIGNED AUTO_INCREMENT` + `uuid CHAR(36)` per riferimenti esterni stabili; timestamp UTC `DATETIME`; nessun `FOREIGN KEY` fisico obbligatorio (compatibilità hosting e dbDelta) ma integrità verificata dai servizi e da health check; indici aggiunti dopo misure (ARCHITETTURA). Colonne e indici sotto sono **proposte** per la Fase 2.

### 5.1 Soci e identità

```text
bc_members
  id, uuid, member_type (socio|tutore|contatto|…), status_record (active|archived),
  first_name, last_name, birth_date, gender?, email, phone, address_*, notes_ref,
  completeness (complete|incomplete), missing_fields (JSON), wp_user_id NULL,
  origin (wordpress|club_manager|import), created_at/by, updated_at/by, archived_at/by
  idx: (last_name, first_name), (status_record), (wp_user_id)

bc_member_identifiers           -- CF, email, tel, ID federazioni: NON unici
  id, member_id, type, value, value_norm, value_hash (HMAC per ricerca su dati cifrati),
  source, verified_at, created_at
  idx: (type, value_norm) non unico, (member_id)

bc_external_identities          -- legame stabile con le origini
  id, member_id, source_system, external_key, fingerprint, schema_version,
  first_seen_at, last_seen_at, last_sync_job_id, status (linked|orphaned|conflict)
  UNIQUE (source_system, external_key)   -- solo quando la chiave è affidabile (Q319)
```

- **Matching** (Q256, Q302): priorità a `external_identities` quando certa; altrimenti punteggio multicampo (CF + nome + data nascita + contatti) che produce **proposte**, mai fusioni automatiche se ambiguo. Fusioni manuali con audit e possibilità di separazione.
- **Account WP**: mai creati automaticamente; `wp_user_id` valorizzato solo da azione autorizzata.

### 5.2 Tessere

```text
bc_cards
  id, uuid, member_id, code (VARCHAR(64) COLLATE utf8mb4_bin), code_norm,
  symbology NULL (Q265), status (active|lost|damaged|replaced|revoked|conflict),
  active_flag TINYINT NULL      -- 1 se active, NULL altrimenti
  issued_at, valid_from, valid_to NULL, replaced_by_card_id NULL, origin, created_at/by
  UNIQUE (code_norm, active_flag)  -- MySQL/MariaDB ammettono più NULL: una sola tessera
                                   -- attiva per codice, storico illimitato
  idx: (member_id), (code_norm)

bc_card_events                   -- storico append-only
  id, card_id, event (issued|renewed|lost|replaced|reassigned|conflict_opened|conflict_resolved),
  from_code, to_code, reason, actor_id, request_id, created_at
```

- `code` conserva **esattamente** la stringa letta (solo trim di spazi/CR/LF del lettore). `code_norm` applica soltanto la normalizzazione del **formato confermato** (Q265): finché il formato non è noto, `code_norm = code`. Nessuna conversione numerica, nessuna rimozione di zeri, nessuna autocorrezione.
- Rinnovo: il codice **non cambia** (Q191); si aggiornano le iscrizioni, non la tessera.
- Smarrimento/danno: la vecchia tessera passa a `lost|damaged` → `replaced`, la nuova è collegata (`replaced_by_card_id`), evento nello storico (Q264).
- Collisione (stesso codice già attivo): la seconda tessera nasce `conflict` (`active_flag NULL`) e apre una pratica in coda risoluzione; l'eccezione autorizzata (Q263) è decisa da chi ha `bracelli_cards_resolve_conflict`, con audit.

### 5.3 Stati distinti

| Concetto | Tabella | Stati |
| --- | --- | --- |
| Tessera | `bc_cards` | sopra |
| Iscrizione associativa | `bc_memberships` (member_id, season, type, status `pending|valid|expired|cancelled`, valid_from/to, source) | Q277, Q334 |
| Singolo corso | `bc_course_enrollments` (Fase 4) | idem, per corso |
| Attivazione operativa sul sito | `bc_site_activations` (member_id, card_id usato, status `active|revoked`, activated_at/by, revoked_at/by, reason, batch_id) | Q339; **non** implica iscrizione valida |

Nessuno stato è derivato da un altro: la UI mostra i quattro separatamente; le regole operative configurabili (Q334) leggono gli stati ma non li fondono.

### 5.4 Provenienza, override locali, storico

```text
bc_source_snapshots      -- ultimo dato ricevuto dalla sorgente, per identità esterna
  id, external_identity_id, payload (JSON whitelisted, minimizzato), fingerprint,
  source_updated_at NULL, received_at, sync_job_id

bc_field_overrides       -- modifiche manuali in WordPress su campi che hanno origine esterna
  id, member_id, field, local_value, source_value_at_override, actor_id, created_at, status

bc_sync_conflicts        -- conflitti per campo (Q275, Q320, Q336)
  id, member_id, field, source_value, local_value, detected_at, status, resolved_by/at, resolution

bc_sync_jobs             -- job di import/sync: conteggi, checkpoint, errori, idempotency_key UNIQUE
```

Regola: un aggiornamento dalla sorgente **non sovrascrive mai in silenzio** un campo con override locale; aggiorna lo snapshot e, se diverso, apre un conflitto. Cancellazioni/archiviazioni nella sorgente cambiano lo stato (`orphaned`/`archived`), non cancellano (Q321).

### 5.5 Infrastruttura

```text
bc_migrations     (id, migration_key UNIQUE, module, checksum, started_at, finished_at, status, error)
bc_audit_events   (id, occurred_at, actor_id, actor_role, action, resource_type, resource_id,
                   diff_redacted JSON, ip_hash, request_id, module)  idx: (occurred_at), (resource_type, resource_id), (actor_id)
bc_jobs           (id, queue, type, idempotency_key UNIQUE, payload, status, attempts,
                   available_at, locked_until, locked_by, last_error, created_at)   -- quando serve
bc_module_state   (module_id PK, version, status, activated_at/by, settings_version)
Impostazioni: option `bracelli_club_settings` (autoload=no per blocchi grandi);
preferenze utente: user meta `bracelli_club_prefs` (tema, palette, widget, preferiti).
```

### 5.6 Dati personali e cifratura (proposta, decisione in Fase 2)

- Minimizzazione: campi importati solo se whitelisted per categoria (Q316).
- Dati particolari (documenti sanitari, Q301): **non** in tabella in chiaro; file in storage privato (§8.3), metadati minimi.
- Cifratura applicativa opzionale per campi selezionati (es. CF) con libsodium (`sodium_crypto_secretbox`) e chiave in `wp-config.php` (`BRACELLI_CLUB_ENC_KEY`), più indice HMAC per la ricerca. **Rischio:** perdita chiave = perdita dati; la chiave va custodita fuori dai backup del DB. Da approvare prima della Fase 2.
- Log/audit: `diff_redacted` esclude o maschera PII per configurazione; IP memorizzato come hash; durata conservazione configurabile nel rispetto degli obblighi (Q137, Q138, Q242).

---

## 6. Permessi

### 6.1 Ruoli iniziali

| Ruolo | Slug | Note |
| --- | --- | --- |
| Proprietario/Amministratore | WP `administrator` | riceve tutte le capability `bracelli_*` all'attivazione; `bracelli_owner` concettuale = admin con `bracelli_settings_manage` |
| Segreteria | `bracelli_segreteria` | soci, tessere, attivazioni; niente finanza generale |
| Istruttore | `bracelli_istruttore` | solo i propri corsi/presenze (scope) |
| Gestore corsi | `bracelli_gestore_corsi` | corsi, calendari, istruttori |

Sottoruoli e permessi personalizzati (Q167, Q300): editor ruoli del plugin che assegna capability atomiche; i ruoli WP nativi non vengono alterati salvo `administrator` (aggiunta capability).

### 6.2 Capabilities (prefisso `bracelli_`)

`dashboard_view`, `dashboard_admin_view`, `settings_manage`, `modules_manage`, `audit_view`, `diagnostics_run`, `members_read`, `members_create`, `members_edit`, `members_archive`, `members_merge`, `members_pii_view` (CF/indirizzo), `members_export`, `cards_read`, `cards_assign`, `cards_replace`, `cards_resolve_conflict`, `activations_manage`, `activations_bulk`, `families_manage`, `import_run`, `sync_view`, `sync_resolve`, `sync_replay`, `courses_*`, `attendance_record`, `attendance_correct_past`, `finance_view`, `finance_record_payment`, `finance_discount_propose`, `finance_discount_apply`, `documents_read_{categoria}`, `documents_health_read`, `documents_manage`.

### 6.3 Regole di enforcement

- Ogni azione server-side chiama `current_user_can()` con la capability specifica **o** una meta capability (`bracelli_edit_member`, `bracelli_record_attendance` con ID) risolta in `map_meta_cap` per gli scope di record (istruttore → solo propri corsi; documenti → categoria + finalità).
- REST: ogni rotta ha `permission_callback` reale (mai `__return_true` per dati privati); `args` con `validate_callback`/`sanitize_callback`; nonce `wp_rest` per la sessione cookie, che **non** sostituisce l'autorizzazione.
- Form admin non-REST: `check_admin_referer()` + capability.
- Pagine admin registrate con la capability corretta; la UI nasconde ciò che non è permesso ma il server rifiuta comunque (test IDOR).
- Documenti sanitari: capability dedicata + motivo/finalità registrato a ogni accesso (Q301).
- Operazioni sensibili: seconda conferma con finestra temporale (Q237) **in aggiunta** ai permessi.

---

## 7. Migrazioni, impostazioni, coda

### 7.1 Migrator

- Ogni migrazione è una classe con chiave stabile `AAAA_MM_NNNN_descrizione`, modulo, `up()` idempotente e `checksum` registrato in `bc_migrations`.
- Esecuzione: all'attivazione del plugin/modulo, all'aggiornamento di versione (confronto `bracelli_club_db_version`) — **mai** su richieste frontend. Lock con `GET_LOCK()` MySQL o option atomica (`add_option` fallisce se esiste) con scadenza.
- DDL tramite `dbDelta()` per create/alter additivi; verifiche esplicite (`SHOW COLUMNS`, `SHOW INDEX`) prima di ogni alter non additivo.
- Migrazioni di dati: a batch tramite coda, con checkpoint e ripresa; report dei conteggi.
- **Solo forward.** Nessun "down" che distrugga dati: si correggono errori con nuove migrazioni. Prima di migrazioni marcate `requires_backup`, il core verifica che esista un backup recente dichiarato e chiede conferma (Q289); se il modulo backups non è attivo, lo dichiara apertamente e richiede conferma manuale dell'amministratore.
- Disinstallazione: `uninstall.php` non elimina tabelle né opzioni salvo flag esplicito "elimina dati" impostato da un amministratore (Q186).

### 7.2 Impostazioni

Schema dichiarato per modulo (tipo, default, validazione, sanitizzazione); versione dello schema con migratori; import/export JSON con **dry-run e diff** prima dell'applicazione (Q185); l'export non include segreti.

### 7.3 Coda e scheduler

- Implementazione propria minimale (D11) introdotta con il primo modulo che ne ha bisogno (Fase 2 import).
- Claim dei job con `UPDATE … SET locked_by=…, locked_until=… WHERE status='pending' AND available_at<=NOW() LIMIT n` + verifica righe aggiornate; retry esponenziale, `dead` dopo N tentativi, idempotency key unica.
- Runner: WP-Cron con budget di tempo/memoria per tick; se TopHost consente un cron di sistema, consigliato chiamare `wp-cron.php` a intervalli fissi e impostare `DISABLE_WP_CRON` (Q307) — **da verificare** sul piano.
- Alternativa: Action Scheduler (GPL, maturo, gestisce più copie per versione). Pro: collaudato; contro: dipendenza condivisa con WooCommerce/altri, idempotenza e dead-letter da costruire sopra. Rivalutare se la coda propria supera ~300 righe o se un modulo richiede funzioni avanzate.

---

## 8. Dashboard, frontend, sicurezza applicativa

### 8.1 Dashboard (Fase 1)

- Pagina principale `admin.php?page=bracelli` impostata come schermata iniziale dopo login (`login_redirect` per utenti con `bracelli_dashboard_view`, Q161), disattivabile per utente.
- Contesto **amministratore** e **dipendente** (Q171) determinato dalle capability, non dal solo ruolo.
- Menu laterale configurabile; opzione per nascondere il menu WP standard **per utente**, con pulsante sempre visibile "Menu WordPress" (Q162, Q172). Il nascondimento è solo presentazione: non revoca accessi.
- Temi chiaro/scuro (con `prefers-color-scheme` come default), palette selezionabile, icone e posizione widget (Q175–177), salvati in user meta via REST.
- Widget: solo con sorgente dati reale. In Fase 1: stato moduli, salute sistema, ultimi eventi di audit, preferiti. Soci/incassi/corsi mostrano "Modulo non attivo" — **nessun contatore inventato**.
- Shell PHP renderizza la struttura e un `<noscript>`/fallback con link alle pagine essenziali; React monta sopra.

### 8.2 Recovery

- `admin.php?page=bracelli-recovery`: pagina PHP pura (nessun JS richiesto) per amministratori: stato moduli, disattivazione singolo modulo, ripristino menu WP, reset preferenze UI del proprio utente, ultimi errori.
- `define( 'BRACELLI_CLUB_SAFE_MODE', true );` in `wp-config.php`: carica solo core + recovery, nessun modulo, nessun nascondimento di menu.
- Compatibile con il recovery mode nativo di WordPress per fatal error.

### 8.3 Documenti privati (Fase 5, decisione infrastrutturale anticipata)

1. Preferito: directory **fuori dalla webroot** se il piano TopHost lo consente (da verificare).
2. Altrimenti: `wp-content/uploads/bracelli-private/` con nomi casuali, `index.php` vuoto, `.htaccess` `Require all denied` (efficace solo su Apache/LiteSpeed: **verificare** il web server di TopHost e testare l'accesso diretto con un file fittizio).
3. Download sempre tramite endpoint PHP con capability, finalità, log accesso e header `Content-Disposition`/`X-Content-Type-Options: nosniff`; mai URL pubblici permanenti.

### 8.4 Regole di codice

`$wpdb->prepare()` per ogni query con input; `esc_html/esc_attr/esc_url/wp_kses` in output; `sanitize_*` e validazione per tipo in input; niente `eval`, niente editor CSS/JS/snippet (Q224); niente richieste HTTP esterne nel runtime salvo updater (disattivabile); `wp_mail` via hosting (Q304).

### 8.5 Frontend build

- `@wordpress/scripts` (webpack) con entry per modulo → `assets/dist/<modulo>/index.js` + `index.asset.php` (dipendenze e versione generate). Enqueue solo sulla pagina admin del modulo (`admin_enqueue_scripts` con controllo `$hook_suffix`).
- TypeScript strict, ESLint, test con Jest/Testing Library; nessun calcolo critico solo lato client.

---

## 9. Aggiornamenti e release

- Header del plugin `Update URI: https://github.com/andrew14420100/Plug-In`; il core aggancia `update_plugins_github.com` (hook `update_plugins_{$hostname}`, WP ≥ 5.8) e legge l'ultima **release stabile** (non pre-release) via API pubblica GitHub, con cache (transient 6–12 h) e timeout breve.
- Ogni release pubblica `bracelli-club-X.Y.Z.zip` + `SHA256SUMS`; prima dell'installazione il plugin scarica lo ZIP, verifica l'hash (filtro `upgrader_pre_download`) e controlla `min_wp`/`min_php` del manifest della release.
- Auto-update **opzionale** tramite l'interfaccia nativa WP (Q165, Q270); se la release dichiara migrazioni `requires_backup`, l'auto-update si ferma e richiede conferma manuale. Fall-back: caricamento ZIP manuale.
- La CI crea la release solo da tag `vX.Y.Z` su `main`, dopo job verdi (Q247–248). Release e applicazione in produzione restano **due atti distinti** (TEST.md).
- Rischio: API GitHub con limite 60 req/h non autenticate per IP condiviso — mitigato da cache e da messaggio "verifica non disponibile" senza errori bloccanti.

---

## 10. Sincronizzatore Windows → WordPress (proposta futura, **non realizzato**)

> Integrazione futura **da verificare**. Nessun componente esiste. Avvio solo dopo le verifiche P0 di `docs/CLUB_MANAGER.md` e autorizzazione esplicita dell'utente (ROADMAP Fase 3).

### 10.1 Architettura proposta

```text
[PC Windows A/B]                                   [TopHost / WordPress]
Club Manager (intatto)                              plugin bracelli-club
   │ sola lettura, metodo supportato                    modulo sync (ingestione)
   ▼                                                    │
Adapter sorgente (read-only) ──► Agente "Bracelli Sync" ──HTTPS POST──► /wp-json/bracelli/v1/sync/batches
                                  servizio Windows        firma HMAC     validazione schema, idempotenza,
                                  coda locale cifrata                    staging, matching, conflitti, audit
                                  lease "agente attivo" ◄── heartbeat ── /sync/lease
```

- **Repository/prodotto separato** dal plugin (es. cartella `connector/` o repository dedicato da decidere); nessun codice Windows nello ZIP del plugin.
- **Adapter sorgente** dietro un'interfaccia (`ISourceAdapter`: `Describe()`, `ReadChanges(checkpoint)`, `ReadSnapshot(page)`), con una sola implementazione scelta dopo l'analisi: API/export ufficiale > backup/export schedulato > lettura di una copia > lettura diretta supportata. Mai scrittura sulla sorgente; apertura in modalità che non blocchi Club Manager; nessuna decompilazione o elusione di licenze.
- **Agente**: servizio Windows avviato al boot (Q312, Q326), indipendente dalla finestra di Club Manager **solo se** il formato lo consente; priorità bassa, throttling adattivo (Q327); coda locale persistente (es. SQLite) con credenziali protette da DPAPI; tecnologia candidata .NET (LTS corrente) — scelta definitiva in ADR dedicato.
- **Un solo agente attivo per archivio** (Q311, CLUB_MANAGER §7): lease server-side con heartbeat e scadenza; il secondo PC resta in standby.
- **Trasporto**: HTTPS verificato; header `X-Bracelli-Key-Id`, `X-Bracelli-Timestamp`, `X-Bracelli-Nonce`, firma HMAC-SHA256 di metodo+path+timestamp+nonce+hash corpo; finestra anti-replay ±5 min e nonce memorizzati; limite dimensione batch; rate limit; chiavi per agente, revocabili e ruotabili (Q325); log connessioni.
- **Payload**: `source`, `external_id`, `schema_version`, `updated_at?`, `fields` (whitelist per categoria, Q316), `fingerprint`, `batch_id`, `idempotency_key`.
- **WordPress**: salva il batch in staging, risponde 202, elabora via coda; matching (§5.1), snapshot e conflitti (§5.4); avanza il checkpoint solo dopo conferma; eventi dubbi → indagine, non avanzamento.
- **Assenza di timestamp sorgente**: differenze per fingerprint su snapshot leggeri, a blocchi e con throttling.
- **Offline**: PC spento → WordPress usa l'ultima copia con data di freschezza; al ritorno l'agente svuota la coda (Q322, Q337).
- **Dashboard sync** (Q324): stato agente, ultimo heartbeat, ultima sync completata, freschezza, conteggi, conflitti, errori, replay autorizzato.
- **Pagamenti** (Q328) esclusi finché non deciso. **Nessuna** sincronizzazione WordPress → Club Manager.

### 10.2 Cosa è consegnabile prima della verifica

Solo il **contratto** (questo paragrafo e, in Fase 2, uno schema JSON del payload usato anche dall'import da file sintetici) e una pagina "Integrazione Club Manager non configurata". Nessun agente, endpoint attivo o connettore fittizio (CLUB_MANAGER "Confini di consegna").

---

## 11. Compatibilità e matrice di test proposta

- **Minimi proposti:** PHP 8.2, WordPress 6.9 (Yoast attuale richiede 6.9; CF7 attuale richiede 7.1 — se il sito usa CF7 aggiornato, il sito è già su 7.1). **Da confermare** con le versioni reali del sito (`FASE-0-INVENTARIO.md` §1); se TopHost usa PHP < 8.2, si rivede D2 prima della Fase 1.
- **Matrice CI:** PHP 8.2 / 8.3 / 8.4 / 8.5 × WP 6.9 / 7.0 / 7.1 (latest); WP trunk/nightly come job non bloccante. MySQL 8.0 e MariaDB 10.6+/10.11 (TopHost: verificare quale).
- **Elementor:** test di attivazione affiancata con Elementor latest (e versione del sito, se diversa) — nessuna integrazione Elementor in Fase 1.
- **Multisite:** non supportato in Fase 1 (dichiarato nel readme del plugin); attivazione di rete rifiutata con messaggio.

---

## 12. Rischi

| Rischio | Impatto | Mitigazione |
| --- | --- | --- |
| Versioni reali sito/hosting ignote | Requisiti minimi errati | Rilievo Site Health in sola lettura (`FASE-0-INVENTARIO.md`); D2 rivedibile |
| Limiti TopHost (memoria, max_execution_time, cron, mail, spazio) | Timeout import/migrazioni, cron non affidabile | Batch con budget, coda, misure su LocalWP con limiti simulati; cron di sistema se disponibile |
| Club Manager: formato/accesso ignoti, licenza | Sync impossibile o non lecita | Fase 3 condizionale; fallback import periodico documentato |
| Lettura che blocca Club Manager su PC multipli | Disservizio segreteria | Solo su copia finché non provato; misure d'impatto |
| Simbologia barcode ignota (Q265) | Ricerca fallita / falsi match | `code` grezzo + normalizzazione solo a formato confermato; test con tessera reale **non** committata |
| Chiave di correlazione ignota (Q319) | Duplicati o fusioni errate | Matching multiplo a proposta, nessuna fusione automatica ambigua |
| PII in repo pubblico | Violazione privacy | `.gitignore`, fixture generate, scansione pattern in CI, review PR |
| Chiave di cifratura persa | Dati illeggibili | Cifratura solo approvata, procedura custodia chiave, test restore |
| Conflitti con plugin esistenti (Elementor, TRX Addons, Amelia, Yoast) | Fatal/regressioni | Prefissi univoci, dipendenze prefissate, test affiancati su copia LocalWP |
| Documenti privati raggiungibili via URL | Data breach | Storage fuori webroot o deny verificato con test reale |
| Updater: release compromessa | Codice malevolo in produzione | Solo release del repo ufficiale, SHA-256, auto-update opzionale, protezione branch/tag |
| Licenza non definita | Incertezza su riuso/distribuzione del codice pubblico | Decisione dell'utente richiesta (v. sotto) |
| Ambizione del perimetro (30 moduli) | Ritardi, qualità | Una fase per volta, gate di approvazione |

**Punto da decidere dall'utente (non bloccante per la Fase 0):** licenza del repository. Osservazione tecnica: i plugin WordPress sono abitualmente distribuiti sotto GPLv2-or-later; con repository pubblico e senza licenza, il codice è visibile ma non riutilizzabile da terzi. Si propone di decidere prima della prima release ZIP (fine Fase 1).

---

## 13. Criteri di accettazione Fase 1

Fase 1 = core + dashboard (ROADMAP). Accettata solo se **tutti** i punti sono dimostrati con evidenze (log CI, screenshot LocalWP forniti dall'utente, checklist firmata in PR).

**Pacchetto e ciclo di vita**
1. ZIP `bracelli-club-0.1.0.zip` con directory interna `bracelli-club/`, `vendor/` di runtime e `assets/dist/` inclusi, senza file di sviluppo; SHA-256 pubblicato.
2. Installazione da ZIP, attivazione, disattivazione, riattivazione e disinstallazione su LocalWP **senza fatal, warning o notice** (`WP_DEBUG` e `WP_DEBUG_LOG` attivi) e senza perdita dati (tabelle/opzioni presenti dopo disattivazione e dopo uninstall senza flag).
3. Con PHP o WP sotto i minimi: avviso admin, nessun fatal.
4. Attivazione affiancata a Elementor (latest) e ai plugin dell'inventario disponibili in LocalWP senza errori.

**Core**
5. ModuleManager: attivazione/disattivazione idempotente, blocco per dipendenze mancanti, cascata solo dopo conferma, stato `degraded` su eccezione di un modulo senza abbattere il core — coperti da test.
6. Migrator: esecuzione ripetuta senza effetti (idempotenza), lock contro esecuzioni concorrenti, registro in `bc_migrations`, nessuna migrazione su richiesta frontend — test di integrazione.
7. Impostazioni: validazione, export/import con dry-run e diff.
8. Permissions: ruoli e capability registrati; test che ogni rotta REST e pagina admin rifiuta utenti senza capability (403/`wp_die`), inclusi tentativi diretti all'endpoint (IDOR) e richieste senza nonce.
9. Audit: eventi per attivazioni moduli, cambi impostazioni, cambi permessi; nessuna PII in chiaro nel diff; consultazione con filtri per chi ha `bracelli_audit_view`.
10. Diagnostica essenziale: versioni PHP/WP/DB, limiti (memoria, tempo), stato cron, stato moduli; diagnostica approfondita solo su richiesta.

**Dashboard**
11. Dashboard Bracelli come pagina iniziale (disattivabile per utente), contesti amministratore/dipendente distinti per capability.
12. Menu laterale configurabile; nascondimento menu WP per utente con pulsante sempre visibile per riaprirlo.
13. Temi chiaro/scuro e palette per utente, preferiti ordinabili, posizione widget, persistiti lato server e validati.
14. Widget solo con dati reali; moduli assenti mostrano "Modulo non attivo/nessun dato" — verifica che **nessun numero inventato** compaia.
15. Recovery: con JavaScript disattivato nel browser e con `BRACELLI_CLUB_SAFE_MODE`, l'amministratore accede alla pagina recovery, disattiva moduli e ripristina il menu WP.
16. Mobile (larghezza 360 px) e tastiera: navigazione completa; contrasto AA nei due temi; nessun pulsante senza funzione.

**Qualità e CI**
17. GitHub Actions verdi: `composer validate`, PHPCS (WordPress Coding Standards), PHPStan (livello concordato, proposta 6), PHPUnit unit + integration (WP test suite) sulla matrice §11, ESLint, `tsc --noEmit`, Jest, `npm run build`, Plugin Check, scansione pattern PII/segreti, build ZIP come artefatto.
18. Asset JS caricati solo nelle pagine Bracelli (verifica: nessuno script `bracelli-*` su altre pagine admin né sul frontend).
19. Misure documentate su LocalWP: tempo di risposta della dashboard e numero di query (Query Monitor), senza soglie inventate; le soglie si fissano dopo questo primo benchmark.
20. README, CHANGELOG e documento di fase aggiornati; report nel formato di `docs/TEST.md`; PR approvata dall'utente.

**Fuori dalla Fase 1** (esplicitamente): soci, tessere, import, coda, sync, connettore, documenti, pagamenti, AI.

---

## 14. Conseguenze

- Il progetto parte con un core piccolo e testabile; ogni modulo successivo si aggancia a contratti stabili (manifest, permessi, migrazioni, audit).
- Le scelte D2 (minimi), D11 (coda) e la cifratura (§5.6) sono esplicitamente rivedibili dopo i rilievi su TopHost/LocalWP.
- Le parti dipendenti da Club Manager restano bloccate dalle verifiche P0 e non compaiono come funzioni attive.

---

## 15. Emendamenti emersi nell'implementazione della Fase 1

| # | Emendamento | Motivo |
| --- | --- | --- |
| E1 | Lo stato dei moduli è salvato nell'option autoload `bracelli_club_modules` invece che nella tabella `bc_module_state` (§5.5) | Nessuna query aggiuntiva per richiesta: misurato 0 query del plugin sul frontend. Il dato è piccolo (un record per modulo) |
| E2 | Aggiornamenti da GitHub (D12, §9) **rinviati** a una fase successiva | Decisione dell'utente all'avvio della Fase 1. L'header `Update URI` non è stato aggiunto per non dichiarare un canale inesistente |
| E3 | Licenza ancora non definita | Decisione dell'utente. Plugin Check la segnala; nessun impatto funzionale |
| E4 | Codice compatibile con PHP **8.1+** (verificato con PHPCompatibility), minimo dichiarato **8.2** provvisorio | Se TopHost usa PHP 8.1 basta cambiare la costante, senza riscrivere codice |
| E5 | Coda (D11) non introdotta in Fase 1 | Nessun modulo della fase la richiede; resta prevista per l'import della Fase 2 |
| E6 | `Settings` e `ModuleManager` non hanno cache propria in memoria: leggono le option (già in cache in WordPress) | Evita incoerenze dopo scritture da altre parti (riscontrate nei test) |
| E7 | I cambi di ruolo si registrano su `add_user_role`/`remove_user_role` (non su `set_user_role`) | In WP 7.1 `set_role()` emette già i primi due: evitati eventi duplicati |
| E8 | Script PowerShell per LocalWP (FASE-0-LOCALWP §2) sostituiti, per la Fase 1, da comandi WP-CLI (`wp bracelli status|migrate|module|diagnose|dev seed-users`) e dalla guida `docs/FASE-1-COLLAUDO-LOCALWP.md` | Gli script PowerShell non sono verificabili in questa sessione (nessun Windows); i comandi WP-CLI sono testati e funzionano anche nella shell di LocalWP |
| E9 | Opzioni `bracelli_club_settings` e `bracelli_club_modules` create all'attivazione | Un'option inesistente costa una query a ogni richiesta (misurato) |
| E10 | Test end-to-end su Apache + mod_php invece del server PHP integrato | Il server integrato si bloccava con le connessioni di Chrome; Apache è anche più vicino a un hosting reale |
