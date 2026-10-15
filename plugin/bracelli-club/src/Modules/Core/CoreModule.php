<?php
/**
 * Modulo Nucleo: menu, pagina iniziale di riserva, moduli e impostazioni.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Core;

use Bracelli\Club\Core\Admin\Assets;
use Bracelli\Club\Core\Admin\Layout;
use Bracelli\Club\Core\Admin\Navigation;
use Bracelli\Club\Core\Admin\Pages;
use Bracelli\Club\Core\Migrations\Migrator;
use Bracelli\Club\Core\Modules\ModuleInterface;
use Bracelli\Club\Core\Permissions\Capabilities;
use Bracelli\Club\Core\Ui\Icons;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registra il menu principale "Bracelli Club" e le pagine del nucleo.
 */
final class CoreModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 */
	public function boot( Plugin $plugin ): void {
		Assets::register();
		add_action( 'admin_menu', [ $this, 'register_menu' ], 5 );
		add_action( 'admin_notices', [ $this, 'migration_notice' ] );
		add_filter( 'admin_body_class', [ $this, 'body_class' ] );

		$modules = new ModulesPage( $plugin );
		$modules->register();
		$settings = new SettingsPage( $plugin );
		$settings->register();
	}

	/**
	 * Menu principale.
	 */
	public function register_menu(): void {
		$hook = add_menu_page(
			__( 'Bracelli Club', 'bracelli-club' ),
			__( 'Bracelli Club', 'bracelli-club' ),
			Capabilities::DASHBOARD_VIEW,
			Pages::PARENT,
			[ $this, 'render_home' ],
			'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="2"><path d="M12 3 5 6v5c0 4.5 3 8.3 7 10 4-1.7 7-5.5 7-10V6l-7-3Z"/><path d="M10 8h3a2 2 0 0 1 0 4h-3V8Zm0 4h3.5a2 2 0 0 1 0 4H10v-4Z"/></svg>' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- icona menu SVG statica.
			3
		);
		Pages::remember( Pages::PARENT, $hook );
		add_submenu_page( Pages::PARENT, __( 'Dashboard', 'bracelli-club' ), __( 'Dashboard', 'bracelli-club' ), Capabilities::DASHBOARD_VIEW, Pages::PARENT, [ $this, 'render_home' ], 0 );
	}

	/**
	 * Pagina principale: la dashboard se il modulo è attivo, altrimenti una panoramica PHP.
	 */
	public function render_home(): void {
		Layout::require_cap( Capabilities::DASHBOARD_VIEW );
		$renderer = Pages::home_renderer();
		if ( null !== $renderer ) {
			$renderer();
			return;
		}
		Layout::open( __( 'Bracelli Club', 'bracelli-club' ), 'dashboard', __( 'Il modulo Dashboard non è attivo: qui trovi i collegamenti alle sezioni disponibili.', 'bracelli-club' ) );
		echo '<div class="bc-grid">';
		foreach ( Navigation::items_for_current_user() as $item ) {
			if ( 'dashboard' === $item['id'] ) {
				continue;
			}
			echo '<a class="bc-card bc-card--link" href="' . esc_url( $item['url'] ) . '">';
			Icons::render( $item['icon'], 28 );
			echo '<span class="bc-card-title">' . esc_html( $item['label'] ) . '</span></a>';
		}
		echo '</div>';
		Layout::close();
	}

	/**
	 * Avviso in caso di migrazione fallita.
	 */
	public function migration_notice(): void {
		if ( ! current_user_can( Capabilities::DIAGNOSTICS_RUN ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$last = get_option( Migrator::RESULT_OPTION );
		if ( ! is_array( $last ) || 'failed' !== ( $last['status'] ?? '' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>Bracelli Club:</strong> ';
		echo esc_html__( 'un aggiornamento del database non è stato completato. I dati esistenti non sono stati cancellati. Dettagli in Bracelli Club → Diagnostica o Ripristino.', 'bracelli-club' );
		echo '</p></div>';
	}

	/**
	 * Classe sul body per le pagine Bracelli.
	 *
	 * @param string $classes Classi esistenti.
	 */
	public function body_class( string $classes ): string {
		$screen = get_current_screen();
		if ( null !== $screen && Pages::is_bracelli_hook( (string) $screen->id ) ) {
			$classes .= ' bc-screen';
		}
		return $classes;
	}
}
