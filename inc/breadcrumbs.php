<?php
/**
 * Breadcrumb trail.
 *
 * One item list (majestic_tube_get_breadcrumb_items()) feeds the visual trail
 * the renderer prints. The BreadcrumbList JSON-LD graph that used to be
 * printed alongside it is gone: structured data belongs to the SEO plugin the
 * site runs, and two BreadcrumbList graphs on one page is worse than one.
 *
 * @package Majestic Tube
 * @version 2.3.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * The breadcrumb trail as a list of items, home first.
 *
 * Each item is array( 'label' => string, 'url' => string ). The final item -
 * the current page - has an empty url, which the renderer prints as the
 * .breadcrumb-current span.
 *
 * @return array<int, array{label:string,url:string}>
 */
function majestic_tube_get_breadcrumb_items() {
	$items = array(
		array(
			'label' => __( 'Home', 'majestic-tube' ),
			'url'   => home_url( '/' ),
		),
	);

	if ( is_single() ) {
		$items[] = array(
			'label' => __( 'Videos', 'majestic-tube' ),
			'url'   => home_url( '/' ),
		);
		$items[] = array(
			'label' => get_the_title(),
			'url'   => '',
		);
	} elseif ( is_singular() ) {
		$items[] = array(
			'label' => get_the_title(),
			'url'   => '',
		);
	} elseif ( is_archive() || is_home() ) {
		$items[] = array(
			'label' => wp_strip_all_tags( get_the_archive_title() ),
			'url'   => '',
		);
	} elseif ( is_search() ) {
		/* translators: %s: search query. */
		$items[] = array(
			'label' => sprintf( __( 'Search: %s', 'majestic-tube' ), get_search_query() ),
			'url'   => '',
		);
	}

	/**
	 * Filter the breadcrumb trail items.
	 *
	 * @param array<int, array{label:string,url:string}> $items Home-first item list; the last item's url is empty.
	 */
	return (array) apply_filters( 'majestic_tube_breadcrumb_items', $items );
}

/**
 * Render the breadcrumb trail.
 */
function majestic_tube_breadcrumbs() {
	if ( is_front_page() ) {
		return;
	}

	$items     = majestic_tube_get_breadcrumb_items();
	$separator = '<span class="breadcrumb-separator" aria-hidden="true">/</span>';

	echo '<nav class="breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'majestic-tube' ) . '">';

	$first = true;

	foreach ( $items as $item ) {
		if ( ! $first ) {
			echo $separator; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static safe string.
		}

		$first = false;

		if ( empty( $item['url'] ) ) {
			echo '<span class="breadcrumb-current">' . esc_html( $item['label'] ) . '</span>';
		} else {
			echo '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
		}
	}

	echo '</nav>';
}
