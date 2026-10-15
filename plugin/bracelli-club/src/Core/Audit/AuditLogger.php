<?php
/**
 * Scrittura e consultazione del registro attività.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Audit;

use Bracelli\Club\Core\Support\ErrorLog;

defined( 'ABSPATH' ) || exit;

/**
 * Registro append-only: il plugin non offre funzioni di modifica o cancellazione degli eventi.
 */
final class AuditLogger {

	/**
	 * Identificativo della richiesta corrente.
	 *
	 * @var string
	 */
	private string $request_id;

	/**
	 * @param \wpdb         $db         Connessione.
	 * @param ErrorLog      $errors     Log errori interno.
	 * @param callable|null $store_ip   Restituisce true se va memorizzata l'impronta IP.
	 */
	public function __construct( private \wpdb $db, private ErrorLog $errors, private $store_ip = null ) {
		$this->request_id = wp_generate_uuid4();
	}

	/**
	 * Nome tabella.
	 */
	public function table(): string {
		return $this->db->prefix . 'bc_audit_events';
	}

	/**
	 * Identificativo della richiesta corrente (per correlare eventi).
	 */
	public function request_id(): string {
		return $this->request_id;
	}

	/**
	 * Registra un evento.
	 *
	 * @param string               $action  Azione, es. "module.activated".
	 * @param array<string, mixed> $context Chiavi facoltative: module, resource_type, resource_id, diff, actor_id, actor_type.
	 */
	public function log( string $action, array $context = [] ): bool {
		$actor_type = (string) ( $context['actor_type'] ?? ( ( defined( 'WP_CLI' ) && WP_CLI ) ? 'cli' : 'user' ) );
		$actor_id   = isset( $context['actor_id'] ) ? (int) $context['actor_id'] : get_current_user_id();
		$diff       = isset( $context['diff'] ) ? wp_json_encode( Redactor::redact( $context['diff'] ) ) : null;

		$row = [
			'occurred_at'   => gmdate( 'Y-m-d H:i:s' ),
			'actor_id'      => max( 0, $actor_id ),
			'actor_type'    => substr( sanitize_key( $actor_type ), 0, 20 ),
			'action'        => substr( (string) preg_replace( '/[^a-z0-9_.\-]/', '', strtolower( $action ) ), 0, 100 ),
			'module'        => substr( sanitize_key( (string) ( $context['module'] ?? '' ) ), 0, 64 ),
			'resource_type' => substr( sanitize_key( (string) ( $context['resource_type'] ?? '' ) ), 0, 64 ),
			'resource_id'   => substr( sanitize_text_field( (string) ( $context['resource_id'] ?? '' ) ), 0, 191 ),
			'diff_redacted' => false === $diff ? null : $diff,
			'ip_hash'       => $this->ip_hash(),
			'request_id'    => $this->request_id,
		];

		$suppress = $this->db->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabella del plugin, scrittura append-only.
		$ok = $this->db->insert( $this->table(), $row );
		$this->db->suppress_errors( $suppress );

		if ( false === $ok ) {
			$this->errors->record( 'audit', 'Scrittura registro non riuscita per l\'azione ' . $row['action'] );
			return false;
		}
		return true;
	}

	/**
	 * Interroga il registro con filtri e paginazione.
	 *
	 * @param array<string, mixed> $filters  action, actor_id, module, resource_type, resource_id, from, to, search.
	 * @param int                  $page     Pagina (da 1).
	 * @param int                  $per_page Righe per pagina (1–100).
	 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
	 */
	public function query( array $filters = [], int $page = 1, int $per_page = 25 ): array {
		$page     = max( 1, $page );
		$per_page = min( 100, max( 1, $per_page ) );
		$where    = [ '1=1' ];
		$params   = [];

		foreach ( [ 'action', 'module', 'resource_type', 'resource_id' ] as $field ) {
			if ( isset( $filters[ $field ] ) && '' !== (string) $filters[ $field ] ) {
				$where[]  = "{$field} = %s";
				$params[] = (string) $filters[ $field ];
			}
		}
		if ( isset( $filters['actor_id'] ) && '' !== (string) $filters['actor_id'] ) {
			$where[]  = 'actor_id = %d';
			$params[] = (int) $filters['actor_id'];
		}
		if ( ! empty( $filters['from'] ) ) {
			$where[]  = 'occurred_at >= %s';
			$params[] = (string) $filters['from'] . ' 00:00:00';
		}
		if ( ! empty( $filters['to'] ) ) {
			$where[]  = 'occurred_at <= %s';
			$params[] = (string) $filters['to'] . ' 23:59:59';
		}
		if ( ! empty( $filters['search'] ) ) {
			$like     = '%' . $this->db->esc_like( (string) $filters['search'] ) . '%';
			$where[]  = '(action LIKE %s OR resource_id LIKE %s OR diff_redacted LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$table     = $this->table();
		$where_sql = implode( ' AND ', $where );
		$offset    = ( $page - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- clausole costruite solo da campi fissi, valori sempre come segnaposto.
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) $this->db->get_var( [] === $params ? $count_sql : $this->db->prepare( $count_sql, $params ) );
		$rows      = $this->db->get_results(
			$this->db->prepare(
				"SELECT id, occurred_at, actor_id, actor_type, action, module, resource_type, resource_id, diff_redacted, request_id FROM {$table} WHERE {$where_sql} ORDER BY occurred_at DESC, id DESC LIMIT %d OFFSET %d",
				array_merge( $params, [ $per_page, $offset ] )
			),
			ARRAY_A
		);
		// phpcs:enable

		$items = [];
		foreach ( (array) $rows as $row ) {
			$row['id']       = (int) $row['id'];
			$row['actor_id'] = (int) $row['actor_id'];
			$row['diff']     = null === $row['diff_redacted'] ? null : json_decode( (string) $row['diff_redacted'], true );
			unset( $row['diff_redacted'] );
			$items[] = $row;
		}

		return [
			'items'    => $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}

	/**
	 * Azioni distinte presenti nel registro (per i filtri).
	 *
	 * @return string[]
	 */
	public function distinct_actions(): array {
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $this->db->get_col( "SELECT DISTINCT action FROM {$table} ORDER BY action ASC LIMIT 200" );
		return array_map( 'strval', (array) $rows );
	}

	/**
	 * Impronta non reversibile dell'IP (16 caratteri), se abilitata.
	 */
	private function ip_hash(): string {
		if ( null !== $this->store_ip && ! ( $this->store_ip )() ) {
			return '';
		}
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( '' === $ip ) {
			return '';
		}
		return substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), 0, 16 );
	}
}
