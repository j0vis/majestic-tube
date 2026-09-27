<?php
/**
 * Term images for actors and video categories.
 *
 * Stores attachment IDs in the original term meta keys so data created with
 * the original theme displays identically: actors-image-id, category-image-id.
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shared field definitions.
 *
 * @return array<string, string> taxonomy => term meta key.
 */
function majestic_tube_term_image_taxonomies() {
	return array(
		'actors'   => 'actors-image-id',
		'category' => 'category-image-id',
	);
}

/**
 * Image field markup shared by add/edit forms.
 *
 * @param int    $image_id Saved attachment ID or 0.
 * @param string $context  Either `add` or `edit`.
 */
function majestic_tube_term_image_field( $image_id = 0, $context = 'add' ) {
	$preview = $image_id ? wp_get_attachment_image( $image_id, 'thumbnail' ) : '';
	$field   = sprintf(
		'<input type="hidden" name="majestic_tube_term_image_id" class="majestic-tube-term-image-id" value="%1$s" />' .
		'<div class="majestic-tube-term-image-preview">%2$s</div>' .
		'<button type="button" class="button majestic-tube-term-image-select">%3$s</button>' .
		'<button type="button" class="button majestic-tube-term-image-remove" %4$s>%5$s</button>',
		esc_attr( $image_id ),
		$preview,
		esc_html__( 'Select image', 'majestic-tube' ),
		$image_id ? '' : 'style="display:none;"',
		esc_html__( 'Remove image', 'majestic-tube' )
	);

	if ( 'edit' === $context ) {
		?>
		<tr class="form-field majestic-tube-term-image-field">
			<th scope="row"><label><?php esc_html_e( 'Image', 'majestic-tube' ); ?></label></th>
			<td><?php echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped values and core attachment markup. ?></td>
		</tr>
		<?php
		return;
	}
	?>
	<div class="form-field majestic-tube-term-image-field">
		<label><?php esc_html_e( 'Image', 'majestic-tube' ); ?></label>
		<?php echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped values and core attachment markup. ?>
	</div>
	<?php
}

/**
 * Render one directory card for an actor or category.
 *
 * @param WP_Term $term     Term object.
 * @param string  $taxonomy Term taxonomy.
 * @return void
 */
function majestic_tube_render_term_card( $term, $taxonomy ) {
	$portrait = majestic_tube_get_term_image_url( $term->term_id, $taxonomy, 'majestic-tube-thumb-medium' );
	$link     = get_term_link( $term );

	if ( is_wp_error( $link ) ) {
		return;
	}

	$type = 'actors' === $taxonomy ? 'actor' : 'category';
	?>
	<article class="video-card <?php echo esc_attr( $type ); ?>-card">
		<a class="video-card-thumbnail" href="<?php echo esc_url( $link ); ?>">
			<?php if ( $portrait ) : ?>
				<img src="<?php echo esc_url( $portrait ); ?>" alt="<?php echo esc_attr( $term->name ); ?>" loading="lazy" width="320" height="180" />
			<?php else : ?>
				<span class="video-card-placeholder"></span>
			<?php endif; ?>
		</a>
		<header class="video-card-header">
			<h3 class="video-card-title">
				<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $term->name ); ?></a>
			</h3>
		</header>
		<footer class="video-card-meta">
			<span class="video-card-views">
				<?php
				printf(
					/* translators: %s: video count. */
					esc_html( _n( '%s video', '%s videos', $term->count, 'majestic-tube' ) ),
					esc_html( number_format_i18n( $term->count ) )
				);
				?>
			</span>
		</footer>
	</article>
	<?php
}

/**
 * Add-form field for each supported taxonomy.
 */
function majestic_tube_term_image_add_fields() {
	foreach ( array_keys( majestic_tube_term_image_taxonomies() ) as $taxonomy ) {
		add_action( $taxonomy . '_add_form_fields', function () use ( $taxonomy ) {
			majestic_tube_term_image_field();
		}, 10, 0 );
	}
}
add_action( 'init', 'majestic_tube_term_image_add_fields', 20 );

