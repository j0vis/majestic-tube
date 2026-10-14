<?php
/**
 * Smoke test for the site-wide icon set.
 *
 * The two halves of this feature can disagree silently. PHP prints a class name
 * and CSS decides which mask sits behind it, and nothing in WordPress connects
 * the two - a name in the list with no mask behind it renders as a box
 * painted in nothing, which looks like a hole where a button should be. This
 * test reads the real inc/icons.php and the real stylesheet and checks they
 * agree, then exercises the helper against the escaping and the fallbacks.
 *
 * Run:  php tests/icons-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

$GLOBALS['opts']     = array();
$GLOBALS['icons_on'] = true;

function __( $s, $d = null ) {
	return $s;
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function sanitize_html_class( $s ) {
	$s = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $s );
	return trim( $s );
}
function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {}
function apply_filters( $hook, $value ) {
	return $value;
}
function majestic_tube_option_is_on( $key ) {
	if ( 'show-icons' === $key ) {
		return (bool) $GLOBALS['icons_on'];
	}
	return false;
}

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
 * Read a theme file with its line endings normalized to "\n".
 *
 * The patterns below are anchored with ^ and $ under /m, which in PCRE match
 * around a \n and not around a \r\n. A release archive carries CRLF whatever
 * the repository stores, so an anchored pattern would read the line
 * ".mt-icon-star {\r" and miss the rule that is plainly in the file. The
 * checks themselves are unchanged; only the line endings leave the question.
 *
 * @param string $relative Path relative to the theme root.
 * @return string
 */
function read_theme_file( $relative ) {
	$text = file_get_contents( MAJESTIC_TUBE_DIR . '/' . $relative );

	return false === $text ? '' : str_replace( array( "\r\n", "\r" ), "\n", $text );
}

/*
 * Pull the two real files apart and load the helper under test. inc/icons.php
 * needs nothing from WordPress beyond the escaping stubs above.
 */
$css = read_theme_file( 'assets/css/main.css' );

preg_match_all( '/^\t--mt-icon-([a-z-]+): url/m', $css, $css_names );
$css_names = $css_names[1];

// The mask body of each icon, used to prove the shapes are not copies.
preg_match_all( '/^\t--mt-icon-([a-z-]+): url\("([^"]+)"\);/m', $css, $css_bodies );
$bodies = array_combine( $css_bodies[1], $css_bodies[2] );

require MAJESTIC_TUBE_DIR . '/inc/icons.php';

echo "The set agrees with the stylesheet\n";

$php_names = majestic_tube_icon_names();

check(
	'every PHP icon name has a mask in the CSS',
	0 === count( array_diff( $php_names, $css_names ) ),
	'missing: ' . implode( ', ', array_diff( $php_names, $css_names ) )
);

check(
	'every mask in the CSS is reachable from PHP',
	0 === count( array_diff( $css_names, $php_names ) ),
	'unreachable: ' . implode( ', ', array_diff( $css_names, $php_names ) )
);

check(
	'every icon has a .mt-icon-<name> rule',
	preg_match_all( '/^\.mt-icon-[a-z-]+ \{$/m', $css ) === count( $css_names ),
	'the set declares ' . count( $css_names ) . ' icons'
);

echo "\nNo two names share a shape\n";

check(
	'every icon body is unique',
	count( array_unique( $bodies ) ) === count( $bodies ),
	'duplicates: ' . implode(
		', ',
		array_keys( array_filter( array_count_values( $bodies ), static fn( $n ) => $n > 1 ) )
	)
);

echo "\nEvery path is a real path\n";

