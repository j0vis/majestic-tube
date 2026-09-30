<?php
/**
 * Facet metadata: titles, descriptions, headings, robots, canonical and schema.
 *
 * The formulas here are the part of programmatic SEO that most often gets
 * fumbled, so the rules they encode are worth stating:
 *
 * - The distinguishing variable leads. "{Actor} in {Category}" beats
 *   "Videos in {Category} featuring {Actor}".
 * - Titles stay under sixty characters by truncating the TAIL variable and
 *   never the head, because the head is what earns the click.
 * - Descriptions carry a measured value (runtime, count, span) rather than an
 *   adjective, which is the difference between copy and filler.
 * - A facet below the index gate is `noindex, follow`, not `noindex, nofollow`
 *   and never a redirect: it still passes PageRank onward, and it is still a
 *   working page for a visitor who arrived from a search that did not need it
 *   to be indexed.
 *
 * @package Majestic Tube
 * @version 2.2.22
 */

defined( 'ABSPATH' ) || exit;

/**
 * The URL for a facet.
 *
 * The single source of truth for facet URLs. Templates, breadcrumbs, the
 * sibling lists, the browse index and the sitemap all build their links here,
 * so a facet cannot be reachable at two addresses.
 *
 * @param string       $type Facet type key.
 * @param WP_Term      $term Primary term.
 * @param WP_Term|null $term2 Secondary term.
 * @param string       $band Duration band key.
 * @return string
 */
function majestic_tube_facet_url( $type, $term, $term2 = null, $band = '' ) {
	$base = home_url( '/' );

	/*
	 * Duck-typed on purpose rather than `instanceof WP_Term`: a filter is
	 * allowed to hand this function any object carrying a slug, and an
	 * instanceof check would fatal on it instead of using it. The same
	 * applies to $term2 below.
	 */
	$slug = ( is_object( $term ) && isset( $term->slug ) ) ? (string) $term->slug : sanitize_title( (string) $term );
	$url  = '';

	switch ( $type ) {
		case 'actor_category':
			$second = ( is_object( $term2 ) && isset( $term2->slug ) ) ? (string) $term2->slug : '';
			$url    = $base . 'actor/' . rawurlencode( $slug ) . '/' . ( $second ? rawurlencode( $second ) . '/' : '' );
			break;

		case 'actor_actor':
			$second = ( is_object( $term2 ) && isset( $term2->slug ) ) ? (string) $term2->slug : '';
			$url    = $base . 'actor/' . rawurlencode( $slug ) . '/with/' . ( $second ? rawurlencode( $second ) . '/' : '' );
			break;

		case 'actor_length':
			$url = $base . 'actor/' . rawurlencode( $slug ) . '/length/' . sanitize_key( $band ) . '/';
			break;

		case 'category_tag':
			$second = ( is_object( $term2 ) && isset( $term2->slug ) ) ? (string) $term2->slug : '';
			$url    = $base . 'category/' . rawurlencode( $slug ) . '/' . ( $second ? rawurlencode( $second ) . '/' : '' );
			break;

		case 'studio':
		case 'series':
			$url = $base . $type . '/' . rawurlencode( $slug ) . '/';
			break;
	}

	/**
	 * Filter a facet URL.
	 *
	 * @param string $url  Facet URL.
	 * @param string $type Facet type key.
	 */
	return (string) apply_filters( 'majestic_tube_facet_url', $url, $type );
}

/**
 * The page title for a facet, built to sixty characters.
 *
 * @param array $facet Output of majestic_tube_current_facet().
 * @return string
 */
