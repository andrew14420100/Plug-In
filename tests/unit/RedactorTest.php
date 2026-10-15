<?php
/**
 * @package BracelliClub
 */

namespace Bracelli\Club\Tests\unit;

use Bracelli\Club\Core\Audit\Redactor;
use PHPUnit\Framework\TestCase;

final class RedactorTest extends TestCase {

	public function test_sensitive_keys_are_masked_recursively(): void {
		$out = Redactor::redact(
			[
				'email'          => 'mario@example.org',
				'codice_fiscale' => 'qualcosa',
				'password'       => 'segreta',
				'nested'         => [
					'telefono' => '000',
					'ok'       => 'visibile',
				],
				'count'          => 3,
				'flag'           => true,
			]
		);
		$this->assertSame( Redactor::MASK, $out['email'] );
		$this->assertSame( Redactor::MASK, $out['codice_fiscale'] );
		$this->assertSame( Redactor::MASK, $out['password'] );
		$this->assertSame( Redactor::MASK, $out['nested']['telefono'] );
		$this->assertSame( 'visibile', $out['nested']['ok'] );
		$this->assertSame( 3, $out['count'] );
		$this->assertTrue( $out['flag'] );
	}

	public function test_emails_and_tax_codes_inside_text_are_masked(): void {
		// Codice fiscale sintatticamente valido ma fittizio (generato per il test).
		$out = Redactor::redact( 'Contatto test.utente@example.com, CF RSSMRA80A01Z999X, fine' );
		$this->assertStringNotContainsString( '@example.com', $out );
		$this->assertStringNotContainsString( 'RSSMRA80A01Z999X', $out );
		$this->assertStringContainsString( 'fine', $out );
	}

	public function test_long_strings_and_depth_are_limited(): void {
		$this->assertLessThanOrEqual( 501, mb_strlen( Redactor::redact( str_repeat( 'a', 2000 ) ) ) );
		$deep = [ 'a' => [ 'b' => [ 'c' => [ 'd' => [ 'e' => [ 'f' => [ 'g' => 'x' ] ] ] ] ] ] ];
		$this->assertSame( '[…]', Redactor::redact( $deep )['a']['b']['c']['d']['e']['f'] );
	}

	public function test_objects_are_not_serialized(): void {
		$this->assertSame( '[object]', Redactor::redact( new \stdClass() ) );
	}
}
