<?php
/**
 * Icone SVG in linea (stesso insieme usato dall'interfaccia React).
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Ui;

defined( 'ABSPATH' ) || exit;

/**
 * Tracciati statici 24×24, tratto "currentColor".
 */
final class Icons {

	private const PATHS = [
		'home'     => 'M3 11.5 12 4l9 7.5M5 10v10h5v-6h4v6h5V10',
		'grid'     => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
		'settings' => 'M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm8-3.5-2.1-.7-.6-1.5 1-2-1.6-1.6-2 1-1.5-.6L12.5 4h-1l-.7 2.1-1.5.6-2-1L5.7 7.3l1 2-.6 1.5L4 11.5v1l2.1.7.6 1.5-1 2 1.6 1.6 2-1 1.5.6.7 2.1h1l.7-2.1 1.5-.6 2 1 1.6-1.6-1-2 .6-1.5 2.1-.7z',
		'shield'   => 'M12 3 5 6v5c0 4.5 3 8.3 7 10 4-1.7 7-5.5 7-10V6l-7-3Zm-3 9 2 2 4-4',
		'list'     => 'M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01',
		'pulse'    => 'M3 12h4l2-6 4 12 2-6h6',
		'lifebuoy' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-5a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM5.6 5.6l3.6 3.6m5.6 5.6 3.6 3.6m0-12.8-3.6 3.6m-5.6 5.6-3.6 3.6',
		'star'     => 'm12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z',
		'user'     => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0',
		'users'    => 'M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm-6 9a6 6 0 0 1 12 0M16 4.3a3.5 3.5 0 0 1 0 6.4M18 14.5a6 6 0 0 1 3 5.5',
		'calendar' => 'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4',
		'chart'    => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
		'document' => 'M6 3h8l4 4v14H6zM14 3v4h4M9 12h6M9 16h6',
		'image'    => 'M4 5h16v14H4zM4 16l5-5 4 4 3-3 4 4M15.5 9.5h.01',
		'bell'     => 'M6 16V11a6 6 0 1 1 12 0v5l2 2H4zM10 20a2 2 0 0 0 4 0',
		'bolt'     => 'M13 3 5 14h6l-1 7 8-11h-6z',
		'heart'    => 'M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10Z',
		'flag'     => 'M5 21V4M5 4h11l-2 4 2 4H5',
		'wplogo'   => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM4.5 8l4.5 12M8 7.5h4M10 7.5 14 19l4-10.5M15.5 7.5H18',
		'sun'      => 'M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0-13v2m0 14v2M3 12h2m14 0h2M5.6 5.6 7 7m10 10 1.4 1.4M5.6 18.4 7 17M17 7l1.4-1.4',
		'moon'     => 'M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5Z',
		'monitor'  => 'M3 5h18v11H3zM8 20h8M12 16v4',
		'menu'     => 'M4 6h16M4 12h16M4 18h16',
		'alert'    => 'M12 4 2.5 20h19zM12 10v4M12 17h.01',
		'check'    => 'm5 12 4.5 4.5L19 7',
	];

	/**
	 * Markup SVG decorativo (aria-hidden). Restituisce stringa vuota per icone sconosciute.
	 *
	 * @param string $name  Nome icona.
	 * @param int    $size  Dimensione in pixel.
	 */
	public static function svg( string $name, int $size = 20 ): string {
		if ( ! isset( self::PATHS[ $name ] ) ) {
			return '';
		}
		return sprintf(
			'<svg class="bc-icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="%2$s"/></svg>',
			$size,
			esc_attr( self::PATHS[ $name ] )
		);
	}

	/**
	 * Regole wp_kses per stampare le icone.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function kses(): array {
		$svg = [
			'class'           => true,
			'width'           => true,
			'height'          => true,
			'viewbox'         => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'aria-hidden'     => true,
			'focusable'       => true,
		];
		return [
			'svg'  => $svg,
			'path' => [ 'd' => true ],
		];
	}

	/**
	 * Stampa un'icona.
	 *
	 * @param string $name Nome icona.
	 * @param int    $size Dimensione.
	 */
	public static function render( string $name, int $size = 20 ): void {
		echo wp_kses( self::svg( $name, $size ), self::kses() );
	}

	/**
	 * Nomi disponibili.
	 *
	 * @return string[]
	 */
	public static function names(): array {
		return array_keys( self::PATHS );
	}
}
