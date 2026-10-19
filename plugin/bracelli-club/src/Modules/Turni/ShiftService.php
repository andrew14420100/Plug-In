<?php
/**
 * Regole e permessi di turni e presenze.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Turni;

use Bracelli\Club\Core\Audit\AuditLogger;
use Bracelli\Club\Core\Permissions\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Unico punto d'ingresso per leggere e modificare turni e presenze.
 * Ogni metodo riceve l'id dell'utente che agisce e verifica sul server le sue capability
 * e, per i dipendenti, che il turno sia proprio. Ogni modifica viene scritta nel registro attività.
 *
 * Regole del prototipo:
 * - i turni non si eliminano: si annullano (stato "cancelled") e lo storico resta nel registro;
 * - un turno annullato, o con presenza già registrata, non si modifica più;
 * - il dipendente registra Presente/Assente solo per i propri turni programmati, dal giorno del turno in poi;
 *   può cambiare la scelta solo entro il giorno del turno.
 */
final class ShiftService {

	public const AUDIT_RESOURCE = 'turni_shift';

	public const STATUS_SCHEDULED = 'scheduled';
	public const STATUS_CANCELLED = 'cancelled';

	public const PRESENT = 'present';
	public const ABSENT  = 'absent';

	/** Giorni nel passato entro cui il dipendente può ancora registrare un turno non registrato. */
	public const PAST_DAYS = 30;

	/**
	 * Funzione che restituisce la data odierna del sito (Y-m-d).
	 *
	 * @var callable
	 */
	private $today;

	/**
	 * @param ShiftRepository $repo  Dati.
	 * @param AuditLogger     $audit Registro attività.
	 * @param callable|null   $today Data odierna (sostituibile nei test); predefinita: fuso orario del sito.
	 */
	public function __construct( private ShiftRepository $repo, private AuditLogger $audit, ?callable $today = null ) {
		$this->today = $today ?? static fn(): string => (string) wp_date( 'Y-m-d' );
	}

	/**
	 * Data odierna del sito.
	 */
	public function today(): string {
		return (string) ( $this->today )();
	}

	/**
	 * Può gestire i turni di tutti.
	 *
	 * @param int $actor Utente.
	 */
	public function can_manage( int $actor ): bool {
		return $actor > 0 && user_can( $actor, Capabilities::TURNI_MANAGE );
	}

	/**
	 * È un dipendente con turni propri.
	 *
	 * @param int $actor Utente.
	 */
	public function is_employee( int $actor ): bool {
		return $actor > 0 && user_can( $actor, Capabilities::TURNI_SELF );
	}

