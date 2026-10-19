<?php
/**
 * Catalogo dei corsi: creazione, modifica, archiviazione, duplicazione e sala per corso.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Corsi;

use Bracelli\Club\Core\Audit\AuditLogger;
use Bracelli\Club\Core\Permissions\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Regole:
 * - nulla si cancella: un corso si archivia (sparisce da calendario e lezioni, conserva orari e presenze) e si ripristina;
 * - un corso modificato dallo staff è marcato "manuale": l'importazione CSV non lo sovrascrive (conflitto nel rapporto);
 * - un corso creato qui ha una chiave "manuale-…" che non può arrivare da un file d'importazione;
 * - la duplicazione copia gli orari come "da confermare": non compaiono in calendario e non occupano sale finché
 *   la segreteria non li conferma (con il controllo delle sovrapposizioni).
 */
final class CatalogService {

	public const AUDIT_RESOURCE = 'corsi_course';
	public const MANUAL_PREFIX  = 'manuale-';

	/**
	 * @param CourseRepository $repo  Dati.
	 * @param AuditLogger      $audit Registro attività.
	 * @param RoomService      $rooms Sale.
	 * @param callable|null    $today Data odierna (sostituibile nei test).
	 */
	public function __construct( private CourseRepository $repo, private AuditLogger $audit, private RoomService $rooms, private $today = null ) {
	}

	/**
	 * @param int $actor Utente.
	 */
	public function can_manage( int $actor ): bool {
		return $actor > 0 && user_can( $actor, Capabilities::CORSI_MANAGE );
	}

	/**
	 * Corsi filtrati con i loro orari (solo per chi gestisce i corsi).
	 *
	 * @param int    $actor   Utente.
	 * @param string $section Disciplina (vuoto = tutte).
	 * @param string $status  active | archived | all.
	 * @param string $search  Testo cercato in nome, gruppo, disciplina e livello.
	 * @return array<int, array<string, mixed>>|null
	 */
	public function list( int $actor, string $section = '', string $status = 'active', string $search = '' ): ?array {
		if ( ! $this->can_manage( $actor ) ) {
			return null;
		}
		$slots = [];
		foreach ( $this->repo->slots() as $slot ) {
			$slots[ $slot['course_id'] ][] = $slot;
		}
		$needle = mb_strtolower( trim( $search ) );
		$out    = [];
		foreach ( $this->repo->courses() as $course ) {
			if ( '' !== $section && $section !== $course['section'] ) {
				continue;
			}
			if ( 'all' !== $status && $status !== $course['status'] ) {
				continue;
			}
			if ( '' !== $needle && ! str_contains( mb_strtolower( $course['name'] . ' ' . $course['group_label'] . ' ' . $course['section'] . ' ' . $course['level'] ), $needle ) ) {
				continue;
			}
			$out[] = $course + [ 'slots' => $slots[ $course['id'] ] ?? [] ];
		}
		return $out;
	}

