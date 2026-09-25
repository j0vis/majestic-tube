<?php
/**
 * Pagination helpers.
 *
 * The original theme shipped its own paginator and every archive template
 * called wpst_page_navi(). Majestic Tube keeps that helper contract while
 * rendering the original KingTube-style paginator structure with clean,
 * escaped links and the same `wpst_page_navi` contract.
 *
 * @package Majestic Tube
 * @version 2.0.6
 */

defined( 'ABSPATH' ) || exit;

/**
 * Current page number of a paginated page template.
 *
 * Page templates use `page` for the /2/ segment while archive-like views use
 * `paged`; both are checked so the term directories paginate correctly on a
 * static front page too.
 *
 * @return int Page number, minimum 1.
 */
function majestic_tube_get_paged() {
	global $paged;

	$query_paged = max(
		absint( get_query_var( 'paged' ) ),
		absint( get_query_var( 'page' ) )
	);

	// The original paginator reads the global $paged value. Keep that source
	// as a fallback for custom main queries while also supporting the page
	// query var used by static page templates.
	return max( 1, absint( $paged ), $query_paged );
}

/**
 * Build the original pagination markup.
 *
 * @param int    $pages      Total number of pages.
 * @param int    $range      Pages shown on each side of the current page.
 * @param int    $current    Current page number.
 * @param string $link_build Optional callable returning a page URL.
 * @return string Markup, or an empty string when there is nothing to page.
 */
function majestic_tube_pagination_markup( $pages, $range = 4, $current = 0, $link_builder = null ) {
	$pages = absint( $pages );

	if ( $pages < 2 ) {
		return '';
	}

	$range     = max( 1, absint( $range ) );
	$current   = $current ? absint( $current ) : majestic_tube_get_paged();
	$current   = min( $pages, max( 1, $current ) );
	$showitems = ( $range * 2 ) + 1;

	if ( ! is_callable( $link_builder ) ) {
		$link_builder = 'get_pagenum_link';
	}

	$link = function ( $page ) use ( $link_builder ) {
		return esc_url( call_user_func( $link_builder, $page ) );
	};

	$output = '<div class="pagination"><ul>';

	if ( $current > 2 && $current > $range + 1 && $showitems < $pages ) {
		$output .= '<li><a href="' . $link( 1 ) . '">' . esc_html__( 'First', 'majestic-tube' ) . '</a></li>';
	}

	if ( $current > 1 && $showitems < $pages ) {
		$output .= '<li><a href="' . $link( $current - 1 ) . '">' . esc_html__( 'Previous', 'majestic-tube' ) . '</a></li>';
	}

	for ( $page = 1; $page <= $pages; $page++ ) {
		if ( 1 !== $pages && ( ! ( $page >= $current + $range + 1 || $page <= $current - $range - 1 ) || $pages <= $showitems ) ) {
			if ( $current === $page ) {
				$output .= '<li><a class="current">' . absint( $page ) . '</a></li>';
			} else {
				$output .= '<li><a href="' . $link( $page ) . '" class="inactive">' . absint( $page ) . '</a></li>';
			}
		}
	}

	if ( $current < $pages && $showitems < $pages ) {
		$output .= '<li><a href="' . $link( $current + 1 ) . '">' . esc_html__( 'Next', 'majestic-tube' ) . '</a></li>';
	}

	if ( $current < $pages - 1 && $current + $range - 1 < $pages && $showitems < $pages ) {
		$output .= "<li><a href='" . $link( $pages ) . "'>" . esc_html__( 'Last', 'majestic-tube' ) . '</a></li>';
	}

	$output .= '</ul></div>';

	return $output;
}

/**
 * Print the pagination for the main query (original wpst_page_navi behavior).
 *
 * @param int|string $pages Total pages. Empty reads the main query.
 * @param int        $range Pages shown on each side of the current page.
 * @return void
 */
function majestic_tube_page_navi( $pages = '', $range = 4 ) {
	if ( '' === $pages ) {
		$pages = isset( $GLOBALS['wp_query']->max_num_pages ) ? $GLOBALS['wp_query']->max_num_pages : 1;
	}

	$markup = majestic_tube_pagination_markup( $pages ? $pages : 1, $range );

	if ( $markup ) {
		/**
		 * Filter the pagination markup.
		 *
		 * @param string $markup Pagination HTML.
		 * @param int    $pages  Total pages.
		 * @param int    $range  Page range.
		 */
		echo wp_kses_post( apply_filters( 'majestic_tube_pagination', $markup, $pages, $range ) );
	}
}

/**
 * Display numbered pagination links for the main query.
 *
 * @return void
 */
function majestic_tube_the_pagination() {
	majestic_tube_page_navi();
}

/**
 * Number of terms shown on each page of a term directory.
 *
 * @param string $option_key Original wpst-options key.
 * @param int    $default    Fallback when the option is missing.
 * @return int
 */
function majestic_tube_terms_per_page( $option_key, $default = 20 ) {
	$per_page = absint( majestic_tube_get_option( 'wpst-options', $option_key, $default ) );

	return $per_page > 0 ? $per_page : $default;
}

