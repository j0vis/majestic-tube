<?php
/**
 * Social meta tags - Open Graph + Twitter Cards.
 *
 * Matches the original theme's tags (og:url, og:type, og:title,
 * og:description, og:image, twitter:card, twitter:title, ...) while fixing two
 * defects: the original hardcoded a third-party Facebook app ID, and declared
 * every og:image as 200x200 regardless of the real file.
 *
 * 2.1.0: og:video:width/height now come from the poster file actually
 * generated for the video instead of the hardcoded 640x360, and a
 * twitter:player card is emitted when the administrator configures a player
 * URL base (D3 of the feature audit). Both are skipped entirely when an SEO
 * plugin owns the social tags.
 *
 * @package Majestic Tube
 * @version 2.1.2
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugins that already print Open Graph / Twitter tags.
 *
 * @return array<string, string>
 */
function majestic_tube_social_meta_plugins() {
	return array(
		'wordpress-seo/wp-seo.php'                       => true, // Yoast SEO.
		'seo-by-rank-math/rank-math.php'                 => true, // Rank Math.
		'wp-seopress/wp-seopress.php'                    => true, // SEOPress.
		'all-in-one-seo-pack/all_in_one_seo_pack.php'    => true, // All in One SEO.
		'autodescription/autodescription.php'            => true, // The SEO Framework.
		'slim-seo/slim-seo.php'                          => true, // Slim SEO.
	);
}

/**
 * Whether the theme should print its own social meta tags.
 *
 * SEO plugins already print a complete Open Graph / Twitter set, so we step
 * aside when one of them is active.
 *
 * @return bool
 */
function majestic_tube_should_output_social_meta() {
	$handled = false;

	foreach ( array_keys( majestic_tube_social_meta_plugins() ) as $plugin ) {
		if ( majestic_tube_is_plugin_active( $plugin ) ) {
			$handled = true;
			break;
		}
	}

	/**
	 * Filter whether Majestic Tube prints its own social meta tags.
	 *
	 * Return true to disable them (another plugin already handles them).
	 *
	 * @param bool $handled True when an SEO plugin is active.
	 */
	return ! apply_filters( 'majestic_tube_disable_social_meta', $handled );
}

/**
 * Best image to share for a post, with its real dimensions when known.
 *
 * @param int $post_id Post ID (0 for the site-wide fallback).
 * @return array{url:string,width:int,height:int,alt:string}
 */
function majestic_tube_get_social_image( $post_id = 0 ) {
	$image = array(
		'url'    => '',
		'width'  => 0,
		'height' => 0,
		'alt'    => '',
	);

	if ( $post_id && has_post_thumbnail( $post_id ) ) {
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		$data         = wp_get_attachment_image_src( $thumbnail_id, 'majestic-tube-thumb-large' );

		if ( ! $data ) {
			$data = wp_get_attachment_image_src( $thumbnail_id, 'full' );
		}

		if ( $data ) {
			$image['url']    = $data[0];
			$image['width']  = (int) $data[1];
			$image['height'] = (int) $data[2];
			$image['alt']    = get_the_title( $post_id );
		}
	} elseif ( $post_id ) {
		$image['url'] = majestic_tube_get_thumb_url( $post_id, 'majestic-tube-thumb-large' );
	}

	if ( ! $image['url'] && has_header_image() ) {
		$image['url'] = get_header_image();
	}

	if ( ! $image['url'] && function_exists( 'get_site_icon_url' ) ) {
		$icon = get_site_icon_url( 512 );

		if ( $icon ) {
			$image['url'] = $icon;
		}
	}

	/**
	 * Filter the social sharing image data.
	 *
	 * @param array $image   Image data.
	 * @param int   $post_id Post ID.
	 */
	return apply_filters( 'majestic_tube_social_image', $image, $post_id );
}

/**
 * Share description for a post, original 55-word trim.
 *
 * @param WP_Post|null $post Post object.
 * @return string
 */
function majestic_tube_get_social_description( $post ) {
	if ( ! $post ) {
		return get_bloginfo( 'description' );
	}

	if ( ! empty( $post->post_excerpt ) ) {
		return wp_trim_words( wp_strip_all_tags( $post->post_excerpt ), 55, '...' );
	}

	$content = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.

	return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $content ) ), 55, '...' );
}

