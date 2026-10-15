import apiFetch from '@wordpress/api-fetch';
import type { DashboardPayload, Preferences } from '../shared/types';

const PATH = '/bracelli/v1/me/preferences';

export function savePreferences(
	partial: Partial< Preferences >
): Promise< Preferences > {
	return apiFetch< Preferences >( {
		path: PATH,
		method: 'POST',
		data: partial,
	} );
}

export function resetPreferences(): Promise< Preferences > {
	return apiFetch< Preferences >( { path: PATH, method: 'DELETE' } );
}

export function fetchDashboard(): Promise< DashboardPayload > {
	return apiFetch< DashboardPayload >( { path: '/bracelli/v1/dashboard' } );
}
