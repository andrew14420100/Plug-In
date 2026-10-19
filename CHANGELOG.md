# Changelog

Formato ispirato a [Keep a Changelog](https://keepachangelog.com/it/1.1.0/); versioni secondo [SemVer](https://semver.org/lang/it/). Nessuna versione del plugin è stata ancora rilasciata.

## [Non rilasciato]

### Audit WordPress.org (plugin 0.3.1, candidato locale, non rilasciato)
- Sicurezza: l'insegnante non registra più lezioni precedenti alla propria assegnazione all'orario (nuova colonna `assigned_from`, migrazione `2026_10_0007`) né modifica la registrazione di un collega; il quadro della segreteria mostra chi ha registrato.
- Sicurezza: il dipendente registra i turni solo fino a 30 giorni prima; solo un amministratore (`manage_options`) attiva «Elimina tutti i dati alla disinstallazione»; le migrazioni da `admin_init` partono solo per gli amministratori (non più da `admin-ajax.php`/`admin-post.php` anonimi).
- `readme.txt` in inglese con sezioni Privacy e Source code; descrizione dell'header in inglese; rimosso `load_plugin_textdomain` (nessuna traduzione inclusa).
- Test: 3 di integrazione nuovi e 2 aggiornati. Rapporto: `docs/AUDIT-WORDPRESS-ORG.md`.

### Aggiunto — Area riservata, corsi e presenze insegnanti (plugin 0.3.0, solo prove locali, non rilasciato)
- Modulo `area`: portale frontend unico (accesso con nome utente o email tramite WordPress, nessuna registrazione pubblica), indirizzo canonico `?bracelli_portal=1` con indirizzo breve verificato, shortcode `[bracelli_area_riservata]` per il pulsante nell'header, segreteria/insegnanti/dipendenti/soci tenuti fuori dalla bacheca WordPress.
- Modulo `corsi`: corsi, orari settimanali e presenze degli insegnanti (tabelle `bc_corsi_*`, migrazioni `2026_10_0005` e `2026_10_0006`); importazione CSV ripetibile con anteprima, conflitti con le modifiche manuali e rapporto (bacheca e `wp bracelli-corsi import`); viste Calendario corsi, Le mie lezioni (Presente/Assente), Presenze insegnanti e Orari dei corsi con storico e annullamento senza cancellazione.
- Ruolo "Socio Bracelli"; l'Istruttore è l'insegnante; capability `bracelli_corsi_view|teach|manage|import`.
- Il modulo Turni diventa una sezione dell'area riservata (stesso portale).
- Test: 18 di integrazione e 9 end-to-end nuovi; ciclo di vita esteso; documento `docs/AREA-RISERVATA-CORSI.md`.
- Migratore compatibile con WordPress Playground (SQLite non ha `GET_LOCK`: prima le migrazioni restavano «locked»); istruzioni di prova in Playground.

### Aggiunto — Prototipo Turni e presenze (plugin 0.2.0, solo LocalWP, non rilasciato)
- Modulo `turni` 0.1.0: area riservata frontend `/area-bracelli/` indipendente dal tema, con accesso tramite le credenziali WordPress.
- Segreteria: elenco turni con filtri e riepilogo presenze, creazione, modifica di data e orario, annullamento con storico (nessuna eliminazione).
- Dipendente: solo i propri turni, registrazione Presente/Assente dal giorno del turno (modificabile solo quel giorno); nessun accesso alla bacheca WordPress né barra di amministrazione.
- Ruolo "Dipendente Bracelli" e capability `bracelli_turni_manage` (Segreteria, Amministratore) e `bracelli_turni_self`; migrazioni `2026_10_0003` e `2026_10_0004`; tabelle `bc_turni_shifts` e `bc_turni_attendance`, eliminate solo con il flag di disinstallazione.
- Test: 19 di integrazione e 8 end-to-end nuovi; `bin/lifecycle-check.sh` verifica anche turni e aggiornamento dalla 0.1.0.
- Documento `docs/PROTOTIPO-TURNI-PRESENZE.md`.

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
