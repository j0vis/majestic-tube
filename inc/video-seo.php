<?php
/**
 * Video structured data - VideoObject JSON-LD, Open Graph video tags and a
 * dedicated video sitemap.
 *
 * Why this is in the theme and not left to the SEO plugin
 * -------------------------------------------------------
 * The theme hands titles, meta descriptions, robots directives and canonicals
 * to whatever SEO plugin is running, and that boundary stays. Video
 * structured data is different: no general-purpose SEO plugin emits it, and a
 * video theme is the only place that knows the duration, the source
 * resolution ladder, the player markup and the actors taxonomy that a
 * VideoObject needs. It is content metadata, not search appearance.
 *
 * It stays additive rather than competing: this module never prints a title,
 * a description, a canonical or a robots directive, and it only fills in
 * og:image when the SEO plugin has not already provided one.
 *
 * Meta keys read (identical to the original WP-Script theme, so importers and
 * the metabox stay the single source of truth):
 *   duration        int, seconds
 *   video_url       direct media file
 *   video_url_240..4k  alternate resolutions
 *   embed           third-party player iframe markup
 *   shortcode       player shortcode
 *   thumb           fallback thumbnail URL
 *   trailer_url     trailer file
 *   post_views_count, likes_count
 *
 * @package Majestic Tube
 * @version 2.4.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bumped when the rewrite rules change, so they flush once on upgrade.
 *
 * A theme has no activation hook of its own when it is replaced in place, and
 * WordPress only flushes rewrite rules when a theme is *switched*.
 */
define( 'MAJESTIC_TUBE_VIDEO_SEO_VERSION', '1.0.0' );

/* -------------------------------------------------------------------------
 * Master switch
 * ---------------------------------------------------------------------- */

/**
 * Whether the video SEO features are switched on.
 *
 * Default on, because a tube theme with no VideoObject is the exception
 * rather than the rule, but it is a real toggle: a site that already gets
 * this markup from somewhere else, or that runs a plugin which would
 * duplicate it, should be able to turn it off from the Customizer.
 *
 * @return bool
 */
function majestic_tube_video_seo_is_enabled() {
	$enabled = 'on' === majestic_tube_get_option( 'wpst-options', 'enable-video-seo', 'on' );

	/**
	 * Filter whether the theme emits video structured data.
	 *
	 * @param bool $enabled Whether video SEO output is enabled.
	 */
	return (bool) apply_filters( 'majestic_tube_enable_video_seo', $enabled );
}

/**
 * Whether the video sitemap endpoint is switched on.
 *
 * @return bool
 */
function majestic_tube_video_seo_sitemap_is_enabled() {
	$enabled = 'on' === majestic_tube_get_option( 'wpst-options', 'video-sitemap-enabled', 'on' );

	/**
	 * Filter whether the video sitemap is generated.
	 *
	 * @param bool $enabled Whether the video sitemap is enabled.
	 */
	return (bool) apply_filters( 'majestic_tube_enable_video_sitemap', $enabled );
}

/* -------------------------------------------------------------------------
 * Configuration
 * ---------------------------------------------------------------------- */

/**
 * Post types that hold videos.
 *
 * The theme registers videos as the built-in "post" type, so that is the only
 * default. A site that also keeps a second, older video post type can add it
 * through the filter - but only after confirming it is not duplicating
 * content, or the sitemap will advertise the duplicates to Google.
 *
 * @return string[]
 */
function majestic_tube_video_seo_post_types() {
	/**
	 * Filter the post types treated as videos.
	 *
	 * @param string[] $post_types Post type names.
	 */
	$post_types = apply_filters( 'majestic_tube_video_seo_post_types', array( 'post' ) );

	return array_values( array_filter( array_map( 'strval', (array) $post_types ) ) );
}

/**
 * How many URLs go in one video sitemap chunk.
 *
 * Google caps a sitemap at 50,000 URLs / 50 MB. 1,000 keeps each file small
 * enough to generate quickly on shared hosting and keeps a single bad row from
 * taking out a whole file.
 *
 * @return int
 */
function majestic_tube_video_seo_chunk_size() {
	$size = (int) majestic_tube_get_option( 'wpst-options', 'video-sitemap-chunk-size', 1000 );

	if ( $size < 1 ) {
		$size = 1000;
	}

	/**
	 * Filter how many URLs go in one video sitemap chunk.
	 *
	 * @param int $size URLs per chunk.
	 */
	return (int) apply_filters( 'majestic_tube_video_seo_sitemap_chunk_size', $size );
}

/**
 * Image size used for thumbnailUrl and video:thumbnail_loc.
 *
 * 'full' gives the highest quality result in search. Swap to a registered
 * thumbnail size if the full-size files are very large.
 *
 * @return string
 */
function majestic_tube_video_seo_thumbnail_size() {
	$size = (string) apply_filters( 'majestic_tube_video_seo_thumbnail_size', 'full' );

	return '' !== $size ? $size : 'full';
}

/* -------------------------------------------------------------------------
 * Data resolution
 *
 * These read through the theme's own helpers rather than the meta keys
 * directly. The helpers already encode decisions that are easy to get wrong -
 * notably that an iframe pasted into a video_url field is an embed and not a
 * file - so re-deriving them here would drift from the player's behaviour.
 * ---------------------------------------------------------------------- */

