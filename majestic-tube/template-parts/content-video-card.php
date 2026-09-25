<?php
/**
 * Video card template part - grid item for video listings.
 *
 * The wrapper keeps the original theme's classes and data attributes
 * (`thumb-block`, `video-preview-item`, `data-post-id`, `data-main-thumb`,
 * `data-thumbs`, `data-trailer`) because WP-Script plugins, child themes and
 * custom CSS written for the original theme target them, plus the
 * `wps_paywall_premium_badge` filter the paywall plugin hooks into.
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

$post_id     = get_the_ID();
$thumb_url   = majestic_tube_get_thumb_url( $post_id );
$trailer_url = majestic_tube_get_trailer_url( $post_id );
$duration    = majestic_tube_get_video_duration( majestic_tube_get_duration_seconds( $post_id ) );
$views       = majestic_tube_get_post_views( $post_id );
$rate        = majestic_tube_get_post_like_rate( $post_id );
$hd          = majestic_tube_is_hd_video( $post_id );
$has_trailer = ! empty( $trailer_url );

if ( ! majestic_tube_option_is_on( 'enable-views-system' ) ) {
	$views = 0;
}

if ( ! majestic_tube_option_is_on( 'enable-duration-system' ) ) {
	$duration = false;
}

if ( ! majestic_tube_option_is_on( 'enable-rating-system' ) ) {
	$rate = 0;
}

/*
 * The original browser-side preview is driven from the card: a trailer wins
 * over the rotation thumbnails, and the rotation thumbnails are only exported
 * when the enable-thumbnails-rotation option is on.
 */
$thumbs = array();

if ( ! $has_trailer && majestic_tube_option_is_on( 'enable-thumbnails-rotation' ) ) {
	$thumbs = majestic_tube_get_multithumbs( $post_id );
}

/*
 * Data attributes written onto the card, each paired with how it must be
 * escaped. The original theme used esc_url() for data-main-thumb and esc_attr()
 * for the rest, selected by comparing the attribute name against a literal -
 * which meant a future attribute silently inherited whichever branch matched.
 * Declaring the kind alongside the value keeps the two together.
 *
 * 'url' attributes get esc_url(), which validates the protocol and entity
 * encodes the query string; 'attr' attributes get esc_attr(). data-thumbs stays
 * 'attr' on purpose: it is a comma-separated list of URLs, not one URL, and the
 * original contract has templates feeding it straight into esc_attr().
 */
$card_attrs = array(
	'data-video-id'   => array( wp_unique_id( 'video_' ), 'attr' ),
	'data-main-thumb' => array( $thumb_url, 'url' ),
);

if ( $has_trailer ) {
	$card_attrs['data-trailer'] = array( $trailer_url, 'url' );
} elseif ( $thumbs ) {
	$card_attrs['data-thumbs'] = array( implode( ',', $thumbs ), 'attr' );
}

$post_class = array( 'thumb-block', 'video-preview-item', 'video-card' );

if ( 1 === (int) majestic_tube_get_option( 'wpst-options', 'videos-per-row-mobile', 2 ) ) {
	$post_class[] = 'full-width';
}
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( $post_class ); ?> data-post-id="<?php echo esc_attr( $post_id ); ?>"
	<?php
	foreach ( $card_attrs as $attr => $pair ) {
		list( $value, $kind ) = $pair;

		if ( '' === $value ) {
			continue;
		}

		printf(
			' %1$s="%2$s"',
			esc_attr( $attr ),
			'url' === $kind ? esc_url( $value ) : esc_attr( $value )
		);
	}
	?>
>
	<a class="video-card-link" href="<?php the_permalink(); ?>" title="<?php echo esc_attr( the_title_attribute( array( 'echo' => false ) ) ); ?>">

		<div class="post-thumbnail inner-border video-card-thumbnail">
			<?php
			/*
			 * The paywall plugin prints its premium badge here. Escaped by the
			 * plugin, kept raw like the original theme does.
			 */
			echo apply_filters( 'wps_paywall_premium_badge', '', $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plugin-owned markup.
			?>

			<div class="video-overlay"></div>

			<?php if ( $thumb_url ) : ?>
				<div class="post-thumbnail-container">
					<img class="video-main-thumb" src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( the_title_attribute( array( 'echo' => false ) ) ); ?>" loading="lazy" width="320" height="180" />
				</div>
			<?php else : ?>
				<div class="post-thumbnail-container no-thumb">
					<span><?php esc_html_e( 'No image', 'majestic-tube' ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( $hd ) : ?>
				<span class="hd-video video-card-hd"><?php esc_html_e( 'HD', 'majestic-tube' ); ?></span>
			<?php endif; ?>

			<?php if ( $views ) : ?>
				<span class="views video-card-views"><?php echo esc_html( majestic_tube_get_human_number( $views ) ); ?></span>
			<?php endif; ?>

			<?php if ( $duration ) : ?>
				<span class="duration video-card-duration"><?php echo esc_html( $duration ); ?></span>
			<?php endif; ?>

			<?php if ( $rate ) : ?>
				<span class="rating video-card-rating"><?php echo esc_html( $rate ); ?>%</span>
			<?php endif; ?>
		</div>

		<header class="entry-header video-card-header">
			<span><?php the_title(); ?></span>
		</header>
	</a>
</article>