function majestic_tube_facet_title( $facet ) {
	if ( ! is_array( $facet ) ) {
		return '';
	}

	$type = $facet['type'];
	$name = $facet['term']->name;
	$name2 = ( isset( $facet['term2'] ) && $facet['term2'] ) ? $facet['term2']->name : '';
	$site = get_bloginfo( 'name' );
	$count = (int) $facet['count'];

	$title = '';

	switch ( $type ) {
		case 'actor_category':
			$title = ( $name2 )
				/* translators: 1: actor name, 2: category name. */
				? sprintf( __( '%1$s in %2$s Videos', 'majestic-tube' ), $name, $name2 )
				/* translators: %s: actor name. */
				: sprintf( __( '%s Videos', 'majestic-tube' ), $name );
			break;

		case 'actor_actor':
			$title = ( $name2 )
				/* translators: 1: first actor, 2: second actor. */
				? sprintf( __( '%1$s with %2$s', 'majestic-tube' ), $name, $name2 )
				: sprintf( __( '%s Pairings', 'majestic-tube' ), $name );
			break;

		case 'actor_length':
			$bands = majestic_tube_length_bands();
			$band_label = isset( $bands[ $facet['band'] ] ) ? $bands[ $facet['band'] ]['label'] : '';

			$title = ( $band_label )
				/* translators: 1: actor name, 2: duration band label. */
				? sprintf( __( '%1$s: %2$s Videos', 'majestic-tube' ), $name, $band_label )
				: sprintf( __( '%s Videos', 'majestic-tube' ), $name );
			break;

		case 'category_tag':
			$title = ( $name2 )
				/* translators: 1: category, 2: tag. */
				? sprintf( __( '%1$s Videos Tagged %2$s', 'majestic-tube' ), $name, $name2 )
				: sprintf( __( '%s Videos', 'majestic-tube' ), $name );
			break;

		case 'studio':
			$title = /* translators: %s: studio name. */
				sprintf( __( '%s Videos', 'majestic-tube' ), $name );
			break;

		case 'series':
			$title = /* translators: %s: series name. */
				sprintf( __( '%s Episodes', 'majestic-tube' ), $name );
			break;
	}

	// A count is the strongest long-tail payload a listing can carry, but it
	// is appended last so it is always the thing truncated.
	$with_count = trim( $title . ' ' . sprintf(
		/* translators: %s: number of videos. */
		__( '(%s)', 'majestic-tube' ),
		number_format_i18n( $count )
	) );

	$title = mb_strlen( $with_count ) <= 60 ? $with_count : $title;

	// A short title still earns the site name; a long one already carries a
	// distinguishing variable and does not need it.
	if ( mb_strlen( $title ) <= 48 && $site ) {
		$title .= ' | ' . $site;
	}

	/**
	 * Filter a facet page title.
	 *
	 * @param string $title Page title.
	 * @param array  $facet Facet descriptor.
	 */
	return (string) apply_filters( 'majestic_tube_facet_title', $title, $facet );
}

/**
 * The meta description for a facet.
 *
 * @param array $facet Output of majestic_tube_current_facet().
 * @return string
 */
function majestic_tube_facet_description( $facet ) {
	if ( ! is_array( $facet ) ) {
		return '';
	}

	$type  = $facet['type'];
	$name  = $facet['term']->name;
	$name2 = ( isset( $facet['term2'] ) && $facet['term2'] ) ? $facet['term2']->name : '';
	$count = (int) $facet['count'];
	$site  = get_bloginfo( 'name' );

	$stats = majestic_tube_facet_stats( $type, $facet['term'], isset( $facet['term2'] ) ? $facet['term2'] : null, $facet['band'] );

	$runtime = isset( $stats['runtime_label'] ) ? $stats['runtime_label'] : '';
	$span    = isset( $stats['span_label'] ) ? $stats['span_label'] : '';

	$description = '';

	switch ( $type ) {
		case 'actor_category':
			$description = ( $name2 )
				/* translators: 1: video count, 2: actor, 3: category, 4: site name, 5: runtime. */
				? sprintf( __( 'Watch %1$s videos of %2$s in %3$s on %4$s. %5$s Browse the full collection in HD.', 'majestic-tube' ), number_format_i18n( $count ), $name, $name2, $site, $runtime ? $runtime . ' of footage.' : '' )
				: '';
			break;

		case 'actor_actor':
			$description = ( $name2 )
				/* translators: 1: first actor, 2: second actor, 3: video count, 4: site. */
				? sprintf( __( '%1$s and %2$s together in %3$s videos. Watch free on %4$s in HD.', 'majestic-tube' ), $name, $name2, number_format_i18n( $count ), $site )
				: '';
			break;

		case 'actor_length':
			$bands = majestic_tube_length_bands();
			$band_label = isset( $bands[ $facet['band'] ] ) ? $bands[ $facet['band'] ]['label'] : '';

			$description = ( $band_label && $runtime )
				/* translators: 1: actor, 2: band, 3: count, 4: runtime, 5: site. */
				? sprintf( __( '%1$s: %2$s videos, %4$s in total. Watch all %3$s on %5$s in HD.', 'majestic-tube' ), $name, mb_strtolower( $band_label ), number_format_i18n( $count ), $runtime, $site )
				: '';
			break;

		case 'category_tag':
			$description = ( $name2 )
				/* translators: 1: count, 2: category, 3: tag, 4: site. */
				? sprintf( __( '%1$s %2$s videos tagged %3$s, collected on %4$s. Free HD playback.', 'majestic-tube' ), number_format_i18n( $count ), mb_strtolower( $name ), $name2, $site )
				: '';
			break;

		case 'studio':
			$description = sprintf(
				/* translators: 1: studio, 2: count, 3: runtime, 4: site. */
				__( 'Every %1$s video in one place: %2$s scenes, %3$s runtime. Watch on %4$s.', 'majestic-tube' ),
				$name,
				number_format_i18n( $count ),
				$runtime ? $runtime : __( 'full length', 'majestic-tube' ),
				$site
			);
			break;

		case 'series':
			$description = sprintf(
				/* translators: 1: series, 2: count, 3: site. */
				__( '%1$s: %2$s episodes to watch in order. Full collection on %3$s.', 'majestic-tube' ),
				$name,
				number_format_i18n( $count ),
				$site
			);
			break;
	}

	if ( '' === trim( (string) $description ) ) {
		$description = sprintf(
			/* translators: 1: count, 2: name, 3: site. */
			__( 'Watch %1$s videos of %2$s on %3$s. Free HD playback, updated as new videos are added.', 'majestic-tube' ),
			number_format_i18n( $count ),
			$name,
			$site
		);
	}

	// A trailing date is the cheapest CTR lever available, but only when we
	// have a real one to quote.
	if ( $span && strlen( $description ) < 110 ) {
		$description .= ' ' . ucfirst( $span ) . '.';
	}

	$description = trim( mb_substr( $description, 0, 158 ) );

	/**
	 * Filter a facet meta description.
	 *
	 * @param string $description Meta description.
	 * @param array  $facet       Facet descriptor.
	 */
	return (string) apply_filters( 'majestic_tube_facet_description', $description, $facet );
}

