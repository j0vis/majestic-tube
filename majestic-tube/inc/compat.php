<?php
/**
 * WP-Script compatibility layer.
 *
 * The original KingTube theme exposed a set of global wpst_* template helpers.
 * WP-Script plugins, child themes and third-party templates call them by name,
 * and some of them are referenced from documentation on docs.wp-script.com.
 * Majestic Tube implements the same helpers so an existing installation keeps
 * working; every function is guarded with function_exists() so a plugin that
 * declares its own version always wins.
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Guess the MIME type of a video (or preview image) from its URL.
 *
 * Original helper: inc/video-functions.php.
 *
 * @param string $video_url File URL.
 * @return string MIME type, or an empty string when it cannot be detected.
 */
if ( ! function_exists( 'wpst_get_type_from_video_url' ) ) {
	function wpst_get_type_from_video_url( $video_url ) {
		if ( ! is_string( $video_url ) || '' === $video_url ) {
			return '';
		}

		return majestic_tube_get_video_mime( $video_url, '' );
	}
}

/**
 * Formatted duration of the current post in the loop (H:i:s or i:s).
 *
 * Original signature takes no arguments and reads the global post.
 *
 * @return string|false Formatted duration, false when <= 0.
 */
if ( ! function_exists( 'wpst_get_video_duration' ) ) {
	function wpst_get_video_duration() {
		global $post;

		$seconds = $post ? majestic_tube_get_duration_seconds( $post->ID ) : 0;

		return majestic_tube_format_duration( $seconds );
	}
}

/**
 * View count for a post (original `post_views_count` meta key).
 *
 * @param int $post_id Post ID.
 * @return int
 */
if ( ! function_exists( 'wpst_get_post_views' ) ) {
	function wpst_get_post_views( $post_id ) {
		return majestic_tube_get_post_views( $post_id );
	}
}

/**
 * Human readable number (1234 => 1K), original rounding rules.
 *
 * @param int $input Number.
 * @return string
 */
if ( ! function_exists( 'wpst_get_human_number' ) ) {
	function wpst_get_human_number( $input = 0 ) {
		return majestic_tube_get_human_number( $input );
	}
}

/**
 * ISO 8601 duration string in the original PnDTnHnMnS form.
 *
 * @param int $seconds Duration in seconds.
 * @return string
 */
if ( ! function_exists( 'wpst_iso8601_duration' ) ) {
	function wpst_iso8601_duration( $seconds ) {
		$seconds = absint( $seconds );
		$days    = floor( $seconds / 86400 );
		$seconds = $seconds % 86400;
		$hours   = floor( $seconds / 3600 );
		$seconds = $seconds % 3600;
		$minutes = floor( $seconds / 60 );
		$seconds = $seconds % 60;

		return sprintf( 'P%dDT%dH%dM%dS', $days, $hours, $minutes, $seconds );
	}
}

/**
 * Rotation thumbnails of a video as a comma separated string.
 *
 * The original helper returns a string (templates feed it straight into
 * esc_attr() for the data-thumbs attribute) or false when there is nothing to
 * rotate. It prefers the images attached to the post when a featured image is
 * set and more than one image is attached, otherwise it uses the `thumbs`
 * meta values written by the WP-Script importers.
 *
 * @param int $post_id Post ID.
 * @return string|false
 */
if ( ! function_exists( 'wpst_get_multithumbs' ) ) {
	function wpst_get_multithumbs( $post_id ) {
		$thumbs = majestic_tube_get_multithumbs( $post_id );

		if ( ! $thumbs ) {
			return false;
		}

		return implode( ',', $thumbs );
	}
}

/**
 * Hover-preview markup for a video: a looping <video> for mp4/webm trailers, an
 * <img> for gif/webp trailers.
 *
 * @param int $post_id Post ID.
 * @return string HTML.
 * @throws Exception When the post has no trailer or an unsupported format.
 */
if ( ! function_exists( 'wpst_get_video_preview' ) ) {
	function wpst_get_video_preview( $post_id ) {
		$video_mime_types = array( 'video/mp4', 'video/webm' );
		$image_mime_types = array( 'image/gif', 'image/webp' );

		$post_id     = intval( $post_id );
		$trailer_url = $post_id ? majestic_tube_get_trailer_url( $post_id ) : '';

		if ( ! $trailer_url ) {
			throw new Exception( 'No trailer found for video #' . esc_html( $post_id ) );
		}

		$trailer_format = wpst_get_type_from_video_url( $trailer_url );

		if ( in_array( $trailer_format, $video_mime_types, true ) ) {
			// The wpst-trailer class is what Clean Tube Player looks for when
			// it decides which <video> tags it may replace, and what the theme
			// stylesheet sizes.
			return '<video class="wpst-trailer" width="100%" height="100%" playsinline autoplay loop muted preload="none"><source src="' . esc_url( $trailer_url ) . '" type="' . esc_attr( $trailer_format ) . '">' . esc_html__( 'Your browser does not support the video tag.', 'majestic-tube' ) . '</video>';
		}

		if ( in_array( $trailer_format, $image_mime_types, true ) ) {
			return '<img class="wpst-trailer preview-thumb" width="100%" height="100%" src="' . esc_url( $trailer_url ) . '" alt="" />';
		}

		throw new Exception( 'Unsupported trailer format for video #' . esc_html( $post_id ) );
	}
}

