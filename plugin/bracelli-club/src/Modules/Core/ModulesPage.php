<?php
/**
 * Catalogo moduli: stato, dipendenze, attivazione e disattivazione con conferma.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Core;

use Bracelli\Club\Core\Admin\Layout;
use Bracelli\Club\Core\Admin\Pages;
use Bracelli\Club\Core\Modules\ModuleManager;
use Bracelli\Club\Core\Permissions\Capabilities;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Pagina "Moduli" (funziona senza JavaScript; azioni via admin-post con nonce + capability).
 */
final class ModulesPage {

	public const SLUG   = 'bracelli-modules';
	public const ACTION = 'bracelli_club_module';

	/**
	 * @param Plugin $plugin Servizi.
	 */
	public function __construct( private Plugin $plugin ) {
	}

	/**
	 * Hook.
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
	}

	/**
	 * Voce di menu.
	 */
	public function menu(): void {
		Pages::add( self::SLUG, __( 'Moduli', 'bracelli-club' ), __( 'Moduli', 'bracelli-club' ), Capabilities::MODULES_MANAGE, [ $this, 'render' ], 10 );
	}

	/**
	 * Etichette di stato.
	 *
	 * @return array<string, string>
	 */
	public static function status_labels(): array {
		return [
			ModuleManager::STATUS_ACTIVE   => __( 'Attivo', 'bracelli-club' ),
			ModuleManager::STATUS_INACTIVE => __( 'Disattivato', 'bracelli-club' ),
			ModuleManager::STATUS_BLOCKED  => __( 'Bloccato', 'bracelli-club' ),
			ModuleManager::STATUS_DEGRADED => __( 'Errore', 'bracelli-club' ),
		];
	}

