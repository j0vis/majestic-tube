<?php
/**
 * Smoke test for the friends & ad links bar.
 *
 * The two functions are lifted out of inc/template-tags.php and run against
 * stubs, so the real code is exercised without a WordPress install. The point
 * of the test is the rel scoping: an ad link must never pass equity, and the
 * main menu must never be rewritten by a filter that exists for an ad strip.
 *
 * The row is also restricted to the site's index, so the stub answers is_home()
 * and every other request is held to printing nothing at all - with the switch
 * on and the menu assigned throughout, so the page is the only difference.
 *
 * Run:  php tests/links-bar-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

$GLOBALS['opts']        = array();
$GLOBALS['menu_exists'] = false;
$GLOBALS['menu_items']  = array();
$GLOBALS['view']        = 'index';

function __( $s, $d = null ) {
	return $s;
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {}
function apply_filters( $hook, $value ) {
	return $value;
}
/*
 * Mirrors the real reader in inc/theme-options.php: a field that appears in
 * the options map falls back to the *map's* declared default, not to whatever
 * the caller passed. A stub that used the caller's default made both switches
 * read as off on a fresh site and hid a bug that was not there.
 */
function majestic_tube_get_option( $group, $field_id, $default = false ) {
	$map_defaults = array(
		'show-links-bar'     => 'on',
		'links-bar-label'    => '',
		'links-bar-new-tab'  => 'on',
		'links-bar-style'    => 'plain',
		'links-bar-icons'    => 'on',
		'links-bar-shine'    => 'on',
	);

	if ( array_key_exists( $field_id, $GLOBALS['opts'] ) ) {
		return $GLOBALS['opts'][ $field_id ];
	}

	if ( array_key_exists( $field_id, $map_defaults ) ) {
		return $map_defaults[ $field_id ];
	}

	return $default;
}
function majestic_tube_option_is_on( $field_id ) {
	return 'on' === majestic_tube_get_option( 'wpst-options', $field_id, 'off' );
}
function has_nav_menu( $location ) {
	return $GLOBALS['menu_exists'];
}

/*
 * The row prints on the index only, so the model has to answer that question
 * rather than let a missing function decide it. $GLOBALS['view'] names the
 * request being simulated - index, single, archive, search, 404, page - and
 * is_home() answers the way WordPress would.
 */
function is_home() {
	return 'index' === $GLOBALS['view'];
}

/**
 * Stand-in for wp_nav_menu(): prints the fixture items as a real menu would.
 *
 * @param array $args Menu arguments.
 * @return void
 */
function wp_nav_menu( $args ) {
	$atts = array(
		'href'   => '#',
		'rel'    => '',
		'target' => '',
	);

	// Mirror the real filter chain so the theme's own filters are exercised.
	$atts = majestic_tube_links_bar_link_attributes( $atts, null, (object) $args );

	echo '<ul class="' . esc_attr( $args['menu_class'] ) . '">';

	foreach ( $GLOBALS['menu_items'] as $item ) {
		$title = majestic_tube_links_bar_item_title( $item['label'], null, (object) $args, 0 );

		printf(
			'<li><a href="%s"%s%s>%s</a></li>',
			esc_url( $item['href'] ),
			$atts['target'] ? ' target="' . esc_attr( $atts['target'] ) . '"' : '',
			$atts['rel'] ? ' rel="' . esc_attr( $atts['rel'] ) . '"' : '',
			$title
		);
	}

	echo '</ul>';
}

// Pull the functions under test out of the theme file.
$source = file_get_contents( MAJESTIC_TUBE_DIR . '/inc/template-tags.php' );

$functions = array(
	'majestic_tube_links_bar',
	'majestic_tube_links_bar_link_attributes',
	'majestic_tube_links_bar_style',
	'majestic_tube_links_bar_icon_sets',
	'majestic_tube_links_bar_icon_set',
	'majestic_tube_links_bar_next_icon',
	'majestic_tube_links_bar_has_own_icon',
	'majestic_tube_links_bar_item_title',
);

foreach ( $functions as $fn ) {
	if ( ! preg_match( '/^function ' . $fn . '\(.*?^}/ms', $source, $match ) ) {
		fwrite( STDERR, "could not extract {$fn}\n" );
		exit( 1 );
	}
	eval( $match[0] );
}

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

