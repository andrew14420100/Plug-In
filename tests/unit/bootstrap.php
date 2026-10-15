<?php
/**
 * Bootstrap dei test unitari: nessun WordPress, solo classi pure.
 *
 * @package BracelliClub
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require_once dirname( __DIR__, 2 ) . '/plugin/bracelli-club/src/autoload.php';
require_once __DIR__ . '/ArrayStateStore.php';
