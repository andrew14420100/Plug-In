<?php
/**
 * Modulo Permessi: matrice ruoli × capability.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Permissions;

use Bracelli\Club\Core\Admin\Layout;
use Bracelli\Club\Core\Admin\Pages;
use Bracelli\Club\Core\Modules\ModuleInterface;
use Bracelli\Club\Core\Permissions\Capabilities;
use Bracelli\Club\Core\Permissions\Roles;
use Bracelli\Club\Core\Ui\Icons;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Solo i ruoli Bracelli sono modificabili; l'amministratore conserva sempre tutte le capability
 * e "Gestione permessi" non è assegnabile ad altri ruoli (evita escalation di privilegi).
 */
final class PermissionsModule implements ModuleInterface {

	public const SLUG         = 'bracelli-permissions';
	public const ACTION_SAVE  = 'bracelli_club_permissions_save';
	public const ACTION_RESET = 'bracelli_club_permissions_reset';

	/**
	 * Servizi.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * {@inheritDoc}
	 */
	public function boot( Plugin $plugin ): void {
		$this->plugin = $plugin;
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_post_' . self::ACTION_SAVE, [ $this, 'handle_save' ] );
		add_action( 'admin_post_' . self::ACTION_RESET, [ $this, 'handle_reset' ] );
	}

	/**
	 * Voce di menu.
	 */
	public function menu(): void {
		Pages::add( self::SLUG, __( 'Permessi', 'bracelli-club' ), __( 'Permessi', 'bracelli-club' ), Capabilities::PERMISSIONS_MANAGE, [ $this, 'render' ], 30 );
	}

	/**
	 * Rendering.
	 */
	public function render(): void {
		Layout::require_cap( Capabilities::PERMISSIONS_MANAGE );
		Layout::open( __( 'Permessi', 'bracelli-club' ), 'permissions', __( 'Capability dei ruoli Bracelli. I controlli avvengono sempre sul server, non solo nell\'interfaccia.', 'bracelli-club' ) );
		Layout::query_notice(
			[
				'saved'     => [ 'success', __( 'Permessi aggiornati.', 'bracelli-club' ) ],
				'unchanged' => [ 'info', __( 'Nessuna modifica.', 'bracelli-club' ) ],
				'reset'     => [ 'success', __( 'Permessi predefiniti ripristinati.', 'bracelli-club' ) ],
				'failed'    => [ 'error', __( 'Operazione non valida.', 'bracelli-club' ) ],
			]
		);

		$caps  = Capabilities::all();
		$roles = Roles::definitions();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="bc-card">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SAVE ) . '">';
		wp_nonce_field( self::ACTION_SAVE );
		echo '<div class="bc-table-scroll"><table class="bc-table bc-matrix"><caption class="screen-reader-text">' . esc_html__( 'Matrice dei permessi', 'bracelli-club' ) . '</caption><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Capability', 'bracelli-club' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Amministratore', 'bracelli-club' ) . '</th>';
		foreach ( $roles as $slug => $role ) {
			echo '<th scope="col">' . esc_html( translate_user_role( $role['label'] ) ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		$current = [];
		foreach ( array_keys( $roles ) as $slug ) {
			$current[ $slug ] = Roles::role_caps( $slug );
		}
		foreach ( $caps as $cap => $def ) {
			echo '<tr><th scope="row"><span class="bc-cap-label">' . esc_html( $def['label'] );
			if ( $def['sensitive'] ) {
				echo ' <span class="bc-badge bc-badge--warning">' . esc_html__( 'Sensibile', 'bracelli-club' ) . '</span>';
			}
			echo '</span><br><span class="bc-muted">' . esc_html( $def['description'] ) . '</span><br><code>' . esc_html( $cap ) . '</code></th>';
			echo '<td class="bc-center">';
			Icons::render( 'check' );
			echo '<span class="screen-reader-text">' . esc_html__( 'Sempre concessa', 'bracelli-club' ) . '</span></td>';
			foreach ( array_keys( $roles ) as $slug ) {
				$id = 'bc-cap-' . $slug . '-' . $cap;
				echo '<td class="bc-center">';
				if ( $def['grantable'] ) {
					printf(
						'<label class="screen-reader-text" for="%1$s">%2$s</label><input type="checkbox" id="%1$s" name="caps[%3$s][]" value="%4$s"%5$s>',
						esc_attr( $id ),
						esc_html( $def['label'] . ' — ' . translate_user_role( $roles[ $slug ]['label'] ) ),
						esc_attr( $slug ),
						esc_attr( $cap ),
						checked( in_array( $cap, $current[ $slug ], true ), true, false )
					);
				} else {
					echo '<span class="bc-muted" title="' . esc_attr__( 'Riservata agli amministratori', 'bracelli-club' ) . '">—</span>';
				}
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		echo '<p><button type="submit" class="bc-button bc-button--primary">' . esc_html__( 'Salva permessi', 'bracelli-club' ) . '</button></p></form>';

		echo '<div class="bc-card"><h2>' . esc_html__( 'Ripristina predefiniti', 'bracelli-club' ) . '</h2><div class="bc-row">';
		foreach ( $roles as $slug => $role ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="bc-inline-form">';
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_RESET ) . '">';
			echo '<input type="hidden" name="role" value="' . esc_attr( $slug ) . '">';
			wp_nonce_field( self::ACTION_RESET . '_' . $slug );
			echo '<button type="submit" class="bc-button bc-button--ghost">' . esc_html( translate_user_role( $role['label'] ) ) . '</button></form>';
		}
		echo '</div></div>';
		Layout::close();
	}

	/**
	 * Salvataggio della matrice.
	 */
	public function handle_save(): void {
		Layout::require_cap( Capabilities::PERMISSIONS_MANAGE );
		check_admin_referer( self::ACTION_SAVE );
		$posted = isset( $_POST['caps'] ) && is_array( $_POST['caps'] ) ? map_deep( wp_unslash( $_POST['caps'] ), 'sanitize_key' ) : [];
		$diff   = [];
		foreach ( Roles::slugs() as $slug ) {
			$wanted = isset( $posted[ $slug ] ) && is_array( $posted[ $slug ] ) ? array_map( 'strval', $posted[ $slug ] ) : [];
			$change = Roles::set_role_caps( $slug, $wanted );
			if ( [] !== $change['added'] || [] !== $change['removed'] ) {
				$diff[ $slug ] = $change;
			}
		}
		if ( [] === $diff ) {
			Layout::redirect( self::SLUG, 'unchanged' );
		}
		$this->plugin->audit->log(
			'permissions.updated',
			[
				'module'        => 'permissions',
				'resource_type' => 'role',
				'resource_id'   => implode( ',', array_keys( $diff ) ),
				'diff'          => $diff,
			]
		);
		Layout::redirect( self::SLUG, 'saved' );
	}

	/**
	 * Ripristino di un ruolo.
	 */
	public function handle_reset(): void {
		Layout::require_cap( Capabilities::PERMISSIONS_MANAGE );
		$slug = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
		check_admin_referer( self::ACTION_RESET . '_' . $slug );
		if ( ! in_array( $slug, Roles::slugs(), true ) ) {
			Layout::redirect( self::SLUG, 'failed' );
		}
		$change = Roles::reset_role( $slug );
		$this->plugin->audit->log(
			'permissions.reset',
			[
				'module'        => 'permissions',
				'resource_type' => 'role',
				'resource_id'   => $slug,
				'diff'          => $change,
			]
		);
		Layout::redirect( self::SLUG, 'reset' );
	}
}
