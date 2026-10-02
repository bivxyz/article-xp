/* Real WordPress/PHP integration tests in an isolated, in-memory SQLite site. */
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { execFileSync } = require( 'node:child_process' );
const { PHP } = require( '@php-wasm/universal' );
const phpVersion = process.env.PHP_VERSION || '8.3';
const phpPackage = '@php-wasm/node-' + phpVersion.replace( '.', '-' );
const { getPHPLoaderModule } = require( phpPackage );
const { loadNodeRuntime } = require( '@php-wasm/node' );
const root = path.resolve( __dirname, '..' );
const wpVersion = process.env.WP_VERSION || '6.2';

async function main() {
	const loader = await getPHPLoaderModule();
	const packageDir = path.dirname( require.resolve( phpPackage ) );
	// Supplying the binary also avoids loader path differences between CJS/ESM.
	const variant = loader.dependencyFilename.includes( 'jspi' ) ? 'jspi' : 'asyncify';
	const binaryDir = fs.readdirSync( path.join( packageDir, variant ) ).find( ( name ) => new RegExp( '^' + phpVersion.replace( '.', '_' ) + '_\\d+$' ).test( name ) );
	const php = new PHP( await loadNodeRuntime( phpVersion, {
		emscriptenOptions: { wasmBinary: fs.readFileSync( path.join( packageDir, variant, binaryDir, 'php_' + phpVersion.replace( '.', '_' ) + '.wasm' ) ), processId: 1 },
	} ) );
	try {
		async function run( code ) {
			let response;
			try {
				response = await php.run( { code: '<?php ' + code } );
			} catch ( error ) {
				console.error( 'PHP request failed:', code.slice( 0, 500 ) );
				throw error;
			}
			if ( response.exitCode !== 0 || response.errors.trim() ) {
				throw new Error( response.errors + '\n' + response.text );
			}
			return response.text;
		}
		async function unzip( url, archive, directory ) {
			const response = await fetch( url );
			if ( ! response.ok ) throw new Error( `Download failed: ${ url } (${ response.status })` );
			const bytes = new Uint8Array( await response.arrayBuffer() );
			console.log( `Extracting ${ archive } (${ bytes.length } bytes)` );
			php.writeFile( archive, bytes );
			await run( `$zip = new ZipArchive(); if ($zip->open('${ archive }') !== true || !$zip->extractTo('${ directory }')) { throw new Exception('Archive extraction failed'); } $zip->close(); unlink('${ archive }');` );
		}
		console.log( `Preparing WordPress ${ wpVersion } / PHP ${ phpVersion } / SQLite` );
		await unzip( wpVersion === 'latest' ? 'https://wordpress.org/latest.zip' : `https://wordpress.org/wordpress-${ wpVersion }.zip`, '/wordpress.zip', '/' );
		await unzip( 'https://downloads.wordpress.org/plugin/sqlite-database-integration.2.2.21.zip', '/sqlite.zip', '/wordpress/wp-content/plugins' );
		php.writeFile( '/wordpress/wp-content/db.php', php.readFileAsBuffer( '/wordpress/wp-content/plugins/sqlite-database-integration/db.copy' ) );
		php.writeFile( '/wordpress/wp-config.php', `<?php
		define('DB_NAME', 'seoblox_tests'); define('DB_USER', 'root'); define('DB_PASSWORD', ''); define('DB_HOST', 'localhost');
		define('DB_CHARSET', 'utf8'); define('DB_COLLATE', '');
		define('WP_HOME', 'http://localhost'); define('WP_SITEURL', 'http://localhost');
		define('AUTH_KEY', 'seoblox-tests'); define('SECURE_AUTH_KEY', 'seoblox-tests'); define('LOGGED_IN_KEY', 'seoblox-tests'); define('NONCE_KEY', 'seoblox-tests');
		define('WP_HTTP_BLOCK_EXTERNAL', true); define('WP_DISABLE_FATAL_ERROR_HANDLER', true); define('WP_DEBUG', false); define('WP_DEBUG_DISPLAY', false); define('DISABLE_WP_CRON', true);
		$table_prefix='wp_'; if (!defined('ABSPATH')) define('ABSPATH', __DIR__.'/'); require_once ABSPATH.'wp-settings.php';` );
		const bootstrap = `$_SERVER['HTTP_HOST']='localhost'; $_SERVER['REQUEST_URI']='/'; $_SERVER['SERVER_NAME']='localhost'; $_SERVER['SERVER_PORT']='80'; require '/wordpress/wp-load.php'; `;
		await run( `define('WP_INSTALLING', true); ${ bootstrap } require_once ABSPATH.'wp-admin/includes/upgrade.php'; add_filter('pre_wp_mail', '__return_true'); wp_install('SEOblox test site', 'admin', 'tests@example.org', true, '', 'seoblox-tests');` );

		function copyDirectory( source, target ) {
			if ( ! php.fileExists( target ) ) php.mkdir( target );
			for ( const entry of fs.readdirSync( source, { withFileTypes: true } ) ) {
				if ( [ '.git', 'node_modules' ].includes( entry.name ) ) continue;
				if ( entry.isDirectory() ) copyDirectory( path.join( source, entry.name ), `${ target }/${ entry.name }` );
				else php.writeFile( `${ target }/${ entry.name }`, fs.readFileSync( path.join( source, entry.name ) ) );
			}
		}
		copyDirectory( root, '/wordpress/wp-content/plugins/seoblox' );
		const files = execFileSync( 'git', [ 'ls-tree', '-r', '--name-only', '78cb57008777057eb0464b26648ee736fe6e695e' ], { cwd: root, encoding: 'utf8' } ).trim().split( '\n' );
		for ( const file of files ) {
			const dest = `/wordpress/wp-content/plugins/article-insights-for-geo/${ file }`;
			if ( ! php.fileExists( path.posix.dirname( dest ) ) ) php.mkdir( path.posix.dirname( dest ) );
			php.writeFile( dest, execFileSync( 'git', [ 'show', `78cb57008777057eb0464b26648ee736fe6e695e:${ file }` ], { cwd: root } ) );
		}
		// Compile every PHP source, including the integration suite, without executing it.
		console.log( await run( `foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('/wordpress/wp-content/plugins/seoblox')) as $file) { if ($file->isFile() && $file->getExtension()==='php') { token_get_all(file_get_contents($file->getPathname()), TOKEN_PARSE); echo 'Syntax OK: '.$file->getFilename()."\\n"; } }` ) );
		await run( `${ bootstrap } require_once ABSPATH.'wp-admin/includes/plugin.php'; $result=activate_plugin('seoblox/seoblox.php'); if (is_wp_error($result)) throw new Exception($result->get_error_message());` );
		console.log( await run( `${ bootstrap } require ABSPATH.'wp-content/plugins/seoblox/tests/fresh-install.php';` ) );
		await run( `${ bootstrap } require_once ABSPATH.'wp-admin/includes/plugin.php'; deactivate_plugins('seoblox/seoblox.php'); delete_option('seoblox_settings'); delete_option('seoblox_migration_version');` );
		await run( `${ bootstrap } require_once ABSPATH.'wp-admin/includes/plugin.php'; $result=activate_plugin('article-insights-for-geo/article-insights-for-geo.php'); if (is_wp_error($result)) throw new Exception($result->get_error_message());` );
		console.log( await run( `${ bootstrap } require ABSPATH.'wp-content/plugins/seoblox/tests/legacy-baseline.php';` ) );
		await run( `${ bootstrap } require_once ABSPATH.'wp-admin/includes/plugin.php'; deactivate_plugins('article-insights-for-geo/article-insights-for-geo.php');` );
		// Simulate an upgrade load without activation, so init must migrate settings.
		await run( `${ bootstrap } update_option('active_plugins', array('seoblox/seoblox.php'));` );
		console.log( await run( `${ bootstrap } require ABSPATH.'wp-content/plugins/seoblox/tests/wp-integration.php';` ) );
		console.log( await run( `${ bootstrap } echo 'PASS: WordPress '.get_bloginfo('version').' / PHP '.PHP_VERSION.' fresh install and Article XP 1.0.5 upgrade';` ) );
	} finally {
		try { php.exit(); } catch ( error ) { console.error( 'PHP cleanup:', error ); }
	}
}
main().catch( ( error ) => { console.error( error ); process.exitCode = 1; } );
