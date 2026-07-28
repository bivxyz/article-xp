const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const test = require( 'node:test' );
const vm = require( 'node:vm' );

const script = fs.readFileSync(
	path.join( __dirname, '..', 'assets', 'js', 'frontend.js' ),
	'utf8'
);

function runPlacement( targetSelector, useArticleFallback ) {
	const calls = [];
	const classes = [];
	const fallback = {
		classList: {
			add( className ) {
				classes.push( className );
			},
		},
	};
	const target = {
		parentNode: {
			insertBefore( node, reference ) {
				calls.push( { location: 'before-content', node, reference } );
			},
		},
	};
	const firstChild = {};
	const article = {
		firstChild,
		insertBefore( node, reference ) {
			calls.push( { location: 'inside-article', node, reference } );
		},
	};
	const document = {
		readyState: 'complete',
		querySelector( selector ) {
			if ( selector === '[data-aig-builder-fallback]' ) {
				return fallback;
			}
			if ( targetSelector && selector === targetSelector ) {
				return target;
			}
			if ( useArticleFallback && selector === 'main article, article' ) {
				return article;
			}
			return null;
		},
	};

	vm.runInNewContext( script, { document } );

	return { calls, classes, fallback, target, article, firstChild };
}

[
	'.oxy-stock-content-styles',
	'.elementor-widget-theme-post-content .elementor-widget-container',
	'.et_pb_post_content',
	'.fl-module-fl-post-content .fl-module-content',
	'.brxe-post-content',
	'.bde-post-content',
	'.wp-block-post-content',
	'article .entry-content',
].forEach( ( selector ) => {
	test( `places fallback before ${ selector }`, () => {
		const result = runPlacement( selector, false );

		assert.equal( result.calls.length, 1 );
		assert.equal( result.calls[ 0 ].location, 'before-content' );
		assert.equal( result.calls[ 0 ].node, result.fallback );
		assert.equal( result.calls[ 0 ].reference, result.target );
		assert.deepEqual( result.classes, [ 'is-positioned' ] );
	} );
} );

test( 'places fallback inside a semantic article when no known content container exists', () => {
	const result = runPlacement( null, true );

	assert.equal( result.calls.length, 1 );
	assert.equal( result.calls[ 0 ].location, 'inside-article' );
	assert.equal( result.calls[ 0 ].node, result.fallback );
	assert.equal( result.calls[ 0 ].reference, result.firstChild );
	assert.deepEqual( result.classes, [ 'is-positioned' ] );
} );

test( 'leaves the footer fallback unchanged when no safe article target exists', () => {
	const result = runPlacement( null, false );

	assert.equal( result.calls.length, 0 );
	assert.deepEqual( result.classes, [] );
} );
