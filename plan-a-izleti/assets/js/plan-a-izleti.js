/**
 * Plan A izleti – filtriranje po vrsti izleta i terminu bez ponovnog učitavanja stranice.
 *
 * Dva izbornika ("Vrsta izleta", "Termin") otvaraju panel s opcijama; istovremeno
 * je otvoren najviše jedan panel. Bez odabranog filtra ("Svi izleti" + "Svi datumi")
 * vidi se prvih N izleta (atribut show); uz odabir se vide svi odgovarajući izleti.
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

		var wrap = root.querySelector( '[data-paiz-filters]' );
		if ( ! wrap ) {
			return;
		}

		var show = parseInt( root.getAttribute( 'data-paiz-show' ), 10 ) || 0;
		var status = root.querySelector( '[data-paiz-status]' );
		var empty = root.querySelector( '[data-paiz-empty]' );
		var toggles = wrap.querySelectorAll( '[data-paiz-toggle]' );
		var panels = wrap.querySelectorAll( '[data-paiz-panel]' );
		var state = { cat: 'all', month: 'all' };
		var open = null;

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

		function toggleFor( name ) {
			return wrap.querySelector( '[data-paiz-toggle="' + name + '"]' );
		}

		function panelFor( name ) {
			return wrap.querySelector( '[data-paiz-panel="' + name + '"]' );
		}

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

			// Opcije: odabrana, i zasivljene/neaktivne ako uz drugi filtar nema izleta.
			each( panels, function ( panel ) {
				var name = panel.getAttribute( 'data-paiz-panel' );
				var toggle = toggleFor( name );
				each( panel.querySelectorAll( '[data-paiz-filter]' ), function ( option ) {
					var value = option.getAttribute( 'data-paiz-filter' );
					var active = state[ name ] === value;
					var available = 'all' === value || ( 'cat' === name ? anyMatch( value, state.month ) : anyMatch( state.cat, value ) );
					option.classList.toggle( 'is-active', active );
					option.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
					option.classList.toggle( 'is-disabled', ! available );
					if ( available ) {
						option.removeAttribute( 'aria-disabled' );
					} else {
						option.setAttribute( 'aria-disabled', 'true' );
					}
					if ( active && toggle ) {
						toggle.querySelector( '[data-paiz-value]' ).textContent = option.textContent;
					}
				} );
			} );

			if ( empty ) {
				empty.hidden = visible.length > 0;
			}
			if ( status ) {
				var text = ( status.getAttribute( 'data-paiz-status-text' ) || '%d' ).replace( '%d', visible.length );
				// Mijenjaj samo kad se broj promijeni, da čitač zaslona ne ponavlja isto.
				if ( status.textContent.trim() !== text ) {
					status.textContent = text;
				}
			}
		}

		function closePanel( returnFocus ) {
			if ( ! open ) {
				return;
			}
			var toggle = toggleFor( open );
			panelFor( open ).hidden = true;
			toggle.setAttribute( 'aria-expanded', 'false' );
			toggle.classList.remove( 'is-open' );
			open = null;
			if ( returnFocus ) {
				toggle.focus();
			}
		}

		function openPanel( name ) {
			closePanel( false );
			var toggle = toggleFor( name );
			var panel = panelFor( name );
			panel.hidden = false;
			toggle.setAttribute( 'aria-expanded', 'true' );
			toggle.classList.add( 'is-open' );
			open = name;
			var current = panel.querySelector( '.paiz-option.is-active' ) || panel.querySelector( '.paiz-option' );
			if ( current ) {
				current.focus( { preventScroll: true } );
			}
		}

		each( toggles, function ( toggle ) {
			toggle.addEventListener( 'click', function () {
				var name = toggle.getAttribute( 'data-paiz-toggle' );
				if ( open === name ) {
					closePanel( false );
				} else {
					openPanel( name );
				}
			} );
		} );

		each( panels, function ( panel ) {
			var name = panel.getAttribute( 'data-paiz-panel' );
			panel.addEventListener( 'click', function ( event ) {
				var option = event.target.closest( '[data-paiz-filter]' );
				if ( ! option || 'true' === option.getAttribute( 'aria-disabled' ) ) {
					return;
				}
				state[ name ] = option.getAttribute( 'data-paiz-filter' );
				apply();
				// Kod odabira tipkovnicom (detail = 0) fokus se vraća na izbornik.
				closePanel( 0 === event.detail );
			} );
		} );

		// Klik izvan izbornika i panela zatvara panel.
		document.addEventListener( 'click', function ( event ) {
			if ( open && ! wrap.contains( event.target ) ) {
				closePanel( false );
			}
		} );

		// Escape zatvara panel; fokus koji napusti filtere također ga zatvara.
		wrap.addEventListener( 'keydown', function ( event ) {
			if ( open && ( 'Escape' === event.key || 'Esc' === event.key ) ) {
				event.preventDefault();
				closePanel( true );
			}
		} );
		wrap.addEventListener( 'focusout', function ( event ) {
			if ( open && event.relatedTarget && ! wrap.contains( event.relatedTarget ) ) {
				closePanel( false );
			}
		} );

		wrap.hidden = false;
		apply();
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
