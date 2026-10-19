<?php
/**
 * Widget della dashboard: solo dati reali o stato "non disponibile".
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Dashboard;

use Bracelli\Club\Core\Admin\Navigation;
use Bracelli\Club\Core\Admin\Pages;
use Bracelli\Club\Core\Modules\ModuleManager;
use Bracelli\Club\Core\Permissions\Capabilities;
use Bracelli\Club\Modules\Audit\AuditRestController;
use Bracelli\Club\Modules\Diagnostics\Checks;
use Bracelli\Club\Modules\Diagnostics\DiagnosticsModule;
use Bracelli\Club\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Nessun contatore inventato: le aree delle fasi future dichiarano esplicitamente di non essere disponibili.
 */
final class Widgets {

	/**
	 * @param Plugin $plugin Servizi.
	 */
	public function __construct( private Plugin $plugin ) {
	}

	/**
	 * Contesto della dashboard dell'utente corrente.
	 */
	public static function context(): string {
		return current_user_can( Capabilities::DASHBOARD_ADMIN_VIEW ) ? 'admin' : 'staff';
	}

	/**
	 * Id dei widget disponibili per l'utente corrente, in ordine predefinito.
	 *
	 * @return string[]
	 */
	public function available_ids(): array {
		$ids = [ 'welcome', 'favorites', 'areas' ];
		if ( 'admin' === self::context() ) {
			$ids[] = 'modules';
		}
		if ( current_user_can( Capabilities::DIAGNOSTICS_RUN ) && $this->plugin->modules->is_active( 'diagnostics' ) ) {
			$ids[] = 'health';
		}
		if ( current_user_can( Capabilities::AUDIT_VIEW ) && $this->plugin->modules->is_active( 'audit' ) ) {
			$ids[] = 'activity';
		}
		return $ids;
	}

	/**
	 * Dati dei widget per l'utente corrente.
	 *
	 * @param array<string, mixed> $prefs Preferenze.
	 * @return array<int, array{id: string, title: string, data: array<string, mixed>}>
	 */
	public function build( array $prefs ): array {
		$titles = [
			'welcome'   => __( 'Benvenuto', 'bracelli-club' ),
			'favorites' => __( 'Preferiti', 'bracelli-club' ),
			'areas'     => __( 'Avanzamento del progetto', 'bracelli-club' ),
			'modules'   => __( 'Stato moduli', 'bracelli-club' ),
			'health'    => __( 'Salute del sistema', 'bracelli-club' ),
			'activity'  => __( 'Attività recenti', 'bracelli-club' ),
		];
		$out    = [];
		foreach ( $this->available_ids() as $id ) {
			$out[] = [
				'id'    => $id,
				'title' => $titles[ $id ],
				'data'  => $this->data( $id, $prefs ),
			];
		}
		return $out;
	}

	/**
	 * @param string               $id    Widget.
	 * @param array<string, mixed> $prefs Preferenze.
	 * @return array<string, mixed>
	 */
	private function data( string $id, array $prefs ): array {
		switch ( $id ) {
			case 'welcome':
				$user = wp_get_current_user();
				return [
					'name'      => $user->display_name,
					'context'   => self::context(),
					'today'     => wp_date( 'l j F Y' ),
					'safe_mode' => $this->plugin->is_safe_mode(),
					'version'   => BRACELLI_CLUB_VERSION,
				];
			case 'favorites':
				$candidates = Navigation::favorite_candidates();
				$items      = [];
				foreach ( (array) $prefs['favorites'] as $favorite ) {
					if ( isset( $candidates[ $favorite['id'] ] ) ) {
						$items[] = [
							'id'    => $favorite['id'],
							'label' => '' !== $favorite['label'] ? $favorite['label'] : $candidates[ $favorite['id'] ]['label'],
							'icon'  => $favorite['icon'],
							'url'   => $candidates[ $favorite['id'] ]['url'],
						];
					}
				}
				return [ 'items' => $items ];
			case 'areas':
				return Roadmap::widget();
			case 'modules':
				$items  = [];
				$counts = [
					ModuleManager::STATUS_ACTIVE   => 0,
					ModuleManager::STATUS_INACTIVE => 0,
					ModuleManager::STATUS_BLOCKED  => 0,
					ModuleManager::STATUS_DEGRADED => 0,
				];
				foreach ( $this->plugin->modules->manifests() as $mid => $manifest ) {
					$status = $this->plugin->modules->status( $mid );
					++$counts[ $status ];
					$items[] = [
						'id'       => $mid,
						'name'     => $manifest->name,
						'version'  => $manifest->version,
						'status'   => $status,
						'required' => $manifest->required,
					];
				}
				return [
					'items'  => $items,
					'counts' => $counts,
					'url'    => current_user_can( Capabilities::MODULES_MANAGE ) ? Pages::url( 'bracelli-modules' ) : '',
				];
			case 'health':
				$module = $this->plugin->modules->instance( 'diagnostics' );
				if ( ! $module instanceof DiagnosticsModule ) {
					return [ 'available' => false ];
				}
				$checks = $module->checks()->basic();
				$issues = array_values(
					array_filter(
						$checks,
						static fn( array $c ): bool => in_array( $c['status'], [ Checks::WARNING, Checks::ERROR ], true )
					)
				);
				return [
					'available' => true,
					'summary'   => Checks::summary( $checks ),
					'issues'    => array_slice( $issues, 0, 5 ),
					'php'       => PHP_VERSION,
					'wp'        => get_bloginfo( 'version' ),
					'url'       => Pages::url( DiagnosticsModule::SLUG ),
				];
			case 'activity':
				$result = $this->plugin->audit->query( [], 1, 6 );
				$items  = [];
				foreach ( $result['items'] as $item ) {
					$items[] = [
						'id'          => $item['id'],
						'occurred_at' => $item['occurred_at'],
						'action'      => $item['action'],
						'actor_label' => AuditRestController::actor_label( (int) $item['actor_id'], (string) $item['actor_type'] ),
						'resource'    => trim( $item['resource_type'] . ' ' . $item['resource_id'] ),
					];
				}
				return [
					'items' => $items,
					'total' => $result['total'],
					'url'   => Pages::url( 'bracelli-audit' ),
				];
		}
		return [];
	}
}
