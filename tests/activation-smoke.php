<?php
/**
 * Smoke test for the theme activation routine.
 *
 * Installation is the one path nobody re-tests by hand: a fresh site, pages
 * created, menus built, links assigned. This drives the real inc/activation.php
 * against a small in-memory model of WordPress's post and nav-menu tables, and
 * checks the state a new install actually lands in.
 *
 * The model deliberately mirrors two core behaviors that are easy to forget
 * and that the routine has to survive:
 *
 *   1. A stock install already contains a **draft** "Privacy Policy" page, and
 *      `wp_page_for_privacy_policy` points at it (wp_install_defaults()).
 *   2. get_page_by_path() does not filter by post_status, so that draft is
 *      returned by a slug lookup exactly like a published page would be.
 *
 * Run:  php tests/activation-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

// The output-type constants core defines in wp-includes/load.php.
define( 'OBJECT', 'OBJECT' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'ARRAY_N', 'ARRAY_N' );

/* -------------------------------------------------------------------------
 * A tiny WordPress: enough posts, options, menus and menu items to run the
 * activation routine, and nothing more.
 * ---------------------------------------------------------------------- */

class WP_Error {
	public $code;

	public function __construct( $code = '' ) {
		$this->code = $code;
	}

	public function get_error_code() {
		return $this->code;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function __( $text, $domain = null ) {
	return $text;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $text ) {
	return (string) $text;
}

/*
 * add_action records rather than ignores, so "the warning is wired up at all"
 * can be checked instead of assumed. Nothing in the theme depends on it being a
 * no-op, and add_filter stays one.
 */
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	if ( ! isset( $GLOBALS['hooks'] ) ) {
		$GLOBALS['hooks'] = array();
	}

	$GLOBALS['hooks'][] = $hook . ':' . ( is_string( $callback ) ? $callback : 'closure' );
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_shortcode( $tag, $callback ) {}

function apply_filters( $hook, $value ) {
	return $value;
}

function absint( $value ) {
	return abs( (int) $value );
}

function sanitize_title( $title ) {
	$slug = strtolower( (string) $title );
	$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );

	return trim( $slug, '-' );
}

function sanitize_email( $email ) {
	return (string) $email;
}

function is_email( $email ) {
	return (bool) filter_var( (string) $email, FILTER_VALIDATE_EMAIL );
}

function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}

function get_permalink( $id ) {
	return 'https://example.test/?page_id=' . (int) $id;
}

function get_bloginfo( $key = '' ) {
	return 'Example Site';
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default;
}

function update_option( $key, $value ) {
	$GLOBALS['options'][ $key ] = $value;

	return true;
}

function delete_option( $key ) {
	unset( $GLOBALS['options'][ $key ] );

	return true;
}

function get_theme_mod( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['theme_mods'] ) ? $GLOBALS['theme_mods'][ $key ] : $default;
}

function set_theme_mod( $key, $value ) {
	$GLOBALS['theme_mods'][ $key ] = $value;
}

function post_type_exists( $type ) {
	return true;
}

function get_current_user_id() {
	return 1;
}

function get_post( $id ) {
	foreach ( $GLOBALS['pages'] as $page ) {
		if ( (int) $page->ID === (int) $id ) {
			return $page;
		}
	}

	return null;
}

/*
 * Mirrors core: no post_status filter at all, so drafts and trashed rows are
 * returned by slug just like published ones.
 */
function get_page_by_path( $path, $output = OBJECT, $post_type = 'page' ) {
	$path = trim( (string) $path, '/' );

	foreach ( $GLOBALS['pages'] as $page ) {
		if ( 'page' === $page->post_type && $page->post_name === $path ) {
			return $page;
		}
	}

	return null;
}

function get_posts( $args = array() ) {
	$out = array();

	foreach ( $GLOBALS['pages'] as $page ) {
		if ( isset( $args['post_type'] ) && $page->post_type !== $args['post_type'] ) {
			continue;
		}

		if ( isset( $args['title'] ) && $page->post_title !== $args['title'] ) {
			continue;
		}

		if ( isset( $args['post_status'] ) ) {
			$statuses = (array) $args['post_status'];

			if ( ! in_array( $page->post_status, $statuses, true ) ) {
				continue;
			}
		}

		$out[] = $page;
	}

	usort( $out, static fn( $a, $b ) => $a->ID <=> $b->ID );

	return $out;
}

function majestic_tube_insert_post( $args ) {
	$id = $GLOBALS['next_id']++;

	$page                 = new stdClass();
	$page->ID             = $id;
	$page->post_title     = isset( $args['post_title'] ) ? $args['post_title'] : '';
	$page->post_name      = isset( $args['post_name'] ) ? $args['post_name'] : '';
	$page->post_content   = isset( $args['post_content'] ) ? $args['post_content'] : '';
	$page->post_status    = isset( $args['post_status'] ) ? $args['post_status'] : 'publish';
	$page->post_type      = isset( $args['post_type'] ) ? $args['post_type'] : 'post';
	$page->post_parent    = isset( $args['post_parent'] ) ? (int) $args['post_parent'] : 0;
	$page->post_author    = 1;

	$GLOBALS['pages'][] = $page;

	return $id;
}

function wp_insert_post( $args, $wp_error = false ) {
	return majestic_tube_insert_post( $args );
}

function wp_update_post( $args, $wp_error = false ) {
	$id = isset( $args['ID'] ) ? (int) $args['ID'] : 0;

	foreach ( $GLOBALS['pages'] as $page ) {
		if ( (int) $page->ID !== $id ) {
			continue;
		}

		foreach ( array( 'post_title', 'post_name', 'post_content', 'post_status', 'post_type', 'post_parent' ) as $field ) {
			if ( array_key_exists( $field, $args ) ) {
				$page->$field = $args[ $field ];
			}
		}

		return $id;
	}

	return $wp_error ? new WP_Error( 'not_found' ) : 0;
}

function wp_untrash_post( $id ) {
	return wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
}

function wp_trash_post( $id ) {
	return wp_update_post( array( 'ID' => $id, 'post_status' => 'trash' ) );
}

