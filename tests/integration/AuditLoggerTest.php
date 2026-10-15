<?php
/**
 * @package BracelliClub
 */

namespace Bracelli\Club\Tests\integration;

use Bracelli\Club\Core\Audit\Redactor;
use Bracelli\Club\Core\Permissions\Roles;

final class AuditLoggerTest extends TestCase {

	public function test_personal_data_is_redacted_before_storage(): void {
		global $wpdb;
		$this->login_as( 'administrator' );
		$this->plugin->audit->log(
			'test.redaction',
			[
				'resource_type' => 'member',
				'resource_id'   => '42',
				'diff'          => [
					'email' => 'socio@example.org',
					'note'  => 'scrivere a socio@example.org',
					'stato' => 'attivo',
				],
			]
		);
		$raw = (string) $wpdb->get_var( "SELECT diff_redacted FROM {$wpdb->prefix}bc_audit_events WHERE action = 'test.redaction'" );
		$this->assertStringNotContainsString( 'socio@example.org', $raw );
		$this->assertStringContainsString( 'attivo', $raw );
		$this->assertStringContainsString( Redactor::MASK, $raw );
	}

	public function test_ip_is_stored_only_as_optional_hash(): void {
		global $wpdb;
		$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
		$this->plugin->audit->log( 'test.ip_on' );
		$hash = (string) $wpdb->get_var( "SELECT ip_hash FROM {$wpdb->prefix}bc_audit_events WHERE action = 'test.ip_on'" );
		$this->assertSame( 16, strlen( $hash ) );
		$this->assertStringNotContainsString( '192.0.2.10', $hash );

		$this->plugin->settings->update( [ 'audit.store_ip_hash' => false ] );
		$this->plugin->audit->log( 'test.ip_off' );
		$this->assertSame( '', (string) $wpdb->get_var( "SELECT ip_hash FROM {$wpdb->prefix}bc_audit_events WHERE action = 'test.ip_off'" ) );
		$this->plugin->settings->update( [ 'audit.store_ip_hash' => true ] );
		unset( $_SERVER['REMOTE_ADDR'] );
	}

	public function test_query_filters_and_search_use_placeholders(): void {
		$this->plugin->audit->log( 'test.alpha', [ 'resource_type' => 'module', 'resource_id' => 'uno' ] );
		$this->plugin->audit->log( 'test.beta', [ 'resource_type' => 'module', 'resource_id' => "d'apostrofo" ] );
		$this->assertSame( 1, $this->plugin->audit->query( [ 'action' => 'test.alpha' ] )['total'] );
		$this->assertSame( 1, $this->plugin->audit->query( [ 'search' => "d'apostrofo" ] )['total'] );
		$this->assertSame( 0, $this->plugin->audit->query( [ 'search' => "' OR '1'='1" ] )['total'] );
		$this->assertSame( 0, $this->plugin->audit->query( [ 'from' => '2999-01-01' ] )['total'] );
	}

	public function test_role_changes_involving_bracelli_roles_are_logged(): void {
		$user_id = $this->user_with_role( 'subscriber' );
		$user    = new \WP_User( $user_id );
		$user->set_role( Roles::ISTRUTTORE );
		$user->remove_role( Roles::ISTRUTTORE );
		$user->add_role( 'editor' );
		$this->assertSame( 1, $this->audit_count( 'user.role_added' ) );
		$this->assertSame( 1, $this->audit_count( 'user.role_removed' ), 'Rimozione di subscriber ed aggiunta di editor non generano eventi.' );
		$event = $this->plugin->audit->query( [ 'action' => 'user.role_added' ] )['items'][0];
		$this->assertSame( (string) $user_id, $event['resource_id'] );
		$this->assertSame( [ 'role' => Roles::ISTRUTTORE ], $event['diff'] );
	}
}
