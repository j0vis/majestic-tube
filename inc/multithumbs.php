<?php
/**
 * Multithumb management.
 *
 * Adds the "Thumbnails" admin metabox (multiple thumbs per video for
 * rotation) and the front-end hover rotation behavior. Uses the original
 * unindexed meta key `thumbs` so existing data keeps working.
 *
 * @package Majestic Tube
 * @version 2.5.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the thumbnails metabox on posts.
 */
function majestic_tube_add_thumbs_meta_box() {
	add_meta_box(
		'majestic-tube-thumbs',
		__( 'Thumbnails (rotation)', 'majestic-tube' ),
		'majestic_tube_thumbs_meta_box_callback',
		'post',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'majestic_tube_add_thumbs_meta_box' );

/**
 * Flatten a post's stored `thumbs` meta into a list of single URLs.
 *
 * Two shapes of this key are in the wild. The theme's own admin screen stores
 * one meta row per thumbnail, and the video importer stores a single row whose
 * URLs are joined with commas. Reading the comma-joined form as one value
 * lets it survive esc_url_raw() intact, because a comma is a legal URL
 * character - so the hover preview would then request one nonexistent image
 * and the admin screen would show a single unremovable entry. Splitting on the
 * comma makes both shapes produce the same list.
 *
 * @param mixed $values Raw get_post_meta( $post_id, 'thumbs', false ) result.
 * @return string[] Individual, non-empty URLs.
 */
function majestic_tube_parse_thumbs_meta( $values ) {
	$urls = array();

	foreach ( (array) $values as $value ) {
		if ( ! is_scalar( $value ) ) {
			continue;
		}

		foreach ( explode( ',', (string) $value ) as $url ) {
			$url = trim( $url );

			if ( '' !== $url ) {
				$urls[] = $url;
			}
		}
	}

	return $urls;
}

/**
 * Turn a front-end "extra thumbnails" box into a list of unique URLs.
 *
 * The admin metabox adds one thumbnail at a time through AJAX, which needs an
 * existing post to attach it to - a visitor filling in the submission form has
 * no post yet. Its box is therefore plain text, and a submitter writes it the
 * way they would write any other list: one URL per line. Commas are accepted
 * too, because that is the shape `thumbs` already has in the database and the
 * shape the rotation attribute exports.
 *
 * Empty lines, duplicates and anything esc_url_raw() refuses are dropped, so
 * what reaches the meta key is only what the card can actually request.
 *
 * @param string $raw Raw textarea value.
 * @return string[] Unique thumbnail URLs, in the order they were typed.
 */
function majestic_tube_parse_submitted_thumbs( $raw ) {
	$urls = array();

	if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
		return $urls;
	}

	foreach ( preg_split( '/[\r\n,]+/', $raw ) as $candidate ) {
		$url = esc_url_raw( trim( $candidate ) );

		if ( '' !== $url && ! in_array( $url, $urls, true ) ) {
			$urls[] = $url;
		}
	}

	return $urls;
}

/**
 * Render the thumbnails metabox.
 *
 * The box prints no nonce field of its own. Nothing in the post form writes
 * this list - a thumbnail is added or removed the moment its button is pressed,
 * through the admin-ajax.php requests below, and those are authorized by the
 * post's own capability check and the ajax nonce. A wp_nonce_field() here was a
 * field no handler ever read, and it read like the list was saved on publish.
 *
 * @param WP_Post $post Current post.
 */
