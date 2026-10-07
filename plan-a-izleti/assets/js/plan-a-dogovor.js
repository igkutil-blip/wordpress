/**
 * Plan A izleti – stranica dogovora: označavanje "Ja sam za!" / "Možda" / "Ne mogu taj datum".
 *
 * Isti uređaj može promijeniti svoj odgovor: ključ odgovora (nasumični niz koji
 * vrati poslužitelj), ime i odabir pamte se lokalno u pregledniku. Nonce se
 * dohvaća svježim REST pozivom prije svakog spremanja (stranice se ne smiju
 * oslanjati na nonce iz HTML-a).
 */
( function () {
	'use strict';

	var config = window.planADogovor || {};
	var text = config.i18n || {};
	var labels = config.labels || {};
	var form = document.querySelector( '[data-paiz-dogovor-form]' );
	var groupsBox = document.querySelector( '[data-paiz-dogovor-groups]' );
	if ( ! form || ! config.rest || ! config.token ) {
		return;
	}

	var nameInput = form.querySelector( 'input[name="name"]' );
	var honeypot = form.querySelector( 'input[name="website"]' );
	var status = form.querySelector( '[data-paiz-dogovor-status]' );
	var buttons = form.querySelectorAll( 'button[name="choice"]' );
	var storeKey = 'paizDogovor.answer.' + config.token;
	var busy = false;

	function load() {
		try {
			return JSON.parse( window.localStorage.getItem( storeKey ) || 'null' ) || {};
		} catch ( e ) {
			return {};
		}
	}

	function save( data ) {
		try {
			window.localStorage.setItem( storeKey, JSON.stringify( data ) );
		} catch ( e ) {}
	}

	function markChoice( choice ) {
		Array.prototype.forEach.call( buttons, function ( button ) {
			var active = button.value === choice;
			button.classList.toggle( 'is-active', active );
			button.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
		} );
	}

	function setStatus( message, isError ) {
		status.textContent = message || '';
		status.classList.toggle( 'is-error', !! isError );
	}

	function renderGroups( groups ) {
		if ( ! groupsBox || ! groups ) {
			return;
		}
		groupsBox.textContent = '';
		Object.keys( labels ).forEach( function ( choice ) {
			var names = groups[ choice ] || [];
			var box = document.createElement( 'div' );
			box.className = 'paiz-dogovor__group paiz-dogovor__group--' + choice;
			box.setAttribute( 'data-choice', choice );

			var title = document.createElement( 'h3' );
			title.className = 'paiz-dogovor__group-title';
			title.appendChild( document.createTextNode( labels[ choice ] + ' ' ) );
			var count = document.createElement( 'span' );
			count.className = 'paiz-dogovor__count';
			count.textContent = '(' + names.length + ')';
			title.appendChild( count );
			box.appendChild( title );

			if ( names.length ) {
				var list = document.createElement( 'ul' );
				list.className = 'paiz-dogovor__names';
				names.forEach( function ( name ) {
					var item = document.createElement( 'li' );
					item.textContent = name;
					list.appendChild( item );
				} );
				box.appendChild( list );
			} else {
				var empty = document.createElement( 'p' );
				empty.className = 'paiz-dogovor__nobody';
				empty.textContent = text.nobody || '';
				box.appendChild( empty );
			}
			groupsBox.appendChild( box );
		} );
	}

	function request( path, options ) {
		options = options || {};
		options.credentials = 'same-origin';
		options.cache = 'no-store';
		options.headers = { Accept: 'application/json' };
		if ( options.body ) {
			options.headers[ 'Content-Type' ] = 'application/json';
		}
		return window.fetch( config.rest + path, options ).then( function ( response ) {
			return response.json().catch( function () {
				return {};
			} ).then( function ( data ) {
				if ( ! response.ok ) {
					throw new Error( ( data && data.message ) || '' );
				}
				return data;
			} );
		} );
	}

	function submit( choice ) {
		if ( busy ) {
			return;
		}
		var name = ( nameInput.value || '' ).replace( /\s+/g, ' ' ).trim().slice( 0, config.maxName || 30 );
		if ( ! name ) {
			setStatus( text.name, true );
			nameInput.focus();
			return;
		}
		var saved = load();
		busy = true;
		form.classList.add( 'is-busy' );
		setStatus( text.saving, false );

		request( 'nonce' )
			.then( function ( data ) {
				return request( 'dogovori/' + config.token + '/odgovor', {
					method: 'POST',
					body: JSON.stringify( {
						name: name,
						choice: choice,
						key: saved.key || '',
						nonce: data.nonce || '',
						website: honeypot ? honeypot.value : '',
					} ),
				} );
			} )
			.then( function ( data ) {
				save( { key: data.key, name: data.name, choice: data.choice } );
				nameInput.value = data.name;
				markChoice( data.choice );
				renderGroups( data.groups );
				setStatus( ( text.saved || '%s' ).replace( '%s', labels[ data.choice ] || '' ), false );
			} )
			.catch( function ( error ) {
				setStatus( ( error && error.message ) || text.error, true );
			} )
			.then( function () {
				busy = false;
				form.classList.remove( 'is-busy' );
			} );
	}

	// Gumbi odgovora su type="submit"; vrijednost se uzima s kliknutog gumba.
	var lastChoice = '';
	Array.prototype.forEach.call( buttons, function ( button ) {
		button.addEventListener( 'click', function () {
			lastChoice = button.value;
		} );
	} );
	// Enter u polju za ime ne smije sam odabrati "Ja sam za!": fokus ide na gumbe.
	nameInput.addEventListener( 'keydown', function ( event ) {
		if ( 'Enter' === event.key ) {
			event.preventDefault();
			var active = form.querySelector( 'button[name="choice"].is-active' ) || buttons[ 0 ];
			if ( active ) {
				active.focus();
			}
		}
	} );
	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
		var choice = ( event.submitter && event.submitter.value ) || lastChoice;
		if ( choice ) {
			submit( choice );
		}
	} );

	// Prethodni odgovor s ovog uređaja.
	var previous = load();
	if ( previous.name ) {
		nameInput.value = previous.name;
	}
	if ( previous.choice ) {
		markChoice( previous.choice );
	}
} )();
