<?php
/**
 * Template tags - WP-Script compatible helpers.
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Canonical video resolution keys and player labels, highest quality first.
 *
 * @return array<string, string>
 */
function majestic_tube_video_resolutions() {
	return array(
		'video_url_4k'   => '4k',
		'video_url_1080' => '1080p',
		'video_url_720'  => '720p',
		'video_url_480'  => '480p',
		'video_url_360'  => '360p',
		'video_url_240'  => '240p',
	);
}

/**
 * Get the best available video source for the player.
 *
 * Resolution priority matches the original: full video_url first, then the
 * highest available resolution file, then embed code, then shortcode.
 *
 * @param int $post_id Post ID.
 * @return array{type:string, sources:array<string,string>, embed:string, shortcode:string}|null
 */
function majestic_tube_get_video_sources( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();

	if ( ! $post_id ) {
		return null;
	}

	$resolutions = majestic_tube_video_resolutions();
	$sources     = array();

	foreach ( array_keys( $resolutions ) as $key ) {
		$url = get_post_meta( $post_id, $key, true );
		if ( $url ) {
			$sources[ $key ] = $url;
		}
	}

	$main_url = get_post_meta( $post_id, 'video_url', true );

	$data = array(
		'type'      => 'none',
		'sources'   => $sources,
		'main'      => $main_url,
		'embed'     => get_post_meta( $post_id, 'embed', true ),
		'shortcode' => get_post_meta( $post_id, 'shortcode', true ),
	);

	if ( $main_url || $sources ) {
		$data['type'] = 'self-hosted';
	} elseif ( $data['embed'] ) {
		$data['type'] = 'embed';
	} elseif ( $data['shortcode'] ) {
		$data['type'] = 'shortcode';
	}

	return $data;
}

/**
 * Human label for a resolution meta key, original strings ("4k", "1080p").
 *
 * @param string $meta_key Resolution meta key.
 * @return string
 */
function majestic_tube_get_quality_label( $meta_key ) {
	if ( 'video_url' === $meta_key ) {
		return __( 'Default', 'majestic-tube' );
	}

	$labels = majestic_tube_video_resolutions();

	return isset( $labels[ $meta_key ] ) ? $labels[ $meta_key ] : '';
}

/**
 * Player-ready source list: resolution files first (highest to lowest, like the
 * original), then the single video_url file.
 *
 * @param int   $post_id Post ID, defaults to the current post.
 * @param array $data    Optional pre-fetched result of
 *                        majestic_tube_get_video_sources(). Pass it when the
 *                        template already has the data, so the eight video_url
 *                        meta keys are not read from the database twice.
 * @return array<int, array{key:string,url:string,label:string,type:string}>
 */
function majestic_tube_get_player_sources( $post_id = 0, $data = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$data    = is_array( $data ) ? $data : majestic_tube_get_video_sources( $post_id );
	$sources = array();

	if ( ! $data ) {
		return $sources;
	}

	if ( $data['sources'] ) {
		foreach ( $data['sources'] as $key => $url ) {
			$sources[] = array(
				'key'   => $key,
				'url'   => $url,
				'label' => majestic_tube_get_quality_label( $key ),
				'type'  => majestic_tube_get_video_mime( $url ),
			);
		}
	} elseif ( $data['main'] ) {
		$sources[] = array(
			'key'   => 'video_url',
			'url'   => $data['main'],
			'label' => '',
			'type'  => majestic_tube_get_video_mime( $data['main'] ),
		);
	}

	return $sources;
}

/**
 * Registered image size from the original `main-thumbnail-quality` option.
 *
 * The option stores the original theme's size names (wpst_thumb_small,
 * wpst_thumb_medium, wpst_thumb_large), which are registered in
 * inc/theme-support.php - plugins such as the WP-Script paywall ask for them
 * by name too.
 *
 * @return string Registered image size name.
 */
function majestic_tube_get_thumb_size() {
	$size    = (string) majestic_tube_get_option( 'wpst-options', 'main-thumbnail-quality', 'wpst_thumb_medium' );
	$allowed = array( 'wpst_thumb_small', 'wpst_thumb_medium', 'wpst_thumb_large' );

	return in_array( $size, $allowed, true ) ? $size : 'wpst_thumb_medium';
}

/**
 * Get the main thumbnail URL, falling back to the thumb meta key.
 *
 * @param int    $post_id Post ID.
 * @param string $size    Image size. Empty uses the configured quality.
 * @return string
 */
function majestic_tube_get_thumb_url( $post_id = 0, $size = '' ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$size    = $size ? $size : majestic_tube_get_thumb_size();

	if ( has_post_thumbnail( $post_id ) ) {
		$thumb_id = get_post_thumbnail_id( $post_id );
		$data     = wp_get_attachment_image_src( $thumb_id, $size );

		// An unregistered size silently returns the full-size file, so fall
		// back explicitly instead of serving a multi-megabyte image in a card.
		if ( ! $data ) {
			$data = wp_get_attachment_image_src( $thumb_id, 'full' );
		}

		if ( $data ) {
			return (string) $data[0];
		}
	}

	return (string) get_post_meta( $post_id, 'thumb', true );
}

