<?php
/**
 * Manifest del modulo Turni e presenze (prototipo).
 *
 * @package BracelliClub
 */

defined( 'ABSPATH' ) || exit;

return [
	'id'             => 'turni',
	'version'        => '0.1.0',
	'name'           => 'Turni del personale (prototipo)',
	'description'    => 'Sezione dell\'area riservata: la segreteria gestisce i turni dei dipendenti, ogni dipendente vede i propri e registra presenza o assenza.',
	'class'          => Bracelli\Club\Modules\Turni\TurniModule::class,
	'required'       => false,
	'default_active' => true,
	'requires'       => [
		'core'        => '^0.1',
		'permissions' => '^0.1',
		'audit'       => '^0.1',
		'area'        => '^0.1',
	],
	'capabilities'   => [ 'bracelli_turni_manage', 'bracelli_turni_self' ],
	'min_php'        => '8.1',
	'min_wp'         => '6.9',
];
