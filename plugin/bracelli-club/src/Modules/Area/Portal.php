<?php
/**
 * Portale unico dell'area riservata (frontend).
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Area;

use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Pagina autonoma servita dal plugin (non usa tema né bacheca WordPress): accesso, intestazione,
 * navigazione per ruolo e smistamento verso le sezioni. Moduli HTML con nonce, schema POST → redirect → GET.
 * Le sezioni verificano sul server i permessi di ogni vista e azione.
 */
final class Portal {

	public const QUERY_VAR = 'bracelli_portal';
	public const STYLE     = 'bracelli-club-portal';
	public const LOGO_FILE = 'assets/images/logo-anniversario.png';

	/**
	 * Errori di validazione da mostrare nel modulo ripresentato.
	 *
	 * @var array<string, string>
	 */
	private array $errors = [];

	/**
	 * Valori inviati da ripresentare dopo un errore.
	 *
	 * @var array<string, string>
	 */
	private array $old = [];

	/**
	 * Errore del modulo di accesso.
	 *
	 * @var string
	 */
	private string $login_error = '';

	/**
	 * Vista da mostrare dopo un errore di validazione (il modulo inviato), invece di quella in GET.
	 *
	 * @var string
	 */
	private string $view_after_error = '';

	/**
	 * @param Plugin $plugin Servizi del nucleo.
	 */
	public function __construct( private Plugin $plugin ) {
	}

