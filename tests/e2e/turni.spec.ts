import { test, expect, type Page } from '@playwright/test';
import { USERS, collectErrors, shot } from './helpers';

/**
 * Prototipo Turni e presenze: area riservata frontend /area-bracelli/ (dati fittizi del sito E2E).
 */
const PORTAL = '/area-bracelli/';

async function portalLogin( page: Page, who: keyof typeof USERS ) {
	await page.context().clearCookies();
	await page.goto( PORTAL );
	await page.fill( '#bcp-log', USERS[ who ].user );
	await page.fill( '#bcp-pwd', USERS[ who ].pass );
	await page.click( 'button[type="submit"]' );
	await expect( page.locator( '.bcp-user__name' ) ).toBeVisible();
}

function today(): string {
	// Data di oggi nel fuso del sito (Europe/Rome), formato Y-m-d.
	return new Intl.DateTimeFormat( 'sv-SE', {
		timeZone: 'Europe/Rome',
	} ).format( new Date() );
}

test.describe.serial( 'Turni e presenze', () => {
	test( 'login errato mostra un messaggio generico', async ( { page } ) => {
		await page.goto( PORTAL );
		await expect( page.locator( 'meta[name="robots"]' ) ).toHaveAttribute(
			'content',
			/noindex/
		);
		await page.fill( '#bcp-log', 'dipendente1' );
		await page.fill( '#bcp-pwd', 'password-sbagliata' );
		await page.click( 'button[type="submit"]' );
		await expect( page.locator( '.bcp-alert--error' ) ).toContainText(
			'Accesso non riuscito'
		);
	} );

	test( 'la segreteria crea, modifica e annulla turni', async ( {
		page,
	} ) => {
		const errors = collectErrors( page );
		await portalLogin( page, 'staff' );
		await expect( page.locator( 'h1' ) ).toHaveText( 'Turni e presenze' );
		await page.click( 'a.bcp-tab:has-text("Nuovo turno")' );

		// Errore di validazione: il modulo viene ripresentato con il campo evidenziato.
		await page.selectOption( '#bcp-employee', {
			label: 'Dipendente Prova Uno',
		} );
		await page.fill( '#bcp-date', today() );
		await page.fill( '#bcp-start', '18:00' );
		await page.fill( '#bcp-end', '09:00' );
		await page.click( 'button:has-text("Crea turno")' );
		await expect( page.locator( '#bcp-end' ) ).toHaveAttribute(
			'aria-invalid',
			'true'
		);

		// Turno di oggi per il dipendente 1.
		await page.fill( '#bcp-start', '08:00' );
		await page.fill( '#bcp-end', '12:00' );
		await page.click( 'button:has-text("Crea turno")' );
		await expect( page.locator( '.bcp-alert--success' ) ).toContainText(
			'Turno creato'
		);
		await expect( page.locator( 'tbody tr' ) ).toHaveCount( 1 );

		// Turno di oggi per il dipendente 2 (non deve essere visibile al dipendente 1).
		await page.goto( PORTAL + '?vista=nuovo' );
		await page.selectOption( '#bcp-employee', {
			label: 'Dipendente Prova Due',
		} );
		await page.fill( '#bcp-date', today() );
		await page.fill( '#bcp-start', '15:00' );
		await page.fill( '#bcp-end', '19:00' );
		await page.click( 'button:has-text("Crea turno")' );
		await expect( page.locator( 'tbody tr' ) ).toHaveCount( 2 );

		// Un terzo turno da modificare e poi annullare.
		await page.goto( PORTAL + '?vista=nuovo' );
		await page.selectOption( '#bcp-employee', {
			label: 'Dipendente Prova Uno',
		} );
		await page.fill( '#bcp-date', today() );
		await page.fill( '#bcp-start', '20:00' );
		await page.fill( '#bcp-end', '21:00' );
		await page.click( 'button:has-text("Crea turno")' );
		const row = page.locator( 'tbody tr', { hasText: '20:00–21:00' } );
		await row.locator( 'a:has-text("Modifica")' ).click();
		await page.fill( '#bcp-end', '22:00' );
		await page.click( 'button:has-text("Salva modifiche")' );
		await expect( page.locator( '.bcp-alert--success' ) ).toContainText(
			'Turno aggiornato'
		);
		await expect( page.locator( '.bcp-history li' ) ).toHaveCount( 2 );
		await page.click( 'a:has-text("Annulla il turno")' );
		await page.click( 'button:has-text("Conferma annullamento")' );
		await expect( page.locator( '.bcp-alert--success' ) ).toContainText(
			'Turno annullato'
		);
		await expect( page.locator( '.bcp-history li' ) ).toHaveCount( 3 );
		await expect(
			page.locator( 'form button:has-text("Salva modifiche")' )
		).toHaveCount( 0 );
		await shot( page, 'turni-segreteria-dettaglio' );

		await page.goto( PORTAL );
		await expect( page.locator( 'tbody tr.is-cancelled' ) ).toHaveCount(
			1
		);
		await shot( page, 'turni-segreteria-elenco' );
		expect( errors ).toEqual( [] );
	} );

	test( 'il dipendente vede solo i propri turni e registra la presenza', async ( {
		page,
	} ) => {
		const errors = collectErrors( page );
		await portalLogin( page, 'employee1' );
		await expect( page.locator( 'h1' ) ).toHaveText( 'I miei turni' );
		await expect( page.locator( 'body' ) ).toContainText( '08:00–12:00' );
		await expect( page.locator( 'body' ) ).not.toContainText(
			'15:00–19:00'
		);
		await expect( page.locator( 'body' ) ).not.toContainText(
			'Dipendente Prova Due'
		);
		await expect(
			page.locator( 'a.bcp-tab:has-text("Nuovo turno")' )
		).toHaveCount( 0 );

		const card = page.locator( 'article.bcp-shift', {
			hasText: '08:00–12:00',
		} );
		await card.locator( 'button:has-text("Presente")' ).click();
		await expect( page.locator( '.bcp-alert--success' ) ).toContainText(
			'Registrazione salvata'
		);
		await expect( card.locator( '.bcp-badge--present' ) ).toBeVisible();
		await expect(
			card.locator( 'button:has-text("Presente")' )
		).toHaveAttribute( 'aria-pressed', 'true' );
		// Il turno annullato è visibile come tale e senza pulsanti.
		const cancelled = page.locator( 'article.bcp-shift.is-cancelled' );
		await expect( cancelled ).toHaveCount( 1 );
		await expect( cancelled.locator( 'button' ) ).toHaveCount( 0 );

		// Le viste della segreteria non sono accessibili: si torna ai propri turni.
		await page.goto( PORTAL + '?vista=turni' );
		await expect( page.locator( 'h1' ) ).toHaveText( 'I miei turni' );
		await expect( page.locator( 'body' ) ).not.toContainText(
			'15:00–19:00'
		);

		// La bacheca WordPress non è accessibile al dipendente.
		await page.goto( '/wp-admin/' );
		await expect( page ).toHaveURL( /area-bracelli/ );
		await page.goto( '/wp-admin/profile.php' );
		await expect( page ).toHaveURL( /area-bracelli/ );
		// Nessuna barra di amministrazione sul sito.
		await page.goto( '/' );
		await expect( page.locator( '#wpadminbar' ) ).toHaveCount( 0 );
		await page.goto( PORTAL );
		await shot( page, 'turni-dipendente' );
		expect( errors ).toEqual( [] );
	} );

	test( 'un dipendente non può registrare la presenza sul turno di un altro', async ( {
		page,
	} ) => {
		// Dipendente 2: legge l'id del proprio turno, poi il dipendente 1 prova a usarlo.
		await portalLogin( page, 'employee2' );
		const id = await page
			.locator( 'article.bcp-shift input[name="turno"]' )
			.first()
			.inputValue();
		await portalLogin( page, 'employee1' );
		const nonce = await page
			.locator( 'article.bcp-shift input[name="_bc_nonce"]' )
			.first()
			.inputValue();
		const response = await page.request.post( PORTAL, {
			form: {
				bc_action: 'attendance',
				turno: id,
				presenza: 'absent',
				_bc_nonce: nonce,
			},
			maxRedirects: 0,
		} );
		expect( response.status() ).toBe( 302 );
		expect( response.headers().location ).toContain( 'esito=expired' );
		await portalLogin( page, 'employee2' );
		await expect(
			page.locator( 'article.bcp-shift .bcp-badge--absent' )
		).toHaveCount( 0 );
		await expect(
			page.locator( 'article.bcp-shift' ).first()
		).toContainText( 'Non registrata' );
	} );

	test( 'la segreteria vede la presenza registrata e il turno non è più modificabile', async ( {
		page,
	} ) => {
		await portalLogin( page, 'staff' );
		const row = page.locator( 'tbody tr', { hasText: '08:00–12:00' } );
		await expect( row.locator( '.bcp-badge--present' ) ).toBeVisible();
		await expect( row.locator( 'a:has-text("Annulla")' ) ).toHaveCount( 0 );
		await page.selectOption( '#bcp-pres', 'present' );
		await page.click( 'button:has-text("Filtra")' );
		await expect( page.locator( 'tbody tr' ) ).toHaveCount( 1 );
		await expect(
			page.locator( '.bcp-stats .is-present strong' )
		).toHaveText( '1' );
	} );

	test( 'la segreteria non accede alle impostazioni critiche', async ( {
		page,
	} ) => {
		await portalLogin( page, 'staff' );
		for ( const slug of [
			'bracelli-modules',
			'bracelli-settings',
			'bracelli-permissions',
		] ) {
			const response = await page.goto(
				`/wp-admin/admin.php?page=${ slug }`
			);
			expect( response?.status() ).toBe( 403 );
		}
		const users = await page.goto( '/wp-admin/users.php' );
		expect( users?.status() ).toBe( 403 );
	} );

	test( 'un abbonato senza permessi non vede nulla', async ( { page } ) => {
		await page.context().clearCookies();
		await page.goto( PORTAL );
		await page.fill( '#bcp-log', USERS.subscriber.user );
		await page.fill( '#bcp-pwd', USERS.subscriber.pass );
		await page.click( 'button[type="submit"]' );
		await expect( page.locator( 'body' ) ).toContainText(
			'non è abilitato'
		);
		await expect( page.locator( 'body' ) ).not.toContainText(
			'08:00–12:00'
		);
	} );

	test( 'area turni leggibile su smartphone', async ( { browser } ) => {
		const context = await browser.newContext( {
			viewport: { width: 360, height: 740 },
		} );
		const page = await context.newPage();
		await page.goto( PORTAL );
		await page.fill( '#bcp-log', USERS.staff.user );
		await page.fill( '#bcp-pwd', USERS.staff.pass );
		await page.click( 'button[type="submit"]' );
		await expect( page.locator( 'tbody tr' ).first() ).toBeVisible();
		const overflow = await page.evaluate(
			() =>
				document.documentElement.scrollWidth -
				document.documentElement.clientWidth
		);
		expect( overflow ).toBeLessThanOrEqual( 0 );
		await shot( page, 'turni-segreteria-mobile' );
		await context.close();
	} );
} );
