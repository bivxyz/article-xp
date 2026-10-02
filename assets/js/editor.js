( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! config ) {
		return;
	}

	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var __ = wp.i18n.__;
	var ServerSideRender = wp.serverSideRender;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var registerPlugin = wp.plugins.registerPlugin;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var SelectControl = wp.components.SelectControl;
	var BaseControl = wp.components.BaseControl;
	var Notice = wp.components.Notice;
	var RichText = wp.blockEditor.RichText;

	// Legacy block names remain editable, but are hidden from the inserter.
	function registerComponentBlock( name, legacyName, settings ) {
		registerBlockType( name, settings );
		registerBlockType( legacyName, Object.assign( {}, settings, {
			supports: Object.assign( {}, settings.supports, { inserter: false } ),
		} ) );
	}

	function blockPreview( blockName, emptyMessage ) {
		return el(
			'div',
			{ className: 'seoblox-editor-preview aig-editor-preview' },
			el( ServerSideRender, {
				block: blockName,
				EmptyResponsePlaceholder: function () {
					return el( Notice, { status: 'info', isDismissible: false }, emptyMessage );
				},
			} )
		);
	}

	registerComponentBlock( 'seoblox/article-details', 'article-insights/details', {
		apiVersion: 2,
		title: __( 'Article Details', 'seoblox' ),
		description: __( 'Display the published or modified date and estimated reading time.', 'seoblox' ),
		icon: 'clock',
		category: 'widgets',
		supports: { html: false, multiple: false },
		edit: function () {
			return blockPreview(
				'seoblox/article-details',
				__( 'Article Details is hidden for this post.', 'seoblox' )
			);
		},
		save: function () {
			return null;
		},
	} );

	registerComponentBlock( 'seoblox/tldr', 'article-insights/tldr', {
		apiVersion: 2,
		title: __( 'TL;DR', 'seoblox' ),
		description: __( 'Display the approved article summary from the SEOblox panel.', 'seoblox' ),
		icon: 'excerpt-view',
		category: 'widgets',
		supports: { html: false, multiple: false },
		edit: function () {
			return blockPreview(
				'seoblox/tldr',
				__( 'Add a TL;DR in the SEOblox panel to display this block.', 'seoblox' )
			);
		},
		save: function () {
			return null;
		},
	} );

	function SEObloxPanel() {
		var state = useSelect( function ( select ) {
			return {
				postType: select( 'core/editor' ).getCurrentPostType(),
				meta: select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {},
			};
		}, [] );
		var editPost = useDispatch( 'core/editor' ).editPost;

		if ( config.enabledPostTypes.indexOf( state.postType ) === -1 ) {
			return null;
		}

		function updateMeta( key, value ) {
			var nextMeta = Object.assign( {}, state.meta );
			nextMeta[ key ] = value;
			editPost( { meta: nextMeta } );
		}

		var format = state.meta[ config.meta.format ] || 'paragraph';
		var richTextProps = {
			className: 'seoblox-editor-tldr aig-editor-tldr',
			value: state.meta[ config.meta.tldr ] || '',
			allowedFormats: [ 'core/bold', 'core/italic', 'core/link' ],
			placeholder: format === 'list'
				? __( 'Add a concise takeaway…', 'seoblox' )
				: __( 'Summarize what the reader will learn…', 'seoblox' ),
			onChange: function ( value ) {
				updateMeta( config.meta.tldr, value );
			},
		};

		if ( format === 'list' ) {
			richTextProps.tagName = 'ul';
			richTextProps.multiline = 'li';
		} else {
			richTextProps.tagName = 'p';
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'seoblox',
				title: __( 'SEOblox', 'seoblox' ),
				className: 'seoblox-document-panel aig-document-panel',
			},
			el( SelectControl, {
				label: __( 'TL;DR format', 'seoblox' ),
				value: format,
				options: [
					{ label: __( 'Short paragraph', 'seoblox' ), value: 'paragraph' },
					{ label: __( 'Bullet list', 'seoblox' ), value: 'list' },
				],
				onChange: function ( value ) {
					updateMeta( config.meta.format, value );
				},
			} ),
			el(
				BaseControl,
				{ label: __( 'Approved TL;DR', 'seoblox' ) },
				el( RichText, richTextProps )
			),
			el( SelectControl, {
				label: __( 'Article details', 'seoblox' ),
				value: state.meta[ config.meta.details ] || 'default',
				options: visibilityOptions(),
				onChange: function ( value ) {
					updateMeta( config.meta.details, value );
				},
			} ),
			el( SelectControl, {
				label: __( 'TL;DR visibility', 'seoblox' ),
				value: state.meta[ config.meta.showTldr ] || 'default',
				options: visibilityOptions(),
				onChange: function ( value ) {
					updateMeta( config.meta.showTldr, value );
				},
			} ),
			el( SelectControl, {
				label: __( 'Placement', 'seoblox' ),
				value: state.meta[ config.meta.placement ] || 'auto',
				options: [
					{ label: __( 'Automatic before content', 'seoblox' ), value: 'auto' },
					{ label: __( 'Manual using blocks', 'seoblox' ), value: 'manual' },
				],
				help: __( 'Manual placement disables automatic output for this post.', 'seoblox' ),
				onChange: function ( value ) {
					updateMeta( config.meta.placement, value );
				},
			} )
		);
	}

	function visibilityOptions() {
		return [
			{ label: __( 'Use global setting', 'seoblox' ), value: 'default' },
			{ label: __( 'Show', 'seoblox' ), value: 'show' },
			{ label: __( 'Hide', 'seoblox' ), value: 'hide' },
		];
	}

	registerPlugin( 'seoblox', {
		render: SEObloxPanel,
		icon: 'visibility',
	} );
} )( window.wp, window.seobloxEditor );
