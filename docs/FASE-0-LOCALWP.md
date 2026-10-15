# Fase 0 — Strategia LocalWP e dati simulati

**Stato:** piano. Gli script e i generatori qui descritti **non esistono ancora**: il generatore di soci sarà implementato in Fase 2 (insieme al modello soci); in Fase 1 servono solo utenti di test per ruoli.

## 1. Siti LocalWP

| Sito | Scopo | Dati | Vita |
| --- | --- | --- | --- |
| `bracelli-dev` | Sviluppo e test del plugin | Solo sintetici | Permanente, ricostruibile da script |
| `bracelli-matrix-*` (opz.) | Prove su versioni PHP/WP diverse (LocalWP permette di scegliere PHP e server web per sito) | Solo sintetici | Temporanei |
| `bracelli-copia` (opz.) | Inventario plugin/Elementor sulla copia del sito | **Reali** | Solo durante l'inventario, poi cancellato |

Configurazione comune:
- Versione PHP e server web allineati al rilievo TopHost (`FASE-0-INVENTARIO.md` §1); se ignoti, PHP 8.2 e 8.3.
- `wp-config.php`: `WP_DEBUG`, `WP_DEBUG_LOG`, `WP_DEBUG_DISPLAY=false`, `SCRIPT_DEBUG` su `bracelli-dev`; `WP_ENVIRONMENT_TYPE=local`.
- Email: catturate da Mailpit integrato in LocalWP; nessun SMTP reale.
- Plugin di diagnostica solo in locale: Query Monitor.
- Il plugin in sviluppo è collegato tramite junction/symlink da `plugin/bracelli-club` del clone del repository, oppure installato dallo ZIP di CI per i test di pacchetto.

Regole aggiuntive per `bracelli-copia` (dati reali):
- non esporre il sito (niente "Live Link" di LocalWP), `blog_public=0`;
- disattivare cron di invio e notifiche **solo nella copia locale** dopo averne annotato lo stato originale;
- nessun export o screenshot con dati personali verso il repository o la chat;
- cancellare il sito LocalWP e il backup scaricato a fine inventario.

## 2. Script di ambiente previsti (Q268) — `tools/localwp/`

| Script | Funzione | Fase |
| --- | --- | --- |
| `setup.ps1` | Verifica prerequisiti (LocalWP, PHP CLI, Composer, Node), installa dipendenze, collega il plugin, imposta `wp-config` di debug | 1 |
| `seed-users.ps1` / `wp bracelli dev seed-users` | Crea utenti fittizi per ogni ruolo Bracelli (password generate localmente, mai committate) | 1 |
| `diagnose.ps1` | Stampa versioni, limiti, stato moduli, ultimi errori di `debug.log` filtrati | 1 |
| `snapshot.ps1` / `restore.ps1` | Export/import DB del solo sito locale (`wp db export/import`) in cartella esclusa da git | 1 |
| `wp bracelli dev fixtures generate` | Generatore soci sintetici (sotto) | 2 |

I comandi WP-CLI `bracelli dev …` saranno registrati **solo** se `wp_get_environment_type()` è `local` o `development`, per impedirne l'uso in produzione.

## 3. Dataset sintetico (Fase 2)

### 3.1 Volumi

| Profilo | Soci | Relazioni indicative | Uso |
| --- | --- | --- | --- |
| `small` | 200 | ~600 | test rapidi, CI |
| `standard` | 5.000 | ~25.000 (tessere, iscrizioni, famiglie, attivazioni) | criterio ROADMAP |
| `stress` | 20.000 | ~100.000 | margine 4× e benchmark |

### 3.2 Regole di generazione

- **Deterministico** con seed (`--seed=42`): stesso input → stesso dataset, per test riproducibili.
- Nomi/cognomi da liste di nomi comuni combinati casualmente; indirizzi su vie e comuni fittizi; email solo su domini riservati per esempi (`example.org`, `example.com`); telefoni in intervalli non assegnati.
- **Codice fiscale:** formato sintatticamente valido ma con codice comune fittizio e/o carattere di controllo volutamente errato in una quota di record, così da non coincidere con persone reali. Un flag interno `synthetic=1` distingue i record generati. Strategia esatta da validare in Fase 2.
- **Barcode:** stringhe con lunghezze variabili e **zeri iniziali** (es. `000123`, `0000123` — distinti), poiché la simbologia reale è ignota (Q265).

### 3.3 Casi limite inclusi intenzionalmente

- Codici che differiscono solo per zeri iniziali; codici duplicati (collisione) su soci diversi; codice di tessera sostituita riusato.
- Soci con CF uguale (tutore registrato due volte, errore di inserimento), CF mancante, CF non valido.
- Omonimi con stessa data di nascita; stessa persona con email diverse.
- Famiglie con più minori e due tutori; minore senza tutore; tutore non socio.
- Tessera valida + iscrizione scaduta; iscrizione valida + tessera smarrita; attivato sul sito ma iscrizione scaduta; non attivato ma iscrizione valida.
- Schede incomplete (campi obbligatori mancanti); caratteri accentati, apostrofi (`D'Amico`), nomi lunghi, emoji in note.
- Record "di origine" con `external_key` per simulare import, inclusi aggiornamenti successivi con conflitti su campi modificati localmente.

### 3.4 Dove stanno i dati

- Il generatore crea i record direttamente nel DB locale; eventuali file JSON di esempio piccoli (`tests/fixtures/*.json`, ammessi dal `.gitignore`) contengono solo dati sintetici e sono revisionati in PR.
- La CI genera il profilo `small` al volo; il profilo `standard` si usa nei benchmark locali e in un job CI dedicato se i tempi lo consentono.

## 4. Misure di prestazione

- Strumenti: Query Monitor (numero/tempo query per pagina), `wp eval` con `microtime`/`memory_get_peak_usage` per comandi batch, `SAVEQUERIES` solo in locale.
- Scenari Fase 1: caricamento dashboard, pagina moduli, pagina audit con 10.000 eventi.
- Scenari Fase 2: ricerca per barcode, ricerca per nome, elenco paginato, attivazione singola, import 5.000 record a batch.
- Riportare: mediana e p95 su almeno 20 esecuzioni, versione PHP/DB, hardware. **Le soglie si fissano dopo il primo benchmark** (TEST.md); LocalWP è più veloce di un hosting condiviso, quindi i valori non vanno presentati come prestazioni TopHost.
- Simulare limiti hosting: `memory_limit` e `max_execution_time` pari ai valori TopHost rilevati.

## 5. Test di ciclo di vita del plugin su LocalWP (manuali, Fase 1)

Installazione da ZIP → attivazione → uso → disattivazione → riattivazione → aggiornamento da ZIP versione precedente → disinstallazione senza flag (dati preservati) → reinstallazione (dati ritrovati) → disinstallazione con flag "elimina dati" su sito usa-e-getta. Ogni passo con `debug.log` vuoto di errori del plugin.
