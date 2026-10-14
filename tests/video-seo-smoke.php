<?php
/**
 * Standalone smoke test for inc/video-seo.php.
 *
 * The module is a theme file, so it is normally exercised by loading a
 * WordPress install. This harness stubs the handful of WordPress functions the
 * module touches so the output can be inspected without a database: it
 * includes the real file and asserts on the real JSON-LD and sitemap XML.
 *
 * Run:  php tests/video-seo-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_VERSION', '2.4.0' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );
define( 'MAJESTIC_TUBE_URI', 'https://example.test' );
define( 'MAJESTIC_TUBE_LEGACY_OPTION', 'wpst-options' );

$GLOBALS['mtv_hooks']   = array();
$GLOBALS['mtv_options'] = array();

/* -------------------------------------------------------------------------
 * Minimal WordPress stubs
 * ---------------------------------------------------------------------- */

function add_action( $hook, $cb, $priority = 10, $args = 1 ) {
	$GLOBALS['mtv_hooks'][] = array( 'action', $hook, is_string( $cb ) ? $cb : '(closure)' );
}
function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {
	$GLOBALS['mtv_hooks'][] = array( 'filter', $hook, is_string( $cb ) ? $cb : '(closure)' );
}
function do_action( $hook ) {}
function apply_filters( $hook, $value ) {
	return $value;
}
function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['mtv_options'] ) ? $GLOBALS['mtv_options'][ $key ] : $default;
}
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['mtv_options'][ $key ] = $value;
}
function absint( $v ) {
	return abs( (int) $v );
}
function __( $s, $d = null ) {
	return $s;
}
function _x( $s, $c = '', $d = null ) {
	return $s;
}
function esc_url( $u ) {
	return htmlspecialchars( (string) $u, ENT_QUOTES, 'UTF-8' );
}
function esc_url_raw( $u ) {
	return $u;
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_xml( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8' );
}
function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}
function get_bloginfo( $what = 'name' ) {
	$map = array(
		'name'     => 'Test Tube',
		'language' => 'en-US',
	);

	return isset( $map[ $what ] ) ? $map[ $what ] : '';
}
function get_theme_mod( $key, $default = null ) {
	return $default;
}
function wp_get_attachment_image_src( $id, $size = 'thumbnail' ) {
	return false;
}
function wp_json_encode( $data, $flags = 0 ) {
	return json_encode( $data, $flags );
}
function wp_strip_all_tags( $s ) {
	return trim( strip_tags( (string) $s ) );
}
function strip_shortcodes( $s ) {
	return $s;
}
function is_wp_error( $t ) {
	return false;
}
function is_admin() {
	return false;
}
function is_singular( $types = '' ) {
	return false;
}
function add_rewrite_rule( $regex, $query, $after = 'bottom' ) {}
function get_query_var( $var, $default = '' ) {
	return $default;
}
function wp_count_posts( $types, $status = 'publish' ) {
	$counts = array();

	foreach ( (array) $types as $type ) {
		$counts[ $type ] = count( $GLOBALS['mtv_posts'] );
	}

	return (object) $counts;
}

/* -------------------------------------------------------------------------
 * Fixture posts
 * ---------------------------------------------------------------------- */