function wp_delete_post( $id, $force = false ) {
	$id = (int) $id;

	foreach ( $GLOBALS['nav_items'] as $index => $item ) {
		if ( (int) $item->ID === $id ) {
			unset( $GLOBALS['nav_items'][ $index ] );
			$GLOBALS['nav_items'] = array_values( $GLOBALS['nav_items'] );

			return true;
		}
	}

	foreach ( $GLOBALS['pages'] as $index => $page ) {
		if ( (int) $page->ID === $id ) {
			unset( $GLOBALS['pages'][ $index ] );
			$GLOBALS['pages'] = array_values( $GLOBALS['pages'] );

			return true;
		}
	}

	return false;
}

function update_post_meta( $id, $key, $value ) {
	$GLOBALS['post_meta'][ (int) $id ][ $key ] = $value;
}

function get_post_meta( $id, $key, $single = false ) {
	return isset( $GLOBALS['post_meta'][ (int) $id ][ $key ] ) ? $GLOBALS['post_meta'][ (int) $id ][ $key ] : '';
}

function wp_get_nav_menu_object( $menu ) {
	foreach ( $GLOBALS['nav_menus'] as $object ) {
		if ( is_numeric( $menu ) && (int) $menu === (int) $object->term_id ) {
			return $object;
		}

		if ( is_string( $menu ) && $menu === $object->name ) {
			return $object;
		}
	}

	return false;
}

function wp_create_nav_menu( $name ) {
	if ( wp_get_nav_menu_object( $name ) ) {
		return new WP_Error( 'menu_exists' );
	}

	$menu          = new stdClass();
	$menu->term_id = 100 + count( $GLOBALS['nav_menus'] );
	$menu->name    = $name;

	$GLOBALS['nav_menus'][] = $menu;

	return $menu->term_id;
}

function wp_get_nav_menu_items( $menu_id ) {
	$out = array();

	foreach ( $GLOBALS['nav_items'] as $item ) {
		if ( (int) $item->menu_id === (int) $menu_id ) {
			$out[] = $item;
		}
	}

	return $out;
}

function wp_update_nav_menu_item( $menu_id, $item_id, $args ) {
	$menu_id = (int) $menu_id;
	$item_id = (int) $item_id;

	foreach ( $GLOBALS['nav_items'] as $item ) {
		if ( $item_id && (int) $item->ID === $item_id && (int) $item->menu_id === $menu_id ) {
			if ( isset( $args['menu-item-title'] ) ) {
				$item->title = $args['menu-item-title'];
			}

			if ( isset( $args['menu-item-object-id'] ) ) {
				$item->object_id = (int) $args['menu-item-object-id'];
			}

			return $item_id;
		}
	}

	$item                = new stdClass();
	$item->ID            = $GLOBALS['next_id']++;
	$item->menu_id       = $menu_id;
	$item->title         = isset( $args['menu-item-title'] ) ? $args['menu-item-title'] : '';
	$item->type          = isset( $args['menu-item-type'] ) && $args['menu-item-type'] ? $args['menu-item-type'] : 'custom';
	$item->object_id     = isset( $args['menu-item-object-id'] ) ? (int) $args['menu-item-object-id'] : 0;
	$item->url           = isset( $args['menu-item-url'] ) ? $args['menu-item-url'] : '';
	$item->menu_item_parent = 0;

	$GLOBALS['nav_items'][] = $item;

	return $item->ID;
}

/* -------------------------------------------------------------------------
 * Load the real activation routine.
 * ---------------------------------------------------------------------- */

/*
 * The administrator's side of the two warnings: who is looking, which screen
 * they are on, and the helpers WordPress's own admin markup reaches for. The
 * notices themselves are not stubbed - they are the shipping functions,
 * rendered into a buffer below, so the checks read the markup an administrator
 * actually sees.
 */

$GLOBALS['screen_id'] = 'dashboard';
$GLOBALS['user_meta'] = array();
$GLOBALS['caps']      = array( 'edit_theme_options' );

function is_admin() {
	return true;
}

function current_user_can( $capability ) {
	return in_array( $capability, $GLOBALS['caps'], true );
}

function get_current_screen() {
	$screen     = new stdClass();
	$screen->id = $GLOBALS['screen_id'];

	return $screen;
}

function get_user_meta( $user_id, $key, $single = false ) {
	return isset( $GLOBALS['user_meta'][ $key ] ) ? $GLOBALS['user_meta'][ $key ] : '';
}

function update_user_meta( $user_id, $key, $value ) {
	$GLOBALS['user_meta'][ $key ] = $value;

	return true;
}

function get_edit_post_link( $id ) {
	return 'https://example.test/wp-admin/post.php?post=' . (int) $id . '&action=edit';
}

function esc_html__( $text, $domain = null ) {
	return esc_html( $text );
}

function esc_attr__( $text, $domain = null ) {
	return esc_attr( $text );
}

function esc_html_e( $text, $domain = null ) {
	echo esc_html( $text );
}

function esc_attr_e( $text, $domain = null ) {
	echo esc_attr( $text );
}

function wp_nonce_url( $url, $action = -1 ) {
	return $url . '&_wpnonce=test-nonce';
}

function add_query_arg( $key, $value = '', $url = '' ) {
	if ( is_array( $key ) ) {
		$url = (string) $value;

		foreach ( $key as $name => $item ) {
			$url = add_query_arg( $name, $item, $url );
		}

		return $url;
	}

	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . rawurlencode( $key ) . '=' . rawurlencode( (string) $value );
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, -1 === $component ? -1 : $component );
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function sanitize_text_field( $text ) {
	return trim( strip_tags( (string) $text ) );
}

function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

require MAJESTIC_TUBE_DIR . '/inc/activation.php';

$passed = 0;
$failed = 0;

function check( $label, $ok, $detail = '' ) {
	global $passed, $failed;

	if ( $ok ) {
		++$passed;
		echo "  ok   $label\n";

		return;
	}

	++$failed;
	echo "  FAIL $label" . ( $detail ? "  ($detail)" : '' ) . "\n";
}

/**
 * Reset the whole model to the state a brand-new WordPress install is in.
 *
 * @param bool $core_privacy_draft Whether the stock draft Privacy Policy page
 *                                 exists, which it does on a real install.
 */
