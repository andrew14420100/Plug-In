<?php
/**
 * Panoramica operativa della segreteria nell'area riservata.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Corsi;

use Bracelli\Club\Modules\Area\Portal;
use Bracelli\Club\Modules\Area\SectionInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Home della segreteria: mostra solo conteggi ricavati dal database e collegamenti a funzioni esistenti.
 * Non simula anagrafiche, pagamenti o documenti che non sono ancora implementati.
 */
final class SegreteriaSection implements SectionInterface {

	public function __construct( private CourseService $courses ) {
	}

	public function id(): string {
		return 'segreteria';
	}

	public function priority(): int {
		return 0;
	}

	public function views( int $user ): array {
		return $this->courses->can_manage( $user )
			? [ 'segreteria' => __( 'Panoramica segreteria', 'bracelli-club' ) ]
			: [];
	}

	public function tabs( int $user ): array {
		return $this->views( $user );
	}

	public function tab_for( string $view ): string {
		return 'segreteria';
	}

	public function messages(): array {
		return [];
	}

	public function handle( Portal $portal, int $user, string $action, int $id ): void {
		$portal->redirect( [ 'vista' => 'segreteria', 'esito' => 'invalid' ] );
	}

	public function render( Portal $portal, int $user, string $view ): void {
		if ( ! $this->courses->can_manage( $user ) ) {
			status_header( 403 );
			echo '<p class="bcp-alert bcp-alert--error">' . esc_html__( 'Non hai i permessi per accedere alla panoramica della segreteria.', 'bracelli-club' ) . '</p>';
			return;
		}

		$repo    = $this->courses->repo();
		$courses = $repo->courses();
		$slots   = $repo->slots( [ 'status' => 'active' ] );
		$rooms   = $repo->rooms();

		$active_courses = count(
			array_filter(
				$courses,
				static fn( array $course ): bool => 'active' === ( $course['status'] ?? '' )
			)
		);
		$active_slots = array_values(
			array_filter(
				$slots,
				static fn( array $slot ): bool => 'active' === ( $slot['course_status'] ?? '' )
			)
		);
		$active_rooms = count(
			array_filter(
				$rooms,
				static fn( array $room ): bool => 'active' === ( $room['status'] ?? '' )
			)
		);
		$without_teacher = count(
			array_filter(
				$active_slots,
				static fn( array $slot ): bool => (int) ( $slot['teacher_id'] ?? 0 ) < 1
			)
		);
		$without_room = count(
			array_filter(
				$active_slots,
				static fn( array $slot ): bool => (int) ( $slot['room_id'] ?? 0 ) < 1 && '' === (string) ( $slot['location'] ?? '' )
			)
		);

		echo '<section class="bcp-card bcp-secretary-hero">';
		echo '<p class="bcp-muted">' . esc_html__( 'CENTRO SPORTIVO · GESTIONE OPERATIVA', 'bracelli-club' ) . '</p>';
		echo '<h2>' . esc_html__( 'Benvenuto in segreteria', 'bracelli-club' ) . '</h2>';
		echo '<p>' . esc_html__( 'Da qui puoi raggiungere le attività quotidiane e controllare i dati operativi disponibili. Le sezioni non ancora sviluppate sono indicate chiaramente.', 'bracelli-club' ) . '</p>';
		echo '</section>';

		echo '<h2>' . esc_html__( 'Situazione corsi', 'bracelli-club' ) . '</h2>';
		echo '<div class="bcp-secretary-metrics">';
		$this->metric( __( 'Corsi attivi', 'bracelli-club' ), number_format_i18n( $active_courses ), __( 'Catalogo corrente', 'bracelli-club' ) );
		$this->metric( __( 'Orari attivi', 'bracelli-club' ), number_format_i18n( count( $active_slots ) ), __( 'Orari settimanali confermati', 'bracelli-club' ) );
		$this->metric( __( 'Sale attive', 'bracelli-club' ), number_format_i18n( $active_rooms ), __( 'Risorse disponibili nel catalogo', 'bracelli-club' ) );
		echo '</div>';

		echo '<h2>' . esc_html__( 'Da completare', 'bracelli-club' ) . '</h2>';
		echo '<div class="bcp-secretary-metrics">';
		$this->metric( __( 'Orari senza insegnante', 'bracelli-club' ), number_format_i18n( $without_teacher ), __( 'Da verificare nel catalogo corsi', 'bracelli-club' ) );
		$this->metric( __( 'Orari senza sala o sede', 'bracelli-club' ), number_format_i18n( $without_room ), __( 'Solo orari senza una collocazione indicata', 'bracelli-club' ) );
		echo '</div>';

		echo '<h2>' . esc_html__( 'Accessi rapidi', 'bracelli-club' ) . '</h2>';
		echo '<div class="bcp-secretary-links">';
		$this->quick_link( 'orari', __( 'Corsi e orari', 'bracelli-club' ), __( 'Consulta e modifica la programmazione settimanale.', 'bracelli-club' ) );
		$this->quick_link( 'nuovo-corso', __( 'Crea un corso', 'bracelli-club' ), __( 'Aggiungi una nuova attività al catalogo.', 'bracelli-club' ) );
		$this->quick_link( 'presenze', __( 'Presenze insegnanti', 'bracelli-club' ), __( 'Controlla le presenze registrate dagli insegnanti.', 'bracelli-club' ) );
		$this->quick_link( 'sale', __( 'Sale e spazi', 'bracelli-club' ), __( 'Gestisci gli spazi disponibili per le lezioni.', 'bracelli-club' ) );
		$this->quick_link( 'corsi', __( 'Calendario corsi', 'bracelli-club' ), __( 'Visualizza la programmazione settimanale.', 'bracelli-club' ) );
		echo '</div>';

		echo '<h2>' . esc_html__( 'Altre aree della segreteria', 'bracelli-club' ) . '</h2>';
		echo '<div class="bcp-secretary-links">';
		$this->planned_card( __( 'Archivio soci', 'bracelli-club' ), __( 'Anagrafiche, famiglie, contatti e storico.', 'bracelli-club' ) );
		$this->planned_card( __( 'Iscrizioni e presenze dei partecipanti', 'bracelli-club' ), __( 'Collegamento dei soci ai corsi e registro delle presenze degli allievi.', 'bracelli-club' ) );
		$this->planned_card( __( 'Quote e pagamenti', 'bracelli-club' ), __( 'Tariffe, sconti autorizzati, incassi registrati e scadenze.', 'bracelli-club' ) );
		$this->planned_card( __( 'Documenti e richieste', 'bracelli-club' ), __( 'Documenti protetti, moduli e richieste da evadere.', 'bracelli-club' ) );
		echo '</div>';
	}

	private function metric( string $label, string $value, string $description ): void {
		echo '<article class="bcp-card bcp-secretary-metric"><p class="bcp-muted">' . esc_html( $label ) . '</p><p class="bcp-secretary-metric__value">' . esc_html( $value ) . '</p><p class="bcp-muted">' . esc_html( $description ) . '</p></article>';
	}

	private function quick_link( string $view, string $title, string $description ): void {
		echo '<a class="bcp-card bcp-secretary-link" href="' . esc_url( Portal::url( [ 'vista' => $view ] ) ) . '"><span class="bcp-secretary-link__title">' . esc_html( $title ) . '</span><span class="bcp-muted">' . esc_html( $description ) . '</span><span class="bcp-link">' . esc_html__( 'Apri sezione →', 'bracelli-club' ) . '</span></a>';
	}

	private function planned_card( string $title, string $description ): void {
		echo '<article class="bcp-card bcp-secretary-link bcp-secretary-link--planned"><span class="bcp-secretary-link__title">' . esc_html( $title ) . '</span><span class="bcp-muted">' . esc_html( $description ) . '</span><span class="bcp-badge">' . esc_html__( 'In pianificazione', 'bracelli-club' ) . '</span></article>';
	}
}
