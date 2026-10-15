<?php
/**
 * Manifest del modulo Registro attività.
 *
 * @package BracelliClub
 */

defined( 'ABSPATH' ) || exit;

return [
	'id'           => 'audit',
	'version'      => '0.1.0',
	'name'         => 'Registro attività',
	'description'  => 'Chi ha fatto cosa e quando, con dati personali mascherati; consultazione con filtri.',
	'class'        => Bracelli\Club\Modules\Audit\AuditModule::class,
	'required'     => true,
	'requires'     => [
		'core'        => '^0.1',
		'permissions' => '^0.1',
	],
	'capabilities' => [ 'bracelli_audit_view' ],
	'min_php'      => '8.1',
	'min_wp'       => '6.9',
];
