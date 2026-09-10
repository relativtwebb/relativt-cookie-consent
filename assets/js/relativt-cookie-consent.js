/**
 * Relativt Cookie Consent – frontend-logik.
 *
 * Läser/skriver samtyckescookien, aktiverar blockerade tredjepartsskript
 * (<script type="text/plain" data-cookiecategory="...">) och blockerade
 * iframes, samt styr banner-UI:t. Ingen jQuery eller andra beroenden.
 *
 * Samtyckescookien innehåller ett slumpat ID och versionsnumret från
 * inställningen "Samtyckesversion". Är versionen äldre än sajtens visas
 * rutan igen. Varje val rapporteras till samtyckesloggen (REST).
 *
 * Publikt API: window.rcc (se längst ner).
 * Event: "rcc_consent_updated" på document (detail = samtyckesobjektet)
 * samt dataLayer-eventet "rcc_consent_update" för Google Tag Manager.
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
	var i18n = settings.i18n || {};
	var CATEGORIES = [ 'statistics', 'marketing' ];

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
	 * Flyttar in ett block rå HTML (data-html-block) i den levande sidan.
	 * <script>-noder återskapas så att de faktiskt exekverar, övriga
	 * noder (t.ex. <img>, <noscript>) flyttas in oförändrade.
	 */
	function activateHtmlBlock( oldScript ) {
		var temp = document.createElement( 'div' );
		temp.innerHTML = oldScript.textContent;

		var frag = document.createDocumentFragment();
		Array.prototype.slice.call( temp.childNodes ).forEach( function ( node ) {
			if ( node.nodeType === 1 && 'SCRIPT' === node.tagName ) {
				var s = document.createElement( 'script' );
				Array.prototype.slice.call( node.attributes ).forEach( function ( attr ) {
					s.setAttribute( attr.name, attr.value );
				} );
				s.text = node.textContent;
				frag.appendChild( s );
			} else {
				frag.appendChild( node );
			}
		} );

		oldScript.parentNode.replaceChild( frag, oldScript );
	}

	/**
	 * Aktiverar alla blockerade skript för en kategori. Ett skript kan
	 * ange flera kategorier ("statistics marketing") och aktiveras då av
	 * endera. Redan aktiverade skript är inte längre type="text/plain"
	 * och hittas därför inte igen.
	 */
	function activateScripts( category ) {
		var blocked = document.querySelectorAll( 'script[type="text/plain"][data-cookiecategory]' );

		Array.prototype.slice.call( blocked ).forEach( function ( oldScript ) {
			var cats = ( oldScript.getAttribute( 'data-cookiecategory' ) || '' ).split( /\s+/ ).filter( Boolean );
			if ( cats.indexOf( category ) === -1 ) {
				return;
			}

			if ( oldScript.hasAttribute( 'data-html-block' ) ) {
				activateHtmlBlock( oldScript );
				return;
			}

			var newScript = document.createElement( 'script' );

			for ( var i = 0; i < oldScript.attributes.length; i++ ) {
				var attr = oldScript.attributes[ i ];
				if ( 'type' === attr.name ) {
					continue;
				}
				if ( 'data-cookiesrc' === attr.name ) {
					newScript.src = attr.value;
					continue;
				}
				newScript.setAttribute( attr.name, attr.value );
			}

			newScript.type = 'text/javascript';
			if ( oldScript.text ) {
				newScript.text = oldScript.text;
			}

			oldScript.parentNode.replaceChild( newScript, oldScript );
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

		CATEGORIES.forEach( function ( cat ) {
			if ( consent[ cat ] ) {
				activateScripts( cat );
			}
		} );
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
	function setupGatedIframe( iframe, consent ) {
		var category = iframe.getAttribute( 'data-cookiecategory' );
		var realSrc = iframe.getAttribute( 'data-cookiesrc' );
		if ( ! category || ! realSrc ) {
			return;
		}

		if ( consent && consent[ category ] ) {
			if ( iframe.getAttribute( 'src' ) !== realSrc ) {
				iframe.src = realSrc;
			}
			var existingOverlay = iframe.parentElement ? iframe.parentElement.querySelector( '.rcc-iframe-overlay' ) : null;
			if ( existingOverlay ) {
				existingOverlay.parentNode.removeChild( existingOverlay );
			}
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

		if ( parent ) {
			parent.insertBefore( overlay, iframe.nextSibling );
		}
	}

	function activateGatedIframes( consent ) {
		var iframes = document.querySelectorAll( 'iframe.rcc-gated-iframe[data-cookiesrc]' );
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

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
