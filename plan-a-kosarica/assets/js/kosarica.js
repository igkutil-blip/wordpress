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

	/**
	 * Završna stranica: 2D kod iz izlaza dodatka za uplatnicu (Hub3) premješta se na
	 * vrh bloka "Podaci za plaćanje", ispod natpisa "Skenirajte i platite". Sam kod i
	 * podaci se ne mijenjaju. Ako se kod ne pronađe, sve ostaje kako ga je ispisao dodatak.
	 */
	function placeCode() {
		var box = document.querySelector( '[data-paka-pay]' );
		if ( ! box || box.querySelector( '.paka-pay__code' ) ) {
			return;
		}
		var hint = /hub|bar|kod|code|pdf417|2d|uplatnic|qr/i;
		var slip = /hub-?3a|slip|uplatnic/i; // slika cijele uplatnice nije 2D kod
		var best = null;
		var bestScore = 0;
		Array.prototype.forEach.call( box.querySelectorAll( 'img, canvas, svg' ), function ( el ) {
			var size = el.getBoundingClientRect();
			var w = el.naturalWidth || size.width;
			if ( w < 60 && size.height < 40 ) {
				return; // ikone i sitne slike
			}
			var text = [ el.id, el.getAttribute( 'class' ), el.getAttribute( 'alt' ), el.getAttribute( 'src' ), el.getAttribute( 'title' ) ].join( ' ' );
			if ( slip.test( el.getAttribute( 'alt' ) || '' ) ) {
				return;
			}
			var score = ( hint.test( text ) ? 10 : 1 ) + ( size.width * size.height ) / 100000;
			if ( score > bestScore ) {
				best = el;
				bestScore = score;
			}
		} );
		if ( ! best ) {
			return;
		}
		var figure = document.createElement( 'div' );
		figure.className = 'paka-pay__code';
		var label = document.createElement( 'p' );
		label.className = 'paka-pay__label';
		label.textContent = box.getAttribute( 'data-label' ) || '';
		figure.appendChild( label );
		figure.appendChild( best );
		box.insertBefore( figure, box.firstChild );
	}

	function initReceived() {
		if ( ! document.querySelector( '[data-paka-pay]' ) ) {
			return;
		}
		placeCode();
		// Neki dodaci kod iscrtavaju skriptom nakon učitavanja.
		window.addEventListener( 'load', function () {
			placeCode();
			window.setTimeout( placeCode, 1200 );
		} );
	}

	// Uklanjanjem zadnje stavke košarica postaje prazna: učitaj stranicu ponovno da se
	// prikaz prazne košarice (i popis izleta sa svojim stilovima) učita u cijelosti.
	function initEmptied() {
		if ( window.jQuery && document.querySelector( '.woocommerce-cart-form' ) ) {
			window.jQuery( document.body ).on( 'wc_cart_emptied', function () {
				window.location.reload();
			} );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initEmptied );
		document.addEventListener( 'DOMContentLoaded', init );
		document.addEventListener( 'DOMContentLoaded', initReceived );
	} else {
		initEmptied();
		init();
		initReceived();
	}
} )();
