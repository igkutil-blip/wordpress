/**
 * Plan A izleti – filtriranje po kategoriji i mjesecu bez ponovnog učitavanja stranice.
 *
 * Bez odabranog filtra ("Sve ture" + "Svi mjeseci") vidi se prvih N izleta
 * (atribut show); uz odabranu kategoriju ili mjesec vide se svi odgovarajući izleti.
 */
( function () {
	'use strict';

	var each = function ( list, fn ) {
		Array.prototype.forEach.call( list, fn );
	};

	function init( root ) {
		if ( root.getAttribute( 'data-paiz-ready' ) ) {
			return;
		}
		root.setAttribute( 'data-paiz-ready', '1' );

		var groups = root.querySelectorAll( '[data-paiz-filters]' );
		if ( ! groups.length ) {
			return;
		}

		var show = parseInt( root.getAttribute( 'data-paiz-show' ), 10 ) || 0;
		var status = root.querySelector( '[data-paiz-status]' );
		var empty = root.querySelector( '[data-paiz-empty]' );
		var state = { cat: 'all', month: 'all' };

		var cards = Array.prototype.map.call( root.querySelectorAll( '.paiz-card' ), function ( el, index ) {
			var time = el.querySelector( '[data-paiz-date]' );
			var dates = {};
			try {
				dates = JSON.parse( el.getAttribute( 'data-paiz-month-dates' ) || '{}' ) || {};
			} catch ( e ) {}
			return {
				el: el,
				index: index,
				cats: ( el.getAttribute( 'data-paiz-cats' ) || '' ).split( ' ' ).filter( Boolean ),
				months: ( el.getAttribute( 'data-paiz-months' ) || '' ).split( ' ' ).filter( Boolean ),
				dates: Array.isArray( dates ) ? {} : dates,
				time: time,
				defaultText: time ? time.textContent : '',
				defaultDate: time ? time.getAttribute( 'datetime' ) : '',
			};
		} );

		function matches( card, cat, month ) {
			var catOk = 'all' === cat || card.cats.indexOf( cat ) !== -1;
			var monthOk = 'all' === month || card.months.indexOf( month ) !== -1;
			return catOk && monthOk;
		}

		function anyMatch( cat, month ) {
			return cards.some( function ( card ) {
				return matches( card, cat, month );
			} );
		}

		function apply() {
			var unfiltered = 'all' === state.cat && 'all' === state.month;
			var visible = [];

			cards.forEach( function ( card ) {
				var showCard = unfiltered ? ( ! show || card.index < show ) : matches( card, state.cat, state.month );
				card.el.hidden = ! showCard;
				if ( showCard ) {
					visible.push( card );
				}

				// Datum termina iz odabranog mjeseca.
				if ( card.time ) {
					var monthDate = 'all' !== state.month ? card.dates[ state.month ] : null;
					card.time.textContent = monthDate ? monthDate[ 1 ] : card.defaultText;
					card.time.setAttribute( 'datetime', monthDate ? monthDate[ 0 ] : card.defaultDate );
				}
				card.el.style.order = '';
			} );

			// U odabranom mjesecu složi po datumu termina u tom mjesecu.
			if ( 'all' !== state.month ) {
				visible
					.slice()
					.sort( function ( a, b ) {
						var da = a.dates[ state.month ] ? a.dates[ state.month ][ 0 ] : '';
						var db = b.dates[ state.month ] ? b.dates[ state.month ][ 0 ] : '';
						return da < db ? -1 : da > db ? 1 : a.index - b.index;
					} )
					.forEach( function ( card, order ) {
						card.el.style.order = String( order );
					} );
			}

			// Gumbi: odabrani, i zasivljeni ako u kombinaciji s drugim filtrom nema izleta.
			each( groups, function ( group ) {
				var name = group.getAttribute( 'data-paiz-group' );
				each( group.querySelectorAll( '[data-paiz-filter]' ), function ( button ) {
					var value = button.getAttribute( 'data-paiz-filter' );
					var active = state[ name ] === value;
					var available = 'all' === value || ( 'cat' === name ? anyMatch( value, state.month ) : anyMatch( state.cat, value ) );
					button.classList.toggle( 'is-active', active );
					button.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
					button.classList.toggle( 'is-disabled', ! available );
					if ( available ) {
						button.removeAttribute( 'aria-disabled' );
					} else {
						button.setAttribute( 'aria-disabled', 'true' );
					}
				} );
			} );

			if ( empty ) {
				empty.hidden = visible.length > 0;
			}
			if ( status ) {
				status.textContent = ( status.getAttribute( 'data-paiz-status-text' ) || '%d' ).replace( '%d', visible.length );
			}
		}

		each( groups, function ( group ) {
			var name = group.getAttribute( 'data-paiz-group' ) || 'cat';
			group.hidden = false;
			group.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( '[data-paiz-filter]' );
				if ( ! button ) {
					return;
				}
				state[ name ] = button.getAttribute( 'data-paiz-filter' );
				apply();
				if ( 'month' === name && button.scrollIntoView ) {
					button.scrollIntoView( { block: 'nearest', inline: 'nearest' } );
				}
			} );
		} );

		apply();
		if ( status ) {
			status.textContent = '';
		}
	}

	function boot() {
		each( document.querySelectorAll( '[data-paiz]' ), init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
