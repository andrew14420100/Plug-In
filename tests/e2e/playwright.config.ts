import { defineConfig, devices } from '@playwright/test';

/**
 * Test end-to-end sul sito preparato da bin/e2e-setup.sh (plugin installato dallo ZIP).
 * E2E_BASE_URL: indirizzo del sito; PW_CHROMIUM_PATH: eseguibile Chromium già installato (facoltativo).
 */
const executablePath = process.env.PW_CHROMIUM_PATH || undefined;

export default defineConfig( {
	testDir: __dirname,
	testMatch: '*.spec.ts',
	fullyParallel: false,
	workers: 1,
	retries: 0,
	timeout: 60_000,
	reporter: [ [ 'list' ] ],
	use: {
		baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:8890',
		locale: 'it-IT',
		timezoneId: 'Europe/Rome',
		trace: 'retain-on-failure',
		launchOptions: executablePath ? { executablePath } : {},
	},
	projects: [ { name: 'chromium', use: { ...devices[ 'Desktop Chrome' ] } } ],
} );