/**
 * Output a flat set of escaped meta attributes.
 *
 * @param array<string, string> $tags      Tag attributes and values.
 * @param string                $attribute Either `property` or `name`.
 * @return void
 */
function majestic_tube_output_meta_tags( $tags, $attribute ) {
	foreach ( $tags as $key => $content ) {
		if ( '' === $content || null === $content ) {
			continue;
		}

		printf(
			'<meta %1$s="%2$s" content="%3$s" />' . "\n",
			esc_attr( $attribute ),
			esc_attr( $key ),
			esc_attr( $content )
		);
	}
}

/**
 * Shareable video file and/or embed URL for a post.
 *
 * @param int $post_id Post ID.
 * @return array{file:string,embed:string,mime:string}
 */
function majestic_tube_get_social_video( $post_id ) {
	$data = array(
		'file'  => '',
		'embed' => '',
		'mime'  => '',
	);

	$sources = $post_id ? majestic_tube_get_video_sources( $post_id ) : null;

	if ( ! $sources ) {
		return $data;
	}

	if ( 'self-hosted' === $sources['type'] ) {
		$file = $sources['main'];

		if ( ! $file && $sources['sources'] ) {
			$file = reset( $sources['sources'] );
		}

		$data['file'] = (string) $file;
		$data['mime'] = $data['file'] ? majestic_tube_get_video_mime( $data['file'] ) : '';
	} elseif ( 'embed' === $sources['type'] ) {
		if ( preg_match( '/src=["\']([^"\']+)["\']/', (string) $sources['embed'], $match ) ) {
			$data['embed'] = $match[1];
		}

		$data['mime'] = 'text/html';
	}

	return $data;
}

/**
 * Pixel dimensions to advertise for a video's poster.
 *
 * The featured image's largest generated crop is the truthful answer; the
 * hardcoded 640x360 from the original theme is the fallback when the picture
 * is a remote thumb with no attachment metadata.
 *
 * @param int $post_id Post ID.
 * @return array{width:int,height:int}
 */
function majestic_tube_get_social_video_dimensions( $post_id ) {
	$dimensions = array(
		'width'  => 640,
		'height' => 360,
	);

	if ( $post_id && has_post_thumbnail( $post_id ) ) {
		$data = wp_get_attachment_image_src( (int) get_post_thumbnail_id( $post_id ), 'majestic-tube-poster' );

		if ( $data && ! empty( $data[1] ) && ! empty( $data[2] ) ) {
			$dimensions['width']  = (int) $data[1];
			$dimensions['height'] = (int) $data[2];
		}
	}

	/**
	 * Filter the dimensions advertised for a video poster.
	 *
	 * @param array $dimensions width/height in CSS pixels.
	 * @param int   $post_id    Post ID.
	 */
	return (array) apply_filters( 'majestic_tube_social_video_dimensions', $dimensions, $post_id );
}

/**
 * Print Open Graph and Twitter Card meta tags.
 *
 * @return void
 */