function reset_site( $core_privacy_draft ) {
	$GLOBALS['options']    = array( 'admin_email' => 'admin@example.test' );
	$GLOBALS['theme_mods'] = array();
	$GLOBALS['pages']      = array();
	$GLOBALS['nav_menus']  = array();
	$GLOBALS['nav_items']  = array();
	$GLOBALS['post_meta']  = array();
	$GLOBALS['next_id']    = 1;

	if ( $core_privacy_draft ) {
		$id = majestic_tube_insert_post(
			array(
				'post_title'   => 'Privacy Policy',
				'post_name'    => 'privacy-policy',
				'post_content' => 'WordPress default privacy text.',
				'post_status'  => 'draft',
				'post_type'    => 'page',
			)
		);

		update_option( 'wp_page_for_privacy_policy', $id );
	}
}

/**
 * Find a page row by slug, in any status.
 */
function page_by_slug( $slug ) {
	foreach ( $GLOBALS['pages'] as $page ) {
		if ( $page->post_name === $slug ) {
			return $page;
		}
	}

	return null;
}

/**
 * Titles of the top-level items in a menu.
 *
 * @return string[]
 */
function top_level_titles( $menu_id ) {
	$titles = array();

	foreach ( wp_get_nav_menu_items( $menu_id ) as $item ) {
		if ( 0 === (int) $item->menu_item_parent ) {
			$titles[] = $item->title;
		}
	}

	return $titles;
}

/**
 * Footer menu items with the viewable status of the page each one points at.
 *
 * @return array<string, bool>
 */
function footer_links() {
	$menu  = wp_get_nav_menu_object( 'Footer Legal Menu' );
	$links = array();

	if ( ! $menu ) {
		return $links;
	}

	foreach ( wp_get_nav_menu_items( $menu->term_id ) as $item ) {
		$page = $item->object_id ? get_post( $item->object_id ) : null;

		$links[ $item->title ] = $page && in_array( $page->post_status, array( 'publish', 'private' ), true );
	}

	return $links;
}

/* -------------------------------------------------------------------------
 * A real install: the stock draft Privacy Policy page is already there.
 * ---------------------------------------------------------------------- */

echo "\nA fresh install, with the stock draft Privacy Policy page\n";

reset_site( true );
majestic_tube_create_initial_pages();
majestic_tube_create_default_menu();

$footer = footer_links();

check( 'the Footer Legal Menu exists', (bool) wp_get_nav_menu_object( 'Footer Legal Menu' ) );
check( 'it links 18 U.S.C. § 2257', isset( $footer['18 U.S.C. § 2257'] ), 'got: ' . implode( ', ', array_keys( $footer ) ) );
check( 'it links DMCA', isset( $footer['DMCA'] ) );
check( 'it links the Privacy Policy', isset( $footer['Privacy Policy'] ) );
check( 'the 2257 link points at a page visitors can open', ! empty( $footer['18 U.S.C. § 2257'] ) );
check( 'the DMCA link points at a page visitors can open', ! empty( $footer['DMCA'] ) );
check( 'the Privacy Policy link points at a page visitors can open', ! empty( $footer['Privacy Policy'] ) );

$privacy = page_by_slug( 'privacy-policy' );
check(
	'the stock draft Privacy Policy page was adopted and published, not duplicated',
	$privacy && in_array( $privacy->post_status, array( 'publish', 'private' ), true ),
	$privacy ? 'status: ' . $privacy->post_status : 'no privacy page'
);
check(
	'there is exactly one Privacy Policy page',
	1 === count( array_filter( $GLOBALS['pages'], static fn( $p ) => 'Privacy Policy' === $p->post_title ) ),
	'found ' . count( array_filter( $GLOBALS['pages'], static fn( $p ) => 'Privacy Policy' === $p->post_title ) )
);

$main_menu = wp_get_nav_menu_object( 'Main Menu' );
$top       = top_level_titles( $main_menu->term_id );

check( 'the Main Menu exists', (bool) $main_menu );
check( 'the Main Menu starts with Home', isset( $top[0] ) && 'Home' === $top[0], 'got: ' . implode( ', ', $top ) );
check( 'Submit a Video is not a top-level item', ! in_array( 'Submit a Video', $top, true ), 'got: ' . implode( ', ', $top ) );
check( 'Profile is not a top-level item', ! in_array( 'Profile', $top, true ), 'got: ' . implode( ', ', $top ) );
check( 'the directory pages are top-level', in_array( 'Actors', $top, true ) && in_array( 'Categories', $top, true ) && in_array( 'Tags', $top, true ), 'got: ' . implode( ', ', $top ) );

/* -------------------------------------------------------------------------
 * A site where WordPress cleared the privacy option, so no stock page exists.
 * ---------------------------------------------------------------------- */

echo "\nAn install with no stock privacy page\n";

reset_site( false );
majestic_tube_create_initial_pages();
majestic_tube_create_default_menu();

$footer = footer_links();

check( 'it still links all three legal pages', 3 === count( $footer ), 'got: ' . implode( ', ', array_keys( $footer ) ) );
check( 'and every one points at a page visitors can open', ! in_array( false, $footer, true ), wp_json_encode_stub( $footer ) );

/* -------------------------------------------------------------------------
 * The state the real test site was found in: the 2257 page exists, but a
 * previous duplicate pushed it onto the suffixed slug 2257-2. It is linked
 * anyway, no third copy is made - and the suffix itself is put right, because
 * a page the theme created should never be served from an address nobody
 * chose.
 *
 * The row also carries the name an earlier release created it under, because
 * that is what a page already sitting on a live site has. That name is the only
 * thing that can find it here: its slug is neither the canonical one nor the
 * retired one, so a lookup that knew only the document's current title would
 * call the page missing and insert the very second copy it exists to prevent.
 * ---------------------------------------------------------------------- */

echo "\nAn install whose 2257 page sits on a suffixed slug\n";

reset_site( false );

$suffixed_id = majestic_tube_insert_post(
	array(
		'post_title'   => '18 USC 2257',
		'post_name'    => '2257-2',
		'post_content' => 'Starter 2257 text.',
		'post_status'  => 'publish',
		'post_type'    => 'page',
	)
);

majestic_tube_create_initial_pages();
majestic_tube_create_default_menu();

$footer = footer_links();
$copies = array_filter( $GLOBALS['pages'], static fn( $p ) => '18 U.S.C. § 2257' === $p->post_title );

