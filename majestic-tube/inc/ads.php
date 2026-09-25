<?php
/**
 * Content placements and legacy advertising-key compatibility.
 *
 * Global placements are rendered from ordinary WordPress widget areas. The
 * original option keys remain readable and are migrated into Content Block
 * widget instances on upgrade; the helpers below keep older integrations and
 * stored data available without making the templates option-backed again.
 *
 * @package Majestic Tube
 * @version 2.0.6
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
