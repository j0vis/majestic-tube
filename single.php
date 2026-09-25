<?php
/**
 * The single video template - WP-Script data model.
 *
 * Renders the player from video_url / resolution files / embed / shortcode,
 * matching the original theme's front-end behavior, plus the ad zones and the
 * "report video" action.
 *
 * @package Majestic Tube
 * @version 2.0.6
 */

get_header();

while ( have_posts() ) :
	the_post();

	// One canonical source read feeds the player, embed and shortcode branches;
	// passing $sources also stops get_player_sources() re-reading every URL key.
	$sources        = majestic_tube_get_video_sources( get_the_ID() );
	$player_sources = majestic_tube_get_player_sources( get_the_ID(), $sources );
	$stats          = majestic_tube_analytics_get( get_the_ID() );
	$views          = $stats['views'];
	$player         = majestic_tube_player_enabled();
	$autoplay       = majestic_tube_option_is_on( 'autoplay-video-player' );
	$source_classes = $player ? 'video-js vjs-default-skin vjs-big-play-centered' : 'majestic-tube-native-player';
	$video_sidebar  = '';

	if ( majestic_tube_option_is_on( 'single-sidebar' ) && is_active_sidebar( 'majestic-tube-video-sidebar' ) ) {
		$video_sidebar = majestic_tube_widget_area_content( 'majestic-tube-video-sidebar' );
	}

	$show_video_sidebar = '' !== trim( $video_sidebar );
	?>

	<div id="primary" class="content-area single-content-area">
		<div class="single-video-layout<?php echo $show_video_sidebar ? ' has-video-sidebar' : ''; ?>">
			<main id="main" class="site-main" role="main">

			<?php
			if ( majestic_tube_option_is_on( 'enable-breadcrumbs' ) ) {
				majestic_tube_breadcrumbs();
			}
			?>

			<article id="post-<?php the_ID(); ?>" <?php post_class( 'single-video' ); ?>>

				<header class="entry-header">
					<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
				</header>

				<div class="video-player" data-post-id="<?php echo esc_attr( get_the_ID() ); ?>" data-views="<?php echo esc_attr( $views ); ?>">
					<?php
					if ( 'self-hosted' === $sources['type'] ) :
						?>
						<video id="wpst-video" class="<?php echo esc_attr( $source_classes ); ?>" controls preload="auto" playsinline
							<?php echo $autoplay ? 'autoplay muted' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attributes. ?>
							width="640" height="360"
							poster="<?php echo esc_url( majestic_tube_get_thumb_url( get_the_ID(), 'majestic-tube-thumb-large' ) ); ?>">
							<?php foreach ( $player_sources as $source ) : ?>
								<source src="<?php echo esc_url( $source['url'] ); ?>" type="<?php echo esc_attr( $source['type'] ); ?>"
									<?php echo $source['label'] ? 'label="' . esc_attr( $source['label'] ) . '" data-res="' . esc_attr( $source['label'] ) . '"' : ''; ?>>
							<?php endforeach; ?>
							<p class="vjs-no-js">
								<?php esc_html_e( 'To view this video please enable JavaScript, and consider upgrading to a web browser that supports HTML5 video.', 'majestic-tube' ); ?>
							</p>
						</video>

					<?php elseif ( 'embed' === $sources['type'] ) : ?>
						<div class="video-embed">
							<?php
							// Embed codes come from trusted site admins and may contain iframes.
							$embed = (string) $sources['embed'];

							if ( current_user_can( 'unfiltered_html' ) ) {
								echo $embed; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- admin-provided embed code.
							} else {
								echo wp_kses(
									$embed,
									array(
										'iframe' => array(
											'src'             => true,
											'width'           => true,
											'height'          => true,
											'frameborder'     => true,
											'allow'           => true,
											'allowfullscreen' => true,
											'loading'         => true,
											'style'           => true,
										),
										'video'  => array(
											'src'      => true,
											'controls' => true,
											'poster'   => true,
											'width'    => true,
											'height'   => true,
										),
										'source' => array(
											'src'  => true,
											'type' => true,
										),
									)
								);
							}
							?>
						</div>

					<?php elseif ( 'shortcode' === $sources['type'] ) : ?>
						<div class="video-shortcode">
							<?php echo do_shortcode( $sources['shortcode'] ); ?>
						</div>
					<?php endif; ?>

					<?php
					/*
					 * Logo watermark over the player (original template-parts/
					 * content-logo-watermark.php behavior): desktop only, never on
					 * mobile, exactly one image either from the watermark option or
					 * from the main logo, positioned by logo-position-video-player.
					 */
					get_template_part( 'template-parts/content', 'logo-watermark' );
					?>						<?php
						/*
						 * Player content is widget-managed. Keep the original
						 * overlay container and close behavior, but do not expose
						 * a placement-specific class or label in the output.
						 */
						$player_content = ! majestic_tube_is_mobile() && ! majestic_tube_is_ctpl_active() && is_active_sidebar( 'majestic-tube-player-content' )
							? majestic_tube_widget_area_content( 'majestic-tube-player-content' )
							: '';

						if ( '' !== trim( $player_content ) && 'none' !== $sources['type'] ) :
							?>
							<div class="happy-inside-player">
								<?php echo $player_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- widget output. ?>
								<button type="button" class="close close-text"><?php esc_html_e( 'Close', 'majestic-tube' ); ?></button>
							</div>
						<?php endif; ?>

				</div>					<?php
					// Per-video code wins; otherwise the below-player widgets render.
					majestic_tube_under_player_content( get_the_ID() );
					?>


				<?php
				/*
				 * Keep the data contract used by the front-end scripts, but show
				 * each number once. The old title meta duplicated views, duration
				 * and likes and mixed them with the action controls.
				 */
				$likes        = $stats['likes'];
				$dislikes     = $stats['dislikes'];
				$rate         = majestic_tube_get_post_like_rate( get_the_ID() );
				$show_views   = majestic_tube_option_is_on( 'enable-views-system' );
				$show_rating  = majestic_tube_option_is_on( 'enable-rating-system' );
				$show_share   = majestic_tube_option_is_on( 'enable-video-share' );
				$show_report  = majestic_tube_option_is_on( 'enable-video-report' );
				$has_summary  = $show_views || $show_rating;
				?>
				<?php if ( $has_summary ) : ?>
				<div class="video-infos">
					<div class="video-infos-left">
						<?php if ( $show_views ) : ?>
							<span class="video-views"><span><?php echo esc_html( majestic_tube_get_human_number( $views ) ); ?></span> <?php esc_html_e( 'views', 'majestic-tube' ); ?></span>
						<?php endif; ?>

						<?php if ( $show_rating ) : ?>
							<span class="likes"><span class="likes_count"><?php echo esc_html( number_format_i18n( $likes ) ); ?></span> <?php esc_html_e( 'likes', 'majestic-tube' ); ?></span>
						<?php endif; ?>
					</div>

					<?php if ( $show_rating ) : ?>
						<div class="video-infos-right">
							<div class="rating-result">
								<span class="video-rate-label"><?php esc_html_e( 'Rating', 'majestic-tube' ); ?></span>
								<div class="video-rate" role="progressbar" aria-label="<?php esc_attr_e( 'Video rating', 'majestic-tube' ); ?>" aria-valuenow="<?php echo esc_attr( $rate ); ?>" aria-valuemin="0" aria-valuemax="100">
									<div class="video-rate-bar" style="width: <?php echo esc_attr( $rate ); ?>%;"></div>
								</div>
								<span class="percentage"><?php echo esc_html( $rate ); ?>%</span>
							</div>
						</div>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<?php if ( $show_rating || $show_share || $show_report ) : ?>
					<div class="video-actions">
						<?php if ( $show_rating ) : ?>
							<div id="rating" class="video-rating-actions">
								<span id="video-rate">
									<?php
									$like_link = wpst_get_post_like_link( get_the_ID() );

									if ( $like_link ) {
										echo $like_link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper.
									} else {
										echo '<span class="post-like"><span class="button disabled">' . esc_html__( 'Thank you!', 'majestic-tube' ) . '</span></span>';
									}
									?>
								</span>
								<button type="button" class="button post-dislike" data-post_id="<?php echo esc_attr( get_the_ID() ); ?>" data-post_like="dislike">
									<i class="fa fa-thumbs-down" aria-hidden="true"></i> <?php esc_html_e( 'Dislike', 'majestic-tube' ); ?>
									<span class="dislike-count"><?php echo esc_html( number_format_i18n( $dislikes ) ); ?></span>
								</button>
							</div>
						<?php endif; ?>

						<?php if ( $show_share || $show_report ) : ?>
							<div class="video-utility-actions">
								<?php
								if ( $show_share ) {
									majestic_tube_share_buttons( get_the_ID() );
								}

								if ( $show_report ) {
									majestic_tube_report_button( get_the_ID() );
								}
								?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php
				/*
				 * Tracking button.
				 *
				 * Original logic: the global tracking-button-link option overrides,
				 * and each video's Tracking URL (meta key tracking_url) is the
				 * fallback. No link means no button at all, so turning the switch on
				 * globally costs nothing. Markup and ids are the original ones.
				 */
				$tracking_button = (string) majestic_tube_get_option( 'wpst-options', 'tracking-button-link', '' );

				if ( '' === $tracking_button ) {
					$tracking_button = majestic_tube_get_tracking_url( get_the_ID() );
				}

				if ( majestic_tube_option_is_on( 'display-tracking-button' ) && '' !== $tracking_button ) :
					$tracking_icon = (string) majestic_tube_get_option( 'wpst-options', 'tracking-button-icon', 'download' );
					$tracking_text = (string) majestic_tube_get_option( 'wpst-options', 'tracking-button-text', '' );
					?>
					<a class="button" id="tracking-url" href="<?php echo esc_url( $tracking_button ); ?>" title="<?php echo esc_attr( get_the_title() ); ?>" target="_blank" rel="nofollow noopener">
						<i class="fa fa-<?php echo esc_attr( $tracking_icon ); ?>" aria-hidden="true"></i>
						<?php echo esc_html( $tracking_text ? $tracking_text : __( 'Watch the full video', 'majestic-tube' ) ); ?>
					</a>
				<?php endif; ?>

				<?php
				/*
				 * Description block, original markup and options: the panel is shown
				 * when show-description-video-about is on, and the `more` class (which
				 * drives the "read more" clamp in main.js) is only added when
				 * truncate-description is on.
				 *
				 * Emptiness is tested against the raw post_content rather than with
				 * get_the_content(). That function does not apply the the_content
				 * filters, so it returned essentially the same string - but fetching
				 * the post a second time only to run trim() on it was wasted work on
				 * every single video page. Stripping the markup also means a post
				 * whose body is nothing but an empty block no longer renders an empty
				 * description panel.
				 */
				$description_post = get_post();
				$show_description = majestic_tube_option_is_on( 'show-description-video-about' );
				$has_description  = $show_description
					&& $description_post
					&& '' !== trim( wp_strip_all_tags( (string) $description_post->post_content ) );

				if ( $has_description ) :
					$desc_classes = 'desc';

					if ( majestic_tube_option_is_on( 'truncate-description' ) ) {
						$desc_classes .= ' more';
					}					?>
					<div class="video-description">
						<div class="<?php echo esc_attr( $desc_classes ); ?> entry-content">
							<h2 class="video-description-title"><?php esc_html_e( 'About this video', 'majestic-tube' ); ?></h2>
							<?php
							the_content();

							wp_link_pages(
								array(
									'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'majestic-tube' ),
									'after'  => '</div>',
								)
							);
							?>
						</div>
					</div>
				<?php endif; ?>

				<footer class="entry-footer">
					<?php
					// Original markup: wpst_cats_tags() prints .tags-list with the
					// categories / actors / tags labels, each gated on its own option.
					if ( majestic_tube_option_is_on( 'show-categories-video-about' ) || majestic_tube_option_is_on( 'show-tags-video-about' ) || majestic_tube_option_is_on( 'show-actors-video-about' ) ) {
						echo '<div class="video-tags">';
						wpst_cats_tags();
						echo '</div>';
					}
					?>
				</footer>

			</article>

			<?php
			// Related videos sharing actors or category.
			if ( majestic_tube_option_is_on( 'display-related-videos' ) ) :
				$related_count = absint( majestic_tube_get_option( 'wpst-options', 'related-videos-number', 6 ) );
				$related       = majestic_tube_get_related_videos( get_the_ID(), $related_count ? $related_count : 6 );

				if ( $related && $related->have_posts() ) :
					?>
					<section class="related-videos">
						<h2 class="related-title"><?php esc_html_e( 'Related Videos', 'majestic-tube' ); ?></h2>

						<div class="video-grid">
						<?php
						while ( $related->have_posts() ) :
							$related->the_post();
							get_template_part( 'template-parts/content', 'video-card' );
						endwhile;
						wp_reset_postdata();
						?>
						</div>
					</section>
					<?php
				endif;
			endif;

			if ( majestic_tube_option_is_on( 'enable-comments' ) && ( comments_open() || get_comments_number() ) ) {
				comments_template();
			}
			?>

			</main>

			<?php if ( $show_video_sidebar ) : ?>
				<aside class="video-sidebar" aria-label="<?php esc_attr_e( 'Video sidebar', 'majestic-tube' ); ?>">
					<?php echo $video_sidebar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- widget output. ?>
				</aside>
			<?php endif; ?>
		</div>
	</div>

	<?php
endwhile;

get_footer();
