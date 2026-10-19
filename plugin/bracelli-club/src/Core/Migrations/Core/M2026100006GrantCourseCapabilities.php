<?php
/**
 * Ruolo Socio e capability del modulo Corsi.
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
 * Crea il ruolo "Socio Bracelli" se manca e aggiunge le capability dei corsi ai ruoli esistenti
 * (l'Istruttore è l'insegnante). Non assegna ruoli ad alcun account e non toglie permessi.
 */
final class M2026100006GrantCourseCapabilities implements MigrationInterface {

	/**
	 * {@inheritDoc}
	 */
	public function key(): string {
		return '2026_10_0006_grant_course_capabilities';
	}

	/**
	 * {@inheritDoc}
	 */
	public function module(): string {
		return 'corsi';
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return 'Ruolo Socio Bracelli e capability dei corsi.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function up( \wpdb $db ): void {
		Roles::sync();
		Roles::grant( Roles::SEGRETERIA, [ Capabilities::CORSI_VIEW, Capabilities::CORSI_MANAGE ] );
		Roles::grant( Roles::GESTORE_CORSI, [ Capabilities::CORSI_VIEW, Capabilities::CORSI_MANAGE ] );
		Roles::grant( Roles::ISTRUTTORE, [ Capabilities::CORSI_VIEW, Capabilities::CORSI_TEACH ] );
	}
}
