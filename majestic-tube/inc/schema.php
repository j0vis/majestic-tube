<?php
/**
 * Structured data - JSON-LD VideoObject markup.
 *
 * The original theme only had microdata attributes; Google now prefers
 * JSON-LD, so this module emits a VideoObject graph for single videos
 * (name, thumbnail, upload date, duration, content/embed URL and engagement
 * counters built from the original meta keys).
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Convert a duration in seconds to an ISO 8601 duration.
 *
 * @param int $seconds Duration in seconds.
 * @return string e.g. PT1H2M3S.
 */
function majestic_tube_get_iso8601_duration( $seconds ) {
	$seconds = absint( $seconds );

	if ( ! $seconds ) {
		return '';
	}

	$hours   = (int) floor( $seconds / 3600 );
	$minutes = (int) floor( ( $seconds % 3600 ) / 60 );
	$secs    = (int) ( $seconds % 60 );

	$duration = 'PT';

	if ( $hours ) {
		$duration .= $hours . 'H';
	}

	if ( $minutes ) {
		$duration .= $minutes . 'M';
	}

	if ( $secs || 'PT' === $duration ) {
		$duration .= $secs . 'S';
	}

	return $duration;
}

/**
 * Build the VideoObject schema for a post, or null when there is nothing to
 * describe.
 *
 * @param int $post_id Post ID.
 * @return array|null
 */
function majestic_tube_get_video_schema( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post ) {
		return null;
	}

	$video = majestic_tube_get_social_video( $post_id );

	if ( ! $video['file'] && ! $video['embed'] ) {
		return null;
	}

	$image = majestic_tube_get_social_image( $post_id );
	$stats = majestic_tube_analytics_get( $post_id );

	$schema = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'VideoObject',
		'name'        => get_the_title( $post_id ),
		'description' => majestic_tube_get_social_description( $post ),
		'url'         => get_permalink( $post_id ),
		'uploadDate'  => get_the_date( DATE_W3C, $post_id ),
		'inLanguage'  => get_bloginfo( 'language' ),
		'isFamilyFriendly' => true,
	);

	if ( $image['url'] ) {
		$schema['thumbnailUrl'] = array( $image['url'] );
	}

	$duration = majestic_tube_get_duration_seconds( $post_id );

	if ( $duration ) {
		$schema['duration'] = majestic_tube_get_iso8601_duration( $duration );
	}

	if ( $video['file'] ) {
		$schema['contentUrl'] = $video['file'];
	} else {
		$schema['embedUrl'] = $video['embed'];
	}

	$interactions = array();
	$interaction_types = array(
		'views' => 'WatchAction',
		'likes' => 'LikeAction',
	);

	foreach ( $interaction_types as $field => $interaction_type ) {
		if ( ! $stats[ $field ] ) {
			continue;
		}

		$interactions[] = array(
			'@type'                => 'InteractionCounter',
			'interactionType'      => array( '@type' => $interaction_type ),
			'userInteractionCount' => (int) $stats[ $field ],
		);
	}

	if ( $interactions ) {
		$schema['interactionStatistic'] = $interactions;
	}

	$actor_ids = wp_get_post_terms( $post_id, 'actors', array( 'fields' => 'names' ) );

	if ( $actor_ids && ! is_wp_error( $actor_ids ) ) {
		$schema['actor'] = array();

		foreach ( $actor_ids as $actor ) {
			$schema['actor'][] = array(
				'@type' => 'Person',
				'name'  => $actor,
			);
		}
	}

	$schema['publisher'] = array(
		'@type' => 'Organization',
		'name'  => get_bloginfo( 'name' ),
		'url'   => home_url( '/' ),
	);

	$logo = has_custom_logo() ? wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' ) : '';

	if ( $logo ) {
		$schema['publisher']['logo'] = array(
			'@type' => 'ImageObject',
			'url'   => $logo,
		);
	}

	/**
	 * Filter the VideoObject schema before it is printed.
	 *
	 * @param array $schema  Schema graph.
	 * @param int   $post_id Post ID.
	 */
	return apply_filters( 'majestic_tube_video_schema', $schema, $post_id );
}

/**
 * Print the JSON-LD graph on single videos.
 *
 * @return void
 */
function majestic_tube_output_video_schema() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}

	$schema = majestic_tube_get_video_schema( get_queried_object_id() );

	if ( ! $schema ) {
		return;
	}

	// Slashes stay escaped so post content can never break out of the script tag.
	$json = wp_json_encode( $schema, JSON_UNESCAPED_UNICODE );

	if ( ! $json ) {
		return;
	}

	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		$json // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON payload, slash-escaped.
	);
}
add_action( 'wp_head', 'majestic_tube_output_video_schema', 6 );
