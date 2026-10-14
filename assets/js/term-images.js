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
		var activeWrapper;

		/**
		 * Write a picked attachment into the field that opened the modal.
		 *
		 * The preview is built as an element with attributes set on it rather
		 * than as a string of HTML: a media library URL containing a quote used
		 * to close the src attribute and turn the rest of the URL into markup.
		 *
		 * The size the picker asks for is not always there. WordPress does not
		 * generate a thumbnail for an image smaller than the registered size,
		 * and a filtered or freshly imported attachment can carry no `sizes`
		 * map at all, so `attachment.sizes.thumbnail.url` was a TypeError that
		 * left the modal open on a selection that never reached the field. Each
		 * smaller size falls back to a larger one, then to the attachment root
		 * (which has the same `url` shape as a size entry), and an attachment
		 * with no usable URL leaves the preview empty instead of showing a
		 * broken image.
		 *
		 * @param {jQuery}  wrapper    Field the modal was opened from.
		 * @param {Object?} attachment Selected attachment, as JSON.
		 */
		function insertPreview( wrapper, attachment ) {
			var sizes = ( attachment && attachment.sizes ) || {};
			var image = sizes.thumbnail || sizes.medium || sizes.full || attachment;
			var url   = image && typeof image.url === 'string' ? image.url : '';

			wrapper.find( '.majestic-tube-term-image-id' ).val( attachment && attachment.id ? attachment.id : '' );

			var preview = wrapper.find( '.majestic-tube-term-image-preview' ).empty();

			if ( url ) {
				preview.append( $( '<img />', { src: url, alt: '' } ) );
			}

			wrapper.find( '.majestic-tube-term-image-remove' ).show();
		}

		$( document ).on( 'click', '.majestic-tube-term-image-select', function ( e ) {
			e.preventDefault();

			// The frame is reused, so the handler cannot close over the field
			// the click came from; it reads the current one instead.
			activeWrapper = $( this ).closest( '.majestic-tube-term-image-field' );

			if ( ! frame ) {
				frame = wp.media( {
					title: majesticTubeTermImage.title,
					button: { text: majesticTubeTermImage.select },
					multiple: false
				} );

				// Bound once, with the frame it belongs to: binding on every
				// open stacked another handler per click, so the Nth selection
				// wrote the preview N times.
				frame.on( 'select', function () {
					var selection = frame.state().get( 'selection' ).first();

					if ( activeWrapper ) {
						insertPreview( activeWrapper, selection ? selection.toJSON() : null );
					}
				} );
			}

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
