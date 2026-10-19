<?php
/**
 * Importazione ripetibile di corsi e orari da file CSV.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Corsi;

use Bracelli\Club\Core\Audit\AuditLogger;

defined( 'ABSPATH' ) || exit;

/**
 * Formato: una riga per orario settimanale, colonne (intestazione obbligatoria, separatore ";" "," o tabulazione):
 * chiave; sezione; corso; gruppo; giorno; inizio; fine; insegnante; sede; note.
 * - "chiave" identifica il corso in modo stabile: rieseguire l'importazione aggiorna, non duplica;
 * - una riga senza giorno e orari registra il corso senza orari (segnalato come da completare);
 * - "insegnante" è solo il nome indicato nei dati: NON collega alcun account (lo fa la segreteria);
 * - gli orari modificati a mano dallo staff non vengono sovrascritti (conflitto segnalato);
 * - nulla viene cancellato: orari presenti nel database ma assenti dal file restano invariati e sono segnalati.
 */
final class CourseImporter {

	public const MAX_ROWS = 2000;

	private const COLUMNS  = [ 'chiave', 'sezione', 'corso', 'gruppo', 'giorno', 'inizio', 'fine', 'insegnante', 'sede', 'note' ];
	private const REQUIRED = [ 'chiave', 'sezione', 'corso', 'giorno', 'inizio', 'fine' ];

	/**
	 * @param CourseRepository $repo  Dati.
	 * @param AuditLogger      $audit Registro attività.
	 */
	public function __construct( private CourseRepository $repo, private AuditLogger $audit ) {
	}