/**
 * Render the current WordPress loop through a template part.
 *
 * @param string $template Template part suffix.
 * @return void
 */
function majestic_tube_render_post_grid( $template = 'video-card' ) {
	while ( have_posts() ) :
		the_post();
		get_template_part( 'template-parts/content', $template );
	endwhile;
}

/**
 * Canonical listing filters and their two user-facing labels.
 *
 * @return array<string, array{title:string,label:string}>
 */
function majestic_tube_video_filters() {
	return array(
		'latest'      => array(
			'title' => __( 'Latest videos', 'majestic-tube' ),
			'label' => __( 'Latest', 'majestic-tube' ),
		),
		'most-viewed' => array(
			'title' => __( 'Most viewed videos', 'majestic-tube' ),
			'label' => __( 'Most viewed', 'majestic-tube' ),
		),
		'longest'     => array(
			'title' => __( 'Longest videos', 'majestic-tube' ),
			'label' => __( 'Longest', 'majestic-tube' ),
		),
		'popular'     => array(
			'title' => __( 'Popular videos', 'majestic-tube' ),
			'label' => __( 'Popular', 'majestic-tube' ),
		),
		'random'      => array(
			'title' => __( 'Random videos', 'majestic-tube' ),
			'label' => __( 'Random', 'majestic-tube' ),
		),
	);
}

/**
 * Shared directory pages used by the fallback menu and activation setup.
 *
 * @return array<string, string>
 */
function majestic_tube_directory_pages() {
	return array(
		'Categories' => 'categories',
		'Tags'       => 'tags',
		'Actors'     => 'actors',
	);
}

/**
 * Get the current sort filter from the query string (original slugs).
 *
 * @return string
 */
function majestic_tube_get_current_filter() {
	$allowed = array_keys( majestic_tube_video_filters() );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only sort param.
	$requested = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : '';

	if ( in_array( $requested, $allowed, true ) ) {
		return $requested;
	}

	// On the homepage the original falls back to the show-videos-homepage
	// option, so the default sort and the sort bar agree.
	if ( is_home() ) {
		$option = (string) majestic_tube_get_option( 'wpst-options', 'show-videos-homepage', 'latest' );

		if ( in_array( $option, $allowed, true ) ) {
			return $option;
		}
	}

	return 'latest';
}

/**
 * Return translated title for a sort filter.
 *
 * @param string $filter Filter slug.
 * @return string
 */
function majestic_tube_get_filter_title( $filter = '' ) {
	$filter = $filter ? $filter : majestic_tube_get_current_filter();
	$labels = majestic_tube_video_filters();

	return isset( $labels[ $filter ] ) ? $labels[ $filter ]['title'] : $labels['latest']['title'];
}

/**
 * Display the sort filter navigation (original query-var contract).
 */
function majestic_tube_filter_nav() {
	$current = majestic_tube_get_current_filter();

	$filters = majestic_tube_video_filters();
	$base_url = remove_query_arg( array( 'filter', 'paged' ) );

	echo '<nav class="filter-nav" aria-label="' . esc_attr__( 'Video filters', 'majestic-tube' ) . '"><ul>';

	foreach ( $filters as $slug => $filter ) {
			printf(
				'<li><a href="%1$s" class="%2$s">%3$s</a></li>',
				esc_url( add_query_arg( 'filter', $slug, $base_url ) ),
				esc_attr( $slug === $current ? 'active' : '' ),
				esc_html( $filter['label'] )
			);
		}

	echo '</ul></nav>';
}

/**
 * Modify the main loop for the sort filters (original behavior).
 *
 * @param WP_Query $query Current query.
 */
function majestic_tube_filter_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	// Per-page handling like the original (desktop vs mobile).
	$per_page = majestic_tube_is_mobile()
		? absint( majestic_tube_get_option( 'wpst-options', 'videos-per-page-mobile' ) )
		: absint( majestic_tube_get_option( 'wpst-options', 'videos-per-page' ) );

	$is_video_archive = $query->is_home() || $query->is_category() || $query->is_tag() || $query->is_tax( 'actors' );

	if ( $per_page && ( $is_video_archive || $query->is_search() ) ) {
		$query->set( 'posts_per_page', $per_page );
	}

	if ( ! $is_video_archive ) {
		return;
	}

	$filter = majestic_tube_get_current_filter();

	switch ( $filter ) {
		case 'most-viewed':
			$query->set( 'meta_key', 'post_views_count' );
			$query->set( 'orderby', 'meta_value_num' );
			$query->set( 'order', 'DESC' );
			break;

		case 'longest':
			$query->set( 'meta_key', 'duration' );
			$query->set( 'orderby', 'meta_value_num' );
			$query->set( 'order', 'DESC' );
			break;

		case 'popular':
			/*
			 * Original behaviour: sort by the `rate` meta value, descending.
			 *
			 * The original also set a meta_query of
			 * `rate NOT EXISTS OR rate EXISTS`, which is a tautology: every
			 * post either has the key or it does not, so the clause matches
			 * everything and filters nothing. Combined with the meta_key below
			 * it made WordPress join wp_postmeta twice under two aliases for
			 * no benefit (reports.php documents exactly this hazard). Posts
			 * with no `rate` row are already excluded by the meta_key join,
			 * which is the same set the tautology was trying to include, so
			 * dropping it preserves the original result set.
			 */
			$query->set( 'meta_key', 'rate' );
			$query->set( 'orderby', 'meta_value_num' );
			$query->set( 'order', 'DESC' );
			break;

		case 'random':
			$query->set( 'orderby', 'rand' );
			break;
	}
}
add_action( 'pre_get_posts', 'majestic_tube_filter_query' );

