<?php
/**
 * Script and style enqueuing.
 *
 * @package Majestic Tube
 * @version 2.0.9
 */

defined( 'ABSPATH' ) || exit;

/**
 * Video.js version bundled with the theme.
 */
define( 'MAJESTIC_TUBE_VIDEOJS_VERSION', '8.10.0' );

/**
 * Path (and URL) of the self-hosted Video.js build.
 *
 * @param string $type Either "js" or "css".
 * @return array{path: string, url: string}
 */
function majestic_tube_videojs_asset( $type ) {
	$files = array(
		'js'  => 'assets/vendor/videojs/video.min.js',
		'css' => 'assets/vendor/videojs/video-js.min.css',
	);

	$relative = isset( $files[ $type ] ) ? $files[ $type ] : $files['js'];

	return array(
		'path' => MAJESTIC_TUBE_DIR . '/' . $relative,
		'url'  => MAJESTIC_TUBE_URI . '/' . $relative,
	);
}

/**
 * Cache-busting version for a theme asset file.
 *
 * @param string $relative Path relative to the theme root.
 * @return string
 */
function majestic_tube_asset_version( $relative ) {
	$path = MAJESTIC_TUBE_DIR . '/' . ltrim( $relative, '/' );

	return file_exists( $path ) ? MAJESTIC_TUBE_VERSION . '.' . filemtime( $path ) : MAJESTIC_TUBE_VERSION;
}

/**
 * Whether the player scripts should be loaded from the CDN.
 *
 * The theme ships Video.js under assets/vendor/videojs; the CDN stays as a
 * fallback for installations where the vendor files were removed.
 *
 * @return bool
 */
function majestic_tube_use_videojs_cdn() {
	/**
	 * Filter whether Video.js is loaded from the CDN instead of the bundled copy.
	 *
	 * @param bool $use_cdn Defaults to true when the bundled files are missing.
	 */
	return (bool) apply_filters( 'majestic_tube_videojs_cdn', ! file_exists( majestic_tube_videojs_asset( 'js' )['path'] ) );
}

/**
 * Whether the Video.js player should be used on this request.
 *
 * Skips the native-player installations and, like the original theme, steps
 * aside when the Clean Tube Player plugin provides its own player.
 *
 * @return bool
 */
function majestic_tube_player_enabled() {
	if ( ! is_single() ) {
		return false;
	}

	if ( majestic_tube_option_is_on( 'use-native-player' ) ) {
		return false;
	}

	if ( majestic_tube_is_ctpl_active() ) {
		return false;
	}

	/**
	 * Filter whether Video.js is enqueued on this request.
	 *
	 * @param bool $enabled Whether the player is used.
	 */
	return (bool) apply_filters( 'majestic_tube_enqueue_videojs', true );
}

/**
 * Enqueue front-end scripts and styles.
 */
