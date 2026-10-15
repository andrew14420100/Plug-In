<?php
/**
 * Modulo Registro attività.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Audit;

use Bracelli\Club\Core\Admin\Assets;
use Bracelli\Club\Core\Admin\Layout;
use Bracelli\Club\Core\Admin\Pages;
use Bracelli\Club\Core\Modules\ModuleInterface;
use Bracelli\Club\Core\Permissions\Capabilities;
use Bracelli\Club\Core\Permissions\Roles;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Pagina di consultazione (React con alternativa PHP), API REST e ascolto dei cambi di ruolo.
 * WP_User::set_role() emette già add_user_role/remove_user_role: non si ascolta set_user_role per evitare duplicati.
 */
final class AuditModule implements ModuleInterface {

	public const SLUG          = 'bracelli-audit';
	public const SCRIPT_HANDLE = 'bracelli-club-audit';

	/**
	 * Servizi.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * {@inheritDoc}
	 */
	public function boot( Plugin $plugin ): void {
		$this->plugin = $plugin;
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'rest_api_init', [ new AuditRestController( $plugin ), 'register_routes' ] );
		add_action( 'add_user_role', [ $this, 'on_add_role' ], 10, 2 );
		add_action( 'remove_user_role', [ $this, 'on_remove_role' ], 10, 2 );
	}

	/**
	 * Voce di menu.
	 */
	public function menu(): void {
		Pages::add( self::SLUG, __( 'Registro attività', 'bracelli-club' ), __( 'Registro attività', 'bracelli-club' ), Capabilities::AUDIT_VIEW, [ $this, 'render' ], 40 );
	}

	/**
	 * Bundle React solo su questa pagina.
	 *
	 * @param string $hook_suffix Pagina corrente.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( Pages::hook( self::SLUG ) !== $hook_suffix ) {
			return;
		}
		Assets::enqueue_bundle( 'audit', self::SCRIPT_HANDLE );
	}

	/**
	 * Rendering: contenitore React + tabella PHP in <noscript>.
	 */
	public function render(): void {
		Layout::require_cap( Capabilities::AUDIT_VIEW );
		Layout::open( __( 'Registro attività', 'bracelli-club' ), 'audit', __( 'Eventi in sola lettura. I dati personali nei dettagli sono mascherati.', 'bracelli-club' ) );
		echo '<div id="bracelli-audit-root" data-actions="' . esc_attr( (string) wp_json_encode( $this->plugin->audit->distinct_actions() ) ) . '"><p class="bc-muted bc-loading">' . esc_html__( 'Caricamento del registro…', 'bracelli-club' ) . '</p></div>';
		echo '<noscript>';
		Layout::notice( 'info', __( 'JavaScript non è attivo: sono mostrati gli ultimi 50 eventi senza filtri.', 'bracelli-club' ) );
		$this->render_static_table();
		echo '</noscript>';
		Layout::close();
	}

	/**
	 * Tabella statica degli ultimi eventi.
	 */
	private function render_static_table(): void {
		$result = $this->plugin->audit->query( [], 1, 50 );
		echo '<div class="bc-card"><table class="bc-table"><thead><tr><th scope="col">' . esc_html__( 'Data (UTC)', 'bracelli-club' ) . '</th><th scope="col">' . esc_html__( 'Utente', 'bracelli-club' ) . '</th><th scope="col">' . esc_html__( 'Azione', 'bracelli-club' ) . '</th><th scope="col">' . esc_html__( 'Risorsa', 'bracelli-club' ) . '</th></tr></thead><tbody>';
		if ( [] === $result['items'] ) {
			echo '<tr><td colspan="4">' . esc_html__( 'Nessun evento registrato.', 'bracelli-club' ) . '</td></tr>';
		}
		foreach ( $result['items'] as $item ) {
			echo '<tr><td>' . esc_html( (string) $item['occurred_at'] ) . '</td><td>' . esc_html( AuditRestController::actor_label( (int) $item['actor_id'], (string) $item['actor_type'] ) ) . '</td><td><code>' . esc_html( (string) $item['action'] ) . '</code></td><td>' . esc_html( trim( $item['resource_type'] . ' ' . $item['resource_id'] ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * @param int    $user_id Utente.
	 * @param string $role    Ruolo aggiunto.
	 */
	public function on_add_role( int $user_id, string $role ): void {
		$this->log_role_change( 'user.role_added', $user_id, $role );
	}

	/**
	 * @param int    $user_id Utente.
	 * @param string $role    Ruolo rimosso.
	 */
	public function on_remove_role( int $user_id, string $role ): void {
		$this->log_role_change( 'user.role_removed', $user_id, $role );
	}

	/**
	 * @param string $action  Azione.
	 * @param int    $user_id Utente.
	 * @param string $role    Ruolo.
	 */
	private function log_role_change( string $action, int $user_id, string $role ): void {
		if ( ! in_array( $role, Roles::slugs(), true ) ) {
			return;
		}
		$this->plugin->audit->log(
			$action,
			[
				'module'        => 'permissions',
				'resource_type' => 'user',
				'resource_id'   => (string) $user_id,
				'diff'          => [ 'role' => $role ],
			]
		);
	}
}
