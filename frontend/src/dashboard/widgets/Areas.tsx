import { __, sprintf } from '@wordpress/i18n';

export type PhaseStatus = 'completed' | 'in_progress' | 'planned';

export interface Phase {
	id: string;
	number: number;
	title: string;
	description: string;
	status: PhaseStatus;
}

export interface ProgressData {
	phases: Phase[];
	completed: number;
	total: number;
	areas: { id: string; label: string; status: PhaseStatus }[];
	note: string;
}

/**
 * Stato leggibile: il colore accompagna sempre un testo e un simbolo (non è l'unica informazione).
 * @param status
 */
function statusLabel( status: PhaseStatus ): string {
	switch ( status ) {
		case 'completed':
			return __( 'Completata', 'bracelli-club' );
		case 'in_progress':
			return __( 'In corso', 'bracelli-club' );
		default:
			return __( 'Pianificata', 'bracelli-club' );
	}
}

const MARKS: Record< PhaseStatus, string > = {
	completed: '✓',
	in_progress: '●',
	planned: '○',
};

/**
 * Avanzamento del progetto: fasi del modulo Corsi con stato reale e aree non ancora avviate.
 * Gli stati arrivano dal server (Roadmap.php): il componente non li deduce né li modifica.
 * @param root0
 * @param root0.data
 */
export default function Areas( { data }: { data: ProgressData } ) {
	const total = Math.max( data.total, 1 );
	return (
		<div className="bc-progress">
			<h4 className="bc-progress-title">
				{ __( 'Modulo Corsi', 'bracelli-club' ) }
			</h4>
			<div
				className="bc-progress-bar"
				role="progressbar"
				aria-valuemin={ 0 }
				aria-valuemax={ data.total }
				aria-valuenow={ data.completed }
				aria-valuetext={ sprintf(
					/* translators: 1: fasi completate, 2: fasi totali. */
					__( '%1$d di %2$d fasi completate', 'bracelli-club' ),
					data.completed,
					data.total
				) }
			>
				<span
					className="bc-progress-fill"
					style={ {
						width: `${ ( data.completed / total ) * 100 }%`,
					} }
				/>
			</div>
			<p className="bc-muted bc-small">
				{ sprintf(
					/* translators: 1: fasi completate, 2: fasi totali. */
					__( '%1$d di %2$d fasi completate', 'bracelli-club' ),
					data.completed,
					data.total
				) }
			</p>
			<ol className="bc-phases">
				{ data.phases.map( ( phase ) => (
					<li
						key={ phase.id }
						className={ `bc-phase is-${ phase.status }` }
					>
						<span className="bc-phase-mark" aria-hidden="true">
							{ MARKS[ phase.status ] }
						</span>
						<span className="bc-phase-body">
							<span className="bc-phase-title">
								{ sprintf(
									/* translators: 1: numero della fase, 2: nome della fase. */
									__( 'Fase %1$d — %2$s', 'bracelli-club' ),
									phase.number,
									phase.title
								) }
							</span>
							<span className="bc-phase-desc">
								{ phase.description }
							</span>
						</span>
						<span
							className={ `bc-badge bc-phase-state is-${ phase.status }` }
						>
							{ statusLabel( phase.status ) }
						</span>
					</li>
				) ) }
			</ol>
			{ data.areas.length > 0 && (
				<>
					<h4 className="bc-progress-title">
						{ __( 'Altre aree del gestionale', 'bracelli-club' ) }
					</h4>
					<ul className="bc-areas">
						{ data.areas.map( ( area ) => (
							<li key={ area.id } className="bc-area">
								<span className="bc-area-label">
									{ area.label }
								</span>
								<span className="bc-area-state">
									{ __(
										'Non ancora avviata',
										'bracelli-club'
									) }
								</span>
							</li>
						) ) }
					</ul>
				</>
			) }
			<p className="bc-muted bc-small">{ data.note }</p>
		</div>
	);
}
