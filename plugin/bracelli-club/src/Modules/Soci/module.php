<?php
/**
 * Manifest del modulo Archivio soci.
 *
 * @package BracelliClub
 */

defined( 'ABSPATH' ) || exit;

return [
	'id'             => 'soci',
	'version'        => '0.1.0',
	'name'           => 'Archivio soci',
	'description'    => 'Anagrafiche dei soci, ricerca, aggiornamento e archiviazione senza cancellazione dei record.',
	'class'          => Bracelli\Club\Modules\Soci\SociModule::class,
	'required'       => false,
	'default_active' => true,
	'requires'       => [
		'core'        => '^0.1',
		'permissions' => '^0.1',
		'audit'       => '^0.1',
		'area'        => '^0.1',
	],
	'capabilities'   => [ 'bracelli_soci_manage' ],
	'min_php'        => '8.1',
	'min_wp'         => '6.9',
];
