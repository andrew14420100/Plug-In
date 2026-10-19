<?php
/**
 * Regole e permessi di corsi, orari e presenze degli insegnanti.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Corsi;

use Bracelli\Club\Core\Audit\AuditLogger;
use Bracelli\Club\Core\Permissions\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Unico punto d'ingresso per leggere e modificare orari e presenze dei corsi.
 * Ogni metodo riceve l'utente che agisce e verifica sul server capability e, per gli insegnanti,
 * che l'orario sia assegnato a loro. Ogni modifica viene scritta nel registro attività.
 *
 * Regole:
 * - una lezione è un orario settimanale in una data con lo stesso giorno della settimana;
 * - l'insegnante registra Presente/Assente solo per le proprie lezioni, dal giorno della lezione
 *   (non prima) e fino a 30 giorni dopo; può cambiare la scelta solo nel giorno della lezione;
 * - gli orari non si eliminano: si annullano e restano nello storico;
 * - un orario modificato dallo staff viene marcato "manuale" e l'importazione non lo sovrascrive.
 */
final class CourseService {

	public const AUDIT_RESOURCE = 'corsi_slot';
	public const PRESENT        = 'present';
	public const ABSENT         = 'absent';
	public const PAST_DAYS      = 30;

	/** Campi di un orario confrontati e registrati nello storico. */
	public const SLOT_KEYS = [ 'weekday', 'start_time', 'end_time', 'teacher_id', 'room_id', 'location' ];

	/**
	 * Funzione che restituisce la data odierna del sito (Y-m-d).
	 *
	 * @var callable
	 */
	private $today;

	/**
	 * Sale e controllo delle sovrapposizioni.
	 *
	 * @var RoomService
	 */
	private RoomService $rooms;

	/**
	 * @param CourseRepository $repo  Dati.
	 * @param AuditLogger      $audit Registro attività.
	 * @param callable|null    $today Data odierna (sostituibile nei test); predefinita: fuso orario del sito.
	 * @param RoomService|null $rooms Sale (predefinito: creato sugli stessi dati).
	 */
	public function __construct( private CourseRepository $repo, private AuditLogger $audit, ?callable $today = null, ?RoomService $rooms = null ) {
		$this->today = $today ?? static fn(): string => (string) wp_date( 'Y-m-d' );
		$this->rooms = $rooms ?? new RoomService( $repo, $audit );
	}

	/**
	 * Registro attività.
	 */
	public function audit(): AuditLogger {
		return $this->audit;
	}

	/**
	 * Sale.
	 */
	public function rooms(): RoomService {
		return $this->rooms;
	}

	/**
	 * Dati.
	 */
	public function repo(): CourseRepository {
		return $this->repo;
	}

	/**
	 * Data odierna del sito.
	 */
	public function today(): string {
		return (string) ( $this->today )();
	}

	/**
	 * @param int $actor Utente.
	 */
	public function can_view( int $actor ): bool {
		return $actor > 0 && user_can( $actor, Capabilities::CORSI_VIEW );
	}

	/**
	 * @param int $actor Utente.
	 */
	public function can_teach( int $actor ): bool {
		return $actor > 0 && user_can( $actor, Capabilities::CORSI_TEACH );
	}

	/**
	 * @param int $actor Utente.
	 */
	public function can_manage( int $actor ): bool {
		return $actor > 0 && user_can( $actor, Capabilities::CORSI_MANAGE );
	}

	/**
	 * Nomi dei giorni (1 = lunedì, come ISO-8601).
	 *
	 * @return array<int, string>
	 */
	public static function weekdays(): array {
		return [
			1 => __( 'Lunedì', 'bracelli-club' ),
			2 => __( 'Martedì', 'bracelli-club' ),
			3 => __( 'Mercoledì', 'bracelli-club' ),
			4 => __( 'Giovedì', 'bracelli-club' ),
			5 => __( 'Venerdì', 'bracelli-club' ),
			6 => __( 'Sabato', 'bracelli-club' ),
			7 => __( 'Domenica', 'bracelli-club' ),
		];
	}

