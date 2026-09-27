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
 * @version 2.1.4
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
 * @param string $letter   Optional A-Z or 0-9 to list only that letter.
 * @return array{terms: array, total: int, error: mixed} Terms, the total term
 *         count, and any WP_Error from the underlying call.
 */
function majestic_tube_get_term_directory( $taxonomy, $per_page, $page = 1, $letter = '' ) {
	$taxonomy = sanitize_key( $taxonomy );
	$per_page = max( 1, absint( $per_page ) );
	$page     = max( 1, absint( $page ) );
	$letter   = preg_match( '/^[A-Z0-9]$/', strtoupper( (string) $letter ) ) ? strtoupper( (string) $letter ) : '';

	if ( ! taxonomy_exists( $taxonomy ) ) {
		return array(
			'terms' => array(),
			'total' => 0,
			'error' => new WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy.', 'majestic-tube' ) ),
		);
	}

	$last_changed = wp_cache_get( 'last_changed', 'terms' );
	$cache_key    = sprintf(
		'%s_%d_%d_%s%s',
		$taxonomy,
		$per_page,
		$page,
		preg_replace( '/[^A-Za-z0-9_.:-]/', '', (string) $last_changed ),
		$letter ? '_letter_' . $letter : ''
	);

	$cached = wp_cache_get( $cache_key, MAJESTIC_TUBE_TERM_CACHE_GROUP );

	if ( is_array( $cached ) && isset( $cached['terms'], $cached['total'] ) ) {
		return $cached;
	}

	// Resolving the letter to concrete IDs, rather than filtering in SQL, keeps
	// the alphabet filter working on every supported WordPress version without a
	// custom WHERE clause, and reuses the bucket the alphabet bar already built.
	$letter_ids = array();

	if ( $letter ) {
		$map        = majestic_tube_get_term_letter_map( $taxonomy );
		$letter_ids = isset( $map[ $letter ] ) ? $map[ $letter ] : array();
	}

	$hide_empty = majestic_tube_term_directory_hide_empty( $taxonomy, $letter );

	$args = array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => $hide_empty,
		'number'     => $per_page,
		'offset'     => ( $page - 1 ) * $per_page,
		'orderby'    => 'name',
		'order'      => 'ASC',
	);

	if ( $letter ) {
		// A letter with no terms is an empty result, not the unfiltered
		// directory - passing an empty include would list everything.
		$args['include'] = $letter_ids ? $letter_ids : array( 0 );
	}

	/**
	 * Filter the arguments used to list a term directory.
	 *
	 * @param array  $args     Arguments passed to get_terms().
	 * @param string $taxonomy Taxonomy being listed.
	 * @param int    $per_page Terms per page.
	 * @param int    $page     Current page.
	 * @param string $letter   Active letter filter, or an empty string.
	 */
	$args = apply_filters( 'majestic_tube_term_directory_args', $args, $taxonomy, $per_page, $page, $letter );

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
			'hide_empty' => $hide_empty,
		);

		if ( $letter ) {
			$count_args['include'] = $letter_ids ? $letter_ids : array( 0 );
		}

		/**
		 * Filter the arguments used to count a term directory.
		 *
		 * @param array  $count_args Count arguments.
		 * @param string $taxonomy   Taxonomy being listed.
		 * @param int    $per_page   Terms per page.
		 * @param int    $page       Current page.
		 * @param string $letter     Active letter filter, or an empty string.
		 */
		$count_args = apply_filters( 'majestic_tube_term_directory_count_args', $count_args, $taxonomy, $per_page, $page, $letter );

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

/**
 * Read the requested alphabet letter for a term directory.
 *
 * The Tags and Actors pages accept a `letter` query argument so a visitor can
 * jump straight to one letter instead of paging through the whole directory.
 * Only a single alphanumeric character is accepted; anything else - including
 * an array, which `?letter[]=a` produces - is discarded and the full
 * alphabetical listing is shown.
 *
 * @return string Uppercase A-Z or 0-9, or an empty string for "all".
 */
