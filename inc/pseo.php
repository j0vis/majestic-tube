<?php
/**
 * Programmatic SEO foundation: facet types, URL resolution and the index gate.
 *
 * The generated pages are VIRTUAL. They are not stored as posts and not
 * written to the database as a shadow copy of the taxonomy data, because a
 * stored copy is a second source of truth: it can disagree with the
 * taxonomies, it has to be resynchronised after every import, and a facet
 * that says "9 videos" next to a grid showing 4 is the classic programmatic
 * SEO self-inflicted wound. Here the facet is derived from the taxonomies on
 * every request, so a stale count is not representable.
 *
 * What that costs is the `/browse/` index, which cannot be assembled by
 * crawling. It is served instead by one cached query per group, so a facet
 * only has to be reachable from `/browse/` - it never has to be enumerated in
 * the database.
 *
 * Facet pages are deliberately a strict, small, enumerable set:
 *
 *   /actor/{actor}/                          the actor archive (core taxonomy)
 *   /actor/{actor}/{category}/               an actor within a category
 *   /actor/{actor}/with/{actor2}/            two actors together
 *   /actor/{actor}/length/{band}/            duration-banded listings
 *   /category/{category}/{tag}/              a tag within a category
 *   /studio/{studio}/                        a production studio
 *   /series/{series}/                        a series
 *   /browse/...                              the crawl spine and index
 *
 * Anything that would multiply further (actor x tag x band x sort) is
 * deliberately absent: the pages would be thin, they would compete with each
 * other, and they would cost more crawl budget than they could earn.
 *
 * Every threshold below is filterable so a site with a different catalogue
 * size can retune the gate without editing the theme.
 *
 * @package Majestic Tube
 * @version 2.2.23
 */

defined( 'ABSPATH' ) || exit;

/**
 * Object cache group for facet data.
 */
const MAJESTIC_TUBE_PSEO_CACHE_GROUP = 'majestic_tube_pseo';

/**
 * Facet types, and the minimum video count each needs to be indexable.
 *
 * A facet with fewer videos still renders and still links - it is useful and
 * it passes PageRank - but it is not asking to be ranked. The distinct
 * per-type floors matter: a duo page with 4 videos is a genuine curiosity
 * that a visitor clicks, while a category/tag intersection with 4 videos is
 * indistinguishable from the pages around it, so it stays out of the index.
 *
 * @return array<string, array{label:string, min:int, min_words:int}>
 */
function majestic_tube_facet_types() {
	$types = array(
		'actor_category' => array(
			'label'      => __( 'actor in category', 'majestic-tube' ),
			'min'        => 6,
			'min_words'  => 250,
		),
		'actor_actor'    => array(
			'label'      => __( 'actor pairing', 'majestic-tube' ),
			'min'        => 4,
			'min_words'  => 250,
		),
		'actor_length'   => array(
			'label'      => __( 'actor duration band', 'majestic-tube' ),
			'min'        => 3,
			'min_words'  => 180,
		),
		'category_tag'   => array(
			'label'      => __( 'tag in category', 'majestic-tube' ),
			'min'        => 5,
			'min_words'  => 220,
		),
		'studio'         => array(
			'label'      => __( 'studio', 'majestic-tube' ),
			'min'        => 8,
			'min_words'  => 250,
		),
		'series'         => array(
			'label'      => __( 'series', 'majestic-tube' ),
			'min'        => 5,
			'min_words'  => 220,
		),
	);

	/**
	 * Filter the facet type table and its indexability thresholds.
	 *
	 * @param array $types Facet type => definition.
	 */
	return (array) apply_filters( 'majestic_tube_facet_types', $types );
}

/**
 * Duration bands for the length facet, in seconds.
 *
 * Fixed bands, not generated ones. A band boundary that moves with the
 * catalogue means yesterday's URL describes something different today, and
 * the page would have to be retired and replaced rather than updated.
 *
 * @return array<string, array{label:string, min:int, max:int}>
 */
