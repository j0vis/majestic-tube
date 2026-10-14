<?php
/**
 * Smoke test for the color system and the menu button.
 *
 * Four claims are worth pinning, because each one is easy to undo by accident:
 *   1. every accent role the theme prints clears a contrast target for any
 *      color an administrator can pick, in both skins - not just for the one
 *      the theme ships with;
 *   2. the defaults declared in the stylesheet are the ones the PHP derives for
 *      the shipped accent, so the fallback and the printed block cannot drift;
 *   3. the stylesheet paints each role with the token meant for it - text and
 *      icons never take the button-fill color, and nothing paints white text on
 *      a fill without going through --mt-accent-fg;
 *   4. the menu button draws one shape, and only one, at every width.
 *
 * inc/custom-code.php and inc/theme-options.php are loaded for real against
 * stubs, so the derivations and the option map under test are the shipping
 * ones rather than a copy of them.
 *
 * Run:  php tests/design-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );
define( 'MAJESTIC_TUBE_VERSION', '2.5.0' );

$GLOBALS['opts']   = array();
$GLOBALS['mods']   = array();
$GLOBALS['hooks']  = array();
$GLOBALS['brand']  = '';

function __( $s, $d = null ) {
	return $s;
}
function absint( $value ) {
	return abs( (int) $value );
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
function esc_attr_e( $s, $d = null ) {
	echo htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function wp_json_encode( $value ) {
	return json_encode( $value );
}
function add_action( $hook, $cb, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook . '::' . ( is_string( $cb ) ? $cb : 'closure' ) ] = $priority;
}
function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook . '::' . ( is_string( $cb ) ? $cb : 'closure' ) ] = $priority;
}
function apply_filters( $hook, $value ) {
	return $value;
}
function wp_strip_all_tags( $string ) {
	return strip_tags( (string) $string );
}
function sanitize_hex_color( $color ) {
	if ( ! is_string( $color ) ) {
		return null;
	}

	$color = trim( $color );

	return preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color ) ? $color : null;
}

/*
 * The option chain, stubbed at the WordPress end rather than at the theme end:
 * the theme's own majestic_tube_get_option() runs, so the test exercises the
 * real Customizer, legacy row and declared-default priority.
 */
function get_theme_mod( $name, $default = false ) {
	return isset( $GLOBALS['mods'][ $name ] ) ? $GLOBALS['mods'][ $name ] : $default;
}
function get_option( $name, $default = false ) {
	if ( 'wpst-options' !== $name ) {
		return $default;
	}

	return array_merge( $GLOBALS['opts'], (array) $GLOBALS['mods'] );
}

require MAJESTIC_TUBE_DIR . '/inc/custom-code.php';
require MAJESTIC_TUBE_DIR . '/inc/theme-options.php';

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */

$passed = 0;
$failed = 0;

function check( $label, $ok, $context = '' ) {
	global $passed, $failed;

	if ( $ok ) {
		++$passed;
		printf( "  [ok]   %s\n", $label );

		return;
	}

	++$failed;
	printf( "  [FAIL] %s%s\n", $label, $context ? "\n         {$context}" : '' );
}

function theme_file( $relative ) {
	return file_get_contents( MAJESTIC_TUBE_DIR . '/' . $relative );
}

/**
 * Split a stylesheet into rules.
 *
 * At-rule preludes are stripped, which loses which breakpoint a rule came from
 * but keeps each rule's selectors and declarations intact - which is all these
 * checks need.
 *
 * @param string $css Stylesheet.
 * @return string[] Rule strings in "selectors { declarations" form.
 */
function css_rules( $css ) {
	$css = preg_replace( '!/\*.*?\*/!s', '', $css );
	$css = preg_replace( '/@media[^{]*\{/', '', $css );
	$css = preg_replace( '/@[a-z-]+[^{]*\{/', '', $css );

	return array_values( array_filter( array_map( 'trim', explode( '}', $css ) ) ) );
}

function rules_matching( $rules, $needle ) {
	return array_values(
		array_filter(
			$rules,
			function ( $rule ) use ( $needle ) {
				return false !== strpos( $rule, $needle );
			}
		)
	);
}

/**
 * The rules that name a selector exactly.
 *
 * A substring match folds ".logo-footer img" into ".logo-footer", and every
 * check below is about which box declares what, so the selector has to be its
 * own entry in the selector list rather than part of a longer one.
 *
 * @param string[] $rules    Rules in "selectors { declarations" form.
 * @param string   $selector Selector to find.
 * @return string[] Matching rules.
 */
function rules_for( $rules, $selector ) {
	return array_values(
		array_filter(
			$rules,
			function ( $rule ) use ( $selector ) {
				$parts = explode( '{', $rule, 2 );

				if ( 2 !== count( $parts ) ) {
					return false;
				}

				return in_array( $selector, array_map( 'trim', explode( ',', $parts[0] ) ), true );
			}
		)
	);
}

/**
 * The declarations of every rule naming one selector.
 *
 * @param string[] $rules    Rules in "selectors { declarations" form.
 * @param string   $selector Selector to find.
 * @return string[] Declaration blocks.
 */
function declarations_for( $rules, $selector ) {
	$blocks = array();

	foreach ( rules_for( $rules, $selector ) as $rule ) {
		$parts    = explode( '{', $rule, 2 );
		$blocks[] = $parts[1];
	}

	return $blocks;
}

/**
 * Whether one of a selector's declaration blocks sets a property to a value.
 *
 * @param string[] $blocks   Declaration blocks.
 * @param string   $property Property name.
 * @param string   $value    Value to look for, without its semicolon.
 * @return bool
 */
