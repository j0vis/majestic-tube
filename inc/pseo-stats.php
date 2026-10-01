<?php
/**
 * Honest catalogue statistics and bounded collection links.
 *
 * @package Majestic Tube
 * @version 2.2.26
 */
defined( 'ABSPATH' ) || exit;
const MAJESTIC_TUBE_PSEO_META = '_mt_pseo_';
const MAJESTIC_TUBE_PSEO_CACHE_TTL = 12 * HOUR_IN_SECONDS;

function majestic_tube_term_derived( $term_id ) {
	$term_id = absint( $term_id );
	$revision = (string) get_option( 'majestic_tube_seo_data_revision', 'initial' );
	$key = 'term_' . $term_id . '_' . $revision;
	$data = wp_cache_get( $key, MAJESTIC_TUBE_PSEO_CACHE_GROUP );
	if ( is_array( $data ) ) { return $data; }
	$stored = get_term_meta( $term_id, MAJESTIC_TUBE_PSEO_META . 'derived', true );
	if ( is_array( $stored ) && isset( $stored['revision'] ) && $stored['revision'] === $revision ) { $data = $stored['data']; }
	else {
		$data = apply_filters( 'majestic_tube_term_derived', null, $term_id );
		if ( ! is_array( $data ) ) { $data = majestic_tube_compute_term_derived( $term_id ); }
		$data = (array) apply_filters( 'majestic_tube_term_derived', $data, $term_id );
		if ( $term_id ) { update_term_meta( $term_id, MAJESTIC_TUBE_PSEO_META . 'derived', array( 'revision' => $revision, 'data' => $data ) ); }
	}
	wp_cache_set( $key, $data, MAJESTIC_TUBE_PSEO_CACHE_GROUP, MAJESTIC_TUBE_PSEO_CACHE_TTL );
	return $data;
}

