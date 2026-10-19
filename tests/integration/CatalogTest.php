<?php
/**
 * Fase 1 del modulo Corsi: catalogo, sale, sovrapposizioni, ruolo "Gestore corsi" non più usato.
 * Solo dati fittizi.
 *
 * @package BracelliClub
 */

namespace Bracelli\Club\Tests\integration;

use Bracelli\Club\Core\Migrations\Core\M2026100008CreateCatalogAndRooms;
use Bracelli\Club\Core\Permissions\Capabilities;
use Bracelli\Club\Core\Permissions\Roles;
use Bracelli\Club\Modules\Area\Portal;
use Bracelli\Club\Modules\Corsi\CatalogService;
use Bracelli\Club\Modules\Corsi\CourseImporter;
use Bracelli\Club\Modules\Corsi\CourseRepository;
use Bracelli\Club\Modules\Corsi\CourseService;
use Bracelli\Club\Modules\Corsi\RoomService;
use Bracelli\Club\Modules\Permissions\PermissionsModule;

final class CatalogTest extends TestCase {

	private const TODAY = '2026-10-18';

	private const CSV = "chiave;sezione;corso;gruppo;giorno;inizio;fine;insegnante;sede;note\n"
		. "prova-karate;Arti marziali;Karate Prova;bambini;Lunedì;17:00;18:00;;Palestra vecchio testo;\n"
		. "prova-karate;Arti marziali;Karate Prova;bambini;Mercoledì;17:00;18:00;;;\n"
		. "prova-judo;Arti marziali;Judo Prova;;Lunedì;17:30;18:30;;;\n"
		. "prova-danza;Danza;Danza Prova;;Lunedì;18:00;19:00;;;\n";

	private CourseRepository $repo;

	private RoomService $rooms;

	private CourseService $service;

	private CatalogService $catalog;

	private CourseImporter $importer;

	private int $segreteria;

	private int $anna;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		add_filter( 'bracelli_club_corsi_import_transaction', '__return_false' );
		$today            = static fn(): string => self::TODAY;
		$this->repo       = new CourseRepository( $wpdb );
		$this->rooms      = new RoomService( $this->repo, $this->plugin->audit );
		$this->service    = new CourseService( $this->repo, $this->plugin->audit, $today, $this->rooms );
		$this->catalog    = new CatalogService( $this->repo, $this->plugin->audit, $this->rooms, $today );
		$this->importer   = new CourseImporter( $this->repo, $this->plugin->audit );
		$this->segreteria = $this->user_with_role( Roles::SEGRETERIA );
		$this->anna       = self::factory()->user->create( [ 'role' => Roles::ISTRUTTORE ] );
		$result           = $this->importer->apply( $this->importer->parse( self::CSV )['rows'], $this->segreteria, 'prova.csv' );
		$this->assertTrue( $result['ok'] );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function slots(): array {
		return $this->repo->slots_by_key();
	}

	private function course( string $key ): array {
		return $this->repo->courses_by_key()[ $key ];
	}

	private function room( string $name ): int {
		$result = $this->rooms->create( $this->segreteria, [ 'name' => $name ] );
		$this->assertTrue( $result['ok'], $result['code'] );
		return $result['id'];
	}

	/**
	 * @param array<string, mixed> $slot    Orario attuale.
	 * @param array<string, mixed> $changes Valori da cambiare.
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	private function edit_slot( array $slot, array $changes ): array {
		return $this->service->update_slot(
			$this->segreteria,
			(int) $slot['id'],
			$changes + [
				'weekday'    => (string) $slot['weekday'],
				'start_time' => substr( (string) $slot['start_time'], 0, 5 ),
				'end_time'   => substr( (string) $slot['end_time'], 0, 5 ),
				'teacher_id' => (string) $slot['teacher_id'],
				'room_id'    => (string) $slot['room_id'],
				'location'   => (string) $slot['location'],
			]
		);
	}

	/* --------------------------------------------------------------
	 * Dati esistenti
	 * -------------------------------------------------------------- */

