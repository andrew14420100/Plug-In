<?php
/**
 * Contenitore dei servizi e avvio del plugin.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club;

use Bracelli\Club\Core\Audit\AuditLogger;
use Bracelli\Club\Core\Migrations\Core\Registry;
use Bracelli\Club\Core\Migrations\Migrator;
use Bracelli\Club\Core\Modules\ModuleInterface;
use Bracelli\Club\Core\Modules\ModuleManager;
use Bracelli\Club\Core\Modules\ModuleManifest;
use Bracelli\Club\Core\Modules\OptionStateStore;
use Bracelli\Club\Core\Settings\Settings;
use Bracelli\Club\Core\Support\ErrorLog;
use Bracelli\Club\Core\Ui\Preferences;

defined( 'ABSPATH' ) || exit;

/**
 * Punto di accesso unico ai servizi del nucleo.
 */
final class Plugin {

	/**
	 * Istanza condivisa.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Impostazioni globali.
	 *
	 * @var Settings
	 */
	public Settings $settings;

	/**
	 * Preferenze per utente.
	 *
	 * @var Preferences
	 */
	public Preferences $preferences;

	/**
	 * Registro errori interno.
	 *
	 * @var ErrorLog
	 */
	public ErrorLog $errors;

	/**
	 * Registro attività.
	 *
	 * @var AuditLogger
	 */
	public AuditLogger $audit;

	/**
	 * Migrazioni.
	 *
	 * @var Migrator
	 */
	public Migrator $migrator;

	/**
	 * Moduli.
	 *
	 * @var ModuleManager
	 */
	public ModuleManager $modules;

	/**
	 * Indica se gli hook sono già registrati.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Costruisce i servizi senza registrare hook.
	 */
	private function __construct() {
		global $wpdb, $wp_version;

		$this->settings    = new Settings();
		$this->preferences = new Preferences( $this->settings );
		$this->errors      = new ErrorLog();
		$this->audit       = new AuditLogger(
			$wpdb,
			$this->errors,
			fn(): bool => (bool) $this->settings->get( 'audit.store_ip_hash' )
		);
		$this->migrator    = new Migrator( $wpdb, Registry::all() );
		$this->modules     = new ModuleManager(
			self::load_manifests(),
			new OptionStateStore(),
			[
				'php' => PHP_VERSION,
				'wp'  => (string) $wp_version,
			],
			'get_current_user_id'
		);
	}

	/**
	 * Istanza condivisa (creata al primo uso).
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Ricrea l'istanza (solo per i test automatici).
	 */
	public static function reset_for_tests(): void {
		self::$instance = null;
	}

	/**
	 * Hook `plugins_loaded`.
	 */
	public static function boot(): void {
		self::instance()->register_hooks();
	}

	/**
	 * Modalità sicura: attiva con la costante BRACELLI_CLUB_SAFE_MODE in wp-config.php.
	 * Avvia solo i moduli marcati safe_mode (Recovery) e non nasconde mai il menu WordPress.
	 */
	public function is_safe_mode(): bool {
		$constant = defined( 'BRACELLI_CLUB_SAFE_MODE' ) && BRACELLI_CLUB_SAFE_MODE;
		/**
		 * Consente ai test automatici di simulare la modalità sicura.
		 *
		 * @param bool $safe_mode Stato derivato dalla costante.
		 */
		return (bool) apply_filters( 'bracelli_club_safe_mode', $constant );
	}

	/**
	 * Registra gli hook e avvia i moduli.
	 */
	public function register_hooks(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'admin_init', [ $this, 'maybe_migrate_for_admin' ], 1 );

		$this->modules->boot(
			static function ( ModuleManifest $manifest ): ?ModuleInterface {
				if ( '' === $manifest->class_name ) {
					return null;
				}
				$class = $manifest->class_name;
				if ( ! class_exists( $class ) || ! is_subclass_of( $class, ModuleInterface::class ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- messaggio interno; quando viene mostrato è escapato.
					throw new \RuntimeException( sprintf( 'Classe del modulo non valida: %s', $class ) );
				}
				return new $class();
			},
			fn( ModuleInterface $module ) => $module->boot( $this ),
			$this->is_safe_mode(),
			function ( string $id, \Throwable $e ): void {
				$this->errors->record( $id, 'Avvio del modulo non riuscito: ' . $e->getMessage(), $e );
			}
		);

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'bracelli', Core\Cli\Command::class );
		}
	}

	/**
	 * Esegue le migrazioni pendenti solo nell'area amministrativa (mai sul frontend).
	 */
	public function maybe_migrate_for_admin(): void {
		// admin_init scatta anche per admin-ajax.php e admin-post.php anonimi: solo un amministratore avvia le migrazioni.
		if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$this->maybe_migrate();
	}

	/**
	 * Esegue le migrazioni pendenti, se ce ne sono.
	 */
	public function maybe_migrate(): void {
		if ( ! $this->migrator->needs_run() ) {
			return;
		}
		$this->run_migrations();
	}

	/**
	 * Esegue le migrazioni e registra l'esito.
	 *
	 * @return array{status: string, ran: string[], error: string}
	 */
	public function run_migrations(): array {
		$result = $this->migrator->run();
		if ( 'ok' === $result['status'] ) {
			$this->audit->log(
				'migrations.applied',
				[
					'module' => 'core',
					'diff'   => [ 'ran' => $result['ran'] ],
				]
			);
		} elseif ( 'failed' === $result['status'] ) {
			$this->errors->record( 'migrations', $result['error'] );
		}
		return $result;
	}

	/**
	 * Carica i manifest da src/Modules/<Nome>/module.php.
	 *
	 * @return ModuleManifest[]
	 */
	public static function load_manifests(): array {
		$files = glob( __DIR__ . '/Modules/*/module.php' );
		$out   = [];
		foreach ( false === $files ? [] : $files as $file ) {
			$data = require $file;
			if ( is_array( $data ) ) {
				$out[] = ModuleManifest::from_array( $data );
			}
		}
		// Ordine di presentazione: obbligatori prima, poi per nome.
		usort(
			$out,
			static fn( ModuleManifest $a, ModuleManifest $b ): int => [ ! $a->required, $a->name ] <=> [ ! $b->required, $b->name ]
		);
		return $out;
	}
}
