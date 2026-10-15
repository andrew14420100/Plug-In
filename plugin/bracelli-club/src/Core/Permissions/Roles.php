<?php
/**
 * Ruoli Bracelli e assegnazione delle capability.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Permissions;

defined( 'ABSPATH' ) || exit;

/**
 * Ruoli predefiniti (ADR-001 §6.1). L'amministratore WordPress riceve sempre tutte le capability.
 * I ruoli nativi diversi da "administrator" non vengono modificati.
 */
final class Roles {

	public const SEGRETERIA    = 'bracelli_segreteria';
	public const ISTRUTTORE    = 'bracelli_istruttore';
	public const GESTORE_CORSI = 'bracelli_gestore_corsi';

	/**
	 * Ruoli e capability predefinite. Le etichette non sono tradotte qui perché
	 * WordPress memorizza il nome del ruolo nel database.
	 *
	 * @return array<string, array{label: string, caps: string[]}>
	 */
	public static function definitions(): array {
		return [
			self::SEGRETERIA    => [
				'label' => 'Segreteria Bracelli',
				'caps'  => [ Capabilities::DASHBOARD_VIEW ],
			],
			self::ISTRUTTORE    => [
				'label' => 'Istruttore Bracelli',
				'caps'  => [ Capabilities::DASHBOARD_VIEW ],
			],
			self::GESTORE_CORSI => [
				'label' => 'Gestore corsi Bracelli',
				'caps'  => [ Capabilities::DASHBOARD_VIEW ],
			],
		];
	}

	/**
	 * @return string[]
	 */
	public static function slugs(): array {
		return array_keys( self::definitions() );
	}

	/**
	 * Crea i ruoli mancanti e garantisce tutte le capability all'amministratore. Idempotente.
	 */
	public static function sync(): void {
		foreach ( self::definitions() as $slug => $definition ) {
			if ( null === get_role( $slug ) ) {
				$caps = [ 'read' => true ];
				foreach ( $definition['caps'] as $cap ) {
					$caps[ $cap ] = true;
				}
				add_role( $slug, $definition['label'], $caps );
			}
		}
		$admin = get_role( 'administrator' );
		if ( null !== $admin ) {
			foreach ( Capabilities::names() as $cap ) {
				if ( ! $admin->has_cap( $cap ) ) {
					$admin->add_cap( $cap );
				}
			}
		}
	}

	/**
	 * Capability Bracelli attualmente assegnate a un ruolo.
	 *
	 * @param string $slug Ruolo.
	 * @return string[]
	 */
	public static function role_caps( string $slug ): array {
		$role = get_role( $slug );
		if ( null === $role ) {
			return [];
		}
		$caps = [];
		foreach ( Capabilities::names() as $cap ) {
			if ( ! empty( $role->capabilities[ $cap ] ) ) {
				$caps[] = $cap;
			}
		}
		return $caps;
	}

	/**
	 * Imposta le capability Bracelli assegnabili di un ruolo Bracelli.
	 * Ignora capability non assegnabili o sconosciute. Restituisce le differenze applicate.
	 *
	 * @param string   $slug Ruolo Bracelli.
	 * @param string[] $caps Capability desiderate.
	 * @return array{added: string[], removed: string[]}
	 * @throws \InvalidArgumentException Se il ruolo non è un ruolo Bracelli.
	 */
	public static function set_role_caps( string $slug, array $caps ): array {
		if ( ! in_array( $slug, self::slugs(), true ) ) {
			throw new \InvalidArgumentException( 'Ruolo non gestito dal plugin.' );
		}
		$role = get_role( $slug );
		if ( null === $role ) {
			throw new \InvalidArgumentException( 'Ruolo non installato.' );
		}
		$wanted  = array_values( array_filter( array_unique( $caps ), [ Capabilities::class, 'is_grantable' ] ) );
		$current = self::role_caps( $slug );
		$added   = [];
		$removed = [];
		foreach ( Capabilities::names() as $cap ) {
			if ( ! Capabilities::is_grantable( $cap ) ) {
				continue;
			}
			$has  = in_array( $cap, $current, true );
			$want = in_array( $cap, $wanted, true );
			if ( $want && ! $has ) {
				$role->add_cap( $cap );
				$added[] = $cap;
			} elseif ( ! $want && $has ) {
				$role->remove_cap( $cap );
				$removed[] = $cap;
			}
		}
		return [
			'added'   => $added,
			'removed' => $removed,
		];
	}

	/**
	 * Ripristina le capability predefinite di un ruolo Bracelli.
	 *
	 * @param string $slug Ruolo.
	 * @return array{added: string[], removed: string[]}
	 */
	public static function reset_role( string $slug ): array {
		$definitions = self::definitions();
		if ( ! isset( $definitions[ $slug ] ) ) {
			throw new \InvalidArgumentException( 'Ruolo non gestito dal plugin.' );
		}
		return self::set_role_caps( $slug, $definitions[ $slug ]['caps'] );
	}

	/**
	 * Rimuove ruoli e capability del plugin (solo disinstallazione con eliminazione dati).
	 */
	public static function remove_all(): void {
		foreach ( self::slugs() as $slug ) {
			remove_role( $slug );
		}
		$admin = get_role( 'administrator' );
		if ( null !== $admin ) {
			foreach ( Capabilities::names() as $cap ) {
				$admin->remove_cap( $cap );
			}
		}
	}
}