	/**
	 * Calendario settimanale: orari attivi dei corsi attivi e corsi senza orari attivi.
	 *
	 * @param int    $actor   Utente.
	 * @param string $section Sezione (facoltativa).
	 * @return array{slots: array<int, array<string, mixed>>, without_slots: array<int, array<string, mixed>>}|null
	 */
	public function calendar( int $actor, string $section = '' ): ?array {
		if ( ! $this->can_view( $actor ) ) {
			return null;
		}
		$slots = array_values(
			array_filter(
				$this->repo->slots(
					[
						'status'  => 'active',
						'section' => $section,
					]
				),
				static fn( array $s ): bool => 'active' === $s['course_status']
			)
		);
		$with  = array_flip( array_map( static fn( array $s ): int => $s['course_id'], $slots ) );
		$empty = [];
		foreach ( $this->repo->courses() as $course ) {
			if ( 'active' === $course['status'] && ! isset( $with[ $course['id'] ] ) && ( '' === $section || $section === $course['section'] ) ) {
				$empty[] = $course;
			}
		}
		return [
			'slots'         => $slots,
			'without_slots' => $empty,
		];
	}

	/**
	 * Sezioni presenti (per i filtri).
	 *
	 * @return string[]
	 */
	public function sections(): array {
		$out = [];
		foreach ( $this->repo->courses() as $course ) {
			$out[ (string) $course['section'] ] = true;
		}
		return array_keys( $out );
	}

	/**
	 * Lezioni dell'insegnante stesso in un intervallo (il filtro sull'insegnante viene dal server).
	 *
	 * @param int    $actor Utente.
	 * @param string $from  Data iniziale.
	 * @param string $to    Data finale.
	 * @return array<int, array<string, mixed>>|null
	 */
	public function lessons_for_teacher( int $actor, string $from, string $to ): ?array {
		if ( ! $this->can_teach( $actor ) ) {
			return null;
		}
		$lessons = $this->occurrences(
			$this->repo->slots(
				[
					'status'     => 'active',
					'teacher_id' => $actor,
				]
			),
			$from,
			$to
		);
		// Le lezioni precedenti all'assegnazione erano di un collega o di un altro giorno: non sono dell'insegnante.
		return array_values(
			array_filter(
				$lessons,
				static fn( array $l ): bool => $l['attendance_teacher_id'] === $actor
					|| null === $l['assigned_from']
					|| $l['lesson_date'] >= $l['assigned_from']
			)
		);
	}

	/**
	 * Quadro delle lezioni di tutti gli insegnanti (segreteria).
	 *
	 * @param int                  $actor   Utente.
	 * @param string               $from    Data iniziale.
	 * @param string               $to      Data finale.
	 * @param array<string, mixed> $filters section, teacher (id, oppure -1 = non assegnato), attendance (present|absent|none).
	 * @return array<int, array<string, mixed>>|null
	 */
	public function overview( int $actor, string $from, string $to, array $filters = [] ): ?array {
		if ( ! $this->can_manage( $actor ) ) {
			return null;
		}
		$query = [
			'status'  => 'active',
			'section' => (string) ( $filters['section'] ?? '' ),
		];
		if ( isset( $filters['teacher'] ) && (int) $filters['teacher'] > 0 ) {
			$query['teacher_id'] = (int) $filters['teacher'];
		} elseif ( isset( $filters['teacher'] ) && -1 === (int) $filters['teacher'] ) {
			$query['teacher_id'] = 0;
		}
		$lessons    = $this->occurrences( $this->repo->slots( $query ), $from, $to );
		$attendance = (string) ( $filters['attendance'] ?? '' );
		if ( '' !== $attendance ) {
			$lessons = array_values(
				array_filter(
					$lessons,
					static fn( array $l ): bool => 'none' === $attendance ? null === $l['attendance_status'] : $attendance === $l['attendance_status']
				)
			);
		}
		return $lessons;
	}

	/**
	 * Trasforma gli orari settimanali in lezioni datate, con l'eventuale registrazione.
	 *
	 * @param array<int, array<string, mixed>> $slots Orari.
	 * @param string                           $from  Data iniziale.
	 * @param string                           $to    Data finale.
	 * @return array<int, array<string, mixed>>
	 */
	private function occurrences( array $slots, string $from, string $to ): array {
		$by_day = [];
		foreach ( $slots as $slot ) {
			if ( 'active' === $slot['course_status'] ) {
				$by_day[ $slot['weekday'] ][] = $slot;
			}
		}
		$records = $this->repo->attendance( array_map( static fn( array $s ): int => $s['id'], $slots ), $from, $to );
		$out     = [];
		$day     = new \DateTimeImmutable( $from . ' 00:00:00', new \DateTimeZone( 'UTC' ) );
		$last    = new \DateTimeImmutable( $to . ' 00:00:00', new \DateTimeZone( 'UTC' ) );
		while ( $day <= $last ) {
			$date = $day->format( 'Y-m-d' );
			foreach ( $by_day[ (int) $day->format( 'N' ) ] ?? [] as $slot ) {
				$record = $records[ $slot['id'] . '|' . $date ] ?? null;
				$out[]  = $slot + [
					'lesson_date'            => $date,
					'attendance_status'      => null === $record ? null : (string) $record['status'],
					'attendance_teacher_id'  => null === $record ? 0 : (int) $record['teacher_id'],
					'attendance_recorded_at' => null === $record ? null : (string) $record['recorded_at'],
					'attendance_updated_at'  => null === $record ? null : $record['updated_at'],
				];
			}
			$day = $day->modify( '+1 day' );
		}
		usort( $out, static fn( array $a, array $b ): int => [ $a['lesson_date'], $a['start_time'], $a['course_name'] ] <=> [ $b['lesson_date'], $b['start_time'], $b['course_name'] ] );
		return $out;
	}

