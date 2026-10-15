<?php
/**
 * Vincoli di versione semplificati per le dipendenze tra moduli.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Supporta: "*", "1.2.3" (esatta), ">=1.2", "^1.2" (stessa major, >= minima).
 */
final class VersionConstraint {

	private const VERSION_PATTERN = '/^\d+(\.\d+){0,2}$/';

	/**
	 * @param string $version Versione da verificare.
	 */
	public static function is_valid_version( string $version ): bool {
		return 1 === preg_match( self::VERSION_PATTERN, $version );
	}

	/**
	 * @param string $constraint Vincolo da verificare.
	 */
	public static function is_valid_constraint( string $constraint ): bool {
		if ( '*' === $constraint ) {
			return true;
		}
		if ( str_starts_with( $constraint, '>=' ) ) {
			return self::is_valid_version( substr( $constraint, 2 ) );
		}
		if ( str_starts_with( $constraint, '^' ) ) {
			return self::is_valid_version( substr( $constraint, 1 ) );
		}
		return self::is_valid_version( $constraint );
	}

	/**
	 * Indica se la versione soddisfa il vincolo.
	 *
	 * @param string $version    Versione disponibile.
	 * @param string $constraint Vincolo richiesto.
	 */
	public static function satisfies( string $version, string $constraint ): bool {
		if ( ! self::is_valid_version( $version ) || ! self::is_valid_constraint( $constraint ) ) {
			return false;
		}
		if ( '*' === $constraint ) {
			return true;
		}
		if ( str_starts_with( $constraint, '>=' ) ) {
			return version_compare( self::normalize( $version ), self::normalize( substr( $constraint, 2 ) ), '>=' );
		}
		if ( str_starts_with( $constraint, '^' ) ) {
			$min   = self::normalize( substr( $constraint, 1 ) );
			$major = (int) explode( '.', $min )[0];
			return version_compare( self::normalize( $version ), $min, '>=' )
				&& (int) explode( '.', self::normalize( $version ) )[0] === $major;
		}
		return version_compare( self::normalize( $version ), self::normalize( $constraint ), '==' );
	}

	/**
	 * Completa una versione a tre componenti (1.2 → 1.2.0).
	 *
	 * @param string $version Versione.
	 */
	private static function normalize( string $version ): string {
		$parts = array_pad( explode( '.', $version ), 3, '0' );
		return implode( '.', array_slice( $parts, 0, 3 ) );
	}
}
