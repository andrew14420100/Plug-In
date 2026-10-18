import { expect, test } from '@playwright/test';
import { login, newPageWithoutJs, shot, waitForDashboard } from './helpers';

test.describe( 'Permessi e sicurezza', () => {
	test( "la segreteria entra nell'area riservata e non nella bacheca WordPress", async ( {
		page,
	} ) => {
		await login( page, 'staff', /area-bracelli|bracelli_portal=1/ );
		await expect( page.locator( '.bcp-user__name' ) ).toBeVisible();
		await shot( page, '07-area-segreteria' );

		for ( const slug of [
			'bracelli',
			'bracelli-modules',
			'bracelli-settings',
			'bracelli-permissions',
			'bracelli-audit',
			'bracelli-recovery',
		] ) {
			await page.goto( `/wp-admin/admin.php?page=${ slug }` );
			await expect( page, slug ).toHaveURL(
				/area-bracelli|bracelli_portal=1/
			);
		}
	} );

	test( 'le API REST rifiutano utenti non autorizzati e richieste senza nonce', async ( {
		page,
	} ) => {
		const anon = await page.request.get(
			'/?rest_route=/bracelli/v1/dashboard'
		);
		expect( anon.status() ).toBe( 401 );

		// La segreteria non usa più la bacheca: il nonce REST si prende dalla dashboard dell'amministratore.
		// Il rifiuto del registro alla segreteria (403) è verificato dai test di integrazione REST.
		await login( page, 'admin' );
		await waitForDashboard( page );
		const results = await page.evaluate( async () => {
			const nonce = (
				window as unknown as {
					wp: { apiFetch: { nonceMiddleware: { nonce: string } } };
				}
			 ).wp.apiFetch.nonceMiddleware.nonce;
			const base = '/?rest_route=';
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
				noNonce: noNonce.status,
				forged: forged.status,
				invalid: invalid.status,
			};
		} );
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
