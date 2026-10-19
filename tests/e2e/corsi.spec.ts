import { test, expect, type Page } from '@playwright/test';
import { USERS, collectErrors, login, shot } from './helpers';

/**
 * Area riservata: accesso, pulsante "Area riservata", corsi e presenze degli insegnanti.
 * Dati fittizi creati da bin/e2e-setup.sh (corsi "Prova", account insegnante1/2, socio1).
 */
const PORTAL = '/area-bracelli/';
const AREA_URL = /area-bracelli|bracelli_portal=1/;

async function portalLogin( page: Page, user: string, password: string ) {
	await page.context().clearCookies();
	await page.goto( PORTAL );
	await page.fill( '#bcp-log', user );
	await page.fill( '#bcp-pwd', password );
	await page.click( 'button[type="submit"]' );
	await expect( page.locator( '.bcp-user__name' ) ).toBeVisible();
}

test.describe.serial( 'Corsi e presenze insegnanti', () => {
	test( "la pagina Accedi non ha registrazione e non rivela se l'account esiste", async ( {
		page,
	} ) => {
		await page.goto( PORTAL );
		await expect( page.locator( 'h1' ) ).toHaveText( 'Accedi' );
		await expect( page.getByLabel( 'Nome utente o email' ) ).toBeVisible();
		await expect( page.locator( 'body' ) ).not.toContainText( /registr/i );
		await expect(
			page.locator( 'img.bcp-logo, .bcp-wordmark' )
		).toHaveCount( 1 );

		await page.fill( '#bcp-log', 'insegnante1' );
		await page.fill( '#bcp-pwd', 'sbagliata' );
		await page.click( 'button[type="submit"]' );
		const wrong = await page.locator( '.bcp-alert--error' ).innerText();

		await page.fill( '#bcp-log', 'account-inesistente@example.org' );
		await page.fill( '#bcp-pwd', 'sbagliata' );
		await page.click( 'button[type="submit"]' );
		await expect( page.locator( '.bcp-alert--error' ) ).toHaveText( wrong );
		await shot( page, 'area-accedi' );
	} );

	test( "il pulsante dello shortcode porta all'accesso e poi all'area", async ( {
		page,
	} ) => {
		await page.context().clearCookies();
		await page.goto( '/prova-pulsante/' );
		const button = page.locator( 'a.bcar' );
		await expect( button ).toHaveText( 'Area riservata' );
		await expect( button.locator( 'svg' ) ).toHaveAttribute(
			'aria-hidden',
			'true'
		);
		await shot( page, 'pulsante-area-riservata' );
		await button.click();
		await expect( page.locator( 'h1' ) ).toHaveText( 'Accedi' );
		await page.fill( '#bcp-log', USERS.member.user );
		await page.fill( '#bcp-pwd', USERS.member.pass );
		await page.click( 'button[type="submit"]' );
		await expect( page.locator( 'h1' ) ).toHaveText(
			'Calendario dei corsi'
		);
		// Da collegato, lo stesso pulsante porta direttamente alla propria area.
		await page.goto( '/prova-pulsante/' );
		await page.locator( 'a.bcar' ).click();
		await expect( page.locator( 'h1' ) ).toHaveText(
			'Calendario dei corsi'
		);
	} );

	test( 'la segreteria assegna un insegnante agli orari di un corso', async ( {
		page,
	} ) => {
		const errors = collectErrors( page );
		await portalLogin( page, USERS.staff.user, USERS.staff.pass );
		await expect( page.locator( 'h1' ) ).toHaveText(
			'Panoramica segreteria'
		);
		await expect(
			page.locator( '.bcp-secretary-metric' )
		).toHaveCount( 5 );
		await expect(
			page.locator( '.bcp-secretary-links' ).first()
		).toContainText( 'Corsi e orari' );
		await page.click( 'a.bcp-tab:has-text("Corsi e orari")' );
		const course = page.locator( 'article.bcp-course', {
			hasText: 'Corso Prova Mattino',
		} );
		await expect( course.locator( '.bcp-slots li' ) ).toHaveCount( 7 );
		await expect(
			page.locator( 'article.bcp-course', {
				hasText: 'Corso Prova Senza Orario',
			} )
		).toContainText( 'Nessun orario' );
		await course.locator( 'a:has-text("Modifica")' ).first().click();
		await page.selectOption( '#bcp-teacher', {
			label: 'Insegnante Prova Uno',
		} );
		await page.check( 'input[name="apply_course"]' );
		await page.click( 'button:has-text("Salva modifiche")' );
		await expect( page.locator( '.bcp-alert--success' ) ).toContainText(
			'Orario aggiornato'
		);
		await expect( page.locator( '.bcp-history li' ) ).toHaveCount( 1 );
		await page.click( 'a.bcp-tab:has-text("Corsi e orari")' );
		await expect(
			page
				.locator( 'article.bcp-course', {
					hasText: 'Corso Prova Mattino',
				} )
				.locator( '.bcp-slots li', { hasText: 'Insegnante Prova Uno' } )
		).toHaveCount( 7 );
		await shot( page, 'segreteria-orari' );
		expect( errors ).toEqual( [] );
	} );

	test( "l'insegnante accede con l'email, vede solo le sue lezioni e registra la presenza", async ( {
		page,
	} ) => {
		const errors = collectErrors( page );
		await portalLogin(
			page,
			'insegnante1@example.org',
			USERS.teacher1.pass
		);
		await expect( page.locator( 'h1' ) ).toHaveText( 'Le mie lezioni' );
		const today = page.locator( 'article.bcp-shift.is-today' );
		await expect( today ).toHaveCount( 1 );
		await expect( today ).toContainText( 'Corso Prova Mattino' );
		await expect( today ).toContainText( '08:00–09:00' );
		await expect( page.locator( 'body' ) ).not.toContainText(
			'Corso Prova Collega'
		);
		await today.locator( 'button:has-text("Presente")' ).click();
		await expect( page.locator( '.bcp-alert--success' ) ).toContainText(
			'Presenza salvata'
		);
		await expect(
			page.locator( 'article.bcp-shift.is-today .bcp-badge--present' )
		).toBeVisible();
		// Le viste della segreteria non sono raggiungibili.
		await page.goto( PORTAL + '?vista=presenze' );
		await expect( page.locator( 'h1' ) ).toHaveText( 'Le mie lezioni' );
		await page.goto( '/wp-admin/' );
		await expect( page ).toHaveURL( AREA_URL );
		await shot( page, 'insegnante-lezioni' );

		// Dopo un nuovo accesso la registrazione resta.
		await page.click( 'a:has-text("Esci")' );
		await expect( page.locator( 'h1' ) ).toHaveText( 'Accedi' );
		await portalLogin( page, USERS.teacher1.user, USERS.teacher1.pass );
		await expect(
			page.locator( 'article.bcp-shift.is-today .bcp-badge--present' )
		).toBeVisible();
		expect( errors ).toEqual( [] );
	} );

	test( "l'insegnante non può registrare la lezione di un collega", async ( {
		page,
	} ) => {
		// Il collega legge l'id della propria lezione di oggi.
		await portalLogin( page, USERS.teacher2.user, USERS.teacher2.pass );
		const card = page.locator( 'article.bcp-shift.is-today' );
		const id = await card.locator( 'input[name="bc_id"]' ).inputValue();
		const date = await card.locator( 'input[name="data"]' ).inputValue();
		// L'insegnante 1 usa il proprio nonce valido con l'id del collega.
		await portalLogin( page, USERS.teacher1.user, USERS.teacher1.pass );
		const nonce = await page
			.locator( 'article.bcp-shift.is-today input[name="_bc_nonce"]' )
			.inputValue();
		const response = await page.request.post( PORTAL, {
			form: {
				bc_action: 'corsi_attendance',
				bc_id: id,
				data: date,
				presenza: 'absent',
				_bc_nonce: nonce,
			},
			maxRedirects: 0,
		} );
		expect( response.status() ).toBe( 302 );
		expect( response.headers().location ).toContain( 'esito=expired' );
		await portalLogin( page, USERS.teacher2.user, USERS.teacher2.pass );
		await expect(
			page.locator( 'article.bcp-shift.is-today' )
		).toContainText( 'Non registrata' );
	} );

	test( 'la segreteria consulta le presenze registrate e mancanti', async ( {
		page,
	} ) => {
		await portalLogin( page, USERS.staff.user, USERS.staff.pass );
		const row = page.locator( 'tbody tr', {
			hasText: 'Corso Prova Mattino',
		} );
		await expect( row ).toContainText( 'Insegnante Prova Uno' );
		await expect( row.locator( '.bcp-badge--present' ) ).toBeVisible();
		await expect(
			page.locator( 'tbody tr', { hasText: 'Corso Prova Collega' } )
		).toContainText( 'Non registrata' );
		await page.selectOption( '#bcp-pres', 'none' );
		await page.click( 'button:has-text("Filtra")' );
		await expect( page.locator( 'tbody tr' ) ).toHaveCount( 1 );
		await shot( page, 'segreteria-presenze' );
	} );

	test( 'il socio vede il calendario, senza insegnanti né viste della segreteria', async ( {
		page,
	} ) => {
		await portalLogin( page, USERS.member.user, USERS.member.pass );
		await expect( page.locator( 'h1' ) ).toHaveText(
			'Calendario dei corsi'
		);
		await expect( page.locator( '.bcp-week' ) ).toContainText(
			'Corso Prova Mattino'
		);
		await expect( page.locator( 'body' ) ).toContainText(
			'Corso Prova Senza Orario'
		);
		await expect( page.locator( 'body' ) ).not.toContainText(
			'Insegnante'
		);
		await expect( page.locator( 'a.bcp-tab' ) ).toHaveCount( 0 );
		await page.goto( '/wp-admin/' );
		await expect( page ).toHaveURL( AREA_URL );
		await shot( page, 'socio-calendario' );
	} );

	test( "l'amministratore importa i corsi da file con anteprima e conferma", async ( {
		page,
	} ) => {
		await login( page, 'admin' );
		await page.goto( '/wp-admin/admin.php?page=bracelli-corsi-import' );
		const csv =
			'chiave;sezione;corso;gruppo;giorno;inizio;fine;insegnante;sede;note\n' +
			'e2e-upload;Sezione Prova;Corso Prova Caricato;;Mercoledì;19:00;20:00;;;\n' +
			'e2e-upload;Sezione Prova;Corso Prova Caricato;;Venerdì;;;;;\n';
		await page.setInputFiles( '#bc-csv', {
			name: 'prova.csv',
			mimeType: 'text/csv',
			buffer: Buffer.from( csv ),
		} );
		await page.click( 'button:has-text("Mostra anteprima")' );
		await expect( page.locator( 'body' ) ).toContainText(
			'nessun dato è ancora stato scritto'
		);
		await expect(
			page.locator( 'tr', { hasText: 'Orari da creare' } )
		).toContainText( '1' );
		await expect( page.locator( 'body' ) ).toContainText(
			'orario mancante'
		);
		await shot( page, 'import-anteprima-corsi' );
		await page.click( 'button:has-text("Conferma e importa")' );
		await expect( page.locator( 'body' ) ).toContainText(
			'Importazione completata'
		);
		// Nell'area riservata il nuovo corso compare nel calendario.
		await page.goto( PORTAL + '?vista=corsi' );
		await expect( page.locator( '.bcp-week' ) ).toContainText(
			'Corso Prova Caricato'
		);
	} );

	test( 'lezioni leggibili su smartphone', async ( { browser } ) => {
		const context = await browser.newContext( {
			viewport: { width: 360, height: 740 },
		} );
		const page = await context.newPage();
		await portalLogin( page, USERS.teacher1.user, USERS.teacher1.pass );
		const overflow = await page.evaluate(
			() =>
				document.documentElement.scrollWidth -
				document.documentElement.clientWidth
		);
		expect( overflow ).toBeLessThanOrEqual( 0 );
		await shot( page, 'insegnante-mobile' );
		await context.close();
	} );
} );
