<?php
/**
 * Manifest del modulo Dashboard.
 *
 * @package BracelliClub
 */

defined( 'ABSPATH' ) || exit;

return [
	'id'             => 'dashboard',
	'version'        => '0.1.0',
	'name'           => 'Dashboard',
	'description'    => 'Dashboard Bracelli con contesti amministratore/dipendente, temi, tavolozze, widget e preferiti per utente.',
	'class'          => Bracelli\Club\Modules\Dashboard\DashboardModule::class,
	'required'       => false,
	'default_active' => true,
	'requires'       => [
		'core'        => '^0.1',
		'permissions' => '^0.1',
	],
	'optional'       => [ 'audit', 'diagnostics' ],
	'capabilities'   => [ 'bracelli_dashboard_view', 'bracelli_dashboard_admin_view' ],
	'min_php'        => '8.1',
	'min_wp'         => '6.9',
];
