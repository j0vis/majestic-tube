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
	$nonce   = wp_nonce_field( 'majestic_tube_term_image', 'majestic_tube_term_image_nonce', true, false );
	$field   = $nonce . sprintf(
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
 * Build the line shown on a category or actor directory card.
 *
 * The Customizer holds a short phrase for each of the two directories, and
 * the tokens below are filled in per card. A token the site has no value for
 * falls back to the term name rather than leaving a hole in the sentence:
 * `{description}` on a category nobody has written a description for becomes
 * the category name, so `Free "{description}" videos` reads sensibly on every
 * card whether or not the administrator got round to writing one.
 *
 * An empty phrase means the site has opted out of the generated line, and the
 * term's own description is used instead, which is what the card showed before
 * the option existed.
 *
 * @param WP_Term $term     Term object.
 * @param string  $taxonomy Term taxonomy.
 * @return string Plain text, ready to escape, or an empty string.
 */
function majestic_tube_term_card_line( $term, $taxonomy ) {
	$template = ( 'actors' === $taxonomy )
		? (string) majestic_tube_get_option( 'wpst-options', 'actor-card-description', '' )
		: (string) majestic_tube_get_option( 'wpst-options', 'category-card-description', '' );

	$description = trim( wp_strip_all_tags( (string) get_term_field( 'description', $term->term_id, $taxonomy, 'raw' ) ) );
	$name        = (string) $term->name;
	$count       = (int) $term->count;

	if ( '' === trim( $template ) ) {
		return $description;
	}

	return strtr(
		$template,
		array(
			'{name}'        => $name,
			'{description}' => '' !== $description ? $description : $name,
			'{count}'       => number_format_i18n( $count ),
			'{videos}'      => _n( 'video', 'videos', $count, 'majestic-tube' ),
		)
	);
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

	/*
	 * The line fills the empty band between the title and the count. A
	 * directory of categories and actors is mostly title plus number, and the
	 * number alone left each card looking unfinished. main.css drops the two
	 * line title reservation on these cards so the line lands in space the
	 * card already had rather than making every card in a row taller.
	 */
	$description = majestic_tube_term_card_line( $term, $taxonomy );
	?>
	<article class="video-card <?php echo esc_attr( $type ); ?>-card">
		<a class="video-card-thumbnail" href="<?php echo esc_url( $link ); ?>">
			<?php if ( $portrait ) : ?>
				<img src="<?php echo esc_url( $portrait ); ?>" alt="<?php echo esc_attr( $term->name ); ?>" loading="lazy" width="320" height="180" />
			<?php else : ?>
				<span class="video-card-placeholder"><?php majestic_tube_icon( 'image' ); ?></span>
			<?php endif; ?>
		</a>
		<header class="video-card-header">
			<h3 class="video-card-title">
				<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $term->name ); ?></a>
			</h3>
			<?php if ( '' !== $description ) : ?>
				<p class="term-card-description"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
		</header>
		<footer class="video-card-meta">
			<?php /*
			 * Deliberately not `video-card-views`. That class is a badge
			 * absolutely positioned over the card's thumbnail, and this count
			 * sits in the meta row underneath the title in normal flow. With
			 * the badge class it was torn out of the card and positioned
			 * against a distant ancestor, so it painted along the left edge of
			 * the page and only showed once hovering a card lifted it clear.
			 */ ?>
			<span class="term-card-count">
				<?php majestic_tube_icon( 'film' ); ?>
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
 * Nonce-verified (term hooks carry no nonce of their own, so the add/edit
 * form prints one). The taxonomy is resolved from the term itself, never
 * trusted from $_POST, so a crafted request cannot pin meta onto a term in
 * another taxonomy.
 *
 * @param int $term_id Term ID.
 */
function majestic_tube_term_image_save( $term_id ) {
	$term_id = absint( $term_id );

	if ( ! $term_id ) {
		return;
	}

	if ( ! isset( $_POST['majestic_tube_term_image_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['majestic_tube_term_image_nonce'] ) ), 'majestic_tube_term_image' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	$term = get_term( $term_id );

	if ( ! $term || is_wp_error( $term ) ) {
		return;
	}

	$taxonomies = majestic_tube_term_image_taxonomies();

	if ( ! isset( $taxonomies[ $term->taxonomy ] ) ) {
		return;
	}

	$meta_key = $taxonomies[ $term->taxonomy ];
	$image_id = isset( $_POST['majestic_tube_term_image_id'] ) ? absint( wp_unslash( $_POST['majestic_tube_term_image_id'] ) ) : 0;

	if ( $image_id ) {
		if ( ! wp_attachment_is_image( $image_id ) ) {
			return;
		}

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