/**
 * Object cache group for the term directory pages.
 */
const MAJESTIC_TUBE_TERM_CACHE_GROUP = 'majestic_tube_terms';

/**
 * How long a cached term directory page is kept, as a backstop.
 *
 * Correctness does not depend on this value. The cache key embeds WordPress's
 * own `last_changed` marker for the terms group, which WordPress bumps whenever
 * any term is created, edited or deleted, so a stale entry simply becomes
 * unreachable. The expiry only stops abandoned keys accumulating on a long-lived
 * persistent object cache.
 */
const MAJESTIC_TUBE_TERM_CACHE_TTL = 6 * HOUR_IN_SECONDS;

/**
 * Fetch one page of a term directory, cached.
 *
 * The Categories, Actors and Tags page templates all ran the same two queries -
 * a paged get_terms() and a wp_count_terms() - on every request. On a site with
 * thousands of actors that is real work repeated for a page that rarely changes
 * between visitors.
 *
 * Both results are cached together under a key that embeds the `last_changed`
 * value WordPress maintains for the terms cache group. That marker changes
 * whenever a term is added, renamed, deleted or reassigned, which means the
 * cache cannot go stale without this module hooking anything: a new marker
 * simply produces a different key. That is deliberately better than flushing on
 * `edited_term`, which would miss the paths that matter here (bulk imports,
 * CLI updates, and anything writing terms without going through the admin UI).
 *
 * @param string $taxonomy Taxonomy name.
 * @param int    $per_page Terms per page.
 * @param int    $page     1-based page number.
 * @return array{terms: array, total: int, error: mixed} Terms, the total term
 *         count, and any WP_Error from the underlying call.
 */
function majestic_tube_get_term_directory( $taxonomy, $per_page, $page = 1 ) {
	$taxonomy = sanitize_key( $taxonomy );
	$per_page = max( 1, absint( $per_page ) );
	$page     = max( 1, absint( $page ) );

	if ( ! taxonomy_exists( $taxonomy ) ) {
		return array(
			'terms' => array(),
			'total' => 0,
			'error' => new WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy.', 'majestic-tube' ) ),
		);
	}

	$last_changed = wp_cache_get( 'last_changed', 'terms' );
	$cache_key    = sprintf(
		'%s_%d_%d_%s',
		$taxonomy,
		$per_page,
		$page,
		preg_replace( '/[^A-Za-z0-9_.:-]/', '', (string) $last_changed )
	);

	$cached = wp_cache_get( $cache_key, MAJESTIC_TUBE_TERM_CACHE_GROUP );

	if ( is_array( $cached ) && isset( $cached['terms'], $cached['total'] ) ) {
		return $cached;
	}

	$args = array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
		'number'     => $per_page,
		'offset'     => ( $page - 1 ) * $per_page,
		'orderby'    => 'name',
		'order'      => 'ASC',
	);

	$terms = get_terms( $args );

	$result = array(
		'terms' => is_wp_error( $terms ) ? array() : $terms,
		'total' => 0,
		'error' => is_wp_error( $terms ) ? $terms : null,
	);

	// The count is only worth a second query when the page actually has terms
	// to paginate; an empty or errored page has nothing to link to.
	if ( ! is_wp_error( $terms ) && $terms ) {
		$count_args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		);

		/**
		 * Filter the arguments used to count a term directory.
		 *
		 * @param array  $count_args Count arguments.
		 * @param string $taxonomy   Taxonomy being listed.
		 * @param int    $per_page   Terms per page.
		 * @param int    $page       Current page.
		 */
		$count_args = apply_filters( 'majestic_tube_term_directory_count_args', $count_args, $taxonomy, $per_page, $page );

		$total = wp_count_terms( $count_args );

		$result['total'] = is_wp_error( $total ) ? 0 : (int) $total;
	}

	// An errored result is not cached, so a transient failure cannot stick.
	if ( ! is_wp_error( $terms ) ) {
		wp_cache_set( $cache_key, $result, MAJESTIC_TUBE_TERM_CACHE_GROUP, MAJESTIC_TUBE_TERM_CACHE_TTL );
	}

	return $result;
}

/**
 * Pagination links for a term directory (actors, categories, tags).
 *
 * Terms are not paginated by WordPress, so the links are derived from the
 * total term count and the per-page option.
 *
 * @param int $total_terms Total number of terms.
 * @param int $per_page    Terms per page.
 * @return void
 */
function majestic_tube_term_pagination( $total_terms, $per_page ) {
	$per_page = max( 1, absint( $per_page ) );
	$pages    = (int) ceil( absint( $total_terms ) / $per_page );

	if ( $pages < 2 ) {
		return;
	}

	// Term directories are page templates, so the page segment lives in the
	// URL as /page/N/ and get_pagenum_link() already resolves it correctly.
	$markup = majestic_tube_pagination_markup( $pages, 4, majestic_tube_get_paged() );

	if ( $markup ) {
		echo wp_kses_post( $markup );
	}
}