/** Aggregate inside SQL; never transfer one PHP row per video. */
function majestic_tube_compute_term_derived( $term_id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT COUNT(*) AS total, SUM(v.duration) AS runtime, SUM(v.is_hd) AS hd, MIN(v.post_date) AS first, MAX(v.post_date) AS last FROM (
		 SELECT p.ID, p.post_date, MAX(CAST(pm.meta_value AS UNSIGNED)) AS duration, MAX(CASE WHEN hd.meta_value='on' THEN 1 ELSE 0 END) AS is_hd
		 FROM {$wpdb->posts} p INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id=p.ID
		 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
		 LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID AND pm.meta_key='duration'
		 LEFT JOIN {$wpdb->postmeta} hd ON hd.post_id=p.ID AND hd.meta_key='hd_video'
		 WHERE tt.term_id=%d AND p.post_status='publish' AND p.post_type='post' GROUP BY p.ID,p.post_date
		) v", $term_id ), ARRAY_A );
	return array( 'total' => (int) ( $row['total'] ?? 0 ), 'runtime' => (int) ( $row['runtime'] ?? 0 ), 'hd' => (int) ( $row['hd'] ?? 0 ), 'first' => $row['first'] ?? '', 'last' => $row['last'] ?? '', 'terms' => majestic_tube_term_top_cooccurrences( $term_id ) );
}
function majestic_tube_term_top_cooccurrences( $term_id ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT other_tt.term_id, COUNT(DISTINCT p.ID) AS shared FROM {$wpdb->term_relationships} mine
		 INNER JOIN {$wpdb->term_taxonomy} mine_tt ON mine_tt.term_taxonomy_id=mine.term_taxonomy_id
		 INNER JOIN {$wpdb->term_relationships} other ON other.object_id=mine.object_id AND other.term_taxonomy_id<>mine.term_taxonomy_id
		 INNER JOIN {$wpdb->term_taxonomy} other_tt ON other_tt.term_taxonomy_id=other.term_taxonomy_id
		 INNER JOIN {$wpdb->posts} p ON p.ID=mine.object_id
		 WHERE mine_tt.term_id=%d AND p.post_status='publish' AND p.post_type='post'
		 GROUP BY other_tt.term_id ORDER BY shared DESC LIMIT 24", absint( $term_id ) ) );
	$out = array();
	foreach ( (array) $rows as $row ) { $out[ (int) $row->term_id ] = (int) $row->shared; }
	return $out;
}
function majestic_tube_human_runtime( $seconds ) {
	$seconds = absint( $seconds );
	if ( ! $seconds ) { return ''; }
	$hours = (int) floor( $seconds / 3600 ); $minutes = (int) floor( ( $seconds % 3600 ) / 60 );
	if ( $hours ) { return sprintf( __( '%1$s h %2$s min', 'majestic-tube' ), number_format_i18n( $hours ), number_format_i18n( $minutes ) ); }
	return $minutes ? sprintf( __( '%s min', 'majestic-tube' ), number_format_i18n( $minutes ) ) : sprintf( __( '%s sec', 'majestic-tube' ), number_format_i18n( $seconds ) );
}
function majestic_tube_derived_labels( $derived ) {
	$span = ''; $years = 0;
	if ( ! empty( $derived['first'] ) && ! empty( $derived['last'] ) ) {
		$first = gmdate( 'Y', strtotime( $derived['first'] ) ); $last = gmdate( 'Y', strtotime( $derived['last'] ) );
		$years = (int) $last - (int) $first;
		$span = $first === $last ? sprintf( __( 'published in %s', 'majestic-tube' ), $first ) : sprintf( __( 'published %1$s to %2$s', 'majestic-tube' ), $first, $last );
	}
	$hd = ! empty( $derived['total'] ) && ! empty( $derived['hd'] ) ? sprintf( __( '%1$s%% of %2$s videos in HD', 'majestic-tube' ), number_format_i18n( round( $derived['hd'] / $derived['total'] * 100 ) ), number_format_i18n( $derived['total'] ) ) : '';
	return array( 'runtime_label' => majestic_tube_human_runtime( $derived['runtime'] ?? 0 ), 'hd_label' => $hd, 'span_label' => $span, 'span_years' => $years );
}
function majestic_tube_facet_related_terms( $term ) {
	$derived = majestic_tube_term_derived( $term->term_id );
	if ( empty( $derived['terms'] ) ) { return array(); }
	$terms = get_terms( array( 'taxonomy' => array_keys( majestic_tube_facet_taxonomies() ), 'include' => array_keys( $derived['terms'] ), 'hide_empty' => true, 'number' => 12, 'orderby' => 'include' ) );
	$out = array();
	if ( is_wp_error( $terms ) ) { return $out; }
	foreach ( $terms as $one ) {
		$url = get_term_link( $one );
		if ( ! is_wp_error( $url ) ) { $out[] = array( 'label' => $one->name, 'url' => $url, 'count' => (int) $derived['terms'][ $one->term_id ] ); }
	}
	return $out;
}
function majestic_tube_facet_siblings( $type, $term, $term2 = null, $band = '' ) {
	$out = array();
	foreach ( majestic_tube_seo_index_rows( $type, 9, 0, '', $term->term_id ) as $row ) {
		if ( (int) $row['term2_id'] === ( $term2 ? (int) $term2->term_id : 0 ) && $row['band'] === $band ) { continue; }
		$out[] = array( 'label' => $row['label'], 'url' => $row['url'] );
		if ( count( $out ) >= 8 ) { break; }
	}
	return (array) apply_filters( 'majestic_tube_facet_siblings', $out, $type, $term );
}
function majestic_tube_facet_attributes( $derived, $band = '' ) {
	$labels = majestic_tube_derived_labels( $derived ); $out = array();
	foreach ( array( 'runtime_label' => __( 'Total runtime', 'majestic-tube' ), 'hd_label' => __( 'Quality', 'majestic-tube' ), 'span_label' => __( 'Coverage', 'majestic-tube' ) ) as $key => $label ) {
		if ( $labels[ $key ] ) { $out[] = array( 'label' => $label, 'value' => $labels[ $key ] ); }
	}
	$bands = majestic_tube_length_bands();
	if ( $band && isset( $bands[ $band ] ) ) { $out[] = array( 'label' => __( 'Length band', 'majestic-tube' ), 'value' => $bands[ $band ]['label'] ); }
	return $out;
}
function majestic_tube_facet_summary( $type, $term, $term2 = null, $band = '', $stats = array() ) {
	$facet = array( 'type' => $type, 'term' => $term, 'term2' => $term2, 'band' => $band, 'count' => $stats['count'] ?? 0 );
	return majestic_tube_collection_apply( '', $facet, 'intro' );
}
function majestic_tube_facet_stats( $type, $term, $term2 = null, $band = '' ) {
	// Term-wide totals would misrepresent an intersection: omit those claims.
	$derived = $term2 || $band ? array() : majestic_tube_term_derived( $term->term_id );
	$labels = majestic_tube_derived_labels( $derived );
	$stats = array_merge( $labels, array( 'count' => majestic_tube_facet_count( $type, $term, $term2, $band ), 'runtime' => $derived['runtime'] ?? 0, 'related' => majestic_tube_facet_related_terms( $term ), 'siblings' => majestic_tube_facet_siblings( $type, $term, $term2, $band ), 'attributes' => majestic_tube_facet_attributes( $derived, $band ) ) );
	$stats['summary'] = majestic_tube_facet_summary( $type, $term, $term2, $band, $stats );
	return $stats;
}
