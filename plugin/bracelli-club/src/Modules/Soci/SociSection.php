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
			'soci'           => __( 'Archivio soci', 'bracelli-club' ),
			'nuovo-socio'    => __( 'Nuovo socio', 'bracelli-club' ),
			'modifica-socio' => __( 'Scheda socio', 'bracelli-club' ),
			'importa-soci'   => __( 'Importa soci da CSV', 'bracelli-club' ),
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
			'restored'  => [ 'success', __( 'Socio riattivato.', 'bracelli-club' ) ],
			'unchanged' => [ 'info', __( 'Nessuna modifica da salvare.', 'bracelli-club' ) ],
			'not_found' => [ 'error', __( 'Socio non trovato.', 'bracelli-club' ) ],
			'duplicate'         => [ 'error', __( 'Esiste già un socio con questa email.', 'bracelli-club' ) ],
			'import_preview'    => [ 'info', __( 'Anteprima CSV pronta: i dati non sono ancora stati salvati.', 'bracelli-club' ) ],
			'import_invalid'    => [ 'error', __( 'Il file presenta errori: correggili e caricalo di nuovo. Nessuna riga è stata importata.', 'bracelli-club' ) ],
			'import_file_error' => [ 'error', __( 'Il file non è stato caricato correttamente. Seleziona un CSV valido fino a 1 MB.', 'bracelli-club' ) ],
			'import_expired'    => [ 'error', __( 'L’anteprima è scaduta o non è valida. Carica nuovamente il file.', 'bracelli-club' ) ],
			'import_applied'    => [ 'success', __( 'Importazione CSV completata.', 'bracelli-club' ) ],
			'import_partial'    => [ 'warning', __( 'Importazione terminata con alcune righe non salvate: controlla il rapporto.', 'bracelli-club' ) ],
			'import_discarded'  => [ 'info', __( 'Anteprima importazione scartata.', 'bracelli-club' ) ],
		];
	}

	/** {@inheritDoc} */
	public function handle( Portal $portal, int $user, string $action, int $id ): void {
		$this->portal = $portal;
		if ( ! $this->service->can_manage( $user ) ) {
			$portal->redirect(
				[
					'vista' => 'soci',
					'esito' => 'forbidden',
				]
			);
		}
		if ( 'export' === $action ) {
			$this->download_csv( $user, false );
		}
		if ( 'template' === $action ) {
			$this->download_csv( $user, true );
		}
		if ( 'import_preview' === $action ) {
			$preview = $this->create_import_preview( $user );
			set_transient( $this->import_key( $user ), $preview, 30 * MINUTE_IN_SECONDS );
			delete_transient( $this->report_key( $user ) );
			$esito = ! empty( $preview['fatal_errors'] ) ? 'import_file_error' : ( 0 === (int) $preview['error_count'] ? 'import_preview' : 'import_invalid' );
			$portal->redirect(
				[
					'vista' => 'importa-soci',
					'esito' => $esito,
				]
			);
		}
		if ( 'import_apply' === $action ) {
			$this->apply_import( $portal, $user );
		}
		if ( 'import_discard' === $action ) {
			delete_transient( $this->import_key( $user ) );
			delete_transient( $this->report_key( $user ) );
			$portal->redirect(
				[
					'vista' => 'soci',
					'esito' => 'import_discarded',
				]
			);
		}
		if ( 'save' === $action ) {
			$input      = $portal->posted( [ 'first_name', 'last_name', 'email', 'phone', 'birth_date', 'address', 'city', 'postal_code', 'status', 'notes' ] );
			$save_input = $input;
			$save_input['guardian_ids']         = $this->posted_guardian_ids();
			$save_input['guardian_ids_present'] = '1';
			$result                             = $this->service->save( $user, $id, $save_input );
			if ( $result['ok'] ) {
				$portal->redirect(
					[
						'vista' => 'soci',
						'esito' => $result['code'],
					]
				);
			}
			$portal->keep_errors(
				$result,
				$input,
				$id > 0 ? 'modifica-socio' : 'nuovo-socio',
				$id > 0
					? [
						'vista' => 'modifica-socio',
						'socio' => $id,
					]
					: [
						'vista' => 'nuovo-socio',
					]
			);
			return;
		}
		if ( 'archive' === $action || 'restore' === $action ) {
			$status = 'archive' === $action ? 'archived' : 'active';
			$result = $this->service->set_status( $user, $id, $status );
			$portal->redirect(
				[
					'vista' => 'soci',
					'esito' => $result['code'],
				]
			);
		}
		$portal->redirect(
			[
				'vista' => 'soci',
				'esito' => 'invalid',
			]
		);
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
			$this->form( null, $user );
			return;
		}
		if ( 'importa-soci' === $view ) {
			$this->render_import_view( $user );
			return;
		}
		if ( 'modifica-socio' === $view ) {
			$member = $this->repo->find( absint( $portal->query( 'socio' ) ) );
			if ( null === $member ) {
				echo '<div class="bcp-card"><p>' . esc_html__( 'Socio non trovato.', 'bracelli-club' ) . '</p><p><a class="bcp-button" href="' . esc_url( Portal::url( [ 'vista' => 'soci' ] ) ) . '">' . esc_html__( 'Torna all’archivio', 'bracelli-club' ) . '</a></p></div>';
				return;
			}
			$this->form( $member, $user );
			return;
		}
		$this->list_view();
	}

	private function list_view(): void {
		$search = sanitize_text_field( $this->portal->query( 'q' ) );
		$status = in_array( $this->portal->query( 'stato' ), [ 'active', 'suspended', 'archived' ], true ) ? $this->portal->query( 'stato' ) : '';
		$page   = max( 1, absint( $this->portal->query( 'pagina' ) ) );
		$result = $this->repo->search( $search, $status, $page, 25 );
		echo '<div class="bcp-soci-toolbar">';
		echo '<a class="bcp-button bcp-button--primary" href="' . esc_url( Portal::url( [ 'vista' => 'nuovo-socio' ] ) ) . '">' . esc_html__( 'Aggiungi socio', 'bracelli-club' ) . '</a> ';
		echo '<a class="bcp-button" href="' . esc_url( Portal::url( [ 'vista' => 'importa-soci' ] ) ) . '">' . esc_html__( 'Importa CSV', 'bracelli-club' ) . '</a> ';
		echo '<form class="bcp-inline-form" method="post" action="' . esc_url( Portal::url() ) . '">';
		$this->portal->action_fields( 'soci_export' );
		echo '<button class="bcp-button" type="submit">' . esc_html__( 'Esporta CSV', 'bracelli-club' ) . '</button></form>';
		echo '</div>';
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
		/* translators: %s: number of matching member records. */
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
			$url = Portal::url(
				[
					'vista' => 'modifica-socio',
					'socio' => (int) $member['id'],
				]
			);
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
				$url = Portal::url(
					[
						'vista'  => 'soci',
						'q'      => $search,
						'stato'  => $status,
						'pagina' => $n,
					]
				);
				echo '<a class="bcp-button' . ( $n === $page ? ' bcp-button--primary' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $n === $page ? ' aria-current="page"' : '' ) . '>' . esc_html( (string) $n ) . '</a> ';
			}
			echo '</nav>';
		}
	}

	/**
	 * @param array<string, mixed>|null $member Record esistente o null.
	 */
	private function form( ?array $member, int $user ): void {
		$id     = (int) ( $member['id'] ?? 0 );
		$values = $this->portal->old() + [
			'first_name'  => (string) ( $member['first_name'] ?? '' ),
			'last_name'   => (string) ( $member['last_name'] ?? '' ),
			'email'       => (string) ( $member['email'] ?? '' ),
			'phone'       => (string) ( $member['phone'] ?? '' ),
			'birth_date'  => (string) ( $member['birth_date'] ?? '' ),
			'address'     => (string) ( $member['address'] ?? '' ),
			'city'        => (string) ( $member['city'] ?? '' ),
			'postal_code' => (string) ( $member['postal_code'] ?? '' ),
			'status'      => (string) ( $member['status'] ?? 'active' ),
			'notes'       => (string) ( $member['notes'] ?? '' ),
		];
		$family              = $id > 0 ? $this->service->family( $user, $id ) : null;
		$history             = $id > 0 ? $this->service->history( $user, $id ) : [];
		$selected_guardians  = $this->selected_guardian_ids( $id );
		$guardian_candidates = $this->service->candidate_guardians( $user, $id );
		$duplicates          = $this->service->possible_duplicates(
			$user,
			(string) $values['first_name'],
			(string) $values['last_name'],
			(string) $values['birth_date'],
			$id
		);
		$form_view = $id > 0 ? 'modifica-socio' : 'nuovo-socio';
		$form_args = [ 'vista' => $form_view ];
		if ( $id > 0 ) {
			$form_args['socio'] = $id;
		}
		?>
<form class="bcp-card bcp-form" method="post" action="<?php echo esc_url( Portal::url( $form_args ) ); ?>">
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
		<div class="bcp-field">
			<label for="bcp-status"><?php esc_html_e( 'Stato anagrafica', 'bracelli-club' ); ?></label>
			<select id="bcp-status" name="status">
				<option value="active"<?php selected( $values['status'], 'active' ); ?>><?php esc_html_e( 'Attivo', 'bracelli-club' ); ?></option>
				<option value="suspended"<?php selected( $values['status'], 'suspended' ); ?>><?php esc_html_e( 'Sospeso', 'bracelli-club' ); ?></option>
				<?php if ( 'archived' === $values['status'] ) : ?>
					<option value="archived" selected><?php esc_html_e( 'Archiviato', 'bracelli-club' ); ?></option>
				<?php endif; ?>
			</select>
		</div>
		<div class="bcp-field bcp-field--full"><label for="bcp-notes"><?php esc_html_e( 'Note interne', 'bracelli-club' ); ?></label><textarea id="bcp-notes" name="notes" rows="4" maxlength="4000"><?php echo esc_textarea( $values['notes'] ); ?></textarea><?php $this->portal->field_error( 'notes' ); ?></div>
		<div class="bcp-field bcp-field--full">
			<label for="bcp-guardians"><?php esc_html_e( 'Genitori o tutori collegati', 'bracelli-club' ); ?></label>
			<input type="hidden" name="guardian_ids_present" value="1">
			<select id="bcp-guardians" name="guardian_ids[]" multiple size="5" aria-describedby="bcp-guardians-help">
				<?php foreach ( $guardian_candidates as $candidate ) : ?>
					<option value="<?php echo esc_attr( (string) $candidate['id'] ); ?>"<?php selected( in_array( (int) $candidate['id'], $selected_guardians, true ) ); ?>><?php echo esc_html( $candidate['last_name'] . ' ' . $candidate['first_name'] . ( 'archived' === $candidate['status'] ? ' — ' . __( 'archiviato', 'bracelli-club' ) : '' ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<p id="bcp-guardians-help" class="bcp-muted"><?php esc_html_e( 'Facoltativo. Puoi selezionare più persone; il collegamento non crea account WordPress e non assegna automaticamente accessi.', 'bracelli-club' ); ?></p>
			<?php $this->portal->field_error( 'guardian_ids' ); ?>
		</div>
	</div>
		<?php if ( [] !== $duplicates ) : ?>
		<div class="bcp-alert bcp-alert--warning" role="status">
			<strong><?php esc_html_e( 'Controlla possibili doppioni', 'bracelli-club' ); ?></strong>
			<p><?php esc_html_e( 'Esistono già anagrafiche con nome e cognome uguali. Verifica che non si tratti della stessa persona.', 'bracelli-club' ); ?></p>
			<ul>
				<?php foreach ( $duplicates as $duplicate ) : ?>
					<li>
						<?php
						echo esc_html(
							$duplicate['last_name'] . ' ' . $duplicate['first_name']
							. ( '' !== (string) ( $duplicate['birth_date'] ?? '' ) ? ' — ' . $duplicate['birth_date'] : '' )
						);
						?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endif; ?>
	<p><button class="bcp-button bcp-button--primary" type="submit"><?php esc_html_e( 'Salva anagrafica', 'bracelli-club' ); ?></button> <a class="bcp-button" href="<?php echo esc_url( Portal::url( [ 'vista' => 'soci' ] ) ); ?>"><?php esc_html_e( 'Annulla', 'bracelli-club' ); ?></a></p>
</form>
		<?php
		if ( $id > 0 && 'archived' !== (string) ( $member['status'] ?? '' ) ) {
			echo '<form class="bcp-card" method="post" action="' . esc_url( Portal::url() ) . '">';
			$this->portal->action_fields( 'soci_archive', $id );
			echo '<p>' . esc_html__( 'Se non è più attivo, archivia il socio. I dati restano nello storico e non vengono cancellati.', 'bracelli-club' ) . '</p>';
			echo '<button class="bcp-button" type="submit">' . esc_html__( 'Archivia socio', 'bracelli-club' ) . '</button></form>';
		} elseif ( $id > 0 ) {
			echo '<form class="bcp-card" method="post" action="' . esc_url( Portal::url() ) . '">';
			$this->portal->action_fields( 'soci_restore', $id );
			echo '<p>' . esc_html__( 'Questa anagrafica è archiviata. Puoi riattivarla senza ricreare il record.', 'bracelli-club' ) . '</p>';
			echo '<button class="bcp-button bcp-button--primary" type="submit">' . esc_html__( 'Riattiva socio', 'bracelli-club' ) . '</button></form>';
		}
		if ( $id > 0 ) {
			$this->render_family( $family );
			$this->render_history( $history );
		}
	}

	/**
	 * Mostra legami familiari senza esporli a ruoli diversi dalla segreteria.
	 *
	 * @param array{guardians: array<int, array<string, mixed>>, dependents: array<int, array<string, mixed>>}|null $family Famiglia.
	 */
	private function render_family( ?array $family ): void {
		if ( null === $family ) {
			return;
		}
		echo '<section class="bcp-card bcp-soci-family"><h2>' . esc_html__( 'Nucleo familiare', 'bracelli-club' ) . '</h2>';
		foreach (
			[
				'guardians'  => __( 'Genitori e tutori', 'bracelli-club' ),
				'dependents' => __( 'Soci collegati come familiari', 'bracelli-club' ),
			] as $key => $label
		) {
			echo '<h3>' . esc_html( $label ) . '</h3>';
			if ( [] === $family[ $key ] ) {
				echo '<p class="bcp-muted">' . esc_html__( 'Nessun collegamento registrato.', 'bracelli-club' ) . '</p>';
				continue;
			}
			echo '<ul>';
			foreach ( $family[ $key ] as $relative ) {
				$url = Portal::url(
					[
						'vista' => 'modifica-socio',
						'socio' => (int) $relative['id'],
					]
				);
				echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $relative['last_name'] . ' ' . $relative['first_name'] ) . '</a>';
				if ( ! empty( $relative['is_primary'] ) ) {
					echo ' <span class="bcp-badge">' . esc_html__( 'Principale', 'bracelli-club' ) . '</span>';
				}
				echo '</li>';
			}
			echo '</ul>';
		}
		echo '</section>';
	}

	/**
	 * Storico delle operazioni e dei campi modificati.
	 *
	 * @param array<int, array<string, mixed>> $history Eventi.
	 */
	private function render_history( array $history ): void {
		echo '<section class="bcp-card bcp-soci-history"><h2>' . esc_html__( 'Storico anagrafica', 'bracelli-club' ) . '</h2>';
		if ( [] === $history ) {
			echo '<p class="bcp-muted">' . esc_html__( 'Non ci sono ancora modifiche registrate.', 'bracelli-club' ) . '</p></section>';
			return;
		}
		echo '<div class="bcp-table-wrap"><table class="bcp-table"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Data', 'bracelli-club' ) . '</th><th scope="col">' . esc_html__( 'Operazione', 'bracelli-club' ) . '</th><th scope="col">' . esc_html__( 'Operatore', 'bracelli-club' ) . '</th><th scope="col">' . esc_html__( 'Campi coinvolti', 'bracelli-club' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $history as $event ) {
			$actor = get_userdata( (int) $event['actor_id'] );
			$fields = isset( $event['fields'] ) && is_array( $event['fields'] ) ? array_map( [ $this, 'field_label' ], $event['fields'] ) : [];
			echo '<tr><td data-label="' . esc_attr__( 'Data', 'bracelli-club' ) . '">' . esc_html( (string) $event['created_at'] ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Operazione', 'bracelli-club' ) . '">' . esc_html( $this->history_action_label( (string) $event['action'] ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Operatore', 'bracelli-club' ) . '">' . esc_html( $actor ? $actor->display_name : __( 'Account non disponibile', 'bracelli-club' ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Campi coinvolti', 'bracelli-club' ) . '">' . esc_html( [] === $fields ? '—' : implode( ', ', $fields ) ) . '</td></tr>';
		}
		echo '</tbody></table></div></section>';
	}

	/**
	 * Etichetta leggibile dei campi registrati nello storico.
	 */
	public function field_label( string $field ): string {
		return match ( $field ) {
			'first_name'   => __( 'Nome', 'bracelli-club' ),
			'last_name'    => __( 'Cognome', 'bracelli-club' ),
			'email'        => __( 'Email', 'bracelli-club' ),
			'phone'        => __( 'Telefono', 'bracelli-club' ),
			'birth_date'   => __( 'Data di nascita', 'bracelli-club' ),
			'address'      => __( 'Indirizzo', 'bracelli-club' ),
			'city'         => __( 'Comune', 'bracelli-club' ),
			'postal_code'  => __( 'CAP', 'bracelli-club' ),
			'status'       => __( 'Stato', 'bracelli-club' ),
			'notes'        => __( 'Note', 'bracelli-club' ),
			'guardian_ids' => __( 'Genitori/tutori', 'bracelli-club' ),
			default        => $field,
		};
	}

	private function history_action_label( string $action ): string {
		return match ( $action ) {
			'created'        => __( 'Creazione', 'bracelli-club' ),
			'updated'        => __( 'Modifica', 'bracelli-club' ),
			'status_changed' => __( 'Cambio stato', 'bracelli-club' ),
			default          => $action,
		};
	}

	/**
	 * IDs non modificabili: le selezioni multiple arrivano come array e vanno convalidate singolarmente.
	 *
	 * @return int[]
	 */
	private function posted_guardian_ids(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- il nonce è verificato da Portal::handle_post().
		if ( ! isset( $_POST['guardian_ids'] ) || ! is_array( $_POST['guardian_ids'] ) ) {
			return [];
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificato da Portal::handle_post().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificato da Portal::handle_post().
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ogni elemento è convertito in intero qui sotto.
		$raw = wp_unslash( $_POST['guardian_ids'] );
		$result = array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return $result;
	}

	/**
	 * Mantiene la selezione inviata in caso di errore, altrimenti carica quella salvata.
	 *
	 * @return int[]
	 */
	private function selected_guardian_ids( int $member_id ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- il nonce è già verificato quando si ripresenta il modulo.
		if ( isset( $_POST['guardian_ids_present'] ) ) {
			return $this->posted_guardian_ids();
		}
		return $member_id > 0 ? $this->repo->guardian_ids( $member_id ) : [];
	}

	/**
	 * Schermata di anteprima e conferma dell'importazione CSV.
	 */
	private function render_import_view( int $user ): void {
		$preview = get_transient( $this->import_key( $user ) );
		$report  = get_transient( $this->report_key( $user ) );

		echo '<p><a class="bcp-button" href="' . esc_url( Portal::url( [ 'vista' => 'soci' ] ) ) . '">' . esc_html__( 'Torna all’archivio', 'bracelli-club' ) . '</a></p>';

		if ( is_array( $report ) ) {
			echo '<section class="bcp-card"><h2>' . esc_html__( 'Rapporto importazione', 'bracelli-club' ) . '</h2>';
			echo '<p>' . esc_html(
				sprintf(
					/* translators: 1: created rows, 2: failed rows. */
					__( 'Anagrafiche create: %1$d. Righe non salvate: %2$d.', 'bracelli-club' ),
					(int) $report['created'],
					(int) $report['failed']
				)
			) . '</p>';
			if ( [] !== $report['failures'] ) {
				echo '<ul>';
				foreach ( $report['failures'] as $failure ) {
					$message = sprintf(
						/* translators: 1: CSV row number, 2: reason the row was not imported. */
						__( 'Riga %1$d: %2$s', 'bracelli-club' ),
						(int) $failure['line'],
						(string) $failure['message']
					);
					echo '<li>' . esc_html( $message ) . '</li>';
				}
				echo '</ul>';
			}
			echo '</section>';
		}

		if ( is_array( $preview ) ) {
			echo '<section class="bcp-card"><h2>' . esc_html__( 'Anteprima CSV', 'bracelli-club' ) . '</h2>';
			$row_count = sprintf(
				/* translators: %d: number of CSV rows in the preview. */
				__( '%d righe da analizzare', 'bracelli-club' ),
				count( $preview['rows'] )
			);
			echo '<p>' . esc_html( (string) $preview['filename'] ) . ' — ' . esc_html( $row_count ) . '</p>';

			if ( [] !== $preview['fatal_errors'] ) {
				echo '<ul class="bcp-import-errors">';
				foreach ( $preview['fatal_errors'] as $error ) {
					echo '<li>' . esc_html( (string) $error ) . '</li>';
				}
				echo '</ul>';
			}

			if ( [] !== $preview['rows'] ) {
				echo '<div class="bcp-table-wrap"><table class="bcp-table"><thead><tr>';
				echo '<th scope="col">' . esc_html__( 'Riga', 'bracelli-club' ) . '</th>';
				echo '<th scope="col">' . esc_html__( 'Anagrafica', 'bracelli-club' ) . '</th>';
				echo '<th scope="col">' . esc_html__( 'Controllo', 'bracelli-club' ) . '</th>';
				echo '</tr></thead><tbody>';
				foreach ( $preview['rows'] as $row ) {
					$member_name = trim( $row['data']['last_name'] . ' ' . $row['data']['first_name'] );
					$details     = [];
					foreach ( $row['errors'] as $error ) {
						$details[] = __( 'Errore: ', 'bracelli-club' ) . $error;
					}
					foreach ( $row['warnings'] as $warning ) {
						$details[] = __( 'Attenzione: ', 'bracelli-club' ) . $warning;
					}
					if ( [] === $details ) {
						$details[] = __( 'Pronta per l’importazione', 'bracelli-club' );
					}
					echo '<tr><td data-label="' . esc_attr__( 'Riga', 'bracelli-club' ) . '">' . esc_html( (string) $row['line'] ) . '</td>';
					echo '<td data-label="' . esc_attr__( 'Anagrafica', 'bracelli-club' ) . '">' . esc_html( '' !== $member_name ? $member_name : __( 'Nome mancante', 'bracelli-club' ) ) . '</td>';
					echo '<td data-label="' . esc_attr__( 'Controllo', 'bracelli-club' ) . '">' . esc_html( implode( ' — ', $details ) ) . '</td></tr>';
				}
				echo '</tbody></table></div>';
			}

			if ( [] === $preview['fatal_errors'] && 0 === (int) $preview['error_count'] && [] !== $preview['rows'] ) {
				echo '<p>' . esc_html__( 'Nessuna modifica è stata ancora salvata. La conferma crea nuove anagrafiche, non aggiorna né cancella record esistenti.', 'bracelli-club' ) . '</p>';
				echo '<form class="bcp-inline-form" method="post" action="' . esc_url( Portal::url( [ 'vista' => 'importa-soci' ] ) ) . '">';
				$this->portal->action_fields( 'soci_import_apply' );
				echo '<button class="bcp-button bcp-button--primary" type="submit">' . esc_html__( 'Conferma importazione', 'bracelli-club' ) . '</button></form>';
			} else {
				echo '<p class="bcp-alert bcp-alert--warning">' . esc_html__( 'Sono presenti errori bloccanti. Nessuna riga verrà importata; correggi il CSV e ricaricalo.', 'bracelli-club' ) . '</p>';
			}

			echo '<form class="bcp-inline-form" method="post" action="' . esc_url( Portal::url( [ 'vista' => 'importa-soci' ] ) ) . '">';
			$this->portal->action_fields( 'soci_import_discard' );
			echo '<button class="bcp-button" type="submit">' . esc_html__( 'Scarta anteprima', 'bracelli-club' ) . '</button></form>';
			echo '</section>';
		}

		?>
<form class="bcp-card bcp-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( Portal::url( [ 'vista' => 'importa-soci' ] ) ); ?>">
		<?php $this->portal->action_fields( 'soci_import_preview' ); ?>
	<h2><?php esc_html_e( 'Carica un file CSV', 'bracelli-club' ); ?></h2>
	<p class="bcp-muted"><?php esc_html_e( 'Il file deve contenere una riga di intestazione e le colonne Nome e Cognome. Può essere separato da punto e virgola o virgola. Il caricamento prepara solo un’anteprima.', 'bracelli-club' ); ?></p>
	<div class="bcp-field">
		<label for="bcp-soci-csv"><?php esc_html_e( 'File CSV (massimo 1 MB, 500 righe)', 'bracelli-club' ); ?></label>
		<input id="bcp-soci-csv" type="file" name="soci_csv" accept=".csv,text/csv" required>
	</div>
	<p><button class="bcp-button bcp-button--primary" type="submit"><?php esc_html_e( 'Carica e controlla', 'bracelli-club' ); ?></button></p>
</form>
<form class="bcp-card" method="post" action="<?php echo esc_url( Portal::url( [ 'vista' => 'importa-soci' ] ) ); ?>">
		<?php $this->portal->action_fields( 'soci_template' ); ?>
	<h2><?php esc_html_e( 'Modello CSV', 'bracelli-club' ); ?></h2>
	<p><?php esc_html_e( 'Scarica il modello vuoto per preparare i dati con le intestazioni riconosciute dall’archivio.', 'bracelli-club' ); ?></p>
	<button class="bcp-button" type="submit"><?php esc_html_e( 'Scarica modello', 'bracelli-club' ); ?></button>
</form>
		<?php
	}

	/**
	 * Prepara e controlla il file senza scrivere nel database.
	 *
	 * @return array{filename: string, rows: array<int, array{line: int, data: array<string, string>, errors: string[], warnings: string[]}>, fatal_errors: string[], error_count: int}
	 */
	private function create_import_preview( int $user ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- il nonce è stato verificato dal portale prima del dispatch.
		$file = isset( $_FILES['soci_csv'] ) && is_array( $_FILES['soci_csv'] ) ? $_FILES['soci_csv'] : null;
		$filename = is_array( $file ) ? sanitize_file_name( (string) ( $file['name'] ?? '' ) ) : '';
		$tmp      = is_array( $file ) ? (string) ( $file['tmp_name'] ?? '' ) : '';
		$base     = [
			'filename'     => $filename,
			'rows'         => [],
			'fatal_errors' => [],
			'error_count'  => 0,
		];

		if (
			null === $file
			|| UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE )
			|| '' === $tmp
			|| ! is_uploaded_file( $tmp )
		) {
			$base['fatal_errors'][] = __( 'Il file non è stato caricato correttamente.', 'bracelli-club' );
			return $base;
		}
		if ( (int) ( $file['size'] ?? 0 ) > 1048576 || ! preg_match( '/\.csv$/i', $filename ) ) {
			$base['fatal_errors'][] = __( 'Seleziona un file CSV di massimo 1 MB.', 'bracelli-club' );
			return $base;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file caricato e verificato, letto in locale.
		$content = (string) file_get_contents( $tmp );
		if ( '' === trim( $content ) ) {
			$base['fatal_errors'][] = __( 'Il file CSV è vuoto.', 'bracelli-club' );
			return $base;
		}

		$first_line = strtok( $content, "\r\n" );
		$delimiter  = is_string( $first_line ) && substr_count( $first_line, ';' ) > substr_count( $first_line, ',' ) ? ';' : ',';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen -- stream temporaneo in memoria, non un file permanente.
		$stream = fopen( 'php://temp', 'r+' );
		if ( false === $stream ) {
			$base['fatal_errors'][] = __( 'Non è stato possibile leggere il CSV.', 'bracelli-club' );
			return $base;
		}
		fwrite( $stream, $content );
		rewind( $stream );
		$headers = fgetcsv( $stream, null, $delimiter, '"', '' );
		if ( false === $headers || [] === $headers ) {
			fclose( $stream );
			$base['fatal_errors'][] = __( 'Il file non contiene intestazioni valide.', 'bracelli-club' );
			return $base;
		}

		$aliases = [
			'nome'            => 'first_name',
			'first_name'      => 'first_name',
			'first-name'      => 'first_name',
			'cognome'         => 'last_name',
			'last_name'       => 'last_name',
			'last-name'       => 'last_name',
			'email'           => 'email',
			'e_mail'          => 'email',
			'telefono'        => 'phone',
			'phone'           => 'phone',
			'cellulare'       => 'phone',
			'data_di_nascita' => 'birth_date',
			'data_nascita'    => 'birth_date',
			'birth_date'      => 'birth_date',
			'indirizzo'       => 'address',
			'address'         => 'address',
			'comune'          => 'city',
			'citta'           => 'city',
			'city'            => 'city',
			'cap'             => 'postal_code',
			'postal_code'     => 'postal_code',
			'stato'           => 'status',
			'status'          => 'status',
			'note'            => 'notes',
			'note_interne'    => 'notes',
			'notes'           => 'notes',
		];
		$columns = [];
		foreach ( $headers as $index => $header ) {
			$normalized = strtolower( trim( remove_accents( ltrim( (string) $header, "\xEF\xBB\xBF" ) ) ) );
			$normalized = sanitize_key( str_replace( [ ' ', '-' ], '_', $normalized ) );
			$target     = $aliases[ $normalized ] ?? '';
			if ( '' !== $target ) {
				$columns[ $target ] = (int) $index;
			}
		}
		if ( ! isset( $columns['first_name'], $columns['last_name'] ) ) {
			fclose( $stream );
			$base['fatal_errors'][] = __( 'Mancano le colonne obbligatorie Nome e Cognome. Scarica il modello e riprova.', 'bracelli-club' );
			return $base;
		}

		$seen_emails = [];
		$line        = 1;
		while ( false !== ( $cells = fgetcsv( $stream, null, $delimiter, '"', '' ) ) ) {
			++$line;
			$filled = array_filter( $cells, static fn( $cell ): bool => '' !== trim( (string) $cell ) );
			if ( [] === $filled ) {
				continue;
			}
			if ( count( $base['rows'] ) >= 500 ) {
				$base['fatal_errors'][] = __( 'Il file supera il limite di 500 righe. Dividilo in più file.', 'bracelli-club' );
				break;
			}

			$data = [
				'first_name' => '',
				'last_name'  => '',
				'email'      => '',
				'phone'      => '',
				'birth_date' => '',
				'address'    => '',
				'city'       => '',
				'postal_code' => '',
				'status'      => 'active',
				'notes'      => '',
			];
			foreach ( $columns as $field => $index ) {
				$data[ $field ] = trim( (string) ( $cells[ $index ] ?? '' ) );
			}
			$data['first_name']  = sanitize_text_field( $data['first_name'] );
			$data['last_name']   = sanitize_text_field( $data['last_name'] );
			$data['email']       = sanitize_email( $data['email'] );
			$data['phone']       = sanitize_text_field( $data['phone'] );
			$data['address']     = sanitize_text_field( $data['address'] );
			$data['city']        = sanitize_text_field( $data['city'] );
			$data['postal_code'] = sanitize_text_field( $data['postal_code'] );
			$data['notes']       = sanitize_textarea_field( $data['notes'] );

			$errors   = [];
			$warnings = [];
			if ( '' === $data['first_name'] ) {
				$errors[] = __( 'Nome mancante.', 'bracelli-club' );
			}
			if ( '' === $data['last_name'] ) {
				$errors[] = __( 'Cognome mancante.', 'bracelli-club' );
			}
			if ( strlen( $data['first_name'] ) > 100 || strlen( $data['last_name'] ) > 100 ) {
				$errors[] = __( 'Nome o cognome troppo lungo.', 'bracelli-club' );
			}
			$raw_email = isset( $columns['email'] ) ? trim( (string) ( $cells[ $columns['email'] ] ?? '' ) ) : '';
			if ( '' !== $raw_email && ( '' === $data['email'] || ! is_email( $data['email'] ) ) ) {
				$errors[] = __( 'Indirizzo email non valido.', 'bracelli-club' );
			}
			if ( '' !== $data['email'] ) {
				$email_key = strtolower( $data['email'] );
				if ( isset( $seen_emails[ $email_key ] ) || $this->repo->email_exists( $data['email'] ) ) {
					$errors[] = __( 'Email già presente nell’archivio o duplicata nel file.', 'bracelli-club' );
				}
				$seen_emails[ $email_key ] = $line;
			}

			$data['birth_date'] = $this->normalize_birth_date( $data['birth_date'] );
			$raw_birth = isset( $columns['birth_date'] ) ? trim( (string) ( $cells[ $columns['birth_date'] ] ?? '' ) ) : '';
			if ( '' !== $raw_birth && '' === $data['birth_date'] ) {
				$errors[] = __( 'Data di nascita non valida: usa AAAA-MM-GG o GG/MM/AAAA.', 'bracelli-club' );
			}
			$status_text = strtolower( remove_accents( trim( $data['status'] ) ) );
			$status_map  = [
				''           => 'active',
				'attivo'     => 'active',
				'active'     => 'active',
				'sospeso'    => 'suspended',
				'suspended'  => 'suspended',
				'archiviato' => 'archived',
				'archived'   => 'archived',
			];
			if ( ! isset( $status_map[ $status_text ] ) ) {
				$errors[] = __( 'Stato non valido: usa Attivo, Sospeso o Archiviato.', 'bracelli-club' );
			} else {
				$data['status'] = $status_map[ $status_text ];
			}
			if ( strlen( $data['address'] ) > 190 || strlen( $data['city'] ) > 100 || strlen( $data['postal_code'] ) > 20 || strlen( $data['phone'] ) > 40 || strlen( $data['email'] ) > 190 || strlen( $data['notes'] ) > 4000 ) {
				$errors[] = __( 'Uno o più campi superano la lunghezza consentita.', 'bracelli-club' );
			}

			$duplicate_matches = $this->service->possible_duplicates(
				$user,
				$data['first_name'],
				$data['last_name'],
				$data['birth_date']
			);
			if ( [] !== $duplicate_matches ) {
				$warnings[] = __( 'Esistono anagrafiche con nome e cognome simili: verifica prima di confermare.', 'bracelli-club' );
			}
			$base['error_count'] += count( $errors );
			$base['rows'][] = [
				'line'     => $line,
				'data'     => $data,
				'errors'   => $errors,
				'warnings' => $warnings,
			];
		}
		fclose( $stream );
		if ( [] === $base['rows'] && [] === $base['fatal_errors'] ) {
			$base['fatal_errors'][] = __( 'Il file non contiene righe da importare.', 'bracelli-club' );
		}
		return $base;
	}

	/**
	 * Converte le date supportate in formato ISO.
	 */
	private function normalize_birth_date( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) ) {
			if ( checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
				return $value;
			}
			return '';
		}
		if ( preg_match( '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $parts ) ) {
			if ( ! checkdate( (int) $parts[2], (int) $parts[1], (int) $parts[3] ) ) {
				return '';
			}
			return sprintf( '%04d-%02d-%02d', (int) $parts[3], (int) $parts[2], (int) $parts[1] );
		}
		return '';
	}

	/**
	 * Applica un’anteprima valida e registra eventuali righe non salvate.
	 */
	private function apply_import( Portal $portal, int $user ): void {
		$preview = get_transient( $this->import_key( $user ) );
		if (
			! is_array( $preview )
			|| [] === $preview['rows']
			|| [] !== $preview['fatal_errors']
			|| 0 !== (int) $preview['error_count']
		) {
			$portal->redirect(
				[
					'vista' => 'importa-soci',
					'esito' => 'import_expired',
				]
			);
		}

		$created  = 0;
		$failures = [];
		foreach ( $preview['rows'] as $row ) {
			$result = $this->service->save( $user, 0, $row['data'] );
			if ( $result['ok'] && 'created' === $result['code'] ) {
				++$created;
			} else {
				$message = implode( ' ', array_values( $result['errors'] ) );
				if ( '' === $message ) {
					$message = __( 'Il record non è stato salvato. Potrebbe essere cambiato dopo l’anteprima.', 'bracelli-club' );
				}
				$failures[] = [
					'line'    => (int) $row['line'],
					'message' => $message,
				];
			}
		}
		delete_transient( $this->import_key( $user ) );
		set_transient(
			$this->report_key( $user ),
			[
				'created'  => $created,
				'failed'   => count( $failures ),
				'failures' => $failures,
			],
			30 * MINUTE_IN_SECONDS
		);
		$portal->redirect(
			[
				'vista' => 'importa-soci',
				'esito' => [] === $failures ? 'import_applied' : 'import_partial',
			]
		);
	}

	/**
	 * Esporta il modello vuoto o le anagrafiche, soltanto per utenti autorizzati.
	 */
	private function download_csv( int $user, bool $template ): void {
		if ( ! $this->service->can_manage( $user ) ) {
			status_header( 403 );
			exit;
		}
		$rows = $template ? [] : $this->repo->export_all();
		if ( ! $template ) {
			$this->service->record_export( $user, count( $rows ) );
		}
		$filename = $template ? 'modello-import-soci.csv' : 'bracelli-soci-' . gmdate( 'Ymd-His' ) . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen -- stream di risposta, nessun file creato.
		$stream = fopen( 'php://output', 'w' );
		if ( false === $stream ) {
			status_header( 500 );
			exit;
		}
		fwrite( $stream, "\xEF\xBB\xBF" );
		fputcsv(
			$stream,
			[
				'Nome',
				'Cognome',
				'Email',
				'Telefono',
				'Data di nascita',
				'Indirizzo',
				'Comune',
				'CAP',
				'Stato',
				'Note',
			],
			';',
			'"',
			''
		);
		foreach ( $rows as $row ) {
			if ( $template ) {
				break;
			}
			fputcsv(
				$stream,
				[
					(string) $row['first_name'],
					(string) $row['last_name'],
					(string) $row['email'],
					(string) $row['phone'],
					(string) ( $row['birth_date'] ?? '' ),
					(string) $row['address'],
					(string) $row['city'],
					(string) $row['postal_code'],
					(string) $row['status'],
					(string) $row['notes'],
				],
				';',
				'"',
				''
			);
		}
		fclose( $stream );
		exit;
	}

	private function import_key( int $user ): string {
		return 'bracelli_soci_import_' . $user;
	}

	private function report_key( int $user ): string {
		return 'bracelli_soci_import_report_' . $user;
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
