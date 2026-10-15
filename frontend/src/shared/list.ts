/**
 * Funzioni pure per ordinamento e visibilità (testate con Jest).
 */

/**
 * Ordina gli elementi secondo `order`; quelli non presenti seguono nell'ordine originale.
 *
 * @param items Elementi con id.
 * @param order Id nell'ordine preferito.
 */
export function applyOrder< T extends { id: string } >(
	items: T[],
	order: string[]
): T[] {
	const position = new Map( order.map( ( id, index ) => [ id, index ] ) );
	return items
		.map( ( item, index ) => ( { item, index } ) )
		.sort( ( a, b ) => {
			const pa = position.get( a.item.id );
			const pb = position.get( b.item.id );
			if ( pa !== undefined && pb !== undefined ) {
				return pa - pb;
			}
			if ( pa !== undefined ) {
				return -1;
			}
			if ( pb !== undefined ) {
				return 1;
			}
			return a.index - b.index;
		} )
		.map( ( entry ) => entry.item );
}

/**
 * Sposta un id di `delta` posizioni (-1 su, +1 giù) restando nei limiti.
 *
 * @param ids   Elenco ordinato.
 * @param id    Id da spostare.
 * @param delta Spostamento.
 */
export function move( ids: string[], id: string, delta: number ): string[] {
	const from = ids.indexOf( id );
	if ( from < 0 ) {
		return ids;
	}
	const to = Math.max( 0, Math.min( ids.length - 1, from + delta ) );
	if ( to === from ) {
		return ids;
	}
	const next = ids.slice();
	next.splice( from, 1 );
	next.splice( to, 0, id );
	return next;
}

/**
 * Sposta `id` nella posizione occupata da `targetId` (trascinamento).
 *
 * @param ids      Elenco ordinato.
 * @param id       Id trascinato.
 * @param targetId Id di destinazione.
 */
export function moveTo(
	ids: string[],
	id: string,
	targetId: string
): string[] {
	const from = ids.indexOf( id );
	const to = ids.indexOf( targetId );
	if ( from < 0 || to < 0 || from === to ) {
		return ids;
	}
	const next = ids.slice();
	next.splice( from, 1 );
	next.splice( to, 0, id );
	return next;
}

/**
 * Aggiunge o rimuove un id da un elenco.
 *
 * @param ids     Elenco.
 * @param id      Id.
 * @param present Se true lo aggiunge, altrimenti lo rimuove.
 */
export function toggle(
	ids: string[],
	id: string,
	present: boolean
): string[] {
	const without = ids.filter( ( value ) => value !== id );
	return present ? [ ...without, id ] : without;
}