function majestic_tube_get_requested_letter() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only browse filter on a public directory, and the value is sanitised to one alphanumeric character below.
	if ( empty( $_GET['letter'] ) || ! is_string( $_GET['letter'] ) ) {
		return '';
	}

	$letter = strtoupper( sanitize_text_field( wp_unslash( $_GET['letter'] ) ) );

	return preg_match( '/^[A-Z0-9]$/', $letter ) ? $letter : '';
}

/**
 * Whether term directories should skip terms that have no posts.
 *
 * Shared by the listing and the alphabet bar on purpose. If the two resolved
 * this separately the bar could advertise a letter with a count of 12 and then
 * link to a page listing 3 terms, because the bar counted empty terms the
 * listing had hidden.
 *
 * Empty terms are hidden by default. The original theme listed them, but a
 * directory of terms with nothing behind them is dead weight on every page that
 * links to it, and bulk imports leave a lot of them behind. The filter stays so
 * a site that wants the original behaviour can return false.
 *
 * @param string $taxonomy Taxonomy name.
 * @param string $letter   Active letter filter, or an empty string.
 * @return bool
 */
function majestic_tube_term_directory_hide_empty( $taxonomy, $letter = '' ) {
	/**
	 * Filter whether a term directory hides terms that have no posts.
	 *
	 * @param bool   $hide_empty Whether to hide terms with no posts.
	 * @param string $taxonomy   Taxonomy being listed.
	 * @param string $letter     Active letter filter, or an empty string.
	 */
	return (bool) apply_filters( 'majestic_tube_term_directory_hide_empty', true, $taxonomy, $letter );
}

/**
 * Map each starting letter to the term IDs filed under it, cached.
 *
 * This backs both the alphabet bar and the letter filter, and it is the reason
 * neither needs a query the other cannot serve.
 *
 * The lookup is one get_terms() of the taxonomy's term names. The naive
 * alternative - asking the database how many terms start with each letter -
 * costs 36 queries for A-Z plus 0-9, and "how many" is not even the number the
 * bar shows, because the bar should only offer letters that lead somewhere.
 * Reading the names once and bucketing them in PHP gives the exact IDs the
 * filter needs as well as the counts the bar needs.
 *
 * Results are cached under the same `last_changed` marker the term directory
 * itself uses, so creating, renaming or deleting a term invalidates this
 * exactly as it invalidates the listing.
 *
 * Terms whose name does not begin with a letter or digit are omitted: there is
 * no honest letter to file them under, and they remain reachable through the
 * unfiltered listing.
 *
 * @param string $taxonomy Taxonomy name.
 * @return array<string, int[]> Letter => term IDs, keys in alphabetical order.
 */
function majestic_tube_get_term_letter_map( $taxonomy ) {
	$taxonomy = sanitize_key( $taxonomy );

	if ( ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}

	$last_changed = wp_cache_get( 'last_changed', 'terms' );
	$cache_key    = sprintf(
		'letters_%s_%s',
		$taxonomy,
		preg_replace( '/[^A-Za-z0-9_.:-]/', '', (string) $last_changed )
	);

	$cached = wp_cache_get( $cache_key, MAJESTIC_TUBE_TERM_CACHE_GROUP );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			// Must match what the listing will show, or the bar's counts lie.
			'hide_empty' => majestic_tube_term_directory_hide_empty( $taxonomy ),
			// Only the name is needed to bucket terms by letter, so this stays a
			// cheap id=>name lookup rather than a full term object per row.
			'fields'     => 'id=>name',
		)
	);

	$map = array();

	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$letter = majestic_tube_term_initial( $term->name );

			if ( '' === $letter ) {
				continue;
			}

			if ( ! isset( $map[ $letter ] ) ) {
				$map[ $letter ] = array();
			}

			$map[ $letter ][] = (int) $term->term_id;
		}
	}

	ksort( $map );

	wp_cache_set( $cache_key, $map, MAJESTIC_TUBE_TERM_CACHE_GROUP, MAJESTIC_TUBE_TERM_CACHE_TTL );

	return $map;
}

