<?php
/**
 * Accesso ai dati dell'archivio soci.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Soci;

defined( 'ABSPATH' ) || exit;

/**
 * Query parametrizzate e scritture non distruttive per l'archivio soci.
 */
final class MemberRepository {

	public function __construct( private \wpdb $db ) {
	}

	public function table(): string {
		return $this->db->prefix . 'bc_members';
	}

	/**
	 * Ricerca anagrafica con paginazione.
	 *
	 * @param string $search Ricerca libera.
	 * @param string $status Stato o stringa vuota.
	 * @param int    $page Pagina.
	 * @param int    $per_page Righe per pagina.
	 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
	 */
	public function search( string $search = '', string $status = '', int $page = 1, int $per_page = 25 ): array {
		$page     = max( 1, $page );
		$per_page = min( 100, max( 1, $per_page ) );
		$where    = [ '1=1' ];
		$params   = [];
		if ( '' !== $search ) {
			$like    = '%' . $this->db->esc_like( $search ) . '%';
			$where[] = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
			$params  = array_merge( $params, [ $like, $like, $like, $like ] );
		}
		if ( in_array( $status, [ 'active', 'suspended', 'archived' ], true ) ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}
		$table     = $this->table();
		$where_sql = implode( ' AND ', $where );
		$offset    = ( $page - 1 ) * $per_page;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- clausole fisse e valori parametrizzati.
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) $this->db->get_var( [] === $params ? $count_sql : $this->db->prepare( $count_sql, $params ) );
		$sql       = "SELECT id, first_name, last_name, email, phone, status, created_at, updated_at FROM {$table} WHERE {$where_sql} ORDER BY last_name ASC, first_name ASC, id ASC LIMIT %d OFFSET %d";
		$rows      = $this->db->get_results( $this->db->prepare( $sql, array_merge( $params, [ $per_page, $offset ] ) ), ARRAY_A );
		// phpcs:enable
		return [
			'items'    => array_map( [ $this, 'cast' ], (array) $rows ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}

	/**
	 * Esporta tutte le anagrafiche con i soli campi previsti per il CSV.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function export_all(): array {
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- tabella interna, nessun input.
		$rows = $this->db->get_results(
			"SELECT id, first_name, last_name, email, phone, birth_date, address, city, postal_code, status, notes FROM {$table} ORDER BY last_name ASC, first_name ASC, id ASC",
			ARRAY_A
		);
		return array_map( [ $this, 'cast' ], (array) $rows );
	}

	/**
	 * Record singolo.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- tabella interna.
		$row = $this->db->get_row( $this->db->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $this->cast( $row ) : null;
	}

	/**
	 * Inserisce o aggiorna un record.
	 *
	 * @param array<string, mixed> $data Dati già validati.
	 * @param int $actor Utente che esegue l'operazione.
	 * @param int $id Record da aggiornare, 0 per crearne uno.
	 */
	public function save( array $data, int $actor, int $id = 0 ): int {
		$now = gmdate( 'Y-m-d H:i:s' );
		if ( $id > 0 ) {
			$data['updated_by'] = $actor;
			$data['updated_at'] = $now;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabella del plugin.
			return false === $this->db->update( $this->table(), $data, [ 'id' => $id ] ) ? 0 : $id;
		}
		$data['created_by'] = $actor;
		$data['created_at'] = $now;
		$data['updated_by'] = $actor;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabella del plugin.
		return false === $this->db->insert( $this->table(), $data ) ? 0 : (int) $this->db->insert_id;
	}

	public function set_status( int $id, string $status, int $actor ): bool {
		if ( ! in_array( $status, [ 'active', 'suspended', 'archived' ], true ) ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabella del plugin.
		return false !== $this->db->update(
			$this->table(),
			[
				'status'     => $status,
				'updated_by' => $actor,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $id ]
		);
	}

	/**
	 * Verifica email già utilizzata, escludendo il record in modifica.
	 */
	public function email_exists( string $email, int $exclude_id = 0 ): bool {
		if ( '' === $email ) {
			return false;
		}
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- tabella interna e valori parametrizzati.
		return (int) $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) FROM {$table} WHERE email = %s AND id <> %d", $email, $exclude_id ) ) > 0;
	}

	/**
	 * Tabella delle relazioni tra soci e genitori/tutori.
	 */
	public function guardians_table(): string {
		return $this->db->prefix . 'bc_member_guardians';
	}

	/**
	 * Tabella dello storico anagrafico.
	 */
	public function history_table(): string {
		return $this->db->prefix . 'bc_member_history';
	}

	/**
	 * Identificativi dei genitori/tutori collegati a un socio.
	 *
	 * @return int[]
	 */
	public function guardian_ids( int $member_id ): array {
		$table = $this->guardians_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- tabella interna e valore parametrizzato.
		$ids = $this->db->get_col( $this->db->prepare( "SELECT guardian_id FROM {$table} WHERE member_id = %d ORDER BY is_primary DESC, id ASC", $member_id ) );
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Anagrafiche dei genitori/tutori collegati.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function guardians( int $member_id ): array {
		$members   = $this->table();
		$relations = $this->guardians_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- nomi tabelle interni e valore parametrizzato.
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT m.*, r.relationship, r.is_primary FROM {$members} m INNER JOIN {$relations} r ON r.guardian_id = m.id WHERE r.member_id = %d ORDER BY r.is_primary DESC, m.last_name ASC, m.first_name ASC",
				$member_id
			),
			ARRAY_A
		);
		return array_map( [ $this, 'cast' ], (array) $rows );
	}

