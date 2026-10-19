<?php
/**
 * Regole di business e autorizzazioni dell'archivio soci.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Soci;

use Bracelli\Club\Core\Audit\AuditLogger;
use Bracelli\Club\Core\Permissions\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Ogni operazione verifica i permessi sul server; i record vengono archiviati, non cancellati.
 */
final class MemberService {

	public function __construct( private MemberRepository $repo, private AuditLogger $audit ) {
	}

	public function can_manage( int $user ): bool {
		return user_can( $user, Capabilities::SOCI_MANAGE ) || user_can( $user, 'manage_options' );
	}

	/**
	 * @param array<string, mixed> $input Dati del modulo.
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public function save( int $actor, int $id, array $input ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return $this->result( false, 'forbidden' );
		}
		$existing = $id > 0 ? $this->repo->find( $id ) : null;
		if ( $id > 0 && null === $existing ) {
			return $this->result( false, 'not_found' );
		}
		$status = (string) ( $input['status'] ?? $existing['status'] ?? 'active' );
		$data   = [
			'first_name'  => sanitize_text_field( $input['first_name'] ?? '' ),
			'last_name'   => sanitize_text_field( $input['last_name'] ?? '' ),
			'email'       => sanitize_email( $input['email'] ?? '' ),
			'phone'       => sanitize_text_field( $input['phone'] ?? '' ),
			'birth_date'  => $this->birth_date( $input['birth_date'] ?? '' ),
			'address'     => sanitize_text_field( $input['address'] ?? '' ),
			'city'        => sanitize_text_field( $input['city'] ?? '' ),
			'postal_code' => sanitize_text_field( $input['postal_code'] ?? '' ),
			'status'      => in_array( $status, [ 'active', 'suspended', 'archived' ], true ) ? $status : (string) ( $existing['status'] ?? 'active' ),
			'notes'       => sanitize_textarea_field( $input['notes'] ?? '' ),
		];
		$errors = [];
		if ( '' === $data['first_name'] ) {
			$errors['first_name'] = __( 'Inserisci il nome.', 'bracelli-club' );
		}
		if ( '' === $data['last_name'] ) {
			$errors['last_name'] = __( 'Inserisci il cognome.', 'bracelli-club' );
		}
		if ( strlen( $data['first_name'] ) > 100 ) {
			$errors['first_name'] = __( 'Il nome è troppo lungo.', 'bracelli-club' );
		}
		if ( strlen( $data['last_name'] ) > 100 ) {
			$errors['last_name'] = __( 'Il cognome è troppo lungo.', 'bracelli-club' );
		}
		if ( strlen( $data['email'] ) > 190 ) {
			$errors['email'] = __( 'L’email è troppo lunga.', 'bracelli-club' );
		}
		if ( '' !== $data['email'] && ! is_email( $data['email'] ) ) {
			$errors['email'] = __( 'Inserisci un indirizzo email valido.', 'bracelli-club' );
		}
		if ( '' !== $data['email'] && $this->repo->email_exists( $data['email'], $id ) ) {
			$errors['email'] = __( 'Esiste già un socio con questo indirizzo email.', 'bracelli-club' );
		}
		if ( strlen( $data['address'] ) > 190 ) {
			$errors['address'] = __( 'L’indirizzo è troppo lungo.', 'bracelli-club' );
		}
		if ( strlen( $data['city'] ) > 100 ) {
			$errors['city'] = __( 'Il comune è troppo lungo.', 'bracelli-club' );
		}
		if ( strlen( $data['postal_code'] ) > 20 ) {
			$errors['postal_code'] = __( 'Il CAP è troppo lungo.', 'bracelli-club' );
		}
		if ( strlen( $data['phone'] ) > 40 ) {
			$errors['phone'] = __( 'Il numero di telefono è troppo lungo.', 'bracelli-club' );
		}
		if ( '' !== ( $input['birth_date'] ?? '' ) && null === $data['birth_date'] ) {
			$errors['birth_date'] = __( 'Inserisci una data di nascita valida.', 'bracelli-club' );
		}
		if ( strlen( $data['notes'] ) > 4000 ) {
			$errors['notes'] = __( 'Le note superano la lunghezza consentita.', 'bracelli-club' );
		}

		$guardian_ids_present = isset( $input['guardian_ids_present'] );
		$guardian_ids         = isset( $input['guardian_ids'] ) && is_array( $input['guardian_ids'] )
			? array_values( array_unique( array_filter( array_map( 'absint', $input['guardian_ids'] ) ) ) )
			: [];
		if ( $guardian_ids_present ) {
			if ( count( $guardian_ids ) > 10 ) {
				$errors['guardian_ids'] = __( 'Puoi collegare al massimo 10 genitori o tutori.', 'bracelli-club' );
			}
			$current_guardians = $id > 0 ? $this->repo->guardian_ids( $id ) : [];
			foreach ( $guardian_ids as $guardian_id ) {
				$guardian          = $this->repo->find( $guardian_id );
				$existing_relation = in_array( $guardian_id, $current_guardians, true );
				if (
					$guardian_id === $id
					|| null === $guardian
					|| ( 'active' !== $guardian['status'] && ! $existing_relation )
					|| ( $id > 0 && $this->repo->is_descendant( $guardian_id, $id ) )
				) {
					$errors['guardian_ids'] = __( 'Uno o più genitori/tutori non sono validi oppure creerebbero una relazione circolare.', 'bracelli-club' );
					break;
				}
			}
		}
		if ( [] !== $errors ) {
			return [
				'ok'     => false,
				'code'   => 'invalid',
				'errors' => $errors,
				'id'     => $id,
			];
		}
		$changed_fields = array_keys( $data );
		if ( is_array( $existing ) ) {
			$changed_fields = [];
			foreach ( $data as $field => $value ) {
				if ( (string) ( $existing[ $field ] ?? '' ) !== (string) ( $value ?? '' ) ) {
					$changed_fields[] = $field;
				}
			}
		}
		$old_guardians = $id > 0 ? $this->repo->guardian_ids( $id ) : [];
		$sorted_old    = $old_guardians;
		$sorted_new    = $guardian_ids;
		sort( $sorted_old );
		sort( $sorted_new );
		$guardians_changed = $guardian_ids_present && $sorted_old !== $sorted_new;
		if ( $id > 0 && [] === $changed_fields && ! $guardians_changed ) {
			return $this->result( true, 'unchanged', $id );
		}

		$saved = $this->repo->save( $data, $actor, $id );
		if ( $saved < 1 ) {
			return $this->result( false, 'db_error', $id );
		}
		if ( $guardian_ids_present && ! $this->repo->set_guardians( $saved, $guardian_ids, $actor ) ) {
			$this->audit->log(
				'member.guardians_update_failed',
				[
					'module'        => 'soci',
					'resource_type' => 'member',
					'resource_id'   => (string) $saved,
					'actor_id'      => $actor,
				]
			);
			return $this->result( false, 'db_error', $saved );
		}
		if ( $guardians_changed ) {
			$changed_fields[] = 'guardian_ids';
		}
		$changed_fields = array_values( array_unique( $changed_fields ) );
		$this->repo->record_history( $saved, $actor, $id > 0 ? 'updated' : 'created', $changed_fields );
		$this->audit->log(
			$id > 0 ? 'member.updated' : 'member.created',
			[
				'module'        => 'soci',
				'resource_type' => 'member',
				'resource_id'   => (string) $saved,
				'diff'          => [
					'fields'        => $changed_fields,
					'status_before' => $existing['status'] ?? null,
					'status_after'  => $data['status'],
				],
				'actor_id'      => $actor,
			]
		);
		return $this->result( true, $id > 0 ? 'updated' : 'created', $saved );
	}

	/**
	 * Archivia o riattiva un socio senza eliminare lo storico.
	 *
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public function set_status( int $actor, int $id, string $status ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return $this->result( false, 'forbidden', $id );
		}
		$existing = $this->repo->find( $id );
		if ( null === $existing ) {
			return $this->result( false, 'not_found', $id );
		}
		if ( ! in_array( $status, [ 'active', 'suspended', 'archived' ], true ) ) {
			return $this->result( false, 'invalid', $id );
		}
		if ( $existing['status'] === $status ) {
			return $this->result( true, 'unchanged', $id );
		}
		if ( ! $this->repo->set_status( $id, $status, $actor ) ) {
			return $this->result( false, 'db_error', $id );
		}
		$this->repo->record_history( $id, $actor, 'status_changed', [ 'status' ] );
		$this->audit->log(
			'archived' === $status ? 'member.archived' : 'member.status_changed',
			[
				'module'        => 'soci',
				'resource_type' => 'member',
				'resource_id'   => (string) $id,
				'diff'          => [
					'status_before' => $existing['status'],
					'status_after'  => $status,
				],
				'actor_id'      => $actor,
			]
		);
		$code = 'archived' === $status ? 'archived' : ( 'archived' === $existing['status'] && 'active' === $status ? 'restored' : 'updated' );
		return $this->result( true, $code, $id );
	}

	/**
	 * Registra l'esportazione CSV senza memorizzare valori anagrafici.
	 */
	public function record_export( int $actor, int $count ): void {
		if ( ! $this->can_manage( $actor ) ) {
			return;
		}
		$this->audit->log(
			'member.exported',
			[
				'module'        => 'soci',
				'resource_type' => 'member_export',
				'resource_id'   => 'csv',
				'diff'          => [
					'record_count' => max( 0, $count ),
				],
				'actor_id'      => $actor,
			]
		);
	}

