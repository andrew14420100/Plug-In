<?php
/**
 * Impostazioni globali del plugin, con schema, validazione, export e import con anteprima.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Settings;

use Bracelli\Club\Core\Ui\Palettes;

defined( 'ABSPATH' ) || exit;

/**
 * Le impostazioni sono un insieme chiuso dichiarato in schema(): chiavi sconosciute vengono ignorate.
 */
final class Settings {

	public const OPTION = 'bracelli_club_settings';

	public const EXPORT_FORMAT = 'bracelli-club-settings';

	public const SCHEMA_VERSION = 1;

	/**
	 * Schema: chiave => [ type, default, choices?, importable ].
	 *
	 * @return array<string, array{type: string, default: mixed, choices?: string[], importable: bool}>
	 */
	public static function schema(): array {
		return [
			'ui.default_theme'         => [
				'type'       => 'enum',
				'default'    => 'auto',
				'choices'    => Palettes::themes(),
				'importable' => true,
			],
			'ui.default_palette'       => [
				'type'       => 'enum',
				'default'    => Palettes::DEFAULT,
				'choices'    => Palettes::ids(),
				'importable' => true,
			],
			'ui.allow_hide_wp_menu'    => [
				'type'       => 'bool',
				'default'    => true,
				'importable' => true,
			],
			'dashboard.login_redirect' => [
				'type'       => 'bool',
				'default'    => true,
				'importable' => true,
			],
			'audit.store_ip_hash'      => [
				'type'       => 'bool',
				'default'    => true,
				'importable' => true,
			],
			'uninstall.delete_data'    => [
				'type'       => 'bool',
				'default'    => false,
				'importable' => false,
			],
		];
	}

	/**
	 * Etichette e descrizioni (tradotte, solo per l'interfaccia).
	 *
	 * @return array<string, array{label: string, help: string}>
	 */
	public static function labels(): array {
		return [
			'ui.default_theme'         => [
				'label' => __( 'Tema predefinito', 'bracelli-club' ),
				'help'  => __( 'Usato per chi non ha scelto un tema personale. "Automatico" segue il sistema operativo.', 'bracelli-club' ),
			],
			'ui.default_palette'       => [
				'label' => __( 'Tavolozza predefinita', 'bracelli-club' ),
				'help'  => __( 'Ogni utente può sceglierne una diversa dalla propria dashboard.', 'bracelli-club' ),
			],
			'ui.allow_hide_wp_menu'    => [
				'label' => __( 'Consenti di nascondere il menu WordPress', 'bracelli-club' ),
				'help'  => __( 'Il pulsante per riaprirlo resta sempre visibile; la modalità sicura lo ignora.', 'bracelli-club' ),
			],
			'dashboard.login_redirect' => [
				'label' => __( 'Apri la dashboard Bracelli dopo l\'accesso', 'bracelli-club' ),
				'help'  => __( 'Valore predefinito; ogni utente può disattivarlo nelle proprie preferenze.', 'bracelli-club' ),
			],
			'audit.store_ip_hash'      => [
				'label' => __( 'Registra l\'impronta dell\'indirizzo IP', 'bracelli-club' ),
				'help'  => __( 'Nel registro attività si salva solo un\'impronta non reversibile, mai l\'IP in chiaro.', 'bracelli-club' ),
			],
			'uninstall.delete_data'    => [
				'label' => __( 'Elimina tutti i dati alla disinstallazione', 'bracelli-club' ),
				'help'  => __( 'Se disattivato (predefinito), tabelle, impostazioni e ruoli restano nel database anche dopo l\'eliminazione del plugin. Non viene mai importato da file.', 'bracelli-club' ),
			],
		];
	}

	/**
	 * Valori predefiniti.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		$out = [];
		foreach ( self::schema() as $key => $def ) {
			$out[ $key ] = $def['default'];
		}
		return $out;
	}

	/**
	 * Tutti i valori correnti (predefiniti + salvati validi).
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		// Nessuna cache propria: get_option è già memorizzato da WordPress e resta coerente con gli aggiornamenti.
		$stored = get_option( self::OPTION, [] );
		$stored = is_array( $stored ) ? $stored : [];
		return array_merge( self::defaults(), self::validate( $stored )['values'] );
	}

	/**
	 * @param string $key Chiave dello schema.
	 * @return mixed
	 */
	public function get( string $key ) {
		$all = $this->all();
		return $all[ $key ] ?? null;
	}

