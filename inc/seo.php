<?php
/**
 * Theme-native search appearance and collection templates.
 *
 * @package Majestic Tube
 * @version 2.2.26
 */
defined( 'ABSPATH' ) || exit;

/** One owner for ordinary pages; virtual collections remain theme-owned. */
function majestic_tube_seo_owned() {
	return ! majestic_tube_term_seo_plugin_active();
}

function majestic_tube_collection_defaults() {
	return array(
		'actor_category' => array( 'label' => __( 'Actors by category', 'majestic-tube' ), 'heading' => '{actor.name} in {category.name}', 'title' => '{actor.name} — {category.name} videos | {site.name}', 'description' => 'Browse {video_count} videos featuring {actor.name} in {category.name} on {site.name}.', 'intro' => '{video_count} videos featuring {actor.name} in {category.name}.' ),
		'actor_actor' => array( 'label' => __( 'Actor pairings', 'majestic-tube' ), 'heading' => '{actor.name} with {actor2.name}', 'title' => '{actor.name} with {actor2.name} | {site.name}', 'description' => 'Browse {video_count} videos featuring {actor.name} and {actor2.name} together on {site.name}.', 'intro' => '{video_count} videos featuring {actor.name} and {actor2.name} together.' ),
		'actor_length' => array( 'label' => __( 'Actors by duration', 'majestic-tube' ), 'heading' => '{actor.name}: {band.name} videos', 'title' => '{actor.name}: {band.name} videos | {site.name}', 'description' => 'Browse {video_count} {band.name} videos featuring {actor.name} on {site.name}.', 'intro' => '{video_count} {band.name} videos featuring {actor.name}.' ),
		'category_tag' => array( 'label' => __( 'Categories by tag', 'majestic-tube' ), 'heading' => '{category.name}: {tag.name}', 'title' => '{category.name} videos tagged {tag.name} | {site.name}', 'description' => 'Browse {video_count} {category.name} videos tagged {tag.name} on {site.name}.', 'intro' => '{video_count} {category.name} videos tagged {tag.name}.' ),
	);
}

function majestic_tube_collection_config( $type ) {
	$defaults = majestic_tube_collection_defaults();
	$saved = (array) get_option( 'majestic_tube_collections', array() );
	return array_merge( isset( $defaults[ $type ] ) ? $defaults[ $type ] : array(), array( 'enabled' => true ), isset( $saved[ $type ] ) && is_array( $saved[ $type ] ) ? $saved[ $type ] : array() );
}

/** Disabling search eligibility does not break working visitor URLs. */
function majestic_tube_collection_enabled( $type ) {
	$config = majestic_tube_collection_config( $type );
	return ! empty( $config['enabled'] );
}

function majestic_tube_collection_variables( $facet ) {
	$values = array( '{site.name}' => get_bloginfo( 'name' ), '{video_count}' => number_format_i18n( $facet['count'] ) );
	foreach ( array( 'term', 'term2' ) as $key ) {
		$term = isset( $facet[ $key ] ) ? $facet[ $key ] : null;
		if ( ! $term ) {
			continue;
		}
		$prefix = array( 'actors' => 'actor', 'category' => 'category', 'post_tag' => 'tag', 'studio' => 'studio', 'series' => 'series' );
		if ( isset( $prefix[ $term->taxonomy ] ) ) {
			$name = 'actor_actor' === $facet['type'] && 'term2' === $key ? 'actor2' : $prefix[ $term->taxonomy ];
			$values[ '{' . $name . '.name}' ] = $term->name;
		}
	}
	$bands = majestic_tube_length_bands();
	if ( ! empty( $facet['band'] ) && isset( $bands[ $facet['band'] ] ) ) {
		$values['{band.name}'] = $bands[ $facet['band'] ]['label'];
	}
	return $values;
}

/** Template text is plain text; interpolation never evaluates code. */
function majestic_tube_collection_text( $text, $facet ) {
	$rendered = strtr( $text, majestic_tube_collection_variables( $facet ) );
	return preg_match( '/[{}]/', $rendered ) ? '' : $rendered;
}

