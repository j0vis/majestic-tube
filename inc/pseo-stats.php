<?php
/**
 * Derived facet data: the values that make a generated page specific.
 *
 * A page is thin when the same sentences appear behind different names. The
 * only cure is real data, so this module computes it once per term and stores
 * it: total runtime, the span of years the term has been published in, how
 * much of its content is HD, which other terms it most often co-occurs with,
 * and the sibling facets that should hang off it as internal links.
 *
 * All of it is derived from post meta and term relationships that already
 * exist. Nothing here is fetched from outside WordPress, and nothing here is
 * invented: a term with no posts reports nothing rather than a zero that reads
 * like a measurement.
 *
 * The values are stored in term meta and cached in the object cache. Storage
 * is invalidated through WordPress's own `last_changed` marker for terms, so a
 * bulk import or a CLI run that never touches our hooks still lands on a fresh
 * key - the same pattern the term directory in inc/pagination.php uses, and
 * for the same reason: hooks alone miss the paths that matter.
 *
 * @package Majestic Tube
 * @version 2.2.23
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prefix for the term meta this module writes.
 */
const MAJESTIC_TUBE_PSEO_META = '_mt_pseo_';

/**
 * How long a derived-stat entry is kept in the object cache, as a backstop.
 */
const MAJESTIC_TUBE_PSEO_CACHE_TTL = 12 * HOUR_IN_SECONDS;

/**
 * One term's derived data, cached.
 *
 * @param int $term_id Term ID.
 * @return array{runtime:int, hd:int, total:int, first:string, last:string, terms:array}
 */
function majestic_tube_term_derived( $term_id ) {
	$term_id = absint( $term_id );

	if ( ! $term_id ) {
		return array(
			'runtime' => 0,
			'hd'      => 0,
			'total'   => 0,
			'first'   => '',
			'last'    => '',
			'terms'   => array(),
		);
	}

	$last_changed = (string) wp_cache_get( 'last_changed', 'terms' );
	$key          = 'term_' . $term_id . '_' . preg_replace( '/[^A-Za-z0-9_.:-]/', '', $last_changed );

	$cached = wp_cache_get( $key, MAJESTIC_TUBE_PSEO_CACHE_GROUP );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$stored = get_term_meta( $term_id, MAJESTIC_TUBE_PSEO_META . 'derived', true );

	if ( is_array( $stored ) && isset( $stored['last_changed'] ) && $stored['last_changed'] === $last_changed ) {
		$data = isset( $stored['data'] ) && is_array( $stored['data'] ) ? $stored['data'] : array();
	} else {
		/*
		 * The filter runs BEFORE the query, not after it. A site that
		 * sources this data elsewhere - a legacy importer's tables, an
		 * external API, a nightly export - must be able to answer the
		 * filter without this function first running the aggregate query it
		 * was trying to avoid. Filtering after the compute would make the
		 * seam useless for exactly the case it exists for.
		 */
		$data = apply_filters( 'majestic_tube_term_derived', null, $term_id );

		if ( ! is_array( $data ) ) {
			$data = majestic_tube_compute_term_derived( $term_id );
		}

		update_term_meta(
			$term_id,
			MAJESTIC_TUBE_PSEO_META . 'derived',
			array(
				'last_changed' => $last_changed,
				'data'         => $data,
			)
		);
	}

	wp_cache_set( $key, $data, MAJESTIC_TUBE_PSEO_CACHE_GROUP, MAJESTIC_TUBE_PSEO_CACHE_TTL );

	/**
	 * Filters a term's derived data a second time, after it is resolved.
	 *
	 * The pre-compute hook above short-circuits the query; this one adjusts
	 * data that was computed here, so a site can correct a value without
	 * having to take the query over entirely.
	 *
	 * @param array $data    Derived data.
	 * @param int   $term_id Term ID.
	 */
	return (array) apply_filters( 'majestic_tube_term_derived', $data, $term_id );
}

