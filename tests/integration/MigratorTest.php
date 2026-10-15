<?php
/**
 * Migrazioni su un prefisso di tabelle dedicato (tabelle reali, rimosse a fine test).
 *
 * @package BracelliClub
 */

namespace Bracelli\Club\Tests\integration;

use Bracelli\Club\Core\Migrations\MigrationInterface;
use Bracelli\Club\Core\Migrations\Migrator;

final class FlakyMigration implements MigrationInterface {

	public static bool $fail = true;

	public static int $runs = 0;

	public function key(): string {
		return '2099_01_0002_flaky';
	}

	public function module(): string {
		return 'test';
	}

	public function description(): string {
		return 'Fallisce finché richiesto.';
	}

	public function up( \wpdb $db ): void {
		++self::$runs;
		if ( self::$fail ) {
			throw new \RuntimeException( 'errore simulato' );
		}
	}
}

final class CreateTableMigration implements MigrationInterface {

	public static int $runs = 0;

	public function key(): string {
		return '2099_01_0001_create_sample';
	}

	public function module(): string {
		return 'test';
	}

	public function description(): string {
		return 'Crea una tabella di prova.';
	}

	public function up( \wpdb $db ): void {
		++self::$runs;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( "CREATE TABLE {$db->prefix}bc_sample ( id bigint(20) unsigned NOT NULL AUTO_INCREMENT, PRIMARY KEY  (id) ) {$db->get_charset_collate()};" );
	}
}

final class MigratorTest extends TestCase {

	private \wpdb $db;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		// Le DDL di questo test devono creare tabelle reali.
		remove_filter( 'query', [ $this, '_create_temporary_tables' ] );
		remove_filter( 'query', [ $this, '_drop_temporary_tables' ] );
		// Connessione separata: le sue scritture non toccano la transazione del test.
		$this->db = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$this->db->set_prefix( $wpdb->prefix . 'bcm_' );
		$this->drop_tables();
		FlakyMigration::$fail       = true;
		FlakyMigration::$runs       = 0;
		CreateTableMigration::$runs = 0;
	}

	public function tear_down(): void {
		global $wpdb;
		// Prima il rollback (rilascia i lock della transazione del test), poi la pulizia sulla connessione separata:
		// le DDL hanno chiuso implicitamente la transazione, quindi alcune scritture sono già definitive.
		parent::tear_down();
		$this->drop_tables();
		$this->db->query( "DELETE FROM {$wpdb->options} WHERE option_name IN ('bracelli_club_test_schema', 'bracelli_club_test_result')" ); // phpcs:ignore
		$this->db->close();
	}

	private function drop_tables(): void {
		foreach ( [ 'bc_migrations', 'bc_sample' ] as $table ) {
			$this->db->query( "DROP TABLE IF EXISTS {$this->db->prefix}{$table}" ); // phpcs:ignore
		}
	}

	private function migrator(): Migrator {
		return new Migrator( $this->db, [ new CreateTableMigration(), new FlakyMigration() ], 'bracelli_club_test_schema', 'bracelli_club_test_result' );
	}

	public function test_failure_is_recorded_and_retry_completes_without_rerunning_done_steps(): void {
		$migrator = $this->migrator();
		$this->assertSame( [ '2099_01_0001_create_sample', '2099_01_0002_flaky' ], $migrator->pending() );

		$first = $migrator->run();
		$this->assertSame( 'failed', $first['status'] );
		$this->assertSame( [ '2099_01_0001_create_sample' ], $first['ran'] );
		$this->assertStringContainsString( 'errore simulato', $first['error'] );
		$applied = $migrator->applied();
		$this->assertSame( 'done', $applied['2099_01_0001_create_sample']['status'] );
		$this->assertSame( 'failed', $applied['2099_01_0002_flaky']['status'] );
		$this->assertSame( [ '2099_01_0002_flaky' ], $migrator->pending() );

		FlakyMigration::$fail = false;
		$second               = $migrator->run();
		$this->assertSame( 'ok', $second['status'] );
		$this->assertSame( [ '2099_01_0002_flaky' ], $second['ran'] );
		$this->assertSame( 1, CreateTableMigration::$runs, 'Le migrazioni completate non vengono rieseguite.' );
		$this->assertSame( [], $migrator->pending() );
		$this->assertSame( 'nothing', $migrator->run()['status'] );
		$this->assertSame( [], $migrator->modified_after_apply() );
		$this->assertSame(
			[
				'table_exists' => true,
				'pending'      => [],
				'modified'     => [],
			],
			$migrator->state()
		);
	}

	public function test_concurrent_run_is_blocked_by_database_lock(): void {
		$migrator = $this->migrator();
		$other    = mysqli_init();
		$host     = DB_HOST;
		$port     = null;
		if ( str_contains( $host, ':' ) ) {
			[ $host, $port ] = explode( ':', $host, 2 );
		}
		$this->assertTrue( mysqli_real_connect( $other, $host, DB_USER, DB_PASSWORD, DB_NAME, null === $port ? null : (int) $port ) );
		$name = mysqli_real_escape_string( $other, $migrator->lock_name() );
		$this->assertSame( '1', (string) mysqli_query( $other, "SELECT GET_LOCK('{$name}', 0)" )->fetch_row()[0] );

		$result = $migrator->run();
		$this->assertSame( 'locked', $result['status'] );
		$this->assertSame( 0, CreateTableMigration::$runs );

		mysqli_query( $other, "SELECT RELEASE_LOCK('{$name}')" );
		mysqli_close( $other );
		FlakyMigration::$fail = false;
		$this->assertSame( 'ok', $migrator->run()['status'] );
	}

	public function test_fingerprint_changes_when_migrations_are_added(): void {
		$one = new Migrator( $this->db, [ new CreateTableMigration() ], 'bracelli_club_test_schema', 'bracelli_club_test_result' );
		$two = $this->migrator();
		$this->assertNotSame( $one->fingerprint(), $two->fingerprint() );
	}
}
