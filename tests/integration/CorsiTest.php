<?php
/**
 * Modulo Corsi: importazione ripetibile, permessi sul server, presenze degli insegnanti, area riservata.
 * Solo dati fittizi; il file reale dei corsi (escluso da Git) viene controllato solo se presente in locale.
 *
 * @package BracelliClub
 */

namespace Bracelli\Club\Tests\integration;

use Bracelli\Club\Core\Permissions\Roles;
use Bracelli\Club\Modules\Area\AreaModule;
use Bracelli\Club\Modules\Area\Portal;
use Bracelli\Club\Modules\Corsi\CourseImporter;
use Bracelli\Club\Modules\Corsi\CourseRepository;
use Bracelli\Club\Modules\Corsi\CourseService;

final class CorsiTest extends TestCase {

	// Domenica 18 ottobre 2026: 2026-10-19 è lunedì, 2026-10-13 è martedì.
	private const TODAY = '2026-10-18';

	private const CSV = "\xEF\xBB\xBFchiave;sezione;corso;gruppo;giorno;inizio;fine;insegnante;sede;note\n"
		. "prova-karate;Arti marziali;Karate Prova;bambini;Lunedì;17:00;18:00;;;\n"
		. "prova-karate;Arti marziali;Karate Prova;bambini;Domenica;17:00;18:00;Nome Fittizio;Palestra A;\n"
		. "prova-judo;Arti marziali;Judo Prova;;Martedì;18:00;19:00;;;\n"
		. "prova-aikido;Arti marziali;Aikido Prova;;Martedì;;;;;Orario non indicato\n"
		. "prova-danza;Danza;Danza Prova;;;;;;;Giorni e orari non indicati\n"
		. "prova-errore;Danza;Errore Prova;;Domenica;19:00;18:00;;;\n"
		. "prova-karate;Arti marziali;Karate Prova;bambini;Lunedì;17:00;18:00;;;\n";

	private CourseRepository $repo;

	private CourseService $service;

	private CourseImporter $importer;

	private int $segreteria;

	private int $anna;

