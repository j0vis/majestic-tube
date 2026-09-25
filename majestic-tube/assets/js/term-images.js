/**
 * Term image picker using the WordPress media modal.
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

( function ( $ ) {
	'use strict';

	$( function () {
		var frame;

		$( document ).on( 'click', '.majestic-tube-term-image-select', function ( e ) {
			e.preventDefault();

			var wrapper = $( this ).closest( '.majestic-tube-term-image-field' );

			if ( ! frame ) {
				frame = wp.media( {
					title: majesticTubeTermImage.title,
					button: { text: majesticTubeTermImage.select },
					multiple: false
				} );
			}

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();

				wrapper.find( '.majestic-tube-term-image-id' ).val( attachment.id );
				wrapper.find( '.majestic-tube-term-image-preview' ).html(
					'<img src="' + attachment.sizes.thumbnail.url + '" alt="" />'
				);
				wrapper.find( '.majestic-tube-term-image-remove' ).show();
			} );

			frame.open();
		} );

		$( document ).on( 'click', '.majestic-tube-term-image-remove', function ( e ) {
			e.preventDefault();

			var wrapper = $( this ).closest( '.majestic-tube-term-image-field' );

			wrapper.find( '.majestic-tube-term-image-id' ).val( '' );
			wrapper.find( '.majestic-tube-term-image-preview' ).empty();
			wrapper.find( '.majestic-tube-term-image-remove' ).hide();
		} );
	} );
}( jQuery ) );