<?php
/**
 * Accesso al database di corsi, orari e presenze degli insegnanti.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Corsi;

defined( 'ABSPATH' ) || exit;

/**
 * Solo query parametrizzate. Nessun metodo di cancellazione: corsi e orari si annullano cambiando stato.
 * Non verifica permessi: lo fanno CourseService e CourseImporter.
 */
final class CourseRepository {

	/**
	 * @param \wpdb $db Connessione.
	 */
	public function __construct( private \wpdb $db ) {
	}

	/**
	 * Connessione (per le transazioni dell'importazione).
	 */
	public function db(): \wpdb {
		return $this->db;
	}

	/**
	 * Tabella dei corsi.
	 */
	public function courses_table(): string {
		return $this->db->prefix . 'bc_corsi_courses';
	}

	/**
	 * Tabella degli orari.
	 */
	public function slots_table(): string {
		return $this->db->prefix . 'bc_corsi_slots';
	}

	/**
	 * Tabella delle presenze degli insegnanti.
	 */
	public function attendance_table(): string {
		return $this->db->prefix . 'bc_corsi_attendance';
	}

	/**
	 * Tutti i corsi, ordinati per sezione e nome.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function courses(): array {
		$table = $this->courses_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- nome tabella interno, nessun input.
		$rows = (array) $this->db->get_results( "SELECT * FROM {$table} ORDER BY section ASC, name ASC, group_label ASC, id ASC", ARRAY_A );
		return array_map( [ $this, 'cast_course' ], $rows );
	}

	/**
	 * @param int $id Corso.
	 * @return array<string, mixed>|null
	 */
	public function find_course( int $id ): ?array {
		$table = $this->courses_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $this->db->get_row( $this->db->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $this->cast_course( $row ) : null;
	}

	/**
	 * Corsi per chiave esterna.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function courses_by_key(): array {
		$out = [];
		foreach ( $this->courses() as $course ) {
			$out[ (string) $course['ext_key'] ] = $course;
		}
		return $out;
	}

	/**
	 * Orari con i dati del corso.
	 *
	 * @param array{status?: string, teacher_id?: int, course_id?: int, weekday?: int, section?: string} $filters Filtri già validati.
	 * @return array<int, array<string, mixed>>
	 */
	public function slots( array $filters = [] ): array {
		$where  = [ '1=1' ];
		$params = [];
		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 's.status = %s';
			$params[] = $filters['status'];
		}
		if ( isset( $filters['teacher_id'] ) ) {
			$where[]  = 's.teacher_id = %d';
			$params[] = (int) $filters['teacher_id'];
		}
		if ( ! empty( $filters['course_id'] ) ) {
			$where[]  = 's.course_id = %d';
			$params[] = (int) $filters['course_id'];
		}
		if ( ! empty( $filters['weekday'] ) ) {
			$where[]  = 's.weekday = %d';
			$params[] = (int) $filters['weekday'];
		}
		if ( ! empty( $filters['section'] ) ) {
			$where[]  = 'c.section = %s';
			$params[] = $filters['section'];
		}
		$slots     = $this->slots_table();
		$courses   = $this->courses_table();
		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT s.*, c.name AS course_name, c.group_label, c.section, c.status AS course_status
			FROM {$slots} s JOIN {$courses} c ON c.id = s.course_id
			WHERE {$where_sql} ORDER BY s.weekday ASC, s.start_time ASC, c.section ASC, c.name ASC, s.id ASC";
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- clausole da campi fissi, valori come segnaposto.
		$rows = (array) $this->db->get_results( [] === $params ? $sql : $this->db->prepare( $sql, $params ), ARRAY_A );
		// phpcs:enable
		return array_map( [ $this, 'cast_slot' ], $rows );
	}

