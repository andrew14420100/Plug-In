<?php
/**
 * Modulo Turni e presenze (prototipo).
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Turni;

use Bracelli\Club\Core\Admin\Layout;
use Bracelli\Club\Core\Admin\Pages;
use Bracelli\Club\Core\Modules\ModuleInterface;
use Bracelli\Club\Core\Permissions\Capabilities;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registra l'indirizzo dell'area turni e tiene i dipendenti fuori dalla bacheca WordPress.
 * Sulle altre pagine del sito non esegue query: controlla solo una variabile di query.
 */
final class TurniModule implements ModuleInterface {

	public const ADMIN_SLUG = 'bracelli-turni';

	/**
	 * Servizi.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Turni e presenze.
	 *
	 * @var ShiftService
	 */
	private ShiftService $service;

	/**
	 * Percorso dell'area (es. /area-bracelli/), modificabile con un filtro.
	 */
	public static function slug(): string {
		/**
		 * Percorso dell'area turni.
		 *
		 * @param string $slug Predefinito "area-bracelli".
		 */
		$slug = sanitize_title( (string) apply_filters( 'bracelli_club_portal_slug', 'area-bracelli' ) );
		return '' === $slug ? 'area-bracelli' : $slug;
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Plugin $plugin ): void {
		global $wpdb;
		$this->plugin  = $plugin;
		$this->service = new ShiftService( new ShiftRepository( $wpdb ), $plugin->audit );

		add_action( 'init', [ $this, 'rewrite' ] );
		add_action( 'init', [ $this, 'maybe_flush_rewrite' ], 99 );
		add_filter( 'query_vars', [ $this, 'query_vars' ] );
		add_action( 'template_redirect', [ $this, 'serve' ], 1 );
		add_filter( 'login_redirect', [ $this, 'login_redirect' ], 30, 3 );
		add_action( 'admin_init', [ $this, 'keep_employees_out_of_admin' ], 2 );
		add_filter( 'show_admin_bar', [ $this, 'show_admin_bar' ] );
		add_action( 'admin_menu', [ $this, 'menu' ] );
	}

	/**
	 * Servizio dei turni (usato dai test).
	 */
	public function service(): ShiftService {
		return $this->service;
	}

	/**
	 * Regola di riscrittura dell'indirizzo.
	 */
	public function rewrite(): void {
		add_rewrite_rule( '^' . preg_quote( self::slug(), '/' ) . '/?$', 'index.php?' . Portal::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Aggiorna le regole salvate solo se manca la nostra (primo avvio o percorso cambiato).
	 */
	public function maybe_flush_rewrite(): void {
		$rules = get_option( 'rewrite_rules' );
		if ( is_array( $rules ) && ! isset( $rules[ '^' . preg_quote( self::slug(), '/' ) . '/?$' ] ) ) {
			flush_rewrite_rules( false );
		}
	}

	/**
	 * @param string[] $vars Variabili di query pubbliche.
	 * @return string[]
	 */
	public function query_vars( array $vars ): array {
		$vars[] = Portal::QUERY_VAR;
		return $vars;
	}

	/**
	 * Serve l'area turni e termina la richiesta.
	 */
	public function serve(): void {
		if ( '1' !== (string) get_query_var( Portal::QUERY_VAR ) ) {
			return;
		}
		( new Portal( $this->plugin, $this->service ) )->serve();
		exit;
	}

	/**
	 * Account che usano solo l'area turni (dipendenti senza altri permessi di gestione).
	 *
	 * @param \WP_User|int $user Utente.
	 */
	public static function is_portal_only( $user ): bool {
		return user_can( $user, Capabilities::TURNI_SELF )
			&& ! user_can( $user, 'edit_posts' )
			&& ! user_can( $user, Capabilities::DASHBOARD_VIEW )
			&& ! user_can( $user, Capabilities::TURNI_MANAGE );
	}

	/**
	 * Dopo il login dal modulo standard, i dipendenti vanno all'area turni.
	 *
	 * @param string             $redirect_to Destinazione.
	 * @param string             $requested   Destinazione richiesta.
	 * @param \WP_User|\WP_Error $user        Utente.
	 */
	public function login_redirect( $redirect_to, $requested, $user ): string {
		if ( $user instanceof \WP_User && self::is_portal_only( $user ) ) {
			return Portal::url();
		}
		return (string) $redirect_to;
	}

	/**
	 * I dipendenti non usano la bacheca WordPress (restano disponibili admin-ajax e admin-post).
	 */
	public function keep_employees_out_of_admin(): void {
		if ( wp_doing_ajax() || ! is_user_logged_in() || ! self::is_portal_only( get_current_user_id() ) ) {
			return;
		}
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
		if ( 'admin-post.php' === $script ) {
			return;
		}
		wp_safe_redirect( Portal::url() );
		exit;
	}

	/**
	 * Nessuna barra di amministrazione per i dipendenti.
	 *
	 * @param bool $show Valore corrente.
	 */
	public function show_admin_bar( $show ): bool {
		return is_user_logged_in() && self::is_portal_only( get_current_user_id() ) ? false : (bool) $show;
	}

	/**
	 * Collegamento all'area turni nel menu Bracelli Club.
	 */
	public function menu(): void {
		Pages::add( self::ADMIN_SLUG, __( 'Turni e presenze', 'bracelli-club' ), __( 'Turni e presenze', 'bracelli-club' ), Capabilities::TURNI_MANAGE, [ $this, 'render_admin' ], 15 );
	}

	/**
	 * Pagina amministrativa: rimanda all'area turni frontend.
	 */
	public function render_admin(): void {
		Layout::require_cap( Capabilities::TURNI_MANAGE );
		Layout::open( __( 'Turni e presenze', 'bracelli-club' ), 'turni', __( 'Prototipo: turni dei dipendenti e registrazione di presenza o assenza.', 'bracelli-club' ) );
		echo '<div class="bc-card"><p>' . esc_html__( 'La gestione dei turni si fa nell\'area turni, la stessa pagina che usano i dipendenti dal telefono o dal computer.', 'bracelli-club' ) . '</p>';
		echo '<p><a class="bc-button bc-button--primary" href="' . esc_url( Portal::url() ) . '">' . esc_html__( 'Apri l\'area turni', 'bracelli-club' ) . '</a></p>';
		echo '<p class="bc-muted">' . esc_html__( 'Indirizzo da comunicare ai dipendenti:', 'bracelli-club' ) . ' <code>' . esc_html( Portal::url() ) . '</code></p>';
		echo '<p class="bc-muted">' . esc_html__( 'Gli account dei dipendenti si creano da Utenti → Aggiungi nuovo utente, con il ruolo "Dipendente Bracelli".', 'bracelli-club' ) . '</p></div>';
		Layout::close();
	}
}
