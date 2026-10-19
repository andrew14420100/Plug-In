<?php
/**
 * Sale e controllo delle sovrapposizioni.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Corsi;

use Bracelli\Club\Core\Audit\AuditLogger;
use Bracelli\Club\Core\Permissions\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * L'elenco delle sale lo crea la segreteria: il plugin non inventa nomi e non assegna sale da solo.
 * Le sale non si eliminano: si archiviano (solo se nessun orario attivo le usa).
 *
 * Sovrapposizione: due orari attivi (o da confermare, quando si confermano) di corsi attivi nella stessa sala,
 * nello stesso giorno, con fasce che si intersecano (un orario che finisce alle 18:00 non si sovrappone
 * a uno che inizia alle 18:00). Il controllo avviene sempre sul server, dentro un lock, prima di salvare.
 */
final class RoomService {

	public const AUDIT_RESOURCE = 'corsi_room';

	/**
	 * @param CourseRepository $repo  Dati.
	 * @param AuditLogger      $audit Registro attività.
	 */
	public function __construct( private CourseRepository $repo, private AuditLogger $audit ) {
	}

	/**
	 * @param int $actor Utente.
	 */
	public function can_manage( int $actor ): bool {
		return $actor > 0 && user_can( $actor, Capabilities::CORSI_MANAGE );
	}

	/**
	 * Sale con il numero di orari attivi (solo per chi gestisce i corsi).
	 *
	 * @param int $actor Utente.
	 * @return array<int, array<string, mixed>>|null
	 */
	public function rooms( int $actor ): ?array {
		if ( ! $this->can_manage( $actor ) ) {
			return null;
		}
		$used = [];
		foreach ( $this->repo->slots( [ 'status' => 'active' ] ) as $slot ) {
			if ( $slot['room_id'] > 0 && 'active' === $slot['course_status'] ) {
				$used[ $slot['room_id'] ] = ( $used[ $slot['room_id'] ] ?? 0 ) + 1;
			}
		}
		return array_map( static fn( array $r ): array => $r + [ 'active_slots' => $used[ $r['id'] ] ?? 0 ], $this->repo->rooms() );
	}

	/**
	 * Sale attive da proporre nei moduli: id => nome.
	 *
	 * @return array<int, string>
	 */
	public function choices(): array {
		$out = [];
		foreach ( $this->repo->rooms() as $room ) {
			if ( 'active' === $room['status'] ) {
				$out[ $room['id'] ] = (string) $room['name'];
			}
		}
		return $out;
	}

	/**
	 * @param int $actor Utente.
	 * @param int $id    Sala.
	 * @return array<string, mixed>|null
	 */
	public function get( int $actor, int $id ): ?array {
		return $this->can_manage( $actor ) && $id > 0 ? $this->repo->find_room( $id ) : null;
	}