/**
 * The H1 for a facet page.
 *
 * One H1, and it is the primary variable. "Videos" alone is a wasted heading:
 * it matches nothing a visitor would type and tells a crawler nothing either.
 *
 * @param array $facet Output of majestic_tube_current_facet().
 * @return string
 */
function majestic_tube_facet_heading( $facet ) {
	if ( ! is_array( $facet ) ) {
		return '';
	}

	$type  = $facet['type'];
	$name  = $facet['term']->name;
	$name2 = ( isset( $facet['term2'] ) && $facet['term2'] ) ? $facet['term2']->name : '';

	switch ( $type ) {
		case 'actor_category':
			$heading = ( $name2 )
				/* translators: 1: actor name, 2: category name. */
				? sprintf( __( '%1$s in %2$s', 'majestic-tube' ), $name, $name2 )
				: $name;
			break;

		case 'actor_actor':
			$heading = ( $name2 )
				/* translators: 1: first actor, 2: second actor. */
				? sprintf( __( '%1$s with %2$s', 'majestic-tube' ), $name, $name2 )
				: $name;
			break;

		case 'actor_length':
			$bands = majestic_tube_length_bands();
			$band_label = isset( $bands[ $facet['band'] ] ) ? $bands[ $facet['band'] ]['label'] : '';
			$heading = ( $band_label )
				/* translators: 1: actor name, 2: band label. */
				? sprintf( __( '%1$s: %2$s Videos', 'majestic-tube' ), $name, $band_label )
				: $name;
			break;

		case 'category_tag':
			$heading = ( $name2 )
				/* translators: 1: category, 2: tag. */
				? sprintf( __( '%1$s: %2$s', 'majestic-tube' ), $name, $name2 )
				: $name;
			break;

		default:
			$heading = $name;
			break;
	}

	/**
	 * Filter a facet H1.
	 *
	 * @param string $heading H1 text.
	 * @param array  $facet   Facet descriptor.
	 */
	return (string) apply_filters( 'majestic_tube_facet_heading', $heading, $facet );
}

/**
 * Replace the document title on a facet page.
 *
 * @param string $title Default title.
 * @return string
 */
function majestic_tube_facet_document_title( $title ) {
	if ( ! majestic_tube_is_facet_request() ) {
		return $title;
	}

	$facet = majestic_tube_current_facet();

	if ( ! $facet ) {
		return $title;
	}

	$built = majestic_tube_facet_title( $facet );

	return $built ? $built : $title;
}
add_filter( 'pre_get_document_title', 'majestic_tube_facet_document_title', 20 );

/**
 * Print the facet meta description.
 *
 * @return void
 */
