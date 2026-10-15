# Changelog

Formato ispirato a [Keep a Changelog](https://keepachangelog.com/it/1.1.0/); versioni secondo [SemVer](https://semver.org/lang/it/). Nessuna versione del plugin è stata ancora rilasciata.

## [Non rilasciato]

### Aggiunto — Fase 1 (plugin 0.1.0, non ancora rilasciato)
- Plugin `bracelli-club` installabile da ZIP con guardie su PHP/WordPress/Multisite (nessun fatal sotto i minimi provvisori PHP 8.2 / WP 6.9).
- Nucleo modulare: manifest, dipendenze con vincoli di versione, rilevamento cicli, stati attivo/disattivato/bloccato/errore, disattivazione a cascata solo su conferma, isolamento degli errori di avvio.
- Migratore versionato e idempotente con registro `{prefix}bc_migrations` e lock di database; mai eseguito sul frontend.
- Ruoli Bracelli e 7 capability atomiche con matrice permessi; controlli sempre lato server (pagine, azioni, REST).
- Registro attività `{prefix}bc_audit_events` con mascheramento dei dati personali, API REST e interfaccia con filtri.
- Dashboard React: contesto amministratore/dipendente, temi chiaro/scuro/automatico, 5 tavolozze, widget e menu personalizzabili, preferiti, menu WordPress nascondibile con pulsante sempre visibile, apertura dopo il login.
- Diagnostica essenziale e approfondita su richiesta; pagina di ripristino senza JavaScript; modalità sicura `BRACELLI_CLUB_SAFE_MODE`.
- Impostazioni globali con esportazione e importazione con anteprima delle differenze.
- Comandi WP-CLI `wp bracelli status|migrate|module|diagnose|dev seed-users`.
- Test: 38 unitari, 45 di integrazione, 14 Jest, 13 end-to-end; PHPCS, PHPStan livello 6, ESLint, Stylelint; script di build ZIP, ciclo di vita e scansione dati personali; workflow CI.
- Documenti `docs/FASE-1-REPORT.md` e `docs/FASE-1-COLLAUDO-LOCALWP.md`; emendamenti all'ADR (§15).

### Aggiunto — Fase 0
- Fase 0: `docs/ADR-001-core.md` (architettura proposta del core, mappa moduli e dipendenze, modello dati preliminare, permessi, migrazioni, updater, sincronizzatore futuro, rischi, criteri di accettazione Fase 1).
- Fase 0: `docs/FASE-0-INVENTARIO.md` (procedure in sola lettura per versioni, plugin, Elementor e ricognizione Club Manager).
- Fase 0: `docs/FASE-0-LOCALWP.md` (strategia LocalWP e dataset sintetico 5.000+ soci).
- Fase 0: `docs/FASE-0-REPORT.md` (verifiche eseguite e non eseguite).
- Documentazione iniziale di progetto (README, CLAUDE.md, specifica, decisioni, architettura, Club Manager, roadmap, test).
