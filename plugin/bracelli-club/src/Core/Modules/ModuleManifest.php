<?php
/**
 * Descrizione dichiarativa di un modulo.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Manifest immutabile di un modulo (vedi ADR-001 §4.1).
 */
final class ModuleManifest {

	/**
	 * @param string                $id             Identificativo univoco ([a-z0-9_]).
	 * @param string                $version        Versione semver del modulo.
	 * @param string                $name           Nome leggibile.
	 * @param string                $description    Descrizione breve.
	 * @param string                $class_name     Classe che implementa ModuleInterface ('' se nessuna).
	 * @param bool                  $required       Se true il modulo non può essere disattivato.
	 * @param bool                  $default_active Stato iniziale per i moduli facoltativi.
	 * @param array<string, string> $requires       Dipendenze obbligatorie: id => vincolo di versione.
	 * @param string[]              $optional       Dipendenze facoltative (usate se attive).
	 * @param string[]              $capabilities   Capability dichiarate dal modulo.
	 * @param string                $min_php        Versione PHP minima.
	 * @param string                $min_wp         Versione WordPress minima.
	 * @param bool                  $safe_mode      Se true il modulo viene avviato anche in modalità sicura.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $version,
		public readonly string $name,
		public readonly string $description,
		public readonly string $class_name,
		public readonly bool $required,
		public readonly bool $default_active,
		public readonly array $requires,
		public readonly array $optional,
		public readonly array $capabilities,
		public readonly string $min_php,
		public readonly string $min_wp,
		public readonly bool $safe_mode
	) {
	}

	/**
	 * Crea un manifest da un array dichiarativo, validandone la forma.
	 *
	 * @param array<string, mixed> $data Dati del manifest.
	 * @return self
	 * @throws \InvalidArgumentException Se il manifest non è valido.
	 */
	public static function from_array( array $data ): self {
		$id = isset( $data['id'] ) && is_string( $data['id'] ) ? $data['id'] : '';
		if ( ! preg_match( '/^[a-z][a-z0-9_]{1,40}$/', $id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- messaggio interno; quando viene mostrato è escapato.
			throw new \InvalidArgumentException( 'Manifest: id modulo non valido.' );
		}
		$version = isset( $data['version'] ) && is_string( $data['version'] ) ? $data['version'] : '';
		if ( ! VersionConstraint::is_valid_version( $version ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- messaggio interno; quando viene mostrato è escapato.
			throw new \InvalidArgumentException( sprintf( 'Manifest %s: versione non valida.', $id ) );
		}
		$requires = [];
		foreach ( (array) ( $data['requires'] ?? [] ) as $dep => $constraint ) {
			if ( ! is_string( $dep ) || ! is_string( $constraint ) || ! VersionConstraint::is_valid_constraint( $constraint ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- messaggio interno; quando viene mostrato è escapato.
				throw new \InvalidArgumentException( sprintf( 'Manifest %s: dipendenza non valida.', $id ) );
			}
			$requires[ $dep ] = $constraint;
		}

		return new self(
			$id,
			$version,
			(string) ( $data['name'] ?? $id ),
			(string) ( $data['description'] ?? '' ),
			(string) ( $data['class'] ?? '' ),
			(bool) ( $data['required'] ?? false ),
			(bool) ( $data['default_active'] ?? true ),
			$requires,
			array_values( array_map( 'strval', (array) ( $data['optional'] ?? [] ) ) ),
			array_values( array_map( 'strval', (array) ( $data['capabilities'] ?? [] ) ) ),
			(string) ( $data['min_php'] ?? '8.1' ),
			(string) ( $data['min_wp'] ?? '6.0' ),
			(bool) ( $data['safe_mode'] ?? false )
		);
	}
}
