/**
 * Plan A košarica – koraci na stranici za plaćanje: kad je dio "Način plaćanja"
 * na ekranu, aktivan je korak 3 (Plaćanje), inače korak 2 (Podaci).
 */
( function () {
	'use strict';

	function init() {
		var steps = document.querySelector( '.paka-checkout [data-paka-steps]' );
		var payment = document.getElementById( 'order_review' );
		if ( ! steps || ! payment || ! window.IntersectionObserver ) {
			return;
		}
		var two = steps.querySelector( '[data-paka-step="2"]' );
		var three = steps.querySelector( '[data-paka-step="3"]' );

		function set( step ) {
			two.classList.toggle( 'is-current', 2 === step );
			two.classList.toggle( 'is-done', 3 === step );
			three.classList.toggle( 'is-current', 3 === step );
			if ( 3 === step ) {
				three.setAttribute( 'aria-current', 'step' );
				two.removeAttribute( 'aria-current' );
			} else {
				two.setAttribute( 'aria-current', 'step' );
				three.removeAttribute( 'aria-current' );
			}
		}

		var observer = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					set( entry.isIntersecting ? 3 : 2 );
				} );
			},
			{ rootMargin: '-45% 0px -45% 0px' }
		);
		observer.observe( payment );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
