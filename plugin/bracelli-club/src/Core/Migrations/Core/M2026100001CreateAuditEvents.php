<?php
/**
 * Tabella del registro attività.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Migrations\Core;

use Bracelli\Club\Core\Migrations\MigrationInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Crea {prefix}bc_audit_events (append-only, PII minimizzate: vedi Redactor).
 */
final class M2026100001CreateAuditEvents implements MigrationInterface {

	/**
	 * {@inheritDoc}
	 */
	public function key(): string {
		return '2026_10_0001_create_audit_events';
	}

	/**
	 * {@inheritDoc}
	 */
	public function module(): string {
		return 'audit';
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return 'Crea la tabella del registro attività.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function up( \wpdb $db ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $db->prefix . 'bc_audit_events';
		$charset = $db->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				occurred_at datetime NOT NULL,
				actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
				actor_type varchar(20) NOT NULL DEFAULT 'user',
				action varchar(100) NOT NULL,
				module varchar(64) NOT NULL DEFAULT '',
				resource_type varchar(64) NOT NULL DEFAULT '',
				resource_id varchar(191) NOT NULL DEFAULT '',
				diff_redacted longtext NULL,
				ip_hash char(16) NOT NULL DEFAULT '',
				request_id char(36) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				KEY occurred_at (occurred_at),
				KEY resource (resource_type,resource_id),
				KEY actor_id (actor_id),
				KEY action (action)
			) {$charset};"
		);
		if ( $table !== $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $db->esc_like( $table ) ) ) ) {
			throw new \RuntimeException( 'Creazione tabella registro attività non riuscita.' );
		}
	}
}
