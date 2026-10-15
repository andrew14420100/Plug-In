import { createRoot } from '@wordpress/element';
import AuditApp from './AuditApp';
import './style.scss';

const root = document.getElementById( 'bracelli-audit-root' );
if ( root ) {
	let actions: string[] = [];
	try {
		const parsed: unknown = JSON.parse( root.dataset.actions ?? '[]' );
		actions = Array.isArray( parsed )
			? parsed.filter( ( a ): a is string => typeof a === 'string' )
			: [];
	} catch {
		actions = [];
	}
	createRoot( root ).render( <AuditApp actions={ actions } /> );
}
