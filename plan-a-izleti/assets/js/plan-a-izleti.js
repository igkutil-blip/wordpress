/**
 * Plan A izleti – filtriranje po vrsti izleta i terminu bez ponovnog učitavanja stranice.
 *
 * Dva izbornika ("Vrsta izleta", "Termin") otvaraju panel s opcijama; istovremeno
 * je otvoren najviše jedan panel. U svakom rezultatu filtra vidi se prvih N izleta
 * (atribut show); "Prikaži još izleta" dodaje sljedećih step, a promjena filtra vraća
 * prikaz na N. "Pogledaj sve izlete" vodi na all_url s odabranim filtrima u adresi
 * (?vrsta=<slug>&termin=GGGG-MM), koje shortcode na toj stranici odmah primijeni.
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

		// Bez izbornika (nema kategorija ni mjeseci) radi samo "Prikaži još izleta".
		var wrap = root.querySelector( '[data-paiz-filters]' ) || document.createElement( 'div' );

		var show = parseInt( root.getAttribute( 'data-paiz-show' ), 10 ) || 0;
		var step = parseInt( root.getAttribute( 'data-paiz-step' ), 10 ) || show;
		var limit = show;
		var status = root.querySelector( '[data-paiz-status]' );
		var empty = root.querySelector( '[data-paiz-empty]' );
		var actions = root.querySelector( '[data-paiz-actions]' );
		var moreBtn = root.querySelector( '[data-paiz-more]' );
		var allLink = root.querySelector( '[data-paiz-all]' );
		var allHref = allLink ? allLink.getAttribute( 'href' ) : '';
		var toggles = wrap.querySelectorAll( '[data-paiz-toggle]' );
		var panels = wrap.querySelectorAll( '[data-paiz-panel]' );
		var state = {
			cat: root.getAttribute( 'data-paiz-initial-cat' ) || 'all',
			month: root.getAttribute( 'data-paiz-initial-month' ) || 'all',
		};
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
				soldOut: el.classList.contains( 'is-sold-out' ),
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

		// Vrijednost filtra za adresu: slug kategorije ili GGGG-MM.
		function paramFor( name ) {
			if ( 'all' === state[ name ] ) {
				return '';
			}
			var option = wrap.querySelector( '[data-paiz-panel="' + name + '"] [data-paiz-filter="' + state[ name ] + '"]' );
			return option ? option.getAttribute( 'data-paiz-param' ) || '' : '';
		}

		function updateAllLink() {
			if ( ! allLink || ! window.URL ) {
				return;
			}
			try {
				var url = new URL( allHref, window.location.href );
				var params = { vrsta: paramFor( 'cat' ), termin: paramFor( 'month' ) };
				Object.keys( params ).forEach( function ( key ) {
					if ( params[ key ] ) {
						url.searchParams.set( key, params[ key ] );
					} else {
						url.searchParams.delete( key );
					}
				} );
				allLink.setAttribute( 'href', url.href );
			} catch ( e ) {}
		}

		// Kartice koje su upravo otkrivene dobivaju blagi prijelaz.
		function reveal( list ) {
			if ( ! list.length || ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) ) {
				return;
			}
			list.forEach( function ( card ) {
				card.el.classList.add( 'is-entering' );
			} );
			void root.offsetHeight; // početno stanje prije prijelaza
			window.requestAnimationFrame( function () {
				list.forEach( function ( card ) {
					card.el.classList.remove( 'is-entering' );
				} );
			} );
		}

		function apply( animate ) {
			var result = [];

			cards.forEach( function ( card ) {
				if ( matches( card, state.cat, state.month ) ) {
					result.push( card );
				}

				// Datum termina iz odabranog mjeseca.
				if ( card.time ) {
					var monthDate = 'all' !== state.month ? card.dates[ state.month ] : null;
					card.time.textContent = monthDate ? monthDate[ 1 ] : card.defaultText;
					card.time.setAttribute( 'datetime', monthDate ? monthDate[ 0 ] : card.defaultDate );
				}
				card.el.style.order = '';
			} );

			// U odabranom mjesecu složi po datumu termina u tom mjesecu; popunjeni na kraj.
			if ( 'all' !== state.month ) {
				result.sort( function ( a, b ) {
					if ( a.soldOut !== b.soldOut ) {
						return a.soldOut ? 1 : -1;
					}
					var da = a.dates[ state.month ] ? a.dates[ state.month ][ 0 ] : '';
					var db = b.dates[ state.month ] ? b.dates[ state.month ][ 0 ] : '';
					return da < db ? -1 : da > db ? 1 : a.index - b.index;
				} );
				result.forEach( function ( card, order ) {
					card.el.style.order = String( order );
				} );
			}

			// Ograničenje (show, pa svakim klikom +step) vrijedi za rezultat filtra.
			var visible = limit ? result.slice( 0, limit ) : result;
			// Novootkriveni izleti, redom kojim se prikazuju.
			var revealed = visible.filter( function ( card ) {
				return card.el.hidden;
			} );
			cards.forEach( function ( card ) {
				card.el.hidden = visible.indexOf( card ) === -1;
			} );
			if ( animate ) {
				reveal( revealed );
			}

			// Gumbi samo kad rezultat ima više izleta od početnog prikaza.
			if ( actions ) {
				actions.hidden = ! show || result.length <= show;
			}
			if ( moreBtn ) {
				moreBtn.hidden = visible.length >= result.length;
			}
			updateAllLink();

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
				var text = ( status.getAttribute( 'data-paiz-status-text' ) || '%1$d / %2$d' )
					.replace( '%1$d', visible.length )
					.replace( '%2$d', result.length );
				// Mijenjaj samo kad se broj promijeni, da čitač zaslona ne ponavlja isto.
				if ( status.textContent.trim() !== text ) {
					status.textContent = text;
				}
			}
			fitShareTags( root );
			return revealed;
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
				limit = show; // promjena filtra vraća početni broj izleta
				apply( false );
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

		if ( moreBtn ) {
			moreBtn.addEventListener( 'click', function () {
				limit += step;
				var revealed = apply( true );
				// Kad gumb nestane, fokus ide na prvi novi izlet (ne gubi se na stranici).
				if ( moreBtn.hidden ) {
					var link = revealed[ 0 ] && revealed[ 0 ].el.querySelector( '.paiz-card__title a' );
					if ( link ) {
						link.focus( { preventScroll: true } );
					}
				}
			} );
		}

		wrap.hidden = false;
		apply( false );
	}

	/**
	 * Oblačić s nazivom aktivnosti: prelazak mišem ili fokus tipkovnicom ga prikaže,
	 * dodir (klik) ga prikaže na 2 sekunde. Ikona je gumb izvan poveznice kartice,
	 * pa dodir ikone ne otvara izlet. Oblačić se drži unutar rubova ekrana.
	 */
	var tip = null;
	var tipTimer = null;
	var tipOwner = null;

	function showTip( button, duration ) {
		var text = button.getAttribute( 'data-paiz-tip' );
		if ( ! text ) {
			return;
		}
		if ( ! tip ) {
			tip = document.createElement( 'div' );
			tip.className = 'paiz-tip';
			tip.setAttribute( 'aria-hidden', 'true' ); // naziv je već u aria-label gumba
			document.body.appendChild( tip );
		}
		window.clearTimeout( tipTimer );
		tipOwner = button;
		tip.textContent = text;
		tip.hidden = false;

		var margin = 8;
		var rect = button.getBoundingClientRect();
		var width = tip.offsetWidth;
		var center = rect.left + rect.width / 2;
		var left = Math.min( Math.max( center - width / 2, margin ), window.innerWidth - width - margin );
		var top = rect.top - tip.offsetHeight - 8;
		if ( top < margin ) {
			top = rect.bottom + 8; // nema mjesta iznad: prikaži ispod ikone
		}
		tip.style.left = left + 'px';
		tip.style.top = top + 'px';
		tip.style.setProperty( '--paiz-tip-arrow', ( center - left ) + 'px' );
		tip.classList.toggle( 'is-below', top > rect.top );

		if ( duration ) {
			tipTimer = window.setTimeout( hideTip, duration );
		}
	}

	function hideTip() {
		window.clearTimeout( tipTimer );
		tipOwner = null;
		if ( tip ) {
			tip.hidden = true;
		}
	}

	function initTips() {
		var actSelector = '.paiz .paiz-act';
		var lastPointer = '';
		document.addEventListener( 'pointerdown', function ( event ) {
			lastPointer = event.pointerType || '';
		}, { passive: true } );
		document.addEventListener( 'mouseover', function ( event ) {
			var button = event.target.closest && event.target.closest( actSelector );
			if ( button && button !== tipOwner ) {
				showTip( button, 0 );
			}
		} );
		document.addEventListener( 'mouseout', function ( event ) {
			var button = event.target.closest && event.target.closest( actSelector );
			if ( button && ! button.contains( event.relatedTarget ) && document.activeElement !== button ) {
				hideTip();
			}
		} );
		document.addEventListener( 'focusin', function ( event ) {
			var button = event.target.closest && event.target.closest( actSelector );
			if ( button ) {
				showTip( button, 0 );
			}
		} );
		document.addEventListener( 'focusout', function ( event ) {
			if ( event.target.closest && event.target.closest( actSelector ) ) {
				hideTip();
			}
		} );
		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest && event.target.closest( actSelector );
			if ( button ) {
				event.preventDefault();
				// Miš: oblačić već prikazuje prelazak mišem. Dodir: prikaži na 2 sekunde.
				if ( 'mouse' !== lastPointer ) {
					showTip( button, 2000 );
				}
			}
		} );
		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key || 'Esc' === event.key ) {
				hideTip();
			}
		} );
		window.addEventListener( 'scroll', hideTip, { passive: true } );
		window.addEventListener( 'resize', hideTip );
	}

	/**
	 * "Predloži ekipi". Poveznica je wa.me (radi i bez JavaScripta). Na mobitelu s
	 * Web Share API-jem otvara se sustavni izbornik za dijeljenje, uz istaknutu
	 * sliku ako uređaj podržava dijeljenje datoteka. Ako dijeljenje ne uspije
	 * (osim kad ga korisnik sam odustane), otvara se wa.me.
	 */
	var shareFiles = {};

	function isMobile() {
		return !! ( window.matchMedia && window.matchMedia( '(hover: none) and (pointer: coarse)' ).matches );
	}

	// Slika se počne preuzimati već pri dodiru, da bude spremna u trenutku klika.
	function prefetchImage( link ) {
		var src = link.getAttribute( 'data-paiz-share-image' );
		if ( ! src || shareFiles[ src ] || ! window.fetch || ! window.File ) {
			return src ? shareFiles[ src ] : null;
		}
		shareFiles[ src ] = window.fetch( src, { credentials: 'same-origin' } )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'image' );
				}
				return response.blob();
			} )
			.then( function ( blob ) {
				var type = blob.type || 'image/jpeg';
				var name = ( src.split( '?' )[ 0 ].split( '/' ).pop() || 'izlet.jpg' ).replace( /[^\w.-]/g, '' ) || 'izlet.jpg';
				return new window.File( [ blob ], name, { type: type } );
			} )
			.catch( function () {
				return null;
			} );
		return shareFiles[ src ];
	}

	function withTimeout( promise, ms ) {
		return Promise.race( [
			promise,
			new Promise( function ( resolve ) {
				window.setTimeout( function () {
					resolve( null );
				}, ms );
			} ),
		] );
	}

	function openWhatsApp( link ) {
		window.location.href = link.href;
	}

	// iPhone/iPad: uz sliku se tekst poruke može izgubiti, pa se dijeli samo tekst.
	function isIOS() {
		var ua = window.navigator.userAgent || '';
		return /iphone|ipad|ipod/i.test( ua ) || ( 'MacIntel' === window.navigator.platform && window.navigator.maxTouchPoints > 1 );
	}

	/**
	 * Dijeljenje na mobitelu:
	 * - uređaj podržava dijeljenje datoteka: slika kartice izleta + tekst poruke s poveznicom,
	 * - iPhone ili bez podrške za datoteke: samo tekst poruke s poveznicom,
	 * - neuspjeh (osim kad korisnik odustane): wa.me s tekstom poruke.
	 */
	function share( link ) {
		var title = link.getAttribute( 'data-paiz-share-title' ) || '';
		var text = link.getAttribute( 'data-paiz-share-text' ) || '';
		var url = link.getAttribute( 'data-paiz-share-url' ) || '';
		var message = text + '\n' + url; // poveznica u tekstu: ne gubi se ni uz sliku
		var pending = isIOS() ? Promise.resolve( null ) : ( prefetchImage( link ) || Promise.resolve( null ) );

		withTimeout( pending, 2000 )
			.then( function ( file ) {
				if ( file && navigator.canShare && navigator.canShare( { files: [ file ] } ) ) {
					return navigator.share( { title: title, text: message, files: [ file ] } );
				}
				return navigator.share( { text: message } );
			} )
			.catch( function ( error ) {
				if ( ! error || 'AbortError' !== error.name ) {
					openWhatsApp( link );
				}
			} );
	}

	function initShare() {
		var selector = '[data-paiz-share]';
		var canShare = !! navigator.share && isMobile();

		if ( canShare && ! isIOS() ) {
			var warm = function ( event ) {
				var link = event.target.closest && event.target.closest( selector );
				if ( link ) {
					prefetchImage( link );
				}
			};
			document.addEventListener( 'pointerdown', warm, { passive: true } );
			document.addEventListener( 'focusin', warm );
		}

		// Dijeljenje kao i prije: mobitel = sustavni izbornik (slika + tekst, iPhone samo
		// tekst), računalo = wa.me u novoj kartici. Poziva se izravno iz klika.
		function send( link ) {
			if ( canShare ) {
				share( link );
				return;
			}
			// Bez "noopener" u trećem argumentu: tada window.open uvijek vraća null.
			var win = window.open( link.href, '_blank' );
			if ( win ) {
				win.opener = null;
			} else {
				openWhatsApp( link ); // skočni prozor blokiran
			}
		}

		document.addEventListener( 'click', function ( event ) {
			var link = event.target.closest && event.target.closest( selector );
			if ( ! link ) {
				return;
			}
			if ( preview.wanted() ) {
				event.preventDefault();
				if ( canShare && ! isIOS() ) {
					prefetchImage( link ); // slika spremna dok korisnik čita pregled
				}
				preview.open( link, send );
				return;
			}
			if ( ! canShare ) {
				return; // obična wa.me poveznica u novoj kartici
			}
			event.preventDefault();
			share( link );
		} );
	}

	/**
	 * Pregled poruke prije dijeljenja: slika kartice izleta i tekst poruke u
	 * "WhatsApp oblačiću". Jedini gumb "Pošalji u WhatsApp" pokreće dijeljenje.
	 * Mobitel: panel s dna ekrana (zatvara se dodirom izvan njega ili povlačenjem
	 * prema dolje); računalo: prozor na sredini (zatvara se klikom izvan njega i tipkom Esc).
	 * Postavka "Pregled prije slanja": first3 (prva 3 puta na uređaju), always, never.
	 */
	var preview = ( function () {
		var config = window.planAIzletiShare || {};
		var text = config.i18n || {};
		var mode = config.preview || 'first3';
		var KEY = 'planAIzleti.sharePreviews';
		var LIMIT = 3;
		var root = null;
		var panel = null;
		var body = null;
		var img = null;
		var bubble = null;
		var sendBtn = null;
		var owner = null;
		var sendFn = null;
		var lastFocus = null;

		function store() {
			try {
				var s = window.localStorage;
				s.setItem( KEY + '.probe', '1' );
				s.removeItem( KEY + '.probe' );
				return s;
			} catch ( e ) {
				return null;
			}
		}

		function wanted() {
			if ( 'never' === mode ) {
				return false;
			}
			if ( 'always' === mode ) {
				return true;
			}
			var s = store();
			if ( ! s ) {
				return true; // bez localStoragea pregled se prikazuje uvijek
			}
			return ( parseInt( s.getItem( KEY ), 10 ) || 0 ) < LIMIT;
		}

		function count() {
			var s = store();
			if ( s && 'first3' === mode ) {
				s.setItem( KEY, String( ( parseInt( s.getItem( KEY ), 10 ) || 0 ) + 1 ) );
			}
		}

		function isSheet() {
			return !! ( window.matchMedia && window.matchMedia( '(max-width: 767px), (hover: none) and (pointer: coarse)' ).matches );
		}

		// Tekst kao u WhatsAppu: *podebljano*, prijelomi reda, poveznica kao tekst.
		function renderMessage( target, message ) {
			target.textContent = '';
			message.split( '\n' ).forEach( function ( line, index ) {
				if ( index ) {
					target.appendChild( document.createElement( 'br' ) );
				}
				var parts = line.split( /(\*[^*\n]+\*)/ );
				parts.forEach( function ( part ) {
					if ( /^\*[^*\n]+\*$/.test( part ) ) {
						var strong = document.createElement( 'strong' );
						strong.textContent = part.slice( 1, -1 );
						target.appendChild( strong );
					} else if ( /^https?:\/\//.test( part ) ) {
						var url = document.createElement( 'span' );
						url.className = 'paiz-preview__url';
						url.textContent = part;
						target.appendChild( url );
					} else if ( part ) {
						target.appendChild( document.createTextNode( part ) );
					}
				} );
			} );
		}

		function build() {
			root = document.createElement( 'div' );
			root.className = 'paiz-preview';
			root.hidden = true;

			panel = document.createElement( 'div' );
			panel.className = 'paiz-preview__panel';
			panel.setAttribute( 'role', 'dialog' );
			panel.setAttribute( 'aria-modal', 'true' );
			panel.setAttribute( 'aria-labelledby', 'paiz-preview-title' );

			var handle = document.createElement( 'div' );
			handle.className = 'paiz-preview__handle';
			handle.setAttribute( 'aria-hidden', 'true' );

			body = document.createElement( 'div' );
			body.className = 'paiz-preview__body';

			var title = document.createElement( 'p' );
			title.className = 'paiz-preview__title';
			title.id = 'paiz-preview-title';
			title.textContent = text.title || '';

			img = document.createElement( 'img' );
			img.className = 'paiz-preview__img';
			img.alt = text.image || '';
			img.decoding = 'async';

			bubble = document.createElement( 'div' );
			bubble.className = 'paiz-preview__bubble';

			body.appendChild( title );
			body.appendChild( img );
			body.appendChild( bubble );

			var foot = document.createElement( 'div' );
			foot.className = 'paiz-preview__foot';
			sendBtn = document.createElement( 'button' );
			sendBtn.type = 'button';
			sendBtn.className = 'paiz-preview__send';
			sendBtn.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M17.47 14.38c-.3-.15-1.75-.86-2.02-.96-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.64.07-.3-.15-1.25-.46-2.38-1.47-.88-.79-1.47-1.76-1.65-2.06-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.06 2.88 1.21 3.08.15.2 2.1 3.2 5.08 4.48.71.31 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.75-.72 2-1.41.25-.69.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35z"/><path d="M12.04 2C6.5 2 2 6.48 2 12c0 1.77.46 3.5 1.34 5.02L2 22l5.12-1.34A10.03 10.03 0 0 0 12.04 22C17.56 22 22 17.52 22 12S17.56 2 12.04 2zm0 18.3c-1.5 0-2.97-.4-4.25-1.16l-.3-.18-3.04.8.81-2.96-.2-.31A8.26 8.26 0 0 1 3.77 12c0-4.56 3.71-8.27 8.27-8.27 4.56 0 8.25 3.71 8.25 8.27 0 4.57-3.7 8.3-8.25 8.3z"/></svg>';
			var label = document.createElement( 'span' );
			label.textContent = text.send || '';
			sendBtn.appendChild( label );
			foot.appendChild( sendBtn );

			panel.appendChild( handle );
			panel.appendChild( body );
			panel.appendChild( foot );
			root.appendChild( panel );
			document.body.appendChild( root );

			// Slanje izravno iz ovog klika (potrebno za sustavni izbornik i nove kartice).
			sendBtn.addEventListener( 'click', function () {
				var link = owner;
				close( false );
				if ( link && sendFn ) {
					sendFn( link );
				}
			} );

			// Dodir/klik izvan panela zatvara.
			root.addEventListener( 'click', function ( event ) {
				if ( ! panel.contains( event.target ) ) {
					close( true );
				}
			} );

			document.addEventListener( 'keydown', function ( event ) {
				if ( isOpen() && ( 'Escape' === event.key || 'Esc' === event.key ) ) {
					event.preventDefault();
					close( true );
				}
				// Fokus ostaje u prozoru (jedini gumb).
				if ( isOpen() && 'Tab' === event.key ) {
					event.preventDefault();
					sendBtn.focus();
				}
			} );

			initDrag();
		}

		// Mobitel: povlačenje panela prema dolje zatvara ga (kad je sadržaj na vrhu).
		function initDrag() {
			var startY = null;
			var delta = 0;
			panel.addEventListener( 'touchstart', function ( event ) {
				if ( ! root.classList.contains( 'is-sheet' ) || event.touches.length !== 1 ) {
					return;
				}
				var fromBody = body.contains( event.target );
				if ( fromBody && body.scrollTop > 0 ) {
					startY = null;
					return;
				}
				startY = event.touches[ 0 ].clientY;
				delta = 0;
			}, { passive: true } );
			panel.addEventListener( 'touchmove', function ( event ) {
				if ( null === startY ) {
					return;
				}
				delta = event.touches[ 0 ].clientY - startY;
				if ( delta > 0 ) {
					panel.style.transform = 'translateY(' + delta + 'px)';
					panel.style.transition = 'none';
					if ( event.cancelable ) {
						event.preventDefault();
					}
				}
			}, { passive: false } );
			function end() {
				if ( null === startY ) {
					return;
				}
				panel.style.transition = '';
				if ( delta > Math.min( 120, panel.offsetHeight * 0.25 ) ) {
					close( true );
				} else {
					panel.style.transform = '';
				}
				startY = null;
				delta = 0;
			}
			panel.addEventListener( 'touchend', end );
			panel.addEventListener( 'touchcancel', end );
		}

		function isOpen() {
			return !! root && ! root.hidden;
		}

		function open( link, send ) {
			if ( ! root ) {
				build();
			}
			owner = link;
			sendFn = send;
			lastFocus = document.activeElement;
			var message = ( link.getAttribute( 'data-paiz-share-text' ) || '' ) + '\n' + ( link.getAttribute( 'data-paiz-share-url' ) || '' );
			renderMessage( bubble, message );
			var src = link.getAttribute( 'data-paiz-share-image' );
			img.hidden = ! src;
			if ( src ) {
				img.src = src;
			}
			root.classList.toggle( 'is-sheet', isSheet() );
			panel.style.transform = '';
			body.scrollTop = 0;
			root.hidden = false;
			document.documentElement.classList.add( 'paiz-preview-open' );
			window.requestAnimationFrame( function () {
				root.classList.add( 'is-visible' );
			} );
			sendBtn.focus( { preventScroll: true } );
			count();
		}

		function close( restoreFocus ) {
			if ( ! isOpen() ) {
				return;
			}
			root.classList.remove( 'is-visible' );
			root.hidden = true;
			panel.style.transform = '';
			document.documentElement.classList.remove( 'paiz-preview-open' );
			if ( restoreFocus && lastFocus && lastFocus.focus ) {
				lastFocus.focus( { preventScroll: true } );
			}
		}

		return {
			wanted: wanted,
			open: open,
		};
	} )();

	/**
	 * Gumb na stranici izleta. U predlošku "smart" PHP ga ispisuje u kartici za
	 * rezervaciju; ovdje se stavlja neposredno iznad njezina naslova. U ostalim
	 * predlošcima premješta se odmah iza gumba za rezervaciju (nemaju kuku na tom mjestu).
	 */
	function placeShareButton() {
		var wrap = document.querySelector( '[data-paiz-share-wrap]' );
		if ( ! wrap ) {
			return;
		}
		var card = wrap.closest( '.ttbm_smart_booking_card, .ttbm-sidebar-booking' );
		if ( card ) {
			var heading = card.querySelector( '.ttbm_smart_booking_title' );
			if ( heading && heading.parentNode ) {
				heading.parentNode.insertBefore( wrap, heading );
			}
			wrap.hidden = false;
			return;
		}
		var after = document.querySelector( '[data-ttbm-book-now], .ttbm_hero_book_now, .ttbm_go_particular_booking' );
		var section = document.getElementById( 'ttbm_booking_section' );
		if ( after && after.parentNode ) {
			after.parentNode.insertBefore( wrap, after.nextSibling );
			wrap.classList.add( 'is-beside-booking' );
		} else if ( section && ! section.contains( wrap ) ) {
			section.insertBefore( wrap, section.firstChild );
		} else if ( wrap.hasAttribute( 'data-paiz-share-fallback' ) ) {
			var content = document.querySelector( '.ttbm_details_page, .ttbm_content__left' );
			if ( ! content ) {
				return; // nije pronađeno prikladno mjesto: gumb ostaje skriven
			}
			content.insertBefore( wrap, content.firstChild );
		}
		wrap.hidden = false;
	}

	/**
	 * Oznaka "Predloži ekipi" na kartici: ako natpis ne stane pokraj oznake
	 * "Popunjeno" ili u širinu slike, ostaje samo ikona.
	 */
	function fitShareTags( scope ) {
		each( ( scope || document ).querySelectorAll( '.paiz-share-tag' ), function ( tag ) {
			var media = tag.parentNode;
			if ( ! media.offsetWidth ) {
				return; // skrivena kartica: provjera kad se prikaže
			}
			tag.classList.remove( 'is-compact' );
			var rect = tag.getBoundingClientRect();
			var box = media.getBoundingClientRect();
			var badge = media.querySelector( '.paiz-card__badge' );
			var limit = badge ? badge.getBoundingClientRect().left - 8 : box.right - 12;
			if ( rect.right > limit ) {
				tag.classList.add( 'is-compact' );
			}
		} );
	}

	function boot() {
		if ( document.querySelector( '.paiz-share-tag' ) ) {
			fitShareTags();
			var fitTimer = null;
			window.addEventListener( 'resize', function () {
				window.clearTimeout( fitTimer );
				fitTimer = window.setTimeout( function () {
					fitShareTags();
				}, 150 );
			} );
			// Fontovi mogu promijeniti širinu natpisa nakon učitavanja.
			if ( document.fonts && document.fonts.ready ) {
				document.fonts.ready.then( function () {
					fitShareTags();
				} );
			}
		}
		if ( document.querySelector( '[data-paiz-share]' ) ) {
			initShare();
			placeShareButton();
		}
		each( document.querySelectorAll( '[data-paiz]' ), init );
		if ( document.querySelector( '.paiz .paiz-act' ) ) {
			initTips();
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