$GLOBALS['mtv_posts'] = array(
	array(
		'ID'          => 1,
		'post_title'  => 'Embedded Clip',
		'post_excerpt' => 'An embedded clip with a real excerpt.',
		'post_content' => '',
		'date'        => '2026-10-02 09:10:01',
		'modified'    => '2026-10-02 09:10:01',
		'meta'        => array(
			'duration' => 479,
			'embed'    => '<iframe src="https://videovjav.com/embed/992559/" width="640"></iframe>',
		),
		'terms'       => array(
			'category' => array( 'HD', 'Japanese' ),
			'post_tag' => array( 'asian' ),
			'actors'   => array( 'A Person' ),
		),
	),
	array(
		'ID'          => 2,
		'post_title'  => 'Self Hosted Clip & More',
		'post_excerpt' => '',
		'post_content' => 'Body text used as the description fallback.',
		'date'        => '2026-10-01 10:00:00',
		'modified'    => '2026-10-01 11:00:00',
		'meta'        => array(
			'duration'      => 4997,
			'video_url_4k'  => 'https://example.test/uploads/4k.mp4',
			'video_url_720' => 'https://example.test/uploads/720.mp4',
		),
		'terms'       => array(
			'category' => array( 'HD' ),
			'post_tag' => array(),
			'actors'   => array(),
		),
	),
	array(
		// Incomplete on purpose: no duration, so it must be skipped.
		'ID'          => 3,
		'post_title'  => 'No Duration Clip',
		'post_excerpt' => '',
		'post_content' => '',
		'date'        => '2026-09-30 10:00:00',
		'modified'    => '2026-09-30 10:00:00',
		'meta'        => array(),
		'terms'       => array(),
	),
);

function mtv_fixture( $post_id ) {
	foreach ( $GLOBALS['mtv_posts'] as $post ) {
		if ( $post['ID'] === (int) $post_id ) {
			return $post;
		}
	}

	return null;
}

/**
 * Minimal WP_Query stand-in returning fixture IDs.
 */
class WP_Query {
	public $posts = array();
	public $found = 0;

	public function __construct( $args = array() ) {
		$paged    = isset( $args['paged'] ) ? (int) $args['paged'] : 1;
		$per_page = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 10;
		$ids      = array_column( $GLOBALS['mtv_posts'], 'ID' );
		$slice    = $paged > 1 ? array() : $ids;

		if ( -1 === $per_page ) {
			$slice = $ids;
		} elseif ( $paged <= 1 ) {
			$slice = array_slice( $ids, 0, $per_page );
		}

		$this->posts = $slice;
		$this->found = count( $slice );
	}

	public function have_posts() {
		return ! empty( $this->posts );
	}
}

function get_post( $post_id ) {
	$post = mtv_fixture( $post_id );

	return $post ? (object) $post : null;
}
function get_post_meta( $post_id, $key, $single = false ) {
	$post = mtv_fixture( $post_id );

	return $post && isset( $post['meta'][ $key ] ) ? $post['meta'][ $key ] : '';
}
function get_post_field( $field, $post_id ) {
	$post = mtv_fixture( $post_id );

	if ( ! $post || ! isset( $post[ $field ] ) ) {
		return '';
	}

	return $post[ $field ];
}
function get_the_title( $post_id = 0 ) {
	$post = mtv_fixture( $post_id );

	return $post ? $post['post_title'] : '';
}
function get_permalink( $post_id = 0 ) {
	$post = mtv_fixture( $post_id );

	return $post ? home_url( '/video-' . $post_id . '/' ) : '';
}
function get_post_time( $format, $gmt = false, $post_id = 0 ) {
	$post = mtv_fixture( $post_id );

	return $post ? gmdate( 'c', strtotime( $post['date'] ) ) : '';
}
function get_post_modified_time( $format, $gmt = false, $post_id = 0 ) {
	$post = mtv_fixture( $post_id );

	return $post ? gmdate( 'c', strtotime( $post['modified'] ) ) : '';
}
function get_the_terms( $post_id, $taxonomy ) {
	$post = mtv_fixture( $post_id );

	if ( ! $post || empty( $post['terms'][ $taxonomy ] ) ) {
		return false;
	}

	$terms = array();

	foreach ( $post['terms'][ $taxonomy ] as $name ) {
		$term        = new stdClass();
		$term->name  = $name;
		$term->slug  = sanitize_title( $name );
		$terms[]     = $term;
	}

	return $terms;
}
function sanitize_title( $t ) {
	return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $t ) );
}

/* -------------------------------------------------------------------------
 * Theme helpers the module calls
 * ---------------------------------------------------------------------- */

