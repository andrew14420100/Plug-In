<?php
/**
 * Contratto dei moduli eseguibili.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Modules;

use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Un modulo registra hook, pagine e rotte nel proprio boot().
 * Non deve eseguire query pesanti né lavoro sulle richieste frontend non pertinenti.
 */
interface ModuleInterface {

	/**
	 * Registra hook del modulo.
	 *
	 * @param Plugin $plugin Servizi del nucleo.
	 */
	public function boot( Plugin $plugin ): void;
}