	/**
	 * Indirizzo canonico dell'area. L'indirizzo breve (/area-bracelli/) si usa solo dopo aver verificato che il
	 * server lo inoltri a WordPress; altrimenti si usa il parametro di query, che funziona con qualsiasi server.
	 *
	 * @param array<string, string|int> $args Parametri aggiuntivi.
	 */
	public static function url( array $args = [] ): string {
		$base = AreaModule::pretty_url_verified()
			? home_url( '/' . AreaModule::slug() . '/' )
			: add_query_arg( self::QUERY_VAR, '1', home_url( '/' ) );
		return [] === $args ? $base : add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $args ) ), $base );
	}

	/**
	 * Gestisce la richiesta.
	 */
	public function serve(): void {
		if ( ! headers_sent() ) {
			nocache_headers();
			send_frame_options_header();
			header( 'X-Robots-Tag: noindex, nofollow' );
			header( 'Referrer-Policy: same-origin' );
		}

		if ( $this->plugin->migrator->needs_run() ) {
			$this->page( __( 'Area non ancora pronta', 'bracelli-club' ), fn() => $this->message( __( 'L\'aggiornamento del database non è ancora stato eseguito. Un amministratore deve aprire la bacheca WordPress una volta per completarlo.', 'bracelli-club' ) ) );
			return;
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' === $method ) {
			$this->handle_post();
		}
		$this->render();
	}

	/* ------------------------------------------------------------------
	 * Azioni (POST)
	 * ------------------------------------------------------------------ */

	/**
	 * Smista le azioni: "login" è del portale, le altre hanno il prefisso della sezione ("corsi_attendance").
	 * Ogni azione ha un nonce legato all'azione e al record; i permessi li verifica la sezione.
	 */
	private function handle_post(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- il nonce è verificato qui sotto per ogni azione.
		$action = isset( $_POST['bc_action'] ) ? sanitize_key( wp_unslash( $_POST['bc_action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id = isset( $_POST['bc_id'] ) ? absint( $_POST['bc_id'] ) : 0;

		if ( 'login' === $action ) {
			$this->handle_login();
			return;
		}
		$user = get_current_user_id();
		if ( 0 === $user ) {
			return;
		}
		$nonce = isset( $_POST['_bc_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_bc_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::nonce_action( $action, $id ) ) ) {
			$this->redirect( [ 'esito' => 'expired' ] );
		}
		$parts   = explode( '_', $action, 2 );
		$section = Sections::get( $parts[0] );
		if ( null === $section || ! isset( $parts[1] ) ) {
			$this->redirect( [ 'esito' => 'invalid' ] );
		}
		$section->handle( $this, $user, $parts[1], $id );
		if ( [] === $this->errors ) {
			$this->redirect( [ 'esito' => 'invalid' ] );
		}
	}

	/**
	 * Accesso con nome utente o email e password, tramite l'autenticazione di WordPress
	 * (stessi controlli e hook del login standard; nessuna password salvata dal plugin).
	 */
	private function handle_login(): void {
		$nonce = isset( $_POST['_bc_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_bc_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'bracelli_portal_login' ) ) {
			$this->login_error = __( 'La pagina era scaduta. Riprova.', 'bracelli-club' );
			return;
		}
		$login = isset( $_POST['log'] ) ? sanitize_user( wp_unslash( $_POST['log'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- la password non va alterata.
		$password = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '';
		if ( '' === $login || '' === $password ) {
			$this->login_error = __( 'Inserisci nome utente o email e password.', 'bracelli-club' );
			return;
		}
		$user = wp_signon(
			[
				'user_login'    => $login,
				'user_password' => $password,
				'remember'      => ! empty( $_POST['rememberme'] ),
			],
			is_ssl()
		);
		if ( is_wp_error( $user ) ) {
			// Messaggio unico: non rivela se l'account esiste.
			$this->login_error = __( 'Accesso non riuscito: controlla nome utente o email e password.', 'bracelli-club' );
			return;
		}
		wp_set_current_user( $user->ID );
		$this->redirect( [] );
	}

	/**
	 * Azione del nonce: legata al nome dell'azione e al record.
	 *
	 * @param string $action Azione completa (con prefisso della sezione).
	 * @param int    $id     Record.
	 */
	public static function nonce_action( string $action, int $id = 0 ): string {
		return 'bracelli_portal_' . $action . ( $id > 0 ? '_' . $id : '' );
	}

	/**
	 * Campi nascosti di un modulo di azione.
	 *
	 * @param string $action Azione completa.
	 * @param int    $id     Record.
	 */
	public function action_fields( string $action, int $id = 0 ): void {
		echo '<input type="hidden" name="bc_action" value="' . esc_attr( $action ) . '">';
		if ( $id > 0 ) {
			echo '<input type="hidden" name="bc_id" value="' . esc_attr( (string) $id ) . '">';
		}
		echo '<input type="hidden" name="_bc_nonce" value="' . esc_attr( wp_create_nonce( self::nonce_action( $action, $id ) ) ) . '">';
	}

	/**
	 * Valori POST come testo semplice.
	 *
	 * @param string[] $keys Campi.
	 * @return array<string, string>
	 */
	public function posted( array $keys ): array {
		$out = [];
		foreach ( $keys as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificato da handle_post().
			$out[ $key ] = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		}
		return $out;
	}

	/**
	 * Errori di validazione: si ripresenta il modulo; altri esiti: redirect con messaggio.
	 *
	 * @param array{ok: bool, code: string, errors: array<string, string>, id: int} $result Esito.
	 * @param array<string, string>                                                $input  Valori inviati.
	 * @param string                                                               $view   Vista del modulo inviato.
	 * @param array<string, string|int>                                            $back   Parametri del redirect per gli altri esiti.
	 */
	public function keep_errors( array $result, array $input, string $view, array $back = [] ): void {
		if ( 'invalid' !== $result['code'] || [] === $result['errors'] ) {
			$this->redirect( $back + [ 'esito' => $result['code'] ] );
		}
		$this->errors           = $result['errors'];
		$this->old              = $input;
		$this->view_after_error = $view;
	}

	/**
	 * Valori inviati da ripresentare (vuoto se non c'è stato un errore).
	 *
	 * @return array<string, string>
	 */
	public function old(): array {
		return $this->old;
	}

	/**
	 * Redirect sicuro verso l'area (termina la richiesta).
	 *
	 * @param array<string, string|int> $args Parametri.
	 * @return never
	 */
	public function redirect( array $args ) {
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Pagine (GET)
	 * ------------------------------------------------------------------ */

	/**
	 * Sceglie la vista in base all'utente e alle viste che le sezioni gli consentono.
	 */
	private function render(): void {
		$user = get_current_user_id();
		if ( 0 === $user ) {
			$this->page( __( 'Accedi', 'bracelli-club' ), fn() => $this->login_form() );
			return;
		}
		$views = [];
		foreach ( Sections::all() as $section ) {
			foreach ( $section->views( $user ) as $view => $title ) {
				$views[ $view ] = [ $section, $title ];
			}
		}
		if ( [] === $views ) {
			status_header( 403 );
			$this->page( __( 'Accesso non consentito', 'bracelli-club' ), fn() => $this->message( __( 'Il tuo account non è abilitato all\'area riservata. Rivolgiti alla segreteria.', 'bracelli-club' ) ) );
			return;
		}
		$view = '' !== $this->view_after_error ? $this->view_after_error : $this->query( 'vista' );
		if ( ! isset( $views[ $view ] ) ) {
			$view = (string) array_key_first( $views );
		}
		[ $section, $title ] = $views[ $view ];
		$this->page(
			$title,
			function () use ( $section, $view, $user ): void {
				$this->nav( $user, $section->tab_for( $view ) );
				$this->notice( $section );
				$section->render( $this, $user, $view );
			}
		);
	}

	/**
	 * Struttura HTML comune.
	 *
	 * @param string   $title Titolo.
	 * @param callable $body  Contenuto.
	 */
	private function page( string $title, callable $body ): void {
		wp_register_style( self::STYLE, BRACELLI_CLUB_URL . 'assets/css/portal.css', [], BRACELLI_CLUB_VERSION );
		$user = wp_get_current_user();
		$logo = self::logo_url();
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $title . ' — ' . __( 'Area riservata Bracelli Club', 'bracelli-club' ) ); ?></title>
		<?php wp_print_styles( [ self::STYLE ] ); ?>
</head>
<body class="bcp">
<a class="bcp-skip" href="#bcp-main"><?php esc_html_e( 'Vai al contenuto', 'bracelli-club' ); ?></a>
<header class="bcp-header">
	<a class="bcp-brand" href="<?php echo esc_url( self::url() ); ?>">
		<?php if ( '' !== $logo ) : ?>
			<img class="bcp-logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php esc_attr_e( 'Bracelli Club', 'bracelli-club' ); ?>">
		<?php else : ?>
			<span class="bcp-wordmark">Bracelli <strong>Club</strong></span>
		<?php endif; ?>
		<span class="bcp-area"><?php esc_html_e( 'Area riservata', 'bracelli-club' ); ?></span>
	</a>
		<?php if ( $user->exists() ) : ?>
	<div class="bcp-user">
		<span class="bcp-user__name"><?php echo esc_html( $user->display_name ); ?></span>
			<?php if ( current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' ) ) : ?>
			<a class="bcp-link" href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( 'Bacheca', 'bracelli-club' ); ?></a>
			<?php endif; ?>
		<a class="bcp-button bcp-button--ghost" href="<?php echo esc_url( wp_logout_url( self::url() ) ); ?>"><?php esc_html_e( 'Esci', 'bracelli-club' ); ?></a>
	</div>
		<?php endif; ?>
</header>
<main id="bcp-main" class="bcp-main">
	<h1 class="bcp-title"><?php echo esc_html( $title ); ?></h1>
		<?php $body(); ?>
</main>
</body>
</html>
		<?php
	}

	/**
	 * Logo ufficiale incluso nel pacchetto, se presente; altrimenti il logo del sito; sostituibile con un filtro.
	 */
	public static function logo_url(): string {
		$url = is_readable( BRACELLI_CLUB_DIR . self::LOGO_FILE ) ? BRACELLI_CLUB_URL . self::LOGO_FILE : '';
		if ( '' === $url ) {
			$id  = (int) get_theme_mod( 'custom_logo' );
			$url = $id > 0 ? (string) wp_get_attachment_image_url( $id, 'medium' ) : '';
		}
		/**
		 * Indirizzo del logo mostrato nell'area riservata ('' = scritta "Bracelli Club").
		 *
		 * @param string $url Logo del plugin o del sito, se presente.
		 */
		return (string) apply_filters( 'bracelli_club_portal_logo_url', $url );
	}

	/**
	 * @param string $text Messaggio.
	 */
	private function message( string $text ): void {
		echo '<div class="bcp-card"><p>' . esc_html( $text ) . '</p>';
		if ( is_user_logged_in() ) {
			echo '<p><a class="bcp-button" href="' . esc_url( wp_logout_url( self::url() ) ) . '">' . esc_html__( 'Esci', 'bracelli-club' ) . '</a></p>';
		}
		echo '</div>';
	}

	/**
	 * Modulo di accesso: nessuna registrazione pubblica, solo account creati dagli amministratori.
	 */
	private function login_form(): void {
		?>
<form class="bcp-card bcp-form bcp-login" method="post" action="<?php echo esc_url( self::url() ); ?>">
		<?php if ( '' !== self::logo_url() ) : ?>
	<img class="bcp-login__logo" src="<?php echo esc_url( self::logo_url() ); ?>" alt="">
		<?php endif; ?>
	<p class="bcp-muted"><?php esc_html_e( 'Accedi con le credenziali che ti ha fornito la segreteria.', 'bracelli-club' ); ?></p>
		<?php if ( '' !== $this->login_error ) : ?>
		<p class="bcp-alert bcp-alert--error" role="alert"><?php echo esc_html( $this->login_error ); ?></p>
		<?php endif; ?>
	<input type="hidden" name="bc_action" value="login">
	<input type="hidden" name="_bc_nonce" value="<?php echo esc_attr( wp_create_nonce( 'bracelli_portal_login' ) ); ?>">
	<label for="bcp-log"><?php esc_html_e( 'Nome utente o email', 'bracelli-club' ); ?></label>
	<input id="bcp-log" name="log" type="text" autocomplete="username" autocapitalize="none" spellcheck="false" required>
	<label for="bcp-pwd"><?php esc_html_e( 'Password', 'bracelli-club' ); ?></label>
	<input id="bcp-pwd" name="pwd" type="password" autocomplete="current-password" required>
	<label class="bcp-check"><input name="rememberme" type="checkbox" value="1"> <?php esc_html_e( 'Ricordami su questo dispositivo', 'bracelli-club' ); ?></label>
	<button class="bcp-button bcp-button--primary" type="submit"><?php esc_html_e( 'Accedi', 'bracelli-club' ); ?></button>
	<p><a class="bcp-link" href="<?php echo esc_url( wp_lostpassword_url( self::url() ) ); ?>"><?php esc_html_e( 'Password dimenticata?', 'bracelli-club' ); ?></a></p>
</form>
		<?php
	}

	/**
	 * Schede di navigazione di tutte le sezioni consentite all'utente.
	 *
	 * @param int    $user   Utente.
	 * @param string $active Scheda attiva.
	 */
	private function nav( int $user, string $active ): void {
		$items = [];
		foreach ( Sections::all() as $section ) {
			$items += $section->tabs( $user );
		}
		if ( count( $items ) < 2 ) {
			return;
		}
		echo '<nav class="bcp-tabs" aria-label="' . esc_attr__( 'Sezioni', 'bracelli-club' ) . '">';
		foreach ( $items as $view => $label ) {
			$is = $view === $active;
			printf(
				'<a class="bcp-tab%1$s" href="%2$s"%3$s>%4$s</a>',
				$is ? ' is-active' : '',
				esc_url( self::url( [ 'vista' => $view ] ) ),
				$is ? ' aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';
	}

	/**
	 * Messaggio di esito dopo un redirect (prima quelli della sezione corrente, poi quelli comuni).
	 *
	 * @param SectionInterface $section Sezione della vista corrente.
	 */
	private function notice( SectionInterface $section ): void {
		$messages = $section->messages() + [
			'unchanged' => [ 'info', __( 'Nessuna modifica.', 'bracelli-club' ) ],
			'forbidden' => [ 'error', __( 'Operazione non consentita al tuo account.', 'bracelli-club' ) ],
			'expired'   => [ 'error', __( 'La pagina era scaduta: l\'operazione non è stata eseguita. Riprova.', 'bracelli-club' ) ],
			'invalid'   => [ 'error', __( 'Dati non validi: l\'operazione non è stata eseguita.', 'bracelli-club' ) ],
			'db_error'  => [ 'error', __( 'Salvataggio non riuscito. Riprova; se il problema continua avvisa l\'amministratore.', 'bracelli-club' ) ],
		];
		$code     = $this->query( 'esito' );
		if ( isset( $messages[ $code ] ) ) {
			[ $type, $text ] = $messages[ $code ];
			echo '<p class="bcp-alert bcp-alert--' . esc_attr( $type ) . '" role="' . ( 'error' === $type ? 'alert' : 'status' ) . '">' . esc_html( $text ) . '</p>';
		}
	}

	/* ------------------------------------------------------------------
	 * Strumenti per le sezioni
	 * ------------------------------------------------------------------ */

	/**
	 * Riepilogo errori del modulo.
	 */
	public function form_errors(): void {
		if ( [] !== $this->errors ) {
			echo '<p class="bcp-alert bcp-alert--error" role="alert">' . esc_html__( 'Controlla i campi evidenziati: i dati non sono stati salvati.', 'bracelli-club' ) . '</p>';
		}
	}

	/**
	 * @param string $field Campo.
	 */
	public function field_error( string $field ): void {
		if ( isset( $this->errors[ $field ] ) ) {
			echo '<p class="bcp-field-error" id="bcp-err-' . esc_attr( $field ) . '">' . esc_html( $this->errors[ $field ] ) . '</p>';
		}
	}

	/**
	 * @param string $field Campo.
	 */
	public function invalid_attr( string $field ): void {
		if ( isset( $this->errors[ $field ] ) ) {
			echo ' aria-invalid="true" aria-describedby="bcp-err-' . esc_attr( $field ) . '"';
		}
	}

	/**
	 * Record non trovato o non visibile all'utente.
	 *
	 * @param string $text Messaggio.
	 */
	public function not_found( string $text ): void {
		status_header( 404 );
		echo '<p class="bcp-alert bcp-alert--error">' . esc_html( $text ) . '</p>';
		echo '<p><a class="bcp-link" href="' . esc_url( self::url() ) . '">' . esc_html__( '← Torna all\'inizio', 'bracelli-club' ) . '</a></p>';
	}

	/**
	 * @param string $date Data Y-m-d (ora locale del sito).
	 */
	public function format_date( string $date ): string {
		return (string) wp_date( 'D j M Y', (int) strtotime( $date . ' 12:00:00 UTC' ), new \DateTimeZone( 'UTC' ) );
	}

	/**
	 * Data e ora di un timestamp tecnico UTC, nel fuso del sito.
	 *
	 * @param string $utc Data e ora UTC (Y-m-d H:i:s).
	 */
	public function format_utc( string $utc ): string {
		return (string) wp_date( 'j M, H:i', (int) strtotime( $utc . ' UTC' ) );
	}

	/**
	 * Nome visualizzato di un utente (anche se l'account è stato rimosso).
	 *
	 * @param int $id Utente.
	 */
	public function user_name( int $id ): string {
		if ( 0 === $id ) {
			return __( 'sistema', 'bracelli-club' );
		}
		$user = get_userdata( $id );
		/* translators: %d: id utente. */
		return false === $user ? sprintf( __( 'utente #%d (non più presente)', 'bracelli-club' ), $id ) : (string) $user->display_name;
	}

	/**
	 * Parametro GET come testo semplice.
	 *
	 * @param string $key Nome.
	 */
	public function query( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo lettura per scegliere la vista.
		return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : '';
	}

	/**
	 * Data GET valida (Y-m-d) oppure il valore predefinito.
	 *
	 * @param string $key      Nome.
	 * @param string $fallback Predefinito.
	 */
	public function query_date( string $key, string $fallback ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtro di sola lettura.
		$value = isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return $value;
		}
		return $fallback;
	}

	/**
	 * Campo nascosto per i moduli GET: un modulo GET sostituisce la query dell'indirizzo,
	 * quindi il parametro dell'area viaggia sempre come campo.
	 */
	public function get_form_fields(): void {
		echo '<input type="hidden" name="' . esc_attr( self::QUERY_VAR ) . '" value="1">';
	}
}
