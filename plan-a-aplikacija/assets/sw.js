/*
 * Plan A aplikacija – service worker.
 * Konfiguraciju (PLAN_A) dodaje PHP ispred ove datoteke.
 *
 * Pravila:
 * - samo GET zahtjevi; sve ostalo ide izravno na mrežu,
 * - košarica, plaćanje, korisnički račun, administracija, prijava, AJAX, REST
 *   i sve stranice s parametrima u adresi: uvijek mreža, nikad predmemorija,
 * - stranice: mreža; spremaju se samo one koje poslužitelj označi zaglavljem
 *   X-Plan-A-Offline (/izleti/ i stranice izleta, samo za neprijavljene posjetitelje),
 *   a koriste se samo kad nema interneta; inače izvanmrežna stranica,
 * - statične datoteke (CSS, JS, fontovi, slike): predmemorija uz osvježavanje u pozadini.
 */
'use strict';

const PREFIX = 'plan-a-';
const STATIC_CACHE = PREFIX + 'static-' + PLAN_A.version;
const PAGES_CACHE = PREFIX + 'pages-' + PLAN_A.version;
const OFFLINE_CACHE = PREFIX + 'offline-' + PLAN_A.version;
const STATIC_LIMIT = 200;
const PAGES_LIMIT = 40;
const STATIC_EXT = /\.(?:css|js|mjs|woff2?|ttf|otf|eot|png|jpe?g|gif|webp|avif|svg|ico)$/i;
const STATIC_QUERY_KEYS = [ 'ver', 'v', 'version' ];
const FONT_HOSTS = [ 'fonts.googleapis.com', 'fonts.gstatic.com' ];
const NETWORK_ONLY_FILES = [ 'admin-ajax.php', 'wp-login.php', 'wp-cron.php', 'xmlrpc.php' ];
const PING_INTERVAL = 10 * 60 * 1000;

let lastPing = 0;
let destroyed = false; // nakon odluke o uklanjanju ništa se više ne sprema

self.addEventListener( 'install', ( event ) => {
	event.waitUntil(
		caches.open( OFFLINE_CACHE )
			.then( ( cache ) => Promise.all( PLAN_A.precache.map( ( url, index ) => {
				const add = cache.add( new Request( url, { cache: 'reload' } ) );
				// Izvanmrežna stranica (prva) je obavezna; ikona nije.
				return index === 0 ? add : add.catch( () => {} );
			} ) ) )
			.then( () => self.skipWaiting() )
	);
} );

self.addEventListener( 'activate', ( event ) => {
	event.waitUntil(
		caches.keys()
			.then( ( keys ) => Promise.all(
				keys
					.filter( ( key ) => key.startsWith( PREFIX ) && ! [ STATIC_CACHE, PAGES_CACHE, OFFLINE_CACHE ].includes( key ) )
					.map( ( key ) => caches.delete( key ) )
			) )
			.then( () => self.clients.claim() )
			.then( () => checkStillActive( true ) )
	);
} );

/**
 * Ako je dodatak deaktiviran ili aplikacija isključena u postavkama:
 * obriši predmemoriju i odjavi ovaj service worker.
 */
function checkStillActive( force ) {
	const now = Date.now();
	if ( ! force && now - lastPing < PING_INTERVAL ) {
		return Promise.resolve();
	}
	lastPing = now;
	return fetch( PLAN_A.pingUrl, { cache: 'no-store', credentials: 'omit' } )
		.then( ( response ) => {
			if ( ! response.ok ) {
				return; // greška poslužitelja: ne zaključuj ništa
			}
			return response.json()
				.then( ( data ) => data && data.planAApp === true && data.active === true )
				.catch( () => false ) // nije JSON: dodatak više ne radi
				.then( ( active ) => ( active ? null : selfDestruct() ) );
		} )
		.catch( () => {} ); // nema mreže: provjeri drugi put
}

function deleteAllCaches() {
	return caches.keys()
		.then( ( keys ) => Promise.all( keys.filter( ( key ) => key.startsWith( PREFIX ) ).map( ( key ) => caches.delete( key ) ) ) );
}

function selfDestruct() {
	destroyed = true;
	return deleteAllCaches()
		.then( () => self.registration.unregister() )
		// Drugi prolaz: upisi koji su već bili u tijeku.
		.then( () => new Promise( ( resolve ) => setTimeout( resolve, 3000 ) ) )
		.then( deleteAllCaches );
}

