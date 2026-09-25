<?php
/**
 * Video meta box - WP-Script compatible meta keys.
 *	 * Uses the exact same post meta keys as the original theme so data is
	 * interchangeable: video_url, video_url_240..4k, embed, shortcode,
	 * duration, post_views_count, likes_count, dislikes_count, voted_IP,
	 * thumbs, trailer_url, thumb, hd_video, tracking_url,
	 * unique_ad_under_player.
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Canonical definitions for the video metabox.
 *
 * Keeping labels and sanitizer types together prevents the save loop and the
 * rendered field list from drifting apart. The map remains filterable below;
 * filters can still add or alter saved fields without changing the fixed UI
 * labels, matching the original metabox contract.
 *
 * @return array<string, array{type:string,label:string}>
 */
function majestic_tube_video_meta_definitions() {
	return array(
		'video_url'      => array( 'type' => 'url', 'label' => __( 'Video URL', 'majestic-tube' ) ),
		'video_url_240'  => array( 'type' => 'url', 'label' => __( 'Video URL 240p', 'majestic-tube' ) ),
		'video_url_360'  => array( 'type' => 'url', 'label' => __( 'Video URL 360p', 'majestic-tube' ) ),
		'video_url_480'  => array( 'type' => 'url', 'label' => __( 'Video URL 480p', 'majestic-tube' ) ),
		'video_url_720'  => array( 'type' => 'url', 'label' => __( 'Video URL 720p', 'majestic-tube' ) ),
		'video_url_1080' => array( 'type' => 'url', 'label' => __( 'Video URL 1080p', 'majestic-tube' ) ),
		'video_url_4k'   => array( 'type' => 'url', 'label' => __( 'Video URL 4K', 'majestic-tube' ) ),
		'embed'          => array( 'type' => 'html', 'label' => __( 'Video embed code', 'majestic-tube' ) ),
		'shortcode'      => array( 'type' => 'html', 'label' => __( 'Video shortcode', 'majestic-tube' ) ),
		'duration'       => array( 'type' => 'int', 'label' => __( 'Duration (seconds)', 'majestic-tube' ) ),
		'post_views_count' => array( 'type' => 'int', 'label' => __( 'Views', 'majestic-tube' ) ),
		'likes_count'    => array( 'type' => 'int', 'label' => __( 'Likes', 'majestic-tube' ) ),
		'dislikes_count' => array( 'type' => 'int', 'label' => __( 'Dislikes', 'majestic-tube' ) ),
		'trailer_url'    => array( 'type' => 'url', 'label' => __( 'Video trailer URL', 'majestic-tube' ) ),
		'thumb'          => array( 'type' => 'url', 'label' => __( 'Main thumbnail', 'majestic-tube' ) ),
		'tracking_url'   => array( 'type' => 'url', 'label' => __( 'Tracking URL', 'majestic-tube' ) ),
		'hd_video'       => array( 'type' => 'onoff', 'label' => __( 'HD video', 'majestic-tube' ) ),
		'unique_ad_under_player' => array( 'type' => 'html', 'label' => __( 'Advertising under the video player', 'majestic-tube' ) ),
	);
}

/**
 * Video meta keys managed by the metabox, with their sanitization types.
 *
 * @return array<string, string> meta_key => sanitizer.
 */
function majestic_tube_video_meta_fields() {
	$fields = array();

	foreach ( majestic_tube_video_meta_definitions() as $key => $definition ) {
		$fields[ $key ] = $definition['type'];
	}

	/**
	 * Filter the metabox-managed meta fields.
	 *
	 * @param array<string, string> $fields meta_key => sanitizer.
	 */
	return apply_filters( 'majestic_tube_video_meta_fields', $fields );
}

/**
 * Map front-end submission fields to the original required-field options.
 *
 * @return array<string, string> Submission field => legacy option id.
 */
function majestic_tube_video_submission_required_fields() {
	return array(
		'title'       => 'video-submit-title-required',
		'description' => 'video-submit-description-required',
		'video'       => 'video-submit-video-link-required',
		'embed'       => 'video-submit-embed-required',
		'thumbnail'   => 'video-submit-thumbnail-link-required',
		'tags'        => 'video-submit-tags-required',
		'actors'      => 'video-submit-actors-required',
		'duration'    => 'video-submit-duration-required',
	);
}