function majestic_tube_facet_meta_description() {
	if ( ! majestic_tube_is_facet_request() || ! is_singular() ) {
		return;
	}

	$facet = majestic_tube_current_facet();

	if ( ! $facet ) {
		return;
	}

	$description = majestic_tube_facet_description( $facet );

	if ( ! $description ) {
		return;
	}

	printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );
}
add_action( 'wp_head', 'majestic_tube_facet_meta_description', 3 );

/**
 * Point the canonical at the clean facet URL.
 *
 * Without this a facet reachable through several rewrite spellings can be
 * indexed under whichever one a crawler happened to land on, which splits its
 * own history across the addresses it was meant to consolidate.
 *
 * @return void
 */
function majestic_tube_facet_canonical() {
	if ( ! is_singular() ) {
		return;
	}

	$url = '';

	if ( majestic_tube_is_facet_request() ) {
		$facet = majestic_tube_current_facet();

		if ( $facet ) {
			$url = majestic_tube_facet_url( $facet['type'], $facet['term'], isset( $facet['term2'] ) ? $facet['term2'] : null, $facet['band'] );
		}
	} elseif ( majestic_tube_is_browse_request() ) {
		$url = ( get_query_var( 'paged' ) > 1 ) ? home_url( '/browse/page/' . absint( get_query_var( 'paged' ) ) . '/' ) : home_url( '/browse/' );
	}

	if ( ! $url ) {
		return;
	}

	printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $url ) );
}
add_action( 'wp_head', 'majestic_tube_facet_canonical', 4 );

/**
 * Noindex the facets that did not clear the gate, and the deep browse pages.
 *
 * `follow` is deliberate and load-bearing. A thin facet is not a bad page; it
 * is a page that is not worth a ranking. Following keeps its links flowing,
 * so a visitor arriving from a long-tail query still walks onward through the
 * hierarchy instead of hitting a dead end.
 *
 * @param array $robots Robots directives.
 * @return array
 */
function majestic_tube_facet_robots( $robots ) {
	// Paginated anything is never a ranking target; core already handles
	// paged archives, and the browse spine needs the same treatment.
	if ( is_paged() ) {
		$robots['noindex']  = true;
		$robots['follow']   = true;
		$robots['max-snippet'] = -1;

		return $robots;
	}

	if ( majestic_tube_is_browse_request() ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;

		return $robots;
	}

	if ( ! majestic_tube_is_facet_request() ) {
		return $robots;
	}

	$facet = majestic_tube_current_facet();

	if ( $facet && ! $facet['indexable'] ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}

	/**
	 * Filter the robots directives for a facet page.
	 *
	 * @param array $robots Directives.
	 * @param array $facet  Facet descriptor, or an empty array.
	 */
	return (array) apply_filters( 'majestic_tube_facet_robots', $robots, $facet ? $facet : array() );
}
add_filter( 'wp_robots', 'majestic_tube_facet_robots', 20 );

/**
 * Print CollectionPage schema for a facet.
 *
 * CollectionPage rather than ItemList, and separate from the VideoObject the
 * videos themselves emit: the facet is a collection, and declaring it as one
 * lets Google read the entity relationships the facet expresses - which term
 * covers which videos - instead of guessing them from anchor text.
 *
 * @return void
 */
function majestic_tube_facet_schema() {
	if ( ! majestic_tube_is_facet_request() || ! is_singular() || ! majestic_tube_should_output_schema() ) {
		return;
	}

	$facet = majestic_tube_current_facet();

	if ( ! $facet ) {
		return;
	}

	$schema = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'CollectionPage',
		'name'        => majestic_tube_facet_heading( $facet ),
		'url'         => majestic_tube_facet_url( $facet['type'], $facet['term'], isset( $facet['term2'] ) ? $facet['term2'] : null, $facet['band'] ),
		'isPartOf'    => array(
			'@type' => 'WebSite',
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		),
		'mainEntity'  => array(
			'@type'       => 'ItemList',
			'numberOfItems' => (int) $facet['count'],
		),
	);

	$description = majestic_tube_facet_description( $facet );

	if ( $description ) {
		$schema['description'] = $description;
	}

	/**
	 * Filter the CollectionPage graph for a facet.
	 *
	 * @param array $schema Schema graph.
	 * @param array $facet  Facet descriptor.
	 */
	$schema = (array) apply_filters( 'majestic_tube_facet_schema', $schema, $facet );

	$json = wp_json_encode( $schema, JSON_UNESCAPED_UNICODE );

	if ( ! $json ) {
		return;
	}

	printf( '<script type="application/ld+json">%s</script>' . "\n", $json // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON payload from wp_json_encode().
	);
}
add_action( 'wp_head', 'majestic_tube_facet_schema', 6 );
