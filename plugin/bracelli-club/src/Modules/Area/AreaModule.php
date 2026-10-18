<?php
/**
 * Modulo Area riservata: portale frontend unico e pulsante "Area riservata".
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Area;

use Bracelli\Club\Core\Admin\Layout;
use Bracelli\Club\Core\Admin\Pages;
use Bracelli\Club\Core\Modules\ModuleInterface;
use Bracelli\Club\Core\Permissions\Capabilities;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registra l'indirizzo dell'area, lo shortcode del pulsante e tiene gli utenti dello staff e i soci fuori
 * dalla bacheca WordPress. Sulle altre pagine del sito non esegue query: controlla solo una variabile di query.
 */
final class AreaModule implements ModuleInterface {

	public const ADMIN_SLUG = 'bracelli-area';
	public const SHORTCODE  = 'bracelli_area_riservata';

	/**
	 * Esito della verifica dell'indirizzo breve: [ ok, structure, slug, checked_at ].
	 */
	public const PRETTY_OPTION = 'bracelli_club_portal_pretty';

	/**
	 * Capability che identificano un account dell'area riservata (staff Bracelli, insegnanti, soci).
	 */
	public const PORTAL_CAPS = [
		Capabilities::DASHBOARD_VIEW,
		Capabilities::TURNI_MANAGE,
		Capabilities::TURNI_SELF,
		Capabilities::CORSI_VIEW,
		Capabilities::CORSI_TEACH,
		Capabilities::CORSI_MANAGE,
	];

	/**
	 * Servizi.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Indica se lo stile del pulsante è già stato stampato nella pagina.
	 *
	 * @var bool
	 */
	private bool $button_style_printed = false;

	/**
	 * Percorso breve dell'area (es. /area-bracelli/), modificabile con un filtro.
	 */
	public static function slug(): string {
		/**
		 * Percorso dell'area riservata.
		 *
		 * @param string $slug Predefinito "area-bracelli".
		 */
		$slug = sanitize_title( (string) apply_filters( 'bracelli_club_portal_slug', 'area-bracelli' ) );
		return '' === $slug ? 'area-bracelli' : $slug;
	}

	/**
	 * Indica se l'indirizzo breve è stato verificato con le impostazioni correnti dei permalink.
	 */
	public static function pretty_url_verified(): bool {
		$structure = (string) get_option( 'permalink_structure' );
		$state     = get_option( self::PRETTY_OPTION );
		return '' !== $structure
			&& is_array( $state )
			&& ! empty( $state['ok'] )
			&& ( $state['structure'] ?? null ) === $structure
			&& ( $state['slug'] ?? null ) === self::slug();
	}

	/**
	 * Verifica (al massimo una volta al giorno, o quando cambiano permalink o percorso) che l'indirizzo breve
	 * arrivi davvero a WordPress: su alcuni server (per esempio senza .htaccess) il server risponde 404 da solo.
	 *
	 * @param bool $force Ripete la verifica anche se recente.
	 */
	public static function check_pretty_url( bool $force = false ): bool {
		$structure = (string) get_option( 'permalink_structure' );
		$state     = get_option( self::PRETTY_OPTION );
		if ( '' === $structure ) {
			return false;
		}
		$fresh = is_array( $state )
			&& ( $state['structure'] ?? null ) === $structure
			&& ( $state['slug'] ?? null ) === self::slug()
			&& (int) ( $state['checked_at'] ?? 0 ) > time() - DAY_IN_SECONDS;
		if ( $fresh && ! $force ) {
			return ! empty( $state['ok'] );
		}
		$response = wp_remote_get(
			home_url( '/' . self::slug() . '/' ),
			[
				'timeout'     => 5,
				'redirection' => 0,
				'sslverify'   => false,
			]
		);
		$ok       = ! is_wp_error( $response )
			&& 200 === (int) wp_remote_retrieve_response_code( $response )
			&& str_contains( (string) wp_remote_retrieve_body( $response ), '<body class="bcp">' );
		update_option(
			self::PRETTY_OPTION,
			[
				'ok'         => $ok,
				'structure'  => $structure,
				'slug'       => self::slug(),
				'checked_at' => time(),
			],
			true
		);
		return $ok;
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Plugin $plugin ): void {
		$this->plugin = $plugin;

		add_action( 'init', [ $this, 'rewrite' ] );
		add_action( 'init', [ $this, 'maybe_flush_rewrite' ], 99 );
		add_action( 'init', [ $this, 'register_shortcode' ] );
		add_filter( 'query_vars', [ $this, 'query_vars' ] );
		add_action( 'template_redirect', [ $this, 'serve' ], 1 );
		add_filter( 'login_redirect', [ $this, 'login_redirect' ], 30, 3 );
		add_action( 'admin_init', [ $this, 'keep_out_of_admin' ], 2 );
		// Le pagine senza permesso vengono bloccate da WordPress prima di admin_init: stesso reindirizzamento.
		add_action( 'admin_page_access_denied', [ $this, 'keep_out_of_admin' ] );
		add_filter( 'show_admin_bar', [ $this, 'show_admin_bar' ] );
		add_action( 'admin_menu', [ $this, 'menu' ] );
	}

