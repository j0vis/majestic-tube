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
 * @version 2.2.10
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
 * A per_page of 0 means "every term on one page". The tags directory is
 * listed that way: a tag is a small label, the cloud is a flat list, and
 * splitting it across pages only hides tags from someone looking for one.
 *
 * @param string $taxonomy Taxonomy name.
 * @param int    $per_page Terms per page, or 0 for every term on one page.
 * @param int    $page     1-based page number.
 * @param string $letter   Optional A-Z or 0-9 to list only that letter.
 * @return array{terms: array, total: int, error: mixed} Terms, the total term
 *         count, and any WP_Error from the underlying call.
 */
function majestic_tube_get_term_directory( $taxonomy, $per_page, $page = 1, $letter = '' ) {
	$taxonomy = sanitize_key( $taxonomy );
	$per_page = absint( $per_page );
	$page     = max( 1, absint( $page ) );
	$letter   = preg_match( '/^[A-Z0-9]$/', strtoupper( (string) $letter ) ) ? strtoupper( (string) $letter ) : '';

	// 0 is the caller's way of saying "do not paginate this directory", not a
	// request for a single term, so it has to survive the normalisation.
	$unlimited = ( 0 === $per_page );
	$per_page  = $unlimited ? 0 : max( 1, $per_page );

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
		'offset'     => $unlimited ? 0 : ( $page - 1 ) * $per_page,
		'orderby'    => 'name',
		'order'      => 'ASC',
	);

	if ( $unlimited ) {
		// get_terms() reads `number` 0 as "no limit", which is the same request.
		$args['number'] = 0;
	}

	if ( $letter ) {
		// A letter with no terms is an empty result, not the unfiltered
		// directory - passing an empty include would list everything.
		$args['include'] = $letter_ids ? $letter_ids : array( 0 );

		/*
		 * Choosing a letter IS the navigation, so it lists every term under
		 * that letter on one page. Paging a slice of a single letter on top of
		 * a bar that already picked the letter is two controls doing one job,
		 * and it is what made a working letter look like it had loaded
		 * nothing when the interesting terms were on page two.
		 */
		$args['number'] = 0;
		$args['offset'] = 0;
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
 * A per_page of 0 means this directory is not paginated - the tags page passes
 * that - so nothing is printed. The guard is here rather than left to the
 * caller simply because `max( 1, 0 )` would otherwise read as one term per
 * page and offer to page through every single tag on the site.
 *
 * @param int $total_terms Total number of terms.
 * @param int $per_page    Terms per page, or 0 for no pagination.
 * @return void
 */
function majestic_tube_term_pagination( $total_terms, $per_page ) {
	$per_page = absint( $per_page );

	if ( $per_page < 1 ) {
		return;
	}
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

	$last_changed = (string) wp_cache_get( 'last_changed', 'terms' );
	$cache_key    = 'majestic_tube_letters_' . $taxonomy;
	$fast_key     = $cache_key . '_' . preg_replace( '/[^A-Za-z0-9_.:-]/', '', $last_changed );

	$cached = wp_cache_get( $fast_key, MAJESTIC_TUBE_TERM_CACHE_GROUP );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	/*
	 * A directory of several hundred tags is a real id=>name read, and the
	 * object cache above only survives the request when a persistent object
	 * cache happens to be installed. On an ordinary install that meant the
	 * lookup ran again on every single page view, which is the database
	 * burden this bar is supposed to avoid.
	 *
	 * The stored copy carries the last_changed marker it was built from.
	 * WordPress bumps that whenever any term is created, renamed or deleted,
	 * so the copy cannot go stale without this module hooking anything, and a
	 * bulk import invalidates it just as reliably as an edit in the admin.
	 */
	$stored = get_option( $cache_key );

	if ( is_array( $stored ) && isset( $stored['last_changed'], $stored['map'] ) && $stored['last_changed'] === $last_changed ) {
		$map = is_array( $stored['map'] ) ? $stored['map'] : array();

		wp_cache_set( $fast_key, $map, MAJESTIC_TUBE_TERM_CACHE_GROUP, MAJESTIC_TUBE_TERM_CACHE_TTL );

		return $map;
	}

	$map = majestic_tube_build_term_letter_map( $taxonomy );

	update_option( $cache_key, array( 'last_changed' => $last_changed, 'map' => $map ), false );

	wp_cache_set( $fast_key, $map, MAJESTIC_TUBE_TERM_CACHE_GROUP, MAJESTIC_TUBE_TERM_CACHE_TTL );

	return $map;
}

/**
 * Read every term name in a taxonomy and bucket the terms by first letter.
 *
 * @param string $taxonomy Taxonomy name.
 * @return array<string, int[]> Letter => term IDs, keys in alphabetical order.
 */
function majestic_tube_build_term_letter_map( $taxonomy ) {
	$hide_empty = majestic_tube_term_directory_hide_empty( $taxonomy );
	$map        = array();

	/*
	 * This is read straight from the term and term_taxonomy tables rather
	 * than through get_terms( 'fields' => 'id=>name' ).
	 *
	 * It has to be. The letter map is what the whole alphabet feature stands
	 * on: the bar's counts come from it, and clicking a letter resolves to the
	 * term IDs it holds. When that read came back empty, the bar had no
	 * letters to draw AND every letter it did draw resolved to an empty ID
	 * set, so the listing underneath went blank. Two visible symptoms, one
	 * cause, and a query that returned no rows could not tell anyone which.
	 *
	 * One SELECT of three columns over the taxonomy is the cheapest reliable
	 * read available, it carries the term count so hide_empty can be applied
	 * without a second query, and it cannot be defeated by a fields or
	 * hide_empty combination the way the API query could.
	 */
	global $wpdb;

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT t.term_id, t.name, tt.count
			 FROM {$wpdb->terms} t
			 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
			 WHERE tt.taxonomy = %s",
			$taxonomy
		)
	);

	if ( ! is_array( $rows ) ) {
		// A direct query refused, most often by a host that disallows them.
		// Fall back to the API rather than showing a directory with no bar.
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => $hide_empty,
				'fields'     => 'id=>name',
			)
		);

		$rows = array();

		foreach ( ( is_wp_error( $terms ) ? array() : (array) $terms ) as $term ) {
			if ( isset( $term->term_id, $term->name ) ) {
				$rows[] = (object) array(
					'term_id' => $term->term_id,
					'name'    => $term->name,
					'count'   => 1,
				);
			}
		}
	}

	foreach ( $rows as $row ) {
		// Keep in step with the listing: a term with no videos is not offered
		// by the bar either, or the two would disagree.
		if ( $hide_empty && isset( $row->count ) && (int) $row->count < 1 ) {
			continue;
		}

		$letter = majestic_tube_term_initial( $row->name );

		if ( '' === $letter ) {
			continue;
		}

		if ( ! isset( $map[ $letter ] ) ) {
			$map[ $letter ] = array();
		}

		$map[ $letter ][] = (int) $row->term_id;
	}

	ksort( $map );

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
}/**
 * Print the A-Z bar for a term directory.
 *
 * Only letters that lead somewhere are printed. A letter with no terms behind
 * it is left off the bar entirely rather than shown dimmed: a chip that cannot
 * be clicked is dead weight in the row, and on a directory where only a dozen
 * letters are ever used the other two dozen chips pushed the useful ones onto
 * a second line for no benefit. The bar is therefore as short as the data
 * makes it, and "All" is always present so a visitor on a filtered view can
 * get back to the full directory.
 *
 * The letter carries no count. The number of terms under a letter is a fact
 * about the directory rather than something a visitor picks between, and on a
 * narrow screen the badge was wide enough to wrap the row onto a second line
 * by itself.
 *
 * Each link carries the letter as a query argument rather than as a path
 * segment, because these directories are page templates: the page segment is
 * already carrying `/page/N/`, and a second rewrite rule would collide with
 * it.
 *
 * @param string $taxonomy Taxonomy name.
 * @return void
 */
