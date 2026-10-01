<?php
/**
 * Per-term SEO fields for categories, actors, tags, studios and series.
 *
 * A term archive is a page. The theme already gives a single video its own
 * title, description and metadata box, and the archive pages a video
 * directory shows were the one place an editor could not do the same: the
 * browser title was whatever WordPress assembled, and the meta description
 * was the site tagline or nothing at all.
 *
 * So each term gets two boxes on the screen where it is already edited - SEO
 * title and meta description - and they drive the browser tab, the Google
 * result, and the Facebook and X cards, because a description that only
 * reaches one of the three is a description half the search engines see.
 *
 * Both fields are opt-in and silent by default. An empty field stores
 * nothing, and an empty term keeps exactly the output it had before this
 * module existed: no behaviour changes on update, and there is no per-term
 * switch to discover and get wrong.
 *
 * An SEO plugin that owns term metadata (Yoast, Rank Math, SEOPress and the
 * rest) is already asking the same questions on the same screen. When one is
 * active this module stands down completely - no boxes, no output - rather
 * than printing a second title and a second description into the same head.
 *
 * @package Majestic Tube
 * @version 2.2.24
 */

defined( 'ABSPATH' ) || exit;

/**
 * Term meta key holding an editor's own page title.
 */
const MAJESTIC_TUBE_TERM_SEO_TITLE = 'majestic_tube_seo_title';

/**
 * Term meta key holding an editor's own page description.
 */
const MAJESTIC_TUBE_TERM_SEO_DESCRIPTION = 'majestic_tube_seo_description';

/**
 * The taxonomies whose terms get the fields.
 *
 * Every taxonomy the site can list videos by, because a video directory
 * without an editable title is a page nobody can describe. Taxonomies that
 * are not registered are skipped, so a site running a child theme that
 * removed one is unaffected.
 *
 * @return string[]
 */
function majestic_tube_term_seo_taxonomies() {
	return array( 'category', 'post_tag', 'actors', 'studio', 'series' );
}

/**
 * Whether an SEO plugin already provides per-term metadata fields.
 *
 * The same plugin list the theme uses to stand down of social tags and of
 * the sitemap: one answer to "does something else already own this?".
 *
 * @return bool
 */
function majestic_tube_term_seo_plugin_active() {

	if ( ! function_exists( 'majestic_tube_social_meta_plugins' ) || ! function_exists( 'majestic_tube_is_plugin_active' ) ) {
		return false;
	}

	foreach ( array_keys( majestic_tube_social_meta_plugins() ) as $plugin ) {
		if ( majestic_tube_is_plugin_active( $plugin ) ) {
			return true;
		}
	}

	return false;
}

/**
 * The taxonomies that exist on this site and can carry the fields.
 *
 * @return string[]
 */
function majestic_tube_term_seo_supported_taxonomies() {

	if ( majestic_tube_term_seo_plugin_active() ) {
		return array();
	}

	return array_values( array_filter(
		majestic_tube_term_seo_taxonomies(),
		function ( $taxonomy ) {
			return taxonomy_exists( $taxonomy );
		}
	) );
}

/**
 * An editor's own title for a term, or an empty string.
 *
 * @param WP_Term|int $term Term object or ID.
 * @return string
 */
function majestic_tube_term_seo_title( $term ) {
	$term_id = $term instanceof WP_Term ? $term->term_id : (int) $term;

	if ( ! $term_id ) {
		return '';
	}

	return trim( (string) get_term_meta( $term_id, MAJESTIC_TUBE_TERM_SEO_TITLE, true ) );
}

/**
 * An editor's own description for a term, or an empty string.
 *
 * @param WP_Term|int $term Term object or ID.
 * @return string
 */
function majestic_tube_term_seo_description( $term ) {
	$term_id = $term instanceof WP_Term ? $term->term_id : (int) $term;

	if ( ! $term_id ) {
		return '';
	}

	return trim( (string) get_term_meta( $term_id, MAJESTIC_TUBE_TERM_SEO_DESCRIPTION, true ) );
}

/**
 * The term whose archive is being viewed, when that is what is being viewed.
 *
 * @return WP_Term|null
 */
function majestic_tube_term_seo_queried_term() {

	if ( ! ( is_category() || is_tag() || is_tax() ) ) {
		return null;
	}

	$term = get_queried_object();

	return ( $term instanceof WP_Term ) ? $term : null;
}

/**
 * The two fields, shared by the add and edit forms.
 *
 * The help text states what a search result actually rewards - a title that
 * fits on one line, a description that earns the click - because a box
 * labelled "SEO title" with no guidance is the one thing guaranteed to be
 * filled with the term name twice.
 *
 * @param WP_Term|null $term    Term being edited, or null on the add form.
 * @param string       $context Either `add` or `edit`.
 */
