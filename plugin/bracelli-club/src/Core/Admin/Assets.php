<?php
/**
 * Caricamento degli asset condivisi solo nelle pagine Bracelli.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * CSS di base (token, tema chiaro/scuro, tavolozze) e bundle React per modulo.
 */
final class Assets {

	public const STYLE_HANDLE = 'bracelli-club-admin';

	/**
	 * Registra il caricamento del CSS condiviso.
	 */
	public static function register(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_shared' ] );
	}

	/**
	 * @param string $hook_suffix Pagina corrente.
	 */
	public static function enqueue_shared( string $hook_suffix ): void {
		if ( ! Pages::is_bracelli_hook( $hook_suffix ) ) {
			return;
		}
		wp_enqueue_style(
			self::STYLE_HANDLE,
			BRACELLI_CLUB_URL . 'assets/css/admin.css',
			[],
			BRACELLI_CLUB_VERSION
		);
	}

	/**
	 * Accoda un bundle compilato (assets/dist/<nome>/index.js + index.asset.php).
	 * Se il bundle manca (pacchetto incompleto) restituisce false: la pagina mostra il fallback PHP.
	 *
	 * @param string $name   Nome bundle.
	 * @param string $handle Handle dello script.
	 */
	public static function enqueue_bundle( string $name, string $handle ): bool {
		$base       = BRACELLI_CLUB_DIR . 'assets/dist/' . $name . '/';
		$asset_file = $base . 'index.asset.php';
		if ( ! is_readable( $asset_file ) || ! is_readable( $base . 'index.js' ) ) {
			return false;
		}
		$asset = require $asset_file;
		$deps  = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : [];
		$ver   = isset( $asset['version'] ) ? (string) $asset['version'] : BRACELLI_CLUB_VERSION;

		wp_enqueue_script(
			$handle,
			BRACELLI_CLUB_URL . 'assets/dist/' . $name . '/index.js',
			$deps,
			$ver,
			[ 'in_footer' => true ]
		);
		foreach ( [ 'style-index.css', 'index.css' ] as $css ) {
			if ( is_readable( $base . $css ) ) {
				wp_enqueue_style( $handle, BRACELLI_CLUB_URL . 'assets/dist/' . $name . '/' . $css, [ self::STYLE_HANDLE ], $ver );
				wp_style_add_data( $handle, 'rtl', 'replace' );
				break;
			}
		}
		wp_set_script_translations( $handle, 'bracelli-club', BRACELLI_CLUB_DIR . 'languages' );
		return true;
	}
}
