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

	function blockPreview( blockName, emptyMessage ) {
		return el(
			'div',
			{ className: 'aig-editor-preview' },
			el( ServerSideRender, {
				block: blockName,
				EmptyResponsePlaceholder: function () {
					return el( Notice, { status: 'info', isDismissible: false }, emptyMessage );
				},
			} )
		);
	}

	registerBlockType( 'article-insights/details', {
		apiVersion: 2,
		title: __( 'Article Details', 'article-insights-for-geo' ),
		description: __( 'Display the published or modified date and estimated reading time.', 'article-insights-for-geo' ),
		icon: 'clock',
		category: 'widgets',
		supports: { html: false, multiple: false },
		edit: function () {
			return blockPreview(
				'article-insights/details',
				__( 'Article Details is hidden for this post.', 'article-insights-for-geo' )
			);
		},
		save: function () {
			return null;
		},
	} );

	registerBlockType( 'article-insights/tldr', {
		apiVersion: 2,
		title: __( 'TL;DR', 'article-insights-for-geo' ),
		description: __( 'Display the approved article summary from the Article XP panel.', 'article-insights-for-geo' ),
		icon: 'excerpt-view',
		category: 'widgets',
		supports: { html: false, multiple: false },
		edit: function () {
			return blockPreview(
				'article-insights/tldr',
				__( 'Add a TL;DR in the Article XP panel to display this block.', 'article-insights-for-geo' )
			);
		},
		save: function () {
			return null;
		},
	} );

	function ArticleInsightsPanel() {
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
			className: 'aig-editor-tldr',
			value: state.meta[ config.meta.tldr ] || '',
			allowedFormats: [ 'core/bold', 'core/italic', 'core/link' ],
			placeholder: format === 'list'
				? __( 'Add a concise takeaway…', 'article-insights-for-geo' )
				: __( 'Summarize what the reader will learn…', 'article-insights-for-geo' ),
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
				name: 'article-insights',
				title: __( 'Article XP', 'article-insights-for-geo' ),
				className: 'aig-document-panel',
			},
			el( SelectControl, {
				label: __( 'TL;DR format', 'article-insights-for-geo' ),
				value: format,
				options: [
					{ label: __( 'Short paragraph', 'article-insights-for-geo' ), value: 'paragraph' },
					{ label: __( 'Bullet list', 'article-insights-for-geo' ), value: 'list' },
				],
				onChange: function ( value ) {
					updateMeta( config.meta.format, value );
				},
			} ),
			el(
				BaseControl,
				{ label: __( 'Approved TL;DR', 'article-insights-for-geo' ) },
				el( RichText, richTextProps )
			),
			el( SelectControl, {
				label: __( 'Article details', 'article-insights-for-geo' ),
				value: state.meta[ config.meta.details ] || 'default',
				options: visibilityOptions(),
				onChange: function ( value ) {
					updateMeta( config.meta.details, value );
				},
			} ),
			el( SelectControl, {
				label: __( 'TL;DR visibility', 'article-insights-for-geo' ),
				value: state.meta[ config.meta.showTldr ] || 'default',
				options: visibilityOptions(),
				onChange: function ( value ) {
					updateMeta( config.meta.showTldr, value );
				},
			} ),
			el( SelectControl, {
				label: __( 'Placement', 'article-insights-for-geo' ),
				value: state.meta[ config.meta.placement ] || 'auto',
				options: [
					{ label: __( 'Automatic before content', 'article-insights-for-geo' ), value: 'auto' },
					{ label: __( 'Manual using blocks', 'article-insights-for-geo' ), value: 'manual' },
				],
				help: __( 'Manual placement disables automatic output for this post.', 'article-insights-for-geo' ),
				onChange: function ( value ) {
					updateMeta( config.meta.placement, value );
				},
			} )
		);
	}

	function visibilityOptions() {
		return [
			{ label: __( 'Use global setting', 'article-insights-for-geo' ), value: 'default' },
			{ label: __( 'Show', 'article-insights-for-geo' ), value: 'show' },
			{ label: __( 'Hide', 'article-insights-for-geo' ), value: 'hide' },
		];
	}

	registerPlugin( 'article-insights-for-geo', {
		render: ArticleInsightsPanel,
		icon: 'visibility',
	} );
} )( window.wp, window.aigEditor );
