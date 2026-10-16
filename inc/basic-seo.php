<?php
/**
 * Basic SEO - the theme's own description, canonical and social cards, plus the
 * optional content-rating labels.
 *
 * Search appearance belongs to the SEO plugin a site runs, and this theme steps
 * aside for one. What it did not offer was a usable fallback for a site that
 * runs no SEO plugin at all: such a page had core's <title> and the robots
 * directive, and nothing else - no meta description to show in a result, no
 * canonical outside singular views, and no Open Graph card, so a shared link
 * rendered as a bare URL with no picture.
 *
 * This module is that fallback and deliberately nothing more:
 *   - It is on by default, so a fresh install is describable without installing
 *     anything else.
 *   - It switches itself off, entirely, the moment a supported SEO plugin is
 *     detected. Two sources of truth for one page title is worse than one, and
 *     the plugin is the one the site owner deliberately chose.
 *   - It never prints a <title>. The browser title stays core's, built from the
 *     title-tag support the theme already declares; the homepage override below
 *     filters those title parts rather than emitting a competing tag.
 *   - It never prints a canonical on a singular view, because core's
 *     rel_canonical() already does, and two canonicals on one page is a
 *     contradiction rather than a clarification.
 *
 * The content-rating labels at the bottom are separate from all of that. They
 * are not search appearance and no SEO plugin emits them, so they keep working
 * while a plugin owns everything else: a site that runs Yoast can still be
 * labeled as adult content.
 *
 * @package Majestic Tube
 * @version 2.5.0
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Whose job this is
 * ---------------------------------------------------------------------- */

/**
 * Whether an SEO plugin is handling search appearance.
 *
 * Each signal is the plugin's own bootstrap constant, which is the most
 * reliable indicator available from a theme: plugins load before the theme, so
 * the constant is defined by the time functions.php runs. The class and
 * function checks are secondary, for the odd build that drops a constant - a
 * false negative here means the theme and the plugin both print a description,
 * so the cheap extra checks are worth carrying.
 *
 * This is not a WordPress API and cannot be exhaustive. A site running an SEO
 * plugin not listed here can say so, or silence the theme outright, through
 * the options: turning "Basic SEO" off is the supported way to hand search
 * appearance to anything this cannot recognize.
 *
 * @return bool
 */
function majestic_tube_seo_plugin_detected() {
	$detected = (
		defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options', false )            // Yoast SEO.
		|| defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath', false )         // Rank Math.
		|| defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' )                  // All in One SEO.
		|| defined( 'SEOPRESS_VERSION' )                                              // SEOPress.
		|| defined( 'THE_SEO_FRAMEWORK_VERSION' ) || function_exists( 'the_seo_framework' ) // The SEO Framework.
		|| defined( 'SLIM_SEO_VER' )                                                  // Slim SEO.
	);

	/**
	 * Filter whether an SEO plugin is considered to be handling this site.
	 *
	 * Return true from a plugin's own bootstrap to claim search appearance
	 * without being on the list above.
	 *
	 * @param bool $detected Whether one of the known plugins was detected.
	 */
	return (bool) apply_filters( 'majestic_tube_seo_plugin_detected', $detected );
}

/**
 * Whether the theme should print its own search appearance on this request.
 *
 * @return bool
 */
function majestic_tube_basic_seo_enabled() {
	if ( majestic_tube_seo_plugin_detected() ) {
		return false;
	}

	$enabled = majestic_tube_option_is_on( 'enable-basic-seo' );

	/**
	 * Filter whether the theme prints its own basic SEO tags.
	 *
	 * @param bool $enabled Whether the option is on and no SEO plugin was detected.
	 */
	return (bool) apply_filters( 'majestic_tube_basic_seo_enabled', $enabled );
}

/**
 * Whether a view is worth describing at all.
 *
 * Search results and 404s are noindexed by core and have nothing stable to say
 * about themselves, so they are left exactly as they were.
 *
 * @return bool
 */
function majestic_tube_seo_view_is_describable() {
	return ! ( is_search() || is_404() || is_feed() || is_robots() );
}

/* -------------------------------------------------------------------------
 * The browser title
 * ---------------------------------------------------------------------- */

/**
 * The homepage search title written in the options.
 *
 * @return string
 */
function majestic_tube_seo_home_title() {
	return trim( (string) majestic_tube_get_option( 'wpst-options', 'home-seo-title', '' ) );
}

