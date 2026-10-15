<?php
/**
 * Preferenze grafiche e di navigazione per singolo utente.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Ui;

use Bracelli\Club\Core\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Salvate in user meta e sempre validate lato server (Q175–177).
 */
final class Preferences {

	public const META = 'bracelli_club_prefs';

	public const MAX_FAVORITES = 20;

	/**
	 * Icone ammesse per i preferiti.
	 *
	 * @return string[]
	 */
	public static function icons(): array {
		return [ 'star', 'home', 'user', 'users', 'calendar', 'chart', 'settings', 'shield', 'document', 'image', 'grid', 'list', 'pulse', 'bell', 'bolt', 'heart', 'flag', 'lifebuoy' ];
	}

	/**
	 * @param Settings $settings Impostazioni globali.
	 */
	public function __construct( private Settings $settings ) {
	}

	/**
	 * Preferenze predefinite (derivate dalle impostazioni globali).
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [
			'theme'              => (string) $this->settings->get( 'ui.default_theme' ),
			'palette'            => (string) $this->settings->get( 'ui.default_palette' ),
			'hide_wp_menu'       => false,
			'login_to_dashboard' => (bool) $this->settings->get( 'dashboard.login_redirect' ),
			'widget_order'       => [],
			'hidden_widgets'     => [],
			'nav_order'          => [],
			'hidden_nav'         => [],
			'favorites'          => [],
		];
	}

	/**
	 * Preferenze effettive di un utente.
	 *
	 * @param int $user_id Utente.
	 * @return array<string, mixed>
	 */
	public function get( int $user_id ): array {
		$stored = get_user_meta( $user_id, self::META, true );
		$prefs  = $this->defaults();
		if ( is_array( $stored ) ) {
			$clean = self::sanitize( $stored, null, null, null );
			$prefs = array_merge( $prefs, $clean['values'] );
		}
		if ( ! $this->settings->get( 'ui.allow_hide_wp_menu' ) ) {
			$prefs['hide_wp_menu'] = false;
		}
		return $prefs;
	}

	/**
	 * Valida un aggiornamento parziale. I parametri null disattivano il controllo sull'elenco ammesso
	 * (usato solo per rileggere dati già salvati).
	 *
	 * @param array<string, mixed> $input              Valori proposti.
	 * @param string[]|null        $allowed_widgets    Widget ammessi per l'utente.
	 * @param string[]|null        $allowed_nav        Voci di menu ammesse.
	 * @param string[]|null        $allowed_favorites  Pagine candidabili come preferiti.
	 * @return array{values: array<string, mixed>, errors: array<string, string>}
	 */
	public static function sanitize( array $input, ?array $allowed_widgets, ?array $allowed_nav, ?array $allowed_favorites ): array {
		$values = [];
		$errors = [];

		if ( array_key_exists( 'theme', $input ) ) {
			if ( in_array( $input['theme'], Palettes::themes(), true ) ) {
				$values['theme'] = $input['theme'];
			} else {
				$errors['theme'] = 'Tema non valido.';
			}
		}
		if ( array_key_exists( 'palette', $input ) ) {
			if ( in_array( $input['palette'], Palettes::ids(), true ) ) {
				$values['palette'] = $input['palette'];
			} else {
				$errors['palette'] = 'Tavolozza non valida.';
			}
		}
		foreach ( [ 'hide_wp_menu', 'login_to_dashboard' ] as $flag ) {
			if ( array_key_exists( $flag, $input ) ) {
				if ( is_bool( $input[ $flag ] ) ) {
					$values[ $flag ] = $input[ $flag ];
				} else {
					$errors[ $flag ] = 'Valore booleano richiesto.';
				}
			}
		}
		foreach ( [
			'widget_order'   => $allowed_widgets,
			'hidden_widgets' => $allowed_widgets,
			'nav_order'      => $allowed_nav,
			'hidden_nav'     => $allowed_nav,
		] as $key => $allowed ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$list = self::id_list( $input[ $key ], $allowed );
			if ( null === $list ) {
				$errors[ $key ] = 'Elenco non valido.';
			} else {
				$values[ $key ] = $list;
			}
		}
		if ( array_key_exists( 'favorites', $input ) ) {
			$favorites = self::favorites( $input['favorites'], $allowed_favorites );
			if ( null === $favorites ) {
				$errors['favorites'] = 'Preferiti non validi.';
			} else {
				$values['favorites'] = $favorites;
			}
		}
		return [
			'values' => $values,
			'errors' => $errors,
		];
	}

	/**
	 * Salva le preferenze complete di un utente.
	 *
	 * @param int                  $user_id Utente.
	 * @param array<string, mixed> $values  Valori già validati.
	 * @return array<string, mixed> Preferenze risultanti.
	 */
	public function update( int $user_id, array $values ): array {
		$stored = get_user_meta( $user_id, self::META, true );
		$stored = is_array( $stored ) ? self::sanitize( $stored, null, null, null )['values'] : [];
		update_user_meta( $user_id, self::META, array_merge( $stored, $values ) );
		return $this->get( $user_id );
	}

	/**
	 * Cancella le preferenze personali (ritorno ai valori predefiniti).
	 *
	 * @param int $user_id Utente.
	 */
	public function reset( int $user_id ): void {
		delete_user_meta( $user_id, self::META );
	}

	/**
	 * @param mixed         $raw     Valore.
	 * @param string[]|null $allowed Id ammessi.
	 * @return string[]|null
	 */
	private static function id_list( $raw, ?array $allowed ): ?array {
		if ( ! is_array( $raw ) || count( $raw ) > 50 ) {
			return null;
		}
		$out = [];
		foreach ( $raw as $id ) {
			if ( ! is_string( $id ) || ! preg_match( '/^[a-z0-9_]{1,40}$/', $id ) ) {
				return null;
			}
			if ( null !== $allowed && ! in_array( $id, $allowed, true ) ) {
				continue;
			}
			if ( ! in_array( $id, $out, true ) ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * @param mixed         $raw     Valore.
	 * @param string[]|null $allowed Pagine ammesse.
	 * @return array<int, array{id: string, label: string, icon: string}>|null
	 */
	private static function favorites( $raw, ?array $allowed ): ?array {
		if ( ! is_array( $raw ) || count( $raw ) > self::MAX_FAVORITES ) {
			return null;
		}
		$out  = [];
		$seen = [];
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['id'] ) || ! is_string( $item['id'] ) ) {
				return null;
			}
			$id = $item['id'];
			if ( ! preg_match( '/^(bc|wp):[a-z0-9_.\-?=&]{1,80}$/i', $id ) ) {
				return null;
			}
			if ( ( null !== $allowed && ! in_array( $id, $allowed, true ) ) || isset( $seen[ $id ] ) ) {
				continue;
			}
			$label       = isset( $item['label'] ) && is_string( $item['label'] ) ? trim( wp_strip_all_tags( $item['label'] ) ) : '';
			$icon        = isset( $item['icon'] ) && in_array( $item['icon'], self::icons(), true ) ? $item['icon'] : 'star';
			$out[]       = [
				'id'    => $id,
				'label' => mb_substr( $label, 0, 60 ),
				'icon'  => $icon,
			];
			$seen[ $id ] = true;
		}
		return $out;
	}
}