function majestic_tube_collection_validate( $type, $input ) {
	$defaults = majestic_tube_collection_defaults();
	if ( ! isset( $defaults[ $type ] ) ) {
		return new WP_Error( 'type', __( 'Unknown collection.', 'majestic-tube' ) );
	}
	$tokens = array( '{site.name}', '{video_count}' );
	preg_match_all( '/\{[^}]+\}/', implode( ' ', $defaults[ $type ] ), $matches );
	$tokens = array_unique( array_merge( $tokens, $matches[0] ) );
	$result = array( 'enabled' => ! empty( $input['enabled'] ) );
	foreach ( array( 'heading', 'title', 'description', 'intro' ) as $field ) {
		$value = sanitize_textarea_field( isset( $input[ $field ] ) && is_string( $input[ $field ] ) ? $input[ $field ] : '' );
		if ( strlen( $value ) > 2000 ) {
			return new WP_Error( 'length', __( 'Each template field must be shorter than 2,000 bytes.', 'majestic-tube' ) );
		}
		if ( '' === trim( $value ) && 'intro' !== $field ) {
			$value = $defaults[ $type ][ $field ];
		}
		$without_tokens = str_replace( $tokens, '', $value );
		if ( preg_match( '/[{}]/', $without_tokens ) ) {
			return new WP_Error( 'token', __( 'Use only the variables listed for this collection. Check unmatched braces too.', 'majestic-tube' ) );
		}
		$result[ $field ] = $value;
	}
	return $result;
}

function majestic_tube_collection_apply( $value, $facet, $field ) {
	$defaults = majestic_tube_collection_defaults();
	if ( ! isset( $defaults[ $facet['type'] ] ) ) {
		return $value;
	}
	$config = majestic_tube_collection_config( $facet['type'] );
	return majestic_tube_collection_text( $config[ $field ], $facet );
}
function majestic_tube_collection_title( $value, $facet ) { return majestic_tube_collection_apply( $value, $facet, 'title' ); }
function majestic_tube_collection_description( $value, $facet ) { return majestic_tube_collection_apply( $value, $facet, 'description' ); }
function majestic_tube_collection_heading( $value, $facet ) { return majestic_tube_collection_apply( $value, $facet, 'heading' ); }
add_filter( 'majestic_tube_facet_title', 'majestic_tube_collection_title', 10, 2 );
add_filter( 'majestic_tube_facet_description', 'majestic_tube_collection_description', 10, 2 );
add_filter( 'majestic_tube_facet_heading', 'majestic_tube_collection_heading', 10, 2 );

/** Ordinary page fields are stored in post meta, never generated shadow posts. */
function majestic_tube_seo_register_meta() {
	foreach ( array( 'post', 'page' ) as $type ) {
		foreach ( array( 'majestic_tube_seo_title', 'majestic_tube_seo_description', 'majestic_tube_seo_canonical', 'majestic_tube_seo_noindex' ) as $key ) {
			register_post_meta( $type, $key, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => 'majestic_tube_seo_canonical' === $key ? 'esc_url_raw' : 'sanitize_text_field', 'auth_callback' => function ( $allowed, $key, $id ) { return current_user_can( 'edit_post', $id ); } ) );
		}
	}
}
add_action( 'init', 'majestic_tube_seo_register_meta' );

