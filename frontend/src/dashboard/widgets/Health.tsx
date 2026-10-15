import { __ } from '@wordpress/i18n';

interface Issue {
	id: string;
	label: string;
	status: 'warning' | 'error';
	value: string;
}

interface HealthData {
	available: boolean;
	summary?: Record< string, number >;
	issues?: Issue[];
	php?: string;
	wp?: string;
	url?: string;
}

export default function Health( { data }: { data: HealthData } ) {
	if ( ! data.available || ! data.summary ) {
		return (
			<p className="bc-muted">
				{ __( 'Diagnostica non disponibile.', 'bracelli-club' ) }
			</p>
		);
	}
	return (
		<div>
			<div className="bc-stats">
				<div className="bc-stat is-ok">
					<span className="bc-stat-value">{ data.summary.ok }</span>
					<span className="bc-stat-label">
						{ __( 'controlli OK', 'bracelli-club' ) }
					</span>
				</div>
				<div
					className={ `bc-stat${ data.summary.warning > 0 ? ' is-warning' : '' }` }
				>
					<span className="bc-stat-value">
						{ data.summary.warning }
					</span>
					<span className="bc-stat-label">
						{ __( 'avvisi', 'bracelli-club' ) }
					</span>
				</div>
				<div
					className={ `bc-stat${ data.summary.error > 0 ? ' is-alert' : '' }` }
				>
					<span className="bc-stat-value">
						{ data.summary.error }
					</span>
					<span className="bc-stat-label">
						{ __( 'errori', 'bracelli-club' ) }
					</span>
				</div>
			</div>
			<p className="bc-muted bc-small">
				PHP { data.php } · WordPress { data.wp }
			</p>
			{ data.issues && data.issues.length > 0 && (
				<ul className="bc-list">
					{ data.issues.map( ( issue ) => (
						<li key={ issue.id }>
							<span
								className={ `bc-status bc-status--${ issue.status }` }
							>
								{ issue.label }
							</span>
							<span className="bc-muted">{ issue.value }</span>
						</li>
					) ) }
				</ul>
			) }
			{ data.url && (
				<p>
					<a href={ data.url }>
						{ __( 'Apri diagnostica', 'bracelli-club' ) }
					</a>
				</p>
			) }
		</div>
	);
}
