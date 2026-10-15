import { expect, test } from '@playwright/test';
import { login, newPageWithoutJs, shot, waitForDashboard } from './helpers';

test.describe( 'Permessi e sicurezza', () => {
	test( 'la segreteria vede il contesto dipendente e non accede alle pagine riservate', async ( {
		page,
	} ) => {
		await login( page, 'staff' );
		await expect( page ).toHaveURL( /page=bracelli$/ );
		await waitForDashboard( page );
		await expect( page.getByText( 'Area personale' ) ).toBeVisible();
		await expect(
			page.getByRole( 'heading', { level: 3, name: 'Stato moduli' } )
		).toHaveCount( 0 );
		await expect(
			page.getByRole( 'heading', { level: 3, name: 'Attività recenti' } )
		).toHaveCount( 0 );
		await shot( page, '07-dashboard-segreteria' );

		for ( const slug of [
			'bracelli-modules',
			'bracelli-settings',
			'bracelli-permissions',
			'bracelli-audit',
			'bracelli-recovery',
		] ) {
			const response = await page.goto(
				`/wp-admin/admin.php?page=${ slug }`
			);
			expect( response?.status(), slug ).toBe( 403 );
		}
	} );

	test( 'le API REST rifiutano utenti non autorizzati e richieste senza nonce', async ( {
		page,
	} ) => {
		const anon = await page.request.get(
			'/?rest_route=/bracelli/v1/dashboard'
		);
		expect( anon.status() ).toBe( 401 );

		await login( page, 'staff' );
		await waitForDashboard( page );
		const results = await page.evaluate( async () => {
			const nonce = (
				window as unknown as {
					wp: { apiFetch: { nonceMiddleware: { nonce: string } } };
				}
			 ).wp.apiFetch.nonceMiddleware.nonce;
			const base = '/?rest_route=';
			const audit = await fetch( `${ base }/bracelli/v1/audit`, {
				headers: { 'X-WP-Nonce': nonce },
			} );
			const noNonce = await fetch(
				`${ base }/bracelli/v1/me/preferences`,
				{
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( { theme: 'dark' } ),
				}
			);
			const forged = await fetch(
				`${ base }/bracelli/v1/me/preferences`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': 'nonce-falso',
					},
					body: JSON.stringify( { theme: 'dark' } ),
				}
			);
			const invalid = await fetch(
				`${ base }/bracelli/v1/me/preferences`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': nonce,
					},
					body: JSON.stringify( {
						palette: '"><script>alert(1)</script>',
					} ),
				}
			);
			return {
				audit: audit.status,
				noNonce: noNonce.status,
				forged: forged.status,
				invalid: invalid.status,
			};
		} );
		expect( results.audit ).toBe( 403 );
		expect( results.noNonce ).toBe( 401 );
		expect( results.forged ).toBe( 403 );
		expect( results.invalid ).toBe( 400 );
	} );

	test( 'un abbonato non vede la dashboard Bracelli', async ( { page } ) => {
		await login( page, 'subscriber' );
		await expect( page ).not.toHaveURL( /page=bracelli/ );
		const response = await page.goto( '/wp-admin/admin.php?page=bracelli' );
		expect( response?.status() ).toBe( 403 );
	} );

	test( 'senza JavaScript restano disponibili collegamenti e ripristino', async ( {
		browser,
	} ) => {
		const page = await newPageWithoutJs( browser );
		await login( page, 'admin' );
		await expect( page.locator( 'noscript' ) ).toHaveCount( 1 );
		await page.goto( '/wp-admin/admin.php?page=bracelli-recovery' );
		await expect(
			page.getByRole( 'heading', { name: 'Ripristino', level: 1 } )
		).toBeVisible();
		await page
			.getByRole( 'button', {
				name: 'Mostra il menu WordPress (per me)',
			} )
			.click();
		await expect(
			page.getByText(
				'Il menu WordPress è di nuovo visibile per il tuo utente.'
			)
		).toBeVisible();
		await shot( page, '08-ripristino-senza-js' );

		await page.goto( '/wp-admin/admin.php?page=bracelli-diagnostics' );
		await expect(
			page
				.getByRole( 'cell', { name: 'Versione PHP' } )
				.or( page.getByRole( 'rowheader', { name: 'Versione PHP' } ) )
		).toBeVisible();
		await page
			.getByRole( 'button', { name: 'Esegui diagnostica approfondita' } )
			.click();
		await expect( page.getByText( /Latenza database/ ) ).toBeVisible();
		await shot( page, '09-diagnostica' );
		await page.context().close();
	} );
} );
