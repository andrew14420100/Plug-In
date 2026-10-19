<?php
/**
 * Data da cui vale l'assegnazione di un orario.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Migrations\Core;

use Bracelli\Club\Core\Migrations\MigrationInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Aggiunge agli orari dei corsi la colonna assigned_from: un insegnante può registrare per la prima volta
 * solo le lezioni da quella data (non quelle di un collega o di un giorno settimanale precedente).
 * Per gli orari esistenti vale la data dell'ultima modifica, oppure della creazione.
 */
final class M2026100007AddSlotAssignedFrom implements MigrationInterface {

	/**
	 * {@inheritDoc}
	 */
	public function key(): string {
		return '2026_10_0007_add_slot_assigned_from';
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
		return 'Data da cui vale l\'assegnazione di ogni orario dei corsi.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function up( \wpdb $db ): void {
		$table = $db->prefix . 'bc_corsi_slots';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuerySchemaChange -- tabella del plugin, migrazione.
		if ( null === $db->get_var( $db->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'assigned_from' ) ) ) {
			$db->query( "ALTER TABLE {$table} ADD COLUMN assigned_from date DEFAULT NULL" );
		}
		if ( null === $db->get_var( $db->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'assigned_from' ) ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- messaggio interno; quando viene mostrato è escapato.
			throw new \RuntimeException( 'Aggiunta colonna non riuscita: ' . $table . '.assigned_from' );
		}
		$db->query( "UPDATE {$table} SET assigned_from = DATE(COALESCE(updated_at, created_at)) WHERE assigned_from IS NULL" );
		// phpcs:enable
	}
}
