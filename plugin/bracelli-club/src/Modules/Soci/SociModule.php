<?php
/**
 * Modulo Archivio soci.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Soci;

use Bracelli\Club\Core\Modules\ModuleInterface;
use Bracelli\Club\Modules\Area\Sections;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registra la sezione dell'archivio soci nel portale già esistente.
 */
final class SociModule implements ModuleInterface {

	/** {@inheritDoc} */
	public function boot( Plugin $plugin ): void {
		global $wpdb;
		$service = new MemberService( new MemberRepository( $wpdb ), $plugin->audit );
		Sections::add( new SociSection( $service, new MemberRepository( $wpdb ) ) );
	}
}