function majestic_tube_length_bands() {
	$bands = array(
		'short'  => array(
			'label' => __( 'Short', 'majestic-tube' ),
			'min'   => 0,
			'max'   => 299,
		),
		'medium' => array(
			'label' => __( 'Medium length', 'majestic-tube' ),
			'min'   => 300,
			'max'   => 1199,
		),
		'long'   => array(
			'label' => __( 'Long', 'majestic-tube' ),
			'min'   => 1200,
			'max'   => 3599,
		),
		'longest' => array(
			'label' => __( 'Longest', 'majestic-tube' ),
			'min'   => 3600,
			'max'   => 0,
		),
	);

	/**
	 * Filter the duration band table.
	 *
	 * @param array $bands Band key => definition. A max of 0 means open ended.
	 */
	return (array) apply_filters( 'majestic_tube_length_bands', $bands );
}

/**
 * Taxonomies the facet system reads, mapped to the URL segment each uses.
 *
 * @return array<string, string>
 */
function majestic_tube_facet_taxonomies() {
	$taxonomies = array(
		'category' => 'category',
		'post_tag' => 'tag',
		'actors'   => 'actor',
		'studio'   => 'studio',
		'series'   => 'series',
	);

	/**
	 * Filter the taxonomies the facet system reads.
	 *
	 * @param array $taxonomies Taxonomy => URL segment.
	 */
	return (array) apply_filters( 'majestic_tube_facet_taxonomies', $taxonomies );
}

/**
 * Register the studio and series taxonomies.
 *
 * Both are ordinary non-hierarchical taxonomies so a bulk import assigns them
 * the same way it assigns tags, and both are optional: a site with no studio
 * or series data simply never produces those pages, because every generator
 * below counts first and returns nothing below the floor.
 *
 * @return void
 */
function majestic_tube_register_facet_taxonomies() {
	$labels = array(
		'studio' => array(
			'name'          => _x( 'Studios', 'taxonomy general name', 'majestic-tube' ),
			'singular_name' => _x( 'Studio', 'taxonomy singular name', 'majestic-tube' ),
			'menu_name'     => __( 'Video Studios', 'majestic-tube' ),
			'all_items'     => __( 'All Studios', 'majestic-tube' ),
			'edit_item'     => __( 'Edit Studio', 'majestic-tube' ),
			'add_new_item'  => __( 'Add New Studio', 'majestic-tube' ),
			'search_items'  => __( 'Search Studios', 'majestic-tube' ),
			'not_found'     => __( 'No studios found', 'majestic-tube' ),
		),
		'series' => array(
			'name'          => _x( 'Series', 'taxonomy general name', 'majestic-tube' ),
			'singular_name' => _x( 'Series', 'taxonomy singular name', 'majestic-tube' ),
			'menu_name'     => __( 'Video Series', 'majestic-tube' ),
			'all_items'     => __( 'All Series', 'majestic-tube' ),
			'edit_item'     => __( 'Edit Series', 'majestic-tube' ),
			'add_new_item'  => __( 'Add New Series', 'majestic-tube' ),
			'search_items'  => __( 'Search Series', 'majestic-tube' ),
			'not_found'     => __( 'No series found', 'majestic-tube' ),
		),
	);

	foreach ( $labels as $taxonomy => $taxonomy_labels ) {
		if ( taxonomy_exists( $taxonomy ) ) {
			continue;
		}

		register_taxonomy(
			$taxonomy,
			'post',
			array(
				'hierarchical'      => false,
				'labels'            => $taxonomy_labels,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'update_count_callback' => '_update_post_term_count',
				'query_var'         => true,
				'show_in_rest'      => true,
				'rewrite'           => array(
					'slug'       => $taxonomy,
					'with_front' => false,
				),
			)
		);
	}
}
add_action( 'init', 'majestic_tube_register_facet_taxonomies', 1 );

/**
 * Add the facet query vars.
 *
 * @param array $vars Public query vars.
 * @return array
 */
