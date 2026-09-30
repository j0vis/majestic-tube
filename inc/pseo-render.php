<?php
/**
 * Facet query wiring, the /browse/ crawl spine, and sibling links.
 *
 * Two jobs that belong together because they answer the same question: what
 * should a visitor see here, and what else should a crawler find from here.
 *
 * The spine exists because facet pages are not discoverable on their own. A
 * crawler arriving at the home page has no path to `/actor/jane/category-a/`
 * unless something links to it, and "something" cannot be the database - it
 * has to be a page. `/browse/` is that page: a real, crawlable, paginated
 * index of every indexable facet, grouped and alphabetised, linked at two
 * hops from home. That is what makes the whole surface indexable without
 * storing a single generated page.
 *
 * @package Majestic Tube
 * @version 2.2.21
 */

defined( 'ABSPATH' ) || exit;

/**
 * The groups the browse index can list, mapped to the facet type each builds.
 *
 * The group key is a URL segment and the facet type is the vocabulary key, and
 * they are NOT the same string: the group is `actor-category` (hyphen, it has
 * to survive a URL segment) while the type is `actor_category`. Keeping the
 * mapping in one table is what stops a lookup of one from silently returning
 * nothing for the other - which is exactly what happened when the builder
 * read the threshold straight out of the type table by group key and got
 * zero, then produced an empty group for the two groups that matter most.
 *
 * @return array<string, array{label:string, type:string}>
 */
function majestic_tube_browse_groups() {
	$groups = array(
		'actor-category' => array(
			'label' => __( 'Actor in category', 'majestic-tube' ),
			'type'  => 'actor_category',
		),
		'actor-pair'     => array(
			'label' => __( 'Actor pairings', 'majestic-tube' ),
			'type'  => 'actor_actor',
		),
		'actor-length'   => array(
			'label' => __( 'Actor duration bands', 'majestic-tube' ),
			'type'  => 'actor_length',
		),
		'category-tag'   => array(
			'label' => __( 'Category tags', 'majestic-tube' ),
			'type'  => 'category_tag',
		),
		'studio'         => array(
			'label' => __( 'Studios', 'majestic-tube' ),
			'type'  => 'studio',
		),
		'series'         => array(
			'label' => __( 'Series', 'majestic-tube' ),
			'type'  => 'series',
		),
	);

	/**
	 * Filter the browse index groups.
	 *
	 * @param array $groups Group key => array( label, type ).
	 */
	return (array) apply_filters( 'majestic_tube_browse_groups', $groups );
}

/**
 * Turn a facet request into the main video query.
 *
 * Runs on pre_get_posts so the page uses the normal loop, the normal
 * pagination and the normal card renderer: a facet page is an archive, and
 * making it one is what keeps it from becoming a second, lesser template that
 * quietly rots.
 *
 * @param WP_Query $query Query being prepared.
 * @return void
 */
function majestic_tube_facet_pre_get_posts( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( ! majestic_tube_is_facet_request() ) {
		return;
	}

	$facet = majestic_tube_current_facet();

	if ( ! $facet ) {
		return;
	}

	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'ignore_sticky_posts' => true,
	);

	$tax_query = array();

	if ( 'category_tag' === $facet['type'] ) {
		$tax_query[] = array(
			'taxonomy' => 'category',
			'field'    => 'term_id',
			'terms'    => array( (int) $facet['term']->term_id ),
		);
		$tax_query[] = array(
			'taxonomy' => 'post_tag',
			'field'    => 'term_id',
			'terms'    => array( (int) $facet['term2']->term_id ),
		);
	} elseif ( 'actor_length' === $facet['type'] ) {
		$tax_query[] = array(
			'taxonomy' => 'actors',
			'field'    => 'term_id',
			'terms'    => array( (int) $facet['term']->term_id ),
		);
	} else {
		$tax_query[] = array(
			'taxonomy' => 'actors',
			'field'    => 'term_id',
			'terms'    => array( (int) $facet['term']->term_id ),
		);

		if ( ! empty( $facet['term2'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'actors',
				'field'    => 'term_id',
				'terms'    => array( (int) $facet['term2']->term_id ),
				'operator' => 'AND',
			);
		}
	}

	if ( count( $tax_query ) > 1 ) {
		$tax_query['relation'] = 'AND';
	}

	$args['tax_query'] = $tax_query;

	if ( ! empty( $facet['band'] ) ) {
		$bands = majestic_tube_length_bands();
		$band  = $bands[ $facet['band'] ];

		$args['meta_query'] = array(
			array(
				'key'     => 'duration',
				'value'   => array( (int) $band['min'], (int) $band['max'] ),
				'type'    => 'NUMERIC',
				'compare' => $band['max'] > 0 ? 'BETWEEN' : '>=',
			),
		);
	}

	$args = (array) apply_filters( 'majestic_tube_facet_query_args', $args, $facet );

	foreach ( $args as $key => $value ) {
		$query->set( $key, $value );
	}

	$query->is_home     = false;
	$query->is_archive  = true;
	$query->is_singular = false;
	$query->is_taxonomy = false;
}
add_action( 'pre_get_posts', 'majestic_tube_facet_pre_get_posts' );

