<?php
/**
 * API REST del registro attività (sola lettura).
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Audit;

use Bracelli\Club\Core\Permissions\Capabilities;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * GET /bracelli/v1/audit — richiede bracelli_audit_view.
 */
final class AuditRestController {

	public const NAMESPACE = 'bracelli/v1';

	/**
	 * @param Plugin $plugin Servizi.
	 */
	public function __construct( private Plugin $plugin ) {
	}

	/**
	 * Registra le rotte.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/audit',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_events' ],
				'permission_callback' => static fn(): bool => current_user_can( Capabilities::AUDIT_VIEW ),
				'args'                => [
					'page'          => [
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					],
					'per_page'      => [
						'type'    => 'integer',
						'default' => 25,
						'minimum' => 1,
						'maximum' => 100,
					],
					'action'        => [
						'type'    => 'string',
						'pattern' => '^[a-z0-9_.\-]{0,100}$',
					],
					'module'        => [
						'type'    => 'string',
						'pattern' => '^[a-z0-9_\-]{0,64}$',
					],
					'resource_type' => [
						'type'    => 'string',
						'pattern' => '^[a-z0-9_\-]{0,64}$',
					],
					'actor_id'      => [
						'type'    => 'integer',
						'minimum' => 0,
					],
					'from'          => [
						'type'    => 'string',
						'pattern' => '^(\d{4}-\d{2}-\d{2})?$',
					],
					'to'            => [
						'type'    => 'string',
						'pattern' => '^(\d{4}-\d{2}-\d{2})?$',
					],
					'search'        => [
						'type'              => 'string',
						'maxLength'         => 100,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * @param \WP_REST_Request $request Richiesta.
	 * @return \WP_REST_Response
	 */
	public function list_events( \WP_REST_Request $request ): \WP_REST_Response {
		$filters = [];
		foreach ( [ 'action', 'module', 'resource_type', 'actor_id', 'from', 'to', 'search' ] as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value && '' !== $value ) {
				$filters[ $key ] = $value;
			}
		}
		$result = $this->plugin->audit->query( $filters, (int) $request->get_param( 'page' ), (int) $request->get_param( 'per_page' ) );
		foreach ( $result['items'] as &$item ) {
			$item['actor_label'] = self::actor_label( (int) $item['actor_id'], (string) $item['actor_type'] );
		}
		unset( $item );

		$response = new \WP_REST_Response( $result );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) max( 1, (int) ceil( $result['total'] / $result['per_page'] ) ) );
		return $response;
	}

	/**
	 * Nome visualizzato dell'autore (senza email).
	 *
	 * @param int    $actor_id   Utente.
	 * @param string $actor_type Tipo.
	 */
	public static function actor_label( int $actor_id, string $actor_type ): string {
		if ( 'cli' === $actor_type && 0 === $actor_id ) {
			return 'WP-CLI';
		}
		if ( 0 === $actor_id ) {
			return __( 'Sistema', 'bracelli-club' );
		}
		$user = get_userdata( $actor_id );
		/* translators: %d: id utente eliminato. */
		return false === $user ? sprintf( __( 'Utente #%d (eliminato)', 'bracelli-club' ), $actor_id ) : $user->display_name;
	}
}