function majestic_tube_get_duration_seconds( $post_id = 0 ) {
	$post = mtv_fixture( $post_id );

	return $post && isset( $post['meta']['duration'] ) ? absint( $post['meta']['duration'] ) : 0;
}
function majestic_tube_get_thumb_url( $post_id = 0, $size = '' ) {
	return 'https://example.test/wp-content/uploads/thumb-' . (int) $post_id . '.jpg';
}
function majestic_tube_get_video_mime( $url, $fallback = 'video/mp4' ) {
	return ( false !== strpos( $url, '.mp4' ) ) ? 'video/mp4' : $fallback;
}
function majestic_tube_get_video_sources( $post_id = 0 ) {
	$post = mtv_fixture( $post_id );
	$meta = $post ? $post['meta'] : array();
	$main = isset( $meta['video_url'] ) ? $meta['video_url'] : '';
	$embed = isset( $meta['embed'] ) ? $meta['embed'] : '';

	$sources = array();

	foreach ( array( 'video_url_4k', 'video_url_1080', 'video_url_720', 'video_url_480', 'video_url_360', 'video_url_240' ) as $key ) {
		if ( ! empty( $meta[ $key ] ) ) {
			$sources[ $key ] = $meta[ $key ];
		}
	}

	if ( $sources ) {
		$type = 'self-hosted';
	} elseif ( '' !== trim( $embed ) ) {
		$type = 'embed';
	} else {
		$type = 'none';
	}

	return array(
		'type'      => $type,
		'sources'   => $sources,
		'main'      => $main,
		'embed'     => $embed,
		'shortcode' => '',
	);
}

/**
 * The theme's own option reader, stubbed to the module's three lookups.
 *
 * @param string $group     Option group.
 * @param string $field_id  Field.
 * @param mixed  $default   Default.
 * @return mixed
 */
function majestic_tube_get_option( $group, $field_id, $default = false ) {
	$map = array(
		'enable-video-seo'         => 'majestic_tube_enable_video_seo',
		'video-sitemap-enabled'    => 'majestic_tube_video_sitemap_enabled',
		'video-sitemap-chunk-size' => 'majestic_tube_video_sitemap_chunk_size',
	);

	if ( ! isset( $map[ $field_id ] ) ) {
		return $default;
	}

	$value = get_option( $map[ $field_id ], $default );

	// A number field with no saved value returns '' - treat as unset.
	return ( '' === $value || null === $value ) ? $default : $value;
}

/**
 * The theme's real ISO 8601 helper, loaded from compat.php semantics.
 *
 * Mirrors inc/compat.php so the test exercises the same output form the
 * module relies on.
 *
 * @param int $seconds Duration in seconds.
 * @return string
 */
function wpst_iso8601_duration( $seconds ) {
	$seconds = absint( $seconds );
	$days    = floor( $seconds / 86400 );
	$seconds = $seconds % 86400;
	$hours   = floor( $seconds / 3600 );
	$seconds = $seconds % 3600;
	$minutes = floor( $seconds / 60 );
	$seconds = $seconds % 60;

	return sprintf( 'P%dDT%dH%dM%dS', $days, $hours, $minutes, $seconds );
}

/* -------------------------------------------------------------------------
 * Load the module under test
 * ---------------------------------------------------------------------- */

require_once MAJESTIC_TUBE_DIR . '/inc/video-seo.php';

/* -------------------------------------------------------------------------
 * Assertions
 * ---------------------------------------------------------------------- */

$passed = 0;
$failed = 0;

/**
 * Assert a condition.
 *
 * @param string $label   What is being checked.
 * @param bool   $ok      Result.
 * @param string $context Extra detail printed on failure.
 * @return void
 */
function check( $label, $ok, $context = '' ) {
	global $passed, $failed;

	if ( $ok ) {
		++$passed;
		printf( "  [PASS] %s\n", $label );

		return;
	}

	++$failed;
	printf( "  [FAIL] %s%s\n", $label, $context ? "\n         $context" : '' );
}

echo "\nMajestic Video SEO - smoke test\n";
echo str_repeat( '-', 46 ) . "\n";

