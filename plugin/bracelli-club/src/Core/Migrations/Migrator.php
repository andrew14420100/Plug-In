<?php
/**
 * Esecuzione controllata delle migrazioni.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Esegue le migrazioni pendenti in ordine, una volta sola, con lock di database
 * (GET_LOCK) contro esecuzioni concorrenti e registro in {prefix}bc_migrations.
 * Non viene mai invocato dalle richieste frontend (vedi Plugin::maybe_migrate()).
 */
final class Migrator {

	public const SCHEMA_OPTION = 'bracelli_club_schema';
	public const RESULT_OPTION = 'bracelli_club_last_migration';

	/**
	 * @param \wpdb                $db            Connessione.
	 * @param MigrationInterface[] $migrations    Migrazioni in ordine di esecuzione.
	 * @param string               $schema_option Option con l'impronta dello schema applicato.
	 * @param string               $result_option Option con l'ultimo esito.
	 */
	public function __construct(
		private \wpdb $db,
		private array $migrations,
		private string $schema_option = self::SCHEMA_OPTION,
		private string $result_option = self::RESULT_OPTION
	) {
	}

	/**
	 * Nome della tabella di registro.
	 */
	public function table(): string {
		return $this->db->prefix . 'bc_migrations';
	}

	/**
	 * Impronta dell'elenco delle migrazioni note: cambia quando se ne aggiunge una.
	 */
	public function fingerprint(): string {
		$keys = array_map( static fn( MigrationInterface $m ): string => $m->key(), $this->migrations );
		return hash( 'sha256', implode( '|', $keys ) );
	}

	/**
	 * Controllo economico (una option autoload) per sapere se servono migrazioni.
	 */
	public function needs_run(): bool {
		return get_option( $this->schema_option ) !== $this->fingerprint();
	}