/**
 * Route facet and browse requests onto their templates.
 *
 * These are virtual pages: no post is behind them, so nothing in WordPress
 * would select a template on its own. Routing them here - rather than
 * letting them fall through to a 404 or to the front page - is what makes
 * the rewrite rules mean anything.
 *
 * @param string $template Resolved template path.
 * @return string
 */
function majestic_tube_pseo_template_include( $template ) {
	if ( is_admin() ) {
		return $template;
	}

	if ( majestic_tube_is_browse_request() ) {
		$candidate = MAJESTIC_TUBE_DIR . '/template-pseo-browse.php';

		return file_exists( $candidate ) ? $candidate : $template;
	}

	if ( majestic_tube_is_facet_request() ) {
		// An unresolvable facet must 404, not render an empty listing.
		if ( ! majestic_tube_current_facet() ) {
			return $template;
		}

		$candidate = MAJESTIC_TUBE_DIR . '/inc/pseo-template.php';

		if ( file_exists( $candidate ) ) {
			return $candidate;
		}
	}

	return $template;
}
add_filter( 'template_include', 'majestic_tube_pseo_template_include', 20 );

/**
 * 404 a facet URL that resolved to nothing.
 *
 * A request that matches a facet rewrite but has no data behind it must not
 * render. A 200 with an empty grid is the single worst outcome available here:
 * it is a thin page, it is indexable by default, and it says "this collection
 * exists" when it does not.
 *
 * @return void
 */
function majestic_tube_facet_maybe_404() {
	if ( is_admin() || ! is_404() ) {
		return;
	}

	if ( ! majestic_tube_is_facet_request() ) {
		return;
	}

	/*
	 * Nothing to repair here. The query vars are set but the resolver found no
	 * term, no count, or a self-pairing, so the request correctly stays a 404
	 * and the theme's 404 template renders. This hook exists to make that
	 * decision explicit and to leave a place for the failure to be logged if a
	 * site ever needs to debug a URL that should have resolved.
	 */
	do_action( 'majestic_tube_facet_unresolved', get_query_var( 'mt_facet' ) );
}

/**
 * Count the facets in one browse group.
 *
 * @param string $group Group key.
 * @return int
 */
function majestic_tube_browse_group_count( $group ) {
	return count( majestic_tube_browse_group_facets( $group, 0, '' ) );
}

/**
 * One page of facets for a browse group.
 *
 * The enumeration walks the taxonomies rather than a stored index, which keeps
 * it honest: a facet that no longer has enough videos simply stops being
 * listed, with no synchronisation step to fall out of date. It is bounded on
 * purpose - a site with a very large catalogue gets a capped index plus a
 * direct link to the taxonomy archives, rather than an unbounded scan on a
 * front-end request.
 *
 * @param string $group Group key.
 * @param int    $per_page Facets per page, or 0 to count only.
 * @param string $letter Optional single-letter filter.
 * @return array<int, array{type:string,term:WP_Term,term2:WP_Term|null,band:string,count:int,url:string,label:string}>
 */
function majestic_tube_browse_group_facets( $group, $per_page = 24, $letter = '' ) {
	$groups = majestic_tube_browse_groups();

	if ( ! isset( $groups[ $group ] ) ) {
		return array();
	}

	$last_changed = (string) wp_cache_get( 'last_changed', 'terms' );
	$key          = 'browse_' . $group . '_' . ( $per_page ? $per_page : 'all' ) . '_' . preg_replace( '/[^A-Za-z0-9_.:-]/', '', $last_changed );

	$cached = wp_cache_get( $key, MAJESTIC_TUBE_PSEO_CACHE_GROUP );

	if ( is_array( $cached ) ) {
		if ( $letter ) {
			return array_values(
				array_filter(
					$cached,
					function ( $facet ) use ( $letter ) {
						return 0 === strcasecmp( substr( $facet['label'], 0, 1 ), $letter );
					}
				)
			);
		}

		return $cached;
	}

	$facets = majestic_tube_build_browse_group( $group );

	wp_cache_set( $key, $facets, MAJESTIC_TUBE_PSEO_CACHE_GROUP, MAJESTIC_TUBE_PSEO_CACHE_TTL );

	if ( $letter ) {
		return array_values(
			array_filter(
				$facets,
				function ( $facet ) use ( $letter ) {
					return 0 === strcasecmp( substr( $facet['label'], 0, 1 ), $letter );
				}
			)
		);
	}

	return $facets;
}