function declares( $blocks, $property, $value ) {
	foreach ( (array) $blocks as $block ) {
		if ( preg_match( '/\b' . preg_quote( $property, '/' ) . '\s*:\s*' . preg_quote( $value, '/' ) . '\b/i', $block ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Whether any block sets a property to one of several values.
 *
 * @param string[] $blocks   Declaration blocks.
 * @param string   $property Property name.
 * @param string[] $values   Values to look for.
 * @return bool
 */
function declares_any( $blocks, $property, $values ) {
	foreach ( (array) $values as $value ) {
		if ( declares( $blocks, $property, $value ) ) {
			return true;
		}
	}

	return false;
}

$main_css   = theme_file( 'assets/css/main.css' );
$player_css = theme_file( 'assets/css/player.css' );
$editor_css = theme_file( 'assets/css/editor.css' );
$rtl_css    = theme_file( 'assets/css/main-rtl.css' );

/* -------------------------------------------------------------------------
 * 1. The derivation
 * ---------------------------------------------------------------------- */

echo "\nThe accent derivation\n";

$shipped       = '#0e7b8a';
$light_page    = majestic_tube_hex_channels( '#f4f5f7' );
$light_card    = majestic_tube_hex_channels( '#ffffff' );
$dark_card     = majestic_tube_hex_channels( '#171b21' );
$dark_page     = majestic_tube_hex_channels( '#0f1216' );
$video_black   = majestic_tube_hex_channels( '#000000' );

/*
 * A spread rather than a couple of samples: a mid-tone brand color, a pale one
 * whose fill cannot carry white text, a near-black one that cannot read as a
 * link on a dark page, and two saturated hues.
 */
$accents = array( $shipped, '#dd3333', '#ffe066', '#123456', '#a855f7', '#ff7a00' );

$shortest = array(
	'fill text'      => 99,
	'light text'     => 99,
	'dark text'      => 99,
	'video text'     => 99,
	'hover light'    => 99,
	'hover dark'     => 99,
	'fill text hover' => 99,
);

foreach ( $accents as $accent ) {
	$rgb        = majestic_tube_hex_channels( $accent );
	$foreground = majestic_tube_fill_foreground( $rgb );
	$text_light = majestic_tube_contrast_safe( $rgb, $light_page, 5.0 );
	$text_dark  = majestic_tube_contrast_safe( $rgb, $dark_card, 7.0 );
	$hover_light = majestic_tube_contrast_safe( $rgb, $light_page, min( 21, max( 6.2, majestic_tube_contrast_ratio( $text_light, $light_page ) + 1.2 ) ) );
	$hover_dark  = majestic_tube_contrast_safe( $rgb, $dark_card, min( 21, max( 8.2, majestic_tube_contrast_ratio( $text_dark, $dark_card ) + 1.2 ) ) );
	$fill_hover = majestic_tube_contrast_safe( $rgb, $foreground, min( 21, majestic_tube_contrast_ratio( $rgb, $foreground ) + 0.5 ) );

	$ratios = array(
		'fill text'       => majestic_tube_contrast_ratio( $foreground, $rgb ),
		'light text'      => min(
			majestic_tube_contrast_ratio( $text_light, $light_page ),
			majestic_tube_contrast_ratio( $text_light, $light_card )
		),
		'dark text'       => min(
			majestic_tube_contrast_ratio( $text_dark, $dark_card ),
			majestic_tube_contrast_ratio( $text_dark, $dark_page )
		),
		'video text'      => majestic_tube_contrast_ratio( $text_dark, $video_black ),
		'hover light'     => majestic_tube_contrast_ratio( $hover_light, $light_page ),
		'hover dark'      => majestic_tube_contrast_ratio( $hover_dark, $dark_card ),
		'fill text hover' => majestic_tube_contrast_ratio( $foreground, $fill_hover ),
	);

	foreach ( $ratios as $role => $ratio ) {
		$shortest[ $role ] = min( $shortest[ $role ], $ratio );
	}

	check(
		"{$accent}: fill text, light text, dark text, video text, both hovers all clear AA",
		min( $ratios ) >= 4.5,
		sprintf( 'lowest ratio %.2f:1 (%s)', min( $ratios ), implode( ', ', array_keys( $ratios, min( $ratios ), true ) ) )
	);

	check(
		"{$accent}: the hovered text is further from its surface than the resting text",
		$ratios['hover light'] > majestic_tube_contrast_ratio( $text_light, $light_page ) - 0.001
		&& $ratios['hover dark'] > majestic_tube_contrast_ratio( $text_dark, $dark_card ) - 0.001,
		sprintf( 'light %.2f vs %.2f, dark %.2f vs %.2f', $ratios['hover light'], majestic_tube_contrast_ratio( $text_light, $light_page ), $ratios['hover dark'], majestic_tube_contrast_ratio( $text_dark, $dark_card ) )
	);

	check(
		"{$accent}: the hovered fill is a step away from the resting fill",
		majestic_tube_channels_hex( $fill_hover ) !== majestic_tube_channels_hex( $rgb )
		|| majestic_tube_contrast_ratio( $rgb, $foreground ) >= 21,
		sprintf( 'fill %s, hover %s', majestic_tube_channels_hex( $rgb ), majestic_tube_channels_hex( $fill_hover ) )
	);
}

check(
	'every role clears its own target for every accent tested',
	min( $shortest ) >= 4.5,
	sprintf( 'lowest across the set: %.2f:1 (%s)', min( $shortest ), implode( ', ', array_keys( $shortest, min( $shortest ), true ) ) )
);

check( 'a pale accent takes dark text on its fill', '#0f1216' === majestic_tube_channels_hex( majestic_tube_fill_foreground( majestic_tube_hex_channels( '#ffe066' ) ) ) );
check( 'a deep accent keeps white text on its fill', '#ffffff' === majestic_tube_channels_hex( majestic_tube_fill_foreground( majestic_tube_hex_channels( '#123456' ) ) ) );

$shipped_hsl = majestic_tube_rgb_to_hsl( majestic_tube_hex_channels( $shipped ) );
$derived_hsl = majestic_tube_rgb_to_hsl( majestic_tube_contrast_safe( majestic_tube_hex_channels( $shipped ), $light_page, 5.0 ) );
check(
	'the derived tone keeps the hue and the saturation of the chosen color',
	abs( $shipped_hsl['h'] - $derived_hsl['h'] ) < 0.005 && abs( $shipped_hsl['s'] - $derived_hsl['s'] ) < 0.01,
	sprintf( 'hue %.4f vs %.4f, saturation %.4f vs %.4f', $shipped_hsl['h'], $derived_hsl['h'], $shipped_hsl['s'], $derived_hsl['s'] )
);

check( 'a color that already clears its target is returned untouched', '#123456' === majestic_tube_channels_hex( majestic_tube_contrast_safe( majestic_tube_hex_channels( '#123456' ), $light_page, 5.0 ) ) );
check(
	'a three-digit hex expands to the same channels as its six-digit form',
	majestic_tube_hex_channels( '#fff' ) === majestic_tube_hex_channels( '#ffffff' )
);
check( 'a non-color is refused rather than guessed at', null === majestic_tube_hex_channels( 'not a color' ) );

/* -------------------------------------------------------------------------
 * 2. The printed option block
 * ---------------------------------------------------------------------- */

echo "\nThe printed accent block\n";

$GLOBALS['opts'] = array( 'main-color' => $shipped );

ob_start();
majestic_tube_output_brand_css();
$printed = ob_get_clean();

check( 'the block is printed as a root style element', false !== strpos( $printed, '<style id="majestic-tube-brand">' ) && false !== strpos( $printed, ':root{' ) );
check( 'it declares the fill', false !== strpos( $printed, '--mt-accent:' . $shipped ) );
check( 'it declares the fill as a triple too', false !== strpos( $printed, '--mt-accent-rgb:14,123,138' ) );
check( 'it declares the text painted on the fill', false !== strpos( $printed, '--mt-accent-fg:#ffffff' ) );
check( 'it declares a hovered fill', false !== strpos( $printed, '--mt-accent-hover:#0d7381' ) );
check( 'it declares the accent as text for both skins', false !== strpos( $printed, '--mt-accent-text:light-dark(#0d7381,#15b9cf)' ) );
check( 'it declares the hovered accent text for both skins', false !== strpos( $printed, '--mt-accent-text-hover:light-dark(#0b626e,#17c9e2)' ) );
check( 'it declares the accent for the always-dark player chrome', false !== strpos( $printed, '--mt-accent-on-dark:#15b9cf' ) );
check( 'it keeps the old name in step for child themes', false !== strpos( $printed, '--mt-accent-dark:light-dark(#0b626e,#17c9e2)' ) );
check( 'the printed block is one style element and nothing else', 1 === substr_count( $printed, '<style' ) && 1 === substr_count( $printed, '</style>' ) );

$GLOBALS['opts'] = array( 'main-color' => '#dd3333' );
ob_start();
majestic_tube_output_brand_css();
$printed_red = ob_get_clean();

check(
	'a different accent moves every derived tone with it',
	false !== strpos( $printed_red, '--mt-accent-text:light-dark(#cb2222,#ec8a8a)' )
	&& false !== strpos( $printed_red, '--mt-accent-on-dark:#ec8a8a' )
	&& false === strpos( $printed_red, '#0d7381' ),
	$printed_red
);

$GLOBALS['opts'] = array( 'main-color' => 'javascript:alert(1)' );
ob_start();
majestic_tube_output_brand_css();
$printed_bad = ob_get_clean();

check( 'a junk value leaves the accent tokens out entirely rather than printing them', false === strpos( $printed_bad, '--mt-accent:' ) );
check( 'and the rest of the option block still prints', false !== strpos( $printed_bad, '--mt-font-family' ) );

$GLOBALS['opts'] = array();

/* -------------------------------------------------------------------------
 * 3. The stylesheet and the derivation agree
 * ---------------------------------------------------------------------- */

echo "\nThe stylesheet defaults\n";

/**
 * Read a custom property out of the :root block.
 *
 * @param string $css  Stylesheet.
 * @param string $name Property name.
 * @return string Raw value, or '' when the property is not declared.
 */
function root_token( $css, $name ) {
	if ( ! preg_match( '/:root\s*\{(.*?)\n\}/s', $css, $m ) ) {
		return '';
	}

	$values = array();

	if ( preg_match_all( '/^\s*' . preg_quote( $name, '/' ) . '\s*:\s*(.+?);\s*$/m', $m[1], $found ) ) {
		foreach ( $found[1] as $value ) {
			$values[] = trim( $value );
		}
	}

	// The last declaration wins in CSS, and it is the light-dark() pair.
	return $values ? end( $values ) : '';
}

$default_rgb    = majestic_tube_hex_channels( $shipped );
$default_text   = majestic_tube_contrast_safe( $default_rgb, $light_page, 5.0 );
$default_dark   = majestic_tube_contrast_safe( $default_rgb, $dark_card, 7.0 );
$default_fg     = majestic_tube_fill_foreground( $default_rgb );
$default_fill_h = majestic_tube_contrast_safe( $default_rgb, $default_fg, min( 21, majestic_tube_contrast_ratio( $default_rgb, $default_fg ) + 0.5 ) );
$default_hov_l  = majestic_tube_contrast_safe( $default_rgb, $light_page, min( 21, max( 6.2, majestic_tube_contrast_ratio( $default_text, $light_page ) + 1.2 ) ) );
$default_hov_d  = majestic_tube_contrast_safe( $default_rgb, $dark_card, min( 21, max( 8.2, majestic_tube_contrast_ratio( $default_dark, $dark_card ) + 1.2 ) ) );

$expected = array(
	'--mt-accent'            => $shipped,
	'--mt-accent-fg'         => majestic_tube_channels_hex( $default_fg ),
	'--mt-accent-hover'      => majestic_tube_channels_hex( $default_fill_h ),
	'--mt-accent-text'       => 'light-dark( ' . majestic_tube_channels_hex( $default_text ) . ', ' . majestic_tube_channels_hex( $default_dark ) . ' )',
	'--mt-accent-text-hover' => 'light-dark( ' . majestic_tube_channels_hex( $default_hov_l ) . ', ' . majestic_tube_channels_hex( $default_hov_d ) . ' )',
	'--mt-accent-on-dark'    => majestic_tube_channels_hex( $default_dark ),
);

foreach ( $expected as $name => $value ) {
	$actual = root_token( $main_css, $name );
	check( "main.css ships the derived value for {$name}", $value === $actual, "expected {$value}, found {$actual}" );
}

check(
	'main.css keeps the rgb triple in step with the fill',
	'14, 123, 138' === root_token( $main_css, '--mt-accent-rgb' )
);
check(
	'the old token name is kept in step in the stylesheet too',
	'light-dark( ' . majestic_tube_channels_hex( $default_hov_l ) . ', ' . majestic_tube_channels_hex( $default_hov_d ) . ' )' === root_token( $main_css, '--mt-accent-dark' ),
	root_token( $main_css, '--mt-accent-dark' )
);
check(
	'the editor repeats the same accent and text tones, in its own root block',
	$shipped === root_token( $editor_css, '--mt-accent' )
	&& majestic_tube_channels_hex( $default_text ) === root_token( $editor_css, '--mt-accent-text' )
	&& majestic_tube_channels_hex( $default_fg ) === root_token( $editor_css, '--mt-accent-fg' )
);
check(
	'the editor paints links with the text tone rather than the fill',
	1 === preg_match( '/\.editor-styles-wrapper a\s*\{[^}]*color:\s*var\(--mt-accent-text\)/', $editor_css )
	&& 1 === preg_match( '/\.editor-styles-wrapper a:hover\s*\{[^}]*color:\s*var\(--mt-accent-text-hover\)/', $editor_css )
);
check( 'and the editor button takes the fill foreground', 1 === preg_match( '/\.wp-block-button__link\s*\{[^}]*color:\s*var\(--mt-accent-fg\)/', $editor_css ) );

/* -------------------------------------------------------------------------
 * 4. Every rule paints the role it means
 * ---------------------------------------------------------------------- */

echo "\nOne token per role in the stylesheets\n";

$misuse = array();

foreach ( array( 'assets/css/main.css' => $main_css, 'assets/css/player.css' => $player_css, 'assets/css/editor.css' => $editor_css ) as $file => $css ) {
	foreach ( css_rules( $css ) as $rule ) {
		// A bare `color:` - not border-color, not background-color - must never
		// take the fill token: that is the mistake that made a hovered link and
		// a hovered icon unreadable on the dark skin.
		if ( preg_match( '/(?<![a-z-])color\s*:\s*var\(--mt-accent\)\s*;/', $rule ) ) {
			$misuse[] = $file . ': text painted with the fill color -> ' . substr( preg_replace( '/\s+/', ' ', $rule ), 0, 70 );
		}

		// A rule that fills with the accent and also colors its text has to use
		// the derived foreground, not a hard-coded white.
		if ( false !== strpos( $rule, 'background: var(--mt-accent)' ) && preg_match( '/(?<![a-z-])color\s*:\s*([^;]+);/', $rule, $m ) ) {
			if ( 'var(--mt-accent-fg)' !== trim( $m[1] ) ) {
				$misuse[] = $file . ': text on the accent fill is ' . trim( $m[1] ) . ' -> ' . substr( preg_replace( '/\s+/', ' ', $rule ), 0, 70 );
			}
		}

		// Hovered fills are their own token so they can move away from the text
		// instead of landing on top of it.
		if ( false !== strpos( $rule, 'background: var(--mt-accent-dark)' ) || false !== strpos( $rule, 'background-color: var(--mt-accent-dark)' ) ) {
			$misuse[] = $file . ': a hovered fill still uses the compatibility token -> ' . substr( preg_replace( '/\s+/', ' ', $rule ), 0, 70 );
		}
	}
}

check( 'every rule paints the role it means, and no rule hard-codes white on the fill', ! $misuse, implode( "\n         ", $misuse ) );
check(
	'the accent-fill rules that carry text all name the foreground token',
	substr_count( $main_css, 'color: var(--mt-accent-fg);' ) >= 14,
	(string) substr_count( $main_css, 'color: var(--mt-accent-fg);' )
);

$player_accent = array();
foreach ( css_rules( $player_css ) as $rule ) {
	if ( preg_match( '/var\(--mt-accent(?![a-z-])/', $rule, $m ) ) {
		$player_accent[] = substr( preg_replace( '/\s+/', ' ', $rule ), 0, 60 );
	}
}

check(
	'the player chrome takes the on-dark accent and nothing else',
	! $player_accent,
	implode( "\n         ", $player_accent )
);
check(
	'and the player really does use it',
	15 === substr_count( $player_css, 'var(--mt-accent-on-dark)' ),
	(string) substr_count( $player_css, 'var(--mt-accent-on-dark)' )
);

/* -------------------------------------------------------------------------
 * 5. The block editor palette follows the same roles
 * ---------------------------------------------------------------------- */

echo "\ntheme.json\n";

$theme_json_raw = theme_file( 'theme.json' );
$theme_json     = json_decode( $theme_json_raw, true );

check( 'theme.json parses', is_array( $theme_json ) );

$palette = array();

foreach ( (array) $theme_json['settings']['color']['palette'] as $entry ) {
	$palette[ $entry['slug'] ] = $entry['color'];
}

check( 'its accent swatch is the color the theme ships', $shipped === ( $palette['accent'] ?? '' ), (string) ( $palette['accent'] ?? 'missing' ) );
// The swatch is a single color while the token is a light-dark() pair, so it is
// compared against the light value - the first one the pair declares.
preg_match_all( '/#[0-9a-f]{6}/i', root_token( $main_css, '--mt-text-muted' ), $muted_hex );
check(
	'its muted swatch matches the light value in the stylesheet',
	( $muted_hex[0][0] ?? '' ) === ( $palette['text-muted'] ?? '' ),
	sprintf( 'palette %s, stylesheet %s', $palette['text-muted'] ?? 'missing', $muted_hex[0][0] ?? 'missing' )
);
check(
	'its links take the accent text tones, not the fill',
	'var(--mt-accent-text)' === $theme_json['styles']['elements']['link']['color']['text']
	&& 'var(--mt-accent-text-hover)' === $theme_json['styles']['elements']['link'][':hover']['color']['text'],
	sprintf( '%s / %s', $theme_json['styles']['elements']['link']['color']['text'], $theme_json['styles']['elements']['link'][':hover']['color']['text'] )
);
check(
	'its buttons paint their label with the derived foreground rather than a hard-coded white',
	'var(--mt-accent-fg)' === $theme_json['styles']['elements']['button']['color']['text'],
	(string) $theme_json['styles']['elements']['button']['color']['text']
);
check(
	'and no part of theme.json reaches for the fill as a text color',
	0 === preg_match_all( '/"(?:text)"\s*:\s*"var\(--mt-accent\)"/', $theme_json_raw )
);

/* -------------------------------------------------------------------------
 * 6. One menu button, not two
 * ---------------------------------------------------------------------- */

echo "\nThe menu button\n";

$main_rules = css_rules( $main_css );
$header     = theme_file( 'header.php' );

$bars = array();

foreach ( array_merge( $main_rules, css_rules( $rtl_css ) ) as $rule ) {
	if ( false !== strpos( $rule, 'menu-toggle' ) && preg_match( '/menu-toggle::(before|after)/', $rule ) ) {
		$bars[] = substr( preg_replace( '/\s+/', ' ', $rule ), 0, 70 );
	}
}

check( 'nothing draws a second hamburger with pseudo-elements', ! $bars, implode( "\n         ", $bars ) );

$toggles = rules_matching( $main_rules, '.button-nav.menu-toggle' );
$visible = array_values(
	array_filter(
		$toggles,
		function ( $rule ) {
			return false !== strpos( $rule, 'display: inline-flex' );
		}
	)
);

check( 'the toggle is a flex box at every width that shows it', count( $visible ) >= 2, (string) count( $visible ) );

$uncentered = array();

foreach ( $visible as $rule ) {
	if ( false === strpos( $rule, 'align-items: center' ) || false === strpos( $rule, 'justify-content: center' ) ) {
		$uncentered[] = substr( preg_replace( '/\s+/', ' ', $rule ), 0, 70 );
	}
}

check( 'and it centers the one icon it is given', ! $uncentered, implode( "\n         ", $uncentered ) );

$touch = array_values(
	array_filter(
		$toggles,
		function ( $rule ) {
			return false !== strpos( $rule, 'min-height: 44px' );
		}
	)
);

check( 'the phone-width toggle keeps a 44px touch target', (bool) $touch );

check(
	'the markup carries one open shape and one close shape',
	1 === preg_match( '/majestic_tube_icon\(\s*\'menu\',\s*\'menu-toggle-open\'\s*\)/', $header )
	&& 1 === preg_match( '/majestic_tube_icon\(\s*\'close\',\s*\'menu-toggle-close\'\s*\)/', $header )
);
check(
	'and the button is a real button with the state WordPress and screen readers expect',
	1 === preg_match( '/<button[^>]*class="button-nav menu-toggle"/s', $header )
	&& false !== strpos( $header, 'aria-controls="primary-menu"' )
	&& false !== strpos( $header, 'aria-expanded="false"' )
	&& false !== strpos( $header, 'type="button"' )
);
check(
	'CSS shows exactly one of the two shapes at a time',
	1 === preg_match( '/\.menu-toggle-close\s*\{\s*display:\s*none;/', $main_css )
	&& 1 === preg_match( '/\[aria-expanded="true"\]\s*\.menu-toggle-open\s*\{\s*display:\s*none;/', $main_css )
	&& 1 === preg_match( '/\[aria-expanded="true"\]\s*\.menu-toggle-close\s*\{\s*display:\s*inline-block;/', $main_css )
);
check(
	'the toggle is hidden where the full menu bar is on screen',
	1 === preg_match( '/#head-mobile,\s*\.button-nav\.menu-toggle\s*\{[^}]*display:\s*none/', $main_css )
);

/* -------------------------------------------------------------------------
 * 7. Colors, not Colours
 * ---------------------------------------------------------------------- */

echo "\nColors, not Colours\n";

/**
 * Every shipped file that carries prose or a label.
 *
 * "Shipped" is what the release build carries, which .gitignore defines: the
 * tools/ harnesses, the tests and the local notes are excluded from the theme
 * zip, so their spelling is not the theme's to enforce.
 *
 * @return string[] Paths relative to the theme root.
 */
function shipped_text_files() {
	$skip_dirs = array( '.git', '.kilo', 'node_modules', 'tests', 'vendor', 'tools', '__pycache__' );
	$skip_ext  = array( 'png', 'jpg', 'jpeg', 'gif', 'webp', 'zip', 'woff', 'woff2', 'ttf', 'otf', 'ico', 'svg', 'mo', 'po', 'map' );
	$files     = array();

	$walk = function ( $dir ) use ( &$walk, &$files, $skip_dirs, $skip_ext ) {
		foreach ( scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$path = $dir . '/' . $entry;
			$rel  = ltrim( str_replace( MAJESTIC_TUBE_DIR, '', $path ), '/' );

			if ( is_dir( $path ) ) {
				if ( ! in_array( $entry, $skip_dirs, true ) ) {
					$walk( $path );
				}

				continue;
			}

			$ext = strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) );

			if ( ! in_array( $ext, $skip_ext, true ) ) {
				$files[] = $rel;
			}
		}
	};

	$walk( MAJESTIC_TUBE_DIR );

	return $files;
}

/*
 * The theme writes American English, which is the spelling WordPress itself
 * uses and the one every other string in these files already has. A British
 * variant is not a typo - it is a second spelling of the same word, and one
 * of them has to win. These are the pairs that had drifted.
 */
$british_words = array(
	'colour',
	'colours',
	'behaviour',
	'behaviours',
	'relabelled',
	'labelled',
	'labelling',
	'centre',
	'centred',
	'centres',
	'neighbour',
	'neighbours',
	'neighbouring',
	'honour',
	'honours',
	'honoured',
	'honouring',
	'recognise',
	'recognised',
	'recognises',
	'recognising',
	'recognisable',
	'unrecognised',
	'catalogue',
	'judgement',
	'grey',
	'greyscaled',
);

$british = array();

foreach ( shipped_text_files() as $file ) {
	$contents = @file_get_contents( MAJESTIC_TUBE_DIR . '/' . $file );

	if ( false === $contents ) {
		continue;
	}

	$hits = array();

	foreach ( $british_words as $word ) {
		if ( preg_match_all( '/\b' . preg_quote( $word, '/' ) . '\b/i', $contents, $found ) ) {
			$hits[] = $word . ' x' . count( $found[0] );
		}
	}

	if ( $hits ) {
		$british[] = $file . ' (' . implode( ', ', $hits ) . ')';
	}
}

check(
	'no shipped file carries a British spelling of a word the theme spells the American way',
	! $british,
	implode( '; ', $british )
);

$options = theme_file( 'inc/theme-options.php' );
$map     = majestic_tube_options_map();

check( 'the section is named in the American spelling the rest of WordPress uses', 1 === preg_match( "/__\(\s*'Colors &amp; Typography'/" , $options ) );
check( 'the panel keeps the accent, scheme, toggle, background and font controls', 5 === count( array_filter( array_keys( $map ), function ( $key ) use ( $map ) { return 'colors' === $map[ $key ]['section']; } ) ), (string) count( array_filter( array_keys( $map ), function ( $key ) use ( $map ) { return 'colors' === $map[ $key ]['section']; } ) ) );
check( 'the accent control is labeled "Accent color"', 'Accent color' === $map['main-color']['label'], (string) $map['main-color']['label'] );
check( 'the scheme control is labeled "Color scheme"', 'Color scheme' === $map['color-scheme']['label'], (string) $map['color-scheme']['label'] );
check( 'the section slug is "colors"', 'colors' === $map['main-color']['section'], (string) $map['main-color']['section'] );
check( 'the default accent is the one the derivation and the stylesheet ship', '#0e7b8a' === $map['main-color']['default'], (string) $map['main-color']['default'] );
check( 'the accent description says what the accent actually drives', false !== strpos( (string) $map['main-color']['description'], 'light skin' ) && false !== strpos( (string) $map['main-color']['description'], 'dark skin' ) );
check( 'the header toggle is described in the American spelling too', false !== strpos( $options, "'Switch color scheme'" ) && false === strpos( $options, "'Switch colour scheme'" ) );

/* -------------------------------------------------------------------------
 * The words on a control, and how core prints them
 * ---------------------------------------------------------------------- */

echo "\nThe Customizer descriptions\n";

/*
 * Core prints a description into the page as markup, not as text: a control's
 * label goes through esc_html() and its description does not, it is echoed raw
 * (WP_Customize_Control::render_content()). A tag written literally in one is
 * therefore never read by anyone, because the browser parses it and drops it -
 * which is how "Extra markup printed before </body>." reached the screen as
 * "Extra markup printed before .". Anything a description means to show as a
 * tag has to be written as an entity, and the only tags a description may
 * carry are the inline ones it uses for emphasis and for marking up an
 * example.
 */
$inline  = array( 'code', 'strong', 'em', 'b', 'i', 'br', 'a', 'span', 'small', 'kbd' );
$printed = array();
$raw     = array();

foreach ( majestic_tube_option_sections() as $section_slug => $section ) {
	if ( ! empty( $section['description'] ) ) {
		$printed[ 'section ' . $section_slug ] = (string) $section['description'];
	}
}

foreach ( $map as $key => $field ) {
	if ( ! empty( $field['description'] ) ) {
		$printed[ 'field ' . $key ] = (string) $field['description'];
	}
}

foreach ( $printed as $where => $text ) {
	// No space is allowed after the bracket: a real tag never has one, and
	// "shorter than < 10" is a sentence, not markup.
	if ( preg_match_all( '#</?([a-z][a-z0-9]*)#i', $text, $found ) ) {
		foreach ( $found[1] as $tag ) {
			if ( ! in_array( strtolower( $tag ), $inline, true ) ) {
				$raw[] = $where . ' says <' . strtolower( $tag ) . '>';
			}
		}
	}
}

check(
	'no description carries a tag the browser would swallow instead of showing',
	! $raw,
	implode( ', ', array_unique( $raw ) )
);
check(
	'and the two script fields name the closing tag they print, readably',
	false !== strpos( (string) $map['other-scripts']['description'], '&lt;/body&gt;' )
		&& false !== strpos( (string) $map['mobile-scripts']['description'], '&lt;/body&gt;' ),
	(string) $map['other-scripts']['description'] . ' | ' . (string) $map['mobile-scripts']['description']
);

/* -------------------------------------------------------------------------
 * 8. The consent options
 * ---------------------------------------------------------------------- */

echo "\nThe consent options\n";

$sections = majestic_tube_option_sections();

check( 'the terms ask is on by default', 'on' === $map['ask-to-accept-terms']['default'], (string) $map['ask-to-accept-terms']['default'] );
check(
	'and is filed with the accounts it concerns',
	isset( $sections[ $map['ask-to-accept-terms']['section'] ] ),
	(string) $map['ask-to-accept-terms']['section']
);
check( 'the cookie notice is off until the site asks for it', 'off' === $map['cookie-notice']['default'], (string) $map['cookie-notice']['default'] );
check( 'and files under the chrome it is painted with', 'chrome' === $map['cookie-notice']['section'], (string) $map['cookie-notice']['section'] );
check( 'its wording is a textarea an operator can write a paragraph in', 'textarea' === $map['cookie-notice-text']['type'], (string) $map['cookie-notice-text']['type'] );
check(
	'each consent setting has its own name to store under',
	3 === count(
		array_unique(
			array(
				$map['ask-to-accept-terms']['setting'],
				$map['cookie-notice']['setting'],
				$map['cookie-notice-text']['setting'],
			)
		)
	)
);
check(
	'the notice says where the choice is kept, since it is not kept on your server',
	false !== strpos( (string) $map['cookie-notice']['description'], 'browser' )
);

/* -------------------------------------------------------------------------
 * 8. The closing block
 * ---------------------------------------------------------------------- */

echo "\nThe closing block\n";

/*
 * The optional footer logo, the legal row and the copyright line close every
 * page, so they close it on one axis or the block reads as two. The theme got
 * two of the three right and left the logo at the gutter, which is the version
 * of this that looks deliberate until you see it.
 *
 * Each of the three is centered by a different mechanism - the logo and the
 * copyright line by text-align, the legal row by the flex axis of its list -
 * which is exactly why one can drift while the other two still look right, and
 * why all three are pinned here rather than the one that was fixed.
 */
$footer_rules = css_rules( $main_css );
$base_rtl     = theme_file( 'rtl.css' );

$logo_box = declarations_for( $footer_rules, '.logo-footer' );
$info_box = declarations_for( $footer_rules, '.site-info' );
$legal    = declarations_for( $footer_rules, '.footer-menu-container ul' );

check(
	'the footer logo is centered on the closing block\'s axis',
	declares( $logo_box, 'text-align', 'center' ) && ! declares_any( $logo_box, 'text-align', array( 'left', 'right' ) ),
	implode( ' | ', $logo_box )
);
check(
	'and the copyright line below it is centered too',
	declares( $info_box, 'text-align', 'center' ),
	implode( ' | ', $info_box )
);
check(
	'so is the legal row, which centers by its list rather than by text-align',
	declares( $legal, 'justify-content', 'center' ),
	implode( ' | ', $legal )
);

// A shared axis needs a shared box: same width, same gutter, same auto margin,
// or "centered" means a different line for each of them on a wide screen.
$shared_box = false;

foreach ( $footer_rules as $rule ) {
	$parts = explode( '{', $rule, 2 );

	if ( 2 !== count( $parts ) ) {
		continue;
	}

	$selectors = array_map( 'trim', explode( ',', $parts[0] ) );

	if ( in_array( '.logo-footer', $selectors, true ) && in_array( '.site-info', $selectors, true ) ) {
		$shared_box = true;
		break;
	}
}

check( 'the logo and the copyright line still share one box rule', $shared_box );

// The right-to-left sheets load after main.css, so a footer-wide alignment in
// one of them would win by cascade order alone - unless it is declared on the
// block itself, which is what these two rules exist to check.
$rtl_logo = array_merge(
	declarations_for( css_rules( $base_rtl ), '.logo-footer' ),
	declarations_for( css_rules( $rtl_css ), '.logo-footer' )
);

check(
	'neither right-to-left sheet pulls the logo off that axis',
	! declares_any( $rtl_logo, 'text-align', array( 'left', 'right' ) ),
	implode( ' | ', $rtl_logo )
);
check(
	'and both keep the copyright line centered where main.css centered it',
	declares( declarations_for( css_rules( $base_rtl ), '.site-info' ), 'text-align', 'center' )
	&& declares( declarations_for( css_rules( $rtl_css ), '.site-info' ), 'text-align', 'center' )
);

// The rule above centers a box, so the box has to be the one the template
// prints: a renamed wrapper would leave the stylesheet right and the footer
// wrong, with nothing failing anywhere.
$footer_template = theme_file( 'footer.php' );

check(
	'the template wraps the footer logo in the box the stylesheet centers',
	1 === preg_match( '/<div class="logo-footer">\s*<a [^>]*href="<\?php echo esc_url\( home_url/', $footer_template ),
	substr( $footer_template, (int) strpos( $footer_template, 'logo-footer' ), 90 )
);

/* -------------------------------------------------------------------------
 * 9. The color scheme and the toggle
 * ---------------------------------------------------------------------- */

echo "\nThe color scheme and the toggle\n";

$main_js = theme_file( 'assets/js/main.js' );

$GLOBALS['mods']['majestic_tube_color_scheme']     = 'light';
$GLOBALS['mods']['majestic_tube_show_theme_toggle'] = 'off';

ob_start();
majestic_tube_color_scheme_script();
$script_off = ob_get_clean();

check(
	'a site that offers no toggle prints no override script at all',
	'' === trim( $script_off ),
	$script_off
);

$GLOBALS['mods']['majestic_tube_show_theme_toggle'] = 'on';

ob_start();
majestic_tube_color_scheme_script();
$script_on = ob_get_clean();

check(
	'with a toggle, a light site still gets the script that honors a stored choice',
	false !== strpos( $script_on, 'localStorage' ) && false !== strpos( $script_on, "setAttribute( 'data-theme', stored )" ),
	$script_on
);
check(
	'and the script carries the site\'s own scheme to compare the choice against',
	false !== strpos( $script_on, "'majestic_tube_theme_base'" ) && false !== strpos( $script_on, 'site  = "light"' ),
	$script_on
);

$guard = strpos( $script_on, 'site === chosen' );
$clear = strpos( $script_on, 'removeItem' );

check(
	'a choice made against the default still in force is applied, and left alone',
	false !== $guard && false !== $clear && $guard < $clear
);
check(
	'while one made against a default that has since changed is dropped, not applied',
	false !== $clear && false === strpos( $script_on, 'site !== chosen' )
);

$GLOBALS['mods']['majestic_tube_color_scheme'] = 'dark';

ob_start();
majestic_tube_color_scheme_script();
$script_dark = ob_get_clean();

check( 'and the same script is printed for a dark site, carrying its scheme', false !== strpos( $script_dark, 'site  = "dark"' ), $script_dark );

/*
 * The gate used to be `light AND no toggle`, which is not the same test: on a
 * dark site with the toggle switched off it still printed the script, so a
 * stored choice went on deciding the page with no control on screen to show it
 * or change it - and, because of that, the same browser rendered the light
 * skin differently depending on whether the toggle happened to be on.
 */
$GLOBALS['mods']['majestic_tube_show_theme_toggle'] = 'off';

ob_start();
majestic_tube_color_scheme_script();
$script_dark_off = ob_get_clean();

check(
	'a dark site with no toggle does not apply an override nobody can see or change',
	'' === trim( $script_dark_off ),
	$script_dark_off
);

$GLOBALS['mods']['majestic_tube_show_theme_toggle'] = 'on';
$GLOBALS['mods']['majestic_tube_color_scheme']     = 'dark';

$GLOBALS['mods']['majestic_tube_color_scheme'] = 'system';

ob_start();
majestic_tube_theme_toggle();
$button = ob_get_clean();

/*
 * data-state is the state on show, which the head script may already have
 * replaced before this markup was parsed; data-default is the site's own
 * scheme, which nothing on the page rewrites. The script and the click handler
 * both need the second one, and reading the first instead is what let a site
 * set to follow the system lose that step from its own cycle after one click.
 */
check(
	'the button carries the site\'s own scheme beside the state on show',
	false !== strpos( $button, 'data-default="system"' ) && false !== strpos( $button, 'data-state="system"' ),
	substr( $button, 0, 220 )
);

$GLOBALS['mods']['majestic_tube_color_scheme'] = 'light';

ob_start();
majestic_tube_theme_toggle();
$button_light = ob_get_clean();

check( 'and it follows the site when the site is pinned instead', false !== strpos( $button_light, 'data-default="light"' ) );

check(
	'the cycle is built from the site\'s scheme rather than from the state on show',
	false !== strpos( $main_js, "button.getAttribute( 'data-default' )" )
	&& false === strpos( $main_js, "'system' === button.getAttribute( 'data-state' )" ),
	'data-default read: ' . ( false !== strpos( $main_js, "button.getAttribute( 'data-default' )" ) ? 'yes' : 'no' )
);
check(
	'a click records the scheme the choice was made against, beside the choice itself',
	false !== strpos( $main_js, "setItem( 'majestic_tube_theme', next )" )
	&& false !== strpos( $main_js, "setItem( 'majestic_tube_theme_base', fallback )" )
);
check(
	'and a state the cycle cannot show falls back to the site\'s own scheme',
	false !== strpos( $main_js, 'order.indexOf( fallback ) > -1 ? fallback : order[ 0 ]' )
);

$GLOBALS['mods']['majestic_tube_show_theme_toggle'] = 'off';

ob_start();
majestic_tube_theme_toggle();
$button_hidden = ob_get_clean();

check( 'and no button at all when the site does not offer one', '' === trim( $button_hidden ), $button_hidden );

$GLOBALS['mods'] = array();

echo "\n" . str_repeat( '-', 46 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

if ( $failed ) {
	exit( 1 );
}

echo "All checks passed.\n";
