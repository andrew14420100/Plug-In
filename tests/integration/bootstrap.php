<?php
/**
 * Bootstrap dei test di integrazione con la test suite ufficiale di WordPress.
 * Richiede WP_TESTS_DIR (vedi bin/install-wp-tests.sh).
 *
 * @package BracelliClub
 */

$bracelli_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $bracelli_tests_dir ) {
	$bracelli_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}
if ( ! file_exists( $bracelli_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "Test suite WordPress non trovata in {$bracelli_tests_dir}. Esegui bin/install-wp-tests.sh.\n" );
	exit( 1 );
}

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );

require_once $bracelli_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__, 2 ) . '/plugin/bracelli-club/bracelli-club.php';
	}
);

// Le tabelle del plugin vanno create prima che i test aprano transazioni (tabelle reali, non temporanee).
tests_add_filter(
	'init',
	static function (): void {
		\Bracelli\Club\Core\Lifecycle::activate();
	},
	1
);

require $bracelli_tests_dir . '/includes/bootstrap.php';
require_once __DIR__ . '/TestCase.php';
