/**
 * Plan A poklon bon – pregled bona uživo, brojač znakova i polje "Drugi iznos".
 * Sve se ponovno provjerava na poslužitelju.
 */
( function () {
	'use strict';

	function money( value ) {
		if ( ! isFinite( value ) || value <= 0 ) {
			return '– €';
		}
		var whole = Math.abs( value - Math.round( value ) ) < 0.005;
		var text = whole ? String( Math.round( value ) ) : value.toFixed( 2 ).replace( '.', ',' );
		return text.replace( /\B(?=(\d{3})+(?!\d))/g, '.' ) + ' €';
	}

	function init( form ) {
		var custom = form.querySelector( '[data-papb-custom]' );
		var customInput = form.querySelector( '#papb-custom' );
		var out = {};
		Array.prototype.forEach.call( form.querySelectorAll( '[data-papb-out]' ), function ( el ) {
			out[ el.getAttribute( 'data-papb-out' ) ] = el;
		} );
		var count = form.querySelector( '[data-papb-count]' );

		function amount() {
			var checked = form.querySelector( 'input[name="papb_amount"]:checked' );
			if ( ! checked ) {
				return 0;
			}
			if ( 'custom' === checked.value ) {
				return parseFloat( String( customInput.value ).replace( ',', '.' ) );
			}
			return parseFloat( checked.value );
		}

		function update() {
			var checked = form.querySelector( 'input[name="papb_amount"]:checked' );
			var isCustom = checked && 'custom' === checked.value;
			if ( custom ) {
				custom.hidden = ! isCustom;
			}
			out.amount.textContent = money( amount() );
			[ 'to', 'from' ].forEach( function ( name ) {
				var input = form.querySelector( '[data-papb-input="' + name + '"]' );
				var value = input.value.trim();
				out[ name ].textContent = value || out[ name ].getAttribute( 'data-empty' );
				out[ name ].classList.toggle( 'is-empty', ! value ); // sivi primjer dok polje nije ispunjeno
			} );
			var message = form.querySelector( '[data-papb-input="message"]' ).value.trim();
			out.message.textContent = message ? '„' + message + '“' : out.message.getAttribute( 'data-empty' );
			out.message.classList.toggle( 'is-empty', ! message ); // sivi primjer dok poruka nije upisana
			if ( count ) {
				count.textContent = String( form.querySelector( '[data-papb-input="message"]' ).value.length );
			}
		}

		form.addEventListener( 'input', update );
		form.addEventListener( 'change', function ( event ) {
			update();
			if ( event.target && 'papb_amount' === event.target.name && 'custom' === event.target.value && customInput ) {
				customInput.focus();
			}
		} );
		update();
	}

	function ready() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-papb-form]' ), init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}
} )();
