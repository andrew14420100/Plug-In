<?php
/**
 * @package BracelliClub
 */

namespace Bracelli\Club\Tests\integration;

use Bracelli\Club\Core\Admin\Pages;
use Bracelli\Club\Modules\Area\Portal;
use Bracelli\Club\Core\Permissions\Roles;
use Bracelli\Club\Modules\Dashboard\DashboardModule;

final class DashboardBehaviourTest extends TestCase {

	private function dashboard(): DashboardModule {
		$module = $this->plugin->modules->instance( 'dashboard' );
		$this->assertInstanceOf( DashboardModule::class, $module );
		return $module;
	}

	public function test_login_redirect_goes_to_bracelli_dashboard_only_when_appropriate(): void {
		$admin = new \WP_User( $this->user_with_role( 'administrator' ) );
		$staff = new \WP_User( $this->user_with_role( Roles::SEGRETERIA ) );
		$sub   = new \WP_User( $this->user_with_role( 'subscriber' ) );
		$dash  = Pages::url( Pages::PARENT );

		$this->assertSame( $dash, apply_filters( 'login_redirect', admin_url(), '', $admin ) );
		$this->assertSame( admin_url( 'edit.php' ), apply_filters( 'login_redirect', admin_url( 'edit.php' ), admin_url( 'edit.php' ), $admin ), 'Una destinazione esplicita viene rispettata.' );
		$this->assertSame( admin_url(), apply_filters( 'login_redirect', admin_url(), '', $sub ) );
		// La segreteria usa l'area riservata, non la bacheca WordPress.
		$this->assertSame( Portal::url(), apply_filters( 'login_redirect', admin_url(), '', $staff ) );

		$this->plugin->preferences->update( $admin->ID, [ 'login_to_dashboard' => false ] );
		$this->assertSame( admin_url(), apply_filters( 'login_redirect', admin_url(), '', $admin ) );
	}

	public function test_wp_menu_hiding_is_per_user_and_never_on_recovery(): void {
		$user = $this->login_as( 'administrator' );
		$this->assertFalse( $this->dashboard()->wp_menu_hidden() );
		$this->plugin->preferences->update( $user, [ 'hide_wp_menu' => true ] );
		$this->assertTrue( $this->dashboard()->wp_menu_hidden() );
		$this->assertStringContainsString( 'bc-hide-wp-menu', $this->dashboard()->body_class( '' ) );

		ob_start();
		$this->dashboard()->menu_toggle_button();
		$button = (string) ob_get_clean();
		$this->assertStringContainsString( 'Mostra menu WordPress', $button );
		$this->assertStringContainsString( '_wpnonce=', $button );

		$_GET['page'] = 'bracelli-recovery';
		$this->assertFalse( $this->dashboard()->wp_menu_hidden(), 'La pagina di ripristino mostra sempre il menu.' );
		unset( $_GET['page'] );

		$this->plugin->settings->update( [ 'ui.allow_hide_wp_menu' => false ] );
		$this->assertFalse( $this->dashboard()->wp_menu_hidden(), 'L\'impostazione globale prevale.' );
		$this->plugin->settings->update( [ 'ui.allow_hide_wp_menu' => true ] );

		wp_set_current_user( $this->user_with_role( Roles::ISTRUTTORE ) );
		$this->assertFalse( $this->dashboard()->wp_menu_hidden(), 'La preferenza è personale.' );
	}

	public function test_toggle_action_flips_preference_with_nonce(): void {
		$user = $this->login_as( Roles::SEGRETERIA );
		$this->capture_redirects();
		$_REQUEST['_wpnonce'] = wp_create_nonce( DashboardModule::ACTION_MENU );
		$_GET['back']         = rawurlencode( '/wp-admin/profile.php' );
		try {
			do_action( 'admin_post_' . DashboardModule::ACTION_MENU );
			$this->fail( 'Redirect atteso.' );
		} catch ( RedirectException $e ) {
			$this->assertStringContainsString( 'profile.php', $e->getMessage() );
		}
		$this->assertTrue( $this->plugin->preferences->get( $user )['hide_wp_menu'] );
	}

	public function test_scripts_load_only_on_bracelli_pages(): void {
		$this->login_as( 'administrator' );
		set_current_screen( 'dashboard' );
		do_action( 'admin_menu' );
		$hook = Pages::hook( Pages::PARENT );
		$this->assertNotNull( $hook );

		do_action( 'admin_enqueue_scripts', 'index.php' );
		$this->assertFalse( wp_script_is( DashboardModule::SCRIPT_HANDLE, 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'bracelli-club-admin', 'enqueued' ) );

		do_action( 'admin_enqueue_scripts', $hook );
		$this->assertTrue( wp_style_is( 'bracelli-club-admin', 'enqueued' ) );
		if ( is_readable( BRACELLI_CLUB_DIR . 'assets/dist/dashboard/index.js' ) ) {
			$this->assertTrue( wp_script_is( DashboardModule::SCRIPT_HANDLE, 'enqueued' ) );
			$this->assertStringContainsString( 'window.bracelliDashboard', (string) wp_scripts()->get_inline_script_data( DashboardModule::SCRIPT_HANDLE, 'before' ) );
		}
		$this->assertFalse( wp_script_is( 'bracelli-club-audit', 'enqueued' ), 'Il bundle del registro resta nella sua pagina.' );
	}

	public function test_inline_bootstrap_data_cannot_break_out_of_script_tag(): void {
		$user = $this->login_as( 'administrator' );
		// Valore scritto direttamente nel database, aggirando la pulizia di WordPress.
		global $wpdb;
		$wpdb->update( $wpdb->users, [ 'display_name' => '</script><script>alert(1)</script>' ], [ 'ID' => $user ] );
		clean_user_cache( $user );
		wp_set_current_user( 0 );
		wp_set_current_user( $user );
		$this->assertSame( '</script><script>alert(1)</script>', wp_get_current_user()->display_name );
		set_current_screen( 'dashboard' );
		do_action( 'admin_menu' );
		do_action( 'admin_enqueue_scripts', Pages::hook( Pages::PARENT ) );
		$inline = (string) wp_scripts()->get_inline_script_data( DashboardModule::SCRIPT_HANDLE, 'before' );
		if ( '' === $inline ) {
			$this->markTestSkipped( 'Bundle non compilato.' );
		}
		$this->assertStringContainsString( 'alert(1)', $inline, 'Il valore di prova deve essere presente (codificato).' );
		$this->assertStringNotContainsString( '</script>', $inline );
		$this->assertStringContainsString( '\u003C\/script\u003E', $inline );
	}

	public function test_frontend_requests_load_no_bracelli_assets(): void {
		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;
		$this->go_to( home_url( '/' ) );
		do_action( 'wp_enqueue_scripts' );
		foreach ( wp_scripts()->queue as $handle ) {
			$this->assertStringNotContainsString( 'bracelli', $handle );
		}
		foreach ( wp_styles()->queue as $handle ) {
			$this->assertStringNotContainsString( 'bracelli', $handle );
		}
	}
}
