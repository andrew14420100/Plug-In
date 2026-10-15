export interface AuditFilters {
	action: string;
	from: string;
	to: string;
	search: string;
}

export const EMPTY_FILTERS: AuditFilters = {
	action: '',
	from: '',
	to: '',
	search: '',
};

/**
 * Costruisce il percorso REST con i soli filtri valorizzati.
 *
 * @param filters Filtri.
 * @param page    Pagina.
 * @param perPage Righe per pagina.
 */
export function buildAuditPath(
	filters: AuditFilters,
	page: number,
	perPage: number
): string {
	const params = new URLSearchParams();
	params.set( 'page', String( page ) );
	params.set( 'per_page', String( perPage ) );
	( Object.keys( filters ) as Array< keyof AuditFilters > ).forEach(
		( key ) => {
			const value = filters[ key ].trim();
			if ( value !== '' ) {
				params.set( key, value );
			}
		}
	);
	return `/bracelli/v1/audit?${ params.toString() }`;
}
