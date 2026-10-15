<?php
/**
 * API REST della dashboard e delle preferenze personali.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Dashboard;

use Bracelli\Club\Core\Admin\Navigation;
use Bracelli\Club\Core\Permissions\Capabilities;
use Bracelli\Club\Core\Ui\Preferences;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * GET  /bracelli/v1/dashboard       — dati della dashboard dell'utente corrente.
 * POST /bracelli/v1/me/preferences  — aggiorna le preferenze dell'utente corrente (solo le proprie).
 * Entrambe richiedono bracelli_dashboard_view; il nonce REST protegge dal CSRF ma non sostituisce il controllo.
 */
final class DashboardRestController {

	public const NAMESPACE = 'bracelli/v1';

	/**
	 * @param Plugin  $plugin  Servizi.
	 * @param Widgets $widgets Widget.
	 */
	public function __construct( private Plugin $plugin, private Widgets $widgets ) {
	}

	/**
	 * Registra le rotte.
	 */
	public function register_routes(): void {
		$permission = static fn(): bool => current_user_can( Capabilities::DASHBOARD_VIEW );
		register_rest_route(
			self::NAMESPACE,
			'/dashboard',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => fn() => new \WP_REST_Response( $this->payload() ),
				'permission_callback' => $permission,
			]
		);
		register_rest_route(
			self::NAMESPACE,
			'/me/preferences',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => fn() => new \WP_REST_Response( $this->plugin->preferences->get( get_current_user_id() ) ),
					'permission_callback' => $permission,
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_preferences' ],
					'permission_callback' => $permission,
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'reset_preferences' ],
					'permission_callback' => $permission,
				],
			]
		);
	}

	/**
	 * Dati completi della dashboard.
	 *
	 * @return array<string, mixed>
	 */
	public function payload(): array {
		$user_id = get_current_user_id();
		$prefs   = $this->plugin->preferences->get( $user_id );
		return [
			'context'    => Widgets::context(),
			'prefs'      => $prefs,
			'widgets'    => $this->widgets->build( $prefs ),
			'nav'        => Navigation::items_for_current_user(),
			'candidates' => array_values( Navigation::favorite_candidates() ),
			'icons'      => Preferences::icons(),
			'palettes'   => \Bracelli\Club\Core\Ui\Palettes::labels(),
			'options'    => [
				'allow_hide_wp_menu' => (bool) $this->plugin->settings->get( 'ui.allow_hide_wp_menu' ) && ! $this->plugin->is_safe_mode(),
			],
			'links'      => [
				'wp_dashboard' => admin_url( 'index.php' ),
				'recovery'     => current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=bracelli-recovery' ) : '',
			],
		];
	}

	/**
	 * Aggiornamento parziale delle preferenze.
	 *
	 * @param \WP_REST_Request $request Richiesta.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_preferences( \WP_REST_Request $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			return new \WP_Error( 'bracelli_invalid_body', __( 'Corpo della richiesta non valido.', 'bracelli-club' ), [ 'status' => 400 ] );
		}
		$allowed_nav = array_map( static fn( array $i ): string => $i['id'], Navigation::items_for_current_user() );
		$result      = Preferences::sanitize(
			$input,
			$this->widgets->available_ids(),
			$allowed_nav,
			array_keys( Navigation::favorite_candidates() )
		);
		if ( isset( $result['values']['hide_wp_menu'] ) && true === $result['values']['hide_wp_menu'] && ! $this->plugin->settings->get( 'ui.allow_hide_wp_menu' ) ) {
			$result['errors']['hide_wp_menu'] = __( 'L\'amministratore non consente di nascondere il menu WordPress.', 'bracelli-club' );
		}
		if ( [] !== $result['errors'] ) {
			return new \WP_Error(
				'bracelli_invalid_preferences',
				__( 'Alcune preferenze non sono valide.', 'bracelli-club' ),
				[
					'status' => 400,
					'fields' => $result['errors'],
				]
			);
		}
		$prefs = $this->plugin->preferences->update( get_current_user_id(), $result['values'] );
		return new \WP_REST_Response( $prefs );
	}

	/**
	 * Ripristino delle preferenze predefinite.
	 */
	public function reset_preferences(): \WP_REST_Response {
		$this->plugin->preferences->reset( get_current_user_id() );
		return new \WP_REST_Response( $this->plugin->preferences->get( get_current_user_id() ) );
	}
}
