<?php
/**
 * Avanzamento del progetto nella dashboard: fasi del modulo Corsi coerenti con il codice.
 *
 * @package BracelliClub
 */

namespace Bracelli\Club\Tests\integration;

use Bracelli\Club\Modules\Corsi\CatalogService;
use Bracelli\Club\Modules\Corsi\RoomService;
use Bracelli\Club\Modules\Dashboard\Roadmap;

final class RoadmapTest extends TestCase {

	public function test_corsi_phases_follow_the_current_plan_in_order(): void {
		$phases = Roadmap::corsi();
		$this->assertSame( [ 0, 1, 2, 3, 4 ], array_column( $phases, 'number' ) );
		$this->assertSame(
			[ 'Stabilizzazione e audit', 'Catalogo e sale', 'Calendario, eccezioni e stagioni', 'Insegnanti, sostituzioni e presenze', 'Calendari e report' ],
			array_column( $phases, 'title' )
		);
		foreach ( $phases as $phase ) {
			$this->assertContains( $phase['status'], [ Roadmap::COMPLETED, Roadmap::IN_PROGRESS, Roadmap::PLANNED ] );
			$this->assertNotSame( '', $phase['description'] );
		}
	}

	public function test_states_match_what_is_really_in_the_code(): void {
		global $wpdb;
		$status = array_column( Roadmap::corsi(), 'status', 'number' );

		// Fase 0: correzioni dell'audit presenti (data di assegnazione degli orari).
		$this->assertSame( Roadmap::COMPLETED, $status[0] );
		$this->assertNotNull( $wpdb->get_var( "SHOW COLUMNS FROM {$wpdb->prefix}bc_corsi_slots LIKE 'assigned_from'" ) );

		// Fase 1: sviluppata (catalogo e sale esistono) ma in collaudo, quindi non ancora "completata".
		$this->assertSame( Roadmap::IN_PROGRESS, $status[1] );
		$this->assertTrue( class_exists( CatalogService::class ) && class_exists( RoomService::class ) );
		$this->assertSame( $wpdb->prefix . 'bc_corsi_rooms', $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'bc_corsi_rooms' ) ) );

		// Fasi 2-4: nessuna funzione sviluppata.
		foreach ( [ 2, 3, 4 ] as $planned ) {
			$this->assertSame( Roadmap::PLANNED, $status[ $planned ], 'Fase ' . $planned );
		}
	}

	public function test_widget_counts_only_completed_phases_and_lists_areas_not_started(): void {
		$widget = Roadmap::widget();
		$this->assertSame( 1, $widget['completed'] );
		$this->assertSame( 5, $widget['total'] );
		$this->assertSame( [ 'members', 'bookings', 'billing', 'documents', 'site' ], array_column( $widget['areas'], 'id' ) );
		$this->assertSame( [ Roadmap::PLANNED ], array_values( array_unique( array_column( $widget['areas'], 'status' ) ) ) );
		$this->assertNotContains( 'courses', array_column( $widget['areas'], 'id' ), 'I corsi hanno ora le loro fasi.' );
	}
}
