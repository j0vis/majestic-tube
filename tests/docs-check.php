<?php
$html = file_get_contents( dirname( __DIR__ ) . '/docs.html' );
foreach ( array( 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'ul', 'li', 'section', 'div', 'p', 'h2', 'h3', 'strong' ) as $tag ) {
	$open = preg_match_all( '~<' . $tag . '(?:\s|>)~i', $html );
	$close = preg_match_all( '~</' . $tag . '>~i', $html );
	if ( $open !== $close ) { throw new RuntimeException( "$tag tags unbalanced: $open/$close" ); }
}
preg_match_all( '~href="#([^"]+)"~', $html, $links );
preg_match_all( '~\bid="([^"]+)"~', $html, $ids );
$missing = array_diff( $links[1], $ids[1] );
if ( $missing ) { throw new RuntimeException( 'Missing anchors: ' . implode( ',', $missing ) ); }
if ( ! str_contains( $html, 'Theme-native SEO workspace' ) ) { throw new RuntimeException( 'New workflow not documented' ); }
echo "HTML tag balance and anchor checks passed\n";
