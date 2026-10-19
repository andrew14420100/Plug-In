<?php
/**
 * Archivio soci nell'area riservata.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Soci;

use Bracelli\Club\Modules\Area\Portal;
use Bracelli\Club\Modules\Area\SectionInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Interfaccia operativa per ricerca, creazione, modifica e archiviazione delle anagrafiche.
 */
final class SociSection implements SectionInterface {

	private Portal $portal;

	public function __construct( private MemberService $service, private MemberRepository $repo ) {
	}

	/** {@inheritDoc} */
	public function id(): string {
		return 'soci';
	}

	/** {@inheritDoc} */
	public function priority(): int {
		return 5;
	}

	/** {@inheritDoc} */
	public function views( int $user ): array {
		if ( ! $this->service->can_manage( $user ) ) {
			return [];
		}
		return [
			'soci'          => __( 'Archivio soci', 'bracelli-club' ),
			'nuovo-socio'   => __( 'Nuovo socio', 'bracelli-club' ),
			'modifica-socio' => __( 'Scheda socio', 'bracelli-club' ),
		];
	}

	/** {@inheritDoc} */
	public function tabs( int $user ): array {
		return $this->service->can_manage( $user ) ? [ 'soci' => __( 'Archivio soci', 'bracelli-club' ) ] : [];
	}

	/** {@inheritDoc} */
	public function tab_for( string $view ): string {
		return 'soci';
	}

	/** {@inheritDoc} */
	public function messages(): array {
		return [
			'created'   => [ 'success', __( 'Anagrafica creata.', 'bracelli-club' ) ],
			'updated'   => [ 'success', __( 'Anagrafica aggiornata.', 'bracelli-club' ) ],
			'archived'  => [ 'success', __( 'Socio archiviato. Il record resta nello storico.', 'bracelli-club' ) ],
			'not_found' => [ 'error', __( 'Socio non trovato.', 'bracelli-club' ) ],
			'duplicate' => [ 'error', __( 'Esiste già un socio con questa email.', 'bracelli-club' ) ],
		];
	}

	/** {@inheritDoc} */
	public function handle( Portal $portal, int $user, string $action, int $id ): void {
		$this->portal = $portal;
		if ( 'save' === $action ) {
			$input  = $portal->posted( [ 'first_name', 'last_name', 'email', 'phone', 'birth_date', 'address', 'city', 'postal_code', 'status', 'notes' ] );
			$result = $this->service->save( $user, $id, $input );
			if ( $result['ok'] ) {
				$portal->redirect( [ 'vista' => 'soci', 'esito' => $result['code'] ] );
			}
			$portal->keep_errors( $result, $input, $id > 0 ? 'modifica-socio' : 'nuovo-socio', $id > 0 ? [ 'vista' => 'modifica-socio', 'socio' => $id ] : [ 'vista' => 'nuovo-socio' ] );
			return;
		}
		if ( 'archive' === $action ) {
			$result = $this->service->set_status( $user, $id, 'archived' );
			$portal->redirect( [ 'vista' => 'soci', 'esito' => $result['code'] ] );
		}
		$portal->redirect( [ 'vista' => 'soci', 'esito' => 'invalid' ] );
	}

	/** {@inheritDoc} */
	public function render( Portal $portal, int $user, string $view ): void {
		$this->portal = $portal;
		if ( ! $this->service->can_manage( $user ) ) {
			status_header( 403 );
			echo '<p class="bcp-alert bcp-alert--error">' . esc_html__( 'Non hai i permessi per gestire l’archivio soci.', 'bracelli-club' ) . '</p>';
			return;
		}
		if ( 'nuovo-socio' === $view ) {
			$this->form( null );
			return;
		}
		if ( 'modifica-socio' === $view ) {
			$member = $this->repo->find( absint( $portal->query( 'socio' ) ) );
			if ( null === $member ) {
				echo '<div class="bcp-card"><p>' . esc_html__( 'Socio non trovato.', 'bracelli-club' ) . '</p><p><a class="bcp-button" href="' . esc_url( Portal::url( [ 'vista' => 'soci' ] ) ) . '">' . esc_html__( 'Torna all’archivio', 'bracelli-club' ) . '</a></p></div>';
				return;
			}
			$this->form( $member );
			return;
		}
		$this->list_view();
	}

