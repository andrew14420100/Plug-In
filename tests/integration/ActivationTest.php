<?php
/**
 * @package BracelliClub
 */

namespace Bracelli\Club\Tests\integration;

use Bracelli\Club\Core\Lifecycle;
use Bracelli\Club\Core\Migrations\Migrator;
use Bracelli\Club\Core\Permissions\Capabilities;
use Bracelli\Club\Core\Permissions\Roles;

final class ActivationTest extends TestCase {

	public function test_tables_roles_and_capabilities_are_installed(): void {
		global $wpdb;
		foreach ( [ 'bc_migrations', 'bc_audit_events', 'bc_turni_shifts', 'bc_turni_attendance' ] as $table ) {
			$this->assertSame( $wpdb->prefix . $table, $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . $table ) ) );
		}
		foreach ( Roles::definitions() as $slug => $definition ) {
			$role = get_role( $slug );
			$this->assertNotNull( $role, $slug );
			$this->assertTrue( $role->has_cap( 'read' ) );
			foreach ( $definition['caps'] as $cap ) {
				$this->assertTrue( $role->has_cap( $cap ), $slug . ' ' . $cap );
			}
			$this->assertFalse( $role->has_cap( Capabilities::MODULES_MANAGE ) );
			$this->assertFalse( $role->has_cap( Capabilities::SETTINGS_MANAGE ) );
			$this->assertFalse( $role->has_cap( Capabilities::PERMISSIONS_MANAGE ) );
		}
		$this->assertTrue( get_role( Roles::SEGRETERIA )->has_cap( Capabilities::TURNI_MANAGE ) );
		$this->assertFalse( get_role( Roles::SEGRETERIA )->has_cap( Capabilities::TURNI_SELF ) );
		$this->assertFalse( get_role( Roles::DIPENDENTE )->has_cap( Capabilities::DASHBOARD_VIEW ) );
		$this->assertFalse( get_role( Roles::DIPENDENTE )->has_cap( Capabilities::TURNI_MANAGE ) );
		$admin = get_role( 'administrator' );
		foreach ( Capabilities::names() as $cap ) {
			$this->assertSame( ! in_array( $cap, Capabilities::NOT_FOR_ADMIN, true ), $admin->has_cap( $cap ), $cap );
		}
	}

	public function test_migrations_are_recorded_and_rerun_is_a_no_op(): void {
		$this->assertSame( [], $this->plugin->migrator->pending() );
		$this->assertFalse( $this->plugin->migrator->needs_run() );
		$applied = $this->plugin->migrator->applied();
		$this->assertSame( 'done', $applied['2026_10_0001_create_audit_events']['status'] );
		$this->assertSame( 'done', $applied['2026_10_0002_install_roles']['status'] );
		$this->assertSame( 'done', $applied['2026_10_0003_create_shift_tables']['status'] );
		$this->assertSame( 'done', $applied['2026_10_0004_grant_shift_capabilities']['status'] );

		$result = $this->plugin->migrator->run();
		$this->assertSame( 'nothing', $result['status'] );
		$this->assertSame( [], $result['ran'] );
	}

	public function test_reactivation_is_idempotent_and_keeps_data(): void {
		$this->plugin->audit->log( 'test.marker' );
		$roles_before = wp_roles()->role_names;

		Lifecycle::deactivate();
		Lifecycle::activate();
		Lifecycle::activate();

		$this->assertSame( 1, $this->audit_count( 'test.marker' ), 'I dati restano dopo disattivazione e riattivazione.' );
		$this->assertSame( $roles_before, wp_roles()->role_names );
		$this->assertGreaterThanOrEqual( 2, $this->audit_count( 'plugin.activated' ) );
		$this->assertGreaterThanOrEqual( 1, $this->audit_count( 'plugin.deactivated' ) );
	}

	public function test_custom_role_capabilities_survive_role_sync(): void {
		get_role( Roles::SEGRETERIA )->add_cap( Capabilities::AUDIT_VIEW );
		Roles::sync();
		$this->assertTrue( get_role( Roles::SEGRETERIA )->has_cap( Capabilities::AUDIT_VIEW ) );
		get_role( Roles::SEGRETERIA )->remove_cap( Capabilities::AUDIT_VIEW );
	}

	public function test_migrations_never_run_on_frontend_requests(): void {
		update_option( Migrator::SCHEMA_OPTION, 'diverso' );
		$this->assertTrue( $this->plugin->migrator->needs_run() );
		$this->go_to( home_url( '/' ) );
		do_action( 'wp_loaded' );
		do_action( 'template_redirect' );
		$this->assertSame( 'diverso', get_option( Migrator::SCHEMA_OPTION ), 'Nessuna migrazione deve partire sul frontend.' );

		set_current_screen( 'dashboard' );
		$this->plugin->maybe_migrate();
		$this->assertSame( $this->plugin->migrator->fingerprint(), get_option( Migrator::SCHEMA_OPTION ) );
	}
}
