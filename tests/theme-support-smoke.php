<?php
/**
 * Smoke test for inc/theme-support.php.
 *
 * The module declares what the theme supports and, more importantly, what
 * WordPress is asked to generate. Both halves are pinned here, because both are
 * the kind of thing an unrelated edit removes by accident:
 *
 *   1. theme support, the editor stylesheet list, the nav menu locations and the
 *      text domain bound to languages/, since a missing one is a silent loss of
 *      a feature rather than an error;
 *   2. the featured image sizes. One registration per dimension is the whole
 *      point of the most recent change to this file: the original theme's
 *      wpst_thumb_* names used to be registered beside these at identical
 *      dimensions, which made WordPress resize and re-encode every upload twice
 *      for a file it had already written. A re-added duplicate fails here;
 *   3. the aliases those names resolve through instead. Each one has to point at
 *      a size that is really registered - an alias pointing at nothing is a
 *      silent fallback to the full-size upload - and an alias that is *also*
 *      registered is the duplicate the change removed;
 *   4. what the alias filter does to metadata: it copies an entry that exists,
 *      adds nothing for one that does not, never overwrites a name that is
 *      already there, and leaves anything that is not a sizes map alone;
 *   5. the body classes, the plugin-active check, the mobile wrapper and the
 *      pingback header.
 *
 * inc/theme-support.php is included, so the code under test is the code that
 * ships. What it is not: a real WordPress. The size registry is a stub that
 * records what the module handed over, and the resolution itself is not
 * re-implemented - WordPress looks a size up in `$metadata['sizes'][ $size ]`
 * and nowhere else, so what is pinned is the map it reads, not core's lookup.
 * The aliases are asserted against the registry built in the same run, so the
 * check means "the name this alias points at was registered" and not "it was
 * registered somewhere".
 *
 * Run:  php tests/theme-support-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

$GLOBALS['passed'] = 0;
$GLOBALS['failed'] = 0;

/* -------------------------------------------------------------------------
 * State the recorders collect.
 * ---------------------------------------------------------------------- */

$GLOBALS['theme_support'] = array(); // feature => args.
$GLOBALS['editor_styles'] = array(); // Every add_editor_style() argument set.
$GLOBALS['nav_menus']     = array(); // location => label.
$GLOBALS['textdomains']   = array(); // domain => path.
$GLOBALS['domains']       = array(); // Every translation domain the module asks for.
$GLOBALS['sizes']         = array(); // Registered size name => array( width, height, crop ).
$GLOBALS['size_calls']    = 0;       // Every registration call, counted separately.
$GLOBALS['hooks']         = array(); // Kind, tag, callback, priority, accepted args.
$GLOBALS['test_filters']  = array(); // Callbacks this suite adds, tag => callbacks.

// Switches the stubs read.
$GLOBALS['multi_author'] = false;
$GLOBALS['singular']     = false;
$GLOBALS['icons']        = true;
$GLOBALS['mobile']       = false;
$GLOBALS['pings']        = false;
$GLOBALS['opts']         = array(); // wpst-options, as majestic_tube_get_option reads them.
$GLOBALS['active']       = array();
$GLOBALS['site_wide']    = array();
$GLOBALS['multisite']    = false;

/* -------------------------------------------------------------------------
 * Core stubs.
 * ---------------------------------------------------------------------- */

/**
 * Record a declared theme support.
 *
 * @param string $feature Feature name.
 * @param array  $args    Feature arguments.
 * @return void
 */
function add_theme_support( $feature, $args = array() ) {
	$GLOBALS['theme_support'][ $feature ] = $args;
}

/**
 * Record an editor stylesheet list.
 *
 * @param array|string $styles Stylesheets.
 * @return void
 */
function add_editor_style( $styles ) {
	$GLOBALS['editor_styles'][] = $styles;
}

/**
 * Record registered nav menu locations.
 *
 * @param array $locations Location => label.
 * @return void
 */
function register_nav_menus( $locations ) {
	$GLOBALS['nav_menus'] = array_merge( $GLOBALS['nav_menus'], (array) $locations );
}

