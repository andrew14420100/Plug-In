<?php
/**
 * Comandi WP-CLI dei corsi.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Corsi;

defined( 'ABSPATH' ) || exit;

/**
 * Importazione dei corsi da terminale (per esempio dalla "site shell" di LocalWP).
 */
final class CorsiCli {

	/**
	 * @param CourseImporter $importer Importazione.
	 */
	public function __construct( private CourseImporter $importer ) {
	}

	/**
	 * Importa corsi e orari da un file CSV. Senza --yes mostra solo l'anteprima (nessuna scrittura).
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Percorso del file CSV.
	 *
	 * [--yes]
	 * : Applica l'importazione dopo l'anteprima.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bracelli-corsi import corsi.csv
	 *     wp bracelli-corsi import corsi.csv --yes
	 *
	 * @param string[]              $args       Argomenti.
	 * @param array<string, string> $assoc_args Opzioni.
	 */
	public function import( array $args, array $assoc_args ): void {
		$file = (string) ( $args[0] ?? '' );
		if ( '' === $file || ! is_readable( $file ) ) {
			\WP_CLI::error( 'File non leggibile.' );
		}
		$parsed = $this->importer->parse( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale indicato dall'operatore.
		foreach ( $parsed['errors'] as $error ) {
			\WP_CLI::warning( $error );
		}
		if ( [] === $parsed['rows'] ) {
			\WP_CLI::error( 'Nessuna riga da importare.' );
		}
		$apply = isset( $assoc_args['yes'] );
		if ( $apply ) {
			$result = $this->importer->apply( $parsed['rows'], get_current_user_id(), basename( $file ) );
			if ( ! $result['ok'] ) {
				\WP_CLI::error( 'Importazione non riuscita, nessuna modifica salvata: ' . $result['error'] );
			}
			$plan = $result['plan'];
		} else {
			$plan = $this->importer->plan( $parsed['rows'] );
		}
		$rows = [];
		foreach ( $plan['counts'] as $key => $value ) {
			$rows[] = [
				'voce'   => $key,
				'numero' => $value,
			];
		}
		\WP_CLI\Utils\format_items( 'table', $rows, [ 'voce', 'numero' ] );
		foreach ( $plan['anomalies'] as $anomaly ) {
			\WP_CLI::log( sprintf( 'Riga %s: %s', $anomaly['line'], $anomaly['message'] ) );
		}
		$apply ? \WP_CLI::success( 'Importazione completata.' ) : \WP_CLI::log( 'Anteprima: nessun dato scritto. Aggiungi --yes per importare.' );
	}
}
