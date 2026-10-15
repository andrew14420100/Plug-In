<?php
/**
 * Registro delle pagine amministrative del plugin.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Core\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Tiene traccia degli hook suffix per caricare CSS/JS solo nelle pagine Bracelli.
 */
final class Pages {

	public const PARENT = 'bracelli';

	/**
	 * Slug pagina => hook suffix.
	 *
	 * @var array<string, string>
	 */
	private static array $hooks = [];

	/**
	 * Renderer della pagina principale (impostato dal modulo Dashboard).
	 *
	 * @var callable|null
	 */
	private static $home_renderer = null;

	/**
	 * Registra una sottopagina del menu Bracelli Club.
	 *
	 * @param string   $slug       Slug pagina.
	 * @param string   $title      Titolo pagina.
	 * @param string   $menu_title Voce di menu.
	 * @param string   $capability Capability richiesta (verificata anche da WordPress all'apertura).
	 * @param callable $render     Funzione di rendering.
	 * @param int|null $position   Posizione nel sottomenu.
	 */
	public static function add( string $slug, string $title, string $menu_title, string $capability, callable $render, ?int $position = null ): void {
		$hook = add_submenu_page( self::PARENT, $title, $menu_title, $capability, $slug, $render, $position );
		if ( is_string( $hook ) ) {
			self::$hooks[ $slug ] = $hook;
		}
	}

	/**
	 * Memorizza l'hook suffix di una pagina registrata altrove (es. menu principale).
	 *
	 * @param string       $slug Slug.
	 * @param string|false $hook Hook suffix.
	 */
	public static function remember( string $slug, $hook ): void {
		if ( is_string( $hook ) && '' !== $hook ) {
			self::$hooks[ $slug ] = $hook;
		}
	}

	/**
	 * @param string $slug Slug.
	 */
	public static function hook( string $slug ): ?string {
		return self::$hooks[ $slug ] ?? null;
	}

	/**
	 * @param string $hook_suffix Hook suffix corrente.
	 */
	public static function is_bracelli_hook( string $hook_suffix ): bool {
		return in_array( $hook_suffix, self::$hooks, true );
	}

	/**
	 * URL di una pagina del plugin.
	 *
	 * @param string               $slug Slug.
	 * @param array<string, mixed> $args Parametri aggiuntivi.
	 */
	public static function url( string $slug, array $args = [] ): string {
		return add_query_arg( array_merge( [ 'page' => $slug ], $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * @param callable $renderer Renderer.
	 */
	public static function set_home_renderer( callable $renderer ): void {
		self::$home_renderer = $renderer;
	}

	/**
	 * Renderer della pagina principale, se un modulo lo ha impostato.
	 */
	public static function home_renderer(): ?callable {
		return self::$home_renderer;
	}

	/**
	 * Azzeramento per i test.
	 */
	public static function reset(): void {
		self::$hooks         = [];
		self::$home_renderer = null;
	}
}
