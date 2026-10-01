<?php
/**
 * Virtual collections: vocabulary, stable routes and exact eligibility.
 *
 * Pages use the existing posts and taxonomy relationships. A rebuildable
 * background index provides discovery; it is not an editable shadow copy.
 *
 * @package Majestic Tube
 * @version 2.2.26
 */
defined( 'ABSPATH' ) || exit;
const MAJESTIC_TUBE_PSEO_CACHE_GROUP = 'majestic_tube_pseo';

function majestic_tube_facet_types() {
	return (array) apply_filters( 'majestic_tube_facet_types', array(
		'actor_category' => array( 'label' => __( 'actor in category', 'majestic-tube' ), 'min' => 6 ),
		'actor_actor' => array( 'label' => __( 'actor pairing', 'majestic-tube' ), 'min' => 4 ),
		'actor_length' => array( 'label' => __( 'actor duration band', 'majestic-tube' ), 'min' => 3 ),
		'category_tag' => array( 'label' => __( 'tag in category', 'majestic-tube' ), 'min' => 5 ),
		'studio' => array( 'label' => __( 'studio', 'majestic-tube' ), 'min' => 8 ),
		'series' => array( 'label' => __( 'series', 'majestic-tube' ), 'min' => 5 ),
	) );
}
function majestic_tube_length_bands() {
	return (array) apply_filters( 'majestic_tube_length_bands', array(
		'short' => array( 'label' => __( 'Short', 'majestic-tube' ), 'min' => 0, 'max' => 299 ),
		'medium' => array( 'label' => __( 'Medium length', 'majestic-tube' ), 'min' => 300, 'max' => 1199 ),
		'long' => array( 'label' => __( 'Long', 'majestic-tube' ), 'min' => 1200, 'max' => 3599 ),
		'longest' => array( 'label' => __( 'Longest', 'majestic-tube' ), 'min' => 3600, 'max' => 0 ),
	) );
}
function majestic_tube_facet_taxonomies() {
	return (array) apply_filters( 'majestic_tube_facet_taxonomies', array( 'category' => 'category', 'post_tag' => 'tag', 'actors' => 'actor', 'studio' => 'studio', 'series' => 'series' ) );
}
function majestic_tube_register_facet_taxonomies() {
	foreach ( array( 'studio' => array( __( 'Studios', 'majestic-tube' ), __( 'Studio', 'majestic-tube' ), __( 'Video Studios', 'majestic-tube' ) ), 'series' => array( __( 'Series', 'majestic-tube' ), __( 'Series', 'majestic-tube' ), __( 'Video Series', 'majestic-tube' ) ) ) as $taxonomy => $labels ) {
		if ( taxonomy_exists( $taxonomy ) ) { continue; }
		register_taxonomy( $taxonomy, 'post', array(
			'labels' => array( 'name' => $labels[0], 'singular_name' => $labels[1], 'menu_name' => $labels[2], 'all_items' => $labels[0], 'edit_item' => sprintf( __( 'Edit %s', 'majestic-tube' ), $labels[1] ), 'add_new_item' => sprintf( __( 'Add New %s', 'majestic-tube' ), $labels[1] ) ),
			'hierarchical' => false, 'public' => true, 'show_ui' => true, 'show_admin_column' => true, 'show_in_rest' => true,
			'update_count_callback' => '_update_post_term_count', 'query_var' => true, 'rewrite' => array( 'slug' => $taxonomy, 'with_front' => false ),
		) );
	}
}
add_action( 'init', 'majestic_tube_register_facet_taxonomies', 1 );
function majestic_tube_facet_query_vars( $vars ) {
	return array_merge( $vars, array( 'mt_facet', 'mt_facet_taxonomy', 'mt_facet_term', 'mt_facet_term2', 'mt_facet_band', 'mt_browse' ) );
}
add_filter( 'query_vars', 'majestic_tube_facet_query_vars' );
function majestic_tube_facet_rewrite_rules() {
	$rules = array(
		'^browse/page/([0-9]{1,5})/?$' => 'index.php?mt_browse=1&paged=$matches[1]',
		'^browse/([a-z0-9-]+)/([a-z0-9])(?:/page/([0-9]{1,5}))?/?$' => 'index.php?mt_browse=1&mt_facet=$matches[1]&mt_facet_band=$matches[2]&paged=$matches[3]',
		'^browse/([a-z0-9-]+)(?:/page/([0-9]{1,5}))?/?$' => 'index.php?mt_browse=1&mt_facet=$matches[1]&paged=$matches[2]',
		'^browse/?$' => 'index.php?mt_browse=1',
		'^actor/([^/]+)/with/([^/]+)/page/([0-9]{1,5})/?$' => 'index.php?mt_facet=actor_actor&mt_facet_taxonomy=actors&mt_facet_term=$matches[1]&mt_facet_term2=$matches[2]&paged=$matches[3]',
		'^actor/([^/]+)/length/([a-z]+)/page/([0-9]{1,5})/?$' => 'index.php?mt_facet=actor_length&mt_facet_taxonomy=actors&mt_facet_term=$matches[1]&mt_facet_band=$matches[2]&paged=$matches[3]',
		'^actor/([^/]+)/([^/]+)/page/([0-9]{1,5})/?$' => 'index.php?mt_facet=actor_category&mt_facet_taxonomy=actors&mt_facet_term=$matches[1]&mt_facet_term2=$matches[2]&paged=$matches[3]',
		'^category/([^/]+)/([^/]+)/page/([0-9]{1,5})/?$' => 'index.php?mt_facet=category_tag&mt_facet_taxonomy=category&mt_facet_term=$matches[1]&mt_facet_term2=$matches[2]&paged=$matches[3]',
		'^actor/([^/]+)/with/([^/]+)/?$' => 'index.php?mt_facet=actor_actor&mt_facet_taxonomy=actors&mt_facet_term=$matches[1]&mt_facet_term2=$matches[2]',
		'^actor/([^/]+)/length/([a-z]+)/?$' => 'index.php?mt_facet=actor_length&mt_facet_taxonomy=actors&mt_facet_term=$matches[1]&mt_facet_band=$matches[2]',
		'^actor/([^/]+)/([^/]+)/?$' => 'index.php?mt_facet=actor_category&mt_facet_taxonomy=actors&mt_facet_term=$matches[1]&mt_facet_term2=$matches[2]',
		'^category/([^/]+)/([^/]+)/?$' => 'index.php?mt_facet=category_tag&mt_facet_taxonomy=category&mt_facet_term=$matches[1]&mt_facet_term2=$matches[2]',
	);
	foreach ( $rules as $pattern => $query ) { add_rewrite_rule( $pattern, $query, 'top' ); }
}
add_action( 'init', 'majestic_tube_facet_rewrite_rules', 12 );
function majestic_tube_facet_rewrite_revision() { return (int) apply_filters( 'majestic_tube_facet_rewrite_revision', 2 ); }
function majestic_tube_maybe_flush_facet_rewrites() {
	$revision = majestic_tube_facet_rewrite_revision();
	if ( (int) get_option( 'majestic_tube_facet_rewrite_revision', 0 ) >= $revision ) { return; }
	flush_rewrite_rules( false ); update_option( 'majestic_tube_facet_rewrite_revision', $revision );
}
add_action( 'admin_init', 'majestic_tube_maybe_flush_facet_rewrites', 30 );
add_action( 'after_switch_theme', 'majestic_tube_maybe_flush_facet_rewrites', 45 );
function majestic_tube_is_facet_request() { return '' !== (string) get_query_var( 'mt_facet' ); }
function majestic_tube_is_browse_request() { return '' !== (string) get_query_var( 'mt_browse' ); }

