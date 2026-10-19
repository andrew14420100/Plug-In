<?php
/**
 * Impostazioni globali con esportazione e importazione con anteprima delle differenze.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Core;

use Bracelli\Club\Core\Admin\Layout;
use Bracelli\Club\Core\Admin\Pages;
use Bracelli\Club\Core\Permissions\Capabilities;
use Bracelli\Club\Core\Settings\Settings;
use Bracelli\Club\Core\Ui\Palettes;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Pagina "Impostazioni" (Q185: import/export con anteprima delle differenze).
 */
final class SettingsPage {

	public const SLUG          = 'bracelli-settings';
	public const ACTION_SAVE   = 'bracelli_club_settings_save';
	public const ACTION_EXPORT = 'bracelli_club_settings_export';
	public const ACTION_IMPORT = 'bracelli_club_settings_import';
	public const ACTION_APPLY  = 'bracelli_club_settings_apply';

	private const PREVIEW_TTL = 900;

	/** Impostazione che solo chi ha manage_options può cambiare. */
	private const ADMIN_ONLY = 'uninstall.delete_data';

	/**
	 * @param Plugin $plugin Servizi.
	 */
	public function __construct( private Plugin $plugin ) {
	}

	/**
	 * Hook.
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_post_' . self::ACTION_SAVE, [ $this, 'handle_save' ] );
		add_action( 'admin_post_' . self::ACTION_EXPORT, [ $this, 'handle_export' ] );
		add_action( 'admin_post_' . self::ACTION_IMPORT, [ $this, 'handle_import' ] );
		add_action( 'admin_post_' . self::ACTION_APPLY, [ $this, 'handle_apply' ] );
	}

	/**
	 * Voce di menu.
	 */
	public function menu(): void {
		Pages::add( self::SLUG, __( 'Impostazioni', 'bracelli-club' ), __( 'Impostazioni', 'bracelli-club' ), Capabilities::SETTINGS_MANAGE, [ $this, 'render' ], 20 );
	}

