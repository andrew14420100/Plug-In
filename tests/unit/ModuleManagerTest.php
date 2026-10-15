<?php
/**
 * @package BracelliClub
 */

namespace Bracelli\Club\Tests\unit;

use Bracelli\Club\Core\Modules\ModuleInterface;
use Bracelli\Club\Core\Modules\ModuleManager;
use Bracelli\Club\Core\Modules\ModuleManifest;
use PHPUnit\Framework\TestCase;

final class ModuleManagerTest extends TestCase {

	private ArrayStateStore $store;

	protected function setUp(): void {
		$this->store = new ArrayStateStore();
	}

	/**
	 * @param array<string, mixed> $data Dati del manifest.
	 */
	private static function manifest( array $data ): ModuleManifest {
		return ModuleManifest::from_array( $data + [ 'version' => '1.0.0' ] );
	}

	/**
	 * core (obbligatorio) ← base ← mid ← top; extra indipendente, disattivato di default.
	 *
	 * @return ModuleManifest[]
	 */
	private function graph(): array {
		return [
			self::manifest( [ 'id' => 'core', 'required' => true ] ),
			self::manifest( [ 'id' => 'base', 'requires' => [ 'core' => '^1.0' ] ] ),
			self::manifest( [ 'id' => 'mid', 'requires' => [ 'base' => '^1.0' ] ] ),
			self::manifest( [ 'id' => 'top', 'requires' => [ 'mid' => '*' ] ] ),
			self::manifest( [ 'id' => 'extra', 'default_active' => false ] ),
		];
	}

	private function manager( ?array $manifests = null, array $env = [ 'php' => '8.3.0', 'wp' => '7.1.0' ] ): ModuleManager {
		return new ModuleManager( $manifests ?? $this->graph(), $this->store, $env, static fn() => 7, static fn() => '2026-10-15 10:00:00' );
	}

	public function test_required_module_is_always_active_and_cannot_be_deactivated(): void {
		$manager = $this->manager();
		$this->assertTrue( $manager->is_active( 'core' ) );
		$result = $manager->deactivate( 'core', true );
		$this->assertFalse( $result['ok'] );
		$this->assertTrue( $manager->is_active( 'core' ) );
		$this->assertSame( 0, $this->store->saves );
	}

	public function test_default_states(): void {
		$manager = $this->manager();
		$this->assertSame( ModuleManager::STATUS_ACTIVE, $manager->status( 'top' ) );
		$this->assertSame( ModuleManager::STATUS_INACTIVE, $manager->status( 'extra' ) );
	}

	public function test_deactivation_with_dependents_requires_confirmation(): void {
		$manager = $this->manager();
		$result  = $manager->deactivate( 'base' );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( [ 'mid', 'top' ], $result['needs_confirmation'] );
		$this->assertTrue( $manager->is_active( 'base' ), 'Senza conferma nulla cambia.' );
		$this->assertSame( 0, $this->store->saves );
	}

	public function test_confirmed_cascade_deactivates_dependents_and_records_actor(): void {
		$manager = $this->manager();
		$result  = $manager->deactivate( 'base', true );
		$this->assertTrue( $result['ok'] );
		$this->assertEqualsCanonicalizing( [ 'base', 'mid', 'top' ], $result['changed'] );
		foreach ( [ 'base', 'mid', 'top' ] as $id ) {
			$this->assertSame( ModuleManager::STATUS_INACTIVE, $manager->status( $id ) );
			$this->assertSame( 7, $this->store->state[ $id ]['changed_by'] );
		}
		$this->assertTrue( $manager->is_active( 'core' ) );
	}

	public function test_activation_requires_enabled_dependencies(): void {
		$manager = $this->manager();
		$manager->deactivate( 'base', true );
		$result = $manager->activate( 'top' );
		$this->assertFalse( $result['ok'] );
		$this->assertStringContainsString( 'mid', implode( ' ', $result['errors'] ) );

		$this->assertTrue( $manager->activate( 'base' )['ok'] );
		$this->assertTrue( $manager->activate( 'mid' )['ok'] );
		$this->assertTrue( $manager->activate( 'top' )['ok'] );
		$this->assertTrue( $manager->is_active( 'top' ) );
	}

