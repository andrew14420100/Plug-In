<?php
/**
 * Sezione "Catalogo e sale" dell'area riservata (segreteria).
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Corsi;

use Bracelli\Club\Modules\Area\Portal;
use Bracelli\Club\Modules\Area\SectionInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Schede dei corsi (nuovo, modifica, duplica, archivia), sala per tutto il corso, conferma degli orari
 * "da confermare" ed elenco delle sale. L'elenco dei corsi con gli orari resta nella scheda "Corsi e orari"
 * (CorsiSection). I permessi li verificano CatalogService e RoomService.
 */
final class CatalogoSection implements SectionInterface {

	/**
	 * Portale della richiesta corrente.
	 *
	 * @var Portal
	 */
	private Portal $p;

	/**
	 * @param CatalogService $catalog Catalogo.
	 * @param RoomService    $rooms   Sale.
	 * @param CourseService  $courses Corsi e orari (sezioni esistenti).
	 */
	public function __construct( private CatalogService $catalog, private RoomService $rooms, private CourseService $courses ) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'catalogo';
	}

	/**
	 * {@inheritDoc}
	 */
	public function priority(): int {
		return 11;
	}

	/**
	 * {@inheritDoc}
	 */
	public function views( int $user ): array {
		if ( ! $this->catalog->can_manage( $user ) ) {
			return [];
		}
		return [
			'nuovo-corso'    => __( 'Nuovo corso', 'bracelli-club' ),
			'corso'          => __( 'Scheda del corso', 'bracelli-club' ),
			'duplica-corso'  => __( 'Duplica corso', 'bracelli-club' ),
			'archivia-corso' => __( 'Archivia corso', 'bracelli-club' ),
			'sala-corso'     => __( 'Sala del corso', 'bracelli-club' ),
			'sale'           => __( 'Sale', 'bracelli-club' ),
			'sala'           => __( 'Modifica sala', 'bracelli-club' ),
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function tabs( int $user ): array {
		return $this->catalog->can_manage( $user ) ? [ 'sale' => __( 'Sale', 'bracelli-club' ) ] : [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function tab_for( string $view ): string {
		return in_array( $view, [ 'sale', 'sala' ], true ) ? 'sale' : 'orari';
	}

	/**
	 * {@inheritDoc}
	 */
	public function messages(): array {
		return self::all_messages();
	}

	/**
	 * Messaggi di catalogo e sale (usati anche dalla scheda "Corsi e orari", dove tornano molti redirect).
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function all_messages(): array {
		return [
			'course_created'    => [ 'success', __( 'Corso creato. Ora puoi aggiungere gli orari.', 'bracelli-club' ) ],
			'course_updated'    => [ 'success', __( 'Scheda del corso aggiornata. La modifica è nello storico.', 'bracelli-club' ) ],
			'course_duplicated' => [ 'success', __( 'Corso duplicato. Gli orari copiati sono "da confermare": controllali e confermali uno per uno.', 'bracelli-club' ) ],
			'course_archived'   => [ 'success', __( 'Corso archiviato: non compare più nel calendario. Orari e presenze restano conservati.', 'bracelli-club' ) ],
			'course_restored'   => [ 'success', __( 'Corso ripristinato.', 'bracelli-club' ) ],
			'room_assigned'     => [ 'success', __( 'Sala assegnata agli orari del corso.', 'bracelli-club' ) ],
			'slot_confirmed'    => [ 'success', __( 'Orario confermato: ora compare nel calendario.', 'bracelli-club' ) ],
			'room_created'      => [ 'success', __( 'Sala aggiunta.', 'bracelli-club' ) ],
			'room_updated'      => [ 'success', __( 'Sala aggiornata.', 'bracelli-club' ) ],
			'room_archived'     => [ 'success', __( 'Sala archiviata.', 'bracelli-club' ) ],
			'room_restored'     => [ 'success', __( 'Sala ripristinata.', 'bracelli-club' ) ],
			'room_in_use'       => [ 'error', __( 'La sala è usata da orari attivi: assegna prima un\'altra sala a quegli orari.', 'bracelli-club' ) ],
			'room_conflict'     => [ 'error', __( 'Sala già occupata in quella fascia: nessuna modifica salvata.', 'bracelli-club' ) ],
			'not_draft'         => [ 'info', __( 'L\'orario non è più da confermare.', 'bracelli-club' ) ],
			'busy'              => [ 'error', __( 'Un\'altra modifica è in corso: riprova tra qualche secondo.', 'bracelli-club' ) ],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function handle( Portal $portal, int $user, string $action, int $id ): void {
		$this->p = $portal;
		// "name" è una variabile di query di WordPress: nei moduli il campo si chiama "nome".
		$fields = [ 'nome', 'section', 'group_label', 'level', 'description', 'notes', 'color' ];
		switch ( $action ) {
			case 'create':
				$input  = $portal->posted( $fields );
				$result = $this->catalog->create( $user, self::named( $input ) );
				$this->after( $result, $input, 'nuovo-corso', [ 'vista' => 'corso' ] );
				return;
			case 'update':
				$input  = $portal->posted( $fields );
				$result = $this->catalog->update( $user, $id, self::named( $input ) );
				$this->after( $result, $input, 'corso', [ 'vista' => 'corso' ], $id );
				return;
			case 'duplicate':
				$input  = $portal->posted( array_merge( $fields, [ 'copy_slots' ] ) );
				$result = $this->catalog->duplicate( $user, $id, self::named( $input ) );
				$this->after( $result, $input, 'duplica-corso', [ 'vista' => 'corso' ] );
				return;
			case 'archive':
			case 'restore':
				$result = $this->catalog->set_archived( $user, $id, 'archive' === $action );
				$portal->redirect(
					[
						'vista' => 'corso',
						'corso' => $id,
						'esito' => $result['code'],
					]
				);
				// Nessun break: redirect() termina la richiesta.
			case 'assignroom':
				$input  = $portal->posted( [ 'room_id', 'only_empty' ] );
				$result = $this->catalog->assign_room( $user, $id, absint( $input['room_id'] ), ! empty( $input['only_empty'] ) );
				$this->after( $result, $input, 'sala-corso', [ 'vista' => 'corso' ], $id );
				return;
			case 'confirm':
				$result = $this->catalog->confirm_slot( $user, $id );
				// Se la sala è occupata si apre l'orario, dove si può scegliere un'altra sala o un'altra fascia.
				$portal->redirect(
					'room_conflict' === $result['code'] ? [
						'vista'  => 'orario',
						'orario' => $id,
						'esito'  => $result['code'],
					] : [
						'vista' => 'orari',
						'esito' => $result['code'],
					]
				);
				// Nessun break: redirect() termina la richiesta.
			case 'roomcreate':
				$input  = $portal->posted( [ 'nome', 'notes' ] );
				$result = $this->rooms->create( $user, self::named( $input ) );
				$this->after( $result, $input, 'sale', [ 'vista' => 'sale' ], 0, [ 'vista' => 'sale' ] );
				return;
			case 'roomupdate':
				$input  = $portal->posted( [ 'nome', 'notes' ] );
				$result = $this->rooms->update( $user, $id, self::named( $input ) );
				$this->after( $result, $input, 'sala', [ 'vista' => 'sale' ], $id, [ 'vista' => 'sale' ] );
				return;
			case 'roomarchive':
			case 'roomrestore':
				$result = $this->rooms->set_archived( $user, $id, 'roomarchive' === $action );
				$portal->redirect(
					[
						'vista' => 'sale',
						'esito' => $result['code'],
					]
				);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( Portal $portal, int $user, string $view ): void {
		$this->p = $portal;
		match ( $view ) {
			'nuovo-corso'    => $this->view_course_form( $user, null, 'create' ),
			'corso'          => $this->view_course( $user ),
			'duplica-corso'  => $this->view_duplicate( $user ),
			'archivia-corso' => $this->view_archive( $user ),
			'sala-corso'     => $this->view_assign_room( $user ),
			'sala'           => $this->view_room( $user ),
			default          => $this->view_rooms( $user ),
		};
	}

	/**
	 * Dopo un'azione: redirect in caso di successo (o di errore non legato ai campi), altrimenti di nuovo il modulo
	 * con gli errori. Una sala occupata è mostrata sul campo della sala.
	 *
	 * @param array{ok: bool, code: string, errors: array<string, string>, id: int} $result Esito.
	 * @param array<string, string>                                                 $input  Dati inviati.
	 * @param string                                                                $view   Vista del modulo.
	 * @param array<string, string|int>                                             $done   Destinazione in caso di successo.
	 * @param int                                                                   $id     Record (se l'esito non lo riporta).
	 * @param array<string, string|int>                                             $back   Destinazione per gli altri errori.
	 */
	private function after( array $result, array $input, string $view, array $done, int $id = 0, array $back = [ 'vista' => 'orari' ] ): void {
		$record = $result['id'] > 0 ? $result['id'] : $id;
		if ( $result['ok'] ) {
			$key = 'sale' === ( $done['vista'] ?? '' ) ? 'sala' : 'corso';
			$this->p->redirect(
				$done + [
					$key    => $record,
					'esito' => $result['code'],
				]
			);
		}
		if ( 'room_conflict' === $result['code'] ) {
			$result['code'] = 'invalid';
		}
		if ( isset( $result['errors']['name'] ) ) {
			$result['errors']['nome'] = $result['errors']['name'];
			unset( $result['errors']['name'] );
		}
		$this->p->keep_errors( $result, $input, $view, $back );
	}

	/* ------------------------------------------------------------------
	 * Corsi
	 * ------------------------------------------------------------------ */

	/**
	 * Scheda del corso: dati, modifica, azioni e storico.
	 *
	 * @param int $user Utente.
	 */
	private function view_course( int $user ): void {
		$course = $this->courses->get_course( $user, absint( $this->p->query( 'corso' ) ) );
		if ( null === $course ) {
			$this->p->not_found( __( 'Corso non trovato.', 'bracelli-club' ) );
			return;
		}
		if ( 'archived' === $course['status'] ) {
			echo '<p class="bcp-alert bcp-alert--info">' . esc_html__( 'Corso archiviato: non compare nel calendario né nelle lezioni. Orari e presenze sono conservati.', 'bracelli-club' ) . '</p>';
		}
		echo '<p class="bcp-row">';
		foreach ( $this->course_links( $course ) as [ $label, $args, $class ] ) {
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( Portal::url( $args ) ) . '">' . esc_html( $label ) . '</a> ';
		}
		echo '</p>';
		$this->view_course_form( $user, $course, 'update' );
		$this->history( $user, (int) $course['id'] );
	}

	/**
	 * Collegamenti alle azioni del corso.
	 *
	 * @param array<string, mixed> $course Corso.
	 * @return array<int, array{0: string, 1: array<string, string|int>, 2: string}>
	 */
	public static function course_links( array $course ): array {
		$id      = (int) $course['id'];
		$links   = [
			[
				__( 'Scheda', 'bracelli-club' ),
				[
					'vista' => 'corso',
					'corso' => $id,
				],
				'bcp-link',
			],
			[
				__( '+ Aggiungi orario', 'bracelli-club' ),
				[
					'vista' => 'nuovo-orario',
					'corso' => $id,
				],
				'bcp-link',
			],
			[
				__( 'Sala per tutto il corso', 'bracelli-club' ),
				[
					'vista' => 'sala-corso',
					'corso' => $id,
				],
				'bcp-link',
			],
			[
				__( 'Duplica', 'bracelli-club' ),
				[
					'vista' => 'duplica-corso',
					'corso' => $id,
				],
				'bcp-link',
			],
		];
		$links[] = [
			'archived' === $course['status'] ? __( 'Ripristina', 'bracelli-club' ) : __( 'Archivia', 'bracelli-club' ),
			[
				'vista' => 'archivia-corso',
				'corso' => $id,
			],
			'archived' === $course['status'] ? 'bcp-link' : 'bcp-link bcp-link--danger',
		];
		return $links;
	}

	/**
	 * Modulo dei dati del corso (nuovo, modifica o duplicazione).
	 *
	 * @param int                       $user   Utente.
	 * @param array<string, mixed>|null $course Corso esistente (o di partenza per la duplicazione).
	 * @param string                    $mode   create | update | duplicate.
	 */
	private function view_course_form( int $user, ?array $course, string $mode ): void {
		if ( ! $this->catalog->can_manage( $user ) ) {
			return;
		}
		$values = [] !== $this->p->old() ? $this->p->old() : ( null === $course ? [] : self::form_values( $course, [ 'section', 'group_label', 'level', 'description', 'notes', 'color' ] ) );
		if ( 'duplicate' === $mode && [] === $this->p->old() ) {
			$values['group_label'] = '';
		}
		$args = match ( $mode ) {
			'create'    => [ 'vista' => 'nuovo-corso' ],
			'update'    => [
				'vista' => 'corso',
				'corso' => (int) ( $course['id'] ?? 0 ),
			],
			default     => [
				'vista' => 'duplica-corso',
				'corso' => (int) ( $course['id'] ?? 0 ),
			],
		};
		$title = match ( $mode ) {
			'create' => __( 'Nuovo corso', 'bracelli-club' ),
			'update' => __( 'Dati del corso', 'bracelli-club' ),
			default  => __( 'Nuovo corso da duplicare', 'bracelli-club' ),
		};
		echo '<form class="bcp-card bcp-form" method="post" action="' . esc_url( Portal::url( $args ) ) . '" novalidate><h2>' . esc_html( $title ) . '</h2>';
		$this->p->action_fields( 'catalogo_' . $mode, (int) ( $course['id'] ?? 0 ) );
		$this->p->form_errors();
		$this->text_field( 'nome', __( 'Nome del corso', 'bracelli-club' ), $values, 190, true );
		echo '<label for="bcp-section">' . esc_html__( 'Disciplina', 'bracelli-club' ) . ' <span aria-hidden="true">*</span></label>';
		echo '<input id="bcp-section" type="text" name="section" maxlength="120" required list="bcp-sections" value="' . esc_attr( $values['section'] ?? '' ) . '"';
		$this->p->invalid_attr( 'section' );
		echo '><datalist id="bcp-sections">';
		foreach ( $this->courses->sections() as $section ) {
			echo '<option value="' . esc_attr( $section ) . '">';
		}
		echo '</datalist>';
		$this->p->field_error( 'section' );
		echo '<p class="bcp-muted">' . esc_html__( 'Scegli una disciplina esistente dall\'elenco oppure scrivine una nuova.', 'bracelli-club' ) . '</p>';
		$this->text_field( 'group_label', __( 'Gruppo (es. bambini 5-8 anni)', 'bracelli-club' ), $values, 190 );
		$this->text_field( 'level', __( 'Livello o fascia d\'età', 'bracelli-club' ), $values, 120 );
		echo '<label for="bcp-description">' . esc_html__( 'Descrizione per i soci', 'bracelli-club' ) . '</label><textarea id="bcp-description" name="description" rows="4" maxlength="2000">' . esc_textarea( $values['description'] ?? '' ) . '</textarea>';
		echo '<label for="bcp-notes">' . esc_html__( 'Note interne (solo segreteria)', 'bracelli-club' ) . '</label><textarea id="bcp-notes" name="notes" rows="3" maxlength="1000">' . esc_textarea( $values['notes'] ?? '' ) . '</textarea>';
		echo '<fieldset class="bcp-colors"><legend>' . esc_html__( 'Colore nel calendario', 'bracelli-club' ) . '</legend>';
		$current = (string) ( $values['color'] ?? '' );
		echo '<label class="bcp-color"><input type="radio" name="color" value=""' . checked( '', $current, false ) . '> <span>' . esc_html__( 'Nessuno', 'bracelli-club' ) . '</span></label>';
		foreach ( CatalogService::colors() as $hex => $name ) {
			echo '<label class="bcp-color"><input type="radio" name="color" value="' . esc_attr( $hex ) . '"' . checked( $hex, $current, false ) . '> <span class="bcp-swatch" style="background:' . esc_attr( $hex ) . '" aria-hidden="true"></span> <span>' . esc_html( $name ) . '</span></label>';
		}
		echo '</fieldset>';
		$this->p->field_error( 'color' );
		if ( 'duplicate' === $mode ) {
			echo '<label class="bcp-check"><input type="checkbox" name="copy_slots" value="1"' . checked( '1', (string) ( $values['copy_slots'] ?? '1' ), false ) . '> ' . esc_html__( 'Copia gli orari come "da confermare" (senza insegnante)', 'bracelli-club' ) . '</label>';
		}
		$button = 'update' === $mode ? __( 'Salva modifiche', 'bracelli-club' ) : __( 'Crea il corso', 'bracelli-club' );
		echo '<div class="bcp-row"><button class="bcp-button bcp-button--primary" type="submit">' . esc_html( $button ) . '</button>';
		echo '<a class="bcp-link" href="' . esc_url( Portal::url( [ 'vista' => 'orari' ] ) ) . '">' . esc_html__( 'Torna ai corsi', 'bracelli-club' ) . '</a></div></form>';
	}

	/**
	 * Duplicazione: modulo precompilato con i dati del corso di partenza.
	 *
	 * @param int $user Utente.
	 */
	private function view_duplicate( int $user ): void {
		$course = $this->courses->get_course( $user, absint( $this->p->query( 'corso' ) ) );
		if ( null === $course ) {
			$this->p->not_found( __( 'Corso non trovato.', 'bracelli-club' ) );
			return;
		}
		/* translators: %s: corso di partenza. */
		echo '<p class="bcp-muted">' . esc_html( sprintf( __( 'Parti da: %s. Cambia almeno il gruppo o il nome, così i due corsi si distinguono.', 'bracelli-club' ), self::label( $course ) ) ) . '</p>';
		$this->view_course_form( $user, $course, 'duplicate' );
	}

	/**
	 * Conferma di archiviazione o di ripristino.
	 *
	 * @param int $user Utente.
	 */
	private function view_archive( int $user ): void {
		$course = $this->courses->get_course( $user, absint( $this->p->query( 'corso' ) ) );
		if ( null === $course ) {
			$this->p->not_found( __( 'Corso non trovato.', 'bracelli-club' ) );
			return;
		}
		$archived = 'archived' === $course['status'];
		echo '<form class="bcp-card bcp-form" method="post" action="' . esc_url(
			Portal::url(
				[
					'vista' => 'archivia-corso',
					'corso' => $course['id'],
				]
			)
		) . '"><h2>' . esc_html( self::label( $course ) ) . '</h2><p>';
		echo esc_html(
			$archived
				? __( 'Il corso tornerà nel calendario con i suoi orari attivi. Se una sala nel frattempo è stata occupata da un altro corso, il ripristino non viene fatto e te lo segnalo.', 'bracelli-club' )
				: __( 'Il corso non comparirà più nel calendario dei soci né nelle lezioni degli insegnanti. Nulla viene eliminato: orari, presenze e storico restano e puoi ripristinarlo quando vuoi. Le sale che usava tornano libere.', 'bracelli-club' )
		);
		echo '</p>';
		$this->p->action_fields( $archived ? 'catalogo_restore' : 'catalogo_archive', (int) $course['id'] );
		echo '<div class="bcp-row"><button class="bcp-button ' . ( $archived ? 'bcp-button--primary' : 'bcp-button--danger' ) . '" type="submit">' . esc_html( $archived ? __( 'Ripristina il corso', 'bracelli-club' ) : __( 'Archivia il corso', 'bracelli-club' ) ) . '</button>';
		echo '<a class="bcp-link" href="' . esc_url(
			Portal::url(
				[
					'vista' => 'corso',
					'corso' => $course['id'],
				]
			)
		) . '">' . esc_html__( 'No, torna indietro', 'bracelli-club' ) . '</a></div></form>';
	}

	/**
	 * Sala per tutti gli orari del corso.
	 *
	 * @param int $user Utente.
	 */
	private function view_assign_room( int $user ): void {
		$course = $this->courses->get_course( $user, absint( $this->p->query( 'corso' ) ) );
		if ( null === $course ) {
			$this->p->not_found( __( 'Corso non trovato.', 'bracelli-club' ) );
			return;
		}
		$choices = $this->rooms->choices();
		echo '<form class="bcp-card bcp-form" method="post" action="' . esc_url(
			Portal::url(
				[
					'vista' => 'sala-corso',
					'corso' => $course['id'],
				]
			)
		) . '" novalidate><h2>' . esc_html( self::label( $course ) ) . '</h2>';
		if ( [] === $choices ) {
			echo '<p class="bcp-alert bcp-alert--info">' . esc_html__( 'Non ci sono ancora sale: aggiungile dalla scheda "Sale".', 'bracelli-club' ) . '</p>';
		}
		$this->p->action_fields( 'catalogo_assignroom', (int) $course['id'] );
		$this->p->form_errors();
		$old = $this->p->old();
		echo '<label for="bcp-room">' . esc_html__( 'Sala', 'bracelli-club' ) . '</label><select id="bcp-room" name="room_id"';
		$this->p->invalid_attr( 'room_id' );
		echo '><option value="0">' . esc_html__( 'Nessuna sala (togli la sala)', 'bracelli-club' ) . '</option>';
		foreach ( $choices as $id => $name ) {
			echo '<option value="' . esc_attr( (string) $id ) . '"' . selected( (string) ( $old['room_id'] ?? '' ), (string) $id, false ) . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select>';
		$this->p->field_error( 'room_id' );
		echo '<label class="bcp-check"><input type="checkbox" name="only_empty" value="1"' . checked( '1', (string) ( $old['only_empty'] ?? '1' ), false ) . '> ' . esc_html__( 'Solo gli orari che non hanno ancora una sala', 'bracelli-club' ) . '</label>';
		echo '<p class="bcp-muted">' . esc_html__( 'Vale per gli orari attivi e da confermare. Se anche un solo orario si sovrapporrebbe a un altro corso nella stessa sala, non viene cambiato nulla.', 'bracelli-club' ) . '</p>';
		echo '<div class="bcp-row"><button class="bcp-button bcp-button--primary" type="submit">' . esc_html__( 'Assegna', 'bracelli-club' ) . '</button>';
		echo '<a class="bcp-link" href="' . esc_url(
			Portal::url(
				[
					'vista' => 'corso',
					'corso' => $course['id'],
				]
			)
		) . '">' . esc_html__( 'Torna alla scheda', 'bracelli-club' ) . '</a></div></form>';
	}

	/**
	 * Storico del corso.
	 *
	 * @param int $user Utente.
	 * @param int $id   Corso.
	 */
	private function history( int $user, int $id ): void {
		$labels = [
			'corsi.course_created'       => __( 'Corso creato', 'bracelli-club' ),
			'corsi.course_updated'       => __( 'Dati del corso modificati', 'bracelli-club' ),
			'corsi.course_duplicated'    => __( 'Corso creato per duplicazione', 'bracelli-club' ),
			'corsi.course_archived'      => __( 'Corso archiviato', 'bracelli-club' ),
			'corsi.course_restored'      => __( 'Corso ripristinato', 'bracelli-club' ),
			'corsi.course_room_assigned' => __( 'Sala assegnata agli orari', 'bracelli-club' ),
		];
		$fields = [
			'name'        => __( 'nome', 'bracelli-club' ),
			'section'     => __( 'disciplina', 'bracelli-club' ),
			'group_label' => __( 'gruppo', 'bracelli-club' ),
			'level'       => __( 'livello', 'bracelli-club' ),
			'description' => __( 'descrizione', 'bracelli-club' ),
			'notes'       => __( 'note', 'bracelli-club' ),
			'color'       => __( 'colore', 'bracelli-club' ),
		];
		echo '<section class="bcp-card" aria-labelledby="bcp-h-chistory"><h2 id="bcp-h-chistory">' . esc_html__( 'Storico del corso', 'bracelli-club' ) . '</h2>';
		$items = $this->catalog->history( $user, $id );
		if ( [] === $items ) {
			echo '<p class="bcp-muted">' . esc_html__( 'Nessuna modifica alla scheda dopo l\'importazione.', 'bracelli-club' ) . '</p></section>';
			return;
		}
		echo '<ol class="bcp-history">';
		foreach ( $items as $item ) {
			$diff = is_array( $item['diff'] ) ? $item['diff'] : [];
			echo '<li><span class="bcp-history__when">' . esc_html( $this->p->format_utc( (string) $item['occurred_at'] ) ) . '</span> <strong>' . esc_html( $labels[ (string) $item['action'] ] ?? (string) $item['action'] ) . '</strong> — ' . esc_html( $this->p->user_name( (int) $item['actor_id'] ) );
			if ( isset( $diff['after'] ) && is_array( $diff['after'] ) ) {
				$names = array_map( static fn( $k ): string => $fields[ $k ] ?? (string) $k, array_keys( $diff['after'] ) );
				/* translators: %s: campi modificati. */
				echo '<br><span class="bcp-muted">' . esc_html( sprintf( __( 'Campi: %s', 'bracelli-club' ), implode( ', ', $names ) ) ) . '</span>';
			}
			echo '</li>';
		}
		echo '</ol></section>';
	}

	/* ------------------------------------------------------------------
	 * Sale
	 * ------------------------------------------------------------------ */

	/**
	 * Elenco delle sale e nuova sala.
	 *
	 * @param int $user Utente.
	 */
	private function view_rooms( int $user ): void {
		$rooms = (array) $this->rooms->rooms( $user );
		echo '<form class="bcp-card bcp-form" method="post" action="' . esc_url( Portal::url( [ 'vista' => 'sale' ] ) ) . '" novalidate><h2>' . esc_html__( 'Nuova sala', 'bracelli-club' ) . '</h2>';
		$this->p->action_fields( 'catalogo_roomcreate' );
		$this->p->form_errors();
		$old = $this->p->old();
		$this->text_field( 'nome', __( 'Nome della sala o del campo', 'bracelli-club' ), $old, 120, true );
		$this->text_field( 'notes', __( 'Note (facoltative)', 'bracelli-club' ), $old, 255 );
		echo '<div class="bcp-row"><button class="bcp-button bcp-button--primary" type="submit">' . esc_html__( 'Aggiungi sala', 'bracelli-club' ) . '</button></div></form>';

		if ( [] === $rooms ) {
			echo '<p class="bcp-card">' . esc_html__( 'Nessuna sala inserita. Gli orari mostrano ancora il vecchio testo "sede", se presente.', 'bracelli-club' ) . '</p>';
			return;
		}
		echo '<div class="bcp-table-wrap"><table class="bcp-table"><caption class="bcp-sr">' . esc_html__( 'Sale', 'bracelli-club' ) . '</caption><thead><tr>';
		foreach ( [ __( 'Sala', 'bracelli-club' ), __( 'Orari attivi', 'bracelli-club' ), __( 'Stato', 'bracelli-club' ), __( 'Azioni', 'bracelli-club' ) ] as $th ) {
			echo '<th scope="col">' . esc_html( $th ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rooms as $room ) {
			$archived = 'archived' === $room['status'];
			echo '<tr><td data-label="' . esc_attr__( 'Sala', 'bracelli-club' ) . '"><strong>' . esc_html( (string) $room['name'] ) . '</strong>';
			if ( '' !== (string) $room['notes'] ) {
				echo '<br><span class="bcp-muted">' . esc_html( (string) $room['notes'] ) . '</span>';
			}
			echo '</td><td data-label="' . esc_attr__( 'Orari attivi', 'bracelli-club' ) . '">' . esc_html( (string) $room['active_slots'] ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Stato', 'bracelli-club' ) . '">' . ( $archived ? '<span class="bcp-badge bcp-badge--cancelled">' . esc_html__( 'Archiviata', 'bracelli-club' ) . '</span>' : '<span class="bcp-badge">' . esc_html__( 'Attiva', 'bracelli-club' ) . '</span>' ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Azioni', 'bracelli-club' ) . '"><a class="bcp-link" href="' . esc_url(
				Portal::url(
					[
						'vista' => 'sala',
						'sala'  => $room['id'],
					]
				)
			) . '">' . esc_html__( 'Modifica', 'bracelli-club' ) . '</a> ';
			echo '<form class="bcp-inline" method="post" action="' . esc_url( Portal::url( [ 'vista' => 'sale' ] ) ) . '">';
			$this->p->action_fields( $archived ? 'catalogo_roomrestore' : 'catalogo_roomarchive', (int) $room['id'] );
			echo '<button class="bcp-link-button" type="submit">' . esc_html( $archived ? __( 'Ripristina', 'bracelli-club' ) : __( 'Archivia', 'bracelli-club' ) ) . '</button></form></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Modifica di una sala.
	 *
	 * @param int $user Utente.
	 */
	private function view_room( int $user ): void {
		$room = $this->rooms->get( $user, absint( $this->p->query( 'sala' ) ) );
		if ( null === $room ) {
			$this->p->not_found( __( 'Sala non trovata.', 'bracelli-club' ) );
			return;
		}
		$values = [] !== $this->p->old() ? $this->p->old() : self::form_values( $room, [ 'notes' ] );
		echo '<form class="bcp-card bcp-form" method="post" action="' . esc_url(
			Portal::url(
				[
					'vista' => 'sala',
					'sala'  => $room['id'],
				]
			)
		) . '" novalidate><h2>' . esc_html( (string) $room['name'] ) . '</h2>';
		$this->p->action_fields( 'catalogo_roomupdate', (int) $room['id'] );
		$this->p->form_errors();
		$this->text_field( 'nome', __( 'Nome della sala o del campo', 'bracelli-club' ), $values, 120, true );
		$this->text_field( 'notes', __( 'Note (facoltative)', 'bracelli-club' ), $values, 255 );
		echo '<div class="bcp-row"><button class="bcp-button bcp-button--primary" type="submit">' . esc_html__( 'Salva', 'bracelli-club' ) . '</button>';
		echo '<a class="bcp-link" href="' . esc_url( Portal::url( [ 'vista' => 'sale' ] ) ) . '">' . esc_html__( 'Torna alle sale', 'bracelli-club' ) . '</a></div></form>';
	}

	/* ------------------------------------------------------------------
	 * Parti comuni
	 * ------------------------------------------------------------------ */

	/**
	 * Campo di testo con etichetta ed errore.
	 *
	 * @param string                $name     Nome.
	 * @param string                $label    Etichetta.
	 * @param array<string, string> $values   Valori.
	 * @param int                   $max      Lunghezza massima.
	 * @param bool                  $required Obbligatorio.
	 */
	private function text_field( string $name, string $label, array $values, int $max, bool $required = false ): void {
		echo '<label for="bcp-' . esc_attr( $name ) . '">' . esc_html( $label ) . ( $required ? ' <span aria-hidden="true">*</span>' : '' ) . '</label>';
		echo '<input id="bcp-' . esc_attr( $name ) . '" type="text" name="' . esc_attr( $name ) . '" maxlength="' . esc_attr( (string) $max ) . '" value="' . esc_attr( $values[ $name ] ?? '' ) . '"' . ( $required ? ' required' : '' );
		$this->p->invalid_attr( $name );
		echo '>';
		$this->p->field_error( $name );
	}

	/**
	 * Dati inviati con "nome" al posto di "name" (vedi handle()).
	 *
	 * @param array<string, string> $input Dati inviati.
	 * @return array<string, string>
	 */
	private static function named( array $input ): array {
		$input['name'] = $input['nome'] ?? '';
		unset( $input['nome'] );
		return $input;
	}

	/**
	 * Valori di un record per il modulo ("name" diventa "nome").
	 *
	 * @param array<string, mixed> $row  Corso o sala.
	 * @param string[]             $keys Altri campi.
	 * @return array<string, string>
	 */
	private static function form_values( array $row, array $keys ): array {
		$out = [ 'nome' => (string) $row['name'] ];
		foreach ( $keys as $key ) {
			$out[ $key ] = (string) ( $row[ $key ] ?? '' );
		}
		return $out;
	}

	/**
	 * Nome del corso con il gruppo.
	 *
	 * @param array<string, mixed> $course Corso.
	 */
	public static function label( array $course ): string {
		$group = (string) ( $course['group_label'] ?? '' );
		return (string) $course['name'] . ( '' !== $group ? ' — ' . $group : '' );
	}
}
