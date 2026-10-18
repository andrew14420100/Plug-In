<?php
/**
 * Ruolo Dipendente e capability del modulo Turni e presenze.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Migrations\Core;

use Bracelli\Club\Core\Migrations\MigrationInterface;
use Bracelli\Club\Core\Permissions\Capabilities;
use Bracelli\Club\Core\Permissions\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Crea il ruolo "Dipendente Bracelli" se manca, dà "Gestione turni" alla Segreteria e all'amministratore.
 * Non assegna ruoli a nessun account e non toglie permessi esistenti.
 */
final class M2026100004GrantShiftCapabilities implements MigrationInterface {

	/**
	 * {@inheritDoc}
	 */
	public function key(): string {
		return '2026_10_0004_grant_shift_capabilities';
	}

	/**
	 * {@inheritDoc}
	 */
	public function module(): string {
		return 'turni';
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return 'Ruolo Dipendente Bracelli e capability dei turni.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function up( \wpdb $db ): void {
		Roles::sync();
		Roles::grant( Roles::SEGRETERIA, [ Capabilities::TURNI_MANAGE ] );
	}
}
