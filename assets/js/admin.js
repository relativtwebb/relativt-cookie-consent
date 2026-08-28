/**
 * Relativt Cookie Consent – inställningssidan.
 * Flikar (alla fält ligger i samma formulär, så "Spara" sparar allt)
 * och WordPress egna färgväljare.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var $tabs = $( '.rcc-tabs .nav-tab' );
		var $panels = $( '.rcc-tab-panel' );

		function activate( id ) {
			if ( ! $( '#rcc-tab-' + id ).length ) {
				id = $tabs.first().data( 'rcc-tab' );
			}
			$tabs.removeClass( 'nav-tab-active' ).filter( '[data-rcc-tab="' + id + '"]' ).addClass( 'nav-tab-active' );
			$panels.prop( 'hidden', true );
			$( '#rcc-tab-' + id ).prop( 'hidden', false );

			try {
				window.localStorage.setItem( 'rccSettingsTab', id );
			} catch ( e ) {
				// Privat läge eller blockerad lagring – ignorera.
			}
		}

		$tabs.on( 'click', function ( e ) {
			e.preventDefault();
			activate( $( this ).data( 'rcc-tab' ) );
		} );

		var initial = ( window.location.hash || '' ).replace( '#rcc-tab-', '' );
		if ( ! initial ) {
			try {
				initial = window.localStorage.getItem( 'rccSettingsTab' ) || '';
			} catch ( e ) {
				initial = '';
			}
		}
		activate( initial );

		if ( $.fn.wpColorPicker ) {
			$( '.rcc-color-field' ).wpColorPicker();
		}
	} );
} )( jQuery );
