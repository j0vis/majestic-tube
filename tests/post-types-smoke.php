<?php
/**
 * Smoke test for the post types and taxonomies the theme registers.
 *
 * The data model is the original theme's: videos are ordinary posts, relabeled
 * as Videos, and actors are a non-hierarchical taxonomy on them. Everything
 * else in the theme - the cards, the single template, the actor archive, the
 * sitemap, the submission form - is written against that shape, so a change
 * here is a change to every surface at once, and most of it fails quietly: an
 * actor who stops being listed, a term count that starts including drafts, an
 * actor archive whose address moves out from under every indexed link.
 *
 * inc/post-types.php is included and its registration functions are called for
 * real, so what is asserted is the arguments WordPress is handed, recorded as
 * they are passed. Four claims worth pinning:
 *
 *   1. the actors taxonomy is registered on `post`, non-hierarchical, public
 *      and in the REST API, with the `actor` rewrite slug the archive and the
 *      permalinks are built on - and the post type itself is never registered
 *      here, because registering one would move videos out from under
 *      everything that reads them today;
 *   2. its labels are all translatable strings in the theme's own domain, and
 *      the ones core requires are present, so no admin screen shows a blank;
 *   3. calling the registration twice - a plugin or a child theme doing the
 *      same thing - is not an error and does not change the arguments;
 *   4. the admin relabel leaves a site alone when the post type is missing,
 *      survives the menu globals being unset, and rewrites only the entries it
 *      means to.
 *
 * Run:  php tests/post-types-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

$GLOBALS['taxonomies']    = array();
$GLOBALS['post_types']    = array();
$GLOBALS['registered']    = array();
$GLOBALS['actions']       = array();
$GLOBALS['translated']    = array();
$GLOBALS['objects']       = array();
$GLOBALS['menu']          = array();
$GLOBALS['submenu']       = array();

/* -------------------------------------------------------------------------
 * WordPress, in as much of it as this module touches.
 * ---------------------------------------------------------------------- */

/**
 * Record a translated string and answer it, the way core does.
 *
 * @param string $text   Text.
 * @param string $domain Text domain.
 * @return string
 */
function mt_translate( $text, $domain ) {
	$GLOBALS['translated'][] = $text . '|' . (string) $domain;

	return $text;
}

function __( $text, $domain = null ) {
	return mt_translate( $text, $domain );
}

function _x( $text, $context, $domain = null ) {
	$GLOBALS['contexts'][ $text ] = $context;

	return mt_translate( $text, $domain );
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['actions'][] = array(
		'hook'     => $hook,
		'callback' => $callback,
		'priority' => $priority,
	);
}

function register_taxonomy( $taxonomy, $object_type, $args = array() ) {
	$GLOBALS['taxonomies'][] = array(
		'taxonomy'    => $taxonomy,
		'object_type' => $object_type,
		'args'        => $args,
	);

	return (object) array( 'name' => $taxonomy );
}

// The module must not register a post type of its own; a call here would move
// videos off `post` and take the whole data model with them.
function register_post_type( $type, $args = array() ) {
	$GLOBALS['registered'][] = $type;

	return (object) array( 'name' => $type );
}

function post_type_exists( $type ) {
	return in_array( $type, $GLOBALS['post_types'], true );
}

function get_post_type_object( $type ) {
	return isset( $GLOBALS['objects'][ $type ] ) ? $GLOBALS['objects'][ $type ] : null;
}

require MAJESTIC_TUBE_DIR . '/inc/post-types.php';

$passed = 0;
$failed = 0;

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
 * The arguments of the most recent registration of one taxonomy.
 *
 * @param string $taxonomy Taxonomy name.
 * @return array
 */
function taxonomy_args( $taxonomy ) {
	foreach ( array_reverse( $GLOBALS['taxonomies'] ) as $call ) {
		if ( $call['taxonomy'] === $taxonomy ) {
			return $call['args'];
		}
	}

	return array();
}

/**
 * The registered object types of the most recent registration of one taxonomy.
 *
 * @param string $taxonomy Taxonomy name.
 * @return mixed
 */
function taxonomy_objects( $taxonomy ) {
	foreach ( array_reverse( $GLOBALS['taxonomies'] ) as $call ) {
		if ( $call['taxonomy'] === $taxonomy ) {
			return $call['object_type'];
		}
	}

	return null;
}

/**
 * Whether one hook is registered, at the priority it has to have.
 *
 * @param string $hook     Hook name.
 * @param string $callback Callback name.
 * @param int    $priority Expected priority.
 * @return bool
 */
