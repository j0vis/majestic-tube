<?php
/**
 * Rebuildable collection index. No public request enumerates the catalogue.
 *
 * @package Majestic Tube
 * @version 2.2.26
 */
defined( 'ABSPATH' ) || exit;

function majestic_tube_seo_index_table() {
	global $wpdb;
	return $wpdb->prefix . 'majestic_collections';
}

function majestic_tube_seo_index_install() {
	if ( '1' === get_option( 'majestic_tube_seo_index_schema' ) ) { return; }
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = majestic_tube_seo_index_table();
	dbDelta( "CREATE TABLE $table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		generation varchar(40) NOT NULL,
		facet_type varchar(32) NOT NULL,
		term_id bigint(20) unsigned NOT NULL,
		term2_id bigint(20) unsigned NOT NULL DEFAULT 0,
		band varchar(16) NOT NULL DEFAULT '',
		video_count bigint(20) unsigned NOT NULL DEFAULT 0,
		label varchar(255) NOT NULL,
		initial varchar(4) NOT NULL DEFAULT '',
		url text NOT NULL,
		lastmod datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY identity (generation,facet_type,term_id,term2_id,band),
		KEY listing (generation,facet_type,initial,id),
		KEY siblings (generation,facet_type,term_id,id)
	) " . $wpdb->get_charset_collate() . ';' );
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ) {
		update_option( 'majestic_tube_seo_index_schema', '1', false );
		majestic_tube_seo_index_dirty();
	}
}
add_action( 'admin_init', 'majestic_tube_seo_index_install' );

/** Version tokens are durable even on sites without persistent object cache. */
function majestic_tube_seo_index_dirty() {
	update_option( 'majestic_tube_seo_data_revision', wp_generate_uuid4(), false );
	if ( ! wp_next_scheduled( 'majestic_tube_seo_index_tick' ) ) {
		wp_schedule_single_event( time() + 30, 'majestic_tube_seo_index_tick' );
	}
}
function majestic_tube_seo_index_post_changed( $id ) {
	if ( 'post' === get_post_type( $id ) && ! wp_is_post_revision( $id ) ) { majestic_tube_seo_index_dirty(); }
}
function majestic_tube_seo_index_meta_changed( $meta_id, $id, $key ) {
	if ( in_array( $key, array( 'duration', 'hd_video' ), true ) ) { majestic_tube_seo_index_post_changed( $id ); }
}
function majestic_tube_seo_index_terms_changed( $id, $terms, $tt_ids, $taxonomy ) {
	if ( in_array( $taxonomy, array( 'actors', 'category', 'post_tag', 'studio', 'series' ), true ) ) { majestic_tube_seo_index_post_changed( $id ); }
}
add_action( 'save_post_post', 'majestic_tube_seo_index_post_changed' );
add_action( 'before_delete_post', 'majestic_tube_seo_index_post_changed' );
function majestic_tube_seo_index_status_changed( $new_status, $old_status, $post ) {
	if ( $new_status !== $old_status && 'post' === $post->post_type ) { majestic_tube_seo_index_dirty(); }
}
add_action( 'transition_post_status', 'majestic_tube_seo_index_status_changed', 10, 3 );
add_action( 'set_object_terms', 'majestic_tube_seo_index_terms_changed', 10, 4 );
add_action( 'added_post_meta', 'majestic_tube_seo_index_meta_changed', 10, 3 );
add_action( 'updated_post_meta', 'majestic_tube_seo_index_meta_changed', 10, 3 );
add_action( 'deleted_post_meta', 'majestic_tube_seo_index_meta_changed', 10, 3 );
add_action( 'created_term', 'majestic_tube_seo_index_dirty' );
add_action( 'edited_term', 'majestic_tube_seo_index_dirty' );
add_action( 'delete_term', 'majestic_tube_seo_index_dirty' );
function majestic_tube_seo_index_reconcile() {
	if ( ! wp_next_scheduled( 'majestic_tube_seo_index_reconcile' ) ) { wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'majestic_tube_seo_index_reconcile' ); }
}
add_action( 'admin_init', 'majestic_tube_seo_index_reconcile' );
add_action( 'majestic_tube_seo_index_reconcile', 'majestic_tube_seo_index_dirty' );
function majestic_tube_seo_index_unschedule() {
	wp_clear_scheduled_hook( 'majestic_tube_seo_index_tick' );
	wp_clear_scheduled_hook( 'majestic_tube_seo_index_reconcile' );
}
add_action( 'switch_theme', 'majestic_tube_seo_index_unschedule' );

