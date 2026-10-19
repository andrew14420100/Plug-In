<?php
/**
 * Sezione "Corsi" dell'area riservata.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Corsi;

use Bracelli\Club\Modules\Area\Portal;
use Bracelli\Club\Modules\Area\SectionInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Calendario dei corsi (soci, insegnanti, segreteria), lezioni e presenza dell'insegnante,
 * quadro delle presenze e gestione degli orari (segreteria). I permessi li verifica CourseService.
 */
final class CorsiSection implements SectionInterface {

	/**
	 * Portale della richiesta corrente.
	 *
	 * @var Portal
	 */
	private Portal $p;

	/**
	 * @param CourseService       $service Corsi.
	 * @param CatalogService|null $catalog Catalogo (elenco filtrato dei corsi); predefinito: creato sugli stessi dati.
	 */
	public function __construct( private CourseService $service, private ?CatalogService $catalog = null ) {
		$this->catalog ??= new CatalogService( $service->repo(), $service->audit(), $service->rooms() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'corsi';
	}

	/**
	 * {@inheritDoc}
	 */
	public function priority(): int {
		return 10;
	}

	/**
	 * {@inheritDoc}
	 */
	public function views( int $user ): array {
		$views = [];
		if ( $this->service->can_manage( $user ) ) {
			$views += [
				'presenze'       => __( 'Presenze degli insegnanti', 'bracelli-club' ),
				'orari'          => __( 'Corsi e orari', 'bracelli-club' ),
				'orario'         => __( 'Modifica orario', 'bracelli-club' ),
				'nuovo-orario'   => __( 'Nuovo orario', 'bracelli-club' ),
				'annulla-orario' => __( 'Annulla orario', 'bracelli-club' ),
			];
		}
		if ( $this->service->can_teach( $user ) ) {
			$views['lezioni'] = __( 'Le mie lezioni', 'bracelli-club' );
		}
		if ( $this->service->can_view( $user ) ) {
			$views['corsi'] = __( 'Calendario dei corsi', 'bracelli-club' );
		}
		return $views;
	}

	/**
	 * {@inheritDoc}
	 */
	public function tabs( int $user ): array {
		$tabs = [];
		if ( $this->service->can_manage( $user ) ) {
			$tabs['presenze'] = __( 'Presenze insegnanti', 'bracelli-club' );
			$tabs['orari']    = __( 'Corsi e orari', 'bracelli-club' );
		}
		if ( $this->service->can_teach( $user ) ) {
			$tabs['lezioni'] = __( 'Le mie lezioni', 'bracelli-club' );
		}
		if ( $this->service->can_view( $user ) ) {
			$tabs['corsi'] = __( 'Calendario corsi', 'bracelli-club' );
		}
		return $tabs;
	}

	/**
	 * {@inheritDoc}
	 */
	public function tab_for( string $view ): string {
		return in_array( $view, [ 'orario', 'nuovo-orario', 'annulla-orario' ], true ) ? 'orari' : $view;
	}

	/**
	 * {@inheritDoc}
	 */
	public function messages(): array {
		return CatalogoSection::all_messages() + [
			'attendance_saved'  => [ 'success', __( 'Presenza salvata.', 'bracelli-club' ) ],
			'slot_updated'      => [ 'success', __( 'Orario aggiornato. La modifica è nello storico.', 'bracelli-club' ) ],
			'slot_created'      => [ 'success', __( 'Orario aggiunto.', 'bracelli-club' ) ],
			'slot_cancelled_ok' => [ 'success', __( 'Orario annullato. Resta visibile nello storico.', 'bracelli-club' ) ],
			'not_found'         => [ 'error', __( 'Lezione o orario non trovato.', 'bracelli-club' ) ],
			'slot_cancelled'    => [ 'error', __( 'L\'orario è annullato: non si può più modificare né registrare.', 'bracelli-club' ) ],
			'unchanged'         => [ 'info', __( 'Nessuna modifica.', 'bracelli-club' ) ],
			'not_a_lesson'      => [ 'error', __( 'In quella data non è prevista questa lezione.', 'bracelli-club' ) ],
			'too_early'         => [ 'error', __( 'Potrai registrare la presenza dal giorno della lezione.', 'bracelli-club' ) ],
			'too_old'           => [ 'error', __( 'La lezione è troppo vecchia per essere registrata: avvisa la segreteria.', 'bracelli-club' ) ],
			'before_assignment' => [ 'error', __( 'La lezione è precedente alla tua assegnazione a questo orario: avvisa la segreteria.', 'bracelli-club' ) ],
			'attendance_locked' => [ 'error', __( 'La presenza si può cambiare solo nel giorno della lezione.', 'bracelli-club' ) ],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function handle( Portal $portal, int $user, string $action, int $id ): void {
		$this->p = $portal;
		switch ( $action ) {
			case 'attendance':
				$input  = $portal->posted( [ 'data', 'presenza' ] );
				$result = $this->service->record_attendance( $user, $id, $input['data'], sanitize_key( $input['presenza'] ) );
				$portal->redirect(
					[
						'vista' => 'lezioni',
						'esito' => $result['code'],
					]
				);
				// Nessun break: redirect() termina la richiesta.
			case 'update':
				$input  = $portal->posted( [ 'weekday', 'start_time', 'end_time', 'teacher_id', 'room_id', 'location', 'apply_course' ] );
				$result = $this->service->update_slot( $user, $id, $input );
				$result = self::conflict_as_field( $result );
				if ( $result['ok'] ) {
					$portal->redirect(
						[
							'vista'  => 'orario',
							'orario' => $id,
							'esito'  => $result['code'],
						]
					);
				}
				$portal->keep_errors( $result, $input, 'orario', [ 'vista' => 'orari' ] );
				return;
			case 'add':
				$input  = $portal->posted( [ 'weekday', 'start_time', 'end_time', 'teacher_id', 'room_id', 'location' ] );
				$result = $this->service->add_slot( $user, $id, $input );
				$result = self::conflict_as_field( $result );
				if ( $result['ok'] ) {
					$portal->redirect(
						[
							'vista'  => 'orario',
							'orario' => $result['id'],
							'esito'  => $result['code'],
						]
					);
				}
				$portal->keep_errors( $result, $input, 'nuovo-orario', [ 'vista' => 'orari' ] );
				return;
			case 'cancel':
				$result = $this->service->cancel_slot( $user, $id );
				$portal->redirect(
					[
						'vista'  => $result['ok'] ? 'orario' : 'orari',
						'orario' => $id,
						'esito'  => $result['code'],
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
			'presenze'       => $this->view_overview( $user ),
			'orari'          => $this->view_courses( $user ),
			'orario'         => $this->view_slot( $user ),
			'nuovo-orario'   => $this->view_add_slot( $user ),
			'annulla-orario' => $this->view_cancel_slot( $user ),
			'lezioni'        => $this->view_lessons( $user ),
			default          => $this->view_calendar( $user ),
		};
	}

	/* ------------------------------------------------------------------
	 * Calendario (soci, insegnanti, segreteria)
	 * ------------------------------------------------------------------ */

	/**
	 * Calendario settimanale dei corsi attivi.
	 *
	 * @param int $user Utente.
	 */
	private function view_calendar( int $user ): void {
		$section  = $this->section_filter();
		$calendar = (array) $this->service->calendar( $user, $section );
		$this->section_form( 'corsi', $section );
		$by_day = [];
		foreach ( $calendar['slots'] ?? [] as $slot ) {
			$by_day[ $slot['weekday'] ][] = $slot;
		}
		if ( [] === $by_day && [] === ( $calendar['without_slots'] ?? [] ) ) {
			echo '<p class="bcp-card">' . esc_html__( 'Nessun corso disponibile.', 'bracelli-club' ) . '</p>';
			return;
		}
		echo '<div class="bcp-week">';
		foreach ( CourseService::weekdays() as $day => $name ) {
			if ( empty( $by_day[ $day ] ) ) {
				continue;
			}
			echo '<section class="bcp-card bcp-day" aria-labelledby="bcp-day-' . esc_attr( (string) $day ) . '"><h2 id="bcp-day-' . esc_attr( (string) $day ) . '">' . esc_html( $name ) . '</h2><ul class="bcp-lessons">';
			foreach ( $by_day[ $day ] as $slot ) {
				echo '<li' . ( '' !== (string) $slot['color'] ? ' class="has-color" style="--bcp-course-color:' . esc_attr( (string) $slot['color'] ) . '"' : '' ) . '><span class="bcp-lesson__time">' . esc_html( $this->slot_time( $slot ) ) . '</span> <span class="bcp-lesson__name">' . esc_html( $this->course_label( $slot ) ) . '</span>';
				echo '<span class="bcp-muted bcp-lesson__section">' . esc_html( (string) $slot['section'] ) . '</span>';
				$where = $slot['room_id'] > 0 ? (string) $slot['room_name'] : (string) $slot['location'];
				if ( '' !== $where ) {
					echo '<span class="bcp-muted bcp-lesson__room">' . esc_html( $where ) . '</span>';
				}
				echo '</li>';
			}
			echo '</ul></section>';
		}
		echo '</div>';
		if ( ! empty( $calendar['without_slots'] ) ) {
			echo '<section class="bcp-card"><h2>' . esc_html__( 'Altri corsi: giorni e orari da chiedere in segreteria', 'bracelli-club' ) . '</h2><ul class="bcp-plain">';
			foreach ( $calendar['without_slots'] as $course ) {
				echo '<li>' . esc_html( $this->course_label( $course, 'name' ) ) . ' <span class="bcp-muted">' . esc_html( (string) $course['section'] ) . '</span></li>';
			}
			echo '</ul></section>';
		}
	}

	/* ------------------------------------------------------------------
	 * Insegnante: le mie lezioni
	 * ------------------------------------------------------------------ */

	/**
	 * Lezioni dell'insegnante con la registrazione Presente/Assente.
	 *
	 * @param int $user Utente.
	 */
	private function view_lessons( int $user ): void {
		$today    = $this->service->today();
		$upcoming = (array) $this->service->lessons_for_teacher( $user, $today, gmdate( 'Y-m-d', (int) strtotime( $today . ' +6 days' ) ) );
		$past     = array_reverse( (array) $this->service->lessons_for_teacher( $user, gmdate( 'Y-m-d', (int) strtotime( $today . ' -7 days' ) ), gmdate( 'Y-m-d', (int) strtotime( $today . ' -1 day' ) ) ) );

		echo '<section aria-labelledby="bcp-h-next"><h2 id="bcp-h-next">' . esc_html__( 'Oggi e prossimi 7 giorni', 'bracelli-club' ) . '</h2>';
		if ( [] === $upcoming ) {
			echo '<p class="bcp-card">' . esc_html__( 'Nessuna lezione assegnata. Se pensi sia un errore, avvisa la segreteria.', 'bracelli-club' ) . '</p>';
		}
		foreach ( $upcoming as $lesson ) {
			$this->lesson_card( $lesson, $today );
		}
		echo '</section><section aria-labelledby="bcp-h-past"><h2 id="bcp-h-past">' . esc_html__( 'Ultimi 7 giorni', 'bracelli-club' ) . '</h2>';
		if ( [] === $past ) {
			echo '<p class="bcp-card">' . esc_html__( 'Nessuna lezione.', 'bracelli-club' ) . '</p>';
		}
		foreach ( $past as $lesson ) {
			$this->lesson_card( $lesson, $today );
		}
		echo '</section>';
	}

	/**
	 * Scheda di una lezione per l'insegnante.
	 *
	 * @param array<string, mixed> $lesson Lezione.
	 * @param string               $today  Oggi.
	 */
	private function lesson_card( array $lesson, string $today ): void {
		$reason   = $lesson['attendance_teacher_id'] > 0 && $lesson['attendance_teacher_id'] !== $lesson['teacher_id']
			? 'attendance_locked'
			: $this->service->attendance_block_reason( $lesson, (string) $lesson['lesson_date'], null !== $lesson['attendance_status'] );
		$is_today = (string) $lesson['lesson_date'] === $today;
		echo '<article class="bcp-card bcp-shift' . ( $is_today ? ' is-today' : '' ) . '">';
		echo '<div class="bcp-shift__head"><p class="bcp-shift__date">' . ( $is_today ? '<span class="bcp-badge bcp-badge--today">' . esc_html__( 'Oggi', 'bracelli-club' ) . '</span> ' : '' ) . esc_html( $this->p->format_date( (string) $lesson['lesson_date'] ) ) . '</p>';
		echo '<p class="bcp-shift__time">' . esc_html( $this->slot_time( $lesson ) ) . '</p></div>';
		echo '<p class="bcp-shift__course">' . esc_html( $this->course_label( $lesson ) ) . '</p>';
		echo '<p class="bcp-shift__state">' . $this->attendance_badge( $lesson ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup con escape.
		if ( '' === $reason ) {
			echo '<form class="bcp-attendance" method="post" action="' . esc_url( Portal::url( [ 'vista' => 'lezioni' ] ) ) . '">';
			$this->p->action_fields( 'corsi_attendance', (int) $lesson['id'] );
			echo '<input type="hidden" name="data" value="' . esc_attr( (string) $lesson['lesson_date'] ) . '">';
			foreach ( [
				'present' => __( 'Presente', 'bracelli-club' ),
				'absent'  => __( 'Assente', 'bracelli-club' ),
			] as $value => $label ) {
				$current = $value === $lesson['attendance_status'];
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
			if ( null !== $lesson['attendance_status'] ) {
				echo '<p class="bcp-muted">' . esc_html__( 'Puoi cambiare la scelta fino alla fine della giornata.', 'bracelli-club' ) . '</p>';
			}
		} elseif ( 'too_early' === $reason ) {
			echo '<p class="bcp-muted">' . esc_html__( 'Potrai registrare la presenza dal giorno della lezione.', 'bracelli-club' ) . '</p>';
		}
		echo '</article>';
	}

	/* ------------------------------------------------------------------
	 * Segreteria: presenze e orari
	 * ------------------------------------------------------------------ */

	/**
	 * Quadro delle presenze degli insegnanti.
	 *
	 * @param int $user Utente.
	 */
	private function view_overview( int $user ): void {
		$today      = $this->service->today();
		$from       = $this->p->query_date( 'dal', $today );
		$to         = $this->p->query_date( 'al', $from );
		$to         = $to < $from ? $from : $to;
		$max        = gmdate( 'Y-m-d', (int) strtotime( $from . ' +30 days' ) );
		$to         = $to > $max ? $max : $to;
		$teachers   = $this->service->teachers( $user );
		$section    = $this->section_filter();
		$teacher    = (int) $this->p->query( 'insegnante' );
		$teacher    = -1 === $teacher || isset( $teachers[ $teacher ] ) ? $teacher : 0;
		$attendance = in_array( $this->p->query( 'presenza' ), [ 'present', 'absent', 'none' ], true ) ? $this->p->query( 'presenza' ) : '';
		$lessons    = (array) $this->service->overview(
			$user,
			$from,
			$to,
			[
				'section'    => $section,
				'teacher'    => $teacher,
				'attendance' => $attendance,
			]
		);
		$counts     = [
			'total'      => count( $lessons ),
			'present'    => 0,
			'absent'     => 0,
			'none'       => 0,
			'unassigned' => 0,
		];
		foreach ( $lessons as $lesson ) {
			if ( 0 === $lesson['teacher_id'] ) {
				++$counts['unassigned'];
			}
			++$counts[ null === $lesson['attendance_status'] ? 'none' : ( 'present' === $lesson['attendance_status'] ? 'present' : 'absent' ) ];
		}
		?>
<form class="bcp-card bcp-filters" method="get" action="<?php echo esc_url( Portal::url() ); ?>">
		<?php $this->p->get_form_fields(); ?>
	<input type="hidden" name="vista" value="presenze">
	<div class="bcp-field"><label for="bcp-dal"><?php esc_html_e( 'Dal', 'bracelli-club' ); ?></label><input id="bcp-dal" type="date" name="dal" value="<?php echo esc_attr( $from ); ?>"></div>
	<div class="bcp-field"><label for="bcp-al"><?php esc_html_e( 'Al', 'bracelli-club' ); ?></label><input id="bcp-al" type="date" name="al" value="<?php echo esc_attr( $to ); ?>"></div>
		<?php $this->section_select( $section ); ?>
	<div class="bcp-field"><label for="bcp-ins"><?php esc_html_e( 'Insegnante', 'bracelli-club' ); ?></label>
		<select id="bcp-ins" name="insegnante">
			<option value=""><?php esc_html_e( 'Tutti', 'bracelli-club' ); ?></option>
			<option value="-1"<?php selected( $teacher, -1 ); ?>><?php esc_html_e( 'Non assegnato', 'bracelli-club' ); ?></option>
		<?php foreach ( $teachers as $id => $name ) : ?>
			<option value="<?php echo esc_attr( (string) $id ); ?>"<?php selected( $teacher, $id ); ?>><?php echo esc_html( $name ); ?></option>
		<?php endforeach; ?>
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
	<li><strong><?php echo esc_html( (string) $counts['total'] ); ?></strong> <?php esc_html_e( 'lezioni', 'bracelli-club' ); ?></li>
	<li class="is-present"><strong><?php echo esc_html( (string) $counts['present'] ); ?></strong> <?php esc_html_e( 'presenti', 'bracelli-club' ); ?></li>
	<li class="is-absent"><strong><?php echo esc_html( (string) $counts['absent'] ); ?></strong> <?php esc_html_e( 'assenti', 'bracelli-club' ); ?></li>
	<li><strong><?php echo esc_html( (string) $counts['none'] ); ?></strong> <?php esc_html_e( 'da registrare', 'bracelli-club' ); ?></li>
	<li class="is-cancelled"><strong><?php echo esc_html( (string) $counts['unassigned'] ); ?></strong> <?php esc_html_e( 'senza insegnante', 'bracelli-club' ); ?></li>
</ul>
		<?php
		if ( [] === $lessons ) {
			echo '<p class="bcp-card">' . esc_html__( 'Nessuna lezione nel periodo e con i filtri scelti.', 'bracelli-club' ) . '</p>';
			return;
		}
		echo '<div class="bcp-table-wrap"><table class="bcp-table"><caption class="bcp-sr">' . esc_html__( 'Lezioni del periodo', 'bracelli-club' ) . '</caption><thead><tr>';
		foreach ( [ __( 'Data', 'bracelli-club' ), __( 'Orario', 'bracelli-club' ), __( 'Corso', 'bracelli-club' ), __( 'Insegnante', 'bracelli-club' ), __( 'Presenza', 'bracelli-club' ) ] as $th ) {
			echo '<th scope="col">' . esc_html( $th ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $lessons as $lesson ) {
			echo '<tr>';
			echo '<td data-label="' . esc_attr__( 'Data', 'bracelli-club' ) . '">' . esc_html( $this->p->format_date( (string) $lesson['lesson_date'] ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Orario', 'bracelli-club' ) . '">' . esc_html( $this->slot_time( $lesson ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Corso', 'bracelli-club' ) . '">' . esc_html( $this->course_label( $lesson ) ) . '</td>';
			// Lezione già registrata: conta chi l'ha registrata, anche se l'orario è passato a un altro insegnante.
			$who = $lesson['attendance_teacher_id'] > 0 ? [ 'teacher_id' => $lesson['attendance_teacher_id'] ] + $lesson : $lesson;
			echo '<td data-label="' . esc_attr__( 'Insegnante', 'bracelli-club' ) . '">' . $this->teacher_cell( $who ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup con escape.
			echo '<td data-label="' . esc_attr__( 'Presenza', 'bracelli-club' ) . '">' . $this->attendance_badge( $lesson ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup con escape.
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Corsi e orari (segreteria): ricerca, filtri, azioni sul corso, sale e orari da confermare.
	 *
	 * @param int $user Utente.
	 */
	private function view_courses( int $user ): void {
		$section = $this->section_filter();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- filtri di sola lettura.
		$search = isset( $_GET['q'] ) && is_string( $_GET['q'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_GET['q'] ) ), 0, 80 ) : '';
		$status = isset( $_GET['stato'] ) && in_array( $_GET['stato'], [ 'active', 'archived', 'all' ], true ) ? sanitize_key( wp_unslash( $_GET['stato'] ) ) : 'active';
		// phpcs:enable
		echo '<p class="bcp-row"><a class="bcp-button bcp-button--primary" href="' . esc_url( Portal::url( [ 'vista' => 'nuovo-corso' ] ) ) . '">' . esc_html__( '+ Nuovo corso', 'bracelli-club' ) . '</a></p>';
		echo '<form class="bcp-card bcp-filters" method="get" action="' . esc_url( Portal::url() ) . '" role="search">';
		$this->p->get_form_fields();
		echo '<input type="hidden" name="vista" value="orari">';
		echo '<div class="bcp-field"><label for="bcp-q">' . esc_html__( 'Cerca', 'bracelli-club' ) . '</label><input id="bcp-q" type="search" name="q" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Nome, gruppo, livello…', 'bracelli-club' ) . '"></div>';
		$this->section_select( $section );
		echo '<div class="bcp-field"><label for="bcp-stato">' . esc_html__( 'Stato', 'bracelli-club' ) . '</label><select id="bcp-stato" name="stato">';
		foreach ( [
			'active'   => __( 'Attivi', 'bracelli-club' ),
			'archived' => __( 'Archiviati', 'bracelli-club' ),
			'all'      => __( 'Tutti', 'bracelli-club' ),
		] as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></div><button class="bcp-button" type="submit">' . esc_html__( 'Filtra', 'bracelli-club' ) . '</button></form>';

		$courses = (array) $this->catalog->list( $user, $section, $status, $search );
		if ( [] === $courses ) {
			echo '<p class="bcp-card">' . esc_html__( 'Nessun corso con questi filtri.', 'bracelli-club' ) . '</p>';
			return;
		}
		/* translators: %d: numero di corsi. */
		echo '<p class="bcp-muted">' . esc_html( sprintf( _n( '%d corso', '%d corsi', count( $courses ), 'bracelli-club' ), count( $courses ) ) ) . '</p>';
		$days = CourseService::weekdays();
		foreach ( $courses as $course ) {
			$archived = 'archived' === $course['status'];
			$style    = '' !== $course['color'] ? ' style="--bcp-course-color:' . esc_attr( $course['color'] ) . '"' : '';
			echo '<article class="bcp-card bcp-course' . ( '' !== $course['color'] ? ' has-color' : '' ) . ( $archived ? ' is-archived' : '' ) . '"' . $style . '><div class="bcp-course__head"><h2>' . esc_html( $this->course_label( $course, 'name' ) ) . '</h2><span class="bcp-muted">' . esc_html( (string) $course['section'] . ( '' !== $course['level'] ? ' · ' . $course['level'] : '' ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $style costruito con esc_attr.
			if ( $archived ) {
				echo ' <span class="bcp-badge bcp-badge--cancelled">' . esc_html__( 'Archiviato', 'bracelli-club' ) . '</span>';
			}
			echo '</div>';
			if ( '' !== (string) $course['notes'] ) {
				echo '<p class="bcp-muted">' . esc_html( (string) $course['notes'] ) . '</p>';
			}
			if ( [] === $course['slots'] ) {
				echo '<p class="bcp-alert bcp-alert--info">' . esc_html__( 'Nessun orario: giorni e orari non sono ancora indicati.', 'bracelli-club' ) . '</p>';
			} else {
				echo '<ul class="bcp-slots">';
				foreach ( $course['slots'] as $slot ) {
					$cancelled = 'cancelled' === $slot['status'];
					$draft     = 'draft' === $slot['status'];
					echo '<li class="' . ( $cancelled ? 'is-cancelled' : '' ) . ( $draft ? 'is-draft' : '' ) . '"><span class="bcp-slot__when">' . esc_html( $days[ $slot['weekday'] ] . ' ' . $this->slot_time( $slot ) ) . '</span>';
					echo '<span class="bcp-slot__teacher">' . $this->teacher_cell( $slot ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup con escape.
					echo '<span class="bcp-slot__room">' . $this->room_cell( $slot ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup con escape.
					if ( $cancelled ) {
						echo '<span class="bcp-badge bcp-badge--cancelled">' . esc_html__( 'Annullato', 'bracelli-club' ) . '</span>';
					}
					if ( $draft ) {
						echo '<span class="bcp-badge bcp-badge--draft">' . esc_html__( 'Da confermare', 'bracelli-club' ) . '</span>';
						echo '<form class="bcp-inline" method="post" action="' . esc_url( Portal::url( [ 'vista' => 'orari' ] ) ) . '">';
						$this->p->action_fields( 'catalogo_confirm', (int) $slot['id'] );
						echo '<button class="bcp-link-button" type="submit">' . esc_html__( 'Conferma', 'bracelli-club' ) . '</button></form>';
					}
					echo ' <a class="bcp-link" href="' . esc_url(
						Portal::url(
							[
								'vista'  => 'orario',
								'orario' => $slot['id'],
							]
						)
					) . '">' . esc_html( $cancelled ? __( 'Dettagli', 'bracelli-club' ) : __( 'Modifica', 'bracelli-club' ) ) . '</a></li>';
				}
				echo '</ul>';
			}
			echo '<p class="bcp-course__actions">';
			foreach ( CatalogoSection::course_links( $course ) as [ $label, $args, $class ] ) {
				echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( Portal::url( $args ) ) . '">' . esc_html( $label ) . '</a> ';
			}
			echo '</p></article>';
		}
	}

	/**
	 * Modifica di un orario con storico.
	 *
	 * @param int $user Utente.
	 */
	private function view_slot( int $user ): void {
		$slot = $this->service->get_slot( $user, absint( $this->p->query( 'orario' ) ) );
		if ( null === $slot ) {
			$this->p->not_found( __( 'Orario non trovato.', 'bracelli-club' ) );
			return;
		}
		$this->slot_summary( $slot );
		if ( 'draft' === $slot['status'] ) {
			echo '<p class="bcp-alert bcp-alert--info">' . esc_html__( 'Orario da confermare: non compare nel calendario e non occupa la sala finché non lo confermi dall\'elenco dei corsi.', 'bracelli-club' ) . '</p>';
		}
		if ( in_array( $slot['status'], [ 'active', 'draft' ], true ) ) {
			$values = [] !== $this->p->old() ? $this->p->old() : [
				'weekday'    => (string) $slot['weekday'],
				'start_time' => substr( (string) $slot['start_time'], 0, 5 ),
				'end_time'   => substr( (string) $slot['end_time'], 0, 5 ),
				'teacher_id' => (string) $slot['teacher_id'],
				'room_id'    => (string) $slot['room_id'],
				'location'   => (string) $slot['location'],
			];
			echo '<form class="bcp-card bcp-form" method="post" action="' . esc_url(
				Portal::url(
					[
						'vista'  => 'orario',
						'orario' => $slot['id'],
					]
				)
			) . '" novalidate><h2>' . esc_html__( 'Modifica orario e insegnante', 'bracelli-club' ) . '</h2>';
			$this->p->action_fields( 'corsi_update', (int) $slot['id'] );
			$this->p->form_errors();
			$this->slot_fields( $user, $values );
			echo '<label class="bcp-check"><input type="checkbox" name="apply_course" value="1"> ' . esc_html__( 'Assegna questo insegnante anche agli altri orari attivi del corso', 'bracelli-club' ) . '</label>';
			echo '<div class="bcp-row"><button class="bcp-button bcp-button--primary" type="submit">' . esc_html__( 'Salva modifiche', 'bracelli-club' ) . '</button>';
			echo '<a class="bcp-link bcp-link--danger" href="' . esc_url(
				Portal::url(
					[
						'vista'  => 'annulla-orario',
						'orario' => $slot['id'],
					]
				)
			) . '">' . esc_html__( 'Annulla questo orario', 'bracelli-club' ) . '</a></div></form>';
		} else {
			echo '<p class="bcp-alert bcp-alert--info">' . esc_html__( 'Orario annullato: non è più modificabile.', 'bracelli-club' ) . '</p>';
		}
		$this->history( $user, (int) $slot['id'] );
		echo '<p><a class="bcp-link" href="' . esc_url( Portal::url( [ 'vista' => 'orari' ] ) ) . '">' . esc_html__( '← Torna agli orari', 'bracelli-club' ) . '</a></p>';
	}

	/**
	 * Nuovo orario per un corso.
	 *
	 * @param int $user Utente.
	 */
	private function view_add_slot( int $user ): void {
		$course = $this->service->get_course( $user, absint( $this->p->query( 'corso' ) ) );
		if ( null === $course ) {
			$this->p->not_found( __( 'Corso non trovato.', 'bracelli-club' ) );
			return;
		}
		echo '<form class="bcp-card bcp-form" method="post" action="' . esc_url(
			Portal::url(
				[
					'vista' => 'nuovo-orario',
					'corso' => $course['id'],
				]
			)
		) . '" novalidate><h2>' . esc_html( $this->course_label( $course, 'name' ) ) . '</h2>';
		$this->p->action_fields( 'corsi_add', (int) $course['id'] );
		$this->p->form_errors();
		$this->slot_fields( $user, $this->p->old() );
		echo '<div class="bcp-row"><button class="bcp-button bcp-button--primary" type="submit">' . esc_html__( 'Aggiungi orario', 'bracelli-club' ) . '</button>';
		echo '<a class="bcp-link" href="' . esc_url( Portal::url( [ 'vista' => 'orari' ] ) ) . '">' . esc_html__( 'Torna agli orari', 'bracelli-club' ) . '</a></div></form>';
	}

	/**
	 * Conferma di annullamento di un orario.
	 *
	 * @param int $user Utente.
	 */
	private function view_cancel_slot( int $user ): void {
		$slot = $this->service->get_slot( $user, absint( $this->p->query( 'orario' ) ) );
		if ( null === $slot ) {
			$this->p->not_found( __( 'Orario non trovato.', 'bracelli-club' ) );
			return;
		}
		$this->slot_summary( $slot );
		if ( ! in_array( $slot['status'], [ 'active', 'draft' ], true ) ) {
			echo '<p class="bcp-alert bcp-alert--info">' . esc_html__( 'Questo orario è già annullato.', 'bracelli-club' ) . '</p>';
			return;
		}
		echo '<form class="bcp-card bcp-form" method="post" action="' . esc_url(
			Portal::url(
				[
					'vista'  => 'annulla-orario',
					'orario' => $slot['id'],
				]
			)
		) . '"><p>' . esc_html__( 'L\'orario verrà segnato come annullato: non sarà eliminato, le presenze già registrate restano e tutto rimane nello storico. Non comparirà più nel calendario e nelle lezioni dell\'insegnante.', 'bracelli-club' ) . '</p>';
		$this->p->action_fields( 'corsi_cancel', (int) $slot['id'] );
		echo '<div class="bcp-row"><button class="bcp-button bcp-button--danger" type="submit">' . esc_html__( 'Conferma annullamento', 'bracelli-club' ) . '</button>';
		echo '<a class="bcp-link" href="' . esc_url(
			Portal::url(
				[
					'vista'  => 'orario',
					'orario' => $slot['id'],
				]
			)
		) . '">' . esc_html__( 'No, torna indietro', 'bracelli-club' ) . '</a></div></form>';
	}

	/* ------------------------------------------------------------------
	 * Parti comuni
	 * ------------------------------------------------------------------ */

	/**
	 * Campi giorno, orari, insegnante e sede.
	 *
	 * @param int                   $user   Utente.
	 * @param array<string, string> $values Valori.
	 */
	private function slot_fields( int $user, array $values ): void {
		$teachers = $this->service->teachers( $user );
		echo '<label for="bcp-weekday">' . esc_html__( 'Giorno', 'bracelli-club' ) . '</label><select id="bcp-weekday" name="weekday"';
		$this->p->invalid_attr( 'weekday' );
		echo '><option value="">' . esc_html__( '— Scegli —', 'bracelli-club' ) . '</option>';
		foreach ( CourseService::weekdays() as $day => $name ) {
			echo '<option value="' . esc_attr( (string) $day ) . '"' . selected( (string) ( $values['weekday'] ?? '' ), (string) $day, false ) . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select>';
		$this->p->field_error( 'weekday' );
		echo '<div class="bcp-row bcp-row--fields">';
		foreach ( [
			'start_time' => __( 'Inizio', 'bracelli-club' ),
			'end_time'   => __( 'Fine', 'bracelli-club' ),
		] as $field => $label ) {
			echo '<div class="bcp-field"><label for="bcp-' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label><input id="bcp-' . esc_attr( $field ) . '" type="time" step="60" name="' . esc_attr( $field ) . '" value="' . esc_attr( $values[ $field ] ?? '' ) . '"';
			$this->p->invalid_attr( $field );
			echo '>';
			$this->p->field_error( $field );
			echo '</div>';
		}
		echo '</div>';
		echo '<label for="bcp-teacher">' . esc_html__( 'Insegnante (account)', 'bracelli-club' ) . '</label><select id="bcp-teacher" name="teacher_id"';
		$this->p->invalid_attr( 'teacher_id' );
		echo '><option value="0">' . esc_html__( 'Nessuno (da assegnare)', 'bracelli-club' ) . '</option>';
		foreach ( $teachers as $id => $name ) {
			echo '<option value="' . esc_attr( (string) $id ) . '"' . selected( (string) ( $values['teacher_id'] ?? '' ), (string) $id, false ) . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select>';
		$this->p->field_error( 'teacher_id' );
		if ( [] === $teachers ) {
			echo '<p class="bcp-muted">' . esc_html__( 'Non ci sono ancora account insegnante: un amministratore deve crearli con il ruolo "Istruttore Bracelli".', 'bracelli-club' ) . '</p>';
		}
		$rooms = $this->service->rooms()->choices();
		$room  = (string) ( $values['room_id'] ?? '0' );
		if ( '0' !== $room && '' !== $room && ! isset( $rooms[ (int) $room ] ) ) {
			// Sala archiviata ancora assegnata a questo orario: resta selezionabile.
			$current = $this->service->repo()->find_room( (int) $room );
			if ( null !== $current ) {
				$rooms[ (int) $room ] = (string) $current['name'];
			}
		}
		echo '<label for="bcp-room">' . esc_html__( 'Sala', 'bracelli-club' ) . '</label><select id="bcp-room" name="room_id"';
		$this->p->invalid_attr( 'room_id' );
		echo '><option value="0">' . esc_html__( 'Nessuna sala', 'bracelli-club' ) . '</option>';
		foreach ( $rooms as $id => $name ) {
			echo '<option value="' . esc_attr( (string) $id ) . '"' . selected( $room, (string) $id, false ) . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select>';
		$this->p->field_error( 'room_id' );
		if ( [] === $rooms ) {
			echo '<p class="bcp-muted">' . esc_html__( 'Non ci sono ancora sale: aggiungile dalla scheda "Sale".', 'bracelli-club' ) . '</p>';
		}
		// Il vecchio testo "sede" resta visibile e modificabile finché l'orario non ha una sala; poi resta conservato.
		if ( '0' === $room || '' === $room ) {
			echo '<label for="bcp-location">' . esc_html__( 'Sede (vecchio testo, finché non scegli una sala)', 'bracelli-club' ) . '</label><input id="bcp-location" type="text" maxlength="120" name="location" value="' . esc_attr( $values['location'] ?? '' ) . '">';
		} else {
			echo '<input type="hidden" name="location" value="' . esc_attr( $values['location'] ?? '' ) . '">';
		}
	}

	/**
	 * Riepilogo di un orario.
	 *
	 * @param array<string, mixed> $slot Orario.
	 */
	private function slot_summary( array $slot ): void {
		echo '<dl class="bcp-card bcp-summary">';
		echo '<div><dt>' . esc_html__( 'Corso', 'bracelli-club' ) . '</dt><dd>' . esc_html( $this->course_label( $slot ) ) . '</dd></div>';
		echo '<div><dt>' . esc_html__( 'Giorno e orario', 'bracelli-club' ) . '</dt><dd>' . esc_html( CourseService::weekdays()[ $slot['weekday'] ] . ' ' . $this->slot_time( $slot ) ) . '</dd></div>';
		echo '<div><dt>' . esc_html__( 'Insegnante', 'bracelli-club' ) . '</dt><dd>' . $this->teacher_cell( $slot ) . '</dd></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup con escape.
		echo '<div><dt>' . esc_html__( 'Sala', 'bracelli-club' ) . '</dt><dd>' . $this->room_cell( $slot ) . '</dd></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup con escape.
		echo '<div><dt>' . esc_html__( 'Origine', 'bracelli-club' ) . '</dt><dd>' . esc_html( null === $slot['ext_key'] ? __( 'Aggiunto dallo staff', 'bracelli-club' ) : ( 1 === $slot['manual'] ? __( 'Importato, poi modificato dallo staff', 'bracelli-club' ) : __( 'Importato', 'bracelli-club' ) ) ) . '</dd></div>';
		echo '</dl>';
	}

	/**
	 * Storico dell'orario dal registro attività.
	 *
	 * @param int $user Utente.
	 * @param int $id   Orario.
	 */
	private function history( int $user, int $id ): void {
		$labels = [
			'corsi.slot_created'        => __( 'Orario aggiunto', 'bracelli-club' ),
			'corsi.slot_updated'        => __( 'Orario o insegnante modificati', 'bracelli-club' ),
			'corsi.slot_cancelled'      => __( 'Orario annullato', 'bracelli-club' ),
			'corsi.slot_confirmed'      => __( 'Orario confermato', 'bracelli-club' ),
			'corsi.attendance_recorded' => __( 'Presenza registrata', 'bracelli-club' ),
			'corsi.attendance_changed'  => __( 'Presenza cambiata', 'bracelli-club' ),
		];
		echo '<section class="bcp-card" aria-labelledby="bcp-h-history"><h2 id="bcp-h-history">' . esc_html__( 'Storico', 'bracelli-club' ) . '</h2>';
		$items = $this->service->history( $user, $id );
		if ( [] === $items ) {
			echo '<p class="bcp-muted">' . esc_html__( 'Nessuna modifica registrata dopo l\'importazione.', 'bracelli-club' ) . '</p></section>';
			return;
		}
		echo '<ol class="bcp-history">';
		foreach ( $items as $item ) {
			$label = $labels[ (string) $item['action'] ] ?? (string) $item['action'];
			$diff  = is_array( $item['diff'] ) ? $item['diff'] : [];
			echo '<li><span class="bcp-history__when">' . esc_html( $this->p->format_utc( (string) $item['occurred_at'] ) ) . '</span> <strong>' . esc_html( $label ) . '</strong> — ' . esc_html( $this->p->user_name( (int) $item['actor_id'] ) );
			$detail = $this->history_detail( (string) $item['action'], $diff );
			if ( '' !== $detail ) {
				echo '<br><span class="bcp-muted">' . esc_html( $detail ) . '</span>';
			}
			echo '</li>';
		}
		echo '</ol></section>';
	}

	/**
	 * @param string               $action Azione.
	 * @param array<string, mixed> $diff   Dettagli.
	 */
	private function history_detail( string $action, array $diff ): string {
		$days = CourseService::weekdays();
		$room = function ( $d ): string {
			if ( ! is_array( $d ) || ! array_key_exists( 'room_id', $d ) ) {
				return '';
			}
			$found = (int) $d['room_id'] > 0 ? $this->service->repo()->find_room( (int) $d['room_id'] ) : null;
			return ', ' . ( null === $found ? __( 'nessuna sala', 'bracelli-club' ) : (string) $found['name'] );
		};
		$slot = function ( $d ) use ( $days, $room ): string {
			if ( ! is_array( $d ) || ! isset( $d['weekday'], $d['start_time'], $d['end_time'] ) ) {
				return '';
			}
			$teacher = (int) ( $d['teacher_id'] ?? 0 );
			return ( $days[ (int) $d['weekday'] ] ?? '' ) . ' ' . $this->slot_time( $d ) . ', ' . ( $teacher > 0 ? $this->p->user_name( $teacher ) : __( 'nessun insegnante', 'bracelli-club' ) ) . $room( $d );
		};

		$att = static fn( $v ): string => 'present' === $v ? __( 'Presente', 'bracelli-club' ) : ( 'absent' === $v ? __( 'Assente', 'bracelli-club' ) : '—' );
		return match ( $action ) {
			'corsi.slot_created'        => $slot( $diff ),
			'corsi.slot_updated'        => isset( $diff['after']['weekday'] ) ? $slot( $diff['before'] ?? null ) . ' → ' . $slot( $diff['after'] ?? null ) : ltrim( $room( $diff['before'] ?? null ) . ' →' . $room( $diff['after'] ?? null ), ', ' ),
			'corsi.attendance_recorded' => $this->p->format_date( (string) ( $diff['lesson_date'] ?? '' ) ) . ': ' . $att( $diff['after'] ?? null ),
			'corsi.attendance_changed'  => $this->p->format_date( (string) ( $diff['lesson_date'] ?? '' ) ) . ': ' . $att( $diff['before'] ?? null ) . ' → ' . $att( $diff['after'] ?? null ),
			default                     => '',
		};
	}

	/**
	 * Insegnante assegnato (account) ed eventuale nome indicato nei dati importati.
	 *
	 * @param array<string, mixed> $slot Orario o lezione.
	 */
	private function teacher_cell( array $slot ): string {
		$out = 0 === (int) $slot['teacher_id']
			? '<span class="bcp-badge">' . esc_html__( 'Da assegnare', 'bracelli-club' ) . '</span>'
			: esc_html( $this->p->user_name( (int) $slot['teacher_id'] ) );
		if ( '' !== (string) ( $slot['teacher_label'] ?? '' ) ) {
			/* translators: %s: nome dell'insegnante indicato nei dati importati. */
			$out .= ' <span class="bcp-muted">' . esc_html( sprintf( __( '(nei dati: %s)', 'bracelli-club' ), (string) $slot['teacher_label'] ) ) . '</span>';
		}
		return $out;
	}

	/**
	 * Sala assegnata oppure il vecchio testo "sede" (finché non c'è una sala).
	 *
	 * @param array<string, mixed> $slot Orario o lezione.
	 */
	private function room_cell( array $slot ): string {
		if ( (int) ( $slot['room_id'] ?? 0 ) > 0 && '' !== (string) ( $slot['room_name'] ?? '' ) ) {
			return esc_html( (string) $slot['room_name'] );
		}
		$old = (string) ( $slot['location'] ?? '' );
		return '' === $old
			? '<span class="bcp-muted">' . esc_html__( 'Sala da assegnare', 'bracelli-club' ) . '</span>'
			/* translators: %s: vecchio testo "sede" dell'orario. */
			: '<span class="bcp-muted">' . esc_html( sprintf( __( 'Sede: %s (sala da assegnare)', 'bracelli-club' ), $old ) ) . '</span>';
	}

	/**
	 * Una sala occupata diventa un errore sul campo della sala, così il modulo resta compilato.
	 *
	 * @param array{ok: bool, code: string, errors: array<string, string>, id: int} $result Esito.
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	private static function conflict_as_field( array $result ): array {
		if ( 'room_conflict' === $result['code'] ) {
			$result['code'] = 'invalid';
		}
		return $result;
	}

	/**
	 * @param array<string, mixed> $lesson Lezione.
	 */
	private function attendance_badge( array $lesson ): string {
		if ( null === $lesson['attendance_status'] ) {
			return '<span class="bcp-badge">' . esc_html__( 'Non registrata', 'bracelli-club' ) . '</span>';
		}
		$present = 'present' === $lesson['attendance_status'];
		$when    = (string) ( $lesson['attendance_updated_at'] ?? '' ) !== '' ? (string) $lesson['attendance_updated_at'] : (string) $lesson['attendance_recorded_at'];
		return sprintf(
			'<span class="bcp-badge bcp-badge--%1$s">%2$s</span> <span class="bcp-muted">%3$s</span>',
			$present ? 'present' : 'absent',
			esc_html( $present ? __( 'Presente', 'bracelli-club' ) : __( 'Assente', 'bracelli-club' ) ),
			/* translators: %s: data e ora della registrazione. */
			esc_html( sprintf( __( 'registrata il %s', 'bracelli-club' ), $this->p->format_utc( $when ) ) )
		);
	}

	/**
	 * Nome del corso con il gruppo.
	 *
	 * @param array<string, mixed> $row   Corso o orario.
	 * @param string               $field Colonna del nome.
	 */
	private function course_label( array $row, string $field = 'course_name' ): string {
		$group = (string) ( $row['group_label'] ?? '' );
		return (string) $row[ $field ] . ( '' !== $group ? ' — ' . $group : '' );
	}

	/**
	 * @param array<string, mixed> $row Orario.
	 */
	private function slot_time( array $row ): string {
		return substr( (string) $row['start_time'], 0, 5 ) . '–' . substr( (string) $row['end_time'], 0, 5 );
	}

	/**
	 * Sezione scelta nel filtro (solo valori esistenti).
	 */
	private function section_filter(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtro di sola lettura.
		$value = isset( $_GET['sezione'] ) && is_string( $_GET['sezione'] ) ? sanitize_text_field( wp_unslash( $_GET['sezione'] ) ) : '';
		return in_array( $value, $this->service->sections(), true ) ? $value : '';
	}

	/**
	 * Filtro per sezione in un modulo GET.
	 *
	 * @param string $view    Vista.
	 * @param string $current Sezione scelta.
	 */
	private function section_form( string $view, string $current ): void {
		echo '<form class="bcp-card bcp-filters" method="get" action="' . esc_url( Portal::url() ) . '">';
		$this->p->get_form_fields();
		echo '<input type="hidden" name="vista" value="' . esc_attr( $view ) . '">';
		$this->section_select( $current );
		echo '<button class="bcp-button" type="submit">' . esc_html__( 'Filtra', 'bracelli-club' ) . '</button></form>';
	}

	/**
	 * @param string $current Sezione scelta.
	 */
	private function section_select( string $current ): void {
		echo '<div class="bcp-field"><label for="bcp-sez">' . esc_html__( 'Disciplina', 'bracelli-club' ) . '</label><select id="bcp-sez" name="sezione"><option value="">' . esc_html__( 'Tutte', 'bracelli-club' ) . '</option>';
		foreach ( $this->service->sections() as $section ) {
			echo '<option value="' . esc_attr( $section ) . '"' . selected( $current, $section, false ) . '>' . esc_html( $section ) . '</option>';
		}
		echo '</select></div>';
	}
}
