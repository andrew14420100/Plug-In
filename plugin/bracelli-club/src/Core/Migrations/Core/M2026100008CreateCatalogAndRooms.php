<?php
/**
 * Catalogo dei corsi e sale.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Migrations\Core;

use Bracelli\Club\Core\Migrations\MigrationInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Crea la tabella delle sale e aggiunge colonne a corsi e orari. Solo aggiunte: nessun dato esistente
 * viene modificato (le nuove colonne partono vuote; il vecchio testo "sede" degli orari resta com'è).
 */
final class M2026100008CreateCatalogAndRooms implements MigrationInterface {

	/**
	 * {@inheritDoc}
	 */
	public function key(): string {
		return '2026_10_0008_create_catalog_and_rooms';
	}

	/**
	 * {@inheritDoc}
	 */
	public function module(): string {
		return 'corsi';
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return 'Sale dei corsi e nuove informazioni del catalogo (descrizione, livello, colore, sala dell\'orario).';
	}

	/**
	 * {@inheritDoc}
	 */
	public function up( \wpdb $db ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$rooms = $db->prefix . 'bc_corsi_rooms';
		dbDelta(
			"CREATE TABLE {$rooms} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(120) NOT NULL,
				notes varchar(255) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'active',
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
				updated_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY status (status)
			) {$db->get_charset_collate()};"
		);
		if ( $rooms !== $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $db->esc_like( $rooms ) ) ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- messaggio interno; quando viene mostrato è escapato.
			throw new \RuntimeException( 'Creazione tabella non riuscita: ' . $rooms );
		}

		$courses = $db->prefix . 'bc_corsi_courses';
		$this->add_column( $db, $courses, 'description', 'text NULL' );
		$this->add_column( $db, $courses, 'level', "varchar(120) NOT NULL DEFAULT ''" );
		$this->add_column( $db, $courses, 'color', "varchar(7) NOT NULL DEFAULT ''" );
		$this->add_column( $db, $courses, 'manual', 'tinyint(1) unsigned NOT NULL DEFAULT 0' );
		$this->add_column( $db, $courses, 'created_by', 'bigint(20) unsigned NOT NULL DEFAULT 0' );
		$this->add_column( $db, $courses, 'updated_by', 'bigint(20) unsigned NOT NULL DEFAULT 0' );
		$this->add_column( $db, $db->prefix . 'bc_corsi_slots', 'room_id', 'bigint(20) unsigned NOT NULL DEFAULT 0' );
	}

	/**
	 * Aggiunge una colonna solo se manca (idempotente) e verifica che ci sia.
	 *
	 * @param \wpdb  $db         Database.
	 * @param string $table      Tabella del plugin.
	 * @param string $column     Colonna.
	 * @param string $definition Definizione SQL fissa.
	 */
	private function add_column( \wpdb $db, string $table, string $column, string $definition ): void {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuerySchemaChange -- tabella del plugin, definizioni fisse, migrazione.
		if ( null === $db->get_var( $db->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) ) ) {
			$db->query( "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}" );
		}
		if ( null === $db->get_var( $db->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- messaggio interno; quando viene mostrato è escapato.
			throw new \RuntimeException( 'Aggiunta colonna non riuscita: ' . $table . '.' . $column );
		}
		// phpcs:enable
	}
}
