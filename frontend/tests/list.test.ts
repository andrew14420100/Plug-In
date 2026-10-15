import { applyOrder, move, moveTo, toggle } from '../src/shared/list';

const items = [ { id: 'a' }, { id: 'b' }, { id: 'c' }, { id: 'd' } ];

describe( 'applyOrder', () => {
	it( 'segue l’ordine salvato e accoda gli elementi non elencati', () => {
		expect(
			applyOrder( items, [ 'c', 'a' ] ).map( ( i ) => i.id )
		).toEqual( [ 'c', 'a', 'b', 'd' ] );
	} );
	it( 'ignora id sconosciuti', () => {
		expect(
			applyOrder( items, [ 'zz', 'd' ] ).map( ( i ) => i.id )
		).toEqual( [ 'd', 'a', 'b', 'c' ] );
	} );
} );

describe( 'move / moveTo / toggle', () => {
	it( 'sposta entro i limiti', () => {
		expect( move( [ 'a', 'b', 'c' ], 'b', -1 ) ).toEqual( [
			'b',
			'a',
			'c',
		] );
		expect( move( [ 'a', 'b', 'c' ], 'a', -1 ) ).toEqual( [
			'a',
			'b',
			'c',
		] );
		expect( move( [ 'a', 'b', 'c' ], 'c', 5 ) ).toEqual( [
			'a',
			'b',
			'c',
		] );
		expect( move( [ 'a', 'b' ], 'x', 1 ) ).toEqual( [ 'a', 'b' ] );
	} );
	it( 'trascina nella posizione di destinazione', () => {
		expect( moveTo( [ 'a', 'b', 'c', 'd' ], 'a', 'c' ) ).toEqual( [
			'b',
			'c',
			'a',
			'd',
		] );
		expect( moveTo( [ 'a', 'b', 'c', 'd' ], 'd', 'a' ) ).toEqual( [
			'd',
			'a',
			'b',
			'c',
		] );
	} );
	it( 'aggiunge e rimuove senza duplicati', () => {
		expect( toggle( [ 'a' ], 'a', true ) ).toEqual( [ 'a' ] );
		expect( toggle( [ 'a', 'b' ], 'a', false ) ).toEqual( [ 'b' ] );
	} );
} );
