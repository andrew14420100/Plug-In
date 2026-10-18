<?php
/**
 * @package BracelliClub
 */

namespace Bracelli\Club\Tests\integration;

use Bracelli\Club\Core\Admin\Navigation;
use Bracelli\Club\Core\Permissions\Roles;
use Bracelli\Club\Plugin;

final class SafeModeAndUninstallTest extends TestCase {

	public function test_safe_mode_boots_only_recovery(): void {
		$property = new \ReflectionProperty( Plugin::class, 'instance' );
		$property->setAccessible( true );
		$original = $property->getValue();
		try {
			add_filter( 'bracelli_club_safe_mode', '__return_true' );
			Plugin::reset_for_tests();
			$safe = Plugin::instance();
			$safe->register_hooks();
			$this->assertTrue( $safe->is_safe_mode() );
			$this->assertNotNull( $safe->modules->instance( 'recovery' ) );
			$this->assertNull( $safe->modules->instance( 'dashboard' ) );
			$this->assertNull( $safe->modules->instance( 'core' ) );

			$this->login_as( 'administrator' );
			$this->assertSame( [ 'recovery' ], array_column( Navigation::items_for_current_user(), 'id' ) );
			$this->assertSame( admin_url(), apply_filters( 'login_redirect', admin_url(), '', wp_get_current_user() ), 'In modalità sicura niente redirect alla dashboard.' );
		} finally {
			remove_filter( 'bracelli_club_safe_mode', '__return_true' );
			$property->setValue( null, $original );
		}
	}

	public function test_uninstall_without_flag_keeps_everything(): void {
		$this->plugin->audit->log( 'test.keep' );
		$this->plugin->settings->update( [ 'ui.default_theme' => 'dark' ] );
		$this->run_uninstall();
		$this->assertSame( 'dark', get_option( 'bracelli_club_settings' )['ui.default_theme'] );
		$this->assertNotNull( get_role( Roles::SEGRETERIA ) );
		$this->assertSame( 1, $this->audit_count( 'test.keep' ) );
	}

	public function test_uninstall_with_flag_removes_options_roles_and_drops_tables(): void {
		$queries = [];
		$capture = static function ( $query ) use ( &$queries ) {
			if ( str_starts_with( trim( (string) $query ), 'DROP TABLE IF EXISTS' ) ) {
				$queries[] = $query;
				return 'SELECT 1'; // Non elimina davvero le tabelle condivise dagli altri test.
			}
			return $query;
		};
		add_filter( 'query', $capture, 1 );
		$user = $this->user_with_role( Roles::SEGRETERIA );
		update_user_meta( $user, 'bracelli_club_prefs', [ 'theme' => 'dark' ] );
		$this->plugin->settings->update( [ 'uninstall.delete_data' => true ] );

		$this->run_uninstall();
		remove_filter( 'query', $capture, 1 );

		$this->assertFalse( get_option( 'bracelli_club_settings' ) );
		$this->assertNull( get_role( Roles::SEGRETERIA ) );
		$this->assertFalse( get_role( 'administrator' )->has_cap( 'bracelli_dashboard_view' ) );
		$this->assertSame( '', get_user_meta( $user, 'bracelli_club_prefs', true ) );
		$this->assertNull( get_role( Roles::DIPENDENTE ) );
		$this->assertNull( get_role( Roles::SOCIO ) );
		$this->assertCount( 7, $queries );
		global $wpdb;
		$this->assertStringContainsString( $wpdb->prefix . 'bc_audit_events', $queries[0] );
		$this->assertStringContainsString( $wpdb->prefix . 'bc_turni_shifts', $queries[2] );
		$this->assertStringContainsString( $wpdb->prefix . 'bc_turni_attendance', $queries[3] );
		$this->assertStringContainsString( $wpdb->prefix . 'bc_corsi_courses', $queries[4] );
		$this->assertStringContainsString( $wpdb->prefix . 'bc_corsi_slots', $queries[5] );
		$this->assertStringContainsString( $wpdb->prefix . 'bc_corsi_attendance', $queries[6] );

		Roles::sync();
	}

	private function run_uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'bracelli-club/bracelli-club.php' );
		}
		include BRACELLI_CLUB_DIR . 'uninstall.php';
	}
}