	public function test_migration_is_additive_and_repeatable_without_touching_existing_data(): void {
		global $wpdb;
		$slot = $this->slots()['prova-karate|1|1'];
		$this->edit_slot( $slot, [ 'teacher_id' => (string) $this->anna ] );
		$wpdb->insert(
			$this->repo->attendance_table(),
			[
				'slot_id'     => $slot['id'],
				'lesson_date' => '2026-10-12',
				'teacher_id'  => $this->anna,
				'status'      => 'present',
				'start_time'  => '17:00:00',
				'end_time'    => '18:00:00',
				'recorded_at' => '2026-10-12 18:00:00',
			]
		);
		$snapshot = static fn(): array => [
			$wpdb->get_results( "SELECT id, ext_key, section, name, group_label, notes, status FROM {$wpdb->prefix}bc_corsi_courses ORDER BY id", ARRAY_A ),
			$wpdb->get_results( "SELECT id, ext_key, course_id, weekday, start_time, end_time, teacher_id, teacher_label, location, status, manual, assigned_from FROM {$wpdb->prefix}bc_corsi_slots ORDER BY id", ARRAY_A ),
			$wpdb->get_results( "SELECT * FROM {$wpdb->prefix}bc_corsi_attendance ORDER BY id", ARRAY_A ),
		];
		$before = $snapshot();
		( new M2026100008CreateCatalogAndRooms() )->up( $wpdb );
		( new M2026100008CreateCatalogAndRooms() )->up( $wpdb );
		$this->assertSame( $before, $snapshot(), 'Corsi, orari, modifiche manuali e presenze restano identici.' );
		$this->assertSame( 'Palestra vecchio testo', $this->slots()['prova-karate|1|1']['location'], 'Il vecchio testo "sede" resta.' );
		$this->assertSame( 0, $this->slots()['prova-karate|1|1']['room_id'], 'Nessuna sala assegnata in automatico.' );
		$this->assertSame( [], $this->repo->rooms(), 'Nessuna sala inventata.' );
	}

	/* --------------------------------------------------------------
	 * Sale
	 * -------------------------------------------------------------- */

	public function test_rooms_are_managed_only_by_segreteria_with_unique_names(): void {
		$id = $this->room( 'Palestra A' );
		$this->assertSame( 'invalid', $this->rooms->create( $this->segreteria, [ 'name' => '  palestra a ' ] )['code'] );
		$this->assertSame( 'invalid', $this->rooms->create( $this->segreteria, [ 'name' => '' ] )['code'] );
		$this->assertSame( 'forbidden', $this->rooms->create( $this->anna, [ 'name' => 'Sala B' ] )['code'] );
		$this->assertSame( 'forbidden', $this->rooms->create( self::factory()->user->create( [ 'role' => Roles::SOCIO ] ), [ 'name' => 'Sala B' ] )['code'] );
		$this->assertSame( 'forbidden', $this->rooms->create( self::factory()->user->create( [ 'role' => Roles::DIPENDENTE ] ), [ 'name' => 'Sala B' ] )['code'] );
		$this->assertSame( 'room_updated', $this->rooms->update( $this->segreteria, $id, [ 'name' => 'Palestra A1', 'notes' => 'piano terra' ] )['code'] );
		$this->assertSame( 'Palestra A1', $this->repo->find_room( $id )['name'] );
	}

