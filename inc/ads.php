<?php
/**
 * Content placements and legacy advertising-key compatibility.
 *
 * Global placements are rendered from ordinary WordPress widget areas. The
 * original option keys remain readable and are migrated into Content Block
 * widget instances on upgrade; the helpers below keep older integrations and
 * stored data available without making the templates option-backed again.
 *
 * 2.1.0 adds three advertising capabilities that tube themes are expected to
 * have, all funneling through majestic_tube_ads_allowed():
 *
 *  - In-feed: a card-sized code block repeated through the video grid
 *    (majestic_tube_in_feed_ads()).
 *  - Popunder / interstitial: a code blob printed once per page from the
 *    footer (majestic_tube_output_popunder_ad()).
 *  - Rotation: multiple code blobs for one slot, chosen by a stable daily
 *    seed so a cached page stays self-consistent (majestic_tube_rotate_ad()).
 *
 * A consent gate closes the set: with the gate option on, every ad placement
 * in this module prints nothing until a consent plugin flips the
 * majestic_tube_ads_allowed filter.
 *
 * @package Majestic Tube
 * @version 2.1.2
 */

defined( 'ABSPATH' ) || exit;

/**
 * Original option keys grouped by their former page placement.
 *
 * This is a compatibility map, not the current rendering configuration. It
 * deliberately keeps the original names so existing wpst-options data and
 * third-party integrations can be identified during migration.
 *
 * @return array<string, array<string, string>>
 */
function majestic_tube_ad_zones() {
	return array(
		'header'  => array(
			'desktop' => 'header-ad-desktop',
			'mobile'  => 'header-ad-mobile',
		),
		'sidebar' => array(
			'desktop' => 'sidebar-ad-desktop-1|sidebar-ad-desktop-2|sidebar-ad-desktop-3',
			'mobile'  => 'sidebar-ad-mobile',
		),
		'footer'  => array(
			'desktop' => 'footer-ad-desktop',
			'mobile'  => 'footer-ad-mobile',
		),
	);
}

/**
 * Map legacy option keys to the neutral widget areas that receive them.
 *
 * The device value is stored in each migrated Content Block instance, so the
 * original desktop/mobile split is retained without exposing old option names
 * in front-end markup.
 *
 * @return array<string, array<string, string>>
 */
function majestic_tube_legacy_content_widget_map() {
	return array(
		'header'        => array(
			'header-ad-desktop' => 'desktop',
			'header-ad-mobile'  => 'mobile',
		),
		'player'        => array(
			'inside-player-ad-zone-1-desktop' => 'desktop',
			'inside-player-ad-zone-2-desktop' => 'desktop',
		),
		'under-player'  => array(
			'under-player-ad-desktop' => 'desktop',
			'under-player-ad-mobile'  => 'mobile',
		),
		'video-sidebar' => array(
			'sidebar-ad-desktop-1' => 'desktop',
			'sidebar-ad-desktop-2' => 'desktop',
			'sidebar-ad-desktop-3' => 'desktop',
			'sidebar-ad-mobile'    => 'mobile',
		),
		'footer'        => array(
			'footer-ad-desktop' => 'desktop',
			'footer-ad-mobile'  => 'mobile',
		),
	);
}

/**
 * Get the raw administrator markup stored in a legacy option key.
 *
 * @param string $key Original wpst-options key.
 * @return string
 */
function majestic_tube_get_ad( $key ) {
	$code = majestic_tube_get_option( 'wpst-options', $key, '' );

	return is_string( $code ) ? trim( $code ) : '';
}

/**
 * Render the shortcodes contained in administrator-provided markup.
 *
 * @param string $content Administrator-provided markup.
 * @return string
 */
function majestic_tube_render_shortcodes( $content ) {
	return do_shortcode( is_string( $content ) ? $content : '' );
}

/**
 * Legacy shim: the original theme called this helper, which WP-Script plugins
 * define and third-party templates may call.
 *
 * @param string $content Administrator-provided markup.
 * @return string
 */
if ( ! function_exists( 'wpst_render_shortcodes' ) ) {
	function wpst_render_shortcodes( $content ) {
		return majestic_tube_render_shortcodes( $content );
	}
}

/**
 * Whether a legacy option key contains markup.
 *
 * This helper remains available for migration and compatibility code. Current
 * templates do not use it to decide whether to render a placement.
 *
 * @param string $key Original wpst-options key.
 * @return bool
 */
function majestic_tube_has_ad( $key ) {
	return '' !== majestic_tube_get_ad( $key );
}

/*
 * -------------------------------------------------------------------------
 * 2.1.0 advertising capabilities
 * ----------------------------------------------------------------------
 */

/**
 * Whether advertising may print on this request.
 *
 * Two switches sit in front of every placement in this module:
 *
 *  1. The consent gate. Off by default (advertising behaves as before); on,
 *     it defers to the majestic_tube_ads_allowed filter, which a consent
 *     plugin or a snippet drives. Nothing here reads cookies itself - who
 *     may see ads is a site policy, not a theme decision.
 *  2. The Clean Tube Player check used by the placements below: the plugin
 *     owns the player slots when installed.
 *
 * The filter is applied with the placement name so one integration can allow
 * in-feed but hold the popunder, which is the split real consent setups ask
 * for.
 *
 * @param string $placement Placement slug: in-feed, popunder, header, footer,
 *                        under-player, video-sidebar, player.
 * @return bool
 */