/**
 * Whether a front-end video submission field is required.
 *
 * @param string $field Submission field key.
 * @return bool
 */
function majestic_tube_video_submission_field_is_required( $field ) {
	$required_fields = majestic_tube_video_submission_required_fields();

	return isset( $required_fields[ $field ] ) && majestic_tube_option_is_on( $required_fields[ $field ] );
}

/**
 * Render one front-end submission field with its configured required state.
 *
 * @param string $name     Field name without the `wpst-` prefix.
 * @param string $label    Visible label.
 * @param string $type     Input type, or `textarea`.
 * @param bool   $required Whether the field is required.
 * @param int    $rows     Textarea rows.
 * @return void
 */
function majestic_tube_render_submission_field( $name, $label, $type = 'text', $required = false, $rows = 5 ) {
	$field_id = 'wpst-' . $name;
	?>
	<div class="form-field">
		<label for="<?php echo esc_attr( $field_id ); ?>">
			<?php echo esc_html( $label ); ?>
			<?php if ( $required ) : ?><span class="required" aria-hidden="true">*</span><?php endif; ?>
		</label>
		<?php if ( 'textarea' === $type ) : ?>
			<textarea id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_id ); ?>" rows="<?php echo absint( $rows ); ?>"<?php echo esc_attr( $required ? ' required' : '' ); ?>></textarea>
		<?php else : ?>
			<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_id ); ?>"<?php echo esc_attr( $required ? ' required' : '' ); ?> />
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Validate a sanitized front-end video submission against the original options.
 *
 * HTML required attributes remain a usability aid; this server-side check keeps
 * the configured switches authoritative when a request bypasses the browser.
 *
 * @param array<string, mixed> $fields Sanitized submission values.
 * @return string[] Validation errors.
 */
function majestic_tube_validate_video_submission( $fields ) {
	$errors   = array();
	$messages = array(
		'title'       => __( 'Please provide a video title.', 'majestic-tube' ),
		'description' => __( 'Please provide a video description.', 'majestic-tube' ),
		'video'       => __( 'Please provide a video URL.', 'majestic-tube' ),
		'embed'       => __( 'Please provide an embed code.', 'majestic-tube' ),
		'thumbnail'   => __( 'Please provide a thumbnail URL.', 'majestic-tube' ),
		'tags'        => __( 'Please provide at least one tag.', 'majestic-tube' ),
		'actors'      => __( 'Please provide at least one actor.', 'majestic-tube' ),
	);

	foreach ( $messages as $field => $message ) {
		if ( majestic_tube_video_submission_field_is_required( $field ) &&
			'' === trim( (string) ( isset( $fields[ $field ] ) ? $fields[ $field ] : '' ) ) ) {
			$errors[] = $message;
		}
	}

	$duration_keys = array( 'duration_hh', 'duration_mm', 'duration_ss' );
	$has_duration  = true;

	foreach ( $duration_keys as $key ) {
		if ( ! array_key_exists( $key, $fields ) || '' === trim( (string) $fields[ $key ] ) ) {
			$has_duration = false;
			break;
		}
	}

	if ( majestic_tube_video_submission_field_is_required( 'duration' ) && ! $has_duration ) {
		$errors[] = __( 'Please provide the video duration.', 'majestic-tube' );
	}

	if ( $has_duration ) {
		$duration_is_numeric = true;

		foreach ( $duration_keys as $key ) {
			if ( ! preg_match( '/^\d+$/', (string) $fields[ $key ] ) ) {
				$duration_is_numeric = false;
				break;
			}
		}

		$hours   = absint( $fields['duration_hh'] );
		$minutes = absint( $fields['duration_mm'] );
		$seconds = absint( $fields['duration_ss'] );

		if ( ! $duration_is_numeric || $hours > 23 || $minutes > 59 || $seconds > 59 ) {
			$errors[] = __( 'Please enter a valid video duration.', 'majestic-tube' );
		}
	}

	return array_values( array_unique( $errors ) );
}

/**
 * Register the video information metabox on posts.
 */