check( 'the footer links 18 U.S.C. § 2257 even though its slug is suffixed', isset( $footer['18 U.S.C. § 2257'] ), 'got: ' . implode( ', ', array_keys( $footer ) ) );
check( 'and that link points at a page visitors can open', ! empty( $footer['18 U.S.C. § 2257'] ) );
check( 'no third copy of the 2257 page is created', 1 === count( $copies ), 'found ' . count( $copies ) );
check(
	'and the copy that was found is renamed, not left under the old name',
	array() === array_filter( $GLOBALS['pages'], static fn( $p ) => '18 USC 2257' === $p->post_title ),
	'titles now: ' . implode( ', ', array_column( $GLOBALS['pages'], 'post_title' ) )
);
check( 'the privacy page is still linked and live', ! empty( $footer['Privacy Policy'] ), wp_json_encode_stub( $footer ) );

$repaired = page_by_slug( '18-usc-2257' );

check( 'the page is put back on the slug the theme links at', (bool) $repaired, 'slugs now: ' . implode( ', ', array_column( $GLOBALS['pages'], 'post_name' ) ) );
check(
	'and it is the same page, not a replacement',
	$repaired && (int) $suffixed_id === (int) $repaired->ID && 'Starter 2257 text.' === $repaired->post_content,
	$repaired ? 'id ' . $repaired->ID . ', content: ' . $repaired->post_content : 'no page on 18-usc-2257'
);
check( 'and the numbered address it was on is gone', ! page_by_slug( '2257-2' ), 'slug 2257-2 still there' );
check( 'so every legal page sits on the slug the theme asks for', array() === off_canonical_legal_pages(), implode( ', ', off_canonical_legal_pages() ) );

/* -------------------------------------------------------------------------
 * Every existing site: the 2257 page is on the right address and holds the
 * operator's own wording, but it still carries the name an earlier release
 * created it under, and the footer link that release built says the same name.
 * The pass that runs after a revision bump has to rename both, without touching
 * the page's content or its address - and without renaming a name the operator
 * chose, which is the same judgment told the other way round.
 * ---------------------------------------------------------------------- */

echo "\nAn install whose 2257 page answers to the name an earlier release gave it\n";

reset_site( false );

$notice_id = majestic_tube_insert_post(
	array(
		'post_title'   => '18 USC 2257',
		'post_name'    => '18-usc-2257',
		'post_content' => 'The operator\'s own 2257 wording.',
		'post_status'  => 'publish',
		'post_type'    => 'page',
	)
);

$footer_menu_id = majestic_tube_get_or_create_nav_menu( 'Footer Legal Menu' );
majestic_tube_update_page_menu_item( $footer_menu_id, '18 USC 2257', $notice_id );
set_theme_mod(
	'nav_menu_locations',
	array( 'majestic_tube_footer_menu' => $footer_menu_id )
);

majestic_tube_create_initial_pages();
majestic_tube_create_default_menu();

$notice       = get_post( $notice_id );
$footer_items = array();

foreach ( wp_get_nav_menu_items( $footer_menu_id ) as $item ) {
	$footer_items[ $item->title ] = (int) $item->object_id;
}

check( 'the page keeps its address', $notice && '18-usc-2257' === $notice->post_name, $notice ? $notice->post_name : 'the page is gone' );
check( 'and its content', $notice && 'The operator\'s own 2257 wording.' === $notice->post_content, $notice ? $notice->post_content : 'the page is gone' );
check( 'while the name it was created under is replaced', $notice && '18 U.S.C. § 2257' === $notice->post_title, $notice ? $notice->post_title : 'the page is gone' );
check(
	'and no second page is created beside it under the new name',
	1 === count( array_filter( $GLOBALS['pages'], static fn( $p ) => false !== strpos( (string) $p->post_title, '2257' ) ) ),
	'pages: ' . implode( ', ', array_column( $GLOBALS['pages'], 'post_title' ) )
);
check( 'the footer link is renamed with it', isset( $footer_items['18 U.S.C. § 2257'] ), 'got: ' . implode( ', ', array_keys( $footer_items ) ) );
check( 'and still points at the page it did', isset( $footer_items['18 U.S.C. § 2257'] ) && (int) $notice_id === $footer_items['18 U.S.C. § 2257'], wp_json_encode_stub( $footer_items ) );

// The same document, under a name the operator typed. It is still the page the
// theme manages - it sits on the retired slug the theme itself recognizes - so
// pretending it does not exist would mint a second copy. Only its name is none
// of the theme's business.
reset_site( false );

$own_id = majestic_tube_insert_post(
	array(
		'post_title'   => 'My Own 2257 Notice',
		'post_name'    => '2257',
		'post_content' => 'Renamed by hand.',
		'post_status'  => 'publish',
		'post_type'    => 'page',
	)
);

$own_menu_id = majestic_tube_get_or_create_nav_menu( 'Footer Legal Menu' );
majestic_tube_update_page_menu_item( $own_menu_id, 'My Own 2257 Notice', $own_id );
set_theme_mod(
	'nav_menu_locations',
	array( 'majestic_tube_footer_menu' => $own_menu_id )
);

majestic_tube_create_initial_pages();
majestic_tube_create_default_menu();

$own        = get_post( $own_id );
$own_footer = footer_links();

check( 'a page the operator named themselves keeps that name', $own && 'My Own 2257 Notice' === $own->post_title, $own ? $own->post_title : 'the page is gone' );
check( 'and the document is not duplicated next to it', 1 === count( array_filter( $GLOBALS['pages'], static fn( $p ) => false !== strpos( (string) $p->post_title, '2257' ) ) ), 'pages: ' . implode( ', ', array_column( $GLOBALS['pages'], 'post_title' ) ) );
check( 'and its footer link keeps the label it had', isset( $own_footer['My Own 2257 Notice'] ), 'got: ' . implode( ', ', array_keys( $own_footer ) ) );

/*
 * The same repair has to wait while two live copies exist: renaming onto a
 * slug the other copy holds would mint the next number instead of removing
 * one. The status screen offers to trash the extra copy, and once it is gone
 * the next pass puts the document back on its own slug.
 */
echo "\nA site with two live copies of the 2257 page\n";