/**
 * Use the homepage search title in the document title on the front page.
 *
 * The title is not printed again here; the parts core already assembles are
 * filtered, so <title> stays a single tag and the site name separator keeps
 * working everywhere else.
 *
 * @param array $parts Title parts: title, page, tagline, site.
 * @return array
 */
function majestic_tube_seo_filter_title_parts( $parts ) {
	if ( ! majestic_tube_basic_seo_enabled() || ! is_array( $parts ) ) {
		return $parts;
	}

	if ( ! ( is_front_page() || is_home() ) ) {
		return $parts;
	}

	$home = majestic_tube_seo_home_title();

	if ( '' === $home ) {
		return $parts;
	}

	$parts['title'] = $home;

	/*
	 * Core appends the tagline to the site name. An editor who typed a whole
	 * homepage title has already said what they want the tab to read, so the
	 * tagline would only lengthen it.
	 */
	unset( $parts['tagline'] );

	return $parts;
}
add_filter( 'document_title_parts', 'majestic_tube_seo_filter_title_parts' );

/* -------------------------------------------------------------------------
 * Description
 * ---------------------------------------------------------------------- */

/**
 * The description for the current view, before truncation.
 *
 * The fallback chain is the one a search result expects: what an editor wrote
 * for this page, then the page's own content, then what the site as a whole
 * says about itself. It always returns something on a describable view, because
 * an empty meta description is worse than a generic one.
 *
 * @return string Plain text, single-spaced.
 */
function majestic_tube_seo_description_text() {
	$text = '';

	if ( is_front_page() || is_home() ) {
		$text = (string) majestic_tube_get_option( 'wpst-options', 'home-seo-description', '' );

		/*
		 * The visible homepage introduction is the fallback the option itself
		 * promises ("also the fallback search description"), and it is the
		 * paragraph the editor has already written about this page. A tagline is
		 * a one-line site motto, which is right when there is no introduction,
		 * and second best when there is one.
		 */
		if ( '' === trim( $text ) ) {
			$text = (string) majestic_tube_get_option( 'wpst-options', 'seo-footer-text', '' );
		}
	} elseif ( is_singular() ) {
		$post_id = (int) get_queried_object_id();

		if ( ! function_exists( 'post_password_required' ) || ! post_password_required( $post_id ) ) {
			$text = trim( (string) get_post_field( 'post_excerpt', $post_id ) );

			if ( '' === $text ) {
				$text = (string) get_post_field( 'post_content', $post_id );
			}

			/*
			 * A video post often carries no body text and no excerpt at all. The
			 * video module already assembles a sentence from the title and its
			 * categories for that case, and it is the same sentence the VideoObject
			 * is given, so the two cannot disagree.
			 */
			if ( '' === trim( wp_strip_all_tags( (string) $text ) ) && function_exists( 'majestic_tube_video_seo_description' ) ) {
				$text = majestic_tube_video_seo_description( $post_id );
			}
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$text = term_description();
	} elseif ( is_author() ) {
		$author = get_queried_object();

		if ( $author && isset( $author->description ) ) {
			$text = (string) $author->description;
		}
	}

	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( (string) $text ) ) ) );

	if ( '' === $text ) {
		$text = trim( (string) get_bloginfo( 'description' ) );
	}

	/**
	 * Filter the description used by the theme's meta tags.
	 *
	 * @param string $text    Plain text.
	 * @param string $context Either 'description' or 'social'.
	 */
	return (string) apply_filters( 'majestic_tube_seo_description', $text );
}

/**
 * The description as a search result would show it.
 *
 * Truncated because a description that runs to a paragraph is cut by the search
 * engine anyway, and the cut is then made without regard to words. 160
 * characters is the long-standing display width; the length is filterable
 * because that number moves.
 *
 * @param string $context Either 'description' or 'social'.
 * @return string
 */
function majestic_tube_seo_excerpt( $context = 'description' ) {
	$text = majestic_tube_seo_description_text();

	if ( '' === $text ) {
		return '';
	}

	$length = (int) apply_filters( 'majestic_tube_seo_description_length', 'social' === $context ? 300 : 160 );
	$length = max( 40, $length );

	return wp_html_excerpt( $text, $length, '…' );
}

/* -------------------------------------------------------------------------
 * URLs
 * ---------------------------------------------------------------------- */