function majestic_tube_seo_post_box() {
	if ( majestic_tube_seo_owned() ) {
		add_meta_box( 'majestic-tube-search', __( 'Search appearance', 'majestic-tube' ), 'majestic_tube_seo_post_fields', array( 'post', 'page' ), 'normal', 'default' );
	}
}
add_action( 'add_meta_boxes', 'majestic_tube_seo_post_box' );
function majestic_tube_seo_post_fields( $post ) {
	wp_nonce_field( 'majestic_tube_search', 'majestic_tube_search_nonce' );
	foreach ( array( 'title' => __( 'Search title', 'majestic-tube' ), 'description' => __( 'Meta description', 'majestic-tube' ) ) as $field => $label ) {
		$id = 'majestic_tube_seo_' . $field;
		echo '<p><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></p>';
		echo '<textarea class="large-text mt-seo-counter" rows="2" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" placeholder="' . esc_attr( 'title' === $field ? $post->post_title : wp_trim_words( wp_strip_all_tags( $post->post_excerpt ?: $post->post_content ), 25 ) ) . '">' . esc_textarea( get_post_meta( $post->ID, $id, true ) ) . '</textarea><p class="description">' . esc_html__( 'Leave empty to use the automatic default. Search engines may show different text.', 'majestic-tube' ) . '</p>';
	}
	echo '<div class="mt-seo-live-preview" aria-live="polite"><p><strong>' . esc_html__( 'Search preview (illustrative)', 'majestic-tube' ) . '</strong></p><p class="mt-seo-live-title"></p><p>' . esc_html( get_permalink( $post->ID ) ) . '</p><p class="mt-seo-live-description"></p></div>';
	echo '<details><summary>' . esc_html__( 'Advanced', 'majestic-tube' ) . '</summary><p><label><input type="checkbox" name="majestic_tube_seo_noindex" value="1" ' . checked( get_post_meta( $post->ID, 'majestic_tube_seo_noindex', true ), '1', false ) . '> ' . esc_html__( 'Exclude from search results and the sitemap', 'majestic-tube' ) . '</label></p><p><label for="majestic_tube_seo_canonical">' . esc_html__( 'Canonical URL override (normally leave empty)', 'majestic-tube' ) . '</label></p><input class="large-text" type="url" id="majestic_tube_seo_canonical" name="majestic_tube_seo_canonical" value="' . esc_attr( get_post_meta( $post->ID, 'majestic_tube_seo_canonical', true ) ) . '"></details>';
}
function majestic_tube_seo_save_post( $id ) {
	if ( ! isset( $_POST['majestic_tube_search_nonce'] ) || ! is_string( $_POST['majestic_tube_search_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['majestic_tube_search_nonce'] ) ), 'majestic_tube_search' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) || ! majestic_tube_seo_owned() ) {
		return;
	}
	foreach ( array( 'title', 'description', 'canonical', 'noindex' ) as $field ) {
		$key = 'majestic_tube_seo_' . $field;
		$value = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		$value = 'canonical' === $field ? esc_url_raw( $value, array( 'http', 'https' ) ) : sanitize_text_field( $value );
		if ( 'noindex' === $field ) { $value = '1' === $value ? '1' : ''; }
		if ( 'canonical' === $field && $value && untrailingslashit( $value ) === untrailingslashit( get_permalink( $id ) ) ) { $value = ''; }
		if ( '' === $value ) { delete_post_meta( $id, $key ); } else { update_post_meta( $id, $key, $value ); }
	}
}
add_action( 'save_post', 'majestic_tube_seo_save_post' );

function majestic_tube_seo_title( $title ) {
	if ( ! majestic_tube_seo_owned() || is_404() || majestic_tube_is_facet_request() ) { return $title; }
	if ( is_front_page() ) {
		$settings = (array) get_option( 'majestic_tube_search_settings', array() );
		if ( ! empty( $settings['home_title'] ) ) { return $settings['home_title']; }
	}
	if ( is_singular() ) {
		$custom = get_post_meta( get_queried_object_id(), 'majestic_tube_seo_title', true );
		if ( $custom ) { return $custom; }
	}
	return $title;
}
add_filter( 'pre_get_document_title', 'majestic_tube_seo_title', 15 );

function majestic_tube_seo_description() {
	if ( is_404() || is_search() ) { return ''; }
	if ( majestic_tube_is_facet_request() && ! majestic_tube_is_browse_request() ) {
		$facet = majestic_tube_current_facet();
		return $facet ? majestic_tube_facet_description( $facet ) : '';
	}
	if ( is_front_page() ) {
		$settings = (array) get_option( 'majestic_tube_search_settings', array() );
		return ! empty( $settings['home_description'] ) ? $settings['home_description'] : ( wp_strip_all_tags( majestic_tube_get_option( 'wpst-options', 'seo-footer-text', '' ) ) ?: get_bloginfo( 'description' ) );
	}
	$term = majestic_tube_term_seo_queried_term();
	if ( $term ) { return majestic_tube_term_seo_description( $term ) ?: majestic_tube_term_seo_description_placeholder( $term ); }
	if ( is_singular() ) {
		$post = get_post( get_queried_object_id() );
		if ( ! $post ) { return ''; }
		return get_post_meta( $post->ID, 'majestic_tube_seo_description', true ) ?: wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_excerpt ?: $post->post_content ) ), 25, '…' );
	}
	return get_bloginfo( 'description' );
}
function majestic_tube_seo_head() {
	if ( ! majestic_tube_seo_owned() || majestic_tube_is_facet_request() || majestic_tube_is_browse_request() ) { return; }
	$text = majestic_tube_seo_description();
	if ( $text ) { printf( '<meta name="description" content="%s">' . "\n", esc_attr( $text ) ); }
	$url = majestic_tube_seo_canonical_url();
	// Core owns ordinary singular canonicals; the override uses its filter.
	if ( $url && ! is_singular() ) { printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $url ) ); }
}
add_action( 'wp_head', 'majestic_tube_seo_head', 3 );
function majestic_tube_seo_canonical_url() {
	if ( is_404() || is_search() ) { return ''; }
	if ( is_singular() ) { return get_post_meta( get_queried_object_id(), 'majestic_tube_seo_canonical', true ) ?: wp_get_canonical_url( get_queried_object_id() ); }
	if ( is_author() || is_date() ) { return get_pagenum_link( max( 1, (int) get_query_var( 'paged' ) ), false ); }
	$term = majestic_tube_term_seo_queried_term();
	$url = $term ? get_term_link( $term ) : ( is_home() && ! is_front_page() ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/' ) );
	if ( is_wp_error( $url ) ) { return ''; }
	return majestic_tube_seo_page_url( $url );
}
function majestic_tube_seo_page_url( $url ) {
	$page = max( 1, (int) get_query_var( 'paged' ) );
	if ( $page < 2 ) { return $url; }
	return get_option( 'permalink_structure' ) ? trailingslashit( $url ) . user_trailingslashit( 'page/' . $page, 'paged' ) : add_query_arg( 'paged', $page, $url );
}
function majestic_tube_seo_canonical_override( $url, $post ) {
	return majestic_tube_seo_owned() ? ( get_post_meta( $post->ID, 'majestic_tube_seo_canonical', true ) ?: $url ) : $url;
}
add_filter( 'get_canonical_url', 'majestic_tube_seo_canonical_override', 10, 2 );
function majestic_tube_seo_robots( $robots ) {
	$term = majestic_tube_term_seo_queried_term();
	if ( majestic_tube_seo_owned() && $term && in_array( $term->taxonomy, array( 'studio', 'series' ), true ) && ! majestic_tube_collection_enabled( $term->taxonomy ) ) { $robots['noindex'] = true; unset( $robots['index'] ); }
	if ( majestic_tube_seo_owned() && is_singular() && get_post_meta( get_queried_object_id(), 'majestic_tube_seo_noindex', true ) ) { $robots['noindex'] = true; unset( $robots['index'] ); }
	return $robots;
}
add_filter( 'wp_robots', 'majestic_tube_seo_robots', 30 );
function majestic_tube_seo_social( $tags ) {
	if ( ! majestic_tube_seo_owned() || is_404() ) { return $tags; }
	$title = wp_get_document_title();
	$description = majestic_tube_seo_description();
	$url = majestic_tube_seo_canonical_url();
	if ( majestic_tube_is_browse_request() ) {
		$group = sanitize_key( (string) get_query_var( 'mt_facet' ) );
		$groups = majestic_tube_browse_groups();
		$path = isset( $groups[ $group ] ) ? '/browse/' . $group . '/' : '/browse/';
		$letter = strtolower( (string) get_query_var( 'mt_facet_band' ) );
		if ( isset( $groups[ $group ] ) && preg_match( '/^[a-z0-9]$/', $letter ) ) { $path .= $letter . '/'; }
		$url = majestic_tube_seo_page_url( home_url( $path ) );
	}
	if ( majestic_tube_is_facet_request() && ! majestic_tube_is_browse_request() ) {
		$facet = majestic_tube_current_facet();
		$url = $facet ? majestic_tube_seo_page_url( majestic_tube_facet_url( $facet['type'], $facet['term'], $facet['term2'], $facet['band'] ) ) : '';
	}
	foreach ( array( 'og:title', 'twitter:title' ) as $key ) { if ( isset( $tags[ $key ] ) ) { $tags[ $key ] = $title; } }
	foreach ( array( 'og:description', 'twitter:description' ) as $key ) { if ( isset( $tags[ $key ] ) ) { $tags[ $key ] = $description; } }
	if ( $url && isset( $tags['og:url'] ) ) { $tags['og:url'] = $url; }
	return $tags;
}
add_filter( 'majestic_tube_social_meta_tags', 'majestic_tube_seo_social', 20 );
add_filter( 'majestic_tube_twitter_meta_tags', 'majestic_tube_seo_social', 20 );

/** Exclude only real noindex/alternate-canonical posts from core sitemaps. */
function majestic_tube_seo_sitemap_args( $args, $type ) {
	if ( ! majestic_tube_seo_owned() ) { return $args; }
	$existing = isset( $args['meta_query'] ) ? $args['meta_query'] : array();
	$args['meta_query'] = array( 'relation' => 'AND' );
	if ( $existing ) { $args['meta_query'][] = $existing; }
	$args['meta_query'][] = array( 'relation' => 'OR', array( 'key' => 'majestic_tube_seo_noindex', 'compare' => 'NOT EXISTS' ), array( 'key' => 'majestic_tube_seo_noindex', 'value' => '1', 'compare' => '!=' ) );
	$args['meta_query'][] = array( 'relation' => 'OR', array( 'key' => 'majestic_tube_seo_canonical', 'compare' => 'NOT EXISTS' ), array( 'key' => 'majestic_tube_seo_canonical', 'value' => '', 'compare' => '=' ) );
	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'majestic_tube_seo_sitemap_args', 10, 2 );
function majestic_tube_seo_sitemap_taxonomies( $taxonomies ) {
	if ( ! majestic_tube_seo_owned() ) { return $taxonomies; }
	foreach ( array( 'studio', 'series' ) as $type ) { if ( ! majestic_tube_collection_enabled( $type ) ) { unset( $taxonomies[ $type ] ); } }
	return $taxonomies;
}
add_filter( 'wp_sitemaps_taxonomies', 'majestic_tube_seo_sitemap_taxonomies' );