/**
 * Record a bound text domain.
 *
 * @param string $domain Text domain.
 * @param string $path   Directory the domain is bound to.
 * @return void
 */
function load_theme_textdomain( $domain, $path = '' ) {
	$GLOBALS['textdomains'][ $domain ] = $path;
}

/**
 * Register the featured image size.
 *
 * WordPress stores this one under the name `post-thumbnail`, which is what core
 * and third-party code ask for, so the recorder files it under that name, in the
 * same registry add_image_size() writes to. That is what makes "every alias
 * points at a registered size" a real check rather than a list of the module's
 * own add_image_size() calls.
 *
 * @param int  $width  Width.
 * @param int  $height Height.
 * @param bool $crop   Whether to crop.
 * @return void
 */
function set_post_thumbnail_size( $width = 0, $height = 0, $crop = false ) {
	++$GLOBALS['size_calls'];

	$GLOBALS['sizes']['post-thumbnail'] = array(
		'width'  => (int) $width,
		'height' => (int) $height,
		'crop'   => (bool) $crop,
	);
}

/**
 * Record a registered image size.
 *
 * @param string $name   Size name.
 * @param int    $width  Width.
 * @param int    $height Height.
 * @param bool   $crop   Whether to crop.
 * @return void
 */
function add_image_size( $name, $width = 0, $height = 0, $crop = false ) {
	++$GLOBALS['size_calls'];

	$GLOBALS['sizes'][ (string) $name ] = array(
		'width'  => (int) $width,
		'height' => (int) $height,
		'crop'   => (bool) $crop,
	);
}

/**
 * Record a hook.
 *
 * @param string $kind     `action` or `filter`.
 * @param string $tag      Hook name.
 * @param mixed  $callback Callback.
 * @param int    $priority Priority.
 * @param int    $accepted Accepted argument count.
 * @return void
 */
function mt_record_hook( $kind, $tag, $callback, $priority, $accepted ) {
	$GLOBALS['hooks'][] = array(
		'kind'     => $kind,
		'tag'      => $tag,
		'callback' => $callback,
		'priority' => (int) $priority,
		'accepted' => (int) $accepted,
	);
}

/**
 * Record an action.
 *
 * @param string $tag      Hook name.
 * @param mixed  $callback Callback.
 * @param int    $priority Priority.
 * @param int    $accepted Accepted argument count.
 * @return void
 */
function add_action( $tag, $callback, $priority = 10, $accepted = 1 ) {
	mt_record_hook( 'action', $tag, $callback, $priority, $accepted );
}

/**
 * Record a filter.
 *
 * @param string $tag      Hook name.
 * @param mixed  $callback Callback.
 * @param int    $priority Priority.
 * @param int    $accepted Accepted argument count.
 * @return void
 */
function add_filter( $tag, $callback, $priority = 10, $accepted = 1 ) {
	mt_record_hook( 'filter', $tag, $callback, $priority, $accepted );
}

/**
 * Run the callbacks this suite added for a tag.
 *
 * Only the callbacks the suite registers are run: the module's own hook
 * registrations are recorded, not executed, so a value can be passed through the
 * filter once and observed once. One argument, which is the arity of the filter
 * under test.
 *
 * @param string $tag   Hook name.
 * @param mixed  $value Value being filtered.
 * @return mixed
 */
function apply_filters( $tag, $value ) {
	foreach ( (array) ( isset( $GLOBALS['test_filters'][ $tag ] ) ? $GLOBALS['test_filters'][ $tag ] : array() ) as $callback ) {
		$value = call_user_func( $callback, $value );
	}

	return $value;
}

/**
 * Translation stub that records the domain it was asked for.
 *
 * @param string $text   Text.
 * @param string $domain Text domain.
 * @return string
 */
function __( $text, $domain = '' ) {
	$GLOBALS['domains'][] = $domain;

	return $text;
}

/**
 * Escape a URL the way core does for an attribute.
 *
 * @param string $url URL.
 * @return string
 */
