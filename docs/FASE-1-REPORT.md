# Rapporto Fase 1 — Nucleo WordPress e dashboard

Formato da `docs/TEST.md` ("Riepilogo output di Claude per ogni fase").

```text
FASE: 1 — Core WordPress e dashboard
Commit/branch: feature/fase-1-core (PR verso main; include il commit della Fase 0 finché la PR #1 non è unita)
Versione plugin: 0.1.0
```

## Moduli realmente implementati

| Modulo | Obbligatorio | Contenuto |
| --- | --- | --- |
| `core` — Nucleo | sì | Bootstrap, autoloader PSR-4 senza dipendenze runtime, manifest e gestore moduli (dipendenze semver, cicli, stato `active/inactive/blocked/degraded`, cascata solo su conferma), migratore versionato con lock `GET_LOCK`, impostazioni con schema/validazione/export/import con anteprima, menu "Bracelli Club", pagine Moduli e Impostazioni, WP-CLI `wp bracelli …` |
| `permissions` — Permessi | sì | Ruoli `bracelli_segreteria`, `bracelli_istruttore`, `bracelli_gestore_corsi`; 7 capability atomiche; matrice ruoli × capability; "Gestione permessi" non assegnabile ad altri ruoli; ripristino predefiniti |
| `audit` — Registro attività | sì | Tabella `{prefix}bc_audit_events` append-only, mascheramento di dati personali (chiavi sensibili, email, codici fiscali), impronta IP facoltativa, API REST con filtri e paginazione, pagina React con alternativa PHP senza JavaScript, eventi su cambi di ruolo Bracelli |
| `recovery` — Ripristino | sì | Pagina PHP senza JavaScript (capability nativa `manage_options`), modalità sicura `BRACELLI_CLUB_SAFE_MODE`, disattivazione moduli, ripristino menu WordPress e preferenze, riesecuzione migrazioni, ultimi errori |
| `dashboard` — Dashboard | no | Dashboard React come pagina iniziale dopo il login (disattivabile per utente), contesti amministratore/dipendente, temi automatico/chiaro/scuro, 5 tavolozze, menu laterale riordinabile/nascondibile, widget riordinabili (trascinamento + pulsanti accessibili) e nascondibili, preferiti con icona ed etichetta, menu WordPress nascondibile con pulsante sempre visibile |
| `diagnostics` — Diagnostica | no | 15 controlli essenziali (versioni, limiti, estensioni, cron, HTTPS, migrazioni, tabelle, moduli, modalità sicura) e diagnostica approfondita solo su richiesta |

**Widget con dati reali soltanto:** benvenuto, preferiti, stato moduli (conteggi reali), salute del sistema, attività recenti. Le aree delle fasi future (soci e tessere, corsi, prenotazioni, incassi, documenti, sito/SEO) sono mostrate come **"Non ancora disponibile — Fase N"**, senza alcun numero (verificato da test PHP, Jest ed E2E).

