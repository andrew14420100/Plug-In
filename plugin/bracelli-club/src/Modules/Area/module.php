<?php
/**
 * Manifest del modulo Area riservata.
 *
 * @package BracelliClub
 */

defined( 'ABSPATH' ) || exit;

return [
	'id'             => 'area',
	'version'        => '0.1.0',
	'name'           => 'Area riservata',
	'description'    => 'Portale frontend unico per segreteria, insegnanti, dipendenti e soci: accesso, navigazione per ruolo e pulsante "Area riservata" con shortcode.',
	'class'          => Bracelli\Club\Modules\Area\AreaModule::class,
	'required'       => false,
	'default_active' => true,
	'requires'       => [
		'core'        => '^0.1',
		'permissions' => '^0.1',
		'audit'       => '^0.1',
	],
	'capabilities'   => [],
	'min_php'        => '8.1',
	'min_wp'         => '6.9',
];
