import { expect, test } from '@playwright/test';
import { collectErrors, login, shot, waitForDashboard } from './helpers';

test.describe( 'Pagine amministrative', () => {
	test( 'disattivazione e riattivazione di un modulo si riflettono sulla dashboard e nel registro', async ( {
		page,
	} ) => {
		await login( page, 'admin' );
		await page.goto( '/wp-admin/admin.php?page=bracelli-modules' );
		const row = page.locator( 'tr', { hasText: 'Diagnostica' } );
		await row.getByRole( 'button', { name: 'Disattiva' } ).click();
		await expect(
			page.getByText( 'Modulo disattivato. I dati restano conservati.' )
		).toBeVisible();

		await page.goto( '/wp-admin/admin.php?page=bracelli' );
		await waitForDashboard( page );
		await expect(
			page.getByRole( 'heading', {
				level: 3,
				name: 'Salute del sistema',
			} )
		).toHaveCount( 0 );
		const diagResponse = await page.goto(
			'/wp-admin/admin.php?page=bracelli-diagnostics'
		);
		expect( diagResponse?.status() ).not.toBe( 200 );

		await page.goto( '/wp-admin/admin.php?page=bracelli-modules' );
		await page
			.locator( 'tr', { hasText: 'Diagnostica' } )
			.getByRole( 'button', { name: 'Attiva' } )
			.click();
		await expect( page.getByText( 'Modulo attivato.' ) ).toBeVisible();
		await shot( page, '10-moduli' );
	} );

	test( 'registro attività con filtri', async ( { page } ) => {
		const errors = collectErrors( page );
		await login( page, 'admin' );
		await page.goto( '/wp-admin/admin.php?page=bracelli-audit' );
		await expect( page.getByText( /eventi trovati/ ) ).toBeVisible();
		await page
			.locator( '#bc-audit-action' )
			.selectOption( 'module.deactivated' );
		await page.getByRole( 'button', { name: 'Filtra' } ).click();
		await expect( page.locator( 'tbody tr' ).first() ).toContainText(
			'module.deactivated'
		);
		await page.getByRole( 'button', { name: 'Dettagli' } ).first().click();
		await expect( page.locator( 'pre.bc-code' ) ).toContainText(
			'diagnostics'
		);
		await shot( page, '11-registro' );
		expect( errors ).toEqual( [] );
	} );

	test( 'impostazioni: esportazione e importazione con anteprima', async ( {
		page,
	} ) => {
		await login( page, 'admin' );
		await page.goto( '/wp-admin/admin.php?page=bracelli-settings' );
		const [ download ] = await Promise.all( [
			page.waitForEvent( 'download' ),
			page
				.getByRole( 'button', { name: 'Scarica esportazione' } )
				.click(),
		] );
		const file = await download.path();
		expect( file ).toBeTruthy();
		const fs = await import( 'fs' );
		const exported = JSON.parse(
			fs.readFileSync( file as string, 'utf8' )
		);
		expect( exported.format ).toBe( 'bracelli-club-settings' );
		expect( exported.settings ).not.toHaveProperty(
			'uninstall.delete_data'
		);

		exported.settings[ 'ui.default_theme' ] = 'dark';
		await page.setInputFiles( '#bc-import-file', {
			name: 'impostazioni.json',
			mimeType: 'application/json',
			buffer: Buffer.from( JSON.stringify( exported ) ),
		} );
		await page.getByRole( 'button', { name: 'Analizza file' } ).click();
		await expect(
			page.getByRole( 'heading', { name: 'Anteprima delle differenze' } )
		).toBeVisible();
		await expect( page.locator( '.bc-preview tbody tr' ) ).toHaveCount( 1 );
		await shot( page, '12-import-anteprima' );
		await expect(
			page.locator( '#bc-setting-ui-default_theme' )
		).toHaveValue( 'auto' );
		await page
			.getByRole( 'button', { name: 'Applica queste modifiche' } )
			.click();
		await expect(
			page.getByText( 'Importazione applicata.' )
		).toBeVisible();
		await expect(
			page.locator( '#bc-setting-ui-default_theme' )
		).toHaveValue( 'dark' );

		await page
			.locator( '#bc-setting-ui-default_theme' )
			.selectOption( 'auto' );
		await page
			.getByRole( 'button', { name: 'Salva impostazioni' } )
			.click();
		await expect( page.getByText( 'Impostazioni salvate.' ) ).toBeVisible();
	} );

	test( 'permessi: concessione del registro alla segreteria', async ( {
		page,
		browser,
	} ) => {
		await login( page, 'admin' );
		const reset = async () => {
			await page.goto( '/wp-admin/admin.php?page=bracelli-permissions' );
			await page
				.getByRole( 'button', { name: 'Segreteria Bracelli' } )
				.click();
			await expect(
				page.getByText( 'Permessi predefiniti ripristinati.' )
			).toBeVisible();
		};
		// Stato iniziale noto e ripristino garantito anche se il test fallisce.
		await reset();
		try {
			await expect(
				page.getByLabel( 'Registro attività — Segreteria Bracelli' )
			).not.toBeChecked();
			await page
				.getByLabel( 'Registro attività — Segreteria Bracelli' )
				.check();
			await page
				.getByRole( 'button', { name: 'Salva permessi' } )
				.click();
			await expect(
				page.getByText( 'Permessi aggiornati.' )
			).toBeVisible();
			await shot( page, '13-permessi' );

			const context = await browser.newContext();
			const staff = await context.newPage();
			await login( staff, 'staff' );
			const response = await staff.goto(
				'/wp-admin/admin.php?page=bracelli-audit'
			);
			expect( response?.status() ).toBe( 200 );
			await context.close();
		} finally {
			await reset();
		}
	} );
} );
