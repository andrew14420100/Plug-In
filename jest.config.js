const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );

module.exports = {
	...defaultConfig,
	rootDir: __dirname,
	testMatch: [ '<rootDir>/frontend/tests/**/*.test.[jt]s?(x)' ],
	testPathIgnorePatterns: [ '/node_modules/', '/vendor/' ],
};