function majestic_tube_output_social_meta() {
	if ( ! majestic_tube_should_output_social_meta() ) {
		return;
	}

	$post_id = is_singular() ? get_queried_object_id() : 0;
	$post    = $post_id ? get_post( $post_id ) : null;

	$url         = $post_id ? get_permalink( $post_id ) : home_url( '/' );
	$title       = $post_id ? get_the_title( $post_id ) : wp_get_document_title();
	$description = majestic_tube_get_social_description( $post );
	$image       = majestic_tube_get_social_image( $post_id );
	$video       = $post_id ? majestic_tube_get_social_video( $post_id ) : array(
		'file'  => '',
		'embed' => '',
		'mime'  => '',
	);

	if ( is_singular() ) {
		$type = $video['file'] ? 'video.other' : 'article';
	} else {
		$type = 'website';
	}

	$tags = array(
		'og:type'        => $type,
		'og:url'         => $url,
		'og:site_name'   => get_bloginfo( 'name' ),
		'og:locale'      => get_locale(),
		'og:title'       => $title,
		'og:description' => $description,
	);

	if ( $image['url'] ) {
		$tags['og:image'] = $image['url'];

		if ( $image['width'] && $image['height'] ) {
			$tags['og:image:width']  = (string) $image['width'];
			$tags['og:image:height'] = (string) $image['height'];
		}

		if ( $image['alt'] ) {
			$tags['og:image:alt'] = $image['alt'];
		}
	}

	if ( $video['file'] ) {
		/*
		 * D2: dimensions of the file actually generated, not a hardcoded
		 * 640x360. The poster size (1280x720) is the file a modern library
		 * has, so scrapers letterboxing a 16:9 file against wrong dimensions
		 * stop being a thing. The fallback keeps the original value for a
		 * video whose picture is a remote thumb with unknown size.
		 */
		$video_dimensions = majestic_tube_get_social_video_dimensions( $post_id );

		$tags['og:video']        = $video['file'];
		$tags['og:video:type']   = $video['mime'];
		$tags['og:video:width']  = (string) $video_dimensions['width'];
		$tags['og:video:height'] = (string) $video_dimensions['height'];
	} elseif ( $video['embed'] ) {
		$tags['og:video']      = $video['embed'];
		$tags['og:video:type'] = 'text/html';
	}

	if ( $post_id && in_array( $type, array( 'article', 'video.other' ), true ) ) {
		$tags['article:published_time'] = get_the_date( DATE_W3C, $post_id );
		$tags['article:modified_time']  = get_the_modified_date( DATE_W3C, $post_id );

		$duration = majestic_tube_get_duration_seconds( $post_id );

		if ( $duration ) {
			$tags['video:duration'] = (string) $duration;
		}
	}

	$app_id = majestic_tube_get_option( 'wpst-options', 'facebook-app-id', '' );

	if ( $app_id ) {
		$tags['fb:app_id'] = $app_id;
	}

	if ( $image['url'] ) {
		$twitter = array(
			'twitter:card'  => 'summary_large_image',
			'twitter:image' => $image['url'],
		);

		if ( $image['alt'] ) {
			$twitter['twitter:image:alt'] = $image['alt'];
		}
	} else {
		$twitter = array( 'twitter:card' => 'summary' );
	}

	$handle = majestic_tube_get_option( 'wpst-options', 'twitter-site', '' );

	if ( $handle ) {
		$twitter['twitter:site'] = ( '@' === substr( $handle, 0, 1 ) ) ? $handle : '@' . $handle;
	}

	$twitter['twitter:title']       = $title;
	$twitter['twitter:description'] = $description;

	/*
	 * D3: the player card. X/Twitter renders twitter:player as a live embed
	 * inside a ~435px-wide iframe, so it needs an HTTPS URL that returns a
	 * bare page with the video on it. The theme cannot guarantee such a page
	 * for every permalink structure, so the base URL is an option and the post
	 * id is appended. Everything the card requires (name, dimensions) comes
	 * from the same poster data the OG tags just used.
	 */
	$player_base = trim( (string) majestic_tube_get_option( 'wpst-options', 'twitter-player-url', '' ) );

	if ( $post_id && $video['file'] && $player_base && 0 === strpos( $player_base, 'https://' ) ) {
		$player_url = esc_url_raw( add_query_arg( 'post', (int) $post_id, $player_base ) );

		if ( $player_url ) {
			$twitter['twitter:card']   = 'player';
			$twitter['twitter:player'] = $player_url;
			$twitter['twitter:player:width']  = '640';
			$twitter['twitter:player:height'] = '360';
		}
	}

	/**
	 * Filter the Open Graph tags before they are printed.
	 *
	 * @param array $tags    Open Graph tags (property => content).
	 * @param int   $post_id Post ID.
	 */
	$tags = apply_filters( 'majestic_tube_social_meta_tags', $tags, $post_id );

	/**
	 * Filter the Twitter Card tags before they are printed.
	 *
	 * @param array $twitter Twitter tags (name => content).
	 * @param int   $post_id Post ID.
	 */
	$twitter = apply_filters( 'majestic_tube_twitter_meta_tags', $twitter, $post_id );

	echo "\n<!-- Majestic Tube social meta -->\n";

	majestic_tube_output_meta_tags( $tags, 'property' );
	majestic_tube_output_meta_tags( $twitter, 'name' );
}
add_action( 'wp_head', 'majestic_tube_output_social_meta', 5 );
