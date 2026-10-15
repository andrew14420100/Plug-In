<?php
/**
 * Comandi WP-CLI: wp bracelli <sottocomando>.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Cli;

use Bracelli\Club\Core\Permissions\Roles;
use Bracelli\Club\Modules\Core\ModulesPage;
use Bracelli\Club\Modules\Diagnostics\Checks;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- firma ($args, $assoc_args) imposta da WP-CLI.

/**
 * Gestione del plugin Bracelli Club da riga di comando (anche per il ripristino senza browser).
 */
final class Command {

	/**
	 * Mostra versione, modalità sicura e stato dei moduli.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bracelli status
	 *
	 * @param string[]              $args       Argomenti.
	 * @param array<string, string> $assoc_args Opzioni.
	 */
	public function status( array $args, array $assoc_args ): void {
		$plugin = Plugin::instance();
		\WP_CLI::log( 'Bracelli Club ' . BRACELLI_CLUB_VERSION . ( $plugin->is_safe_mode() ? ' (modalità sicura)' : '' ) );
		\WP_CLI::log( 'Migrazioni in sospeso: ' . count( $plugin->migrator->pending() ) );
		$this->module_list( [], [] );
	}

	/**
	 * Esegue le migrazioni pendenti.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bracelli migrate
	 *
	 * @param string[]              $args       Argomenti.
	 * @param array<string, string> $assoc_args Opzioni.
	 */
	public function migrate( array $args, array $assoc_args ): void {
		$result = Plugin::instance()->run_migrations();
		if ( 'failed' === $result['status'] ) {
			\WP_CLI::error( 'Migrazione non riuscita: ' . $result['error'] );
		}
		if ( 'locked' === $result['status'] ) {
			\WP_CLI::error( 'Un\'altra esecuzione delle migrazioni è in corso.' );
		}
		\WP_CLI::success( 'nothing' === $result['status'] ? 'Nessuna migrazione da eseguire.' : 'Eseguite: ' . implode( ', ', $result['ran'] ) );
	}

	/**
	 * Gestione moduli.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : list | activate | deactivate
	 *
	 * [<id>]
	 * : Id del modulo.
	 *
	 * [--cascade]
	 * : Conferma la disattivazione dei moduli dipendenti.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bracelli module list
	 *     wp bracelli module deactivate diagnostics --cascade
	 *
	 * @param string[]              $args       Argomenti.
	 * @param array<string, string> $assoc_args Opzioni.
	 */
	public function module( array $args, array $assoc_args ): void {
		$action = $args[0] ?? 'list';
		if ( 'list' === $action ) {
			$this->module_list( [], [] );
			return;
		}
		$id = $args[1] ?? '';
		if ( ! in_array( $action, [ 'activate', 'deactivate' ], true ) || '' === $id ) {
			\WP_CLI::error( 'Uso: wp bracelli module <list|activate|deactivate> [<id>] [--cascade]' );
		}
		$result = ModulesPage::apply( Plugin::instance(), $id, $action, isset( $assoc_args['cascade'] ), 'cli' );
		if ( ! empty( $result['needs_confirmation'] ) ) {
			\WP_CLI::error( 'Dipendono da questo modulo: ' . implode( ', ', $result['needs_confirmation'] ) . '. Ripeti con --cascade per disattivarli tutti.' );
		}
		if ( ! $result['ok'] ) {
			\WP_CLI::error( implode( '; ', $result['errors'] ) );
		}
		\WP_CLI::success( [] === $result['changed'] ? 'Nessuna modifica.' : 'Aggiornati: ' . implode( ', ', $result['changed'] ) );
	}

	/**
	 * Esegue la diagnostica di base.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bracelli diagnose
	 *
	 * @param string[]              $args       Argomenti.
	 * @param array<string, string> $assoc_args Opzioni.
	 */
	public function diagnose( array $args, array $assoc_args ): void {
		$rows = [];
		foreach ( ( new Checks( Plugin::instance() ) )->basic() as $check ) {
			$rows[] = [
				'controllo' => $check['label'],
				'esito'     => $check['status'],
				'valore'    => $check['value'],
			];
		}
		\WP_CLI\Utils\format_items( 'table', $rows, [ 'controllo', 'esito', 'valore' ] );
	}

	/**
	 * Strumenti di sviluppo (solo ambienti "local" o "development").
	 *
	 * ## OPTIONS
	 *
	 * <tool>
	 * : seed-users — crea un utente fittizio per ciascun ruolo Bracelli.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bracelli dev seed-users
	 *
	 * @param string[]              $args       Argomenti.
	 * @param array<string, string> $assoc_args Opzioni.
	 */
	public function dev( array $args, array $assoc_args ): void {
		if ( ! in_array( wp_get_environment_type(), [ 'local', 'development' ], true ) ) {
			\WP_CLI::error( 'Disponibile solo con WP_ENVIRONMENT_TYPE "local" o "development".' );
		}
		if ( 'seed-users' !== ( $args[0] ?? '' ) ) {
			\WP_CLI::error( 'Strumento sconosciuto. Disponibile: seed-users' );
		}
		$rows = [];
		foreach ( Roles::definitions() as $slug => $role ) {
			$login = str_replace( 'bracelli_', 'demo_', $slug );
			if ( username_exists( $login ) ) {
				$rows[] = [
					'utente'   => $login,
					'ruolo'    => $slug,
					'password' => '(già esistente)',
				];
				continue;
			}
			$password = wp_generate_password( 16, false );
			$user_id  = wp_insert_user(
				[
					'user_login'   => $login,
					'user_pass'    => $password,
					'user_email'   => $login . '@example.org',
					'display_name' => $role['label'] . ' (prova)',
					'role'         => $slug,
				]
			);
			if ( is_wp_error( $user_id ) ) {
				\WP_CLI::warning( $user_id->get_error_message() );
				continue;
			}
			$rows[] = [
				'utente'   => $login,
				'ruolo'    => $slug,
				'password' => $password,
			];
		}
		\WP_CLI\Utils\format_items( 'table', $rows, [ 'utente', 'ruolo', 'password' ] );
		\WP_CLI::log( 'Le password sono mostrate una sola volta e non vengono salvate altrove.' );
	}

	/**
	 * @param string[]              $args       Argomenti.
	 * @param array<string, string> $assoc_args Opzioni.
	 */
	private function module_list( array $args, array $assoc_args ): void {
		$manager = Plugin::instance()->modules;
		$rows    = [];
		foreach ( $manager->manifests() as $id => $manifest ) {
			$rows[] = [
				'id'           => $id,
				'versione'     => $manifest->version,
				'stato'        => $manager->status( $id ),
				'obbligatorio' => $manifest->required ? 'sì' : 'no',
				'dipendenze'   => implode( ',', array_keys( $manifest->requires ) ),
			];
		}
		\WP_CLI\Utils\format_items( 'table', $rows, [ 'id', 'versione', 'stato', 'obbligatorio', 'dipendenze' ] );
	}
}
