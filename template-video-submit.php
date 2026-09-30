<?php
/**
 * Template Name: Submit a Video
 *
 * Front-end video submission form. Creates a pending post with the original
 * meta keys (video_url, embed, thumb, duration) and sets the video format.
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

$error              = '';
$success            = '';
$submission_enabled = majestic_tube_option_is_on( 'enable-video-submission' );
$logged_in          = is_user_logged_in();

// Handle submission only while the feature is enabled and the visitor is logged in.
if ( $submission_enabled && $logged_in && isset( $_POST['wpst-submitted'] ) ) {
	if ( ! isset( $_POST['wpst-post_nonce_field'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpst-post_nonce_field'] ) ), 'post_nonce' ) ) {
		$error = __( 'Security check failed. Please try again.', 'majestic-tube' );
	} else {
		$captcha_ok = true;

		if ( majestic_tube_captcha_is_configured() ) {
			$field      = majestic_tube_captcha_token_field();
			$token      = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
			$captcha_ok = majestic_tube_verify_captcha( $token );

			if ( ! $captcha_ok ) {
				$error = __( 'Captcha verification failed, please try again.', 'majestic-tube' );
			}
		}

		if ( $captcha_ok ) {
			$title              = isset( $_POST['wpst-video_title'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['wpst-video_title'] ) ), 0, 200 ) : '';
			$desc               = isset( $_POST['wpst-video_description'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['wpst-video_description'] ) ), 0, 5000 ) : '';
			$video              = isset( $_POST['wpst-video_url'] ) ? esc_url_raw( wp_unslash( $_POST['wpst-video_url'] ) ) : '';
			$embed              = isset( $_POST['wpst-embed'] ) ? majestic_tube_kses_player_markup( wp_unslash( $_POST['wpst-embed'] ) ) : '';
			$thumb              = isset( $_POST['wpst-thumb'] ) ? esc_url_raw( wp_unslash( $_POST['wpst-thumb'] ) ) : '';
			$category           = isset( $_POST['wpst-category_selected'] ) ? absint( wp_unslash( $_POST['wpst-category_selected'] ) ) : 0;
			$tags               = isset( $_POST['wpst-tags'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['wpst-tags'] ) ), 0, 500 ) : '';
			$actors             = isset( $_POST['wpst-actors'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['wpst-actors'] ) ), 0, 500 ) : '';

			if ( $category && ! term_exists( $category, 'category' ) ) {
				$category = 0;
			}
			$duration_raw       = array(
				'hh' => '',
				'mm' => '',
				'ss' => '',
			);

			foreach ( array( 'hh', 'mm', 'ss' ) as $unit ) {
				$field = 'wpst-duration_' . $unit;

				if ( isset( $_POST[ $field ] ) && ! is_array( $_POST[ $field ] ) ) {
					$duration_raw[ $unit ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
				}
			}

			$duration_fields_ok = '' !== $duration_raw['hh'] && '' !== $duration_raw['mm'] && '' !== $duration_raw['ss'];
			$hh                 = absint( $duration_raw['hh'] );
			$mm                 = absint( $duration_raw['mm'] );
			$ss                 = absint( $duration_raw['ss'] );
			$duration           = $hh * 3600 + $mm * 60 + $ss;
			$submission         = array(
				'title'       => $title,
				'description' => $desc,
				'video'       => $video,
				'embed'       => $embed,
				'thumbnail'   => $thumb,
				'tags'        => $tags,
				'actors'      => $actors,
			);

			if ( $duration_fields_ok ) {
				$submission['duration_hh'] = $duration_raw['hh'];
				$submission['duration_mm'] = $duration_raw['mm'];
				$submission['duration_ss'] = $duration_raw['ss'];
			}

			$validation_errors = majestic_tube_validate_video_submission( $submission );

			if ( $validation_errors ) {
				$error = implode( ' ', $validation_errors );
			} else {
				$post_information = array(
					'post_title'    => $title,
					'post_content'  => $desc,
					'post_type'     => 'post',
					'post_status'   => 'pending',
					'post_category' => $category ? array( $category ) : array(),
					'tax_input'     => array(
						'post_tag' => $tags,
						'actors'   => $actors,
					),
				);

				$post_id = wp_insert_post( $post_information, true );

				if ( is_wp_error( $post_id ) || ! $post_id ) {
					$error = __( 'Could not create the submission. Please try again.', 'majestic-tube' );
				} else {
					if ( $video ) {
						update_post_meta( $post_id, 'video_url', $video );
					}
					if ( $embed ) {
						update_post_meta( $post_id, 'embed', $embed );
					}
					if ( $thumb ) {
						update_post_meta( $post_id, 'thumb', $thumb );
					}
					if ( $duration_fields_ok ) {
						update_post_meta( $post_id, 'duration', $duration );
					}
					set_post_format( $post_id, 'video' );

					/*
					 * wp_insert_post() applies tax_input only when the acting user
					 * holds the taxonomy's assign_terms capability, and that
					 * capability is edit_posts for both post_tag and the actors
					 * taxonomy. A member who registered through the theme is a
					 * subscriber, so the tags and actors typed into this form were
					 * handed to the insert and then silently dropped. Neither call
					 * below is capability gated, and the post is already known to be
					 * this visitor's own pending submission.
					 */
					if ( $tags ) {
						// wp_set_post_terms() splits the comma list itself.
						wp_set_post_terms( $post_id, $tags, 'post_tag' );
					}

					/*
					 * wp_set_object_terms() does not split a comma list - it would
					 * store the whole string as one actor named "a, b" - so the list
					 * is split here first.
					 */
					$actor_names = array();

					foreach ( explode( ',', (string) $actors ) as $actor_name ) {
						$actor_name = trim( $actor_name );

						if ( '' !== $actor_name ) {
							$actor_names[] = $actor_name;
						}
					}

					if ( $actor_names && taxonomy_exists( 'actors' ) ) {
						wp_set_object_terms( $post_id, $actor_names, 'actors' );
					}

					$success = __( 'Thanks for submitting a video! Your submission is being moderated.', 'majestic-tube' );
				}
			}
		}
	}
}