function majestic_tube_term_seo_field( $term = null, $context = 'edit' ) {

	$term_id      = ( $term instanceof WP_Term ) ? (int) $term->term_id : 0;
	$title        = majestic_tube_term_seo_title( $term_id );
	$description  = majestic_tube_term_seo_description( $term_id );
	$name         = ( $term instanceof WP_Term ) ? $term->name : '';
	$site         = get_bloginfo( 'name' );
	$placeholder  = trim( $name . ( $name && $site ? ' - ' . $site : '' ) );
	$term_text    = ( $term instanceof WP_Term ) ? trim( wp_strip_all_tags( (string) $term->description ) ) : '';
	$description_placeholder = ( '' !== $term_text ) ? wp_trim_words( $term_text, 22, '...' ) : '';

	$nonce = wp_nonce_field( 'majestic_tube_term_seo', 'majestic_tube_term_seo_nonce', true, false );

	$title_field = $nonce . sprintf(
		'<input type="text" name="majestic_tube_seo_title" id="majestic_tube_seo_title" class="majestic-tube-seo-field regular-text" data-max="60" value="%1$s" placeholder="%2$s" maxlength="120" autocomplete="off" />
		 <span id="majestic_tube_seo_title-counter" class="description majestic-tube-seo-counter"></span>
		 <p class="description">%3$s</p>',
		esc_attr( $title ),
		esc_attr( $placeholder ),
		esc_html__( 'Shown in the browser tab and as the title on Google. Leave empty to keep the default. Around 60 characters fits on one line of results.', 'majestic-tube' )
	);

	$description_field = sprintf(
		'<textarea name="majestic_tube_seo_description" id="majestic_tube_seo_description" class="majestic-tube-seo-field" data-max="160" rows="3" placeholder="%1$s">%2$s</textarea>
		 <span id="majestic_tube_seo_description-counter" class="description majestic-tube-seo-counter"></span>
		 <p class="description">%3$s</p>',
		esc_attr( $description_placeholder ),
		esc_textarea( $description ),
		esc_html__( 'The two or three lines Google shows under the title, and the text Facebook and X share. Leave empty to keep the default. Around 160 characters.', 'majestic-tube' )
	);

	if ( 'edit' === $context ) {
		?>
		<tr class="form-field">
			<th scope="row"><label for="majestic_tube_seo_title"><?php esc_html_e( 'SEO title', 'majestic-tube' ); ?></label></th>
			<td>
				<?php echo $title_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped values. ?>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="majestic_tube_seo_description"><?php esc_html_e( 'Meta description', 'majestic-tube' ); ?></label></th>
			<td>
				<?php echo $description_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped values. ?>
			</td>
		</tr>
		<?php
		majestic_tube_term_seo_counter_script();
		return;
	}

	?>
	<div class="form-field">
		<label for="majestic_tube_seo_title"><?php esc_html_e( 'SEO title', 'majestic-tube' ); ?></label>
		<?php echo $title_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped values. ?>
	</div>
	<div class="form-field">
		<label for="majestic_tube_seo_description"><?php esc_html_e( 'Meta description', 'majestic-tube' ); ?></label>
		<?php echo $description_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped values. ?>
	</div>
	<?php
	majestic_tube_term_seo_counter_script();
}

/**
 * The character counter under the two fields.
 *
 * Vanilla JavaScript, printed once per screen, because a length hint is the
 * whole difference between a title written for a result and a title that
 * gets truncated - and a jQuery dependency to count characters would be a
 * poor trade for fourteen lines.
 *
 * @return void
 */
function majestic_tube_term_seo_counter_script() {

	static $printed = false;

	if ( $printed ) {
		return;
	}

	$printed = true;
	?>
	<script>
	( function () {
		function paint( field ) {
			var out = document.getElementById( field.id + '-counter' );
			if ( ! out ) {
				return;
			}
			var max = parseInt( field.getAttribute( 'data-max' ), 10 ) || 160;
			var used = field.value.length;
			out.textContent = used + ' / ' + max + ' characters';
			out.style.color = used === 0 ? '#646970' : ( used <= max ? '#1a7f37' : '#b32d2e' );
		}
		function bind( event ) {
			var field = event.target;
			if ( field && field.classList && field.classList.contains( 'majestic-tube-seo-field' ) ) {
				paint( field );
			}
		}
		document.addEventListener( 'input', bind );
		document.addEventListener( 'DOMContentLoaded', function () {
			var fields = document.querySelectorAll( '.majestic-tube-seo-field' );
			for ( var i = 0; i < fields.length; i++ ) {
				paint( fields[ i ] );
			}
		} );
	} )();
	</script>
	<?php
}

/**
 * Add the fields to every "add new term" screen.
 *
 * @return void
 */
function majestic_tube_term_seo_add_fields() {

	foreach ( majestic_tube_term_seo_supported_taxonomies() as $taxonomy ) {
		add_action(
			$taxonomy . '_add_form_fields',
			function () {
				majestic_tube_term_seo_field( null, 'add' );
			},
			10,
			0
		);
	}
}
add_action( 'init', 'majestic_tube_term_seo_add_fields', 20 );

/**
 * Add the fields to every "edit term" screen.
 *
 * @return void
 */
function majestic_tube_term_seo_edit_fields() {

	foreach ( majestic_tube_term_seo_supported_taxonomies() as $taxonomy ) {
		add_action(
			$taxonomy . '_edit_form_fields',
			function ( $term ) {
				majestic_tube_term_seo_field( $term, 'edit' );
			},
			10,
			1
		);
	}
}
add_action( 'init', 'majestic_tube_term_seo_edit_fields', 20 );