	/**
	 * Nuova sala.
	 *
	 * @param int                  $actor Utente.
	 * @param array<string, mixed> $input name, notes (non validati).
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public function create( int $actor, array $input ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return Result::fail( 'forbidden' );
		}
		[ $data, $errors ] = $this->validate( $input, 0 );
		if ( [] !== $errors ) {
			return Result::fail( 'invalid', $errors );
		}
		$id = $this->repo->insert(
			$this->repo->rooms_table(),
			$data + [
				'status'     => 'active',
				'created_by' => $actor,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			]
		);
		if ( 0 === $id ) {
			return Result::fail( 'db_error' );
		}
		$this->log( 'corsi.room_created', $id, $data );
		return Result::ok( 'room_created', $id );
	}

	/**
	 * Modifica nome e note di una sala.
	 *
	 * @param int                  $actor Utente.
	 * @param int                  $id    Sala.
	 * @param array<string, mixed> $input name, notes (non validati).
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public function update( int $actor, int $id, array $input ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return Result::fail( 'forbidden' );
		}
		$room = $this->repo->find_room( $id );
		if ( null === $room ) {
			return Result::fail( 'not_found' );
		}
		[ $data, $errors ] = $this->validate( $input, $id );
		if ( [] !== $errors ) {
			return Result::fail( 'invalid', $errors );
		}
		$before = [
			'name'  => (string) $room['name'],
			'notes' => (string) $room['notes'],
		];
		if ( $before === $data ) {
			return Result::ok( 'unchanged', $id );
		}
		$this->repo->update(
			$this->repo->rooms_table(),
			$id,
			$data + [
				'updated_by' => $actor,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			]
		);
		$this->log(
			'corsi.room_updated',
			$id,
			[
				'before' => $before,
				'after'  => $data,
			]
		);
		return Result::ok( 'room_updated', $id );
	}

	/**
	 * Archivia o ripristina una sala. Si archivia solo se nessun orario attivo di un corso attivo la usa.
	 *
	 * @param int  $actor   Utente.
	 * @param int  $id      Sala.
	 * @param bool $archive true = archivia, false = ripristina.
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public function set_archived( int $actor, int $id, bool $archive ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return Result::fail( 'forbidden' );
		}
		$room = $this->repo->find_room( $id );
		if ( null === $room ) {
			return Result::fail( 'not_found' );
		}
		$status = $archive ? 'archived' : 'active';
		if ( $status === $room['status'] ) {
			return Result::ok( 'unchanged', $id );
		}
		if ( $archive ) {
			foreach ( $this->repo->slots( [ 'room_id' => $id ] ) as $slot ) {
				if ( in_array( $slot['status'], [ 'active', 'draft' ], true ) && 'archived' !== $slot['course_status'] ) {
					return Result::fail( 'room_in_use' );
				}
			}
		}
		$this->repo->update(
			$this->repo->rooms_table(),
			$id,
			[
				'status'     => $status,
				'updated_by' => $actor,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			]
		);
		$this->log( $archive ? 'corsi.room_archived' : 'corsi.room_restored', $id, [ 'name' => (string) $room['name'] ] );
		return Result::ok( $archive ? 'room_archived' : 'room_restored', $id );
	}

	/**
	 * Orari attivi che occuperebbero la stessa sala nello stesso momento.
	 *
	 * @param array<int, array<string, mixed>> $candidates Orari da salvare con id (0 se nuovo), room_id, weekday, start_time,
	 *        end_time; vengono confrontati con il database e fra loro.
	 * @return array<int, array<string, mixed>> Orari in conflitto (dal database o fra i candidati).
	 */
	public function conflicts( array $candidates ): array {
		$ignore = [];
		foreach ( $candidates as $c ) {
			if ( $c['id'] > 0 ) {
				$ignore[ $c['id'] ] = true;
			}
		}
		$found = [];
		foreach ( $candidates as $i => $c ) {
			if ( $c['room_id'] <= 0 ) {
				continue;
			}
			foreach ( $this->repo->slots(
				[
					'room_id' => $c['room_id'],
					'weekday' => $c['weekday'],
					'status'  => 'active',
				]
			) as $other ) {
				if ( ! isset( $ignore[ $other['id'] ] ) && 'active' === $other['course_status'] && self::overlap( $c, $other ) ) {
					$found[ $other['id'] ] = $other;
				}
			}
			foreach ( $candidates as $j => $d ) {
				if ( $j > $i && $d['room_id'] === $c['room_id'] && $d['weekday'] === $c['weekday'] && self::overlap( $c, $d ) ) {
					$found[ 'c' . $i ] = $c;
					$found[ 'c' . $j ] = $d;
				}
			}
		}
		return array_values( $found );
	}