function esc_url( $url ) {
	return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
}

/**
 * Whether more than one author has published.
 *
 * @return bool
 */
function is_multi_author() {
	return (bool) $GLOBALS['multi_author'];
}

/**
 * Whether the request is for a single post.
 *
 * @return bool
 */
function is_singular() {
	return (bool) $GLOBALS['singular'];
}

/**
 * Whether pings are open on the current post.
 *
 * @return bool
 */
function pings_open() {
	return (bool) $GLOBALS['pings'];
}

/**
 * A bloginfo value, with a query string so escaping can be observed.
 *
 * @param string $name   Field name.
 * @param string $filter Filter.
 * @return string
 */
function get_bloginfo( $name = '', $filter = '' ) {
	return 'https://example.test/pingback.php?id=7&t=2';
}

/**
 * Whether the install is multisite.
 *
 * @return bool
 */
function is_multisite() {
	return (bool) $GLOBALS['multisite'];
}

/**
 * An option.
 *
 * @param string $name    Option name.
 * @param mixed  $default Default.
 * @return mixed
 */
function get_option( $name, $default = false ) {
	if ( 'active_plugins' === $name ) {
		return $GLOBALS['active'];
	}

	return isset( $GLOBALS['options'][ $name ] ) ? $GLOBALS['options'][ $name ] : $default;
}

/**
 * A network option.
 *
 * @param string $name    Option name.
 * @param mixed  $default Default.
 * @return mixed
 */
function get_site_option( $name, $default = false ) {
	return 'active_sitewide_plugins' === $name ? $GLOBALS['site_wide'] : $default;
}

/**
 * Whether the request is a mobile one.
 *
 * @return bool
 */
function wp_is_mobile() {
	return (bool) $GLOBALS['mobile'];
}

/**
 * The theme's icon switch, owned by inc/icons.php.
 *
 * @return bool
 */
function majestic_tube_icons_enabled() {
	return (bool) $GLOBALS['icons'];
}

/**
 * The theme's option reader, owned by inc/theme-options.php.
 *
 * @param string $group   Option group.
 * @param string $key     Key.
 * @param mixed  $default Default.
 * @return mixed
 */
function majestic_tube_get_option( $group, $key, $default = '' ) {
	return isset( $GLOBALS['opts'][ $key ] ) ? $GLOBALS['opts'][ $key ] : $default;
}

/* -------------------------------------------------------------------------
 * The module under test.
 * ---------------------------------------------------------------------- */

require_once MAJESTIC_TUBE_DIR . '/inc/theme-support.php';

$passed = 0;
$failed = 0;

/**
 * Record one check.
 *
 * @param string $label   What is being asserted.
 * @param bool   $ok      Whether it held.
 * @param string $context Shown when it did not.
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

/**
 * Capture the output of a callback.
 *
 * @param callable $callback Callback.
 * @return string
 */
function capture( $callback ) {
	ob_start();
	call_user_func( $callback );

	return ob_get_clean();
}

/**
 * The hooks the module registered, as `kind tag callback` strings.
 *
 * @param string $tag Hook name.
 * @return array
 */
function hooks_for( $tag ) {
	$found = array();

	foreach ( $GLOBALS['hooks'] as $hook ) {
		if ( $hook['tag'] === $tag ) {
			$found[] = $hook;
		}
	}

	return $found;
}

/**
 * One hook by tag and callback.
 *
 * A hook that was never registered comes back as an empty record rather than
 * null, so a missing registration fails a check instead of raising a warning
 * about reading an offset off null.
 *
 * @param string $tag      Hook name.
 * @param string $callback Callback name.
 * @return array
 */
function hook( $tag, $callback ) {
	foreach ( $GLOBALS['hooks'] as $recorded ) {
		if ( $recorded['tag'] === $tag && $recorded['callback'] === $callback ) {
			return $recorded;
		}
	}

	return array(
		'kind'     => '',
		'tag'      => $tag,
		'callback' => $callback,
		'priority' => 0,
		'accepted' => 0,
	);
}

