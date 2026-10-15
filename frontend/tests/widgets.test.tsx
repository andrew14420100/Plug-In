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

describe( 'Areas', () => {
	it( 'dichiara le aree non disponibili senza mostrare contatori', () => {
		const { container } = render(
			<Areas
				data={ {
					items: [
						{ id: 'members', label: 'Soci e tessere', phase: 2 },
						{ id: 'billing', label: 'Quote e incassi', phase: 5 },
					],
					note: 'Nessun dato disponibile.',
				} }
			/>
		);
		expect( screen.getAllByText( 'Non ancora disponibile' ) ).toHaveLength(
			2
		);
		const text = ( container.textContent ?? '' ).replace( /Fase \d/g, '' );
		expect( text ).not.toMatch( /\d/ );
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
			title: 'Aree gestionali',
			data: { items: [], note: '' },
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
					name: /Nascondi widget — Aree gestionali/,
				} )
			);
		} );
		expect( onChange ).toHaveBeenLastCalledWith( {
			hidden_widgets: [ 'areas' ],
		} );
	} );
} );