/**
 * Seconds of runtime for a post.
 *
 * @param int $post_id Post ID.
 * @return int Seconds, 0 when unset.
 */
function majestic_tube_video_seo_duration_seconds( $post_id ) {
	return (int) majestic_tube_get_duration_seconds( $post_id );
}

/**
 * ISO 8601 duration for schema.org and the video sitemap.
 *
 * Delegates to the theme's existing helper so the value matches whatever
 * else already reads it.
 *
 * @param int $seconds Duration in seconds.
 * @return string
 */
function majestic_tube_video_seo_iso8601_duration( $seconds ) {
	return wpst_iso8601_duration( $seconds );
}

/**
 * Best direct media file for a post, if it is self-hosted.
 *
 * @param int $post_id Post ID.
 * @return string Empty string when the video is an embed or a shortcode.
 */
function majestic_tube_video_seo_content_loc( $post_id ) {
	$sources = majestic_tube_get_video_sources( $post_id );

	if ( ! $sources || 'self-hosted' !== $sources['type'] ) {
		return '';
	}

	if ( ! empty( $sources['main'] ) ) {
		return $sources['main'];
	}

	/*
	 * No single default file. majestic_tube_video_resolutions() is ordered
	 * 4k first, so the first entry is already the best file on offer -
	 * reversing it would hand Google the 240p rendition.
	 */
	foreach ( $sources['sources'] as $url ) {
		return $url;
	}

	return '';
}

/**
 * The iframe's src attribute from stored player markup.
 *
 * The theme's own majestic_tube_extract_iframe() returns the whole <iframe>
 * element, which is what the player needs. Schema needs the URL inside it, so
 * the attribute is read here.
 *
 * @param string $markup Raw embed markup.
 * @return string
 */
function majestic_tube_video_seo_player_src( $markup ) {
	if ( ! is_string( $markup ) || '' === trim( $markup ) ) {
		return '';
	}

	if ( ! preg_match( '/<iframe\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1/is', $markup, $match ) ) {
		return '';
	}

	return esc_url_raw( trim( $match[2] ) );
}

/**
 * Player page for an embedded video.
 *
 * @param int $post_id Post ID.
 * @return string Empty string when the video is self-hosted.
 */
function majestic_tube_video_seo_player_loc( $post_id ) {
	$sources = majestic_tube_get_video_sources( $post_id );

	if ( ! $sources ) {
		return '';
	}

	if ( 'self-hosted' === $sources['type'] ) {
		return '';
	}

	return majestic_tube_video_seo_player_src( $sources['embed'] );
}

/**
 * Thumbnail URL for a post.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function majestic_tube_video_seo_thumbnail_url( $post_id ) {
	$url = (string) majestic_tube_get_thumb_url( $post_id, majestic_tube_video_seo_thumbnail_size() );

	if ( '' !== $url ) {
		return $url;
	}

	return (string) get_post_meta( $post_id, 'thumb', true );
}

/**
 * A description for the video.
 *
 * Google wants a text description on every video. Majestic Tube posts often
 * have no excerpt and little body text, so this falls back through excerpt ->
 * content -> a sentence assembled from the title and its categories. The
 * weakest of those still beats emitting nothing; `wp majestic-tube audit` reports how
 * many posts needed a fallback.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function majestic_tube_video_seo_description( $post_id ) {
	$description = (string) get_post_field( 'post_excerpt', $post_id );
	$description = trim( $description );

	if ( '' === $description ) {
		$content = (string) get_post_field( 'post_content', $post_id );
		$content = trim( wp_strip_all_tags( strip_shortcodes( $content ) ) );
		$content = preg_replace( '/\s+/u', ' ', $content );
		$content = trim( (string) $content );

		if ( '' !== $content ) {
			$description = $content;
		}
	}

	if ( '' === $description ) {
		$title    = (string) get_the_title( $post_id );
		$terms    = majestic_tube_video_seo_terms( $post_id, 'category' );
		$category = $terms ? implode( ', ', $terms ) : '';

		/* translators: %s: video title, %s: comma separated category names. */
		$description = $category
			? sprintf( __( 'Watch %1$s. %2$s videos on %3$s.', 'majestic-tube' ), $title, $category, get_bloginfo( 'name' ) )
			: sprintf( __( 'Watch %s on %s.', 'majestic-tube' ), $title, get_bloginfo( 'name' ) );
	}

	return majestic_tube_video_seo_truncate( $description, 4000 );
}

/**
 * Whether a description came from real editorial text.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function majestic_tube_video_seo_has_real_description( $post_id ) {
	$excerpt = trim( (string) get_post_field( 'post_excerpt', $post_id ) );

	if ( '' !== $excerpt ) {
		return true;
	}

	$content = trim( wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $post_id ) ) ) );

	return '' !== $content;
}

/**
 * Term names for a taxonomy, without the leading "Uncategorised".
 *
 * @param int    $post_id   Post ID.
 * @param string $taxonomy  Taxonomy name.
 * @param int    $limit     Maximum terms to return.
 * @return string[]
 */
function majestic_tube_video_seo_terms( $post_id, $taxonomy, $limit = 12 ) {
	$terms = get_the_terms( $post_id, $taxonomy );

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return array();
	}

	$names = array();

	foreach ( $terms as $term ) {
		if ( 'Uncategorized' === $term->slug || 'uncategorized' === $term->slug ) {
			continue;
		}

		$names[] = $term->name;
	}

	return array_slice( array_values( array_unique( $names ) ), 0, $limit );
}

