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
			$like     = '%' . $this->db->esc_like( $search ) . '%';
			$where[]  = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
			$params   = array_merge( $params, [ $like, $like, $like, $like ] );
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
			[ 'status' => $status, 'updated_by' => $actor, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ],
			[ 'id' => $id ]
		);
	}

	/**
	 * Converte i tipi restituiti da wpdb.
	 *
	 * @param array<string, mixed> $row Riga.
	 * @return array<string, mixed>
	 */
	private function cast( array $row ): array {
		$row['id'] = (int) $row['id'];
		$row['created_by'] = isset( $row['created_by'] ) ? (int) $row['created_by'] : 0;
		$row['updated_by'] = isset( $row['updated_by'] ) ? (int) $row['updated_by'] : 0;
		return $row;
	}
}