	/**
	 * Storico e relazioni sono accessibili solo a chi gestisce l'archivio.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function history( int $actor, int $id ): array {
		if ( ! $this->can_manage( $actor ) || null === $this->repo->find( $id ) ) {
			return [];
		}
		return $this->repo->history( $id );
	}

	/**
	 * Genitori/tutori e soci collegati alla stessa anagrafica.
	 *
	 * @return array{guardians: array<int, array<string, mixed>>, dependents: array<int, array<string, mixed>>}|null
	 */
	public function family( int $actor, int $id ): ?array {
		if ( ! $this->can_manage( $actor ) || null === $this->repo->find( $id ) ) {
			return null;
		}
		return [
			'guardians'  => $this->repo->guardians( $id ),
			'dependents' => $this->repo->dependents( $id ),
		];
	}

	/**
	 * Candidate per la selezione dei genitori/tutori: soci attivi più collegamenti esistenti.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function candidate_guardians( int $actor, int $exclude_id = 0 ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return [];
		}
		$existing = $exclude_id > 0 ? $this->repo->guardian_ids( $exclude_id ) : [];
		$rows     = $this->repo->search( '', '', 1, 100 )['items'];
		return array_values(
			array_filter(
				$rows,
				static fn( array $row ): bool => (int) $row['id'] !== $exclude_id
					&& ( 'active' === $row['status'] || in_array( (int) $row['id'], $existing, true ) )
			)
		);
	}

	/**
	 * Possibili doppioni, senza includere il record in modifica.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function possible_duplicates( int $actor, string $first_name, string $last_name, string $birth_date = '', int $exclude_id = 0 ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return [];
		}
		return $this->repo->potential_duplicates( $first_name, $last_name, $birth_date, $exclude_id );
	}

	/**
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	private function result( bool $ok, string $code, int $id = 0 ): array {
		return [
			'ok'     => $ok,
			'code'   => $code,
			'errors' => [],
			'id'     => $id,
		];
	}

	private function birth_date( string $value ): ?string {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) ) {
			return null;
		}
		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ? $value : null;
	}
}