/**
 * Build a useful primary-menu fallback when no menu is assigned.
 *
 * WordPress otherwise renders no navigation at all, which leaves the header
 * looking broken on a fresh install. Only top-level pages are included here;
 * their children remain available through the normal menu editor.
 *
 * @param array $args Arguments supplied by wp_nav_menu().
 * @return string Primary menu list items.
 */
function majestic_tube_primary_menu_fallback( $args = array() ) {
	$home_class = is_front_page() ? ' menu-item-home current-menu-item' : '';
	$items      = sprintf(
		'<li class="menu-item%1$s"><a href="%2$s">%3$s</a></li>',
		esc_attr( $home_class ),
		esc_url( home_url( '/' ) ),
		esc_html__( 'Home', 'majestic-tube' )
	);

	foreach ( majestic_tube_directory_pages() as $title => $path ) {
		$page = get_page_by_path( $path );

		if ( ! $page || ! isset( $page->ID ) ) {
			continue;
		}

		$items .= sprintf(
			'<li class="menu-item"><a href="%1$s">%2$s</a></li>',
			esc_url( get_permalink( $page->ID ) ),
			esc_html( $title )
		);
	}

	$args = (object) array_merge(
		(array) $args,
		array( 'theme_location' => 'majestic_tube_main_menu' )
	);

	/** This filter preserves membership links on a fresh, menu-less install. */
	$items = apply_filters( 'wp_nav_menu_items', $items, $args );

	/*
	 * WordPress treats a fallback callback as the complete menu output. Returning
	 * only <li> elements here leaves the <nav> without a list, so the mobile
	 * toggle cannot find #primary-menu and desktop alignment is lost. Keep the
	 * fallback's wrapper identical to the normal wp_nav_menu() output.
	 */
	$menu_id    = isset( $args->menu_id ) && $args->menu_id ? $args->menu_id : 'primary-menu';
	$menu_class = isset( $args->menu_class ) && $args->menu_class ? $args->menu_class : 'menu';

	return sprintf(
		'<ul id="%1$s" class="%2$s">%3$s</ul>',
		esc_attr( $menu_id ),
		esc_attr( $menu_class ),
		$items
	);
}

/**
 * Guess the video MIME type from a file URL, like the original helper.
 *
 * @param string $url      Video file URL.
 * @param string $fallback MIME type to use when the extension is unknown.
 * @return string MIME type.
 */
function majestic_tube_get_video_mime( $url, $fallback = 'video/mp4' ) {
	$allowed         = get_allowed_mime_types();
	$allowed['m3u8'] = 'application/x-mpegURL';

	if ( ! is_string( $url ) || '' === $url ) {
		return $fallback;
	}

	$path     = wp_parse_url( $url, PHP_URL_PATH );
	$filename = $path ? basename( $path ) : '';
	$filetype = $filename ? wp_check_filetype( $filename, $allowed ) : array( 'type' => '' );

	return $filetype['type'] ? $filetype['type'] : $fallback;
}

/**
 * Get the trailer URL for hover preview (original meta key).
 *
 * @param int $post_id Post ID.
 * @return string
 */
function majestic_tube_get_trailer_url( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();

	return (string) get_post_meta( $post_id, 'trailer_url', true );
}

/**
 * Return a WP_Query of videos related by shared actors (falls back to latest).
 *
 * @param int $post_id Current video post ID.
 * @param int $count   Number of related videos.
 * @return WP_Query|null
 */
function majestic_tube_get_related_videos( $post_id, $count = 6 ) {
	$actor_ids = wp_get_post_terms( $post_id, 'actors', array( 'fields' => 'ids' ) );

	$args = array(
		'post_type'      => 'post',
		'posts_per_page' => $count,
		'post__not_in'   => array( $post_id ),
		'no_found_rows'  => true,
		'ignore_sticky_posts' => true,
	);

	if ( $actor_ids ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'actors',
				'field'    => 'term_id',
				'terms'    => $actor_ids,
			),
		);
	}

	$related = new WP_Query( $args );

	if ( ! $related->have_posts() && $actor_ids ) {
		// Fallback: latest videos.
		unset( $args['tax_query'] );
		$related = new WP_Query( $args );
	}

	return $related->have_posts() ? $related : null;
}