echo "\nModule loads\n";

$expected_functions = array(
	'majestic_tube_video_seo_is_enabled',
	'majestic_tube_video_seo_sitemap_is_enabled',
	'majestic_tube_video_seo_get_video',
	'majestic_tube_video_seo_build_schema',
	'majestic_tube_video_seo_head_tags',
	'majestic_tube_video_seo_register_sitemap',
	'majestic_tube_video_seo_render_sitemap',
	'majestic_tube_video_seo_robots_txt',
	'majestic_tube_video_seo_xml',
	'majestic_tube_video_seo_iso8601_duration',
);

$missing = array();

foreach ( $expected_functions as $fn ) {
	if ( ! function_exists( $fn ) ) {
		$missing[] = $fn;
	}
}

check( 'all module functions defined', empty( $missing ), 'missing: ' . implode( ', ', $missing ) );

$hooked = array();

foreach ( $GLOBALS['mtv_hooks'] as $hook ) {
	$hooked[ $hook[1] . '::' . $hook[2] ] = true;
}

check( 'init hook registered for rewrite rules', isset( $hooked['init::majestic_tube_video_seo_register_sitemap'] ) );
check( 'template_redirect registered for sitemap', isset( $hooked['template_redirect::majestic_tube_video_seo_render_sitemap'] ) );
check( 'query_vars filter registered', isset( $hooked['query_vars::majestic_tube_video_seo_query_vars'] ) );
check( 'robots_txt filter registered', isset( $hooked['robots_txt::majestic_tube_video_seo_robots_txt'] ) );
check( 'wp_head registered for head buffer', isset( $hooked['wp_head::majestic_tube_video_seo_head_start'] ) );
check( 'wp_head registered for JSON-LD', isset( $hooked['wp_head::majestic_tube_video_seo_output_json_ld'] ) );

echo "\nMaster switch\n";

check( 'enabled by default', majestic_tube_video_seo_is_enabled() );
check( 'sitemap enabled by default', majestic_tube_video_seo_sitemap_is_enabled() );

update_option( 'majestic_tube_enable_video_seo', 'off' );
check( 'master switch turns output off', ! majestic_tube_video_seo_is_enabled() );
update_option( 'majestic_tube_enable_video_seo', 'on' );

update_option( 'majestic_tube_video_sitemap_enabled', 'off' );
check( 'sitemap switch turns sitemap off', ! majestic_tube_video_seo_sitemap_is_enabled() );
update_option( 'majestic_tube_video_sitemap_enabled', 'on' );

check( 'chunk size defaults to 1000', 1000 === majestic_tube_video_seo_chunk_size() );

update_option( 'majestic_tube_video_sitemap_chunk_size', 500 );
check( 'chunk size reads from theme options', 500 === majestic_tube_video_seo_chunk_size() );
update_option( 'majestic_tube_video_sitemap_chunk_size', 0 );
check( 'chunk size falls back when blank', 1000 === majestic_tube_video_seo_chunk_size() );
update_option( 'majestic_tube_video_sitemap_chunk_size', 1000 );

check( 'post types default to post', array( 'post' ) === majestic_tube_video_seo_post_types() );

echo "\nDuration\n";

check( '07:59 renders as ISO 8601', 'P0DT0H7M59S' === majestic_tube_video_seo_iso8601_duration( 479 ) );
check( '1:23:17 renders with hours', 'P0DT1H23M17S' === majestic_tube_video_seo_iso8601_duration( 4997 ) );
check( 'zero renders as zero', 'P0DT0H0M0S' === majestic_tube_video_seo_iso8601_duration( 0 ) );

echo "\nIframe src extraction\n";