/**
 * Truncate on a word boundary.
 *
 * @param string $text  Input.
 * @param int    $limit Maximum characters.
 * @return string
 */
function majestic_tube_video_seo_truncate( $text, $limit ) {
	if ( function_exists( 'mb_strlen' ) ) {
		if ( mb_strlen( $text ) <= $limit ) {
			return $text;
		}

		return rtrim( mb_substr( $text, 0, $limit - 1 ) ) . '…';
	}

	if ( strlen( $text ) <= $limit ) {
		return $text;
	}

	return rtrim( substr( $text, 0, $limit - 1 ) ) . '…';
}

/**
 * Everything needed to describe a video, or null when required data is absent.
 *
 * Google requires a thumbnail, a duration and either a contentUrl or an
 * embedUrl. Returning null rather than a partial object keeps malformed
 * VideoObject nodes out of the page: a video with no markup can still rank as
 * a normal page, whereas a broken one is a quality signal.
 *
 * @param int $post_id Post ID.
 * @return array|null
 */
function majestic_tube_video_seo_get_video( $post_id ) {
	static $cache = array();

	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		return null;
	}

	if ( array_key_exists( $post_id, $cache ) ) {
		return $cache[ $post_id ];
	}

	/*
	 * Cached because a single video page resolves the same post three times:
	 * once for the JSON-LD, once for the head tags and once more for the
	 * og:image fallback. Each miss re-reads nine meta keys and two term lists.
	 */
	$cache[ $post_id ] = majestic_tube_video_seo_resolve_video( $post_id );

	return $cache[ $post_id ];
}

/**
 * Read a post's video data. Uncached; call majestic_tube_video_seo_get_video().
 *
 * @param int $post_id Post ID.
 * @return array|null
 */
function majestic_tube_video_seo_resolve_video( $post_id ) {
	$seconds = majestic_tube_video_seo_duration_seconds( $post_id );

	if ( $seconds <= 0 ) {
		return null;
	}

	$thumbnail = majestic_tube_video_seo_thumbnail_url( $post_id );

	if ( '' === $thumbnail ) {
		return null;
	}

	$content_loc = majestic_tube_video_seo_content_loc( $post_id );
	$player_loc  = majestic_tube_video_seo_player_loc( $post_id );

	if ( '' === $content_loc && '' === $player_loc ) {
		return null;
	}

	$content = $post_id ? get_post( $post_id ) : null;

	if ( ! $content ) {
		return null;
	}

	return array(
		'id'          => $post_id,
		'title'       => majestic_tube_video_seo_truncate( (string) get_the_title( $post_id ), 200 ),
		'description' => majestic_tube_video_seo_description( $post_id ),
		'thumbnail'   => $thumbnail,
		'seconds'     => $seconds,
		'duration'    => majestic_tube_video_seo_iso8601_duration( $seconds ),
		'content_loc' => $content_loc,
		'player_loc'  => $player_loc,
		'url'         => get_permalink( $post_id ),
		'upload_date' => get_post_time( 'c', true, $post_id ),
		'modified'    => get_post_modified_time( 'c', true, $post_id ),
		'genres'      => majestic_tube_video_seo_terms( $post_id, 'category' ),
		'tags'        => majestic_tube_video_seo_terms( $post_id, 'post_tag', 20 ),
		'actors'      => majestic_tube_video_seo_terms( $post_id, 'actors' ),
		'mime'        => $content_loc ? majestic_tube_get_video_mime( $content_loc, 'video/mp4' ) : 'video/mp4',
		// Third-party players cannot be indexed into this site's video
		// inventory. Surfaced in the audit so the gap is visible.
		'external'    => '' !== $player_loc && ! majestic_tube_video_seo_is_local_url( $player_loc ),
	);
}

/**
 * Whether a URL points at this site.
 *
 * @param string $url URL to test.
 * @return bool
 */
function majestic_tube_video_seo_is_local_url( $url ) {
	$host = wp_parse_url( $url, PHP_URL_HOST );

	if ( ! $host ) {
		return false;
	}

	$home = wp_parse_url( home_url(), PHP_URL_HOST );

	return $host === $home;
}

/* -------------------------------------------------------------------------
 * VideoObject JSON-LD
 * ---------------------------------------------------------------------- */

/**
 * Build the VideoObject node.
 *
 * Emitted as its own script block rather than merged into the SEO plugin's
 * @graph. A standalone node anchored with @id and mainEntityOfPage is valid
 * and is what Google documents; merging would couple this plugin to another
 * plugin's internal filter names for no ranking gain.
 *
 * @param array $video Data from majestic_tube_video_seo_get_video().
 * @return array
 */
