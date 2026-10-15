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

echo "1. Disattivazione: i dati restano"
$WP plugin is-active bracelli-club || $WP plugin activate bracelli-club
$WP eval 'Bracelli\Club\Plugin::instance()->audit->log("lifecycle.marker");'
BEFORE=$(events)
$WP plugin deactivate bracelli-club --quiet
table_exists bc_audit_events || fail "tabella eliminata alla disattivazione"
[ "$(events)" -ge "$BEFORE" ] || fail "eventi persi alla disattivazione"

echo "2. Riattivazione idempotente"
$WP plugin activate bracelli-club --quiet
$WP bracelli migrate | grep -q "Nessuna migrazione" || fail "migrazioni rieseguite"
[ "$($WP db query "SELECT COUNT(*) FROM ${PREFIX}bc_audit_events WHERE action='lifecycle.marker'" --skip-column-names)" = "1" ] || fail "marker mancante"

echo "3. Disinstallazione SENZA flag: file rimossi, dati conservati"
$WP plugin uninstall bracelli-club --deactivate --quiet
table_exists bc_audit_events || fail "tabella eliminata senza flag"
$WP option get bracelli_club_settings >/dev/null 2>&1 || $WP option get bracelli_club_schema >/dev/null || fail "opzioni eliminate senza flag"
$WP role exists bracelli_segreteria || fail "ruolo eliminato senza flag"

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
$WP role exists bracelli_segreteria 2>/dev/null && fail "ruolo non eliminato con flag"
$WP option get bracelli_club_settings >/dev/null 2>&1 && fail "opzioni non eliminate con flag"

echo "7. Installazione pulita dopo l'eliminazione"
$WP plugin install "$E2E_ZIP" --activate --quiet
table_exists bc_audit_events || fail "tabelle non ricreate"

if [ -s "$E2E_DIR/wp-content/debug.log" ]; then
	cat "$E2E_DIR/wp-content/debug.log"
	fail "debug.log non vuoto"
fi
echo "Ciclo di vita OK"
