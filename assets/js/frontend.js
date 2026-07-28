( function () {
	'use strict';

	function placeBuilderFallback() {
		var fallback = document.querySelector( '[data-aig-builder-fallback]' );
		if ( ! fallback ) {
			return;
		}

		var contentSelectors = [
			'.oxy-stock-content-styles',
			'.elementor-widget-theme-post-content .elementor-widget-container',
			'.elementor-widget-theme-post-content',
			'.et_pb_post_content',
			'.fl-module-fl-post-content .fl-module-content',
			'.fl-post-content',
			'.brxe-post-content',
			'.bde-post-content',
			'.wp-block-post-content',
			'article .entry-content',
			'.entry-content',
			'.post-content'
		];
		var target = null;

		contentSelectors.some( function ( selector ) {
			target = document.querySelector( selector );
			return Boolean( target );
		} );

		if ( target && target.parentNode ) {
			target.parentNode.insertBefore( fallback, target );
			fallback.classList.add( 'is-positioned' );
			return;
		}

		var article = document.querySelector( 'main article, article' );
		if ( article ) {
			article.insertBefore( fallback, article.firstChild );
			fallback.classList.add( 'is-positioned' );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', placeBuilderFallback );
	} else {
		placeBuilderFallback();
	}
} )();