/* -------------------------------------------------------------------------
 * The setup callback. Everything below reads one run of it.
 * ---------------------------------------------------------------------- */

echo "\nWhat the module hooks\n";

check( 'the setup callback runs on after_setup_theme', 'action' === hook( 'after_setup_theme', 'majestic_tube_setup' )['kind'] );

/*
 * Both ends of the metadata's life, at the default priority. The write end
 * covers a new upload; the read end covers a record written somewhere else, and
 * it takes one argument, which is what the filter passes as its first.
 */
$generated = hook( 'wp_generate_attachment_metadata', 'majestic_tube_alias_image_sizes' );
$read      = hook( 'wp_get_attachment_metadata', 'majestic_tube_alias_image_sizes' );

check(
	'the metadata is filtered when it is generated',
	'filter' === $generated['kind'] && 10 === $generated['priority'] && 1 === $generated['accepted'],
	'got: ' . implode( '/', array( $generated['kind'], $generated['priority'], $generated['accepted'] ) )
);

check(
	'and again every time it is read',
	'filter' === $read['kind'] && 10 === $read['priority'] && 1 === $read['accepted'],
	'got: ' . implode( '/', array( $read['kind'], $read['priority'], $read['accepted'] ) )
);

check(
	'both body class filters are registered',
	2 === count( hooks_for( 'body_class' ) )
		&& 'majestic_tube_body_classes' === hooks_for( 'body_class' )[0]['callback']
		&& 'majestic_tube_legacy_body_classes' === hooks_for( 'body_class' )[1]['callback']
);

check( 'the pingback header is printed from wp_head', 'action' === hook( 'wp_head', 'majestic_tube_pingback_header' )['kind'] );

$tags = array();

foreach ( $GLOBALS['hooks'] as $recorded ) {
	$tags[] = $recorded['kind'] . ':' . $recorded['tag'];
}

check(
	'and nothing else is hooked, on load',
	array(
		'action:after_setup_theme',
		'filter:wp_generate_attachment_metadata',
		'filter:wp_get_attachment_metadata',
		'filter:body_class',
		'filter:body_class',
		'action:wp_head',
	) === $tags,
	implode( ', ', $tags )
);

echo "\nTheme support\n";

majestic_tube_setup();

$support = $GLOBALS['theme_support'];

check( 'post thumbnails are supported', array_key_exists( 'post-thumbnails', $support ) );
check( 'the document title is left to core', array_key_exists( 'title-tag', $support ) );
check( 'so are the feed links', array_key_exists( 'automatic-feed-links', $support ) );
check( 'the video post format is declared', array( 'video' ) === $support['post-formats'] );

check(
	'HTML5 markup is declared for the forms and the comment list',
	array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ) === $support['html5'],
	'got: ' . implode( ', ', (array) $support['html5'] )
);

check(
	'the html5 list carries no script or style key',
	! in_array( 'script', (array) $support['html5'], true ) && ! in_array( 'style', (array) $support['html5'], true ),
	'core deprecated both and always emits HTML5-style tags'
);

check(
	'the custom logo is 250 square but free in both directions',
	array( 'height' => 250, 'width' => 250, 'flex-width' => true, 'flex-height' => true ) === $support['custom-logo'],
	'got: ' . var_export( $support['custom-logo'], true )
);

check( 'widget selective refresh is on', array_key_exists( 'customize-selective-refresh-widgets', $support ) );
check( 'wide alignment is on', array_key_exists( 'align-wide', $support ) );
check( 'responsive embeds are on', array_key_exists( 'responsive-embeds', $support ) );
check( 'editor styles are declared', array_key_exists( 'editor-styles', $support ) );

check(
	'the font faces are listed before the editor stylesheet',
	array( array( 'assets/css/fonts.css', 'assets/css/editor.css' ) ) === $GLOBALS['editor_styles'],
	'got: ' . var_export( $GLOBALS['editor_styles'], true )
);