	public function test_overlapping_slots_in_the_same_room_are_blocked_on_the_server(): void {
		$room   = $this->room( 'Palestra A' );
		$slots  = $this->slots();
		$karate = $slots['prova-karate|1|1'];
		$judo   = $slots['prova-judo|1|1'];
		$danza  = $slots['prova-danza|1|1'];
		$this->assertTrue( $this->edit_slot( $karate, [ 'room_id' => (string) $room ] )['ok'] );

		// Judo lunedì 17:30-18:30 si sovrappone a Karate 17:00-18:00.
		$result = $this->edit_slot( $judo, [ 'room_id' => (string) $room ] );
		$this->assertSame( 'room_conflict', $result['code'] );
		$this->assertStringContainsString( 'Karate Prova', $result['errors']['room_id'] );
		$this->assertSame( 0, $this->repo->find_slot( $judo['id'] )['room_id'], 'Nessuna modifica salvata.' );

		// Danza 18:00-19:00 tocca la fine di Karate ma non si sovrappone.
		$this->assertTrue( $this->edit_slot( $danza, [ 'room_id' => (string) $room ] )['ok'] );
		// Stessa fascia in un altro giorno: nessun conflitto.
		$this->assertTrue( $this->edit_slot( $slots['prova-karate|3|1'], [ 'room_id' => (string) $room ] )['ok'] );

		// Nuovo orario sovrapposto: rifiutato anche in aggiunta.
		$added = $this->service->add_slot(
			$this->segreteria,
			$this->course( 'prova-judo' )['id'],
			[
				'weekday'    => '1',
				'start_time' => '18:30',
				'end_time'   => '19:30',
				'room_id'    => (string) $room,
			]
		);
		$this->assertSame( 'room_conflict', $added['code'] );
		$this->assertCount( 1, $this->repo->slots( [ 'course_id' => $this->course( 'prova-judo' )['id'] ] ) );
	}

	public function test_cancelled_slots_and_archived_courses_free_the_room(): void {
		$room  = $this->room( 'Palestra A' );
		$slots = $this->slots();
		$this->assertTrue( $this->edit_slot( $slots['prova-karate|1|1'], [ 'room_id' => (string) $room ] )['ok'] );
		$this->assertSame( 'room_conflict', $this->edit_slot( $slots['prova-judo|1|1'], [ 'room_id' => (string) $room ] )['code'] );

		$this->assertTrue( $this->catalog->set_archived( $this->segreteria, $this->course( 'prova-karate' )['id'], true )['ok'] );
		$this->assertTrue( $this->edit_slot( $slots['prova-judo|1|1'], [ 'room_id' => (string) $room ] )['ok'], 'Un corso archiviato non occupa la sala.' );

		// Il ripristino che occuperebbe una sala ormai usata viene rifiutato e il corso resta archiviato.
		$restore = $this->catalog->set_archived( $this->segreteria, $this->course( 'prova-karate' )['id'], false );
		$this->assertSame( 'room_conflict', $restore['code'] );
		$this->assertSame( 'archived', $this->course( 'prova-karate' )['status'] );

		$this->assertTrue( $this->service->cancel_slot( $this->segreteria, $slots['prova-judo|1|1']['id'] )['ok'] );
		$this->assertTrue( $this->catalog->set_archived( $this->segreteria, $this->course( 'prova-karate' )['id'], false )['ok'], 'Un orario annullato non occupa la sala.' );
	}

	public function test_assign_room_to_whole_course_is_all_or_nothing(): void {
		$room  = $this->room( 'Palestra A' );
		$other = $this->room( 'Sala B' );
		$slots = $this->slots();
		$this->assertTrue( $this->edit_slot( $slots['prova-judo|1|1'], [ 'room_id' => (string) $room ] )['ok'] );

		$karate = $this->course( 'prova-karate' )['id'];
		$result = $this->catalog->assign_room( $this->segreteria, $karate, $room, false );
		$this->assertSame( 'room_conflict', $result['code'], 'Lunedì si sovrappone al Judo.' );
		foreach ( $this->repo->slots( [ 'course_id' => $karate ] ) as $slot ) {
			$this->assertSame( 0, $slot['room_id'], 'Nessun orario cambiato, nemmeno quello del mercoledì.' );
		}

		$this->assertTrue( $this->catalog->assign_room( $this->segreteria, $karate, $other, false )['ok'] );
		$this->edit_slot( $this->slots()['prova-karate|3|1'], [ 'room_id' => '0' ] );
		$this->assertTrue( $this->catalog->assign_room( $this->segreteria, $karate, $room, true )['ok'], 'Solo gli orari senza sala.' );
		$rooms = array_column( $this->repo->slots( [ 'course_id' => $karate ] ), 'room_id', 'weekday' );
		$this->assertSame( $other, $rooms[1] );
		$this->assertSame( $room, $rooms[3] );
		$this->assertSame( 'forbidden', $this->catalog->assign_room( $this->anna, $karate, $room, false )['code'] );
	}

