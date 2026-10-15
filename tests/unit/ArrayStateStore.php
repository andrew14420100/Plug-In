<?php
/**
 * Archivio di stato in memoria per i test.
 *
 * @package BracelliClub
 */

namespace Bracelli\Club\Tests\unit;

use Bracelli\Club\Core\Modules\ModuleStateStore;

final class ArrayStateStore implements ModuleStateStore {

	/** @var array<string, array{active: bool, changed_at: string, changed_by: int}> */
	public array $state = [];

	public int $saves = 0;

	public function load(): array {
		return $this->state;
	}

	public function save( array $state ): void {
		$this->state = $state;
		++$this->saves;
	}
}
