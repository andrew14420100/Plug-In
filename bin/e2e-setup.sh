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
# Prototipo Turni e presenze: due dipendenti fittizi, fuso orario italiano e permalink "parlanti" come su un sito reale.
$WP user create dipendente1 dipendente1@example.org --role=bracelli_dipendente --user_pass=e2e-dip1-pass --display_name="Dipendente Prova Uno" --quiet
$WP user create dipendente2 dipendente2@example.org --role=bracelli_dipendente --user_pass=e2e-dip2-pass --display_name="Dipendente Prova Due" --quiet
$WP option update timezone_string Europe/Rome --quiet
# Corsi e presenze insegnanti: account e corsi FITTIZI (mai dati reali nei test).
$WP user create insegnante1 insegnante1@example.org --role=bracelli_istruttore --user_pass=e2e-ins1-pass --display_name="Insegnante Prova Uno" --quiet
$WP user create insegnante2 insegnante2@example.org --role=bracelli_istruttore --user_pass=e2e-ins2-pass --display_name="Insegnante Prova Due" --quiet
$WP user create socio1 socio1@example.org --role=bracelli_socio --user_pass=e2e-socio-pass --display_name="Socio Prova" --quiet
CORSI_CSV=$(mktemp --suffix=.csv)
{
	echo "chiave;sezione;corso;gruppo;giorno;inizio;fine;insegnante;sede;note"
	for giorno in Lunedì Martedì Mercoledì Giovedì Venerdì Sabato Domenica; do
		echo "e2e-mattino;Sezione Prova;Corso Prova Mattino;adulti;$giorno;08:00;09:00;;;"
		echo "e2e-collega;Sezione Prova;Corso Prova Collega;;$giorno;10:00;11:00;Nome Fittizio;;"
	done
	echo "e2e-senza-orario;Altra Sezione;Corso Prova Senza Orario;;;;;;;Orari da definire"
} > "$CORSI_CSV"
$WP bracelli-corsi import "$CORSI_CSV" --yes --quiet
rm -f "$CORSI_CSV"
INS2=$($WP user get insegnante2 --field=ID)
$WP db query "UPDATE $($WP db prefix)bc_corsi_slots SET teacher_id = $INS2 WHERE ext_key LIKE 'e2e-collega|%'"
# Pagina con il pulsante "Area riservata" (shortcode), come nel widget Shortcode di Elementor.
$WP post create --post_type=page --post_status=publish --post_title="Prova pulsante" --post_name=prova-pulsante --post_content='[bracelli_area_riservata]' --quiet
$WP rewrite structure '/%postname%/' --quiet
# WP-CLI non sa se Apache ha mod_rewrite: il file .htaccess standard di WordPress si scrive qui.
cat > "$E2E_DIR/.htaccess" <<'HTACCESS'
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
HTACCESS
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
