<?php
/**
 * Tabelle del modulo Corsi.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Migrations\Core;

use Bracelli\Club\Core\Migrations\MigrationInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Distingue i quattro concetti richiesti:
 * - {prefix}bc_corsi_courses: il corso (chiave esterna stabile per importazioni ripetibili);
 * - {prefix}bc_corsi_slots: gli orari settimanali ricorrenti, con l'insegnante assegnato (account) e quello indicato nei dati;
 * - l'insegnante è un account WordPress collegato all'orario, mai dedotto dal solo nome;
 * - {prefix}bc_corsi_attendance: presenza o assenza dell'insegnante per una lezione (orario + data).
 * Nessun dato viene eliminato: corsi e orari si annullano cambiando stato.
 */
final class M2026100005CreateCourseTables implements MigrationInterface {

	/**
	 * {@inheritDoc}
	 */
	public function key(): string {
		return '2026_10_0005_create_course_tables';
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
		return 'Crea le tabelle di corsi, orari e presenze degli insegnanti.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function up( \wpdb $db ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset    = $db->get_charset_collate();
		$courses    = $db->prefix . 'bc_corsi_courses';
		$slots      = $db->prefix . 'bc_corsi_slots';
		$attendance = $db->prefix . 'bc_corsi_attendance';
		dbDelta(
			"CREATE TABLE {$courses} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				ext_key varchar(100) NOT NULL,
				section varchar(120) NOT NULL DEFAULT '',
				name varchar(190) NOT NULL,
				group_label varchar(190) NOT NULL DEFAULT '',
				notes text NULL,
				status varchar(20) NOT NULL DEFAULT 'active',
				source varchar(40) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				updated_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY ext_key (ext_key),
				KEY section (section)
			) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$slots} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				ext_key varchar(150) DEFAULT NULL,
				course_id bigint(20) unsigned NOT NULL,
				weekday tinyint(1) unsigned NOT NULL,
				start_time time NOT NULL,
				end_time time NOT NULL,
				teacher_id bigint(20) unsigned NOT NULL DEFAULT 0,
				teacher_label varchar(120) NOT NULL DEFAULT '',
				location varchar(120) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'active',
				manual tinyint(1) unsigned NOT NULL DEFAULT 0,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
				updated_at datetime DEFAULT NULL,
				cancelled_by bigint(20) unsigned NOT NULL DEFAULT 0,
				cancelled_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY ext_key (ext_key),
				KEY course_id (course_id),
				KEY teacher_weekday (teacher_id,weekday),
				KEY weekday (weekday)
			) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$attendance} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				slot_id bigint(20) unsigned NOT NULL,
				lesson_date date NOT NULL,
				teacher_id bigint(20) unsigned NOT NULL,
				status varchar(20) NOT NULL,
				start_time time NOT NULL,
				end_time time NOT NULL,
				recorded_at datetime NOT NULL,
				updated_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY slot_date (slot_id,lesson_date),
				KEY teacher_id (teacher_id),
				KEY lesson_date (lesson_date)
			) {$charset};"
		);
		foreach ( [ $courses, $slots, $attendance ] as $table ) {
			if ( $table !== $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $db->esc_like( $table ) ) ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- messaggio interno; quando viene mostrato è escapato.
				throw new \RuntimeException( 'Creazione tabella non riuscita: ' . $table );
			}
		}
	}
}