	/**
	 * Soci per i quali una persona risulta genitore/tutore.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function dependents( int $guardian_id ): array {
		$members   = $this->table();
		$relations = $this->guardians_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- nomi tabelle interni e valore parametrizzato.
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT m.*, r.relationship, r.is_primary FROM {$members} m INNER JOIN {$relations} r ON r.member_id = m.id WHERE r.guardian_id = %d ORDER BY m.last_name ASC, m.first_name ASC",
				$guardian_id
			),
			ARRAY_A
		);
		return array_map( [ $this, 'cast' ], (array) $rows );
	}

	/**
	 * Sostituisce i collegamenti familiari mantenendo le relazioni non cambiate.
	 *
	 * @param int   $member_id Socio collegato.
	 * @param int[] $guardian_ids Genitori/tutori richiesti.
	 * @param int   $actor Utente che modifica i collegamenti.
	 */
	public function set_guardians( int $member_id, array $guardian_ids, int $actor ): bool {
		$guardian_ids = array_values( array_unique( array_filter( array_map( 'absint', $guardian_ids ) ) ) );
		$current      = $this->guardian_ids( $member_id );
		$table        = $this->guardians_table();
		$now          = gmdate( 'Y-m-d H:i:s' );

		// Inseriamo prima i nuovi collegamenti: un errore non elimina quelli già presenti.
		foreach ( $guardian_ids as $index => $guardian_id ) {
			$is_primary = 0 === $index ? 1 : 0;
			if ( ! in_array( $guardian_id, $current, true ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabella del plugin.
				$ok = $this->db->insert(
					$table,
					[
						'member_id'    => $member_id,
						'guardian_id'  => $guardian_id,
						'relationship' => 'genitore/tutore',
						'is_primary'   => $is_primary,
						'created_by'   => $actor,
						'created_at'   => $now,
					]
				);
				if ( false === $ok ) {
					return false;
				}
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabella del plugin.
				$ok = $this->db->update(
					$table,
					[ 'is_primary' => $is_primary ],
					[
						'member_id'   => $member_id,
						'guardian_id' => $guardian_id,
					]
				);
				if ( false === $ok ) {
					return false;
				}
			}
		}

		foreach ( array_diff( $current, $guardian_ids ) as $guardian_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabella del plugin.
			if (
				false === $this->db->delete(
					$table,
					[
						'member_id'   => $member_id,
						'guardian_id' => $guardian_id,
					]
				)
			) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Registra soltanto i nomi dei campi modificati, mai i valori personali.
	 *
	 * @param int      $member_id Socio.
	 * @param int      $actor Utente.
	 * @param string   $action Operazione.
	 * @param string[] $fields Campi modificati.
	 */
	public function record_history( int $member_id, int $actor, string $action, array $fields ): bool {
		$table   = $this->history_table();
		$fields  = array_values( array_unique( array_filter( array_map( 'sanitize_key', $fields ) ) ) );
		$encoded = wp_json_encode( $fields );
		if ( false === $encoded ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- storico append-only del plugin.
		return false !== $this->db->insert(
			$table,
			[
				'member_id'      => $member_id,
				'actor_id'       => max( 0, $actor ),
				'action'         => sanitize_key( $action ),
				'changed_fields' => $encoded,
				'created_at'     => gmdate( 'Y-m-d H:i:s' ),
			]
		);
	}

	/**
	 * Storico di una singola anagrafica, dal più recente.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function history( int $member_id, int $limit = 50 ): array {
		$table = $this->history_table();
		$limit = min( 100, max( 1, $limit ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- tabella interna e valori parametrizzati.
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT id, member_id, actor_id, action, changed_fields, created_at FROM {$table} WHERE member_id = %d ORDER BY created_at DESC, id DESC LIMIT %d",
				$member_id,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable
		$out = [];
		foreach ( (array) $rows as $row ) {
			$row['id']            = (int) $row['id'];
			$row['member_id']     = (int) $row['member_id'];
			$row['actor_id']      = (int) $row['actor_id'];
			$row['fields']        = json_decode( (string) $row['changed_fields'], true );
			$row['fields']        = is_array( $row['fields'] ) ? array_map( 'strval', $row['fields'] ) : [];
			unset( $row['changed_fields'] );
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Cerca possibili doppioni in base a nome/cognome e, se disponibile, data di nascita.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function potential_duplicates( string $first_name, string $last_name, string $birth_date = '', int $exclude_id = 0 ): array {
		if ( '' === trim( $first_name ) || '' === trim( $last_name ) ) {
			return [];
		}
		$table  = $this->table();
		$params = [ trim( $first_name ), trim( $last_name ), $exclude_id ];
		$where  = 'LOWER(first_name) = LOWER(%s) AND LOWER(last_name) = LOWER(%s) AND id <> %d';
		if ( '' !== $birth_date ) {
			$where   .= ' AND (birth_date = %s OR birth_date IS NULL)';
			$params[] = $birth_date;
		}
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- clausola costruita da campi fissi e valori parametrizzati.
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT id, first_name, last_name, birth_date, email, status FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT 10",
				$params
			),
			ARRAY_A
		);
		// phpcs:enable
		return array_map( [ $this, 'cast' ], (array) $rows );
	}

	/**
	 * Indica se un socio è già discendente del tutore proposto, evitando relazioni circolari.
	 */
	public function is_descendant( int $member_id, int $ancestor_id ): bool {
		if ( $member_id < 1 || $ancestor_id < 1 || $member_id === $ancestor_id ) {
			return false;
		}
		$table   = $this->guardians_table();
		$queue   = [ $ancestor_id ];
		$visited = [];
		$visited_count = 0;
		while ( [] !== $queue && $visited_count < 1000 ) {
			$current = (int) array_shift( $queue );
			if ( isset( $visited[ $current ] ) ) {
				continue;
			}
			$visited[ $current ] = true;
			++$visited_count;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- tabella interna e valore parametrizzato.
			$children = $this->db->get_col( $this->db->prepare( "SELECT member_id FROM {$table} WHERE guardian_id = %d", $current ) );
			foreach ( array_map( 'intval', (array) $children ) as $child_id ) {
				if ( $member_id === $child_id ) {
					return true;
				}
				if ( ! isset( $visited[ $child_id ] ) ) {
					$queue[] = $child_id;
				}
			}
		}
		return false;
	}

	/**
	 * Converte i tipi restituiti da wpdb.
	 *
	 * @param array<string, mixed> $row Riga.
	 * @return array<string, mixed>
	 */
	private function cast( array $row ): array {
		$row['id']         = (int) $row['id'];
		$row['created_by'] = isset( $row['created_by'] ) ? (int) $row['created_by'] : 0;
		$row['updated_by'] = isset( $row['updated_by'] ) ? (int) $row['updated_by'] : 0;
		return $row;
	}
}
