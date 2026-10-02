/**
 * Relativt Cookie Consent – frontend-logik.
 *
 * Läser/skriver samtyckescookien, aktiverar blockerade tredjepartsskript
 * (<script type="text/plain" data-cookiecategory="...">) och blockerade
 * iframes, samt styr banner-UI:t. Ingen jQuery eller andra beroenden.
 *
 * Samtyckescookien innehåller ett slumpat ID och versionsnumret från
 * inställningen "Samtyckesversion". Är versionen äldre än sajtens visas
 * rutan igen. Varje val rapporteras till samtyckesloggen (REST), och
 * varje automatisk visning av rutan räknas till statistiken.
 *
 * Publikt API: window.rcc (se längst ner).
 * Events på document:
 *   "rcc_ready"           en gång, direkt när window.rcc finns
 *                         (detail = getConsent(), dvs. samtycket eller null).
 *   "rcc_consent_updated" vid varje val (detail = samtyckesobjektet).
 * Dessutom dataLayer-eventet "rcc_consent_update" för Google Tag Manager,
 * och WP Consent API:s "wp_listen_for_consent_change" när det pluginet
 * är aktivt.
 *
 * Registrerade cookies (kakregistret) i en nekad kategori raderas vid
 * valet och vid varje sidladdning.
 */