function majestic_tube_facet_query_vars( $vars ) {
	$vars[] = 'mt_facet';
	$vars[] = 'mt_facet_taxonomy';
	$vars[] = 'mt_facet_term';
	$vars[] = 'mt_facet_term2';
	$vars[] = 'mt_facet_band';
	$vars[] = 'mt_browse';

	return $vars;
}
add_filter( 'query_vars', 'majestic_tube_facet_query_vars' );

/**
 * Register the facet rewrite rules.
 *
 * Order matters. WordPress matches these in order, so the most specific
 * pattern has to be registered first: `/actor/x/length/long/` would
 * otherwise be swallowed by the two-segment actor/category rule, and
 * `browse/actors/` would be read as a group named "actors" with no letter.
 *
 * @return void
 */
function majestic_tube_facet_rewrite_rules() {
	// /browse/... - the crawl spine. Registered before the bare rule below.
	add_rewrite_rule( '^browse/page/([0-9]{1,5})/?$', 'index.php?mt_browse=1&paged=$matches[1]', 'top' );
	add_rewrite_rule( '^browse/([a-z0-9-]+)/([a-z0-9])(?:/page/([0-9]{1,5}))?/?$', 'index.php?mt_browse=1&mt_facet=$matches[1]&mt_facet_band=$matches[2]&paged=$matches[3]', 'top' );
	add_rewrite_rule( '^browse/([a-z0-9-]+)(?:/page/([0-9]{1,5}))?/?$', 'index.php?mt_browse=1&mt_facet=$matches[1]&paged=$matches[2]', 'top' );
	add_rewrite_rule( '^browse/?$', 'index.php?mt_browse=1', 'top' );

	// Actor facets. The three-segment forms come first.
	add_rewrite_rule( '^actor/([^/]+)/with/([^/]+)/?$', 'index.php?mt_facet=actor_actor&mt_facet_taxonomy=actors&mt_facet_term=$matches[1]&mt_facet_term2=$matches[2]', 'top' );
	add_rewrite_rule( '^actor/([^/]+)/length/([a-z]+)/?$', 'index.php?mt_facet=actor_length&mt_facet_taxonomy=actors&mt_facet_term=$matches[1]&mt_facet_band=$matches[2]', 'top' );
	add_rewrite_rule( '^actor/([^/]+)/([^/]+)/?$', 'index.php?mt_facet=actor_category&mt_facet_taxonomy=actors&mt_facet_term=$matches[1]&mt_facet_term2=$matches[2]', 'top' );

	// Category and tag intersections.
	add_rewrite_rule( '^category/([^/]+)/([^/]+)/?$', 'index.php?mt_facet=category_tag&mt_facet_taxonomy=category&mt_facet_term=$matches[1]&mt_facet_term2=$matches[2]', 'top' );
}
add_action( 'init', 'majestic_tube_facet_rewrite_rules', 12 );

/**
 * Revision of the facet rewrite rules.
 *
 * Bumping this makes every site flush its rewrite rules once, so a change to
 * the patterns above is picked up without the administrator having to visit
 * Settings > Permalinks.
 *
 * @return int
 */
function majestic_tube_facet_rewrite_revision() {
	/**
	 * Filter the facet rewrite rule revision.
	 *
	 * @param int $revision Current revision.
	 */
	return (int) apply_filters( 'majestic_tube_facet_rewrite_revision', 1 );
}

/**
 * Flush the rewrite rules once per revision.
 *
 * @return void
 */
function majestic_tube_maybe_flush_facet_rewrites() {
	$revision = majestic_tube_facet_rewrite_revision();

	if ( (int) get_option( 'majestic_tube_facet_rewrite_revision', 0 ) >= $revision ) {
		return;
	}

	flush_rewrite_rules( false );
	update_option( 'majestic_tube_facet_rewrite_revision', $revision );
}
add_action( 'admin_init', 'majestic_tube_maybe_flush_facet_rewrites', 30 );
add_action( 'after_switch_theme', 'majestic_tube_maybe_flush_facet_rewrites', 45 );

/**
 * Whether the current request is one of the generated facet pages.
 *
 * @return bool
 */
