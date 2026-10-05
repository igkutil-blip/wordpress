/**
 * Plan A izleti – filtriranje po kategorijama bez ponovnog učitavanja stranice.
 */
( function () {
	'use strict';

	function init( root ) {
		if ( root.getAttribute( 'data-paiz-ready' ) ) {
			return;
		}
		root.setAttribute( 'data-paiz-ready', '1' );

		var filters = root.querySelector( '[data-paiz-filters]' );
		if ( ! filters ) {
			return;
		}
		var buttons = filters.querySelectorAll( '[data-paiz-filter]' );
		var cards = root.querySelectorAll( '[data-paiz-cats]' );
		var status = root.querySelector( '[data-paiz-status]' );

		filters.hidden = false;

		filters.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-paiz-filter]' );
			if ( ! button ) {
				return;
			}
			var filter = button.getAttribute( 'data-paiz-filter' );
			var visible = 0;

			Array.prototype.forEach.call( buttons, function ( item ) {
				var active = item === button;
				item.classList.toggle( 'is-active', active );
				item.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
			} );

			Array.prototype.forEach.call( cards, function ( card ) {
				var cats = ( card.getAttribute( 'data-paiz-cats' ) || '' ).split( ' ' );
				var show = 'all' === filter || cats.indexOf( filter ) !== -1;
				card.hidden = ! show;
				if ( show ) {
					visible++;
				}
			} );

			if ( status ) {
				status.textContent = ( status.getAttribute( 'data-paiz-status-text' ) || '%d' ).replace( '%d', visible );
			}
		} );
	}

	function boot() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-paiz]' ), init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
