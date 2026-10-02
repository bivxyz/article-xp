const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const test = require( 'node:test' );
const vm = require( 'node:vm' );
const script = fs.readFileSync( path.join( __dirname, '../assets/js/editor.js' ), 'utf8' );

function editor( meta = {}, postType = 'post' ) {
	const blocks = {};
	const plugins = {};
	const edits = [];
	const config = {
		enabledPostTypes: [ 'post' ],
		meta: { tldr: '_seoblox_tldr', format: '_seoblox_tldr_format', details: '_seoblox_show_details', showTldr: '_seoblox_show_tldr', placement: '_seoblox_placement' },
	};
	const wp = {
		element: { createElement: ( type, props, ...children ) => ( { type, props, children } ) },
		blocks: { registerBlockType: ( name, settings ) => { blocks[ name ] = settings; } },
		i18n: { __: ( text, domain ) => { assert.equal( domain, 'seoblox' ); return text; } },
		serverSideRender: 'ServerSideRender',
		editPost: { PluginDocumentSettingPanel: 'Panel' },
		plugins: { registerPlugin: ( name, settings ) => { plugins[ name ] = settings; } },
		data: {
			useSelect: ( callback ) => callback( () => ( { getCurrentPostType: () => postType, getEditedPostAttribute: () => meta } ) ),
			useDispatch: () => ( { editPost: ( edit ) => edits.push( edit ) } ),
		},
		components: { SelectControl: 'Select', BaseControl: 'Base', Notice: 'Notice' },
		blockEditor: { RichText: 'RichText' },
	};
	vm.runInNewContext( script, { window: { wp, seobloxEditor: config } } );
	return { blocks, plugins, edits };
}

function find( tree, type ) {
	if ( ! tree ) return null;
	if ( tree.type === type ) return tree;
	for ( const child of tree.children || [] ) {
		const found = find( child, type );
		if ( found ) return found;
	}
	return null;
}

test( 'new blocks and hidden legacy aliases share editable dynamic definitions', () => {
	const { blocks } = editor();
	assert.equal( Object.keys( blocks ).length, 4 );
	for ( const [ current, legacy ] of [ [ 'seoblox/article-details', 'article-insights/details' ], [ 'seoblox/tldr', 'article-insights/tldr' ] ] ) {
		assert.equal( blocks[ legacy ].supports.inserter, false );
		assert.equal( blocks[ legacy ].edit, blocks[ current ].edit );
		assert.equal( blocks[ legacy ].save(), null );
		const preview = blocks[ legacy ].edit();
		assert.equal( find( preview, 'ServerSideRender' ).props.block, current );
	}
} );

test( 'editor loads effective summary and saves only the new summary key', () => {
	const meta = { _seoblox_tldr: '<li>Kept summary</li>', _seoblox_tldr_format: 'list', _seoblox_placement: 'manual' };
	const { plugins, edits } = editor( meta );
	const panel = plugins.seoblox.render();
	assert.equal( panel.props.title, 'SEOblox' );
	assert.equal( panel.props.className, 'seoblox-document-panel aig-document-panel' );
	const summary = find( panel, 'RichText' );
	assert.equal( summary.props.value, meta._seoblox_tldr );
	assert.equal( summary.props.tagName, 'ul' );
	summary.props.onChange( '' );
	assert.equal( edits[ 0 ].meta._seoblox_tldr, '' );
	assert.equal( edits[ 0 ].meta._seoblox_placement, 'manual' );
	assert.equal( '_aig_tldr' in edits[ 0 ].meta, false );
} );

test( 'editor panel is unavailable for commerce post types', () => {
	assert.equal( editor( {}, 'product' ).plugins.seoblox.render(), null );
} );