function majestic_tube_current_facet() {
	static $resolved = array();
	$key = wp_json_encode( array( get_query_var( 'mt_facet' ), get_query_var( 'mt_facet_taxonomy' ), get_query_var( 'mt_facet_term' ), get_query_var( 'mt_facet_term2' ), get_query_var( 'mt_facet_band' ) ) );
	if ( array_key_exists( $key, $resolved ) ) { return $resolved[ $key ]; }
	$resolved[ $key ] = null;
	if ( majestic_tube_is_browse_request() ) { return null; }
	$type = sanitize_key( (string) get_query_var( 'mt_facet' ) );
	$types = majestic_tube_facet_types();
	if ( ! isset( $types[ $type ] ) ) { return null; }
	$taxonomy = sanitize_key( (string) get_query_var( 'mt_facet_taxonomy' ) );
	$expected = 'category_tag' === $type ? 'category' : ( in_array( $type, array( 'studio', 'series' ), true ) ? $type : 'actors' );
	if ( $taxonomy !== $expected ) { return null; }
	$primary = sanitize_title( (string) get_query_var( 'mt_facet_term' ) );
	$secondary = sanitize_title( (string) get_query_var( 'mt_facet_term2' ) );
	$band = sanitize_key( (string) get_query_var( 'mt_facet_band' ) );
	$term = get_term_by( 'slug', $primary, $taxonomy );
	if ( ! $term || is_wp_error( $term ) ) { return null; }
	$term2 = null;
	if ( in_array( $type, array( 'actor_category', 'actor_actor', 'category_tag' ), true ) ) {
		$second_taxonomy = 'actor_actor' === $type ? 'actors' : ( 'category_tag' === $type ? 'post_tag' : 'category' );
		$term2 = get_term_by( 'slug', $secondary, $second_taxonomy );
		if ( ! $term2 || is_wp_error( $term2 ) || (int) $term2->term_id === (int) $term->term_id || $band ) { return null; }
	} elseif ( 'actor_length' === $type ) {
		$bands = majestic_tube_length_bands();
		if ( ! isset( $bands[ $band ] ) || $secondary ) { return null; }
	} elseif ( $secondary || $band ) { return null; }
	if ( 'actor_actor' === $type && $term->term_id > $term2->term_id ) { $swap = $term; $term = $term2; $term2 = $swap; }
	$count = majestic_tube_facet_count( $type, $term, $term2, $band );
	if ( $count < 1 ) { return null; }
	return $resolved[ $key ] = array( 'type' => $type, 'primary' => $term->slug, 'secondary' => $term2 ? $term2->slug : '', 'term' => $term, 'term2' => $term2, 'band' => $band, 'count' => $count, 'indexable' => majestic_tube_facet_indexable( $type, $count, $term, $term2, $band ) );
}

