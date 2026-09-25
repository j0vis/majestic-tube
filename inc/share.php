<?php
/**
 * Social sharing buttons - original per-network switches.
 *
 * The original theme printed a share block under the player, with one
 * wpst-options switch per network (facebook-video-share, twitter-video-share,
 * linkedin-video-share, ...). Those keys are part of the documented option
 * surface, so Majestic Tube renders the same set of links, with the same ids
 * on the icons (`#facebook`, `#twitter`, ...) because custom CSS targets them.
 *
 * Deliberate difference: the original also rendered a Google+ button. Google+
 * was shut down in 2019 and plus.google.com/share no longer resolves, so the
 * google-plus-video-share option is mapped (plugins and custom code can still
 * read it) but no dead link is printed.
 *
 * @package Majestic Tube
 * @version 2.0.0
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
		'facebook-video-share'     => array(
			'icon'  => 'facebook',
			'label' => __( 'Share on Facebook', 'majestic-tube' ),
			'url'   => function ( $context ) {
				return 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $context['url'] );
			},
		),
		'twitter-video-share'      => array(
			'icon'  => 'twitter',
			'label' => __( 'Share on X', 'majestic-tube' ),
			'url'   => function ( $context ) {
				return 'https://twitter.com/intent/tweet?url=' . rawurlencode( $context['url'] ) . '&text=' . rawurlencode( $context['title'] );
			},
		),
		'linkedin-video-share'     => array(
			'icon'  => 'linkedin',
			'label' => __( 'Share on LinkedIn', 'majestic-tube' ),
			'url'   => function ( $context ) {
				return 'https://www.linkedin.com/shareArticle?mini=true&url=' . rawurlencode( $context['url'] ) . '&title=' . rawurlencode( $context['title'] ) . '&source=' . rawurlencode( home_url( '/' ) );
			},
		),
		'tumblr-video-share'       => array(
			'icon'  => 'tumblr-square',
			'label' => __( 'Share on Tumblr', 'majestic-tube' ),
			'url'   => function ( $context ) {
				return 'https://tumblr.com/widgets/share/tool?canonicalUrl=' . rawurlencode( $context['url'] );
			},
		),
		'reddit-video-share'       => array(
			'icon'  => 'reddit-square',
			'label' => __( 'Share on Reddit', 'majestic-tube' ),
			'url'   => function ( $context ) {
				return 'https://www.reddit.com/submit?url=' . rawurlencode( $context['url'] ) . '&title=' . rawurlencode( $context['title'] );
			},
		),
		'odnoklassniki-video-share' => array(
			'icon'  => 'odnoklassniki',
			'label' => __( 'Share on Odnoklassniki', 'majestic-tube' ),
			'url'   => function ( $context ) {
				return 'https://www.odnoklassniki.ru/dk?st.cmd=addShare&st._surl=' . rawurlencode( $context['url'] ) . '&title=' . rawurlencode( $context['title'] );
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
		'description' => majestic_tube_get_social_description( get_post( $post_id ) ),
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
			'id'    => 'majestic-tube-share-' . str_replace( '-video-share', '', $option_key ),
			'icon'  => $network['icon'],
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
			<i class="fa fa-share-alt" aria-hidden="true"></i> <?php esc_html_e( 'Share', 'majestic-tube' ); ?>
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