	/**
	 * Valida un insieme parziale di valori.
	 *
	 * @param array<string, mixed> $input Valori da validare.
	 * @param bool                 $for_import Se true scarta le chiavi non importabili.
	 * @return array{values: array<string, mixed>, errors: array<string, string>, ignored: string[]}
	 */
	public static function validate( array $input, bool $for_import = false ): array {
		$schema  = self::schema();
		$values  = [];
		$errors  = [];
		$ignored = [];
		foreach ( $input as $key => $raw ) {
			$key = (string) $key;
			if ( ! isset( $schema[ $key ] ) || ( $for_import && ! $schema[ $key ]['importable'] ) ) {
				$ignored[] = $key;
				continue;
			}
			$def = $schema[ $key ];
			if ( 'bool' === $def['type'] ) {
				if ( is_bool( $raw ) ) {
					$values[ $key ] = $raw;
				} elseif ( in_array( $raw, [ 1, 0, '1', '0', 'true', 'false' ], true ) ) {
					$values[ $key ] = in_array( $raw, [ 1, '1', 'true' ], true );
				} else {
					$errors[ $key ] = 'Valore booleano non valido.';
				}
				continue;
			}
			if ( 'enum' === $def['type'] ) {
				if ( is_string( $raw ) && in_array( $raw, $def['choices'] ?? [], true ) ) {
					$values[ $key ] = $raw;
				} else {
					$errors[ $key ] = 'Valore non ammesso.';
				}
			}
		}
		return [
			'values'  => $values,
			'errors'  => $errors,
			'ignored' => $ignored,
		];
	}

	/**
	 * Salva i valori indicati (già validati). Restituisce le differenze: chiave => [ old, new ].
	 *
	 * @param array<string, mixed> $values Valori validi.
	 * @return array<string, array{0: mixed, 1: mixed}>
	 */
	public function update( array $values ): array {
		$current = $this->all();
		$diff    = self::diff( $current, $values );
		if ( [] === $diff ) {
			return [];
		}
		update_option( self::OPTION, array_merge( $current, $values ), true );
		return $diff;
	}

	/**
	 * Differenze tra valori correnti e proposti.
	 *
	 * @param array<string, mixed> $current Correnti.
	 * @param array<string, mixed> $next    Proposti.
	 * @return array<string, array{0: mixed, 1: mixed}>
	 */
	public static function diff( array $current, array $next ): array {
		$diff = [];
		foreach ( $next as $key => $value ) {
			$old = $current[ $key ] ?? null;
			if ( $old !== $value ) {
				$diff[ $key ] = [ $old, $value ];
			}
		}
		return $diff;
	}

	/**
	 * Dati esportabili (senza chiavi non importabili).
	 *
	 * @return array<string, mixed>
	 */
	public function export(): array {
		$settings = [];
		foreach ( $this->all() as $key => $value ) {
			if ( self::schema()[ $key ]['importable'] ) {
				$settings[ $key ] = $value;
			}
		}
		return [
			'format'         => self::EXPORT_FORMAT,
			'schema_version' => self::SCHEMA_VERSION,
			'plugin_version' => defined( 'BRACELLI_CLUB_VERSION' ) ? BRACELLI_CLUB_VERSION : '',
			'exported_at'    => gmdate( 'c' ),
			'settings'       => $settings,
		];
	}

	/**
	 * Analizza un file di importazione SENZA applicarlo (dry-run).
	 *
	 * @param string               $json    Contenuto JSON.
	 * @param array<string, mixed> $current Valori correnti.
	 * @return array{ok: bool, error: string, values: array<string, mixed>, changes: array<string, array{0: mixed, 1: mixed}>, errors: array<string, string>, ignored: string[]}
	 */
	public static function preview_import( string $json, array $current ): array {
		$empty = [
			'ok'      => false,
			'error'   => '',
			'values'  => [],
			'changes' => [],
			'errors'  => [],
			'ignored' => [],
		];
		if ( strlen( $json ) > 65536 ) {
			$empty['error'] = 'File troppo grande.';
			return $empty;
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || ( $data['format'] ?? '' ) !== self::EXPORT_FORMAT || ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			$empty['error'] = 'Il file non è un\'esportazione di impostazioni Bracelli Club.';
			return $empty;
		}
		if ( (int) ( $data['schema_version'] ?? 0 ) > self::SCHEMA_VERSION ) {
			$empty['error'] = 'Il file proviene da una versione più recente del plugin.';
			return $empty;
		}
		$result = self::validate( $data['settings'], true );
		return [
			'ok'      => true,
			'error'   => '',
			'values'  => $result['values'],
			'changes' => self::diff( $current, $result['values'] ),
			'errors'  => $result['errors'],
			'ignored' => $result['ignored'],
		];
	}
}
