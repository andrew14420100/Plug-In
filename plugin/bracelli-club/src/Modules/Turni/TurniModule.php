<?php
/**
 * Modulo Turni del personale (prototipo).
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Turni;

use Bracelli\Club\Core\Modules\ModuleInterface;
use Bracelli\Club\Modules\Area\Sections;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Aggiunge all'area riservata la sezione dei turni del personale (segreteria e dipendenti).
 */
final class TurniModule implements ModuleInterface {

	/**
	 * Turni e presenze.
	 *
	 * @var ShiftService
	 */
	private ShiftService $service;

	/**
	 * {@inheritDoc}
	 */
	public function boot( Plugin $plugin ): void {
		global $wpdb;
		$this->service = new ShiftService( new ShiftRepository( $wpdb ), $plugin->audit );
		Sections::add( new TurniSection( $this->service ) );
	}

	/**
	 * Servizio dei turni.
	 */
	public function service(): ShiftService {
		return $this->service;
	}
}
