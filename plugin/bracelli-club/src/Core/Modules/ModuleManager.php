<?php
/**
 * Gestione di stato, dipendenze e avvio dei moduli.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Regole (ADR-001 §4.2):
 * - i moduli obbligatori sono sempre attivi;
 * - l'attivazione richiede dipendenze attive e versioni compatibili;
 * - la disattivazione dei moduli da cui altri dipendono avviene a cascata SOLO con conferma;
 * - un'eccezione nel boot di un modulo lo porta in stato "degraded" senza fermare gli altri;
 * - nessuna operazione elimina dati.
 */
final class ModuleManager {

	public const STATUS_ACTIVE   = 'active';
	public const STATUS_INACTIVE = 'inactive';
	public const STATUS_BLOCKED  = 'blocked';
	public const STATUS_DEGRADED = 'degraded';

	/**
	 * Manifest indicizzati per id.
	 *
	 * @var array<string, ModuleManifest>
	 */
	private array $manifests = [];

	/**
	 * Moduli che hanno fallito il boot in questa richiesta: id => messaggio.
	 *
	 * @var array<string, string>
	 */
	private array $degraded = [];

	/**
	 * Moduli avviati in questa richiesta.
	 *
	 * @var array<string, ModuleInterface>
	 */
	private array $booted = [];

