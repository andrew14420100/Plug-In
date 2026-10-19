<?php
/**
 * Modulo Corsi e presenze insegnanti.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Corsi;

use Bracelli\Club\Core\Admin\Layout;
use Bracelli\Club\Core\Admin\Pages;
use Bracelli\Club\Core\Modules\ModuleInterface;
use Bracelli\Club\Core\Permissions\Capabilities;
use Bracelli\Club\Modules\Area\Sections;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registra la sezione Corsi dell'area riservata e la pagina di importazione (solo amministratori).
 * L'importazione ha due passi: anteprima (nessuna scrittura) e conferma.
 */
final class CorsiModule implements ModuleInterface {

	public const ADMIN_SLUG     = 'bracelli-corsi-import';
	public const ACTION_PREVIEW = 'bracelli_corsi_import_preview';
	public const ACTION_APPLY   = 'bracelli_corsi_import_apply';
	public const ACTION_DISCARD = 'bracelli_corsi_import_discard';
	public const MAX_BYTES      = 1048576;

	/**
	 * Corsi.
	 *
	 * @var CourseService
	 */
	private CourseService $service;

	/**
	 * Importazione.
	 *
	 * @var CourseImporter
	 */
	private CourseImporter $importer;

	/**
	 * {@inheritDoc}
	 */
	public function boot( Plugin $plugin ): void {
		global $wpdb;
		$repo           = new CourseRepository( $wpdb );
		$rooms          = new RoomService( $repo, $plugin->audit );
		$this->service  = new CourseService( $repo, $plugin->audit, null, $rooms );
		$catalog        = new CatalogService( $repo, $plugin->audit, $rooms );
		$this->importer = new CourseImporter( $repo, $plugin->audit );
		Sections::add( new CorsiSection( $this->service, $catalog ) );
		Sections::add( new CatalogoSection( $catalog, $rooms, $this->service ) );

		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_post_' . self::ACTION_PREVIEW, [ $this, 'handle_preview' ] );
		add_action( 'admin_post_' . self::ACTION_APPLY, [ $this, 'handle_apply' ] );
		add_action( 'admin_post_' . self::ACTION_DISCARD, [ $this, 'handle_discard' ] );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'bracelli-corsi', new CorsiCli( $this->importer ) );
		}
	}

	/**
	 * Servizio dei corsi.
	 */
	public function service(): CourseService {
		return $this->service;
	}

	/**
	 * Importazione.
	 */
	public function importer(): CourseImporter {
		return $this->importer;
	}

	/**
	 * Voce di menu (riservata agli amministratori).
	 */
	public function menu(): void {
		Pages::add( self::ADMIN_SLUG, __( 'Importa corsi', 'bracelli-club' ), __( 'Importa corsi', 'bracelli-club' ), Capabilities::CORSI_IMPORT, [ $this, 'render' ], 16 );
	}

	/**
	 * Chiave del transient con l'anteprima dell'utente.
	 *
	 * @param string $kind preview|report.
	 */
	private static function transient( string $kind ): string {
		return 'bracelli_corsi_import_' . $kind . '_' . get_current_user_id();
	}

	/**
	 * Primo passo: legge il file caricato e prepara l'anteprima, senza scrivere nel database.
	 */
	public function handle_preview(): void {
		Layout::require_cap( Capabilities::CORSI_IMPORT );
		check_admin_referer( self::ACTION_PREVIEW );
		$file = isset( $_FILES['bc_csv'] ) && is_array( $_FILES['bc_csv'] ) ? $_FILES['bc_csv'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- controllato qui sotto.
		$name = is_array( $file ) ? sanitize_file_name( (string) ( $file['name'] ?? '' ) ) : '';
		$tmp  = is_array( $file ) ? (string) ( $file['tmp_name'] ?? '' ) : '';
		if ( null === $file || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			Layout::redirect( self::ADMIN_SLUG, 'nofile' );
		}
		if ( (int) ( $file['size'] ?? 0 ) > self::MAX_BYTES || ! preg_match( '/\.(csv|txt)$/i', $name ) ) {
			Layout::redirect( self::ADMIN_SLUG, 'badfile' );
		}
		$content = (string) file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file caricato, letto localmente.
		$parsed  = $this->importer->parse( $content );
		set_transient(
			self::transient( 'preview' ),
			[
				'name'   => $name,
				'sha'    => hash( 'sha256', $content ),
				'rows'   => $parsed['rows'],
				'errors' => $parsed['errors'],
			],
			30 * MINUTE_IN_SECONDS
		);
		delete_transient( self::transient( 'report' ) );
		Layout::redirect( self::ADMIN_SLUG, [] === $parsed['errors'] ? 'preview' : 'parse_errors' );
	}

	/**
	 * Secondo passo: applica l'anteprima confermata (ricalcolata sul database attuale).
	 */
	public function handle_apply(): void {
		Layout::require_cap( Capabilities::CORSI_IMPORT );
		check_admin_referer( self::ACTION_APPLY );
		$preview = get_transient( self::transient( 'preview' ) );
		if ( ! is_array( $preview ) || [] === $preview['rows'] || [] !== $preview['errors'] ) {
			Layout::redirect( self::ADMIN_SLUG, 'expired' );
		}
		$result = $this->importer->apply( $preview['rows'], get_current_user_id(), (string) $preview['name'] );
		delete_transient( self::transient( 'preview' ) );
		set_transient(
			self::transient( 'report' ),
			[
				'name'   => $preview['name'],
				'result' => $result,
			],
			30 * MINUTE_IN_SECONDS
		);
		Layout::redirect( self::ADMIN_SLUG, $result['ok'] ? 'applied' : 'failed' );
	}

	/**
	 * Scarta l'anteprima.
	 */
	public function handle_discard(): void {
		Layout::require_cap( Capabilities::CORSI_IMPORT );
		check_admin_referer( self::ACTION_DISCARD );
		delete_transient( self::transient( 'preview' ) );
		Layout::redirect( self::ADMIN_SLUG, 'discarded' );
	}

	/**
	 * Pagina: caricamento, anteprima o rapporto finale.
	 */
	public function render(): void {
		Layout::require_cap( Capabilities::CORSI_IMPORT );
		Layout::open( __( 'Importa corsi', 'bracelli-club' ), 'corsi', __( 'Carica il file CSV dei corsi: prima vedi l\'anteprima, poi confermi. Rieseguire l\'importazione non crea duplicati e non cancella nulla.', 'bracelli-club' ) );
		Layout::query_notice(
			[
				'nofile'       => [ 'error', __( 'Nessun file caricato.', 'bracelli-club' ) ],
				'badfile'      => [ 'error', __( 'Il file deve essere un CSV (.csv) di al massimo 1 MB.', 'bracelli-club' ) ],
				'parse_errors' => [ 'error', __( 'Il file non è nel formato atteso: vedi gli errori qui sotto.', 'bracelli-club' ) ],
				'preview'      => [ 'info', __( 'Anteprima pronta: nessun dato è ancora stato scritto.', 'bracelli-club' ) ],
				'expired'      => [ 'error', __( 'Anteprima scaduta o non valida: carica di nuovo il file.', 'bracelli-club' ) ],
				'applied'      => [ 'success', __( 'Importazione completata.', 'bracelli-club' ) ],
				'failed'       => [ 'error', __( 'Importazione non riuscita: nessuna modifica è stata salvata.', 'bracelli-club' ) ],
				'discarded'    => [ 'info', __( 'Anteprima scartata.', 'bracelli-club' ) ],
			]
		);
		$report  = get_transient( self::transient( 'report' ) );
		$preview = get_transient( self::transient( 'preview' ) );
		if ( is_array( $report ) ) {
			echo '<div class="bc-card"><h2>' . esc_html__( 'Rapporto dell\'importazione', 'bracelli-club' ) . '</h2><p class="bc-muted">' . esc_html( (string) $report['name'] ) . '</p>';
			if ( ! $report['result']['ok'] ) {
				echo '<p>' . esc_html( (string) $report['result']['error'] ) . '</p>';
			}
			$this->render_plan( $report['result']['plan'] );
			echo '</div>';
		} elseif ( is_array( $preview ) ) {
			echo '<div class="bc-card"><h2>' . esc_html__( 'Anteprima', 'bracelli-club' ) . '</h2><p class="bc-muted">' . esc_html( (string) $preview['name'] ) . ' — ' . esc_html( sprintf( '%d righe', count( $preview['rows'] ) ) ) . '</p>';
			if ( [] !== $preview['errors'] ) {
				echo '<ul>';
				foreach ( $preview['errors'] as $error ) {
					echo '<li>' . esc_html( (string) $error ) . '</li>';
				}
				echo '</ul>';
			} else {
				$this->render_plan( $this->importer->plan( $preview['rows'] ) );
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="bc-inline-form">';
				echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_APPLY ) . '">';
				wp_nonce_field( self::ACTION_APPLY );
				echo '<button type="submit" class="bc-button bc-button--primary">' . esc_html__( 'Conferma e importa', 'bracelli-club' ) . '</button></form> ';
			}
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="bc-inline-form">';
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_DISCARD ) . '">';
			wp_nonce_field( self::ACTION_DISCARD );
			echo '<button type="submit" class="bc-button bc-button--ghost">' . esc_html__( 'Scarta', 'bracelli-club' ) . '</button></form></div>';
		}

		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="bc-card">';
		echo '<h2>' . esc_html__( 'Carica il file', 'bracelli-club' ) . '</h2>';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_PREVIEW ) . '">';
		wp_nonce_field( self::ACTION_PREVIEW );
		echo '<p><label for="bc-csv">' . esc_html__( 'File CSV (colonne: chiave; sezione; corso; gruppo; giorno; inizio; fine; insegnante; sede; note)', 'bracelli-club' ) . '</label><br><input id="bc-csv" type="file" name="bc_csv" accept=".csv,text/csv" required></p>';
		echo '<p class="bc-muted">' . esc_html__( 'Una riga per ogni orario settimanale. La colonna "insegnante" è solo un nome indicato nei dati: gli account degli insegnanti si collegano agli orari dall\'area riservata (Corsi e orari), mai in automatico.', 'bracelli-club' ) . '</p>';
		echo '<p><button type="submit" class="bc-button bc-button--primary">' . esc_html__( 'Mostra anteprima', 'bracelli-club' ) . '</button></p></form>';
		Layout::close();
	}

	/**
	 * Tabelle del piano o del rapporto.
	 *
	 * @param array<string, mixed> $plan Piano.
	 */
	private function render_plan( array $plan ): void {
		$c    = $plan['counts'];
		$rows = [
			[ __( 'Corsi da creare', 'bracelli-club' ), $c['courses_create'] ],
			[ __( 'Corsi da aggiornare', 'bracelli-club' ), $c['courses_update'] ],
			[ __( 'Corsi invariati', 'bracelli-club' ), $c['courses_unchanged'] ],
			[ __( 'Corsi modificati o archiviati dallo staff (non sovrascritti)', 'bracelli-club' ), $c['courses_conflict'] ?? 0 ],
			[ __( 'Orari da creare', 'bracelli-club' ), $c['slots_create'] ],
			[ __( 'Orari da aggiornare', 'bracelli-club' ), $c['slots_update'] ],
			[ __( 'Orari invariati', 'bracelli-club' ), $c['slots_unchanged'] ],
			[ __( 'Orari modificati dallo staff (non sovrascritti)', 'bracelli-club' ), $c['slots_conflict'] ],
			[ __( 'Orari nel database ma non nel file (lasciati invariati)', 'bracelli-club' ), $c['untouched'] ],
			[ __( 'Voci da chiarire (non importate o senza orario)', 'bracelli-club' ), $c['anomalies'] ],
		];
		echo '<table class="bc-table"><tbody>';
		foreach ( $rows as [ $label, $value ] ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( [] !== $plan['anomalies'] ) {
			echo '<h3>' . esc_html__( 'Voci da chiarire', 'bracelli-club' ) . '</h3><ul>';
			foreach ( $plan['anomalies'] as $anomaly ) {
				/* translators: 1: numero di riga, 2: messaggio. */
				echo '<li>' . esc_html( sprintf( __( 'Riga %1$s: %2$s', 'bracelli-club' ), $anomaly['line'], $anomaly['message'] ) ) . '</li>';
			}
			echo '</ul>';
		}
		$kept = array_filter( $plan['courses'], static fn( array $c ): bool => 'conflict' === $c['action'] );
		if ( [] !== $kept ) {
			echo '<h3>' . esc_html__( 'Corsi non sovrascritti', 'bracelli-club' ) . '</h3><ul>';
			foreach ( $kept as $course ) {
				echo '<li>' . esc_html( $course['ext_key'] . ': ' . ( $course['reason'] ?? '' ) ) . '</li>';
			}
			echo '</ul>';
		}
		$conflicts = array_filter( $plan['slots'], static fn( array $s ): bool => 'conflict' === $s['action'] );
		if ( [] !== $conflicts ) {
			echo '<h3>' . esc_html__( 'Orari non sovrascritti', 'bracelli-club' ) . '</h3><ul>';
			foreach ( $conflicts as $slot ) {
				echo '<li>' . esc_html( $slot['ext_key'] . ': ' . $slot['reason'] ) . '</li>';
			}
			echo '</ul>';
		}
	}
}
