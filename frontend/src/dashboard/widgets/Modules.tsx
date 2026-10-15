import { __ } from '@wordpress/i18n';

interface ModuleRow {
	id: string;
	name: string;
	version: string;
	status: 'active' | 'inactive' | 'blocked' | 'degraded';
	required: boolean;
}

interface ModulesData {
	items: ModuleRow[];
	counts: Record< string, number >;
	url: string;
}

const LABELS: Record< string, string > = {
	active: __( 'Attivo', 'bracelli-club' ),
	inactive: __( 'Disattivato', 'bracelli-club' ),
	blocked: __( 'Bloccato', 'bracelli-club' ),
	degraded: __( 'Errore', 'bracelli-club' ),
};

export default function Modules( { data }: { data: ModulesData } ) {
	return (
		<div>
			<div className="bc-stats">
				<div className="bc-stat">
					<span className="bc-stat-value">
						{ data.counts.active ?? 0 }
					</span>
					<span className="bc-stat-label">
						{ __( 'attivi', 'bracelli-club' ) }
					</span>
				</div>
				<div className="bc-stat">
					<span className="bc-stat-value">
						{ data.counts.inactive ?? 0 }
					</span>
					<span className="bc-stat-label">
						{ __( 'disattivati', 'bracelli-club' ) }
					</span>
				</div>
				<div
					className={ `bc-stat${ ( data.counts.degraded ?? 0 ) + ( data.counts.blocked ?? 0 ) > 0 ? ' is-alert' : '' }` }
				>
					<span className="bc-stat-value">
						{ ( data.counts.degraded ?? 0 ) +
							( data.counts.blocked ?? 0 ) }
					</span>
					<span className="bc-stat-label">
						{ __( 'con problemi', 'bracelli-club' ) }
					</span>
				</div>
			</div>
			<ul className="bc-list">
				{ data.items.map( ( item ) => (
					<li key={ item.id }>
						<span className="bc-grow">{ item.name }</span>
						<code>{ item.version }</code>
						<span
							className={ `bc-status bc-status--${ item.status }` }
						>
							{ LABELS[ item.status ] ?? item.status }
						</span>
					</li>
				) ) }
			</ul>
			{ data.url && (
				<p>
					<a href={ data.url }>
						{ __( 'Gestisci moduli', 'bracelli-club' ) }
					</a>
				</p>
			) }
		</div>
	);
}
