<?php
/**
 * Integration tests for a disposable WordPress PHPUnit test installation.
 * Do not bootstrap these against a production database.
 */
class Majestic_Tube_SEO_Integration_Test extends WP_UnitTestCase {
	public static function set_up_before_class() {
		parent::set_up_before_class();
		$root = dirname( __DIR__ );
		if ( ! defined( 'MAJESTIC_TUBE_DIR' ) ) { define( 'MAJESTIC_TUBE_DIR', $root ); }
		if ( ! defined( 'MAJESTIC_TUBE_URI' ) ) { define( 'MAJESTIC_TUBE_URI', 'https://example.org/theme' ); }
		if ( ! defined( 'MAJESTIC_TUBE_VERSION' ) ) { define( 'MAJESTIC_TUBE_VERSION', '2.2.26' ); }
		if ( ! function_exists( 'majestic_tube_current_facet' ) ) {
			foreach ( array( 'theme-support.php', 'theme-options.php', 'meta-social.php', 'term-seo.php', 'breadcrumbs.php', 'pseo.php', 'pseo-stats.php', 'pseo-meta.php', 'pseo-render.php', 'pseo-sitemap.php', 'seo.php', 'seo-index.php', 'seo-admin.php' ) as $file ) { require_once $root . '/inc/' . $file; }
		}
		if ( ! taxonomy_exists( 'actors' ) ) { register_taxonomy( 'actors', 'post', array( 'public' => true ) ); }
		majestic_tube_register_facet_taxonomies();
		majestic_tube_seo_index_install();
	}
	public function set_up() {
		parent::set_up();
		update_option( 'majestic_tube_collections', array() );
		delete_option( 'majestic_tube_seo_index_error' );
		delete_option( 'majestic_tube_seo_index_job' );
		delete_option( 'majestic_tube_seo_index_lock' );
	}
	private function term( $taxonomy ) { return self::factory()->term->create_and_get( array( 'taxonomy' => $taxonomy, 'name' => 'Test ' . wp_generate_uuid4() ) ); }
	private function video( $actor, $category, $tag = null, $duration = 120 ) {
		$id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		wp_set_object_terms( $id, array( $actor->term_id ), 'actors' );
		wp_set_object_terms( $id, array( $category->term_id ), 'category' );
		if ( $tag ) { wp_set_object_terms( $id, array( $tag->term_id ), 'post_tag' ); }
		update_post_meta( $id, 'duration', $duration );
		return $id;
	}
	public function test_exact_intersection_grid_and_count_agree() {
		$actor = $this->term( 'actors' ); $category = $this->term( 'category' ); $other = $this->term( 'category' );
		for ( $i = 0; $i < 6; $i++ ) { $this->video( $actor, $category ); }
		$this->video( $actor, $other );
		$args = majestic_tube_facet_query( 'actor_category', $actor, $category );
		$query = new WP_Query( array_merge( $args, array( 'posts_per_page' => 30 ) ) );
		$this->assertSame( 6, (int) $query->found_posts );
		$this->assertSame( 6, majestic_tube_facet_count( 'actor_category', $actor, $category ) );
		$rows = majestic_tube_seo_index_candidates( $actor, 'actor_category' );
		$matching = array_filter( $rows, function ( $row ) use ( $category ) { return (int) $row['secondary'] === (int) $category->term_id; } );
		$this->assertSame( 6, (int) array_values( $matching )[0]['count'] );
	}
	public function test_duration_band_open_bound() {
		$actor = $this->term( 'actors' ); $category = $this->term( 'category' );
		$this->video( $actor, $category, null, 3600 ); $this->video( $actor, $category, null, 8000 ); $this->video( $actor, $category, null, 3599 );
		$this->assertSame( 2, majestic_tube_facet_count( 'actor_length', $actor, null, 'longest' ) );
		$rows = majestic_tube_seo_index_candidates( $actor, 'actor_length' );
		$this->assertSame( 2, (int) $rows[3]['count'] );
	}
	public function test_term_description_is_printed_in_head() {
		$term = $this->term( 'category' ); update_term_meta( $term->term_id, MAJESTIC_TUBE_TERM_SEO_DESCRIPTION, 'A real archive description' );
		$this->go_to( get_term_link( $term ) );
		ob_start(); majestic_tube_seo_head(); $head = ob_get_clean();
		$this->assertStringContainsString( 'name="description" content="A real archive description"', $head );
		$this->assertFalse( has_filter( 'wp_meta', 'majestic_tube_term_seo_meta' ) );
	}
	public function test_post_noindex_is_excluded_from_core_sitemap_query() {
		$id = self::factory()->post->create( array( 'post_status' => 'publish' ) ); update_post_meta( $id, 'majestic_tube_seo_noindex', '1' );
		$args = majestic_tube_seo_sitemap_args( array( 'post_type' => 'post', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 100 ), 'post' );
		$query = new WP_Query( $args ); $this->assertNotContains( $id, $query->posts );
	}
	public function test_csv_reader_preserves_quoted_values_and_rejects_duplicates() {
		$term = $this->term( 'category' );
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$stream = fopen( 'php://temp', 'r+' );
		fwrite( $stream, 'taxonomy,slug,title,description' . "\n" . 'category,' . $term->slug . ',"A title, with comma",A description' . "\n" ); rewind( $stream );
		try { $rows = majestic_tube_seo_csv_read( $stream ); } finally { fclose( $stream ); }
		$this->assertSame( 'A title, with comma', $rows[0]['title'] );
		$this->assertSame( '', get_term_meta( $term->term_id, MAJESTIC_TUBE_TERM_SEO_TITLE, true ) );
	}
	public function test_category_tag_index_and_sitemap_use_real_sql() {
		$category = $this->term( 'category' ); $actor = $this->term( 'actors' ); $tag = $this->term( 'post_tag' );
		for ( $i = 0; $i < 6; $i++ ) { $this->video( $actor, $category, $tag ); }
		majestic_tube_seo_index_dirty();
		for ( $i = 0; $i < 100; $i++ ) { majestic_tube_seo_index_tick(); if ( ! get_option( 'majestic_tube_seo_index_job' ) ) { break; } }
		$this->assertFalse( get_option( 'majestic_tube_seo_index_error' ) );
		$rows = majestic_tube_seo_index_rows( 'category_tag', 60, 0, '', $category->term_id );
		$this->assertNotEmpty( $rows );
		$this->assertSame( 6, (int) $rows[0]['video_count'] );
		$this->assertSame( $tag->term_id, (int) $rows[0]['term2_id'] );
		majestic_tube_register_facet_sitemap();
		$provider = wp_sitemaps_get_server()->registry->get_provider( 'majestic-tube' );
		$this->assertInstanceOf( 'Majestic_Tube_PSEO_Sitemap', $provider );
		$urls = $provider->get_url_list( 1 );
		$this->assertNotEmpty( $urls );
		foreach ( $urls as $url ) { $this->assertStringNotContainsString( '/browse/', $url['loc'] ); }
	}
}