	private int $bruno;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		// La suite isola ogni prova in una transazione: l'importazione non deve aprirne (e chiuderne) un'altra.
		add_filter( 'bracelli_club_corsi_import_transaction', '__return_false' );
		$this->repo       = new CourseRepository( $wpdb );
		$this->service    = new CourseService( $this->repo, $this->plugin->audit, static fn(): string => self::TODAY );
		$this->importer   = new CourseImporter( $this->repo, $this->plugin->audit );
		$this->segreteria = $this->user_with_role( Roles::SEGRETERIA );
		$this->anna       = self::factory()->user->create(
			[
				'role'         => Roles::ISTRUTTORE,
				'display_name' => 'Insegnante Fittizia Anna',
			]
		);
		$this->bruno      = self::factory()->user->create(
			[
				'role'         => Roles::ISTRUTTORE,
				'display_name' => 'Insegnante Fittizio Bruno',
			]
		);
	}

	/**
	 * Importa il CSV di prova e restituisce gli orari per chiave.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function import(): array {
		$result = $this->importer->apply( $this->importer->parse( self::CSV )['rows'], $this->segreteria, 'prova.csv' );
		$this->assertTrue( $result['ok'], $result['error'] );
		return $this->repo->slots_by_key();
	}

	public function test_plan_reports_counts_and_anomalies_without_writing(): void {
		$parsed = $this->importer->parse( self::CSV );
		$this->assertSame( [], $parsed['errors'] );
		$plan = $this->importer->plan( $parsed['rows'] );
		$this->assertSame( 5, $plan['counts']['courses_create'] );
		$this->assertSame( 3, $plan['counts']['slots_create'] );
		// Aikido senza orario, Danza senza giorno né orario, fine prima dell'inizio, riga duplicata.
		$this->assertSame( 4, $plan['counts']['anomalies'] );
		$messages = implode( "\n", array_column( $plan['anomalies'], 'message' ) );
		$this->assertStringContainsString( 'orario mancante', $messages );
		$this->assertStringContainsString( 'senza giorno né orario', $messages );
		$this->assertStringContainsString( 'non è successiva', $messages );
		$this->assertStringContainsString( 'ripetuto', $messages );
		$this->assertSame( [], $this->repo->courses(), 'L\'anteprima non scrive nulla.' );
	}

	public function test_import_is_idempotent_and_keeps_teacher_label_without_account(): void {
		$slots = $this->import();
		$this->assertCount( 5, $this->repo->courses() );
		$this->assertCount( 3, $slots );
		$sunday = $slots['prova-karate|7|1'];
		$this->assertSame( 'Nome Fittizio', $sunday['teacher_label'] );
		$this->assertSame( 0, $sunday['teacher_id'], 'Il nome indicato nei dati non collega alcun account.' );
		$this->assertSame( 'Palestra A', $sunday['location'] );

		$again = $this->importer->apply( $this->importer->parse( self::CSV )['rows'], $this->segreteria, 'prova.csv' );
		$this->assertSame( 0, $again['plan']['counts']['courses_create'] );
		$this->assertSame( 0, $again['plan']['counts']['slots_create'] );
		$this->assertSame( 3, $again['plan']['counts']['slots_unchanged'] );
		$this->assertCount( 5, $this->repo->courses() );
		$this->assertCount( 3, $this->repo->slots_by_key() );
	}

	public function test_reimport_updates_imported_slots_but_never_overwrites_staff_changes(): void {
		$slots  = $this->import();
		$karate = $slots['prova-karate|1|1'];
		$judo   = $slots['prova-judo|2|1'];
		$this->assertSame(
			'slot_updated',
			$this->service->update_slot(
				$this->segreteria,
				$judo['id'],
				[
					'weekday'    => '2',
					'start_time' => '18:30',
					'end_time'   => '19:30',
					'teacher_id' => (string) $this->anna,
				]
			)['code']
		);
		$changed = str_replace( [ 'Lunedì;17:00;18:00;;;', 'Martedì;18:00;19:00' ], [ 'Lunedì;17:15;18:15;;;', 'Martedì;20:00;21:00' ], self::CSV );
		$result  = $this->importer->apply( $this->importer->parse( $changed )['rows'], $this->segreteria, 'prova2.csv' );
		$this->assertSame( 1, $result['plan']['counts']['slots_update'] );
		$this->assertSame( 1, $result['plan']['counts']['slots_conflict'] );
		$this->assertSame( '17:15:00', $this->repo->find_slot( $karate['id'] )['start_time'] );
		$kept = $this->repo->find_slot( $judo['id'] );
		$this->assertSame( '18:30:00', $kept['start_time'], 'La modifica della segreteria resta.' );
		$this->assertSame( $this->anna, $kept['teacher_id'] );
	}

	public function test_slots_missing_from_file_are_left_untouched(): void {
		$this->import();
		$without_judo = implode( "\n", array_filter( explode( "\n", self::CSV ), static fn( string $l ): bool => ! str_contains( $l, 'Judo' ) ) );
		$plan         = $this->importer->plan( $this->importer->parse( $without_judo )['rows'] );
		$this->assertSame( 0, $plan['counts']['untouched'], 'Il corso Judo non è nel file: i suoi orari non vengono considerati.' );
		$without_sunday = str_replace( "prova-karate;Arti marziali;Karate Prova;bambini;Domenica;17:00;18:00;Nome Fittizio;Palestra A;\n", '', self::CSV );
		$plan           = $this->importer->plan( $this->importer->parse( $without_sunday )['rows'] );
		$this->assertSame( 1, $plan['counts']['untouched'] );
		$this->importer->apply( $this->importer->parse( $without_sunday )['rows'], $this->segreteria, 'prova3.csv' );
		$this->assertSame( 'active', $this->repo->slots_by_key()['prova-karate|7|1']['status'], 'Nessuna cancellazione automatica.' );
	}

	public function test_parse_rejects_files_without_required_columns_and_reads_comma_csv(): void {
		$this->assertNotSame( [], $this->importer->parse( "nome,giorno\nKarate,Lunedì\n" )['errors'] );
		$comma = "chiave,sezione,corso,gruppo,giorno,inizio,fine,insegnante,sede,note\nprova-x,Sezione,Corso X,,lun,9.30,10.30,,,\n";
		$plan  = $this->importer->plan( $this->importer->parse( $comma )['rows'] );
		$this->assertSame( 1, $plan['counts']['slots_create'] );
		$this->assertSame( '09:30:00', $plan['slots'][0]['start_time'] );
		$this->assertSame( 1, CourseImporter::weekday( 'Lunedì' ) );
		$this->assertSame( 3, CourseImporter::weekday( 'mercoledi' ) );
		$this->assertSame( 0, CourseImporter::weekday( 'Mertedì' ), 'Un giorno scritto male non viene indovinato.' );
	}

	public function test_teacher_sees_only_own_lessons_and_records_attendance(): void {
		$slots  = $this->import();
		$sunday = $slots['prova-karate|7|1']['id'];
		$monday = $slots['prova-karate|1|1']['id'];
		$this->assign( $sunday, $this->anna );
		$this->assign( $monday, $this->bruno );

		$lessons = $this->service->lessons_for_teacher( $this->anna, '2026-10-12', '2026-10-25' );
		$this->assertSame( [ $sunday ], array_values( array_unique( array_column( $lessons, 'id' ) ) ) );
		$this->assertSame( [ '2026-10-18', '2026-10-25' ], array_column( $lessons, 'lesson_date' ) );

		$this->assertSame( 'attendance_saved', $this->service->record_attendance( $this->anna, $sunday, self::TODAY, 'present' )['code'] );
		$this->assertSame( 'present', $this->repo->find_attendance( $sunday, self::TODAY )['status'] );
		$this->assertSame( 'attendance_saved', $this->service->record_attendance( $this->anna, $sunday, self::TODAY, 'absent' )['code'], 'Nel giorno della lezione si può cambiare.' );
		$this->assertSame( 'unchanged', $this->service->record_attendance( $this->anna, $sunday, self::TODAY, 'absent' )['code'] );
		$this->assertSame( 'absent', $this->service->lessons_for_teacher( $this->anna, self::TODAY, self::TODAY )[0]['attendance_status'] );
	}

	public function test_attendance_date_rules(): void {
		$slots  = $this->import();
		$sunday = $slots['prova-karate|7|1']['id'];
		$this->assign( $sunday, $this->anna );
		$this->assertSame( 'too_early', $this->service->record_attendance( $this->anna, $sunday, '2026-10-25', 'present' )['code'] );
		$this->assertSame( 'not_a_lesson', $this->service->record_attendance( $this->anna, $sunday, '2026-10-17', 'present' )['code'], 'Sabato non c\'è questa lezione.' );
		$this->assertSame( 'before_assignment', $this->service->record_attendance( $this->anna, $sunday, '2026-10-11', 'present' )['code'], 'Assegnata oggi: le lezioni passate non sono sue.' );
		// Assegnazione di due mesi fa: le lezioni passate si registrano fino a 30 giorni.
		$this->repo->update( $this->repo->slots_table(), $sunday, [ 'assigned_from' => '2026-08-01' ] );
		$this->assertSame( 'too_old', $this->service->record_attendance( $this->anna, $sunday, '2026-08-16', 'present' )['code'] );
		$this->assertSame( 'attendance_saved', $this->service->record_attendance( $this->anna, $sunday, '2026-10-11', 'present' )['code'], 'Una lezione passata si può ancora registrare.' );
		$this->assertSame( 'attendance_locked', $this->service->record_attendance( $this->anna, $sunday, '2026-10-11', 'absent' )['code'] );
		$this->assertSame( 'invalid', $this->service->record_attendance( $this->anna, $sunday, '2026-13-40', 'present' )['code'] );
		$this->assertSame( 'invalid', $this->service->record_attendance( $this->anna, $sunday, self::TODAY, 'ritardo' )['code'] );
	}

	public function test_new_teacher_cannot_claim_or_overwrite_a_colleague_lessons(): void {
		$slots  = $this->import();
		$sunday = $slots['prova-karate|7|1']['id'];
		$this->assign( $sunday, $this->anna );
		$this->repo->update( $this->repo->slots_table(), $sunday, [ 'assigned_from' => '2026-09-01' ] );
		$this->assertSame( 'attendance_saved', $this->service->record_attendance( $this->anna, $sunday, self::TODAY, 'present' )['code'] );

		// La segreteria passa l'orario a Bruno.
		$this->assign( $sunday, $this->bruno );
		$this->assertSame( self::TODAY, $this->repo->find_slot( $sunday )['assigned_from'] );
		$this->assertSame( 'attendance_locked', $this->service->record_attendance( $this->bruno, $sunday, self::TODAY, 'absent' )['code'], 'La registrazione di Anna non si sovrascrive.' );
		$this->assertSame( 'present', $this->repo->find_attendance( $sunday, self::TODAY )['status'] );
		$this->assertSame( 'before_assignment', $this->service->record_attendance( $this->bruno, $sunday, '2026-10-11', 'present' )['code'], 'Le lezioni non registrate di Anna non diventano di Bruno.' );
		$this->assertSame( [ self::TODAY, '2026-10-25' ], array_column( $this->service->lessons_for_teacher( $this->bruno, '2026-10-04', '2026-10-25' ), 'lesson_date' ) );

		// Cambio di giorno: le domeniche passate non diventano martedì da registrare.
		$this->repo->update( $this->repo->slots_table(), $sunday, [ 'assigned_from' => '2026-09-01' ] );
		$current = $this->repo->find_slot( $sunday );
		$this->assertTrue(
			$this->service->update_slot(
				$this->segreteria,
				$sunday,
				[
					'weekday'    => '2',
					'start_time' => substr( (string) $current['start_time'], 0, 5 ),
					'end_time'   => substr( (string) $current['end_time'], 0, 5 ),
					'teacher_id' => (string) $this->bruno,
					'location'   => (string) $current['location'],
				]
			)['ok']
		);
		$this->assertSame( 'before_assignment', $this->service->record_attendance( $this->bruno, $sunday, '2026-10-13', 'present' )['code'] );
	}

	public function test_teacher_cannot_record_for_a_colleague_or_unassigned_slot(): void {
		$slots  = $this->import();
		$sunday = $slots['prova-karate|7|1']['id'];
		$this->assign( $sunday, $this->bruno );
		$this->assertSame( 'not_found', $this->service->record_attendance( $this->anna, $sunday, self::TODAY, 'present' )['code'] );
		$this->assertSame( 'not_found', $this->service->record_attendance( $this->anna, $slots['prova-judo|2|1']['id'], '2026-10-13', 'present' )['code'] );
		$this->assertNull( $this->repo->find_attendance( $sunday, self::TODAY ) );
		$this->assertSame( 'forbidden', $this->service->record_attendance( $this->segreteria, $sunday, self::TODAY, 'present' )['code'] );
		$this->assertSame( 'forbidden', $this->service->record_attendance( self::factory()->user->create( [ 'role' => Roles::SOCIO ] ), $sunday, self::TODAY, 'present' )['code'] );
	}

	public function test_only_staff_manages_slots_and_changes_are_kept_in_history(): void {
		$slots = $this->import();
		$judo  = $slots['prova-judo|2|1']['id'];
		foreach ( [ $this->anna, self::factory()->user->create( [ 'role' => Roles::SOCIO ] ), 0 ] as $actor ) {
			$this->assertSame( 'forbidden', $this->service->update_slot( $actor, $judo, [] )['code'] );
			$this->assertSame( 'forbidden', $this->service->cancel_slot( $actor, $judo )['code'] );
			$this->assertSame( 'forbidden', $this->service->add_slot( $actor, 1, [] )['code'] );
			$this->assertNull( $this->service->overview( $actor, self::TODAY, self::TODAY ) );
			$this->assertSame( [], $this->service->teachers( $actor ) );
		}
		$invalid = $this->service->update_slot(
			$this->segreteria,
			$judo,
			[
				'weekday'    => '9',
				'start_time' => '20:00',
				'end_time'   => '19:00',
				'teacher_id' => (string) $this->segreteria,
			]
		);
		$this->assertSame( [ 'weekday', 'end_time', 'teacher_id' ], array_keys( $invalid['errors'] ) );

		$this->assign( $judo, $this->anna );
		$this->assertSame( 'slot_cancelled_ok', $this->service->cancel_slot( $this->segreteria, $judo )['code'] );
		$slot = $this->repo->find_slot( $judo );
		$this->assertSame( 'cancelled', $slot['status'], 'Annullato, non eliminato.' );
		$this->assertSame( 1, $slot['manual'] );
		$this->assertSame( [ 'corsi.slot_cancelled', 'corsi.slot_updated' ], array_column( $this->service->history( $this->segreteria, $judo ), 'action' ) );
		$this->assertSame( [], $this->service->lessons_for_teacher( $this->anna, '2026-10-12', '2026-10-25' ) );
	}

	public function test_staff_adds_slot_to_course_without_times_and_assigns_teacher_to_whole_course(): void {
		$this->import();
		$aikido = null;
		foreach ( $this->repo->courses() as $course ) {
			if ( 'prova-aikido' === $course['ext_key'] ) {
				$aikido = $course;
			}
		}
		$this->assertNotNull( $aikido );
		$added = $this->service->add_slot(
			$this->segreteria,
			$aikido['id'],
			[
				'weekday'    => '2',
				'start_time' => '20:00',
				'end_time'   => '21:00',
			]
		);
		$this->assertSame( 'slot_created', $added['code'] );
		$this->assertNull( $this->repo->find_slot( $added['id'] )['ext_key'], 'Gli orari aggiunti a mano non sono toccati dall\'importazione.' );

		$slots = $this->repo->slots_by_key();
		$this->service->update_slot(
			$this->segreteria,
			$slots['prova-karate|1|1']['id'],
			[
				'weekday'      => '1',
				'start_time'   => '17:00',
				'end_time'     => '18:00',
				'teacher_id'   => (string) $this->bruno,
				'apply_course' => '1',
			]
		);
		$this->assertSame( $this->bruno, $this->repo->find_slot( $slots['prova-karate|7|1']['id'] )['teacher_id'] );
	}

	public function test_overview_lists_lessons_with_attendance_and_unassigned_filter(): void {
		$slots  = $this->import();
		$sunday = $slots['prova-karate|7|1']['id'];
		$this->assign( $sunday, $this->anna );
		$this->service->record_attendance( $this->anna, $sunday, self::TODAY, 'present' );
		$week = $this->service->overview( $this->segreteria, '2026-10-12', self::TODAY );
		$this->assertCount( 3, $week, 'Lunedì karate, martedì judo, domenica karate.' );
		$this->assertCount( 1, $this->service->overview( $this->segreteria, '2026-10-12', self::TODAY, [ 'attendance' => 'present' ] ) );
		$this->assertCount( 2, $this->service->overview( $this->segreteria, '2026-10-12', self::TODAY, [ 'teacher' => -1 ] ) );
		$this->assertCount( 1, $this->service->overview( $this->segreteria, '2026-10-12', self::TODAY, [ 'teacher' => $this->anna ] ) );
	}

	public function test_calendar_for_members_lists_active_slots_and_courses_without_times(): void {
		$this->import();
		$socio    = self::factory()->user->create( [ 'role' => Roles::SOCIO ] );
		$calendar = $this->service->calendar( $socio );
		$this->assertCount( 3, $calendar['slots'] );
		$this->assertSame( [ 'prova-aikido', 'prova-danza', 'prova-errore' ], array_column( $calendar['without_slots'], 'ext_key' ) );
		$this->assertNull( $this->service->calendar( self::factory()->user->create( [ 'role' => 'subscriber' ] ) ) );
	}

	public function test_roles_capabilities_and_no_admin_area_for_members_and_teachers(): void {
		foreach ( [ $this->anna, $this->segreteria, self::factory()->user->create( [ 'role' => Roles::SOCIO ] ) ] as $user ) {
			$this->assertTrue( AreaModule::is_portal_only( $user ) );
			$this->assertFalse( user_can( $user, 'manage_options' ) );
			$this->assertFalse( user_can( $user, 'bracelli_corsi_import' ) );
		}
		$admin = $this->user_with_role( 'administrator' );
		$this->assertTrue( user_can( $admin, 'bracelli_corsi_manage' ) );
		$this->assertTrue( user_can( $admin, 'bracelli_corsi_import' ) );
		$this->assertFalse( user_can( $admin, 'bracelli_corsi_teach' ), 'L\'amministratore non è un insegnante.' );
	}

	/* --------------------------------------------------------------
	 * Area riservata e shortcode
	 * -------------------------------------------------------------- */

	/**
	 * @param array<string, mixed> $post Campi POST (vuoto = GET).
	 * @param array<string, mixed> $get  Parametri GET.
	 * @return array{0: string, 1: string}
	 */
	private function portal( array $post = [], array $get = [] ): array {
		$this->capture_redirects();
		$_GET                      = $get;
		$_POST                     = $post;
		$_SERVER['REQUEST_METHOD'] = [] === $post ? 'GET' : 'POST';
		ob_start();
		try {
			( new Portal( $this->plugin ) )->serve();
		} catch ( RedirectException $e ) {
			ob_end_clean();
			return [ '', $e->getMessage() ];
		}
		return [ (string) ob_get_clean(), '' ];
	}

	public function test_login_page_has_no_public_registration(): void {
		wp_set_current_user( 0 );
		[ $html ] = $this->portal();
		$this->assertStringContainsString( '<h1 class="bcp-title">Accedi</h1>', $html );
		$this->assertStringContainsString( 'Nome utente o email', $html );
		$this->assertStringNotContainsStringIgnoringCase( 'registr', $html );
	}

	public function test_portal_teacher_attendance_requires_ownership_even_with_valid_nonce(): void {
		$slots  = $this->import();
		$today  = $this->service_now()->today();
		$day    = (int) gmdate( 'N', (int) strtotime( $today . ' 12:00:00 UTC' ) );
		$mine   = $this->service_now()->add_slot( $this->segreteria, $this->repo->courses()[0]['id'], [ 'weekday' => (string) $day, 'start_time' => '08:00', 'end_time' => '09:00', 'teacher_id' => (string) $this->anna ] )['id'];
		$theirs = $this->service_now()->add_slot( $this->segreteria, $this->repo->courses()[0]['id'], [ 'weekday' => (string) $day, 'start_time' => '10:00', 'end_time' => '11:00', 'teacher_id' => (string) $this->bruno ] )['id'];
		wp_set_current_user( $this->anna );

		[ $html ] = $this->portal( [], [ 'vista' => 'lezioni' ] );
		$this->assertStringContainsString( '08:00–09:00', $html );
		$this->assertStringNotContainsString( '10:00–11:00', $html );

		[ , $location ] = $this->portal(
			[
				'bc_action' => 'corsi_attendance',
				'bc_id'     => (string) $theirs,
				'data'      => $today,
				'presenza'  => 'present',
				'_bc_nonce' => wp_create_nonce( Portal::nonce_action( 'corsi_attendance', $theirs ) ),
			]
		);
		$this->assertStringContainsString( 'esito=not_found', $location );
		$this->assertNull( $this->repo->find_attendance( $theirs, $today ) );

		[ , $location ] = $this->portal(
			[
				'bc_action' => 'corsi_attendance',
				'bc_id'     => (string) $mine,
				'data'      => $today,
				'presenza'  => 'present',
				'_bc_nonce' => wp_create_nonce( Portal::nonce_action( 'corsi_attendance', $mine ) ),
			]
		);
		$this->assertStringContainsString( 'esito=attendance_saved', $location );
		$this->assertSame( 'present', $this->repo->find_attendance( $mine, $today )['status'] );
		unset( $slots );
	}

	public function test_portal_views_follow_roles(): void {
		$this->import();
		$socio = self::factory()->user->create( [ 'role' => Roles::SOCIO ] );
		wp_set_current_user( $socio );
		[ $html ] = $this->portal( [], [ 'vista' => 'presenze' ] );
		$this->assertStringContainsString( 'Calendario dei corsi', $html, 'Il socio non vede le viste della segreteria.' );
		$this->assertStringNotContainsString( 'Presenze insegnanti', $html );
		$this->assertStringContainsString( 'Karate Prova', $html );

		wp_set_current_user( $this->segreteria );
		[ $html ] = $this->portal();
		$this->assertStringContainsString( 'Presenze degli insegnanti', $html );
		$this->assertStringContainsString( 'Orari dei corsi', $html );
		$this->assertStringNotContainsString( 'Le mie lezioni', $html );
	}

	public function test_shortcode_renders_accessible_button_to_portal(): void {
		$html = do_shortcode( '[bracelli_area_riservata]' );
		$this->assertStringContainsString( 'class="bcar bcar--oro"', $html );
		$this->assertStringContainsString( 'href="' . esc_url( Portal::url() ) . '"', $html );
		$this->assertStringContainsString( '<span>Area riservata</span>', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertStringContainsString( 'bcar--scuro', do_shortcode( '[bracelli_area_riservata stile="scuro" testo="Accedi"]' ) );
		$this->assertStringContainsString( 'bcar--oro', do_shortcode( '[bracelli_area_riservata stile="<script>"]' ) );
		$this->assertStringNotContainsString( '<script>', do_shortcode( '[bracelli_area_riservata testo="<script>alert(1)</script>"]' ) );
	}

	public function test_real_course_file_matches_flyers_if_present(): void {
		$file = dirname( __DIR__, 2 ) . '/private/corsi/corsi-volantini-2026-09-16.csv';
		if ( ! is_readable( $file ) ) {
			$this->markTestSkipped( 'File dei corsi reali non presente (escluso dal repository).' );
		}
		$parsed = $this->importer->parse( (string) file_get_contents( $file ) );
		$this->assertSame( [], $parsed['errors'] );
		$plan = $this->importer->plan( $parsed['rows'] );
		$this->assertSame( 60, $plan['counts']['courses_create'] );
		$this->assertSame( 91, $plan['counts']['slots_create'] );
	}

	/**
	 * Servizio con la data reale (per le prove sul portale).
	 */
	private function service_now(): CourseService {
		return new CourseService( $this->repo, $this->plugin->audit );
	}

	/**
	 * @param int $slot    Orario.
	 * @param int $teacher Insegnante.
	 */
	private function assign( int $slot, int $teacher ): void {
		$current = $this->repo->find_slot( $slot );
		$result  = $this->service->update_slot(
			$this->segreteria,
			$slot,
			[
				'weekday'    => (string) $current['weekday'],
				'start_time' => substr( (string) $current['start_time'], 0, 5 ),
				'end_time'   => substr( (string) $current['end_time'], 0, 5 ),
				'teacher_id' => (string) $teacher,
				'location'   => (string) $current['location'],
			]
		);
		$this->assertTrue( $result['ok'], $result['code'] );
	}
}