reset_site( false );

$first_id  = majestic_tube_insert_post(
	array(
		'post_title'   => '18 USC 2257',
		'post_name'    => '2257-2',
		'post_content' => 'Kept text.',
		'post_status'  => 'publish',
		'post_type'    => 'page',
	)
);
$second_id = majestic_tube_insert_post(
	array(
		'post_title'   => '18 USC 2257',
		'post_name'    => '2257-3',
		'post_content' => 'Extra copy text.',
		'post_status'  => 'publish',
		'post_type'    => 'page',
	)
);

$status = majestic_tube_legal_page_status();

check( 'both copies are seen', isset( $status['duplicates']['18 U.S.C. § 2257'] ) && 1 === count( $status['duplicates']['18 U.S.C. § 2257'] ), 'found ' . count( $status['duplicates']['18 U.S.C. § 2257'] ) . ' duplicates' );
check( 'and the extra one is the one named', in_array( $second_id, $status['duplicates']['18 U.S.C. § 2257'], true ), 'named: ' . implode( ', ', $status['duplicates']['18 U.S.C. § 2257'] ) );
check( 'while two copies exist the slug is left alone', ! page_by_slug( '2257' ) );

// What the status screen's "move to the trash" button does, then the pass the
// welcome screen runs afterwards.
wp_trash_post( $second_id );
majestic_tube_get_or_create_legal_page( '18 U.S.C. § 2257', majestic_tube_legal_pages()['18 U.S.C. § 2257'], true );

$kept = page_by_slug( '18-usc-2257' );

check( 'with the extra copy gone, the document returns to its own slug', (bool) $kept && (int) $first_id === (int) $kept->ID, 'slugs now: ' . implode( ', ', array_column( $GLOBALS['pages'], 'post_name' ) ) );
check( 'and the copy that was kept is the one that survived', $kept && 'Kept text.' === $kept->post_content, $kept ? $kept->post_content : 'no page on 18-usc-2257' );
check( 'and the retired slug it was on is gone with it', ! page_by_slug( '2257-2' ), 'slug 2257-2 still there' );
check( 'and the trashed copy stays in the trash, recoverable', 'trash' === get_post( $second_id )->post_status, get_post( $second_id )->post_status );

/* -------------------------------------------------------------------------
 * An existing site: the menu was built by an older release and the setup
 * revision is already stored, so this is a re-run after a revision bump.
 * ---------------------------------------------------------------------- */

echo "\nAn existing install, re-checked after a revision bump\n";

reset_site( false );

foreach ( majestic_tube_activation_pages() as $title => $template ) {
	majestic_tube_create_page( $title, $template );
}

foreach ( majestic_tube_legal_pages() as $title => $definition ) {
	majestic_tube_get_or_create_legal_page( $title, $definition );
}

// Reproduce the old default: every activation page at the top level.
$menu_id = majestic_tube_get_or_create_nav_menu( 'Main Menu' );

foreach ( majestic_tube_activation_pages() as $title => $template ) {
	$page = get_page_by_path( sanitize_title( $title ) );

	if ( $page ) {
		majestic_tube_update_page_menu_item( $menu_id, $title, $page->ID );
	}
}

update_option( 'majestic_tube_setup_revision', 5 );

// A privacy page the administrator has returned to draft on purpose.
$privacy = page_by_slug( 'privacy-policy' );
wp_update_post( array( 'ID' => $privacy->ID, 'post_status' => 'draft' ) );

majestic_tube_create_initial_pages();
majestic_tube_create_default_menu();

$top     = top_level_titles( $menu_id );
$privacy = page_by_slug( 'privacy-policy' );

check( 'the old top-level Submit a Video item is removed', ! in_array( 'Submit a Video', $top, true ), 'got: ' . implode( ', ', $top ) );
check( 'the old top-level Profile item is removed', ! in_array( 'Profile', $top, true ), 'got: ' . implode( ', ', $top ) );
check(
	'a page the administrator drafted by hand is left as a draft',
	$privacy && 'draft' === $privacy->post_status,
	$privacy ? 'status: ' . $privacy->post_status : 'no privacy page'
);

/* -------------------------------------------------------------------------
 * An existing site whose privacy page is still WordPress's stock draft, and
 * whose footer legal repair has not run since the fix. This is the upgrade.
 * ---------------------------------------------------------------------- */

echo "\nAn existing install, whose privacy page is still the stock draft\n";

reset_site( true );
majestic_tube_create_initial_pages();
majestic_tube_create_default_menu();

// Put the privacy page back into the draft state an earlier release left, and
// rewind the footer repair marker so the repair runs again, as it does once
// after a theme update.
$privacy = page_by_slug( 'privacy-policy' );
wp_update_post( array( 'ID' => $privacy->ID, 'post_status' => 'draft' ) );
update_option( 'majestic_tube_footer_legal_revision', 1 );

majestic_tube_repair_footer_legal_links();

$footer  = footer_links();
$privacy = page_by_slug( 'privacy-policy' );

check( 'the footer still links all three legal pages', 3 === count( $footer ), 'got: ' . implode( ', ', array_keys( $footer ) ) );
check( 'the repair publishes the adopted draft, so the link is live', ! empty( $footer['Privacy Policy'] ), wp_json_encode_stub( $footer ) );
check(
	'and the page really is published',
	$privacy && in_array( $privacy->post_status, array( 'publish', 'private' ), true ),
	$privacy ? 'status: ' . $privacy->post_status : 'no privacy page'
);

/* -------------------------------------------------------------------------
 * An existing site whose legal pages still end with the retired template
 * notice an earlier release shipped. The upgrade has to remove it without
 * touching anything the administrator wrote.
 * ---------------------------------------------------------------------- */

echo "\nAn existing install, whose legal pages still carry the old notice\n";

reset_site( false );
majestic_tube_create_initial_pages();

$dmca = page_by_slug( 'dmca' );

check(
	'the copy this release creates carries no template notice',
	false === strpos( $dmca->post_content, 'Template notice' ),
	'shipped content still has one'
);