( function () {
	'use strict';

	var settings = window.rccSettings || {};
	var COOKIE_NAME = settings.cookieName || 'relativt_cookie_consent';
	var EXPIRY_DAYS = parseInt( settings.cookieExpiryDays, 10 ) || 180;
	var RELOAD_ON_REVOKE = !! settings.reloadOnRevoke;
	var USE_BACKDROP = !! settings.backdrop;
	var CONSENT_VERSION = parseInt( settings.consentVersion, 10 ) || 1;
	var LOG_ENDPOINT = settings.logEndpoint || '';
	var VIEW_ENDPOINT = settings.viewEndpoint || '';
	var i18n = settings.i18n || {};
	var CATEGORIES = [ 'statistics', 'marketing' ];
	var REGISTRY = Array.isArray( settings.cookieRegistry ) ? settings.cookieRegistry : [];
	var PROTECTED = Array.isArray( settings.protectedCookies ) ? settings.protectedCookies : [];
	var WP_CONSENT = ( settings.wpConsentApi && 'object' === typeof settings.wpConsentApi ) ? settings.wpConsentApi : null;

	var banner, backdrop, categoriesEl, toggleBtn, saveBtn, rejectBtn, acceptBtn, reopenBtn, statsInput, marketingInput, metaEl, metaIdEl, metaDateEl;
	var lastFocused = null;

	/* ---------------------------------------------------------------
	 * Cookie
	 * ------------------------------------------------------------ */

	function getConsent() {
		var match = document.cookie.match( new RegExp( '(?:^|; )' + COOKIE_NAME.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) + '=([^;]*)' ) );
		if ( ! match ) {
			return null;
		}
		try {
			var parsed = JSON.parse( decodeURIComponent( match[ 1 ] ) );
			return ( parsed && typeof parsed === 'object' ) ? parsed : null;
		} catch ( e ) {
			return null;
		}
	}

	/**
	 * Cookies från före versionshanteringen saknar version och räknas
	 * som version 1, så en uppdatering av pluginet i sig tvingar inte
	 * fram nya samtycken.
	 */
	function isCurrent( consent ) {
		return !! consent && ( parseInt( consent.version, 10 ) || 1 ) === CONSENT_VERSION;
	}

	function currentConsent() {
		var c = getConsent();
		return isCurrent( c ) ? c : null;
	}

	function generateId() {
		var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
		var out = '';
		var i;
		if ( window.crypto && window.crypto.getRandomValues ) {
			var buf = new Uint8Array( 20 );
			window.crypto.getRandomValues( buf );
			for ( i = 0; i < buf.length; i++ ) {
				out += chars.charAt( buf[ i ] % chars.length );
			}
		} else {
			for ( i = 0; i < 20; i++ ) {
				out += chars.charAt( Math.floor( Math.random() * chars.length ) );
			}
		}
		return out;
	}

	function writeCookie( consent ) {
		var expires = new Date();
		expires.setTime( expires.getTime() + EXPIRY_DAYS * 24 * 60 * 60 * 1000 );

		document.cookie =
			COOKIE_NAME +
			'=' +
			encodeURIComponent( JSON.stringify( consent ) ) +
			';expires=' +
			expires.toUTCString() +
			';path=/;SameSite=Lax' +
			( 'https:' === window.location.protocol ? ';Secure' : '' );
	}

	function normalize( consent ) {
		var out = { necessary: true, statistics: false, marketing: false };
		if ( consent && typeof consent === 'object' ) {
			CATEGORIES.forEach( function ( cat ) {
				out[ cat ] = !! consent[ cat ];
			} );
		}
		return out;
	}

	function setConsent( input ) {
		var previous = getConsent();
		var consent = normalize( input );
		consent.id = ( previous && previous.id ) ? previous.id : generateId();
		consent.version = CONSENT_VERSION;
		consent.timestamp = new Date().toISOString();

		writeCookie( consent );
		logConsent( consent );

		var revoked = false;
		if ( isCurrent( previous ) ) {
			CATEGORIES.forEach( function ( cat ) {
				if ( previous[ cat ] && ! consent[ cat ] ) {
					revoked = true;
				}
			} );
		}

		applyConsent( consent );
		activateGatedIframes( consent );
		cleanupCookies( consent );
		syncWpConsentApi( consent );

		document.dispatchEvent(
			new CustomEvent( 'rcc_consent_updated', { detail: consent } )
		);

		if ( revoked && RELOAD_ON_REVOKE ) {
			window.location.reload();
		}

		return consent;
	}

	/* ---------------------------------------------------------------
	 * Samtyckeslogg
	 * ------------------------------------------------------------ */

	/**
	 * Rapporterar valet till REST-endpointen. Körs i bakgrunden och får
	 * aldrig blockera eller påverka UI:t; keepalive gör att anropet
	 * överlever en omladdning direkt efter valet.
	 */
	function logConsent( consent ) {
		if ( ! LOG_ENDPOINT ) {
			return;
		}
		var payload = JSON.stringify( {
			id: consent.id,
			statistics: !! consent.statistics,
			marketing: !! consent.marketing,
			version: CONSENT_VERSION
		} );

		try {
			if ( window.fetch ) {
				window.fetch( LOG_ENDPOINT, {
					method: 'POST',
					credentials: 'omit',
					keepalive: true,
					headers: { 'Content-Type': 'application/json' },
					body: payload
				} ).catch( function () {} );
			} else if ( window.XMLHttpRequest ) {
				var xhr = new XMLHttpRequest();
				xhr.open( 'POST', LOG_ENDPOINT, true );
				xhr.setRequestHeader( 'Content-Type', 'application/json' );
				xhr.send( payload );
			}
		} catch ( e ) {
			// Loggning är best effort.
		}
	}

	/**
	 * Räknar en visning av rutan till statistiken. Skickar ingenting om
	 * besökaren: ingen cookie, inget ID, ingen kropp.
	 */
	function countView() {
		if ( ! VIEW_ENDPOINT || ! window.fetch ) {
			return;
		}
		try {
			window.fetch( VIEW_ENDPOINT, {
				method: 'POST',
				credentials: 'omit',
				keepalive: true
			} ).catch( function () {} );
		} catch ( e ) {
			// Räkningen är best effort.
		}
	}

	function formatDate( iso ) {
		var d = iso ? new Date( iso ) : null;
		if ( ! d || isNaN( d.getTime() ) ) {
			return '';
		}
		var pad = function ( n ) {
			return ( n < 10 ? '0' : '' ) + n;
		};
		return d.getFullYear() + '-' + pad( d.getMonth() + 1 ) + '-' + pad( d.getDate() ) + ' ' + pad( d.getHours() ) + ':' + pad( d.getMinutes() );
	}

	function updateMeta( consent ) {
		if ( ! metaEl ) {
			return;
		}
		if ( consent && consent.id ) {
			metaIdEl.textContent = consent.id;
			metaDateEl.textContent = formatDate( consent.timestamp );
			metaEl.hidden = false;
		} else {
			metaEl.hidden = true;
		}
	}

	/* ---------------------------------------------------------------
	 * Aktivering av blockerade skript
	 * ------------------------------------------------------------ */

	/**
	 * Blockerade element finns i tre former:
	 *
	 * 1. <script type="text/plain" data-cookiecategory="..."> – ett
	 *    enskilt skript, inline eller med data-cookiesrc. Skrivs ut av
	 *    de inbyggda verktygen och av domänblockeringen.
	 * 2. <script type="application/json" data-rcc-html-block> – ett block
	 *    rå HTML (egen kod) lagrat som JSON-sträng.
	 * 3. <script type="text/plain" data-html-block> – samma sak i det
	 *    äldre formatet (rå HTML), kvar för kod som anropar
	 *    rcc_blocked_script_open() med data-html-block.
	 *
	 * Allt körs i en kö i dokumentordning. Ett externt skript utan
	 * async/defer måste ladda klart innan nästa körs, precis som när
	 * webbläsaren läser sidan. Annars kan ett inline-skript som anropar
	 * ett bibliotek köras innan biblioteket finns.
	 */
	var BLOCKED_SELECTOR = 'script[type="text/plain"][data-cookiecategory], script[type="application/json"][data-rcc-html-block][data-cookiecategory]';
	var SCRIPT_TIMEOUT = 10000;

	var queue = [];
	var running = false;

	function runQueue() {
		if ( running ) {
			return;
		}
		var job = queue.shift();
		if ( ! job ) {
			return;
		}
		running = true;
		var finished = false;
		job( function () {
			if ( finished ) {
				return;
			}
			finished = true;
			running = false;
			runQueue();
		} );
	}

	function enqueue( job ) {
		queue.push( job );
		runQueue();
	}

	function once( fn ) {
		var called = false;
		return function () {
			if ( ! called ) {
				called = true;
				fn();
			}
		};
	}

	/**
	 * Skapar ett körbart <script> från ett blockerat eller inert skript.
	 * Skript som tolkats via innerHTML/<template> körs aldrig av
	 * webbläsaren, så de måste alltid återskapas.
	 */
	function makeScript( old ) {
		var s = document.createElement( 'script' );
		var originalType = old.getAttribute( 'data-rcc-type' );
		var blocked = 'text/plain' === ( old.getAttribute( 'type' ) || '' ).toLowerCase() && old.hasAttribute( 'data-cookiecategory' );

		for ( var i = 0; i < old.attributes.length; i++ ) {
			var attr = old.attributes[ i ];
			if ( 'data-cookiesrc' === attr.name ) {
				s.setAttribute( 'src', attr.value );
				continue;
			}
			if ( ( 'type' === attr.name && blocked ) || 'data-rcc-type' === attr.name || 'data-rcc-queued' === attr.name ) {
				continue;
			}
			s.setAttribute( attr.name, attr.value );
		}

		if ( blocked ) {
			s.type = originalType || 'text/javascript';
		}
		if ( ! s.src && old.text ) {
			s.text = old.text;
		}
		// Dynamiskt skapade skript är async som standard. Utan
		// async-attribut ska de i stället köras i tur och ordning.
		if ( s.src && ! old.hasAttribute( 'async' ) ) {
			s.async = false;
		}
		return s;
	}

	/**
	 * Lägger in ett körbart skript före ref och anropar done när nästa
	 * steg får köras: direkt för inline- och async-skript, efter
	 * load/error för externa skript som ska köras i ordning.
	 */
	function insertScript( s, parent, ref, done ) {
		var waits = !! s.src && ! s.async && ! s.hasAttribute( 'defer' ) && 'module' !== s.type;
		if ( waits ) {
			var next = once( done );
			s.onload = next;
			s.onerror = next;
			window.setTimeout( next, SCRIPT_TIMEOUT );
		}
		parent.insertBefore( s, ref );
		if ( ! waits ) {
			done();
		}
	}

	/**
	 * Gör alla inerta <script> inuti ett redan inlagt element körbara,
	 * ett i taget.
	 */
	function runInnerScripts( el, done ) {
		var inner = el.nodeType === 1 && el.querySelectorAll ? Array.prototype.slice.call( el.querySelectorAll( 'script' ) ) : [];
		var i = 0;
		( function step() {
			if ( i >= inner.length ) {
				done();
				return;
			}
			var old = inner[ i++ ];
			if ( ! old.parentNode ) {
				step();
				return;
			}
			insertScript( makeScript( old ), old.parentNode, old, function () {
				if ( old.parentNode ) {
					old.parentNode.removeChild( old );
				}
				step();
			} );
		} )();
	}

	/**
	 * Lägger in noderna före ref i tur och ordning och kör skripten.
	 */
	function insertNodes( nodes, parent, ref, done ) {
		var i = 0;
		( function step() {
			if ( i >= nodes.length ) {
				done();
				return;
			}
			var node = nodes[ i++ ];
			if ( node.nodeType === 1 && 'SCRIPT' === node.tagName ) {
				insertScript( makeScript( node ), parent, ref, step );
				return;
			}
			parent.insertBefore( node, ref );
			runInnerScripts( node, step );
		} )();
	}

	/**
	 * Tolkar HTML-blocket. <noscript> tas bort: i en <template> blir
	 * innehållet riktiga element, och en <img>-pixel i <noscript> skulle
	 * då laddas och räkna besöket en gång till utöver skriptet.
	 */
	function parseHtml( html ) {
		var tpl = document.createElement( 'template' );
		var root;
		if ( 'content' in tpl ) {
			tpl.innerHTML = html;
			root = tpl.content;
		} else {
			root = document.createElement( 'div' );
			root.innerHTML = html;
		}
		Array.prototype.slice.call( root.querySelectorAll( 'noscript' ) ).forEach( function ( el ) {
			el.parentNode.removeChild( el );
		} );
		return Array.prototype.slice.call( root.childNodes );
	}

	function htmlBlockSource( el ) {
		if ( el.hasAttribute( 'data-rcc-html-block' ) ) {
			try {
				var html = JSON.parse( el.textContent || '""' );
				return 'string' === typeof html ? html : '';
			} catch ( e ) {
				return '';
			}
		}
		return el.textContent || '';
	}

	function activateElement( el, done ) {
		var parent = el.parentNode;
		if ( ! parent ) {
			done();
			return;
		}
		var remove = function () {
			if ( el.parentNode ) {
				el.parentNode.removeChild( el );
			}
			done();
		};

		if ( el.hasAttribute( 'data-rcc-html-block' ) || el.hasAttribute( 'data-html-block' ) ) {
			insertNodes( parseHtml( htmlBlockSource( el ) ), parent, el, remove );
			return;
		}

		insertScript( makeScript( el ), parent, el, remove );
	}

	/**
	 * Köar alla blockerade element som någon av de godkända kategorierna
	 * låser upp. Ett element kan ange flera kategorier ("statistics
	 * marketing") och aktiveras då av endera. Köade element märks så att
	 * de aldrig körs två gånger.
	 */
	function activateScripts( consent ) {
		var blocked = document.querySelectorAll( BLOCKED_SELECTOR );

		Array.prototype.slice.call( blocked ).forEach( function ( el ) {
			if ( el.hasAttribute( 'data-rcc-queued' ) ) {
				return;
			}
			var cats = ( el.getAttribute( 'data-cookiecategory' ) || '' ).split( /\s+/ ).filter( Boolean );
			var allowed = cats.some( function ( cat ) {
				return !! consent[ cat ];
			} );
			if ( ! allowed ) {
				return;
			}
			el.setAttribute( 'data-rcc-queued', '1' );
			enqueue( function ( done ) {
				activateElement( el, done );
			} );
		} );
	}

	function applyConsent( consent ) {
		var granted = function ( yes ) {
			return yes ? 'granted' : 'denied';
		};

		// Google Consent Mode v2.
		if ( typeof window.gtag === 'function' ) {
			window.gtag( 'consent', 'update', {
				analytics_storage: granted( consent.statistics ),
				ad_storage: granted( consent.marketing ),
				ad_user_data: granted( consent.marketing ),
				ad_personalization: granted( consent.marketing ),
				personalization_storage: granted( consent.marketing )
			} );
		}

		// Google Tag Manager-trigger.
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push( {
			event: 'rcc_consent_update',
			rcc_statistics: !! consent.statistics,
			rcc_marketing: !! consent.marketing
		} );

		activateScripts( consent );
	}

	/* ---------------------------------------------------------------
	 * Blockerade iframes (YouTube, Vimeo m.fl.)
	 * ------------------------------------------------------------ */

	/**
	 * PHP-sidan har redan flyttat src till data-cookiesrc innan sidan
	 * nådde webbläsaren. Här läggs en overlay-knapp ovanpå de iframes
	 * som fortfarande väntar på samtycke, och riktig src sätts på de som
	 * redan har det.
	 */
	function categoriesOf( el ) {
		return ( el.getAttribute( 'data-cookiecategory' ) || '' ).split( /\s+/ ).filter( Boolean );
	}

	function setupGatedIframe( iframe, consent ) {
		var cats = categoriesOf( iframe );
		var realSrc = iframe.getAttribute( 'data-cookiesrc' );
		if ( ! cats.length || ! realSrc ) {
			return;
		}
		// En iframe kan ange flera kategorier ("statistics marketing") och
		// visas då när endera är godkänd. Knappen ber om den första.
		var category = cats[ 0 ];
		var allowed = !! consent && cats.some( function ( cat ) {
			return !! consent[ cat ];
		} );

		if ( allowed ) {
			if ( iframe.getAttribute( 'src' ) !== realSrc ) {
				iframe.src = realSrc;
			}
			if ( iframe.rccOverlay && iframe.rccOverlay.parentNode ) {
				iframe.rccOverlay.parentNode.removeChild( iframe.rccOverlay );
			}
			iframe.rccOverlay = null;
			return;
		}

		if ( iframe.dataset.rccSetup ) {
			return;
		}
		iframe.dataset.rccSetup = '1';

		var parent = iframe.parentElement;
		if ( parent ) {
			var computed = window.getComputedStyle( parent );
			if ( 'static' === computed.position ) {
				parent.style.position = 'relative';
			}
		}

		var label = i18n[ category ] || category;
		var text = i18n.showContent || 'Visa innehåll';
		if ( i18n.requiresConsent ) {
			text += ' (' + i18n.requiresConsent + ' ' + label.toLowerCase() + ')';
		}

		var overlay = document.createElement( 'div' );
		overlay.className = 'rcc-iframe-overlay';

		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'rcc-iframe-overlay__btn';
		btn.textContent = text;

		btn.addEventListener( 'click', function () {
			var current = normalize( currentConsent() );
			current[ category ] = true;
			setConsent( current );
		} );

		overlay.appendChild( btn );
		iframe.rccOverlay = overlay;

		if ( parent ) {
			parent.insertBefore( overlay, iframe.nextSibling );
		}
	}

	function activateGatedIframes( consent ) {
		// Klassen rcc-gated-iframe används för CSS, men urvalet går på
		// attributen så att en iframe med två class-attribut ändå hittas.
		var iframes = document.querySelectorAll( 'iframe[data-cookiesrc][data-cookiecategory]' );
		Array.prototype.slice.call( iframes ).forEach( function ( iframe ) {
			setupGatedIframe( iframe, consent );
		} );
	}

	function observeNewIframes() {
		if ( ! window.MutationObserver ) {
			return;
		}
		var scheduled = false;
		var observer = new MutationObserver( function () {
			if ( scheduled ) {
				return;
			}
			scheduled = true;
			window.setTimeout( function () {
				scheduled = false;
				activateGatedIframes( currentConsent() );
			}, 50 );
		} );
		observer.observe( document.body, { childList: true, subtree: true } );
	}

	/* ---------------------------------------------------------------
	 * Kakregister: städning av nekade cookies
	 * ------------------------------------------------------------ */

	function patternToRegExp( pattern ) {
		return new RegExp( '^' + String( pattern ).split( '*' ).map( function ( part ) {
			return part.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
		} ).join( '.*' ) + '$' );
	}

	var registryRules = REGISTRY.filter( function ( entry ) {
		return entry && 'string' === typeof entry.name && Array.isArray( entry.categories );
	} ).map( function ( entry ) {
		return { re: patternToRegExp( entry.name ), categories: entry.categories };
	} );
	var protectedRules = PROTECTED.filter( function ( p ) {
		return 'string' === typeof p && '' !== p;
	} ).map( patternToRegExp );

	function cookieNames() {
		var names = [];
		( document.cookie ? document.cookie.split( ';' ) : [] ).forEach( function ( pair ) {
			var name = pair.split( '=' )[ 0 ].replace( /^\s+|\s+$/g, '' );
			if ( name && names.indexOf( name ) === -1 ) {
				names.push( name );
			}
		} );
		return names;
	}

	/**
	 * Raderas när cookien matchar minst en registrerad post och ingen av
	 * de matchande posterna har en godkänd eller nödvändig kategori.
	 * Skyddade cookies (pluginets egen, WordPress, WP Consent API) rörs
	 * aldrig. Samma regel som rcc_cookies_to_delete() i PHP.
	 */
	function shouldDelete( name, consent ) {
		var i;
		for ( i = 0; i < protectedRules.length; i++ ) {
			if ( protectedRules[ i ].test( name ) ) {
				return false;
			}
		}
		var matched = false;
		for ( i = 0; i < registryRules.length; i++ ) {
			var rule = registryRules[ i ];
			if ( ! rule.re.test( name ) ) {
				continue;
			}
			matched = true;
			var allowed = rule.categories.some( function ( cat ) {
				return 'necessary' === cat || !! consent[ cat ];
			} );
			if ( allowed ) {
				return false;
			}
		}
		return matched;
	}

	/**
	 * JS ser inte vilken domän eller sökväg en cookie satts på, så den
	 * raderas på alla som är möjliga: utan domän och på värden och varje
	 * överordnad domän, med sökvägen / och sidans egna sökvägar.
	 */
	function deleteCookie( name ) {
		var host = window.location.hostname;
		var domains = [ '' ];
		if ( host.indexOf( '.' ) !== -1 && ! /^[\d.]+$/.test( host ) && host.indexOf( ':' ) === -1 ) {
			var parts = host.split( '.' );
			for ( var i = 0; i < parts.length - 1; i++ ) {
				domains.push( '.' + parts.slice( i ).join( '.' ) );
			}
		}
		var paths = [ '/' ];
		var segments = window.location.pathname.split( '/' ).filter( Boolean );
		var path = '';
		segments.forEach( function ( seg ) {
			path += '/' + seg;
			paths.push( path, path + '/' );
		} );
		var secure = 'https:' === window.location.protocol ? ';Secure' : '';
		domains.forEach( function ( domain ) {
			paths.forEach( function ( p ) {
				document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;max-age=0;path=' + p + ( domain ? ';domain=' + domain : '' ) + secure;
			} );
		} );
	}

	/**
	 * Raderar registrerade cookies i nekade kategorier. Körs bara med ett
	 * gällande samtycke: innan besökaren valt (eller när versionen är
	 * inaktuell) har ingen kategori nekats, och skripten är ändå blockerade.
	 */
	function cleanupCookies( consent ) {
		if ( ! consent || ! registryRules.length || ( consent.statistics && consent.marketing ) ) {
			return [];
		}
		var deleted = [];
		cookieNames().forEach( function ( name ) {
			if ( shouldDelete( name, consent ) ) {
				deleteCookie( name );
				deleted.push( name );
			}
		} );
		return deleted;
	}

	/* ---------------------------------------------------------------
	 * WP Consent API
	 * ------------------------------------------------------------ */

	/**
	 * Talar om för WP Consent API att samtycke krävs innan något sätts.
	 * Görs direkt när skriptet körs, så att plugin som väntar på
	 * wp_consent_type_defined får beskedet så tidigt som möjligt.
	 */
	function defineWpConsentType() {
		if ( ! WP_CONSENT ) {
			return;
		}
		window.wp_consent_type = WP_CONSENT.type || 'optin';
		document.dispatchEvent( new CustomEvent( 'wp_consent_type_defined' ) );
	}

	function wpConsentCookie( category ) {
		var prefix = ( window.consent_api && window.consent_api.cookie_prefix ) || 'wp_consent';
		var name = prefix + '_' + category + '=';
		var parts = document.cookie ? document.cookie.split( ';' ) : [];
		for ( var i = 0; i < parts.length; i++ ) {
			var c = parts[ i ].replace( /^\s+/, '' );
			if ( c.indexOf( name ) === 0 ) {
				return c.substring( name.length );
			}
		}
		return '';
	}

	/**
	 * Sätter WP Consent API:s kategorier utifrån samtycket. wp_set_consent
	 * skickar wp_listen_for_consent_change bara när ett värde ändras.
	 *
	 * Utan gällande samtycke sätts bara "functional" (nödvändiga). Står en
	 * annan kategori kvar som "allow" från ett samtycke till en äldre
	 * version sätts den till "deny", så att andra plugin slutar spåra.
	 */
	var wpConsentRetry = false;

	function syncWpConsentApi( consent ) {
		if ( ! WP_CONSENT ) {
			return;
		}
		if ( 'function' !== typeof window.wp_set_consent ) {
			// API:ts skript ska laddas före pluginets. Har en optimering
			// ändrat ordningen görs ett nytt försök när sidan laddat klart.
			if ( ! wpConsentRetry ) {
				wpConsentRetry = true;
				window.addEventListener( 'load', function () {
					if ( 'function' === typeof window.wp_set_consent ) {
						syncWpConsentApi( currentConsent() );
					}
				} );
			}
			return;
		}
		var map = WP_CONSENT.categories || {};
		Object.keys( map ).forEach( function ( apiCategory ) {
			var ours = map[ apiCategory ];
			if ( 'necessary' === ours ) {
				window.wp_set_consent( apiCategory, 'allow' );
			} else if ( consent ) {
				window.wp_set_consent( apiCategory, consent[ ours ] ? 'allow' : 'deny' );
			} else if ( 'allow' === wpConsentCookie( apiCategory ) ) {
				window.wp_set_consent( apiCategory, 'deny' );
			}
		} );
	}

	/* ---------------------------------------------------------------
	 * Banner-UI
	 * ------------------------------------------------------------ */

	function showBanner() {
		if ( ! banner ) {
			return;
		}
		lastFocused = document.activeElement;
		banner.hidden = false;
		if ( backdrop ) {
			backdrop.hidden = false;
		}
		if ( USE_BACKDROP ) {
			document.documentElement.classList.add( 'rcc-lock' );
		}
		if ( reopenBtn ) {
			reopenBtn.hidden = true;
		}
		var firstBtn = banner.querySelector( '.rcc-btn--primary' ) || banner.querySelector( 'button' );
		if ( firstBtn && USE_BACKDROP ) {
			firstBtn.focus();
		}
	}

	function hideBanner() {
		if ( banner ) {
			banner.hidden = true;
		}
		if ( backdrop ) {
			backdrop.hidden = true;
		}
		document.documentElement.classList.remove( 'rcc-lock' );
		if ( reopenBtn ) {
			reopenBtn.hidden = false;
		}
		if ( lastFocused && typeof lastFocused.focus === 'function' && lastFocused !== document.body ) {
			try {
				lastFocused.focus();
			} catch ( e ) {
				// Elementet kan ha försvunnit.
			}
		}
		lastFocused = null;
	}

	function setPanelOpen( open ) {
		if ( categoriesEl ) {
			categoriesEl.hidden = ! open;
		}
		if ( saveBtn ) {
			saveBtn.hidden = ! open;
		}
		if ( toggleBtn ) {
			toggleBtn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}
	}

	function openSettingsPanel( consent ) {
		updateMeta( consent );
		consent = normalize( consent );
		if ( statsInput ) {
			statsInput.checked = consent.statistics;
		}
		if ( marketingInput ) {
			marketingInput.checked = consent.marketing;
		}
		showBanner();
		setPanelOpen( true );
	}

	function openSettings() {
		openSettingsPanel( currentConsent() );
	}

	function acceptAll() {
		setConsent( { statistics: true, marketing: true } );
		hideBanner();
	}

	function rejectAll() {
		setConsent( { statistics: false, marketing: false } );
		hideBanner();
	}

	function saveSelection() {
		setConsent( {
			statistics: !! ( statsInput && statsInput.checked ),
			marketing: !! ( marketingInput && marketingInput.checked )
		} );
		hideBanner();
	}

	function init() {
		banner = document.getElementById( 'rcc-banner' );
		backdrop = document.getElementById( 'rcc-backdrop' );
		categoriesEl = document.getElementById( 'rcc-categories' );
		toggleBtn = document.getElementById( 'rcc-toggle-settings' );
		saveBtn = document.getElementById( 'rcc-save-settings' );
		rejectBtn = document.getElementById( 'rcc-reject-all' );
		acceptBtn = document.getElementById( 'rcc-accept-all' );
		reopenBtn = document.getElementById( 'rcc-reopen' );
		statsInput = document.getElementById( 'rcc-cat-statistics' );
		marketingInput = document.getElementById( 'rcc-cat-marketing' );
		metaEl = document.getElementById( 'rcc-consent-meta' );
		metaIdEl = document.getElementById( 'rcc-consent-id' );
		metaDateEl = document.getElementById( 'rcc-consent-date' );

		// Ett samtycke med äldre version gäller inte: skript förblir
		// blockerade och rutan visas igen, men ID:t behålls så att
		// loggen visar hela historiken.
		var existing = currentConsent();

		activateGatedIframes( existing );
		observeNewIframes();

		if ( ! banner ) {
			return;
		}

		if ( existing ) {
			applyConsent( normalize( existing ) );
			if ( reopenBtn ) {
				reopenBtn.hidden = false;
			}
		} else {
			showBanner();
			countView();
		}

		if ( toggleBtn ) {
			toggleBtn.addEventListener( 'click', function () {
				var open = categoriesEl ? categoriesEl.hidden : true;
				if ( open ) {
					openSettingsPanel( currentConsent() );
				} else {
					setPanelOpen( false );
				}
			} );
		}
		if ( acceptBtn ) {
			acceptBtn.addEventListener( 'click', acceptAll );
		}
		if ( rejectBtn ) {
			rejectBtn.addEventListener( 'click', rejectAll );
		}
		if ( saveBtn ) {
			saveBtn.addEventListener( 'click', saveSelection );
		}

		// Alla element med data-rcc-open öppnar inställningarna: den
		// flytande knappen, kortkoden och egna länkar i menyer/footer.
		document.addEventListener( 'click', function ( e ) {
			var trigger = e.target.closest ? e.target.closest( '[data-rcc-open]' ) : null;
			if ( ! trigger ) {
				return;
			}
			e.preventDefault();
			openSettings();
		} );

		// Escape stänger rutan, men bara om ett val redan finns – annars
		// ska besökaren ta ställning.
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && banner && ! banner.hidden && currentConsent() ) {
				hideBanner();
			}
		} );
	}

	/* ---------------------------------------------------------------
	 * Publikt API
	 * ------------------------------------------------------------ */

	window.rcc = {
		getConsent: function () {
			var c = currentConsent();
			return c ? normalize( c ) : null;
		},
		hasConsent: function ( category ) {
			var c = currentConsent();
			return !! ( c && c[ category ] );
		},
		getConsentId: function () {
			var c = getConsent();
			return ( c && c.id ) ? c.id : null;
		},
		openSettings: openSettings,
		acceptAll: acceptAll,
		rejectAll: rejectAll,
		save: function ( consent ) {
			var saved = setConsent( consent || {} );
			hideBanner();
			return saved;
		},
		refreshIframes: function () {
			activateGatedIframes( currentConsent() );
		}
	};

	// Körs direkt, före DOMContentLoaded: WP Consent API och städningen
	// behöver bara cookies, och andra plugin ska få rätt besked tidigt.
	( function boot() {
		var existing = currentConsent();
		defineWpConsentType();
		syncWpConsentApi( existing );
		cleanupCookies( existing );
		document.dispatchEvent( new CustomEvent( 'rcc_ready', { detail: window.rcc.getConsent() } ) );
	} )();

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