	public function test_operations_are_idempotent(): void {
		$manager = $this->manager();
		$first   = $manager->activate( 'extra' );
		$second  = $manager->activate( 'extra' );
		$this->assertSame( [ 'extra' ], $first['changed'] );
		$this->assertSame( [], $second['changed'] );
		$this->assertTrue( $second['ok'] );
		$this->assertSame( 1, $this->store->saves );

		$manager->deactivate( 'extra' );
		$again = $manager->deactivate( 'extra' );
		$this->assertTrue( $again['ok'] );
		$this->assertSame( [], $again['changed'] );
	}

	public function test_version_and_environment_constraints_block_modules(): void {
		$manifests = [
			self::manifest( [ 'id' => 'core', 'required' => true, 'version' => '1.0.0' ] ),
			self::manifest( [ 'id' => 'needs_v2', 'requires' => [ 'core' => '^2.0' ] ] ),
			self::manifest( [ 'id' => 'needs_php', 'min_php' => '9.0' ] ),
		];
		$manager = $this->manager( $manifests );
		$this->assertSame( ModuleManager::STATUS_BLOCKED, $manager->status( 'needs_v2' ) );
		$this->assertSame( ModuleManager::STATUS_BLOCKED, $manager->status( 'needs_php' ) );
		$this->assertNotEmpty( $manager->requirement_problems( 'needs_php' ) );
	}

	public function test_unknown_dependency_and_cycles_are_rejected(): void {
		try {
			$this->manager( [ self::manifest( [ 'id' => 'alpha', 'requires' => [ 'ghost' => '*' ] ] ) ] );
			$this->fail( 'Dipendenza sconosciuta non rilevata.' );
		} catch ( \InvalidArgumentException $e ) {
			$this->assertStringContainsString( 'ghost', $e->getMessage() );
		}
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/circolare/' );
		$this->manager(
			[
				self::manifest( [ 'id' => 'alpha', 'requires' => [ 'beta' => '*' ] ] ),
				self::manifest( [ 'id' => 'beta', 'requires' => [ 'alpha' => '*' ] ] ),
			]
		);
	}

	public function test_boot_order_and_failure_isolation(): void {
		$manager = $this->manager();
		$order   = $manager->boot_order();
		$this->assertLessThan( array_search( 'mid', $order, true ), array_search( 'base', $order, true ) );
		$this->assertLessThan( array_search( 'top', $order, true ), array_search( 'mid', $order, true ) );

		$booted = [];
		$errors = [];
		$module = new class() implements ModuleInterface {
			public function boot( \Bracelli\Club\Plugin $plugin ): void {}
		};
		$started = $manager->boot(
			static function ( ModuleManifest $m ) use ( $module ) {
				if ( 'base' === $m->id ) {
					throw new \RuntimeException( 'boom' );
				}
				return $module;
			},
			static function () use ( &$booted ): void {
				$booted[] = true;
			},
			false,
			static function ( string $id, \Throwable $e ) use ( &$errors ): void {
				$errors[ $id ] = $e->getMessage();
			}
		);
		$this->assertSame( [ 'base' => 'boom' ], $errors );
		$this->assertSame( ModuleManager::STATUS_DEGRADED, $manager->status( 'base' ) );
		$this->assertSame( ModuleManager::STATUS_BLOCKED, $manager->status( 'mid' ), 'Un dipendente di un modulo in errore non si avvia.' );
		$this->assertContains( 'core', $started );
		$this->assertNotContains( 'mid', $started );
		$this->assertSame( 'boom', $manager->degraded_reason( 'base' ) );
	}

	public function test_safe_mode_boots_only_safe_modules(): void {
		$manifests = [
			self::manifest( [ 'id' => 'core', 'required' => true ] ),
			self::manifest( [ 'id' => 'recovery', 'required' => true, 'safe_mode' => true ] ),
			self::manifest( [ 'id' => 'ui' ] ),
		];
		$started = $this->manager( $manifests )->boot( static fn() => null, static function (): void {}, true );
		$this->assertSame( [ 'recovery' ], $started );
	}

	public function test_invalid_manifests_are_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		ModuleManifest::from_array( [ 'id' => 'Bad Id', 'version' => '1.0.0' ] );
	}

	public function test_duplicate_ids_are_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->manager( [ self::manifest( [ 'id' => 'alpha' ] ), self::manifest( [ 'id' => 'alpha' ] ) ] );
	}
}