/** Grouped co-occurrence, not a Cartesian product or a query per pair. */
function majestic_tube_seo_index_candidates( $term, $type ) {
	global $wpdb;
	$rows = array();
	if ( in_array( $type, array( 'studio', 'series' ), true ) ) {
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->term_relationships} r ON r.object_id=p.ID WHERE r.term_taxonomy_id=%d AND p.post_status='publish' AND p.post_type='post'", $term->term_taxonomy_id ) );
		return array( array( 'secondary' => 0, 'band' => '', 'count' => $count ) );
	}
	if ( 'actor_length' === $type ) {
		foreach ( majestic_tube_length_bands() as $band => $range ) {
			// EXISTS avoids duplicate meta rows inflating the count.
			$sql = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->term_relationships} r ON r.object_id=p.ID WHERE r.term_taxonomy_id=%d AND p.post_status='publish' AND p.post_type='post' AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id=p.ID AND m.meta_key='duration' AND CAST(m.meta_value AS SIGNED)>=%d";
			$params = array( $term->term_taxonomy_id, $range['min'] );
			if ( $range['max'] > 0 ) { $sql .= ' AND CAST(m.meta_value AS SIGNED)<=%d'; $params[] = $range['max']; }
			$rows[] = array( 'secondary' => 0, 'band' => $band, 'count' => (int) $wpdb->get_var( $wpdb->prepare( $sql . ')', $params ) ) );
		}
		return $rows;
	}
	$taxonomy = 'actor_actor' === $type ? 'actors' : ( 'actor_category' === $type ? 'category' : 'post_tag' );
	$sql = "SELECT tt.term_id AS secondary, COUNT(DISTINCT p.ID) AS count FROM {$wpdb->posts} p INNER JOIN {$wpdb->term_relationships} mine ON mine.object_id=p.ID INNER JOIN {$wpdb->term_relationships} other ON other.object_id=p.ID INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=other.term_taxonomy_id WHERE mine.term_taxonomy_id=%d AND tt.taxonomy=%s AND p.post_type='post' AND p.post_status='publish'";
	$params = array( $term->term_taxonomy_id, $taxonomy );
	if ( 'actor_actor' === $type ) { $sql .= ' AND tt.term_id>%d'; $params[] = $term->term_id; }
	$sql .= ' GROUP BY tt.term_id';
	$results = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	foreach ( (array) $results as $row ) { $row['band'] = ''; $rows[] = $row; }
	return $rows;
}

