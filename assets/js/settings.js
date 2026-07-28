( function () {
	'use strict';

	function field( key ) {
		return document.querySelector( '[name="aig_settings[' + key + ']"]' );
	}

	function isHex( value ) {
		return /^#[0-9A-Fa-f]{6}$/.test( value );
	}

	function initPreview() {
		var preview = document.querySelector( '.aig-settings-preview' );
		if ( ! preview ) {
			return;
		}

		var controls = {
			background: field( 'background' ),
			accent: field( 'accent' ),
			text: field( 'text_color' ),
			radius: field( 'border_radius' ),
			spacing: field( 'spacing' ),
			modifiedLabel: field( 'modified_label' ),
			readLabel: field( 'read_label' ),
		};

		function updateColor( key, property ) {
			var input = controls[ key ];
			if ( ! input || ! isHex( input.value ) ) {
				return;
			}

			preview.style.setProperty( property, input.value );
			var swatch = document.querySelector( '[data-aig-swatch="' + input.name + '"]' );
			if ( swatch ) {
				swatch.style.backgroundColor = input.value;
			}
		}

		function update() {
			updateColor( 'background', '--aig-background' );
			updateColor( 'accent', '--aig-accent' );
			updateColor( 'text', '--aig-text' );

			if ( controls.radius ) {
				var radius = Math.min( 40, Math.max( 0, parseInt( controls.radius.value, 10 ) || 0 ) );
				preview.style.setProperty( '--aig-radius', radius + 'px' );
			}

			if ( controls.spacing ) {
				preview.style.setProperty(
					'--aig-padding',
					controls.spacing.value === 'compact' ? '14px 18px' : '18px 22px'
				);
			}

			var modifiedPreview = preview.querySelector( '[data-aig-preview-modified]' );
			if ( modifiedPreview && controls.modifiedLabel ) {
				modifiedPreview.textContent = controls.modifiedLabel.value || 'Last updated on';
			}

			var readPreview = preview.querySelector( '[data-aig-preview-read]' );
			if ( readPreview && controls.readLabel ) {
				readPreview.textContent = ( controls.readLabel.value || '%s min read' ).replace( /%s/g, '8' );
			}
		}

		Object.keys( controls ).forEach( function ( key ) {
			if ( controls[ key ] ) {
				controls[ key ].addEventListener( 'input', update );
				controls[ key ].addEventListener( 'change', update );
			}
		} );

		update();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initPreview );
	} else {
		initPreview();
	}
} )();
