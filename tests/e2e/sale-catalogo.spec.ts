import { test, expect, type Page } from '@playwright/test';
import { USERS, collectErrors, shot } from './helpers';

/**
 * Fase 1 del modulo Corsi: sale, catalogo e blocco delle sovrapposizioni.
 * Gira dopo corsi.spec.ts (ordine alfabetico) e usa solo corsi e sale fittizi creati qui.
 */
const PORTAL = '/area-bracelli/';

async function staffLogin( page: Page ) {
	await page.context().clearCookies();
	await page.goto( PORTAL );
	await page.fill( '#bcp-log', USERS.staff.user );
	await page.fill( '#bcp-pwd', USERS.staff.pass );
	await page.click( 'button[type="submit"]' );
	await expect( page.locator( '.bcp-user__name' ) ).toBeVisible();
}

async function newCourse( page: Page, name: string ) {
	await page.click( 'a.bcp-tab:has-text("Corsi e orari")' );
	await page.click( 'a:has-text("+ Nuovo corso")' );
	await page.fill( '#bcp-nome', name );
	await page.fill( '#bcp-section', 'Sezione Prova' );
	await page.check( 'input[name="color"][value="#2f6fd6"]' );
	await page.click( 'button:has-text("Crea il corso")' );
	await expect( page.locator( '.bcp-alert--success' ) ).toContainText(
		'Corso creato'
	);
}

async function addSlot( page: Page, start: string, end: string, room: string ) {
	await page.click( 'a:has-text("+ Aggiungi orario")' );
	await page.selectOption( '#bcp-weekday', '1' );
	await page.fill( '#bcp-start_time', start );
	await page.fill( '#bcp-end_time', end );
	await page.selectOption( '#bcp-room', { label: room } );
	await page.click( 'button:has-text("Aggiungi orario")' );
}

test.describe.serial( 'Catalogo dei corsi e sale', () => {
	test( 'la segreteria crea le sale (nessuna sala esiste in partenza)', async ( {
		page,
	} ) => {
		const errors = collectErrors( page );
		await staffLogin( page );
		await page.click( 'a.bcp-tab:has-text("Sale")' );
		await expect( page.locator( 'body' ) ).toContainText(
			'Nessuna sala inserita'
		);
		for ( const name of [ 'Sala Prova E2E 1', 'Sala Prova E2E 2' ] ) {
			await page.fill( '#bcp-nome', name );
			await page.click( 'button:has-text("Aggiungi sala")' );
			await expect( page.locator( '.bcp-alert--success' ) ).toContainText(
				'Sala aggiunta'
			);
		}
		await page.fill( '#bcp-nome', 'sala prova e2e 1' );
		await page.click( 'button:has-text("Aggiungi sala")' );
		await expect( page.locator( '#bcp-err-nome' ) ).toContainText(
			'Esiste già'
		);
		await expect( page.locator( 'tbody tr' ) ).toHaveCount( 2 );
		await shot( page, 'catalogo-sale' );
		expect( errors ).toEqual( [] );
	} );

	test( 'una sala già occupata nella stessa fascia blocca il salvataggio', async ( {
		page,
	} ) => {
		const errors = collectErrors( page );
		await staffLogin( page );
		await newCourse( page, 'Corso Prova Catalogo' );
		await addSlot( page, '15:00', '16:00', 'Sala Prova E2E 1' );
		await expect( page.locator( '.bcp-alert--success' ) ).toContainText(
			'Orario aggiunto'
		);

		await newCourse( page, 'Corso Prova Sovrapposto' );
		await addSlot( page, '15:30', '16:30', 'Sala Prova E2E 1' );
		await expect( page.locator( '#bcp-err-room_id' ) ).toContainText(
			'Corso Prova Catalogo'
		);
		await expect( page.locator( '#bcp-start_time' ) ).toHaveValue(
			'15:30'
		);
		await shot( page, 'catalogo-conflitto-sala' );
		await page.selectOption( '#bcp-room', { label: 'Sala Prova E2E 2' } );
		await page.click( 'button:has-text("Aggiungi orario")' );
		await expect( page.locator( '.bcp-alert--success' ) ).toContainText(
			'Orario aggiunto'
		);
		expect( errors ).toEqual( [] );
	} );

	test( 'il corso duplicato ha orari da confermare, controllati sulla sala', async ( {
		page,
	} ) => {
		await staffLogin( page );
		await page.goto( PORTAL + '?vista=orari&q=Catalogo' );
		const source = page.locator( 'article.bcp-course', {
			hasText: 'Corso Prova Catalogo',
		} );
		await source.locator( 'a:has-text("Duplica")' ).click();
		await page.fill( '#bcp-group_label', 'ragazzi' );
		await page.click( 'button:has-text("Crea il corso")' );
		await expect( page.locator( '.bcp-alert--success' ) ).toContainText(
			'da confermare'
		);
		await page.goto( PORTAL + '?vista=orari&q=ragazzi' );
		const copy = page.locator( 'article.bcp-course', {
			hasText: 'ragazzi',
		} );
		await expect( copy.locator( '.bcp-badge--draft' ) ).toHaveCount( 1 );
		await copy.locator( 'button:has-text("Conferma")' ).click();
		await expect( page.locator( '.bcp-alert--error' ) ).toContainText(
			'Sala già occupata'
		);
		await page.fill( '#bcp-start_time', '17:00' );
		await page.fill( '#bcp-end_time', '18:00' );
		await page.click( 'button:has-text("Salva modifiche")' );
		await page.goto( PORTAL + '?vista=orari&q=ragazzi' );
		await page
			.locator( 'article.bcp-course', { hasText: 'ragazzi' } )
			.locator( 'button:has-text("Conferma")' )
			.click();
		await expect( page.locator( '.bcp-alert--success' ) ).toContainText(
			'Orario confermato'
		);
		await shot( page, 'catalogo-corsi' );
	} );

	test( 'un corso archiviato esce dal calendario e resta consultabile', async ( {
		page,
	} ) => {
		await staffLogin( page );
		await page.goto( PORTAL + '?vista=orari&q=Sovrapposto' );
		await page
			.locator( 'article.bcp-course', {
				hasText: 'Corso Prova Sovrapposto',
			} )
			.locator( 'a:has-text("Archivia")' )
			.click();
		await page.click( 'button:has-text("Archivia il corso")' );
		await expect( page.locator( '.bcp-alert--success' ) ).toContainText(
			'Corso archiviato'
		);
		await page.goto( PORTAL + '?vista=corsi' );
		await expect( page.locator( '.bcp-week' ) ).toContainText(
			'Corso Prova Catalogo'
		);
		await expect( page.locator( 'body' ) ).not.toContainText(
			'Corso Prova Sovrapposto'
		);
		await page.goto( PORTAL + '?vista=orari&stato=archived' );
		await expect( page.locator( 'article.bcp-course' ) ).toHaveCount( 1 );
		await expect( page.locator( 'article.bcp-course' ) ).toContainText(
			'Archiviato'
		);
	} );

	test( 'corsi e orari leggibili su smartphone', async ( { browser } ) => {
		const context = await browser.newContext( {
			viewport: { width: 375, height: 740 },
		} );
		const page = await context.newPage();
		await staffLogin( page );
		await page.goto( PORTAL + '?vista=orari' );
		const width = await page.evaluate(
			() => document.documentElement.scrollWidth
		);
		expect( width ).toBeLessThanOrEqual( 375 );
		await shot( page, 'catalogo-telefono' );
		await context.close();
	} );
} );
