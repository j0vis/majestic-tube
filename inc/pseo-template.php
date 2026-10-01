<?php
/**
 * Template Name: Facet
 *
 * Renders a generated facet page. Not reachable by URL directly: the pSEO
 * rewrite rules map onto it, and the facet is resolved from the query vars
 * rather than from a post.
 *
 * The layout is deliberately modular and every block is conditional. A block
 * that has no data behind it is omitted, never padded - a facet that can only
 * fill two blocks prints two blocks, which is the honest version of a thin
 * page and the reason those pages are `noindex`.
 *
 * @package Majestic Tube
 * @version 2.2.26
 */

defined( 'ABSPATH' ) || exit;

get_header();

$facet = majestic_tube_current_facet();

if ( ! $facet ) {
	// The resolver and the 404 handler agree that there is nothing here.
	status_header( 404 );
	get_template_part( 'template-parts/content', 'none' );
	get_footer();

	return;
}

$type   = $facet['type'];
$term   = $facet['term'];
$term2  = isset( $facet['term2'] ) ? $facet['term2'] : null;
$band   = isset( $facet['band'] ) ? $facet['band'] : '';
$count  = (int) $facet['count'];
$stats  = majestic_tube_facet_stats( $type, $term, $term2, $band );
$parent = get_term_link( $term );
$parent = is_wp_error( $parent ) ? '' : $parent;
?>

<div id="primary" class="content-area facet-area">
	<main id="main" class="site-main">

		<?php majestic_tube_breadcrumbs(); ?>

		<header class="page-header facet-header">
			<?php
			/*
			 * The portrait is the one strong visual a listing page has. It is
			 * only printed when the term actually has one - term-images.php
			 * already falls back to a member video's thumbnail, and a
			 * category with neither gets a placeholder rather than a broken
			 * image.
			 */
			$portrait = '';

			if ( 'actors' === $term->taxonomy ) {
				$portrait = majestic_tube_get_term_image_url( $term->term_id, 'actors', 'majestic-tube-thumb-medium' );
			} elseif ( in_array( $term->taxonomy, array( 'category', 'studio', 'series' ), true ) ) {
				$portrait = majestic_tube_get_term_image_url( $term->term_id, $term->taxonomy, 'majestic-tube-thumb-medium' );
			}

			if ( $portrait ) :
				?>
				<img class="facet-portrait" src="<?php echo esc_url( $portrait ); ?>" alt="<?php echo esc_attr( $term->name ); ?>" width="320" height="180" />
			<?php endif; ?>

			<h1 class="page-title facet-title"><?php echo esc_html( majestic_tube_facet_heading( $facet ) ); ?></h1>

			<?php if ( ! empty( $stats['summary'] ) ) : ?>
				<div class="facet-summary"><?php echo esc_html( $stats['summary'] ); ?></div>
			<?php endif; ?>

			<p class="facet-count">
				<?php
				printf(
					/* translators: %s: number of videos. */
					esc_html( _n( '%s video', '%s videos', $count, 'majestic-tube' ) ),
					esc_html( number_format_i18n( $count ) )
				);
				?>
			</p>

			<?php if ( ! empty( $stats['attributes'] ) ) : ?>
				<ul class="facet-attributes">
					<?php foreach ( $stats['attributes'] as $attribute ) : ?>
						<li>
							<span class="facet-attribute-label"><?php echo esc_html( $attribute['label'] ); ?></span>
							<span class="facet-attribute-value"><?php echo esc_html( $attribute['value'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</header>

		<?php if ( $parent ) : ?>
			<nav class="facet-parent" aria-label="<?php esc_attr_e( 'Parent collection', 'majestic-tube' ); ?>">
				<a href="<?php echo esc_url( $parent ); ?>">
					<?php
					printf(
						/* translators: %s: term name. */
						esc_html__( 'All %s videos', 'majestic-tube' ),
						esc_html( $term->name )
					);
					?>
				</a>
			</nav>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>

			<h2 class="facet-videos-heading">
				<?php
				printf(
					/* translators: %s: video count. */
					esc_html__( 'All %s videos', 'majestic-tube' ),
					esc_html( number_format_i18n( $count ) )
				);
				?>
			</h2>

			<div class="video-grid">
				<?php majestic_tube_render_post_grid(); ?>
			</div>

			<?php majestic_tube_the_pagination(); ?>

		<?php else : ?>

			<?php get_template_part( 'template-parts/content', 'none' ); ?>

		<?php endif; ?>

		<?php
		/*
		 * Sibling links. This is the block that decides whether a facet is a
		 * leaf or a node: a page that links only downward, into its own grid,
		 * ends the crawl. Linking sideways into the same actor's other
		 * categories and pairings is what keeps PageRank circulating through
		 * the cluster instead of pooling at the bottom of it.
		 */
		if ( ! empty( $stats['siblings'] ) ) :
			?>
			<section class="facet-siblings">
				<h2 class="facet-section-heading">
					<?php
					if ( 'actor_actor' === $type ) {
						esc_html_e( 'More pairings', 'majestic-tube' );
					} elseif ( 'actor_length' === $type ) {
						esc_html_e( 'Other lengths', 'majestic-tube' );
					} elseif ( 'category_tag' === $type ) {
						esc_html_e( 'More tags in this category', 'majestic-tube' );
					} else {
						esc_html_e( 'More in this collection', 'majestic-tube' );
					}
					?>
				</h2>

				<ul class="facet-sibling-list">
					<?php foreach ( $stats['siblings'] as $sibling ) : ?>
						<li>
							<a href="<?php echo esc_url( $sibling['url'] ); ?>"><?php echo esc_html( $sibling['label'] ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $stats['related'] ) ) : ?>
			<section class="facet-related">
				<h2 class="facet-section-heading"><?php esc_html_e( 'Related collections', 'majestic-tube' ); ?></h2>

				<ul class="facet-related-list">
					<?php foreach ( array_slice( $stats['related'], 0, 12 ) as $related ) : ?>
						<li>
							<a href="<?php echo esc_url( $related['url'] ); ?>"><?php echo esc_html( $related['label'] ); ?></a>
							<span class="facet-related-count">
								<?php
								printf(
									/* translators: %s: number of shared videos. */
									esc_html__( '%s shared', 'majestic-tube' ),
									esc_html( number_format_i18n( (int) $related['count'] ) )
								);
								?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<nav class="facet-browse-more" aria-label="<?php esc_attr_e( 'Browse more collections', 'majestic-tube' ); ?>">
			<a href="<?php echo esc_url( home_url( '/browse/' ) ); ?>"><?php esc_html_e( 'Browse all collections', 'majestic-tube' ); ?></a>
		</nav>

	</main>
</div>

<?php
get_footer();
