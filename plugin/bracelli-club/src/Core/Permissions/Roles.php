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
	public const DIPENDENTE    = 'bracelli_dipendente';
	public const SOCIO         = 'bracelli_socio';

	/**
	 * Ruoli e capability predefinite. Le etichette non sono tradotte qui perché
	 * WordPress memorizza il nome del ruolo nel database.
	 *
	 * Un ruolo "legacy" non si crea più e non si assegna a nuovi utenti, ma resta valido (con i suoi permessi)
	 * per chi lo ha già: lo si converte solo su conferma dell'amministratore (PermissionsModule).
	 *
	 * @return array<string, array{label: string, caps: string[], legacy?: bool}>
	 */
	public static function definitions(): array {
		return [
			self::SEGRETERIA    => [
				'label' => 'Segreteria Bracelli',
				'caps'  => [ Capabilities::DASHBOARD_VIEW, Capabilities::TURNI_MANAGE, Capabilities::CORSI_VIEW, Capabilities::CORSI_MANAGE, Capabilities::SOCI_MANAGE ],
			],
			// Insegnante: vede solo le proprie lezioni e registra la propria presenza.
			self::ISTRUTTORE    => [
				'label' => 'Istruttore Bracelli',
				'caps'  => [ Capabilities::DASHBOARD_VIEW, Capabilities::CORSI_VIEW, Capabilities::CORSI_TEACH ],
			],
			// Non più usato (i ruoli sono amministratore, segreteria, insegnanti e soci): resta solo per chi lo ha già.
			self::GESTORE_CORSI => [
				'label'  => 'Gestore corsi Bracelli',
				'caps'   => [ Capabilities::DASHBOARD_VIEW, Capabilities::CORSI_VIEW, Capabilities::CORSI_MANAGE ],
				'legacy' => true,
			],
			// Prototipo Turni e presenze: usa solo l'area turni, senza bacheca WordPress.
			self::DIPENDENTE    => [
				'label' => 'Dipendente Bracelli',
				'caps'  => [ Capabilities::TURNI_SELF ],
			],
			// Socio o cliente: per ora consulta il calendario dei corsi attivi.
			self::SOCIO         => [
				'label' => 'Socio Bracelli',
				'caps'  => [ Capabilities::CORSI_VIEW ],
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
	 * Ruoli Bracelli presenti sul sito (un ruolo legacy compare solo se esiste ancora).
	 *
	 * @return array<string, array{label: string, caps: string[], legacy?: bool}>
	 */
	public static function existing(): array {
		return array_filter( self::definitions(), static fn( string $slug ): bool => null !== get_role( $slug ), ARRAY_FILTER_USE_KEY );
	}

	/**
	 * Ruoli assegnabili nella schermata Utenti: un ruolo legacy resta solo per un utente che lo ha già,
	 * così salvare il suo profilo non gli cambia ruolo; non è proponibile a nessun altro.
	 *
	 * @param array<string, mixed> $roles Ruoli modificabili.
	 * @return array<string, mixed>
	 */
	public static function filter_editable( array $roles ): array {
		// phpcs:ignore WordPress.Security.NonceVerification -- serve solo a capire quale utente si sta modificando; WordPress verifica il resto.
		$target = isset( $_REQUEST['user_id'] ) ? absint( $_REQUEST['user_id'] ) : 0;
		foreach ( self::definitions() as $slug => $definition ) {
			if ( empty( $definition['legacy'] ) || ! isset( $roles[ $slug ] ) ) {
				continue;
			}
			$user = $target > 0 ? get_userdata( $target ) : false;
			if ( false === $user || ! in_array( $slug, (array) $user->roles, true ) ) {
				unset( $roles[ $slug ] );
			}
		}
		return $roles;
	}

	/**
	 * Crea i ruoli mancanti e garantisce tutte le capability all'amministratore. Idempotente.
	 */
	public static function sync(): void {
		foreach ( self::definitions() as $slug => $definition ) {
			if ( null === get_role( $slug ) && empty( $definition['legacy'] ) ) {
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
				if ( ! in_array( $cap, Capabilities::NOT_FOR_ADMIN, true ) && ! $admin->has_cap( $cap ) ) {
					$admin->add_cap( $cap );
				}
			}
		}
	}

	/**
	 * Aggiunge capability a un ruolo esistente senza toglierne altre (usato dalle migrazioni dei moduli).
	 *
	 * @param string   $slug Ruolo.
	 * @param string[] $caps Capability da aggiungere.
	 */
	public static function grant( string $slug, array $caps ): void {
		$role = get_role( $slug );
		if ( null === $role ) {
			return;
		}
		foreach ( $caps as $cap ) {
			if ( ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
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