/**
 * Compute one term's runtime, HD share, date span and most co-occurring terms.
 *
 * One query, because the obvious implementation - load the posts, then loop
 * them - costs a meta read per video and turns a 2,000-post term into a few
 * thousand queries on a page load that must stay fast.
 *
 * @param int $term_id Term ID.
 * @return array{runtime:int, hd:int, total:int, first:string, last:string, terms:array}
 */
function majestic_tube_compute_term_derived( $term_id ) {
	global $wpdb;

	$empty = array(
		'runtime' => 0,
		'hd'      => 0,
		'total'   => 0,
		'first'   => '',
		'last'    => '',
		'terms'   => array(),
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- aggregates a term's whole corpus in one read; get_posts() would issue a meta query per post.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT p.post_date, MAX(CAST(pm.meta_value AS UNSIGNED)) AS duration, MAX(CASE WHEN hd.meta_value = 'on' THEN 1 ELSE 0 END) AS is_hd
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
			 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			 LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'duration'
			 LEFT JOIN {$wpdb->postmeta} hd ON hd.post_id = p.ID AND hd.meta_key = 'hd_video'
			 WHERE tt.term_id = %d AND p.post_status = 'publish' AND p.post_type = 'post'
			 GROUP BY p.ID",
			$term_id
		)
	);

	if ( ! is_array( $rows ) || ! $rows ) {
		return $empty;
	}

	$runtime   = 0;
	$hd        = 0;
	$first     = '';
	$last      = '';

	foreach ( $rows as $row ) {
		$runtime += (int) $row->duration;
		$hd      += (int) $row->is_hd;

		$date = (string) $row->post_date;

		if ( '' === $first || $date < $first ) {
			$first = $date;
		}

		if ( '' === $last || $date > $last ) {
			$last = $date;
		}
	}

	return array(
		'runtime' => $runtime,
		'hd'      => $hd,
		'total'   => count( $rows ),
		'first'   => $first,
		'last'    => $last,
		'terms'   => majestic_tube_term_top_cooccurrences( $term_id ),
	);
}

/**
 * The terms this one most often appears with.
 *
 * Shared terms are the best available proxy for "what should this page link
 * to next": they are derived from real co-occurrence rather than from a
 * hand-written list, so they stay correct as the catalogue changes.
 *
 * @param int $term_id Term ID.
 * @return array<int, int> Taxonomy-agnostic term IDs keyed by shared post count.
 */
function majestic_tube_term_top_cooccurrences( $term_id ) {
	global $wpdb;

	$term_id = absint( $term_id );

	if ( ! $term_id ) {
		return array();
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one grouped read; the metadata API cannot express a join.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT other.term_id, COUNT(DISTINCT other.object_id) AS shared
			 FROM {$wpdb->term_relationships} mine
			 INNER JOIN {$wpdb->term_taxonomy} mine_tt ON mine_tt.term_taxonomy_id = mine.term_taxonomy_id
			 INNER JOIN {$wpdb->term_relationships} other ON other.object_id = mine.object_id AND other.term_taxonomy_id != mine.term_taxonomy_id
			 INNER JOIN {$wpdb->term_taxonomy} other_tt ON other_tt.term_taxonomy_id = other.term_taxonomy_id
			 WHERE mine_tt.term_id = %d AND other_tt.count > 0
			 GROUP BY other.term_id
			 ORDER BY shared DESC
			 LIMIT 24",
			$term_id
		)
	);

	if ( ! is_array( $rows ) ) {
		return array();
	}

	$out = array();

	foreach ( $rows as $row ) {
		$out[ (int) $row->term_id ] = (int) $row->shared;
	}

	return $out;
}

/**
 * Human labels for a term's derived numbers.
 *
 * @param array $derived Output of majestic_tube_term_derived().
 * @return array{runtime_label:string, hd_label:string, span_label:string, span_years:int}
 */
