<?php
/**
 * Manifest del modulo Corsi.
 *
 * @package BracelliClub
 */

defined( 'ABSPATH' ) || exit;

return [
	'id'             => 'corsi',
	'version'        => '0.1.0',
	'name'           => 'Corsi e presenze insegnanti',
	'description'    => 'Corsi e orari settimanali (importabili da file), calendario nell\'area riservata, lezioni dell\'insegnante con registrazione Presente/Assente e quadro per la segreteria.',
	'class'          => Bracelli\Club\Modules\Corsi\CorsiModule::class,
	'required'       => false,
	'default_active' => true,
	'requires'       => [
		'core'        => '^0.1',
		'permissions' => '^0.1',
		'audit'       => '^0.1',
		'area'        => '^0.1',
	],
	'capabilities'   => [ 'bracelli_corsi_view', 'bracelli_corsi_teach', 'bracelli_corsi_manage', 'bracelli_corsi_import' ],
	'min_php'        => '8.1',
	'min_wp'         => '6.9',
];
