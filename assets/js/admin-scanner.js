/**
 * Relativt Cookie Consent – skannern i WP-admin.
 * Hämtar sidlistan och skannar en sida per anrop, så att inget enskilt
 * anrop tar för lång tid och förloppet syns. Laddar sedan om sidan för
 * att visa resultatet, som byggs i PHP.
 */
( function () {
	'use strict';

	var cfg = window.rccScanner || {};
	var i18n = cfg.i18n || {};

	function format( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		return String( str || '' ).replace( /%(\d)\$[sd]|%s/g, function ( match, n ) {
			var i = n ? parseInt( n, 10 ) - 1 : 0;
			return args[ i ] !== undefined ? args[ i ] : '';
		} );
	}

	function post( action, data ) {
		var body = new window.FormData();
		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );
		Object.keys( data || {} ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );
		return window.fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( res ) {
				return res.json().catch( function () {
					throw new Error( 'HTTP ' + res.status );
				} );
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					throw new Error( ( json && json.data && json.data.message ) || 'okänt fel' );
				}
				return json.data;
			} );
	}

	function init() {
		var btn = document.getElementById( 'rcc-scan-start' );
		if ( ! btn ) {
			return;
		}
		var box = document.getElementById( 'rcc-scan-progress' );
		var bar = document.getElementById( 'rcc-scan-bar' );
		var status = document.getElementById( 'rcc-scan-status' );
		var extra = document.getElementById( 'rcc-scan-extra' );

		function say( text ) {
			status.textContent = text;
		}

		btn.addEventListener( 'click', function () {
			btn.disabled = true;
			box.hidden = false;
			bar.value = 0;
			say( i18n.starting );

			post( 'rcc_scan_start', { extra: extra ? extra.value : '' } )
				.then( function ( data ) {
					var urls = data.urls || [];
					if ( data.skipped && data.skipped.length ) {
						window.console && window.console.warn( format( i18n.skipped, data.skipped.join( ', ' ) ) );
					}
					var i = 0;
					function next() {
						if ( i >= urls.length ) {
							return post( 'rcc_scan_finish', {} );
						}
						var url = urls[ i ];
						say( format( i18n.scanning, i + 1, urls.length, url ) );
						bar.value = Math.round( ( i / urls.length ) * 100 );
						i++;
						return post( 'rcc_scan_url', { url: url } ).catch( function () {
							// En sida som inte gick att hämta stoppar inte resten;
							// felet syns i listan över skannade sidor.
						} ).then( next );
					}
					return next();
				} )
				.then( function () {
					bar.value = 100;
					say( i18n.done );
					var url = new window.URL( window.location.href );
					url.searchParams.delete( 'rcc_notice' );
					url.searchParams.delete( 'domain' );
					url.hash = '';
					window.location.href = url.toString();
				} )
				.catch( function ( err ) {
					say( format( i18n.failed, err && err.message ? err.message : err ) );
					btn.disabled = false;
				} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
