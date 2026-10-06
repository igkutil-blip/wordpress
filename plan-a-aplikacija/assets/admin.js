/* Plan A aplikacija – odabir logotipa i boje u postavkama. */
( function ( $ ) {
	'use strict';

	$( function () {
		var frame;
		var $id = $( '#plan-a-logo-id' );
		var $preview = $( '#plan-a-logo-preview' );
		var $remove = $( '#plan-a-logo-remove' );

		$( '.plan-a-color' ).wpColorPicker();

		$( '#plan-a-logo-select' ).on( 'click', function ( event ) {
			event.preventDefault();
			if ( ! frame ) {
				frame = wp.media( {
					title: window.planAAppAdmin.title,
					button: { text: window.planAAppAdmin.button },
					library: { type: 'image' },
					multiple: false,
				} );
				frame.on( 'select', function () {
					var image = frame.state().get( 'selection' ).first().toJSON();
					var url = image.sizes && image.sizes.medium ? image.sizes.medium.url : image.url;
					$id.val( image.id );
					$preview.empty().append( $( '<img>', { src: url, alt: '' } ).css( { maxWidth: 160, maxHeight: 160, background: '#f0f0f1', padding: 8 } ) );
					$remove.prop( 'hidden', false );
				} );
			}
			frame.open();
		} );

		$remove.on( 'click', function ( event ) {
			event.preventDefault();
			$id.val( '0' );
			$preview.empty();
			$remove.prop( 'hidden', true );
		} );
	} );
} )( jQuery );