function hooked( $hook, $callback, $priority = null ) {
	foreach ( $GLOBALS['actions'] as $action ) {
		if ( $action['hook'] === $hook && $action['callback'] === $callback ) {
			if ( null === $priority || $action['priority'] === $priority ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Every text domain a translated string was recorded with.
 *
 * @return string[]
 */
function translated_domains() {
	$domains = array();

	foreach ( $GLOBALS['translated'] as $entry ) {
		$domains[] = substr( $entry, strrpos( $entry, '|' ) + 1 );
	}

	return array_values( array_unique( $domains ) );
}

echo "\nMajestic Tube - post types and taxonomies\n";
echo str_repeat( '-', 46 ) . "\n";

echo "\nHooked where it has to be\n";

// Core registers `page`, `post` and `nav_menu` on init priority 0; registering
// the taxonomy in the same pass is what keeps a flushed rewrite rule set
// complete.
check( 'the taxonomy is registered on init at priority 0', hooked( 'init', 'majestic_tube_create_actors_taxonomy', 0 ) );
check( 'the admin relabel runs on admin_menu', hooked( 'admin_menu', 'majestic_tube_relabel_post_type' ) );
check( 'a theme switch registers it as well', hooked( 'after_switch_theme', 'majestic_tube_activate' ) );

echo "\nThe actors taxonomy\n";

majestic_tube_create_actors_taxonomy();

$args = taxonomy_args( 'actors' );

check(
	'registered exactly once, under the name the templates read',
	1 === count( $GLOBALS['taxonomies'] ) && 'actors' === $GLOBALS['taxonomies'][0]['taxonomy'],
	count( $GLOBALS['taxonomies'] ) . ' registration(s), first: ' . ( $GLOBALS['taxonomies'][0]['taxonomy'] ?? 'none' )
);
check( 'and on the post type videos are stored as', 'post' === taxonomy_objects( 'actors' ), var_export( taxonomy_objects( 'actors' ), true ) );
check( 'no post type is registered here', array() === $GLOBALS['registered'], implode( ', ', $GLOBALS['registered'] ) );

check( 'non-hierarchical, like the original', isset( $args['hierarchical'] ) && false === $args['hierarchical'] );
check( 'shown in the admin', ! empty( $args['show_ui'] ) );
check( 'with a column in the posts list', ! empty( $args['show_admin_column'] ) );
check( 'queryable by name', ! empty( $args['query_var'] ) );
check( 'available to the REST API, so the editor can set actors', ! empty( $args['show_in_rest'] ) );

// Non-hierarchical terms on posts count through this callback; without it the
// count beside an actor includes drafts, pending submissions and trash.
check( 'counts through the published-post callback', isset( $args['update_count_callback'] ) && '_update_post_term_count' === $args['update_count_callback'], var_export( $args['update_count_callback'] ?? null, true ) );

// The archive lives at /actor/<name>/, and `with_front` has to stay false or
// the site's permalink prefix would be inserted in front of it.
check( 'rewrites to the slug the archive is built on', isset( $args['rewrite']['slug'] ) && 'actor' === $args['rewrite']['slug'], var_export( $args['rewrite'] ?? null, true ) );
check( 'and does not take the permalink front', isset( $args['rewrite']['with_front'] ) && false === $args['rewrite']['with_front'] );

echo "\nLabels a translator can work with\n";

$labels = isset( $args['labels'] ) ? $args['labels'] : array();

$required = array(
	'name',
	'singular_name',
	'search_items',
	'popular_items',
	'all_items',
	'parent_item',
	'parent_item_colon',
	'edit_item',
	'update_item',
	'add_new_item',
	'new_item_name',
	'separate_items_with_commas',
	'add_or_remove_items',
	'choose_from_most_used',
	'not_found',
	'menu_name',
);

$missing = array();

foreach ( $required as $key ) {
	if ( ! array_key_exists( $key, $labels ) ) {
		$missing[] = $key;
	}
}

check( 'every label core reads is present', ! $missing, 'missing: ' . implode( ', ', $missing ) );

// A non-hierarchical taxonomy passes null for the two parent labels on purpose;
// a string there would put a "Parent" field on a screen that has no parents.
// array_key_exists(), not isset(): the value being present and null is the
// property being pinned, and isset() answers false for exactly that.
check(
	'the parent labels are present and null, as a non-hierarchical taxonomy wants',
	array_key_exists( 'parent_item', $labels ) && null === $labels['parent_item']
		&& array_key_exists( 'parent_item_colon', $labels ) && null === $labels['parent_item_colon'],
	var_export( array( $labels['parent_item'] ?? 'absent', $labels['parent_item_colon'] ?? 'absent' ), true )
);
check( 'the menu entry names the taxonomy', isset( $labels['menu_name'] ) && 'Actors' === $labels['menu_name'] );
check( 'the singular label is the singular one', isset( $labels['singular_name'] ) && 'Actor' === $labels['singular_name'] );

check(
	'every label was translated through the theme domain',
	array( 'majestic-tube' ) === translated_domains(),
	implode( ', ', translated_domains() )
);

check( 'the name carries a translator context', isset( $GLOBALS['contexts']['Actors'] ) && '' !== $GLOBALS['contexts']['Actors'], var_export( $GLOBALS['contexts']['Actors'] ?? null, true ) );
check( 'so does the singular name', isset( $GLOBALS['contexts']['Actor'] ) );

echo "\nRegistering twice is not an error\n";

$before = count( $GLOBALS['taxonomies'] );

$threw = '';

try {
	majestic_tube_create_actors_taxonomy();
} catch ( Throwable $e ) {
	$threw = $e->getMessage();
}

check( 'a second registration does not fatal', '' === $threw, $threw );
check( 'and registers the same taxonomy again', count( $GLOBALS['taxonomies'] ) === $before + 1 );
check( 'with the same arguments', taxonomy_args( 'actors' ) == $args, 'the second call disagreed with the first' );

echo "\nThe theme switch path\n";

majestic_tube_activate();
check( 'activating registers the taxonomy directly', count( $GLOBALS['taxonomies'] ) === $before + 2 );

echo "\nThe admin relabel\n";

$GLOBALS['translated'] = array();
$GLOBALS['objects']    = array();

// No post type to relabel: the function has to leave without touching labels.
majestic_tube_relabel_post_type();

check( 'a site without the post type is left alone', array() === $GLOBALS['translated'], implode( ', ', $GLOBALS['translated'] ) );

$labels_object = (object) array(
	'name'    => 'Posts',
	'all_items' => 'All Posts',
	'menu_name' => 'Posts',
);

$GLOBALS['objects']['post'] = (object) array( 'labels' => $labels_object );
$GLOBALS['post_types']      = array( 'post' );
$GLOBALS['menu']            = array( 5 => array( 'Posts' ), 20 => array( 'Pages' ) );
$GLOBALS['submenu']         = array(
	'edit.php' => array(
		5  => array( 'All Posts' ),
		10 => array( 'Add Post' ),
		15 => array( 'Categories' ),
		16 => array( 'Tags' ),
		20 => array( 'Something Else' ),
	),
);

majestic_tube_relabel_post_type();

check( 'the post type is named Videos', 'Videos' === $labels_object->name );
check( 'the menu entry is renamed', 'Videos' === $GLOBALS['menu'][5][0], var_export( $GLOBALS['menu'][5][0] ?? null, true ) );
check( 'the all-items entry is renamed', 'All Videos' === $labels_object->all_items );
check( 'the add-new entry is renamed', 'Add Video' === $labels_object->add_new );
check( 'the category submenu entry is renamed', 'Video Categories' === $GLOBALS['submenu']['edit.php'][15][0], var_export( $GLOBALS['submenu']['edit.php'][15][0] ?? null, true ) );
check( 'the tag submenu entry is renamed', 'Video Tags' === $GLOBALS['submenu']['edit.php'][16][0] );
check( 'a submenu entry it does not own is untouched', 'Something Else' === $GLOBALS['submenu']['edit.php'][20][0] );
check( 'another top-level menu is untouched', 'Pages' === $GLOBALS['menu'][20][0] );
check( 'the new labels are translated in the theme domain', array( 'majestic-tube' ) === translated_domains(), implode( ', ', translated_domains() ) );

echo "\nThe menu globals may not be there\n";

// wp-admin is not the only caller of admin_menu, and the two globals are not
// guaranteed to be arrays by the time this runs.
$GLOBALS['menu']    = array();
$GLOBALS['submenu'] = array();

$threw = '';

try {
	majestic_tube_relabel_post_type();
} catch ( Throwable $e ) {
	$threw = $e->getMessage();
}

check( 'empty menus do not fatal', '' === $threw, $threw );

unset( $GLOBALS['menu'], $GLOBALS['submenu'] );

$threw = '';

try {
	majestic_tube_relabel_post_type();
} catch ( Throwable $e ) {
	$threw = $e->getMessage();
}

check( 'unset menus do not fatal either', '' === $threw, $threw );
check( 'and the labels are still relabeled', 'Videos' === $labels_object->name );

echo "\n" . str_repeat( '-', 46 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

if ( $failed ) {
	exit( 1 );
}

echo "All checks passed.\n";