$malformed = array();
foreach ( $css_names as $name ) {
	$body = $bodies[ $name ];

	// Unescape the handful of characters the CSS author had to escape.
	$decoded = str_replace(
		array( '%3C', '%3E', '%23', '%22', '%25' ),
		array( '<', '>', '#', '"', '%' ),
		$body
	);

	if ( ! preg_match( "#^data:image/svg\+xml,<svg[^>]*viewBox='0 0 24 24'><path d='([^']+)'/></svg>$#", $decoded, $m ) ) {
		$malformed[] = $name . ' (not a single-path 24x24 svg)';
		continue;
	}

	$path = $m[1];

	// A path needs a command and a coordinate. Anything else paints nothing.
	if ( ! preg_match( '/^[Mm]/', $path ) || ! preg_match( '/[0-9]/', $path ) ) {
		$malformed[] = $name . ' (no drawable command)';
		continue;
	}

	/*
	 /*
	 * NaN, Infinity, a doubled decimal point, a comma where a command belongs
	 * and a command with nothing to draw are the ways a hand-copied path
	 * actually breaks, and each renders as an empty box.
	 *
	 * Two things that look broken but are not: a letter followed by a digit,
	 * which SVG treats as an implicit command repeat ("L20.49 19", "H5"), and
	 * a decimal point followed by a number, which is just the next coordinate.
	 */
	if ( preg_match( '/NaN|Infinity|\d\.\.|[,]\s*[a-zA-Z]|\s[-,]\s/', $path ) ) {
		$malformed[] = $name . ' (malformed path data)';
	}

	// Arc flags are single characters in [01]; anything else is a typo.
	if ( preg_match_all( '/[Aa]([^A-Za-z]*)/', $path, $arcs ) ) {
		foreach ( $arcs[1] as $args ) {
			if ( ! preg_match( '/^[-\d.\s]*[01][-\d.\s]*[01][-\d.\s]+$/', $args ) ) {
				$malformed[] = $name . ' (bad arc flags)';
				break;
			}
		}
	}
}

check( 'all ' . count( $css_names ) . ' paths are well formed', 0 === count( $malformed ), implode( '; ', $malformed ) );

echo "\nThe helper prints what the CSS expects\n";

$icon = majestic_tube_get_icon( 'search' );

check( 'carries the base class', false !== strpos( $icon, 'mt-icon' ) );
check( 'carries the named class', false !== strpos( $icon, 'mt-icon-search' ) );
check( 'is hidden from assistive technology', false !== strpos( $icon, 'aria-hidden="true"' ) );

$extra = majestic_tube_get_icon( 'heart', 'my-class' );
check( 'accepts an extra class', false !== strpos( $extra, 'my-class' ) );
check( 'keeps the named class alongside it', false !== strpos( $extra, 'mt-icon-heart' ) );

echo "\nAn unknown name returns nothing\n";

check( 'unknown name is an empty string', '' === majestic_tube_get_icon( 'definitely-not-an-icon' ) );
check( 'empty name is an empty string', '' === majestic_tube_get_icon( '' ) );
check( 'an empty string prints no box', '' === majestic_tube_get_icon( 'definitely-not-an-icon', 'x' ) );

echo "\nA name from user input cannot inject markup\n";

$hostile = majestic_tube_get_icon( 'search" onload="alert(1)' );
check( 'a quote in the name cannot break out of the attribute', false === strpos( $hostile, '" onload' ) );
check( 'or reach the class attribute at all', false === strpos( $hostile, 'onload' ) );

$hostile_class = majestic_tube_get_icon( 'search', 'a"><script>alert(1)</script>' );
check( 'a hostile extra class is escaped', false === strpos( $hostile_class, '<script>' ) );
check( 'and no unescaped quote survives in the class attribute', 1 === preg_match_all( '/"/', $hostile_class ) || false !== strpos( $hostile_class, '&quot;' ) );

echo "\nEvery sort tab has its own icon\n";

$sort_slugs = array( 'latest', 'most-viewed', 'longest', 'popular', 'random' );
$sort_icons = array();

foreach ( $sort_slugs as $slug ) {
	$markup = majestic_tube_sort_icon( $slug );
	preg_match( '/mt-icon-([a-z-]+)/', $markup, $m );
	$sort_icons[ $slug ] = isset( $m[1] ) ? $m[1] : null;

	check( "$slug has an icon", '' !== $markup );
}

check(
	'no two sort tabs share an icon, so the tabs can be told apart',
	count( array_unique( array_filter( $sort_icons ) ) ) === count( $sort_slugs ),
	implode( ', ', $sort_icons )
);

check( 'an unknown sort slug returns nothing', '' === majestic_tube_sort_icon( 'not-a-sort' ) );

echo "\nThe player controls only print names the set knows\n";

/*
 * The player controls are built by main.js, so they cannot go through the PHP
 * helper. They carry their own allowlist instead, and the two are allowed to
 * disagree in one direction only: anything the script can print must exist in
 * the theme's set, or a control silently loses its mark.
 */
