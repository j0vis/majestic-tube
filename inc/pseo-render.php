<?php
/**
 * Virtual collection routing and bounded discovery.
 *
 * @package Majestic Tube
 * @version 2.2.26
 */
defined( 'ABSPATH' ) || exit;

function majestic_tube_browse_groups() {
	return (array) apply_filters( 'majestic_tube_browse_groups', array(
		'actor-category' => array( 'label' => __( 'Actors by category', 'majestic-tube' ), 'type' => 'actor_category' ),
		'actor-pair' => array( 'label' => __( 'Actor pairings', 'majestic-tube' ), 'type' => 'actor_actor' ),
		'actor-length' => array( 'label' => __( 'Actors by duration', 'majestic-tube' ), 'type' => 'actor_length' ),
		'category-tag' => array( 'label' => __( 'Categories by tag', 'majestic-tube' ), 'type' => 'category_tag' ),
		'studio' => array( 'label' => __( 'Studios', 'majestic-tube' ), 'type' => 'studio' ),
		'series' => array( 'label' => __( 'Series', 'majestic-tube' ), 'type' => 'series' ),
	) );
}

function majestic_tube_facet_pre_get_posts( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) { return; }
	if ( majestic_tube_is_browse_request() ) {
		$query->set( 'post__in', array( 0 ) );
		$query->set( 'no_found_rows', true );
		return;
	}
	if ( ! majestic_tube_is_facet_request() ) { return; }
	$facet = majestic_tube_current_facet();
	if ( ! $facet ) {
		$query->set( 'post__in', array( 0 ) );
		return;
	}
	$args = majestic_tube_facet_query( $facet['type'], $facet['term'], $facet['term2'], $facet['band'] );
	foreach ( array( 'posts_per_page', 'fields', 'update_post_meta_cache', 'update_post_term_cache' ) as $key ) { unset( $args[ $key ] ); }
	$args['posts_per_page'] = max( 1, min( 100, (int) majestic_tube_get_option( 'wpst-options', 'videos-per-page', 30 ) ) );
	// Earlier homepage sort hooks must not filter a collection by a views meta key.
	$query->set( 'meta_key', '' );
	$query->set( 'orderby', array( 'date' => 'DESC', 'ID' => 'DESC' ) );
	$query->set( 'date_query', array() );
	foreach ( $args as $key => $value ) { $query->set( $key, $value ); }
	$query->is_home = false;
	$query->is_archive = true;
	$query->is_singular = false;
	$query->is_tax = false;
	$query->is_category = false;
	$query->is_tag = false;
}
add_action( 'pre_get_posts', 'majestic_tube_facet_pre_get_posts', 20 );

/** HTTP status is set before get_header(), not inside the rendered template. */
function majestic_tube_facet_status() {
	if ( is_admin() ) { return; }
	if ( majestic_tube_is_browse_request() ) {
		global $wp_query;
		$groups = majestic_tube_browse_groups();
		$group = sanitize_key( (string) get_query_var( 'mt_facet' ) );
		$letter = (string) get_query_var( 'mt_facet_band' );
		$page = max( 1, (int) get_query_var( 'paged' ) );
		$valid = ( ! $group || isset( $groups[ $group ] ) ) && ( ! $letter || preg_match( '/^[a-zA-Z0-9]$/', $letter ) );
		if ( $page > 1 && ( ! $group || $page > (int) ceil( majestic_tube_browse_group_count( $group, $letter ) / 60 ) ) ) { $valid = false; }
		if ( ! $valid ) { $wp_query->set_404(); status_header( 404 ); nocache_headers(); return; }
		$wp_query->is_404 = false;
		status_header( 200 );
		return;
	}
	if ( majestic_tube_is_facet_request() && ! majestic_tube_current_facet() ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		do_action( 'majestic_tube_facet_unresolved', get_query_var( 'mt_facet' ) );
	}
}
add_action( 'template_redirect', 'majestic_tube_facet_status', 1 );

/** Core cannot infer a virtual archive's canonical from a queried post. */
function majestic_tube_facet_redirect_canonical( $redirect ) {
	return majestic_tube_is_facet_request() || majestic_tube_is_browse_request() ? false : $redirect;
}
add_filter( 'redirect_canonical', 'majestic_tube_facet_redirect_canonical' );

function majestic_tube_pseo_template_include( $template ) {
	if ( is_admin() || is_404() ) { return $template; }
	if ( majestic_tube_is_browse_request() ) { $file = '/template-pseo-browse.php'; }
	elseif ( majestic_tube_is_facet_request() && majestic_tube_current_facet() && ! is_404() ) { $file = '/inc/pseo-template.php'; }
	else { return $template; }
	$child = get_stylesheet_directory() . $file;
	if ( file_exists( $child ) ) { return $child; }
	return file_exists( MAJESTIC_TUBE_DIR . $file ) ? MAJESTIC_TUBE_DIR . $file : $template;
}
add_filter( 'template_include', 'majestic_tube_pseo_template_include', 20 );

function majestic_tube_browse_group_count( $group, $letter = '' ) {
	$groups = majestic_tube_browse_groups();
	return isset( $groups[ $group ] ) ? majestic_tube_seo_index_count( $groups[ $group ]['type'], $letter ) : 0;
}
function majestic_tube_browse_group_facets( $group, $per_page = 24, $letter = '', $offset = 0 ) {
	$groups = majestic_tube_browse_groups();
	return isset( $groups[ $group ] ) ? majestic_tube_seo_index_facets( $groups[ $group ]['type'], $per_page ?: 60, $offset, $letter ) : array();
}

function majestic_tube_facet_breadcrumbs( $items ) {
	if ( ! majestic_tube_is_facet_request() && ! majestic_tube_is_browse_request() ) { return $items; }
	$items = array( array( 'label' => __( 'Home', 'majestic-tube' ), 'url' => home_url( '/' ) ), array( 'label' => __( 'Browse', 'majestic-tube' ), 'url' => home_url( '/browse/' ) ) );
	if ( majestic_tube_is_browse_request() ) {
		$groups = majestic_tube_browse_groups();
		$group = sanitize_key( (string) get_query_var( 'mt_facet' ) );
		if ( isset( $groups[ $group ] ) ) { $items[] = array( 'label' => $groups[ $group ]['label'], 'url' => '' ); }
		else { $items[1]['url'] = ''; }
		return $items;
	}
	$facet = majestic_tube_current_facet();
	if ( ! $facet ) { return $items; }
	$link = get_term_link( $facet['term'] );
	if ( ! is_wp_error( $link ) ) { $items[] = array( 'label' => $facet['term']->name, 'url' => $link ); }
	$items[] = array( 'label' => majestic_tube_facet_heading( $facet ), 'url' => '' );
	return $items;
}
add_filter( 'majestic_tube_breadcrumb_items', 'majestic_tube_facet_breadcrumbs', 20 );

function majestic_tube_add_browse_to_menu() {
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	$menu_id = isset( $locations['majestic_tube_main_menu'] ) ? absint( $locations['majestic_tube_main_menu'] ) : 0;
	if ( ! $menu_id ) { return; }
	$url = home_url( '/browse/' );
	foreach ( (array) wp_get_nav_menu_items( $menu_id ) as $item ) {
		if ( untrailingslashit( $item->url ) === untrailingslashit( $url ) ) { return; }
	}
	wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => __( 'Browse', 'majestic-tube' ), 'menu-item-url' => $url, 'menu-item-status' => 'publish', 'menu-item-type' => 'custom' ) );
}
add_action( 'after_switch_theme', 'majestic_tube_add_browse_to_menu', 60 );
