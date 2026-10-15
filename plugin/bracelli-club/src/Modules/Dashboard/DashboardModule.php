<?php
/**
 * Modulo Dashboard.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Dashboard;

use Bracelli\Club\Core\Admin\Assets;
use Bracelli\Club\Core\Admin\Layout;
use Bracelli\Club\Core\Admin\Navigation;
use Bracelli\Club\Core\Admin\Pages;
use Bracelli\Club\Core\Modules\ModuleInterface;
use Bracelli\Club\Core\Permissions\Capabilities;
use Bracelli\Club\Core\Ui\Icons;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard React con alternativa PHP, menu WordPress nascondibile e redirect dopo il login.
 */
final class DashboardModule implements ModuleInterface {

	public const SCRIPT_HANDLE = 'bracelli-club-dashboard';
	public const ACTION_MENU   = 'bracelli_club_toggle_wp_menu';

	/**
	 * Servizi.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Widget.
	 *
	 * @var Widgets
	 */
	private Widgets $widgets;

	/**
	 * Controller REST.
	 *
	 * @var DashboardRestController
	 */
	private DashboardRestController $rest;

	/**
	 * {@inheritDoc}
	 */
	public function boot( Plugin $plugin ): void {
		$this->plugin  = $plugin;
		$this->widgets = new Widgets( $plugin );
		$this->rest    = new DashboardRestController( $plugin, $this->widgets );

		Pages::set_home_renderer( [ $this, 'render' ] );
		add_action( 'rest_api_init', [ $this->rest, 'register_routes' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_filter( 'login_redirect', [ $this, 'login_redirect' ], 20, 3 );

		add_action( 'admin_post_' . self::ACTION_MENU, [ $this, 'handle_toggle_menu' ] );
		add_filter( 'admin_body_class', [ $this, 'body_class' ] );
		add_action( 'admin_head', [ $this, 'hide_menu_styles' ] );
		add_action( 'admin_footer', [ $this, 'menu_toggle_button' ] );
		add_action( 'admin_bar_menu', [ $this, 'admin_bar' ], 100 );
	}

	/**
	 * Bundle React solo nella pagina dashboard, con dati iniziali già calcolati sul server.
	 *
	 * @param string $hook_suffix Pagina corrente.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( Pages::hook( Pages::PARENT ) !== $hook_suffix || ! current_user_can( Capabilities::DASHBOARD_VIEW ) ) {
			return;
		}
		if ( Assets::enqueue_bundle( 'dashboard', self::SCRIPT_HANDLE ) ) {
			wp_add_inline_script(
				self::SCRIPT_HANDLE,
				// JSON_HEX_*: nessuna stringa può chiudere il tag <script> (difesa in profondità).
				'window.bracelliDashboard = ' . wp_json_encode( $this->rest->payload(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';',
				'before'
			);
		}
	}

	/**
	 * Pagina dashboard: contenitore React; senza JavaScript mostra collegamenti PHP.
	 */
	public function render(): void {
		$attrs = Layout::theme_attributes();
		printf(
			'<div class="wrap bc-app bc-dashboard-wrap" data-bc-theme="%1$s" data-bc-palette="%2$s"><h1 class="screen-reader-text">%3$s</h1><hr class="wp-header-end">',
			esc_attr( $attrs['theme'] ),
			esc_attr( $attrs['palette'] ),
			esc_html__( 'Dashboard Bracelli Club', 'bracelli-club' )
		);
		echo '<div id="bracelli-dashboard-root"><div class="bc-boot" role="status"><span class="bc-boot-mark" aria-hidden="true">B</span><span>' . esc_html__( 'Caricamento della dashboard…', 'bracelli-club' ) . '</span></div></div>';
		echo '<noscript><div class="bc-card"><h2>' . esc_html__( 'JavaScript non è attivo', 'bracelli-club' ) . '</h2><p>' . esc_html__( 'La dashboard interattiva richiede JavaScript. Tutte le funzioni restano raggiungibili da questi collegamenti:', 'bracelli-club' ) . '</p><ul class="bc-list">';
		foreach ( Navigation::items_for_current_user() as $item ) {
			echo '<li><a href="' . esc_url( $item['url'] ) . '">';
			Icons::render( $item['icon'] );
			echo ' ' . esc_html( $item['label'] ) . '</a></li>';
		}
		echo '<li><a href="' . esc_url( admin_url( 'index.php' ) ) . '">' . esc_html__( 'Bacheca WordPress', 'bracelli-club' ) . '</a></li>';
		echo '</ul></div></noscript></div>';
	}

	/**
	 * Dopo il login apre la dashboard Bracelli se l'utente lo preferisce e non è richiesta un'altra pagina.
	 *
	 * @param string            $redirect_to Destinazione calcolata.
	 * @param string            $requested   Destinazione richiesta.
	 * @param \WP_User|\WP_Error $user        Utente.
	 */
	public function login_redirect( $redirect_to, $requested, $user ): string {
		if ( ! $user instanceof \WP_User || $this->plugin->is_safe_mode() || ! user_can( $user, Capabilities::DASHBOARD_VIEW ) ) {
			return (string) $redirect_to;
		}
		$default = admin_url();
		if ( '' !== (string) $requested && untrailingslashit( (string) $requested ) !== untrailingslashit( $default ) && untrailingslashit( (string) $requested ) !== untrailingslashit( admin_url( 'index.php' ) ) ) {
			return (string) $redirect_to;
		}
		$prefs = $this->plugin->preferences->get( (int) $user->ID );
		if ( empty( $prefs['login_to_dashboard'] ) ) {
			return (string) $redirect_to;
		}
		return Pages::url( Pages::PARENT );
	}

	/**
	 * Indica se il menu WordPress va nascosto per l'utente corrente.
	 */
	public function wp_menu_hidden(): bool {
		if ( $this->plugin->is_safe_mode() || ! is_user_logged_in() || ! current_user_can( Capabilities::DASHBOARD_VIEW ) ) {
			return false;
		}
		if ( ! $this->plugin->settings->get( 'ui.allow_hide_wp_menu' ) ) {
			return false;
		}
		// Mai nascondere nella pagina di ripristino.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sola lettura dello slug pagina.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'bracelli-recovery' === $page ) {
			return false;
		}
		$prefs = $this->plugin->preferences->get( get_current_user_id() );
		return ! empty( $prefs['hide_wp_menu'] );
	}

	/**
	 * @param string $classes Classi body.
	 */
	public function body_class( string $classes ): string {
		return $this->wp_menu_hidden() ? $classes . ' bc-hide-wp-menu' : $classes;
	}

	/**
	 * CSS minimo in linea (funziona anche se il foglio di stile non viene caricato).
	 */
	public function hide_menu_styles(): void {
		if ( ! $this->wp_menu_hidden() ) {
			return;
		}
		echo '<style id="bracelli-club-hide-wp-menu">body.bc-hide-wp-menu #adminmenumain{display:none}body.bc-hide-wp-menu #wpcontent,body.bc-hide-wp-menu #wpfooter{margin-left:0}.bc-wp-menu-toggle{position:fixed;left:16px;bottom:16px;z-index:99999;display:inline-flex;align-items:center;gap:6px;padding:10px 14px;border-radius:999px;background:#0b1220;color:#e6f6ff;border:1px solid #22d3ee;text-decoration:none;font-weight:600;box-shadow:0 6px 24px rgba(34,211,238,.25)}.bc-wp-menu-toggle:focus,.bc-wp-menu-toggle:hover{color:#fff;background:#13203a;outline:2px solid #22d3ee;outline-offset:2px}</style>';
	}

	/**
	 * Pulsante sempre visibile per riaprire il menu (link con nonce, nessun JavaScript).
	 */
	public function menu_toggle_button(): void {
		if ( ! $this->wp_menu_hidden() ) {
			return;
		}
		echo '<a class="bc-wp-menu-toggle" href="' . esc_url( $this->toggle_url() ) . '">';
		Icons::render( 'wplogo', 18 );
		echo '<span>' . esc_html__( 'Mostra menu WordPress', 'bracelli-club' ) . '</span></a>';
	}

	/**
	 * Voce nella barra di amministrazione.
	 *
	 * @param \WP_Admin_Bar $bar Barra.
	 */
	public function admin_bar( \WP_Admin_Bar $bar ): void {
		if ( ! is_admin() || $this->plugin->is_safe_mode() || ! current_user_can( Capabilities::DASHBOARD_VIEW ) || ! $this->plugin->settings->get( 'ui.allow_hide_wp_menu' ) ) {
			return;
		}
		$bar->add_node(
			[
				'id'    => 'bracelli-club-wp-menu',
				'title' => $this->wp_menu_hidden() ? __( 'Mostra menu WordPress', 'bracelli-club' ) : __( 'Nascondi menu WordPress', 'bracelli-club' ),
				'href'  => $this->toggle_url(),
			]
		);
	}

	/**
	 * URL dell'azione di alternanza.
	 */
	private function toggle_url(): string {
		$back = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return wp_nonce_url(
			add_query_arg(
				[
					'action' => self::ACTION_MENU,
					'back'   => rawurlencode( $back ),
				],
				admin_url( 'admin-post.php' )
			),
			self::ACTION_MENU
		);
	}

	/**
	 * Alterna la visibilità del menu WordPress per l'utente corrente.
	 */
	public function handle_toggle_menu(): void {
		Layout::require_cap( Capabilities::DASHBOARD_VIEW );
		check_admin_referer( self::ACTION_MENU );
		$user_id = get_current_user_id();
		$prefs   = $this->plugin->preferences->get( $user_id );
		$hide    = empty( $prefs['hide_wp_menu'] ) && (bool) $this->plugin->settings->get( 'ui.allow_hide_wp_menu' );
		$this->plugin->preferences->update( $user_id, [ 'hide_wp_menu' => $hide ] );
		$back = isset( $_GET['back'] ) ? rawurldecode( sanitize_text_field( wp_unslash( $_GET['back'] ) ) ) : '';
		wp_safe_redirect( wp_validate_redirect( $back, Pages::url( Pages::PARENT ) ) );
		exit;
	}
}
