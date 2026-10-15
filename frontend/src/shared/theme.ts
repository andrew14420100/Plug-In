import type { Theme } from './types';

/**
 * Tema effettivo, risolvendo "auto" con la preferenza del sistema.
 *
 * @param theme       Tema scelto.
 * @param prefersDark Preferenza del sistema operativo.
 */
export function resolveTheme(
	theme: Theme,
	prefersDark: boolean
): 'light' | 'dark' {
	if ( theme === 'auto' ) {
		return prefersDark ? 'dark' : 'light';
	}
	return theme;
}

/**
 * Applica tema e tavolozza al contenitore della pagina.
 *
 * @param element Contenitore `.bc-app`.
 * @param theme   Tema.
 * @param palette Tavolozza.
 */
export function applyThemeAttributes(
	element: HTMLElement | null,
	theme: Theme,
	palette: string
): void {
	if ( ! element ) {
		return;
	}
	element.dataset.bcTheme = theme;
	element.dataset.bcPalette = palette;
}

/**
 * Converte una data UTC "Y-m-d H:i:s" in Date.
 *
 * @param value Data dal server.
 */
export function parseUtc( value: string ): Date {
	return new Date( value.replace( ' ', 'T' ) + 'Z' );
}

/**
 * Tempo relativo in italiano ("3 minuti fa").
 *
 * @param date Data.
 * @param now  Riferimento.
 */
export function relativeTime( date: Date, now: Date = new Date() ): string {
	const seconds = Math.round( ( date.getTime() - now.getTime() ) / 1000 );
	const format = new Intl.RelativeTimeFormat( 'it', { numeric: 'auto' } );
	const abs = Math.abs( seconds );
	if ( abs < 60 ) {
		return format.format( seconds, 'second' );
	}
	if ( abs < 3600 ) {
		return format.format( Math.round( seconds / 60 ), 'minute' );
	}
	if ( abs < 86400 ) {
		return format.format( Math.round( seconds / 3600 ), 'hour' );
	}
	return format.format( Math.round( seconds / 86400 ), 'day' );
}