$js       = read_theme_file( 'assets/js/main.js' );
$player   = read_theme_file( 'assets/css/player.css' );
$card     = read_theme_file( 'template-parts/content-video-card.php' );
$term     = read_theme_file( 'inc/term-images.php' );
$widget   = read_theme_file( 'inc/widget-videos.php' );
$assets   = read_theme_file( 'inc/assets.php' );

preg_match( '/var ICON_NAMES = \[(.*?)\];/s', $js, $m );
$js_names = preg_match_all( '/\'([a-z-]+)\'/', $m[1] ?? '', $mm ) ? $mm[1] : array();

check( 'main.js declares the names it can print', count( $js_names ) > 0 );

check(
	'every name main.js can print is in the PHP list',
	0 === count( array_diff( $js_names, $php_names ) ),
	'not in the set: ' . implode( ', ', array_diff( $js_names, $php_names ) )
);

check(
	'every name main.js can print has a mask in the CSS',
	0 === count( array_diff( $js_names, $css_names ) ),
	'no mask: ' . implode( ', ', array_diff( $js_names, $css_names ) )
);

check(
	'the player controls actually use the helper',
	(bool) preg_match_all( '/prependIcon\(\s*\w+,\s*\'[a-z-]+\'/', $js )
);

check(
	'the localised options tell the script whether icons are on',
	(bool) preg_match( '/\'icons\'\s*=>\s*majestic_tube_icons_enabled\(\)/', $assets )
);

echo "\nThe cards and the player are covered\n";

/*
 * A check per surface rather than a count, so a name that quietly stops being
 * used is reported as the surface it went missing from.
 */
$surfaces = array(
	'grid card duration badge'   => array( $card, "/majestic_tube_icon\( 'clock' \)/" ),
	'grid card views badge'      => array( $card, "/majestic_tube_icon\( 'eye' \)/" ),
	'grid card rating badge'     => array( $card, "/majestic_tube_icon\( 'star' \)/" ),
	'grid card empty thumbnail'  => array( $card, "/majestic_tube_icon\( 'image' \)/" ),
	'term card empty thumbnail'  => array( $term, "/video-card-placeholder.*?majestic_tube_icon\( 'image' \)/s" ),
	'term card video count'      => array( $term, "/majestic_tube_icon\( 'film' \)/" ),
	'widget duration badge'      => array( $widget, "/majestic_tube_get_icon\( 'clock' \)/" ),
	'widget views'               => array( $widget, "/majestic_tube_icon\( 'eye' \)/" ),
);

foreach ( $surfaces as $label => $surface ) {
	check( "the $label carries an icon", (bool) preg_match( $surface[1], $surface[0] ) );
}

check(
	'the quality, speed, theater and resume controls are all wired',
	(bool) preg_match( '/prependIcon\(\s*button,\s*\'sliders\'/', $js )
	&& (bool) preg_match( '/prependIcon\(\s*button,\s*\'gauge\'/', $js )
	&& (bool) preg_match( '/prependIcon\(\s*button,\s*\'expand\'/', $js )
	&& (bool) preg_match( '/prependIcon\(\s*resume,\s*\'play\'/', $js )
	&& (bool) preg_match( '/prependIcon\(\s*dismiss,\s*\'close\'/', $js )
);

check(
	'no control keeps a hand-drawn ::before glyph any more',
	! preg_match( '/\.mt-theater-toggle::before\s*\{/', $player ),
	'the theater mark is still drawn in player.css'
);

check(
	'the dismiss button falls back to a character only when icons are off',
	(bool) preg_match( "#if \( icon\( 'close' \) \) \{\s*prependIcon\( dismiss, 'close' \);#", $js )
	&& (bool) preg_match( "#\} else \{\s*dismiss\.textContent = '.{1,4}';#u", $js )
);

check(
	'the menu rows reserve the check, so the labels stay in a column',
	(bool) preg_match( '/\.mt-quality-item \.mt-icon\s*\{\s*opacity:\s*0/', $player )
	&& (bool) preg_match( '/\.mt-speed-item \.mt-icon\s*\{\s*opacity:\s*0/', $player )
);

check(
	'every player control is laid out to hold an icon',
	(bool) preg_match( '/\.mt-quality-toggle\s*\{[^}]*display:\s*inline-flex/', $player )
	&& (bool) preg_match( '/\.mt-speed-toggle\s*\{[^}]*display:\s*inline-flex/', $player )
	&& (bool) preg_match( '/\.mt-resume-play\s*\{[^}]*display:\s*inline-flex/', $player )
);