function majestic_tube_term_letter_nav( $taxonomy ) {
	$letters = majestic_tube_get_term_letters( $taxonomy );
	$current = majestic_tube_get_requested_letter();
	$base    = majestic_tube_term_directory_base_url();

	// Reading order, letters first. Digits sit last because on a video site
	// they are rare, and "4K" or "18" filed under a digit should not push the
	// letters a visitor actually came for off the row.
	$all_letters = array_merge( range( 'A', 'Z' ), range( 0, 9 ) );

	$available = array();

	foreach ( $all_letters as $letter ) {
		if ( ! empty( $letters[ $letter ] ) ) {
			$available[] = $letter;
		}
	}

	// Nothing to sort by. Printing a bar with only "All" on it would be a
	// control that does nothing, so the whole nav is skipped.
	if ( ! $available ) {
		return;
	}
	?>
	<nav class="term-letter-nav" aria-label="<?php esc_attr_e( 'Browse by letter', 'majestic-tube' ); ?>">
		<ul>
			<li>
				<a class="term-letter<?php echo '' === $current ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( $base ); ?>"
					<?php echo '' === $current ? ' aria-current="true"' : ''; ?>><?php esc_html_e( 'All', 'majestic-tube' ); ?></a>
			</li>
			<?php foreach ( $available as $letter ) : ?>
				<li>
					<a class="term-letter<?php echo $current === $letter ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( add_query_arg( 'letter', rawurlencode( $letter ), $base ) ); ?>"
						<?php echo $current === $letter ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $letter ); ?></a>
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