	/**
	 * L'insegnante registra Presente o Assente per una propria lezione.
	 *
	 * @param int    $actor   Utente.
	 * @param int    $slot_id Orario.
	 * @param string $date    Data della lezione (non validata).
	 * @param string $status  present|absent (non validato).
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public function record_attendance( int $actor, int $slot_id, string $date, string $status ): array {
		if ( ! $this->can_teach( $actor ) ) {
			return $this->fail( 'forbidden' );
		}
		if ( ! in_array( $status, [ self::PRESENT, self::ABSENT ], true ) || ! self::valid_date( $date ) ) {
			return $this->fail( 'invalid' );
		}
		$slot = $this->repo->find_slot( $slot_id );
		// Un orario assegnato a un collega risulta "non trovato": non si rivela nemmeno che esiste.
		if ( null === $slot || $slot['teacher_id'] !== $actor ) {
			return $this->fail( 'not_found' );
		}
		$record = $this->repo->find_attendance( $slot_id, $date );
		// La registrazione fatta da un collega (prima di un cambio di insegnante) non si modifica.
		if ( null !== $record && (int) $record['teacher_id'] !== $actor ) {
			return $this->fail( 'attendance_locked' );
		}
		$reason = $this->attendance_block_reason( $slot, $date, null !== $record );
		if ( '' !== $reason ) {
			return $this->fail( $reason );
		}
		$previous = null === $record ? null : (string) $record['status'];
		if ( $previous === $status ) {
			return $this->ok( 'unchanged', $slot_id );
		}
		if ( ! $this->repo->save_attendance( $slot, $date, $actor, $status, null !== $record ) ) {
			return $this->fail( 'db_error' );
		}
		$this->log(
			null === $previous ? 'corsi.attendance_recorded' : 'corsi.attendance_changed',
			$slot_id,
			[
				'teacher_id'  => $actor,
				'lesson_date' => $date,
				'before'      => $previous,
				'after'       => $status,
			]
		);
		return $this->ok( 'attendance_saved', $slot_id );
	}

	/**
	 * Motivo per cui l'insegnante non può registrare (o cambiare) la presenza, oppure ''.
	 *
	 * @param array<string, mixed> $slot     Orario.
	 * @param string               $date     Data della lezione.
	 * @param bool                 $recorded Se esiste già una registrazione.
	 */
	public function attendance_block_reason( array $slot, string $date, bool $recorded ): string {
		$today = $this->today();
		if ( 'active' !== $slot['status'] || 'active' !== ( $slot['course_status'] ?? 'active' ) ) {
			return 'slot_cancelled';
		}
		if ( (int) gmdate( 'N', (int) strtotime( $date . ' 12:00:00 UTC' ) ) !== (int) $slot['weekday'] ) {
			return 'not_a_lesson';
		}
		if ( $date > $today ) {
			return 'too_early';
		}
		if ( $date < gmdate( 'Y-m-d', (int) strtotime( $today . ' -' . self::PAST_DAYS . ' days' ) ) ) {
			return 'too_old';
		}
		if ( $recorded && $date !== $today ) {
			return 'attendance_locked';
		}
		if ( ! $recorded && null !== ( $slot['assigned_from'] ?? null ) && $date < (string) $slot['assigned_from'] ) {
			return 'before_assignment';
		}
		return '';
	}

