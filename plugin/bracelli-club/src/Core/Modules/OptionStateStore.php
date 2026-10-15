<?php
/**
 * Stato moduli salvato in una option autoload (una sola lettura per richiesta).
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Implementazione su wp_options.
 */
final class OptionStateStore implements ModuleStateStore {

	public const OPTION = 'bracelli_club_modules';

	/**
	 * {@inheritDoc}
	 */
	public function load(): array {
		$raw   = get_option( self::OPTION, [] );
		$state = [];
		if ( ! is_array( $raw ) ) {
			return $state;
		}
		foreach ( $raw as $id => $row ) {
			if ( ! is_string( $id ) || ! is_array( $row ) ) {
				continue;
			}
			$state[ $id ] = [
				'active'     => (bool) ( $row['active'] ?? false ),
				'changed_at' => (string) ( $row['changed_at'] ?? '' ),
				'changed_by' => (int) ( $row['changed_by'] ?? 0 ),
			];
		}
		return $state;
	}

	/**
	 * {@inheritDoc}
	 */
	public function save( array $state ): void {
		update_option( self::OPTION, $state, true );
	}
}