// Put the notice back the way an earlier release left it, with a sentence of
// the administrator's own after it, then re-run the setup as a revision bump
// would.
wp_update_post(
	array(
		'ID'           => $dmca->ID,
		'post_content' => $dmca->post_content
			. "\n<hr />\n"
			. '<p><strong>Template notice:</strong> This page is a general starting point.</p>'
			. "\n<p>Ask our designated agent about notices.</p>",
	)
);

majestic_tube_create_initial_pages();

$upgraded = page_by_slug( 'dmca' );

check(
	'the upgrade removes the retired notice',
	false === strpos( $upgraded->post_content, 'Template notice' ),
	'still present: ' . $upgraded->post_content
);
check( 'and the separator above it', false === strpos( $upgraded->post_content, '<hr' ) );
check( 'the policy itself is untouched', false !== strpos( $upgraded->post_content, 'repeat infringers' ) );
check(
	'the administrator\'s own wording after it survives',
	false !== strpos( $upgraded->post_content, 'Ask our designated agent about notices.' )
);
check(
	'and re-running again changes nothing further',
	$upgraded->post_content === page_by_slug( 'dmca' )->post_content
);

/*
 * The revision is the release-time switch that makes an existing install run
 * the setup once more, so it has to move with this change or the strip above
 * never reaches a site that is already live. The 2257 rename rides the same
 * switch, for the same reason: the page that has to be renamed is already on
 * every existing site, and only a pass that runs again will reach it.
 */
check(
	'the setup revision was bumped for this change',
	7 === majestic_tube_setup_revision(),
	'got: ' . majestic_tube_setup_revision()
);

// The slug repair rides the footer repair, which is gated the same way, so an
// install that is already live has to run that pass once more after updating.
check(
	'the footer repair revision was bumped for the slug repair',
	3 === majestic_tube_footer_legal_revision(),
	'got: ' . majestic_tube_footer_legal_revision()
);

/* -------------------------------------------------------------------------
 * A legal page that has picked up code, an embed or an ad block.
 *
 * The theme withholds its own widget areas on a legal page, but nothing stops
 * an editor from pasting an ad tag into the policy itself - and a theme that
 * quietly deleted what the administrator wrote would be the worse bug. What it
 * does instead is say so: on the dashboard as a dismissible notice, and beside
 * the page list on the welcome screen. Both are the shipping functions here,
 * rendered for real.
 * ---------------------------------------------------------------------- */

echo "\nThe slugs the theme asks WordPress for\n";

/*
 * wp_unique_post_slug() refuses a page slug that is a number, with or without
 * the pagination base, because /2257/ is indistinguishable from a page number
 * in a URL the site generates itself. A release that asked for one was handed
 * 2257-2 instead - which is where every numbered legal slug on a live site came
 * from. The shipped slugs have to be names WordPress will actually publish.
 */
/*
 * The legacy name is checked in neither direction: it is a name the theme only
 * looks for, and the numeric one is there because an earlier release was
 * published on WordPress that still allowed it. What matters is the name a page
 * is asked to sit on.
 */
$numeric = array();

foreach ( majestic_tube_legal_pages() as $title => $definition ) {
	if ( ! empty( $definition['slug'] ) && preg_match( '/^(?:page)?\d+$/i', (string) $definition['slug'] ) ) {
		$numeric[] = $title . ' => ' . $definition['slug'];
	}
}

check( 'no legal page is asked for a numeric slug, which WordPress refuses to publish', array() === $numeric, implode( ', ', $numeric ) );
check(
	'the 2257 notice is asked for on a name WordPress can give it',
	'18-usc-2257' === majestic_tube_legal_pages()['18 U.S.C. § 2257']['slug'],
	majestic_tube_legal_pages()['18 U.S.C. § 2257']['slug']
);

/*
 * A retired numeric address still has to lead somewhere. What it must not do is
 * lead back to the page answering it - that is a redirect loop, not a fix - so
 * the redirect only fires when the numbered address is a different row from the
 * document's own page, which is the case a stale duplicate leaves behind.
 */
$saved_uri = $_SERVER['REQUEST_URI'] ?? '';

reset_site( false );

$canonical_id = majestic_tube_insert_post(
	array(
		'post_title'   => '18 USC 2257',
		'post_name'    => '18-usc-2257',
		'post_content' => 'The notice.',
		'post_status'  => 'publish',
		'post_type'    => 'page',
	)
);
$duplicate_id = majestic_tube_insert_post(
	array(
		'post_title'   => '18 USC 2257',
		'post_name'    => '2257-2',
		'post_content' => 'A stale copy.',
		'post_status'  => 'publish',
		'post_type'    => 'page',
	)
);

$_SERVER['REQUEST_URI'] = '/2257-2/';
check(
	'a retired numbered address sends the visitor to the document',
	'https://example.test/?page_id=' . $canonical_id === majestic_tube_retired_2257_redirect_url(),
	'target: ' . majestic_tube_retired_2257_redirect_url()
);
$_SERVER['REQUEST_URI'] = '/18-usc-2257/';
check( 'and the document itself is not redirected to itself', '' === majestic_tube_retired_2257_redirect_url(), majestic_tube_retired_2257_redirect_url() );
$_SERVER['REQUEST_URI'] = '/about/';
check( 'while any other page is left alone', '' === majestic_tube_retired_2257_redirect_url(), majestic_tube_retired_2257_redirect_url() );

// The document on its own, with nothing left on a numbered address: a published
// link to the old address still reaches the document.
wp_delete_post( $duplicate_id, true );
$_SERVER['REQUEST_URI'] = '/2257-2/';
check(
	'with the stale copy gone, the retired address still reaches the document',
	'https://example.test/?page_id=' . $canonical_id === majestic_tube_retired_2257_redirect_url(),
	'target: ' . majestic_tube_retired_2257_redirect_url()
);

// An unrelated page that happens to sit on a retired address is its own page.
wp_delete_post( $canonical_id, true );
$other_id = majestic_tube_insert_post(
	array(
		'post_title'   => 'Our suppliers',
		'post_name'    => '2257',
		'post_content' => 'Not the notice.',
		'post_status'  => 'publish',
		'post_type'    => 'page',
	)
);

$_SERVER['REQUEST_URI'] = '/2257/';
check( 'a page with an unrelated title on that address is left alone', '' === majestic_tube_retired_2257_redirect_url(), 'redirected to: ' . majestic_tube_retired_2257_redirect_url() );