function render() {
	ob_start();
	majestic_tube_links_bar();

	return trim( (string) ob_get_clean() );
}

echo "\nMajestic Tube - friends & ad links bar\n";
echo str_repeat( '-', 46 ) . "\n";

echo "\nNothing prints when it should not\n";

// No options saved at all: the declared defaults apply, so the bar is on.
$GLOBALS['opts']        = array();
$GLOBALS['menu_exists'] = false;
$GLOBALS['menu_items']  = array();
$GLOBALS['view']        = 'index';
check( 'no menu assigned prints nothing', '' === render() );

$GLOBALS['menu_exists'] = true;
$GLOBALS['opts']        = array( 'show-links-bar' => 'off' );
check( 'switch off prints nothing', '' === render() );

$GLOBALS['opts'] = array( 'show-links-bar' => 'on' );
$GLOBALS['menu_items'] = array();
check( 'assigned but empty prints no shell', '' === render(), 'got: ' . render() );

echo "\nThe index, and nowhere else\n";

$GLOBALS['opts']        = array( 'show-links-bar' => 'on' );
$GLOBALS['menu_exists'] = true;
$GLOBALS['menu_items']  = array( array( 'label' => 'Partner', 'href' => 'https://p.example/' ) );

$GLOBALS['view'] = 'index';
check( 'the index prints the row', false !== strpos( render(), '<nav class="links-bar"' ), render() );

/*
 * Every other request the theme serves: the video a visitor opened, a listing
 * of one term or actor, a search result, a missing address, and an ordinary
 * page - which is what a legal document, the submission form, and every other
 * page an editor writes are. The switch stays on and the menu stays assigned
 * through all of them, so the page is the only thing that differs.
 */
$surfaces = array(
	'single'  => 'a single video page',
	'archive' => 'a category, tag or actor archive',
	'search'  => 'a search result',
	'404'     => 'a missing address',
	'page'    => 'an ordinary page, which is what a legal document and the submission form are',
);

foreach ( $surfaces as $view => $label ) {
	$GLOBALS['view'] = $view;
	check( 'prints nothing on ' . $label, '' === render(), render() );
}

$GLOBALS['view'] = 'index';

echo "\nDefaults come from the options map\n";

$GLOBALS['opts']       = array();
$GLOBALS['menu_items'] = array( array( 'label' => 'P', 'href' => 'https://p.example/' ) );
$html                   = render();
check( 'nothing saved still prints the bar', false !== strpos( $html, 'links-bar' ) );
check( 'new tab on by default', false !== strpos( $html, 'target="_blank"' ) );

echo "\nRenders when switched on with a menu\n";

$GLOBALS['menu_items'] = array(
	array( 'label' => 'Partner One', 'href' => 'https://one.example/' ),
	array( 'label' => 'Partner Two', 'href' => 'https://two.example/' ),
);

$html = render();
check( 'prints a nav landmark', false !== strpos( $html, '<nav class="links-bar"' ) );
check( 'has an accessible name', false !== strpos( $html, 'aria-label="Friends and partners"' ) );
check( 'prints both links', false !== strpos( $html, 'Partner One' ) && false !== strpos( $html, 'Partner Two' ) );
check( 'no visible heading without a label', false === strpos( $html, 'links-bar-label' ) );

echo "\nOptional visible label\n";

$GLOBALS['opts']['links-bar-label'] = 'Friends';
$html                               = render();
check( 'label prints as a heading', false !== strpos( $html, '<span class="links-bar-label">Friends</span>' ) );
check( 'label becomes the nav name', false !== strpos( $html, 'aria-label="Friends"' ) );

unset( $GLOBALS['opts']['links-bar-label'] );

echo "\nAdvertising rel attributes\n";

$atts = majestic_tube_links_bar_link_attributes(
	array( 'href' => 'https://x.example/', 'rel' => '', 'target' => '' ),
	null,
	(object) array( 'theme_location' => 'majestic_tube_links_menu' )
);