function majestic_tube_video_seo_build_schema( $video ) {
	$logo = '';

	if ( function_exists( 'get_custom_logo' ) ) {
		$logo_id = (int) get_theme_mod( 'custom_logo' );

		if ( $logo_id ) {
			$src = wp_get_attachment_image_src( $logo_id, 'full' );
			$logo = $src ? (string) $src[0] : '';
		}
	}

	$schema = array(
		'@context'         => 'https://schema.org',
		'@type'            => 'VideoObject',
		'@id'              => $video['url'] . '#video',
		'name'             => $video['title'],
		'description'      => $video['description'],
		'thumbnailUrl'     => array( $video['thumbnail'] ),
		'uploadDate'       => $video['upload_date'],
		'dateModified'     => $video['modified'],
		'duration'         => $video['duration'],
		'url'              => $video['url'],
		'mainEntityOfPage' => $video['url'],
		'inLanguage'       => get_bloginfo( 'language' ),
		// The site is 18+; isFamilyFriendly must say so or video results can
		// surface it in contexts it should not.
		'isFamilyFriendly' => false,
		'publisher'        => array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
		),
	);

	if ( $logo ) {
		$schema['publisher']['logo'] = array(
			'@type'  => 'ImageObject',
			'url'    => $logo,
			'width'  => 600,
			'height' => 60,
		);
	}

	if ( $video['content_loc'] ) {
		$schema['contentUrl']      = $video['content_loc'];
		$schema['encodingFormat']  = $video['mime'];
		$schema['embedUrl']        = $video['player_loc'] ? $video['player_loc'] : $video['content_loc'];
	} else {
		// Embedded player only. embedUrl is the documented field for this.
		$schema['embedUrl'] = $video['player_loc'];
		$schema['contentUrl'] = $video['player_loc'];
	}

	if ( $video['genres'] ) {
		$schema['genre'] = $video['genres'];
	}

	if ( $video['actors'] ) {
		$schema['actor'] = array_map(
			static function ( $name ) {
				return array(
					'@type' => 'Person',
					'name'  => $name,
				);
			},
			$video['actors']
		);
	}

	/**
	 * Filter the VideoObject node before it is serialised.
	 *
	 * @param array $schema Node data.
	 * @param array $video  Resolved video data.
	 */
	return apply_filters( 'majestic_tube_video_seo_video_schema', $schema, $video );
}

/**
 * Print the VideoObject script tag.
 *
 * @return void
 */