// And with no 2257 document left at all there is nothing to point at.
wp_delete_post( $other_id, true );
check( 'and with no notice published, the address is simply not there', '' === majestic_tube_retired_2257_redirect_url(), 'redirected to: ' . majestic_tube_retired_2257_redirect_url() );

$_SERVER['REQUEST_URI'] = $saved_uri;

echo "\nA legal page that has picked up an ad tag\n";

/**
 * Legal pages whose live copy is not on the slug the theme asks for.
 *
 * @return string[] Page titles with the slug each one actually sits on.
 */
function off_canonical_legal_pages() {
	$off = array();

	foreach ( majestic_tube_legal_pages() as $title => $definition ) {
		$page = majestic_tube_find_legal_page( $definition, $title );

		if ( ! $page || ! isset( $page->post_name ) || $definition['slug'] !== $page->post_name ) {
			$off[] = $title . ' on ' . ( $page && isset( $page->post_name ) ? $page->post_name : 'nothing' );
		}
	}

	return $off;
}

/**
 * What the warning reports about one page, as a list of kinds.
 *
 * @param string $title Legal page title.
 * @return string[]
 */
function flagged_kinds( $title ) {
	$flagged = majestic_tube_legal_pages_with_code_blocks();

	return isset( $flagged[ $title ]['kinds'] ) ? $flagged[ $title ]['kinds'] : array();
}

/**
 * Render a function's HTML, the way wp-admin would.
 *
 * @param string $function Function name.
 * @return string
 */
function render_admin( $function ) {
	ob_start();
	call_user_func( $function );

	return (string) ob_get_clean();
}

reset_site( false );
majestic_tube_create_initial_pages();

check(
	'the copy this release creates is clean, so the warning is silent to begin with',
	array() === majestic_tube_legal_pages_with_code_blocks(),
	implode( ', ', array_keys( majestic_tube_legal_pages_with_code_blocks() ) )
);

$dmca    = page_by_slug( 'dmca' );
$privacy = page_by_slug( 'privacy-policy' );

/*
 * A policy that names the script it loads is not carrying one. The Code block
 * an editor writes that sentence in holds text a visitor reads, so it is a
 * quotation - and flagging it would teach an administrator to dismiss the
 * warning without reading it.
 */
wp_update_post(
	array(
		'ID'           => $dmca->ID,
		'post_content' => "<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>We load google adsbygoogle.js and nothing else.</code></pre>\n<!-- /wp:code -->",
	)
);

check(
	'a policy that only names the ad script inside a Code block is left alone',
	array() === majestic_tube_legal_pages_with_code_blocks(),
	implode( ', ', flagged_kinds( 'DMCA' ) )
);

/*
 * The same two comments around a raw tag are not a quotation: the browser does
 * not read them as one and the tag prints, so markup that runs is looked for
 * across the whole page rather than outside the code blocks alone.
 */
wp_update_post(
	array(
		'ID'           => $dmca->ID,
		'post_content' => "<!-- wp:code -->\n<script src=\"https://ads.example.test/ads.js\"></script>\n<!-- /wp:code -->",
	)
);

$flagged = majestic_tube_legal_pages_with_code_blocks();

check( 'a raw script tag is reported, comments around it or not', isset( $flagged['DMCA'] ), implode( ', ', array_keys( $flagged ) ) );
check(
	'and the report says what was found',
	array( 'a script tag' ) === flagged_kinds( 'DMCA' ),
	implode( ', ', flagged_kinds( 'DMCA' ) )
);
check( 'and names the page it is on', isset( $flagged['DMCA'] ) && (int) $dmca->ID === $flagged['DMCA']['id'], isset( $flagged['DMCA'] ) ? (string) $flagged['DMCA']['id'] : 'nothing flagged' );
check(
	'the other legal pages are not dragged into the report',
	! isset( $flagged['Privacy Policy'], $flagged['18 U.S.C. § 2257'] ),
	implode( ', ', array_keys( $flagged ) )
);

// A page that is not one of the theme's legal documents is not the warning's business.
majestic_tube_insert_post(
	array(
		'post_title'   => 'About',
		'post_name'    => 'about',
		'post_content' => '<script src="https://ads.example.test/ads.js"></script>',
		'post_status'  => 'publish',
		'post_type'    => 'page',
	)
);

check( 'an ordinary page with an ad tag on it is not reported', ! isset( majestic_tube_legal_pages_with_code_blocks()['About'] ) );

/*
 * The shape the warning most often has to describe: a network ad pasted into a
 * Custom HTML block, where four separate things are true at once and the report
 * has to carry all of them.
 */
wp_update_post(
	array(
		'ID'           => $dmca->ID,
		'post_content' => "<p>We use advertising.</p>\n<!-- wp:html -->\n<ins class=\"adsbygoogle\" data-ad-client=\"ca-pub-000000\"></ins>\n<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>\n<!-- /wp:html -->",
	)
);

$kinds = flagged_kinds( 'DMCA' );

check(
	'an ad pasted into a Custom HTML block is reported as every kind it carries',
	4 === count( $kinds ) && ! array_diff( array( 'a Custom HTML block', 'a script tag', 'an embed or ad frame', 'a network ad marker' ), $kinds ),
	implode( ', ', $kinds )
);

wp_update_post( array( 'ID' => $dmca->ID, 'post_content' => '<p>Advertising partners: see [adsense] for details.</p>' ) );

check( 'an ad shortcode in the page is reported too', in_array( 'an ad or embed shortcode', flagged_kinds( 'DMCA' ), true ), implode( ', ', flagged_kinds( 'DMCA' ) ) );

wp_update_post( array( 'ID' => $dmca->ID, 'post_content' => '<!-- wp:ads/leaderboard {"id":7} /-->' ) );

check( 'a block from an advertising plugin is recognized by its name', in_array( 'an advertising block', flagged_kinds( 'DMCA' ), true ), implode( ', ', flagged_kinds( 'DMCA' ) ) );

/*
 * The dismissal is remembered against the state of the pages rather than as a
 * plain "seen it" flag: an administrator who has read the warning is left alone
 * while nothing changes, and is told again when something new turns up.
 */
$key_ad_block = majestic_tube_legal_code_notice_key( majestic_tube_legal_pages_with_code_blocks() );