$title_required       = majestic_tube_video_submission_field_is_required( 'title' );
$description_required = majestic_tube_video_submission_field_is_required( 'description' );
$video_required       = majestic_tube_video_submission_field_is_required( 'video' );
$embed_required       = majestic_tube_video_submission_field_is_required( 'embed' );
$thumb_required       = majestic_tube_video_submission_field_is_required( 'thumbnail' );
$duration_required    = majestic_tube_video_submission_field_is_required( 'duration' );
$tags_required        = majestic_tube_video_submission_field_is_required( 'tags' );
$actors_required      = majestic_tube_video_submission_field_is_required( 'actors' );

get_header();
?>

<div id="primary" class="content-area video-submit-area">
	<main id="main" class="site-main">

		<?php if ( $submission_enabled ) : ?>

			<header class="entry-header">
				<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
			</header>

			<?php if ( $error ) : ?>
				<div class="majestic-tube-alert error" role="alert"><?php echo esc_html( $error ); ?></div>
			<?php endif; ?>

			<?php if ( ! $logged_in ) : ?>

				<div class="majestic-tube-alert info" role="status">
					<p>
						<?php esc_html_e( 'You must be logged in to submit a video. Please', 'majestic-tube' ); ?>
						<a href="<?php echo esc_url( '#wpst-login' ); ?>"><?php esc_html_e( 'log in', 'majestic-tube' ); ?></a>
						<?php esc_html_e( 'or', 'majestic-tube' ); ?>
						<a href="<?php echo esc_url( '#wpst-register' ); ?>"><?php esc_html_e( 'register', 'majestic-tube' ); ?>.</a>
					</p>
				</div>

			<?php elseif ( $success ) : ?>
				<div class="majestic-tube-alert success" role="status"><?php echo esc_html( $success ); ?></div>
			<?php else : ?>

				<form id="wpst_video_submit_form" action="<?php echo esc_url( get_permalink() ); ?>" method="post" class="video-submit-form">

					<?php
					majestic_tube_render_submission_field( 'video_title', __( 'Video title', 'majestic-tube' ), 'text', $title_required );
					majestic_tube_render_submission_field( 'video_description', __( 'Description', 'majestic-tube' ), 'textarea', $description_required, 5 );
					majestic_tube_render_submission_field( 'video_url', __( 'Video URL (mp4, webm)', 'majestic-tube' ), 'url', $video_required );
					majestic_tube_render_submission_field( 'embed', __( '...or embed code (YouTube iframe, etc.)', 'majestic-tube' ), 'textarea', $embed_required, 3 );
					majestic_tube_render_submission_field( 'thumb', __( 'Thumbnail URL', 'majestic-tube' ), 'url', $thumb_required );
					?>

					<div class="form-field">
						<label>
							<?php esc_html_e( 'Duration', 'majestic-tube' ); ?>
							<?php if ( $duration_required ) : ?><span class="required" aria-hidden="true">*</span><?php endif; ?>
						</label>
						<div class="duration-fields">
							<input type="number" min="0" max="23" name="wpst-duration_hh" placeholder="HH"<?php echo esc_attr( $duration_required ? ' required' : '' ); ?> />
							<input type="number" min="0" max="59" name="wpst-duration_mm" placeholder="MM"<?php echo esc_attr( $duration_required ? ' required' : '' ); ?> />
							<input type="number" min="0" max="59" name="wpst-duration_ss" placeholder="SS"<?php echo esc_attr( $duration_required ? ' required' : '' ); ?> />
						</div>
					</div>

					<div class="form-field">
						<label for="wpst-category_selected"><?php esc_html_e( 'Category', 'majestic-tube' ); ?></label>
						<?php
						wp_dropdown_categories(
							array(
								'id'                => 'wpst-category_selected',
								'name'              => 'wpst-category_selected',
								'show_option_none' => __( 'Select a category', 'majestic-tube' ),
								'option_none_value' => '0',
								'hierarchical'      => true,
								'hide_empty'        => false,
							)
						);
						?>
					</div>

					<?php
					majestic_tube_render_submission_field( 'tags', __( 'Tags (comma separated)', 'majestic-tube' ), 'text', $tags_required );
					majestic_tube_render_submission_field( 'actors', __( 'Actors (comma separated)', 'majestic-tube' ), 'text', $actors_required );
					?>

					<?php if ( majestic_tube_captcha_is_configured() ) : ?>
						<div class="form-field">
							<?php majestic_tube_captcha_widget(); ?>
						</div>
					<?php endif; ?>

					<div class="form-field">
						<button type="submit"><?php esc_html_e( 'Submit video', 'majestic-tube' ); ?></button>
					</div>

					<input type="hidden" name="wpst-submitted" value="1" />
					<?php wp_nonce_field( 'post_nonce', 'wpst-post_nonce_field' ); ?>

				</form>

			<?php endif; ?>

		<?php else : ?>

			<p><?php esc_html_e( 'Video submission is currently disabled.', 'majestic-tube' ); ?></p>

		<?php endif; ?>

	</main>
</div>

<?php
get_footer();
