import { createRoot } from '@wordpress/element';
import App from './App';
import type { DashboardPayload } from '../shared/types';
import './style.scss';

declare global {
	interface Window {
		bracelliDashboard?: DashboardPayload;
	}
}

const root = document.getElementById( 'bracelli-dashboard-root' );
if ( root && window.bracelliDashboard ) {
	createRoot( root ).render( <App initial={ window.bracelliDashboard } /> );
}
