<?php
/**
 * Esito delle operazioni dei servizi Corsi.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Corsi;

defined( 'ABSPATH' ) || exit;

/**
 * Forma comune: ok, codice dell'esito (messaggio nella sezione), errori per campo, id del record.
 */
final class Result {

	/**
	 * @param string $code Esito.
	 * @param int    $id   Record.
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public static function ok( string $code, int $id ): array {
		return [
			'ok'     => true,
			'code'   => $code,
			'errors' => [],
			'id'     => $id,
		];
	}

	/**
	 * @param string                $code   Esito.
	 * @param array<string, string> $errors Errori per campo.
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public static function fail( string $code, array $errors = [] ): array {
		return [
			'ok'     => false,
			'code'   => $code,
			'errors' => $errors,
			'id'     => 0,
		];
	}
}
