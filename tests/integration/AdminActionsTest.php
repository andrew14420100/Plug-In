<?php
/**
 * Pagine e azioni amministrative: capability, nonce, audit.
 *
 * @package BracelliClub
 */

namespace Bracelli\Club\Tests\integration;

use Bracelli\Club\Core\Permissions\Capabilities;
use Bracelli\Club\Core\Permissions\Roles;
use Bracelli\Club\Modules\Core\ModulesPage;
use Bracelli\Club\Modules\Core\SettingsPage;
use Bracelli\Club\Modules\Permissions\PermissionsModule;
use Bracelli\Club\Modules\Recovery\RecoveryModule;

final class AdminActionsTest extends TestCase {

	public function test_module_toggle_requires_capability(): void {
		$this->login_as( Roles::SEGRETERIA );
		$this->expectException( \WPDieException::class );
		$this->admin_post( ModulesPage::ACTION, ModulesPage::ACTION . '_deactivate_diagnostics', [ 'module' => 'diagnostics', 'op' => 'deactivate' ] );
	}

	public function test_module_toggle_requires_valid_nonce(): void {
		$this->login_as( 'administrator' );
		$_POST    = [ 'module' => 'diagnostics', 'op' => 'deactivate', '_wpnonce' => 'invalido' ];
		$_REQUEST = $_POST;
		$this->expectException( \WPDieException::class );
		do_action( 'admin_post_' . ModulesPage::ACTION );
	}

	public function test_module_deactivation_and_activation_are_audited(): void {
		$this->login_as( 'administrator' );
		$location = $this->admin_post( ModulesPage::ACTION, ModulesPage::ACTION . '_deactivate_diagnostics', [ 'module' => 'diagnostics', 'op' => 'deactivate' ] );
		$this->assertStringContainsString( 'bc_notice=deactivated', $location );
		$this->assertFalse( $this->plugin->modules->is_enabled( 'diagnostics' ) );
		$this->assertSame( 1, $this->audit_count( 'module.deactivated' ) );

		$location = $this->admin_post( ModulesPage::ACTION, ModulesPage::ACTION . '_activate_diagnostics', [ 'module' => 'diagnostics', 'op' => 'activate' ] );
		$this->assertStringContainsString( 'bc_notice=activated', $location );
		$this->assertTrue( $this->plugin->modules->is_enabled( 'diagnostics' ) );
		$this->assertSame( 1, $this->audit_count( 'module.activated' ) );
	}

	public function test_required_module_cannot_be_deactivated_from_ui(): void {
		$this->login_as( 'administrator' );
		$location = $this->admin_post( ModulesPage::ACTION, ModulesPage::ACTION . '_deactivate_audit', [ 'module' => 'audit', 'op' => 'deactivate' ] );
		$this->assertStringContainsString( 'bc_notice=failed', $location );
		$this->assertTrue( $this->plugin->modules->is_active( 'audit' ) );
	}

	public function test_settings_save_validates_and_audits_diff(): void {
		$this->login_as( 'administrator' );
		$location = $this->admin_post(
			SettingsPage::ACTION_SAVE,
			SettingsPage::ACTION_SAVE,
			[ 'settings' => [ 'ui.default_theme' => 'dark', 'ui.default_palette' => 'brace' ] ]
		);
		$this->assertStringContainsString( 'bc_notice=saved', $location );
		$this->assertSame( 'dark', $this->plugin->settings->get( 'ui.default_theme' ) );
		$event = $this->plugin->audit->query( [ 'action' => 'settings.updated' ] )['items'][0];
		$this->assertSame( [ 'auto', 'dark' ], $event['diff']['ui.default_theme'] );

		$location = $this->admin_post( SettingsPage::ACTION_SAVE, SettingsPage::ACTION_SAVE, [ 'settings' => [ 'ui.default_theme' => '<script>' ] ] );
		$this->assertStringContainsString( 'bc_notice=invalid', $location );
		$this->assertSame( 'dark', $this->plugin->settings->get( 'ui.default_theme' ) );
	}

	public function test_settings_require_capability(): void {
		$this->login_as( 'editor' );
		$this->expectException( \WPDieException::class );
		$this->admin_post( SettingsPage::ACTION_SAVE, SettingsPage::ACTION_SAVE, [ 'settings' => [ 'ui.default_theme' => 'dark' ] ] );
	}