	/**
	 * Dipendenti a cui si possono assegnare turni (solo per chi gestisce i turni).
	 *
	 * @param int $actor Utente.
	 * @return array<int, string> id => nome visualizzato
	 */
	public function employees( int $actor ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return [];
		}
		$users = get_users(
			[
				'capability' => Capabilities::TURNI_SELF,
				'orderby'    => 'display_name',
				'order'      => 'ASC',
				'fields'     => [ 'ID', 'display_name' ],
				'number'     => 1000,
			]
		);
		$out   = [];
		foreach ( $users as $user ) {
			$out[ (int) $user->ID ] = (string) $user->display_name;
		}
		return $out;
	}

	/**
	 * Turni per chi gestisce (tutti i dipendenti).
	 *
	 * @param int                  $actor   Utente.
	 * @param array<string, mixed> $filters Filtri già validati (from, to, employee_id, status, attendance).
	 * @return array<int, array<string, mixed>>|null null se non autorizzato.
	 */
	public function list_for_manager( int $actor, array $filters ): ?array {
		if ( ! $this->can_manage( $actor ) ) {
			return null;
		}
		return $this->repo->search( $filters );
	}

	/**
	 * Turni del dipendente stesso: il filtro sull'id viene dal server, mai dalla richiesta.
	 *
	 * @param int    $actor Utente.
	 * @param string $from  Data iniziale Y-m-d.
	 * @param string $to    Data finale Y-m-d.
	 * @return array<int, array<string, mixed>>|null null se non autorizzato.
	 */
	public function list_for_employee( int $actor, string $from, string $to ): ?array {
		if ( ! $this->is_employee( $actor ) ) {
			return null;
		}
		return $this->repo->search(
			[
				'from'        => $from,
				'to'          => $to,
				'employee_id' => $actor,
			]
		);
	}

	/**
	 * Turno visibile all'utente (gestione: tutti; dipendente: solo i propri), altrimenti null.
	 *
	 * @param int $actor Utente.
	 * @param int $id    Turno.
	 * @return array<string, mixed>|null
	 */
	public function get_visible( int $actor, int $id ): ?array {
		$shift = $id > 0 ? $this->repo->find( $id ) : null;
		if ( null === $shift ) {
			return null;
		}
		if ( $this->can_manage( $actor ) || ( $this->is_employee( $actor ) && $shift['employee_id'] === $actor ) ) {
			return $shift;
		}
		return null;
	}

	/**
	 * Crea un turno.
	 *
	 * @param int                  $actor Utente.
	 * @param array<string, mixed> $input employee_id, shift_date, start_time, end_time (non validati).
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public function create( int $actor, array $input ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return $this->fail( 'forbidden' );
		}
		$employee_id = isset( $input['employee_id'] ) ? absint( $input['employee_id'] ) : 0;
		$errors      = [];
		if ( 0 === $employee_id || ! $this->is_employee( $employee_id ) ) {
			$errors['employee_id'] = __( 'Scegli un dipendente valido.', 'bracelli-club' );
		}
		[ $slot, $slot_errors ] = $this->validate_slot( $input );
		$errors                += $slot_errors;
		if ( [] === $errors && $this->repo->has_overlap( $employee_id, $slot['shift_date'], $slot['start_time'], $slot['end_time'] ) ) {
			$errors['start_time'] = __( 'Il dipendente ha già un turno programmato che si sovrappone a questo orario.', 'bracelli-club' );
		}
		if ( [] !== $errors ) {
			return $this->fail( 'invalid', $errors );
		}

		$id = $this->repo->insert(
			[
				'employee_id' => $employee_id,
				'shift_date'  => $slot['shift_date'],
				'start_time'  => $slot['start_time'],
				'end_time'    => $slot['end_time'],
				'status'      => self::STATUS_SCHEDULED,
				'created_by'  => $actor,
				'created_at'  => gmdate( 'Y-m-d H:i:s' ),
			]
		);
		if ( 0 === $id ) {
			return $this->fail( 'db_error' );
		}
		$this->log( 'turni.shift_created', $id, [ 'employee_id' => $employee_id ] + $slot );
		return $this->ok( 'created', $id );
	}

	/**
	 * Modifica data e orario di un turno programmato (il dipendente assegnato non cambia).
	 *
	 * @param int                  $actor Utente.
	 * @param int                  $id    Turno.
	 * @param array<string, mixed> $input shift_date, start_time, end_time (non validati).
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public function update( int $actor, int $id, array $input ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return $this->fail( 'forbidden' );
		}
		$shift = $this->repo->find( $id );
		if ( null === $shift ) {
			return $this->fail( 'not_found' );
		}
		$locked = $this->lock_reason( $shift );
		if ( '' !== $locked ) {
			return $this->fail( $locked );
		}
		[ $slot, $errors ] = $this->validate_slot( $input );
		if ( [] === $errors && $this->repo->has_overlap( $shift['employee_id'], $slot['shift_date'], $slot['start_time'], $slot['end_time'], $id ) ) {
			$errors['start_time'] = __( 'Il dipendente ha già un turno programmato che si sovrappone a questo orario.', 'bracelli-club' );
		}
		if ( [] !== $errors ) {
			return $this->fail( 'invalid', $errors );
		}
		$before = [
			'shift_date' => (string) $shift['shift_date'],
			'start_time' => (string) $shift['start_time'],
			'end_time'   => (string) $shift['end_time'],
		];
		if ( $before === $slot ) {
			return $this->ok( 'unchanged', $id );
		}
		$saved = $this->repo->update(
			$id,
			$slot + [
				'updated_by' => $actor,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			]
		);
		if ( ! $saved ) {
			return $this->fail( 'db_error' );
		}
		$this->log(
			'turni.shift_updated',
			$id,
			[
				'employee_id' => $shift['employee_id'],
				'before'      => $before,
				'after'       => $slot,
			]
		);
		return $this->ok( 'updated', $id );
	}

	/**
	 * Annulla un turno conservandolo (nessuna cancellazione dal database).
	 *
	 * @param int $actor Utente.
	 * @param int $id    Turno.
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public function cancel( int $actor, int $id ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return $this->fail( 'forbidden' );
		}
		$shift = $this->repo->find( $id );
		if ( null === $shift ) {
			return $this->fail( 'not_found' );
		}
		$locked = $this->lock_reason( $shift );
		if ( '' !== $locked ) {
			return $this->fail( $locked );
		}
		$saved = $this->repo->update(
			$id,
			[
				'status'       => self::STATUS_CANCELLED,
				'cancelled_by' => $actor,
				'cancelled_at' => gmdate( 'Y-m-d H:i:s' ),
			]
		);
		if ( ! $saved ) {
			return $this->fail( 'db_error' );
		}
		$this->log(
			'turni.shift_cancelled',
			$id,
			[
				'employee_id' => $shift['employee_id'],
				'shift_date'  => $shift['shift_date'],
				'start_time'  => $shift['start_time'],
				'end_time'    => $shift['end_time'],
			]
		);
		return $this->ok( 'cancelled', $id );
	}

	/**
	 * Il dipendente registra Presente o Assente per un proprio turno.
	 *
	 * @param int    $actor  Utente.
	 * @param int    $id     Turno.
	 * @param string $status present|absent (non validato).
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public function record_attendance( int $actor, int $id, string $status ): array {
		if ( ! $this->is_employee( $actor ) ) {
			return $this->fail( 'forbidden' );
		}
		if ( ! in_array( $status, [ self::PRESENT, self::ABSENT ], true ) ) {
			return $this->fail( 'invalid' );
		}
		$shift = $this->repo->find( $id );
		// Un turno di un altro dipendente risulta "non trovato": non si rivela nemmeno che esiste.
		if ( null === $shift || $shift['employee_id'] !== $actor ) {
			return $this->fail( 'not_found' );
		}
		$reason = $this->attendance_block_reason( $shift );
		if ( '' !== $reason ) {
			return $this->fail( $reason );
		}
		$previous = $shift['attendance_status'];
		if ( $previous === $status ) {
			return $this->ok( 'unchanged', $id );
		}
		if ( ! $this->repo->save_attendance( $id, $actor, $status, null !== $previous ) ) {
			return $this->fail( 'db_error' );
		}
		$this->log(
			null === $previous ? 'turni.attendance_recorded' : 'turni.attendance_changed',
			$id,
			[
				'employee_id' => $actor,
				'shift_date'  => $shift['shift_date'],
				'before'      => $previous,
				'after'       => $status,
			]
		);
		return $this->ok( 'attendance_saved', $id );
	}

	/**
	 * Motivo per cui il dipendente non può registrare (o cambiare) la presenza, oppure ''.
	 *
	 * @param array<string, mixed> $shift Turno con presenza.
	 */
	public function attendance_block_reason( array $shift ): string {
		$today = $this->today();
		if ( self::STATUS_SCHEDULED !== $shift['status'] ) {
			return 'shift_cancelled';
		}
		if ( (string) $shift['shift_date'] > $today ) {
			return 'too_early';
		}
		if ( null !== $shift['attendance_status'] && (string) $shift['shift_date'] !== $today ) {
			return 'attendance_locked';
		}
		if ( (string) $shift['shift_date'] < gmdate( 'Y-m-d', (int) strtotime( $today . ' -' . self::PAST_DAYS . ' days' ) ) ) {
			return 'too_old';
		}
		return '';
	}

	/**
	 * Motivo per cui il turno non è più modificabile o annullabile, oppure ''.
	 *
	 * @param array<string, mixed> $shift Turno con presenza.
	 */
	public function lock_reason( array $shift ): string {
		if ( self::STATUS_SCHEDULED !== $shift['status'] ) {
			return 'shift_cancelled';
		}
		if ( null !== $shift['attendance_status'] ) {
			return 'has_attendance';
		}
		return '';
	}

	/**
	 * Storico di un turno dal registro attività (solo per chi gestisce i turni).
	 *
	 * @param int $actor Utente.
	 * @param int $id    Turno.
	 * @return array<int, array<string, mixed>>
	 */
	public function history( int $actor, int $id ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return [];
		}
		$result = $this->audit->query(
			[
				'resource_type' => self::AUDIT_RESOURCE,
				'resource_id'   => (string) $id,
			],
			1,
			100
		);
		return $result['items'];
	}

	/**
	 * Valida data e orari. Gli orari devono stare nello stesso giorno (fine dopo l'inizio).
	 *
	 * @param array<string, mixed> $input Dati grezzi.
	 * @return array{0: array{shift_date: string, start_time: string, end_time: string}, 1: array<string, string>}
	 */
	public function validate_slot( array $input ): array {
		$errors = [];
		$date   = is_string( $input['shift_date'] ?? null ) ? trim( $input['shift_date'] ) : '';
		$start  = is_string( $input['start_time'] ?? null ) ? trim( $input['start_time'] ) : '';
		$end    = is_string( $input['end_time'] ?? null ) ? trim( $input['end_time'] ) : '';

		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) || (int) $m[1] < 2000 || (int) $m[1] > 2100 ) {
			$errors['shift_date'] = __( 'Indica una data valida.', 'bracelli-club' );
		}
		$valid_time = static fn( string $t ): bool => (bool) preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $t );
		if ( ! $valid_time( $start ) ) {
			$errors['start_time'] = __( 'Indica un orario di inizio valido (hh:mm).', 'bracelli-club' );
		}
		if ( ! $valid_time( $end ) ) {
			$errors['end_time'] = __( 'Indica un orario di fine valido (hh:mm).', 'bracelli-club' );
		}
		if ( ! isset( $errors['start_time'] ) && ! isset( $errors['end_time'] ) && $end <= $start ) {
			$errors['end_time'] = __( 'L\'orario di fine deve essere successivo all\'inizio (turni a cavallo della mezzanotte non supportati).', 'bracelli-club' );
		}
		return [
			[
				'shift_date' => $date,
				'start_time' => $start . ':00',
				'end_time'   => $end . ':00',
			],
			$errors,
		];
	}

	/**
	 * @param string               $action Azione.
	 * @param int                  $id     Turno.
	 * @param array<string, mixed> $diff   Dettagli (nessun dato personale: solo id, date e orari).
	 */
	private function log( string $action, int $id, array $diff ): void {
		$this->audit->log(
			$action,
			[
				'module'        => 'turni',
				'resource_type' => self::AUDIT_RESOURCE,
				'resource_id'   => (string) $id,
				'diff'          => $diff,
			]
		);
	}

	/**
	 * @param string $code Esito.
	 * @param int    $id   Turno.
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	private function ok( string $code, int $id ): array {
		return [
			'ok'     => true,
			'code'   => $code,
			'errors' => [],
			'id'     => $id,
		];
	}

	/**
	 * @param string                $code   Esito.
	 * @param array<string, string> $errors Errori per campo.
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	private function fail( string $code, array $errors = [] ): array {
		return [
			'ok'     => false,
			'code'   => $code,
			'errors' => $errors,
			'id'     => 0,
		];
	}
}
