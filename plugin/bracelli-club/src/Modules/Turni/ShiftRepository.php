<?php
/**
 * Accesso al database di turni e presenze.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Turni;

defined( 'ABSPATH' ) || exit;

/**
 * Solo query parametrizzate. Nessun metodo di cancellazione: i turni si annullano (ShiftService).
 * Non verifica permessi: lo fa ShiftService, unico punto d'ingresso per le operazioni.
 */
final class ShiftRepository {

	/**
	 * @param \wpdb $db Connessione.
	 */
	public function __construct( private \wpdb $db ) {
	}

	/**
	 * Tabella dei turni.
	 */
	public function shifts_table(): string {
		return $this->db->prefix . 'bc_turni_shifts';
	}

	/**
	 * Tabella delle presenze.
	 */
	public function attendance_table(): string {
		return $this->db->prefix . 'bc_turni_attendance';
	}

	/**
	 * Un turno con l'eventuale registrazione di presenza.
	 *
	 * @param int $id Turno.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		$rows = $this->select( 's.id = %d', [ $id ], 1 );
		return $rows[0] ?? null;
	}

	/**
	 * Turni filtrati, ordinati per data e ora.
	 *
	 * @param array{from?: string, to?: string, employee_id?: int, status?: string, attendance?: string} $filters Filtri già validati.
	 * @param int                                                                                     $limit   Massimo righe.
	 * @return array<int, array<string, mixed>>
	 */
	public function search( array $filters, int $limit = 500 ): array {
		$where  = [ '1=1' ];
		$params = [];
		if ( ! empty( $filters['from'] ) ) {
			$where[]  = 's.shift_date >= %s';
			$params[] = $filters['from'];
		}
		if ( ! empty( $filters['to'] ) ) {
			$where[]  = 's.shift_date <= %s';
			$params[] = $filters['to'];
		}
		if ( ! empty( $filters['employee_id'] ) ) {
			$where[]  = 's.employee_id = %d';
			$params[] = (int) $filters['employee_id'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 's.status = %s';
			$params[] = $filters['status'];
		}
		if ( ! empty( $filters['attendance'] ) ) {
			if ( 'none' === $filters['attendance'] ) {
				$where[] = 'a.id IS NULL';
			} else {
				$where[]  = 'a.status = %s';
				$params[] = $filters['attendance'];
			}
		}
		return $this->select( implode( ' AND ', $where ), $params, $limit );
	}

	/**
	 * Indica se il dipendente ha già un turno programmato che si sovrappone all'intervallo.
	 *
	 * @param int    $employee_id Dipendente.
	 * @param string $date        Data Y-m-d.
	 * @param string $start       Inizio H:i:s.
	 * @param string $end         Fine H:i:s.
	 * @param int    $exclude_id  Turno da escludere (in modifica).
	 */
	public function has_overlap( int $employee_id, string $date, string $start, string $end, int $exclude_id = 0 ): bool {
		$table = $this->shifts_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- nome tabella interno, valori con segnaposto.
		$found = $this->db->get_var( $this->db->prepare( "SELECT id FROM {$table} WHERE employee_id = %d AND shift_date = %s AND status = 'scheduled' AND id <> %d AND start_time < %s AND end_time > %s LIMIT 1", $employee_id, $date, $exclude_id, $end, $start ) );
		return null !== $found;
	}

	/**
	 * Inserisce un turno e ne restituisce l'id (0 se non riuscito).
	 *
	 * @param array<string, mixed> $data Colonne.
	 */
	public function insert( array $data ): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabella del plugin.
		$ok = $this->db->insert( $this->shifts_table(), $data );
		return false === $ok ? 0 : (int) $this->db->insert_id;
	}

	/**
	 * Aggiorna un turno.
	 *
	 * @param int                  $id   Turno.
	 * @param array<string, mixed> $data Colonne.
	 */
	public function update( int $id, array $data ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabella del plugin.
		return false !== $this->db->update( $this->shifts_table(), $data, [ 'id' => $id ] );
	}

	/**
	 * Inserisce o aggiorna la registrazione di presenza di un turno (al massimo una per turno).
	 *
	 * @param int    $shift_id    Turno.
	 * @param int    $employee_id Dipendente.
	 * @param string $status      present|absent.
	 * @param bool   $exists      Se esiste già una registrazione.
	 */
	public function save_attendance( int $shift_id, int $employee_id, string $status, bool $exists ): bool {
		$now = gmdate( 'Y-m-d H:i:s' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- tabella del plugin.
		if ( $exists ) {
			$ok = $this->db->update(
				$this->attendance_table(),
				[
					'status'     => $status,
					'updated_at' => $now,
				],
				[ 'shift_id' => $shift_id ]
			);
		} else {
			$ok = $this->db->insert(
				$this->attendance_table(),
				[
					'shift_id'    => $shift_id,
					'employee_id' => $employee_id,
					'status'      => $status,
					'recorded_at' => $now,
				]
			);
		}
		// phpcs:enable
		return false !== $ok;
	}

	/**
	 * Conteggi delle presenze per un insieme di turni già filtrati.
	 *
	 * @param array<int, array<string, mixed>> $rows Turni.
	 * @return array{total: int, cancelled: int, present: int, absent: int, none: int}
	 */
	public static function summarize( array $rows ): array {
		$out = [
			'total'     => count( $rows ),
			'cancelled' => 0,
			'present'   => 0,
			'absent'    => 0,
			'none'      => 0,
		];
		foreach ( $rows as $row ) {
			if ( 'cancelled' === $row['status'] ) {
				++$out['cancelled'];
			} elseif ( null === $row['attendance_status'] ) {
				++$out['none'];
			} else {
				++$out[ 'present' === $row['attendance_status'] ? 'present' : 'absent' ];
			}
		}
		return $out;
	}

	/**
	 * Query comune con la presenza collegata.
	 *
	 * @param string            $where  Clausola con segnaposto, costruita solo da campi fissi.
	 * @param array<int, mixed> $params Valori.
	 * @param int               $limit  Massimo righe.
	 * @return array<int, array<string, mixed>>
	 */
	private function select( string $where, array $params, int $limit ): array {
		$shifts     = $this->shifts_table();
		$attendance = $this->attendance_table();
		$params[]   = max( 1, $limit );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- clausole da campi fissi, valori sempre come segnaposto.
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT s.id, s.employee_id, s.shift_date, s.start_time, s.end_time, s.status, s.created_by, s.created_at, s.updated_by, s.updated_at, s.cancelled_by, s.cancelled_at,
					a.status AS attendance_status, a.recorded_at AS attendance_recorded_at, a.updated_at AS attendance_updated_at
				FROM {$shifts} s LEFT JOIN {$attendance} a ON a.shift_id = s.id
				WHERE {$where} ORDER BY s.shift_date ASC, s.start_time ASC, s.id ASC LIMIT %d",
				$params
			),
			ARRAY_A
		);
		// phpcs:enable
		$out = [];
		foreach ( (array) $rows as $row ) {
			foreach ( [ 'id', 'employee_id', 'created_by', 'updated_by', 'cancelled_by' ] as $int ) {
				$row[ $int ] = (int) $row[ $int ];
			}
			$out[] = $row;
		}
		return $out;
	}
}