function majestic_tube_is_facet_request() {
	return '' !== (string) get_query_var( 'mt_facet' );
}

/**
 * Whether the current request is the browse index.
 *
 * @return bool
 */
function majestic_tube_is_browse_request() {
	return '' !== (string) get_query_var( 'mt_browse' );
}

/**
 * Resolve the facet the current request describes, with its two terms resolved.
 *
 * Every consumer in the pSEO modules reads the facet through this function so
 * there is exactly one place that turns query vars into a validated, counted
 * object. A facet whose terms do not resolve, or which resolves to nothing at
 * all, returns null and the caller 404s - which is the important part: a facet
 * URL that matches the rules but has no data behind it must never render as
 * an empty indexable page.
 *
 * @return array|null {
 *     @type string $type     Facet type key.
 *     @type string $primary  Primary term slug.
 *     @type string $secondary Secondary term slug, or an empty string.
 *     @type string $band     Duration band key, or an empty string.
 *     @type WP_Term $term    Primary term.
 *     @type WP_Term|null $term2 Secondary term, when the facet has one.
 *     @type int    $count    Video count for this exact facet.
 *     @type bool   $indexable Whether the facet clears the index gate.
 * }
 */
function majestic_tube_current_facet() {
	$types = majestic_tube_facet_types();
	$type  = sanitize_key( (string) get_query_var( 'mt_facet' ) );

	if ( ! $type || ! isset( $types[ $type ] ) ) {
		return null;
	}

	$taxonomy  = sanitize_key( (string) get_query_var( 'mt_facet_taxonomy' ) );
	$primary   = sanitize_title( (string) get_query_var( 'mt_facet_term' ) );
	$secondary = sanitize_title( (string) get_query_var( 'mt_facet_term2' ) );
	$band      = sanitize_key( (string) get_query_var( 'mt_facet_band' ) );

	$taxonomies = majestic_tube_facet_taxonomies();

	if ( ! $primary || ! isset( $taxonomies[ $taxonomy ] ) ) {
		return null;
	}

	$term = get_term_by( 'slug', $primary, $taxonomy );

	if ( ! $term || is_wp_error( $term ) ) {
		return null;
	}

	$term2 = null;

	if ( $secondary ) {
		// A duo and a category intersection both carry a second term; the
		// taxonomy it belongs to is implied by the facet type.
		$second_taxonomy = ( 'actor_actor' === $type ) ? 'actors' : ( 'category_tag' === $type ? 'post_tag' : 'category' );

		$term2 = get_term_by( 'slug', $secondary, $second_taxonomy );

		if ( ! $term2 || is_wp_error( $term2 ) ) {
			return null;
		}

		// A self-pairing is not a facet.
		if ( (int) $term->term_id === (int) $term2->term_id ) {
			return null;
		}
	}

	if ( $band ) {
		$bands = majestic_tube_length_bands();

		if ( ! isset( $bands[ $band ] ) ) {
			return null;
		}
	}

	$count = majestic_tube_facet_count( $type, $term, $term2, $band );

	if ( $count < 1 ) {
		return null;
	}

	$gate = majestic_tube_facet_indexable( $type, $count, $term, $term2, $band );

	return array(
		'type'      => $type,
		'primary'   => $primary,
		'secondary' => $secondary,
		'band'      => $band,
		'term'      => $term,
		'term2'     => $term2,
		'count'     => $count,
		'indexable' => $gate,
	);
}

/**
 * Count the videos behind one facet.
 *
 * This is the number every part of the system agrees on: the grid, the
 * headline, the schema, the sibling links and the index gate all read it from
 * here, so a page cannot claim 12 videos above a grid of 4.
 *
 * @param string      $type Facet type key.
 * @param WP_Term     $term Primary term.
 * @param WP_Term|null $term2 Secondary term.
 * @param string      $band Duration band key.
 * @return int
 */
