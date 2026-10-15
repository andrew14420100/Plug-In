<?php
/**
 * Struttura comune delle pagine amministrative renderizzate in PHP.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Admin;

use Bracelli\Club\Core\Ui\Icons;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Le pagine PHP funzionano senza JavaScript (requisito di recupero, Q172–173).
 */
final class Layout {

	/**
	 * Attributi tema/tavolozza dell'utente corrente.
	 *
	 * @return array{theme: string, palette: string}
	 */
	public static function theme_attributes(): array {
		$prefs = Plugin::instance()->preferences->get( get_current_user_id() );
		return [
			'theme'   => (string) $prefs['theme'],
			'palette' => (string) $prefs['palette'],
		];
	}

	/**
	 * Apre il contenitore della pagina con intestazione e navigazione.
	 *
	 * @param string $title    Titolo.
	 * @param string $current  Id voce di navigazione corrente.
	 * @param string $subtitle Sottotitolo facoltativo.
	 */
	public static function open( string $title, string $current, string $subtitle = '' ): void {
		$attrs = self::theme_attributes();
		printf(
			'<div class="wrap bc-app bc-page" data-bc-theme="%1$s" data-bc-palette="%2$s">',
			esc_attr( $attrs['theme'] ),
			esc_attr( $attrs['palette'] )
		);
		echo '<div class="bc-shell">';
		self::sidebar( $current );
		echo '<main class="bc-main" id="bc-main">';
		echo '<header class="bc-header"><div>';
		echo '<p class="bc-eyebrow">Bracelli Club</p>';
		echo '<h1 class="bc-title">' . esc_html( $title ) . '</h1>';
		if ( '' !== $subtitle ) {
			echo '<p class="bc-subtitle">' . esc_html( $subtitle ) . '</p>';
		}
		echo '</div></header>';
		echo '<hr class="wp-header-end">';
		if ( Plugin::instance()->is_safe_mode() ) {
			self::notice( 'warning', __( 'Modalità sicura attiva: sono avviati solo il nucleo e il ripristino.', 'bracelli-club' ) );
		}
	}

	/**
	 * Chiude il contenitore.
	 */
	public static function close(): void {
		echo '</main></div></div>';
	}

	/**
	 * Navigazione laterale (link semplici, nessun JavaScript richiesto).
	 *
	 * @param string $current Voce corrente.
	 */
	private static function sidebar( string $current ): void {
		echo '<nav class="bc-sidebar" aria-label="' . esc_attr__( 'Navigazione Bracelli', 'bracelli-club' ) . '">';
		echo '<div class="bc-brand"><span class="bc-brand-mark" aria-hidden="true">B</span><span class="bc-brand-name">Bracelli<span>Club</span></span></div>';
		echo '<ul class="bc-nav">';
		foreach ( Navigation::items_for_current_user() as $item ) {
			$is_current = $item['id'] === $current;
			printf(
				'<li><a class="bc-nav-link%1$s" href="%2$s"%3$s>',
				$is_current ? ' is-current' : '',
				esc_url( $item['url'] ),
				$is_current ? ' aria-current="page"' : ''
			);
			Icons::render( $item['icon'] );
			echo '<span>' . esc_html( $item['label'] ) . '</span></a></li>';
		}
		echo '</ul></nav>';
	}

	/**
	 * Avviso con stile Bracelli.
	 *
	 * @param string $type    success | warning | error | info.
	 * @param string $message Testo.
	 */
	public static function notice( string $type, string $message ): void {
		$type = in_array( $type, [ 'success', 'warning', 'error', 'info' ], true ) ? $type : 'info';
		printf(
			'<div class="bc-notice bc-notice--%1$s" role="%2$s"><p>%3$s</p></div>',
			esc_attr( $type ),
			'error' === $type ? 'alert' : 'status',
			esc_html( $message )
		);
	}

	/**
	 * Mostra l'avviso corrispondente al codice in ?bc_notice= (solo codici noti, nessun testo riflesso).
	 *
	 * @param array<string, array{0: string, 1: string}> $messages codice => [ tipo, testo ].
	 */
	public static function query_notice( array $messages ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sola lettura di un codice da elenco chiuso.
		$code = isset( $_GET['bc_notice'] ) ? sanitize_key( wp_unslash( $_GET['bc_notice'] ) ) : '';
		if ( '' !== $code && isset( $messages[ $code ] ) ) {
			self::notice( $messages[ $code ][0], $messages[ $code ][1] );
		}
	}

	/**
	 * Interrompe con 403 se manca la capability (difesa in profondità oltre al controllo del menu).
	 *
	 * @param string $capability Capability.
	 */
	public static function require_cap( string $capability ): void {
		if ( ! current_user_can( $capability ) ) {
			wp_die(
				esc_html__( 'Non hai i permessi per accedere a questa pagina.', 'bracelli-club' ),
				esc_html__( 'Accesso negato', 'bracelli-club' ),
				[ 'response' => 403 ]
			);
		}
	}

	/**
	 * Redirect sicuro verso una pagina del plugin con codice di avviso.
	 *
	 * @param string               $slug   Pagina.
	 * @param string               $notice Codice avviso.
	 * @param array<string, mixed> $args   Altri parametri.
	 * @return never
	 */
	public static function redirect( string $slug, string $notice, array $args = [] ) {
		wp_safe_redirect( Pages::url( $slug, array_merge( [ 'bc_notice' => $notice ], $args ) ) );
		exit;
	}
}
