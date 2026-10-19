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
							'Per iniziare: 1) apri Diagnostica e controlla gli avvisi; 2) in Moduli leggi le descrizioni e lascia attivi quelli obbligatori; 3) usa Impostazioni per le regole comuni. Personalizza cambia solo l’aspetto del tuo account. Area riservata e Corsi (catalogo, sale, orari e presenze degli insegnanti) sono in prova: usa solo account e dati fittizi. Soci, tessere, iscrizioni, prenotazioni, incassi e sincronizzazione non sono ancora gestiti.',
							'bracelli-club'
						)
					: __(
							'Qui trovi le pagine abilitate dal tuo ruolo. Personalizza cambia solo l’aspetto della tua dashboard. Se una voce manca o compare un avviso, chiedi all’amministratore.',
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