function majestic_tube_derived_labels( $derived ) {
	$derived = is_array( $derived ) ? $derived : array();
	$total   = isset( $derived['total'] ) ? (int) $derived['total'] : 0;
	$runtime = isset( $derived['runtime'] ) ? (int) $derived['runtime'] : 0;
	$hd      = isset( $derived['hd'] ) ? (int) $derived['hd'] : 0;
	$first   = isset( $derived['first'] ) ? (string) $derived['first'] : '';
	$last    = isset( $derived['last'] ) ? (string) $derived['last'] : '';

	$hd_label = '';

	if ( $total && $hd ) {
		$hd_label = sprintf(
			/* translators: 1: percentage of the term's videos in HD, 2: number of videos. */
			__( '%1$s%% of %2$s videos in HD', 'majestic-tube' ),
			number_format_i18n( (int) round( ( $hd / $total ) * 100 ) ),
			number_format_i18n( $total )
		);
	}

	$span_label = '';
	$span_years = 0;

	if ( $first && $last ) {
		$first_year = (int) gmdate( 'Y', strtotime( $first ) );
		$last_year  = (int) gmdate( 'Y', strtotime( $last ) );
		$span_years = max( 0, $last_year - $first_year );

		$span_label = ( $span_years > 0 )
			/* translators: 1: first year, 2: last year. */
			? sprintf( __( 'published %1$s to %2$s', 'majestic-tube' ), number_format_i18n( $first_year ), number_format_i18n( $last_year ) )
			/* translators: %s: year. */
			: sprintf( __( 'published in %s', 'majestic-tube' ), number_format_i18n( $first_year ) );
	}

	return array(
		'runtime_label' => majestic_tube_human_runtime( $runtime ),
		'hd_label'      => $hd_label,
		'span_label'    => $span_label,
		'span_years'    => $span_years,
	);
}

/**
 * Format a runtime in seconds the way a reader thinks about it.
 *
 * @param int $seconds Total seconds.
 * @return string
 */
function majestic_tube_human_runtime( $seconds ) {
	$seconds = absint( $seconds );

	if ( ! $seconds ) {
		return '';
	}

	$hours   = (int) floor( $seconds / 3600 );
	$minutes = (int) floor( ( $seconds % 3600 ) / 60 );

	if ( $hours ) {
		/* translators: 1: hours, 2: minutes. */
		return sprintf( __( '%1$s h %2$s min', 'majestic-tube' ), number_format_i18n( $hours ), number_format_i18n( $minutes ) );
	}

	if ( $minutes ) {
		/* translators: %s: minutes. */
		return sprintf( __( '%s min', 'majestic-tube' ), number_format_i18n( $minutes ) );
	}

	/* translators: %s: number of seconds. */
	return sprintf( __( '%s sec', 'majestic-tube' ), number_format_i18n( $seconds ) );
}

/**
 * Everything a facet template needs to describe itself, in one call.
 *
 * The template never reaches past this to gather data itself. That is what
 * keeps the word budget, the headline and the rendered page describing the
 * same thing: they all read one array, computed once.
 *
 * @param string       $type Facet type key.
 * @param WP_Term      $term Primary term.
 * @param WP_Term|null $term2 Secondary term.
 * @param string       $band Duration band key.
 * @return array
 */
function majestic_tube_facet_stats( $type, $term, $term2 = null, $band = '' ) {
	$derived = majestic_tube_term_derived( $term->term_id );
	$labels  = majestic_tube_derived_labels( $derived );

	$siblings  = majestic_tube_facet_siblings( $type, $term, $term2, $band );
	$related   = majestic_tube_facet_related_terms( $term );
	$attributes = majestic_tube_facet_attributes( $derived, $band );

	$stats = array(
		'count'         => 0,
		'runtime_label' => $labels['runtime_label'],
		'runtime'       => isset( $derived['runtime'] ) ? (int) $derived['runtime'] : 0,
		'hd_label'      => $labels['hd_label'],
		'span_label'    => $labels['span_label'],
		'span_years'    => $labels['span_years'],
		'related'       => $related,
		'siblings'      => $siblings,
		'attributes'    => $attributes,
		'summary'       => '',
	);

	$stats['count']   = majestic_tube_facet_count( $type, $term, $term2, $band );
	$stats['summary'] = majestic_tube_facet_summary( $type, $term, $term2, $band, $stats );

	return $stats;
}

