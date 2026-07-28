( function () {
	'use strict';

	function placeBuilderFallback() {
		var fallback = document.querySelector( '[data-aig-builder-fallback]' );
		if ( ! fallback ) {
			return;
		}

		var target = document.querySelector(
			'.oxy-stock-content-styles, .wp-block-post-content, article .entry-content, .entry-content, .post-content'
		);

		if ( target && target.parentNode ) {
			target.parentNode.insertBefore( fallback, target );
			fallback.classList.add( 'is-positioned' );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', placeBuilderFallback );
	} else {
		placeBuilderFallback();
	}
} )();
