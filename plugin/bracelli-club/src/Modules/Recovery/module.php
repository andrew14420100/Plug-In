<?php
/**
 * Manifest del modulo Ripristino.
 *
 * @package BracelliClub
 */

defined( 'ABSPATH' ) || exit;

return [
	'id'           => 'recovery',
	'version'      => '0.1.0',
	'name'         => 'Ripristino',
	'description'  => 'Pagina di emergenza senza JavaScript: disattiva moduli, ripristina il menu WordPress e le preferenze.',
	'class'        => Bracelli\Club\Modules\Recovery\RecoveryModule::class,
	'required'     => true,
	'requires'     => [],
	'capabilities' => [],
	'min_php'      => '8.1',
	'min_wp'       => '6.9',
	'safe_mode'    => true,
];
