import { __, sprintf } from '@wordpress/i18n';
import { Fragment, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import type { AuditPage } from '../shared/types';
import { parseUtc } from '../shared/theme';
import { buildAuditPath, EMPTY_FILTERS, type AuditFilters } from './query';

const PER_PAGE = 25;

export default function AuditApp( { actions }: { actions: string[] } ) {
	const [ draft, setDraft ] = useState< AuditFilters >( EMPTY_FILTERS );
	const [ filters, setFilters ] = useState< AuditFilters >( EMPTY_FILTERS );
	const [ page, setPage ] = useState( 1 );
	const [ data, setData ] = useState< AuditPage | null >( null );
	const [ error, setError ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ open, setOpen ] = useState< number | null >( null );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );
		apiFetch< AuditPage >( {
			path: buildAuditPath( filters, page, PER_PAGE ),
		} )
			.then( ( result ) => {
				if ( ! cancelled ) {
					setData( result );
					setError( '' );
				}
			} )
			.catch( ( err: { message?: string } ) => {
				if ( ! cancelled ) {
					setError(
						err?.message ??
							__( 'Errore di caricamento.', 'bracelli-club' )
					);
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setLoading( false );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [ filters, page ] );

	const pages = data ? Math.max( 1, Math.ceil( data.total / PER_PAGE ) ) : 1;
	const field = ( key: keyof AuditFilters ) => ( {
		value: draft[ key ],
		onChange: ( e: { target: { value: string } } ) =>
			setDraft( { ...draft, [ key ]: e.target.value } ),
	} );

	return (
		<div className="bc-audit">
			<form
				className="bc-card bc-filters"
				onSubmit={ ( event ) => {
					event.preventDefault();
					setPage( 1 );
					setFilters( { ...draft } );
				} }
			>
				<div className="bc-filter">
					<label htmlFor="bc-audit-action">
						{ __( 'Azione', 'bracelli-club' ) }
					</label>
					<select id="bc-audit-action" { ...field( 'action' ) }>
						<option value="">
							{ __( 'Tutte', 'bracelli-club' ) }
						</option>
						{ actions.map( ( action ) => (
							<option key={ action } value={ action }>
								{ action }
							</option>
						) ) }
					</select>
				</div>
				<div className="bc-filter">
					<label htmlFor="bc-audit-from">
						{ __( 'Dal', 'bracelli-club' ) }
					</label>
					<input
						id="bc-audit-from"
						type="date"
						{ ...field( 'from' ) }
					/>
				</div>
				<div className="bc-filter">
					<label htmlFor="bc-audit-to">
						{ __( 'Al', 'bracelli-club' ) }
					</label>
					<input id="bc-audit-to" type="date" { ...field( 'to' ) } />
				</div>
				<div className="bc-filter bc-filter--grow">
					<label htmlFor="bc-audit-search">
						{ __( 'Cerca', 'bracelli-club' ) }
					</label>
					<input
						id="bc-audit-search"
						type="search"
						maxLength={ 100 }
						{ ...field( 'search' ) }
					/>
				</div>
				<div className="bc-filter bc-filter--actions">
					<button
						type="submit"
						className="bc-button bc-button--primary"
					>
						{ __( 'Filtra', 'bracelli-club' ) }
					</button>
					<button
						type="button"
						className="bc-button bc-button--ghost"
						onClick={ () => {
							setDraft( EMPTY_FILTERS );
							setFilters( EMPTY_FILTERS );
							setPage( 1 );
						} }
					>
						{ __( 'Azzera', 'bracelli-club' ) }
					</button>
				</div>
			</form>

			{ error && (
				<div className="bc-notice bc-notice--error" role="alert">
					<p>{ error }</p>
				</div>
			) }

			<div className="bc-card" aria-busy={ loading }>
				<p className="bc-muted" role="status">
					{ data
						? sprintf(
								/* translators: %d: numero di eventi. */
								__( '%d eventi trovati', 'bracelli-club' ),
								data.total
							)
						: __( 'Caricamento…', 'bracelli-club' ) }
				</p>
				<div className="bc-table-scroll">
					<table className="bc-table">
						<thead>
							<tr>
								<th scope="col">
									{ __( 'Data', 'bracelli-club' ) }
								</th>
								<th scope="col">
									{ __( 'Utente', 'bracelli-club' ) }
								</th>
								<th scope="col">
									{ __( 'Azione', 'bracelli-club' ) }
								</th>
								<th scope="col">
									{ __( 'Risorsa', 'bracelli-club' ) }
								</th>
								<th scope="col">
									<span className="screen-reader-text">
										{ __( 'Dettagli', 'bracelli-club' ) }
									</span>
								</th>
							</tr>
						</thead>
						<tbody>
							{ data && data.items.length === 0 && (
								<tr>
									<td colSpan={ 5 }>
										{ __(
											'Nessun evento con questi filtri.',
											'bracelli-club'
										) }
									</td>
								</tr>
							) }
							{ data?.items.map( ( item ) => {
								const date = parseUtc( item.occurred_at );
								const expanded = open === item.id;
								return (
									<Fragment key={ item.id }>
										<tr>
											<td>
												<time
													dateTime={ date.toISOString() }
												>
													{ date.toLocaleString(
														'it-IT'
													) }
												</time>
											</td>
											<td>{ item.actor_label }</td>
											<td>
												<code>{ item.action }</code>
											</td>
											<td>
												{ [
													item.resource_type,
													item.resource_id,
												]
													.filter( Boolean )
													.join( ' ' ) || '—' }
											</td>
											<td>
												{ item.diff !== null && (
													<button
														type="button"
														className="bc-button bc-button--ghost bc-button--small"
														aria-expanded={
															expanded
														}
														aria-controls={ `bc-audit-diff-${ item.id }` }
														onClick={ () =>
															setOpen(
																expanded
																	? null
																	: item.id
															)
														}
													>
														{ expanded
															? __(
																	'Chiudi',
																	'bracelli-club'
																)
															: __(
																	'Dettagli',
																	'bracelli-club'
																) }
													</button>
												) }
											</td>
										</tr>
										{ expanded && (
											<tr
												id={ `bc-audit-diff-${ item.id }` }
											>
												<td colSpan={ 5 }>
													<pre className="bc-code">
														{ JSON.stringify(
															item.diff,
															null,
															2
														) }
													</pre>
													<span className="bc-muted bc-small">
														{ __(
															'ID richiesta:',
															'bracelli-club'
														) }{ ' ' }
														<code>
															{ item.request_id }
														</code>
													</span>
												</td>
											</tr>
										) }
									</Fragment>
								);
							} ) }
						</tbody>
					</table>
				</div>
				<nav
					className="bc-pagination"
					aria-label={ __( 'Pagine del registro', 'bracelli-club' ) }
				>
					<button
						type="button"
						className="bc-button"
						disabled={ page <= 1 || loading }
						onClick={ () => setPage( page - 1 ) }
					>
						{ __( 'Precedente', 'bracelli-club' ) }
					</button>
					<span>
						{ sprintf(
							/* translators: 1: pagina corrente, 2: pagine totali. */
							__( 'Pagina %1$d di %2$d', 'bracelli-club' ),
							page,
							pages
						) }
					</span>
					<button
						type="button"
						className="bc-button"
						disabled={ page >= pages || loading }
						onClick={ () => setPage( page + 1 ) }
					>
						{ __( 'Successiva', 'bracelli-club' ) }
					</button>
				</nav>
			</div>
		</div>
	);
}