/** One query definition for grid and totals. Exact category membership is intentional. */
function majestic_tube_facet_query( $type, $term, $term2 = null, $band = '' ) {
	$taxonomy = in_array( $type, array( 'studio', 'series' ), true ) ? $type : ( 'category_tag' === $type ? 'category' : 'actors' );
	$tax_query = array( 'relation' => 'AND', array( 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => array( (int) $term->term_id ), 'include_children' => false ) );
	if ( $term2 ) { $tax_query[] = array( 'taxonomy' => 'actor_actor' === $type ? 'actors' : ( 'category_tag' === $type ? 'post_tag' : 'category' ), 'field' => 'term_id', 'terms' => array( (int) $term2->term_id ), 'include_children' => false ); }
	$args = array( 'post_type' => 'post', 'post_status' => 'publish', 'ignore_sticky_posts' => true, 'tax_query' => $tax_query );
	if ( $band ) {
		$bands = majestic_tube_length_bands();
		if ( ! isset( $bands[ $band ] ) ) { $args['post__in'] = array( 0 ); }
		else {
			$range = $bands[ $band ];
			$args['meta_query'] = array( array( 'key' => 'duration', 'type' => 'NUMERIC', 'compare' => $range['max'] > 0 ? 'BETWEEN' : '>=', 'value' => $range['max'] > 0 ? array( (int) $range['min'], (int) $range['max'] ) : (int) $range['min'] ) );
		}
	}
	return (array) apply_filters( 'majestic_tube_facet_query_args', $args, array( 'type' => $type, 'term' => $term, 'term2' => $term2, 'band' => $band ) );
}
function majestic_tube_facet_count( $type, $term, $term2 = null, $band = '' ) {
	static $counts = array();
	$key = implode( ':', array( $type, $term->term_id, $term2 ? $term2->term_id : 0, $band ) );
	if ( ! isset( $counts[ $key ] ) ) {
		$query = new WP_Query( array_merge( majestic_tube_facet_query( $type, $term, $term2, $band ), array( 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => false, 'update_post_meta_cache' => false, 'update_post_term_cache' => false ) ) );
		$counts[ $key ] = (int) $query->found_posts;
	}
	return max( 0, (int) apply_filters( 'majestic_tube_facet_count', $counts[ $key ], $type, $term, $term2, $band ) );
}
function majestic_tube_facet_indexable( $type, $count, $term, $term2 = null, $band = '' ) {
	$types = majestic_tube_facet_types();
	$eligible = isset( $types[ $type ] ) && $count >= max( 1, (int) $types[ $type ]['min'] ) && majestic_tube_collection_enabled( $type );
	return (bool) apply_filters( 'majestic_tube_facet_indexable', $eligible, $type, $count, $term, $term2, $band );
}
/** Compatibility adapter; discovery now uses the exact same gate. */
function majestic_tube_facet_is_listable( $type, $count, $term, $term2 = null, $band = '' ) {
	return (bool) apply_filters( 'majestic_tube_facet_is_listable', majestic_tube_facet_indexable( $type, $count, $term, $term2, $band ), $type, $count, $term, $term2, $band );
}
