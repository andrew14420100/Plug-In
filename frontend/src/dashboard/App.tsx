import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import type { DashboardPayload, Preferences, Theme } from '../shared/types';
import { applyThemeAttributes } from '../shared/theme';
import { resetPreferences, savePreferences } from './api';
import Sidebar from './Sidebar';
import Topbar from './Topbar';
import WidgetGrid from './WidgetGrid';
import Customize from './Customize';

type Status = 'idle' | 'saving' | 'saved' | 'error';

export default function App( { initial }: { initial: DashboardPayload } ) {
	const [ prefs, setPrefs ] = useState< Preferences >( initial.prefs );
	const [ editing, setEditing ] = useState( false );
	const [ status, setStatus ] = useState< Status >( 'idle' );
	const confirmed = useRef< Preferences >( initial.prefs );
	const timer = useRef< ReturnType< typeof setTimeout > | null >( null );
	// I salvataggi sono serializzati: una risposta lenta non può sovrascrivere una modifica successiva.
	const queue = useRef< Promise< unknown > >( Promise.resolve() );
	const sequence = useRef( 0 );

	useEffect( () => {
		applyThemeAttributes(
			document.querySelector< HTMLElement >( '.bc-dashboard-wrap' ),
			prefs.theme,
			prefs.palette
		);
	}, [ prefs.theme, prefs.palette ] );

	const flashSaved = () => {
		setStatus( 'saved' );
		if ( timer.current ) {
			clearTimeout( timer.current );
		}
		timer.current = setTimeout( () => setStatus( 'idle' ), 2000 );
	};

	const update = useCallback( ( partial: Partial< Preferences > ) => {
		setPrefs( ( current ) => ( { ...current, ...partial } ) );
		setStatus( 'saving' );
		const id = ++sequence.current;
		const request = queue.current.then( () => savePreferences( partial ) );
		queue.current = request.catch( () => null );
		return request
			.then( ( saved ) => {
				confirmed.current = saved;
				if ( id === sequence.current ) {
					setPrefs( saved );
					flashSaved();
				}
				return saved;
			} )
			.catch( () => {
				setPrefs( confirmed.current );
				setStatus( 'error' );
				return null;
			} );
	}, [] );

	const reset = () => {
		setStatus( 'saving' );
		resetPreferences()
			.then( ( saved ) => {
				confirmed.current = saved;
				setPrefs( saved );
				flashSaved();
			} )
			.catch( () => setStatus( 'error' ) );
	};

	const welcome = initial.widgets.find( ( w ) => w.id === 'welcome' )
		?.data as { name: string; today: string } | undefined;

	return (
		<div className="bc-shell">
			<Sidebar
				nav={ initial.nav }
				prefs={ prefs }
				allowHideWpMenu={ initial.options.allow_hide_wp_menu }
				wpDashboard={ initial.links.wp_dashboard }
				onToggleWpMenu={ () => {
					update( { hide_wp_menu: ! prefs.hide_wp_menu } ).then(
						( saved ) => {
							if ( saved ) {
								window.location.reload();
							}
						}
					);
				} }
			/>
			<main className="bc-main" id="bc-main">
				<Topbar
					name={ welcome?.name ?? '' }
					today={ welcome?.today ?? '' }
					context={ initial.context }
					prefs={ prefs }
					palettes={ initial.palettes }
					editing={ editing }
					status={ status }
					onTheme={ ( theme: Theme ) => update( { theme } ) }
					onPalette={ ( palette ) => update( { palette } ) }
					onToggleEdit={ () => setEditing( ! editing ) }
				/>
				{ status === 'error' && (
					<div className="bc-notice bc-notice--error" role="alert">
						<p>
							{ __(
								'Non è stato possibile salvare le preferenze. Verifica la connessione e riprova.',
								'bracelli-club'
							) }
						</p>
					</div>
				) }
				{ editing && (
					<Customize
						prefs={ prefs }
						nav={ initial.nav }
						candidates={ initial.candidates }
						icons={ initial.icons }
						allowHideWpMenu={ initial.options.allow_hide_wp_menu }
						onChange={ update }
						onReset={ reset }
						onClose={ () => setEditing( false ) }
					/>
				) }
				<WidgetGrid
					widgets={ initial.widgets }
					prefs={ prefs }
					candidates={ initial.candidates }
					editing={ editing }
					onChange={ update }
					onCustomize={ () => setEditing( true ) }
				/>
			</main>
		</div>
	);
}
