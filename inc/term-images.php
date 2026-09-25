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
 * Get a term image URL.
 *
 * @param int    $term_id Term ID.
 * @param string $taxonomy Taxonomy slug.
 * @param string $size    Image size.
 * @return string URL or empty string.
 */
function majestic_tube_get_term_image_url( $term_id, $taxonomy, $size = 'majestic-tube-thumb-medium' ) {
	$taxonomies = majestic_tube_term_image_taxonomies();

	if ( ! isset( $taxonomies[ $taxonomy ] ) ) {
		return '';
	}

	$image_id = (int) get_term_meta( $term_id, $taxonomies[ $taxonomy ], true );

	if ( ! $image_id ) {
		return '';
	}

	$url = wp_get_attachment_image_url( $image_id, $size );

	return $url ? $url : '';
}