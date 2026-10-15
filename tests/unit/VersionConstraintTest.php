<?php
/**
 * @package BracelliClub
 */

namespace Bracelli\Club\Tests\unit;

use Bracelli\Club\Core\Modules\VersionConstraint;
use PHPUnit\Framework\TestCase;

final class VersionConstraintTest extends TestCase {

	/**
	 * @dataProvider cases
	 */
	public function test_satisfies( string $version, string $constraint, bool $expected ): void {
		$this->assertSame( $expected, VersionConstraint::satisfies( $version, $constraint ) );
	}

	/**
	 * @return array<string, array{string, string, bool}>
	 */
	public static function cases(): array {
		return [
			'qualsiasi'               => [ '0.1.0', '*', true ],
			'esatta'                  => [ '1.2.3', '1.2.3', true ],
			'esatta abbreviata'       => [ '1.2.0', '1.2', true ],
			'esatta diversa'          => [ '1.2.4', '1.2.3', false ],
			'minima ok'               => [ '2.0.0', '>=1.5', true ],
			'minima ko'               => [ '1.4.9', '>=1.5', false ],
			'caret stessa major'      => [ '1.9.0', '^1.2', true ],
			'caret major superiore'   => [ '2.0.0', '^1.2', false ],
			'caret sotto minima'      => [ '1.1.0', '^1.2', false ],
			'caret zero'              => [ '0.1.0', '^0.1', true ],
			'versione non valida'     => [ 'abc', '*', false ],
			'vincolo non valido'      => [ '1.0.0', '~1.0', false ],
		];
	}

	public function test_validity(): void {
		$this->assertTrue( VersionConstraint::is_valid_version( '10.20.30' ) );
		$this->assertFalse( VersionConstraint::is_valid_version( '1.2.3.4' ) );
		$this->assertFalse( VersionConstraint::is_valid_version( '1.2-beta' ) );
		$this->assertTrue( VersionConstraint::is_valid_constraint( '>=0.1' ) );
		$this->assertFalse( VersionConstraint::is_valid_constraint( '<2' ) );
	}
}
