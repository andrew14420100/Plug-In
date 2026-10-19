# Audit per la directory WordPress.org — Bracelli Club 0.3.1

Data: 19/10/2026. Ambiente: container Linux (non il PC del proprietario). Branch locale `audit/wordpress-org`, creato da `prototype/presenze-locale-v0.1.0` (commit a1dba73). Salvato sul solo branch `audit/wordpress-org` su autorizzazione del proprietario; nessun merge, release o invio a WordPress.org.

## Esito

**Non pronto per la pubblicazione.** Il codice è sicuro e installabile, e passa Plugin Check tranne la licenza. Restano però tre ostacoli che non dipendono dal codice e spettano al proprietario:

1. la licenza non è dichiarata;
2. ci sono segnaposto di funzioni future e un modulo dichiarato prototipo;
3. va deciso se un gestionale scritto per un solo centro sportivo debba stare nella directory pubblica. La decisione Q158 di `docs/DECISIONI.md` dice di no.

## 1. Versione esaminata

| Fonte | Contenuto | Valutazione |
| --- | --- | --- |
| `main` | Solo documentazione | — |
| `feature/fase-1-core` (PR #2) | Nucleo 0.1.0 | Superata dal prototipo |
| `prototype/presenze-locale-v0.1.0` (PR #3) | 0.3.0 + correzione Playground | **Versione più completa con sorgenti e test: base dell'audit** |
| `bracelli-club-0.1.8.zip` / `0.1.9.zip` (fuori dal repository) | 0.3.0 + grafica 0.1.5 del proprietario | Diversa dal repository, dettagli sotto |

Differenze tra lo ZIP 0.1.8 e il repository:
- `assets/css/redesign.css` e `assets/images/logo-anniversario.png` (1,3 MB, logo del 35°) sono presenti solo nello ZIP.
- 13 file PHP sono modificati: testi, logo, layout.
- Il bundle `assets/dist/dashboard/index.js` dello ZIP non corrisponde ai sorgenti in `frontend/`: contiene il logo, testi diversi e ruoli ARIA diversi. Per la linea guida 4 il codice compilato deve avere sorgenti pubblici.

Quindi lo ZIP 0.1.8 non può essere usato così com'è per la directory. Il candidato è costruito dal repository, senza la grafica 0.1.5 e senza il logo.

## 2. Requisiti WordPress.org

Esiti possibili: ✅ superato · ❌ fallito · ➖ non applicabile · ❔ non verificabile qui.

| # | Requisito | Esito | Evidenza |
| --- | --- | --- | --- |
| G1 | Licenza GPL o compatibile per tutto il pacchetto | ❌ | Nessuna licenza nell'header, nel readme o in `composer.json` («non ancora decisa»). Plugin Check: `plugin_header_no_license`, `no_license` |
| G2 | Responsabilità sui contenuti inclusi | ✅ | Nessuna libreria di terze parti inclusa. React e i pacchetti `@wordpress/*` sono esterni, forniti da WordPress. Nessun font, immagine o icona esterna (SVG inline del progetto) |
| G3 | Versione stabile nella directory | ➖ | Da gestire dopo l'eventuale approvazione |
| G4 | Codice leggibile e sorgenti disponibili | ✅ nel candidato / ❌ nello ZIP 0.1.8 | Sorgenti in `frontend/`. Build riproducibile: `npm run build` ricrea byte per byte `assets/dist`. Il readme ora rimanda al repository pubblico |
| G5 | Nessun trialware | ✅ | Nessuna funzione bloccata o a pagamento |
| G6 | SaaS | ➖ | Nessun servizio esterno |
| G7 | Nessun tracciamento senza consenso | ✅ | Unica richiesta: verifica dell'indirizzo breve sul sito stesso, solo dalla pagina admin «Area riservata». Documentata nel readme |
| G8 | Nessun codice da server esterni o aggiornamenti esterni | ✅ | Nessun updater, `eval`, download o CDN. L'aggiornamento automatico era previsto dalla roadmap: incompatibile con la directory se fatto da altri server |
| G9 | Comportamento onesto | ✅ | Nessuna affermazione di conformità legale |
| G10 | Nessun link o credito pubblico non richiesto | ✅ | Il pulsante appare solo dove si inserisce lo shortcode |
| G11 | Nessun dirottamento della bacheca | ⚠️ | Avvisi limitati (migrazione fallita). La dashboard Bracelli si apre dopo il login degli amministratori e il menu WordPress si può nascondere, sempre con un pulsante per riaprirlo. Segreteria, insegnanti e soci sono tenuti fuori da wp-admin: scelta voluta, ma un revisore potrebbe chiedere di renderla configurabile |
| G12 | Readme senza spam, massimo 5 tag | ✅ | 5 tag |
| G13 | Librerie di WordPress | ✅ | Usa `wp-element`, `wp-api-fetch`, `wp-i18n`, `react-jsx-runtime` |
| G14–15 | Commit e versioni | ✅ | Versione 0.3.1 coerente tra header, costante e Stable tag |
| G16 | Plugin completo, niente segnaposto | ❌ | Widget «Aree gestionali»: «Non ancora disponibile — Fase N» (`src/Modules/Dashboard/Widgets.php`, `frontend/src/dashboard/widgets/Areas.tsx`). Modulo «Turni del personale (prototipo)». Diagnostica «In Fase 1 il plugin non pianifica attività…» (`src/Modules/Diagnostics/Checks.php:108`). Il readme elenca le funzioni non ancora incluse |
| G17 | Marchi e nomi | ❔ | Slug `bracelli-club` libero (API: «Plugin not found»), nessun plugin simile. È ammesso solo se chi invia rappresenta legalmente il Bracelli Club |
| FAQ | Niente plugin «senza scopo pratico», framework o segnaposto | ⚠️ | Ha funzioni reali (area riservata, corsi, presenze, turni), ma è costruito per un solo centro: ruoli «Socio Bracelli», indirizzo `/area-bracelli/`, interfaccia solo in italiano. Nessuna regola scritta lo vieta, ma la revisione umana potrebbe giudicarlo non utile al pubblico |
| R1 | Readme in inglese e valido | ✅ (dopo la correzione) | Plugin Check: risolti i 2 errori di lingua e l'avviso sulla descrizione breve oltre 150 caratteri. Il validatore web non è usabile senza pubblicare il readme: usato il parser ufficiale incluso in Plugin Check |
| R2 | Contributors = utenti WordPress.org | ❌ | `bracelliclub` non esiste (profiles.wordpress.org → 404) |
| R3 | Requires at least / Requires PHP / Tested up to | ✅ | 6.9 / 8.2 / 7.1. Test eseguiti qui: WP 7.1.3 e 6.9.10 con PHP 8.3.6. PHP 8.2, 8.4 e 8.5 e MySQL 8.0 sono provati solo nella CI (ultima esecuzione verde sul commit a1dba73) |
| R4 | Text Domain uguale allo slug | ✅ | `bracelli-club` |
| R5 | Stringhe di origine in inglese | ⚠️ | Tutte le stringhe sono in italiano. Non è una regola, ma translate.wordpress.org presuppone l'inglese come lingua di origine |

## 3. Problemi trovati e correzioni

| # | Gravità | File | Causa | Soluzione | Test |
| --- | --- | --- | --- | --- | --- |
| 1 | Media | `Corsi/CourseService.php`, `CorsiSection.php`, nuova migrazione `M2026100007AddSlotAssignedFrom` | Le lezioni passate si calcolavano dal giorno e dall'insegnante *attuali*. Un insegnante appena assegnato poteva registrare lezioni del collega o di un giorno precedente, e sovrascrivere oggi la registrazione del collega | Nuova colonna `assigned_from`, aggiornata quando cambiano insegnante o giorno. Nuovo esito `before_assignment`. La registrazione di un altro insegnante resta bloccata. Il quadro mostra chi ha registrato | `CorsiTest::test_new_teacher_cannot_claim_or_overwrite_a_colleague_lessons`, `test_attendance_date_rules` aggiornato |
| 2 | Bassa | `src/Plugin.php` | Le migrazioni su `admin_init` partivano anche da `admin-ajax.php` o `admin-post.php` anonimi: dopo un fallimento, ogni visita le ripeteva | Nuovo `maybe_migrate_for_admin()`: solo con `manage_options` e mai in AJAX | `ActivationTest::test_admin_requests_migrate_only_for_administrators` |
| 3 | Bassa | `Core/SettingsPage.php` | Un ruolo Bracelli con «gestione impostazioni» poteva attivare «Elimina tutti i dati alla disinstallazione» | Solo con `manage_options`; per gli altri l'interruttore è disattivato | `AdminActionsTest::test_only_administrators_enable_data_deletion_on_uninstall` |
| 4 | Bassa | `Turni/ShiftService.php`, `TurniSection.php` | Nessun limite indietro nel tempo per registrare un turno | Limite di 30 giorni, come per i corsi (`too_old`) | `TurniTest::test_attendance_time_rules` |
| 5 | Plugin Check (errore) | `readme.txt` | Readme in italiano, descrizione breve oltre 150 caratteri | Readme in inglese con Privacy, Source code e FAQ | Plugin Check |
| 6 | Plugin Check (avviso) | `src/Plugin.php` | `load_plugin_textdomain` scoraggiato; nessuna traduzione inclusa | Rimosso | Plugin Check |

Segnalazioni verificate ma lasciate invariate (informative):
- **Verifica dell'indirizzo breve con `sslverify => false`.** Riguarda solo il sito stesso. Usare il filtro del core violerebbe le regole sui prefissi.
- **Login del portale con `wp_signon()`.** I plugin che proteggono solo `wp-login.php` (captcha, 2FA) non si applicano. Nessuna protezione propria contro i tentativi ripetuti (già documentato).
- **Transient brevi (massimo 30 minuti).** Restano dopo la disinstallazione.
- **Regola `/area-bracelli/`.** Non viene rimossa alla disattivazione; con il plugin spento la pagina risponde con la normale pagina di WordPress.
- **Nome del file CSV nel registro attività.**
- **Shortcode con il modulo «Area riservata» spento.** Compare come testo.

Verificato e corretto in tutto il codice (revisione completa):
- permessi lato server su ogni pagina, azione, REST e portale; i nonce non fanno da autorizzazione;
- controllo di proprietà su lezioni e turni;
- escaping di HTML, attributi, URL e JSON;
- query sempre con `$wpdb->prepare`;
- upload con controllo di dimensione ed estensione, letti in memoria;
- redirect con `wp_safe_redirect`;
- guardia `ABSPATH` nei file eseguibili;
- disinstallazione che cancella i dati solo con l'opzione esplicita;
- dati personali mascherati nel registro.

## 4. Controlli eseguiti (qui, 19/10/2026)

Ambiente: PHP 8.3.6, MariaDB 10.11.14, Apache, WordPress 7.1.3 (italiano) e 6.9.10, Node 22, Chromium (Playwright).

| Controllo | Superati | Falliti | Saltati | Note |
| --- | --- | --- | --- | --- |
| PHP lint (tutti i file del plugin) | tutti | 0 | 0 | |
| PHPCS (regole WordPress del progetto) | ✅ | 0 | 0 | |
| PHPStan livello 6 | — | — | — | **Non eseguibile qui**: download da GitHub bloccato dal proxy. Eseguito dalla CI sul commit a1dba73, non sulle modifiche di questo audit |
| PHPUnit unit | 38 | 0 | 0 | |
| PHPUnit integrazione, WP 7.1.3, ordine casuale | 86 | 0 | 0 | 83 prima dell'audit + 3 nuovi |
| PHPUnit integrazione, WP 6.9.10 | 86 | 0 | 0 | |
| ESLint, Stylelint, TypeScript, Jest | 14 test Jest | 0 | 0 | |
| Build JS riproducibile | ✅ | | | Nessuna differenza in `assets/dist` |
| E2E Chromium sul pacchetto candidato | 31 | 0 | 0 | `debug.log` vuoto |
| Ciclo di vita (`bin/lifecycle-check.sh`) | 8 passi | 0 | 0 | Disattivazione e riattivazione, disinstallazione con e senza dati, reinstallazione, aggiornamento forzato, aggiornamento dalla 0.1.0 |
| Aggiornamento 0.3.0 → 0.3.1 con corsi e presenze | ✅ | | | Migrazione 7 applicata, 14 orari con `assigned_from`, presenza conservata |
| Moduli disattivati dal vivo (corsi; area con dipendenti) | ✅ | | | Nessun errore; la cascata richiede `--cascade` |
| WordPress Playground (SQLite) con il pacchetto 0.3.1 | ✅ | | | Installazione da ZIP, migrazioni, import di 60 corsi e 91 orari, ruoli, presenza, accesso. Solo per valutazione: non rappresenta MySQL |
| Plugin Check 2.1.0 (tutte le categorie, anche sperimentali) | — | 2 errori | — | Solo licenza (header e readme). Prima dell'audit: 4 errori e 2 avvisi |
| Scansione dati personali e segreti | ✅ | | | 159 file tracciati |
| MySQL 8.0, PHP 8.2/8.4/8.5 | — | — | — | Non disponibili qui: solo CI |
| Accessibilità automatica (axe) e prestazioni | — | — | — | Non eseguite: strumenti non presenti nel progetto. Gli E2E verificano solo layout mobile e uso senza JavaScript |

## 5. Pacchetto candidato

| | |
| --- | --- |
| File | `dist/bracelli-club-release-candidate.zip` (copia anche nello scratchpad della sessione) |
| Versione | 0.3.1 (header, costante, Stable tag coerenti) |
| Dimensione | 163.562 byte (83 file, una sola cartella `bracelli-club/`) |
| SHA-256 | `2237dbe8e83f0169e89a4aeb44dd0bc1b5c3cd3d30e904efef0145d1d43818f2` |
| Contenuto | PHP, `readme.txt`, CSS e bundle compilati. Nessun `node_modules`, test, mappa, log, `.env`, credenziale o logo |
| Installazione di prova | WordPress 7.1.3 pulito da ZIP: attivo, dashboard caricata, 31 E2E superati |

## 6. Decisioni del proprietario

1. **Pubblicare o no.** La decisione Q158 esclude la directory WordPress.org. Va confermata o cambiata.
2. **Licenza.** Serve una licenza GPLv2 o successiva (o compatibile) per tutto il codice. È una scelta legale e commerciale: va aggiunta a header, readme, `composer.json` e a un file `LICENSE`.
3. **Account e marchio.** Serve un utente WordPress.org per `Contributors`, e la conferma che chi invia rappresenta il Bracelli Club. Il logo del 35° anniversario non è nel candidato e non va pubblicato senza i diritti.
4. **Segnaposto e prototipi.** Per la directory vanno tolti o nascosti:
   - il widget «Aree gestionali — Fase N» (dalla 0.4.1 sostituito da «Avanzamento del progetto», che però mostra ancora fasi pianificate);
   - il riferimento a «Fase 1» nella diagnostica (corretto nella 0.4.1);
   - l'etichetta «prototipo» dei Turni, oppure va escluso il modulo.

   Il widget fa parte del collaudo di Fase 1 approvato, quindi non l'ho tolto.
5. **Uso generale.** Per un pubblico generico servono:
   - nomi di ruoli e indirizzo configurabili;
   - interfaccia con stringhe di origine in inglese e traduzione italiana;
   - accesso a wp-admin configurabile per i ruoli del club.
6. **Grafica 0.1.5.** Va portata nel repository con i sorgenti del bundle, se si vuole conservarla.
7. **Aggiornamento automatico.** Quello previsto dalla roadmap da un server proprio non è ammesso per un plugin ospitato su WordPress.org.

## 7. Passi successivi prima di un eventuale invio

1. Prendere le decisioni 1–3, poi aggiungere licenza e file `LICENSE`, e correggere `Contributors`.
2. Rimuovere i segnaposto e decidere sui Turni (decisione 4).
3. Rilanciare la CI completa (PHPStan, matrice PHP 8.2–8.5, MySQL 8.0) sul branch: servono commit e push, che non ho fatto.
4. Collaudare sul PC del proprietario (LocalWP) con dati fittizi.
5. Rieseguire Plugin Check: l'obiettivo è 0 errori. Poi inviare lo ZIP dal modulo ufficiale. La revisione umana di WordPress.org resta separata e non è garantita.