/**
 * Print the categories / actors / tags list of the current video.
 *
 * Each group honours its original visibility switch.
 *
 * @return void
 */
if ( ! function_exists( 'wpst_cats_tags' ) ) {
	function wpst_cats_tags() {
		global $post;

		if ( ! $post || 'post' !== get_post_type( $post ) ) {
			return;
		}

		$categories = get_the_category();
		$tags       = get_the_tags();
		$actors     = wp_get_post_terms( $post->ID, 'actors' );

		if ( is_wp_error( $actors ) ) {
			$actors = array();
		}

		if ( ! $categories && ! $tags && ! $actors ) {
			return;
		}

		echo '<div class="tags-list">';

		if ( $categories && majestic_tube_option_is_on( 'show-categories-video-about' ) ) {
			foreach ( (array) $categories as $category ) {
				printf(
					'<a href="%1$s" class="label" title="%2$s"><i class="fa fa-folder"></i> %2$s</a>',
					esc_url( get_category_link( $category->term_id ) ),
					esc_attr( $category->name )
				);
			}
		}

		if ( $actors && majestic_tube_option_is_on( 'show-actors-video-about' ) ) {
			foreach ( (array) $actors as $actor ) {
				$link = get_term_link( $actor );

				if ( is_wp_error( $link ) ) {
					continue;
				}

				printf(
					'<a href="%1$s" class="label" title="%2$s"><i class="fa fa-star"></i> %2$s</a>',
					esc_url( $link ),
					esc_attr( $actor->name )
				);
			}
		}

		if ( $tags && majestic_tube_option_is_on( 'show-tags-video-about' ) ) {
			foreach ( (array) $tags as $tag ) {
				printf(
					'<a href="%1$s" class="label" title="%2$s"><i class="fa fa-tag"></i> %2$s</a>',
					esc_url( get_tag_link( $tag->term_id ) ),
					esc_attr( $tag->name )
				);
			}
		}

		echo '</div>';
	}
}

/**
 * Get a post object (original thin wrapper around get_post()).
 *
 * @param int $post_id Post ID.
 * @return WP_Post|null
 */
if ( ! function_exists( 'wpst_get_post_data' ) ) {
	function wpst_get_post_data( $post_id ) {
		return get_post( $post_id );
	}
}

/**
 * Like percentage of a video (round, original math).
 *
 * @param int $post_id Post ID.
 * @return int
 */
if ( ! function_exists( 'wpst_get_post_like_rate' ) ) {
	function wpst_get_post_like_rate( $post_id ) {
		return majestic_tube_get_post_like_rate( $post_id );
	}
}

/**
 * Whether the current visitor already voted on this video in the last 24h.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
if ( ! function_exists( 'wpst_has_already_voted' ) ) {
	function wpst_has_already_voted( $post_id ) {
		return majestic_tube_has_already_voted( $post_id );
	}
}

/**
 * The like button markup, original classes and data attributes.
 *
 * Front-end scripts bind to `.post-like a` and read data-post_id /
 * data-post_like, so the markup keeps the original shape.
 *
 * @param int $post_id Post ID.
 * @return string HTML.
 */
if ( ! function_exists( 'wpst_get_post_like_link' ) ) {
	function wpst_get_post_like_link( $post_id ) {
		if ( majestic_tube_has_already_voted( $post_id ) ) {
			return '';
		}

		return '<span class="post-like"><a class="button" href="#post-' . absint( $post_id ) . '" data-post_id="' . absint( $post_id ) . '" data-post_like="like"><span class="like" title="' . esc_attr__( 'I like it', 'majestic-tube' ) . '"><i class="fa fa-heart"></i> ' . esc_html__( 'Like it', 'majestic-tube' ) . '</span></a></span>';
	}
}

/**
 * Title of the current listing filter ("Latest videos", ...).
 *
 * No arguments, original signature: the filter comes from the query string or,
 * on the homepage, from the `show-videos-homepage` option.
 *
 * @return string
 */
if ( ! function_exists( 'wpst_get_filter_title' ) ) {
	function wpst_get_filter_title() {
		return majestic_tube_get_filter_title( majestic_tube_get_current_filter() );
	}
}

/**
 * The `active` CSS class for the current listing filter.
 *
 * @param string $filter Filter slug.
 * @return string|false
 */
if ( ! function_exists( 'wpst_selected_filter' ) ) {
	function wpst_selected_filter( $filter ) {
		return majestic_tube_get_current_filter() === $filter ? 'active' : false;
	}
}

/**
 * Current archive URL with the /page/N/ segment removed.
 *
 * The original built this from `home_url( $wp->request )` and cut the string at
 * the first "/page", which breaks on any permalink structure that does not put
 * paging in the path. get_pagenum_link( 1 ) resolves the same target through
 * the rewrite rules, so the URL is correct for path-, query- and plain
 * permalinks alike; the regex is a belt-and-braces fallback for structures
 * where core still emits the segment.
 *
 * @return string
 */
