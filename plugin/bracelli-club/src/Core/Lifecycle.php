<?php
/**
 * Attivazione e disattivazione del plugin.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core;

use Bracelli\Club\Core\Modules\OptionStateStore;
use Bracelli\Club\Core\Settings\Settings;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * La disattivazione non elimina dati; la rimozione avviene solo in uninstall.php se richiesta.
 */
final class Lifecycle {

	public const INSTALLED_OPTION = 'bracelli_club_installed_at';

	/**
	 * Hook di attivazione.
	 */
	public static function activate(): void {
		$plugin = Plugin::instance();
		$result = $plugin->run_migrations();
		add_option( self::INSTALLED_OPTION, gmdate( 'Y-m-d H:i:s' ), '', false );
		// Opzioni lette a ogni richiesta: create subito in autoload, così non costano query aggiuntive.
		add_option( Settings::OPTION, [], '', true );
		add_option( OptionStateStore::OPTION, [], '', true );
		$plugin->audit->log(
			'plugin.activated',
			[
				'module' => 'core',
				'diff'   => [
					'version'    => BRACELLI_CLUB_VERSION,
					'migrations' => $result['status'],
				],
			]
		);
	}

	/**
	 * Hook di disattivazione: nessuna cancellazione di dati.
	 */
	public static function deactivate(): void {
		Plugin::instance()->audit->log(
			'plugin.deactivated',
			[
				'module' => 'core',
				'diff'   => [ 'version' => BRACELLI_CLUB_VERSION ],
			]
		);
	}
}
