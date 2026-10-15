/**
 * Bundle separati per modulo, compilati in plugin/bracelli-club/assets/dist/<modulo>/.
 * React e le librerie @wordpress/* NON sono inclusi: vengono forniti da WordPress (externals).
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const base = Array.isArray( defaultConfig ) ? defaultConfig[ 0 ] : defaultConfig;

module.exports = {
	...base,
	entry: {
		'dashboard/index': path.resolve( __dirname, 'frontend/src/dashboard/index.tsx' ),
		'audit/index': path.resolve( __dirname, 'frontend/src/audit/index.tsx' ),
	},
	output: {
		...base.output,
		path: path.resolve( __dirname, 'plugin/bracelli-club/assets/dist' ),
		clean: true,
	},
};
