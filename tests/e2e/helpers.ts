import { expect, type Browser, type Page } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

export const USERS = {
	admin: { user: 'admin', pass: 'e2e-admin-pass' },
	staff: { user: 'segreteria', pass: 'e2e-staff-pass' },
	subscriber: { user: 'abbonato', pass: 'e2e-sub-pass' },
	employee1: { user: 'dipendente1', pass: 'e2e-dip1-pass' },
	employee2: { user: 'dipendente2', pass: 'e2e-dip2-pass' },
};

export async function login( page: Page, who: keyof typeof USERS ) {
	await page.goto( '/wp-login.php', { waitUntil: 'load' } );
	// La pagina di login sposta il focus con un timer: si ricompila finché i valori non sono quelli attesi.
	for ( let attempt = 0; attempt < 3; attempt++ ) {
		await page.fill( '#user_login', USERS[ who ].user );
		await page.fill( '#user_pass', USERS[ who ].pass );
		if (
			( await page.inputValue( '#user_login' ) ) === USERS[ who ].user &&
			( await page.inputValue( '#user_pass' ) ) === USERS[ who ].pass
		) {
			break;
		}
		await page.waitForTimeout( 300 );
	}
	await page.click( '#wp-submit' );
	await page.waitForURL( /\/wp-admin\//, { waitUntil: 'domcontentloaded' } );
}

/**
 * Raccoglie errori JavaScript ed errori di console della pagina.
 *
 * @param page Pagina.
 */
export function collectErrors( page: Page ): string[] {
	const errors: string[] = [];
	page.on( 'pageerror', ( e ) => errors.push( `pageerror: ${ e.message }` ) );
	page.on( 'console', ( m ) => {
		if ( m.type() === 'error' ) {
			errors.push( `console: ${ m.text() }` );
		}
	} );
	return errors;
}

export async function shot( page: Page, name: string ) {
	const dir = process.env.E2E_SHOTS_DIR;
	if ( ! dir ) {
		return;
	}
	fs.mkdirSync( dir, { recursive: true } );
	await page.screenshot( {
		path: path.join( dir, `${ name }.png` ),
		fullPage: true,
	} );
}

export async function newPageWithoutJs( browser: Browser ) {
	const context = await browser.newContext( { javaScriptEnabled: false } );
	return context.newPage();
}

export async function waitForDashboard( page: Page ) {
	await expect( page.locator( '.bc-widgets' ) ).toBeVisible();
}
