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
		await expect( page.locator( 'h1' ) ).toHaveText( 'Panoramica segreteria' );
		await page.locator( 'a.bcp-tab' ).filter( { hasText: 'Archivio soci' } ).click();
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
		const row = page.locator( 'tbody tr', { hasText: 'Nicolò E2E Famiglia' } );
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
} );
