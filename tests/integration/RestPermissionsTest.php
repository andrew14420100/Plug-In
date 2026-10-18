<?php
/**
 * Controlli di accesso REST lato server.
 *
 * @package BracelliClub
 */

namespace Bracelli\Club\Tests\integration;

use Bracelli\Club\Core\Permissions\Capabilities;
use Bracelli\Club\Core\Permissions\Roles;
use Bracelli\Club\Core\Ui\Preferences;

final class RestPermissionsTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	private function request( string $method, string $route, array $body = null, array $query = [] ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, $route );
		if ( null !== $body ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}
		foreach ( $query as $k => $v ) {
			$request->set_param( $k, $v );
		}
		return rest_get_server()->dispatch( $request );
	}

	public function test_routes_are_registered(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/bracelli/v1/dashboard', $routes );
		$this->assertArrayHasKey( '/bracelli/v1/me/preferences', $routes );
		$this->assertArrayHasKey( '/bracelli/v1/audit', $routes );
	}

	public function test_anonymous_users_are_rejected_everywhere(): void {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->request( 'GET', '/bracelli/v1/dashboard' )->get_status() );
		$this->assertSame( 401, $this->request( 'GET', '/bracelli/v1/audit' )->get_status() );
		$this->assertSame( 401, $this->request( 'POST', '/bracelli/v1/me/preferences', [ 'theme' => 'dark' ] )->get_status() );
		$this->assertSame( 401, $this->request( 'DELETE', '/bracelli/v1/me/preferences' )->get_status() );
	}

	public function test_subscriber_is_forbidden(): void {
		$this->login_as( 'subscriber' );
		$this->assertSame( 403, $this->request( 'GET', '/bracelli/v1/dashboard' )->get_status() );
		$this->assertSame( 403, $this->request( 'POST', '/bracelli/v1/me/preferences', [ 'theme' => 'dark' ] )->get_status() );
		$this->assertSame( 403, $this->request( 'GET', '/bracelli/v1/audit' )->get_status() );
	}

	public function test_staff_sees_staff_dashboard_but_not_audit(): void {
		$this->login_as( Roles::SEGRETERIA );
		$response = $this->request( 'GET', '/bracelli/v1/dashboard' );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'staff', $data['context'] );
		$ids = array_column( $data['widgets'], 'id' );
		$this->assertNotContains( 'modules', $ids );
		$this->assertNotContains( 'activity', $ids );
		$this->assertNotContains( 'health', $ids );
		$this->assertSame( [ 'dashboard' ], array_column( $data['nav'], 'id' ) );
		$this->assertSame( 403, $this->request( 'GET', '/bracelli/v1/audit' )->get_status() );
	}

	public function test_granted_capability_is_honoured_server_side(): void {
		$this->login_as( Roles::SEGRETERIA );
		get_role( Roles::SEGRETERIA )->add_cap( Capabilities::AUDIT_VIEW );
		wp_get_current_user()->get_role_caps(); // Ricalcola le capability dell'utente (in produzione avviene alla richiesta successiva).
		$this->assertSame( 200, $this->request( 'GET', '/bracelli/v1/audit' )->get_status() );
		get_role( Roles::SEGRETERIA )->remove_cap( Capabilities::AUDIT_VIEW );
	}

	public function test_admin_dashboard_has_real_widgets_and_no_invented_numbers(): void {
		$this->login_as( 'administrator' );
		$data = $this->request( 'GET', '/bracelli/v1/dashboard' )->get_data();
		$this->assertSame( 'admin', $data['context'] );
		$widgets = array_column( $data['widgets'], 'data', 'id' );
		$this->assertEqualsCanonicalizing( [ 'welcome', 'favorites', 'areas', 'modules', 'health', 'activity' ], array_keys( $widgets ) );
		foreach ( $widgets['areas']['items'] as $area ) {
			$this->assertSame( [ 'id', 'label', 'phase' ], array_keys( $area ), 'Le aree future non espongono contatori.' );
		}
		$this->assertSame( count( $this->plugin->modules->manifests() ), array_sum( $widgets['modules']['counts'] ) );
	}

	public function test_preferences_are_validated_and_saved_for_current_user_only(): void {
		$user  = $this->login_as( Roles::SEGRETERIA );
		$other = $this->user_with_role( Roles::SEGRETERIA );

		$ok = $this->request(
			'POST',
			'/bracelli/v1/me/preferences',
			[
				'theme'     => 'dark',
				'palette'   => 'aurora',
				'favorites' => [
					[ 'id' => 'wp:profile.php', 'label' => '<b>Profilo</b>', 'icon' => 'user' ],
					[ 'id' => 'wp:plugins.php', 'label' => 'Non consentito', 'icon' => 'grid' ],
				],
			]
		);
		$this->assertSame( 200, $ok->get_status() );
		$prefs = $this->plugin->preferences->get( $user );
		$this->assertSame( 'dark', $prefs['theme'] );
		$this->assertSame( 'aurora', $prefs['palette'] );
		$this->assertSame( [ [ 'id' => 'wp:profile.php', 'label' => 'Profilo', 'icon' => 'user' ] ], $prefs['favorites'], 'Le pagine non permesse vengono scartate e il markup rimosso.' );
		$this->assertSame( '', get_user_meta( $other, Preferences::META, true ), 'Le preferenze di altri utenti non cambiano.' );

		$bad = $this->request( 'POST', '/bracelli/v1/me/preferences', [ 'theme' => 'neon' ] );
		$this->assertSame( 400, $bad->get_status() );
		$this->assertSame( 'dark', $this->plugin->preferences->get( $user )['theme'] );

		$reset = $this->request( 'DELETE', '/bracelli/v1/me/preferences' );
		$this->assertSame( 200, $reset->get_status() );
		$this->assertSame( 'auto', $this->plugin->preferences->get( $user )['theme'] );
	}

	public function test_hiding_wp_menu_respects_global_setting(): void {
		$this->login_as( 'administrator' );
		$this->plugin->settings->update( [ 'ui.allow_hide_wp_menu' => false ] );
		$response = $this->request( 'POST', '/bracelli/v1/me/preferences', [ 'hide_wp_menu' => true ] );
		$this->assertSame( 400, $response->get_status() );
		$this->plugin->settings->update( [ 'ui.allow_hide_wp_menu' => true ] );
		$this->assertSame( 200, $this->request( 'POST', '/bracelli/v1/me/preferences', [ 'hide_wp_menu' => true ] )->get_status() );
	}

	public function test_audit_endpoint_filters_paginates_and_validates(): void {
		$this->login_as( 'administrator' );
		for ( $i = 0; $i < 30; $i++ ) {
			$this->plugin->audit->log( 'test.rest_page', [ 'resource_id' => (string) $i ] );
		}
		$response = $this->request( 'GET', '/bracelli/v1/audit', null, [ 'action' => 'test.rest_page', 'per_page' => 10, 'page' => 2 ] );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 30, $data['total'] );
		$this->assertCount( 10, $data['items'] );
		$this->assertSame( '30', $response->get_headers()['X-WP-Total'] );
		$this->assertSame( '3', $response->get_headers()['X-WP-TotalPages'] );
		$this->assertArrayHasKey( 'actor_label', $data['items'][0] );

		$invalid = $this->request( 'GET', '/bracelli/v1/audit', null, [ 'per_page' => 500 ] );
		$this->assertSame( 400, $invalid->get_status() );
		$injection = $this->request( 'GET', '/bracelli/v1/audit', null, [ 'action' => "x' OR 1=1 --" ] );
		$this->assertSame( 400, $injection->get_status() );
	}
}