	/**
	 * Regola di riscrittura dell'indirizzo breve.
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
	 * Serve l'area riservata e termina la richiesta.
	 */
	public function serve(): void {
		if ( '1' !== (string) get_query_var( Portal::QUERY_VAR ) ) {
			return;
		}
		( new Portal( $this->plugin ) )->serve();
		exit;
	}

	/**
	 * Account che usano solo l'area riservata: staff Bracelli, insegnanti e soci senza permessi
	 * di redazione o amministrazione di WordPress.
	 *
	 * @param \WP_User|int $user Utente.
	 */
	public static function is_portal_only( $user ): bool {
		if ( user_can( $user, 'edit_posts' ) || user_can( $user, 'manage_options' ) ) {
			return false;
		}
		foreach ( self::PORTAL_CAPS as $cap ) {
			if ( user_can( $user, $cap ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Dopo il login dal modulo standard di WordPress, gli account dell'area vanno all'area riservata.
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
	 * Gli account dell'area non usano la bacheca WordPress (restano disponibili admin-ajax e admin-post).
	 */
	public function keep_out_of_admin(): void {
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
	 * Nessuna barra di amministrazione per gli account dell'area.
	 *
	 * @param bool $show Valore corrente.
	 */
	public function show_admin_bar( $show ): bool {
		return is_user_logged_in() && self::is_portal_only( get_current_user_id() ) ? false : (bool) $show;
	}

	/**
	 * Shortcode del pulsante.
	 */
	public function register_shortcode(): void {
		add_shortcode( self::SHORTCODE, [ $this, 'shortcode' ] );
	}

	/**
	 * Pulsante "Area riservata" con icona: porta all'accesso se l'utente non è collegato, altrimenti alla sua area.
	 * Uso: [bracelli_area_riservata] — attributi facoltativi: testo="…", stile="oro|scuro|chiaro".
	 *
	 * @param array<string, string>|string $atts Attributi.
	 */
	public function shortcode( $atts ): string {
		$atts  = shortcode_atts(
			[
				'testo' => __( 'Area riservata', 'bracelli-club' ),
				'stile' => 'oro',
			],
			is_array( $atts ) ? $atts : [],
			self::SHORTCODE
		);
		$style = in_array( $atts['stile'], [ 'oro', 'scuro', 'chiaro' ], true ) ? $atts['stile'] : 'oro';
		$label = '' !== trim( (string) $atts['testo'] ) ? (string) $atts['testo'] : __( 'Area riservata', 'bracelli-club' );

		$css = '';
		if ( ! $this->button_style_printed ) {
			$this->button_style_printed = true;
			$css                        = '<style id="bracelli-area-button">'
				. '.bcar{display:inline-flex;align-items:center;justify-content:center;gap:.55em;min-height:44px;padding:.65em 1.3em;border:2px solid transparent;border-radius:999px;font:inherit;font-weight:600;line-height:1.2;letter-spacing:.01em;text-decoration:none!important;white-space:nowrap;box-sizing:border-box;transition:background-color .2s,color .2s,border-color .2s}'
				. '.bcar svg{width:1.2em;height:1.2em;flex:none}'
				. '.bcar:focus-visible{outline:3px solid rgba(184,137,43,.55);outline-offset:2px}'
				. '.bcar--oro{background:#b8892b;border-color:#b8892b;color:#111318!important}'
				. '.bcar--oro:hover,.bcar--oro:focus-visible{background:#111318;border-color:#111318;color:#fff!important}'
				. '.bcar--scuro{background:#111318;border-color:#111318;color:#fff!important}'
				. '.bcar--scuro:hover,.bcar--scuro:focus-visible{background:#b8892b;border-color:#b8892b;color:#111318!important}'
				. '.bcar--chiaro{background:#fff;border-color:#b8892b;color:#111318!important}'
				. '.bcar--chiaro:hover,.bcar--chiaro:focus-visible{background:#b8892b;color:#111318!important}'
				. '@media (max-width:480px){.bcar{padding:.55em 1em;font-size:.95em}}'
				. '</style>';
		}
		$icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M5 20a7 7 0 0 1 14 0"/></svg>';
		return $css . sprintf(
			'<a class="bcar bcar--%1$s" href="%2$s" rel="nofollow">%3$s<span>%4$s</span></a>',
			esc_attr( $style ),
			esc_url( Portal::url() ),
			$icon,
			esc_html( $label )
		);
	}

	/**
	 * Pagina informativa nel menu Bracelli Club (solo amministratori).
	 */
	public function menu(): void {
		Pages::add( self::ADMIN_SLUG, __( 'Area riservata', 'bracelli-club' ), __( 'Area riservata', 'bracelli-club' ), 'manage_options', [ $this, 'render_admin' ], 15 );
	}

	/**
	 * Indirizzo dell'area, verifica dell'indirizzo breve, shortcode e account.
	 */
	public function render_admin(): void {
		Layout::require_cap( 'manage_options' );
		Layout::open( __( 'Area riservata', 'bracelli-club' ), 'area', __( 'Portale unico per segreteria, insegnanti, dipendenti e soci, separato dalla bacheca WordPress.', 'bracelli-club' ) );
		$pretty = self::check_pretty_url();
		echo '<div class="bc-card"><h2>' . esc_html__( 'Indirizzo', 'bracelli-club' ) . '</h2>';
		echo '<p><a class="bc-button bc-button--primary" href="' . esc_url( Portal::url() ) . '">' . esc_html__( 'Apri l\'area riservata', 'bracelli-club' ) . '</a></p>';
		echo '<p class="bc-muted">' . esc_html__( 'Indirizzo da comunicare:', 'bracelli-club' ) . ' <code>' . esc_html( Portal::url() ) . '</code></p>';
		if ( ! $pretty ) {
			echo '<p class="bc-muted">' . esc_html__( 'L\'indirizzo breve /area-bracelli/ non è disponibile su questo server (permalink semplici oppure regole di riscrittura assenti, per esempio senza file .htaccess): per questo l\'area usa l\'indirizzo con ?bracelli_portal=1, che funziona sempre.', 'bracelli-club' ) . '</p>';
		}
		echo '</div>';
		echo '<div class="bc-card"><h2>' . esc_html__( 'Pulsante "Area riservata" per l\'header', 'bracelli-club' ) . '</h2>';
		echo '<p>' . esc_html__( 'In Elementor aggiungi il widget "Shortcode" nel punto dell\'header in cui vuoi il pulsante e scrivi:', 'bracelli-club' ) . '</p>';
		echo '<p><code>[' . esc_html( self::SHORTCODE ) . ']</code></p>';
		echo '<p class="bc-muted">' . esc_html__( 'Varianti: stile="scuro" oppure stile="chiaro"; testo="…" per cambiare la scritta. Chi non è collegato va alla pagina "Accedi", chi è collegato alla propria area.', 'bracelli-club' ) . '</p>';
		echo '<p>' . $this->shortcode( [] ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup costruito con escape in shortcode().
		echo '<div class="bc-card"><h2>' . esc_html__( 'Account', 'bracelli-club' ) . '</h2><p>' . esc_html__( 'Non esiste registrazione pubblica: gli account si creano da Utenti → Aggiungi nuovo utente. Ruoli: "Segreteria Bracelli" (orari dei corsi e presenze degli insegnanti), "Istruttore Bracelli" (insegnante: proprie lezioni e presenza), "Socio Bracelli" (calendario dei corsi), "Dipendente Bracelli" (turni del personale).', 'bracelli-club' ) . '</p></div>';
		Layout::close();
	}
}