	/**
	 * @param int $id Orario.
	 * @return array<string, mixed>|null
	 */
	public function find_slot( int $id ): ?array {
		$slots   = $this->slots_table();
		$courses = $this->courses_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $this->db->get_row( $this->db->prepare( "SELECT s.*, c.name AS course_name, c.group_label, c.section, c.status AS course_status FROM {$slots} s JOIN {$courses} c ON c.id = s.course_id WHERE s.id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $this->cast_slot( $row ) : null;
	}

	/**
	 * Orari per chiave esterna (solo quelli nati da un'importazione).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function slots_by_key(): array {
		$table = $this->slots_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = (array) $this->db->get_results( "SELECT * FROM {$table} WHERE ext_key IS NOT NULL", ARRAY_A );
		$out  = [];
		foreach ( $rows as $row ) {
			$out[ (string) $row['ext_key'] ] = $this->cast_slot( $row );
		}
		return $out;
	}

	/**
	 * @param string               $table Tabella (courses_table() o slots_table()).
	 * @param array<string, mixed> $data  Colonne.
	 */
	public function insert( string $table, array $data ): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabella del plugin.
		$ok = $this->db->insert( $table, $data );
		return false === $ok ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * @param string               $table Tabella.
	 * @param int                  $id    Riga.
	 * @param array<string, mixed> $data  Colonne.
	 */
	public function update( string $table, int $id, array $data ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabella del plugin.
		return false !== $this->db->update( $table, $data, [ 'id' => $id ] );
	}

	/**
	 * Presenze registrate per gli orari indicati in un intervallo di date, indicizzate "slot_id|data".
	 *
	 * @param int[]  $slot_ids Orari.
	 * @param string $from     Data iniziale.
	 * @param string $to       Data finale.
	 * @return array<string, array<string, mixed>>
	 */
	public function attendance( array $slot_ids, string $from, string $to ): array {
		$slot_ids = array_values( array_filter( array_map( 'intval', $slot_ids ) ) );
		if ( [] === $slot_ids ) {
			return [];
		}
		$table        = $this->attendance_table();
		$placeholders = implode( ',', array_fill( 0, count( $slot_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- segnaposto generati, valori come parametri.
		$rows = (array) $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$table} WHERE slot_id IN ({$placeholders}) AND lesson_date BETWEEN %s AND %s",
				array_merge( $slot_ids, [ $from, $to ] )
			),
			ARRAY_A
		);
		// phpcs:enable
		$out = [];
		foreach ( $rows as $row ) {
			$out[ $row['slot_id'] . '|' . $row['lesson_date'] ] = $row;
		}
		return $out;
	}

	/**
	 * Registrazione di una lezione, se esiste.
	 *
	 * @param int    $slot_id Orario.
	 * @param string $date    Data.
	 * @return array<string, mixed>|null
	 */
	public function find_attendance( int $slot_id, string $date ): ?array {
		$found = $this->attendance( [ $slot_id ], $date, $date );
		return $found[ $slot_id . '|' . $date ] ?? null;
	}

	/**
	 * Inserisce o aggiorna la registrazione di una lezione (al massimo una per orario e data).
	 *
	 * @param array<string, mixed> $slot   Orario.
	 * @param string               $date   Data.
	 * @param int                  $actor  Insegnante.
	 * @param string               $status present|absent.
	 * @param bool                 $exists Se la registrazione esiste già.
	 */
	public function save_attendance( array $slot, string $date, int $actor, string $status, bool $exists ): bool {
		$now = gmdate( 'Y-m-d H:i:s' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- tabella del plugin.
		if ( $exists ) {
			$ok = $this->db->update(
				$this->attendance_table(),
				[
					'status'     => $status,
					'updated_at' => $now,
				],
				[
					'slot_id'     => (int) $slot['id'],
					'lesson_date' => $date,
				]
			);
		} else {
			$ok = $this->db->insert(
				$this->attendance_table(),
				[
					'slot_id'     => (int) $slot['id'],
					'lesson_date' => $date,
					'teacher_id'  => $actor,
					'status'      => $status,
					'start_time'  => (string) $slot['start_time'],
					'end_time'    => (string) $slot['end_time'],
					'recorded_at' => $now,
				]
			);
		}
		// phpcs:enable
		return false !== $ok;
	}

	/**
	 * @param array<string, mixed> $row Riga.
	 * @return array<string, mixed>
	 */
	private function cast_course( array $row ): array {
		$row['id'] = (int) $row['id'];
		return $row;
	}

	/**
	 * @param array<string, mixed> $row Riga.
	 * @return array<string, mixed>
	 */
	private function cast_slot( array $row ): array {
		foreach ( [ 'id', 'course_id', 'weekday', 'teacher_id', 'manual', 'created_by', 'updated_by', 'cancelled_by' ] as $int ) {
			$row[ $int ] = (int) ( $row[ $int ] ?? 0 );
		}
		return $row;
	}
}