check(
	'the three menu locations are registered, in the order the customizer lists them',
	array( 'majestic_tube_main_menu', 'majestic_tube_footer_menu', 'majestic_tube_links_menu' ) === array_keys( $GLOBALS['nav_menus'] ),
	'got: ' . implode( ', ', array_keys( $GLOBALS['nav_menus'] ) )
);

check(
	'and every label is translated in the theme domain',
	array( 'Main Menu', 'Footer Menu', 'Friends & Ad Links' ) === array_values( $GLOBALS['nav_menus'] )
		&& $GLOBALS['nav_menus']['majestic_tube_links_menu'] === 'Friends & Ad Links'
);

check(
	'no label was asked for in another domain',
	array( 'majestic-tube', 'majestic-tube', 'majestic-tube' ) === $GLOBALS['domains'],
	'got: ' . implode( ', ', $GLOBALS['domains'] )
);

check(
	'the text domain is bound to the language folder',
	MAJESTIC_TUBE_DIR . '/languages' === $GLOBALS['textdomains']['majestic-tube']
		&& 'majestic-tube' === key( $GLOBALS['textdomains'] ),
	'got: ' . var_export( $GLOBALS['textdomains'], true )
);

check(
	'and the content width is only defaulted, not overwritten',
	1200 === $GLOBALS['content_width'],
	'got: ' . var_export( isset( $GLOBALS['content_width'] ) ? $GLOBALS['content_width'] : null, true )
);

$GLOBALS['content_width'] = 999;
majestic_tube_setup();
check( 'a content width that was already set is left alone', 999 === $GLOBALS['content_width'] );
unset( $GLOBALS['content_width'] );

echo "\nOne registration per image file\n";

$sizes = $GLOBALS['sizes'];

check(
	'five sizes are registered in all',
	5 === count( $sizes ),
	'got: ' . implode( ', ', array_keys( $sizes ) )
);

check(
	'the four the module adds are named exactly once',
	array( 'majestic-tube-thumb-large', 'majestic-tube-thumb-small', 'majestic-tube-thumb-card', 'majestic-tube-poster' ) === array_values( array_diff( array_keys( $sizes ), array( 'post-thumbnail' ) ) ),
	'got: ' . implode( ', ', array_keys( $sizes ) )
);

check(
	'the featured image size is 320x180 and cropped',
	array( 'width' => 320, 'height' => 180, 'crop' => true ) === $sizes['post-thumbnail'],
	var_export( isset( $sizes['post-thumbnail'] ) ? $sizes['post-thumbnail'] : null, true )
);

check(
	'each registered size keeps the original dimensions',
	array( 'width' => 640, 'height' => 360, 'crop' => true ) === $sizes['majestic-tube-thumb-large']
		&& array( 'width' => 150, 'height' => 84, 'crop' => true ) === $sizes['majestic-tube-thumb-small']
		&& array( 'width' => 480, 'height' => 270, 'crop' => true ) === $sizes['majestic-tube-thumb-card']
		&& array( 'width' => 1280, 'height' => 720, 'crop' => true ) === $sizes['majestic-tube-poster']
);

/*
 * The heart of the change: a registered size is a promise to generate a file, so
 * two names for one dimension pair is the same file written twice. This is the
 * check that fails if one of the legacy registrations comes back.
 */
$dimensions = array();

foreach ( $sizes as $name => $size ) {
	$key = $size['width'] . 'x' . $size['height'];

	if ( ! isset( $dimensions[ $key ] ) ) {
		$dimensions[ $key ] = array();
	}

	$dimensions[ $key ][] = $name;
}

$duplicated = array();

foreach ( $dimensions as $key => $names ) {
	if ( count( $names ) > 1 ) {
		$duplicated[] = $key . ' (' . implode( ' + ', $names ) . ')';
	}
}

check(
	'no two registered sizes are the same file',
	array() === $duplicated,
	'these dimensions are generated twice: ' . implode( ', ', $duplicated )
);

check(
	'the original theme names are not registered at all',
	! isset( $sizes['wpst_thumb_large'] ) && ! isset( $sizes['wpst_thumb_medium'] ) && ! isset( $sizes['wpst_thumb_small'] ),
	'they resolve from the metadata instead'
);