	public function test_room_in_use_cannot_be_archived_and_archived_room_is_not_selectable(): void {
		$room = $this->room( 'Palestra A' );
		$this->edit_slot( $this->slots()['prova-karate|1|1'], [ 'room_id' => (string) $room ] );
		$this->assertSame( 'room_in_use', $this->rooms->set_archived( $this->segreteria, $room, true )['code'] );
		$this->edit_slot( $this->slots()['prova-karate|1|1'], [ 'room_id' => '0' ] );
		$this->assertSame( 'room_archived', $this->rooms->set_archived( $this->segreteria, $room, true )['code'] );
		$this->assertSame( 'invalid', $this->edit_slot( $this->slots()['prova-karate|1|1'], [ 'room_id' => (string) $room ] )['code'] );
		$this->assertArrayNotHasKey( $room, $this->rooms->choices() );
		$this->assertSame( 'room_restored', $this->rooms->set_archived( $this->segreteria, $room, false )['code'] );
	}

	/* --------------------------------------------------------------
	 * Catalogo
	 * -------------------------------------------------------------- */

	public function test_create_and_update_course_with_history_and_manual_flag(): void {
		$created = $this->catalog->create(
			$this->segreteria,
			[
				'name'        => 'Corso Fittizio',
				'section'     => 'Danza',
				'level'       => 'principianti',
				'description' => 'Descrizione di prova',
				'color'       => '#2f6fd6',
			]
		);
		$this->assertTrue( $created['ok'] );
		$course = $this->repo->find_course( $created['id'] );
		$this->assertStringStartsWith( CatalogService::MANUAL_PREFIX, $course['ext_key'] );
		$this->assertSame( 1, $course['manual'] );
		$this->assertSame( 'invalid', $this->catalog->create( $this->segreteria, [ 'name' => '', 'section' => '' ] )['code'] );
		$this->assertSame( 'invalid', $this->catalog->create( $this->segreteria, [ 'name' => 'X', 'section' => 'Y', 'color' => 'red;background:url(x)' ] )['code'] );
		$this->assertSame( 'forbidden', $this->catalog->create( $this->anna, [ 'name' => 'X', 'section' => 'Y' ] )['code'] );

		$karate = $this->course( 'prova-karate' );
		$this->assertSame( 0, $karate['manual'] );
		$update = $this->catalog->update( $this->segreteria, $karate['id'], [ 'name' => 'Karate Prova', 'section' => 'Arti marziali', 'group_label' => 'bambini 6-9', 'notes' => '' ] );
		$this->assertSame( 'course_updated', $update['code'] );
		$this->assertSame( 1, $this->course( 'prova-karate' )['manual'] );
		$event = $this->plugin->audit->query( [ 'action' => 'corsi.course_updated' ] )['items'][0];
		$this->assertSame( [ 'group_label' => 'bambini' ], $event['diff']['before'] );
		$this->assertSame( [ 'group_label' => 'bambini 6-9' ], $event['diff']['after'] );
		$this->assertCount( 1, $this->catalog->history( $this->segreteria, $karate['id'] ) );
	}

	public function test_import_never_overwrites_manual_or_archived_courses_nor_creates_duplicates(): void {
		$karate = $this->course( 'prova-karate' );
		$judo   = $this->course( 'prova-judo' );
		$this->catalog->update( $this->segreteria, $karate['id'], [ 'name' => 'Karate (nome corretto)', 'section' => 'Arti marziali', 'group_label' => 'bambini' ] );
		$this->catalog->set_archived( $this->segreteria, $judo['id'], true );
		$courses = count( $this->repo->courses() );
		$slots   = count( $this->repo->slots() );

		$plan = $this->importer->plan( $this->importer->parse( self::CSV )['rows'] );
		$this->assertSame( 2, $plan['counts']['courses_conflict'] );
		$this->assertTrue( $this->importer->apply( $this->importer->parse( self::CSV )['rows'], $this->segreteria )['ok'] );
		$this->assertSame( 'Karate (nome corretto)', $this->course( 'prova-karate' )['name'] );
		$this->assertSame( 'archived', $this->course( 'prova-judo' )['status'], 'L\'importazione non riattiva un corso archiviato.' );
		$this->assertCount( $courses, $this->repo->courses() );
		$this->assertCount( $slots, $this->repo->slots() );

		$reserved = $this->importer->plan( $this->importer->parse( "chiave;sezione;corso;gruppo;giorno;inizio;fine\nmanuale-x;A;B;;Lunedì;10:00;11:00\n" )['rows'] );
		$this->assertSame( 0, $reserved['counts']['courses_create'] );
		$this->assertSame( 1, $reserved['counts']['anomalies'] );
	}

