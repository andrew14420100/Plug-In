<?php
/**
 * Avanzamento del progetto mostrato nella dashboard.
 *
 * @package BracelliClub
 */

declare(strict_types=1);

namespace Bracelli\Club\Modules\Dashboard;

defined( 'ABSPATH' ) || exit;

/**
 * Unica fonte delle fasi mostrate nella dashboard (widget "Avanzamento del progetto").
 *
 * Gli stati descrivono ciò che è davvero nel codice di questa versione, non la roadmap:
 * - completed: funzioni implementate, verificate dai test automatici e dalla CI;
 * - in_progress: funzioni implementate e verificate in locale, in collaudo o in attesa di approvazione;
 * - planned: pianificata, nessuna funzione ancora sviluppata.
 * Uno stato cambia solo con il rilascio che contiene le funzioni e le verifiche corrispondenti.
 */
final class Roadmap {

	public const COMPLETED   = 'completed';
	public const IN_PROGRESS = 'in_progress';
	public const PLANNED     = 'planned';

	/**
	 * Fasi del modulo Corsi, in ordine.
	 *
	 * @return array<int, array{id: string, number: int, title: string, description: string, status: string}>
	 */
	public static function corsi(): array {
		return [
			[
				'id'          => 'corsi-0',
				'number'      => 0,
				'title'       => __( 'Stabilizzazione e audit', 'bracelli-club' ),
				'description' => __( 'Correzioni di sicurezza, controlli e conservazione dei dati.', 'bracelli-club' ),
				'status'      => self::COMPLETED,
			],
			[
				'id'          => 'corsi-1',
				'number'      => 1,
				'title'       => __( 'Catalogo e sale', 'bracelli-club' ),
				'description' => __( 'Gestione dei corsi, modifica e archiviazione, duplicazione, sale e controllo delle sovrapposizioni.', 'bracelli-club' ),
				'status'      => self::IN_PROGRESS,
			],
			[
				'id'          => 'corsi-2',
				'number'      => 2,
				'title'       => __( 'Calendario, eccezioni e stagioni', 'bracelli-club' ),
				'description' => __( 'Festività, chiusure, sospensioni, spostamenti, recuperi e lezioni straordinarie.', 'bracelli-club' ),
				'status'      => self::PLANNED,
			],
			[
				'id'          => 'corsi-3',
				'number'      => 3,
				'title'       => __( 'Insegnanti, sostituzioni e presenze', 'bracelli-club' ),
				'description' => __( 'Più insegnanti per lezione, sostituzioni per singola data, stati delle lezioni, richieste e notifiche.', 'bracelli-club' ),
				'status'      => self::PLANNED,
			],
			[
				'id'          => 'corsi-4',
				'number'      => 4,
				'title'       => __( 'Calendari e report', 'bracelli-club' ),
				'description' => __( 'Vista giornaliera e settimanale, filtri, stampa, registri ed esportazioni.', 'bracelli-club' ),
				'status'      => self::PLANNED,
			],
		];
	}

	/**
	 * Aree del gestionale non ancora avviate (roadmap generale, docs/ROADMAP.md).
	 *
	 * @return array<int, array{id: string, label: string, status: string}>
	 */
	public static function other_areas(): array {
		$out = [];
		foreach ( [
			'members'   => __( 'Soci e tessere', 'bracelli-club' ),
			'bookings'  => __( 'Prenotazioni', 'bracelli-club' ),
			'billing'   => __( 'Quote e incassi', 'bracelli-club' ),
			'documents' => __( 'Documenti', 'bracelli-club' ),
			'site'      => __( 'Sito, SEO e moduli web', 'bracelli-club' ),
		] as $id => $label ) {
			$out[] = [
				'id'     => $id,
				'label'  => $label,
				'status' => self::PLANNED,
			];
		}
		return $out;
	}

	/**
	 * Dati del widget.
	 *
	 * @return array{phases: array<int, array<string, mixed>>, completed: int, total: int, areas: array<int, array<string, string>>, note: string}
	 */
	public static function widget(): array {
		$phases = self::corsi();
		return [
			'phases'    => $phases,
			'completed' => count( array_filter( $phases, static fn( array $p ): bool => self::COMPLETED === $p['status'] ) ),
			'total'     => count( $phases ),
			'areas'     => self::other_areas(),
			'note'      => __( 'Una fase risulta completata solo quando le sue funzioni sono implementate e verificate; "in corso" indica una fase sviluppata ma ancora in collaudo.', 'bracelli-club' ),
		];
	}
}
