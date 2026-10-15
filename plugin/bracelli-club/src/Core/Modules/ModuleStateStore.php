<?php
/**
 * Persistenza dello stato attivo/inattivo dei moduli.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Archivio di stato dei moduli.
 */
interface ModuleStateStore {

	/**
	 * Restituisce lo stato salvato: id => [ 'active' => bool, 'changed_at' => string, 'changed_by' => int ].
	 *
	 * @return array<string, array{active: bool, changed_at: string, changed_by: int}>
	 */
	public function load(): array;

	/**
	 * Salva lo stato completo.
	 *
	 * @param array<string, array{active: bool, changed_at: string, changed_by: int}> $state Stato.
	 */
	public function save( array $state ): void;
}