	/**
	 * Insegnanti a cui si possono assegnare orari (solo per chi gestisce i corsi).
	 *
	 * @param int $actor Utente.
	 * @return array<int, string> id => nome visualizzato
	 */
	public function teachers( int $actor ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return [];
		}
		$out = [];
		foreach (
			get_users(
				[
					'capability' => Capabilities::CORSI_TEACH,
					'orderby'    => 'display_name',
					'order'      => 'ASC',
					'fields'     => [ 'ID', 'display_name' ],
					'number'     => 1000,
				]
			) as $user
		) {
			$out[ (int) $user->ID ] = (string) $user->display_name;
		}
		return $out;
	}

	/**
	 * Orario visibile a chi gestisce i corsi, altrimenti null.
	 *
	 * @param int $actor Utente.
	 * @param int $id    Orario.
	 * @return array<string, mixed>|null
	 */
	public function get_slot( int $actor, int $id ): ?array {
		return $this->can_manage( $actor ) && $id > 0 ? $this->repo->find_slot( $id ) : null;
	}

	/**
	 * Corso visibile a chi gestisce i corsi, altrimenti null.
	 *
	 * @param int $actor Utente.
	 * @param int $id    Corso.
	 * @return array<string, mixed>|null
	 */
	public function get_course( int $actor, int $id ): ?array {
		return $this->can_manage( $actor ) && $id > 0 ? $this->repo->find_course( $id ) : null;
	}

	/**
	 * Corsi con i loro orari (segreteria).
	 *
	 * @param int    $actor   Utente.
	 * @param string $section Sezione (facoltativa).
	 * @return array<int, array<string, mixed>>|null
	 */
	public function courses_with_slots( int $actor, string $section = '' ): ?array {
		if ( ! $this->can_manage( $actor ) ) {
			return null;
		}
		$slots = [];
		foreach ( $this->repo->slots( [ 'section' => $section ] ) as $slot ) {
			$slots[ $slot['course_id'] ][] = $slot;
		}
		$out = [];
		foreach ( $this->repo->courses() as $course ) {
			if ( '' === $section || $section === $course['section'] ) {
				$out[] = $course + [ 'slots' => $slots[ $course['id'] ] ?? [] ];
			}
		}
		return $out;
	}

	/**
	 * Modifica giorno, orario, insegnante, sala e sede di un orario attivo o da confermare.
	 * Una sala già occupata nella stessa fascia blocca il salvataggio (controllo sul server, con lock).
	 *
	 * @param int                  $actor Utente.
	 * @param int                  $id    Orario.
	 * @param array<string, mixed> $input weekday, start_time, end_time, teacher_id, room_id, location, apply_course (non validati).
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public function update_slot( int $actor, int $id, array $input ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return $this->fail( 'forbidden' );
		}
		$slot = $this->repo->find_slot( $id );
		if ( null === $slot ) {
			return $this->fail( 'not_found' );
		}
		if ( ! in_array( $slot['status'], [ 'active', 'draft' ], true ) ) {
			return $this->fail( 'slot_cancelled' );
		}
		[ $data, $errors ] = $this->validate_slot( $input, (int) $slot['room_id'] );
		if ( [] !== $errors ) {
			return $this->fail( 'invalid', $errors );
		}
		return $this->rooms->locked( fn(): array => $this->save_slot_update( $actor, $slot, $data, ! empty( $input['apply_course'] ) ) );
	}

	/**
	 * Parte di update_slot eseguita sotto lock.
	 *
	 * @param int                  $actor Utente.
	 * @param array<string, mixed> $slot  Orario attuale.
	 * @param array<string, mixed> $data  Valori validati.
	 * @param bool                 $apply Applica l'insegnante agli altri orari attivi del corso.
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	private function save_slot_update( int $actor, array $slot, array $data, bool $apply ): array {
		$id = (int) $slot['id'];
		if ( 'active' === $slot['status'] ) {
			$conflicts = $this->rooms->conflicts( [ [ 'id' => $id ] + array_intersect_key( $data, array_flip( [ 'room_id', 'weekday', 'start_time', 'end_time' ] ) ) ] );
			if ( [] !== $conflicts ) {
				return $this->fail( 'room_conflict', [ 'room_id' => RoomService::describe( $conflicts ) ] );
			}
		}
		$keys   = self::SLOT_KEYS;
		$before = array_intersect_key( $slot, array_flip( $keys ) );
		$after  = array_intersect_key( $data, array_flip( $keys ) );
		if ( $before == $after && ! $apply ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- confronto di valori, tipi già normalizzati.
			return $this->ok( 'unchanged', $id );
		}
		if ( $before != $after ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
			$this->save_slot_change( $actor, $slot, $after );
		}
		if ( $apply ) {
			foreach ( $this->repo->slots(
				[
					'course_id' => (int) $slot['course_id'],
					'status'    => 'active',
				]
			) as $other ) {
				if ( $other['id'] !== $id && $other['teacher_id'] !== $data['teacher_id'] ) {
					$this->save_slot_change(
						$actor,
						$other,
						array_merge( array_intersect_key( $other, array_flip( $keys ) ), [ 'teacher_id' => $data['teacher_id'] ] )
					);
				}
			}
		}
		return $this->ok( 'slot_updated', $id );
	}

	/**
	 * Aggiunge un orario a un corso (per esempio per un corso importato senza orari).
	 *
	 * @param int                  $actor     Utente.
	 * @param int                  $course_id Corso.
	 * @param array<string, mixed> $input     weekday, start_time, end_time, teacher_id, room_id, location (non validati).
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public function add_slot( int $actor, int $course_id, array $input ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return $this->fail( 'forbidden' );
		}
		if ( null === $this->repo->find_course( $course_id ) ) {
			return $this->fail( 'not_found' );
		}
		[ $data, $errors ] = $this->validate_slot( $input );
		if ( [] !== $errors ) {
			return $this->fail( 'invalid', $errors );
		}
		return $this->rooms->locked( fn(): array => $this->insert_slot( $actor, $course_id, $data ) );
	}

	/**
	 * Parte di add_slot eseguita sotto lock.
	 *
	 * @param int                  $actor     Utente.
	 * @param int                  $course_id Corso.
	 * @param array<string, mixed> $data      Valori validati.
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	private function insert_slot( int $actor, int $course_id, array $data ): array {
		$conflicts = $this->rooms->conflicts( [ [ 'id' => 0 ] + array_intersect_key( $data, array_flip( [ 'room_id', 'weekday', 'start_time', 'end_time' ] ) ) ] );
		if ( [] !== $conflicts ) {
			return $this->fail( 'room_conflict', [ 'room_id' => RoomService::describe( $conflicts ) ] );
		}
		$id = $this->repo->insert(
			$this->repo->slots_table(),
			$data + [
				'course_id'     => $course_id,
				'status'        => 'active',
				'manual'        => 1,
				'created_by'    => $actor,
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
				'assigned_from' => $this->today(),
			]
		);
		if ( 0 === $id ) {
			return $this->fail( 'db_error' );
		}
		$this->log( 'corsi.slot_created', $id, [ 'course_id' => $course_id ] + $data );
		return $this->ok( 'slot_created', $id );
	}

	/**
	 * Annulla un orario conservandolo (nessuna cancellazione dal database).
	 *
	 * @param int $actor Utente.
	 * @param int $id    Orario.
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public function cancel_slot( int $actor, int $id ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return $this->fail( 'forbidden' );
		}
		$slot = $this->repo->find_slot( $id );
		if ( null === $slot ) {
			return $this->fail( 'not_found' );
		}
		if ( ! in_array( $slot['status'], [ 'active', 'draft' ], true ) ) {
			return $this->fail( 'slot_cancelled' );
		}
		$now = gmdate( 'Y-m-d H:i:s' );
		if ( ! $this->repo->update(
			$this->repo->slots_table(),
			$id,
			[
				'status'       => 'cancelled',
				'manual'       => 1,
				'cancelled_by' => $actor,
				'cancelled_at' => $now,
			]
		) ) {
			return $this->fail( 'db_error' );
		}
		$this->log(
			'corsi.slot_cancelled',
			$id,
			[
				'course_id'  => $slot['course_id'],
				'weekday'    => $slot['weekday'],
				'start_time' => $slot['start_time'],
				'end_time'   => $slot['end_time'],
			]
		);
		return $this->ok( 'slot_cancelled_ok', $id );
	}

	/**
	 * Storico di un orario dal registro attività (solo per chi gestisce i corsi).
	 *
	 * @param int $actor Utente.
	 * @param int $id    Orario.
	 * @return array<int, array<string, mixed>>
	 */
	public function history( int $actor, int $id ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return [];
		}
		return $this->audit->query(
			[
				'resource_type' => self::AUDIT_RESOURCE,
				'resource_id'   => (string) $id,
			],
			1,
			100
		)['items'];
	}

	/**
	 * Valida i dati di un orario. L'insegnante deve essere un account con la capability "Insegnante" (o nessuno).
	 *
	 * @param array<string, mixed> $input     Dati grezzi.
	 * @param int                  $keep_room Sala attuale dell'orario (resta valida anche se archiviata).
	 * @return array{0: array{weekday: int, start_time: string, end_time: string, teacher_id: int, room_id: int, location: string}, 1: array<string, string>}
	 */
	public function validate_slot( array $input, int $keep_room = 0 ): array {
		$errors  = [];
		$weekday = isset( $input['weekday'] ) ? absint( $input['weekday'] ) : 0;
		$start   = is_string( $input['start_time'] ?? null ) ? trim( $input['start_time'] ) : '';
		$end     = is_string( $input['end_time'] ?? null ) ? trim( $input['end_time'] ) : '';
		$teacher = isset( $input['teacher_id'] ) ? absint( $input['teacher_id'] ) : 0;
		$place   = is_string( $input['location'] ?? null ) ? mb_substr( sanitize_text_field( $input['location'] ), 0, 120 ) : '';
		if ( $weekday < 1 || $weekday > 7 ) {
			$errors['weekday'] = __( 'Scegli il giorno della settimana.', 'bracelli-club' );
		}
		$valid_time = static fn( string $t ): bool => (bool) preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $t );
		if ( ! $valid_time( $start ) ) {
			$errors['start_time'] = __( 'Indica un orario di inizio valido (hh:mm).', 'bracelli-club' );
		}
		if ( ! $valid_time( $end ) ) {
			$errors['end_time'] = __( 'Indica un orario di fine valido (hh:mm).', 'bracelli-club' );
		}
		if ( ! isset( $errors['start_time'] ) && ! isset( $errors['end_time'] ) && $end <= $start ) {
			$errors['end_time'] = __( 'L\'orario di fine deve essere successivo all\'inizio.', 'bracelli-club' );
		}
		if ( $teacher > 0 && ! user_can( $teacher, Capabilities::CORSI_TEACH ) ) {
			$errors['teacher_id'] = __( 'Scegli un insegnante valido.', 'bracelli-club' );
		}
		[ $room, $room_error ] = $this->rooms->validate_choice( $input['room_id'] ?? 0, $keep_room );
		if ( '' !== $room_error ) {
			$errors['room_id'] = $room_error;
		}
		return [
			[
				'weekday'    => $weekday,
				'start_time' => $start . ':00',
				'end_time'   => $end . ':00',
				'teacher_id' => $teacher,
				'room_id'    => $room,
				'location'   => $place,
			],
			$errors,
		];
	}

	/**
	 * Data Y-m-d valida.
	 *
	 * @param string $date Data.
	 */
	public static function valid_date( string $date ): bool {
		return (bool) preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/**
	 * Salva una modifica di orario come manuale, con storico.
	 *
	 * @param int                  $actor Utente.
	 * @param array<string, mixed> $slot  Orario attuale.
	 * @param array<string, mixed> $after Nuovi valori (weekday, start_time, end_time, teacher_id, room_id, location).
	 */
	private function save_slot_change( int $actor, array $slot, array $after ): void {
		$keys   = self::SLOT_KEYS;
		$before = array_intersect_key( $slot, array_flip( $keys ) );
		$extra  = [
			'manual'     => 1,
			'updated_by' => $actor,
			'updated_at' => gmdate( 'Y-m-d H:i:s' ),
		];
		// Nuovo insegnante o nuovo giorno: le lezioni passate non diventano registrabili.
		if ( (int) $after['teacher_id'] !== (int) $slot['teacher_id'] || (int) $after['weekday'] !== (int) $slot['weekday'] ) {
			$extra['assigned_from'] = $this->today();
		}
		$this->repo->update( $this->repo->slots_table(), (int) $slot['id'], $after + $extra );
		$this->log(
			'corsi.slot_updated',
			(int) $slot['id'],
			[
				'course_id' => $slot['course_id'],
				'before'    => $before,
				'after'     => $after,
			]
		);
	}

	/**
	 * @param string               $action Azione.
	 * @param int                  $id     Orario.
	 * @param array<string, mixed> $diff   Dettagli (solo id, giorni e orari).
	 */
	private function log( string $action, int $id, array $diff ): void {
		$this->audit->log(
			$action,
			[
				'module'        => 'corsi',
				'resource_type' => self::AUDIT_RESOURCE,
				'resource_id'   => (string) $id,
				'diff'          => $diff,
			]
		);
	}

	/**
	 * @param string $code Esito.
	 * @param int    $id   Record.
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