	/**
	 * Nuovo corso.
	 *
	 * @param int                  $actor Utente.
	 * @param array<string, mixed> $input name, section, group_label, level, description, notes, color (non validati).
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public function create( int $actor, array $input ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return Result::fail( 'forbidden' );
		}
		[ $data, $errors ] = $this->validate( $input );
		if ( [] !== $errors ) {
			return Result::fail( 'invalid', $errors );
		}
		$id = $this->repo->insert(
			$this->repo->courses_table(),
			$data + [
				'ext_key'    => self::MANUAL_PREFIX . wp_generate_uuid4(),
				'status'     => 'active',
				'source'     => 'manual',
				'manual'     => 1,
				'created_by' => $actor,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			]
		);
		if ( 0 === $id ) {
			return Result::fail( 'db_error' );
		}
		$this->log( 'corsi.course_created', $id, $this->logged( $data ) );
		return Result::ok( 'course_created', $id );
	}

	/**
	 * Modifica i dati di un corso (gli orari si modificano a parte).
	 *
	 * @param int                  $actor Utente.
	 * @param int                  $id    Corso.
	 * @param array<string, mixed> $input Come create().
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public function update( int $actor, int $id, array $input ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return Result::fail( 'forbidden' );
		}
		$course = $this->repo->find_course( $id );
		if ( null === $course ) {
			return Result::fail( 'not_found' );
		}
		[ $data, $errors ] = $this->validate( $input );
		if ( [] !== $errors ) {
			return Result::fail( 'invalid', $errors );
		}
		$before = [];
		foreach ( array_keys( $data ) as $key ) {
			$before[ $key ] = (string) $course[ $key ];
		}
		if ( $before === $data ) {
			return Result::ok( 'unchanged', $id );
		}
		$this->repo->update(
			$this->repo->courses_table(),
			$id,
			$data + [
				'manual'     => 1,
				'updated_by' => $actor,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			]
		);
		$changed = array_keys( array_diff_assoc( $data, $before ) );
		$this->log(
			'corsi.course_updated',
			$id,
			[
				'before' => $this->logged( array_intersect_key( $before, array_flip( $changed ) ) ),
				'after'  => $this->logged( array_intersect_key( $data, array_flip( $changed ) ) ),
			]
		);
		return Result::ok( 'course_updated', $id );
	}

	/**
	 * Archivia o ripristina un corso. Il ripristino verifica che i suoi orari attivi non occupino sale già usate.
	 *
	 * @param int  $actor   Utente.
	 * @param int  $id      Corso.
	 * @param bool $archive true = archivia, false = ripristina.
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public function set_archived( int $actor, int $id, bool $archive ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return Result::fail( 'forbidden' );
		}
		$course = $this->repo->find_course( $id );
		if ( null === $course ) {
			return Result::fail( 'not_found' );
		}
		$status = $archive ? 'archived' : 'active';
		if ( $status === $course['status'] ) {
			return Result::ok( 'unchanged', $id );
		}
		return $this->rooms->locked(
			function () use ( $actor, $id, $archive, $status ): array {
				if ( ! $archive ) {
					$conflicts = $this->rooms->conflicts(
						$this->candidates(
							$this->repo->slots(
								[
									'course_id' => $id,
									'status'    => 'active',
								]
							)
						)
					);
					if ( [] !== $conflicts ) {
						return Result::fail( 'room_conflict', [ 'room_id' => RoomService::describe( $conflicts ) ] );
					}
				}
				$this->repo->update(
					$this->repo->courses_table(),
					$id,
					[
						'status'     => $status,
						'manual'     => 1,
						'updated_by' => $actor,
						'updated_at' => gmdate( 'Y-m-d H:i:s' ),
					]
				);
				$this->log( $archive ? 'corsi.course_archived' : 'corsi.course_restored', $id, [] );
				return Result::ok( $archive ? 'course_archived' : 'course_restored', $id );
			}
		);
	}

	/**
	 * Nuovo corso a partire da uno esistente. Gli orari (se richiesti) sono copiati "da confermare",
	 * senza insegnante: giorno, orari, sala e sede restano da verificare.
	 *
	 * @param int                  $actor  Utente.
	 * @param int                  $source Corso di partenza.
	 * @param array<string, mixed> $input  Come create(), più copy_slots.
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public function duplicate( int $actor, int $source, array $input ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return Result::fail( 'forbidden' );
		}
		if ( null === $this->repo->find_course( $source ) ) {
			return Result::fail( 'not_found' );
		}
		$created = $this->create( $actor, $input );
		if ( ! $created['ok'] ) {
			return $created;
		}
		$copied = 0;
		if ( ! empty( $input['copy_slots'] ) ) {
			$now = gmdate( 'Y-m-d H:i:s' );
			foreach ( $this->repo->slots( [ 'course_id' => $source ] ) as $slot ) {
				if ( 'cancelled' === $slot['status'] ) {
					continue;
				}
				$this->repo->insert(
					$this->repo->slots_table(),
					[
						'course_id'     => $created['id'],
						'weekday'       => $slot['weekday'],
						'start_time'    => $slot['start_time'],
						'end_time'      => $slot['end_time'],
						'teacher_id'    => 0,
						'room_id'       => $slot['room_id'],
						'location'      => $slot['location'],
						'status'        => 'draft',
						'manual'        => 1,
						'created_by'    => $actor,
						'created_at'    => $now,
						'assigned_from' => $this->today(),
					]
				);
				++$copied;
			}
		}
		$this->log(
			'corsi.course_duplicated',
			$created['id'],
			[
				'source_id'   => $source,
				'draft_slots' => $copied,
			]
		);
		return Result::ok( 'course_duplicated', $created['id'] );
	}

	/**
	 * Assegna una sala a tutti gli orari attivi e da confermare di un corso (0 = togli la sala).
	 * Tutto o niente: se anche un solo orario attivo si sovrapporrebbe, non cambia nulla.
	 *
	 * @param int  $actor      Utente.
	 * @param int  $course_id  Corso.
	 * @param int  $room_id    Sala (già scelta dall'elenco).
	 * @param bool $only_empty Solo gli orari che non hanno ancora una sala.
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public function assign_room( int $actor, int $course_id, int $room_id, bool $only_empty ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return Result::fail( 'forbidden' );
		}
		if ( null === $this->repo->find_course( $course_id ) ) {
			return Result::fail( 'not_found' );
		}
		[ $room, $error ] = $this->rooms->validate_choice( $room_id );
		if ( '' !== $error ) {
			return Result::fail( 'invalid', [ 'room_id' => $error ] );
		}
		return $this->rooms->locked(
			function () use ( $actor, $course_id, $room, $only_empty ): array {
				$targets = [];
				foreach ( $this->repo->slots( [ 'course_id' => $course_id ] ) as $slot ) {
					if ( in_array( $slot['status'], [ 'active', 'draft' ], true ) && $slot['room_id'] !== $room && ( ! $only_empty || 0 === $slot['room_id'] ) ) {
						$targets[] = $slot;
					}
				}
				if ( [] === $targets ) {
					return Result::ok( 'unchanged', $course_id );
				}
				$course    = $this->repo->find_course( $course_id );
				$active    = array_filter( $targets, static fn( array $s ): bool => 'active' === $s['status'] );
				$conflicts = 'active' === ( $course['status'] ?? '' ) ? $this->rooms->conflicts( $this->candidates( $active, $room ) ) : [];
				if ( [] !== $conflicts ) {
					return Result::fail( 'room_conflict', [ 'room_id' => RoomService::describe( $conflicts ) ] );
				}
				$now = gmdate( 'Y-m-d H:i:s' );
				foreach ( $targets as $slot ) {
					$this->repo->update(
						$this->repo->slots_table(),
						$slot['id'],
						[
							'room_id'    => $room,
							'manual'     => 1,
							'updated_by' => $actor,
							'updated_at' => $now,
						]
					);
					$this->audit->log(
						'corsi.slot_updated',
						[
							'module'        => 'corsi',
							'resource_type' => CourseService::AUDIT_RESOURCE,
							'resource_id'   => (string) $slot['id'],
							'diff'          => [
								'course_id' => $course_id,
								'before'    => [ 'room_id' => $slot['room_id'] ],
								'after'     => [ 'room_id' => $room ],
							],
						]
					);
				}
				$this->log(
					'corsi.course_room_assigned',
					$course_id,
					[
						'room_id' => $room,
						'slots'   => count( $targets ),
					]
				);
				return Result::ok( 'room_assigned', $course_id );
			}
		);
	}

	/**
	 * Conferma un orario "da confermare" (diventa attivo), con il controllo delle sovrapposizioni.
	 *
	 * @param int $actor Utente.
	 * @param int $id    Orario.
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public function confirm_slot( int $actor, int $id ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return Result::fail( 'forbidden' );
		}
		$slot = $this->repo->find_slot( $id );
		if ( null === $slot ) {
			return Result::fail( 'not_found' );
		}
		if ( 'draft' !== $slot['status'] ) {
			return Result::fail( 'not_draft' );
		}
		return $this->rooms->locked(
			function () use ( $actor, $slot, $id ): array {
				$conflicts = 'active' === $slot['course_status'] ? $this->rooms->conflicts( $this->candidates( [ $slot ] ) ) : [];
				if ( [] !== $conflicts ) {
					return Result::fail( 'room_conflict', [ 'room_id' => RoomService::describe( $conflicts ) ] );
				}
				$this->repo->update(
					$this->repo->slots_table(),
					$id,
					[
						'status'        => 'active',
						'updated_by'    => $actor,
						'updated_at'    => gmdate( 'Y-m-d H:i:s' ),
						'assigned_from' => $this->today(),
					]
				);
				$this->audit->log(
					'corsi.slot_confirmed',
					[
						'module'        => 'corsi',
						'resource_type' => CourseService::AUDIT_RESOURCE,
						'resource_id'   => (string) $id,
						'diff'          => [ 'course_id' => $slot['course_id'] ],
					]
				);
				return Result::ok( 'slot_confirmed', $id );
			}
		);
	}

	/**
	 * Storico di un corso (solo per chi gestisce i corsi).
	 *
	 * @param int $actor Utente.
	 * @param int $id    Corso.
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
	 * Colori proposti per le discipline (contrasto adeguato con testo scuro o chiaro).
	 *
	 * @return array<string, string> esadecimale => nome
	 */
	public static function colors(): array {
		return [
			'#c8962b' => __( 'Oro', 'bracelli-club' ),
			'#d64545' => __( 'Rosso', 'bracelli-club' ),
			'#e07b22' => __( 'Arancione', 'bracelli-club' ),
			'#2f9e5b' => __( 'Verde', 'bracelli-club' ),
			'#1f8a99' => __( 'Ottanio', 'bracelli-club' ),
			'#2f6fd6' => __( 'Blu', 'bracelli-club' ),
			'#7a4fd1' => __( 'Viola', 'bracelli-club' ),
			'#c2408f' => __( 'Fucsia', 'bracelli-club' ),
			'#6b7280' => __( 'Grigio', 'bracelli-club' ),
		];
	}

