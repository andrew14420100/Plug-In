import {
	applyThemeAttributes,
	parseUtc,
	relativeTime,
	resolveTheme,
} from '../src/shared/theme';
import { buildAuditPath, EMPTY_FILTERS } from '../src/audit/query';

describe( 'tema', () => {
	it( 'risolve "auto" con la preferenza di sistema', () => {
		expect( resolveTheme( 'auto', true ) ).toBe( 'dark' );
		expect( resolveTheme( 'auto', false ) ).toBe( 'light' );
		expect( resolveTheme( 'light', true ) ).toBe( 'light' );
	} );
	it( 'applica gli attributi al contenitore', () => {
		const el = document.createElement( 'div' );
		applyThemeAttributes( el, 'dark', 'aurora' );
		expect( el.dataset.bcTheme ).toBe( 'dark' );
		expect( el.dataset.bcPalette ).toBe( 'aurora' );
		expect( () =>
			applyThemeAttributes( null, 'dark', 'aurora' )
		).not.toThrow();
	} );
} );

describe( 'date', () => {
	it( 'interpreta le date del server come UTC', () => {
		expect( parseUtc( '2026-10-15 08:30:00' ).toISOString() ).toBe(
			'2026-10-15T08:30:00.000Z'
		);
	} );
	it( 'formatta il tempo relativo in italiano', () => {
		const now = new Date( '2026-10-15T10:00:00Z' );
		expect( relativeTime( new Date( '2026-10-15T09:57:00Z' ), now ) ).toBe(
			'3 minuti fa'
		);
		expect( relativeTime( new Date( '2026-10-14T10:00:00Z' ), now ) ).toBe(
			'ieri'
		);
	} );
} );

describe( 'filtri registro', () => {
	it( 'include solo i filtri valorizzati e codifica i valori', () => {
		expect( buildAuditPath( EMPTY_FILTERS, 1, 25 ) ).toBe(
			'/bracelli/v1/audit?page=1&per_page=25'
		);
		const path = buildAuditPath(
			{ ...EMPTY_FILTERS, action: 'module.activated', search: ' a&b ' },
			2,
			10
		);
		expect( path ).toBe(
			'/bracelli/v1/audit?page=2&per_page=10&action=module.activated&search=a%26b'
		);
	} );
} );