check( 'adds sponsored', false !== strpos( $atts['rel'], 'sponsored' ) );
check( 'adds nofollow', false !== strpos( $atts['rel'], 'nofollow' ) );
check( 'adds noopener', false !== strpos( $atts['rel'], 'noopener' ) );
check( 'adds noreferrer', false !== strpos( $atts['rel'], 'noreferrer' ) );

echo "\nScope: only the ad menu is rewritten\n";

$main = majestic_tube_links_bar_link_attributes(
	array( 'href' => '/about/', 'rel' => '', 'target' => '' ),
	null,
	(object) array( 'theme_location' => 'majestic_tube_main_menu' )
);

check( 'main menu untouched', '' === $main['rel'] && '' === $main['target'] );

$footer = majestic_tube_links_bar_link_attributes(
	array( 'href' => '/privacy/', 'rel' => 'nofollow', 'target' => '' ),
	null,
	(object) array( 'theme_location' => 'majestic_tube_footer_menu' )
);

check( 'footer menu keeps its own rel', 'nofollow' === $footer['rel'] );

$no_location = majestic_tube_links_bar_link_attributes(
	array( 'href' => '/x/', 'rel' => '', 'target' => '' ),
	null,
	(object) array()
);

check( 'no theme_location is left alone', '' === $no_location['rel'] );

echo "\nExisting rel tokens are not duplicated\n";

$pre = majestic_tube_links_bar_link_attributes(
	array( 'href' => 'https://x.example/', 'rel' => 'nofollow me', 'target' => '' ),
	null,
	(object) array( 'theme_location' => 'majestic_tube_links_menu' )
);

check( 'keeps the editor tokens', false !== strpos( $pre['rel'], 'me' ) );
check( 'nofollow appears once', 1 === substr_count( $pre['rel'], 'nofollow' ), 'got: ' . $pre['rel'] );

echo "\nNew tab switch\n";

$GLOBALS['opts']['links-bar-new-tab'] = 'off';
$html                                  = render();
check( 'new tab can be turned off', false === strpos( $html, 'target="_blank"' ) );
check( 'rel is still applied without it', false !== strpos( $html, 'sponsored' ) );

$GLOBALS['opts']['links-bar-new-tab'] = 'on';
check( 'new tab can be turned back on', false !== strpos( render(), 'target="_blank"' ) );

echo "\nStyle preset\n";

$GLOBALS['opts'] = array( 'show-links-bar' => 'on' );

check( 'defaults to plain', 'plain' === majestic_tube_links_bar_style() );
check( 'plain carries no emoji', array() === majestic_tube_links_bar_icon_set( 'plain' ) );
check( 'neon carries no emoji', array() === majestic_tube_links_bar_icon_set( 'neon' ) );

foreach ( array( 'stars', 'rockets', 'glam' ) as $style ) {
	$set = majestic_tube_links_bar_icon_set( $style );
	check( $style . ' has an icon set', count( $set ) >= 3 );
}

$GLOBALS['opts']['links-bar-style'] = 'not-a-real-style';
check( 'unknown style falls back to plain', 'plain' === majestic_tube_links_bar_style() );

echo "\nStyle reaches the markup\n";

$GLOBALS['opts']['links-bar-style'] = 'stars';
$html                               = render();
check( 'data-style is printed', false !== strpos( $html, 'data-style="stars"' ) );
check( 'data-shine is printed', false !== strpos( $html, 'data-shine="1"' ) );

$GLOBALS['opts']['links-bar-shine'] = 'off';
check( 'shine can be turned off', false === strpos( render(), 'data-shine' ) );

$GLOBALS['opts']['links-bar-shine'] = 'on';
$GLOBALS['opts']['links-bar-style'] = 'plain';
check( 'plain style is printed', false !== strpos( render(), 'data-style="plain"' ) );

echo "\nEmoji in front of each link\n";

$GLOBALS['opts']['links-bar-style'] = 'stars';
$GLOBALS['menu_items']              = array(
	array( 'label' => 'Alpha', 'href' => 'https://a.example/' ),
	array( 'label' => 'Beta', 'href' => 'https://b.example/' ),
	array( 'label' => 'Gamma', 'href' => 'https://c.example/' ),
	array( 'label' => 'Delta', 'href' => 'https://d.example/' ),
	array( 'label' => 'Epsilon', 'href' => 'https://e.example/' ),
);

