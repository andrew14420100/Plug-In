import { __, sprintf } from '@wordpress/i18n';

interface Area {
	id: string;
	label: string;
	phase: number;
}

/**
 * Aree delle fasi future: dichiarano esplicitamente che non ci sono dati (nessun contatore inventato).
 * @param root0
 * @param root0.data
 * @param root0.data.items
 * @param root0.data.note
 */
export default function Areas( {
	data,
}: {
	data: { items: Area[]; note: string };
} ) {
	return (
		<div>
			<ul className="bc-areas">
				{ data.items.map( ( area ) => (
					<li key={ area.id } className="bc-area">
						<span className="bc-area-label">{ area.label }</span>
						<span className="bc-area-state">
							{ __( 'Non ancora disponibile', 'bracelli-club' ) }
						</span>
						<span className="bc-badge">
							{ sprintf(
								/* translators: %d: numero della fase. */
								__( 'Fase %d', 'bracelli-club' ),
								area.phase
							) }
						</span>
					</li>
				) ) }
			</ul>
			<p className="bc-muted bc-small">{ data.note }</p>
		</div>
	);
}
