<?php
/**
 * Tavolozze grafiche disponibili (i colori sono definiti in assets/css/admin.css).
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Ui;

defined( 'ABSPATH' ) || exit;

/**
 * Elenco chiuso: l'utente sceglie tra tavolozze verificate per contrasto, non colori arbitrari (Q175).
 */
final class Palettes {

	public const DEFAULT = 'bracelli';

	/**
	 * @return string[]
	 */
	public static function ids(): array {
		return [ 'bracelli', 'aurora', 'brace', 'smeraldo', 'grafite' ];
	}

	/**
	 * @return array<string, string> id => etichetta
	 */
	public static function labels(): array {
		return [
			'bracelli' => __( 'Bracelli — blu notte e ciano', 'bracelli-club' ),
			'aurora'   => __( 'Aurora — viola e verde acqua', 'bracelli-club' ),
			'brace'    => __( 'Brace — arancio e corallo', 'bracelli-club' ),
			'smeraldo' => __( 'Smeraldo — verde e lime', 'bracelli-club' ),
			'grafite'  => __( 'Grafite — monocromatico', 'bracelli-club' ),
		];
	}

	/**
	 * @return string[]
	 */
	public static function themes(): array {
		return [ 'auto', 'light', 'dark' ];
	}
}
