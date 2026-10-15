<?php
/**
 * Majestic Tube functions and definitions.
 *
 * This file is a lightweight loader. All functionality lives in /inc modules.
 *
 * @package Majestic Tube
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

define( 'MAJESTIC_TUBE_VERSION', '2.5.5' );
define( 'MAJESTIC_TUBE_DIR', get_template_directory() );
define( 'MAJESTIC_TUBE_URI', get_template_directory_uri() );

/**
 * Load required modules.
 */
require MAJESTIC_TUBE_DIR . '/inc/theme-support.php';
require MAJESTIC_TUBE_DIR . '/inc/activation.php';
require MAJESTIC_TUBE_DIR . '/inc/theme-options.php';
require MAJESTIC_TUBE_DIR . '/inc/site-icon.php';
require MAJESTIC_TUBE_DIR . '/inc/template-tags.php';
require MAJESTIC_TUBE_DIR . '/inc/icons.php';
require MAJESTIC_TUBE_DIR . '/inc/custom-code.php';
require MAJESTIC_TUBE_DIR . '/inc/compat.php';
require MAJESTIC_TUBE_DIR . '/inc/assets.php';
require MAJESTIC_TUBE_DIR . '/inc/post-types.php';
require MAJESTIC_TUBE_DIR . '/inc/analytics.php';
require MAJESTIC_TUBE_DIR . '/inc/video-meta.php';
require MAJESTIC_TUBE_DIR . '/inc/basic-seo.php';
require MAJESTIC_TUBE_DIR . '/inc/video-seo.php';
require MAJESTIC_TUBE_DIR . '/inc/ajax.php';
require MAJESTIC_TUBE_DIR . '/inc/ads.php';
require MAJESTIC_TUBE_DIR . '/inc/reports.php';
require MAJESTIC_TUBE_DIR . '/inc/share.php';
require MAJESTIC_TUBE_DIR . '/inc/term-images.php';
require MAJESTIC_TUBE_DIR . '/inc/multithumbs.php';
require MAJESTIC_TUBE_DIR . '/inc/widget-videos.php';
require MAJESTIC_TUBE_DIR . '/inc/ajax-login-register.php';
require MAJESTIC_TUBE_DIR . '/inc/pagination.php';
require MAJESTIC_TUBE_DIR . '/inc/breadcrumbs.php';
require MAJESTIC_TUBE_DIR . '/inc/widgets.php';
require MAJESTIC_TUBE_DIR . '/inc/consent.php';
require MAJESTIC_TUBE_DIR . '/inc/template-filters.php';

/*
 * Search appearance belongs to the SEO plugin the site runs, and two modules
 * here know it. inc/basic-seo.php supplies the meta description, the archive
 * canonical and the social card a site needs when no plugin is installed, and
 * switches itself off - completely - the moment one of the recognized plugins
 * is detected, so there is never a second answer to the same question.
 * inc/video-seo.php is the one thing a general-purpose plugin cannot do: a
 * VideoObject needs the runtime, the source resolution ladder, the player
 * markup and the actors taxonomy, all of which only this theme knows. It stays
 * additive and never prints a title, a description or a canonical of its own.
 * Neither module prints a <title>; the browser title remains core's.
 */
