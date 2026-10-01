<?php
/**
 * Collection metadata from one validated page context.
 *
 * @package Majestic Tube
 * @version 2.2.26
 */
defined( 'ABSPATH' ) || exit;

function majestic_tube_facet_url( $type, $term, $term2 = null, $band = '' ) {
	if ( 'actor_actor' === $type && $term2 && isset( $term->term_id, $term2->term_id ) && $term->term_id > $term2->term_id ) { $swap = $term; $term = $term2; $term2 = $swap; }
	$slug = is_object( $term ) && isset( $term->slug ) ? $term->slug : sanitize_title( (string) $term );
	$second = $term2 && isset( $term2->slug ) ? rawurlencode( $term2->slug ) : '';
	$url = '';
	switch ( $type ) {
		case 'actor_category': $url = home_url( '/actor/' . rawurlencode( $slug ) . '/' . $second . '/' ); break;
		case 'actor_actor': $url = home_url( '/actor/' . rawurlencode( $slug ) . '/with/' . $second . '/' ); break;
		case 'actor_length': $url = home_url( '/actor/' . rawurlencode( $slug ) . '/length/' . sanitize_key( $band ) . '/' ); break;
		case 'category_tag': $url = home_url( '/category/' . rawurlencode( $slug ) . '/' . $second . '/' ); break;
		case 'studio':
		case 'series':
			$url = is_object( $term ) && isset( $term->taxonomy ) ? get_term_link( $term ) : home_url( '/' . $type . '/' . rawurlencode( $slug ) . '/' );
			if ( is_wp_error( $url ) ) { $url = ''; }
			break;
	}
	return (string) apply_filters( 'majestic_tube_facet_url', $url, $type );
}

function majestic_tube_facet_heading( $facet ) {
	if ( ! is_array( $facet ) || empty( $facet['term'] ) ) { return ''; }
	$name = $facet['term']->name;
	$second = ! empty( $facet['term2'] ) ? $facet['term2']->name : '';
	$bands = majestic_tube_length_bands();
	$heading = $name;
	if ( 'actor_category' === $facet['type'] && $second ) { $heading = sprintf( __( '%1$s in %2$s', 'majestic-tube' ), $name, $second ); }
	elseif ( 'actor_actor' === $facet['type'] && $second ) { $heading = sprintf( __( '%1$s with %2$s', 'majestic-tube' ), $name, $second ); }
	elseif ( 'category_tag' === $facet['type'] && $second ) { $heading = $name . ': ' . $second; }
	elseif ( 'actor_length' === $facet['type'] && isset( $bands[ $facet['band'] ] ) ) { $heading = $name . ': ' . $bands[ $facet['band'] ]['label']; }
	return (string) apply_filters( 'majestic_tube_facet_heading', $heading, $facet );
}
function majestic_tube_facet_title( $facet ) {
	if ( ! is_array( $facet ) ) { return ''; }
	$title = majestic_tube_facet_heading( $facet ) . ' | ' . get_bloginfo( 'name' );
	return (string) apply_filters( 'majestic_tube_facet_title', $title, $facet );
}
function majestic_tube_facet_description( $facet ) {
	if ( ! is_array( $facet ) ) { return ''; }
	$text = sprintf( __( 'Browse %1$s videos in %2$s on %3$s.', 'majestic-tube' ), number_format_i18n( $facet['count'] ), majestic_tube_facet_heading( $facet ), get_bloginfo( 'name' ) );
	return (string) apply_filters( 'majestic_tube_facet_description', $text, $facet );
}
function majestic_tube_facet_document_title( $title ) {
	if ( ! majestic_tube_seo_owned() || ! majestic_tube_is_facet_request() || majestic_tube_is_browse_request() || is_404() ) { return $title; }
	$facet = majestic_tube_current_facet();
	return $facet ? majestic_tube_facet_title( $facet ) : $title;
}
add_filter( 'pre_get_document_title', 'majestic_tube_facet_document_title', 20 );

function majestic_tube_facet_meta_description() {
	if ( ! majestic_tube_seo_owned() || ! majestic_tube_is_facet_request() || majestic_tube_is_browse_request() || is_404() ) { return; }
	$facet = majestic_tube_current_facet();
	if ( $facet ) { printf( '<meta name="description" content="%s">' . "\n", esc_attr( majestic_tube_facet_description( $facet ) ) ); }
}
add_action( 'wp_head', 'majestic_tube_facet_meta_description', 3 );

function majestic_tube_facet_canonical() {
	if ( ! majestic_tube_seo_owned() || is_404() ) { return; }
	$url = '';
	if ( majestic_tube_is_browse_request() ) {
		$group = sanitize_key( (string) get_query_var( 'mt_facet' ) );
		$groups = majestic_tube_browse_groups();
		$path = '/browse/';
		if ( isset( $groups[ $group ] ) ) {
			$path .= $group . '/';
			$letter = strtolower( (string) get_query_var( 'mt_facet_band' ) );
			if ( preg_match( '/^[a-z0-9]$/', $letter ) ) { $path .= $letter . '/'; }
		}
		$url = home_url( $path );
	} elseif ( majestic_tube_is_facet_request() ) {
		$facet = majestic_tube_current_facet();
		if ( $facet ) { $url = majestic_tube_facet_url( $facet['type'], $facet['term'], $facet['term2'], $facet['band'] ); }
	}
	if ( $url ) { printf( '<link rel="canonical" href="%s">' . "\n", esc_url( majestic_tube_seo_page_url( $url ) ) ); }
}
add_action( 'wp_head', 'majestic_tube_facet_canonical', 4 );

function majestic_tube_facet_robots( $robots ) {
	if ( ! majestic_tube_seo_owned() ) { return $robots; }
	if ( majestic_tube_is_browse_request() ) {
		$robots['noindex'] = true; unset( $robots['index'] );
		return $robots;
	}
	if ( ! majestic_tube_is_facet_request() ) { return $robots; }
	$facet = majestic_tube_current_facet();
	if ( ! $facet || ! $facet['indexable'] ) { $robots['noindex'] = true; unset( $robots['index'] ); }
	// Genuine pagination remains self-canonical, not universally noindexed.
	return (array) apply_filters( 'majestic_tube_facet_robots', $robots, $facet ?: array() );
}
add_filter( 'wp_robots', 'majestic_tube_facet_robots', 20 );

function majestic_tube_facet_schema() {
	if ( ! majestic_tube_is_facet_request() || majestic_tube_is_browse_request() || is_404() || ! majestic_tube_should_output_schema() ) { return; }
	$facet = majestic_tube_current_facet();
	if ( ! $facet ) { return; }
	$schema = array(
		'@context' => 'https://schema.org', '@type' => 'CollectionPage',
		'name' => majestic_tube_facet_heading( $facet ),
		'url' => majestic_tube_seo_page_url( majestic_tube_facet_url( $facet['type'], $facet['term'], $facet['term2'], $facet['band'] ) ),
		'description' => majestic_tube_facet_description( $facet ),
		'isPartOf' => array( '@type' => 'WebSite', 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ),
	);
	$json = wp_json_encode( apply_filters( 'majestic_tube_facet_schema', $schema, $facet ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE );
	if ( $json ) { echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; }
}
add_action( 'wp_head', 'majestic_tube_facet_schema', 6 );
