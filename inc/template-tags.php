<?php
/**
 * Template tags - WP-Script compatible helpers.
 *
 * @package Majestic Tube
 * @version 2.3.0
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
	$embed    = get_post_meta( $post_id, 'embed', true );

	/*
	 * An iframe saved into a URL field before the save path learned to move
	 * it is still an embed, not a file. Counting it as a source would report
	 * the video as self-hosted and suppress a perfectly good embed, so drop
	 * it from the source list and fall back to the embed key.
	 */
	$sources = array_filter(
		$sources,
		static function ( $url ) {
			return '' === majestic_tube_extract_iframe( $url );
		}
	);

	$main_iframe = majestic_tube_extract_iframe( $main_url );

	if ( '' !== $main_iframe ) {
		$main_url = '';

		if ( '' === trim( (string) $embed ) ) {
			$embed = $main_iframe;
		}
	}

	$data = array(
		'type'      => 'none',
		'sources'   => $sources,
		'main'      => $main_url,
		'embed'     => $embed,
		'shortcode' => get_post_meta( $post_id, 'shortcode', true ),
	);

	/*
	 * Which of the two fields the player uses is a site-wide Customizer
	 * choice (Appearance -> Customize -> Video Page -> Video Page Layout),
	 * not a per-post one, and it is resolved here because this is the only
	 * place the theme ranks the fields. Everything downstream - the player
	 * branches in single.php and the quality menu - reads the type decided
	 * here, so one setting moves all of them and every post on the site at
	 * once.
	 *
	 * The choice sets priority, not availability. A post that does not carry
	 * the preferred field still plays from the other one: forcing `embed`
	 * over a post whose embed box is empty would otherwise leave the page
	 * with no player at all, which is a far worse outcome than ignoring the
	 * setting for that one post. Both fields stay in the returned data
	 * either way, so turning the setting back needs no re-save.
	 */
	$preference = majestic_tube_get_option( 'wpst-options', 'video-player-source', 'auto' );

	// is_string, not a cast: a filter handing back an array would otherwise
	// raise a warning on the cast and produce the string "Array".
	$preference = is_string( $preference ) ? $preference : '';

	if ( ! in_array( $preference, array( 'auto', 'url', 'embed' ), true ) ) {
		$preference = 'auto';
	}

	$has_file  = ( $main_url || $sources );
	$has_embed = '' !== trim( (string) $data['embed'] );

	if ( 'embed' === $preference && $has_embed ) {
		$data['type'] = 'embed';
	} elseif ( $has_file ) {
		$data['type'] = 'self-hosted';
	} elseif ( $has_embed ) {
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
 * Whether an attachment really has one generated intermediate size.
 *
 * image_downsize() does not fail when a registered size has no file: it answers
 * with the original upload instead, which is how a two-megabyte photo ends up
 * behind a disabled button. Reading the attachment metadata answers the
 * question the caller is actually asking.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $size          Registered size name.
 * @return bool
 */
function majestic_tube_attachment_has_size( $attachment_id, $size ) {
	$metadata = wp_get_attachment_metadata( $attachment_id );
	$sizes    = isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ? $metadata['sizes'] : array();

	return isset( $sizes[ $size ]['file'] ) && '' !== $sizes[ $size ]['file'];
}

/**
 * The `sizes` attribute for a grid card thumbnail.
 *
 * It mirrors the three column counts the stylesheet uses, built from the same
 * options, so the browser downloads the one file that matches the box it is
 * about to paint instead of the configured card size at every width. The
 * division is by viewport width rather than by the real card width, which
 * ignores the page gutters and the grid gap: the result is a few percent too
 * wide, so the browser errs towards the sharper file.
 *
 * @return string
 */
function majestic_tube_card_image_sizes() {
	$desktop = absint( majestic_tube_get_option( 'wpst-options', 'videos-per-row', 5 ) );
	$desktop = $desktop ? min( max( $desktop, 1 ), 12 ) : 5;
	$tablet  = min( $desktop, 3 );
	$mobile  = absint( majestic_tube_get_option( 'wpst-options', 'videos-per-row-mobile', 2 ) );
	$mobile  = $mobile ? min( max( $mobile, 1 ), 3 ) : 2;

	$sizes = array(
		'(max-width: 600px) calc(100vw / ' . $mobile . ')',
		'(max-width: 1100px) calc(100vw / ' . $tablet . ')',
		'calc(100vw / ' . $desktop . ')',
	);

	/**
	 * Filter the sizes attribute used by grid card thumbnails.
	 *
	 * @param array $sizes Size conditions, narrowest first.
	 */
	$sizes = (array) apply_filters( 'majestic_tube_card_image_sizes', $sizes );

	return implode( ', ', array_filter( array_map( 'strval', $sizes ) ) );
}

/**
 * Render a grid card thumbnail as a responsive image.
 *
 * The card used to print one URL with fixed width and height attributes, so
 * every visitor received the same 320x180 file: on a phone that is more pixels
 * than the screen shows, and in a two-column desktop grid - or at any device
 * pixel ratio above 1 - fewer than the box needs, which is what made the posters
 * look soft. wp_get_attachment_image() prints the srcset WordPress already knows
 * how to build from the generated sizes, then the browser picks from it with the
 * sizes attribute above.
 *
 * Videos whose picture is the external `thumb` meta key have no attachment to
 * build a srcset from. Those keep the original single-file markup, so nothing
 * about a remote thumbnail changes.
 *
 * @param int $post_id Post ID.
 * @return string Image markup, or an empty string when the post has no image.
 */
function majestic_tube_get_card_image( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$alt     = the_title_attribute(
		array(
			'echo' => false,
			'post' => $post_id,
		)
	);

	if ( has_post_thumbnail( $post_id ) ) {
		$markup = wp_get_attachment_image(
			(int) get_post_thumbnail_id( $post_id ),
			majestic_tube_get_thumb_size(),
			false,
			array(
				'class'   => 'video-main-thumb',
				'alt'     => $alt,
				'loading' => 'lazy',
				'sizes'   => majestic_tube_card_image_sizes(),
			)
		);

		if ( $markup ) {
			return $markup;
		}
	}

	$url = majestic_tube_get_thumb_url( $post_id );

	if ( '' === $url ) {
		return '';
	}

	return sprintf(
		'<img class="video-main-thumb" src="%1$s" alt="%2$s" loading="lazy" width="320" height="180" />',
		esc_url( $url ),
		esc_attr( $alt )
	);
}

/**
 * Largest generated thumbnail for a full-width player.
 *
 * A poster is painted at the width of the player column, which is wider than
 * every size the theme used to register, so the browser was upscaling a
 * 640x360 file on every screen bigger than a netbook. The first size below that
 * really exists on this attachment wins.
 *
 * @param int $post_id Post ID.
 * @return string Poster URL, empty when the post has no image.
 */
function majestic_tube_get_poster_url( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();

	if ( ! has_post_thumbnail( $post_id ) ) {
		return majestic_tube_get_thumb_url( $post_id );
	}

	$thumb_id = (int) get_post_thumbnail_id( $post_id );

	foreach ( array( 'majestic-tube-poster', 'majestic-tube-thumb-large', 'wpst_thumb_large' ) as $size ) {
		if ( ! majestic_tube_attachment_has_size( $thumb_id, $size ) ) {
			continue;
		}

		$data = wp_get_attachment_image_src( $thumb_id, $size );

		if ( $data && ! empty( $data[0] ) ) {
			return (string) $data[0];
		}
	}

	// Nothing cropped, so fall back to the same helper the templates used
	// before this function existed.
	return majestic_tube_get_thumb_url( $post_id, 'majestic-tube-thumb-large' );
}

/**
 * Render the current WordPress loop through a template part.
 *
 * When in-feed advertising is enabled and has code, a card-sized content block
 * is printed after every majestic_tube_in_feed_frequency() video cards. The
 * block is a grid item like the cards themselves, so the row rhythm survives;
 * main.css spans it across the full row on the single-column phone layout.
 *
 * @param string $template Template part suffix.
 * @return void
 */
function majestic_tube_render_post_grid( $template = 'video-card' ) {
	$in_feed_code   = majestic_tube_get_in_feed_ad();
	$in_feed_every  = 0;
	$card_index     = 0;

	if ( $in_feed_code && majestic_tube_ads_allowed( 'in-feed' ) ) {
		$in_feed_code  = majestic_tube_rotate_ad( $in_feed_code, 'in-feed' );
		$in_feed_every = majestic_tube_in_feed_frequency();
	}

	while ( have_posts() ) :
		the_post();

		get_template_part( 'template-parts/content', $template );
		$card_index++;

		if ( $in_feed_every && 0 === $card_index % $in_feed_every ) {
			majestic_tube_content_block(
				$in_feed_code,
				array(
					'majestic-tube-content-block--in-feed',
					'video-grid-ad',
				),
				'infeed-ad-code'
			);
		}
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
 * Default number of tags in the popular tags bar.
 */
const MAJESTIC_TUBE_POPULAR_TAGS_DEFAULT = 20;

/**
 * How many tags the popular tags bar should show.
 *
 * @return int
 */
function majestic_tube_popular_tags_limit() {
	$limit = absint( majestic_tube_get_option( 'wpst-options', 'popular-tags-count', MAJESTIC_TUBE_POPULAR_TAGS_DEFAULT ) );

	// A cleared or zeroed field means "use the default" rather than "print an
	// empty bar". The bar exists to be useful, and an empty one is just a gap
	// under the sort bar.
	$limit = ( $limit > 0 ) ? $limit : MAJESTIC_TUBE_POPULAR_TAGS_DEFAULT;

	/**
	 * Filter how many tags the popular tags bar shows.
	 *
	 * @param int $limit Number of tags.
	 */
	return (int) apply_filters( 'majestic_tube_popular_tags_limit', $limit );
}

/**
 * The most-used tags, in order, for the popular tags bar.
 *
 * The bar is printed under the sort bar on the front page, so it belongs to
 * the hottest query a video site has. The list is fetched once and kept in the
 * terms object cache under a key that embeds WordPress's own `last_changed`
 * marker for that group - the same self-invalidating key the term directory
 * pages use. A tag added, renamed, reassigned or removed by an import, a CLI
 * run or the admin screen is therefore reflected on the next request without
 * this function hooking anything, which also covers the bulk paths that an
 * `edited_term` flush would miss.
 *
 * @param int $limit Number of tags. 0 uses the configured value.
 * @return WP_Term[] Tags ordered by use, or an empty array on error.
 */
function majestic_tube_popular_tags( $limit = 0 ) {
	$limit = $limit ? absint( $limit ) : majestic_tube_popular_tags_limit();

	if ( ! taxonomy_exists( 'post_tag' ) ) {
		return array();
	}

	$last_changed = wp_cache_get( 'last_changed', 'terms' );
	$cache_key    = sprintf(
		'popular_tags_%d_%s',
		$limit,
		preg_replace( '/[^A-Za-z0-9_.:-]/', '', (string) $last_changed )
	);

	$cached = wp_cache_get( $cache_key, MAJESTIC_TUBE_TERM_CACHE_GROUP );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$args = array(
		'taxonomy'   => 'post_tag',
		'hide_empty' => true,
		'number'     => $limit,
		/*
		 * `orderby` is one string here, not the key => direction array that
		 * WP_Query accepts. WP_Term_Query::parse_orderby() passes the value
		 * straight to strtolower() with no array handling, so an array is a
		 * fatal error that takes the front page with it, not a graceful
		 * fallback. The name tiebreak is done in PHP instead, below.
		 */
		'orderby'    => 'count',
		'order'      => 'DESC',
	);

	/**
	 * Filter the query used to build the popular tags bar.
	 *
	 * @param array $args  Arguments passed to get_terms().
	 * @param int   $limit Number of tags requested.
	 */
	$args = apply_filters( 'majestic_tube_popular_tags_args', $args, $limit );

	$terms = get_terms( $args );

	// An errored result is not cached, so a transient failure cannot stick.
	if ( is_wp_error( $terms ) ) {
		return array();
	}

	/*
	 * One ORDER BY column is all the term query has, so tags tied on count
	 * come back in whatever order the database felt like, and the bar
	 * reshuffles itself between two visits that ought to look identical.
	 * Sorting the fetched page settles that: most used first, then by name.
	 *
	 * This cannot decide *which* tags are in the top N when the count at the
	 * boundary is tied - that is settled before PHP ever sees the rows - but
	 * within the bar the order is now fixed.
	 */
	usort(
		$terms,
		static function ( $a, $b ) {
			$by_count = (int) $b->count - (int) $a->count;

			if ( 0 !== $by_count ) {
				return $by_count;
			}

			$by_name = strcasecmp( $a->name, $b->name );

			// strcasecmp alone ties on names differing only by case, which
			// MySQL and PHP do not agree on; the exact comparison makes the
			// order total.
			return 0 !== $by_name ? $by_name : strcmp( $a->name, $b->name );
		}
	);

	wp_cache_set( $cache_key, $terms, MAJESTIC_TUBE_TERM_CACHE_GROUP, MAJESTIC_TUBE_TERM_CACHE_TTL );

	return $terms;
}

/**
 * Display the popular tags bar, directly under the sort filter bar.
 *
 * The tag strip scrolls sideways by touch, by trackpad, and through the two
 * arrow buttons. Those buttons are printed with the `hidden` attribute so a
 * visitor without JavaScript is not shown two buttons that do nothing, and the
 * script reveals them only once it has measured that the tags actually
 * overflow the row.
 *
 * @return void
 */
function majestic_tube_tags_slider() {
	if ( ! majestic_tube_option_is_on( 'show-popular-tags-slider' ) ) {
		return;
	}

	$tags = majestic_tube_popular_tags();

	// On a tag archive the tag being read is marked, the same way the sort bar
	// marks the sort in use.
	$current = is_tag() ? (int) get_queried_object_id() : 0;
	$items   = '';

	foreach ( $tags as $tag ) {
		$link = get_term_link( $tag );

		if ( is_wp_error( $link ) ) {
			continue;
		}

		$items .= sprintf(
			'<li><a href="%1$s" class="%2$s">%3$s<span class="screen-reader-text"> %4$s</span><span class="tags-slider-count">%5$s</span></a></li>',
			esc_url( $link ),
			esc_attr( (int) $tag->term_id === $current ? 'active' : '' ),
			esc_html( $tag->name ),
			esc_html( _n( 'video', 'videos', (int) $tag->count, 'majestic-tube' ) ),
			esc_html( number_format_i18n( (int) $tag->count ) )
		);
	}

	/*
	 * Nothing worth drawing a row around: either the site has no tagged
	 * videos at all, or every term it has came back without a usable link.
	 * Either way the bar prints nothing - no heading, no empty shell, no gap
	 * under the sort bar where a bar used to be.
	 */
	if ( ! $items ) {
		return;
	}

	/*
	 * $items is assembled entirely from escaped parts in the loop above, so it
	 * is printed as markup rather than escaped a second time.
	 */
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped output above.
	printf(
		'<nav class="tags-slider" aria-label="%1$s" data-tags-slider>
			<button type="button" class="tags-slider-nav tags-slider-prev" data-tags-slider-prev aria-label="%2$s" hidden><span aria-hidden="true">&larr;</span></button>
			<ul class="tags-slider-track" data-tags-slider-track>%3$s</ul>
			<button type="button" class="tags-slider-nav tags-slider-next" data-tags-slider-next aria-label="%4$s" hidden><span aria-hidden="true">&rarr;</span></button>
		</nav>',
		esc_attr__( 'Popular tags', 'majestic-tube' ),
		esc_attr__( 'Scroll the tags back', 'majestic-tube' ),
		$items,
		esc_attr__( 'Scroll the tags forward', 'majestic-tube' )
	);
}

/**
 * Whether the site records likes at all.
 *
 * The popular listing sorts on a counter, and a counter only exists once
 * somebody has used the like button. A freshly imported archive has videos,
 * views and thumbnails but no votes yet, so ordering by likes matched nothing
 * and the tab came up empty - on a site with hundreds of videos, which reads
 * as a broken page rather than as "no votes yet". The answer is cached for an
 * hour and cleared the moment the first vote lands, so the first vote is what
 * promotes the tab to the ranking it was asking for.
 *
 * @return bool
 */
function majestic_tube_site_records_likes() {
	$cache_key = 'majestic_tube_site_records_likes';
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return 'yes' === $cached;
	}

	$voted = get_posts(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 1,
			'fields'              => 'ids',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'suppress_filters'    => false,
			'meta_query'          => array(
				array(
					'key'     => 'likes_count',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		)
	);

	$has_likes = ! empty( $voted );

	set_transient( $cache_key, $has_likes ? 'yes' : 'no', HOUR_IN_SECONDS );

	/**
	 * Filter whether the site has any recorded likes.
	 *
	 * The popular listing uses this to decide between ranking by likes and
	 * ranking by views, so a site whose votes live in another table can point
	 * it at them.
	 *
	 * @param bool $has_likes Whether at least one video has a vote.
	 */
	return (bool) apply_filters( 'majestic_tube_site_records_likes', $has_likes );
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
			 * Popular means most liked, so the sort is on the likes count and
			 * not on the `rate` percentage the original used. `rate` is an
			 * approval ratio (1 like out of 1 vote is 100%), so a video with
			 * a single vote outranked one with five hundred.
			 *
			 * The counter only exists once somebody votes, though, and an
			 * unvoted archive has no `rate` rows at all - which is what
			 * emptied this tab on a site with hundreds of imported videos.
			 * When the site has no likes yet, the same question ("what has
			 * the audience engaged with") has a different answer, so the tab
			 * ranks by views until the first vote promotes it to likes.
			 *
			 * E3 kept, but opt-in: on an import-heavy archive an unbounded
			 * sort over millions of rows is expensive, so a site that wants
			 * it can bound the set with this filter. It is off by default
			 * because a window narrower than the archive empties the
			 * listing outright - a library backdated by an import has no
			 * posts in the last 30 days, so the page went blank with no
			 * error and no explanation.
			 */
			$popular_days = (int) apply_filters( 'majestic_tube_popular_window_days', 0 );

			if ( $popular_days > 0 ) {
				$query->set(
					'date_query',
					array(
						array(
							'after' => $popular_days . ' days ago',
							'column' => 'post_date_gmt',
						),
					)
				);
			}

			$query->set( 'meta_key', majestic_tube_site_records_likes() ? 'likes_count' : 'post_views_count' );
			$query->set( 'orderby', 'meta_value_num' );
			$query->set( 'order', 'DESC' );
			break;

		case 'random':
			/*
			 * E3: bare ORDER BY RAND() scans the whole result set on every
			 * request, which is the classic large-table killer, so the draw
			 * can be confined to a recent window. The window is off by
			 * default for the same reason as the popular one above: on a
			 * backdated or dormant archive the narrower window returned an
			 * empty page rather than a slower one. A site large enough to
			 * need the guard turns it back on.
			 */
			$random_days = (int) apply_filters( 'majestic_tube_random_window_days', 0 );

			if ( $random_days > 0 ) {
				$query->set(
					'date_query',
					array(
						array(
							'after' => $random_days . ' days ago',
							'column' => 'post_date_gmt',
						),
					)
				);
			}

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
}/**
 * TTL backstop for the cached related-videos list.
 *
 * The cache key embeds WordPress's own `last_changed` marker for the posts
 * group, so correctness does not depend on the expiry; the TTL only stops
 * abandoned keys accumulating on a persistent object cache.
 */
const MAJESTIC_TUBE_RELATED_CACHE_TTL = 6 * HOUR_IN_SECONDS;

/**
 * Return a WP_Query of videos related by shared actors (falls back to latest).
 *
 * The result ID list is cached per post in the analytics object-cache group
 * with a TTL backstop. Correctness rides on WordPress's own `last_changed`
 * marker for the posts group, exactly like the term-directory cache in
 * pagination.php rides on the terms marker: WordPress bumps `last_changed`
 * whenever any post is written, so a stale list simply becomes unreachable
 * instead of needing this module to hook every write path.
 *
 * @param int $post_id Current video post ID.
 * @param int $count   Number of related videos.
 * @return WP_Query|null
 */
function majestic_tube_get_related_videos( $post_id, $count = 6 ) {
	$count   = max( 1, absint( $count ) );
	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		return null;
	}

	$cache_key = 'related_' . $post_id . '_' . $count;
	$cached    = wp_cache_get( $cache_key, MAJESTIC_TUBE_ANALYTICS_GROUP );

	if ( is_array( $cached ) ) {
		if ( ! $cached ) {
			return null;
		}

		return new WP_Query(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => $count,
				'post__in'            => $cached,
				'orderby'             => 'post__in',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			)
		);
	}

	$actor_ids = wp_get_post_terms( $post_id, 'actors', array( 'fields' => 'ids' ) );
	$args = array(
		'post_type'           => 'post',
		'posts_per_page'      => $count,
		'post__not_in'        => array( $post_id ),
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
		'fields'              => 'ids',
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
	$ids     = $related->have_posts() ? array_map( 'absint', $related->posts ) : array();

	if ( ! $ids && $actor_ids ) {
		// Fallback: latest videos.
		unset( $args['tax_query'] );
		$related = new WP_Query( $args );
		$ids     = $related->have_posts() ? array_map( 'absint', $related->posts ) : array();
	}

	/*
	 * The empty result is cached too: a video with no relatives and no other
	 * content would otherwise re-run both queries on every view.
	 */
	wp_cache_set( $cache_key, $ids, MAJESTIC_TUBE_ANALYTICS_GROUP, MAJESTIC_TUBE_RELATED_CACHE_TTL );

	if ( ! $ids ) {
		return null;
	}

	return new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $count,
			'post__in'            => $ids,
			'orderby'             => 'post__in',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		)
	);
}