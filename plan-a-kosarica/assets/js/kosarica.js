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
	/**
	 * Naplata: polje za kod u sažetku. Kod se šalje WooCommerceu (wc-ajax apply_coupon, kao zadani
	 * obrazac za kupon), a sažetak se zatim osvježi. Poruka se zadržava i nakon osvježavanja.
	 */
	function initCheckoutCode() {
		var $ = window.jQuery;
		var params = window.wc_checkout_params;
		if ( ! $ || ! params || ! document.querySelector( '.paka-checkout' ) ) {
			return;
		}
		var last = null; // { text, ok }

		function show() {
			var box = document.querySelector( '[data-paka-code]' );
			var msg = document.querySelector( '[data-paka-code-msg]' );
			if ( ! box || ! msg || ! last ) {
				return;
			}
			box.open = true;
			msg.textContent = last.text;
			msg.className = 'paka-code__msg ' + ( last.ok ? 'is-ok' : 'is-error' );
		}

		function apply() {
			var input = document.getElementById( 'paka_code' );
			var button = document.querySelector( '[data-paka-apply]' );
			var code = input ? input.value.trim() : '';
			if ( ! code ) {
				input && input.focus();
				return;
			}
			if ( button ) {
				button.disabled = true;
			}
			$.ajax( {
				type: 'POST',
				url: params.wc_ajax_url.toString().replace( '%%endpoint%%', 'apply_coupon' ),
				data: { security: params.apply_coupon_nonce, coupon_code: code, billing_email: $( '#billing_email' ).val() },
				dataType: 'html',
			} ).done( function ( html ) {
				var holder = document.createElement( 'div' );
				holder.innerHTML = html;
				var error = holder.querySelector( '.woocommerce-error, .is-error' );
				last = { text: ( holder.textContent || '' ).replace( /\s+/g, ' ' ).trim(), ok: ! error };
				$( document.body ).trigger( 'applied_coupon_in_checkout', [ code ] );
				$( document.body ).trigger( 'update_checkout', { update_shipping_method: false } );
				show();
			} ).always( function () {
				if ( button ) {
					button.disabled = false;
				}
			} );
		}

		document.addEventListener( 'click', function ( event ) {
			if ( event.target.closest && event.target.closest( '[data-paka-apply]' ) ) {
				event.preventDefault();
				apply();
			}
		} );
		// Enter u polju za kod primjenjuje kod (ne šalje narudžbu).
		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key && event.target && 'paka_code' === event.target.id ) {
				event.preventDefault();
				apply();
			}
		} );
		$( document.body ).on( 'updated_checkout', function () {
			show();
			last = last && last.ok ? null : last; // uspješna poruka prikaže se jednom
		} );
	}

	function initEmptied() {
		if ( window.jQuery && document.querySelector( '.woocommerce-cart-form' ) ) {
			window.jQuery( document.body ).on( 'wc_cart_emptied', function () {
				window.location.reload();
			} );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initEmptied );
		document.addEventListener( 'DOMContentLoaded', initCheckoutCode );
		document.addEventListener( 'DOMContentLoaded', init );
		document.addEventListener( 'DOMContentLoaded', initReceived );
	} else {
		initEmptied();
		initCheckoutCode();
		init();
		initReceived();
	}
} )();