	/**
	 * Esegue $work con un lock sul database, così due salvataggi contemporanei non superano entrambi il controllo.
	 * Con SQLite (WordPress Playground) le scritture sono già serializzate e il lock non serve.
	 *
	 * @param callable $work Lavoro da proteggere.
	 * @return mixed Risultato di $work, oppure un esito 'busy' se il lock non è disponibile.
	 */
	public function locked( callable $work ) {
		$db = $this->repo->db();
		if ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) {
			return $work();
		}
		$lock = substr( 'bracelli_corsi_rooms_' . $db->prefix, 0, 64 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- lock applicativo, non cacheabile.
		if ( '1' !== (string) $db->get_var( $db->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
			return Result::fail( 'busy' );
		}
		try {
			return $work();
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- rilascio del lock applicativo.
			$db->query( $db->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * Valida la sala scelta per un orario (0 = nessuna sala).
	 *
	 * @param mixed $value Valore inviato.
	 * @param int   $keep  Sala attuale dell'orario: resta valida anche se archiviata.
	 * @return array{0: int, 1: string} Sala ed eventuale errore.
	 */
	public function validate_choice( $value, int $keep = 0 ): array {
		$id = is_scalar( $value ) ? absint( $value ) : 0;
		if ( 0 === $id ) {
			return [ 0, '' ];
		}
		$room = $this->repo->find_room( $id );
		if ( null === $room || ( 'active' !== $room['status'] && $id !== $keep ) ) {
			return [ 0, __( 'Scegli una sala dell\'elenco.', 'bracelli-club' ) ];
		}
		return [ $id, '' ];
	}

	/**
	 * Messaggio leggibile con gli orari in conflitto.
	 *
	 * @param array<int, array<string, mixed>> $conflicts Orari in conflitto.
	 */
	public static function describe( array $conflicts ): string {
		$days  = CourseService::weekdays();
		$parts = [];
		foreach ( array_slice( $conflicts, 0, 5 ) as $c ) {
			$name    = (string) ( $c['course_name'] ?? __( 'questo corso', 'bracelli-club' ) );
			$group   = (string) ( $c['group_label'] ?? '' );
			$parts[] = $name . ( '' !== $group ? ' — ' . $group : '' ) . ' (' . ( $days[ (int) $c['weekday'] ] ?? '' ) . ' ' . substr( (string) $c['start_time'], 0, 5 ) . '–' . substr( (string) $c['end_time'], 0, 5 ) . ')';
		}
		/* translators: %s: elenco degli orari che occupano già la sala. */
		return sprintf( __( 'Sala già occupata in quella fascia: %s.', 'bracelli-club' ), implode( '; ', $parts ) );
	}

	/**
	 * Intersezione tra due fasce dello stesso giorno (gli estremi che si toccano non contano).
	 *
	 * @param array<string, mixed> $a Orario.
	 * @param array<string, mixed> $b Orario.
	 */
	public static function overlap( array $a, array $b ): bool {
		return (string) $a['start_time'] < (string) $b['end_time'] && (string) $b['start_time'] < (string) $a['end_time'];
	}

	/**
	 * @param array<string, mixed> $input Dati grezzi.
	 * @param int                  $id    Sala modificata (0 se nuova).
	 * @return array{0: array{name: string, notes: string}, 1: array<string, string>}
	 */
	private function validate( array $input, int $id ): array {
		$name   = is_string( $input['name'] ?? null ) ? trim( sanitize_text_field( $input['name'] ) ) : '';
		$notes  = is_string( $input['notes'] ?? null ) ? trim( sanitize_text_field( $input['notes'] ) ) : '';
		$errors = [];
		if ( '' === $name ) {
			$errors['name'] = __( 'Scrivi il nome della sala.', 'bracelli-club' );
		} elseif ( mb_strlen( $name ) > 120 ) {
			$errors['name'] = __( 'Il nome può avere al massimo 120 caratteri.', 'bracelli-club' );
		} else {
			foreach ( $this->repo->rooms() as $room ) {
				if ( $room['id'] !== $id && mb_strtolower( (string) $room['name'] ) === mb_strtolower( $name ) ) {
					$errors['name'] = __( 'Esiste già una sala con questo nome (anche tra quelle archiviate).', 'bracelli-club' );
					break;
				}
			}
		}
		return [
			[
				'name'  => $name,
				'notes' => mb_substr( $notes, 0, 255 ),
			],
			$errors,
		];
	}

	/**
	 * @param string               $action Azione.
	 * @param int                  $id     Sala.
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
