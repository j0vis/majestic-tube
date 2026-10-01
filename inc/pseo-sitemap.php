<?php
/**
 * Bounded sitemap reads from the theme's published collection index.
 *
 * @package Majestic Tube
 * @version 2.2.26
 */
defined( 'ABSPATH' ) || exit;

function majestic_tube_should_output_facet_sitemap() {
	return (bool) apply_filters( 'majestic_tube_output_facet_sitemap', majestic_tube_seo_owned() );
}
function majestic_tube_register_facet_sitemap() {
	if ( class_exists( 'WP_Sitemaps_Provider' ) && function_exists( 'wp_register_sitemap_provider' ) && majestic_tube_should_output_facet_sitemap() ) {
		wp_register_sitemap_provider( 'majestic-tube', new Majestic_Tube_PSEO_Sitemap() );
	}
}
add_action( 'wp_sitemaps_init', 'majestic_tube_register_facet_sitemap' );

if ( class_exists( 'WP_Sitemaps_Provider' ) ) {
	class Majestic_Tube_PSEO_Sitemap extends WP_Sitemaps_Provider {
		public function __construct() { $this->name = 'majestic-tube'; $this->object_type = 'majestic-tube'; }
		private function limit() { return max( 1, min( 2000, wp_sitemaps_get_max_urls( 'majestic-tube' ) ) ); }
		public function get_url_list( $page_num, $object_subtype = '' ) {
			$list = array();
			foreach ( majestic_tube_seo_index_rows( 'generated', $this->limit(), ( max( 1, (int) $page_num ) - 1 ) * $this->limit() ) as $row ) {
				// Ordinary studio/series archives are already in core's taxonomy sitemap.
				if ( in_array( $row['facet_type'], array( 'studio', 'series' ), true ) ) { continue; }
				$list[] = array( 'loc' => $row['url'] );
			}
			// Omit lastmod until exact collection/template change dates are available.
			return (array) apply_filters( 'majestic_tube_facet_sitemap_urls', $list );
		}
		public function get_max_num_pages( $object_subtype = '' ) { return (int) ceil( majestic_tube_seo_index_count( 'generated' ) / $this->limit() ); }
	}
}