	public function test_permissions_matrix_cannot_grant_admin_only_capability(): void {
		$this->login_as( 'administrator' );
		$location = $this->admin_post(
			PermissionsModule::ACTION_SAVE,
			PermissionsModule::ACTION_SAVE,
			[
				'caps' => [
					Roles::SEGRETERIA => [ Capabilities::DASHBOARD_VIEW, Capabilities::AUDIT_VIEW, Capabilities::PERMISSIONS_MANAGE, 'manage_options' ],
				],
			]
		);
		$this->assertStringContainsString( 'bc_notice=saved', $location );
		$role = get_role( Roles::SEGRETERIA );
		$this->assertTrue( $role->has_cap( Capabilities::AUDIT_VIEW ) );
		$this->assertFalse( $role->has_cap( Capabilities::PERMISSIONS_MANAGE ), 'Gestione permessi non è assegnabile.' );
		$this->assertFalse( $role->has_cap( 'manage_options' ), 'Capability WordPress native non sono assegnabili.' );
		$this->assertFalse( get_role( Roles::ISTRUTTORE )->has_cap( Capabilities::DASHBOARD_VIEW ), 'Ruoli non inviati perdono le capability non spuntate.' );
		$this->assertSame( 1, $this->audit_count( 'permissions.updated' ) );

		$this->admin_post( PermissionsModule::ACTION_RESET, PermissionsModule::ACTION_RESET . '_' . Roles::SEGRETERIA, [ 'role' => Roles::SEGRETERIA ] );
		$this->admin_post( PermissionsModule::ACTION_RESET, PermissionsModule::ACTION_RESET . '_' . Roles::ISTRUTTORE, [ 'role' => Roles::ISTRUTTORE ] );
		$this->assertFalse( get_role( Roles::SEGRETERIA )->has_cap( Capabilities::AUDIT_VIEW ) );
		$this->assertTrue( get_role( Roles::ISTRUTTORE )->has_cap( Capabilities::DASHBOARD_VIEW ) );
	}

	public function test_recovery_requires_manage_options_and_works(): void {
		$user = $this->login_as( 'administrator' );
		$this->plugin->preferences->update( $user, [ 'hide_wp_menu' => true, 'theme' => 'dark' ] );

		$this->assertStringContainsString( 'bc_notice=menu_shown', $this->admin_post( RecoveryModule::ACTION, RecoveryModule::ACTION . '_show_wp_menu', [ 'op' => 'show_wp_menu' ] ) );
		$this->assertFalse( $this->plugin->preferences->get( $user )['hide_wp_menu'] );
		$this->assertSame( 'dark', $this->plugin->preferences->get( $user )['theme'] );

		$this->assertStringContainsString( 'bc_notice=prefs_reset', $this->admin_post( RecoveryModule::ACTION, RecoveryModule::ACTION . '_reset_prefs', [ 'op' => 'reset_prefs' ] ) );
		$this->assertSame( 'auto', $this->plugin->preferences->get( $user )['theme'] );

		$this->assertStringContainsString( 'bc_notice=all_off', $this->admin_post( RecoveryModule::ACTION, RecoveryModule::ACTION . '_deactivate_all', [ 'op' => 'deactivate_all' ] ) );
		$this->assertFalse( $this->plugin->modules->is_enabled( 'dashboard' ) );
		$this->assertFalse( $this->plugin->modules->is_enabled( 'diagnostics' ) );
		$this->assertTrue( $this->plugin->modules->is_enabled( 'audit' ) );

		$this->login_as( Roles::SEGRETERIA );
		$this->expectException( \WPDieException::class );
		$this->admin_post( RecoveryModule::ACTION, RecoveryModule::ACTION . '_deactivate_all', [ 'op' => 'deactivate_all' ] );
	}

	/**
	 * @dataProvider pages
	 */
	public function test_admin_pages_render_for_admin_and_deny_others( string $render, string $deny_role ): void {
		$this->login_as( 'administrator' );
		set_current_screen( 'dashboard' );
		ob_start();
		$this->render_page( $render );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'bc-app', $html );
		$this->assertStringNotContainsString( 'Warning', $html );

		$this->login_as( $deny_role );
		$this->expectException( \WPDieException::class );
		ob_start();
		try {
			$this->render_page( $render );
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function pages(): array {
		return [
			'moduli'       => [ 'modules', Roles::SEGRETERIA ],
			'impostazioni' => [ 'settings', Roles::SEGRETERIA ],
			'permessi'     => [ 'permissions', 'editor' ],
			'registro'     => [ 'audit', Roles::SEGRETERIA ],
			'diagnostica'  => [ 'diagnostics', Roles::SEGRETERIA ],
			'ripristino'   => [ 'recovery', Roles::SEGRETERIA ],
			'home'         => [ 'home', 'subscriber' ],
		];
	}

	private function render_page( string $page ): void {
		$modules = $this->plugin->modules;
		switch ( $page ) {
			case 'modules':
				( new ModulesPage( $this->plugin ) )->render();
				return;
			case 'settings':
				( new SettingsPage( $this->plugin ) )->render();
				return;
			case 'home':
				$modules->instance( 'core' )->render_home();
				return;
			default:
				$modules->instance( $page )->render();
		}
	}
}