/**
 * The terms this facet should link sideways to, as label/URL pairs.
 *
 * @param WP_Term $term Primary term.
 * @return array<int, array{label:string,url:string,count:int}>
 */
function majestic_tube_facet_related_terms( $term ) {
	$derived = majestic_tube_term_derived( $term->term_id );
	$out     = array();

	if ( empty( $derived['terms'] ) || ! is_array( $derived['terms'] ) ) {
		return $out;
	}

	$ids = array_keys( $derived['terms'] );
	$ids = array_map( 'absint', $ids );

	$terms = get_terms(
		array(
			'taxonomy'   => array( 'category', 'post_tag', 'actors', 'studio', 'series' ),
			'include'    => $ids,
			'orderby'    => 'include',
			'hide_empty' => true,
		)
	);

	if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
		return $out;
	}

	$by_id = array();

	foreach ( $terms as $one ) {
		$by_id[ (int) $one->term_id ] = $one;
	}

	foreach ( $derived['terms'] as $id => $shared ) {
		$id = (int) $id;

		if ( ! isset( $by_id[ $id ] ) ) {
			continue;
		}

		$link = get_term_link( $by_id[ $id ] );

		if ( is_wp_error( $link ) ) {
			continue;
		}

		$out[] = array(
			'label' => $by_id[ $id ]->name,
			'url'   => $link,
			'count' => (int) $shared,
		);
	}

	return $out;
}

/**
 * Sibling facets for this one, as label/URL pairs.
 *
 * Siblings are what stop a generated page being a leaf: an actor/category page
 * links to the same actor's other categories, and to the pairings that share
 * its second term, so PageRank arrives from and returns to the hubs rather
 * than dead-ending on the facet.
 *
 * @param string       $type Facet type key.
 * @param WP_Term      $term Primary term.
 * @param WP_Term|null $term2 Secondary term.
 * @param string       $band Duration band key.
 * @return array<int, array{label:string,url:string}>
 */
function majestic_tube_facet_siblings( $type, $term, $term2 = null, $band = '' ) {
	$out = array();
	$taxonomies = majestic_tube_facet_taxonomies();

	if ( 'actor_category' === $type && $term2 ) {
		// The actor's other categories.
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => true,
				'number'     => 8,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'exclude'    => array( (int) $term2->term_id ),
			)
		);

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $one ) {
				$out[] = array(
					'label' => $one->name,
					'url'   => majestic_tube_facet_url( $type, $term, $one ),
				);
			}
		}
	} elseif ( 'actor_actor' === $type && $term2 ) {
		// Pairings that share either participant, so the pairing cluster is
		// internally linked rather than a set of isolated pages.
		$derived = majestic_tube_term_derived( $term->term_id );
		$ids     = isset( $derived['terms'] ) ? array_keys( $derived['terms'] ) : array();

		foreach ( array_slice( $ids, 0, 6 ) as $id ) {
			$other = get_term( absint( $id ), 'actors' );

			if ( ! $other || is_wp_error( $other ) || (int) $other->term_id === (int) $term->term_id ) {
				continue;
			}

			$out[] = array(
				'label' => sprintf(
					/* translators: 1: first actor name, 2: second actor name. */
					__( '%1$s with %2$s', 'majestic-tube' ),
					$term->name,
					$other->name
				),
				'url'   => majestic_tube_facet_url( $type, $term, $other ),
			);
		}
	} elseif ( 'actor_length' === $type ) {
		$bands = majestic_tube_length_bands();

		foreach ( $bands as $key => $definition ) {
			if ( $key === $band ) {
				continue;
			}

			$out[] = array(
				'label' => $definition['label'],
				'url'   => majestic_tube_facet_url( $type, $term, null, $key ),
			);
		}
	} elseif ( 'category_tag' === $type && $term2 ) {
		$tags = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => true,
				'number'     => 8,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'exclude'    => array( (int) $term2->term_id ),
			)
		);

		if ( ! is_wp_error( $tags ) ) {
			foreach ( $tags as $one ) {
				$out[] = array(
					'label' => $one->name,
					'url'   => majestic_tube_facet_url( $type, $term, $one ),
				);
			}
		}
	}

	/**
	 * Filter a facet's sibling links.
	 *
	 * @param array  $out   Sibling links.
	 * @param string $type  Facet type key.
	 * @param WP_Term $term Primary term.
	 */
	return (array) apply_filters( 'majestic_tube_facet_siblings', $out, $type, $term );
}

