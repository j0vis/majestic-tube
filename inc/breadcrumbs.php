<?php
/**
 * Breadcrumb trail and BreadcrumbList structured data.
 *
 * The visual trail and the JSON-LD graph are built from one item list
 * (majestic_tube_get_breadcrumb_items()), so they can never describe
 * different paths. The JSON-LD is printed from wp_head on non-front-page
 * requests, which is where core's Yoast/Rank Math print theirs; both listen
 * for the same filter the plugins document for disabling theme schema
 * (majestic_tube_disable_schema).
 *
 * @package Majestic Tube
 * @version 2.1.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * The breadcrumb trail as a list of items, home first.
 *
 * Each item is array( 'label' => string, 'url' => string ). The final item -
 * the current page - has an empty url, which the renderer prints as the
 * .breadcrumb-current span and the JSON-LD omits entirely (Google rejects
 * self-referencing trail items).
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

/**
 * Whether the theme should print its JSON-LD graphs.
 *
 * Yoast, Rank Math and friends declare the same filter contract for their own
 * schema output; honoring one shared flag here keeps a site from emitting two
 * BreadcrumbList graphs on every page.
 *
 * @return bool
 */
function majestic_tube_should_output_schema() {
	$disabled = false;

	foreach ( array_keys( majestic_tube_social_meta_plugins() ) as $plugin ) {
		if ( majestic_tube_is_plugin_active( $plugin ) ) {
			$disabled = true;
			break;
		}
	}

	/**
	 * Filter whether Majestic Tube prints its own JSON-LD schema.
	 *
	 * Return true when an SEO plugin already owns structured data.
	 *
	 * @param bool $disabled True when an SEO plugin is active.
	 */
	return ! apply_filters( 'majestic_tube_disable_schema', $disabled );
}

/**
 * Print BreadcrumbList JSON-LD on every non-front-page view.
 *
 * The current item is omitted: Google's BreadcrumbList documentation wants
 * only ancestors in the trail, and a self-referencing last node makes the
 * graph fail the rich-results test.
 *
 * @return void
 */
function majestic_tube_output_breadcrumb_schema() {
	if ( is_front_page() || ! majestic_tube_should_output_schema() ) {
		return;
	}

	$items = majestic_tube_get_breadcrumb_items();
	$items = array_filter(
		$items,
		function ( $item ) {
			return ! empty( $item['url'] ) && '' !== trim( (string) $item['label'] );
		}
	);

	if ( count( $items ) < 2 ) {
		return;
	}

	$positions = array();
	$index     = 0;

	foreach ( $items as $item ) {
		$index++;
		$positions[] = array(
			'@type'    => 'ListItem',
			'position' => $index,
			'name'     => wp_strip_all_tags( (string) $item['label'] ),
			'item'     => esc_url_raw( $item['url'] ),
		);
	}

	$schema = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $positions,
	);

	/**
	 * Filter the BreadcrumbList schema before it is printed.
	 *
	 * @param array $schema Schema graph.
	 */
	$schema = (array) apply_filters( 'majestic_tube_breadcrumb_schema', $schema );

	$json = wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	if ( ! $json ) {
		return;
	}

	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		$json // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON payload.
	);
}
add_action( 'wp_head', 'majestic_tube_output_breadcrumb_schema', 6 );