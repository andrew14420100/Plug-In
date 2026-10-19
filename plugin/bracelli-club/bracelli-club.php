<?php
/**
 * Plugin Name:       Bracelli Club
 * Description:       Gestionale modulare del centro sportivo Bracelli Club: nucleo, dashboard, permessi, registro attività, diagnostica, area riservata, corsi e presenze insegnanti, turni del personale.
 * Version:           0.3.0
 * Requires at least: 6.9
 * Requires PHP:      8.2
 * Author:            Bracelli Club
 * Text Domain:       bracelli-club
 * Domain Path:       /languages
 *
 * I requisiti minimi PHP/WordPress sono PROVVISORI fino alla verifica dell'hosting TopHost
 * (docs/ADR-001-core.md, decisione D2). Questo file usa solo sintassi compatibile con
 * versioni PHP datate, così su un ambiente non supportato mostra un avviso invece di un errore fatale.
 *
 * Licenza: non ancora decisa dal proprietario del progetto.
 *
 * @package BracelliClub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BRACELLI_CLUB_VERSION', '0.3.0' );
define( 'BRACELLI_CLUB_FILE', __FILE__ );
define( 'BRACELLI_CLUB_DIR', plugin_dir_path( __FILE__ ) );
define( 'BRACELLI_CLUB_URL', plugin_dir_url( __FILE__ ) );
define( 'BRACELLI_CLUB_MIN_PHP', '8.2' );
define( 'BRACELLI_CLUB_MIN_WP', '6.9' );

/**
 * Restituisce l'elenco dei requisiti non soddisfatti (vuoto se l'ambiente è supportato).
 *
 * @return string[]
 */
function bracelli_club_unmet_requirements() {
	global $wp_version;

	$problems = array();
	if ( version_compare( PHP_VERSION, BRACELLI_CLUB_MIN_PHP, '<' ) ) {
		/* translators: 1: versione PHP richiesta, 2: versione PHP presente. */
		$problems[] = sprintf( __( 'PHP %1$s o successivo (presente: %2$s)', 'bracelli-club' ), BRACELLI_CLUB_MIN_PHP, PHP_VERSION );
	}
	if ( version_compare( $wp_version, BRACELLI_CLUB_MIN_WP, '<' ) ) {
		/* translators: 1: versione WordPress richiesta, 2: versione WordPress presente. */
		$problems[] = sprintf( __( 'WordPress %1$s o successivo (presente: %2$s)', 'bracelli-club' ), BRACELLI_CLUB_MIN_WP, $wp_version );
	}
	if ( is_multisite() ) {
		$problems[] = __( 'installazione singola di WordPress (Multisite non è supportato in questa versione)', 'bracelli-club' );
	}
	return $problems;
}

/**
 * Verifica rapida, senza stringhe tradotte (le traduzioni non vanno caricate prima di `init`).
 *
 * @return bool
 */
function bracelli_club_environment_ok() {
	global $wp_version;
	return version_compare( PHP_VERSION, BRACELLI_CLUB_MIN_PHP, '>=' )
		&& version_compare( $wp_version, BRACELLI_CLUB_MIN_WP, '>=' )
		&& ! is_multisite();
}

if ( ! bracelli_club_environment_ok() ) {
	add_action(
		'admin_notices',
		function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			$bracelli_club_problems = bracelli_club_unmet_requirements();
			echo '<div class="notice notice-error"><p><strong>Bracelli Club</strong> ';
			echo esc_html__( 'non è stato avviato. Requisiti mancanti:', 'bracelli-club' ) . ' ';
			echo esc_html( implode( '; ', $bracelli_club_problems ) );
			echo '</p></div>';
		}
	);
	register_activation_hook(
		__FILE__,
		function () {
			$bracelli_club_problems = bracelli_club_unmet_requirements();
			wp_die(
				esc_html__( 'Bracelli Club non può essere attivato. Requisiti mancanti:', 'bracelli-club' ) . ' ' . esc_html( implode( '; ', $bracelli_club_problems ) ),
				esc_html__( 'Requisiti non soddisfatti', 'bracelli-club' ),
				array( 'back_link' => true )
			);
		}
	);
	return;
}

require_once BRACELLI_CLUB_DIR . 'src/autoload.php';

register_activation_hook( __FILE__, array( 'Bracelli\\Club\\Core\\Lifecycle', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Bracelli\\Club\\Core\\Lifecycle', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Bracelli\\Club\\Plugin', 'boot' ) );