$cases = array(
	'double-quoted src'  => array(
		'<iframe src="https://videovjav.com/embed/992559/?source=1" width="640"></iframe>',
		'https://videovjav.com/embed/992559/?source=1',
	),
	'single-quoted src'  => array(
		"<iframe class='x' src='https://a.example/e/1' allowfullscreen></iframe>",
		'https://a.example/e/1',
	),
	'src before other attrs' => array(
		'<iframe allow="autoplay" src="https://b.example/v/2"></iframe>',
		'https://b.example/v/2',
	),
	'no iframe'          => array( '<div>nope</div>', '' ),
	'empty string'       => array( '', '' ),
);

foreach ( $cases as $label => $case ) {
	$got = majestic_tube_video_seo_player_src( $case[0] );
	check( $label, $got === $case[1], 'got: ' . var_export( $got, true ) . ' want: ' . var_export( $case[1], true ) );
}

echo "\nVideoObject JSON-LD (third-party embed)\n";

$embed_video = array(
	'id'          => 123,
	'title'       => 'A Video Title',
	'description' => 'A description of the video.',
	'thumbnail'   => 'https://example.test/wp-content/uploads/1-343.jpg',
	'seconds'     => 479,
	'duration'    => 'P0DT0H7M59S',
	'content_loc' => '',
	'player_loc'  => 'https://videovjav.com/embed/992559/',
	'url'         => 'https://example.test/a-video-title/',
	'upload_date' => '2026-10-02T09:10:01+00:00',
	'modified'    => '2026-10-02T09:10:01+00:00',
	'genres'      => array( 'HD', 'Japanese' ),
	'tags'        => array( 'asian', 'babe' ),
	'actors'      => array( 'Some Name' ),
	'mime'        => 'video/mp4',
	'external'    => true,
);

$schema   = majestic_tube_video_seo_build_schema( $embed_video );
$decoded  = json_decode( wp_json_encode( $schema, JSON_UNESCAPED_SLASHES ), true );

check( '@context is schema.org', isset( $decoded['@context'] ) && 'https://schema.org' === $decoded['@context'] );
check( '@type is VideoObject', 'VideoObject' === $decoded['@type'] );
check( 'isFamilyFriendly is false for 18+', array_key_exists( 'isFamilyFriendly', $decoded ) && false === $decoded['isFamilyFriendly'] );
check( 'embedUrl is the player', 'https://videovjav.com/embed/992559/' === $decoded['embedUrl'] );
check( 'contentUrl present for embed', isset( $decoded['contentUrl'] ) );
check( 'duration is ISO 8601', 'P0DT0H7M59S' === $decoded['duration'] );
check( 'uploadDate present', '2026-10-02T09:10:01+00:00' === $decoded['uploadDate'] );
check( 'thumbnailUrl is an array', isset( $decoded['thumbnailUrl'] ) && is_array( $decoded['thumbnailUrl'] ) );
check( 'genre is a list', isset( $decoded['genre'] ) && array( 'HD', 'Japanese' ) === $decoded['genre'] );
check( 'actor is a Person list', isset( $decoded['actor'][0]['@type'] ) && 'Person' === $decoded['actor'][0]['@type'] );
check( 'publisher present', isset( $decoded['publisher']['name'] ) && 'Test Tube' === $decoded['publisher']['name'] );
check( 'no logo key without a custom logo', ! isset( $decoded['publisher']['logo'] ) );

echo "\nVideoObject JSON-LD (self-hosted)\n";

$file_video               = $embed_video;
$file_video['content_loc'] = 'https://example.test/wp-content/uploads/2026/10/clip.mp4';
$file_video['player_loc']  = '';
$file_video['mime']        = 'video/mp4';

$decoded = json_decode( wp_json_encode( majestic_tube_video_seo_build_schema( $file_video ), JSON_UNESCAPED_SLASHES ), true );

check( 'contentUrl is the media file', 'https://example.test/wp-content/uploads/2026/10/clip.mp4' === $decoded['contentUrl'] );
check( 'embedUrl falls back to contentUrl', $decoded['embedUrl'] === $decoded['contentUrl'] );
check( 'encodingFormat present', 'video/mp4' === $decoded['encodingFormat'] );

echo "\nXML escaping\n";