	/**
	 * Legge il CSV.
	 *
	 * @param string $content Contenuto del file.
	 * @return array{rows: array<int, array<string, string>>, errors: string[]}
	 */
	public function parse( string $content ): array {
		$content = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $content );
		if ( ! mb_check_encoding( $content, 'UTF-8' ) ) {
			$content = (string) mb_convert_encoding( $content, 'UTF-8', 'Windows-1252' );
		}
		$lines = preg_split( '/\r\n|\r|\n/', $content );
		$lines = false === $lines ? [] : $lines;
		$first = (string) ( $lines[0] ?? '' );
		$delim = ';';
		foreach ( [ ';', "\t", ',' ] as $candidate ) {
			if ( substr_count( $first, $candidate ) >= 5 ) {
				$delim = $candidate;
				break;
			}
		}
		$header = array_map( static fn( $h ): string => strtolower( trim( (string) $h ) ), str_getcsv( $first, $delim, '"', '' ) );
		$errors = [];
		foreach ( self::REQUIRED as $col ) {
			if ( ! in_array( $col, $header, true ) ) {
				$errors[] = sprintf( 'Colonna obbligatoria mancante: %s', $col );
			}
		}
		if ( [] !== $errors ) {
			return [
				'rows'   => [],
				'errors' => $errors,
			];
		}
		$rows = [];
		foreach ( array_slice( $lines, 1, self::MAX_ROWS + 1, true ) as $index => $line ) {
			if ( '' === trim( (string) $line ) ) {
				continue;
			}
			if ( count( $rows ) >= self::MAX_ROWS ) {
				$errors[] = sprintf( 'Il file supera %d righe: le righe successive non sono state lette.', self::MAX_ROWS );
				break;
			}
			$cells = str_getcsv( (string) $line, $delim, '"', '' );
			$row   = [ 'line' => (string) ( $index + 1 ) ];
			foreach ( self::COLUMNS as $col ) {
				$pos         = array_search( $col, $header, true );
				$row[ $col ] = false === $pos ? '' : sanitize_text_field( (string) ( $cells[ $pos ] ?? '' ) );
			}
			$rows[] = $row;
		}
		return [
			'rows'   => $rows,
			'errors' => $errors,
		];
	}

	/**
	 * Confronta le righe con il database e prepara il piano (nessuna scrittura).
	 *
	 * @param array<int, array<string, string>> $rows Righe lette da parse().
	 * @return array{courses: array<int, array<string, mixed>>, slots: array<int, array<string, mixed>>, anomalies: array<int, array<string, string>>, untouched: array<int, array<string, mixed>>, counts: array<string, int>}
	 */
	public function plan( array $rows ): array {
		$courses   = [];
		$slots     = [];
		$anomalies = [];
		$ordinals  = [];
		$seen      = [];
		foreach ( $rows as $row ) {
			$key = sanitize_title( $row['chiave'] );
			if ( '' === $key || '' === $row['corso'] ) {
				$anomalies[] = $this->anomaly( $row, 'Chiave o nome del corso mancante: riga saltata.' );
				continue;
			}
			$course = [
				'ext_key'     => mb_substr( $key, 0, 100 ),
				'section'     => mb_substr( $row['sezione'], 0, 120 ),
				'name'        => mb_substr( $row['corso'], 0, 190 ),
				'group_label' => mb_substr( $row['gruppo'], 0, 190 ),
				'notes'       => [],
			];
			if ( isset( $courses[ $key ] ) ) {
				foreach ( [ 'section', 'name', 'group_label' ] as $field ) {
					if ( $courses[ $key ][ $field ] !== $course[ $field ] ) {
						$anomalies[] = $this->anomaly( $row, sprintf( 'Il corso "%s" ha valori diversi nella colonna %s rispetto alle righe precedenti: usati quelli della prima riga.', $key, $field ) );
					}
				}
			} else {
				$courses[ $key ] = $course;
			}
			if ( '' !== $row['note'] && ! in_array( $row['note'], $courses[ $key ]['notes'], true ) ) {
				$courses[ $key ]['notes'][] = $row['note'];
			}

			$has_time = '' !== $row['inizio'] || '' !== $row['fine'];
			if ( '' === $row['giorno'] && ! $has_time ) {
				$anomalies[] = $this->anomaly( $row, sprintf( 'Corso "%s" senza giorno né orario: registrato senza orari, da completare.', $this->label( $row ) ) );
				continue;
			}
			$weekday = self::weekday( $row['giorno'] );
			$start   = self::time( $row['inizio'] );
			$end     = self::time( $row['fine'] );
			if ( 0 === $weekday ) {
				$anomalies[] = $this->anomaly( $row, sprintf( 'Giorno non riconosciuto "%s" per "%s": orario non importato.', $row['giorno'], $this->label( $row ) ) );
				continue;
			}
			if ( '' === $start || '' === $end ) {
				$anomalies[] = $this->anomaly( $row, sprintf( '"%s", %s: orario mancante o non valido, orario non importato.', $this->label( $row ), CourseService::weekdays()[ $weekday ] ) );
				continue;
			}
			if ( $end <= $start ) {
				$anomalies[] = $this->anomaly( $row, sprintf( '"%s", %s: la fine (%s) non è successiva all\'inizio (%s), orario non importato.', $this->label( $row ), CourseService::weekdays()[ $weekday ], substr( $end, 0, 5 ), substr( $start, 0, 5 ) ) );
				continue;
			}
			$signature = $key . '|' . $weekday . '|' . $start . '|' . $end;
			if ( isset( $seen[ $signature ] ) ) {
				$anomalies[] = $this->anomaly( $row, sprintf( '"%s": orario ripetuto nel file (%s %s-%s), riga saltata.', $this->label( $row ), CourseService::weekdays()[ $weekday ], substr( $start, 0, 5 ), substr( $end, 0, 5 ) ) );
				continue;
			}
			$seen[ $signature ]           = true;
			$ordinals[ $key ][ $weekday ] = ( $ordinals[ $key ][ $weekday ] ?? 0 ) + 1;
			$slots[]                      = [
				'ext_key'       => mb_substr( $key, 0, 100 ) . '|' . $weekday . '|' . $ordinals[ $key ][ $weekday ],
				'course_key'    => $key,
				'weekday'       => $weekday,
				'start_time'    => $start,
				'end_time'      => $end,
				'teacher_label' => mb_substr( $row['insegnante'], 0, 120 ),
				'location'      => mb_substr( $row['sede'], 0, 120 ),
				'line'          => $row['line'],
			];
		}

		$existing_courses = $this->repo->courses_by_key();
		$existing_slots   = $this->repo->slots_by_key();
		$counts           = array_fill_keys( [ 'courses_create', 'courses_update', 'courses_unchanged', 'slots_create', 'slots_update', 'slots_unchanged', 'slots_conflict', 'untouched', 'anomalies' ], 0 );

		$course_plan = [];
		foreach ( $courses as $key => $course ) {
			$course['notes'] = implode( ' · ', $course['notes'] );
			$current         = $existing_courses[ $key ] ?? null;
			if ( null === $current ) {
				$action = 'create';
			} else {
				$action = ( $current['section'] === $course['section'] && $current['name'] === $course['name'] && $current['group_label'] === $course['group_label'] && (string) $current['notes'] === $course['notes'] && 'active' === $current['status'] ) ? 'unchanged' : 'update';
			}
			++$counts[ 'courses_' . $action ];
			$course_plan[] = $course + [ 'action' => $action ];
		}

		$slot_plan = [];
		$in_file   = [];
		foreach ( $slots as $slot ) {
			$in_file[ $slot['ext_key'] ] = true;
			$current                     = $existing_slots[ $slot['ext_key'] ] ?? null;
			$reason                      = '';
			if ( null === $current ) {
				$action = 'create';
			} else {
				$same   = (int) $current['weekday'] === $slot['weekday'] && (string) $current['start_time'] === $slot['start_time'] && (string) $current['end_time'] === $slot['end_time']
					&& (string) $current['teacher_label'] === $slot['teacher_label'] && (string) $current['location'] === $slot['location'] && 'active' === $current['status'];
				$action = $same ? 'unchanged' : ( 1 === (int) $current['manual'] ? 'conflict' : 'update' );
				if ( 'conflict' === $action ) {
					$reason = 'Orario modificato dallo staff: non sovrascritto.';
				}
			}
			++$counts[ 'slots_' . $action ];
			$slot_plan[] = $slot + [
				'action' => $action,
				'reason' => $reason,
			];
		}

		$untouched = [];
		foreach ( $existing_slots as $ext_key => $slot ) {
			$course_key = (string) strstr( $ext_key, '|', true );
			if ( isset( $courses[ $course_key ] ) && ! isset( $in_file[ $ext_key ] ) && 'active' === $slot['status'] ) {
				$untouched[] = $slot;
			}
		}
		$counts['untouched'] = count( $untouched );
		$counts['anomalies'] = count( $anomalies );

		return [
			'courses'   => $course_plan,
			'slots'     => $slot_plan,
			'anomalies' => $anomalies,
			'untouched' => $untouched,
			'counts'    => $counts,
		];
	}

	/**
	 * Applica il piano in una transazione. Non cancella nulla e non sovrascrive gli orari modificati a mano.
	 *
	 * @param array<int, array<string, string>> $rows  Righe lette da parse().
	 * @param int                               $actor Utente che importa.
	 * @param string                            $label Nome del file (solo per il registro).
	 * @return array{ok: bool, plan: array<string, mixed>, error: string}
	 */
	public function apply( array $rows, int $actor, string $label = '' ): array {
		$plan = $this->plan( $rows );
		$db   = $this->repo->db();
		$now  = gmdate( 'Y-m-d H:i:s' );
		/**
		 * Usa una transazione per applicare l'importazione "tutto o niente" (disattivabile solo nei test automatici,
		 * che isolano già ogni prova in una transazione propria).
		 *
		 * @param bool $use Predefinito true.
		 */
		$tx = (bool) apply_filters( 'bracelli_club_corsi_import_transaction', true );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- transazione sulle tabelle del plugin.
		if ( $tx ) {
			$db->query( 'START TRANSACTION' );
		}
		try {
			$ids = [];
			foreach ( $this->repo->courses_by_key() as $key => $course ) {
				$ids[ $key ] = (int) $course['id'];
			}
			foreach ( $plan['courses'] as $course ) {
				$data = [
					'section'     => $course['section'],
					'name'        => $course['name'],
					'group_label' => $course['group_label'],
					'notes'       => $course['notes'],
					'status'      => 'active',
					'source'      => 'import',
				];
				if ( 'create' === $course['action'] ) {
					$ids[ $course['ext_key'] ] = $this->repo->insert(
						$this->repo->courses_table(),
						$data + [
							'ext_key'    => $course['ext_key'],
							'created_at' => $now,
						]
					);
					if ( 0 === $ids[ $course['ext_key'] ] ) {
						throw new \RuntimeException( 'Inserimento corso non riuscito: ' . $course['ext_key'] );
					}
				} elseif ( 'update' === $course['action'] ) {
					$this->repo->update( $this->repo->courses_table(), $ids[ $course['ext_key'] ], $data + [ 'updated_at' => $now ] );
				}
			}
			$existing = $this->repo->slots_by_key();
			foreach ( $plan['slots'] as $slot ) {
				$data = [
					'weekday'       => $slot['weekday'],
					'start_time'    => $slot['start_time'],
					'end_time'      => $slot['end_time'],
					'teacher_label' => $slot['teacher_label'],
					'location'      => $slot['location'],
					'status'        => 'active',
				];
				if ( 'create' === $slot['action'] ) {
					$id = $this->repo->insert(
						$this->repo->slots_table(),
						$data + [
							'ext_key'       => $slot['ext_key'],
							'course_id'     => $ids[ $slot['course_key'] ],
							'created_by'    => $actor,
							'created_at'    => $now,
							'assigned_from' => current_time( 'Y-m-d' ),
						]
					);
					if ( 0 === $id ) {
						throw new \RuntimeException( 'Inserimento orario non riuscito: ' . $slot['ext_key'] );
					}
				} elseif ( 'update' === $slot['action'] ) {
					$this->repo->update(
						$this->repo->slots_table(),
						(int) $existing[ $slot['ext_key'] ]['id'],
						$data + [
							'updated_by' => $actor,
							'updated_at' => $now,
						]
					);
				}
			}
			if ( $tx ) {
				$db->query( 'COMMIT' );
			}
		} catch ( \Throwable $e ) {
			if ( $tx ) {
				$db->query( 'ROLLBACK' );
			}
			return [
				'ok'    => false,
				'plan'  => $plan,
				'error' => $e->getMessage(),
			];
		}
		// phpcs:enable
		$this->audit->log(
			'corsi.import_applied',
			[
				'module'        => 'corsi',
				'resource_type' => 'corsi_import',
				'resource_id'   => mb_substr( sanitize_file_name( $label ), 0, 100 ),
				'diff'          => $plan['counts'],
				'actor_id'      => $actor,
			]
		);
		return [
			'ok'    => true,
			'plan'  => $plan,
			'error' => '',
		];
	}

	/**
	 * Giorno della settimana da testo italiano (1 = lunedì) oppure 0.
	 *
	 * @param string $text Testo.
	 */
	public static function weekday( string $text ): int {
		$t   = strtolower( remove_accents( trim( $text ) ) );
		$map = [
			'lun' => 1,
			'mar' => 2,
			'mer' => 3,
			'gio' => 4,
			'ven' => 5,
			'sab' => 6,
			'dom' => 7,
		];
		$key = substr( $t, 0, 3 );
		$day = $map[ $key ] ?? 0;
		$all = [ 'lunedi', 'martedi', 'mercoledi', 'giovedi', 'venerdi', 'sabato', 'domenica' ];
		return 0 !== $day && ( $t === $key || $t === $all[ $day - 1 ] ) ? $day : 0;
	}

	/**
	 * Orario "H:MM", "HH:MM" o "HH.MM" in formato "HH:MM:00", oppure ''.
	 *
	 * @param string $text Testo.
	 */
	public static function time( string $text ): string {
		if ( ! preg_match( '/^([01]?\d|2[0-3])[:.]([0-5]\d)$/', trim( $text ), $m ) ) {
			return '';
		}
		return sprintf( '%02d:%s:00', (int) $m[1], $m[2] );
	}

	/**
	 * @param array<string, string> $row     Riga.
	 * @param string                $message Messaggio.
	 * @return array<string, string>
	 */
	private function anomaly( array $row, string $message ): array {
		return [
			'line'    => $row['line'],
			'key'     => $row['chiave'],
			'message' => $message,
		];
	}

	/**
	 * @param array<string, string> $row Riga.
	 */
	private function label( array $row ): string {
		return trim( $row['corso'] . ( '' !== $row['gruppo'] ? ' (' . $row['gruppo'] . ')' : '' ) );
	}
}
