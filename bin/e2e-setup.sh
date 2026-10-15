#!/usr/bin/env bash
# Prepara un sito WordPress usa-e-getta, installa il plugin DALLO ZIP e avvia il server PHP integrato.
# SOLO per test locali/CI: crea utenti con password di prova note e azzera il database indicato.
#
# Variabili: E2E_DIR, E2E_DB_NAME, E2E_DB_USER, E2E_DB_PASS, E2E_DB_HOST, E2E_PORT, E2E_WP_VERSION, E2E_ZIP,
#            E2E_SERVER (apache | php; predefinito apache se disponibile, più vicino a un hosting reale).
set -euo pipefail

ROOT=$(cd "$(dirname "$0")/.." && pwd)
E2E_DIR=${E2E_DIR:-/tmp/bracelli-e2e}
E2E_DB_NAME=${E2E_DB_NAME:-wp_e2e}
E2E_DB_USER=${E2E_DB_USER:-wp}
E2E_DB_PASS=${E2E_DB_PASS:-wp}
E2E_DB_HOST=${E2E_DB_HOST:-127.0.0.1}
E2E_PORT=${E2E_PORT:-8890}
E2E_WP_VERSION=${E2E_WP_VERSION:-latest}
E2E_ZIP=${E2E_ZIP:-$(ls "$ROOT"/dist/bracelli-club-*.zip | head -1)}
WP="wp --path=$E2E_DIR --allow-root"
if [ -z "${E2E_SERVER:-}" ]; then
	if command -v apache2ctl >/dev/null 2>&1; then E2E_SERVER=apache; else E2E_SERVER=php; fi
fi

case "$E2E_DB_NAME" in
	*e2e*) ;;
	*) echo "Per sicurezza il database deve contenere 'e2e' nel nome." >&2; exit 1 ;;
esac

mkdir -p "$E2E_DIR"
if [ ! -f "$E2E_DIR/wp-load.php" ]; then
	$WP core download --version="$E2E_WP_VERSION" --locale=it_IT --quiet
fi
rm -f "$E2E_DIR/wp-config.php"
$WP config create --dbname="$E2E_DB_NAME" --dbuser="$E2E_DB_USER" --dbpass="$E2E_DB_PASS" --dbhost="$E2E_DB_HOST" --skip-check --quiet --extra-php <<'PHP'
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'DISABLE_WP_CRON', true );
PHP
$WP db create >/dev/null 2>&1 || true
$WP db reset --yes --quiet
$WP core install --url="http://127.0.0.1:$E2E_PORT" --title="Bracelli E2E" --admin_user=admin --admin_password=e2e-admin-pass --admin_email=admin@example.org --skip-email --quiet
$WP plugin install "$E2E_ZIP" --activate --force
$WP user create segreteria segreteria@example.org --role=bracelli_segreteria --user_pass=e2e-staff-pass --display_name="Segreteria Prova" --quiet
$WP user create abbonato abbonato@example.org --role=subscriber --user_pass=e2e-sub-pass --quiet
rm -f "$E2E_DIR/wp-content/debug.log"

if [ "$E2E_SERVER" = "apache" ]; then
	chown -R www-data:www-data "$E2E_DIR/wp-content"
	cat > /etc/apache2/sites-available/bracelli-e2e.conf <<APACHE
Listen $E2E_PORT
<VirtualHost 127.0.0.1:$E2E_PORT>
	DocumentRoot $E2E_DIR
	<Directory $E2E_DIR>
		AllowOverride All
		Require all granted
	</Directory>
	ErrorLog \${APACHE_LOG_DIR}/bracelli-e2e-error.log
</VirtualHost>
APACHE
	a2enmod -q rewrite >/dev/null
	a2ensite -q bracelli-e2e >/dev/null
	apache2ctl -k restart 2>/dev/null || apache2ctl -k start
else
	if ! curl -s -o /dev/null "http://127.0.0.1:$E2E_PORT/wp-login.php"; then
		nohup php -S "127.0.0.1:$E2E_PORT" -t "$E2E_DIR" > "$E2E_DIR/php-server.log" 2>&1 &
	fi
fi
for _ in $(seq 1 30); do
	curl -s -o /dev/null "http://127.0.0.1:$E2E_PORT/wp-login.php" && break
	sleep 0.5
done
$WP bracelli status
echo "Sito E2E pronto su http://127.0.0.1:$E2E_PORT con $E2E_SERVER (plugin da $E2E_ZIP)"
