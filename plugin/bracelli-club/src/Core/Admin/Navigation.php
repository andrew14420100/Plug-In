<?php
/**
 * Voci di navigazione del plugin e pagine candidabili come preferiti.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Admin;

use Bracelli\Club\Core\Permissions\Capabilities;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Le voci sono filtrate per capability e stato dei moduli: la UI non mostra ciò che il server rifiuterebbe.
 */
final class Navigation {

	/**
	 * Tutte le voci Bracelli note.
	 *
	 * @return array<int, array{id: string, label: string, slug: string, icon: string, cap: string, module: string}>
	 */
	public static function definitions(): array {
		return [
			[
				'id'     => 'dashboard',
				'label'  => __( 'Dashboard', 'bracelli-club' ),
				'slug'   => Pages::PARENT,
				'icon'   => 'home',
				'cap'    => Capabilities::DASHBOARD_VIEW,
				'module' => 'dashboard',
			],
			[
				'id'     => 'modules',
				'label'  => __( 'Moduli', 'bracelli-club' ),
				'slug'   => 'bracelli-modules',
				'icon'   => 'grid',
				'cap'    => Capabilities::MODULES_MANAGE,
				'module' => 'core',
			],
			[
				'id'     => 'settings',
				'label'  => __( 'Impostazioni', 'bracelli-club' ),
				'slug'   => 'bracelli-settings',
				'icon'   => 'settings',
				'cap'    => Capabilities::SETTINGS_MANAGE,
				'module' => 'core',
			],
			[
				'id'     => 'permissions',
				'label'  => __( 'Permessi', 'bracelli-club' ),
				'slug'   => 'bracelli-permissions',
				'icon'   => 'shield',
				'cap'    => Capabilities::PERMISSIONS_MANAGE,
				'module' => 'permissions',
			],
			[
				'id'     => 'audit',
				'label'  => __( 'Registro attività', 'bracelli-club' ),
				'slug'   => 'bracelli-audit',
				'icon'   => 'list',
				'cap'    => Capabilities::AUDIT_VIEW,
				'module' => 'audit',
			],
			[
				'id'     => 'diagnostics',
				'label'  => __( 'Diagnostica', 'bracelli-club' ),
				'slug'   => 'bracelli-diagnostics',
				'icon'   => 'pulse',
				'cap'    => Capabilities::DIAGNOSTICS_RUN,
				'module' => 'diagnostics',
			],
			[
				'id'     => 'recovery',
				'label'  => __( 'Ripristino', 'bracelli-club' ),
				'slug'   => 'bracelli-recovery',
				'icon'   => 'lifebuoy',
				'cap'    => 'manage_options',
				'module' => 'recovery',
			],
			// In coda: la dashboard aggiunge in fondo le voci che l'utente non ha ancora ordinato.
			[
				'id'     => 'turni',
				'label'  => __( 'Turni e presenze', 'bracelli-club' ),
				'slug'   => 'bracelli-turni',
				'icon'   => 'calendar',
				'cap'    => Capabilities::TURNI_MANAGE,
				'module' => 'turni',
			],
		];
	}

	/**
	 * Voci disponibili per l'utente corrente.
	 *
	 * @return array<int, array{id: string, label: string, url: string, icon: string}>
	 */
	public static function items_for_current_user(): array {
		$plugin  = Plugin::instance();
		$modules = $plugin->modules;
		$safe    = $plugin->is_safe_mode();
		$items   = [];
		foreach ( self::definitions() as $def ) {
			if ( $safe && 'recovery' !== $def['id'] ) {
				continue;
			}
			if ( 'dashboard' !== $def['id'] && ! $modules->is_active( $def['module'] ) ) {
				continue;
			}
			if ( ! current_user_can( $def['cap'] ) ) {
				continue;
			}
			$items[] = [
				'id'    => $def['id'],
				'label' => $def['label'],
				'url'   => Pages::url( $def['slug'] ),
				'icon'  => $def['icon'],
			];
		}
		return $items;
	}

	/**
	 * Pagine che l'utente corrente può aggiungere ai preferiti (elenco chiuso, niente URL arbitrari).
	 *
	 * @return array<string, array{id: string, label: string, url: string, icon: string}>
	 */
	public static function favorite_candidates(): array {
		$out = [];
		foreach ( self::items_for_current_user() as $item ) {
			$out[ 'bc:' . $item['id'] ] = [
				'id'    => 'bc:' . $item['id'],
				'label' => $item['label'],
				'url'   => $item['url'],
				'icon'  => $item['icon'],
			];
		}
		$wp_pages = [
			[ 'index.php', __( 'Bacheca WordPress', 'bracelli-club' ), 'read', 'home' ],
			[ 'profile.php', __( 'Il mio profilo', 'bracelli-club' ), 'read', 'user' ],
			[ 'edit.php?post_type=page', __( 'Pagine', 'bracelli-club' ), 'edit_pages', 'document' ],
			[ 'edit.php', __( 'Articoli', 'bracelli-club' ), 'edit_posts', 'document' ],
			[ 'upload.php', __( 'Media', 'bracelli-club' ), 'upload_files', 'image' ],
			[ 'users.php', __( 'Utenti', 'bracelli-club' ), 'list_users', 'users' ],
			[ 'plugins.php', __( 'Plugin', 'bracelli-club' ), 'activate_plugins', 'grid' ],
			[ 'site-health.php', __( 'Salute del sito', 'bracelli-club' ), 'view_site_health_checks', 'pulse' ],
			[ 'options-general.php', __( 'Impostazioni WordPress', 'bracelli-club' ), 'manage_options', 'settings' ],
		];
		foreach ( $wp_pages as [ $path, $label, $cap, $icon ] ) {
			if ( current_user_can( $cap ) ) {
				$id         = 'wp:' . $path;
				$out[ $id ] = [
					'id'    => $id,
					'label' => $label,
					'url'   => admin_url( $path ),
					'icon'  => $icon,
				];
			}
		}
		return $out;
	}
}