check(
	'and neither is the theme name for the featured size',
	! isset( $sizes['majestic-tube-thumb-medium'] ),
	'post-thumbnail is the registration that makes that file'
);

/*
 * The same claim counted from the other side: five registration calls for five
 * files. Registering one name twice would not show up in the map above, but it
 * is a second resize of the same file on every upload, so it is counted here.
 */
$GLOBALS['sizes']      = array();
$GLOBALS['size_calls'] = 0;

majestic_tube_setup();

check(
	'one call per file, five calls in all',
	5 === $GLOBALS['size_calls'] && 5 === count( $GLOBALS['sizes'] ),
	'got: ' . $GLOBALS['size_calls'] . ' calls for ' . count( $GLOBALS['sizes'] ) . ' names'
);

echo "\nThe aliases point at something\n";

$aliases = majestic_tube_image_size_aliases();

check(
	'every original theme name has an alias',
	array( 'wpst_thumb_large', 'wpst_thumb_small', 'wpst_thumb_medium', 'majestic-tube-thumb-medium' ) === array_keys( $aliases ),
	'got: ' . implode( ', ', array_keys( $aliases ) )
);

$dangling = array();

foreach ( $aliases as $alias => $source ) {
	if ( ! isset( $sizes[ $source ] ) ) {
		$dangling[] = $alias . ' => ' . $source;
	}

	if ( isset( $sizes[ $alias ] ) ) {
		$dangling[] = $alias . ' is also registered';
	}
}

check(
	'each one points at a size this module registered, and is not registered itself',
	array() === $dangling,
	implode( ', ', $dangling )
);

check(
	'the 320x180 names resolve through the featured image size',
	'post-thumbnail' === $aliases['wpst_thumb_medium'] && 'post-thumbnail' === $aliases['majestic-tube-thumb-medium']
);

check(
	'the large and small names resolve to their own file',
	'majestic-tube-thumb-large' === $aliases['wpst_thumb_large'] && 'majestic-tube-thumb-small' === $aliases['wpst_thumb_small']
);

/*
 * WordPress resolves a size name in the metadata and nowhere else, so an image
 * smaller than the requested size has no entry and falls back to the original
 * upload - exactly what an ungenerated registered size does. Nothing here
 * re-implements that lookup; it pins the map core reads.
 */
/* One record as a new upload writes it: the sizes the module registers, and
   nothing else - the aliases are what the filter is here to add. */
$thumbnails = array(
	'sizes' => array(
		'post-thumbnail'            => array( 'file' => 'clip-320x180.jpg', 'width' => 320, 'height' => 180, 'mime-type' => 'image/jpeg' ),
		'majestic-tube-thumb-large' => array( 'file' => 'clip-640x360.jpg', 'width' => 640, 'height' => 360, 'mime-type' => 'image/jpeg' ),
		'majestic-tube-thumb-small' => array( 'file' => 'clip-150x84.jpg', 'width' => 150, 'height' => 84, 'mime-type' => 'image/jpeg' ),
	),
);

$aliased = majestic_tube_alias_image_sizes( $thumbnails );

check(
	'the metadata ends up naming every size the theme can ask for',
	array( 'post-thumbnail', 'majestic-tube-thumb-large', 'majestic-tube-thumb-small', 'wpst_thumb_large', 'wpst_thumb_small', 'wpst_thumb_medium', 'majestic-tube-thumb-medium' ) === array_keys( $aliased['sizes'] ),
	'got: ' . implode( ', ', array_keys( $aliased['sizes'] ) )
);

check(
	'and each alias is a copy of the entry that made the file',
	$aliased['sizes']['wpst_thumb_large'] === $thumbnails['sizes']['majestic-tube-thumb-large']
		&& $aliased['sizes']['wpst_thumb_small'] === $thumbnails['sizes']['majestic-tube-thumb-small']
		&& $aliased['sizes']['wpst_thumb_medium'] === $thumbnails['sizes']['post-thumbnail']
		&& $aliased['sizes']['majestic-tube-thumb-medium'] === $thumbnails['sizes']['post-thumbnail'],
	'both names have to answer with the same file'
);