function majestic_tube_thumbs_meta_box_callback( $post ) {
	$thumbs = majestic_tube_parse_thumbs_meta( get_post_meta( $post->ID, 'thumbs', false ) );
	?>
	<div class="majestic-tube-thumbs-list" id="majestic-tube-thumbs-list">
		<?php
		foreach ( $thumbs as $thumb_url ) {
			printf(
				'<div class="majestic-tube-thumb-item">
					<img src="%1$s" alt="" />
					<button type="button" class="button majestic-tube-thumb-remove" data-thumb="%2$s">%3$s</button>
				</div>',
				esc_url( $thumb_url ),
				esc_attr( $thumb_url ),
				esc_html__( 'Remove', 'majestic-tube' )
			);
		}
		?>
	</div>

	<p>
		<input type="url" id="majestic-tube-new-thumb-url" placeholder="<?php esc_attr_e( 'https://... thumbnail URL', 'majestic-tube' ); ?>" class="large-text" />
		<button type="button" class="button button-primary majestic-tube-thumb-add"><?php esc_html_e( 'Add thumbnail', 'majestic-tube' ); ?></button>
	</p>
	<p class="description"><?php esc_html_e( 'Multiple thumbnails are rotated on hover in the front-end. The first one is used as the main thumb if no featured image is set.', 'majestic-tube' ); ?></p>

	<script>
	// Data passed from PHP for the AJAX handlers. The strings are encoded with
	// the JSON_HEX_* flags inc/assets.php uses for its own inline payload: the
	// labels come from translation files, and a translated sentence holding
	// "</script>" must not be able to end this block.
	window.majesticTubeThumbsAdmin = {
		postId: <?php echo esc_js( $post->ID ); ?>,
		ajaxUrl: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>,
		nonce: <?php echo wp_json_encode( wp_create_nonce( 'ajax-nonce' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>,
		removeLabel: <?php echo wp_json_encode( __( 'Remove', 'majestic-tube' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>,
		errorLabel: <?php echo wp_json_encode( __( 'The thumbnail could not be saved. Please try again.', 'majestic-tube' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>
	};

	/*
	 * The Add and Remove buttons had no listener at all: this block handed out
	 * the endpoint, the nonce and the post id, and nothing read them, so both
	 * controls did nothing on every post edit screen. The listeners live here,
	 * beside the markup they drive, because the screens that render this box
	 * enqueue no script of their own. The handlers behind them check the
	 * capability against the post each time, so the data here is not the thing
	 * that decides what a request may change.
	 */
	( function () {
		var data = window.majesticTubeThumbsAdmin;
		var list = document.getElementById( 'majestic-tube-thumbs-list' );
		var input = document.getElementById( 'majestic-tube-new-thumb-url' );

		if ( ! data || ! list || ! input ) {
			return;
		}

		function request( action, url ) {
			var body = new FormData();

			body.append( 'action', action );
			body.append( 'nonce', data.nonce );
			body.append( 'post_id', data.postId );
			body.append( 'thumb_url', url );

			return fetch( data.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			} ).then( function ( response ) {
				return response.json();
			} ).then( function ( json ) {
				if ( ! json || ! json.result ) {
					throw new Error( 'rejected' );
				}

				return json;
			} );
		}

		function row( url ) {
			var item = document.createElement( 'div' );
			var image = document.createElement( 'img' );
			var button = document.createElement( 'button' );

			item.className = 'majestic-tube-thumb-item';
			image.src = url;
			image.alt = '';
			button.type = 'button';
			button.className = 'button majestic-tube-thumb-remove';
			button.setAttribute( 'data-thumb', url );
			button.textContent = data.removeLabel;
			item.appendChild( image );
			item.appendChild( button );

			return item;
		}

		function failed() {
			window.alert( data.errorLabel );
		}

		document.addEventListener( 'click', function ( e ) {
			var target = e.target;

			if ( ! target || typeof target.closest !== 'function' ) {
				return;
			}

			var add = target.closest( '.majestic-tube-thumb-add' );
			var remove = target.closest( '.majestic-tube-thumb-remove' );

			if ( add ) {
				e.preventDefault();

				var url = input.value.trim();

				if ( ! url ) {
					input.focus();

					return;
				}

				request( 'majestic_tube_insert_thumb', url ).then( function () {
					list.appendChild( row( url ) );
					input.value = '';
				} ).catch( failed );

				return;
			}

			if ( remove ) {
				e.preventDefault();

				request( 'majestic_tube_remove_thumb', remove.getAttribute( 'data-thumb' ) ).then( function () {
					var item = remove.closest( '.majestic-tube-thumb-item' );

					if ( item ) {
						item.parentNode.removeChild( item );
					}
				} ).catch( failed );
			}
		} );
	}() );
	</script>
	<?php
}

/**
 * Shared request handling for adding or removing one thumbnail.
 *
 * @param bool $remove Whether to remove the exact URL instead of adding it.
 */
function majestic_tube_handle_thumb_mutation( $remove ) {
	check_ajax_referer( 'ajax-nonce', 'nonce' );

	if ( ! isset( $_POST['post_id'], $_POST['thumb_url'] ) || is_array( $_POST['post_id'] ) || ! is_string( $_POST['thumb_url'] ) ) {
		wp_send_json( array( 'result' => false ), 400 );
	}

	$post_id   = absint( wp_unslash( $_POST['post_id'] ) );
	$thumb_url = esc_url_raw( wp_unslash( $_POST['thumb_url'] ) );

	if ( ! $post_id || ! $thumb_url ) {
		wp_send_json( array( 'result' => false ), 400 );
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json( array( 'result' => false ), 403 );
	}

	if ( $remove ) {
		/*
		 * delete_post_meta() matches one whole row, so it finds nothing when
		 * the row holds several comma-joined URLs - which is exactly how the
		 * importer stores them. Rebuild the row without the requested URL and
		 * fall back to a plain delete when it was stored as a row of its own.
		 */
		$stored = get_post_meta( $post_id, 'thumbs', false );
		$joined = false;

		foreach ( (array) $stored as $value ) {
			if ( is_string( $value ) && false !== strpos( $value, ',' ) ) {
				$joined = true;
				break;
			}
		}

		if ( $joined ) {
			$remaining = array();

			foreach ( majestic_tube_parse_thumbs_meta( $stored ) as $url ) {
				if ( $url !== $thumb_url ) {
					$remaining[] = $url;
				}
			}

			delete_post_meta( $post_id, 'thumbs' );

			if ( $remaining ) {
				update_post_meta( $post_id, 'thumbs', implode( ',', $remaining ) );
			}
		} else {
			delete_post_meta( $post_id, 'thumbs', $thumb_url );
		}
	} else {
		add_post_meta( $post_id, 'thumbs', $thumb_url, false );
	}

	// KingTube exposes a flat boolean, not wp_send_json_success()'s wrapper.
	wp_send_json( array( 'result' => true ) );
}

/**
 * AJAX: add a thumbnail to a post.
 */
function majestic_tube_ajax_insert_thumb() {
	majestic_tube_handle_thumb_mutation( false );
}
add_action( 'wp_ajax_majestic_tube_insert_thumb', 'majestic_tube_ajax_insert_thumb' );

/**
 * AJAX: remove a thumbnail (deletes by exact meta value, like the original).
 */
function majestic_tube_ajax_remove_thumb() {
	majestic_tube_handle_thumb_mutation( true );
}
add_action( 'wp_ajax_majestic_tube_remove_thumb', 'majestic_tube_ajax_remove_thumb' );

/**
 * Get the rotation thumbnails of a video.
 *
 * Mirrors the original lookup order: when the post has a featured image and
 * more than one image is attached to it, the attached images are used (sorted);
 * otherwise the `thumbs` meta values written by the WP-Script importers are
 * used. Front-end rotation reads the card's data-thumbs attribute, so this
 * helper is the single source of truth for templates.
 *
 * The string form of this list is exposed to WP-Script templates as
 * wpst_get_multithumbs(); see inc/compat.php.
 *
 * @param int $post_id Post ID, defaults to the current post.
 * @return string[] Thumbnail URLs, highest priority first.
 */
function majestic_tube_get_multithumbs( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();

	if ( ! $post_id ) {
		return array();
	}

	$thumbs = array();

	if ( has_post_thumbnail( $post_id ) ) {
		$attachments = get_attached_media( 'image', $post_id );

		if ( count( $attachments ) > 1 ) {
			$size = majestic_tube_get_thumb_size();

			foreach ( (array) $attachments as $attachment ) {
				$url = wp_get_attachment_image_url( $attachment->ID, $size );

				if ( $url ) {
					$thumbs[] = $url;
				}
			}

			sort( $thumbs );
		}
	}

	if ( ! $thumbs ) {
		$thumbs = majestic_tube_parse_thumbs_meta( get_post_meta( $post_id, 'thumbs', false ) );
	}

	$urls = array();

	foreach ( $thumbs as $thumb ) {
		$thumb = esc_url_raw( $thumb );

		// The original switched http:// to https:// on SSL requests so hover
		// previews do not trigger mixed-content warnings.
		if ( $thumb && is_ssl() ) {
			$thumb = preg_replace( '#^http://#i', 'https://', $thumb );
		}

		if ( $thumb && ! in_array( $thumb, $urls, true ) ) {
			$urls[] = $thumb;
		}
	}

	/**
	 * Filter the rotation thumbnails of a video.
	 *
	 * @param string[] $urls    Thumbnail URLs.
	 * @param int      $post_id Post ID.
	 */
	return apply_filters( 'majestic_tube_multithumbs', $urls, $post_id );
}