function put( name, request, response, limit ) {
	if ( destroyed ) {
		return Promise.resolve();
	}
	return caches.open( name )
		.then( ( cache ) => cache.put( request, response ) )
		.then( () => trimCache( name, limit ) );
}

function isNetworkOnly( url ) {
	if ( url.origin !== self.location.origin ) {
		return false;
	}
	const path = url.pathname;
	if ( NETWORK_ONLY_FILES.some( ( file ) => path.endsWith( '/' + file ) ) ) {
		return true;
	}
	if ( url.searchParams.has( 'plan-a-app' ) ) {
		return true;
	}
	return PLAN_A.networkOnlyPaths.some( ( prefix ) => path === prefix || path === prefix.replace( /\/$/, '' ) || path.startsWith( prefix ) );
}

function isStatic( request, url ) {
	const sameOrigin = url.origin === self.location.origin;
	if ( ! sameOrigin && ! FONT_HOSTS.includes( url.hostname ) ) {
		return false;
	}
	if ( sameOrigin ) {
		// Samo parametri verzije (npr. style.css?ver=1.2); sve ostalo nije statično.
		for ( const key of url.searchParams.keys() ) {
			if ( ! STATIC_QUERY_KEYS.includes( key ) ) {
				return false;
			}
		}
	}
	return [ 'style', 'script', 'font', 'image' ].includes( request.destination ) || STATIC_EXT.test( url.pathname );
}

function trimCache( name, limit ) {
	return caches.open( name ).then( ( cache ) => cache.keys().then( ( keys ) => {
		if ( keys.length > limit ) {
			return Promise.all( keys.slice( 0, keys.length - limit ).map( ( key ) => cache.delete( key ) ) );
		}
	} ) );
}

function offlineFallback() {
	return caches.open( OFFLINE_CACHE ).then( ( cache ) => cache.match( PLAN_A.offlineUrl ) );
}

self.addEventListener( 'fetch', ( event ) => {
	const request = event.request;
	if ( destroyed || request.method !== 'GET' || request.headers.has( 'range' ) ) {
		return;
	}
	const url = new URL( request.url );
	if ( url.protocol !== 'https:' && url.protocol !== 'http:' ) {
		return;
	}

	// Stranice (navigacija).
	if ( request.mode === 'navigate' ) {
		if ( url.origin === self.location.origin ) {
			event.waitUntil( checkStillActive( false ) );
		}

		// Košarica, plaćanje, račun, administracija, stranice s parametrima:
		// samo mreža, ništa se ne sprema. Bez veze prikazuje se izvanmrežna stranica.
		if ( isNetworkOnly( url ) || url.search ) {
			event.respondWith( fetch( request ).catch( offlineFallback ) );
			return;
		}

		event.respondWith(
			fetch( request )
				.then( ( response ) => {
					if ( response.ok && response.type === 'basic' && response.headers.get( 'X-Plan-A-Offline' ) === '1' ) {
						event.waitUntil( put( PAGES_CACHE, request, response.clone(), PAGES_LIMIT ) );
					}
					return response;
				} )
				.catch( () => caches.open( PAGES_CACHE )
					.then( ( cache ) => cache.match( request ) )
					.then( ( cached ) => cached || offlineFallback() ) )
		);
		return;
	}

	if ( isNetworkOnly( url ) || ! isStatic( request, url ) ) {
		return; // AJAX, REST, košarica, sve ostalo: izravno na mrežu
	}

	// Statične datoteke: odmah iz predmemorije, a u pozadini osvježi.
	event.respondWith(
		// Traži u svim predmemorijama dodatka (npr. ikona izvanmrežne stranice).
		caches.match( request ).then( ( cached ) => {
			const network = fetch( request )
				.then( ( response ) => {
					if ( response.ok && ( response.type === 'basic' || response.type === 'cors' ) ) {
						put( STATIC_CACHE, request, response.clone(), STATIC_LIMIT );
					}
					return response;
				} )
				.catch( () => cached || Response.error() );
			if ( cached ) {
				event.waitUntil( network.then( () => undefined ) );
				return cached;
			}
			return network;
		} )
	);
} );
