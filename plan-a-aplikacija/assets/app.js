/**
 * Plan A aplikacija – registracija service workera, poziv na instalaciju
 * i donja navigacija u načinu aplikacije.
 */
( function () {
	'use strict';

	var config = window.planAApp || {};
	var i18n = config.i18n || {};
	var KEY_VISITS = 'planAApp.visits';
	var KEY_SESSION = 'planAApp.session';
	var KEY_DISMISSED = 'planAApp.dismissedAt';
	var KEY_DISMISSED_VISIT = 'planAApp.dismissedVisit';
	var DISMISS_DAYS = 30;

	function storage( type ) {
		try {
			var s = window[ type ];
			var probe = '__planA';
			s.setItem( probe, '1' );
			s.removeItem( probe );
			return s;
		} catch ( e ) {
			return null;
		}
	}
	var local = storage( 'localStorage' );
	var session = storage( 'sessionStorage' );

	function isStandalone() {
		return ( window.matchMedia && window.matchMedia( '(display-mode: standalone)' ).matches ) || window.navigator.standalone === true;
	}

	function isIOS() {
		var ua = window.navigator.userAgent || '';
		return /iphone|ipad|ipod/i.test( ua ) || ( 'MacIntel' === window.navigator.platform && window.navigator.maxTouchPoints > 1 );
	}

	function isMobile() {
		return !! ( window.matchMedia && window.matchMedia( '(hover: none) and (pointer: coarse)' ).matches );
	}

	// --- Način aplikacije: donja navigacija ---------------------------------
	function applyStandalone() {
		document.documentElement.classList.toggle( 'plan-a-standalone', isStandalone() );
	}
	applyStandalone();
	if ( window.matchMedia ) {
		var mq = window.matchMedia( '(display-mode: standalone)' );
		if ( mq.addEventListener ) {
			mq.addEventListener( 'change', applyStandalone );
		}
	}

	// --- Anonimna statistika (samo u instaliranoj aplikaciji) -----------------
	// Bez kolačića i identifikatora: uređaj sam pamti je li već javio
	// prvo otvaranje, današnji dan i ovo pokretanje.
	function platform() {
		if ( isIOS() ) {
			return 'ios';
		}
		return /android/i.test( window.navigator.userAgent || '' ) ? 'android' : 'other';
	}

	// Kolačić sesije po kojem poslužitelj prepoznaje rezervaciju iz aplikacije.
	// Android aplikacija i Chrome dijele kolačiće, pa se u pregledniku briše.
	function markSource() {
		var secure = 'https:' === location.protocol ? '; Secure' : '';
		if ( config.statUrl && isStandalone() ) {
			document.cookie = 'plan_a_app_src=' + platform() + '; path=/; SameSite=Lax' + secure;
		} else if ( document.cookie.indexOf( 'plan_a_app_src=' ) !== -1 ) {
			document.cookie = 'plan_a_app_src=; path=/; max-age=0; SameSite=Lax' + secure;
		}
	}
	markSource();

	function reportUsage() {
		if ( ! config.statUrl || ! isStandalone() || ! window.navigator.sendBeacon ) {
			return;
		}
		var store = storage( 'localStorage' );
		var sess = storage( 'sessionStorage' );
		if ( ! store ) {
			return;
		}
		var d = new Date();
		var today = d.getFullYear() + '-' + ( d.getMonth() + 1 ) + '-' + d.getDate();
		var events = [];
		if ( ! store.getItem( 'planAApp.stats.first' ) ) {
			events.push( 'first' );
			store.setItem( 'planAApp.stats.first', '1' );
		}
		if ( store.getItem( 'planAApp.stats.day' ) !== today ) {
			events.push( 'day' );
			store.setItem( 'planAApp.stats.day', today );
		}
		if ( sess && ! sess.getItem( 'planAApp.stats.open' ) ) {
			events.push( 'open' );
			sess.setItem( 'planAApp.stats.open', '1' );
		}
		if ( events.length ) {
			var data = new FormData();
			data.append( 'platform', platform() );
			data.append( 'events', events.join( ',' ) );
			window.navigator.sendBeacon( config.statUrl, data );
		}
	}
	reportUsage();

	// --- Service worker ------------------------------------------------------
	if ( 'serviceWorker' in navigator && config.swUrl && ( 'https:' === location.protocol || 'localhost' === location.hostname ) ) {
		window.addEventListener( 'load', function () {
			navigator.serviceWorker.register( config.swUrl, { scope: config.scope || '/' } ).catch( function () {} );
		} );
	}

	// --- Broj posjeta (jednom po sesiji preglednika) -------------------------
	var visits = 0;
	if ( local ) {
		visits = parseInt( local.getItem( KEY_VISITS ), 10 ) || 0;
		if ( ! session || ! session.getItem( KEY_SESSION ) ) {
			visits++;
			local.setItem( KEY_VISITS, String( visits ) );
			if ( session ) {
				session.setItem( KEY_SESSION, '1' );
			}
		}
	}

	// "Ne sada" vrijedi 30 dana: uz postavku "od druge posjete", a na iPhoneu uvijek,
	// jer Safari ne može saznati je li aplikacija već dodana na početni zaslon.
	function longDismiss() {
		return ! config.promptEvery || isIOS();
	}

	function dismissedRecently() {
		if ( ! longDismiss() ) {
			// Pri svakoj posjeti: "Ne sada" vrijedi samo do kraja ove posjete.
			return !! ( session && session.getItem( KEY_DISMISSED_VISIT ) );
		}
		var at = local ? parseInt( local.getItem( KEY_DISMISSED ), 10 ) : 0;
		return !! at && Date.now() - at < DISMISS_DAYS * 24 * 60 * 60 * 1000;
	}

	function remember() {
		if ( ! longDismiss() ) {
			if ( session ) {
				session.setItem( KEY_DISMISSED_VISIT, '1' );
			}
			return;
		}
		if ( local ) {
			local.setItem( KEY_DISMISSED, String( Date.now() ) );
		}
	}

	function canPrompt() {
		return config.installPrompt && ! config.noPromptPage && ! isStandalone() && isMobile() && visits >= ( config.promptEvery ? 1 : 2 ) && ! dismissedRecently();
	}

	// --- Traka za instalaciju ------------------------------------------------
	var bar = null;

	function hideBar() {
		if ( bar ) {
			bar.parentNode.removeChild( bar );
			bar = null;
			document.documentElement.classList.remove( 'plan-a-has-install-bar' );
		}
	}

	function button( text, className, onClick ) {
		var b = document.createElement( 'button' );
		b.type = 'button';
		b.className = className;
		b.textContent = text;
		b.addEventListener( 'click', onClick );
		return b;
	}

	function showBar( mode, onInstall ) {
		if ( bar || ! document.body ) {
			return;
		}
		bar = document.createElement( 'div' );
		bar.className = 'plan-a-install plan-a-install--' + mode;
		bar.setAttribute( 'role', 'region' );
		bar.setAttribute( 'aria-label', i18n.barLabel || '' );

		var text = document.createElement( 'div' );
		text.className = 'plan-a-install__text';
		var title = document.createElement( 'strong' );
		title.textContent = i18n.title || '';
		text.appendChild( title );
		if ( 'ios' === mode ) {
			var hint = document.createElement( 'span' );
			hint.className = 'plan-a-install__hint';
			hint.textContent = i18n.iosText || '';
			text.appendChild( hint );
		}

		var actions = document.createElement( 'div' );
		actions.className = 'plan-a-install__actions';
		actions.appendChild( button( i18n.notNow || '', 'plan-a-install__later', function () {
			remember();
			hideBar();
		} ) );
		if ( 'install' === mode ) {
			actions.appendChild( button( i18n.install || '', 'plan-a-install__go', onInstall ) );
		}

		bar.appendChild( text );
		bar.appendChild( actions );
		document.body.appendChild( bar );
		document.documentElement.classList.add( 'plan-a-has-install-bar' );
	}

	if ( config.installPrompt ) {
		// Android / Chrome: preglednik javlja da je instalacija moguća.
		window.addEventListener( 'beforeinstallprompt', function ( event ) {
			event.preventDefault();
			var deferred = event;
			if ( ! canPrompt() ) {
				return;
			}
			showBar( 'install', function () {
				hideBar();
				deferred.prompt();
				if ( deferred.userChoice ) {
					deferred.userChoice.then( function ( choice ) {
						if ( choice && 'dismissed' === choice.outcome ) {
							remember();
						}
					} );
				}
			} );
		} );

		window.addEventListener( 'appinstalled', function () {
			hideBar();
			remember();
		} );

		// iPhone / iPad: automatska instalacija ne postoji, prikaži uputu.
		if ( isIOS() && canPrompt() ) {
			if ( 'loading' === document.readyState ) {
				document.addEventListener( 'DOMContentLoaded', function () {
					showBar( 'ios' );
				} );
			} else {
				showBar( 'ios' );
			}
		}
	}
} )();
