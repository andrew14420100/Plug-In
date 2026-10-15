<?php
/**
 * Ruoli e capability iniziali.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Migrations\Core;

use Bracelli\Club\Core\Migrations\MigrationInterface;
use Bracelli\Club\Core\Permissions\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Registra i ruoli Bracelli e assegna all'amministratore tutte le capability del plugin.
 * Idempotente: non rimuove permessi personalizzati già assegnati.
 */
final class M2026100002InstallRoles implements MigrationInterface {

	/**
	 * {@inheritDoc}
	 */
	public function key(): string {
		return '2026_10_0002_install_roles';
	}

	/**
	 * {@inheritDoc}
	 */
	public function module(): string {
		return 'permissions';
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return 'Registra ruoli Bracelli e capability dell\'amministratore.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function up( \wpdb $db ): void {
		Roles::sync();
	}
}
