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

	public const ACTION_CONVERT     = 'bracelli_club_legacy_convert';
	public const ACTION_DROP_LEGACY = 'bracelli_club_legacy_drop';

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
		add_action( 'admin_post_' . self::ACTION_CONVERT, [ $this, 'handle_convert' ] );
		add_action( 'admin_post_' . self::ACTION_DROP_LEGACY, [ $this, 'handle_drop_legacy' ] );
		add_filter( 'editable_roles', [ Roles::class, 'filter_editable' ] );
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
				'converted' => [ 'success', __( 'Utente convertito in Segreteria Bracelli. La modifica è nel registro attività.', 'bracelli-club' ) ],
				'confirm'   => [ 'error', __( 'Spunta la conferma: nessuna modifica eseguita.', 'bracelli-club' ) ],
				'dropped'   => [ 'success', __( 'Ruolo "Gestore corsi" eliminato (nessun utente lo aveva).', 'bracelli-club' ) ],
			]
		);

		$caps  = Capabilities::all();
		$roles = Roles::existing();

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
			if ( in_array( $cap, Capabilities::NOT_FOR_ADMIN, true ) ) {
				echo '<span class="bc-muted" title="' . esc_attr__( 'Non applicabile: indica un dipendente, non un privilegio', 'bracelli-club' ) . '">—</span></td>';
			} else {
				Icons::render( 'check' );
				echo '<span class="screen-reader-text">' . esc_html__( 'Sempre concessa', 'bracelli-club' ) . '</span></td>';
			}
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
		$this->render_legacy();
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
		foreach ( array_keys( Roles::existing() ) as $slug ) {
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
		if ( ! array_key_exists( $slug, Roles::existing() ) ) {
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
	/**
	 * Ruolo "Gestore corsi" non più usato: utenti che lo hanno e conversione su conferma, una persona alla volta.
	 * Nessuna conversione automatica: i permessi di questi utenti restano invariati finché l'amministratore non decide.
	 */
	private function render_legacy(): void {
		$role = get_role( Roles::GESTORE_CORSI );
		if ( null === $role ) {
			return;
		}
		$users = get_users(
			[
				'role'    => Roles::GESTORE_CORSI,
				'orderby' => 'display_name',
				'fields'  => [ 'ID', 'display_name', 'user_login' ],
			]
		);
		echo '<div class="bc-card"><h2>' . esc_html__( 'Ruolo "Gestore corsi" (non più usato)', 'bracelli-club' ) . '</h2>';
		echo '<p class="bc-muted">' . esc_html__( 'Il ruolo non si assegna più a nuovi utenti. Chi lo ha già continua a gestire i corsi come prima, finché non decidi di convertirlo.', 'bracelli-club' ) . '</p>';
		if ( [] === $users ) {
			echo '<p>' . esc_html__( 'Nessun utente ha questo ruolo. Puoi eliminarlo: non cambia i permessi di nessuno.', 'bracelli-club' ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_DROP_LEGACY ) . '">';
			wp_nonce_field( self::ACTION_DROP_LEGACY );
			echo '<p><label><input type="checkbox" name="confirm" value="1"> ' . esc_html__( 'Confermo l\'eliminazione del ruolo "Gestore corsi".', 'bracelli-club' ) . '</label></p>';
			echo '<button type="submit" class="bc-button bc-button--ghost">' . esc_html__( 'Elimina il ruolo', 'bracelli-club' ) . '</button></form></div>';
			return;
		}
		/* translators: %d: numero di utenti. */
		echo '<p>' . esc_html( sprintf( _n( '%d utente ha ancora questo ruolo.', '%d utenti hanno ancora questo ruolo.', count( $users ), 'bracelli-club' ), count( $users ) ) ) . ' ' . esc_html__( 'Convertire un utente in "Segreteria Bracelli" gli toglie "Gestore corsi" e gli dà i permessi della segreteria, che comprendono anche la gestione dei turni del personale.', 'bracelli-club' ) . '</p>';
		echo '<div class="bc-table-scroll"><table class="bc-table"><thead><tr><th scope="col">' . esc_html__( 'Utente', 'bracelli-club' ) . '</th><th scope="col">' . esc_html__( 'Conversione', 'bracelli-club' ) . '</th></tr></thead><tbody>';
		foreach ( $users as $user ) {
			$id = (int) $user->ID;
			echo '<tr><td>' . esc_html( (string) $user->display_name ) . ' <span class="bc-muted">(' . esc_html( (string) $user->user_login ) . ')</span></td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_CONVERT ) . '"><input type="hidden" name="user_id" value="' . esc_attr( (string) $id ) . '">';
			wp_nonce_field( self::ACTION_CONVERT . '_' . $id );
			/* translators: %s: nome dell'utente. */
			echo '<label><input type="checkbox" name="confirm" value="1"> ' . esc_html( sprintf( __( 'Confermo: %s diventa Segreteria Bracelli', 'bracelli-club' ), (string) $user->display_name ) ) . '</label> ';
			echo '<button type="submit" class="bc-button bc-button--ghost">' . esc_html__( 'Converti', 'bracelli-club' ) . '</button></form></td></tr>';
		}
		echo '</tbody></table></div></div>';
	}

	/**
	 * Converte un utente "Gestore corsi" in "Segreteria Bracelli" (solo con conferma esplicita).
	 */
	public function handle_convert(): void {
		Layout::require_cap( Capabilities::PERMISSIONS_MANAGE );
		$id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		check_admin_referer( self::ACTION_CONVERT . '_' . $id );
		if ( ! current_user_can( 'promote_user', $id ) ) {
			Layout::redirect( self::SLUG, 'failed' );
		}
		if ( empty( $_POST['confirm'] ) ) {
			Layout::redirect( self::SLUG, 'confirm' );
		}
		$user = get_userdata( $id );
		if ( false === $user || ! in_array( Roles::GESTORE_CORSI, (array) $user->roles, true ) || null === get_role( Roles::SEGRETERIA ) ) {
			Layout::redirect( self::SLUG, 'failed' );
		}
		$before = array_values( (array) $user->roles );
		$user->remove_role( Roles::GESTORE_CORSI );
		if ( ! in_array( Roles::SEGRETERIA, (array) $user->roles, true ) ) {
			$user->add_role( Roles::SEGRETERIA );
		}
		$this->plugin->audit->log(
			'roles.legacy_converted',
			[
				'module'        => 'permissions',
				'resource_type' => 'user',
				'resource_id'   => (string) $id,
				'diff'          => [
					'before' => $before,
					'after'  => array_values( (array) get_userdata( $id )->roles ),
				],
			]
		);
		Layout::redirect( self::SLUG, 'converted' );
	}

	/**
	 * Elimina il ruolo "Gestore corsi" solo se nessun utente lo ha e con conferma esplicita.
	 */
	public function handle_drop_legacy(): void {
		Layout::require_cap( Capabilities::PERMISSIONS_MANAGE );
		check_admin_referer( self::ACTION_DROP_LEGACY );
		if ( empty( $_POST['confirm'] ) ) {
			Layout::redirect( self::SLUG, 'confirm' );
		}
		$count = count(
			get_users(
				[
					'role'   => Roles::GESTORE_CORSI,
					'fields' => 'ID',
					'number' => 1,
				]
			)
		);
		if ( null === get_role( Roles::GESTORE_CORSI ) || $count > 0 ) {
			Layout::redirect( self::SLUG, 'failed' );
		}
		remove_role( Roles::GESTORE_CORSI );
		$this->plugin->audit->log(
			'roles.legacy_dropped',
			[
				'module'        => 'permissions',
				'resource_type' => 'role',
				'resource_id'   => Roles::GESTORE_CORSI,
			]
		);
		Layout::redirect( self::SLUG, 'dropped' );
	}
}
