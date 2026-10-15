<?php
/**
 * Registro delle capability del plugin.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Permissions;

defined( 'ABSPATH' ) || exit;

/**
 * Capability atomiche (ADR-001 §6.2). In Fase 1 esistono solo quelle del nucleo:
 * i moduli successivi aggiungeranno le proprie con una nuova migrazione.
 */
final class Capabilities {

	public const DASHBOARD_VIEW       = 'bracelli_dashboard_view';
	public const DASHBOARD_ADMIN_VIEW = 'bracelli_dashboard_admin_view';
	public const MODULES_MANAGE       = 'bracelli_modules_manage';
	public const SETTINGS_MANAGE      = 'bracelli_settings_manage';
	public const PERMISSIONS_MANAGE   = 'bracelli_permissions_manage';
	public const AUDIT_VIEW           = 'bracelli_audit_view';
	public const DIAGNOSTICS_RUN      = 'bracelli_diagnostics_run';

	/**
	 * Definizioni: capability => [ label, description, grantable, sensitive ].
	 * "grantable" = assegnabile ai ruoli Bracelli dalla pagina Permessi.
	 * "sensitive" = mostrata con avviso perché consente modifiche alla configurazione o accesso al registro.
	 *
	 * @return array<string, array{label: string, description: string, grantable: bool, sensitive: bool}>
	 */
	public static function all(): array {
		return [
			self::DASHBOARD_VIEW       => [
				'label'       => __( 'Accesso alla dashboard', 'bracelli-club' ),
				'description' => __( 'Vede la dashboard Bracelli (contesto dipendente).', 'bracelli-club' ),
				'grantable'   => true,
				'sensitive'   => false,
			],
			self::DASHBOARD_ADMIN_VIEW => [
				'label'       => __( 'Dashboard amministrativa', 'bracelli-club' ),
				'description' => __( 'Vede il contesto amministratore con stato moduli e sistema.', 'bracelli-club' ),
				'grantable'   => true,
				'sensitive'   => false,
			],
			self::MODULES_MANAGE       => [
				'label'       => __( 'Gestione moduli', 'bracelli-club' ),
				'description' => __( 'Attiva e disattiva i moduli facoltativi.', 'bracelli-club' ),
				'grantable'   => true,
				'sensitive'   => true,
			],
			self::SETTINGS_MANAGE      => [
				'label'       => __( 'Impostazioni', 'bracelli-club' ),
				'description' => __( 'Modifica, esporta e importa le impostazioni globali.', 'bracelli-club' ),
				'grantable'   => true,
				'sensitive'   => true,
			],
			self::PERMISSIONS_MANAGE   => [
				'label'       => __( 'Gestione permessi', 'bracelli-club' ),
				'description' => __( 'Assegna capability ai ruoli. Riservata agli amministratori.', 'bracelli-club' ),
				'grantable'   => false,
				'sensitive'   => true,
			],
			self::AUDIT_VIEW           => [
				'label'       => __( 'Registro attività', 'bracelli-club' ),
				'description' => __( 'Consulta il registro delle operazioni.', 'bracelli-club' ),
				'grantable'   => true,
				'sensitive'   => true,
			],
			self::DIAGNOSTICS_RUN      => [
				'label'       => __( 'Diagnostica', 'bracelli-club' ),
				'description' => __( 'Consulta lo stato del sistema e avvia la diagnostica approfondita.', 'bracelli-club' ),
				'grantable'   => true,
				'sensitive'   => false,
			],
		];
	}

	/**
	 * @return string[]
	 */
	public static function names(): array {
		return array_keys( self::definitions_without_labels() );
	}

	/**
	 * Elenco senza stringhe tradotte (utilizzabile prima di `init`).
	 *
	 * @return array<string, bool> capability => grantable
	 */
	public static function definitions_without_labels(): array {
		return [
			self::DASHBOARD_VIEW       => true,
			self::DASHBOARD_ADMIN_VIEW => true,
			self::MODULES_MANAGE       => true,
			self::SETTINGS_MANAGE      => true,
			self::PERMISSIONS_MANAGE   => false,
			self::AUDIT_VIEW           => true,
			self::DIAGNOSTICS_RUN      => true,
		];
	}

	/**
	 * @param string $cap Capability.
	 */
	public static function is_grantable( string $cap ): bool {
		return self::definitions_without_labels()[ $cap ] ?? false;
	}
}