/**
 * How many facets one browse group may enumerate.
 *
 * A cap, deliberately. Past this the group is better served by the taxonomy
 * archive than by an index page, and an unbounded scan on a front-end request
 * is exactly the kind of thing that only looks fine until the catalogue grows.
 */
const MAJESTIC_TUBE_PSEO_MAX_PER_GROUP = 2000;

/**
 * Build one browse group's facet list.
 *
 * @param string $group Group key.
 * @return array
 */
function majestic_tube_build_browse_group( $group ) {
	$types  = majestic_tube_facet_types();
	$groups = majestic_tube_browse_groups();
	$out    = array();

	if ( ! isset( $groups[ $group ]['type'] ) ) {
		return $out;
	}

	$type = $groups[ $group ]['type'];

	// A term is only worth a facet page if it has enough behind it to clear
	// the type's floor. Counting by term count first means the expensive
	// per-facet count only runs for candidates that already look viable.
	$min = isset( $types[ $type ] ) ? (int) $types[ $type ]['min'] : 0;

	$primary_taxonomy = ( 'studio' === $type || 'series' === $type ) ? $type : 'actors';

	$terms = get_terms(
		array(
			'taxonomy'   => $primary_taxonomy,
			'hide_empty' => true,
			'number'     => MAJESTIC_TUBE_PSEO_MAX_PER_GROUP,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);

	if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
		return $out;
	}

	foreach ( $terms as $term ) {
		if ( 'studio' === $type || 'series' === $type ) {
			if ( (int) $term->count < $min ) {
				continue;
			}

			if ( ! majestic_tube_facet_is_listable( $type, (int) $term->count, $term, null, '' ) ) {
				continue;
			}

			$out[] = array(
				'type'  => $type,
				'term'  => $term,
				'term2' => null,
				'band'  => '',
				'count' => (int) $term->count,
				'url'   => majestic_tube_facet_url( $type, $term ),
				'label' => $term->name,
			);

			continue;
		}

		if ( 'actor_length' === $type ) {
			foreach ( array_keys( majestic_tube_length_bands() ) as $band ) {
				$count = majestic_tube_facet_count( 'actor_length', $term, null, $band );

				if ( $count < $min ) {
					continue;
				}

				if ( ! majestic_tube_facet_is_listable( 'actor_length', $count, $term, null, $band ) ) {
					continue;
				}

				$out[] = array(
					'type'  => 'actor_length',
					'term'  => $term,
					'term2' => null,
					'band'  => $band,
					'count' => $count,
					'url'   => majestic_tube_facet_url( 'actor_length', $term, null, $band ),
					'label' => $term->name,
				);
			}

			continue;
		}

		// Pairings and intersections need the secondary axis. The shared
		// terms the stats layer already computed for this actor are exactly
		// the candidates, so the browse index costs no extra query.
		$derived = majestic_tube_term_derived( $term->term_id );
		$candidates = isset( $derived['terms'] ) && is_array( $derived['terms'] ) ? array_keys( $derived['terms'] ) : array();

		$secondary_taxonomy = ( 'actor_actor' === $type ) ? 'actors' : ( 'actor_category' === $type ? 'category' : '' );

		if ( ! $secondary_taxonomy || ! $candidates ) {
			continue;
		}

		$secondaries = get_terms(
			array(
				'taxonomy'   => $secondary_taxonomy,
				'include'    => array_map( 'absint', array_slice( $candidates, 0, 12 ) ),
				'hide_empty' => true,
				'orderby'    => 'include',
			)
		);

		if ( is_wp_error( $secondaries ) || ! is_array( $secondaries ) ) {
			continue;
		}

		foreach ( $secondaries as $secondary ) {
			if ( (int) $secondary->term_id === (int) $term->term_id ) {
				continue;
			}

			// The shared count is the pair count, already computed; the exact
			// count is only re-derived when the cheap one looks marginal.
			$shared = isset( $derived['terms'][ (int) $secondary->term_id ] ) ? (int) $derived['terms'][ (int) $secondary->term_id ] : 0;

			if ( $shared < $min ) {
				continue;
			}

			/*
			 * The cheap gate, not the full one. This runs once per candidate
			 * across the whole catalogue, so it reads the cached rollup rather
			 * than issuing a video count and two term queries per facet. The
			 * floors are the same; the authority is
			 * majestic_tube_facet_indexable(), which the resolver runs again
			 * on the actual page request.
			 */
			if ( ! majestic_tube_facet_is_listable( $type, $shared, $term, $secondary, '' ) ) {
				continue;
			}

			$out[] = array(
				'type'  => $type,
				'term'  => $term,
				'term2' => $secondary,
				'band'  => '',
				'count' => $shared,
				'url'   => majestic_tube_facet_url( $type, $term, $secondary ),
				'label' => ( 'actor_actor' === $type )
					/* translators: 1: first actor, 2: second actor. */
					? sprintf( __( '%1$s with %2$s', 'majestic-tube' ), $term->name, $secondary->name )
					/* translators: 1: actor name, 2: category name. */
					: sprintf( __( '%1$s in %2$s', 'majestic-tube' ), $term->name, $secondary->name ),
			);
		}
	}

	return $out;
}

