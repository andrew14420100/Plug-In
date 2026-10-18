<?php
/**
 * Disinstallazione: per impostazione predefinita NON elimina alcun dato (Q186).
 * I dati vengono rimossi solo se un amministratore ha attivato "Elimina tutti i dati alla disinstallazione".
 *
 * @package BracelliClub
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$bracelli_club_settings = get_option( 'bracelli_club_settings', array() );
if ( ! is_array( $bracelli_club_settings ) || true !== ( $bracelli_club_settings['uninstall.delete_data'] ?? false ) ) {
	return;
}

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- rimozione esplicita richiesta dall'amministratore.
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}bc_audit_events`" );
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}bc_migrations`" );
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}bc_turni_shifts`" );
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}bc_turni_attendance`" );
// phpcs:enable

foreach ( array( 'bracelli_club_settings', 'bracelli_club_modules', 'bracelli_club_schema', 'bracelli_club_last_migration', 'bracelli_club_errors', 'bracelli_club_installed_at' ) as $bracelli_club_option ) {
	delete_option( $bracelli_club_option );
}

delete_metadata( 'user', 0, 'bracelli_club_prefs', '', true );

foreach ( array( 'bracelli_segreteria', 'bracelli_istruttore', 'bracelli_gestore_corsi', 'bracelli_dipendente' ) as $bracelli_club_role ) {
	remove_role( $bracelli_club_role );
}
$bracelli_club_admin = get_role( 'administrator' );
if ( null !== $bracelli_club_admin ) {
	foreach ( array_keys( $bracelli_club_admin->capabilities ) as $bracelli_club_cap ) {
		if ( 0 === strpos( (string) $bracelli_club_cap, 'bracelli_' ) ) {
			$bracelli_club_admin->remove_cap( $bracelli_club_cap );
		}
	}
}