	public function test_archived_course_disappears_from_calendar_and_lessons_but_keeps_data(): void {
		$slot = $this->slots()['prova-karate|1|1'];
		$this->edit_slot( $slot, [ 'teacher_id' => (string) $this->anna ] );
		$this->repo->update( $this->repo->slots_table(), $slot['id'], [ 'assigned_from' => '2026-09-01' ] );
		$this->assertTrue( $this->service->record_attendance( $this->anna, $slot['id'], '2026-10-12', 'present' )['ok'] );
		$this->assertTrue( $this->catalog->set_archived( $this->segreteria, $this->course( 'prova-karate' )['id'], true )['ok'] );

		$names = array_column( $this->service->calendar( $this->segreteria )['slots'], 'course_name' );
		$this->assertNotContains( 'Karate Prova', $names );
		$this->assertSame( [], $this->service->lessons_for_teacher( $this->anna, '2026-10-12', '2026-10-25' ) );
		$this->assertSame( 'present', $this->repo->find_attendance( $slot['id'], '2026-10-12' )['status'], 'Le presenze restano.' );
		$this->assertSame( 'active', $this->repo->find_slot( $slot['id'] )['status'], 'Gli orari restano.' );
		$this->assertCount( 1, $this->catalog->list( $this->segreteria, '', 'archived' ) );
	}

	public function test_duplicate_creates_draft_slots_that_need_confirmation(): void {
		$room = $this->room( 'Palestra A' );
		$this->catalog->assign_room( $this->segreteria, $this->course( 'prova-karate' )['id'], $room, false );
		$karate = $this->course( 'prova-karate' );
		$dup    = $this->catalog->duplicate(
			$this->segreteria,
			$karate['id'],
			[
				'name'        => 'Karate Prova',
				'section'     => 'Arti marziali',
				'group_label' => 'ragazzi',
				'copy_slots'  => '1',
			]
		);
		$this->assertSame( 'course_duplicated', $dup['code'] );
		$drafts = $this->repo->slots( [ 'course_id' => $dup['id'] ] );
		$this->assertCount( 2, $drafts );
		foreach ( $drafts as $draft ) {
			$this->assertSame( 'draft', $draft['status'] );
			$this->assertSame( 0, $draft['teacher_id'], 'L\'insegnante non viene copiato.' );
			$this->assertSame( $room, $draft['room_id'] );
		}
		$names = array_column( $this->service->calendar( $this->segreteria )['slots'], 'group_label' );
		$this->assertNotContains( 'ragazzi', $names, 'Gli orari da confermare non sono nel calendario.' );

		$confirm = $this->catalog->confirm_slot( $this->segreteria, $drafts[0]['id'] );
		$this->assertSame( 'room_conflict', $confirm['code'], 'Stessa sala e stessa fascia del corso di partenza.' );
		$this->assertSame( 'draft', $this->repo->find_slot( $drafts[0]['id'] )['status'] );

		$moved = $this->edit_slot( $this->repo->find_slot( $drafts[0]['id'] ), [ 'start_time' => '19:00', 'end_time' => '20:00' ] );
		$this->assertTrue( $moved['ok'], 'Un orario da confermare si può correggere.' );
		$this->assertSame( 'slot_confirmed', $this->catalog->confirm_slot( $this->segreteria, $drafts[0]['id'] )['code'] );
		$this->assertSame( 'active', $this->repo->find_slot( $drafts[0]['id'] )['status'] );
		$this->assertSame( 'not_draft', $this->catalog->confirm_slot( $this->segreteria, $drafts[0]['id'] )['code'] );
	}