/**
 * Add a facet's siblings to the breadcrumb trail.
 *
 * Without this a facet is a leaf: the trail stops at the archive and the
 * reader has no way back up. The hub-and-spoke model is not just a linking
 * diagram, it is a navigation one.
 *
 * @param array $items Breadcrumb items.
 * @return array
 */
function majestic_tube_facet_breadcrumbs( $items ) {
	if ( ! is_array( $items ) || empty( $items ) ) {
		return $items;
	}

	if ( majestic_tube_is_browse_request() ) {
		$group = sanitize_key( (string) get_query_var( 'mt_facet' ) );
		$groups = majestic_tube_browse_groups();
		$label = isset( $groups[ $group ]['label'] ) ? $groups[ $group ]['label'] : __( 'Browse', 'majestic-tube' );

		$items[] = array(
			'label' => __( 'Browse', 'majestic-tube' ),
			'url'   => home_url( '/browse/' ),
		);

		if ( $label ) {
			$items[] = array(
				'label' => $label,
				'url'   => '',
			);
		}

		return $items;
	}

	if ( ! majestic_tube_is_facet_request() ) {
		return $items;
	}

	$facet = majestic_tube_current_facet();

	if ( ! $facet ) {
		return $items;
	}

	$items[] = array(
		'label' => __( 'Browse', 'majestic-tube' ),
		'url'   => home_url( '/browse/' ),
	);

	/*
	 * The archive link is the term's own archive, whatever taxonomy it
	 * belongs to. Keying this off the facet TYPE was wrong: the facet type
	 * ('actor_category') is not a taxonomy, so the hub step was silently
	 * never added and every facet trail went straight from the spine to the
	 * page itself.
	 */
	if ( isset( $facet['term']->taxonomy, $facet['term']->term_id ) ) {
		$archive = get_term_link( $facet['term'] );

		if ( ! is_wp_error( $archive ) ) {
			$items[] = array(
				'label' => $facet['term']->name,
				'url'   => $archive,
			);
		}
	}

	$items[] = array(
		'label' => majestic_tube_facet_heading( $facet ),
		'url'   => '',
	);

	return $items;
}
add_filter( 'majestic_tube_breadcrumb_items', 'majestic_tube_facet_breadcrumbs', 20 );

/**
 * Add the browse index to the main menu on activation.
 *
 * @return void
 */
function majestic_tube_add_browse_to_menu() {
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	$locations = is_array( $locations ) ? $locations : array();

	$menu_id = isset( $locations['majestic_tube_main_menu'] ) ? absint( $locations['majestic_tube_main_menu'] ) : 0;

	if ( ! $menu_id ) {
		return;
	}

	$url = home_url( '/browse/' );

	$items = wp_get_nav_menu_items( $menu_id );
	$items = is_array( $items ) ? $items : array();

	foreach ( $items as $item ) {
		if ( isset( $item->url ) && untrailingslashit( $item->url ) === untrailingslashit( $url ) ) {
			return;
		}
	}

	if ( ! wp_update_nav_menu_item(
		$menu_id,
		0,
		array(
			'menu-item-title'  => __( 'Browse', 'majestic-tube' ),
			'menu-item-url'    => $url,
			'menu-item-status' => 'publish',
		)
	) ) {
		return;
	}
}
add_action( 'after_switch_theme', 'majestic_tube_add_browse_to_menu', 60 );