check(
	'the 320 names resolve to one file, not to two copies of it',
	$aliased['sizes']['wpst_thumb_medium']['file'] === $aliased['sizes']['post-thumbnail']['file']
		&& $aliased['sizes']['majestic-tube-thumb-medium']['file'] === $aliased['sizes']['post-thumbnail']['file']
);

$no_file = majestic_tube_alias_image_sizes(
	array(
		'sizes' => array(
			'post-thumbnail' => array( 'file' => 'clip-320x180.jpg', 'width' => 320, 'height' => 180 ),
		),
	)
);

check(
	'a size that was never generated gets no alias',
	! isset( $no_file['sizes']['wpst_thumb_large'] ) && ! isset( $no_file['sizes']['wpst_thumb_small'] ),
	'an alias pointing at nothing would resolve to the full-size upload'
);

$already = majestic_tube_alias_image_sizes(
	array(
		'sizes' => array(
			'post-thumbnail' => array( 'file' => 'clip-320x180.jpg' ),
			'wpst_thumb_medium' => array( 'file' => 'older-crop.jpg' ),
		),
	)
);

check(
	'an entry that is already there is never overwritten',
	'older-crop.jpg' === $already['sizes']['wpst_thumb_medium']['file'],
	'a library uploaded by the original theme keeps the record it already has'
);

check(
	'a name that is already there is still added for the others',
	isset( $already['sizes']['majestic-tube-thumb-medium'] )
);

$untouched = array( 'file' => 'clip.jpg', 'width' => 1920, 'height' => 1080 );

check(
	'metadata with no sizes map comes back unchanged',
	$untouched === majestic_tube_alias_image_sizes( $untouched )
);

check(
	'a sizes key that is not an array comes back unchanged',
	array( 'sizes' => 'nonsense' ) === majestic_tube_alias_image_sizes( array( 'sizes' => 'nonsense' ) )
);

check(
	'an empty sizes map is left empty',
	array( 'sizes' => array() ) === majestic_tube_alias_image_sizes( array( 'sizes' => array() ) )
);

check(
	'and a false or missing record is passed straight through',
	false === majestic_tube_alias_image_sizes( false ) && null === majestic_tube_alias_image_sizes( null )
);

check(
	'nothing is added for a record carrying only unrelated sizes',
	array( 'sizes' => array( 'thumbnail' => array( 'file' => 'clip-150x150.jpg' ) ) ) === majestic_tube_alias_image_sizes( array( 'sizes' => array( 'thumbnail' => array( 'file' => 'clip-150x150.jpg' ) ) ) )
);

echo "\nA site can extend the aliases\n";

$GLOBALS['test_filters']['majestic_tube_image_size_aliases'] = array(
	/**
	 * Add a size name of our own.
	 *
	 * @param array $aliases Aliases.
	 * @return array
	 */
	function ( $aliases ) {
		$aliases['legacy_portrait'] = 'majestic-tube-thumb-card';

		return $aliases;
	},
);

$extended = majestic_tube_image_size_aliases();

check(
	'the filter can add a name',
	isset( $extended['legacy_portrait'] ) && 5 === count( $extended ),
	'got: ' . implode( ', ', array_keys( $extended ) )
);

check(
	'and the added name follows the same rules',
	'clip-480x270.jpg' === majestic_tube_alias_image_sizes(
		array( 'sizes' => array( 'majestic-tube-thumb-card' => array( 'file' => 'clip-480x270.jpg' ) ) )
	)['sizes']['legacy_portrait']['file']
);

unset( $GLOBALS['test_filters']['majestic_tube_image_size_aliases'] );

echo "\nBody classes\n";

$GLOBALS['multi_author'] = false;
$GLOBALS['singular']     = true;
$GLOBALS['icons']        = true;

check(
	'a single-author single post adds no class of its own',
	array() === majestic_tube_body_classes( array() ),
	'got: ' . var_export( majestic_tube_body_classes( array() ), true )
);