/**
 * Save the fields from the add and edit forms.
 *
 * Nonce-verified, because the term hooks carry none of their own, and the
 * taxonomy is resolved from the term rather than trusted from $_POST, so a
 * crafted request cannot pin a title onto a term in a taxonomy that has no
 * fields. A field cleared in the editor deletes its meta rather than storing
 * an empty string, so "unset" stays distinguishable from "set to nothing".
 *
 * @param int $term_id Term ID.
 */
function majestic_tube_term_seo_save( $term_id ) {

	$term_id = absint( $term_id );

	if ( ! $term_id ) {
		return;
	}

	if ( ! isset( $_POST['majestic_tube_term_seo_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['majestic_tube_term_seo_nonce'] ) ), 'majestic_tube_term_seo' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	$term = get_term( $term_id );

	if ( ! $term || is_wp_error( $term ) ) {
		return;
	}

	if ( ! in_array( $term->taxonomy, majestic_tube_term_seo_supported_taxonomies(), true ) ) {
		return;
	}

	$title       = isset( $_POST['majestic_tube_seo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['majestic_tube_seo_title'] ) ) : '';
	$description = isset( $_POST['majestic_tube_seo_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['majestic_tube_seo_description'] ) ) : '';

	foreach (
		array(
			MAJESTIC_TUBE_TERM_SEO_TITLE       => trim( $title ),
			MAJESTIC_TUBE_TERM_SEO_DESCRIPTION => trim( $description ),
		) as $key => $value
	) {
		if ( '' === $value ) {
			delete_term_meta( $term_id, $key );
		} else {
			update_term_meta( $term_id, $key, $value );
		}
	}
}
add_action( 'created_term', 'majestic_tube_term_seo_save', 10, 1 );
add_action( 'edited_term', 'majestic_tube_term_seo_save', 10, 1 );

/**
 * Use the editor's title for the browser tab and the Google result.
 *
 * The filter runs after WordPress has assembled its own title and before it
 * is printed, so returning the editor's string replaces the whole thing -
 * including the "Category Archives:" framing and the trailing site name,
 * which is exactly what someone writing their own title expects to happen.
 * An unset term is left alone.
 *
 * @param string $title Assembled document title.
 * @return string
 */
function majestic_tube_term_seo_document_title( $title ) {

	if ( majestic_tube_term_seo_plugin_active() ) {
		return $title;
	}

	$term = majestic_tube_term_seo_queried_term();

	if ( ! $term ) {
		return $title;
	}

	$custom = majestic_tube_term_seo_title( $term );

	return ( '' !== $custom ) ? $custom : $title;
}
add_filter( 'pre_get_document_title', 'majestic_tube_term_seo_document_title', 10 );

/**
 * Use the editor's description for the search result.
 *
 * WordPress fills the description tag with the site tagline on an archive,
 * which is either empty or the same sentence on every category page. The
 * editor's text replaces it when there is one, and the core value stands
 * when there is not.
 *
 * @param array  $meta      Meta keys and values.
 * @param string $meta_type One of blog, term, post or home.
 * @return array
 */
function majestic_tube_term_seo_meta( $meta, $meta_type = '' ) {

	if ( majestic_tube_term_seo_plugin_active() ) {
		return $meta;
	}

	if ( 'term' !== $meta_type && ! ( is_category() || is_tag() || is_tax() ) ) {
		return $meta;
	}

	$term = majestic_tube_term_seo_queried_term();

	if ( ! $term ) {
		return $meta;
	}

	$description = majestic_tube_term_seo_description( $term );

	if ( '' !== $description ) {
		$meta['description'] = $description;
	}

	return $meta;
}
add_filter( 'wp_meta', 'majestic_tube_term_seo_meta', 10, 2 );

/**
 * Use the editor's description for the Facebook and X cards too.
 *
 * One field, three places a reader meets the page. Without this the share
 * card would keep showing the site tagline while Google shows the written
 * description, which is the kind of inconsistency nobody notices until it
 * is pointed out.
 *
 * @param array $tags Open Graph or Twitter Card tags.
 * @return array
 */
function majestic_tube_term_seo_social_tags( $tags ) {

	if ( majestic_tube_term_seo_plugin_active() ) {
		return $tags;
	}

	$term = majestic_tube_term_seo_queried_term();

	if ( ! $term ) {
		return $tags;
	}

	$description = majestic_tube_term_seo_description( $term );

	if ( '' === $description ) {
		return $tags;
	}

	if ( isset( $tags['og:description'] ) ) {
		$tags['og:description'] = $description;
	}

	if ( isset( $tags['twitter:description'] ) ) {
		$tags['twitter:description'] = $description;
	}

	return $tags;
}
add_filter( 'majestic_tube_social_meta_tags', 'majestic_tube_term_seo_social_tags', 10 );
add_filter( 'majestic_tube_twitter_meta_tags', 'majestic_tube_term_seo_social_tags', 10 );
