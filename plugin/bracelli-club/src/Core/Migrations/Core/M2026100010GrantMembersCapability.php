<?php
/**
 * Capability dell'archivio soci.
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
 * Assegna il permesso archivio soci alla segreteria senza rimuovere altri permessi.
 */
final class M2026100010GrantMembersCapability implements MigrationInterface {

	/** {@inheritDoc} */
	public function key(): string {
		return '2026_10_0010_grant_members_capability';
	}

	/** {@inheritDoc} */
	public function module(): string {
		return 'soci';
	}

	/** {@inheritDoc} */
	public function description(): string {
		return 'Abilita la gestione dell’archivio soci alla segreteria.';
	}

	/** {@inheritDoc} */
	public function up( \wpdb $db ): void {
		Roles::sync();
		Roles::grant( Roles::SEGRETERIA, [ Capabilities::SOCI_MANAGE ] );
	}
}