/**
 * Edit-form field for each supported taxonomy.
 */
function majestic_tube_term_image_edit_fields() {
	foreach ( majestic_tube_term_image_taxonomies() as $taxonomy => $meta_key ) {
		add_action( $taxonomy . '_edit_form_fields', function ( $term ) use ( $meta_key ) {
			$image_id = (int) get_term_meta( $term->term_id, $meta_key, true );
			majestic_tube_term_image_field( $image_id, 'edit' );
		}, 10, 1 );
	}
}
add_action( 'init', 'majestic_tube_term_image_edit_fields', 20 );

/**
 * Save term image from add/edit forms.
 *
 * @param int $term_id Term ID.
 */
function majestic_tube_term_image_save( $term_id ) {
	if ( ! isset( $_POST['majestic_tube_term_image_id'] ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- term save hooks have no nonce context; capability checked below.
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	$image_id = absint( wp_unslash( $_POST['majestic_tube_term_image_id'] ) );

	// Determine which taxonomy's meta key to use.
	$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';

	$taxonomies = majestic_tube_term_image_taxonomies();

	if ( ! isset( $taxonomies[ $taxonomy ] ) ) {
		return;
	}

	$meta_key = $taxonomies[ $taxonomy ];

	if ( $image_id ) {
		update_term_meta( $term_id, $meta_key, $image_id );
	} else {
		delete_term_meta( $term_id, $meta_key );
	}
}
add_action( 'created_term', 'majestic_tube_term_image_save', 10, 1 );
add_action( 'edited_term', 'majestic_tube_term_image_save', 10, 1 );

/**
 * How a term directory should pick a stand-in image from its own posts.
 *
 * An actor term stands for one person, so the most recent video is the honest
 * choice: it is the same video a visitor would land on, and it is stable. A
 * category term stands for a shelf of unrelated videos, where any single one is
 * arbitrary, so a random member video gives each card its own character.
 *
 * @param string $taxonomy Taxonomy slug.
 * @return string `recent` or `random`.
 */
function majestic_tube_term_fallback_mode( $taxonomy ) {
	$mode = 'category' === $taxonomy ? 'random' : 'recent';

	/**
	 * Filter how a term with no image of its own borrows one from its posts.
	 *
	 * @param string $mode     Either `recent` or `random`.
	 * @param string $taxonomy Taxonomy slug.
	 */
	$mode = apply_filters( 'majestic_tube_term_fallback_mode', $mode, $taxonomy );

	return 'random' === $mode ? 'random' : 'recent';
}

/**
 * Pick a post from a term to borrow a thumbnail from.
 *
 * Only posts that actually have a featured image are considered, because a post
 * without one would send the caller back to the placeholder it was trying to
 * avoid.
 *
 * The chosen post ID is cached rather than the query result, which is what makes
 * `random` usable: without a cache the card would show a different image on every
 * page load, and the directory would run one extra query per card per request.
 * Caching it means each term keeps its pick until the cache lapses or a term or
 * post changes, both of which are already tracked by the `last_changed` markers
 * baked into the key.
 *
 * @param int    $term_id  Term ID.
 * @param string $taxonomy Taxonomy slug.
 * @return int Post ID, or 0 when the term has no post with a thumbnail.
 */
function majestic_tube_get_term_fallback_post_id( $term_id, $taxonomy ) {
	$term_id = (int) $term_id;

	if ( $term_id <= 0 ) {
		return 0;
	}

	$taxonomies = majestic_tube_term_image_taxonomies();

	if ( ! isset( $taxonomies[ $taxonomy ] ) ) {
		return 0;
	}

	$mode = majestic_tube_term_fallback_mode( $taxonomy );

	$cache_key = sprintf(
		'fallbackpost_%s_%d_%s_%s_%s',
		$taxonomy,
		$term_id,
		$mode,
		preg_replace( '/[^A-Za-z0-9_.:-]/', '', (string) wp_cache_get( 'last_changed', 'terms' ) ),
		preg_replace( '/[^A-Za-z0-9_.:-]/', '', (string) wp_cache_get( 'last_changed', 'posts' ) )
	);

	$cached = wp_cache_get( $cache_key, MAJESTIC_TUBE_TERM_CACHE_GROUP );

	if ( false !== $cached ) {
		return (int) $cached;
	}

	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 1,
		'fields'              => 'ids',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
		'orderby'             => 'random' === $mode ? 'rand' : 'date',
		'order'               => 'DESC',
		'tax_query'           => array(
			array(
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => $term_id,
			),
		),
		// Without this the query can return a post that has no thumbnail, and the
		// caller ends up back at the placeholder it was trying to replace.
		'meta_query'          => array(
			array(
				'key'     => '_thumbnail_id',
				'compare' => 'EXISTS',
			),
		),
	);

	/**
	 * Filter the query used to borrow a thumbnail from a term's posts.
	 *
	 * @param array  $args     Arguments passed to get_posts().
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param string $mode     Either `recent` or `random`.
	 */
	$args = apply_filters( 'majestic_tube_term_fallback_post_args', $args, $term_id, $taxonomy, $mode );

	$post_ids = get_posts( $args );
	$post_id  = ! empty( $post_ids ) ? (int) $post_ids[0] : 0;

	// A term with no usable post is a legitimate answer, so the negative result is
	// cached too. Otherwise every page load would repeat the query to learn nothing.
	wp_cache_set( $cache_key, $post_id, MAJESTIC_TUBE_TERM_CACHE_GROUP, MAJESTIC_TUBE_TERM_CACHE_TTL );

	return $post_id;
}

/**
 * Get a term image URL.
 *
 * Falls back to a thumbnail from one of the term's own posts when no image was
 * uploaded for the term, so a directory of never-customised terms still looks
 * like a directory rather than a wall of placeholders.
 *
 * @param int    $term_id       Term ID.
 * @param string $taxonomy      Taxonomy slug.
 * @param string $size          Image size.
 * @param bool   $allow_fallback Whether to borrow an image from a post. Default true.
 * @return string URL or empty string.
 */
function majestic_tube_get_term_image_url( $term_id, $taxonomy, $size = 'majestic-tube-thumb-medium', $allow_fallback = true ) {
	$taxonomies = majestic_tube_term_image_taxonomies();

	if ( ! isset( $taxonomies[ $taxonomy ] ) ) {
		return '';
	}

	$image_id = (int) get_term_meta( $term_id, $taxonomies[ $taxonomy ], true );

	if ( $image_id ) {
		$url = wp_get_attachment_image_url( $image_id, $size );

		if ( $url ) {
			return $url;
		}
	}

	// No usable image of the term's own, so borrow one from its posts. This also
	// covers the case where the meta points at an attachment that has since been
	// deleted, which would otherwise render as a broken image.
	if ( ! $allow_fallback ) {
		return '';
	}

	$post_id = majestic_tube_get_term_fallback_post_id( $term_id, $taxonomy );

	if ( ! $post_id ) {
		return '';
	}

	$thumb_id = (int) get_post_thumbnail_id( $post_id );

	if ( ! $thumb_id ) {
		return '';
	}

	$url = wp_get_attachment_image_url( $thumb_id, $size );

	/**
	 * Filter the image borrowed from a term's posts.
	 *
	 * Returning an empty string here suppresses the fallback and restores the
	 * placeholder, without having to unhook anything.
	 *
	 * @param string $url      Image URL, or an empty string.
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param int    $post_id  Post the image was taken from.
	 * @param string $size     Image size.
	 */
	return (string) apply_filters( 'majestic_tube_term_fallback_image_url', (string) $url, $term_id, $taxonomy, $post_id, $size );
}