<?php
/**
 * Area riservata frontend "Turni e presenze".
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Turni;

use Bracelli\Club\Core\Permissions\Capabilities;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Pagina autonoma servita dal plugin (non usa tema né bacheca WordPress).
 * Moduli HTML con nonce, schema POST → redirect → GET; permessi verificati da ShiftService.
 */
final class Portal {

	public const QUERY_VAR = 'bracelli_portal';
	public const STYLE     = 'bracelli-club-portal';

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
	 * @param Plugin       $plugin  Servizi del nucleo.
	 * @param ShiftService $service Turni e presenze.
	 */
	public function __construct( private Plugin $plugin, private ShiftService $service ) {
	}

	/**
	 * Indirizzo dell'area (con permalink semplici usa il parametro di query).
	 *
	 * @param array<string, string|int> $args Parametri aggiuntivi.
	 */
	public static function url( array $args = [] ): string {
		$base = '' === (string) get_option( 'permalink_structure' )
			? add_query_arg( self::QUERY_VAR, '1', home_url( '/' ) )
			: home_url( '/' . TurniModule::slug() . '/' );
		return [] === $args ? $base : add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $args ) ), $base );
	}

	/**
	 * Gestisce la richiesta e termina.
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
	 * Smista le azioni inviate. Ogni azione ha un proprio nonce; i permessi li verifica ShiftService.
	 */
	private function handle_post(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- il nonce è verificato per ogni azione qui sotto.
		$action = isset( $_POST['bc_action'] ) ? sanitize_key( wp_unslash( $_POST['bc_action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$shift_id = isset( $_POST['turno'] ) ? absint( $_POST['turno'] ) : 0;

		if ( 'login' === $action ) {
			$this->handle_login();
			return;
		}
		$user = get_current_user_id();
		if ( 0 === $user ) {
			return;
		}
		$nonce_action = 'bracelli_turni_' . $action . ( $shift_id > 0 ? '_' . $shift_id : '' );
		$nonce        = isset( $_POST['_bc_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_bc_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			$this->redirect( [ 'esito' => 'expired' ] );
		}

		switch ( $action ) {
			case 'create':
				$input  = $this->posted( [ 'employee_id', 'shift_date', 'start_time', 'end_time' ] );
				$result = $this->service->create( $user, $input );
				if ( $result['ok'] ) {
					$this->redirect(
						[
							'vista' => 'turni',
							'dal'   => $input['shift_date'],
							'al'    => $input['shift_date'],
							'esito' => 'created',
						]
					);
				}
				$this->keep_errors( $result, $input, 'nuovo' );
				return;
			case 'update':
				$input  = $this->posted( [ 'shift_date', 'start_time', 'end_time' ] );
				$result = $this->service->update( $user, $shift_id, $input );
				if ( $result['ok'] ) {
					$this->redirect(
						[
							'vista' => 'modifica',
							'turno' => $shift_id,
							'esito' => $result['code'],
						]
					);
				}
				$this->keep_errors( $result, $input, 'modifica' );
				return;
			case 'cancel':
				$result = $this->service->cancel( $user, $shift_id );
				$this->redirect(
					[
						'vista' => $result['ok'] ? 'modifica' : 'turni',
						'turno' => $shift_id,
						'esito' => $result['code'],
					]
				);
				// Nessun break: redirect() termina la richiesta.
			case 'attendance':
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificato sopra.
				$status = isset( $_POST['presenza'] ) ? sanitize_key( wp_unslash( $_POST['presenza'] ) ) : '';
				$result = $this->service->record_attendance( $user, $shift_id, $status );
				$this->redirect(
					[
						'vista' => 'miei',
						'esito' => $result['code'],
					]
				);
		}
		$this->redirect( [ 'esito' => 'invalid' ] );
	}

	/**
	 * Accesso con le credenziali WordPress (stessi controlli e hook del login standard).
	 */
	private function handle_login(): void {
		$nonce = isset( $_POST['_bc_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_bc_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'bracelli_turni_login' ) ) {
			$this->login_error = __( 'La pagina era scaduta. Riprova.', 'bracelli-club' );
			return;
		}
		$login = isset( $_POST['log'] ) ? sanitize_user( wp_unslash( $_POST['log'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- la password non va alterata.
		$password = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '';
		if ( '' === $login || '' === $password ) {
			$this->login_error = __( 'Inserisci nome utente e password.', 'bracelli-club' );
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
			// Messaggio unico: non rivela se esiste il nome utente.
			$this->login_error = __( 'Accesso non riuscito: controlla nome utente e password.', 'bracelli-club' );
			return;
		}
		wp_set_current_user( $user->ID );
		$this->redirect( [] );
	}

	/**
	 * @param string[] $keys Campi.
	 * @return array<string, string>
	 */
	private function posted( array $keys ): array {
		$out = [];
		foreach ( $keys as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificato dal chiamante.
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
	 */
	private function keep_errors( array $result, array $input, string $view ): void {
		if ( 'invalid' !== $result['code'] || [] === $result['errors'] ) {
			$this->redirect( [ 'esito' => $result['code'] ] );
		}
		$this->errors           = $result['errors'];
		$this->old              = $input;
		$this->view_after_error = $view;
	}

	/**
	 * @param array<string, string|int> $args Parametri.
	 * @return never
	 */
	private function redirect( array $args ) {
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Pagine (GET)
	 * ------------------------------------------------------------------ */

	/**
	 * Sceglie la vista in base all'utente e ai permessi.
	 */
	private function render(): void {
		$user = get_current_user_id();
		if ( 0 === $user ) {
			$this->page( __( 'Accedi', 'bracelli-club' ), fn() => $this->login_form() );
			return;
		}
		$manage   = $this->service->can_manage( $user );
		$employee = $this->service->is_employee( $user );
		if ( ! $manage && ! $employee ) {
			status_header( 403 );
			$this->page( __( 'Accesso non consentito', 'bracelli-club' ), fn() => $this->message( __( 'Il tuo account non è abilitato all\'area turni. Rivolgiti alla segreteria.', 'bracelli-club' ) ) );
			return;
		}
		$view    = '' !== $this->view_after_error ? $this->view_after_error : $this->query( 'vista' );
		$allowed = array_merge( $manage ? [ 'turni', 'nuovo', 'modifica', 'annulla' ] : [], $employee ? [ 'miei' ] : [] );
		if ( ! in_array( $view, $allowed, true ) ) {
			$view = $manage ? 'turni' : 'miei';
		}
		$titles = [
			'turni'    => __( 'Turni e presenze', 'bracelli-club' ),
			'nuovo'    => __( 'Nuovo turno', 'bracelli-club' ),
			'modifica' => __( 'Dettaglio turno', 'bracelli-club' ),
			'annulla'  => __( 'Annulla turno', 'bracelli-club' ),
			'miei'     => __( 'I miei turni', 'bracelli-club' ),
		];
		$this->page(
			$titles[ $view ],
			function () use ( $view, $user, $manage, $employee ): void {
				$this->nav( $view, $manage, $employee );
				$this->notice();
				match ( $view ) {
					'turni'    => $this->view_list( $user ),
					'nuovo'    => $this->view_create( $user ),
					'modifica' => $this->view_detail( $user ),
					'annulla'  => $this->view_cancel( $user ),
					default    => $this->view_mine( $user ),
				};
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
		$logo = $this->logo_url();
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $title . ' — ' . __( 'Area turni Bracelli Club', 'bracelli-club' ) ); ?></title>
		<?php wp_print_styles( [ self::STYLE ] ); ?>
</head>
<body class="bcp">
<a class="bcp-skip" href="#bcp-main"><?php esc_html_e( 'Vai al contenuto', 'bracelli-club' ); ?></a>
<header class="bcp-header">
	<div class="bcp-brand">
		<?php if ( '' !== $logo ) : ?>
			<img class="bcp-logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php esc_attr_e( 'Bracelli Club', 'bracelli-club' ); ?>">
		<?php else : ?>
			<span class="bcp-wordmark">Bracelli <strong>Club</strong></span>
		<?php endif; ?>
		<span class="bcp-area"><?php esc_html_e( 'Area turni', 'bracelli-club' ); ?></span>
	</div>
		<?php if ( $user->exists() ) : ?>
	<div class="bcp-user">
		<span class="bcp-user__name"><?php echo esc_html( $user->display_name ); ?></span>
			<?php if ( current_user_can( 'edit_posts' ) || current_user_can( Capabilities::DASHBOARD_VIEW ) ) : ?>
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
	 * Logo: quello impostato nel sito (Aspetto → Personalizza → Logo), sostituibile con un filtro.
	 */
	private function logo_url(): string {
		$id  = (int) get_theme_mod( 'custom_logo' );
		$url = $id > 0 ? (string) wp_get_attachment_image_url( $id, 'medium' ) : '';
		/**
		 * Indirizzo del logo mostrato nell'area turni ('' = scritta "Bracelli Club").
		 *
		 * @param string $url Logo del sito, se impostato.
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
	 * Modulo di accesso.
	 */
	private function login_form(): void {
		?>
<form class="bcp-card bcp-form bcp-login" method="post" action="<?php echo esc_url( self::url() ); ?>">
	<p class="bcp-muted"><?php esc_html_e( 'Accedi con le credenziali che ti ha fornito la segreteria.', 'bracelli-club' ); ?></p>
		<?php if ( '' !== $this->login_error ) : ?>
		<p class="bcp-alert bcp-alert--error" role="alert"><?php echo esc_html( $this->login_error ); ?></p>
		<?php endif; ?>
	<input type="hidden" name="bc_action" value="login">
	<input type="hidden" name="_bc_nonce" value="<?php echo esc_attr( wp_create_nonce( 'bracelli_turni_login' ) ); ?>">
	<label for="bcp-log"><?php esc_html_e( 'Nome utente o email', 'bracelli-club' ); ?></label>
	<input id="bcp-log" name="log" type="text" autocomplete="username" required>
	<label for="bcp-pwd"><?php esc_html_e( 'Password', 'bracelli-club' ); ?></label>
	<input id="bcp-pwd" name="pwd" type="password" autocomplete="current-password" required>
	<label class="bcp-check"><input name="rememberme" type="checkbox" value="1"> <?php esc_html_e( 'Ricordami su questo dispositivo', 'bracelli-club' ); ?></label>
	<button class="bcp-button bcp-button--primary" type="submit"><?php esc_html_e( 'Accedi', 'bracelli-club' ); ?></button>
	<p><a class="bcp-link" href="<?php echo esc_url( wp_lostpassword_url( self::url() ) ); ?>"><?php esc_html_e( 'Password dimenticata?', 'bracelli-club' ); ?></a></p>
</form>
		<?php
	}

	/**
	 * Schede di navigazione.
	 *
	 * @param string $current  Vista corrente.
	 * @param bool   $manage   Gestione turni.
	 * @param bool   $employee Dipendente.
	 */
	private function nav( string $current, bool $manage, bool $employee ): void {
		$items = [];
		if ( $manage ) {
			$items['turni'] = __( 'Turni e presenze', 'bracelli-club' );
			$items['nuovo'] = __( 'Nuovo turno', 'bracelli-club' );
		}
		if ( $employee ) {
			$items['miei'] = __( 'I miei turni', 'bracelli-club' );
		}
		if ( count( $items ) < 2 ) {
			return;
		}
		echo '<nav class="bcp-tabs" aria-label="' . esc_attr__( 'Sezioni', 'bracelli-club' ) . '">';
		foreach ( $items as $view => $label ) {
			$active = $view === $current || ( 'turni' === $view && in_array( $current, [ 'modifica', 'annulla' ], true ) );
			printf(
				'<a class="bcp-tab%1$s" href="%2$s"%3$s>%4$s</a>',
				$active ? ' is-active' : '',
				esc_url( self::url( [ 'vista' => $view ] ) ),
				$active ? ' aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';
	}

	/**
	 * Messaggio di esito dopo un redirect.
	 */
	private function notice(): void {
		$messages = [
			'created'           => [ 'success', __( 'Turno creato.', 'bracelli-club' ) ],
			'updated'           => [ 'success', __( 'Turno aggiornato.', 'bracelli-club' ) ],
			'unchanged'         => [ 'info', __( 'Nessuna modifica.', 'bracelli-club' ) ],
			'cancelled'         => [ 'success', __( 'Turno annullato. Resta visibile nello storico.', 'bracelli-club' ) ],
			'attendance_saved'  => [ 'success', __( 'Registrazione salvata.', 'bracelli-club' ) ],
			'forbidden'         => [ 'error', __( 'Operazione non consentita al tuo account.', 'bracelli-club' ) ],
			'not_found'         => [ 'error', __( 'Turno non trovato.', 'bracelli-club' ) ],
			'shift_cancelled'   => [ 'error', __( 'Il turno è annullato: non si può più modificare né registrare.', 'bracelli-club' ) ],
			'has_attendance'    => [ 'error', __( 'Il dipendente ha già registrato presenza o assenza: il turno non si può più modificare né annullare.', 'bracelli-club' ) ],
			'too_early'         => [ 'error', __( 'Potrai registrare presenza o assenza dal giorno del turno.', 'bracelli-club' ) ],
			'attendance_locked' => [ 'error', __( 'La registrazione si può cambiare solo nel giorno del turno.', 'bracelli-club' ) ],
			'expired'           => [ 'error', __( 'La pagina era scaduta: l\'operazione non è stata eseguita. Riprova.', 'bracelli-club' ) ],
			'invalid'           => [ 'error', __( 'Dati non validi: l\'operazione non è stata eseguita.', 'bracelli-club' ) ],
			'db_error'          => [ 'error', __( 'Salvataggio non riuscito. Riprova; se il problema continua avvisa l\'amministratore.', 'bracelli-club' ) ],
		];
		$code     = $this->query( 'esito' );
		if ( isset( $messages[ $code ] ) ) {
			[ $type, $text ] = $messages[ $code ];
			echo '<p class="bcp-alert bcp-alert--' . esc_attr( $type ) . '" role="' . ( 'error' === $type ? 'alert' : 'status' ) . '">' . esc_html( $text ) . '</p>';
		}
	}

	/**
	 * Elenco turni e presenze (segreteria).
	 *
	 * @param int $user Utente.
	 */
	private function view_list( int $user ): void {
		$today     = $this->service->today();
		$from      = $this->query_date( 'dal', $today );
		$to        = $this->query_date( 'al', gmdate( 'Y-m-d', (int) strtotime( $today . ' +13 days' ) ) );
		$employees = $this->service->employees( $user );
		if ( $to < $from ) {
			$to = $from;
		}
		$max = gmdate( 'Y-m-d', (int) strtotime( $from . ' +92 days' ) );
		if ( $to > $max ) {
			$to = $max;
		}
		$employee   = absint( $this->query( 'dipendente' ) );
		$status     = in_array( $this->query( 'stato' ), [ 'scheduled', 'cancelled' ], true ) ? $this->query( 'stato' ) : '';
		$attendance = in_array( $this->query( 'presenza' ), [ 'present', 'absent', 'none' ], true ) ? $this->query( 'presenza' ) : '';
		$rows       = (array) $this->service->list_for_manager(
			$user,
			[
				'from'        => $from,
				'to'          => $to,
				'employee_id' => isset( $employees[ $employee ] ) ? $employee : 0,
				'status'      => $status,
				'attendance'  => $attendance,
			]
		);
		$sum        = ShiftRepository::summarize( $rows );
		?>
<form class="bcp-card bcp-filters" method="get" action="<?php echo esc_url( self::url() ); ?>">
		<?php if ( '' === (string) get_option( 'permalink_structure' ) ) : ?>
	<input type="hidden" name="<?php echo esc_attr( self::QUERY_VAR ); ?>" value="1">
		<?php endif; ?>
	<input type="hidden" name="vista" value="turni">
	<div class="bcp-field"><label for="bcp-dal"><?php esc_html_e( 'Dal', 'bracelli-club' ); ?></label><input id="bcp-dal" type="date" name="dal" value="<?php echo esc_attr( $from ); ?>"></div>
	<div class="bcp-field"><label for="bcp-al"><?php esc_html_e( 'Al', 'bracelli-club' ); ?></label><input id="bcp-al" type="date" name="al" value="<?php echo esc_attr( $to ); ?>"></div>
	<div class="bcp-field"><label for="bcp-dip"><?php esc_html_e( 'Dipendente', 'bracelli-club' ); ?></label>
		<select id="bcp-dip" name="dipendente"><option value=""><?php esc_html_e( 'Tutti', 'bracelli-club' ); ?></option>
		<?php foreach ( $employees as $id => $name ) : ?>
			<option value="<?php echo esc_attr( (string) $id ); ?>"<?php selected( $employee, $id ); ?>><?php echo esc_html( $name ); ?></option>
		<?php endforeach; ?>
		</select></div>
	<div class="bcp-field"><label for="bcp-stato"><?php esc_html_e( 'Turno', 'bracelli-club' ); ?></label>
		<select id="bcp-stato" name="stato">
			<option value=""><?php esc_html_e( 'Tutti', 'bracelli-club' ); ?></option>
			<option value="scheduled"<?php selected( $status, 'scheduled' ); ?>><?php esc_html_e( 'Programmati', 'bracelli-club' ); ?></option>
			<option value="cancelled"<?php selected( $status, 'cancelled' ); ?>><?php esc_html_e( 'Annullati', 'bracelli-club' ); ?></option>
		</select></div>
	<div class="bcp-field"><label for="bcp-pres"><?php esc_html_e( 'Presenza', 'bracelli-club' ); ?></label>
		<select id="bcp-pres" name="presenza">
			<option value=""><?php esc_html_e( 'Tutte', 'bracelli-club' ); ?></option>
			<option value="present"<?php selected( $attendance, 'present' ); ?>><?php esc_html_e( 'Presente', 'bracelli-club' ); ?></option>
			<option value="absent"<?php selected( $attendance, 'absent' ); ?>><?php esc_html_e( 'Assente', 'bracelli-club' ); ?></option>
			<option value="none"<?php selected( $attendance, 'none' ); ?>><?php esc_html_e( 'Non registrata', 'bracelli-club' ); ?></option>
		</select></div>
	<button class="bcp-button" type="submit"><?php esc_html_e( 'Filtra', 'bracelli-club' ); ?></button>
</form>
<ul class="bcp-stats" aria-label="<?php esc_attr_e( 'Riepilogo del periodo', 'bracelli-club' ); ?>">
	<li><strong><?php echo esc_html( (string) $sum['total'] ); ?></strong> <?php esc_html_e( 'turni', 'bracelli-club' ); ?></li>
	<li class="is-present"><strong><?php echo esc_html( (string) $sum['present'] ); ?></strong> <?php esc_html_e( 'presenti', 'bracelli-club' ); ?></li>
	<li class="is-absent"><strong><?php echo esc_html( (string) $sum['absent'] ); ?></strong> <?php esc_html_e( 'assenti', 'bracelli-club' ); ?></li>
	<li><strong><?php echo esc_html( (string) $sum['none'] ); ?></strong> <?php esc_html_e( 'da registrare', 'bracelli-club' ); ?></li>
	<li class="is-cancelled"><strong><?php echo esc_html( (string) $sum['cancelled'] ); ?></strong> <?php esc_html_e( 'annullati', 'bracelli-club' ); ?></li>
</ul>
		<?php
		if ( [] === $rows ) {
			echo '<div class="bcp-card"><p>' . esc_html__( 'Nessun turno nel periodo e con i filtri scelti.', 'bracelli-club' ) . '</p><p><a class="bcp-button bcp-button--primary" href="' . esc_url( self::url( [ 'vista' => 'nuovo' ] ) ) . '">' . esc_html__( 'Crea un turno', 'bracelli-club' ) . '</a></p></div>';
			return;
		}
		echo '<div class="bcp-table-wrap"><table class="bcp-table"><caption class="bcp-sr">' . esc_html__( 'Turni del periodo', 'bracelli-club' ) . '</caption><thead><tr>';
		foreach ( [ __( 'Data', 'bracelli-club' ), __( 'Orario', 'bracelli-club' ), __( 'Dipendente', 'bracelli-club' ), __( 'Turno', 'bracelli-club' ), __( 'Presenza', 'bracelli-club' ), __( 'Azioni', 'bracelli-club' ) ] as $th ) {
			echo '<th scope="col">' . esc_html( $th ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$editable = '' === $this->service->lock_reason( $row );
			echo '<tr class="' . ( 'cancelled' === $row['status'] ? 'is-cancelled' : '' ) . '">';
			echo '<td data-label="' . esc_attr__( 'Data', 'bracelli-club' ) . '">' . esc_html( $this->format_date( (string) $row['shift_date'] ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Orario', 'bracelli-club' ) . '">' . esc_html( $this->format_slot( $row ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Dipendente', 'bracelli-club' ) . '">' . esc_html( $this->user_name( (int) $row['employee_id'] ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Turno', 'bracelli-club' ) . '">' . $this->status_badge( (string) $row['status'] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup costruito con escape in status_badge().
			echo '<td data-label="' . esc_attr__( 'Presenza', 'bracelli-club' ) . '">' . $this->attendance_badge( $row ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup costruito con escape in attendance_badge().
			echo '<td data-label="' . esc_attr__( 'Azioni', 'bracelli-club' ) . '" class="bcp-actions">';
			echo '<a class="bcp-link" href="' . esc_url(
				self::url(
					[
						'vista' => 'modifica',
						'turno' => $row['id'],
					]
				)
			) . '">' . esc_html( $editable ? __( 'Modifica', 'bracelli-club' ) : __( 'Dettagli', 'bracelli-club' ) ) . '</a>';
			if ( $editable ) {
				echo ' <a class="bcp-link bcp-link--danger" href="' . esc_url(
					self::url(
						[
							'vista' => 'annulla',
							'turno' => $row['id'],
						]
					)
				) . '">' . esc_html__( 'Annulla', 'bracelli-club' ) . '</a>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
		if ( count( $rows ) >= 500 ) {
			echo '<p class="bcp-muted">' . esc_html__( 'Mostrati i primi 500 turni: restringi il periodo o i filtri.', 'bracelli-club' ) . '</p>';
		}
	}

	/**
	 * Nuovo turno (segreteria).
	 *
	 * @param int $user Utente.
	 */
	private function view_create( int $user ): void {
		$employees = $this->service->employees( $user );
		if ( [] === $employees ) {
			echo '<div class="bcp-card"><p>' . esc_html__( 'Non ci sono ancora dipendenti a cui assegnare turni. Un amministratore deve creare gli account con il ruolo "Dipendente Bracelli".', 'bracelli-club' ) . '</p></div>';
			return;
		}
		$selected = absint( $this->old['employee_id'] ?? '' );
		?>
<form class="bcp-card bcp-form" method="post" action="<?php echo esc_url( self::url( [ 'vista' => 'nuovo' ] ) ); ?>" novalidate>
	<input type="hidden" name="bc_action" value="create">
	<input type="hidden" name="_bc_nonce" value="<?php echo esc_attr( wp_create_nonce( 'bracelli_turni_create' ) ); ?>">
		<?php $this->form_errors(); ?>
	<label for="bcp-employee"><?php esc_html_e( 'Dipendente', 'bracelli-club' ); ?></label>
	<select id="bcp-employee" name="employee_id" required<?php $this->invalid_attr( 'employee_id' ); ?>>
		<option value=""><?php esc_html_e( '— Scegli —', 'bracelli-club' ); ?></option>
		<?php foreach ( $employees as $id => $name ) : ?>
		<option value="<?php echo esc_attr( (string) $id ); ?>"<?php selected( $selected, $id ); ?>><?php echo esc_html( $name ); ?></option>
		<?php endforeach; ?>
	</select>
		<?php $this->field_error( 'employee_id' ); ?>
		<?php $this->slot_fields( $this->old + [ 'shift_date' => $this->service->today() ] ); ?>
	<div class="bcp-row">
		<button class="bcp-button bcp-button--primary" type="submit"><?php esc_html_e( 'Crea turno', 'bracelli-club' ); ?></button>
		<a class="bcp-link" href="<?php echo esc_url( self::url( [ 'vista' => 'turni' ] ) ); ?>"><?php esc_html_e( 'Torna all\'elenco', 'bracelli-club' ); ?></a>
	</div>
</form>
		<?php
	}

	/**
	 * Dettaglio, modifica e storico di un turno (segreteria).
	 *
	 * @param int $user Utente.
	 */
	private function view_detail( int $user ): void {
		$shift = $this->service->get_visible( $user, absint( $this->query( 'turno' ) ) );
		if ( null === $shift ) {
			$this->not_found();
			return;
		}
		$lock = $this->service->lock_reason( $shift );
		$this->summary( $shift );
		if ( '' === $lock ) {
			$values = [] !== $this->old ? $this->old : [
				'shift_date' => (string) $shift['shift_date'],
				'start_time' => substr( (string) $shift['start_time'], 0, 5 ),
				'end_time'   => substr( (string) $shift['end_time'], 0, 5 ),
			];
			?>
<form class="bcp-card bcp-form" method="post" action="
			<?php
			echo esc_url(
				self::url(
					[
						'vista' => 'modifica',
						'turno' => $shift['id'],
					]
				)
			);
			?>
														" novalidate>
	<h2><?php esc_html_e( 'Modifica data e orario', 'bracelli-club' ); ?></h2>
	<input type="hidden" name="bc_action" value="update">
	<input type="hidden" name="turno" value="<?php echo esc_attr( (string) $shift['id'] ); ?>">
	<input type="hidden" name="_bc_nonce" value="<?php echo esc_attr( wp_create_nonce( 'bracelli_turni_update_' . $shift['id'] ) ); ?>">
			<?php $this->form_errors(); ?>
			<?php $this->slot_fields( $values ); ?>
	<div class="bcp-row">
		<button class="bcp-button bcp-button--primary" type="submit"><?php esc_html_e( 'Salva modifiche', 'bracelli-club' ); ?></button>
		<a class="bcp-link bcp-link--danger" href="
			<?php
			echo esc_url(
				self::url(
					[
						'vista' => 'annulla',
						'turno' => $shift['id'],
					]
				)
			);
			?>
													"><?php esc_html_e( 'Annulla il turno', 'bracelli-club' ); ?></a>
	</div>
</form>
			<?php
		} else {
			$why = 'has_attendance' === $lock
				? __( 'Il dipendente ha già registrato presenza o assenza: il turno non è più modificabile.', 'bracelli-club' )
				: __( 'Turno annullato: non è più modificabile.', 'bracelli-club' );
			echo '<p class="bcp-alert bcp-alert--info">' . esc_html( $why ) . '</p>';
		}
		$this->history( $user, (int) $shift['id'] );
		echo '<p><a class="bcp-link" href="' . esc_url( self::url( [ 'vista' => 'turni' ] ) ) . '">' . esc_html__( '← Torna all\'elenco', 'bracelli-club' ) . '</a></p>';
	}

	/**
	 * Conferma di annullamento (segreteria).
	 *
	 * @param int $user Utente.
	 */
	private function view_cancel( int $user ): void {
		$shift = $this->service->get_visible( $user, absint( $this->query( 'turno' ) ) );
		if ( null === $shift ) {
			$this->not_found();
			return;
		}
		$this->summary( $shift );
		if ( '' !== $this->service->lock_reason( $shift ) ) {
			echo '<p class="bcp-alert bcp-alert--info">' . esc_html__( 'Questo turno non si può annullare.', 'bracelli-club' ) . '</p>';
			return;
		}
		?>
<form class="bcp-card bcp-form" method="post" action="
		<?php
		echo esc_url(
			self::url(
				[
					'vista' => 'annulla',
					'turno' => $shift['id'],
				]
			)
		);
		?>
														">
	<p><?php esc_html_e( 'Il turno verrà segnato come annullato: non sarà eliminato e resterà nello storico. Il dipendente lo vedrà come annullato.', 'bracelli-club' ); ?></p>
	<input type="hidden" name="bc_action" value="cancel">
	<input type="hidden" name="turno" value="<?php echo esc_attr( (string) $shift['id'] ); ?>">
	<input type="hidden" name="_bc_nonce" value="<?php echo esc_attr( wp_create_nonce( 'bracelli_turni_cancel_' . $shift['id'] ) ); ?>">
	<div class="bcp-row">
		<button class="bcp-button bcp-button--danger" type="submit"><?php esc_html_e( 'Conferma annullamento', 'bracelli-club' ); ?></button>
		<a class="bcp-link" href="
		<?php
		echo esc_url(
			self::url(
				[
					'vista' => 'modifica',
					'turno' => $shift['id'],
				]
			)
		);
		?>
									"><?php esc_html_e( 'No, torna indietro', 'bracelli-club' ); ?></a>
	</div>
</form>
		<?php
	}

	/**
	 * Turni del dipendente con registrazione Presente/Assente.
	 *
	 * @param int $user Utente.
	 */
	private function view_mine( int $user ): void {
		$today    = $this->service->today();
		$upcoming = (array) $this->service->list_for_employee( $user, $today, gmdate( 'Y-m-d', (int) strtotime( $today . ' +30 days' ) ) );
		$past     = array_reverse( (array) $this->service->list_for_employee( $user, gmdate( 'Y-m-d', (int) strtotime( $today . ' -30 days' ) ), gmdate( 'Y-m-d', (int) strtotime( $today . ' -1 day' ) ) ) );

		echo '<section aria-labelledby="bcp-h-next"><h2 id="bcp-h-next">' . esc_html__( 'Oggi e prossimi 30 giorni', 'bracelli-club' ) . '</h2>';
		if ( [] === $upcoming ) {
			echo '<p class="bcp-card">' . esc_html__( 'Nessun turno programmato.', 'bracelli-club' ) . '</p>';
		}
		foreach ( $upcoming as $shift ) {
			$this->employee_card( $shift, $today );
		}
		echo '</section><section aria-labelledby="bcp-h-past"><h2 id="bcp-h-past">' . esc_html__( 'Ultimi 30 giorni', 'bracelli-club' ) . '</h2>';
		if ( [] === $past ) {
			echo '<p class="bcp-card">' . esc_html__( 'Nessun turno.', 'bracelli-club' ) . '</p>';
		}
		foreach ( $past as $shift ) {
			$this->employee_card( $shift, $today );
		}
		echo '</section>';
	}

	/**
	 * Scheda di un turno per il dipendente.
	 *
	 * @param array<string, mixed> $shift Turno.
	 * @param string               $today Oggi.
	 */
	private function employee_card( array $shift, string $today ): void {
		$reason   = $this->service->attendance_block_reason( $shift );
		$is_today = (string) $shift['shift_date'] === $today;
		echo '<article class="bcp-card bcp-shift' . ( 'cancelled' === $shift['status'] ? ' is-cancelled' : '' ) . ( $is_today ? ' is-today' : '' ) . '">';
		echo '<div class="bcp-shift__head"><p class="bcp-shift__date">' . ( $is_today ? '<span class="bcp-badge bcp-badge--today">' . esc_html__( 'Oggi', 'bracelli-club' ) . '</span> ' : '' ) . esc_html( $this->format_date( (string) $shift['shift_date'] ) ) . '</p>';
		echo '<p class="bcp-shift__time">' . esc_html( $this->format_slot( $shift ) ) . '</p></div>';
		echo '<p class="bcp-shift__state">' . $this->status_badge( (string) $shift['status'] ) . ' ' . $this->attendance_badge( $shift ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup con escape.
		if ( '' === $reason ) {
			echo '<form class="bcp-attendance" method="post" action="' . esc_url( self::url( [ 'vista' => 'miei' ] ) ) . '">';
			echo '<input type="hidden" name="bc_action" value="attendance"><input type="hidden" name="turno" value="' . esc_attr( (string) $shift['id'] ) . '">';
			echo '<input type="hidden" name="_bc_nonce" value="' . esc_attr( wp_create_nonce( 'bracelli_turni_attendance_' . $shift['id'] ) ) . '">';
			foreach ( [
				'present' => __( 'Presente', 'bracelli-club' ),
				'absent'  => __( 'Assente', 'bracelli-club' ),
			] as $value => $label ) {
				$current = $value === $shift['attendance_status'];
				printf(
					'<button class="bcp-button bcp-button--%1$s%2$s" type="submit" name="presenza" value="%3$s" aria-pressed="%4$s">%5$s</button>',
					esc_attr( $value ),
					$current ? ' is-selected' : '',
					esc_attr( $value ),
					$current ? 'true' : 'false',
					esc_html( $label )
				);
			}
			echo '</form>';
			if ( null !== $shift['attendance_status'] ) {
				echo '<p class="bcp-muted">' . esc_html__( 'Puoi cambiare la scelta fino alla fine della giornata.', 'bracelli-club' ) . '</p>';
			}
		} elseif ( 'too_early' === $reason ) {
			echo '<p class="bcp-muted">' . esc_html__( 'Potrai registrare presenza o assenza dal giorno del turno.', 'bracelli-club' ) . '</p>';
		}
		echo '</article>';
	}

	/* ------------------------------------------------------------------
	 * Parti comuni
	 * ------------------------------------------------------------------ */

	/**
	 * Campi data e orari.
	 *
	 * @param array<string, string> $values Valori.
	 */
	private function slot_fields( array $values ): void {
		?>
	<label for="bcp-date"><?php esc_html_e( 'Data', 'bracelli-club' ); ?></label>
	<input id="bcp-date" type="date" name="shift_date" required value="<?php echo esc_attr( $values['shift_date'] ?? '' ); ?>"<?php $this->invalid_attr( 'shift_date' ); ?>>
		<?php $this->field_error( 'shift_date' ); ?>
	<div class="bcp-row bcp-row--fields">
		<div class="bcp-field"><label for="bcp-start"><?php esc_html_e( 'Inizio', 'bracelli-club' ); ?></label>
		<input id="bcp-start" type="time" name="start_time" required step="60" value="<?php echo esc_attr( $values['start_time'] ?? '' ); ?>"<?php $this->invalid_attr( 'start_time' ); ?>>
		<?php $this->field_error( 'start_time' ); ?></div>
		<div class="bcp-field"><label for="bcp-end"><?php esc_html_e( 'Fine', 'bracelli-club' ); ?></label>
		<input id="bcp-end" type="time" name="end_time" required step="60" value="<?php echo esc_attr( $values['end_time'] ?? '' ); ?>"<?php $this->invalid_attr( 'end_time' ); ?>>
		<?php $this->field_error( 'end_time' ); ?></div>
	</div>
		<?php
	}

	/**
	 * Riepilogo errori del modulo.
	 */
	private function form_errors(): void {
		if ( [] !== $this->errors ) {
			echo '<p class="bcp-alert bcp-alert--error" role="alert">' . esc_html__( 'Controlla i campi evidenziati: il turno non è stato salvato.', 'bracelli-club' ) . '</p>';
		}
	}

	/**
	 * @param string $field Campo.
	 */
	private function field_error( string $field ): void {
		if ( isset( $this->errors[ $field ] ) ) {
			echo '<p class="bcp-field-error" id="bcp-err-' . esc_attr( $field ) . '">' . esc_html( $this->errors[ $field ] ) . '</p>';
		}
	}

	/**
	 * @param string $field Campo.
	 */
	private function invalid_attr( string $field ): void {
		if ( isset( $this->errors[ $field ] ) ) {
			echo ' aria-invalid="true" aria-describedby="bcp-err-' . esc_attr( $field ) . '"';
		}
	}

	/**
	 * Riepilogo di un turno.
	 *
	 * @param array<string, mixed> $shift Turno.
	 */
	private function summary( array $shift ): void {
		echo '<dl class="bcp-card bcp-summary">';
		echo '<div><dt>' . esc_html__( 'Dipendente', 'bracelli-club' ) . '</dt><dd>' . esc_html( $this->user_name( (int) $shift['employee_id'] ) ) . '</dd></div>';
		echo '<div><dt>' . esc_html__( 'Data', 'bracelli-club' ) . '</dt><dd>' . esc_html( $this->format_date( (string) $shift['shift_date'] ) ) . '</dd></div>';
		echo '<div><dt>' . esc_html__( 'Orario', 'bracelli-club' ) . '</dt><dd>' . esc_html( $this->format_slot( $shift ) ) . '</dd></div>';
		echo '<div><dt>' . esc_html__( 'Turno', 'bracelli-club' ) . '</dt><dd>' . $this->status_badge( (string) $shift['status'] ) . '</dd></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup con escape.
		echo '<div><dt>' . esc_html__( 'Presenza', 'bracelli-club' ) . '</dt><dd>' . $this->attendance_badge( $shift ) . '</dd></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup con escape.
		echo '</dl>';
	}

	/**
	 * Storico del turno dal registro attività.
	 *
	 * @param int $user Utente.
	 * @param int $id   Turno.
	 */
	private function history( int $user, int $id ): void {
		$labels = [
			'turni.shift_created'       => __( 'Turno creato', 'bracelli-club' ),
			'turni.shift_updated'       => __( 'Data o orario modificati', 'bracelli-club' ),
			'turni.shift_cancelled'     => __( 'Turno annullato', 'bracelli-club' ),
			'turni.attendance_recorded' => __( 'Presenza registrata', 'bracelli-club' ),
			'turni.attendance_changed'  => __( 'Registrazione cambiata', 'bracelli-club' ),
		];
		echo '<section class="bcp-card" aria-labelledby="bcp-h-history"><h2 id="bcp-h-history">' . esc_html__( 'Storico', 'bracelli-club' ) . '</h2>';
		$items = $this->service->history( $user, $id );
		if ( [] === $items ) {
			echo '<p class="bcp-muted">' . esc_html__( 'Nessun evento registrato.', 'bracelli-club' ) . '</p></section>';
			return;
		}
		echo '<ol class="bcp-history">';
		foreach ( $items as $item ) {
			$when   = wp_date( 'j M Y, H:i', (int) strtotime( (string) $item['occurred_at'] . ' UTC' ) );
			$label  = $labels[ (string) $item['action'] ] ?? (string) $item['action'];
			$detail = $this->history_detail( (string) $item['action'], is_array( $item['diff'] ) ? $item['diff'] : [] );
			echo '<li><span class="bcp-history__when">' . esc_html( (string) $when ) . '</span> <strong>' . esc_html( $label ) . '</strong> — ' . esc_html( $this->user_name( (int) $item['actor_id'] ) );
			if ( '' !== $detail ) {
				echo '<br><span class="bcp-muted">' . esc_html( $detail ) . '</span>';
			}
			echo '</li>';
		}
		echo '</ol></section>';
	}

	/**
	 * Testo dei dettagli di un evento.
	 *
	 * @param string               $action Azione.
	 * @param array<string, mixed> $diff   Dettagli.
	 */
	private function history_detail( string $action, array $diff ): string {
		$slot = fn( $d ): string => is_array( $d ) && isset( $d['shift_date'], $d['start_time'], $d['end_time'] )
			? $this->format_date( (string) $d['shift_date'] ) . ' ' . $this->format_slot( $d )
			: '';
		$att  = static fn( $v ): string => 'present' === $v ? __( 'Presente', 'bracelli-club' ) : ( 'absent' === $v ? __( 'Assente', 'bracelli-club' ) : '—' );
		return match ( $action ) {
			'turni.shift_created'       => $slot( $diff ),
			'turni.shift_updated'       => $slot( $diff['before'] ?? null ) . ' → ' . $slot( $diff['after'] ?? null ),
			'turni.attendance_recorded' => $att( $diff['after'] ?? null ),
			'turni.attendance_changed'  => $att( $diff['before'] ?? null ) . ' → ' . $att( $diff['after'] ?? null ),
			default                     => '',
		};
	}

	/**
	 * Turno non trovato o non visibile.
	 */
	private function not_found(): void {
		status_header( 404 );
		echo '<p class="bcp-alert bcp-alert--error">' . esc_html__( 'Turno non trovato.', 'bracelli-club' ) . '</p>';
		echo '<p><a class="bcp-link" href="' . esc_url( self::url() ) . '">' . esc_html__( '← Torna all\'elenco', 'bracelli-club' ) . '</a></p>';
	}

	/**
	 * @param string $status Stato del turno.
	 */
	private function status_badge( string $status ): string {
		return 'cancelled' === $status
			? '<span class="bcp-badge bcp-badge--cancelled">' . esc_html__( 'Annullato', 'bracelli-club' ) . '</span>'
			: '<span class="bcp-badge bcp-badge--scheduled">' . esc_html__( 'Programmato', 'bracelli-club' ) . '</span>';
	}

	/**
	 * @param array<string, mixed> $shift Turno con presenza.
	 */
	private function attendance_badge( array $shift ): string {
		if ( null === $shift['attendance_status'] ) {
			return 'cancelled' === $shift['status'] ? '' : '<span class="bcp-badge">' . esc_html__( 'Non registrata', 'bracelli-club' ) . '</span>';
		}
		$present = 'present' === $shift['attendance_status'];
		$when    = (string) ( $shift['attendance_updated_at'] ?? '' ) !== '' ? (string) $shift['attendance_updated_at'] : (string) $shift['attendance_recorded_at'];
		return sprintf(
			'<span class="bcp-badge bcp-badge--%1$s">%2$s</span> <span class="bcp-muted">%3$s</span>',
			$present ? 'present' : 'absent',
			esc_html( $present ? __( 'Presente', 'bracelli-club' ) : __( 'Assente', 'bracelli-club' ) ),
			/* translators: %s: data e ora della registrazione. */
			esc_html( sprintf( __( 'registrata il %s', 'bracelli-club' ), (string) wp_date( 'j M, H:i', (int) strtotime( $when . ' UTC' ) ) ) )
		);
	}

	/**
	 * @param string $date Data Y-m-d (ora locale del sito).
	 */
	private function format_date( string $date ): string {
		return (string) wp_date( 'D j M Y', (int) strtotime( $date . ' 12:00:00 UTC' ), new \DateTimeZone( 'UTC' ) );
	}

	/**
	 * @param array<string, mixed> $row Turno.
	 */
	private function format_slot( array $row ): string {
		return substr( (string) $row['start_time'], 0, 5 ) . '–' . substr( (string) $row['end_time'], 0, 5 );
	}

	/**
	 * Nome visualizzato di un utente (anche se l'account è stato rimosso).
	 *
	 * @param int $id Utente.
	 */
	private function user_name( int $id ): string {
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
	private function query( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo lettura per scegliere la vista.
		return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : '';
	}

	/**
	 * Data GET valida (Y-m-d) oppure il valore predefinito.
	 *
	 * @param string $key     Nome.
	 * @param string $fallback Predefinito.
	 */
	private function query_date( string $key, string $fallback ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtro di sola lettura.
		$value = isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return $value;
		}
		return $fallback;
	}
}