	/**
	 * Crea la tabella di registro se manca. Idempotente.
	 */
	public function ensure_registry(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $this->db->get_charset_collate();
		$table   = $this->table();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				migration_key varchar(191) NOT NULL,
				module varchar(64) NOT NULL,
				checksum char(64) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL,
				started_at datetime NOT NULL,
				finished_at datetime DEFAULT NULL,
				error text NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY migration_key (migration_key)
			) {$charset};"
		);
	}

	/**
	 * Righe del registro indicizzate per chiave.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function applied(): array {
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- nome tabella interno, nessun input utente.
		$rows = $this->db->get_results( "SELECT migration_key, module, checksum, status, started_at, finished_at, error FROM {$table}", ARRAY_A );
		$out  = [];
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['migration_key'] ] = $row;
		}
		return $out;
	}

	/**
	 * Chiavi delle migrazioni non ancora completate.
	 *
	 * @return string[]
	 */
	public function pending(): array {
		$applied = $this->table_exists() ? $this->applied() : [];
		$pending = [];
		foreach ( $this->migrations as $migration ) {
			$row = $applied[ $migration->key() ] ?? null;
			if ( null === $row || 'done' !== $row['status'] ) {
				$pending[] = $migration->key();
			}
		}
		return $pending;
	}

	/**
	 * Stato complessivo con una sola lettura del registro (usato dalla diagnostica).
	 *
	 * @return array{table_exists: bool, pending: string[], modified: string[]}
	 */
	public function state(): array {
		$exists   = $this->table_exists();
		$applied  = $exists ? $this->applied() : [];
		$pending  = [];
		$modified = [];
		foreach ( $this->migrations as $migration ) {
			$row = $applied[ $migration->key() ] ?? null;
			if ( null === $row || 'done' !== $row['status'] ) {
				$pending[] = $migration->key();
			} elseif ( $row['checksum'] !== $this->checksum( $migration ) ) {
				$modified[] = $migration->key();
			}
		}
		return [
			'table_exists' => $exists,
			'pending'      => $pending,
			'modified'     => $modified,
		];
	}

	/**
	 * Migrazioni già applicate il cui file è cambiato dopo l'esecuzione (da segnalare in diagnostica).
	 *
	 * @return string[]
	 */
	public function modified_after_apply(): array {
		if ( ! $this->table_exists() ) {
			return [];
		}
		$applied  = $this->applied();
		$modified = [];
		foreach ( $this->migrations as $migration ) {
			$row = $applied[ $migration->key() ] ?? null;
			if ( null !== $row && 'done' === $row['status'] && $row['checksum'] !== $this->checksum( $migration ) ) {
				$modified[] = $migration->key();
			}
		}
		return $modified;
	}

	/**
	 * Esegue le migrazioni pendenti.
	 *
	 * @return array{status: string, ran: string[], error: string}
	 *         status: "ok" | "nothing" | "locked" | "failed".
	 */
	public function run(): array {
		$lock = $this->lock_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- lock applicativo, non cacheabile.
		$got = $this->db->get_var( $this->db->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) );
		// SQLite (WordPress Playground) non ha GET_LOCK: il traduttore risponde "1=1"; lì il file
		// di database è già serializzato e il lock applicativo non serve.
		if ( '1' !== (string) $got && ! $this->is_sqlite() ) {
			return $this->remember(
				[
					'status' => 'locked',
					'ran'    => [],
					'error'  => '',
				]
			);
		}

		try {
			$this->ensure_registry();
			$applied = $this->applied();
			$ran     = [];
			foreach ( $this->migrations as $migration ) {
				$key = $migration->key();
				if ( isset( $applied[ $key ] ) && 'done' === $applied[ $key ]['status'] ) {
					continue;
				}
				$this->record_start( $migration, isset( $applied[ $key ] ) );
				try {
					$migration->up( $this->db );
				} catch ( \Throwable $e ) {
					$this->record_end( $key, 'failed', $e->getMessage() );
					return $this->remember(
						[
							'status' => 'failed',
							'ran'    => $ran,
							'error'  => sprintf( '%s: %s', $key, $e->getMessage() ),
						]
					);
				}
				$this->record_end( $key, 'done', null );
				$ran[] = $key;
			}
			update_option( $this->schema_option, $this->fingerprint(), true );
			return $this->remember(
				[
					'status' => [] === $ran ? 'nothing' : 'ok',
					'ran'    => $ran,
					'error'  => '',
				]
			);
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- rilascio lock applicativo.
			$this->db->query( $this->db->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * Indica se WordPress usa il driver SQLite (es. WordPress Playground).
	 */
	private function is_sqlite(): bool {
		return defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE;
	}

	/**
	 * Nome del lock MySQL (limitato a 64 caratteri).
	 */
	public function lock_name(): string {
		return substr( 'bracelli_club_migrate_' . $this->db->prefix . md5( (string) DB_NAME ), 0, 64 );
	}

	/**
	 * Indica se la tabella di registro esiste.
	 */
	public function table_exists(): bool {
		$table = $this->table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $table === $this->db->get_var( $this->db->prepare( 'SHOW TABLES LIKE %s', $this->db->esc_like( $table ) ) );
	}

	/**
	 * Impronta del file sorgente della migrazione.
	 *
	 * @param MigrationInterface $migration Migrazione.
	 */
	private function checksum( MigrationInterface $migration ): string {
		$file = ( new \ReflectionClass( $migration ) )->getFileName();
		if ( false === $file || ! is_readable( $file ) ) {
			return '';
		}
		return (string) hash_file( 'sha256', $file );
	}

	/**
	 * @param MigrationInterface $migration Migrazione.
	 * @param bool               $exists    Se esiste già una riga (tentativo precedente fallito).
	 */
	private function record_start( MigrationInterface $migration, bool $exists ): void {
		$data = [
			'migration_key' => $migration->key(),
			'module'        => $migration->module(),
			'checksum'      => $this->checksum( $migration ),
			'status'        => 'running',
			'started_at'    => gmdate( 'Y-m-d H:i:s' ),
			'finished_at'   => null,
			'error'         => null,
		];
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		if ( $exists ) {
			$this->db->update( $this->table(), $data, [ 'migration_key' => $migration->key() ] );
		} else {
			$this->db->insert( $this->table(), $data );
		}
		// phpcs:enable
	}

	/**
	 * @param string      $key    Chiave.
	 * @param string      $status Esito.
	 * @param string|null $error  Errore.
	 */
	private function record_end( string $key, string $status, ?string $error ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->db->update(
			$this->table(),
			[
				'status'      => $status,
				'finished_at' => gmdate( 'Y-m-d H:i:s' ),
				'error'       => null === $error ? null : substr( $error, 0, 1000 ),
			],
			[ 'migration_key' => $key ]
		);
	}

	/**
	 * Memorizza l'ultimo esito per diagnostica e avvisi.
	 *
	 * @param array{status: string, ran: string[], error: string} $result Esito.
	 * @return array{status: string, ran: string[], error: string}
	 */
	private function remember( array $result ): array {
		// Autoload: l'avviso di migrazione fallita la legge in ogni pagina amministrativa.
		update_option(
			$this->result_option,
			$result + [ 'at' => gmdate( 'Y-m-d H:i:s' ) ],
			true
		);
		return $result;
	}
}