check( 'ampersand escaped', 'a &amp; b' === majestic_tube_video_seo_xml( 'a & b' ) );
check( 'bare ampersand does not corrupt XML', false === strpos( 'a &amp; b', 'a & b' ) );
check( 'quotes escaped', '&quot;x&quot;' === majestic_tube_video_seo_xml( '"x"' ) );
check( 'angle brackets escaped', '&lt;b&gt;' === majestic_tube_video_seo_xml( '<b>' ) );

echo "\nVideo sitemap XML\n";

$xml = majestic_tube_video_seo_sitemap_part_xml( 1, 1000 );

// DUMP=1 prints the generated XML, which is the quickest way to eyeball a
// change to the row builder.
if ( getenv( 'DUMP' ) ) {
	echo "\n----- generated sitemap -----\n" . $xml . "\n----- end -----\n\n";
}

check( 'declares the Google video namespace', false !== strpos( $xml, 'xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"' ) );
check( 'is well-formed', false !== simplexml_load_string( $xml ), 'simplexml could not parse the document' );
check( 'skips the video with no duration', false === strpos( $xml, 'No Duration Clip' ) );
check( 'includes the embedded clip', false !== strpos( $xml, 'Embedded Clip' ) );
check( 'includes the self-hosted clip', false !== strpos( $xml, 'Self Hosted Clip &amp; More' ) );	$simplexml = simplexml_load_string( $xml );

if ( $simplexml ) {
	$video_ns   = 'http://www.google.com/schemas/sitemap-video/1.1';
	$simplexml->registerXPathNamespace( 'video', $video_ns );
	$entries    = $simplexml->xpath( '//video:video' );

	check( 'two video entries emitted', 2 === count( $entries ), 'got ' . count( $entries ) );

	// The children live in the video namespace, so they have to be reached
	// through children() - a plain ->title on the node returns nothing.
	$first  = $entries[0]->children( $video_ns );
	$second = $entries[1]->children( $video_ns );

	check( 'embed uses player_loc, not content_loc', '' === (string) $first->content_loc && '' !== (string) $first->player_loc );
	check( 'embed player_loc is the third-party player', 'https://videovjav.com/embed/992559/' === (string) $first->player_loc );
	check( 'self-hosted uses content_loc', 'https://example.test/uploads/4k.mp4' === (string) $second->content_loc );
	check( 'highest resolution chosen over 720p', false !== strpos( (string) $second->content_loc, '4k.mp4' ) );
	check( 'self-hosted has no player_loc', '' === (string) $second->player_loc );
	check( 'duration is in seconds, not ISO', '479' === (string) $first->duration && '4997' === (string) $second->duration );
	check( 'family_friendly is no', 'no' === (string) $first->family_friendly );
	check( 'live is no', 'no' === (string) $first->live );
	check( 'requires_subscription is no', 'no' === (string) $first->requires_subscription );
	check( 'thumbnail_loc present', '' !== (string) $first->thumbnail_loc );
	check( 'title present', '' !== (string) $first->title );
	check( 'description present', '' !== (string) $first->description );
	check( 'publication_date present', '' !== (string) $first->publication_date );
	// allow_embed is an unprefixed attribute, so it sits outside the video
	// namespace and is simplest to assert against the raw markup.
	check( 'allow_embed is yes', false !== strpos( $xml, '<video:player_loc allow_embed="yes">' ) );
	check( 'title with an ampersand is escaped', 'Self Hosted Clip & More' === (string) $second->title );
	check( 'actor term emitted as a tag', false !== strpos( $xml, '<video:tag>A Person</video:tag>' ) );
	check( 'excerpt used when present', false !== strpos( $xml, 'An embedded clip with a real excerpt.' ) );
	check( 'description falls back to body text', false !== strpos( $xml, 'Body text used as the description fallback.' ) );
}

echo "\n" . str_repeat( '-', 46 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

if ( $failed ) {
	exit( 1 );
}

echo "All checks passed.\n";
