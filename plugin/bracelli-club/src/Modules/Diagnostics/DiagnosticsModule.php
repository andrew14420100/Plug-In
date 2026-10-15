<?php
/**
 * Modulo Diagnostica.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Diagnostics;

use Bracelli\Club\Core\Admin\Layout;
use Bracelli\Club\Core\Admin\Pages;
use Bracelli\Club\Core\Modules\ModuleInterface;
use Bracelli\Club\Core\Permissions\Capabilities;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Pagina PHP (funziona senza JavaScript).
 */
final class DiagnosticsModule implements ModuleInterface {

	public const SLUG        = 'bracelli-diagnostics';
	public const ACTION_DEEP = 'bracelli_club_diagnostics_deep';

	/**
	 * Servizi.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Controlli.
	 *
	 * @var Checks
	 */
	private Checks $checks;

	/**
	 * {@inheritDoc}
	 */
	public function boot( Plugin $plugin ): void {
		$this->plugin = $plugin;
		$this->checks = new Checks( $plugin );
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_post_' . self::ACTION_DEEP, [ $this, 'handle_deep' ] );
	}

	/**
	 * Controlli (usati anche dal widget della dashboard).
	 */
	public function checks(): Checks {
		return $this->checks;
	}

	/**
	 * Voce di menu.
	 */
	public function menu(): void {
		Pages::add( self::SLUG, __( 'Diagnostica', 'bracelli-club' ), __( 'Diagnostica', 'bracelli-club' ), Capabilities::DIAGNOSTICS_RUN, [ $this, 'render' ], 50 );
	}