	public function test_search_and_filters(): void {
		$this->assertSame( [ 'Judo Prova' ], array_column( $this->catalog->list( $this->segreteria, '', 'active', 'judo' ), 'name' ) );
		$this->assertSame( [ 'Danza Prova' ], array_column( $this->catalog->list( $this->segreteria, 'Danza' ), 'name' ) );
		$this->assertCount( 0, $this->catalog->list( $this->segreteria, '', 'archived' ) );
		$this->assertNull( $this->catalog->list( $this->anna ) );
	}

	/* --------------------------------------------------------------
	 * Area riservata
	 * -------------------------------------------------------------- */

	/**
	 * @param array<string, mixed> $post Campi POST.
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

	public function test_portal_catalog_actions_respect_roles_even_with_valid_nonce(): void {
		$karate = $this->course( 'prova-karate' )['id'];
		wp_set_current_user( $this->anna );
		[ , $location ] = $this->portal(
			[
				'bc_action' => 'catalogo_archive',
				'bc_id'     => (string) $karate,
				'_bc_nonce' => wp_create_nonce( Portal::nonce_action( 'catalogo_archive', $karate ) ),
			]
		);
		$this->assertStringContainsString( 'esito=forbidden', $location );
		$this->assertSame( 'active', $this->course( 'prova-karate' )['status'] );
		[ $html ] = $this->portal( [], [ 'vista' => 'sale' ] );
		$this->assertStringNotContainsString( 'Nuova sala', $html, 'L\'insegnante non vede le sale da gestire.' );

		wp_set_current_user( $this->segreteria );
		[ , $location ] = $this->portal(
			[
				'bc_action' => 'catalogo_roomcreate',
				'bc_id'     => '0',
				'nome'      => 'Palestra <b>A</b>',
				'_bc_nonce' => wp_create_nonce( Portal::nonce_action( 'catalogo_roomcreate', 0 ) ),
			]
		);
		$this->assertStringContainsString( 'esito=room_created', $location );
		[ $html ] = $this->portal( [], [ 'vista' => 'sale' ] );
		$this->assertStringContainsString( 'Palestra A', $html );
		$this->assertStringNotContainsString( '<b>A</b>', $html );

		[ $html ] = $this->portal( [], [ 'vista' => 'orari', 'q' => 'karate' ] );
		$this->assertStringContainsString( 'Karate Prova', $html );
		$this->assertStringNotContainsString( 'Judo Prova', $html );
		$this->assertStringContainsString( 'Sede: Palestra vecchio testo (sala da assegnare)', $html );
	}

	/* --------------------------------------------------------------
	 * Ruoli
	 * -------------------------------------------------------------- */

	public function test_dipendente_has_no_course_permissions(): void {
		$role = get_role( Roles::DIPENDENTE );
		foreach ( [ Capabilities::CORSI_VIEW, Capabilities::CORSI_TEACH, Capabilities::CORSI_MANAGE, Capabilities::CORSI_IMPORT ] as $cap ) {
			$this->assertFalse( $role->has_cap( $cap ), $cap );
		}
	}