function majestic_tube_facet_count( $type, $term, $term2 = null, $band = '' ) {
	$args = array(
		'post_type'              => 'post',
		'post_status'            => 'publish',
		'posts_per_page'         => 1,
		'fields'                 => 'ids',
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	);

	$tax_query = array();

	if ( 'actor_length' === $type ) {
		$tax_query[] = array(
			'taxonomy' => 'actors',
			'field'    => 'term_id',
			'terms'    => array( (int) $term->term_id ),
		);
	} elseif ( 'category_tag' === $type ) {
		$tax_query[] = array(
			'taxonomy' => 'category',
			'field'    => 'term_id',
			'terms'    => array( (int) $term->term_id ),
		);
		$tax_query[] = array(
			'taxonomy' => 'post_tag',
			'field'    => 'term_id',
			'terms'    => array( (int) $term2->term_id ),
		);
	} else {
		$tax_query[] = array(
			'taxonomy' => 'actors',
			'field'    => 'term_id',
			'terms'    => array( (int) $term->term_id ),
		);

		if ( $term2 ) {
			$tax_query[] = array(
				'taxonomy' => 'actors',
				'field'    => 'term_id',
				'terms'    => array( (int) $term2->term_id ),
				'operator' => 'AND',
			);
		}
	}

	if ( count( $tax_query ) > 1 ) {
		$tax_query['relation'] = 'AND';
		$args['tax_query']      = $tax_query;
	} else {
		$args['tax_query'] = $tax_query;
	}

	if ( $band ) {
		$bands = majestic_tube_length_bands();
		$args['meta_query'] = array(
			array(
				'key'     => 'duration',
				'value'   => array( (int) $bands[ $band ]['min'], (int) $bands[ $band ]['max'] ),
				'type'    => 'NUMERIC',
				'compare' => $bands[ $band ]['max'] > 0 ? 'BETWEEN' : '>=',
			),
		);
	}

	$query = new WP_Query( $args );
	$count = (int) $query->found_posts;

	/**
	 * Filter the number of videos behind a facet.
	 *
	 * The count every consumer of a facet agrees on passes through here:
	 * the grid, the headline, the schema and the index gate all read the
	 * filtered value, so a site that resolves facets against an external
	 * catalogue can override one number rather than four call sites.
	 *
	 * @param int          $count Video count.
	 * @param string       $type  Facet type key.
	 * @param WP_Term      $term  Primary term.
	 * @param WP_Term|null $term2 Secondary term.
	 * @param string       $band  Duration band key.
	 */
	return (int) apply_filters( 'majestic_tube_facet_count', $count, $type, $term, $term2, $band );
}

/**
 * Whether a facet is allowed to ask to be ranked.
 *
 * The gate is a video count, a floor on distinct variables, and - the part
 * that actually prevents a thin-content penalty - a word budget the template
 * has to be able to fill. A facet that clears the count but cannot produce
 * the words stays out of the index, because a page with 9 cards and 60 words
 * of preamble is worse than no page at all: it is a thin result for a query
 * it cannot answer, and it spends crawl budget to do it.
 *
 * @param string       $type Facet type key.
 * @param int          $count Video count.
 * @param WP_Term      $term Primary term.
 * @param WP_Term|null $term2 Secondary term.
 * @param string       $band Duration band key.
 * @return bool
 */
function majestic_tube_facet_indexable( $type, $count, $term, $term2 = null, $band = '' ) {
	$types = majestic_tube_facet_types();

	if ( ! isset( $types[ $type ] ) ) {
		return false;
	}

	$min       = max( 1, (int) $types[ $type ]['min'] );
	$min_words = max( 0, (int) $types[ $type ]['min_words'] );

	$indexable = $count >= $min;

	if ( $indexable && $min_words ) {
		$indexable = majestic_tube_facet_word_budget( $type, $term, $term2, $band ) >= $min_words;
	}

	/**
	 * Filter whether a facet is indexable.
	 *
	 * @param bool         $indexable Current decision.
	 * @param string       $type      Facet type key.
	 * @param int          $count     Video count.
	 * @param WP_Term      $term      Primary term.
	 * @param WP_Term|null $term2     Secondary term.
	 * @param string       $band      Duration band key.
	 */
	return (bool) apply_filters( 'majestic_tube_facet_indexable', $indexable, $type, $count, $term, $term2, $band );
}

