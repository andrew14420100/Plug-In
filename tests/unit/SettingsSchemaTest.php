<?php
/**
 * @package BracelliClub
 */

namespace Bracelli\Club\Tests\unit;

use Bracelli\Club\Core\Settings\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsSchemaTest extends TestCase {

	public function test_validation_accepts_known_values_and_rejects_others(): void {
		$result = Settings::validate(
			[
				'ui.default_theme'      => 'dark',
				'ui.default_palette'    => 'inesistente',
				'ui.allow_hide_wp_menu' => '0',
				'audit.store_ip_hash'   => 'forse',
				'chiave.sconosciuta'    => 1,
			]
		);
		$this->assertSame( 'dark', $result['values']['ui.default_theme'] );
		$this->assertFalse( $result['values']['ui.allow_hide_wp_menu'] );
		$this->assertArrayHasKey( 'ui.default_palette', $result['errors'] );
		$this->assertArrayHasKey( 'audit.store_ip_hash', $result['errors'] );
		$this->assertSame( [ 'chiave.sconosciuta' ], $result['ignored'] );
	}

	public function test_delete_data_is_never_importable(): void {
		$result = Settings::validate( [ 'uninstall.delete_data' => true ], true );
		$this->assertSame( [], $result['values'] );
		$this->assertSame( [ 'uninstall.delete_data' ], $result['ignored'] );
	}

	public function test_preview_import_reports_changes_without_applying(): void {
		$current = Settings::defaults();
		$json    = json_encode(
			[
				'format'         => Settings::EXPORT_FORMAT,
				'schema_version' => 1,
				'settings'       => [
					'ui.default_theme'      => 'light',
					'ui.default_palette'    => Settings::defaults()['ui.default_palette'],
					'uninstall.delete_data' => true,
				],
			]
		);
		$preview = Settings::preview_import( (string) $json, $current );
		$this->assertTrue( $preview['ok'] );
		$this->assertSame( [ 'ui.default_theme' => [ 'auto', 'light' ] ], $preview['changes'] );
		$this->assertContains( 'uninstall.delete_data', $preview['ignored'] );
	}

	/**
	 * @dataProvider invalid_imports
	 */
	public function test_preview_import_rejects_invalid_files( string $json ): void {
		$this->assertFalse( Settings::preview_import( $json, Settings::defaults() )['ok'] );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalid_imports(): array {
		return [
			'non json'          => [ 'non è json' ],
			'formato estraneo'  => [ '{"format":"altro","settings":{}}' ],
			'schema futuro'     => [ '{"format":"bracelli-club-settings","schema_version":99,"settings":{}}' ],
			'settings mancanti' => [ '{"format":"bracelli-club-settings","schema_version":1}' ],
			'troppo grande'     => [ str_repeat( ' ', 70000 ) ],
		];
	}

	public function test_diff_only_lists_changed_keys(): void {
		$this->assertSame(
			[ 'b' => [ 1, 2 ] ],
			Settings::diff( [ 'a' => true, 'b' => 1 ], [ 'a' => true, 'b' => 2 ] )
		);
	}
}
