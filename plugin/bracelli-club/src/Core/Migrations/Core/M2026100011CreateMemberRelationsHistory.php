<?php
/**
 * Relazioni familiari e storico dell'archivio soci.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Migrations\Core;

use Bracelli\Club\Core\Migrations\MigrationInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Le relazioni collegano anagrafiche esistenti. Lo storico registra i campi cambiati senza duplicare dati personali.
 */
final class M2026100011CreateMemberRelationsHistory implements MigrationInterface {

	/** {@inheritDoc} */
	public function key(): string {
		return '2026_10_0011_create_member_relations_history';
	}

	/** {@inheritDoc} */
	public function module(): string {
		return 'soci';
	}

	/** {@inheritDoc} */
	public function description(): string {
		return 'Relazioni tra soci e tutori e storico delle modifiche anagrafiche.';
	}

	/** {@inheritDoc} */
	public function up( \wpdb $db ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset   = $db->get_charset_collate();
		$relations = $db->prefix . 'bc_member_guardians';
		$history   = $db->prefix . 'bc_member_history';

		dbDelta(
			"CREATE TABLE {$relations} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				member_id bigint(20) unsigned NOT NULL,
				guardian_id bigint(20) unsigned NOT NULL,
				relationship varchar(40) NOT NULL DEFAULT 'genitore/tutore',
				is_primary tinyint(1) unsigned NOT NULL DEFAULT 0,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY member_guardian (member_id,guardian_id),
				KEY member_id (member_id),
				KEY guardian_id (guardian_id)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$history} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				member_id bigint(20) unsigned NOT NULL,
				actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
				action varchar(40) NOT NULL,
				changed_fields text NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY member_created (member_id,created_at),
				KEY actor_id (actor_id)
			) {$charset};"
		);

		foreach ( [ $relations, $history ] as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $table !== $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $db->esc_like( $table ) ) ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- messaggio interno; quando viene mostrato è escapato.
				throw new \RuntimeException( 'Creazione tabella non riuscita: ' . $table );
			}
		}
	}
}
