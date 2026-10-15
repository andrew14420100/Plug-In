<?php
/**
 * Riduzione dei dati personali nei dettagli del registro.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Maschera chiavi sensibili, indirizzi email e codici fiscali; limita lunghezza e profondità.
 */
final class Redactor {

	public const MASK = '[redatto]';

	private const SENSITIVE_KEY = '/(pass|pwd|secret|token|api[_-]?key|auth|nonce|cookie|email|e-mail|phone|telefono|cellulare|tax|codice[_-]?fiscale|^cf$|fiscal|address|indirizzo|iban|card[_-]?number|birth|nascita|health|sanitar|document)/i';

	private const EMAIL = '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i';

	private const TAX_CODE = '/\b[A-Z]{6}[0-9LMNPQRSTUV]{2}[A-EHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]\b/i';

	private const MAX_STRING = 500;

	private const MAX_DEPTH = 5;

	/**
	 * @param mixed $value Valore da ripulire.
	 * @param int   $depth Profondità corrente.
	 * @return mixed
	 */
	public static function redact( $value, int $depth = 0 ) {
		if ( $depth > self::MAX_DEPTH ) {
			return '[…]';
		}
		if ( is_array( $value ) ) {
			$out = [];
			foreach ( $value as $key => $item ) {
				if ( is_string( $key ) && 1 === preg_match( self::SENSITIVE_KEY, $key ) ) {
					$out[ $key ] = self::MASK;
					continue;
				}
				$out[ $key ] = self::redact( $item, $depth + 1 );
			}
			return $out;
		}
		if ( is_string( $value ) ) {
			$value = (string) preg_replace( self::EMAIL, self::MASK, $value );
			$value = (string) preg_replace( self::TAX_CODE, self::MASK, $value );
			if ( mb_strlen( $value ) > self::MAX_STRING ) {
				$value = mb_substr( $value, 0, self::MAX_STRING ) . '…';
			}
			return $value;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}
		return '[' . gettype( $value ) . ']';
	}
}
