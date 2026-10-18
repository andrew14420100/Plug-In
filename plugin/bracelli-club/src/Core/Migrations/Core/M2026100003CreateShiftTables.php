<?php
/**
 * Tabelle del modulo Turni e presenze (prototipo).
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Migrations\Core;

use Bracelli\Club\Core\Migrations\MigrationInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Crea {prefix}bc_turni_shifts (turni, mai cancellati: l'annullamento è uno stato) e
 * {prefix}bc_turni_attendance (una registrazione presente/assente per turno).
 * Data e orari del turno sono "ora locale del sito"; i timestamp tecnici sono in UTC.
 */
final class M2026100003CreateShiftTables implements MigrationInterface {

	/**
	 * {@inheritDoc}
	 */
	public function key(): string {
		return '2026_10_0003_create_shift_tables';
	}

	/**
	 * {@inheritDoc}
	 */
	public function module(): string {
		return 'turni';
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return 'Crea le tabelle di turni e presenze.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function up( \wpdb $db ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset    = $db->get_charset_collate();
		$shifts     = $db->prefix . 'bc_turni_shifts';
		$attendance = $db->prefix . 'bc_turni_attendance';
		dbDelta(
			"CREATE TABLE {$shifts} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				employee_id bigint(20) unsigned NOT NULL,
				shift_date date NOT NULL,
				start_time time NOT NULL,
				end_time time NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'scheduled',
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
				updated_at datetime DEFAULT NULL,
				cancelled_by bigint(20) unsigned NOT NULL DEFAULT 0,
				cancelled_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY employee_date (employee_id,shift_date),
				KEY shift_date (shift_date)
			) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$attendance} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				shift_id bigint(20) unsigned NOT NULL,
				employee_id bigint(20) unsigned NOT NULL,
				status varchar(20) NOT NULL,
				recorded_at datetime NOT NULL,
				updated_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY shift_id (shift_id),
				KEY employee_id (employee_id)
			) {$charset};"
		);
		foreach ( [ $shifts, $attendance ] as $table ) {
			if ( $table !== $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $db->esc_like( $table ) ) ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- messaggio interno; quando viene mostrato è escapato.
				throw new \RuntimeException( 'Creazione tabella non riuscita: ' . $table );
			}
		}
	}
}
