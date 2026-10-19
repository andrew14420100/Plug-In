<?php
/**
 * Archivio soci: validazione, permessi e conservazione dei record.
 *
 * @package BracelliClub
 */

namespace Bracelli\Club\Tests\integration;

use Bracelli\Club\Core\Permissions\Roles;
use Bracelli\Club\Modules\Soci\MemberRepository;
use Bracelli\Club\Modules\Soci\MemberService;

final class MembersTest extends TestCase {

	private MemberRepository $repo;
	private MemberService $service;
	private int $segreteria;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$this->repo       = new MemberRepository( $wpdb );
		$this->service    = new MemberService( $this->repo, $this->plugin->audit );
		$this->segreteria = $this->user_with_role( Roles::SEGRETERIA );
	}

	public function test_secretary_can_create_and_search_member(): void {
		$email = 'mario.rossi' . chr( 64 ) . 'example.test';
		$result = $this->service->save(
			$this->segreteria,
			0,
			[
				'first_name' => 'Mario',
				'last_name'  => 'Rossi',
				'email'      => $email,
				'phone'      => '3331234567',
				'birth_date' => '1990-02-10',
				'status'     => 'active',
			]
		);
		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'created', $result['code'] );
		$found = $this->repo->search( $email );
		$this->assertSame( 1, $found['total'] );
		$this->assertSame( 'Rossi', $found['items'][0]['last_name'] );
	}

	public function test_member_email_cannot_be_duplicated(): void {
		$duplicate_email = 'duplicato' . chr( 64 ) . 'example.test';
		$input = [
			'first_name' => 'Mario',
			'last_name'  => 'Rossi',
			'email'      => $duplicate_email,
		];
		$this->assertTrue( $this->service->save( $this->segreteria, 0, $input )['ok'] );
		$result = $this->service->save(
			$this->segreteria,
			0,
			[
				'first_name' => 'Luigi',
				'last_name'  => 'Bianchi',
				'email'      => $duplicate_email,
			]
		);
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'invalid', $result['code'] );
		$this->assertArrayHasKey( 'email', $result['errors'] );
	}

	public function test_non_secretary_cannot_create_members(): void {
		$user = self::factory()->user->create( [ 'role' => Roles::ISTRUTTORE ] );
		$result = $this->service->save(
			$user,
			0,
			[ 'first_name' => 'Test', 'last_name' => 'Insegnante' ]
		);
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'forbidden', $result['code'] );
	}

	public function test_archiving_preserves_member_record(): void {
		$created = $this->service->save(
			$this->segreteria,
			0,
			[ 'first_name' => 'Anna', 'last_name' => 'Verdi' ]
		);
		$this->assertTrue( $created['ok'] );
		$archived = $this->service->set_status( $this->segreteria, $created['id'], 'archived' );
		$this->assertTrue( $archived['ok'] );
		$this->assertSame( 'archived', $this->repo->find( $created['id'] )['status'] );
	}

	public function test_family_relations_and_member_history_are_recorded(): void {
		$guardian = $this->service->save(
			$this->segreteria,
			0,
			[
				'first_name' => 'Giulia',
				'last_name'  => 'Verdi',
				'email'      => 'giulia.verdi@example.test',
			]
		);
		$this->assertTrue( $guardian['ok'] );
		$member = $this->service->save(
			$this->segreteria,
			0,
			[
				'first_name'          => 'Luca',
				'last_name'           => 'Verdi',
				'birth_date'          => '2014-05-20',
				'status'              => 'active',
				'guardian_ids_present' => '1',
				'guardian_ids'        => [ $guardian['id'] ],
			]
		);
		$this->assertTrue( $member['ok'] );
		$this->assertSame( [ $guardian['id'] ], $this->repo->guardian_ids( $member['id'] ) );
		$this->assertSame( $member['id'], $this->repo->dependents( $guardian['id'] )[0]['id'] );
		$this->assertSame( $guardian['id'], $this->repo->guardians( $member['id'] )[0]['id'] );

		$history = $this->repo->history( $member['id'] );
		$this->assertNotEmpty( $history );
		$this->assertContains( 'created', array_column( $history, 'action' ) );

		// Non si possono creare cicli: un figlio non può diventare tutore del proprio tutore.
		$result = $this->service->save(
			$this->segreteria,
			$guardian['id'],
			[
				'first_name'          => 'Giulia',
				'last_name'           => 'Verdi',
				'email'               => 'giulia.verdi@example.test',
				'status'              => 'active',
				'guardian_ids_present' => '1',
				'guardian_ids'        => [ $member['id'] ],
			]
		);
		$this->assertFalse( $result['ok'] );
		$this->assertArrayHasKey( 'guardian_ids', $result['errors'] );
	}

	public function test_possible_duplicate_search_matches_name_and_birth_date(): void {
		$created = $this->service->save(
			$this->segreteria,
			0,
			[
				'first_name' => 'Sara',
				'last_name'  => 'Bianchi',
				'email'      => 'sara.bianchi@example.test',
				'birth_date' => '2004-07-14',
			]
		);
		$this->assertTrue( $created['ok'] );
		$matches = $this->repo->potential_duplicates( 'Sara', 'Bianchi', '2004-07-14' );
		$this->assertCount( 1, $matches );
		$this->assertSame( $created['id'], $matches[0]['id'] );
	}


}