$html = render();
$set  = majestic_tube_links_bar_icon_set( 'stars' );
$size = count( $set );

foreach ( array( 'Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon' ) as $index => $label ) {
	check(
		$label . ' gets icon ' . ( $index + 1 ),
		false !== strpos( $html, 'aria-hidden="true">' . $set[ $index % $size ] . '</span>' . $label ),
		'expected ' . $set[ $index % $size ] . ' before ' . $label
	);
}

echo "\nIcon set wraps around\n";

// One more link than the set holds, so the wrap is actually exercised.
// The list is reset first: this section appends to whatever the previous one
// left behind, and an inherited list shifts every index.
$GLOBALS['menu_items'] = array();
$count                 = count( $set );

for ( $i = 0; $i <= $count; $i++ ) {
	$GLOBALS['menu_items'][] = array( 'label' => 'L' . $i, 'href' => 'https://' . $i . '.example/' );
}

$html = render();
check(
	'the glyph after the last unique one repeats the set',
	false !== strpos( $html, $set[0] . '</span>L' . $count ),
	'expected ' . $set[0] . ' before L' . $count
);

echo "\nThe counter resets between renders\n";

$first = render();
$again = render();
check( 'two renders are identical', $first === $again, 'first: ' . $first . ' again: ' . $again );

echo "\nAn icon the editor typed is respected\n";

$GLOBALS['menu_items'] = array( array( 'label' => '🎯 My Partner', 'href' => 'https://p.example/' ) );
$html                    = render();
check( 'no theme icon is stacked on top', 0 === substr_count( $html, 'links-bar-icon' ), 'got: ' . $html );
check( 'the editor emoji survives', false !== strpos( $html, '🎯 My Partner' ) );

check( 'detects a leading emoji', majestic_tube_links_bar_has_own_icon( '🚀 Rocket' ) );
check( 'detects a leading dingbat', majestic_tube_links_bar_has_own_icon( '★ Star' ) );
check( 'plain text is not an icon', ! majestic_tube_links_bar_has_own_icon( 'Partner' ) );
check( 'an emoji later in the label is not leading', ! majestic_tube_links_bar_has_own_icon( 'Partner 🚀' ) );

echo "\nIcons can be switched off\n";

$GLOBALS['opts']['links-bar-icons'] = 'off';
$GLOBALS['menu_items']              = array( array( 'label' => 'Alpha', 'href' => 'https://a.example/' ) );
$html                                = render();
check( 'no icon printed when off', false === strpos( $html, 'links-bar-icon' ) );
check( 'the label is still there', false !== strpos( $html, 'Alpha' ) );

$GLOBALS['opts']['links-bar-icons'] = 'on';

echo "\nNeon gets no emoji but keeps the style\n";

$GLOBALS['opts']['links-bar-style'] = 'neon';
$GLOBALS['menu_items']              = array( array( 'label' => 'Alpha', 'href' => 'https://a.example/' ) );
$html                                = render();
check( 'neon prints no icon span', false === strpos( $html, 'links-bar-icon' ) );
check( 'neon still prints its label', false !== strpos( $html, 'data-style="neon"' ) );

echo "\nThe row label gets an icon too\n";

$GLOBALS['opts']['links-bar-style'] = 'glam';
$GLOBALS['opts']['links-bar-label'] = 'Friends';
$html                               = render();
check( 'label carries the first glyph', false !== strpos( $html, 'links-bar-label"><span class="links-bar-icon" aria-hidden="true">🔥</span>Friends' ) );

echo "\nIcon filter is scoped like the rel filter\n";

$main = majestic_tube_links_bar_item_title(
	'Alpha',
	null,
	(object) array( 'theme_location' => 'majestic_tube_main_menu' ),
	0
);
check( 'main menu title untouched', 'Alpha' === $main );

$child = majestic_tube_links_bar_item_title(
	'Alpha',
	null,
	(object) array( 'theme_location' => 'majestic_tube_links_menu' ),
	1
);
check( 'sub-item untouched', 'Alpha' === $child );

echo "\n" . str_repeat( '-', 46 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

if ( $failed ) {
	exit( 1 );
}

echo "All checks passed.\n";