check( 'the same state produces the same key', $key_ad_block === majestic_tube_legal_code_notice_key( majestic_tube_legal_pages_with_code_blocks() ) );

// Editing the wording of the flagged page is not news about it.
wp_update_post( array( 'ID' => $dmca->ID, 'post_content' => "<!-- wp:ads/leaderboard {\"id\":7} /-->\n<p>Last reviewed today.</p>" ) );

check( 'editing a flagged page without changing what was found keeps the key', $key_ad_block === majestic_tube_legal_code_notice_key( majestic_tube_legal_pages_with_code_blocks() ) );

// A second page turning up is news.
wp_update_post( array( 'ID' => $privacy->ID, 'post_content' => $privacy->post_content . "\n<iframe src=\"https://ads.example.test/frame\"></iframe>" ) );

$key_two_pages = majestic_tube_legal_code_notice_key( majestic_tube_legal_pages_with_code_blocks() );

check( 'a second page turning up changes the key', $key_ad_block !== $key_two_pages );

$GLOBALS['user_meta'] = array();

check( 'nothing is dismissed before the administrator has read it', false === majestic_tube_legal_code_notice_is_dismissed( $key_two_pages ) );

update_user_meta( get_current_user_id(), 'majestic_tube_legal_code_notice', $key_two_pages );

check( 'after a dismissal, that state is remembered', majestic_tube_legal_code_notice_is_dismissed( $key_two_pages ) );
check( 'and a different state is not', false === majestic_tube_legal_code_notice_is_dismissed( $key_ad_block ) );

check(
	'the warning is registered on the dashboard, and its dismissal on admin_init',
	in_array( 'admin_notices:majestic_tube_legal_code_notice', $GLOBALS['hooks'], true ) && in_array( 'admin_init:majestic_tube_handle_legal_code_notice_dismissal', $GLOBALS['hooks'], true ),
	implode( ', ', $GLOBALS['hooks'] )
);

/* -------------------------------------------------------------------------
 * What the administrator is actually shown.
 * ---------------------------------------------------------------------- */

echo "\nWhat the administrator is shown\n";

// Back to one page carrying one ad, which is the state the screenshots describe.
wp_update_post( array( 'ID' => $privacy->ID, 'post_content' => 'WordPress default privacy text.' ) );
wp_update_post( array( 'ID' => $dmca->ID, 'post_content' => "<p>The policy.</p>\n<script src=\"https://ads.example.test/ads.js\"></script>" ) );
$GLOBALS['user_meta'] = array();

$notice = render_admin( 'majestic_tube_legal_code_notice' );

check( 'the dashboard warning is shown', false !== strpos( $notice, 'notice notice-warning' ), $notice );
check( 'it names the page and links at its editor', false !== strpos( $notice, '<a href="' . get_edit_post_link( $dmca->ID ) . '">DMCA</a>' ), $notice );
check( 'it says what was found there', false !== strpos( $notice, 'a script tag' ), $notice );
check( 'and it offers a dismissal', false !== strpos( $notice, 'Dismiss this warning' ) && false !== strpos( $notice, 'majestic-tube-legal-notice=dismiss' ) && false !== strpos( $notice, '_wpnonce=' ), $notice );

$GLOBALS['user_meta'] = array( 'majestic_tube_legal_code_notice' => majestic_tube_legal_code_notice_key( majestic_tube_legal_pages_with_code_blocks() ) );

check( 'once dismissed, the same state is shown nothing', '' === render_admin( 'majestic_tube_legal_code_notice' ) );

$GLOBALS['user_meta'] = array();
$GLOBALS['screen_id'] = 'edit-post';

check( 'and the dashboard is the only screen it appears on', '' === render_admin( 'majestic_tube_legal_code_notice' ) );

$GLOBALS['screen_id']  = 'dashboard';
$GLOBALS['caps']       = array();

check( 'an account that may not manage the theme is not told', '' === render_admin( 'majestic_tube_legal_code_notice' ) );

$GLOBALS['caps'] = array( 'edit_theme_options' );

wp_update_post( array( 'ID' => $dmca->ID, 'post_content' => '<p>The policy.</p>' ) );

check( 'with the ad gone, the warning goes with it', '' === render_admin( 'majestic_tube_legal_code_notice' ) );

// The welcome screen repeats it beside the page list, for the administrator
// who is already looking at the pages themselves.
$welcome = render_admin( 'majestic_tube_render_welcome_page' );

check(
	'the welcome screen rendered, so the two checks below mean something',
	false !== strpos( $welcome, 'Welcome to Majestic Tube' ) && false !== strpos( $welcome, 'These are the compliance pages the theme creates.' ),
	$welcome
);
check( 'the welcome screen stays silent while the pages are clean', false === strpos( $welcome, 'carries code, an embed or an ad block' ) );
check( 'and lists each page with no note against it', false === strpos( $welcome, 'Contains ' ) );

wp_update_post( array( 'ID' => $dmca->ID, 'post_content' => "<p>The policy.</p>\n<script src=\"https://ads.example.test/ads.js\"></script>" ) );

$welcome = render_admin( 'majestic_tube_render_welcome_page' );

check( 'with an ad on a page, it says so beside the list', false !== strpos( $welcome, 'A legal page carries code, an embed or an ad block.' ) );
check( 'it names the page and links at its editor', false !== strpos( $welcome, '<a href="' . get_edit_post_link( $dmca->ID ) . '">DMCA</a>' ) );
check( 'it says what was found', false !== strpos( $welcome, 'a script tag' ) );
check( 'and the page row itself repeats it', false !== strpos( $welcome, 'Contains a script tag' ) );
check( 'it still lists all three legal pages', false !== strpos( $welcome, '18 U.S.C. § 2257' ) && false !== strpos( $welcome, 'Privacy Policy' ) );

/**
 * Readable dump of the footer status map, for a failing check's detail.
 */
function wp_json_encode_stub( $value ) {
	$pairs = array();

	foreach ( $value as $key => $ok ) {
		$pairs[] = $key . '=' . ( $ok ? 'live' : 'dead' );
	}

	return implode( ', ', $pairs );
}

echo "\n" . str_repeat( '-', 44 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

exit( $failed ? 1 : 0 );