echo "\nA child theme can add shapes of its own\n";

// Registration is one-way and lives in a static, so the empty case has to be
// asked before anything is registered rather than after.
check( 'nothing registered means no stylesheet at all', '' === majestic_tube_registered_icons_css() );

$majestic_tube_path = 'M12 2 2 22h20L12 2zm0 5 5 11H7l5-11z';
$majestic_tube_wide = array(
	'path'    => 'M2 10h4v8H2v-8zM10 10h4v8h-4v-8zM18 10h4v8h-4v-8z',
	'viewbox' => '0 0 32 24',
);

// Everything here that should not survive. Listed inline so that a rule that
// stops rejecting one of them fails a check rather than passing quietly.
$majestic_tube_refused = array(
	'bad name'      => $majestic_tube_path,
	'Bad-Name'      => $majestic_tube_path,
	'1starts-digit' => $majestic_tube_path,
	'injection'     => 'M0 0"/**/{background:url(javascript:alert(1))}',
	'no-moveto'     => 'L1 1 2 2',
	'no-digits'     => 'MmMmM',
	'zero-viewbox'  => array( 'path' => $majestic_tube_path, 'viewbox' => '0 0 0 24' ),
	'short-viewbox' => array( 'path' => $majestic_tube_path, 'viewbox' => '0 0 24' ),
	'word-viewbox'  => array( 'path' => $majestic_tube_path, 'viewbox' => 'a b c d' ),
	'not-a-shape'   => 42,
);

majestic_tube_register_icons( array_merge(
	array(
		'brand-mark'    => $majestic_tube_path,
		'wide-wordmark' => $majestic_tube_wide,
	),
	$majestic_tube_refused
) );

$registered = majestic_tube_registered_icons();

check(
	'the two valid icons registered',
	array( 'brand-mark', 'wide-wordmark' ) === array_keys( $registered ),
	implode( ', ', array_keys( $registered ) )
);

check(
	'every malformed entry was refused',
	0 === count( array_intersect( array_keys( $majestic_tube_refused ), array_keys( $registered ) ) ),
	'leaked: ' . implode( ', ', array_intersect( array_keys( $majestic_tube_refused ), array_keys( $registered ) ) )
);

check(
	'a registered name is printable',
	in_array( 'brand-mark', majestic_tube_icon_names(), true )
);

check(
	'and does not leak into the theme list the generator cross-checks',
	! in_array( 'brand-mark', majestic_tube_theme_icon_names(), true )
	&& 43 === count( majestic_tube_theme_icon_names() )
);

$markup = majestic_tube_get_icon( 'brand-mark' );
check( 'the markup carries the named class', false !== strpos( $markup, 'mt-icon-brand-mark' ) );
check( 'and stays hidden from assistive technology', false !== strpos( $markup, 'aria-hidden="true"' ) );

check( 'a refused name prints nothing', '' === majestic_tube_get_icon( 'injection' ) );
check( 'and so does a name nobody registered', '' === majestic_tube_get_icon( 'never-registered' ) );

check(
	'the custom viewbox is kept',
	isset( $registered['wide-wordmark'] ) && '0 0 32 24' === $registered['wide-wordmark']['viewbox']
);

$icon_css = majestic_tube_registered_icons_css();

check(
	'the stylesheet declares a property and a rule per icon',
	(bool) preg_match( '/--mt-icon-brand-mark: url\("data:image\/svg\+xml,/', $icon_css )
	&& (bool) preg_match( '/^\.mt-icon-brand-mark \{$/m', $icon_css )
	&& (bool) preg_match( '/^\.mt-icon-wide-wordmark \{$/m', $icon_css )
);

check(
	'each rule paints in currentColor and masks on both engines',
	(bool) preg_match( '/\.mt-icon-brand-mark \{[^}]*background-color: currentColor;[^}]*-webkit-mask-image: var\(--mt-icon-brand-mark\);[^}]*mask-image: var\(--mt-icon-brand-mark\);[^}]*\}/s', $icon_css )
);

check( 'the block has balanced braces', substr_count( $icon_css, '{' ) === substr_count( $icon_css, '}' ) );
check( 'four braces per icon: a :root and a rule', 4 === substr_count( $icon_css, '{' ) );

