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
	 * @param array<string, string> $input Dati del modulo.
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	public function save( int $actor, int $id, array $input ): array {
		if ( ! $this->can_manage( $actor ) ) {
			return $this->result( false, 'forbidden' );
		}
		if ( $id > 0 && null === $this->repo->find( $id ) ) {
			return $this->result( false, 'not_found' );
		}
		$data = [
			'first_name' => sanitize_text_field( $input['first_name'] ?? '' ),
			'last_name'  => sanitize_text_field( $input['last_name'] ?? '' ),
			'email'      => sanitize_email( $input['email'] ?? '' ),
			'phone'      => sanitize_text_field( $input['phone'] ?? '' ),
			'birth_date' => $this->birth_date( $input['birth_date'] ?? '' ),
			'address'    => sanitize_text_field( $input['address'] ?? '' ),
			'city'       => sanitize_text_field( $input['city'] ?? '' ),
			'postal_code'=> sanitize_text_field( $input['postal_code'] ?? '' ),
			'status'     => in_array( $input['status'] ?? 'active', [ 'active', 'suspended' ], true ) ? $input['status'] : 'active',
			'notes'      => sanitize_textarea_field( $input['notes'] ?? '' ),
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
		if ( [] !== $errors ) {
			return [ 'ok' => false, 'code' => 'invalid', 'errors' => $errors, 'id' => $id ];
		}
		$saved = $this->repo->save( $data, $actor, $id );
		if ( $saved < 1 ) {
			return $this->result( false, 'db_error', $id );
		}
		$this->audit->log(
			$id > 0 ? 'member.updated' : 'member.created',
			[
				'module'        => 'soci',
				'resource_type' => 'member',
				'resource_id'   => (string) $saved,
				'diff'          => [ 'fields' => array_keys( $data ), 'status' => $data['status'] ],
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
		if ( null === $this->repo->find( $id ) ) {
			return $this->result( false, 'not_found', $id );
		}
		if ( ! $this->repo->set_status( $id, $status, $actor ) ) {
			return $this->result( false, 'db_error', $id );
		}
		$this->audit->log(
			'archived' === $status ? 'member.archived' : 'member.status_changed',
			[
				'module'        => 'soci',
				'resource_type' => 'member',
				'resource_id'   => (string) $id,
				'diff'          => [ 'status' => $status ],
				'actor_id'      => $actor,
			]
		);
		return $this->result( true, 'archived' === $status ? 'archived' : 'updated', $id );
	}

	/**
	 * @return array{ok: bool, code: string, errors: array<string, string>, id: int}
	 */
	private function result( bool $ok, string $code, int $id = 0 ): array {
		return [ 'ok' => $ok, 'code' => $code, 'errors' => [], 'id' => $id ];
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
