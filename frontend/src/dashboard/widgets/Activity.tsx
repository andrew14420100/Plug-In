import { __ } from '@wordpress/i18n';
import { parseUtc, relativeTime } from '../../shared/theme';

interface ActivityItem {
	id: number;
	occurred_at: string;
	action: string;
	actor_label: string;
	resource: string;
}

export default function Activity( {
	data,
}: {
	data: { items: ActivityItem[]; total: number; url: string };
} ) {
	if ( data.items.length === 0 ) {
		return (
			<p className="bc-muted">
				{ __( 'Nessuna attività registrata.', 'bracelli-club' ) }
			</p>
		);
	}
	return (
		<div>
			<ol className="bc-timeline">
				{ data.items.map( ( item ) => {
					const date = parseUtc( item.occurred_at );
					return (
						<li key={ item.id }>
							<code>{ item.action }</code>
							<span className="bc-muted">
								{ item.actor_label }
								{ item.resource ? ` · ${ item.resource }` : '' }
							</span>
							<time
								dateTime={ date.toISOString() }
								title={ date.toLocaleString( 'it-IT' ) }
							>
								{ relativeTime( date ) }
							</time>
						</li>
					);
				} ) }
			</ol>
			<p>
				<a href={ data.url }>
					{ __( 'Apri il registro completo', 'bracelli-club' ) }
				</a>
			</p>
		</div>
	);
}
