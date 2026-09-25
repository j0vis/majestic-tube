<?php
/**
 * Multithumb management.
 *
 * Adds the "Thumbnails" admin metabox (multiple thumbs per video for
 * rotation) and the front-end hover rotation behavior. Uses the original
 * unindexed meta key `thumbs` so existing data keeps working.
 *
 * @package Majestic Tube
 * @version 2.0.0
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
 * Render the thumbnails metabox.
 *
 * @param WP_Post $post Current post.
 */
function majestic_tube_thumbs_meta_box_callback( $post ) {
	wp_nonce_field( 'majestic_tube_thumbs', 'majestic_tube_thumbs_nonce' );

	$thumbs = get_post_meta( $post->ID, 'thumbs', false );
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
	// Data passed from PHP for the AJAX handlers.
	window.majesticTubeThumbsAdmin = {
		postId: <?php echo esc_js( $post->ID ); ?>,
		ajaxUrl: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
		nonce: <?php echo wp_json_encode( wp_create_nonce( 'ajax-nonce' ) ); ?>
	};
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

	if ( ! isset( $_POST['post_id'], $_POST['thumb_url'] ) ) {
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
		delete_post_meta( $post_id, 'thumbs', $thumb_url );
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
		$thumbs = (array) get_post_meta( $post_id, 'thumbs', false );
	}

	$urls = array();

	foreach ( $thumbs as $thumb ) {
		$thumb = esc_url_raw( $thumb );

		// The original switched http:// to https:// on SSL requests so hover
		// previews do not trigger mixed-content warnings.
		if ( $thumb && is_ssl() ) {
			$thumb = str_replace( 'http://', 'https://', $thumb );
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