function majestic_tube_add_video_meta_box() {
	add_meta_box(
		'majestic-tube-video-information',
		__( 'Video information', 'majestic-tube' ),
		'majestic_tube_video_meta_box_callback',
		'post',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'majestic_tube_add_video_meta_box' );

/**
 * Render a single metabox field row.
 *
 * @param string $key   Meta key.
 * @param string $label Field label.
 * @param string $type  Sanitizer type (url|int|html|onoff).
 * @param int    $post_id Post ID.
 */
function majestic_tube_render_meta_field( $key, $label, $type, $post_id ) {
	$value = get_post_meta( $post_id, $key, true );

	echo '<p class="majestic-tube-meta-field majestic-tube-meta-' . esc_attr( $type ) . '">';
	echo '<label for="majestic_tube_field_' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label>';

	switch ( $type ) {
		case 'html':
			printf(
				'<textarea id="%1$s" name="%2$s" class="large-text" rows="3">%3$s</textarea>',
				esc_attr( 'majestic_tube_field_' . $key ),
				esc_attr( 'majestic_tube_meta_' . $key ),
				esc_textarea( $value )
			);
			break;
		case 'int':
			printf(
				'<input type="number" min="0" step="1" id="%1$s" name="%2$s" value="%3$s" class="small-text" />',
				esc_attr( 'majestic_tube_field_' . $key ),
				esc_attr( 'majestic_tube_meta_' . $key ),
				esc_attr( $value )
			);
			break;
		case 'onoff':
			printf(
				'<select id="%1$s" name="%2$s">
					<option value="on" %3$s>%4$s</option>
					<option value="off" %5$s>%6$s</option>
				</select>',
				esc_attr( 'majestic_tube_field_' . $key ),
				esc_attr( 'majestic_tube_meta_' . $key ),
				checked( $value, 'on', false ),
				esc_html__( 'On', 'majestic-tube' ),
				checked( $value, 'off', false ),
				esc_html__( 'Off', 'majestic-tube' )
			);
			break;
		default: // url.
			printf(
				'<input type="url" id="%1$s" name="%2$s" value="%3$s" class="large-text code" />',
				esc_attr( 'majestic_tube_field_' . $key ),
				esc_attr( 'majestic_tube_meta_' . $key ),
				esc_attr( $value )
			);
	}

	echo '</p>';
}

/**
 * Render the video information metabox.
 *
 * @param WP_Post $post Current post.
 */
function majestic_tube_video_meta_box_callback( $post ) {
	wp_nonce_field( 'majestic_tube_video_meta', 'majestic_tube_video_meta_nonce' );

	$sanitizers = majestic_tube_video_meta_fields();

	foreach ( majestic_tube_video_meta_definitions() as $key => $definition ) {
		majestic_tube_render_meta_field(
			$key,
			$definition['label'],
			isset( $sanitizers[ $key ] ) ? $sanitizers[ $key ] : $definition['type'],
			$post->ID
		);
	}
}

/**
 * Save video meta with nonce verification, capability check and sanitization.
 *
 * @param int $post_id Post ID.
 */
function majestic_tube_save_video_meta( $post_id ) {
	if ( ! isset( $_POST['majestic_tube_video_meta_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['majestic_tube_video_meta_nonce'] ) ), 'majestic_tube_video_meta' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	/*
	 * Writing to a revision is never intended. WordPress creates a revision
	 * inside the same request that saves the post, and save_post fires again
	 * for the revision; the nonce is still present in $_POST and
	 * map_meta_cap() lets the author edit their own revision, so the guard
	 * above would pass and every video meta key would be duplicated onto the
	 * revision row.
	 */
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( majestic_tube_video_meta_fields() as $key => $type ) {
		$field_name = 'majestic_tube_meta_' . $key;

		if ( ! isset( $_POST[ $field_name ] ) ) {
			continue;
		}

		$raw = wp_unslash( $_POST[ $field_name ] );

		switch ( $type ) {
			case 'url':
				$value = esc_url_raw( $raw );
				break;
			case 'int':
				$value = absint( $raw );
				break;
			case 'onoff':
				$value = ( 'on' === $raw ) ? 'on' : 'off';
				break;
			default:
				$value = majestic_tube_sanitize_ad_code( $raw );
		}

		update_post_meta( $post_id, $key, $value );
	}
}
add_action( 'save_post', 'majestic_tube_save_video_meta' );

/**
 * Get post views for a post id (original meta key).
 *
 * Reads through the shared analytics layer, so a card on an archive does not
 * issue its own meta query and repeat page loads are served from cache.
 *
 * @param int $post_id Post ID.
 * @return int
 */
function majestic_tube_get_post_views( $post_id ) {
	return majestic_tube_analytics_get_field( $post_id, 'views' );
}

/**
 * Increment post views atomically.
 *
 * Delegates to the analytics layer, which does the single-statement UPDATE
 * (so concurrent requests cannot lose a view), creates the row when it is
 * missing, and repopulates the cache from the value the database reports.
 *
 * @param int $post_id Post ID.
 * @return int New count.
 */
function majestic_tube_increment_post_views( $post_id ) {
	return majestic_tube_analytics_increment_views( $post_id );
}

/**
 * Get the like rate percentage for a post (original keys).
 *
 * This is the single source of truth for the rating percentage: the AJAX
 * handlers, the stored `rate` meta and every card badge all call it. The
 * handlers previously used ceil() while this used round(), so a post with 1
 * like out of 2 votes reported 50 immediately after voting and 33 after the
 * next page load. round() is kept because it is what the display path uses and
 * because it does not inflate a single vote into a visible 1% swing.
 *
 * @param int $post_id Post ID.
 * @return int Percentage 0-100.
 */
function majestic_tube_get_post_like_rate( $post_id ) {
	return majestic_tube_analytics_like_rate( $post_id );
}

/**
 * Format a duration in seconds as H:i:s or i:s like the original.
 *
 * @param int $seconds Duration in seconds.
 * @return string|false Formatted duration or false if <= 0.
 */
function majestic_tube_format_duration( $seconds ) {
	$seconds = absint( $seconds );

	if ( $seconds <= 0 ) {
		return false;
	}

	return $seconds >= 3600 ? gmdate( 'H:i:s', $seconds ) : gmdate( 'i:s', $seconds );
}

/**
 * Read a video's duration in seconds.
 *
 * @param int $post_id Post ID, defaults to the current post.
 * @return int
 */
function majestic_tube_get_duration_seconds( $post_id = 0 ) {
	$post_id = $post_id ? absint( $post_id ) : absint( get_the_ID() );

	return $post_id ? absint( get_post_meta( $post_id, 'duration', true ) ) : 0;
}

/**
 * Read a video's tracking URL.
 *
 * @param int $post_id Post ID, defaults to the current post.
 * @return string
 */
function majestic_tube_get_tracking_url( $post_id = 0 ) {
	$post_id = $post_id ? absint( $post_id ) : absint( get_the_ID() );

	return $post_id ? (string) get_post_meta( $post_id, 'tracking_url', true ) : '';
}

/**
 * Whether a video has the original HD marker.
 *
 * @param int $post_id Post ID, defaults to the current post.
 * @return bool
 */
function majestic_tube_is_hd_video( $post_id = 0 ) {
	$post_id = $post_id ? absint( $post_id ) : absint( get_the_ID() );

	return $post_id && 'on' === get_post_meta( $post_id, 'hd_video', true );
}

/**
 * Formatted duration of a post.
 *
 * @param int $seconds Duration in seconds. 0 reads the current post meta.
 * @return string|false Formatted duration or false if <= 0.
 */
function majestic_tube_get_video_duration( $seconds = 0 ) {
	if ( ! $seconds ) {
		$seconds = majestic_tube_get_duration_seconds();
	}

	return majestic_tube_format_duration( $seconds );
}

/**
 * Human-readable number like 1.5K / 2M (original helper behavior).
 *
 * @param int $input Number.
 * @return string
 */
function majestic_tube_get_human_number( $input = 0 ) {
	$input = absint( $input );

	if ( $input < 1000 ) {
		return (string) $input;
	}

	$units = array(
		1000000000000 => 'T',
		1000000000    => 'B',
		1000000       => 'M',
		1000          => 'K',
	);

	foreach ( $units as $divisor => $suffix ) {
		if ( $input >= $divisor ) {
			$floored = floor( $input / $divisor );

			return number_format( $floored ) . $suffix;
		}
	}

	return (string) $input;
}