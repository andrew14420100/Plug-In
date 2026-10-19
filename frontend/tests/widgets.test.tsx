import { act, fireEvent, render, screen } from '@testing-library/react';
import Areas from '../src/dashboard/widgets/Areas';
import Favorites from '../src/dashboard/widgets/Favorites';
import WidgetGrid from '../src/dashboard/WidgetGrid';
import type { Preferences, Widget } from '../src/shared/types';

const basePrefs: Preferences = {
	theme: 'auto',
	palette: 'bracelli',
	hide_wp_menu: false,
	login_to_dashboard: true,
	widget_order: [],
	hidden_widgets: [],
	nav_order: [],
	hidden_nav: [],
	favorites: [],
};

describe( 'Areas (avanzamento del progetto)', () => {
	const data = {
		phases: [
			{
				id: 'corsi-0',
				number: 0,
				title: 'Stabilizzazione e audit',
				description: 'Correzioni di sicurezza.',
				status: 'completed' as const,
			},
			{
				id: 'corsi-1',
				number: 1,
				title: 'Catalogo e sale',
				description: 'Corsi e sale.',
				status: 'in_progress' as const,
			},
			{
				id: 'corsi-2',
				number: 2,
				title: 'Calendario, eccezioni e stagioni',
				description: 'Festività e chiusure.',
				status: 'planned' as const,
			},
		],
		completed: 1,
		total: 3,
		areas: [
			{
				id: 'members',
				label: 'Soci e tessere',
				status: 'planned' as const,
			},
		],
		note: 'Nota.',
	};

	it( 'mostra le fasi in ordine con lo stato dichiarato dal server, in testo', () => {
		render( <Areas data={ data } /> );
		const items = screen.getAllByRole( 'listitem' );
		expect( items[ 0 ].textContent ).toContain(
			'Fase 0 — Stabilizzazione e audit'
		);
		expect( items[ 0 ].textContent ).toContain( 'Completata' );
		expect( items[ 1 ].textContent ).toContain( 'In corso' );
		expect( items[ 2 ].textContent ).toContain( 'Pianificata' );
		expect( screen.getByText( 'Non ancora avviata' ) ).toBeTruthy();
	} );

	it( "l'indicatore conta solo le fasi completate", () => {
		render( <Areas data={ data } /> );
		const bar = screen.getByRole( 'progressbar' );
		expect( bar.getAttribute( 'aria-valuenow' ) ).toBe( '1' );
		expect( bar.getAttribute( 'aria-valuemax' ) ).toBe( '3' );
		expect( screen.getByText( '1 di 3 fasi completate' ) ).toBeTruthy();
	} );
} );

describe( 'Favorites', () => {
	it( 'mostra solo i preferiti che corrispondono a pagine consentite', () => {
		render(
			<Favorites
				favorites={ [
					{ id: 'wp:profile.php', label: '', icon: 'user' },
					{ id: 'wp:plugins.php', label: 'Plugin', icon: 'grid' },
				] }
				candidates={ [
					{
						id: 'wp:profile.php',
						label: 'Il mio profilo',
						url: '/wp-admin/profile.php',
						icon: 'user',
					},
				] }
				onCustomize={ () => undefined }
			/>
		);
		expect(
			screen
				.getByRole( 'link', { name: 'Il mio profilo' } )
				.getAttribute( 'href' )
		).toBe( '/wp-admin/profile.php' );
		expect( screen.queryByText( 'Plugin' ) ).toBeNull();
	} );
} );

describe( 'WidgetGrid', () => {
	const widgets: Widget[] = [
		{
			id: 'welcome',
			title: 'Benvenuto',
			data: {
				name: 'Prova',
				context: 'staff',
				today: '',
				safe_mode: false,
				version: '0.1.0',
			},
		},
		{
			id: 'areas',
			title: 'Avanzamento del progetto',
			data: { phases: [], completed: 0, total: 0, areas: [], note: '' },
		},
		{ id: 'favorites', title: 'Preferiti', data: {} },
	];

	it( 'rispetta ordine e widget nascosti', () => {
		render(
			<WidgetGrid
				widgets={ widgets }
				prefs={ {
					...basePrefs,
					widget_order: [ 'favorites', 'welcome' ],
					hidden_widgets: [ 'areas' ],
				} }
				candidates={ [] }
				editing={ false }
				onChange={ () => undefined }
				onCustomize={ () => undefined }
			/>
		);
		const titles = screen
			.getAllByRole( 'heading', { level: 3 } )
			.map( ( h ) => h.textContent );
		expect( titles ).toEqual( [ 'Preferiti', 'Benvenuto' ] );
	} );

	it( 'in modifica consente di riordinare e nascondere con pulsanti accessibili', () => {
		const onChange = jest.fn();
		render(
			<WidgetGrid
				widgets={ widgets }
				prefs={ basePrefs }
				candidates={ [] }
				editing
				onChange={ onChange }
				onCustomize={ () => undefined }
			/>
		);
		act( () => {
			fireEvent.click(
				screen.getByRole( 'button', {
					name: /Sposta dopo — Benvenuto/,
				} )
			);
		} );
		expect( onChange ).toHaveBeenLastCalledWith( {
			widget_order: [ 'areas', 'welcome', 'favorites' ],
		} );
		act( () => {
			fireEvent.click(
				screen.getByRole( 'button', {
					name: /Nascondi widget — Avanzamento del progetto/,
				} )
			);
		} );
		expect( onChange ).toHaveBeenLastCalledWith( {
			hidden_widgets: [ 'areas' ],
		} );
	} );
} );
