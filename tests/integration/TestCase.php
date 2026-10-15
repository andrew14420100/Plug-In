<?php
/**
 * Base dei test di integrazione.
 *
 * @package BracelliClub
 */

namespace Bracelli\Club\Tests\integration;

use Bracelli\Club\Core\Permissions\Roles;
use Bracelli\Club\Plugin;

/**
 * Eccezione usata per intercettare i redirect (che altrimenti terminano la richiesta con exit).
 */
final class RedirectException extends \RuntimeException {
}

abstract class TestCase extends \WP_UnitTestCase {

	protected Plugin $plugin;

	public function set_up(): void {
		parent::set_up();
		$this->plugin = Plugin::instance();
		// I ruoli in memoria vengono riallineati al database (ripristinato dal rollback del test precedente).
		wp_roles()->for_site();
		Roles::sync();
		// Code di script e stili sono globali: ogni test parte da code vuote.
		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;
	}

	public function tear_down(): void {
		$_POST    = [];
		$_GET     = [];
		$_REQUEST = [];
		parent::tear_down();
	}

	protected function user_with_role( string $role ): int {
		return self::factory()->user->create( [ 'role' => $role ] );
	}

	protected function login_as( string $role ): int {
		$id = $this->user_with_role( $role );
		wp_set_current_user( $id );
		return $id;
	}

	/**
	 * Fa sì che wp_safe_redirect lanci un'eccezione con la destinazione.
	 */
	protected function capture_redirects(): void {
		add_filter(
			'wp_redirect',
			static function ( $location ) {
				throw new RedirectException( (string) $location );
			},
			1
		);
	}

	/**
	 * Esegue un'azione admin-post con nonce valido e restituisce la destinazione del redirect.
	 *
	 * @param string               $action      Azione (hook admin_post_*).
	 * @param string               $nonce_action Azione del nonce.
	 * @param array<string, mixed> $post         Campi POST.
	 */
	protected function admin_post( string $action, string $nonce_action, array $post = [] ): string {
		$this->capture_redirects();
		$_POST                = $post + [ '_wpnonce' => wp_create_nonce( $nonce_action ) ];
		$_REQUEST             = $_POST;
		$_SERVER['REQUEST_METHOD'] = 'POST';
		try {
			do_action( 'admin_post_' . $action );
		} catch ( RedirectException $e ) {
			return $e->getMessage();
		}
		$this->fail( 'Nessun redirect eseguito.' );
	}

	/**
	 * Conta gli eventi di audit con l'azione indicata.
	 *
	 * @param string $action Azione.
	 */
	protected function audit_count( string $action ): int {
		return $this->plugin->audit->query( [ 'action' => $action ] )['total'];
	}
}
