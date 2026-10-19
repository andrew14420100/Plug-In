import { expect, test } from '@playwright/test';
import { collectErrors, login, shot, waitForDashboard } from './helpers';

test.describe( 'Dashboard amministratore', () => {
	test( 'dopo il login apre la dashboard Bracelli senza errori JavaScript', async ( {
		page,
	} ) => {
		const errors = collectErrors( page );
		await login( page, 'admin' );
		await expect( page ).toHaveURL( /admin\.php\?page=bracelli$/ );
		await waitForDashboard( page );
		await expect(
			page.getByRole( 'heading', { name: /Ciao,/ } )
		).toBeVisible();
		for ( const title of [
			'Benvenuto',
			'Preferiti',
			'Avanzamento del progetto',
			'Stato moduli',
			'Salute del sistema',
			'Attività recenti',
		] ) {
			await expect(
				page.getByRole( 'heading', { level: 3, name: title } )
			).toBeVisible();
		}
		const progress = page.locator( '.bc-progress' );
		await expect( progress.locator( '.bc-phase' ) ).toHaveCount( 5 );
		await expect( progress.locator( '.bc-phase' ).nth( 0 ) ).toContainText(
			'Fase 0 — Stabilizzazione e audit'
		);
		await expect( progress.locator( '.bc-phase' ).nth( 0 ) ).toContainText(
			'Completata'
		);
		await expect( progress.locator( '.bc-phase' ).nth( 1 ) ).toContainText(
			'In corso'
		);
		await expect( progress.locator( '.is-planned' ) ).toHaveCount( 6 );
		await expect( progress.getByRole( 'progressbar' ) ).toHaveAttribute(
			'aria-valuenow',
			'1'
		);
		await expect( progress ).not.toContainText( 'Non ancora disponibile' );
		await shot( page, '01-dashboard-admin-chiaro' );
		expect( errors ).toEqual( [] );
	} );

	test( 'tema scuro e tavolozza vengono salvati per utente', async ( {
		page,
	} ) => {
		await login( page, 'admin' );
		await waitForDashboard( page );
		await page
			.getByRole( 'button', { name: 'Scuro', exact: true } )
			.click();
		await page.getByRole( 'button', { name: /Aurora/ } ).click();
		await expect(
			page.getByRole( 'button', { name: 'Scuro', exact: true } )
		).toHaveAttribute( 'aria-pressed', 'true' );
		await expect( page.getByText( 'Preferenze salvate' ) ).toBeVisible();
		await page.reload();
		await waitForDashboard( page );
		const wrap = page.locator( '.bc-dashboard-wrap' );
		await expect( wrap ).toHaveAttribute( 'data-bc-theme', 'dark' );
		await expect( wrap ).toHaveAttribute( 'data-bc-palette', 'aurora' );
		await shot( page, '02-dashboard-admin-scuro-aurora' );

		// Anche le pagine PHP usano le stesse preferenze.
		await page.goto( '/wp-admin/admin.php?page=bracelli-modules' );
		await expect( page.locator( '.bc-page' ) ).toHaveAttribute(
			'data-bc-theme',
			'dark'
		);
		await shot( page, '03-moduli-scuro' );

		await page.goto( '/wp-admin/admin.php?page=bracelli' );
		await page
			.getByRole( 'button', { name: 'Automatico', exact: true } )
			.click();
		await page.getByRole( 'button', { name: /^Bracelli/ } ).click();
		await expect( page.getByText( 'Preferenze salvate' ) ).toBeVisible();
	} );

	test( 'preferiti, riordino e widget nascosti persistono', async ( {
		page,
	} ) => {
		await login( page, 'admin' );
		await waitForDashboard( page );
		await page.getByRole( 'button', { name: 'Personalizza' } ).click();
		await page
			.getByLabel( 'Pagina' )
			.selectOption( { label: 'Il mio profilo' } );
		await page
			.getByLabel( 'Etichetta (facoltativa)' )
			.fill( 'Profilo personale' );
		await page
			.getByRole( 'button', { name: 'Aggiungi', exact: true } )
			.click();
		await page
			.getByRole( 'button', {
				name: /Nascondi widget — Avanzamento del progetto/,
			} )
			.click();
		await page
			.getByRole( 'button', { name: /Sposta prima — Preferiti/ } )
			.click();
		await expect( page.getByText( 'Preferenze salvate' ) ).toBeVisible();
		await shot( page, '04-personalizzazione' );
		await page
			.getByRole( 'button', { name: 'Fine personalizzazione' } )
			.click();

		await page.reload();
		await waitForDashboard( page );
		await expect(
			page.getByRole( 'link', { name: 'Profilo personale' } )
		).toHaveAttribute( 'href', /profile\.php$/ );
		await expect(
			page.getByRole( 'heading', {
				level: 3,
				name: 'Avanzamento del progetto',
			} )
		).toHaveCount( 0 );
		const titles = await page.locator( '.bc-widget h3' ).allTextContents();
		expect( titles.indexOf( 'Preferiti' ) ).toBe( 0 );

		await page.getByRole( 'button', { name: 'Personalizza' } ).click();
		await page
			.getByRole( 'button', {
				name: 'Ripristina preferenze predefinite',
			} )
			.click();
		await expect( page.getByText( 'Preferenze salvate' ) ).toBeVisible();
	} );

	test( 'il menu WordPress si nasconde e si riapre sempre dal pulsante', async ( {
		page,
	} ) => {
		await login( page, 'admin' );
		await waitForDashboard( page );
		await Promise.all( [
			page.waitForNavigation(),
			page
				.getByRole( 'button', { name: 'Nascondi menu WordPress' } )
				.click(),
		] );
		await page.goto( '/wp-admin/edit.php' );
		await expect( page.locator( '#adminmenuwrap' ) ).toBeHidden();
		const toggle = page.locator( '.bc-wp-menu-toggle' );
		await expect( toggle ).toBeVisible();
		await shot( page, '05-menu-wp-nascosto' );
		await toggle.click();
		await expect( page ).toHaveURL( /edit\.php/ );
		await expect( page.locator( '#adminmenuwrap' ) ).toBeVisible();
	} );

	test( 'layout mobile senza scorrimento orizzontale', async ( { page } ) => {
		await page.setViewportSize( { width: 360, height: 800 } );
		await login( page, 'admin' );
		await waitForDashboard( page );
		const overflow = await page.evaluate(
			() => document.documentElement.scrollWidth - window.innerWidth
		);
		expect( overflow ).toBeLessThanOrEqual( 1 );
		await shot( page, '06-dashboard-mobile' );
	} );
} );