	public function test_legacy_gestore_role_is_not_created_but_existing_users_keep_their_permissions(): void {
		remove_role( Roles::GESTORE_CORSI );
		Roles::sync();
		$this->assertNull( get_role( Roles::GESTORE_CORSI ), 'Non si crea più sulle nuove installazioni.' );

		// Sito esistente: il ruolo c'è e un utente lo ha.
		add_role( Roles::GESTORE_CORSI, 'Gestore corsi Bracelli', [ 'read' => true, Capabilities::DASHBOARD_VIEW => true, Capabilities::CORSI_VIEW => true, Capabilities::CORSI_MANAGE => true ] );
		$gestore = self::factory()->user->create( [ 'role' => Roles::GESTORE_CORSI ] );
		Roles::sync();
		$this->plugin->run_migrations();
		$this->assertTrue( user_can( $gestore, Capabilities::CORSI_MANAGE ), 'I permessi esistenti restano.' );
		$this->assertTrue( $this->catalog->can_manage( $gestore ) );

		// Non assegnabile a nuovi utenti, ma resta nel profilo di chi lo ha già.
		$_REQUEST = [];
		$this->assertArrayNotHasKey( Roles::GESTORE_CORSI, Roles::filter_editable( [ Roles::GESTORE_CORSI => [], Roles::SEGRETERIA => [] ] ) );
		$_REQUEST = [ 'user_id' => (string) $gestore ];
		$this->assertArrayHasKey( Roles::GESTORE_CORSI, Roles::filter_editable( [ Roles::GESTORE_CORSI => [], Roles::SEGRETERIA => [] ] ) );
		$_REQUEST = [ 'user_id' => (string) $this->segreteria ];
		$this->assertArrayNotHasKey( Roles::GESTORE_CORSI, Roles::filter_editable( [ Roles::GESTORE_CORSI => [], Roles::SEGRETERIA => [] ] ) );
		$_REQUEST = [];
	}

	public function test_legacy_conversion_requires_explicit_confirmation_and_changes_one_user(): void {
		add_role( Roles::GESTORE_CORSI, 'Gestore corsi Bracelli', [ 'read' => true, Capabilities::CORSI_VIEW => true, Capabilities::CORSI_MANAGE => true ] );
		$first  = self::factory()->user->create( [ 'role' => Roles::GESTORE_CORSI ] );
		$second = self::factory()->user->create( [ 'role' => Roles::GESTORE_CORSI ] );
		$this->login_as( 'administrator' );

		$location = $this->admin_post( PermissionsModule::ACTION_CONVERT, PermissionsModule::ACTION_CONVERT . '_' . $first, [ 'user_id' => (string) $first ] );
		$this->assertStringContainsString( 'bc_notice=confirm', $location );
		$this->assertContains( Roles::GESTORE_CORSI, get_userdata( $first )->roles, 'Senza conferma non cambia nulla.' );

		$location = $this->admin_post(
			PermissionsModule::ACTION_CONVERT,
			PermissionsModule::ACTION_CONVERT . '_' . $first,
			[
				'user_id' => (string) $first,
				'confirm' => '1',
			]
		);
		$this->assertStringContainsString( 'bc_notice=converted', $location );
		$this->assertSame( [ Roles::SEGRETERIA ], array_values( get_userdata( $first )->roles ) );
		$this->assertContains( Roles::GESTORE_CORSI, get_userdata( $second )->roles, 'L\'altro utente resta com\'era.' );
		$this->assertSame( 1, $this->audit_count( 'roles.legacy_converted' ) );

		$location = $this->admin_post( PermissionsModule::ACTION_DROP_LEGACY, PermissionsModule::ACTION_DROP_LEGACY, [ 'confirm' => '1' ] );
		$this->assertStringContainsString( 'bc_notice=failed', $location, 'Il ruolo non si elimina finché qualcuno lo ha.' );
		$this->assertNotNull( get_role( Roles::GESTORE_CORSI ) );

		$this->login_as( Roles::SEGRETERIA );
		$this->expectException( \WPDieException::class );
		$this->admin_post(
			PermissionsModule::ACTION_CONVERT,
			PermissionsModule::ACTION_CONVERT . '_' . $second,
			[
				'user_id' => (string) $second,
				'confirm' => '1',
			]
		);
	}

	public function test_permissions_page_works_without_the_legacy_role(): void {
		remove_role( Roles::GESTORE_CORSI );
		$this->login_as( 'administrator' );
		$location = $this->admin_post( PermissionsModule::ACTION_SAVE, PermissionsModule::ACTION_SAVE, [ 'caps' => [] ] );
		$this->assertStringContainsString( 'bc_notice=', $location );
		$this->assertNull( get_role( Roles::GESTORE_CORSI ) );
	}
}
