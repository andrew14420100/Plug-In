#!/usr/bin/env bash
# Controlla i file tracciati da git (repository PUBBLICO): niente dati personali, segreti o dump.
# Esce con errore se trova corrispondenze. Eccezioni ammesse:
#  - email su domini riservati agli esempi (example.org/.com/.net) e l'indirizzo noreply degli autori automatici;
#  - codici fiscali di prova con codice comune fittizio Z999.
set -euo pipefail
cd "$(dirname "$0")/.."

FILES=$(git ls-files)
STATUS=0

report() {
	echo "✗ $1"
	echo "$2" | head -20
	STATUS=1
}

BAD_FILES=$(echo "$FILES" | grep -E '(^|/)\.env($|\.)|\.sql(\.gz)?$|\.sqlite3?$|\.db$|\.dump$|\.bak$|\.pem$|\.key$|\.p12$|\.pfx$|wp-config\.php$|\.csv$|\.xlsx?$' | grep -v '\.env\.example$' || true)
[ -n "$BAD_FILES" ] && report "File non ammessi nel repository pubblico" "$BAD_FILES"

TEXT_FILES=$(echo "$FILES" | grep -vE '\.(png|jpe?g|gif|ico|woff2?|zip)$|package-lock\.json$|composer\.lock$' || true)

EMAILS=$(echo "$TEXT_FILES" | xargs grep -nHoE '[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}' 2>/dev/null | grep -vE '@example\.(org|com|net)$|noreply@anthropic\.com$|@wordpress/|@testing-library/|@playwright/|@types/' || true)
[ -n "$EMAILS" ] && report "Indirizzi email non di esempio" "$EMAILS"

CF=$(echo "$TEXT_FILES" | xargs grep -nHoE '\b[A-Z]{6}[0-9LMNPQRSTUV]{2}[A-EHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]\b' 2>/dev/null | grep -v 'Z999' || true)
[ -n "$CF" ] && report "Possibili codici fiscali reali" "$CF"

SECRETS=$(echo "$TEXT_FILES" | xargs grep -nHE 'BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY|ghp_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}|sk-[A-Za-z0-9]{20,}|AKIA[0-9A-Z]{16}|xox[baprs]-[A-Za-z0-9-]{10,}' 2>/dev/null || true)
[ -n "$SECRETS" ] && report "Possibili segreti o chiavi" "$SECRETS"

IBAN=$(echo "$TEXT_FILES" | xargs grep -nHoE '\bIT[0-9]{2}[A-Z][0-9]{10}[0-9A-Z]{12}\b' 2>/dev/null || true)
[ -n "$IBAN" ] && report "Possibili IBAN" "$IBAN"

if [ "$STATUS" -eq 0 ]; then
	echo "✓ Nessun dato personale, segreto o file vietato nei $(echo "$FILES" | wc -l) file tracciati."
fi
exit "$STATUS"
