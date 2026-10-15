import { __, sprintf } from '@wordpress/i18n';

interface WelcomeData {
	name: string;
	context: 'admin' | 'staff';
	today: string;
	safe_mode: boolean;
	version: string;
}

export default function Welcome( { data }: { data: WelcomeData } ) {
	return (
		<div className="bc-welcome">
			<p>
				{ data.context === 'admin'
					? __(
							'Stai usando il contesto amministratore: vedi stato del sistema, moduli e registro attività.',
							'bracelli-club'
						)
					: __(
							'Stai usando il contesto dipendente: vedi i tuoi collegamenti rapidi e le aree a cui hai accesso.',
							'bracelli-club'
						) }
			</p>
			<p className="bc-muted">
				{ sprintf(
					/* translators: %s: versione del plugin. */
					__( 'Bracelli Club versione %s', 'bracelli-club' ),
					data.version
				) }
			</p>
			{ data.safe_mode && (
				<p className="bc-notice bc-notice--warning">
					{ __( 'Modalità sicura attiva.', 'bracelli-club' ) }
				</p>
			) }
		</div>
	);
}
