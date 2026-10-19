<?php
/**
 * Controlli diagnostici.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Diagnostics;

use Bracelli\Club\Core\Permissions\Roles;
use Bracelli\Club\Core\Modules\ModuleManager;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * I controlli di base sono leggeri (nessuna scansione di file o tabelle).
 * La diagnostica approfondita esegue query aggiuntive solo quando richiesta (Q246).
 */
final class Checks {

	public const OK      = 'ok';
	public const WARNING = 'warning';
	public const ERROR   = 'error';
	public const INFO    = 'info';

	/**
	 * @param Plugin $plugin Servizi.
	 */
	public function __construct( private Plugin $plugin ) {
	}

	/**
	 * Controlli essenziali.
	 *
	 * @return array<int, array{id: string, label: string, status: string, value: string, hint: string}>
	 */
	public function basic(): array {
		global $wpdb, $wp_version;
		$checks = [];

		$php_ok   = version_compare( PHP_VERSION, BRACELLI_CLUB_MIN_PHP, '>=' );
		$checks[] = $this->item(
			'php',
			__( 'Versione PHP', 'bracelli-club' ),
			$php_ok ? self::OK : self::ERROR,
			PHP_VERSION,
			/* translators: %s: versione minima. */
			sprintf( __( 'Minimo provvisorio: %s (da confermare sull\'hosting TopHost).', 'bracelli-club' ), BRACELLI_CLUB_MIN_PHP )
		);
		$checks[] = $this->item(
			'WordPress',
			__( 'Versione WordPress', 'bracelli-club' ),
			version_compare( (string) $wp_version, BRACELLI_CLUB_MIN_WP, '>=' ) ? self::OK : self::ERROR,
			(string) $wp_version,
			/* translators: %s: versione minima. */
			sprintf( __( 'Minimo provvisorio: %s.', 'bracelli-club' ), BRACELLI_CLUB_MIN_WP )
		);
		$server_info = method_exists( $wpdb, 'db_server_info' ) ? (string) $wpdb->db_server_info() : (string) $wpdb->db_version();
		$checks[]    = $this->item( 'database', __( 'Database', 'bracelli-club' ), self::INFO, $server_info, '' );

		$memory   = (string) ini_get( 'memory_limit' );
		$bytes    = '-1' === $memory ? PHP_INT_MAX : wp_convert_hr_to_bytes( $memory );
		$checks[] = $this->item(
			'memory',
			__( 'Memoria PHP', 'bracelli-club' ),
			$bytes >= 128 * MB_IN_BYTES ? self::OK : self::WARNING,
			$memory . ' (WP_MEMORY_LIMIT ' . ( defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '—' ) . ')',
			__( 'Consigliati almeno 128M per le operazioni amministrative.', 'bracelli-club' )
		);
		$max_exec = (int) ini_get( 'max_execution_time' );
		$checks[] = $this->item(
			'max_execution_time',
			__( 'Tempo massimo di esecuzione', 'bracelli-club' ),
			0 === $max_exec || $max_exec >= 30 ? self::OK : self::WARNING,
			0 === $max_exec ? __( 'illimitato', 'bracelli-club' ) : $max_exec . ' s',
			__( 'Le operazioni lunghe delle fasi future useranno elaborazione a blocchi.', 'bracelli-club' )
		);
		$checks[] = $this->item(
			'upload',
			__( 'Dimensione massima upload', 'bracelli-club' ),
			self::INFO,
			size_format( wp_max_upload_size() ),
			''
		);

		$missing = [];
		foreach ( [ 'sodium', 'intl', 'mbstring', 'zip', 'json', 'openssl' ] as $ext ) {
			if ( ! extension_loaded( $ext ) ) {
				$missing[] = $ext;
			}
		}
		$checks[] = $this->item(
			'extensions',
			__( 'Estensioni PHP', 'bracelli-club' ),
			[] === $missing ? self::OK : self::WARNING,
			[] === $missing ? __( 'tutte presenti', 'bracelli-club' ) : __( 'mancanti:', 'bracelli-club' ) . ' ' . implode( ', ', $missing ),
			__( 'Controllate: sodium, intl, mbstring, zip, json, openssl.', 'bracelli-club' )
		);

		$cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$checks[]      = $this->item(
			'cron',
			__( 'WP-Cron', 'bracelli-club' ),
			self::INFO,
			$cron_disabled ? __( 'disattivato (serve un cron di sistema)', 'bracelli-club' ) : __( 'attivo (dipende dalle visite al sito)', 'bracelli-club' ),
			__( 'Il plugin non pianifica attività automatiche. La scelta del cron per TopHost va verificata prima di introdurne (Q307).', 'bracelli-club' )
		);

		$checks[] = $this->item(
			'https',
			__( 'HTTPS', 'bracelli-club' ),
			str_starts_with( (string) get_option( 'siteurl' ), 'https://' ) ? self::OK : self::WARNING,
			(string) wp_parse_url( (string) get_option( 'siteurl' ), PHP_URL_SCHEME ),
			''
		);
		$checks[] = $this->item(
			'object_cache',
			__( 'Cache oggetti persistente', 'bracelli-club' ),
			self::INFO,
			wp_using_ext_object_cache() ? __( 'presente', 'bracelli-club' ) : __( 'assente', 'bracelli-club' ),
			''
		);
		$checks[] = $this->item(
			'debug',
			__( 'Modalità debug', 'bracelli-club' ),
			( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY && defined( 'WP_DEBUG' ) && WP_DEBUG && 'production' === wp_get_environment_type() ) ? self::WARNING : self::INFO,
			( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'WP_DEBUG on' : 'WP_DEBUG off' ) . ' · ' . wp_get_environment_type(),
			__( 'In produzione gli errori non devono essere mostrati ai visitatori.', 'bracelli-club' )
		);

		$state    = $this->plugin->migrator->state();
		$pending  = $state['pending'];
		$modified = $state['modified'];
		$checks[] = $this->item(
			'migrations',
			__( 'Migrazioni database', 'bracelli-club' ),
			[] !== $pending ? self::ERROR : ( [] !== $modified ? self::WARNING : self::OK ),
			[] !== $pending
				/* translators: %d: numero di migrazioni. */
				? sprintf( _n( '%d in sospeso', '%d in sospeso', count( $pending ), 'bracelli-club' ), count( $pending ) )
				: __( 'tutte applicate', 'bracelli-club' ),
			[] !== $modified ? __( 'File di migrazione modificati dopo l\'esecuzione:', 'bracelli-club' ) . ' ' . implode( ', ', $modified ) : ''
		);

		$audit_table = $this->plugin->audit->table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$audit_ok = $audit_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $audit_table ) ) );
		$checks[] = $this->item(
			'tables',
			__( 'Tabelle del plugin', 'bracelli-club' ),
			$audit_ok && $state['table_exists'] ? self::OK : self::ERROR,
			$audit_ok && $state['table_exists'] ? __( 'presenti', 'bracelli-club' ) : __( 'mancanti', 'bracelli-club' ),
			''
		);

		$problems = [];
		foreach ( array_keys( $this->plugin->modules->manifests() ) as $id ) {
			$status = $this->plugin->modules->status( $id );
			if ( ModuleManager::STATUS_DEGRADED === $status || ModuleManager::STATUS_BLOCKED === $status ) {
				$problems[] = $id;
			}
		}
		$checks[] = $this->item(
			'modules',
			__( 'Moduli', 'bracelli-club' ),
			[] === $problems ? self::OK : self::ERROR,
			[] === $problems ? __( 'nessun errore', 'bracelli-club' ) : __( 'con problemi:', 'bracelli-club' ) . ' ' . implode( ', ', $problems ),
			''
		);
		$legacy   = null === get_role( Roles::GESTORE_CORSI ) ? -1 : count(
			get_users(
				[
					'role'   => Roles::GESTORE_CORSI,
					'fields' => 'ID',
				]
			)
		);
		$checks[] = $this->item(
			'legacy_roles',
			__( 'Ruolo "Gestore corsi" (non più usato)', 'bracelli-club' ),
			$legacy > 0 ? self::WARNING : self::INFO,
			-1 === $legacy
				? __( 'non presente', 'bracelli-club' )
				/* translators: %d: numero di utenti. */
				: sprintf( _n( '%d utente', '%d utenti', $legacy, 'bracelli-club' ), $legacy ),
			$legacy > 0 ? __( 'Questi utenti conservano i loro permessi. Puoi convertirli uno alla volta da Bracelli Club → Permessi.', 'bracelli-club' ) : ''
		);
		$checks[] = $this->item(
			'safe_mode',
			__( 'Modalità sicura', 'bracelli-club' ),
			$this->plugin->is_safe_mode() ? self::WARNING : self::OK,
			$this->plugin->is_safe_mode() ? __( 'attiva', 'bracelli-club' ) : __( 'non attiva', 'bracelli-club' ),
			''
		);
		return $checks;
	}

	/**
	 * Conteggio per stato.
	 *
	 * @param array<int, array{status: string}> $checks Controlli.
	 * @return array<string, int>
	 */
	public static function summary( array $checks ): array {
		$out = [
			self::OK      => 0,
			self::WARNING => 0,
			self::ERROR   => 0,
			self::INFO    => 0,
		];
		foreach ( $checks as $check ) {
			++$out[ $check['status'] ];
		}
		return $out;
	}

	/**
	 * Diagnostica approfondita (eseguita solo su richiesta esplicita).
	 *
	 * @return array<string, mixed>
	 */
	public function deep(): array {
		global $wpdb;
		$started = microtime( true );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$tables    = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT TABLE_NAME AS name, TABLE_ROWS AS row_estimate, ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024) AS size_kb FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME LIKE %s',
				DB_NAME,
				$wpdb->esc_like( $wpdb->prefix . 'bc_' ) . '%'
			),
			ARRAY_A
		);
		$latencies = [];
		for ( $i = 0; $i < 20; $i++ ) {
			$t = microtime( true );
			$wpdb->get_var( 'SELECT 1' );
			$latencies[] = ( microtime( true ) - $t ) * 1000;
		}
		// phpcs:enable
		sort( $latencies );

		$uploads    = wp_upload_dir( null, false );
		$upload_dir = (string) $uploads['basedir'];
		$free       = function_exists( 'disk_free_space' ) && is_dir( $upload_dir ) ? @disk_free_space( $upload_dir ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- funzione spesso disabilitata sugli hosting condivisi.

		return [
			'tables'           => array_map(
				static fn( array $row ): array => [
					'name'         => (string) $row['name'],
					'row_estimate' => (int) $row['row_estimate'],
					'size_kb'      => (int) $row['size_kb'],
				],
				(array) $tables
			),
			'db_latency_ms'    => [
				'median' => round( $latencies[9], 3 ),
				'p95'    => round( $latencies[18], 3 ),
			],
			'uploads_writable' => wp_is_writable( $upload_dir ),
			'disk_free'        => false === $free ? null : size_format( (int) $free ),
			'peak_memory'      => size_format( memory_get_peak_usage( true ) ),
			'errors'           => count( $this->plugin->errors->all() ),
			'duration_ms'      => round( ( microtime( true ) - $started ) * 1000, 1 ),
			'generated_at'     => gmdate( 'Y-m-d H:i:s' ),
		];
	}

	/**
	 * @param string $id     Id.
	 * @param string $label  Etichetta.
	 * @param string $status Stato.
	 * @param string $value  Valore.
	 * @param string $hint   Nota.
	 * @return array{id: string, label: string, status: string, value: string, hint: string}
	 */
	private function item( string $id, string $label, string $status, string $value, string $hint ): array {
		return [
			'id'     => $id,
			'label'  => $label,
			'status' => $status,
			'value'  => $value,
			'hint'   => $hint,
		];
	}
}