**Non incluso (come da roadmap e indicazioni):** soci, tessere, coda, import, connettore Club Manager, documenti, pagamenti, AI, aggiornamenti automatici da GitHub (rinviati su richiesta dell'utente).

## Test eseguiti (comandi ed esito)

Ambiente: container Linux, PHP 8.3.6, MariaDB 10.11.14, Apache 2.4.58 + mod_php, WordPress 7.1.3 (e 6.9.10, 6.8.3 dove indicato), Node 22.22.0, Chromium (Playwright 1.56.1).

| Verifica | Comando | Esito |
| --- | --- | --- |
| Test unitari PHP (senza WordPress) | `vendor/bin/phpunit -c phpunit-unit.xml.dist` | **38 test, 95 asserzioni — OK** |
| Test di integrazione PHP, WP 7.1.3 | `vendor/bin/phpunit -c phpunit-integration.xml.dist` (+ `--order-by=random`) | **45 test, 224 asserzioni — OK** in ordine fisso e con 10 semi casuali |
| Test di integrazione PHP, WP 6.9.10 | idem con test suite 6.9 installata da `bin/install-wp-tests.sh` (ripiego git, senza svn) | **45 test — OK** |
| PHPCS (WordPress-Extra + PHPCompatibilityWP 8.1+) | `vendor/bin/phpcs` | **0 errori, 0 avvisi** |
| PHPStan livello 6 + estensione WordPress | `vendor/bin/phpstan analyse` | **Nessun errore** |
| ESLint, Stylelint | `npm run lint:js`, `npm run lint:css` | **0 problemi** |
| TypeScript strict | `npm run typecheck` | **OK** |
| Jest + Testing Library | `npm run test:js` | **14 test — OK** |
| Build bundle separati | `npm run build` | dashboard 24 KB, registro 6,5 KB (React fornito da WordPress) |
| ZIP | `bin/build-zip.sh` | 60 file in un'unica cartella `bracelli-club/`, nessun file di sviluppo |
| Ciclo di vita su WordPress reale | `bin/lifecycle-check.sh` | **OK**: disattivazione e riattivazione senza perdita dati; disinstallazione senza flag conserva tabelle/opzioni/ruoli; reinstallazione ritrova i dati; reinstallazione forzata dello stesso ZIP; disinstallazione con flag elimina tutto; reinstallazione pulita |
| Test end-to-end nel browser (plugin installato dallo ZIP, Apache) | `bin/e2e-setup.sh && npm run test:e2e` | **13 test — OK**, 3 esecuzioni consecutive senza fallimenti; `debug.log` vuoto (nessun warning/notice PHP) |
| Plugin Check 2.1.0 (WordPress.org) | `wp plugin check bracelli-club` | Solo segnalazioni attese, vedi sotto |
| Requisiti minimi su WordPress 6.8.3 | installazione manuale | L'installer rifiuta lo ZIP; l'attivazione è rifiutata con messaggio; se forzato attivo nel DB mostra "Requisiti mancanti" e non carica codice: **nessun fatal** |
| Scansione dati personali/segreti | `bin/check-public-safety.sh` | Nessun riscontro |

**Scenari E2E coperti:** login → dashboard; nessun errore JavaScript; tema scuro e tavolozza persistenti (anche nelle pagine PHP); preferiti, riordino e widget nascosti persistenti; ripristino preferenze; menu WordPress nascosto e riaperto dal pulsante fisso; layout a 360 px senza scorrimento orizzontale; segreteria in contesto dipendente con **403** su Moduli/Impostazioni/Permessi/Registro/Ripristino; REST: anonimo **401**, segreteria su registro **403**, senza nonce **401**, nonce falso **403**, valore non valido **400**; abbonato **403** sulla dashboard; con JavaScript disattivato: collegamenti `<noscript>`, Ripristino e Diagnostica funzionanti; disattivazione/riattivazione modulo riflessa sulla dashboard; registro con filtri e dettagli; export/import impostazioni con anteprima; concessione e revoca permessi alla segreteria verificata con un secondo utente.

**Revisione di sicurezza mirata:** capability verificate in ogni pagina, azione admin-post e rotta REST; nonce su tutte le azioni; query con segnaposto; output con escape; redirect validati; file di importazione verificati (caricamento, dimensione, formato); preferiti limitati a un elenco chiuso di pagine. Correzione applicata durante la revisione: i dati iniziali della dashboard incorporati in `<script>` ora usano `JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT`; un test lo verifica e fallisce se la protezione viene rimossa (verificato con una mutazione).

**Plugin Check — segnalazioni residue e motivazione:**

| Codice | Motivo |
| --- | --- |
| `plugin_header_no_license`, `no_license` | Licenza lasciata aperta su indicazione dell'utente |
| `readme_*_non_official_language` | Regola della sola directory WordPress.org (il plugin non vi sarà pubblicato, Q158) |
| `load_plugin_textdomain` (avviso) | Necessario proprio perché il plugin non è ospitato su WordPress.org |

## Test non eseguiti e ragioni

| Test | Ragione |
| --- | --- |
| Installazione su **LocalWP Windows** | Non disponibile in questa sessione (container Linux). Procedura per l'utente: `docs/FASE-1-COLLAUDO-LOCALWP.md` |
| Matrice PHP 8.2 / 8.4 / 8.5 e MySQL 8.0 | Nel container c'è solo PHP 8.3 e MariaDB. La matrice completa è nel workflow GitHub Actions `.github/workflows/ci.yml`: l'esito si legge sulla PR |
| Compatibilità con Elementor e con i plugin del sito | L'inventario del sito (Fase 0) non è ancora stato compilato; test affiancato da fare in LocalWP su copia |
| Hosting TopHost (limiti, cron, mail, web server) | Nessun accesso; minimi PHP/WP restano **provvisori** |
| Lettori screen reader reali | Verificate solo struttura (ruoli ARIA, etichette, tastiera nei test) e contrasto dei colori |

## Compatibilità verificata (versioni esatte)

- WordPress **7.1.3** (unit, integrazione, E2E, ciclo di vita, Plugin Check) e **6.9.10** (integrazione).
- PHP **8.3.6**; sintassi verificata compatibile con PHP **8.1+** da PHPCompatibility (abbassare il minimo provvisorio non richiede modifiche al codice).
- MariaDB **10.11.14**; Apache **2.4.58** con mod_php.
- Rifiuto controllato su WordPress **6.8.3**.

## Benchmark reali (container, non rappresentativo di TopHost)

Apache + mod_php, 4 vCPU Xeon 2.8 GHz, 25 richieste per URL, utente amministratore:

| URL | Mediana | p95 | Query | Memoria picco |
| --- | --- | --- | --- | --- |
| Dashboard Bracelli (`admin.php?page=bracelli`) | 80,5 ms | 96,9 ms | 28 | 7 MB |
| API `/bracelli/v1/dashboard` | 32,7 ms | 38,0 ms | 14 | 5,2 MB |
| Registro attività | 69,8 ms | 76,1 ms | 26 | 7 MB |
| Bacheca WP — plugin attivo / disattivo | 93,5 / 81,8 ms | 119 / 112 ms | 40 / 40 | 7 / 7 MB |
| Home pubblica — plugin attivo / disattivo | 85,9 / 94,4 ms | 104 / 124 ms | **31 / 31** | 6,6 / 6,5 MB |

Il plugin non aggiunge query sul frontend e non carica asset fuori dalle proprie pagine. Le differenze di tempo sono entro il rumore di misura. Nessuna soglia è stata fissata: va definita dopo il benchmark in LocalWP/TopHost (TEST.md).

## File ZIP e checksum

- `dist/bracelli-club-0.1.0.zip` — 98.857 byte, 60 file
- SHA-256 (build locale consegnata): `623f399d1b19f3f7a339ef2b0a33217b68706dc9de5bf1777524cd4eb09be414`
- La CI pubblica anche lo ZIP come artefatto `bracelli-club-zip`; la build CI ha un checksum proprio (date dei file diverse), riportato nel file `.sha256` accanto allo ZIP.
- **Non è una release:** nessun tag creato. La release si fa solo dopo l'approvazione (CLAUDE.md §7).

## Rischi residui

1. Requisiti minimi provvisori (PHP 8.2, WP 6.9) da confermare su TopHost.
2. Coesistenza con Elementor/tema ThemeREX/altri plugin non ancora provata.
3. Il nascondimento del menu WordPress usa CSS su `#adminmenumain`: va riverificato se un plugin o una versione futura di WordPress cambiano la struttura dell'admin (il pulsante di riapertura e la pagina di ripristino restano comunque disponibili).
4. Il server di test integrato di PHP si è dimostrato inaffidabile con Chrome: gli E2E usano Apache. In LocalWP si userà il server web di LocalWP.
5. Le etichette sono scritte direttamente in italiano (dominio `bracelli-club` pronto per eventuali traduzioni).

## Cosa serve per l'approvazione

1. Esito verde della CI sulla PR (matrice PHP 8.2–8.5 × WP 6.9 / 7.0 / ultima).
2. Collaudo manuale in LocalWP seguendo `docs/FASE-1-COLLAUDO-LOCALWP.md`.
3. Approvazione della PR. Solo dopo si potrà creare il tag `v0.1.0` e avviare la Fase 2.