/**
 * The cheap half of the index gate, for enumerating many facets at once.
 *
 * majestic_tube_facet_indexable() is the authority, and it is the right answer
 * for the one facet being rendered. It is the wrong tool for a sitemap page or
 * a browse index, where it would run once per facet and each call costs a
 * video count plus two term queries: tens of thousands of queries to answer a
 * question that only needs the cached per-term rollup.
 *
 * So this measures the same two floors from the only cheap source available -
 * the term's derived rollup, which is one option read - and accepts that it
 * is an estimate. The floors are identical, so the two functions agree on
 * every facet with real data; they can only differ on a facet sitting exactly
 * on a boundary, and for those the full gate in the resolver has the final
 * say because it runs on the actual page request.
 *
 * @param string       $type Facet type key.
 * @param int          $count Video count.
 * @param WP_Term      $term Primary term.
 * @param WP_Term|null $term2 Secondary term.
 * @param string       $band Duration band key.
 * @return bool
 */
function majestic_tube_facet_is_listable( $type, $count, $term, $term2 = null, $band = '' ) {
	$types = majestic_tube_facet_types();

	if ( ! isset( $types[ $type ] ) ) {
		return false;
	}

	$min       = max( 1, (int) $types[ $type ]['min'] );
	$min_words = max( 0, (int) $types[ $type ]['min_words'] );

	$listable = $count >= $min;

	if ( $listable && $min_words ) {
		$listable = majestic_tube_facet_rollup_word_budget( $term, $count, $term2 ) >= $min_words;
	}

	/**
	 * Filter whether a facet belongs in the browse index and the sitemap.
	 *
	 * @param bool         $listable Current decision.
	 * @param string       $type     Facet type key.
	 * @param int          $count    Video count.
	 * @param WP_Term      $term     Primary term.
	 * @param WP_Term|null $term2    Secondary term.
	 * @param string       $band     Duration band key.
	 */
	return (bool) apply_filters( 'majestic_tube_facet_is_listable', $listable, $type, $count, $term, $term2, $band );
}

/**
 * Word budget from the cached rollup alone, with no queries beyond one option.
 *
 * Deliberately built to track the same structure as the authoritative
 * majestic_tube_facet_word_budget() so the two agree on real facets, and
 * deliberately built to fail low. The error that matters here is admitting a
 * facet the page will noindex: the sitemap would be asking a crawler to spend
 * budget on a URL the theme has already told it not to rank. The opposite
 * error only leaves a live page out of the submission, and the page is still
 * indexable and still linked from /browse/, so it is still found.
 *
 * The card words are therefore taken as the smaller of what the caller
 * believes the facet holds and what the rollup confirms. A pair facet shares
 * one rollup with the primary term, so the term's total would overstate the
 * videos the pair actually lists, and a pair that clears the word floor on
 * the term's numbers but not on its own would be submitted and then noindexed.
 *
 * @param WP_Term      $term  Primary term.
 * @param int          $count Video count for this facet.
 * @param WP_Term|null $term2 Secondary term.
 * @return int
 */