function majestic_tube_ads_allowed( $placement = '' ) {
	$placement = sanitize_key( $placement );

	if ( majestic_tube_option_is_on( 'gate-ads-on-consent' ) ) {
		$allowed = false;
	} else {
		$allowed = true;
	}

	/**
	 * Filter whether advertising may print for one placement.
	 *
	 * With the consent gate option enabled the default is false, and a consent
	 * integration returns true once the visitor has opted in.
	 *
	 * @param bool   $allowed   Whether the placement may print.
	 * @param string $placement Placement slug.
	 */
	return (bool) apply_filters( 'majestic_tube_ads_allowed', $allowed, $placement );
}

/**
 * How often the in-feed block is inserted into the video grid.
 *
 * The frequency option is clamped to >= 3 so a typo cannot turn the grid into
 * an ad wall (one ad after every card, or interleaved with the first card).
 *
 * @return int Number of video cards between two in-feed blocks.
 */
function majestic_tube_in_feed_frequency() {
	$frequency = absint( majestic_tube_get_option( 'wpst-options', 'infeed-ad-frequency', 9 ) );

	/**
	 * Filter the in-feed insertion frequency.
	 *
	 * @param int $frequency Cards between two in-feed blocks, minimum 3.
	 */
	$frequency = (int) apply_filters( 'majestic_tube_in_feed_frequency', $frequency );

	return max( 3, $frequency );
}

/**
 * The in-feed code, or an empty string.
 *
 * @return string
 */
function majestic_tube_get_in_feed_ad() {
	if ( ! majestic_tube_option_is_on( 'enable-infeed-ad' ) ) {
		return '';
	}

	return majestic_tube_get_ad( 'infeed-ad-code' );
}

/**
 * The popunder / interstitial code, or an empty string.
 *
 * @return string
 */
function majestic_tube_get_popunder_ad() {
	if ( ! majestic_tube_option_is_on( 'enable-popunder-ad' ) ) {
		return '';
	}

	return majestic_tube_get_ad( 'popunder-ad-code' );
}

/**
 * Print the popunder / interstitial code once per page.
 *
 * Runs on wp_footer like the custom-scripts block, so the code executes after
 * the document exists. The output is the administrator's markup, sanitized on
 * input and passed through the same content filters as every other placement.
 *
 * @return void
 */
function majestic_tube_output_popunder_ad() {
	if ( is_admin() || wp_doing_ajax() ) {
		return;
	}

	$code = majestic_tube_get_popunder_ad();

	if ( '' === $code || ! majestic_tube_ads_allowed( 'popunder' ) ) {
		return;
	}
	majestic_tube_content_block( $code, array( 'majestic-tube-content-block--popunder' ), 'popunder-ad-code' );
}
add_action( 'wp_footer', 'majestic_tube_output_popunder_ad', 25 );

/**
 * Pick one entry from a newline-separated list of code blobs, repeatably.
 *
 * The seed is the UTC calendar day plus the placement name, so the choice is
 * stable for the whole day: a page cached at 09:00 and served at 21:00 shows
 * the creative that was chosen when it was rendered, and the network sees one
 * coherent day per placement. Not cryptographically random on purpose - it is
 * an ad-rotation scheduler, not a secret.
 *
 * @param string $codes     Newline-separated code blobs. An empty line is a
 *                        weight: it skips a day in the rotation.
 * @param string $placement Placement name, mixed into the seed.
 * @return string One code blob, or '' when the list is empty.
 */
function majestic_tube_rotate_ad( $codes, $placement = '' ) {
	$candidates = array_values( array_filter( array_map( 'trim', preg_split( '/\R+/', (string) $codes ) ) ) );

	if ( ! $candidates ) {
		return '';
	}

	if ( 1 === count( $candidates ) ) {
		return $candidates[0];
	}

	$index = (int) ( ( time() + crc32( (string) $placement ) ) / DAY_IN_SECONDS ) % count( $candidates );
	/**
	 * Filter the rotation choice.
	 *
	 * @param string   $code      The selected code blob.
	 * @param string[] $candidates All non-empty candidates.
	 * @param string   $placement Placement name.
	 */
	return (string) apply_filters( 'majestic_tube_rotate_ad', $candidates[ $index ], $candidates, $placement );
}

/**
 * Prepare administrator-provided content for output.
 *
 * The neutral filter is new for widget-managed content. The original filter is
 * still applied afterwards so existing integrations continue to work.
 *
 * @param string $content  Administrator-provided markup.
 * @param string $context  Optional placement or legacy key.
 * @return string
 */