$GLOBALS['multi_author'] = true;
$GLOBALS['singular']     = false;

$classes = majestic_tube_body_classes( array() );

check( 'a multi-author site is marked group-blog', in_array( 'group-blog', $classes, true ) );
check( 'anything that is not a single post is an hfeed', in_array( 'hfeed', $classes, true ) );

$GLOBALS['icons'] = false;

check( 'and the icon switch rides on the body', in_array( 'no-icons', majestic_tube_body_classes( array() ), true ) );

$GLOBALS['icons']        = true;
$GLOBALS['multi_author'] = false;

check(
	'the classes are appended, not replaced',
	array( 'existing', 'hfeed' ) === majestic_tube_body_classes( array( 'existing' ) ),
	'the callback has to return the list it was handed'
);

$GLOBALS['opts'] = array(
	'custom-background'        => 'on',
	'videos-per-row-mobile'    => 1,
);
$GLOBALS['mobile'] = true;

check(
	'the original theme classes still come from wpst-options',
	array( 'custom-background', 'wpst-mobile', 'wpst-one-per-row-mobile' ) === majestic_tube_legacy_body_classes( array() ),
	'got: ' . implode( ', ', majestic_tube_legacy_body_classes( array() ) )
);

$GLOBALS['mobile'] = false;
$GLOBALS['opts']   = array();

check(
	'a desktop viewer is told so, and the other options stay at their defaults',
	array( 'wpst-desktop' ) === majestic_tube_legacy_body_classes( array() ),
	'got: ' . implode( ', ', majestic_tube_legacy_body_classes( array() ) )
);

echo "\nPlugin detection, mobile and pingbacks\n";

$GLOBALS['active']    = array( 'akismet/akismet.php' );
$GLOBALS['multisite'] = false;

check( 'an active plugin is found in the option', majestic_tube_is_plugin_active( 'akismet/akismet.php' ) );
check( 'an inactive one is not', ! majestic_tube_is_plugin_active( 'other/other.php' ) );

$GLOBALS['multisite'] = true;
$GLOBALS['site_wide'] = array( 'network/network.php' => time() );

check( 'a network-activated plugin counts too', majestic_tube_is_plugin_active( 'network/network.php' ) );
check( 'and one that is only in the local list still counts', majestic_tube_is_plugin_active( 'akismet/akismet.php' ) );
check( 'an inactive plugin is not reported active on multisite either', ! majestic_tube_is_plugin_active( 'other/other.php' ) );

$GLOBALS['multisite'] = false;
$GLOBALS['site_wide'] = array();

$GLOBALS['mobile'] = true;

check( 'the mobile wrapper answers core', majestic_tube_is_mobile() );
check( 'and the compatibility name delegates to it', function_exists( 'wpst_is_mobile' ) && wpst_is_mobile() );

$GLOBALS['mobile'] = false;

check( 'both report desktop', ! majestic_tube_is_mobile() && ! wpst_is_mobile() );

$GLOBALS['singular'] = true;
$GLOBALS['pings']    = true;

$printed = capture( 'majestic_tube_pingback_header' );

check(
	'a post with pings open prints the pingback link',
	'<link rel="pingback" href="https://example.test/pingback.php?id=7&amp;t=2">' === $printed,
	'got: ' . $printed
);

check(
	'and the URL is escaped rather than printed raw',
	false === strpos( $printed, '?' ) || false === strpos( $printed, '&t=' ),
	'a bare ampersand in an attribute is invalid markup'
);

$GLOBALS['pings'] = false;

check( 'pings closed prints nothing', '' === capture( 'majestic_tube_pingback_header' ) );

$GLOBALS['singular'] = false;
$GLOBALS['pings']    = true;

check( 'and so does a page that is not a single post', '' === capture( 'majestic_tube_pingback_header' ) );

echo "\n" . str_repeat( '-', 46 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

if ( $failed ) {
	exit( 1 );
}

echo "All checks passed.\n";