	/**
	 * @param array<string, mixed> $input Dati grezzi.
	 * @return array{0: array{name: string, section: string, group_label: string, level: string, description: string, notes: string, color: string}, 1: array<string, string>}
	 */
	private function validate( array $input ): array {
		$text   = static fn( string $k, int $max ): string => is_string( $input[ $k ] ?? null ) ? mb_substr( trim( sanitize_text_field( $input[ $k ] ) ), 0, $max ) : '';
		$area   = static fn( string $k, int $max ): string => is_string( $input[ $k ] ?? null ) ? mb_substr( trim( sanitize_textarea_field( $input[ $k ] ) ), 0, $max ) : '';
		$data   = [
			'name'        => $text( 'name', 190 ),
			'section'     => $text( 'section', 120 ),
			'group_label' => $text( 'group_label', 190 ),
			'level'       => $text( 'level', 120 ),
			'description' => $area( 'description', 2000 ),
			'notes'       => $area( 'notes', 1000 ),
			'color'       => is_string( $input['color'] ?? null ) ? strtolower( trim( $input['color'] ) ) : '',
		];
		$errors = [];
		if ( '' === $data['name'] ) {
			$errors['name'] = __( 'Scrivi il nome del corso.', 'bracelli-club' );
		}
		if ( '' === $data['section'] ) {
			$errors['section'] = __( 'Indica la disciplina.', 'bracelli-club' );
		}
		if ( '' !== $data['color'] && ! isset( self::colors()[ $data['color'] ] ) ) {
			$errors['color'] = __( 'Scegli un colore dell\'elenco.', 'bracelli-club' );
		}
		return [ $data, $errors ];
	}

