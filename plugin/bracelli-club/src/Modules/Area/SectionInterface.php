<?php
/**
 * Contratto di una sezione dell'area riservata.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Area;

defined( 'ABSPATH' ) || exit;

/**
 * Una sezione (per esempio Corsi o Turni) aggiunge viste e azioni al portale unico.
 * La sezione decide, sul server, quali viste e azioni sono consentite all'utente.
 */
interface SectionInterface {

	/**
	 * Identificativo: prefisso delle azioni (es. "corsi" per "corsi_attendance").
	 */
	public function id(): string;

	/**
	 * Ordine: le sezioni con numero più basso compaiono prima e forniscono la vista iniziale.
	 */
	public function priority(): int;

	/**
	 * Viste consentite all'utente: vista => titolo della pagina.
	 *
	 * @param int $user Utente.
	 * @return array<string, string>
	 */
	public function views( int $user ): array;

	/**
	 * Schede di navigazione dell'utente: vista => etichetta.
	 *
	 * @param int $user Utente.
	 * @return array<string, string>
	 */
	public function tabs( int $user ): array;

	/**
	 * Scheda evidenziata per una vista secondaria (es. "modifica" → "turni").
	 *
	 * @param string $view Vista.
	 */
	public function tab_for( string $view ): string;

	/**
	 * Messaggi di esito propri della sezione: codice => [ tipo, testo ].
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function messages(): array;

	/**
	 * Esegue un'azione POST (nonce già verificato). Deve terminare con un redirect,
	 * oppure registrare errori di validazione sul portale per ripresentare il modulo.
	 *
	 * @param Portal $portal Portale.
	 * @param int    $user   Utente.
	 * @param string $action Azione senza prefisso.
	 * @param int    $id     Record dell'azione (0 se nessuno).
	 */
	public function handle( Portal $portal, int $user, string $action, int $id ): void;

	/**
	 * Mostra una vista consentita.
	 *
	 * @param Portal $portal Portale.
	 * @param int    $user   Utente.
	 * @param string $view   Vista.
	 */
	public function render( Portal $portal, int $user, string $view ): void;
}