if ( ! function_exists( 'wpst_get_nopaging_url' ) ) {
	function wpst_get_nopaging_url() {
		$nopaging_url = get_pagenum_link( 1 );
		$nopaging_url = preg_replace( '#/page/\d+/?$#', '/', $nopaging_url );

		return trailingslashit( esc_url_raw( $nopaging_url ) );
	}
}

/**
 * Archive pagination, original markup and signature.
 *
 * @param int|string $pages Total pages (empty reads the main query).
 * @param int        $range Pages shown on each side of the current page.
 * @return void
 */
if ( ! function_exists( 'wpst_page_navi' ) ) {
	function wpst_page_navi( $pages = '', $range = 4 ) {
		majestic_tube_page_navi( $pages, $range );
	}
}

/**
 * Page number suffix used by archive titles ("page 2").
 *
 * The original helper had a bug (it read an undefined variable) so it never
 * printed anything; this returns the value instead of dropping it.
 *
 * @param string $separator Separator printed before the page number.
 * @return string
 */
if ( ! function_exists( 'wpst_page_number' ) ) {
	function wpst_page_number( $separator = '' ) {
		$paged = majestic_tube_get_paged();

		if ( $paged > 1 ) {
			return $separator . 'page ' . $paged;
		}

		return '';
	}
}

/**
 * Register the legacy body-class and pingback hooks under their original names
 * when no plugin already provides them.
 */
if ( ! function_exists( 'wpst_body_classes' ) ) {
	function wpst_body_classes( $classes ) {
		return majestic_tube_body_classes( $classes );
	}
}

if ( ! function_exists( 'wpst_pingback_header' ) ) {
	function wpst_pingback_header() {
		majestic_tube_pingback_header();
	}
}

if ( ! function_exists( 'wpst_get_async_post_data' ) ) {
	function wpst_get_async_post_data() {
		majestic_tube_get_post_data();
	}
}

if ( ! function_exists( 'wpst_set_post_views' ) ) {
	function wpst_set_post_views() {
		majestic_tube_set_post_views();
	}
}

if ( ! function_exists( 'wpst_post_like' ) ) {
	function wpst_post_like() {
		majestic_tube_post_like();
	}
}

/*
 * The four helpers below are called by the original theme's own templates but
 * had no shim, so a child theme or plugin copied from KingTube hit a fatal
 * "undefined function" on activation. They are the remainder of the original's
 * template-facing surface.
 */

/**
 * Breadcrumb trail.
 *
 * The original additionally hid the trail on mobile visitors and on the blog
 * home. Majestic Tube's helper only suppresses it on the front page, and the
 * mobile suppression is deliberately not copied: hiding navigation because of a
 * user-agent string is a bug, not a contract, and the same argument is why
 * wp_is_mobile() is no longer called from a fallback anywhere in this theme.
 *
 * @return void
 */
if ( ! function_exists( 'wpst_breadcrumbs' ) ) {
	function wpst_breadcrumbs() {
		majestic_tube_breadcrumbs();
	}
}

/**
 * Post date, original markup: .posted-on wrapping one or two <time> elements.
 *
 * The second <time class="updated"> is only emitted when the post has actually
 * been modified, matching the original so custom CSS keyed on .updated is not
 * given a phantom element on a never-edited post.
 *
 * @return void
 */
if ( ! function_exists( 'wpst_posted_on' ) ) {
	function wpst_posted_on() {
		$format = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';

		if ( get_the_time( 'U' ) !== get_the_modified_time( 'U' ) ) {
			$format = '<time class="entry-date published" datetime="%1$s">%2$s</time><time class="updated" datetime="%3$s">%4$s</time>';
		}

		$time_string = sprintf(
			$format,
			esc_attr( get_the_date( DATE_W3C ) ),
			esc_html( get_the_date() ),
			esc_attr( get_the_modified_date( DATE_W3C ) ),
			esc_html( get_the_modified_date() )
		);

		printf(
			'<span class="posted-on">%s</span>',
			esc_html(
				sprintf(
					/* translators: %s: post date. */
					_x( 'Posted on %s', 'post date', 'majestic-tube' ),
					$time_string
				)
			)
		);
	}
}

/**
 * Prepend the featured image to feed content.
 *
 * Registered on the same two filters the original used, so RSS readers show a
 * thumbnail for videos that have one.
 *
 * @param string $content Feed content.
 * @return string
 */
if ( ! function_exists( 'wpst_rss_post_thumbnail' ) ) {
	function wpst_rss_post_thumbnail( $content ) {
		$post = get_post();

		if ( $post && has_post_thumbnail( $post->ID ) ) {
			$content = '<p>' . get_the_post_thumbnail( $post->ID ) . '</p>' . $content;
		}

		return $content;
	}
	add_filter( 'the_excerpt_rss', 'wpst_rss_post_thumbnail' );
	add_filter( 'the_content_feed', 'wpst_rss_post_thumbnail' );
}
