=== Bracelli Club ===
Contributors: bracelliclub
Tags: gestionale, centro sportivo, dashboard
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.3.0

Gestionale modulare del centro sportivo Bracelli Club: nucleo della Fase 1, area riservata, corsi e presenze degli insegnanti, turni del personale (solo per prove in locale).

== Description ==

Plugin ad uso esclusivo del sito Bracelli Club (non destinato alla directory WordPress.org).

Novità della versione 0.3.0 (solo prove in locale):

* Area riservata unica con accesso tramite nome utente o email; pulsante con lo shortcode [bracelli_area_riservata].
* Corsi e orari importati da file CSV con anteprima; calendario dei corsi, lezioni dell'insegnante con Presente/Assente, quadro delle presenze e gestione degli orari per la segreteria.
* Ruolo "Socio Bracelli"; l'Istruttore Bracelli è l'insegnante.

Prototipo della versione 0.2.0 (solo per prove in LocalWP, non per il sito pubblico):

* Modulo "Turni e presenze": area riservata frontend all'indirizzo /area-bracelli/ (o ?bracelli_portal=1 con permalink semplici).
* La segreteria crea, modifica e annulla i turni dei dipendenti e consulta presenze e assenze; i turni non si eliminano.
* Il dipendente vede solo i propri turni e registra Presente o Assente; non entra nella bacheca WordPress.
* Nuovo ruolo "Dipendente Bracelli"; capability "Gestione turni" (segreteria) e "Dipendente con turni".

Funzioni della Fase 1 (dalla versione 0.1.0):

* Nucleo modulare con catalogo moduli, dipendenze, attivazione e disattivazione a cascata solo su conferma.
* Dashboard Bracelli con contesto amministratore e dipendente, temi chiaro/scuro, tavolozze, widget riordinabili e preferiti per utente.
* Ruoli Bracelli (segreteria, istruttore, gestore corsi) e matrice dei permessi verificata lato server.
* Registro attività con dati personali mascherati.
* Diagnostica essenziale e approfondita su richiesta.
* Pagina di ripristino senza JavaScript e modalità sicura (`define( 'BRACELLI_CLUB_SAFE_MODE', true );`).
* Impostazioni con esportazione e importazione con anteprima delle differenze.
* Comandi WP-CLI: `wp bracelli status|migrate|module|diagnose`.

Non include ancora: archivio soci, tessere, corsi, prenotazioni, incassi, documenti, sincronizzazione con Club Manager, aggiornamenti automatici. Le aree corrispondenti sono indicate come "non ancora disponibili".

I requisiti minimi PHP 8.2 e WordPress 6.9 sono provvisori fino alla verifica dell'hosting.

Licenza: non ancora definita dal proprietario del progetto.

== Installation ==

1. Fai un backup del sito.
2. Carica `bracelli-club-0.3.0.zip` da Plugin → Aggiungi nuovo → Carica plugin.
3. Attiva il plugin. Le tabelle `{prefisso}bc_migrations`, `{prefisso}bc_audit_events`, `{prefisso}bc_turni_shifts` e `{prefisso}bc_turni_attendance` vengono create automaticamente.

La disattivazione e la disinstallazione NON eliminano i dati, salvo l'opzione esplicita "Elimina tutti i dati alla disinstallazione".

== Changelog ==

= 0.3.0 =
* Area riservata, corsi e presenze degli insegnanti (prova locale, non rilasciato).

= 0.2.0 =
* Prototipo "Turni e presenze" per le prove in locale (non rilasciato).

= 0.1.0 =
* Prima versione: Fase 1 (nucleo e dashboard).
