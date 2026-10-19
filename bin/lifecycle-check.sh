#!/usr/bin/env bash
# Verifica del ciclo di vita sul sito E2E (bin/e2e-setup.sh): attivazione, disattivazione, disinstallazione
# con e senza eliminazione dati, reinstallazione e reinstallazione forzata dello stesso ZIP (aggiornamento).
set -euo pipefail

ROOT=$(cd "$(dirname "$0")/.." && pwd)
E2E_DIR=${E2E_DIR:-/tmp/bracelli-e2e}
E2E_ZIP=${E2E_ZIP:-$(ls "$ROOT"/dist/bracelli-club-*.zip | head -1)}
WP="wp --path=$E2E_DIR --allow-root"
PREFIX=$($WP db prefix)

fail() { echo "ERRORE: $*" >&2; exit 1; }
table_exists() { [ "$($WP db query "SHOW TABLES LIKE '${PREFIX}$1'" --skip-column-names)" = "${PREFIX}$1" ]; }
events() { $WP db query "SELECT COUNT(*) FROM ${PREFIX}bc_audit_events" --skip-column-names; }
shifts() { $WP db query "SELECT COUNT(*) FROM ${PREFIX}bc_turni_shifts WHERE shift_date='2000-01-01'" --skip-column-names; }

echo "1. Disattivazione: i dati restano"
$WP plugin is-active bracelli-club || $WP plugin activate bracelli-club
$WP eval 'Bracelli\Club\Plugin::instance()->audit->log("lifecycle.marker");'
# Turno fittizio di controllo (data convenzionale 2000-01-01).
$WP db query "INSERT INTO ${PREFIX}bc_turni_shifts (employee_id, shift_date, start_time, end_time, status, created_at) VALUES (0, '2000-01-01', '09:00:00', '10:00:00', 'scheduled', UTC_TIMESTAMP())"
BEFORE=$(events)
$WP plugin deactivate bracelli-club --quiet
table_exists bc_audit_events || fail "tabella eliminata alla disattivazione"
[ "$(events)" -ge "$BEFORE" ] || fail "eventi persi alla disattivazione"
[ "$(shifts)" = "1" ] || fail "turni persi alla disattivazione"

echo "2. Riattivazione idempotente"
$WP plugin activate bracelli-club --quiet
$WP bracelli migrate | grep -q "Nessuna migrazione" || fail "migrazioni rieseguite"
[ "$($WP db query "SELECT COUNT(*) FROM ${PREFIX}bc_audit_events WHERE action='lifecycle.marker'" --skip-column-names)" = "1" ] || fail "marker mancante"

echo "3. Disinstallazione SENZA flag: file rimossi, dati conservati"
$WP plugin uninstall bracelli-club --deactivate --quiet
table_exists bc_audit_events || fail "tabella eliminata senza flag"
$WP option get bracelli_club_settings >/dev/null 2>&1 || $WP option get bracelli_club_schema >/dev/null || fail "opzioni eliminate senza flag"
$WP role exists bracelli_segreteria || fail "ruolo eliminato senza flag"
$WP role exists bracelli_dipendente || fail "ruolo dipendente eliminato senza flag"
[ "$(shifts)" = "1" ] || fail "turni eliminati senza flag"
table_exists bc_corsi_courses || fail "tabella corsi eliminata senza flag"
table_exists bc_corsi_rooms || fail "tabella sale eliminata senza flag"
$WP role exists bracelli_socio || fail "ruolo socio eliminato senza flag"

echo "4. Reinstallazione dallo ZIP: dati ritrovati"
$WP plugin install "$E2E_ZIP" --activate --quiet
[ "$($WP db query "SELECT COUNT(*) FROM ${PREFIX}bc_audit_events WHERE action='lifecycle.marker'" --skip-column-names)" = "1" ] || fail "dati non ritrovati"

echo "5. Reinstallazione forzata dello stesso ZIP (percorso di aggiornamento)"
$WP plugin install "$E2E_ZIP" --force --activate --quiet
$WP bracelli status >/dev/null