/**
 * The letters that actually have terms, for rendering an alphabet bar.
 *
 * Counts only, so a template can print the bar without caring about IDs.
 *
 * @param string $taxonomy Taxonomy name.
 * @return array<string, int> Letter => number of terms, in alphabetical order.
 */
function majestic_tube_get_term_letters( $taxonomy ) {
	$counts = array();

	foreach ( majestic_tube_get_term_letter_map( $taxonomy ) as $letter => $ids ) {
		$counts[ $letter ] = count( $ids );
	}

	return $counts;
}

/**
 * The single character a term name files under in the alphabet bar.
 *
 * A name counts under the first letter of its first word, so "AnnaBelle" files
 * under A rather than being split. Names that do not start with a letter or a
 * digit - a term that is only punctuation, say - return an empty string and are
 * left out of the bar, because there is no honest letter to file them under.
 *
 * @param string $name Term name.
 * @return string Uppercase A-Z or 0-9, or an empty string.
 */
function majestic_tube_term_initial( $name ) {
	$name = trim( wp_strip_all_tags( (string) $name ) );

	if ( '' === $name ) {
		return '';
	}

	// Take the first character of the first word.
	if ( function_exists( 'mb_substr' ) ) {
		$first = mb_substr( $name, 0, 1, 'UTF-8' );
	} else {
		$first = substr( $name, 0, 1 );
	}

	$first = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $first, 'UTF-8' ) : strtoupper( $first );

	return preg_match( '/^[A-Z0-9]$/', $first ) ? $first : '';
}

/**
 * Print the A-Z bar for a term directory.
 *
 * Renders "All" followed by one link per letter that actually has terms, with
 * the active letter marked. Each link carries the letter as a query argument
 * rather than as a path segment, because these directories are page templates:
 * the page segment is already carrying `/page/N/`, and a second rewrite rule
 * would collide with it. Pagination links keep the argument, so moving between
 * pages does not silently drop the filter.
 *
 * @param string $taxonomy Taxonomy name.
 * @return void
 */
function majestic_tube_term_letter_nav( $taxonomy ) {
	$letters = majestic_tube_get_term_letters( $taxonomy );

	// Nothing to navigate between on a directory with no terms at all.
	if ( ! $letters ) {
		return;
	}

	$current = majestic_tube_get_requested_letter();
	$base    = majestic_tube_term_directory_base_url();
	?>
	<nav class="term-letter-nav" aria-label="<?php esc_attr_e( 'Browse by letter', 'majestic-tube' ); ?>">
		<ul>
			<li>
				<a class="term-letter<?php echo '' === $current ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( $base ); ?>"
					<?php echo '' === $current ? ' aria-current="true"' : ''; ?>><?php esc_html_e( 'All', 'majestic-tube' ); ?></a>
			</li>
			<?php foreach ( $letters as $letter => $count ) : ?>
				<li>
					<a class="term-letter<?php echo $current === $letter ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( add_query_arg( 'letter', rawurlencode( $letter ), $base ) ); ?>"
						<?php echo $current === $letter ? ' aria-current="true"' : ''; ?>>
						<?php echo esc_html( $letter ); ?>
						<span class="term-letter-count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php
}

/**
 * The directory URL that letter links are built from.
 *
 * Must be the unfiltered page-one URL, with any existing `letter` and page
 * segment removed, so that "All" really does return to the full directory and
 * picking a letter does not inherit the page number from the link that was
 * followed to get here.
 *
 * @return string
 */
function majestic_tube_term_directory_base_url() {
	$url = remove_query_arg( array( 'letter', 'paged', 'page' ) );

	/**
	 * Filter the base URL the term directory alphabet bar links from.
	 *
	 * @param string $url Base directory URL.
	 */
	return apply_filters( 'majestic_tube_term_directory_base_url', $url );
}