/**
 * The address of the page being viewed, for og:url and for a canonical.
 *
 * Singular views answer with their permalink; paged views answer with the
 * address of the page actually shown, so page 2 does not claim to be page 1.
 * Views with no address of their own - search and 404 - answer with an empty
 * string, and the caller prints nothing.
 *
 * @return string
 */
function majestic_tube_seo_view_url() {
	$url = '';

	if ( is_singular() ) {
		$url = (string) get_permalink( (int) get_queried_object_id() );
	} elseif ( is_front_page() || is_home() ) {
		$url = home_url( '/' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();

		if ( $term && ! is_wp_error( $term ) ) {
			$link = get_term_link( $term );

			if ( ! is_wp_error( $link ) ) {
				$url = (string) $link;
			}
		}
	} elseif ( is_author() ) {
		$url = (string) get_author_posts_url( (int) get_queried_object_id() );
	} elseif ( is_post_type_archive() ) {
		$post_type = get_query_var( 'post_type' );

		if ( is_array( $post_type ) ) {
			$post_type = reset( $post_type );
		}

		if ( $post_type ) {
			$url = (string) get_post_type_archive_link( (string) $post_type );
		}
	}

	if ( '' === $url || is_singular() ) {
		return $url;
	}

	$paged = max( (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

	if ( $paged > 1 ) {
		$url = get_option( 'permalink_structure' )
			? user_trailingslashit( trailingslashit( $url ) . 'page/' . $paged, 'paged' )
			: add_query_arg( 'paged', $paged, $url );
	}

	return $url;
}

/**
 * The canonical address, or an empty string when core already prints one.
 *
 * Core hooks rel_canonical() to wp_head for singular queries only. That is
 * exactly the split used here: a singular view leaves the tag to core, and an
 * archive - which core does not describe - gets one from the theme.
 *
 * @return string
 */
function majestic_tube_seo_canonical_url() {
	if ( is_singular() ) {
		return '';
	}

	return majestic_tube_seo_view_url();
}

/* -------------------------------------------------------------------------
 * Social card
 * ---------------------------------------------------------------------- */

/**
 * The image a shared link should show.
 *
 * The widest crop of the video's own picture wins on a video page, because that
 * is the image a viewer recognizes; a term archive uses its term image; and
 * anything else uses the site logo or the Site Icon, so a card is never
 * pictureless.
 *
 * @return string URL, or an empty string when the site has no usable image.
 */
function majestic_tube_seo_social_image() {
	if ( is_singular() && function_exists( 'majestic_tube_get_poster_url' ) ) {
		$post_id = (int) get_queried_object_id();

		if ( ! function_exists( 'post_password_required' ) || ! post_password_required( $post_id ) ) {
			$url = (string) majestic_tube_get_poster_url( $post_id );

			if ( '' !== $url ) {
				return $url;
			}
		}
	}

	if ( ( is_category() || is_tag() || is_tax() ) && function_exists( 'majestic_tube_get_term_image_url' ) ) {
		$term = get_queried_object();

		if ( $term instanceof WP_Term ) {
			$url = (string) majestic_tube_get_term_image_url( (int) $term->term_id, (string) $term->taxonomy, 'majestic-tube-thumb-large' );

			if ( '' !== $url ) {
				return $url;
			}
		}
	}

	$logo_id = (int) get_theme_mod( 'custom_logo' );

	if ( $logo_id ) {
		$src = wp_get_attachment_image_src( $logo_id, 'full' );

		if ( $src && ! empty( $src[0] ) ) {
			return (string) $src[0];
		}
	}

	return (string) get_site_icon_url( 512 );
}

/* -------------------------------------------------------------------------
 * Head output
 * ---------------------------------------------------------------------- */

/**
 * Print the description, canonical and social card.
 *
 * Priority 2 keeps the description near the top of the head; on a singular
 * video page the video module is already buffering the whole head from
 * priority 1, so these tags are folded into that buffer and its og:image
 * fallback sees the image printed here and stands down rather than adding a
 * second one.
 *
 * @return void
 */
function majestic_tube_seo_output_head() {
	if ( ! majestic_tube_basic_seo_enabled() || ! majestic_tube_seo_view_is_describable() ) {
		return;
	}

	$url         = (string) majestic_tube_seo_view_url();
	$description = majestic_tube_seo_excerpt( 'description' );
	$social      = majestic_tube_seo_excerpt( 'social' );
	$image       = (string) majestic_tube_seo_social_image();
	$title       = wp_get_document_title();
	$lines       = array();

	if ( '' !== $description ) {
		$lines[] = sprintf( '<meta name="description" content="%s" />', esc_attr( $description ) );
	}

	$canonical = majestic_tube_seo_canonical_url();

	if ( '' !== $canonical ) {
		$lines[] = sprintf( '<link rel="canonical" href="%s" />', esc_url( $canonical ) );
	}

	// Nothing below can be addressed without a URL, so an unaddressable view
	// keeps only the description it may have printed above.
	if ( '' === $url ) {
		majestic_tube_seo_print( $lines );

		return;
	}

	if ( '' !== $title ) {
		$lines[] = sprintf( '<meta property="og:title" content="%s" />', esc_attr( $title ) );
		$lines[] = sprintf( '<meta name="twitter:title" content="%s" />', esc_attr( $title ) );
	}

	if ( '' !== $social ) {
		$lines[] = sprintf( '<meta property="og:description" content="%s" />', esc_attr( $social ) );
		$lines[] = sprintf( '<meta name="twitter:description" content="%s" />', esc_attr( $social ) );
	}

	$lines[] = sprintf( '<meta property="og:url" content="%s" />', esc_url( $url ) );
	$lines[] = sprintf( '<meta property="og:type" content="%s" />', is_singular() ? 'article' : 'website' );
	$lines[] = sprintf( '<meta property="og:site_name" content="%s" />', esc_attr( get_bloginfo( 'name' ) ) );
	$lines[] = sprintf( '<meta property="og:locale" content="%s" />', esc_attr( get_locale() ) );

	if ( is_singular() ) {
		$post_id = (int) get_queried_object_id();

		$lines[] = sprintf( '<meta property="article:published_time" content="%s" />', esc_attr( (string) get_post_time( 'c', true, $post_id ) ) );
		$lines[] = sprintf( '<meta property="article:modified_time" content="%s" />', esc_attr( (string) get_post_modified_time( 'c', true, $post_id ) ) );
	}

	if ( '' !== $image ) {
		$lines[] = sprintf( '<meta property="og:image" content="%s" />', esc_url( $image ) );
		$lines[] = sprintf( '<meta name="twitter:image" content="%s" />', esc_url( $image ) );
	}

	$lines[] = sprintf(
		'<meta name="twitter:card" content="%s" />',
		'' !== $image ? 'summary_large_image' : 'summary'
	);

	majestic_tube_seo_print( $lines );
}

/**
 * Print a run of head tags in one marked block.
 *
 * @param string[] $lines Complete tag markup.
 * @return void
 */
function majestic_tube_seo_print( $lines ) {
	$lines = array_filter( array_map( 'strval', (array) $lines ) );

	if ( ! $lines ) {
		return;
	}

	echo "\n<!-- Majestic Tube: basic SEO -->\n";

	foreach ( $lines as $line ) {
		echo $line . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the caller.
	}
}
add_action( 'wp_head', 'majestic_tube_seo_output_head', 2 );

/* -------------------------------------------------------------------------
 * Content rating labels
 * ---------------------------------------------------------------------- */

/**
 * The labels an adult site may need to declare what it is.
 *
 * Both are opt-in and neither is search appearance, so they are printed even
 * while an SEO plugin owns everything else: no general-purpose SEO plugin emits
 * them, and a site cannot label itself through the plugin instead.
 *
 * The RTA label is the one Restricted To Adults prescribes verbatim and is what
 * parental-control and filtering software looks for. `rating=adult` is the
 * generic value Google documents. Google honors either value as an explicit
 * content signal, and neither one is a noindex instruction - to keep pages out
 * of the index, noindex is the tool, not this.
 *
 * @return void
 */
function majestic_tube_seo_output_rating_labels() {
	if ( is_admin() || is_feed() ) {
		return;
	}

	$lines = array();

	if ( majestic_tube_option_is_on( 'adult-content-rating' ) ) {
		$lines[] = '<meta name="rating" content="adult" />';
	}

	if ( majestic_tube_option_is_on( 'rta-label' ) ) {
		$lines[] = '<meta name="RATING" content="RTA-5042-1996-1400-1577-RTA" />';
	}

	if ( ! $lines ) {
		return;
	}

	echo "\n<!-- Majestic Tube: content rating -->\n";

	foreach ( $lines as $line ) {
		echo $line . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
	}
}
add_action( 'wp_head', 'majestic_tube_seo_output_rating_labels', 3 );
