/**
 * Plan A poklon bon – odabir fotografije i logotipa u postavkama (medijateka).
 */
jQuery( function ( $ ) {
	'use strict';

	$( '.papb-media' ).each( function () {
		var $cell = $( this );
		var $input = $cell.find( 'input[type="hidden"]' );
		var $img = $cell.find( 'img' );
		var $clear = $cell.find( '.papb-media__clear' );
		var frame;

		$cell.find( '.papb-media__pick' ).on( 'click', function () {
			if ( ! frame ) {
				frame = wp.media( {
					title: $cell.data( 'title' ),
					library: { type: 'image' },
					multiple: false,
				} );
				frame.on( 'select', function () {
					var file = frame.state().get( 'selection' ).first().toJSON();
					var size = ( file.sizes && ( file.sizes.medium || file.sizes.full ) ) || file;
					$input.val( file.id );
					$img.attr( 'src', size.url ).show();
					$clear.prop( 'hidden', false );
				} );
			}
			frame.open();
		} );

		$clear.on( 'click', function () {
			$input.val( '0' );
			$img.attr( 'src', '' ).hide();
			$clear.prop( 'hidden', true );
		} );
	} );
} );