echo "6. Disinstallazione CON flag: dati eliminati"
$WP eval 'Bracelli\Club\Plugin::instance()->settings->update(["uninstall.delete_data" => true]);'
$WP plugin uninstall bracelli-club --deactivate --quiet
table_exists bc_audit_events && fail "tabella non eliminata con flag"
table_exists bc_migrations && fail "registro migrazioni non eliminato con flag"
table_exists bc_turni_shifts && fail "tabella turni non eliminata con flag"
table_exists bc_turni_attendance && fail "tabella presenze non eliminata con flag"
table_exists bc_corsi_courses && fail "tabella corsi non eliminata con flag"
table_exists bc_corsi_slots && fail "tabella orari non eliminata con flag"
table_exists bc_corsi_attendance && fail "tabella presenze insegnanti non eliminata con flag"
table_exists bc_corsi_rooms && fail "tabella sale non eliminata con flag"
$WP role exists bracelli_socio 2>/dev/null && fail "ruolo socio non eliminato con flag"
$WP role exists bracelli_dipendente 2>/dev/null && fail "ruolo dipendente non eliminato con flag"
$WP role exists bracelli_segreteria 2>/dev/null && fail "ruolo non eliminato con flag"
$WP option get bracelli_club_settings >/dev/null 2>&1 && fail "opzioni non eliminate con flag"

echo "7. Installazione pulita dopo l'eliminazione"
$WP plugin install "$E2E_ZIP" --activate --quiet
table_exists bc_audit_events || fail "tabelle non ricreate"
table_exists bc_turni_shifts || fail "tabella turni non ricreata"
table_exists bc_corsi_slots || fail "tabella orari non ricreata"
table_exists bc_corsi_rooms || fail "tabella sale non ricreata"

OLD_ZIP="$ROOT/dist/bracelli-club-0.1.0.zip"
if [ -f "$OLD_ZIP" ] && [ "$OLD_ZIP" != "$E2E_ZIP" ]; then
	echo "8. Aggiornamento dalla 0.1.0 caricando il nuovo ZIP sopra quello vecchio"
	$WP eval 'Bracelli\Club\Plugin::instance()->settings->update(["uninstall.delete_data" => true]);'
	$WP plugin uninstall bracelli-club --deactivate --quiet
	$WP plugin install "$OLD_ZIP" --activate --quiet
	$WP eval 'Bracelli\Club\Plugin::instance()->audit->log("lifecycle.upgrade_marker");'
	table_exists bc_turni_shifts && fail "la 0.1.0 non dovrebbe avere la tabella turni"
	$WP plugin install "$E2E_ZIP" --force --quiet
	# Con un aggiornamento l'hook di attivazione non parte: le migrazioni girano alla prima pagina della bacheca
	# (Plugin::maybe_migrate su admin_init). Prima di allora l'area turni lo dice invece di dare errore.
	SITE_URL=$($WP option get home)
	curl -s "$SITE_URL/?bracelli_portal=1" | grep -q "Area non ancora pronta" || fail "area turni senza avviso prima della migrazione"
	$WP eval 'Bracelli\Club\Plugin::instance()->maybe_migrate();'
	table_exists bc_turni_shifts || fail "migrazione turni non eseguita dopo l'aggiornamento"
	$WP role exists bracelli_dipendente || fail "ruolo dipendente non creato dopo l'aggiornamento"
	$WP eval 'exit( get_role( "bracelli_segreteria" )->has_cap( "bracelli_turni_manage" ) ? 0 : 1 );' || fail "segreteria senza gestione turni dopo l'aggiornamento"
	[ "$($WP db query "SELECT COUNT(*) FROM ${PREFIX}bc_audit_events WHERE action='lifecycle.upgrade_marker'" --skip-column-names)" = "1" ] || fail "dati persi nell'aggiornamento"
	curl -s "$SITE_URL/?bracelli_portal=1" | grep -q 'name="pwd"' || fail "area turni non disponibile dopo la migrazione"
fi

if [ -s "$E2E_DIR/wp-content/debug.log" ]; then
	cat "$E2E_DIR/wp-content/debug.log"
	fail "debug.log non vuoto"
fi
echo "Ciclo di vita OK"
