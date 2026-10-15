<?php
/**
 * Manifest del modulo Nucleo.
 *
 * @package BracelliClub
 */

defined( 'ABSPATH' ) || exit;

return [
	'id'           => 'core',
	'version'      => '0.1.0',
	'name'         => 'Nucleo',
	'description'  => 'Menu Bracelli, catalogo moduli, impostazioni globali e migrazioni.',
	'class'        => Bracelli\Club\Modules\Core\CoreModule::class,
	'required'     => true,
	'requires'     => [],
	'capabilities' => [ 'bracelli_modules_manage', 'bracelli_settings_manage' ],
	'min_php'      => '8.1',
	'min_wp'       => '6.9',
];