	/**
	 * @param ModuleManifest[]      $manifests Elenco dei manifest.
	 * @param ModuleStateStore      $store     Persistenza.
	 * @param array<string, string> $env       Versioni ambiente: [ 'php' => ..., 'wp' => ... ].
	 * @param callable|null         $actor     Restituisce l'id dell'utente corrente.
	 * @param callable|null         $clock     Restituisce la data UTC corrente (Y-m-d H:i:s).
	 * @throws \InvalidArgumentException Su id duplicati, dipendenze sconosciute o cicli.
	 */
	public function __construct(
		array $manifests,
		private ModuleStateStore $store,
		private array $env,
		private $actor = null,
		private $clock = null
	) {
		foreach ( $manifests as $manifest ) {
			if ( isset( $this->manifests[ $manifest->id ] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- messaggio interno; quando viene mostrato è escapato.
				throw new \InvalidArgumentException( sprintf( 'Modulo duplicato: %s', $manifest->id ) );
			}
			$this->manifests[ $manifest->id ] = $manifest;
		}
		$this->validate_graph();
	}

	/**
	 * @return array<string, ModuleManifest>
	 */
	public function manifests(): array {
		return $this->manifests;
	}

	/**
	 * @param string $id Id modulo.
	 */
	public function manifest( string $id ): ?ModuleManifest {
		return $this->manifests[ $id ] ?? null;
	}

	/**
	 * Indica se il modulo è abilitato (stato desiderato), indipendentemente da blocchi/errori.
	 *
	 * @param string $id Id modulo.
	 */
	public function is_enabled( string $id ): bool {
		$manifest = $this->manifests[ $id ] ?? null;
		if ( null === $manifest ) {
			return false;
		}
		if ( $manifest->required ) {
			return true;
		}
		$state = $this->state();
		return isset( $state[ $id ] ) ? $state[ $id ]['active'] : $manifest->default_active;
	}

	/**
	 * Stato effettivo del modulo.
	 *
	 * @param string $id Id modulo.
	 */
	public function status( string $id ): string {
		if ( ! $this->is_enabled( $id ) ) {
			return self::STATUS_INACTIVE;
		}
		if ( isset( $this->degraded[ $id ] ) ) {
			return self::STATUS_DEGRADED;
		}
		if ( [] !== $this->requirement_problems( $id ) ) {
			return self::STATUS_BLOCKED;
		}
		return self::STATUS_ACTIVE;
	}

	/**
	 * Indica se il modulo è attivo e funzionante.
	 *
	 * @param string $id Id modulo.
	 */
	public function is_active( string $id ): bool {
		return self::STATUS_ACTIVE === $this->status( $id );
	}

	/**
	 * Messaggio d'errore del boot, se il modulo è degradato.
	 *
	 * @param string $id Id modulo.
	 */
	public function degraded_reason( string $id ): ?string {
		return $this->degraded[ $id ] ?? null;
	}

	/**
	 * Problemi che impediscono l'attivazione/avvio del modulo.
	 *
	 * @param string $id Id modulo.
	 * @return string[]
	 */
	public function requirement_problems( string $id ): array {
		$manifest = $this->manifests[ $id ] ?? null;
		if ( null === $manifest ) {
			return [ sprintf( 'Modulo sconosciuto: %s', $id ) ];
		}
		$problems = [];
		if ( isset( $this->env['php'] ) && version_compare( $this->env['php'], $manifest->min_php, '<' ) ) {
			$problems[] = sprintf( 'Richiede PHP %s', $manifest->min_php );
		}
		if ( isset( $this->env['wp'] ) && version_compare( $this->env['wp'], $manifest->min_wp, '<' ) ) {
			$problems[] = sprintf( 'Richiede WordPress %s', $manifest->min_wp );
		}
		foreach ( $manifest->requires as $dep_id => $constraint ) {
			$dep = $this->manifests[ $dep_id ];
			if ( ! VersionConstraint::satisfies( $dep->version, $constraint ) ) {
				$problems[] = sprintf( 'Richiede %s %s (presente %s)', $dep_id, $constraint, $dep->version );
				continue;
			}
			if ( ! $this->is_enabled( $dep_id ) ) {
				$problems[] = sprintf( 'Richiede il modulo attivo: %s', $dep_id );
				continue;
			}
			if ( isset( $this->degraded[ $dep_id ] ) || [] !== $this->requirement_problems( $dep_id ) ) {
				$problems[] = sprintf( 'Il modulo richiesto non è operativo: %s', $dep_id );
			}
		}
		return $problems;
	}

	/**
	 * Moduli abilitati che dipendono (anche indirettamente) dal modulo indicato.
	 *
	 * @param string $id Id modulo.
	 * @return string[]
	 */
	public function enabled_dependents( string $id ): array {
		$result = [];
		$queue  = [ $id ];
		while ( [] !== $queue ) {
			$current = array_shift( $queue );
			foreach ( $this->manifests as $manifest ) {
				if ( isset( $manifest->requires[ $current ] ) && $this->is_enabled( $manifest->id ) && ! in_array( $manifest->id, $result, true ) ) {
					$result[] = $manifest->id;
					$queue[]  = $manifest->id;
				}
			}
		}
		return $result;
	}

	/**
	 * Attiva un modulo. Idempotente.
	 *
	 * @param string $id Id modulo.
	 * @return array{ok: bool, changed: string[], errors: string[]}
	 */
	public function activate( string $id ): array {
		$manifest = $this->manifests[ $id ] ?? null;
		if ( null === $manifest ) {
			return [
				'ok'      => false,
				'changed' => [],
				'errors'  => [ sprintf( 'Modulo sconosciuto: %s', $id ) ],
			];
		}
		if ( $this->is_enabled( $id ) ) {
			return [
				'ok'      => true,
				'changed' => [],
				'errors'  => [],
			];
		}
		$problems = $this->requirement_problems( $id );
		if ( [] !== $problems ) {
			return [
				'ok'      => false,
				'changed' => [],
				'errors'  => $problems,
			];
		}
		$this->write_state( [ $id ], true );
		return [
			'ok'      => true,
			'changed' => [ $id ],
			'errors'  => [],
		];
	}

	/**
	 * Disattiva un modulo. Se altri moduli dipendono da esso serve $cascade_confirmed.
	 *
	 * @param string $id                Id modulo.
	 * @param bool   $cascade_confirmed Conferma esplicita della disattivazione a cascata.
	 * @return array{ok: bool, changed: string[], errors: string[], needs_confirmation: string[]}
	 */
	public function deactivate( string $id, bool $cascade_confirmed = false ): array {
		$manifest = $this->manifests[ $id ] ?? null;
		if ( null === $manifest ) {
			return [
				'ok'                 => false,
				'changed'            => [],
				'errors'             => [ sprintf( 'Modulo sconosciuto: %s', $id ) ],
				'needs_confirmation' => [],
			];
		}
		if ( $manifest->required ) {
			return [
				'ok'                 => false,
				'changed'            => [],
				'errors'             => [ sprintf( 'Il modulo %s è obbligatorio e non può essere disattivato.', $id ) ],
				'needs_confirmation' => [],
			];
		}
		if ( ! $this->is_enabled( $id ) ) {
			return [
				'ok'                 => true,
				'changed'            => [],
				'errors'             => [],
				'needs_confirmation' => [],
			];
		}
		$dependents = $this->enabled_dependents( $id );
		foreach ( $dependents as $dependent ) {
			if ( $this->manifests[ $dependent ]->required ) {
				return [
					'ok'                 => false,
					'changed'            => [],
					'errors'             => [ sprintf( 'Il modulo obbligatorio %s dipende da %s.', $dependent, $id ) ],
					'needs_confirmation' => [],
				];
			}
		}
		if ( [] !== $dependents && ! $cascade_confirmed ) {
			return [
				'ok'                 => false,
				'changed'            => [],
				'errors'             => [],
				'needs_confirmation' => $dependents,
			];
		}
		$changed = array_merge( $dependents, [ $id ] );
		$this->write_state( $changed, false );
		return [
			'ok'                 => true,
			'changed'            => $changed,
			'errors'             => [],
			'needs_confirmation' => [],
		];
	}

	/**
	 * Avvia i moduli abilitati in ordine di dipendenza.
	 *
	 * @param callable(ModuleManifest): ?ModuleInterface $factory  Crea l'istanza del modulo.
	 * @param callable(ModuleInterface): void            $booter   Esegue il boot.
	 * @param bool                                       $safe_mode Se true avvia solo i moduli con safe_mode.
	 * @param callable(string, \Throwable): void|null    $on_error Notifica errori.
	 * @return string[] Moduli avviati.
	 */
	public function boot( callable $factory, callable $booter, bool $safe_mode = false, ?callable $on_error = null ): array {
		$started = [];
		foreach ( $this->boot_order() as $id ) {
			$manifest = $this->manifests[ $id ];
			if ( $safe_mode && ! $manifest->safe_mode ) {
				continue;
			}
			if ( ! $this->is_enabled( $id ) || [] !== $this->requirement_problems( $id ) ) {
				continue;
			}
			try {
				$module = $factory( $manifest );
				if ( null !== $module ) {
					$booter( $module );
					$this->booted[ $id ] = $module;
				}
				$started[] = $id;
			} catch ( \Throwable $e ) {
				$this->degraded[ $id ] = $e->getMessage();
				if ( null !== $on_error ) {
					$on_error( $id, $e );
				}
			}
		}
		return $started;
	}

	/**
	 * Istanza avviata di un modulo, se presente.
	 *
	 * @param string $id Id modulo.
	 */
	public function instance( string $id ): ?ModuleInterface {
		return $this->booted[ $id ] ?? null;
	}

	/**
	 * Ordine topologico: dipendenze prima dei dipendenti.
	 *
	 * @return string[]
	 */
	public function boot_order(): array {
		$order   = [];
		$visited = [];
		$visit   = function ( string $id ) use ( &$visit, &$order, &$visited ): void {
			if ( isset( $visited[ $id ] ) ) {
				return;
			}
			$visited[ $id ] = true;
			foreach ( array_keys( $this->manifests[ $id ]->requires ) as $dep ) {
				$visit( $dep );
			}
			$order[] = $id;
		};
		foreach ( array_keys( $this->manifests ) as $id ) {
			$visit( $id );
		}
		return $order;
	}

	/**
	 * Verifica dipendenze sconosciute e cicli.
	 *
	 * @throws \InvalidArgumentException Se il grafo non è valido.
	 */
	private function validate_graph(): void {
		foreach ( $this->manifests as $manifest ) {
			foreach ( array_keys( $manifest->requires ) as $dep ) {
				if ( ! isset( $this->manifests[ $dep ] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- messaggio interno; quando viene mostrato è escapato.
					throw new \InvalidArgumentException( sprintf( 'Il modulo %s richiede il modulo sconosciuto %s.', $manifest->id, $dep ) );
				}
			}
		}
		$state = [];
		$check = function ( string $id, array $path ) use ( &$check, &$state ): void {
			if ( 2 === ( $state[ $id ] ?? 0 ) ) {
				return;
			}
			if ( 1 === ( $state[ $id ] ?? 0 ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- messaggio interno; quando viene mostrato è escapato.
				throw new \InvalidArgumentException( 'Dipendenza circolare: ' . implode( ' -> ', array_merge( $path, [ $id ] ) ) );
			}
			$state[ $id ] = 1;
			foreach ( array_keys( $this->manifests[ $id ]->requires ) as $dep ) {
				$check( $dep, array_merge( $path, [ $id ] ) );
			}
			$state[ $id ] = 2;
		};
		foreach ( array_keys( $this->manifests ) as $id ) {
			$check( $id, [] );
		}
	}

	/**
	 * @return array<string, array{active: bool, changed_at: string, changed_by: int}>
	 */
	private function state(): array {
		// Lettura diretta: l'archivio su option è già in cache in WordPress e resta coerente con altre scritture.
		return $this->store->load();
	}

	/**
	 * @param string[] $ids    Moduli da aggiornare.
	 * @param bool     $active Nuovo stato.
	 */
	private function write_state( array $ids, bool $active ): void {
		$state = $this->state();
		$now   = null !== $this->clock ? (string) ( $this->clock )() : gmdate( 'Y-m-d H:i:s' );
		$actor = null !== $this->actor ? (int) ( $this->actor )() : 0;
		foreach ( $ids as $id ) {
			$state[ $id ] = [
				'active'     => $active,
				'changed_at' => $now,
				'changed_by' => $actor,
			];
		}
		$this->store->save( $state );
	}
}
