<?php
/**
 * Sezione "Turni del personale" dell'area riservata.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Turni;

use Bracelli\Club\Modules\Area\Portal;
use Bracelli\Club\Modules\Area\SectionInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Viste e azioni dei turni del personale; i permessi li verifica ShiftService.
 */
final class TurniSection implements SectionInterface {

	/**
	 * Portale della richiesta corrente.
	 *
	 * @var Portal
	 */
	private Portal $p;

	/**
	 * @param ShiftService $service Turni e presenze.
	 */
	public function __construct( private ShiftService $service ) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'turni';
	}

	/**
	 * {@inheritDoc}
	 */
	public function priority(): int {
		return 20;
	}

	/**
	 * {@inheritDoc}
	 */
	public function views( int $user ): array {
		$views = [];
		if ( $this->service->can_manage( $user ) ) {
			$views += [
				'turni'    => __( 'Turni del personale', 'bracelli-club' ),
				'nuovo'    => __( 'Nuovo turno', 'bracelli-club' ),
				'modifica' => __( 'Dettaglio turno', 'bracelli-club' ),
				'annulla'  => __( 'Annulla turno', 'bracelli-club' ),
			];
		}
		if ( $this->service->is_employee( $user ) ) {
			$views['miei'] = __( 'I miei turni', 'bracelli-club' );
		}
		return $views;
	}

	/**
	 * {@inheritDoc}
	 */
	public function tabs( int $user ): array {
		$tabs = [];
		if ( $this->service->can_manage( $user ) ) {
			$tabs['turni'] = __( 'Turni del personale', 'bracelli-club' );
		}
		if ( $this->service->is_employee( $user ) ) {
			$tabs['miei'] = __( 'I miei turni', 'bracelli-club' );
		}
		return $tabs;
	}

	/**
	 * {@inheritDoc}
	 */
	public function tab_for( string $view ): string {
		return 'miei' === $view ? 'miei' : 'turni';
	}

	/**
	 * {@inheritDoc}
	 */
	public function messages(): array {
		return [
			'created'           => [ 'success', __( 'Turno creato.', 'bracelli-club' ) ],
			'updated'           => [ 'success', __( 'Turno aggiornato.', 'bracelli-club' ) ],
			'cancelled'         => [ 'success', __( 'Turno annullato. Resta visibile nello storico.', 'bracelli-club' ) ],
			'attendance_saved'  => [ 'success', __( 'Registrazione salvata.', 'bracelli-club' ) ],
			'not_found'         => [ 'error', __( 'Turno non trovato.', 'bracelli-club' ) ],
			'shift_cancelled'   => [ 'error', __( 'Il turno è annullato: non si può più modificare né registrare.', 'bracelli-club' ) ],
			'has_attendance'    => [ 'error', __( 'Il dipendente ha già registrato presenza o assenza: il turno non si può più modificare né annullare.', 'bracelli-club' ) ],
			'too_early'         => [ 'error', __( 'Potrai registrare presenza o assenza dal giorno del turno.', 'bracelli-club' ) ],
			'attendance_locked' => [ 'error', __( 'La registrazione si può cambiare solo nel giorno del turno.', 'bracelli-club' ) ],
			'too_old'           => [ 'error', __( 'Il turno è troppo vecchio per essere registrato: avvisa la segreteria.', 'bracelli-club' ) ],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function handle( Portal $portal, int $user, string $action, int $id ): void {
		$this->p = $portal;
		switch ( $action ) {
			case 'create':
				$input  = $portal->posted( [ 'employee_id', 'shift_date', 'start_time', 'end_time' ] );
				$result = $this->service->create( $user, $input );
				if ( $result['ok'] ) {
					$portal->redirect(
						[
							'vista' => 'turni',
							'dal'   => $input['shift_date'],
							'al'    => $input['shift_date'],
							'esito' => 'created',
						]
					);
				}
				$portal->keep_errors( $result, $input, 'nuovo', [ 'vista' => 'turni' ] );
				return;
			case 'update':
				$input  = $portal->posted( [ 'shift_date', 'start_time', 'end_time' ] );
				$result = $this->service->update( $user, $id, $input );
				if ( $result['ok'] ) {
					$portal->redirect(
						[
							'vista' => 'modifica',
							'turno' => $id,
							'esito' => $result['code'],
						]
					);
				}
				$portal->keep_errors( $result, $input, 'modifica', [ 'vista' => 'turni' ] );
				return;
			case 'cancel':
				$result = $this->service->cancel( $user, $id );
				$portal->redirect(
					[
						'vista' => $result['ok'] ? 'modifica' : 'turni',
						'turno' => $id,
						'esito' => $result['code'],
					]
				);
				// Nessun break: redirect() termina la richiesta.
			case 'attendance':
				$status = $portal->posted( [ 'presenza' ] )['presenza'];
				$result = $this->service->record_attendance( $user, $id, sanitize_key( $status ) );
				$portal->redirect(
					[
						'vista' => 'miei',
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
			'turni'    => $this->view_list( $user ),
			'nuovo'    => $this->view_create( $user ),
			'modifica' => $this->view_detail( $user ),
			'annulla'  => $this->view_cancel( $user ),
			default    => $this->view_mine( $user ),
		};
	}

	/**
	 * Elenco turni e presenze (segreteria).
	 *
	 * @param int $user Utente.
	 */
	private function view_list( int $user ): void {
		$today     = $this->service->today();
		$from      = $this->p->query_date( 'dal', $today );
		$to        = $this->p->query_date( 'al', gmdate( 'Y-m-d', (int) strtotime( $today . ' +13 days' ) ) );
		$employees = $this->service->employees( $user );
		if ( $to < $from ) {
			$to = $from;
		}
		$max = gmdate( 'Y-m-d', (int) strtotime( $from . ' +92 days' ) );
		if ( $to > $max ) {
			$to = $max;
		}
		$employee   = absint( $this->p->query( 'dipendente' ) );
		$status     = in_array( $this->p->query( 'stato' ), [ 'scheduled', 'cancelled' ], true ) ? $this->p->query( 'stato' ) : '';
		$attendance = in_array( $this->p->query( 'presenza' ), [ 'present', 'absent', 'none' ], true ) ? $this->p->query( 'presenza' ) : '';
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
<form class="bcp-card bcp-filters" method="get" action="<?php echo esc_url( Portal::url() ); ?>">
			<?php $this->p->get_form_fields(); ?>
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
			echo '<div class="bcp-card"><p>' . esc_html__( 'Nessun turno nel periodo e con i filtri scelti.', 'bracelli-club' ) . '</p><p><a class="bcp-button bcp-button--primary" href="' . esc_url( Portal::url( [ 'vista' => 'nuovo' ] ) ) . '">' . esc_html__( 'Crea un turno', 'bracelli-club' ) . '</a></p></div>';
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
			echo '<td data-label="' . esc_attr__( 'Data', 'bracelli-club' ) . '">' . esc_html( $this->p->format_date( (string) $row['shift_date'] ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Orario', 'bracelli-club' ) . '">' . esc_html( $this->format_slot( $row ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Dipendente', 'bracelli-club' ) . '">' . esc_html( $this->p->user_name( (int) $row['employee_id'] ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Turno', 'bracelli-club' ) . '">' . $this->status_badge( (string) $row['status'] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup costruito con escape in status_badge().
			echo '<td data-label="' . esc_attr__( 'Presenza', 'bracelli-club' ) . '">' . $this->attendance_badge( $row ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup costruito con escape in attendance_badge().
			echo '<td data-label="' . esc_attr__( 'Azioni', 'bracelli-club' ) . '" class="bcp-actions">';
			echo '<a class="bcp-link" href="' . esc_url(
				Portal::url(
					[
						'vista' => 'modifica',
						'turno' => $row['id'],
					]
				)
			) . '">' . esc_html( $editable ? __( 'Modifica', 'bracelli-club' ) : __( 'Dettagli', 'bracelli-club' ) ) . '</a>';
			if ( $editable ) {
				echo ' <a class="bcp-link bcp-link--danger" href="' . esc_url(
					Portal::url(
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
		$selected = absint( $this->p->old()['employee_id'] ?? '' );
		?>
<form class="bcp-card bcp-form" method="post" action="<?php echo esc_url( Portal::url( [ 'vista' => 'nuovo' ] ) ); ?>" novalidate>
		<?php $this->p->action_fields( 'turni_create' ); ?>
		<?php $this->p->form_errors(); ?>
	<label for="bcp-employee"><?php esc_html_e( 'Dipendente', 'bracelli-club' ); ?></label>
	<select id="bcp-employee" name="employee_id" required<?php $this->p->invalid_attr( 'employee_id' ); ?>>
		<option value=""><?php esc_html_e( '— Scegli —', 'bracelli-club' ); ?></option>
		<?php foreach ( $employees as $id => $name ) : ?>
		<option value="<?php echo esc_attr( (string) $id ); ?>"<?php selected( $selected, $id ); ?>><?php echo esc_html( $name ); ?></option>
		<?php endforeach; ?>
	</select>
		<?php $this->p->field_error( 'employee_id' ); ?>
		<?php $this->slot_fields( $this->p->old() + [ 'shift_date' => $this->service->today() ] ); ?>
	<div class="bcp-row">
		<button class="bcp-button bcp-button--primary" type="submit"><?php esc_html_e( 'Crea turno', 'bracelli-club' ); ?></button>
		<a class="bcp-link" href="<?php echo esc_url( Portal::url( [ 'vista' => 'turni' ] ) ); ?>"><?php esc_html_e( 'Torna all\'elenco', 'bracelli-club' ); ?></a>
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
		$shift = $this->service->get_visible( $user, absint( $this->p->query( 'turno' ) ) );
		if ( null === $shift ) {
			$this->p->not_found( __( 'Turno non trovato.', 'bracelli-club' ) );
			return;
		}
		$lock = $this->service->lock_reason( $shift );
		$this->summary( $shift );
		if ( '' === $lock ) {
			$values = [] !== $this->p->old() ? $this->p->old() : [
				'shift_date' => (string) $shift['shift_date'],
				'start_time' => substr( (string) $shift['start_time'], 0, 5 ),
				'end_time'   => substr( (string) $shift['end_time'], 0, 5 ),
			];
			?>
<form class="bcp-card bcp-form" method="post" action="
			<?php
			echo esc_url(
				Portal::url(
					[
						'vista' => 'modifica',
						'turno' => $shift['id'],
					]
				)
			);
			?>
														" novalidate>
	<h2><?php esc_html_e( 'Modifica data e orario', 'bracelli-club' ); ?></h2>
			<?php $this->p->action_fields( 'turni_update', (int) $shift['id'] ); ?>
			<?php $this->p->form_errors(); ?>
			<?php $this->slot_fields( $values ); ?>
	<div class="bcp-row">
		<button class="bcp-button bcp-button--primary" type="submit"><?php esc_html_e( 'Salva modifiche', 'bracelli-club' ); ?></button>
		<a class="bcp-link bcp-link--danger" href="
			<?php
			echo esc_url(
				Portal::url(
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
		echo '<p><a class="bcp-link" href="' . esc_url( Portal::url( [ 'vista' => 'turni' ] ) ) . '">' . esc_html__( '← Torna all\'elenco', 'bracelli-club' ) . '</a></p>';
	}

	/**
	 * Conferma di annullamento (segreteria).
	 *
	 * @param int $user Utente.
	 */
	private function view_cancel( int $user ): void {
		$shift = $this->service->get_visible( $user, absint( $this->p->query( 'turno' ) ) );
		if ( null === $shift ) {
			$this->p->not_found( __( 'Turno non trovato.', 'bracelli-club' ) );
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
			Portal::url(
				[
					'vista' => 'annulla',
					'turno' => $shift['id'],
				]
			)
		);
		?>
														">
	<p><?php esc_html_e( 'Il turno verrà segnato come annullato: non sarà eliminato e resterà nello storico. Il dipendente lo vedrà come annullato.', 'bracelli-club' ); ?></p>
		<?php $this->p->action_fields( 'turni_cancel', (int) $shift['id'] ); ?>
	<div class="bcp-row">
		<button class="bcp-button bcp-button--danger" type="submit"><?php esc_html_e( 'Conferma annullamento', 'bracelli-club' ); ?></button>
		<a class="bcp-link" href="
		<?php
		echo esc_url(
			Portal::url(
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
		echo '<div class="bcp-shift__head"><p class="bcp-shift__date">' . ( $is_today ? '<span class="bcp-badge bcp-badge--today">' . esc_html__( 'Oggi', 'bracelli-club' ) . '</span> ' : '' ) . esc_html( $this->p->format_date( (string) $shift['shift_date'] ) ) . '</p>';
		echo '<p class="bcp-shift__time">' . esc_html( $this->format_slot( $shift ) ) . '</p></div>';
		echo '<p class="bcp-shift__state">' . $this->status_badge( (string) $shift['status'] ) . ' ' . $this->attendance_badge( $shift ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup con escape.
		if ( '' === $reason ) {
			echo '<form class="bcp-attendance" method="post" action="' . esc_url( Portal::url( [ 'vista' => 'miei' ] ) ) . '">';
			$this->p->action_fields( 'turni_attendance', (int) $shift['id'] );
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
	<input id="bcp-date" type="date" name="shift_date" required value="<?php echo esc_attr( $values['shift_date'] ?? '' ); ?>"<?php $this->p->invalid_attr( 'shift_date' ); ?>>
		<?php $this->p->field_error( 'shift_date' ); ?>
	<div class="bcp-row bcp-row--fields">
		<div class="bcp-field"><label for="bcp-start"><?php esc_html_e( 'Inizio', 'bracelli-club' ); ?></label>
		<input id="bcp-start" type="time" name="start_time" required step="60" value="<?php echo esc_attr( $values['start_time'] ?? '' ); ?>"<?php $this->p->invalid_attr( 'start_time' ); ?>>
		<?php $this->p->field_error( 'start_time' ); ?></div>
		<div class="bcp-field"><label for="bcp-end"><?php esc_html_e( 'Fine', 'bracelli-club' ); ?></label>
		<input id="bcp-end" type="time" name="end_time" required step="60" value="<?php echo esc_attr( $values['end_time'] ?? '' ); ?>"<?php $this->p->invalid_attr( 'end_time' ); ?>>
		<?php $this->p->field_error( 'end_time' ); ?></div>
	</div>
		<?php
	}

	/**
	 * Riepilogo di un turno.
	 *
	 * @param array<string, mixed> $shift Turno.
	 */
	private function summary( array $shift ): void {
		echo '<dl class="bcp-card bcp-summary">';
		echo '<div><dt>' . esc_html__( 'Dipendente', 'bracelli-club' ) . '</dt><dd>' . esc_html( $this->p->user_name( (int) $shift['employee_id'] ) ) . '</dd></div>';
		echo '<div><dt>' . esc_html__( 'Data', 'bracelli-club' ) . '</dt><dd>' . esc_html( $this->p->format_date( (string) $shift['shift_date'] ) ) . '</dd></div>';
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
			echo '<li><span class="bcp-history__when">' . esc_html( (string) $when ) . '</span> <strong>' . esc_html( $label ) . '</strong> — ' . esc_html( $this->p->user_name( (int) $item['actor_id'] ) );
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
			? $this->p->format_date( (string) $d['shift_date'] ) . ' ' . $this->format_slot( $d )
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
	 * @param array<string, mixed> $row Turno.
	 */
	private function format_slot( array $row ): string {
		return substr( (string) $row['start_time'], 0, 5 ) . '–' . substr( (string) $row['end_time'], 0, 5 );
	}
}