/**
 * The short attribute list printed in the stats bar.
 *
 * Only real values. A facet with no runtime reports no runtime: an empty
 * attribute is honest, and a fabricated zero would read as a measurement the
 * site never made.
 *
 * @param array  $derived Term derived data.
 * @param string $band Duration band key, or an empty string.
 * @return array<int, array{label:string,value:string}>
 */
function majestic_tube_facet_attributes( $derived, $band = '' ) {
	$attributes = array();
	$labels     = majestic_tube_derived_labels( $derived );

	if ( ! empty( $labels['runtime_label'] ) ) {
		$attributes[] = array(
			'label' => __( 'Total runtime', 'majestic-tube' ),
			'value' => $labels['runtime_label'],
		);
	}

	if ( ! empty( $labels['hd_label'] ) ) {
		$attributes[] = array(
			'label' => __( 'Quality', 'majestic-tube' ),
			'value' => $labels['hd_label'],
		);
	}

	if ( ! empty( $labels['span_label'] ) ) {
		$attributes[] = array(
			'label' => __( 'Coverage', 'majestic-tube' ),
			'value' => $labels['span_label'],
		);
	}

	if ( $band ) {
		$bands = majestic_tube_length_bands();

		if ( isset( $bands[ $band ] ) ) {
			$attributes[] = array(
				'label' => __( 'Length band', 'majestic-tube' ),
				'value' => $bands[ $band ]['label'],
			);
		}
	}

	return $attributes;
}

/**
 * The one- or two-sentence summary that opens a facet page.
 *
 * Built from measured values only, in a fixed shape, so it reads like a
 * sentence a person wrote rather than a filled-in slot - and because every
 * value in it is real, two facets of the same type never produce the same
 * text. When there is not enough to say, the caller gets an empty string and
 * the template omits the paragraph rather than padding it.
 *
 * @param string       $type Facet type key.
 * @param WP_Term      $term Primary term.
 * @param WP_Term|null $term2 Secondary term.
 * @param string       $band Duration band key.
 * @param array        $stats Facet stats.
 * @return string Plain text, ready to escape.
 */