check(
	'the refused injection left no trace in the stylesheet',
	false === strpos( $icon_css, 'javascript' )
	&& false === strpos( $icon_css, 'background:url' )
	&& false === strpos( $icon_css, 'no-moveto' )
	&& false === strpos( $icon_css, 'zero-viewbox' )
);

echo "\nThe PHP escaper agrees with the generator\n";

/*
 * tools/build-icon-set.py builds the theme's own masks and inc/icons.php builds
 * a child theme's. Two implementations of one escaping rule is the only place
 * the two halves of this feature can quietly diverge, so it is checked against
 * the stylesheet the generator actually wrote rather than against a fixture.
 */
$generated = '';
$star_path = '';

if ( preg_match( '/--mt-icon-star: (url\("data:image\/svg\+xml,[^"]+"\));/', $css, $m ) ) {
	$generated = $m[1];
	preg_match( "/viewBox='[^']*'><path d='([^']+)'/", urldecode( $generated ), $p );
	$star_path = isset( $p[1] ) ? $p[1] : '';
}

check( 'the star mask was found in the stylesheet', '' !== $generated && '' !== $star_path );

check(
	'and the PHP escaper reproduces it byte for byte',
	$generated === majestic_tube_icon_mask_url( $star_path ),
	"generator: $generated\nphp      : " . majestic_tube_icon_mask_url( $star_path )
);

check(
	'a percent sign in the payload is escaped, not left to break the url token',
	false !== strpos( majestic_tube_icon_mask_url( 'M0 0h100%' ), '%25' )
);

echo "\nA child theme can replace a shape the theme ships\n";

majestic_tube_register_icons( array( 'star' => 'M12 2 3 22h18L12 2z' ) );
$override_css = majestic_tube_registered_icons_css();

check( 'the override is accepted', isset( majestic_tube_registered_icons()['star'] ) );
check( 'the star is still one of the printable names', in_array( 'star', majestic_tube_icon_names(), true ) );
check( 'and is still one name, not two', 1 === count( array_keys( majestic_tube_icon_names(), 'star', true ) ) );
check(
	'the override declares the same variable main.css does, so it wins',
	(bool) preg_match( '/--mt-icon-star: url\("data:image\/svg\+xml,[^"]*M12 2 3 22h18L12 2z/', $override_css )
);

echo "\nThe off switch\n";

check( 'icons are on by default', majestic_tube_icons_enabled() );

$GLOBALS['icons_on'] = false;
check( 'the option turns them off', ! majestic_tube_icons_enabled() );

echo "\nThe stylesheet can hide the whole set\n";

check(
	'a .no-icons rule hides every icon at once',
	(bool) preg_match( '/\.no-icons \.mt-icon,\s*\n?\.no-icons \.fa\[class\*="fa-"\]/', $css )
	&& (bool) preg_match( '/\.no-icons \.fa\[class\*="fa-"\]\s*\{\s*display:\s*none/', $css )
);

check(
	'the hide rule covers the legacy classes too',
	(bool) preg_match( '/\.no-icons \.fa\[class\*="fa-"\]\s*\{[^}]*display:\s*none/', $css )
);

check(
	'the hide rule reaches the player controls too, since they load the same set',
	(bool) preg_match( '/\.no-icons \.mt-icon/', $player )
	|| (bool) preg_match( '/\.no-icons \.mt-icon/', $css )
);

echo "\nThe supersession is complete\n";

// Any surviving raw Unicode ::before on an icon class would paint a glyph on
// top of a mask and clip it, which is the exact bug this set set out to fix.
$leftover = array();
foreach ( $css_names as $name ) {
	if ( preg_match( '/\.fa-[a-z-]+\s*::before\s*\{[^}]*content:/', $css ) ) {
		$leftover[] = 'fa-*::before';
		break;
	}
}

$share_letters = preg_match_all( '/#majestic-tube-share-[a-z]+::before/', $css );

check(
	'the share letters are the only ::before marks left',
	0 === count( $leftover ),
	implode( ', ', array_unique( $leftover ) )
);

check( 'all eight share letters survive', 8 === $share_letters, "found $share_letters" );

check(
	'share letters are excluded from the mask',
	(bool) preg_match( '/\.sharing-buttons \.fa\s*\{[^}]*mask-image:\s*none/', $css )
);

echo "\n" . str_repeat( '-', 44 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

exit( $failed ? 1 : 0 );