/** Each batch visits 10 primary terms, with a keyset cursor and DB-backed lock. */
function majestic_tube_seo_index_tick() {
	if ( '1' !== get_option( 'majestic_tube_seo_index_schema' ) || get_option( 'majestic_tube_seo_index_error', '' ) ) { return; }
	$locked = (int) get_option( 'majestic_tube_seo_index_lock', 0 );
	if ( $locked && $locked < time() - 300 ) { delete_option( 'majestic_tube_seo_index_lock' ); }
	$token = time();
	if ( ! add_option( 'majestic_tube_seo_index_lock', $token, '', false ) ) { return; }
	global $wpdb;
	try {
		$revision = (string) get_option( 'majestic_tube_seo_data_revision', 'initial' );
		$active = (array) get_option( 'majestic_tube_seo_index_active', array() );
		$job = (array) get_option( 'majestic_tube_seo_index_job', array() );
		if ( ! $job && isset( $active['revision'] ) && $active['revision'] === $revision ) { return; }
		if ( ! $job ) { $job = array( 'generation' => wp_generate_uuid4(), 'revision' => $revision, 'cursor' => 0, 'started' => time(), 'processed' => 0 ); }
		// Data may change during a build; finish safely, then queue a fresh generation.
		$terms = $wpdb->get_results( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_id>%d AND taxonomy IN ('actors','category','studio','series') ORDER BY term_id ASC LIMIT 10", $job['cursor'] ) );
		if ( $wpdb->last_error ) { throw new RuntimeException( 'Could not read collection data.' ); }
		$types = majestic_tube_facet_types();
		$table = majestic_tube_seo_index_table();
		foreach ( $terms as $record ) {
			$term = get_term( (int) $record->term_id );
			if ( ! $term || is_wp_error( $term ) ) { $job['cursor'] = $record->term_id; continue; }
			$selected = 'actors' === $term->taxonomy ? array( 'actor_category', 'actor_actor', 'actor_length' ) : array( 'category' === $term->taxonomy ? 'category_tag' : $term->taxonomy );
			foreach ( $selected as $type ) {
				if ( ! isset( $types[ $type ] ) ) { continue; }
				$rows = majestic_tube_seo_index_candidates( $term, $type );
				if ( $wpdb->last_error ) { throw new RuntimeException( 'Could not count collection videos.' ); }
				foreach ( $rows as $row ) {
					$count = (int) $row['count'];
					$second = ! empty( $row['secondary'] ) ? get_term( (int) $row['secondary'] ) : null;
					if ( is_wp_error( $second ) || ( ! empty( $row['secondary'] ) && ! $second ) ) { continue; }
					$count = (int) apply_filters( 'majestic_tube_facet_count', $count, $type, $term, $second, $row['band'] );
					if ( ! majestic_tube_facet_indexable( $type, $count, $term, $second, $row['band'] ) ) { continue; }
					// The same eligibility policy controls rendered pages and discovery.
					$facet = array( 'type' => $type, 'term' => $term, 'term2' => $second, 'band' => $row['band'], 'count' => $count );
					$label = majestic_tube_facet_heading( $facet );
					if ( strlen( $label ) > 255 ) { $label = function_exists( 'mb_strcut' ) ? mb_strcut( $label, 0, 255, 'UTF-8' ) : iconv( 'UTF-8', 'UTF-8//IGNORE', substr( $label, 0, 255 ) ); }
					$inserted = $wpdb->replace( $table, array( 'generation' => $job['generation'], 'facet_type' => $type, 'term_id' => $term->term_id, 'term2_id' => $second ? $second->term_id : 0, 'band' => $row['band'], 'video_count' => $count, 'label' => $label, 'initial' => strtoupper( substr( remove_accents( $label ), 0, 1 ) ), 'url' => majestic_tube_facet_url( $type, $term, $second, $row['band'] ), 'lastmod' => current_time( 'mysql', true ) ) );
					if ( false === $inserted ) { throw new RuntimeException( 'Could not write the collection index.' ); }
				}
			}
			$job['cursor'] = (int) $term->term_id;
			$job['processed']++;
			update_option( 'majestic_tube_seo_index_job', $job, false );
		}
		if ( count( $terms ) < 10 && $job['revision'] !== get_option( 'majestic_tube_seo_data_revision', 'initial' ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE generation=%s", $job['generation'] ) );
			delete_option( 'majestic_tube_seo_index_job' );
			if ( ! wp_next_scheduled( 'majestic_tube_seo_index_tick' ) ) { wp_schedule_single_event( time() + 10, 'majestic_tube_seo_index_tick' ); }
			return;
		}
		if ( count( $terms ) < 10 ) {
			update_option( 'majestic_tube_seo_index_active', array( 'generation' => $job['generation'], 'revision' => $job['revision'], 'updated' => time(), 'processed' => $job['processed'] ), false );
			delete_option( 'majestic_tube_seo_index_job' );
			delete_option( 'majestic_tube_seo_index_error' );
			// Keep the immediately previous generation until the next build,
			// so concurrent readers holding that token never see a deleted index.
			$previous = isset( $active['generation'] ) ? $active['generation'] : '';
			$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE generation<>%s AND generation<>%s", $job['generation'], $previous ) );
		} else { update_option( 'majestic_tube_seo_index_job', $job, false ); }
		if ( count( $terms ) === 10 || $job['revision'] !== get_option( 'majestic_tube_seo_data_revision', 'initial' ) ) {
			if ( ! wp_next_scheduled( 'majestic_tube_seo_index_tick' ) ) { wp_schedule_single_event( time() + 10, 'majestic_tube_seo_index_tick' ); }
		}
	} catch ( Throwable $error ) {
		update_option( 'majestic_tube_seo_index_error', $error->getMessage(), false );
		if ( isset( $job ) ) { update_option( 'majestic_tube_seo_index_job', $job, false ); }
		// Keep the old active generation; an administrator can retry explicitly.
	} finally {
		if ( (int) get_option( 'majestic_tube_seo_index_lock' ) === $token ) { delete_option( 'majestic_tube_seo_index_lock' ); }
	}
}
add_action( 'majestic_tube_seo_index_tick', 'majestic_tube_seo_index_tick' );

/** Bounded SQL reads; no term hydration is needed for sitemap output. */
function majestic_tube_seo_index_where( $type = '', $letter = '' ) {
	global $wpdb;
	$active = (array) get_option( 'majestic_tube_seo_index_active', array() );
	if ( empty( $active['generation'] ) ) { return '1=0'; }
	$enabled = array();
	foreach ( majestic_tube_facet_types() as $key => $definition ) { if ( majestic_tube_collection_enabled( $key ) ) { $enabled[] = $key; } }
	if ( 'generated' === $type ) { $enabled = array_values( array_diff( $enabled, array( 'studio', 'series' ) ) ); $type = ''; }
	if ( ! $enabled || ( $type && ! in_array( $type, $enabled, true ) ) ) { return '1=0'; }
	$where = $wpdb->prepare( 'generation=%s', $active['generation'] );
	$where .= $wpdb->prepare( ' AND facet_type IN (' . implode( ',', array_fill( 0, count( $enabled ), '%s' ) ) . ')', $enabled );
	// Current floors also apply immediately to an older active generation.
	$floors = array();
	foreach ( majestic_tube_facet_types() as $key => $definition ) {
		if ( in_array( $key, $enabled, true ) ) { $floors[] = $wpdb->prepare( '(facet_type=%s AND video_count>=%d)', $key, max( 1, (int) $definition['min'] ) ); }
	}
	$where .= ' AND (' . implode( ' OR ', $floors ) . ')';
	if ( $type ) { $where .= $wpdb->prepare( ' AND facet_type=%s', $type ); }
	if ( $letter ) { $where .= $wpdb->prepare( ' AND initial=%s', strtoupper( $letter ) ); }
	return $where;
}
function majestic_tube_seo_index_count( $type = '', $letter = '' ) {
	if ( '1' !== get_option( 'majestic_tube_seo_index_schema' ) ) { return 0; }
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . majestic_tube_seo_index_table() . ' WHERE ' . majestic_tube_seo_index_where( $type, $letter ) );
}
function majestic_tube_seo_index_rows( $type = '', $limit = 60, $offset = 0, $letter = '', $term_id = 0 ) {
	if ( '1' !== get_option( 'majestic_tube_seo_index_schema' ) ) { return array(); }
	global $wpdb;
	$limit = max( 1, min( 2000, (int) $limit ) );
	$where = majestic_tube_seo_index_where( $type, $letter );
	if ( $term_id ) { $where .= $wpdb->prepare( ' AND term_id=%d', $term_id ); }
	$sql = 'SELECT * FROM ' . majestic_tube_seo_index_table() . ' WHERE ' . $where . $wpdb->prepare( ' ORDER BY id ASC LIMIT %d OFFSET %d', $limit, max( 0, (int) $offset ) );
	return (array) $wpdb->get_results( $sql, ARRAY_A );
}
function majestic_tube_seo_index_facets( $type, $limit = 60, $offset = 0, $letter = '' ) {
	$out = array();
	$rows = majestic_tube_seo_index_rows( $type, $limit, $offset, $letter );
	$ids = array();
	foreach ( $rows as $row ) { $ids[] = (int) $row['term_id']; if ( $row['term2_id'] ) { $ids[] = (int) $row['term2_id']; } }
	if ( $ids ) { get_terms( array( 'taxonomy' => array_keys( majestic_tube_facet_taxonomies() ), 'include' => array_unique( $ids ), 'hide_empty' => false, 'update_term_meta_cache' => false ) ); }
	foreach ( $rows as $row ) {
		$term = get_term( (int) $row['term_id'] );
		$term2 = $row['term2_id'] ? get_term( (int) $row['term2_id'] ) : null;
		if ( ! $term || is_wp_error( $term ) || is_wp_error( $term2 ) || ( $row['term2_id'] && ! $term2 ) ) { continue; }
		$out[] = array( 'type' => $row['facet_type'], 'term' => $term, 'term2' => $term2, 'band' => $row['band'], 'count' => (int) $row['video_count'], 'url' => $row['url'], 'label' => $row['label'] );
	}
	return $out;
}
