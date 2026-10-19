<?php
/**
 * Registro delle sezioni dell'area riservata.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Area;

defined( 'ABSPATH' ) || exit;

/**
 * Le sezioni si registrano nel boot dei propri moduli (che dipendono dal modulo Area).
 */
final class Sections {

	/**
	 * Sezioni per identificativo.
	 *
	 * @var array<string, SectionInterface>
	 */
	private static array $sections = [];

	/**
	 * @param SectionInterface $section Sezione.
	 */
	public static function add( SectionInterface $section ): void {
		self::$sections[ $section->id() ] = $section;
	}

	/**
	 * Sezioni ordinate per priorità.
	 *
	 * @return SectionInterface[]
	 */
	public static function all(): array {
		$list = array_values( self::$sections );
		usort( $list, static fn( SectionInterface $a, SectionInterface $b ): int => $a->priority() <=> $b->priority() );
		return $list;
	}

	/**
	 * @param string $id Identificativo.
	 */
	public static function get( string $id ): ?SectionInterface {
		return self::$sections[ $id ] ?? null;
	}
}