	private function list_view(): void {
		$search = sanitize_text_field( $this->portal->query( 'q' ) );
		$status = in_array( $this->portal->query( 'stato' ), [ 'active', 'suspended', 'archived' ], true ) ? $this->portal->query( 'stato' ) : '';
		$page   = max( 1, absint( $this->portal->query( 'pagina' ) ) );
		$result = $this->repo->search( $search, $status, $page, 25 );
		echo '<p><a class="bcp-button bcp-button--primary" href="' . esc_url( Portal::url( [ 'vista' => 'nuovo-socio' ] ) ) . '">' . esc_html__( 'Aggiungi socio', 'bracelli-club' ) . '</a></p>';
		?>
<form class="bcp-card bcp-filters" method="get" action="<?php echo esc_url( Portal::url() ); ?>">
		<?php $this->portal->get_form_fields(); ?>
	<input type="hidden" name="vista" value="soci">
	<div class="bcp-field"><label for="bcp-soci-search"><?php esc_html_e( 'Cerca socio', 'bracelli-club' ); ?></label><input id="bcp-soci-search" type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Nome, cognome, email o telefono', 'bracelli-club' ); ?>"></div>
	<div class="bcp-field"><label for="bcp-soci-status"><?php esc_html_e( 'Stato', 'bracelli-club' ); ?></label><select id="bcp-soci-status" name="stato">
		<option value=""><?php esc_html_e( 'Tutti gli stati', 'bracelli-club' ); ?></option>
		<option value="active"<?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Attivo', 'bracelli-club' ); ?></option>
		<option value="suspended"<?php selected( $status, 'suspended' ); ?>><?php esc_html_e( 'Sospeso', 'bracelli-club' ); ?></option>
		<option value="archived"<?php selected( $status, 'archived' ); ?>><?php esc_html_e( 'Archiviato', 'bracelli-club' ); ?></option>
	</select></div>
	<button class="bcp-button" type="submit"><?php esc_html_e( 'Cerca', 'bracelli-club' ); ?></button>
</form>
		<?php
		echo '<p class="bcp-muted">' . esc_html( sprintf( __( '%s soci trovati', 'bracelli-club' ), number_format_i18n( $result['total'] ) ) ) . '</p>';
		if ( [] === $result['items'] ) {
			echo '<div class="bcp-card"><p>' . esc_html__( 'Nessun socio trovato con questi filtri.', 'bracelli-club' ) . '</p></div>';
			return;
		}
		echo '<div class="bcp-table-wrap"><table class="bcp-table"><thead><tr>';
		foreach ( [ __( 'Cognome e nome', 'bracelli-club' ), __( 'Contatti', 'bracelli-club' ), __( 'Stato', 'bracelli-club' ), __( 'Azioni', 'bracelli-club' ) ] as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $result['items'] as $member ) {
			$url = Portal::url( [ 'vista' => 'modifica-socio', 'socio' => (int) $member['id'] ] );
			echo '<tr><td data-label="' . esc_attr__( 'Cognome e nome', 'bracelli-club' ) . '"><strong>' . esc_html( $member['last_name'] . ' ' . $member['first_name'] ) . '</strong></td>';
			echo '<td data-label="' . esc_attr__( 'Contatti', 'bracelli-club' ) . '">' . esc_html( $member['email'] ) . ( '' !== $member['phone'] ? '<br>' . esc_html( $member['phone'] ) : '' ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Stato', 'bracelli-club' ) . '">' . esc_html( $this->status_label( (string) $member['status'] ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Azioni', 'bracelli-club' ) . '"><a class="bcp-button" href="' . esc_url( $url ) . '">' . esc_html__( 'Apri scheda', 'bracelli-club' ) . '</a></td></tr>';
		}
		echo '</tbody></table></div>';
		$pages = (int) ceil( $result['total'] / $result['per_page'] );
		if ( $pages > 1 ) {
			echo '<nav class="bcp-pagination" aria-label="' . esc_attr__( 'Pagine archivio soci', 'bracelli-club' ) . '">';
			for ( $n = 1; $n <= $pages; $n++ ) {
				$url = Portal::url( [ 'vista' => 'soci', 'q' => $search, 'stato' => $status, 'pagina' => $n ] );
				echo '<a class="bcp-button' . ( $n === $page ? ' bcp-button--primary' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $n === $page ? ' aria-current="page"' : '' ) . '>' . esc_html( (string) $n ) . '</a> ';
			}
			echo '</nav>';
		}
	}

	/**
	 * @param array<string, mixed>|null $member Record esistente o null.
	 */
	private function form( ?array $member ): void {
		$id     = (int) ( $member['id'] ?? 0 );
		$values = $this->portal->old() + [
			'first_name' => (string) ( $member['first_name'] ?? '' ),
			'last_name'  => (string) ( $member['last_name'] ?? '' ),
			'email'      => (string) ( $member['email'] ?? '' ),
			'phone'      => (string) ( $member['phone'] ?? '' ),
			'birth_date' => (string) ( $member['birth_date'] ?? '' ),
			'address'    => (string) ( $member['address'] ?? '' ),
			'city'       => (string) ( $member['city'] ?? '' ),
			'postal_code' => (string) ( $member['postal_code'] ?? '' ),
			'status'     => (string) ( $member['status'] ?? 'active' ),
			'notes'      => (string) ( $member['notes'] ?? '' ),
		];
		?>
<form class="bcp-card bcp-form" method="post" action="<?php echo esc_url( Portal::url() ); ?>">
		<?php $this->portal->action_fields( 'soci_save', $id ); ?>
		<?php $this->portal->form_errors(); ?>
	<div class="bcp-form-grid">
		<div class="bcp-field"><label for="bcp-first"><?php esc_html_e( 'Nome *', 'bracelli-club' ); ?></label><input id="bcp-first" name="first_name" required maxlength="100" value="<?php echo esc_attr( $values['first_name'] ); ?>"><?php $this->portal->field_error( 'first_name' ); ?></div>
		<div class="bcp-field"><label for="bcp-last"><?php esc_html_e( 'Cognome *', 'bracelli-club' ); ?></label><input id="bcp-last" name="last_name" required maxlength="100" value="<?php echo esc_attr( $values['last_name'] ); ?>"><?php $this->portal->field_error( 'last_name' ); ?></div>
		<div class="bcp-field"><label for="bcp-email"><?php esc_html_e( 'Email', 'bracelli-club' ); ?></label><input id="bcp-email" type="email" name="email" maxlength="190" value="<?php echo esc_attr( $values['email'] ); ?>"><?php $this->portal->field_error( 'email' ); ?></div>
		<div class="bcp-field"><label for="bcp-phone"><?php esc_html_e( 'Telefono', 'bracelli-club' ); ?></label><input id="bcp-phone" type="tel" name="phone" maxlength="40" value="<?php echo esc_attr( $values['phone'] ); ?>"><?php $this->portal->field_error( 'phone' ); ?></div>
		<div class="bcp-field"><label for="bcp-birth"><?php esc_html_e( 'Data di nascita', 'bracelli-club' ); ?></label><input id="bcp-birth" type="date" name="birth_date" value="<?php echo esc_attr( $values['birth_date'] ); ?>"><?php $this->portal->field_error( 'birth_date' ); ?></div>
		<div class="bcp-field"><label for="bcp-address"><?php esc_html_e( 'Indirizzo', 'bracelli-club' ); ?></label><input id="bcp-address" name="address" maxlength="190" value="<?php echo esc_attr( $values['address'] ); ?>"><?php $this->portal->field_error( 'address' ); ?></div>
		<div class="bcp-field"><label for="bcp-city"><?php esc_html_e( 'Comune', 'bracelli-club' ); ?></label><input id="bcp-city" name="city" maxlength="100" value="<?php echo esc_attr( $values['city'] ); ?>"><?php $this->portal->field_error( 'city' ); ?></div>
		<div class="bcp-field"><label for="bcp-postal"><?php esc_html_e( 'CAP', 'bracelli-club' ); ?></label><input id="bcp-postal" name="postal_code" maxlength="20" value="<?php echo esc_attr( $values['postal_code'] ); ?>"><?php $this->portal->field_error( 'postal_code' ); ?></div>
		<div class="bcp-field"><label for="bcp-status"><?php esc_html_e( 'Stato anagrafica', 'bracelli-club' ); ?></label><select id="bcp-status" name="status"><option value="active"<?php selected( $values['status'], 'active' ); ?>><?php esc_html_e( 'Attivo', 'bracelli-club' ); ?></option><option value="suspended"<?php selected( $values['status'], 'suspended' ); ?>><?php esc_html_e( 'Sospeso', 'bracelli-club' ); ?></option></select></div>
		<div class="bcp-field bcp-field--full"><label for="bcp-notes"><?php esc_html_e( 'Note interne', 'bracelli-club' ); ?></label><textarea id="bcp-notes" name="notes" rows="4" maxlength="4000"><?php echo esc_textarea( $values['notes'] ); ?></textarea><?php $this->portal->field_error( 'notes' ); ?></div>
	</div>
	<p><button class="bcp-button bcp-button--primary" type="submit"><?php esc_html_e( 'Salva anagrafica', 'bracelli-club' ); ?></button> <a class="bcp-button" href="<?php echo esc_url( Portal::url( [ 'vista' => 'soci' ] ) ); ?>"><?php esc_html_e( 'Annulla', 'bracelli-club' ); ?></a></p>
</form>
		<?php
		if ( $id > 0 && 'archived' !== (string) ( $member['status'] ?? '' ) ) {
			echo '<form class="bcp-card" method="post" action="' . esc_url( Portal::url() ) . '">';
			$this->portal->action_fields( 'soci_archive', $id );
			echo '<p>' . esc_html__( 'Se non è più attivo, archivia il socio. I dati restano nello storico e non vengono cancellati.', 'bracelli-club' ) . '</p>';
			echo '<button class="bcp-button" type="submit">' . esc_html__( 'Archivia socio', 'bracelli-club' ) . '</button></form>';
		}
	}

	private function status_label( string $status ): string {
		return match ( $status ) {
			'active'    => __( 'Attivo', 'bracelli-club' ),
			'suspended' => __( 'Sospeso', 'bracelli-club' ),
			'archived'  => __( 'Archiviato', 'bracelli-club' ),
			default     => __( 'Sconosciuto', 'bracelli-club' ),
		};
	}
}
