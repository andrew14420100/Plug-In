<?php
/**
 * Contratto di una migrazione dello schema o dei dati.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Le migrazioni sono solo "in avanti" e devono essere idempotenti:
 * eseguirle due volte non deve produrre effetti diversi da una volta sola.
 */
interface MigrationInterface {

	/**
	 * Chiave stabile e univoca, es. "2026_10_0001_create_audit_events".
	 */
	public function key(): string;

	/**
	 * Modulo proprietario.
	 */
	public function module(): string;

	/**
	 * Descrizione leggibile.
	 */
	public function description(): string;

	/**
	 * Esegue la migrazione.
	 *
	 * @param \wpdb $db Connessione WordPress.
	 * @throws \RuntimeException Se la migrazione fallisce.
	 */
	public function up( \wpdb $db ): void;
}