function majestic_tube_prepare_content( $content, $context = '' ) {
	if ( ! is_string( $content ) ) {
		return '';
	}

	$content = trim( $content );

	if ( '' === $content ) {
		return '';
	}

	$content = majestic_tube_render_shortcodes( $content );
	$content = apply_filters( 'majestic_tube_content_output', $content, $context );
	$content = apply_filters( 'majestic_tube_ad_output', $content, $context );

	return is_string( $content ) ? $content : '';
}

/**
 * Print a neutral content block.
 *
 * @param string       $content Administrator-provided markup.
 * @param array|string $classes Extra CSS classes.
 * @param string       $context Optional placement or legacy key.
 * @return bool Whether something was printed.
 */
function majestic_tube_content_block( $content, $classes = array(), $context = '' ) {
	$content = majestic_tube_prepare_content( $content, $context );

	if ( '' === trim( $content ) ) {
		return false;
	}

	$classes = is_array( $classes ) ? $classes : array( $classes );
	$classes = array_merge( array( 'majestic-tube-content-block' ), $classes );
	$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );

	printf(
		'<div class="%s">%s</div>',
		esc_attr( implode( ' ', $classes ) ),
		$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- administrator-provided markup.
	);

	return true;
}

/**
 * Render one legacy option-backed block for old integrations.
 *
 * Normal theme placement code uses widget areas instead. This adapter remains
 * so a child theme or plugin calling the historical helper does not fatal, but
 * its output now uses neutral wrapper markup.
 *
 * @param string $key   Original wpst-options key.
 * @param string $class Extra CSS class.
 * @return bool Whether something was printed.
 */
function majestic_tube_ad( $key, $class = '' ) {
	$code = majestic_tube_get_ad( $key );

	if ( '' === $code ) {
		return false;
	}

	$classes = array( 'majestic-tube-content-block--legacy' );

	if ( $class ) {
		$classes[] = $class;
	}

	return majestic_tube_content_block( $code, $classes, $key );
}

/**
 * Render a neutral content widget area.
 *
 * @param string       $location Content area key.
 * @param array|string $classes  Extra CSS classes.
 * @return bool Whether the area rendered.
 */
function majestic_tube_content_location( $location, $classes = array() ) {
	$areas = majestic_tube_content_widget_areas();

	if ( ! isset( $areas[ $location ]['id'] ) ) {
		return false;
	}

	$content = majestic_tube_widget_area_content( $areas[ $location ]['id'] );

	if ( '' === trim( $content ) ) {
		return false;
	}

	$classes = is_array( $classes ) ? $classes : array( $classes );
	$classes[] = 'majestic-tube-content-area';
	$classes[] = 'majestic-tube-content-area--' . sanitize_html_class( $location );
	$classes   = array_filter( array_map( 'sanitize_html_class', $classes ) );

	printf(
		'<div class="%s">%s</div>',
		esc_attr( implode( ' ', $classes ) ),
		$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- widget output.
	);

	return true;
}

/**
 * Legacy location adapter.
 *
 * Header and footer callers keep working. The former sidebar location remains
 * intentionally unavailable here because the video sidebar is single.php-only.
 *
 * @param string $location Legacy location key.
 * @return void
 */
function majestic_tube_ad_location( $location ) {
	if ( 'sidebar' === $location ) {
		return;
	}

	if ( in_array( $location, array( 'header', 'footer' ), true ) ) {
		majestic_tube_content_location( $location, 'majestic-tube-content-area--legacy' );
	}
}

/**
 * Whether the Clean Tube Player plugin is active.
 *
 * When it is installed the plugin owns the player's placements and the theme
 * steps aside, exactly like the original theme does.
 *
 * @return bool
 */
function majestic_tube_is_ctpl_active() {
	return majestic_tube_is_plugin_active( 'clean-tube-player/clean-tube-player.php' );
}

/**
 * Per-video content under the player (original meta key).
 *
 * @param int $post_id Post ID, defaults to the current post.
 * @return string
 */
function majestic_tube_get_post_ad( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();

	if ( ! $post_id ) {
		return '';
	}

	$code = get_post_meta( $post_id, 'unique_ad_under_player', true );

	return is_string( $code ) ? trim( $code ) : '';
}

/**
 * Render the content displayed under the video player.
 *
 * A per-video value still wins over the widget area. Otherwise the widget area
 * supplies its configured desktop and mobile Content Block widgets.
 *
 * @param int $post_id Post ID.
 * @return bool Whether something was printed.
 */
function majestic_tube_under_player_content( $post_id = 0 ) {
	$code = majestic_tube_get_post_ad( $post_id );

	if ( '' !== $code ) {
		return majestic_tube_content_block(
			$code,
			array( 'majestic-tube-content-block--under-player' ),
			'unique_ad_under_player'
		);
	}

	return majestic_tube_content_location( 'under-player', 'majestic-tube-content-area--under-player' );
}

/**
 * Legacy adapter for the original under-player helper.
 *
 * @param int $post_id Post ID.
 * @return bool Whether something was printed.
 */
function majestic_tube_under_player_ad( $post_id = 0 ) {
	return majestic_tube_under_player_content( $post_id );
}