	/**
	 * Rendering.
	 */
	public function render(): void {
		Layout::require_cap( Capabilities::SETTINGS_MANAGE );
		Layout::open( __( 'Impostazioni', 'bracelli-club' ), 'settings', __( 'Valori globali del plugin. Le preferenze grafiche personali si scelgono dalla dashboard.', 'bracelli-club' ) );
		Layout::query_notice(
			[
				'saved'          => [ 'success', __( 'Impostazioni salvate.', 'bracelli-club' ) ],
				'unchanged'      => [ 'info', __( 'Nessuna modifica da salvare.', 'bracelli-club' ) ],
				'invalid'        => [ 'error', __( 'Alcuni valori non sono validi e non sono stati salvati.', 'bracelli-club' ) ],
				'imported'       => [ 'success', __( 'Importazione applicata.', 'bracelli-club' ) ],
				'import_invalid' => [ 'error', __( 'File di importazione non valido o anteprima scaduta.', 'bracelli-club' ) ],
			]
		);

		$values = $this->plugin->settings->all();
		$labels = Settings::labels();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="bc-card bc-form">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SAVE ) . '">';
		wp_nonce_field( self::ACTION_SAVE );
		echo '<h2>' . esc_html__( 'Impostazioni generali', 'bracelli-club' ) . '</h2>';
		foreach ( Settings::schema() as $key => $def ) {
			$field_id = 'bc-setting-' . str_replace( '.', '-', $key );
			$label    = $labels[ $key ]['label'] ?? $key;
			$help     = $labels[ $key ]['help'] ?? '';
			echo '<div class="bc-field' . ( 'uninstall.delete_data' === $key ? ' bc-field--danger' : '' ) . '">';
			if ( 'bool' === $def['type'] ) {
				$locked = self::ADMIN_ONLY === $key && ! current_user_can( 'manage_options' );
				if ( ! $locked ) {
					echo '<input type="hidden" name="settings[' . esc_attr( $key ) . ']" value="0">';
				}
				printf(
					'<label class="bc-switch" for="%1$s"><input type="checkbox" id="%1$s" name="settings[%2$s]" value="1"%3$s%4$s aria-describedby="%1$s-help"><span>%5$s</span></label>',
					esc_attr( $field_id ),
					esc_attr( $key ),
					checked( true, (bool) $values[ $key ], false ),
					disabled( $locked, true, false ),
					esc_html( $label )
				);
			} else {
				echo '<label for="' . esc_attr( $field_id ) . '">' . esc_html( $label ) . '</label>';
				echo '<select id="' . esc_attr( $field_id ) . '" name="settings[' . esc_attr( $key ) . ']" aria-describedby="' . esc_attr( $field_id ) . '-help">';
				foreach ( $def['choices'] ?? [] as $choice ) {
					printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $choice ), selected( $values[ $key ], $choice, false ), esc_html( self::choice_label( $key, $choice ) ) );
				}
				echo '</select>';
			}
			echo '<p class="bc-help" id="' . esc_attr( $field_id ) . '-help">' . esc_html( $help ) . '</p></div>';
		}
		echo '<p><button type="submit" class="bc-button bc-button--primary">' . esc_html__( 'Salva impostazioni', 'bracelli-club' ) . '</button></p>';
		echo '</form>';

		echo '<div class="bc-grid bc-grid--2">';
		echo '<div class="bc-card"><h2>' . esc_html__( 'Esporta', 'bracelli-club' ) . '</h2>';
		echo '<p class="bc-muted">' . esc_html__( 'Scarica un file JSON con le impostazioni globali (senza dati personali né segreti).', 'bracelli-club' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_EXPORT ) . '">';
		wp_nonce_field( self::ACTION_EXPORT );
		echo '<button type="submit" class="bc-button">' . esc_html__( 'Scarica esportazione', 'bracelli-club' ) . '</button></form></div>';

		echo '<div class="bc-card"><h2>' . esc_html__( 'Importa', 'bracelli-club' ) . '</h2>';
		echo '<p class="bc-muted">' . esc_html__( 'Il file viene prima analizzato: vedrai le differenze e potrai confermare o annullare.', 'bracelli-club' ) . '</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_IMPORT ) . '">';
		wp_nonce_field( self::ACTION_IMPORT );
		echo '<label for="bc-import-file">' . esc_html__( 'File JSON', 'bracelli-club' ) . '</label> ';
		echo '<input type="file" id="bc-import-file" name="settings_file" accept="application/json,.json" required> ';
		echo '<button type="submit" class="bc-button">' . esc_html__( 'Analizza file', 'bracelli-club' ) . '</button></form>';
		$this->render_preview();
		echo '</div></div>';

		Layout::close();
	}

	/**
	 * Anteprima dell'importazione (se presente).
	 */
	private function render_preview(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- token di sola lettura, l'applicazione richiede nonce.
		$token = isset( $_GET['preview'] ) ? sanitize_key( wp_unslash( $_GET['preview'] ) ) : '';
		if ( '' === $token ) {
			return;
		}
		$preview = get_transient( $this->preview_key( $token ) );
		if ( ! is_array( $preview ) ) {
			Layout::notice( 'warning', __( 'Anteprima non più disponibile: analizza di nuovo il file.', 'bracelli-club' ) );
			return;
		}
		$labels = Settings::labels();
		echo '<div class="bc-preview" aria-live="polite"><h3>' . esc_html__( 'Anteprima delle differenze', 'bracelli-club' ) . '</h3>';
		if ( [] === $preview['changes'] ) {
			echo '<p>' . esc_html__( 'Il file non cambia nessuna impostazione.', 'bracelli-club' ) . '</p>';
		} else {
			echo '<table class="bc-table"><thead><tr><th scope="col">' . esc_html__( 'Impostazione', 'bracelli-club' ) . '</th><th scope="col">' . esc_html__( 'Attuale', 'bracelli-club' ) . '</th><th scope="col">' . esc_html__( 'Nuovo', 'bracelli-club' ) . '</th></tr></thead><tbody>';
			foreach ( $preview['changes'] as $key => [ $old, $new ] ) {
				echo '<tr><td>' . esc_html( $labels[ $key ]['label'] ?? $key ) . '</td><td>' . esc_html( self::format_value( $key, $old ) ) . '</td><td><strong>' . esc_html( self::format_value( $key, $new ) ) . '</strong></td></tr>';
			}
			echo '</tbody></table>';
		}
		if ( [] !== $preview['errors'] ) {
			echo '<p class="bc-help">' . esc_html__( 'Valori scartati perché non validi:', 'bracelli-club' ) . ' ' . esc_html( implode( ', ', array_keys( $preview['errors'] ) ) ) . '</p>';
		}
		if ( [] !== $preview['ignored'] ) {
			echo '<p class="bc-help">' . esc_html__( 'Chiavi ignorate (sconosciute o non importabili):', 'bracelli-club' ) . ' ' . esc_html( implode( ', ', array_map( 'strval', $preview['ignored'] ) ) ) . '</p>';
		}
		if ( [] !== $preview['changes'] ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_APPLY ) . '">';
			echo '<input type="hidden" name="token" value="' . esc_attr( $token ) . '">';
			wp_nonce_field( self::ACTION_APPLY );
			echo '<button type="submit" class="bc-button bc-button--primary">' . esc_html__( 'Applica queste modifiche', 'bracelli-club' ) . '</button> ';
			echo '<a class="bc-button bc-button--ghost" href="' . esc_url( Pages::url( self::SLUG ) ) . '">' . esc_html__( 'Annulla', 'bracelli-club' ) . '</a></form>';
		}
		echo '</div>';
	}

	/**
	 * Salvataggio del modulo.
	 */
	public function handle_save(): void {
		Layout::require_cap( Capabilities::SETTINGS_MANAGE );
		check_admin_referer( self::ACTION_SAVE );
		$raw    = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? map_deep( wp_unslash( $_POST['settings'] ), 'sanitize_text_field' ) : [];
		$result = Settings::validate( $raw );
		if ( [] !== $result['errors'] ) {
			Layout::redirect( self::SLUG, 'invalid' );
		}
		// La cancellazione dei dati alla disinstallazione la decide solo un amministratore WordPress.
		if ( ! current_user_can( 'manage_options' ) ) {
			unset( $result['values'][ self::ADMIN_ONLY ] );
		}
		$diff = $this->plugin->settings->update( $result['values'] );
		if ( [] === $diff ) {
			Layout::redirect( self::SLUG, 'unchanged' );
		}
		$this->plugin->audit->log(
			'settings.updated',
			[
				'module'        => 'core',
				'resource_type' => 'settings',
				'diff'          => $diff,
			]
		);
		Layout::redirect( self::SLUG, 'saved' );
	}

	/**
	 * Download dell'esportazione.
	 */
	public function handle_export(): void {
		Layout::require_cap( Capabilities::SETTINGS_MANAGE );
		check_admin_referer( self::ACTION_EXPORT );
		$this->plugin->audit->log(
			'settings.exported',
			[
				'module'        => 'core',
				'resource_type' => 'settings',
			]
		);
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="bracelli-club-impostazioni-' . gmdate( 'Ymd-His' ) . '.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo wp_json_encode( $this->plugin->settings->export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Analisi del file (dry-run): nessuna modifica alle impostazioni.
	 */
	public function handle_import(): void {
		Layout::require_cap( Capabilities::SETTINGS_MANAGE );
		check_admin_referer( self::ACTION_IMPORT );
		$file = $_FILES['settings_file'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validato sotto (dimensione, errore, file caricato).
		if ( ! is_array( $file ) || UPLOAD_ERR_OK !== ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || (int) ( $file['size'] ?? 0 ) > 65536 || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			Layout::redirect( self::SLUG, 'import_invalid' );
		}
		$json    = (string) file_get_contents( (string) $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file temporaneo appena caricato.
		$preview = Settings::preview_import( $json, $this->plugin->settings->all() );
		if ( ! $preview['ok'] ) {
			Layout::redirect( self::SLUG, 'import_invalid' );
		}
		$token = strtolower( wp_generate_password( 20, false ) );
		set_transient( $this->preview_key( $token ), $preview, self::PREVIEW_TTL );
		wp_safe_redirect( Pages::url( self::SLUG, [ 'preview' => $token ] ) );
		exit;
	}

	/**
	 * Applicazione dell'anteprima confermata.
	 */
	public function handle_apply(): void {
		Layout::require_cap( Capabilities::SETTINGS_MANAGE );
		check_admin_referer( self::ACTION_APPLY );
		$token   = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : '';
		$preview = '' === $token ? false : get_transient( $this->preview_key( $token ) );
		if ( ! is_array( $preview ) ) {
			Layout::redirect( self::SLUG, 'import_invalid' );
		}
		delete_transient( $this->preview_key( $token ) );
		$valid = Settings::validate( (array) $preview['values'], true );
		$diff  = $this->plugin->settings->update( $valid['values'] );
		$this->plugin->audit->log(
			'settings.imported',
			[
				'module'        => 'core',
				'resource_type' => 'settings',
				'diff'          => $diff,
			]
		);
		Layout::redirect( self::SLUG, 'imported' );
	}

	/**
	 * Chiave transient legata all'utente (un utente non può applicare l'anteprima di un altro).
	 *
	 * @param string $token Token.
	 */
	private function preview_key( string $token ): string {
		return 'bc_settings_preview_' . get_current_user_id() . '_' . substr( $token, 0, 20 );
	}

	/**
	 * Etichetta leggibile di una scelta.
	 *
	 * @param string $key    Chiave.
	 * @param string $choice Valore.
	 */
	public static function choice_label( string $key, string $choice ): string {
		if ( 'ui.default_palette' === $key ) {
			return Palettes::labels()[ $choice ] ?? $choice;
		}
		if ( 'ui.default_theme' === $key ) {
			$themes = [
				'auto'  => __( 'Automatico (sistema)', 'bracelli-club' ),
				'light' => __( 'Chiaro', 'bracelli-club' ),
				'dark'  => __( 'Scuro', 'bracelli-club' ),
			];
			return $themes[ $choice ] ?? $choice;
		}
		return $choice;
	}

	/**
	 * Valore leggibile per l'anteprima.
	 *
	 * @param string $key   Chiave.
	 * @param mixed  $value Valore.
	 */
	private static function format_value( string $key, $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? __( 'Sì', 'bracelli-club' ) : __( 'No', 'bracelli-club' );
		}
		return is_string( $value ) ? self::choice_label( $key, $value ) : '—';
	}
}