function majestic_tube_enqueue_assets() {
	$version = majestic_tube_asset_version( 'assets/js/main.js' );
	$player  = majestic_tube_player_enabled();
	$deps    = array();

	$ratio        = (string) majestic_tube_get_option( 'wpst-options', 'thumbnails-ratio', '16/9' );
	$ctpl_install = majestic_tube_is_ctpl_active();
	$post_id      = is_single() ? (int) get_queried_object_id() : 0;

	// Main stylesheet (style.css header + component styles). Asset filemtimes
	// prevent a browser/CDN from continuing to serve the previous dark pass.
	wp_enqueue_style( 'majestic-tube-style', get_stylesheet_uri(), array(), majestic_tube_asset_version( 'style.css' ) );

	// main.css also carries the theme's design tokens; the type is the
	// operating system's own interface font, so nothing extra is enqueued.
	wp_enqueue_style(
		'majestic-tube-main',
		MAJESTIC_TUBE_URI . '/assets/css/main.css',
		array( 'majestic-tube-style' ),
		majestic_tube_asset_version( 'assets/css/main.css' )
	);

	// RTL stylesheets (base + component overrides).
	if ( is_rtl() ) {
		// Core normally prints rtl.css directly from wp_head. Enqueue it here
		// for deterministic ordering, and remove that second output path.
		remove_action( 'wp_head', 'locale_stylesheet' );
		wp_enqueue_style(
			'majestic-tube-rtl-style',
			MAJESTIC_TUBE_URI . '/rtl.css',
			array( 'majestic-tube-style' ),
			majestic_tube_asset_version( 'rtl.css' )
		);
		wp_enqueue_style(
			'majestic-tube-rtl',
			MAJESTIC_TUBE_URI . '/assets/css/main-rtl.css',
			array( 'majestic-tube-main', 'majestic-tube-rtl-style' ),
			majestic_tube_asset_version( 'assets/css/main-rtl.css' )
		);
	}

	// Video.js, loaded before main.js so the API is available when it runs.
	if ( $player ) {
		if ( majestic_tube_use_videojs_cdn() ) {
			wp_enqueue_style( 'majestic-tube-videojs', 'https://vjs.zencdn.net/' . MAJESTIC_TUBE_VIDEOJS_VERSION . '/video-js.min.css', array(), MAJESTIC_TUBE_VIDEOJS_VERSION );
			wp_enqueue_script(
				'majestic-tube-videojs',
				'https://vjs.zencdn.net/' . MAJESTIC_TUBE_VIDEOJS_VERSION . '/video.min.js',
				array(),
				MAJESTIC_TUBE_VIDEOJS_VERSION,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		} else {
			$local_css = majestic_tube_videojs_asset( 'css' );
			$local_js  = majestic_tube_videojs_asset( 'js' );

			wp_enqueue_style(
				'majestic-tube-videojs',
				$local_css['url'],
				array(),
				majestic_tube_asset_version( 'assets/vendor/videojs/video-js.min.css' )
			);
			wp_enqueue_script(
				'majestic-tube-videojs',
				$local_js['url'],
				array(),
				majestic_tube_asset_version( 'assets/vendor/videojs/video.min.js' ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}

		$deps[] = 'majestic-tube-videojs';
	}

	// Player skin + quality selector: needed by both the Video.js and the
	// native player, so it is loaded on any single video.
	if ( is_single() ) {
		wp_enqueue_style(
			'majestic-tube-player',
			MAJESTIC_TUBE_URI . '/assets/css/player.css',
			array( 'majestic-tube-main' ),
			majestic_tube_asset_version( 'assets/css/player.css' )
		);
	}

	// Main script: no jQuery dependency, vanilla JS.
	wp_enqueue_script(
		'majestic-tube-main',
		MAJESTIC_TUBE_URI . '/assets/js/main.js',
		$deps,
		$version,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	/*
	 * Pass data to JS in a single inline payload.
	 *
	 * Three separate wp_localize_script() calls used to emit three inline
	 * <script> blocks before main.js. They are merged into one
	 * wp_add_inline_script() call, which is the API WordPress recommends for
	 * new code (wp_localize_script() is soft-discouraged and its escaping is
	 * awkward for nested data).
	 *
	 * The three global names are part of the contract and are preserved exactly:
	 *   - majesticTubeData  - the theme's own object, read by main.js.
	 *   - wpst_ajax_var     - original name for url / nonce / ctpl_installed,
	 *                         read by WP-Script plugins and child themes.
	 *   - options           - original global carrying thumbnails_ratio; the
	 *                         original main.js does options.thumbnails_ratio.split('/').
	 * Each is emitted as its own `var` so plugins reading them by name keep
	 * working. The nonce is generated once and shared, so all three carry an
	 * identical value.
	 */
	$ajax_url = admin_url( 'admin-ajax.php' );
	$nonce    = wp_create_nonce( 'ajax-nonce' );

	$script_data = array(
		'majesticTubeData' => array(
			'ajaxUrl'        => $ajax_url,
			'url'            => $ajax_url,
			'nonce'          => $nonce,
			'ctpl_installed' => $ctpl_install,
			'postId'         => $post_id,
			'isMobile'       => majestic_tube_is_mobile(),
			'options'        => array(
				'thumbnails_ratio' => $ratio,
				'thumbnailsRatio'  => $ratio,
				'rotateThumbs'     => majestic_tube_option_is_on( 'enable-thumbnails-rotation' ),
				'qualitySelector'  => majestic_tube_option_is_on( 'videojs-quality-selector' ),
				'nativePlayer'     => majestic_tube_option_is_on( 'use-native-player' ),
				'autoplay'         => majestic_tube_option_is_on( 'autoplay-video-player' ),
			),
			'i18n'           => array(
				'likeError'     => __( 'Could not record your vote. Please try again.', 'majestic-tube' ),
				'alreadyRated'  => __( 'You have already rated this video.', 'majestic-tube' ),
				'reportError'   => __( 'Could not send your report. Please try again.', 'majestic-tube' ),
				'reported'      => __( 'Thanks, our team will review this video.', 'majestic-tube' ),
				'alreadyReport' => __( 'You have already reported this video. Thanks!', 'majestic-tube' ),
				'quality'       => __( 'Quality', 'majestic-tube' ),
				'autoQuality'   => __( 'Auto', 'majestic-tube' ),
				'readMore'      => __( 'Read more', 'majestic-tube' ),
				'readLess'      => __( 'Read less', 'majestic-tube' ),
				'openMenu'      => __( 'Menu', 'majestic-tube' ),
				'closeMenu'     => __( 'Close', 'majestic-tube' ),
			),
		),
		'wpst_ajax_var'    => array(
			'url'            => $ajax_url,
			'nonce'          => $nonce,
			'ctpl_installed' => $ctpl_install,
			'post_id'        => $post_id,
		),
		'options'          => array(
			'thumbnails_ratio' => $ratio,
		),
	);

	// wp_json_encode flags keep </script> and friends from escaping the block;
	// the payload is administrator-influenced (locale strings, ratio option).
	$inline = '';

	foreach ( $script_data as $global_name => $global_value ) {
		$inline .= sprintf(
			'var %1$s = %2$s;' . "\n",
			$global_name,
			wp_json_encode( $global_value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES )
		);
	}

	wp_add_inline_script( 'majestic-tube-main', $inline, 'before' );

	// Comment reply script.
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'majestic_tube_enqueue_assets' );

/**
 * Enqueue the term image picker on the taxonomy screens that use it.
 *
 * This used to live inside the front-end wp_enqueue_scripts callback behind an
 * is_admin() check. It worked, but it tied an admin-only dependency to the
 * front-end hook: wp_enqueue_scripts does not fire on every admin screen (it is
 * skipped for admin-ajax and a few others), so any screen that needed the picker
 * but did not fire that hook would silently lose it. admin_enqueue_scripts is
 * the correct home and is guaranteed to run wherever the screen is rendered.
 *
 * @param string $hook_suffix Current admin page.
 * @return void
 */
function majestic_tube_enqueue_admin_assets( $hook_suffix = '' ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	// WP_Screen exposes taxonomy dynamically; reading an unset property warns
	// on PHP 8.2+, so it is checked with isset().
	$taxonomy = ( $screen && isset( $screen->taxonomy ) ) ? (string) $screen->taxonomy : '';

	if ( ! in_array( $taxonomy, array( 'actors', 'category' ), true ) ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script(
		'majestic-tube-term-images',
		MAJESTIC_TUBE_URI . '/assets/js/term-images.js',
		array( 'jquery', 'media-editor' ),
		majestic_tube_asset_version( 'assets/js/term-images.js' ),
		array(
			'in_footer' => true,
		)
	);
	wp_localize_script(
		'majestic-tube-term-images',
		'majesticTubeTermImage',
		array(
			'title'  => __( 'Choose an image', 'majestic-tube' ),
			'select' => __( 'Use selected image', 'majestic-tube' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'majestic_tube_enqueue_admin_assets' );

/**
 * Preconnect to the Video.js CDN when the player is served from it.
 *
 * @param array  $urls          URLs to print for resource hints.
 * @param string $relation_type The relation type.
 * @return array
 */
function majestic_tube_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type && majestic_tube_player_enabled() && majestic_tube_use_videojs_cdn() ) {
		$urls[] = array(
			'href'        => 'https://vjs.zencdn.net',
			'crossorigin' => 'anonymous',
		);
	}

	return $urls;
}
add_filter( 'wp_resource_hints', 'majestic_tube_resource_hints', 10, 2 );