	/**
	 * Rendering.
	 */
	public function render(): void {
		Layout::require_cap( Capabilities::MODULES_MANAGE );
		$manager = $this->plugin->modules;
		Layout::open( __( 'Moduli', 'bracelli-club' ), 'modules', __( 'Stato, versioni e dipendenze. La disattivazione non elimina i dati.', 'bracelli-club' ) );
		Layout::query_notice(
			[
				'activated'   => [ 'success', __( 'Modulo attivato.', 'bracelli-club' ) ],
				'deactivated' => [ 'success', __( 'Modulo disattivato. I dati restano conservati.', 'bracelli-club' ) ],
				'unchanged'   => [ 'info', __( 'Nessuna modifica: il modulo era già in quello stato.', 'bracelli-club' ) ],
				'failed'      => [ 'error', __( 'Operazione non eseguita: requisiti non soddisfatti o modulo obbligatorio.', 'bracelli-club' ) ],
			]
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo visualizzazione della richiesta di conferma; l'azione richiede nonce.
		$confirm = isset( $_GET['confirm'] ) ? sanitize_key( wp_unslash( $_GET['confirm'] ) ) : '';
		if ( '' !== $confirm && null !== $manager->manifest( $confirm ) ) {
			$this->render_confirmation( $confirm );
		}

		$labels = self::status_labels();
		echo '<div class="bc-card"><table class="bc-table"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Modulo', 'bracelli-club' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Versione', 'bracelli-club' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Stato', 'bracelli-club' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Dipendenze', 'bracelli-club' ) . '</th>';
		echo '<th scope="col"><span class="screen-reader-text">' . esc_html__( 'Azioni', 'bracelli-club' ) . '</span></th>';
		echo '</tr></thead><tbody>';
		foreach ( $manager->manifests() as $id => $manifest ) {
			$status = $manager->status( $id );
			echo '<tr><td><strong>' . esc_html( $manifest->name ) . '</strong>';
			if ( $manifest->required ) {
				echo ' <span class="bc-badge">' . esc_html__( 'Obbligatorio', 'bracelli-club' ) . '</span>';
			}
			echo '<br><span class="bc-muted">' . esc_html( $manifest->description ) . '</span></td>';
			echo '<td><code>' . esc_html( $manifest->version ) . '</code></td>';
			echo '<td><span class="bc-status bc-status--' . esc_attr( $status ) . '">' . esc_html( $labels[ $status ] ?? $status ) . '</span>';
			$reason = $manager->degraded_reason( $id );
			if ( null !== $reason ) {
				echo '<br><span class="bc-muted">' . esc_html( $reason ) . '</span>';
			}
			if ( ModuleManager::STATUS_BLOCKED === $status ) {
				echo '<br><span class="bc-muted">' . esc_html( implode( '; ', $manager->requirement_problems( $id ) ) ) . '</span>';
			}
			echo '</td><td>';
			$deps = array_keys( $manifest->requires );
			echo '' === implode( '', $deps ) ? '—' : esc_html( implode( ', ', $deps ) );
			if ( [] !== $manifest->optional ) {
				echo '<br><span class="bc-muted">' . esc_html__( 'Facoltative:', 'bracelli-club' ) . ' ' . esc_html( implode( ', ', $manifest->optional ) ) . '</span>';
			}
			echo '</td><td class="bc-actions">';
			if ( ! $manifest->required ) {
				$enabled = $manager->is_enabled( $id );
				$this->action_form( $id, $enabled ? 'deactivate' : 'activate', $enabled ? __( 'Disattiva', 'bracelli-club' ) : __( 'Attiva', 'bracelli-club' ), false );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<p class="bc-muted">' . esc_html__( 'Le funzioni delle fasi successive (soci, tessere, corsi, prenotazioni, incassi) non sono ancora installate: compariranno qui solo quando saranno realmente disponibili.', 'bracelli-club' ) . '</p>';
		Layout::close();
	}

	/**
	 * Riquadro di conferma per la disattivazione a cascata.
	 *
	 * @param string $id Modulo.
	 */
	private function render_confirmation( string $id ): void {
		$dependents = $this->plugin->modules->enabled_dependents( $id );
		if ( [] === $dependents ) {
			return;
		}
		$names = [];
		foreach ( $dependents as $dep ) {
			$names[] = (string) $this->plugin->modules->manifest( $dep )?->name;
		}
		echo '<div class="bc-card bc-card--warning" role="alertdialog" aria-labelledby="bc-confirm-title">';
		echo '<h2 id="bc-confirm-title">' . esc_html__( 'Conferma disattivazione a cascata', 'bracelli-club' ) . '</h2>';
		echo '<p>' . esc_html(
			sprintf(
				/* translators: 1: modulo, 2: elenco moduli dipendenti. */
				__( 'Disattivando "%1$s" verranno disattivati anche: %2$s. Nessun dato verrà eliminato.', 'bracelli-club' ),
				(string) $this->plugin->modules->manifest( $id )?->name,
				implode( ', ', $names )
			)
		) . '</p>';
		$this->action_form( $id, 'deactivate', __( 'Confermo, disattiva tutti', 'bracelli-club' ), true );
		echo ' <a class="bc-button bc-button--ghost" href="' . esc_url( Pages::url( self::SLUG ) ) . '">' . esc_html__( 'Annulla', 'bracelli-club' ) . '</a>';
		echo '</div>';
	}

	/**
	 * Modulo HTML per un'azione.
	 *
	 * @param string $id      Modulo.
	 * @param string $op      activate | deactivate.
	 * @param string $label   Etichetta pulsante.
	 * @param bool   $cascade Conferma cascata.
	 */
	private function action_form( string $id, string $op, string $label, bool $cascade ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="bc-inline-form">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		echo '<input type="hidden" name="module" value="' . esc_attr( $id ) . '">';
		echo '<input type="hidden" name="op" value="' . esc_attr( $op ) . '">';
		if ( $cascade ) {
			echo '<input type="hidden" name="cascade" value="1">';
		}
		wp_nonce_field( self::ACTION . '_' . $op . '_' . $id );
		echo '<button type="submit" class="bc-button' . ( 'deactivate' === $op ? ' bc-button--danger' : '' ) . '">' . esc_html( $label ) . '</button>';
		echo '</form>';
	}

	/**
	 * Gestione azione (admin-post).
	 */
	public function handle(): void {
		Layout::require_cap( Capabilities::MODULES_MANAGE );
		$id = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		if ( ! in_array( $op, [ 'activate', 'deactivate' ], true ) ) {
			wp_die( esc_html__( 'Operazione non valida.', 'bracelli-club' ), '', [ 'response' => 400 ] );
		}
		check_admin_referer( self::ACTION . '_' . $op . '_' . $id );
		$cascade = isset( $_POST['cascade'] ) && '1' === $_POST['cascade'];
		$result  = self::apply( $this->plugin, $id, $op, $cascade );

		if ( ! empty( $result['needs_confirmation'] ) ) {
			wp_safe_redirect( Pages::url( self::SLUG, [ 'confirm' => $id ] ) );
			exit;
		}
		if ( ! $result['ok'] ) {
			Layout::redirect( self::SLUG, 'failed' );
		}
		Layout::redirect( self::SLUG, [] === $result['changed'] ? 'unchanged' : ( 'activate' === $op ? 'activated' : 'deactivated' ) );
	}

	/**
	 * Applica un'operazione e la registra (condiviso con Recovery e WP-CLI).
	 *
	 * @param Plugin $plugin  Servizi.
	 * @param string $id      Modulo.
	 * @param string $op      activate | deactivate.
	 * @param bool   $cascade Conferma cascata.
	 * @param string $origin  Origine (ui, recovery, cli).
	 * @return array{ok: bool, changed: string[], errors: string[], needs_confirmation?: string[]}
	 */
	public static function apply( Plugin $plugin, string $id, string $op, bool $cascade, string $origin = 'ui' ): array {
		$result = 'activate' === $op ? $plugin->modules->activate( $id ) : $plugin->modules->deactivate( $id, $cascade );
		if ( $result['ok'] && [] !== $result['changed'] ) {
			foreach ( $result['changed'] as $changed ) {
				$plugin->audit->log(
					'activate' === $op ? 'module.activated' : 'module.deactivated',
					[
						'module'        => 'core',
						'resource_type' => 'module',
						'resource_id'   => $changed,
						'diff'          => [
							'requested' => $id,
							'cascade'   => $cascade,
							'origin'    => $origin,
						],
					]
				);
			}
		}
		return $result;
	}
}
