import { test, expect, type Page } from '@playwright/test';
import { USERS, collectErrors } from './helpers';

const PORTAL = '/area-bracelli/';

async function portalLogin( page: Page ) {
	await page.context().clearCookies();
	await page.goto( PORTAL );
	await page.fill( '#bcp-log', USERS.staff.user );
	await page.fill( '#bcp-pwd', USERS.staff.pass );
	await page.click( 'button[type="submit"]' );
	await expect( page.locator( '.bcp-user__name' ) ).toBeVisible();
}

test.describe.serial( 'Archivio soci', () => {
	test( 'la segreteria crea le anagrafiche, collega un tutore, consulta lo storico e archivia senza cancellare', async ( {
		page,
	} ) => {
		const errors = collectErrors( page );
		await portalLogin( page );
		await expect( page.locator( 'h1' ) ).toHaveText(
			'Panoramica segreteria'
		);
		await page
			.locator( 'a.bcp-tab' )
			.filter( { hasText: 'Archivio soci' } )
			.click();
		await expect( page.locator( 'h1' ) ).toHaveText( 'Archivio soci' );

		await page.click( 'a:has-text("Aggiungi socio")' );
		await page.fill( '#bcp-first', 'Carla' );
		await page.fill( '#bcp-last', 'E2E Famiglia' );
		await page.fill( '#bcp-email', 'carla-famiglia-e2e@example.org' );
		await page.fill( '#bcp-phone', '3330001122' );
		await page.fill( '#bcp-birth', '1980-03-05' );
		await page.click( 'button:has-text("Salva anagrafica")' );
		await expect( page.locator( '.bcp-alert--success' ) ).toContainText(
			'Anagrafica creata'
		);

		await page.click( 'a:has-text("Aggiungi socio")' );
		await page.fill( '#bcp-first', 'Nicolò' );
		await page.fill( '#bcp-last', 'E2E Famiglia' );
		await page.fill( '#bcp-birth', '2014-04-03' );
		await page.selectOption( '#bcp-guardians', {
			label: 'E2E Famiglia Carla',
		} );
		await page.click( 'button:has-text("Salva anagrafica")' );
		await expect( page.locator( '.bcp-alert--success' ) ).toContainText(
			'Anagrafica creata'
		);

		await page.fill( '#bcp-soci-search', 'Nicolò' );
		await page.click( 'button:has-text("Cerca")' );
		const row = page.locator( 'tbody tr', {
			hasText: 'Nicolò E2E Famiglia',
		} );
		await expect( row ).toBeVisible();
		await row.locator( 'a:has-text("Apri scheda")' ).click();
		await expect( page.locator( 'h1' ) ).toHaveText( 'Scheda socio' );
		await expect( page.locator( '.bcp-soci-family' ) ).toContainText(
			'E2E Famiglia Carla'
		);
		await expect( page.locator( '.bcp-soci-history' ) ).toContainText(
			'Creazione'
		);

		await page.click( 'button:has-text("Archivia socio")' );
		await expect( page.locator( 'h1' ) ).toHaveText( 'Archivio soci' );
		await expect( page.locator( '.bcp-alert--success' ) ).toContainText(
			'Socio archiviato'
		);
		await page.selectOption( '#bcp-soci-status', 'archived' );
		await page.fill( '#bcp-soci-search', 'Nicolò' );
		await page.click( 'button:has-text("Cerca")' );
		await expect(
			page.locator( 'tbody tr', { hasText: 'Nicolò E2E Famiglia' } )
		).toContainText( 'Archiviato' );
		expect( errors ).toEqual( [] );
	} );

	test( 'importa un CSV solo dopo anteprima e conferma, poi esporta l’archivio', async ( {
		page,
	} ) => {
		const errors = collectErrors( page );
		await portalLogin( page );
		await page
			.locator( 'a.bcp-tab' )
			.filter( { hasText: 'Archivio soci' } )
			.click();
		await page.click( 'a:has-text("Importa CSV")' );
		const csv = [
			'Nome;Cognome;Email;Telefono;Data di nascita;Indirizzo;Comune;CAP;Stato;Note',
			'Marta;CSV E2E;import-csv-e2e@example.org;3331230000;2008-12-05;Via CSV 1;Roma;00100;Attivo;Record di test',
		].join( '\\n' );
		await page.setInputFiles( '#bcp-soci-csv', {
			name: 'importa-soci.csv',
			mimeType: 'text/csv',
			buffer: Buffer.from( csv ),
		} );
		await page.click( 'button:has-text("Carica e controlla")' );
		await expect( page.locator( 'h1' ) ).toHaveText( 'Importa soci da CSV' );
		await expect( page.locator( 'body' ) ).toContainText( 'Anteprima CSV' );
		await expect( page.locator( 'body' ) ).toContainText(
			'Nessuna modifica è stata ancora salvata'
		);
		await page.click( 'button:has-text("Conferma importazione")' );
		await expect( page.locator( 'body' ) ).toContainText(
			'Importazione CSV completata'
		);

		await page
			.locator( 'a.bcp-tab' )
			.filter( { hasText: 'Archivio soci' } )
			.click();
		await page.fill( '#bcp-soci-search', 'Marta' );
		await page.click( 'button:has-text("Cerca")' );
		await expect(
			page.locator( 'tbody tr', { hasText: 'Marta CSV E2E' } )
		).toBeVisible();

		const downloadPromise = page.waitForEvent( 'download' );
		await page
			.locator( '.bcp-soci-toolbar form' )
			.getByRole( 'button', { name: 'Esporta CSV' } )
			.click();
		const download = await downloadPromise;
		expect( download.suggestedFilename() ).toMatch( /^bracelli-soci-.*\.csv$/ );
		expect( errors ).toEqual( [] );
	} );

} );
