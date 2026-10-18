<?php
/**
 * Modulo Turni e presenze: permessi sul server, accessi incrociati, storico, regole di registrazione.
 *
 * @package BracelliClub
 */

namespace Bracelli\Club\Tests\integration;

use Bracelli\Club\Core\Permissions\Roles;
use Bracelli\Club\Modules\Turni\Portal;
use Bracelli\Club\Modules\Turni\ShiftRepository;
use Bracelli\Club\Modules\Turni\ShiftService;
use Bracelli\Club\Modules\Turni\TurniModule;

final class TurniTest extends TestCase {

	private const TODAY = '2026-10-18';

	private ShiftService $service;

	private int $segreteria;

	private int $mario;

	private int $lucia;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$this->service    = new ShiftService( new ShiftRepository( $wpdb ), $this->plugin->audit, static fn(): string => self::TODAY );
		$this->segreteria = $this->user_with_role( Roles::SEGRETERIA );
		$this->mario      = self::factory()->user->create(
			[
				'role'         => Roles::DIPENDENTE,
				'display_name' => 'Dipendente Fittizio Uno',
			]
		);
		$this->lucia      = self::factory()->user->create(
			[
				'role'         => Roles::DIPENDENTE,
				'display_name' => 'Dipendente Fittizio Due',
			]
		);
	}

	/**
	 * @param int    $employee Dipendente.
	 * @param string $date     Data.
	 * @param string $start    Inizio.
	 * @param string $end      Fine.
	 */
	private function shift( int $employee, string $date = self::TODAY, string $start = '09:00', string $end = '13:00' ): int {
		$result = $this->service->create(
			$this->segreteria,
			[
				'employee_id' => (string) $employee,
				'shift_date'  => $date,
				'start_time'  => $start,
				'end_time'    => $end,
			]
		);
		$this->assertTrue( $result['ok'], $result['code'] . ' ' . wp_json_encode( $result['errors'] ) );
		return $result['id'];
	}

	private function shift_audit_count( string $action, int $shift ): int {
		return $this->plugin->audit->query(
			[
				'action'        => $action,
				'resource_type' => ShiftService::AUDIT_RESOURCE,
				'resource_id'   => (string) $shift,
			]
		)['total'];
	}

	public function test_segreteria_creates_shift_and_it_is_audited(): void {
		$id    = $this->shift( $this->mario );
		$shift = $this->service->get_visible( $this->segreteria, $id );
		$this->assertSame( $this->mario, $shift['employee_id'] );
		$this->assertSame( self::TODAY, $shift['shift_date'] );
		$this->assertSame( '09:00:00', $shift['start_time'] );
		$this->assertSame( 'scheduled', $shift['status'] );
		$this->assertNull( $shift['attendance_status'] );
		$this->assertSame( 1, $this->shift_audit_count( 'turni.shift_created', $id ) );
	}

	public function test_create_validates_input(): void {
		$cases = [
			'employee_id' => [ 'employee_id' => (string) $this->segreteria ],
			'shift_date'  => [ 'shift_date' => '2026-02-30' ],
			'start_time'  => [ 'start_time' => '25:00' ],
			'end_time'    => [
				'start_time' => '14:00',
				'end_time'   => '13:00',
			],
		];
		foreach ( $cases as $field => $override ) {
			$result = $this->service->create(
				$this->segreteria,
				$override + [
					'employee_id' => (string) $this->mario,
					'shift_date'  => self::TODAY,
					'start_time'  => '09:00',
					'end_time'    => '13:00',
				]
			);
			$this->assertFalse( $result['ok'], $field );
			$this->assertSame( 'invalid', $result['code'] );
			$this->assertArrayHasKey( $field, $result['errors'] );
		}
		$this->assertSame( [], $this->service->list_for_manager( $this->segreteria, [] ) );
	}

	public function test_overlapping_shifts_for_same_employee_are_rejected(): void {
		$this->shift( $this->mario, self::TODAY, '09:00', '13:00' );
		$overlap = $this->service->create(
			$this->segreteria,
			[
				'employee_id' => (string) $this->mario,
				'shift_date'  => self::TODAY,
				'start_time'  => '12:00',
				'end_time'    => '15:00',
			]
		);
		$this->assertSame( 'invalid', $overlap['code'] );
		// Turno spezzato contiguo e stesso orario per un altro dipendente: consentiti.
		$this->shift( $this->mario, self::TODAY, '13:00', '17:00' );
		$this->shift( $this->lucia, self::TODAY, '09:00', '13:00' );
	}

	public function test_employee_and_anonymous_cannot_manage_shifts(): void {
		$id = $this->shift( $this->mario );
		foreach ( [ $this->mario, 0, self::factory()->user->create( [ 'role' => 'subscriber' ] ) ] as $actor ) {
			$this->assertSame( 'forbidden', $this->service->create( $actor, [ 'employee_id' => (string) $this->mario ] )['code'] );
			$this->assertSame( 'forbidden', $this->service->update( $actor, $id, [] )['code'] );
			$this->assertSame( 'forbidden', $this->service->cancel( $actor, $id )['code'] );
			$this->assertNull( $this->service->list_for_manager( $actor, [] ) );
			$this->assertSame( [], $this->service->history( $actor, $id ) );
		}
		$this->assertSame( 'scheduled', $this->service->get_visible( $this->segreteria, $id )['status'] );
	}

	public function test_employee_sees_only_own_shifts(): void {
		$mine   = $this->shift( $this->mario );
		$others = $this->shift( $this->lucia );
		$list   = $this->service->list_for_employee( $this->mario, '2026-01-01', '2026-12-31' );
		$this->assertSame( [ $mine ], array_column( $list, 'id' ) );
		$this->assertNotNull( $this->service->get_visible( $this->mario, $mine ) );
		$this->assertNull( $this->service->get_visible( $this->mario, $others ) );
		$this->assertNull( $this->service->list_for_employee( $this->segreteria, '2026-01-01', '2026-12-31' ) );
	}

	public function test_update_changes_date_and_time_with_history(): void {
		$id     = $this->shift( $this->mario );
		$result = $this->service->update(
			$this->segreteria,
			$id,
			[
				'shift_date' => '2026-10-19',
				'start_time' => '15:00',
				'end_time'   => '19:30',
			]
		);
		$this->assertSame( 'updated', $result['code'] );
		$shift = $this->service->get_visible( $this->segreteria, $id );
		$this->assertSame( '2026-10-19', $shift['shift_date'] );
		$this->assertSame( '19:30:00', $shift['end_time'] );
		$this->assertSame( $this->segreteria, $shift['updated_by'] );

		$history = $this->service->history( $this->segreteria, $id );
		$this->assertSame( [ 'turni.shift_updated', 'turni.shift_created' ], array_column( $history, 'action' ) );
		$this->assertSame( '09:00:00', $history[0]['diff']['before']['start_time'] );
		$this->assertSame( '15:00:00', $history[0]['diff']['after']['start_time'] );

		$same = $this->service->update(
			$this->segreteria,
			$id,
			[
				'shift_date' => '2026-10-19',
				'start_time' => '15:00',
				'end_time'   => '19:30',
			]
		);
		$this->assertSame( 'unchanged', $same['code'] );
	}

	public function test_cancel_keeps_the_shift_and_locks_it(): void {
		global $wpdb;
		$id = $this->shift( $this->mario );
		$this->assertSame( 'cancelled', $this->service->cancel( $this->segreteria, $id )['code'] );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT status, cancelled_by FROM {$wpdb->prefix}bc_turni_shifts WHERE id = %d", $id ), ARRAY_A );
		$this->assertSame( 'cancelled', $row['status'] );
		$this->assertSame( (string) $this->segreteria, $row['cancelled_by'] );
		$this->assertSame( 'shift_cancelled', $this->service->cancel( $this->segreteria, $id )['code'] );
		$this->assertSame( 'shift_cancelled', $this->service->update( $this->segreteria, $id, [] )['code'] );
		$this->assertSame( 'shift_cancelled', $this->service->record_attendance( $this->mario, $id, 'present' )['code'] );
		$this->assertSame( 1, $this->shift_audit_count( 'turni.shift_cancelled', $id ) );
		// Il dipendente continua a vedere il turno, come annullato.
		$this->assertSame( 'cancelled', $this->service->list_for_employee( $this->mario, self::TODAY, self::TODAY )[0]['status'] );
	}

	public function test_employee_records_attendance_for_own_shift_today(): void {
		$id = $this->shift( $this->mario );
		$this->assertSame( 'attendance_saved', $this->service->record_attendance( $this->mario, $id, 'present' )['code'] );
		$this->assertSame( 'present', $this->service->get_visible( $this->mario, $id )['attendance_status'] );
		$this->assertSame( 'unchanged', $this->service->record_attendance( $this->mario, $id, 'present' )['code'] );
		// Stesso giorno: la scelta si può cambiare, con storico.
		$this->assertSame( 'attendance_saved', $this->service->record_attendance( $this->mario, $id, 'absent' )['code'] );
		$this->assertSame( 'absent', $this->service->get_visible( $this->segreteria, $id )['attendance_status'] );
		$this->assertSame( 1, $this->shift_audit_count( 'turni.attendance_recorded', $id ) );
		$this->assertSame( 1, $this->shift_audit_count( 'turni.attendance_changed', $id ) );
		// Con una registrazione, la segreteria non può più modificare o annullare il turno.
		$this->assertSame( 'has_attendance', $this->service->cancel( $this->segreteria, $id )['code'] );
		$this->assertSame( 'has_attendance', $this->service->update( $this->segreteria, $id, [] )['code'] );
	}

	public function test_employee_cannot_record_for_another_employee(): void {
		$id     = $this->shift( $this->lucia );
		$result = $this->service->record_attendance( $this->mario, $id, 'present' );
		$this->assertSame( 'not_found', $result['code'] );
		$this->assertNull( $this->service->get_visible( $this->segreteria, $id )['attendance_status'] );
		$this->assertSame( 'forbidden', $this->service->record_attendance( $this->segreteria, $id, 'present' )['code'] );
	}

	public function test_attendance_time_rules(): void {
		$future = $this->shift( $this->mario, '2026-10-19' );
		$this->assertSame( 'too_early', $this->service->record_attendance( $this->mario, $future, 'present' )['code'] );

		$past = $this->shift( $this->mario, '2026-10-17' );
		$this->assertSame( 'attendance_saved', $this->service->record_attendance( $this->mario, $past, 'absent' )['code'] );
		$this->assertSame( 'attendance_locked', $this->service->record_attendance( $this->mario, $past, 'present' )['code'] );

		$this->assertSame( 'invalid', $this->service->record_attendance( $this->mario, $past, 'in_ritardo' )['code'] );
	}

	public function test_employees_list_contains_only_employee_accounts(): void {
		$employees = $this->service->employees( $this->segreteria );
		$this->assertArrayHasKey( $this->mario, $employees );
		$this->assertArrayHasKey( $this->lucia, $employees );
		$this->assertArrayNotHasKey( $this->segreteria, $employees );
		$this->assertArrayNotHasKey( 1, $employees );
		$this->assertSame( [], $this->service->employees( $this->mario ) );
	}

	public function test_login_redirect_and_portal_only_accounts(): void {
		$module = new TurniModule();
		$this->assertTrue( TurniModule::is_portal_only( $this->mario ) );
		$this->assertFalse( TurniModule::is_portal_only( $this->segreteria ) );
		$this->assertSame( Portal::url(), $module->login_redirect( admin_url(), '', get_userdata( $this->mario ) ) );
		$this->assertSame( admin_url(), $module->login_redirect( admin_url(), '', get_userdata( $this->segreteria ) ) );
		wp_set_current_user( $this->mario );
		$this->assertFalse( $module->show_admin_bar( true ) );
	}

	/* --------------------------------------------------------------
	 * Richieste al portale (moduli HTML con nonce)
	 * -------------------------------------------------------------- */

	/**
	 * Esegue il portale e restituisce l'HTML oppure la destinazione del redirect.
	 *
	 * @param array<string, mixed> $post Campi POST (vuoto = GET).
	 * @param array<string, mixed> $get  Parametri GET.
	 * @return array{0: string, 1: string} [html, redirect]
	 */
	private function portal( array $post = [], array $get = [] ): array {
		global $wpdb;
		$this->capture_redirects();
		$_GET                      = $get;
		$_POST                     = $post;
		$_SERVER['REQUEST_METHOD'] = [] === $post ? 'GET' : 'POST';
		$portal                    = new Portal( $this->plugin, new ShiftService( new ShiftRepository( $wpdb ), $this->plugin->audit ) );
		ob_start();
		try {
			$portal->serve();
		} catch ( RedirectException $e ) {
			ob_end_clean();
			return [ '', $e->getMessage() ];
		}
		return [ (string) ob_get_clean(), '' ];
	}

	public function test_portal_shows_login_form_to_visitors(): void {
		wp_set_current_user( 0 );
		[ $html ] = $this->portal();
		$this->assertStringContainsString( 'name="pwd"', $html );
		$this->assertStringNotContainsString( 'Dipendente Fittizio', $html );
	}

	public function test_portal_refuses_accounts_without_permissions(): void {
		$this->login_as( 'subscriber' );
		[ $html ] = $this->portal();
		$this->assertStringContainsString( 'non è abilitato', $html );
		$this->assertStringNotContainsString( 'bc_action', $html );
	}

	public function test_portal_employee_page_shows_only_own_shifts(): void {
		$service = new ShiftService( new ShiftRepository( $GLOBALS['wpdb'] ), $this->plugin->audit );
		$today   = $service->today();
		$this->shift( $this->mario, $today, '08:00', '10:00' );
		$this->shift( $this->lucia, $today, '17:00', '19:00' );
		wp_set_current_user( $this->mario );
		[ $html ] = $this->portal();
		$this->assertStringContainsString( '08:00–10:00', $html );
		$this->assertStringNotContainsString( '17:00–19:00', $html );
		$this->assertStringContainsString( 'value="present"', $html );
		// Il dipendente non può aprire le viste della segreteria.
		[ $html ] = $this->portal( [], [ 'vista' => 'turni' ] );
		$this->assertStringNotContainsString( '17:00–19:00', $html );
		$this->assertStringNotContainsString( 'name="employee_id"', $html );
	}

	public function test_portal_attendance_requires_nonce_and_ownership(): void {
		$service = new ShiftService( new ShiftRepository( $GLOBALS['wpdb'] ), $this->plugin->audit );
		$others  = $this->shift( $this->lucia, $service->today() );
		$mine    = $this->shift( $this->mario, $service->today(), '14:00', '15:00' );
		wp_set_current_user( $this->mario );

		[ , $location ] = $this->portal(
			[
				'bc_action' => 'attendance',
				'turno'     => (string) $mine,
				'presenza'  => 'present',
				'_bc_nonce' => 'falso',
			]
		);
		$this->assertStringContainsString( 'esito=expired', $location );
		$this->assertNull( $service->get_visible( $this->mario, $mine )['attendance_status'] );

		// Nonce valido (preso per il proprio turno) ma id del turno di un altro dipendente.
		[ , $location ] = $this->portal(
			[
				'bc_action' => 'attendance',
				'turno'     => (string) $others,
				'presenza'  => 'present',
				'_bc_nonce' => wp_create_nonce( 'bracelli_turni_attendance_' . $mine ),
			]
		);
		$this->assertStringContainsString( 'esito=expired', $location );
		[ , $location ] = $this->portal(
			[
				'bc_action' => 'attendance',
				'turno'     => (string) $others,
				'presenza'  => 'present',
				'_bc_nonce' => wp_create_nonce( 'bracelli_turni_attendance_' . $others ),
			]
		);
		$this->assertStringContainsString( 'esito=not_found', $location );
		$this->assertNull( $service->get_visible( $this->segreteria, $others )['attendance_status'] );

		[ , $location ] = $this->portal(
			[
				'bc_action' => 'attendance',
				'turno'     => (string) $mine,
				'presenza'  => 'present',
				'_bc_nonce' => wp_create_nonce( 'bracelli_turni_attendance_' . $mine ),
			]
		);
		$this->assertStringContainsString( 'esito=attendance_saved', $location );
		$this->assertSame( 'present', $service->get_visible( $this->mario, $mine )['attendance_status'] );
	}

	public function test_portal_employee_cannot_create_shifts_even_with_valid_nonce(): void {
		wp_set_current_user( $this->mario );
		[ , $location ] = $this->portal(
			[
				'bc_action'   => 'create',
				'employee_id' => (string) $this->mario,
				'shift_date'  => self::TODAY,
				'start_time'  => '09:00',
				'end_time'    => '10:00',
				'_bc_nonce'   => wp_create_nonce( 'bracelli_turni_create' ),
			]
		);
		$this->assertStringContainsString( 'esito=forbidden', $location );
		$this->assertSame( [], $this->service->list_for_manager( $this->segreteria, [] ) );
	}

	public function test_portal_segreteria_creates_and_sees_validation_errors(): void {
		wp_set_current_user( $this->segreteria );
		[ $html ] = $this->portal(
			[
				'bc_action'   => 'create',
				'employee_id' => (string) $this->mario,
				'shift_date'  => self::TODAY,
				'start_time'  => '18:00',
				'end_time'    => '09:00',
				'_bc_nonce'   => wp_create_nonce( 'bracelli_turni_create' ),
			]
		);
		$this->assertStringContainsString( 'aria-invalid="true"', $html );
		$this->assertSame( [], $this->service->list_for_manager( $this->segreteria, [] ) );

		[ , $location ] = $this->portal(
			[
				'bc_action'   => 'create',
				'employee_id' => (string) $this->mario,
				'shift_date'  => self::TODAY,
				'start_time'  => '09:00',
				'end_time'    => '18:00',
				'_bc_nonce'   => wp_create_nonce( 'bracelli_turni_create' ),
			]
		);
		$this->assertStringContainsString( 'esito=created', $location );
		$this->assertCount( 1, $this->service->list_for_manager( $this->segreteria, [] ) );

		[ $html ] = $this->portal(
			[],
			[
				'vista' => 'turni',
				'dal'   => self::TODAY,
				'al'    => self::TODAY,
			]
		);
		$this->assertStringContainsString( 'Dipendente Fittizio Uno', $html );
		$this->assertStringContainsString( '09:00–18:00', $html );
	}

	public function test_portal_output_is_escaped(): void {
		global $wpdb;
		$evil = self::factory()->user->create( [ 'role' => Roles::DIPENDENTE ] );
		// WordPress ripulisce già i nomi: si simula un dato malevolo scritto direttamente nel database.
		$wpdb->update( $wpdb->users, [ 'display_name' => '<script>alert(1)</script>' ], [ 'ID' => $evil ] );
		clean_user_cache( $evil );
		$this->shift( $evil );
		wp_set_current_user( $this->segreteria );
		[ $html ] = $this->portal(
			[],
			[
				'vista' => 'turni',
				'dal'   => self::TODAY,
				'al'    => self::TODAY,
			]
		);
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}
}
