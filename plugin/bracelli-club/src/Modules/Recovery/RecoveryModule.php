<?php
/**
 * Modulo Ripristino (attivo anche in modalità sicura).
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Recovery;

use Bracelli\Club\Core\Admin\Assets;
use Bracelli\Club\Core\Admin\Layout;
use Bracelli\Club\Core\Admin\Pages;
use Bracelli\Club\Core\Migrations\Migrator;
use Bracelli\Club\Core\Modules\ModuleInterface;
use Bracelli\Club\Modules\Core\ModulesPage;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Richiede la capability nativa "manage_options", così resta raggiungibile anche se i permessi Bracelli
 * fossero danneggiati. Usa solo moduli HTML e redirect: nessun JavaScript.
 */
final class RecoveryModule implements ModuleInterface {

	public const SLUG       = 'bracelli-recovery';
	public const ACTION     = 'bracelli_club_recovery';
	public const CAPABILITY = 'manage_options';

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
		add_action( 'admin_menu', [ $this, 'menu' ], 20 );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
		if ( $plugin->is_safe_mode() ) {
			Assets::register();
			add_action( 'admin_notices', [ $this, 'safe_mode_notice' ] );
		}
	}

	/**
	 * Voce di menu: sottomenu in modalità normale, voce principale in modalità sicura.
	 */
	public function menu(): void {
		if ( $this->plugin->is_safe_mode() ) {
			$hook = add_menu_page( __( 'Ripristino Bracelli', 'bracelli-club' ), __( 'Ripristino Bracelli', 'bracelli-club' ), self::CAPABILITY, self::SLUG, [ $this, 'render' ], 'dashicons-sos', 3 );
			Pages::remember( self::SLUG, $hook );
			return;
		}
		Pages::add( self::SLUG, __( 'Ripristino', 'bracelli-club' ), __( 'Ripristino', 'bracelli-club' ), self::CAPABILITY, [ $this, 'render' ], 90 );
	}

	/**
	 * Avviso globale in modalità sicura.
	 */
	public function safe_mode_notice(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>Bracelli Club:</strong> ';
		echo esc_html__( 'modalità sicura attiva (BRACELLI_CLUB_SAFE_MODE). Sono disponibili solo le funzioni di ripristino.', 'bracelli-club' );
		echo ' <a href="' . esc_url( Pages::url( self::SLUG ) ) . '">' . esc_html__( 'Apri Ripristino', 'bracelli-club' ) . '</a></p></div>';
	}

	/**
	 * Rendering.
	 */
	public function render(): void {
		Layout::require_cap( self::CAPABILITY );
		Layout::open( __( 'Ripristino', 'bracelli-club' ), 'recovery', __( 'Strumenti di emergenza utilizzabili anche senza JavaScript. Nessuna azione elimina dati.', 'bracelli-club' ) );
		Layout::query_notice(
			[
				'module_off'  => [ 'success', __( 'Modulo disattivato (con eventuali moduli dipendenti).', 'bracelli-club' ) ],
				'all_off'     => [ 'success', __( 'Tutti i moduli facoltativi sono stati disattivati.', 'bracelli-club' ) ],
				'menu_shown'  => [ 'success', __( 'Il menu WordPress è di nuovo visibile per il tuo utente.', 'bracelli-club' ) ],
				'prefs_reset' => [ 'success', __( 'Preferenze grafiche ripristinate ai valori predefiniti.', 'bracelli-club' ) ],
				'errors_off'  => [ 'success', __( 'Registro errori svuotato.', 'bracelli-club' ) ],
				'migrated'    => [ 'success', __( 'Migrazioni eseguite: controlla l\'esito qui sotto.', 'bracelli-club' ) ],
				'failed'      => [ 'error', __( 'Operazione non eseguita.', 'bracelli-club' ) ],
			]
		);

		$manager = $this->plugin->modules;

		echo '<div class="bc-grid bc-grid--2">';

		echo '<section class="bc-card"><h2>' . esc_html__( 'Interfaccia', 'bracelli-club' ) . '</h2><div class="bc-stack">';
		$this->button( 'show_wp_menu', __( 'Mostra il menu WordPress (per me)', 'bracelli-club' ) );
		$this->button( 'reset_prefs', __( 'Ripristina le mie preferenze grafiche', 'bracelli-club' ) );
		echo '<p class="bc-muted">' . esc_html__( 'In alternativa la dashboard WordPress standard resta sempre raggiungibile da wp-admin/index.php.', 'bracelli-club' ) . '</p>';
		echo '</div></section>';

		echo '<section class="bc-card"><h2>' . esc_html__( 'Modalità sicura', 'bracelli-club' ) . '</h2>';
		echo '<p>' . esc_html( $this->plugin->is_safe_mode() ? __( 'Attiva.', 'bracelli-club' ) : __( 'Non attiva.', 'bracelli-club' ) ) . '</p>';
		echo '<p class="bc-muted">' . esc_html__( 'Per attivarla aggiungi in wp-config.php la riga seguente; per disattivarla rimuovila. In modalità sicura vengono avviati solo il nucleo minimo e questa pagina.', 'bracelli-club' ) . '</p>';
		echo '<pre class="bc-code">define( \'BRACELLI_CLUB_SAFE_MODE\', true );</pre></section>';

		echo '</div>';

		echo '<section class="bc-card"><h2>' . esc_html__( 'Moduli facoltativi', 'bracelli-club' ) . '</h2>';
		$any = false;
		echo '<ul class="bc-list">';
		foreach ( $manager->manifests() as $id => $manifest ) {
			if ( $manifest->required || ! $manager->is_enabled( $id ) ) {
				continue;
			}
			$any        = true;
			$dependents = $manager->enabled_dependents( $id );
			echo '<li><strong>' . esc_html( $manifest->name ) . '</strong> <span class="bc-status bc-status--' . esc_attr( $manager->status( $id ) ) . '">' . esc_html( ModulesPage::status_labels()[ $manager->status( $id ) ] ?? '' ) . '</span> ';
			if ( [] !== $dependents ) {
				echo '<span class="bc-muted">' . esc_html__( 'Disattiva anche:', 'bracelli-club' ) . ' ' . esc_html( implode( ', ', $dependents ) ) . '</span> ';
			}
			$this->button( 'deactivate_module', __( 'Disattiva', 'bracelli-club' ), [ 'module' => $id ], true );
			echo '</li>';
		}
		echo '</ul>';
		if ( $any ) {
			$this->button( 'deactivate_all', __( 'Disattiva tutti i moduli facoltativi', 'bracelli-club' ), [], true );
		} else {
			echo '<p class="bc-muted">' . esc_html__( 'Nessun modulo facoltativo attivo.', 'bracelli-club' ) . '</p>';
		}
		echo '</section>';

		$last = get_option( Migrator::RESULT_OPTION );
		echo '<section class="bc-card"><h2>' . esc_html__( 'Database', 'bracelli-club' ) . '</h2>';
		if ( is_array( $last ) ) {
			/* translators: 1: esito, 2: data. */
			echo '<p>' . esc_html( sprintf( __( 'Ultima esecuzione migrazioni: %1$s (%2$s UTC).', 'bracelli-club' ), (string) ( $last['status'] ?? '' ), (string) ( $last['at'] ?? '' ) ) ) . '</p>';
			if ( ! empty( $last['error'] ) ) {
				echo '<p class="bc-muted">' . esc_html( (string) $last['error'] ) . '</p>';
			}
		}
		$pending = $this->plugin->migrator->pending();
		/* translators: %d: numero migrazioni. */
		echo '<p>' . esc_html( sprintf( __( 'Migrazioni in sospeso: %d', 'bracelli-club' ), count( $pending ) ) ) . '</p>';
		$this->button( 'run_migrations', __( 'Riprova le migrazioni', 'bracelli-club' ) );
		echo '</section>';

		$errors = $this->plugin->errors->all();
		echo '<section class="bc-card"><h2>' . esc_html__( 'Ultimi errori', 'bracelli-club' ) . '</h2>';
		if ( [] === $errors ) {
			echo '<p class="bc-muted">' . esc_html__( 'Nessun errore registrato.', 'bracelli-club' ) . '</p>';
		} else {
			echo '<ul class="bc-list">';
			foreach ( array_reverse( array_slice( $errors, -10 ) ) as $error ) {
				echo '<li><code>' . esc_html( $error['at'] ) . '</code> <strong>' . esc_html( $error['source'] ) . '</strong> ' . esc_html( $error['message'] ) . '</li>';
			}
			echo '</ul>';
			$this->button( 'clear_errors', __( 'Svuota il registro errori', 'bracelli-club' ) );
		}
		echo '</section>';

		Layout::close();
	}

	/**
	 * Pulsante-modulo per un'azione di ripristino.
	 *
	 * @param string                $op     Operazione.
	 * @param string                $label  Etichetta.
	 * @param array<string, string> $extra  Campi aggiuntivi.
	 * @param bool                  $danger Stile di avvertimento.
	 */
	private function button( string $op, string $label, array $extra = [], bool $danger = false ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="bc-inline-form">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		echo '<input type="hidden" name="op" value="' . esc_attr( $op ) . '">';
		foreach ( $extra as $name => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}
		wp_nonce_field( self::ACTION . '_' . $op );
		echo '<button type="submit" class="bc-button' . ( $danger ? ' bc-button--danger' : '' ) . '">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * Gestione delle azioni.
	 */
	public function handle(): void {
		Layout::require_cap( self::CAPABILITY );
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		check_admin_referer( self::ACTION . '_' . $op );
		$user_id = get_current_user_id();

		switch ( $op ) {
			case 'show_wp_menu':
				$this->plugin->preferences->update( $user_id, [ 'hide_wp_menu' => false ] );
				$this->log( 'recovery.wp_menu_shown' );
				Layout::redirect( self::SLUG, 'menu_shown' );
				// No break: redirect termina la richiesta.
			case 'reset_prefs':
				$this->plugin->preferences->reset( $user_id );
				$this->log( 'recovery.preferences_reset' );
				Layout::redirect( self::SLUG, 'prefs_reset' );
				// No break.
			case 'deactivate_module':
				$id     = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';
				$result = ModulesPage::apply( $this->plugin, $id, 'deactivate', true, 'recovery' );
				Layout::redirect( self::SLUG, $result['ok'] ? 'module_off' : 'failed' );
				// No break.
			case 'deactivate_all':
				foreach ( $this->plugin->modules->manifests() as $id => $manifest ) {
					if ( ! $manifest->required && $this->plugin->modules->is_enabled( $id ) ) {
						ModulesPage::apply( $this->plugin, $id, 'deactivate', true, 'recovery' );
					}
				}
				Layout::redirect( self::SLUG, 'all_off' );
				// No break.
			case 'run_migrations':
				$this->plugin->run_migrations();
				Layout::redirect( self::SLUG, 'migrated' );
				// No break.
			case 'clear_errors':
				$this->plugin->errors->clear();
				$this->log( 'recovery.errors_cleared' );
				Layout::redirect( self::SLUG, 'errors_off' );
				// No break.
			default:
				Layout::redirect( self::SLUG, 'failed' );
		}
	}

	/**
	 * @param string $action Azione.
	 */
	private function log( string $action ): void {
		$this->plugin->audit->log(
			$action,
			[
				'module'        => 'recovery',
				'resource_type' => 'user',
				'resource_id'   => (string) get_current_user_id(),
			]
		);
	}
}
