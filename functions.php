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

define( 'MAJESTIC_TUBE_VERSION', '2.2.23' );
define( 'MAJESTIC_TUBE_DIR', get_template_directory() );
define( 'MAJESTIC_TUBE_URI', get_template_directory_uri() );

/**
 * Load required modules.
 */
require MAJESTIC_TUBE_DIR . '/inc/theme-support.php';
require MAJESTIC_TUBE_DIR . '/inc/activation.php';
require MAJESTIC_TUBE_DIR . '/inc/theme-options.php';
require MAJESTIC_TUBE_DIR . '/inc/template-tags.php';
require MAJESTIC_TUBE_DIR . '/inc/custom-code.php';
require MAJESTIC_TUBE_DIR . '/inc/compat.php';
require MAJESTIC_TUBE_DIR . '/inc/assets.php';
require MAJESTIC_TUBE_DIR . '/inc/post-types.php';
require MAJESTIC_TUBE_DIR . '/inc/analytics.php';
require MAJESTIC_TUBE_DIR . '/inc/video-meta.php';
require MAJESTIC_TUBE_DIR . '/inc/ajax.php';
require MAJESTIC_TUBE_DIR . '/inc/ads.php';
require MAJESTIC_TUBE_DIR . '/inc/reports.php';
require MAJESTIC_TUBE_DIR . '/inc/meta-social.php';
require MAJESTIC_TUBE_DIR . '/inc/share.php';
require MAJESTIC_TUBE_DIR . '/inc/schema.php';
require MAJESTIC_TUBE_DIR . '/inc/term-images.php';
require MAJESTIC_TUBE_DIR . '/inc/multithumbs.php';
require MAJESTIC_TUBE_DIR . '/inc/widget-videos.php';
require MAJESTIC_TUBE_DIR . '/inc/ajax-login-register.php';
require MAJESTIC_TUBE_DIR . '/inc/pagination.php';
require MAJESTIC_TUBE_DIR . '/inc/breadcrumbs.php';
require MAJESTIC_TUBE_DIR . '/inc/widgets.php';
require MAJESTIC_TUBE_DIR . '/inc/template-filters.php';

/*
 * Programmatic SEO. The modules are ordered by dependency: pseo.php owns the
 * facet vocabulary, rewrites and the index gate; pseo-stats.php derives the
 * values that make a facet specific; pseo-meta.php turns them into titles,
 * descriptions and directives; pseo-render.php wires the queries, the browse
 * spine and the breadcrumbs; pseo-sitemap.php submits whatever the gate
 * approved, and nothing else.
 */
require MAJESTIC_TUBE_DIR . '/inc/pseo.php';
require MAJESTIC_TUBE_DIR . '/inc/pseo-options.php';
require MAJESTIC_TUBE_DIR . '/inc/pseo-stats.php';
require MAJESTIC_TUBE_DIR . '/inc/pseo-meta.php';
require MAJESTIC_TUBE_DIR . '/inc/pseo-render.php';
require MAJESTIC_TUBE_DIR . '/inc/pseo-sitemap.php';