	/**
	 * Rendering.
	 */
	public function render(): void {
		Layout::require_cap( Capabilities::DIAGNOSTICS_RUN );
		Layout::open( __( 'Diagnostica', 'bracelli-club' ), 'diagnostics', __( 'Valori rilevati su questo server. Nessun dato viene inviato all\'esterno.', 'bracelli-club' ) );

		$labels = [
			Checks::OK      => __( 'OK', 'bracelli-club' ),
			Checks::WARNING => __( 'Attenzione', 'bracelli-club' ),
			Checks::ERROR   => __( 'Errore', 'bracelli-club' ),
			Checks::INFO    => __( 'Info', 'bracelli-club' ),
		];
		echo '<div class="bc-card"><table class="bc-table"><thead><tr><th scope="col">' . esc_html__( 'Controllo', 'bracelli-club' ) . '</th><th scope="col">' . esc_html__( 'Esito', 'bracelli-club' ) . '</th><th scope="col">' . esc_html__( 'Valore', 'bracelli-club' ) . '</th></tr></thead><tbody>';
		foreach ( $this->checks->basic() as $check ) {
			echo '<tr><th scope="row">' . esc_html( $check['label'] ) . '</th><td><span class="bc-status bc-status--' . esc_attr( $check['status'] ) . '">' . esc_html( $labels[ $check['status'] ] ) . '</span></td><td>' . esc_html( $check['value'] );
			if ( '' !== $check['hint'] ) {
				echo '<br><span class="bc-muted">' . esc_html( $check['hint'] ) . '</span>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';

		echo '<div class="bc-card"><h2>' . esc_html__( 'Diagnostica approfondita', 'bracelli-club' ) . '</h2>';
		echo '<p class="bc-muted">' . esc_html__( 'Misura dimensioni delle tabelle del plugin, latenza del database, spazio e memoria. Viene eseguita solo quando la richiedi.', 'bracelli-club' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_DEEP ) . '">';
		wp_nonce_field( self::ACTION_DEEP );
		echo '<button type="submit" class="bc-button bc-button--primary">' . esc_html__( 'Esegui diagnostica approfondita', 'bracelli-club' ) . '</button></form>';
		$deep = get_transient( $this->deep_key() );
		if ( is_array( $deep ) ) {
			$this->render_deep( $deep );
		}
		echo '</div>';

		$errors = $this->plugin->errors->all();
		echo '<div class="bc-card"><h2>' . esc_html__( 'Ultimi errori interni', 'bracelli-club' ) . '</h2>';
		if ( [] === $errors ) {
			echo '<p class="bc-muted">' . esc_html__( 'Nessun errore registrato.', 'bracelli-club' ) . '</p>';
		} else {
			echo '<ul class="bc-list">';
			foreach ( array_reverse( $errors ) as $error ) {
				echo '<li><code>' . esc_html( $error['at'] ) . '</code> <strong>' . esc_html( $error['source'] ) . '</strong> ' . esc_html( $error['message'] ) . ( '' !== $error['where'] ? ' <span class="bc-muted">(' . esc_html( $error['where'] ) . ')</span>' : '' ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
		Layout::close();
	}

	/**
	 * @param array<string, mixed> $deep Risultati.
	 */
	private function render_deep( array $deep ): void {
		/* translators: %s: data UTC. */
		echo '<h3>' . esc_html( sprintf( __( 'Risultati del %s (UTC)', 'bracelli-club' ), (string) $deep['generated_at'] ) ) . '</h3>';
		echo '<ul class="bc-list">';
		/* translators: 1: mediana, 2: p95 in millisecondi. */
		echo '<li>' . esc_html( sprintf( __( 'Latenza database: mediana %1$s ms, p95 %2$s ms (20 query).', 'bracelli-club' ), (string) $deep['db_latency_ms']['median'], (string) $deep['db_latency_ms']['p95'] ) ) . '</li>';
		echo '<li>' . esc_html__( 'Cartella upload scrivibile:', 'bracelli-club' ) . ' ' . esc_html( $deep['uploads_writable'] ? __( 'sì', 'bracelli-club' ) : __( 'no', 'bracelli-club' ) ) . '</li>';
		echo '<li>' . esc_html__( 'Spazio libero:', 'bracelli-club' ) . ' ' . esc_html( null === $deep['disk_free'] ? __( 'non rilevabile su questo hosting', 'bracelli-club' ) : (string) $deep['disk_free'] ) . '</li>';
		echo '<li>' . esc_html__( 'Memoria di picco della richiesta:', 'bracelli-club' ) . ' ' . esc_html( (string) $deep['peak_memory'] ) . '</li>';
		/* translators: %s: millisecondi. */
		echo '<li>' . esc_html( sprintf( __( 'Durata analisi: %s ms', 'bracelli-club' ), (string) $deep['duration_ms'] ) ) . '</li>';
		echo '</ul><table class="bc-table"><thead><tr><th scope="col">' . esc_html__( 'Tabella', 'bracelli-club' ) . '</th><th scope="col">' . esc_html__( 'Righe (stima)', 'bracelli-club' ) . '</th><th scope="col">KB</th></tr></thead><tbody>';
		foreach ( (array) $deep['tables'] as $table ) {
			echo '<tr><td><code>' . esc_html( $table['name'] ) . '</code></td><td>' . esc_html( number_format_i18n( $table['row_estimate'] ) ) . '</td><td>' . esc_html( number_format_i18n( $table['size_kb'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Esecuzione su richiesta.
	 */
	public function handle_deep(): void {
		Layout::require_cap( Capabilities::DIAGNOSTICS_RUN );
		check_admin_referer( self::ACTION_DEEP );
		$deep = $this->checks->deep();
		set_transient( $this->deep_key(), $deep, 15 * MINUTE_IN_SECONDS );
		$this->plugin->audit->log(
			'diagnostics.deep_run',
			[
				'module' => 'diagnostics',
				'diff'   => [ 'duration_ms' => $deep['duration_ms'] ],
			]
		);
		wp_safe_redirect( Pages::url( self::SLUG ) );
		exit;
	}

	/**
	 * Chiave transient per utente.
	 */
	private function deep_key(): string {
		return 'bc_diag_deep_' . get_current_user_id();
	}
}