function majestic_tube_facet_summary( $type, $term, $term2 = null, $band = '', $stats = array() ) {
	$count   = isset( $stats['count'] ) ? (int) $stats['count'] : 0;
	$runtime = isset( $stats['runtime_label'] ) ? (string) $stats['runtime_label'] : '';
	$related = isset( $stats['related'] ) && is_array( $stats['related'] ) ? $stats['related'] : array();

	if ( ! $count ) {
		return '';
	}

	$name  = $term->name;
	$parts = array();

	switch ( $type ) {
		case 'actor_category':
			$parts[] = ( $term2 )
				/* translators: 1: video count, 2: actor name, 3: category name. */
				? sprintf( _n( '%1$s video of %2$s in %3$s.', '%1$s videos of %2$s in %3$s.', $count, 'majestic-tube' ), number_format_i18n( $count ), $name, $term2->name )
				/* translators: 1: video count, 2: category name. */
				: sprintf( _n( '%1$s video filed under %2$s.', '%1$s videos filed under %2$s.', $count, 'majestic-tube' ), number_format_i18n( $count ), $name );
			break;

		case 'actor_actor':
			$parts[] = ( $term2 )
				/* translators: 1: video count, 2: first actor, 3: second actor. */
				? sprintf( _n( '%1$s video pairs %2$s with %3$s.', '%1$s videos pair %2$s with %3$s.', $count, 'majestic-tube' ), number_format_i18n( $count ), $name, $term2->name )
				: sprintf( _n( '%1$s video pairs two performers.', '%1$s videos pair two performers.', $count, 'majestic-tube' ), number_format_i18n( $count ) );
			break;

		case 'actor_length':
			$bands = majestic_tube_length_bands();
			$band_label = isset( $bands[ $band ] ) ? $bands[ $band ]['label'] : '';

			$parts[] = ( $band_label )
				/* translators: 1: video count, 2: actor name, 3: duration band. */
				? sprintf( _n( '%1$s %2$s video in the %3$s band.', '%1$s %2$s videos in the %3$s band.', $count, 'majestic-tube' ), number_format_i18n( $count ), $name, mb_strtolower( $band_label ) )
				/* translators: 1: video count, 2: actor name. */
				: sprintf( _n( '%1$s video of %2$s.', '%1$s videos of %2$s.', $count, 'majestic-tube' ), number_format_i18n( $count ), $name );
			break;

		case 'category_tag':
			$parts[] = ( $term2 )
				/* translators: 1: video count, 2: category, 3: tag. */
				? sprintf( _n( '%1$s video in %2$s carries the %3$s tag.', '%1$s videos in %2$s carry the %3$s tag.', $count, 'majestic-tube' ), number_format_i18n( $count ), $name, $term2->name )
				: sprintf( _n( '%1$s video in this category.', '%1$s videos in this category.', $count, 'majestic-tube' ), number_format_i18n( $count ) );
			break;

		case 'studio':
			$parts[] = /* translators: 1: video count, 2: studio name. */
				sprintf( _n( '%1$s video from %2$s.', '%1$s videos from %2$s.', $count, 'majestic-tube' ), number_format_i18n( $count ), $name );
			break;

		case 'series':
			$parts[] = /* translators: 1: video count, 2: series name. */
				sprintf( _n( '%1$s episode in %2$s.', '%1$s episodes in %2$s.', $count, 'majestic-tube' ), number_format_i18n( $count ), $name );
			break;

		default:
			$parts[] = sprintf(
				/* translators: 1: video count, 2: term name. */
				_n( '%1$s video of %2$s.', '%1$s videos of %2$s.', $count, 'majestic-tube' ),
				number_format_i18n( $count ),
				$name
			);
			break;
	}

	if ( $runtime ) {
		$parts[] = sprintf(
			/* translators: 1: actor or entity name, 2: total runtime. */
			__( 'Together they run for %1$s, and %2$s is the most common co-star.', 'majestic-tube' ),
			$runtime,
			$name
		);
	}

	if ( $related && isset( $related[0]['label'] ) ) {
		$parts[] = sprintf(
			/* translators: 1: term name, 2: another term name, 3: shared video count. */
			__( '%1$s appears most often alongside %2$s, shared across %3$s videos.', 'majestic-tube' ),
			$name,
			$related[0]['label'],
			number_format_i18n( (int) $related[0]['count'] )
		);
	}

	$summary = implode( ' ', $parts );

	/**
	 * Filter a facet's generated summary sentence.
	 *
	 * The term, band and stats are passed as extra arguments so a listener
	 * can replace the sentence with wording built from the same real values;
	 * existing listeners reading only the first two arguments are unaffected.
	 *
	 * @param string       $summary Plain text summary.
	 * @param string       $type    Facet type key.
	 * @param WP_Term|null $term    Primary term.
	 * @param WP_Term|null $term2   Secondary term.
	 * @param string       $band    Duration band key.
	 * @param array        $stats   Facet stats.
	 */
	return (string) apply_filters( 'majestic_tube_facet_summary', $summary, $type, $term, $term2, $band, $stats );
}
