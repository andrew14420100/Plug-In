# Bracelli Club Plug-In

**Stato:** Fase 0 approvata. **Fase 1 (nucleo e dashboard) implementata in `plugin/bracelli-club`, versione 0.1.0, in attesa di collaudo e approvazione.** **Prototipo in prova locale (plugin 0.3.0, branch `prototype/presenze-locale-v0.1.0`): area riservata, corsi dai volantini e presenze degli insegnanti, turni del personale.** **Audit WordPress.org e candidato 0.3.1 sul branch locale `audit/wordpress-org`: vedi `docs/AUDIT-WORDPRESS-ORG.md`.** Nessuna release pubblicata.  
**Repository ufficiale:** https://github.com/andrew14420100/Plug-In  
**Destinazione:** https://centrosportivobracelliclub.it/ (NON intervenire in produzione durante lo sviluppo).  
**Strumenti:** Claude Code, WordPress, PHP, React/TypeScript, LocalWP (Windows), GitHub Actions.  
**Costo:** nessun abbonamento o servizio esterno a pagamento obbligatorio.

## Obiettivo

Un unico plugin WordPress modulare per centralizzare le funzioni del sito e del centro sportivo. Sostituzione graduale dei plugin esistenti **solo dopo inventario, test, backup, migrazione verificata e approvazione**. Inizialmente Club Manager resta operativo: dati ricevuti con sincronizzazione *unidirezionale Club Manager → WordPress*, da progettare **solo dopo l'analisi della sorgente**.

### Come orientarsi

| File | Contenuto |
| --- | --- |
| [CLAUDE.md](CLAUDE.md) | Istruzioni vincolanti per Claude Code e primo incarico |
| [docs/SPECIFICA.md](docs/SPECIFICA.md) | Requisiti funzionali e non funzionali per moduli |
| [docs/DECISIONI.md](docs/DECISIONI.md) | Registro delle decisioni e dei punti ancora aperti |
| [docs/ARCHITETTURA.md](docs/ARCHITETTURA.md) | Struttura tecnica, dati, sicurezza e integrazioni |
| [docs/CLUB_MANAGER.md](docs/CLUB_MANAGER.md) | Regole e verifiche per la sincronizzazione del gestionale |
| [docs/ROADMAP.md](docs/ROADMAP.md) | Fasi, dipendenze e criteri di accettazione |
| [docs/TEST.md](docs/TEST.md) | Strategia di verifica, CI, prestazioni e rilascio |
| [docs/ADR-001-core.md](docs/ADR-001-core.md) | Fase 0: architettura proposta del core, moduli, dati, permessi, criteri Fase 1 |
| [docs/FASE-0-INVENTARIO.md](docs/FASE-0-INVENTARIO.md) | Fase 0: rilievo versioni, inventario plugin, ricognizione Club Manager (da compilare) |
| [docs/FASE-0-LOCALWP.md](docs/FASE-0-LOCALWP.md) | Fase 0: ambiente LocalWP e dati sintetici |
| [docs/FASE-0-REPORT.md](docs/FASE-0-REPORT.md) | Fase 0: verifiche eseguite/non eseguite e richieste di approvazione |
| [docs/FASE-1-REPORT.md](docs/FASE-1-REPORT.md) | Fase 1: moduli implementati, test eseguiti, benchmark, rischi |
| [docs/FASE-1-COLLAUDO-LOCALWP.md](docs/FASE-1-COLLAUDO-LOCALWP.md) | Fase 1: checklist di collaudo manuale in LocalWP |
| [docs/PROTOTIPO-TURNI-PRESENZE.md](docs/PROTOTIPO-TURNI-PRESENZE.md) | Prototipo Turni e presenze: decisioni, installazione in LocalWP, prova dei ruoli, test, limiti |
| [docs/AREA-RISERVATA-CORSI.md](docs/AREA-RISERVATA-CORSI.md) | Area riservata, pulsante shortcode, corsi importati e presenze degli insegnanti: decisioni, importazione, prova, test, limiti |
| [CHANGELOG.md](CHANGELOG.md) | Registro delle modifiche |

## Principi non negoziabili

1. **Una funzione vera o nessuna funzione.** Nessun pulsante simulato, report inventato o finta sincronizzazione.
2. **Non toccare il sito in produzione** senza approvazione esplicita. Operare prima in LocalWP su Windows.
3. **Non modificare Club Manager**, il suo database o il suo archivio; primo approccio esclusivamente di lettura, soltanto se supportato/autorizzato e dopo backup.
4. **Soci WordPress separati dagli account WP.** Non creare automaticamente utenti WordPress per ciascun socio.
5. **Privacy:** nessun dato personale dei soci, certificato, segreto, password, dump di produzione o chiave API in questo repository pubblico; usare dati fittizi per i test.
6. **Moduli indipendenti:** stato attivo/disattivo, dipendenze, permessi, versioni, diagnostica e conservazione dati salvo cancellazione esplicita conforme alle norme.
7. **Sicurezza by default:** capability checks server-side, nonce dove appropriato, REST permission callbacks, validazione e escaping, query preparate, accessi ai documenti verificati.
8. **Prestazioni:** hosting condiviso TopHost, almeno 1.001–5.000 soci/anno, ~5 operatori contemporanei attesi; code limitate, elaborazione a blocchi, niente processi pesanti su ogni richiesta.
9. **Aggiornamenti:** release testate, ZIP verificati, backup prima delle migrazioni, recupero, nessuna perdita silenziosa di dati.
10. **Incrementalità:** progettare l'architettura completa, implementare e verificare una fase alla volta.

## Sviluppo

```text
plugin/bracelli-club/   il plugin (PHP senza dipendenze runtime, asset compilati in assets/dist)
frontend/               sorgenti React/TypeScript (bundle separati: dashboard, registro)
tests/                  unit, integration (test suite WordPress), e2e (Playwright)
bin/                    install-wp-tests, build-zip, e2e-setup, lifecycle-check, check-public-safety
```

```bash
composer install && npm ci
npm run build                      # asset React
vendor/bin/phpunit -c phpunit-unit.xml.dist
bin/install-wp-tests.sh wp_tests root '' 127.0.0.1 latest
vendor/bin/phpunit -c phpunit-integration.xml.dist
vendor/bin/phpcs && vendor/bin/phpstan analyse
npm run lint:js && npm run lint:css && npm run typecheck && npm run test:js
bin/build-zip.sh                   # dist/bracelli-club-<versione>.zip + .sha256
bin/e2e-setup.sh && bin/lifecycle-check.sh && bin/e2e-setup.sh && npm run test:e2e
```

La CI (`.github/workflows/ci.yml`) esegue tutto su PHP 8.2–8.5 × WordPress 6.9 / 7.0 / ultima, più MariaDB, e pubblica lo ZIP come artefatto.

## Avvio con Claude Code

Apri il repository in Claude Code e impartisci:

> Leggi integralmente `CLAUDE.md` e tutti i documenti nella cartella `docs/`. Esegui SOLTANTO la Fase 0 di `docs/ROADMAP.md`: inventario tecnico, ADR di architettura, verifica dipendenze e piano di test, senza modificare WordPress pubblico né Club Manager. Mostrami cosa hai verificato, cosa rimane ignoto e il piano per la Fase 1. Non dichiarare moduli funzionanti prima di averli implementati e testati.

**Nota:** le decisioni 1–339 provengono da un questionario iterativo. I documenti raccolgono le scelte tecniche e funzionali; per alcuni dettagli rimangono verifiche aperte. Non interpretare il numero di domande come il numero di funzionalità già implementate.