function majestic_tube_facet_rollup_word_budget( $term, $count = 0, $term2 = null ) {
	$derived = majestic_tube_term_derived( $term->term_id );
	$labels  = majestic_tube_derived_labels( $derived );

	$names = 2;

	foreach ( array( $term, $term2 ) as $one ) {
		if ( $one && isset( $one->name ) ) {
			$names += max( 1, count( explode( ' ', (string) $one->name ) ) );
		}
	}

	$words = 40 + $names * 2;

	if ( ! empty( $labels['runtime_label'] ) ) {
		$words += 3;
	}

	// The authority does not count HD, but over-counting here only makes this
	// gate more permissive than the page, which is the safe direction to err.
	if ( ! empty( $labels['hd_label'] ) ) {
		$words += 3;
	}

	if ( ! empty( $labels['span_label'] ) ) {
		$words += 6;
	}

	// Capped to approximate the authority's related, sibling and attribute
	// blocks, which are capped too. An uncapped co-occurrence list would let a
	// single well-connected actor inflate every one of its facets.
	$co = isset( $derived['terms'] ) && is_array( $derived['terms'] ) ? count( $derived['terms'] ) : 0;
	$words += min( 12, $co ) * 3;

	$total = isset( $derived['total'] ) ? (int) $derived['total'] : 0;
	$cards = min( 24, min( max( 0, (int) $count ), $total ) );
	$words += $cards * 10;

	/**
	 * Filter the cheap word budget used when enumerating many facets.
	 *
	 * @param int          $words Estimated variable word count.
	 * @param WP_Term      $term  Primary term.
	 * @param int          $count Video count for this facet.
	 * @param WP_Term|null $term2 Secondary term.
	 */
	return (int) apply_filters( 'majestic_tube_facet_rollup_word_budget', $words, $term, $count, $term2 );
}

/**
 * How many words of genuinely variable text this facet can supply.
 *
 * This is not a guess at template length. It counts the distinct data values
 * the template will actually print - the two names, the runtime, the date
 * range, the co-stars, the category and tag names, the studio and series
 * names - using the same averages a human sentence costs, so the number moves
 * with the data and stops a data-poor facet from claiming depth it lacks.
 *
 * The video cards count too, and that is not padding. A card carries a title,
 * an actor name, a duration and a view count; on a facet with a dozen videos
 * that is over a hundred words of real, per-page, unique copy - the single
 * largest source of variation on the page. A facet that cannot fill the
 * budget has too few videos to fill it, which is the same signal as the
 * video floor, measured a second way.
 *
 * The thresholds in majestic_tube_facet_types() were calibrated against this
 * function, so the two must move together: raise min_words without adding
 * template copy and every facet stops being indexable at once.
 *
 * @param string       $type Facet type key.
 * @param WP_Term      $term Primary term.
 * @param WP_Term|null $term2 Secondary term.
 * @param string       $band Duration band key.
 * @return int
 */
function majestic_tube_facet_word_budget( $type, $term, $term2 = null, $band = '' ) {
	$stats = majestic_tube_facet_stats( $type, $term, $term2, $band );

	// Each distinct name averages roughly two words in a sentence.
	$names = 2;

	foreach ( array( $term, $term2 ) as $one ) {
		if ( $one && isset( $one->name ) ) {
			$names += max( 1, count( explode( ' ', (string) $one->name ) ) );
		}
	}

	$words = 40; // Fixed connective prose: the template's own sentences.
	$words += $names * 2;

	if ( ! empty( $stats['runtime_label'] ) ) {
		$words += 3;
	}

	if ( ! empty( $stats['date_range_label'] ) ) {
		$words += 6;
	}

	// Every co-star, sibling facet and secondary term is a named link with
	// anchor text, which is real on-page copy a reader and a crawler both see.
	$words += count( $stats['related'] ) * 3;
	$words += count( $stats['siblings'] ) * 3;
	$words += count( $stats['attributes'] ) * 3;

	if ( ! empty( $stats['summary'] ) ) {
		$words += count( explode( ' ', (string) $stats['summary'] ) );
	}

	/*
	 * The cards. Capped at one page of results: a facet listing 4,000 videos
	 * renders 24 of them, and only rendered words are on the page.
	 */
	$words += min( 24, isset( $stats['count'] ) ? (int) $stats['count'] : 0 ) * 10;

	/**
	 * Filter the computed word budget for a facet.
	 *
	 * @param int          $words Estimated variable word count.
	 * @param string       $type  Facet type key.
	 * @param WP_Term      $term  Primary term.
	 * @param WP_Term|null $term2 Secondary term.
	 * @param string       $band  Duration band key.
	 */
	return (int) apply_filters( 'majestic_tube_facet_word_budget', $words, $type, $term, $term2, $band );
}
