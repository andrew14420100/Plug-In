<?php
/**
 * Manifest del modulo Permessi.
 *
 * @package BracelliClub
 */

defined( 'ABSPATH' ) || exit;

return [
	'id'           => 'permissions',
	'version'      => '0.1.0',
	'name'         => 'Permessi',
	'description'  => 'Ruoli Bracelli e assegnazione delle capability, verificate sempre lato server.',
	'class'        => Bracelli\Club\Modules\Permissions\PermissionsModule::class,
	'required'     => true,
	'requires'     => [ 'core' => '^0.1' ],
	'capabilities' => [ 'bracelli_permissions_manage' ],
	'min_php'      => '8.1',
	'min_wp'       => '6.9',
];
