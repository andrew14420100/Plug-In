<?php
/**
 * Elenco ordinato delle migrazioni del plugin.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Migrations\Core;

use Bracelli\Club\Core\Migrations\MigrationInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Le nuove migrazioni si aggiungono SOLO in coda; quelle esistenti non si modificano.
 */
final class Registry {

	/**
	 * @return MigrationInterface[]
	 */
	public static function all(): array {
		return [
			new M2026100001CreateAuditEvents(),
			new M2026100002InstallRoles(),
			new M2026100003CreateShiftTables(),
			new M2026100004GrantShiftCapabilities(),
			new M2026100005CreateCourseTables(),
			new M2026100006GrantCourseCapabilities(),
			new M2026100007AddSlotAssignedFrom(),
			new M2026100008CreateCatalogAndRooms(),
		];
	}
}
