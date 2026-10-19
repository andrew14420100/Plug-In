<?php
/**
 * Archivio anagrafico dei soci.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Migrations\Core;

use Bracelli\Club\Core\Migrations\MigrationInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Crea l'archivio soci senza tessere, codici a barre o collegamenti a software esterni.
 */
final class M2026100009CreateMembersTable implements MigrationInterface {

	/** {@inheritDoc} */
	public function key(): string {
		return '2026_10_0009_create_members_table';
	}

	/** {@inheritDoc} */
	public function module(): string {
		return 'soci';
	}

	/** {@inheritDoc} */
	public function description(): string {
		return 'Crea l’archivio anagrafico dei soci.';
	}

	/** {@inheritDoc} */
	public function up( \wpdb $db ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = $db->prefix . 'bc_members';
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				first_name varchar(100) NOT NULL,
				last_name varchar(100) NOT NULL,
				email varchar(190) NOT NULL DEFAULT '',
				phone varchar(40) NOT NULL DEFAULT '',
				birth_date date DEFAULT NULL,
				address varchar(190) NOT NULL DEFAULT '',
				city varchar(100) NOT NULL DEFAULT '',
				postal_code varchar(20) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'active',
				notes text NULL,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
				updated_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY last_name (last_name),
				KEY email (email),
				KEY status (status)
			) {$db->get_charset_collate()};"
		);
		if ( $table !== $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $db->esc_like( $table ) ) ) ) {
			throw new \RuntimeException( 'Creazione tabella non riuscita: ' . $table );
		}
	}
}
