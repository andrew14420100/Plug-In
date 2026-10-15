<?php
/**
 * Manifest del modulo Diagnostica.
 *
 * @package BracelliClub
 */

defined( 'ABSPATH' ) || exit;

return [
	'id'             => 'diagnostics',
	'version'        => '0.1.0',
	'name'           => 'Diagnostica',
	'description'    => 'Versioni, limiti dell\'hosting, stato di moduli e database; analisi approfondita solo su richiesta.',
	'class'          => Bracelli\Club\Modules\Diagnostics\DiagnosticsModule::class,
	'required'       => false,
	'default_active' => true,
	'requires'       => [
		'core'  => '^0.1',
		'audit' => '^0.1',
	],
	'capabilities'   => [ 'bracelli_diagnostics_run' ],
	'min_php'        => '8.1',
	'min_wp'         => '6.9',
];
