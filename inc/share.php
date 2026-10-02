<?php
/**
 * Social sharing buttons - original per-network switches.
 *
 * The original theme printed a share block under the player, with one
 * wpst-options switch per network (facebook-video-share, twitter-video-share,
 * linkedin-video-share, ...). Those keys are part of the documented option
 * surface and the icons keep their original ids (`#facebook`, `#twitter`, ...)
 * because custom CSS written for the original theme targets them.
 *
 * Deliberate differences: the original also rendered a Google+ button, and
 * shipped Facebook, LinkedIn, Tumblr and Odnoklassniki. Google+ was shut down
 * in 2019 and plus.google.com/share no longer resolves. Facebook, LinkedIn and
 * Tumblr all remove or restrict adult content, so their share endpoints are
 * useless to - at best a nuisance for - a site of this kind, and Odnoklassniki
 * (ok.ru) is no longer reachable for an anonymous share. None of those five
 * networks prints a button. Their wpst-options keys stay mapped, so plugins
 * and custom code can still read a stored value, and their Customizer
 * toggles are hidden rather than removed outright.
 *
 * What remains: X/Twitter, Reddit, and email. A plugin that still needs one of
 * the retired networks can put it back through the majestic_tube_share_links
 * filter, which receives the finished link list before it is printed.
 *
 * @package Majestic Tube
 * @version 2.3.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Networks keyed by their wpst-options switch.
 *
 * The callback receives the share context and returns a URL, or an empty
 * string to skip the network.
 *
 * @return array<string, array{icon:string, label:string, url:callable}>
 */
function majestic_tube_share_networks() {
	return array(
		'twitter-video-share'      => array(
			'icon'  => 'twitter',
			'label' => __( 'Share on X', 'majestic-tube' ),
			'url'   => function ( $context ) {
				return 'https://twitter.com/intent/tweet?url=' . rawurlencode( $context['url'] ) . '&text=' . rawurlencode( $context['title'] );
			},
		),
		'reddit-video-share'       => array(
			'icon'  => 'reddit-square',
			'label' => __( 'Share on Reddit', 'majestic-tube' ),
			'url'   => function ( $context ) {
				return 'https://www.reddit.com/submit?url=' . rawurlencode( $context['url'] ) . '&title=' . rawurlencode( $context['title'] );
			},
		),
		'email-video-share'        => array(
			'icon'  => 'envelope',
			'label' => __( 'Share by email', 'majestic-tube' ),
			'url'   => function ( $context ) {
				return 'mailto:?subject=' . rawurlencode( $context['title'] ) . '&body=' . rawurlencode( $context['url'] );
			},
		),
	);
}

/**
 * The text a share link carries alongside the title.
 *
 * The excerpt when there is one, otherwise the post's own words, trimmed to the
 * length a network link preview will show. This is a share link, not a search
 * description: no SEO plugin is consulted and none is needed.
 *
 * @param WP_Post|null $post Post object.
 * @return string
 */
function majestic_tube_share_description( $post ) {

	if ( ! $post ) {
		return '';
	}

	if ( ! empty( $post->post_excerpt ) ) {
		return wp_trim_words( wp_strip_all_tags( $post->post_excerpt ), 55, '...' );
	}

	$content = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.

	return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $content ) ), 55, '...' );
}

/**
 * Print the share block for a post.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function majestic_tube_share_buttons( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();

	if ( ! $post_id || ! majestic_tube_option_is_on( 'enable-video-share' ) ) {
		return;
	}

	$context = array(
		'url'         => get_permalink( $post_id ),
		'title'       => get_the_title( $post_id ),
		'description' => majestic_tube_share_description( get_post( $post_id ) ),
	);

	$links = array();

	foreach ( majestic_tube_share_networks() as $option_key => $network ) {
		if ( ! majestic_tube_option_is_on( $option_key ) ) {
			continue;
		}

		$url = call_user_func( $network['url'], $context );

		if ( ! $url ) {
			continue;
		}

		$links[] = array(
			'id'    => sanitize_html_class( 'majestic-tube-share-' . str_replace( '-video-share', '', $option_key ) ),
			'icon'  => sanitize_html_class( $network['icon'] ),
			'label' => $network['label'],
			'url'   => $url,
		);
	}

	/**
	 * Filter the share links printed under the video player.
	 *
	 * @param array $links   Links, each with id/icon/label/url.
	 * @param int   $post_id Post ID.
	 */
	$links = apply_filters( 'majestic_tube_share_links', $links, $post_id );

	if ( ! $links ) {
		return;
	}
	?>
	<div class="video-share">
		<button type="button" class="button video-share-toggle">
			<?php majestic_tube_icon( 'share' ); ?> <?php esc_html_e( 'Share', 'majestic-tube' ); ?>
		</button>
		<div class="sharing-buttons">
			<?php foreach ( $links as $link ) : ?>
				<a target="_blank" rel="noopener nofollow" href="<?php echo esc_url( $link['url'] ); ?>"
					aria-label="<?php echo esc_attr( $link['label'] ); ?>">
					<i id="<?php echo esc_attr( $link['id'] ); ?>" class="fa fa-<?php echo esc_attr( $link['icon'] ); ?>" aria-hidden="true"></i>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}
