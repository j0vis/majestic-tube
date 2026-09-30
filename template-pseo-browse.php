<?php
/**
 * The /browse/ crawl spine.
 *
 * This page exists for one reason: generated facets are not discoverable on
 * their own. A crawler has no path from the home page to
 * `/actor/someone/with/someone-else/` unless a page links to it, and no
 * generated page can link to it without the same problem one level down. So
 * the spine does it explicitly - every indexable facet, grouped, alphabetised,
 * paginated, two hops from home.
 *
 * It is `noindex, follow`. It is not a ranking target; it is a map.
 *
 * @package Majestic Tube
 * @version 2.2.21
 */

defined( 'ABSPATH' ) || exit;

get_header();

$groups  = majestic_tube_browse_groups();
$group   = sanitize_key( (string) get_query_var( 'mt_facet' ) );
$letter  = strtoupper( sanitize_text_field( wp_unslash( get_query_var( 'mt_facet_band' ) ) ) );
$letter  = preg_match( '/^[A-Z0-9]$/', $letter ) ? $letter : '';
$paged   = max( 1, absint( get_query_var( 'paged' ) ) );
$per_page = 60;

// An unknown group falls back to the group index rather than 404ing: a bad
// URL on a map page is a dead end for a visitor and a crawl trap for a bot.
$is_index = ! $group || ! isset( $groups[ $group ] );

$facets      = $is_index ? array() : majestic_tube_browse_group_facets( $group, 0, $letter );
$total       = count( $facets );
$total_pages = $total > $per_page ? (int) ceil( $total / $per_page ) : 1;
$page_facets = array_slice( $facets, ( $paged - 1 ) * $per_page, $per_page );
?>

<div id="primary" class="content-area browse-area">
	<main id="main" class="site-main">

		<?php majestic_tube_breadcrumbs(); ?>

		<header class="page-header">
			<h1 class="page-title">
				<?php
				echo esc_html(
					$is_index
						? __( 'Browse all collections', 'majestic-tube' )
						: $groups[ $group ]['label']
				);
				?>
			</h1>

			<?php if ( $is_index ) : ?>
				<p class="browse-intro">
					<?php esc_html_e( 'Every collection on this site, grouped by how its videos relate to each other. Each page is a real listing built from the catalogue, not a generated stub.', 'majestic-tube' ); ?>
				</p>
			<?php endif; ?>
		</header>

		<?php if ( $is_index ) : ?>

			<ul class="browse-group-list">
				<?php foreach ( $groups as $key => $group_data ) : ?>
					<?php
					$count = majestic_tube_browse_group_count( $key );

					// A group with nothing in it is not linked at all. A map
					// page full of empty rooms is worse than a short one.
					if ( ! $count ) {
						continue;
					}
					?>
					<li class="browse-group-item">
						<a href="<?php echo esc_url( home_url( '/browse/' . rawurlencode( $key ) . '/' ) ); ?>">
							<?php echo esc_html( $group_data['label'] ); ?>
						</a>
						<span class="browse-group-count">
							<?php
							printf(
								/* translators: %s: number of pages. */
								esc_html__( '%s pages', 'majestic-tube' ),
								esc_html( number_format_i18n( $count ) )
							);
							?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>

		<?php elseif ( ! $page_facets ) : ?>

			<?php get_template_part( 'template-parts/content', 'none' ); ?>

		<?php else : ?>

			<nav class="browse-letter-nav" aria-label="<?php esc_attr_e( 'Filter by letter', 'majestic-tube' ); ?>">
				<a class="browse-letter<?php echo '' === $letter ? ' is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/browse/' . rawurlencode( $group ) . '/' ) ); ?>"><?php esc_html_e( 'All', 'majestic-tube' ); ?></a>

				<?php foreach ( range( 'A', 'Z' ) as $one ) : ?>
					<a class="browse-letter<?php echo $letter === $one ? ' is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/browse/' . rawurlencode( $group ) . '/' . strtolower( $one ) . '/' ) ); ?>"><?php echo esc_html( $one ); ?></a>
				<?php endforeach; ?>
			</nav>

			<p class="browse-count">
				<?php
				printf(
					/* translators: %s: number of collection pages. */
					esc_html( _n( '%s collection', '%s collections', $total, 'majestic-tube' ) ),
					esc_html( number_format_i18n( $total ) )
				);
				?>
			</p>

			<ul class="browse-facet-list">
				<?php foreach ( $page_facets as $one ) : ?>
					<li class="browse-facet-item">
						<a href="<?php echo esc_url( $one['url'] ); ?>"><?php echo esc_html( $one['label'] ); ?></a>
						<span class="browse-facet-count">
							<?php
							printf(
								/* translators: %s: number of videos. */
								esc_html( _n( '%s video', '%s videos', (int) $one['count'], 'majestic-tube' ) ),
								esc_html( number_format_i18n( (int) $one['count'] ) )
							);
							?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php
			/*
			 * Real page links, not a JS pager. A spine exists to be crawled;
			 * a spine whose continuation is only reachable by clicking is not
			 * a spine.
			 */
			if ( $total_pages > 1 ) :
				?>
				<nav class="browse-pagination" aria-label="<?php esc_attr_e( 'Browse pages', 'majestic-tube' ); ?>">
					<?php
					$base = home_url( '/browse/' . rawurlencode( $group ) . '/' );

					if ( $letter ) {
						$base .= strtolower( $letter ) . '/';
					}

					if ( $paged > 1 ) :
						$prev = ( 1 === $paged ) ? $base : $base . 'page/' . ( $paged - 1 ) . '/';
						?>
						<a class="browse-page-prev" rel="prev" href="<?php echo esc_url( $prev ); ?>"><?php esc_html_e( 'Previous', 'majestic-tube' ); ?></a>
					<?php endif; ?>

					<span class="browse-page-of">
						<?php
						printf(
							/* translators: 1: current page, 2: total pages. */
							esc_html__( 'Page %1$s of %2$s', 'majestic-tube' ),
							esc_html( number_format_i18n( $paged ) ),
							esc_html( number_format_i18n( $total_pages ) )
						);
						?>
					</span>

					<?php if ( $paged < $total_pages ) : ?>
						<a class="browse-page-next" rel="next" href="<?php echo esc_url( $base . 'page/' . ( $paged + 1 ) . '/' ); ?>"><?php esc_html_e( 'Next', 'majestic-tube' ); ?></a>
					<?php endif; ?>
				</nav>
			<?php endif; ?>

		<?php endif; ?>

	</main>
</div>

<?php
get_footer();