function majestic_tube_video_seo_output_json_ld() {
	$video = majestic_tube_video_seo_get_video( get_queried_object_id() );

	if ( ! $video ) {
		return;
	}

	$json = wp_json_encode( majestic_tube_video_seo_build_schema( $video ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	if ( false === $json ) {
		return;
	}

	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		$json // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode output.
	);
}

/* -------------------------------------------------------------------------
 * Open Graph and Twitter video tags
 * ---------------------------------------------------------------------- */

/**
 * Build the head tags this plugin is responsible for.
 *
 * @return string
 */
function majestic_tube_video_seo_head_tags() {
	$video = majestic_tube_video_seo_get_video( get_queried_object_id() );

	if ( ! $video ) {
		return '';
	}

	$lines = array();

	// The primary media URL. For a self-hosted file this is the file; for an
	// embed it is the player.
	$primary = $video['content_loc'] ? $video['content_loc'] : $video['player_loc'];

	if ( $video['content_loc'] ) {
		$lines[] = array( 'og:video', $video['content_loc'] );
		$lines[] = array( 'og:video:type', $video['mime'] );
	}

	$lines[] = array( 'og:video:url', $primary );

	if ( 0 === strpos( $primary, 'https://' ) ) {
		$lines[] = array( 'og:video:secure_url', $primary );
	}

	$lines[] = array( 'og:video:width', '1280' );
	$lines[] = array( 'og:video:height', '720' );

	// og:video:duration is an integer count of seconds, unlike the schema
	// field which is ISO 8601.
	$lines[] = array( 'og:video:duration', (string) $video['seconds'] );
	$lines[] = array( 'og:video:release_date', $video['upload_date'] );

	foreach ( $video['genres'] as $genre ) {
		$lines[] = array( 'og:video:tag', $genre );
	}

	foreach ( $video['actors'] as $actor ) {
		$lines[] = array( 'og:video:actor', $actor );
	}

	$out = '';

	foreach ( $lines as $line ) {
		$out .= sprintf(
			'<meta property="%s" content="%s" />' . "\n",
			esc_attr( $line[0] ),
			esc_attr( $line[1] )
		);
	}

	// Twitter's player card understands an embeddable player. The card type
	// itself is left to the SEO plugin; only the player fields are added.
	if ( $video['player_loc'] ) {
		$out .= sprintf( '<meta name="twitter:player" content="%s" />' . "\n", esc_url( $video['player_loc'] ) );
		$out .= sprintf( '<meta name="twitter:player:width" content="%d" />' . "\n", 1280 );
		$out .= sprintf( '<meta name="twitter:player:height" content="%d" />' . "\n", 720 );
	}

	return $out;
}

/**
 * Start buffering <head> on video pages.
 *
 * og:image is only added when the SEO plugin has not already emitted one, and
 * the only reliable way to know is to read what the other hooks produced.
 *
 * @return void
 */
function majestic_tube_video_seo_head_start() {
	if ( ! is_singular( majestic_tube_video_seo_post_types() ) ) {
		return;
	}

	/*
	 * The flag matters: majestic_tube_video_seo_head_finish() runs on the same condition, but if
	 * anything ever short-circuited it, an unguarded ob_get_clean() would
	 * close a buffer this plugin never opened and swallow the rest of the
	 * page.
	 */
	static $started = false;

	if ( $started ) {
		return;
	}

	$started = true;

	ob_start();
	add_action( 'wp_head', 'majestic_tube_video_seo_head_finish', PHP_INT_MAX );
}

/**
 * Close the buffer and append this plugin's tags.
 *
 * @return void
 */
function majestic_tube_video_seo_head_finish() {
	if ( ! is_singular( majestic_tube_video_seo_post_types() ) || ! ob_get_level() ) {
		return;
	}

	$head = ob_get_clean();
	$tags = majestic_tube_video_seo_head_tags();

	if ( '' === $tags ) {
		echo $head; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- passthrough.

		return;
	}

	if ( false === stripos( $head, 'og:image' ) ) {
		$video = majestic_tube_video_seo_get_video( get_queried_object_id() );

		if ( $video ) {
			$tags .= sprintf(
				'<meta property="og:image" content="%s" />' . "\n",
				esc_url( $video['thumbnail'] )
			);
		}
	}

	echo $head . $tags; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- passthrough plus escaped tags.
}

/* -------------------------------------------------------------------------
 * Video sitemap
 * ---------------------------------------------------------------------- */

/**
 * Register the sitemap rewrite and query var.
 *
 * @return void
 */
function majestic_tube_video_seo_register_sitemap() {
	add_rewrite_rule( '^video-sitemap\.xml$', 'index.php?majestic_tube_video_seo_sitemap=index', 'top' );
	add_rewrite_rule( '^video-sitemap-([0-9]+)\.xml$', 'index.php?majestic_tube_video_seo_sitemap=part&majestic_tube_video_seo_part=$matches[1]', 'top' );
}

/**
 * Expose the query vars.
 *
 * @param string[] $vars Public query vars.
 * @return string[]
 */
function majestic_tube_video_seo_query_vars( $vars ) {
	$vars[] = 'majestic_tube_video_seo_sitemap';
	$vars[] = 'majestic_tube_video_seo_part';

	return $vars;
}

/**
 * Flush rewrite rules once after install.
 *
 * A plugin has no activation hook when dropped in by hand, so the rules are
 * flushed on version change instead.
 *
 * @return void
 */
function majestic_tube_video_seo_maybe_flush_rewrites() {
	if ( get_option( 'majestic_tube_video_seo_rewrite_version' ) === MAJESTIC_TUBE_VIDEO_SEO_VERSION ) {
		return;
	}

	flush_rewrite_rules( false );
	update_option( 'majestic_tube_video_seo_rewrite_version', MAJESTIC_TUBE_VIDEO_SEO_VERSION, false );
}

/**
 * Number of published videos.
 *
 * @return int
 */
function majestic_tube_video_seo_total_videos() {
	$counts = wp_count_posts( majestic_tube_video_seo_post_types(), 'publish' );

	$total = 0;

	foreach ( (array) $counts as $count ) {
		$total += (int) $count;
	}

	return $total;
}

/**
 * Serve a sitemap request.
 *
 * @return void
 */
function majestic_tube_video_seo_render_sitemap() {
	$mode = get_query_var( 'majestic_tube_video_seo_sitemap' );

	if ( ! $mode ) {
		return;
	}

	$chunk = max( 1, majestic_tube_video_seo_chunk_size() );
	$total = majestic_tube_video_seo_total_videos();
	$parts = max( 1, (int) ceil( $total / $chunk ) );

	if ( 'part' === $mode ) {
		$part = max( 1, (int) get_query_var( 'majestic_tube_video_seo_part' ) );
		majestic_tube_video_seo_send_xml();
		echo majestic_tube_video_seo_sitemap_part_xml( $part, $chunk );
		exit;
	}

	majestic_tube_video_seo_send_xml();

	if ( 1 === $parts ) {
		echo majestic_tube_video_seo_sitemap_part_xml( 1, $chunk );
		exit;
	}

	printf(
		'<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
		'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
	);

	for ( $i = 1; $i <= $parts; $i++ ) {
		printf(
			"\t<sitemap>\n\t\t<loc>%s</loc>\n\t\t<lastmod>%s</lastmod>\n\t</sitemap>\n",
			esc_url( home_url( '/video-sitemap-' . $i . '.xml' ) ),
			esc_xml( gmdate( 'c' ) )
		);
	}

	echo "</sitemapindex>\n";
	exit;
}

/**
 * Send XML headers.
 *
 * @return void
 */
function majestic_tube_video_seo_send_xml() {
	if ( headers_sent() ) {
		return;
	}

	header( 'Content-Type: application/xml; charset=UTF-8' );
	header( 'X-Robots-Tag: noindex, follow', true );
}

/**
 * XML-escape a value.
 *
 * @param string $value Raw value.
 * @return string
 */
function majestic_tube_video_seo_xml( $value ) {
	return esc_xml( $value );
}

/**
 * Render one chunk of the video sitemap.
 *
 * Rows missing required data are skipped rather than emitted half-filled. The
 * audit command reports how many that is, because a sitemap full of skips
 * looks to Google like a site that stopped maintaining its videos.
 *
 * @param int $part  Chunk number, 1-based.
 * @param int $chunk URLs per chunk.
 * @return string
 */
function majestic_tube_video_seo_sitemap_part_xml( $part, $chunk ) {
	$query = new WP_Query(
		array(
			'post_type'           => majestic_tube_video_seo_post_types(),
			'post_status'         => 'publish',
			'posts_per_page'      => $chunk,
			'paged'               => max( 1, $part ),
			// Ordered by ID rather than date: post dates move during bulk
			// imports, which would reshuffle every page boundary and make
			// Google re-crawl URLs that did not change.
			'orderby'             => 'ID',
			'order'               => 'ASC',
			'fields'              => 'ids',
			'no_found_rows'       => false,
			'ignore_sticky_posts' => true,
			'suppress_filters'    => true,
		)
	);

	$out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

	if ( ! $query->have_posts() ) {
		return $out . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"></urlset>';
	}

	$out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

	foreach ( $query->posts as $post_id ) {
		$video = majestic_tube_video_seo_get_video( $post_id );

		if ( ! $video ) {
			continue;
		}

		$out .= "\t<url>\n";
		$out .= "\t\t<loc>" . majestic_tube_video_seo_xml( $video['url'] ) . "</loc>\n";
		$out .= "\t\t<lastmod>" . majestic_tube_video_seo_xml( $video['modified'] ) . "</lastmod>\n";
		$out .= "\t\t<video:video>\n";
		$out .= "\t\t\t<video:thumbnail_loc>" . majestic_tube_video_seo_xml( $video['thumbnail'] ) . "</video:thumbnail_loc>\n";
		$out .= "\t\t\t<video:title>" . majestic_tube_video_seo_xml( $video['title'] ) . "</video:title>\n";
		$out .= "\t\t\t<video:description>" . majestic_tube_video_seo_xml( $video['description'] ) . "</video:description>\n";

		/*
		 * content_loc must be the media file itself. An embed has no file,
		 * so only player_loc is emitted - putting a player page in
		 * content_loc is the most common reason Google rejects a video
		 * sitemap outright.
		 */
		if ( $video['content_loc'] ) {
			$out .= "\t\t\t<video:content_loc>" . majestic_tube_video_seo_xml( $video['content_loc'] ) . "</video:content_loc>\n";
		}

		if ( $video['player_loc'] ) {
			$out .= "\t\t\t<video:player_loc allow_embed=\"yes\">" . majestic_tube_video_seo_xml( $video['player_loc'] ) . "</video:player_loc>\n";
		}

		$out .= "\t\t\t<video:duration>" . (int) $video['seconds'] . "</video:duration>\n";
		$out .= "\t\t\t<video:publication_date>" . majestic_tube_video_seo_xml( $video['upload_date'] ) . "</video:publication_date>\n";
		$out .= "\t\t\t<video:family_friendly>no</video:family_friendly>\n";
		$out .= "\t\t\t<video:requires_subscription>no</video:requires_subscription>\n";
		$out .= "\t\t\t<video:live>no</video:live>\n";
		$out .= "\t\t\t<video:uploader info=\"" . majestic_tube_video_seo_xml( home_url() ) . '">' . majestic_tube_video_seo_xml( get_bloginfo( 'name' ) ) . "</video:uploader>\n";

		foreach ( $video['genres'] as $genre ) {
			$out .= "\t\t\t<video:tag>" . majestic_tube_video_seo_xml( $genre ) . "</video:tag>\n";
		}

		foreach ( $video['actors'] as $actor ) {
			$out .= "\t\t\t<video:tag>" . majestic_tube_video_seo_xml( $actor ) . "</video:tag>\n";
		}

		$out .= "\t\t</video:video>\n";
		$out .= "\t</url>\n";
	}

	$out .= '</urlset>';

	return $out;
}

/* -------------------------------------------------------------------------
 * robots.txt
 * ---------------------------------------------------------------------- */

/**
 * Advertise the video sitemap in robots.txt.
 *
 * @param string $output Existing robots.txt body.
 * @param string $public Whether the site is public.
 * @return string
 */
function majestic_tube_video_seo_robots_txt( $output, $public ) {
	if ( '1' !== (string) $public ) {
		return $output;
	}

	$output = rtrim( $output, "\n" );

	return $output . "\n\nSitemap: " . esc_url( home_url( '/video-sitemap.xml' ) ) . "\n";
}

/* -------------------------------------------------------------------------
 * Admin: what is missing
 *
 * A sitemap can only advertise what the data supports. These two surfaces
 * exist so the gaps are visible before the sitemap is submitted rather than
 * after Google quietly ignores it.
 * ---------------------------------------------------------------------- */

/**
 * Add a "Video SEO" column to the video list table.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function majestic_tube_video_seo_admin_column( $columns ) {
	$columns['majestic_tube_video_seo_seo'] = __( 'Video SEO', 'majestic-tube' );

	return $columns;
}

/**
 * Render the column.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 * @return void
 */
function majestic_tube_video_seo_admin_column_content( $column, $post_id ) {
	if ( 'majestic_tube_video_seo_seo' !== $column ) {
		return;
	}

	$video = majestic_tube_video_seo_get_video( $post_id );

	if ( $video ) {
		printf(
			'<span title="%s">%s</span>',
			esc_attr(
				sprintf(
					/* translators: 1: duration, 2: source kind. */
					__( 'Duration %1$s, %2$s', 'majestic-tube' ),
					majestic_tube_video_seo_iso8601_duration( $video['seconds'] ),
					$video['content_loc'] ? __( 'self-hosted', 'majestic-tube' ) : __( 'embed', 'majestic-tube' )
				)
			),
			esc_html( $video['content_loc'] ? 'OK' : __( 'OK (embed)', 'majestic-tube' ) )
		);

		return;
	}

	$missing = array();

	if ( majestic_tube_video_seo_duration_seconds( $post_id ) <= 0 ) {
		$missing[] = __( 'duration', 'majestic-tube' );
	}

	if ( '' === majestic_tube_video_seo_thumbnail_url( $post_id ) ) {
		$missing[] = __( 'thumbnail', 'majestic-tube' );
	}

	if ( '' === majestic_tube_video_seo_content_loc( $post_id ) && '' === majestic_tube_video_seo_player_loc( $post_id ) ) {
		$missing[] = __( 'source', 'majestic-tube' );
	}

	printf(
		'<span style="color:#b32d2e">%s</span>',
		esc_html(
			$missing
				? sprintf(
					/* translators: %s: comma separated list of missing fields. */
					__( 'Missing: %s', 'majestic-tube' ),
					implode( ', ', $missing )
				)
				: __( 'Missing data', 'majestic-tube' )
		)
	);
}

/**
 * Warn on the video list screen when most posts are incomplete.
 *
 * @return void
 */
function majestic_tube_video_seo_admin_notice() {
	$screen = get_current_screen();

	if ( ! $screen || 'edit-post' !== $screen->id ) {
		return;
	}

	$counts = wp_count_posts( majestic_tube_video_seo_post_types(), 'publish' );
	$total  = 0;

	foreach ( (array) $counts as $count ) {
		$total += (int) $count;
	}

	if ( $total < 50 ) {
		return;
	}

	$sample = get_posts(
		array(
			'post_type'      => majestic_tube_video_seo_post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);

	if ( ! $sample ) {
		return;
	}

	$incomplete = 0;

	foreach ( $sample as $post_id ) {
		if ( ! majestic_tube_video_seo_get_video( $post_id ) ) {
			++$incomplete;
		}
	}

	$percent = (int) round( ( $incomplete / count( $sample ) ) * 100 );

	if ( $percent < 20 ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
		esc_html__( 'Majestic Video SEO:', 'majestic-tube' ),
		esc_html(
			sprintf(
				/* translators: 1: percentage of sampled posts, 2: sample size. */
				__( '%1$d%% of the last %2$d published videos are missing a duration, thumbnail or source, so they are left out of the video sitemap.', 'majestic-tube' ),
				$percent,
				count( $sample )
			)
		)
	);
}

/* -------------------------------------------------------------------------
 * WP-CLI audit
 * ---------------------------------------------------------------------- */

/**
 * Report how much of the library can actually be submitted to Google.
 *
 * The embed-host breakdown matters most: a library built on another domain's
 * player is eligible for normal web indexing but its videos will not enter
 * this site's video inventory, which is worth knowing before planning around
 * video search traffic.
 *
 * ## EXAMPLES
 *
 *     wp majestic-tube audit
 *     wp majestic-tube audit --limit=5000
 *     wp majestic-tube audit --show-issues=20
 *
 * @param array $args       Positional args (unused).
 * @param array $assoc_args Flags: --limit, --show-issues.
 * @return void
 */
function majestic_tube_video_seo_cli_audit( $args, $assoc_args ) {
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
		return;
	}

	$limit       = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 0;
	$show_issues = isset( $assoc_args['show-issues'] ) ? (int) $assoc_args['show-issues'] : 0;

	$query_args = array(
		'post_type'           => majestic_tube_video_seo_post_types(),
		'post_status'         => 'publish',
		'posts_per_page'      => $limit > 0 ? $limit : -1,
		'fields'              => 'ids',
		'orderby'             => 'ID',
		'order'               => 'ASC',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
		'suppress_filters'    => true,
	);

	$ids = get_posts( $query_args );

	$stats = array(
		'total'          => 0,
		'ok'             => 0,
		'missing_dur'    => 0,
		'missing_thumb'  => 0,
		'missing_source'  => 0,
		'no_description' => 0,
		'self_hosted'    => 0,
		'embed'          => 0,
		'external_embed' => 0,
	);

	$hosts = array();
	$bad   = array();

	foreach ( $ids as $post_id ) {
		++$stats['total'];

		$video = majestic_tube_video_seo_get_video( $post_id );

		if ( $video ) {
			++$stats['ok'];

			if ( $video['content_loc'] ) {
				++$stats['self_hosted'];
			} else {
				++$stats['embed'];
			}

			if ( $video['external'] ) {
				++$stats['external_embed'];
				$host                  = (string) wp_parse_url( $video['player_loc'], PHP_URL_HOST );
				$hosts[ $host ]        = isset( $hosts[ $host ] ) ? $hosts[ $host ] + 1 : 1;
			}
		} else {
			if ( majestic_tube_video_seo_duration_seconds( $post_id ) <= 0 ) {
				++$stats['missing_dur'];
			}

			if ( '' === majestic_tube_video_seo_thumbnail_url( $post_id ) ) {
				++$stats['missing_thumb'];
			}

			if ( '' === majestic_tube_video_seo_content_loc( $post_id ) && '' === majestic_tube_video_seo_player_loc( $post_id ) ) {
				++$stats['missing_source'];
			}

			if ( count( $bad ) < 25 ) {
				$bad[] = $post_id;
			}
		}

		if ( ! majestic_tube_video_seo_has_real_description( $post_id ) ) {
			++$stats['no_description'];
		}
	}

	$total = max( 1, $stats['total'] );

	WP_CLI::line( '' );
	WP_CLI::log( 'Majestic Video SEO audit' );
	WP_CLI::line( str_repeat( '-', 46 ) );
	WP_CLI::log( sprintf( 'Published videos      : %d', $stats['total'] ) );
	WP_CLI::log( sprintf( 'Submittable           : %d (%.1f%%)', $stats['ok'], ( $stats['ok'] / $total ) * 100 ) );
	WP_CLI::line( '' );
	WP_CLI::log( sprintf( 'Missing duration      : %d', $stats['missing_dur'] ) );
	WP_CLI::log( sprintf( 'Missing thumbnail     : %d', $stats['missing_thumb'] ) );
	WP_CLI::log( sprintf( 'Missing source        : %d', $stats['missing_source'] ) );
	WP_CLI::log( sprintf( 'No real description   : %d (%.1f%%)', $stats['no_description'], ( $stats['no_description'] / $total ) * 100 ) );
	WP_CLI::line( '' );
	WP_CLI::log( sprintf( 'Self-hosted files     : %d', $stats['self_hosted'] ) );
	WP_CLI::log( sprintf( 'Embedded players      : %d', $stats['embed'] ) );
	WP_CLI::log( sprintf( '...on another domain  : %d (%.1f%%)', $stats['external_embed'], ( $stats['external_embed'] / $total ) * 100 ) );
	WP_CLI::line( '' );

	if ( $hosts ) {
		arsort( $hosts );
		WP_CLI::log( 'Embed hosts:' );

		foreach ( array_slice( $hosts, 0, 10, true ) as $host => $count ) {
			WP_CLI::log( sprintf( '  %-40s %d', $host, $count ) );
		}

		WP_CLI::line( '' );
		WP_CLI::warning( 'Videos played from another domain cannot be indexed into this site\'s video inventory. They can still rank as normal web pages, but they will not appear in Google Video results. Self-hosting, or embedding a player served from this domain, is the only way to qualify.' );
		WP_CLI::line( '' );
	}

	if ( $bad ) {
		WP_CLI::log( 'Sample of incomplete posts:' );

		foreach ( array_slice( $bad, 0, $show_issues ) as $post_id ) {
			WP_CLI::log( sprintf( '  #%d  %s', $post_id, get_the_title( $post_id ) ) );
		}

		WP_CLI::line( '' );
	}

	if ( $stats['ok'] < $stats['total'] ) {
		WP_CLI::warning( sprintf( '%d videos will be skipped by the video sitemap. Fix them before submitting it.', $stats['total'] - $stats['ok'] ) );

		return;
	}

	WP_CLI::success( 'Every published video has the data a video sitemap entry needs.' );
}
add_action(
	'cli_command',
	function ( $commands ) {
		if ( class_exists( 'WP_CLI' ) ) {
			$commands->add_command( 'majestic-tube audit', 'majestic_tube_video_seo_cli_audit' );
		}
	}
);

/* -------------------------------------------------------------------------
 * Boot
 * ---------------------------------------------------------------------- */

/**
 * Front-end structured data. Gated on the master switch.
 *
 * @return void
 */
function majestic_tube_video_seo_boot_front_end() {
	if ( ! majestic_tube_video_seo_is_enabled() ) {
		return;
	}

	add_action( 'wp_head', 'majestic_tube_video_seo_head_start', 1 );
	add_action( 'wp_head', 'majestic_tube_video_seo_output_json_ld', 5 );
}

/**
 * Video sitemap endpoint, rewrite rules and robots.txt line.
 *
 * Registered independently of the master switch so a site can keep its video
 * sitemap while turning off the in-page markup, or the reverse.
 *
 * @return void
 */
function majestic_tube_video_seo_boot_sitemap() {
	if ( ! majestic_tube_video_seo_sitemap_is_enabled() ) {
		return;
	}

	add_action( 'init', 'majestic_tube_video_seo_register_sitemap', 5 );
	add_action( 'wp_loaded', 'majestic_tube_video_seo_maybe_flush_rewrites' );
	add_filter( 'query_vars', 'majestic_tube_video_seo_query_vars' );
	add_action( 'template_redirect', 'majestic_tube_video_seo_render_sitemap' );
	add_filter( 'robots_txt', 'majestic_tube_video_seo_robots_txt', 10, 2 );
}

/**
 * Admin diagnostics. Gated on the master switch.
 *
 * @return void
 */
function majestic_tube_video_seo_boot_admin() {
	if ( ! majestic_tube_video_seo_is_enabled() || ! is_admin() ) {
		return;
	}

	add_filter( 'manage_post_posts_columns', 'majestic_tube_video_seo_admin_column' );
	add_action( 'manage_post_posts_custom_column', 'majestic_tube_video_seo_admin_column_content', 10, 2 );
	add_action( 'admin_notices', 'majestic_tube_video_seo_admin_notice' );
}
add_action( 'admin_init', 'majestic_tube_video_seo_boot_admin' );

/*
 * Called directly rather than through plugins_loaded. A theme's functions.php
 * is included after plugins_loaded has already fired, so a boot registered
 * there would never run; at this point init and wp_loaded are both still
 * ahead of us, which is exactly what the two functions above need.
 */
majestic_tube_video_seo_boot_front_end();
majestic_tube_video_seo_boot_sitemap();
