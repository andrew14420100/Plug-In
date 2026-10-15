<?php
/**
 * Registro circolare degli ultimi errori interni (senza dati personali).
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Support;

use Bracelli\Club\Core\Audit\Redactor;

defined( 'ABSPATH' ) || exit;

/**
 * Conserva al massimo 30 voci in una option non autoload; consultabile da Recovery e Diagnostica.
 */
final class ErrorLog {

	public const OPTION = 'bracelli_club_errors';

	private const MAX = 30;

	/**
	 * @param string          $source  Modulo o componente.
	 * @param string          $message Messaggio.
	 * @param \Throwable|null $error   Eccezione facoltativa (si registrano solo file e riga).
	 */
	public function record( string $source, string $message, ?\Throwable $error = null ): void {
		$entries   = $this->all();
		$entries[] = [
			'at'      => gmdate( 'Y-m-d H:i:s' ),
			'source'  => sanitize_key( $source ),
			'message' => (string) Redactor::redact( mb_substr( $message, 0, 300 ) ),
			'where'   => null === $error ? '' : basename( $error->getFile() ) . ':' . $error->getLine(),
		];
		if ( count( $entries ) > self::MAX ) {
			$entries = array_slice( $entries, -self::MAX );
		}
		update_option( self::OPTION, $entries, false );
	}

	/**
	 * @return array<int, array{at: string, source: string, message: string, where: string}>
	 */
	public function all(): array {
		$entries = get_option( self::OPTION, [] );
		return is_array( $entries ) ? array_values( $entries ) : [];
	}

	/**
	 * Svuota il registro errori.
	 */
	public function clear(): void {
		delete_option( self::OPTION );
	}
}
