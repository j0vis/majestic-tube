<?php
/**
 * XML sitemap for the generated facet pages.
 *
 * The core sitemap knows about posts and taxonomies. It does not know that
 * /actor/someone/with/someone-else/ exists, and it certainly does not know
 * which of those pages are worth submitting. This module adds a provider that
 * submits exactly the facets the index gate approved, plus the /browse/ spine
 * that leads to them.
 *
 * Two signals agreeing is the entire point. A thin facet is `noindex` on the
 * page AND absent from the sitemap, so a crawler is never invited to spend
 * budget on a URL the theme has already told it not to rank. Submitting a page
 * you have noindexed is a contradiction that costs crawl budget; noindexing a
 * page you submitted wastes the submission. Neither is acceptable on their
 * own, which is why the gate in inc/pseo.php is consulted here rather than a
 * second, looser threshold being invented.
 *
 * If an SEO plugin is active it owns sitemaps outright, and this provider
 * stands down - two sitemaps covering the same URLs is worse than one.
 *
 * @package Majestic Tube
 * @version 2.2.20
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the facet sitemap provider, when the theme should own sitemaps.
 *
 * Hooked to init_sitemaps, which core dispatches once the sitemaps registry is
 * ready. Guarded for WordPress before 5.5, where the whole API is absent.
 *
 * @return void
 */
function majestic_tube_register_facet_sitemap() {
	if ( ! class_exists( 'WP_Sitemaps_Provider' ) || ! function_exists( 'wp_sitemaps_add_provider' ) ) {
		return;
	}

	if ( ! majestic_tube_should_output_facet_sitemap() ) {
		return;
	}

	wp_sitemaps_add_provider( 'majestic-tube', 'Majestic_Tube_PSEO_Sitemap' );
}
add_action( 'init_sitemaps', 'majestic_tube_register_facet_sitemap' );

/**
 * Whether the theme, rather than an SEO plugin, should own the facet sitemap.
 *
 * @return bool
 */
function majestic_tube_should_output_facet_sitemap() {
	$handled = false;

	// The same list the theme uses to decide about social meta and schema:
	// one place answers "does an SEO plugin already own this?".
	foreach ( array_keys( majestic_tube_social_meta_plugins() ) as $plugin ) {
		if ( majestic_tube_is_plugin_active( $plugin ) ) {
			$handled = true;
			break;
		}
	}

	$owned = ! $handled;

	/**
	 * Filter whether Majestic Tube registers its facet sitemap provider.
	 *
	 * Return true to force it on alongside an SEO plugin, which is only safe
	 * if that plugin's own sitemap excludes facets.
	 *
	 * @param bool $owned True when the theme owns sitemaps.
	 */
	return (bool) apply_filters( 'majestic_tube_output_facet_sitemap', $owned );
}

/**
 * The facet sitemap provider.
 *
 * Only defined when WordPress supplies the base class, so the theme loads
 * cleanly on an older install where the class is simply absent.
 */
if ( class_exists( 'WP_Sitemaps_Provider' ) && ! class_exists( 'Majestic_Tube_PSEO_Sitemap' ) ) {
	/**
	 * Lists the generated facet pages across as many sitemap files as it takes.
	 */
	class Majestic_Tube_PSEO_Sitemap extends WP_Sitemaps_Provider {

		/**
		 * Provider name, used in the sitemap index.
		 */
		public function __construct() {
			$this->name = 'majestic-tube';
		}

		/**
		 * Fetch one page of URLs.
		 *
		 * The browse index groups is already cached per group, so assembling
		 * the whole catalogue here costs one cached read per group rather than
		 * a query per facet. The flat list is then sliced to this file's page.
		 *
		 * @param int $page Page number, 1-based.
		 * @return array<int, array<string, mixed>> URL map entries.
		 */
		public function get_url_map( $page ) {
			$page   = max( 1, (int) $page );
			$offset = ( $page - 1 ) * static::MAX_URLS_PER_SITEMAP;

			return array_slice( $this->build_flat_list(), $offset, static::MAX_URLS_PER_SITEMAP );
		}

		/**
		 * Provider name for the sitemap index.
		 *
		 * @param int $page Page number.
		 * @return string
		 */
		public function get_provider_name( $page ) {
			return sprintf(
				/* translators: %s: sitemap page number. */
				__( 'Generated collections %s', 'majestic-tube' ),
				number_format_i18n( (int) $page )
			);
		}

		/**
		 * Every submittable URL, the spine first.
		 *
		 * @return array<int, array<string, mixed>>
		 */
		private function build_flat_list() {
			$list = array();

			/*
			 * The spine leads. A crawler that reads the sitemap finds the map
			 * before it finds the territory, which is the crawl order we want
			 * and the reason the spine is discoverable at all on a site whose
			 * facets were never linked from a template.
			 */
			foreach ( $this->spine_urls() as $spine ) {
				$list[] = array( 'loc' => $spine );
			}

			foreach ( array_keys( majestic_tube_browse_groups() ) as $group ) {
				// The full list, not a page of it: the sitemap covers
				// everything the gate approved, however many files that takes.
				$facets = majestic_tube_browse_group_facets( $group, 0, '' );

				foreach ( $facets as $facet ) {
					$entry = array( 'loc' => $facet['url'] );
					$mod   = $this->last_modified( $facet );

					if ( $mod ) {
						$entry['lastmod'] = $mod;
					}

					$list[] = $entry;
				}
			}

			/**
			 * Filter the facet sitemap URL list.
			 *
			 * @param array $list URL map entries.
			 */
			return (array) apply_filters( 'majestic_tube_facet_sitemap_urls', $list );
		}

		/**
		 * The crawl-spine URLs, in the order a crawler should meet them.
		 *
		 * @return string[]
		 */
		private function spine_urls() {
			$urls = array( home_url( '/browse/' ) );

			foreach ( array_keys( majestic_tube_browse_groups() ) as $group ) {
				// An empty group is not linked: the spine points at pages that
				// exist, never at rooms with nothing in them.
				if ( ! majestic_tube_browse_group_count( $group ) ) {
					continue;
				}

				$urls[] = home_url( '/browse/' . rawurlencode( $group ) . '/' );
			}

			return $urls;
		}

		/**
		 * The newest publish date behind a facet, as a W3C date.
		 *
		 * A lastmod is worth having: it tells a crawler a facet changed when
		 * its videos changed, without anyone having to remember to re-submit.
		 * It comes from the same cached rollup the page itself reads, so it
		 * cannot disagree with the listing.
		 *
		 * @param array $facet One browse group entry.
		 * @return string W3C date, or an empty string.
		 */
		private function last_modified( $facet ) {
			$derived = majestic_tube_term_derived( (int) $facet['term']->term_id );
			$last    = isset( $derived['last'] ) ? (string) $derived['last'] : '';

			if ( '' === $last ) {
				return '';
			}

			$time = strtotime( $last );

			if ( ! $time ) {
				return '';
			}

			return gmdate( DATE_W3C, $time );
		}
	}
}