	/**
	 * Candidati per il controllo delle sale.
	 *
	 * @param array<int, array<string, mixed>> $slots Orari.
	 * @param int|null                         $room  Sala da usare al posto di quella attuale.
	 * @return array<int, array<string, mixed>>
	 */
	private function candidates( array $slots, ?int $room = null ): array {
		$out = [];
		foreach ( $slots as $slot ) {
			$out[] = [
				'id'          => (int) $slot['id'],
				'room_id'     => $room ?? (int) $slot['room_id'],
				'weekday'     => (int) $slot['weekday'],
				'start_time'  => (string) $slot['start_time'],
				'end_time'    => (string) $slot['end_time'],
				'course_name' => (string) ( $slot['course_name'] ?? '' ),
				'group_label' => (string) ( $slot['group_label'] ?? '' ),
			];
		}
		return $out;
	}

	/**
	 * Nel registro attività: niente testi lunghi, solo i campi brevi e la lunghezza di descrizione e note.
	 *
	 * @param array<string, string> $data Dati del corso.
	 * @return array<string, mixed>
	 */
	private function logged( array $data ): array {
		foreach ( [ 'description', 'notes' ] as $long ) {
			if ( isset( $data[ $long ] ) ) {
				$data[ $long ] = sprintf( '(%d caratteri)', mb_strlen( $data[ $long ] ) );
			}
		}
		return $data;
	}

	/**
	 * Data odierna del sito.
	 */
	private function today(): string {
		return null === $this->today ? (string) wp_date( 'Y-m-d' ) : (string) ( $this->today )();
	}

	/**
	 * @param string               $action Azione.
	 * @param int                  $id     Corso.
	 * @param array<string, mixed> $diff   Dettagli.
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
}
