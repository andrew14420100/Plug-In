# Piano di test e release

## Ambienti

- **LocalWP Windows**: sito totalmente separato da Bracelli pubblico; usare soltanto dati fittizi inizialmente.
- CI GitHub Actions: matrix PHP/WP basata sulle versioni supportate scelte in ADR; toolchain JS build per editor React e integrazione PHP.
- Staging facoltativo quando possibile senza spese obbligatorie; **mai test distruttivi in produzione**.
- Backup e restore su ambiente isolato: non dichiarare rollback testato se verificato solo un downgrade di file.

## Test per componente

| Componente | Casi minimi |
|---|---|
| Core ZIP | attivazione/disattivazione/riattivazione, multisite se realmente supportato, ruoli limitati, fallimento JS e recovery WP |
| Moduli | dipendenze, attivazione e disattivazione in cascata solo dopo conferma, registri, migrazioni, uninstall senza cancellazione non voluta |
| Sicurezza | current_user_can server, REST callback, nonce/CSRF, IDOR, XSS/SQLi, upload documenti, link privati, sessioni e privilegi |
| Tessere | zeri iniziali, barcode identici, annullamento, scaduti, rinnovo invariato codice, sostituzione e storico |
| Anagrafica | import con preview, deduplica, idempotenza, stati indipendenti, famiglie e campi obbligatori, conflitti locali/remoti |
| Attivazioni | socio in archivio ma non attivo sul sito, conferma, batch multipli, ripetizioni senza duplicati |
| Corsi/presenze | autosave, concorrenza, retrodatazione limitata, ricorrenze/festività/recuperi |
| Prenotazioni | collisioni simultanee, disponibilità, annullamento, conferme, accesso guest e antispam |
| Incassi | listini storicizzati, sconto manuale, acconti/rate, accesso admin e visibilità del personale |
| Sync | offline/online, reorder, replay, eventi persi, chiavi revocate, source read-only, stress >5.000 record |
| SEO/migrazione | metadati e canonical Yoast comparati, redirect, sitemap, nessuna perdita contenuti |
| Backup | database e file, destinazioni e spazio, integrità, prova ripristino isolata |
| Updates | build ZIP e cartella coerente, checksum/hash, compatibilità, nuova installazione, upgrade e recovery |
| Accessibilità | tastiera, contrasto, screen reader, mobile e gestione degli errori |

## CI minima proposta

- `composer validate`, `composer install` con lockfile; PHP_CodeSniffer WordPress Coding Standards, PHPStan o equivalente concordato, PHPUnit/WP integration test.
- `npm ci`, ESLint, TypeScript typecheck, test frontend e `npm run build` in pipeline; dependency audit e SBOM opzionale.
- Plugin Check e check fatal errors in WP install pulita, preparazione ZIP da asset già buildati.
- Smoke installazione ZIP, DB migration e permessi in ambiente isolato.
- Publish GitHub Release stable solo quando job obbligatori completati con successo. Preferire PR con review e tag firmati/checksum per release.
- Misura p95 query principali e tempi dashboard su dataset almeno 5.000 soci simulati; definire soglie solo dopo benchmark LocalWP/TopHost compatibile.

## Sicurezza distribuzione

- GitHub pubblico: workflow non stampano segreti; permessi GitHub Actions minimi, niente trigger di codice non fidato con token privilegiati.
- ZIP moduli esterni: firma o hash/verified provenance se disponibile, validare directory, percorsi, tipo file, versioni; review esplicita. I checksum **non garantiscono da soli sicurezza del codice**.
- Aggiornamenti: distinguere *creazione automatica release dopo CI* e *applicazione su produzione*. Le decisioni dell'utente favoriscono auto-update, ma per migrazioni irreversibili richiedere comunque gate/protezione e consenso specifico.
- Rilevare differenze backup/schema prima di ripristino; database restore sotto conferma.
- Non inviare dati personali nei log/corpi errori o telemetria senza adeguata base e controllo.

## Riepilogo output di Claude per ogni fase

```text
FASE:
Commit/branch:
Moduli realmente implementati:
Test eseguiti con comandi ed esito:
Test non eseguiti e ragioni:
Compatibilità verificata (versioni esatte):
Benchmark veri (se disponibili):
File ZIP e checksum:
Rischi residui:
Cosa serve per l'approvazione:
```

**Stop release** se test falliscono, migrazioni producono errori gravi, backup non verificati prima dei cambiamenti necessari, permessi non sicuri o funzioni visibili sono simulate.
