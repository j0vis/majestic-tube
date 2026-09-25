<?php
/**
 * Video reporting - front-end flag button plus moderation screens.
 *
 * Visitors can flag a broken or miscategorized video; reports are stored in
 * dedicated post meta and surfaced under Videos > Reported Videos, with a
 * dashboard widget for editors.
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Report reasons offered to visitors.
 *
 * @return array<string, string>
 */
function majestic_tube_report_reasons() {
	return array(
		'broken'  => __( 'The video does not play', 'majestic-tube' ),
		'wrong'   => __( 'Wrong video or thumbnail', 'majestic-tube' ),
		'spam'    => __( 'Spam or misleading', 'majestic-tube' ),
		'other'   => __( 'Something else', 'majestic-tube' ),
	);
}

/**
 * Build the shared query arguments for reported videos.
 *
 * The list and exact-count queries intentionally remain separate caches, but
 * their post status and meta filter must describe the same population.
 *
 * @param int           $posts_per_page Number of posts to request.
 * @param bool          $no_found_rows  Whether to skip found-row counting.
 * @param array|string  $orderby        Optional orderby clause.
 * @return array<string, mixed>
 */
function majestic_tube_reported_post_query_args( $posts_per_page, $no_found_rows = true, $orderby = null ) {
	$args = array(
		'post_type'        => 'post',
		'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
		'posts_per_page'   => absint( $posts_per_page ),
		'fields'           => 'ids',
		'no_found_rows'    => (bool) $no_found_rows,
		'suppress_filters' => false,
		'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'reported' => array(
				'key'     => 'reported_count',
				'value'   => 1,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			),
		),
	);

	if ( $orderby ) {
		$args['orderby'] = $orderby;
	}

	return $args;
}

/**
 * Read one report aggregate cache without merging its two independent keys.
 *
 * @param string $key Cache key suffix (`reported_ids` or `reported_count`).
 * @return mixed False when uncached.
 */
function majestic_tube_report_cache_get( $key ) {
	$cached = wp_cache_get( $key, MAJESTIC_TUBE_ANALYTICS_GROUP );

	if ( false !== $cached ) {
		return $cached;
	}

	$cached = get_transient( 'majestic_tube_' . $key );

	if ( false !== $cached ) {
		wp_cache_set( $key, $cached, MAJESTIC_TUBE_ANALYTICS_GROUP );
		return $cached;
	}

	return false;
}

/**
 * Store one report aggregate cache without merging its two independent keys.
 *
 * @param string $key   Cache key suffix.
 * @param mixed  $value Aggregate value.
 * @return void
 */
function majestic_tube_report_cache_set( $key, $value ) {
	set_transient( 'majestic_tube_' . $key, $value, 10 * MINUTE_IN_SECONDS );
	wp_cache_set( $key, $value, MAJESTIC_TUBE_ANALYTICS_GROUP );
}

/**
 * Report count for a video.
 *
 * @param int $post_id Post ID.
 * @return int
 */
function majestic_tube_get_report_count( $post_id ) {
	return majestic_tube_analytics_get_field( $post_id, 'reported' );
}

/**
 * Whether the current visitor already reported this video recently.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function majestic_tube_has_already_reported( $post_id ) {
	return majestic_tube_ip_was_recorded_recently( $post_id, 'reported_ips', DAY_IN_SECONDS );
}

/**
 * Register a report against a video.
 *
 * @param int    $post_id Post ID.
 * @param string $reason  Reason slug.
 * @param string $message Optional free-text message.
 * @return int New report count.
 */
function majestic_tube_add_report( $post_id, $reason = '', $message = '' ) {
	$count = majestic_tube_get_report_count( $post_id ) + 1;

	update_post_meta( $post_id, 'reported_count', $count );
	majestic_tube_analytics_set( $post_id, array( 'reported' => $count ) );
	majestic_tube_flush_report_cache();

	majestic_tube_record_ip_history( $post_id, 'reported_ips' );

	$reasons = get_post_meta( $post_id, 'reported_reasons', true );

	if ( ! is_array( $reasons ) ) {
		$reasons = array();
	}

	$entry = array(
		'reason'  => sanitize_key( $reason ),
		'message' => sanitize_textarea_field( $message ),
		'time'    => time(),
	);

	array_unshift( $reasons, $entry );
	update_post_meta( $post_id, 'reported_reasons', array_slice( $reasons, 0, 50 ) );

	/**
	 * Fires after a video report is stored.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $entry   Report entry.
	 * @param int   $count   Total reports for the video.
	 */
	do_action( 'majestic_tube_video_reported', $post_id, $entry, $count );

	return $count;
}

/**
 * Remove every report stored against a video.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function majestic_tube_clear_reports( $post_id ) {
	delete_post_meta( $post_id, 'reported_count' );
	delete_post_meta( $post_id, 'reported_ips' );
	delete_post_meta( $post_id, 'reported_reasons' );
	majestic_tube_analytics_set( $post_id, array( 'reported' => 0 ) );
	majestic_tube_flush_report_cache();

	/**
	 * Fires after a video's reports are cleared.
	 *
	 * @param int $post_id Post ID.
	 */
	do_action( 'majestic_tube_reports_cleared', $post_id );
}

/**
 * Last report entry for a video.
 *
 * @param int $post_id Post ID.
 * @return array|false
 */
function majestic_tube_get_last_report( $post_id ) {
	$reasons = get_post_meta( $post_id, 'reported_reasons', true );

	if ( ! is_array( $reasons ) || ! $reasons ) {
		return false;
	}	return reset( $reasons );
}

/**
 * Drop the cached report data (called whenever reports change).
 *
 * Clears both the transient (the durable copy, 10 min) and the object cache
 * entry that fronts it, so a report filed a second ago is never masked by a
 * cached aggregate.
 *
 * @return void
 */
function majestic_tube_flush_report_cache() {
	delete_transient( 'majestic_tube_reported_ids' );
	delete_transient( 'majestic_tube_reported_count' );

	wp_cache_delete( 'reported_ids', MAJESTIC_TUBE_ANALYTICS_GROUP );
	wp_cache_delete( 'reported_count', MAJESTIC_TUBE_ANALYTICS_GROUP );
}

/**
 * The complete, ordered list of reported post IDs.
 *
 * The list is always fetched once at full size and cached whole; callers slice
 * it. Previously a single transient held whatever the first caller happened to
 * request, so a dashboard widget asking for 5 IDs could leave the moderation
 * screen and the total count reporting five rows forever.
 *
 * The full list is capped so a pathological number of reports cannot exhaust
 * memory; the exact total comes from majestic_tube_count_reported_videos(),
 * which does not depend on this list.
 *
 * @return int[]
 */
function majestic_tube_get_all_reported_post_ids() {
	// Object cache first: it answers repeat reads within a request and across
	// requests on a persistent cache, without touching the options table the
	// transient lives in.
	$cached = majestic_tube_report_cache_get( 'reported_ids' );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	/*
	 * A single named meta_query clause is used for both filtering and ordering.
	 * Passing meta_key next to a meta_query would make WordPress join the
	 * postmeta table twice under different aliases.
	 */
	$ids = get_posts(
		majestic_tube_reported_post_query_args(
			500,
			true,
			array( 'reported' => 'DESC' )
		)
	);

	$ids = array_map( 'absint', $ids );

	majestic_tube_report_cache_set( 'reported_ids', $ids );

	return $ids;
}

/**
 * Post IDs with at least one report, most reported first.
 *
 * @param int $limit Max IDs to return (-1 for every cached ID).
 * @return int[]
 */
function majestic_tube_get_reported_post_ids( $limit = 50 ) {
	$ids = majestic_tube_get_all_reported_post_ids();

	return $limit > 0 ? array_slice( $ids, 0, absint( $limit ) ) : $ids;
}

/**
 * Total number of videos carrying at least one report.
 *
 * Counted with SQL rather than counted from the cached list, so the total is
 * exact and does not inherit the list's 500-ID cap.
 *
 * @return int
 */
function majestic_tube_count_reported_videos() {
	$count = majestic_tube_report_cache_get( 'reported_count' );

	if ( false !== $count ) {
		return (int) $count;
	}

	$query = new WP_Query(
		majestic_tube_reported_post_query_args( 1, false )
	);

	$count = (int) $query->found_posts;

	majestic_tube_report_cache_set( 'reported_count', $count );

	return $count;
}

/**
 * AJAX endpoint: flag a video (action name: report-video).
 *
 * @return void
 */
function majestic_tube_report_video() {
	majestic_tube_verify_ajax_nonce();

	$post_id = majestic_tube_require_ajax_post_id(
		__( 'Invalid video.', 'majestic-tube' ),
		__( 'Invalid video.', 'majestic-tube' )
	);
	$reason  = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '';
	$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

	if ( ! majestic_tube_option_is_on( 'enable-video-report' ) ) {
		wp_send_json_error( array( 'message' => __( 'Reporting is disabled.', 'majestic-tube' ) ), 403 );
	}

	$reasons = majestic_tube_report_reasons();

	if ( ! isset( $reasons[ $reason ] ) ) {
		$reason = 'other';
	}

	if ( majestic_tube_has_already_reported( $post_id ) ) {
		wp_send_json_success(
			array(
				'alreadyreported' => true,
				'count'           => majestic_tube_get_report_count( $post_id ),
				'message'         => __( 'You have already reported this video. Thanks!', 'majestic-tube' ),
			)
		);
	}

	$count = majestic_tube_add_report( $post_id, $reason, $message );

	wp_send_json_success(
		array(
			'alreadyreported' => false,
			'count'           => $count,
			'message'         => __( 'Thanks, our team will review this video.', 'majestic-tube' ),
		)
	);
}
add_action( 'wp_ajax_report-video', 'majestic_tube_report_video' );
add_action( 'wp_ajax_nopriv_report-video', 'majestic_tube_report_video' );

/**
 * Print the report button and panel on single videos.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function majestic_tube_report_button( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();

	if ( ! $post_id || ! majestic_tube_option_is_on( 'enable-video-report' ) ) {
		return;
	}

	$count = majestic_tube_get_report_count( $post_id );
	?>
	<div class="video-report">
		<button type="button" class="video-report-toggle" aria-expanded="false" aria-controls="video-report-panel-<?php echo esc_attr( $post_id ); ?>">
			<?php esc_html_e( 'Report this video', 'majestic-tube' ); ?>
			<?php if ( $count && current_user_can( 'edit_post', $post_id ) ) : ?>
				<span class="video-report-count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
			<?php endif; ?>
		</button>

		<form class="video-report-form" id="video-report-panel-<?php echo esc_attr( $post_id ); ?>" hidden data-post_id="<?php echo esc_attr( $post_id ); ?>">
			<label class="video-report-label" for="video-report-reason-<?php echo esc_attr( $post_id ); ?>">
				<?php esc_html_e( 'What is wrong with this video?', 'majestic-tube' ); ?>
			</label>

			<select name="reason" id="video-report-reason-<?php echo esc_attr( $post_id ); ?>">
				<?php foreach ( majestic_tube_report_reasons() as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<label class="video-report-label" for="video-report-message-<?php echo esc_attr( $post_id ); ?>">
				<?php esc_html_e( 'Details (optional)', 'majestic-tube' ); ?>
			</label>
			<textarea name="message" id="video-report-message-<?php echo esc_attr( $post_id ); ?>" rows="3"></textarea>

			<button type="submit" class="video-report-submit"><?php esc_html_e( 'Send report', 'majestic-tube' ); ?></button>
			<span class="video-report-feedback" role="status" aria-live="polite"></span>
		</form>
	</div>
	<?php
}

/**
 * Register the moderation screen under the Videos menu.
 *
 * @return void
 */
function majestic_tube_register_reports_page() {
	add_submenu_page(
		'edit.php',
		__( 'Reported Videos', 'majestic-tube' ),
		__( 'Reported Videos', 'majestic-tube' ) . majestic_tube_reports_menu_bubble(),
		'edit_others_posts',
		'majestic-tube-reports',
		'majestic_tube_render_reports_page'
	);
}
add_action( 'admin_menu', 'majestic_tube_register_reports_page' );

/**
 * Pending reports bubble for the admin menu.
 *
 * @return string
 */
function majestic_tube_reports_menu_bubble() {
	$count = majestic_tube_count_reported_videos();

	if ( ! $count ) {
		return '';
	}

	return ' <span class="update-plugins count-' . esc_attr( $count ) . '"><span class="plugin-count">' . esc_html( number_format_i18n( $count ) ) . '</span></span>';
}

/**
 * Handle the "clear reports" admin action.
 *
 * @return void
 */
function majestic_tube_handle_clear_reports() {
	if ( ! isset( $_GET['post'] ) ) {
		return;
	}

	$post_id = absint( wp_unslash( $_GET['post'] ) );

	if ( ! $post_id || ! isset( $_GET['_wpnonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'majestic_tube_clear_reports_' . $post_id ) ) {
		wp_die(
			esc_html__( 'Security check failed.', 'majestic-tube' ),
			esc_html__( 'Reported Videos', 'majestic-tube' ),
			array( 'response' => 403 )
		);
	}

	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die(
			esc_html__( 'You are not allowed to moderate reports.', 'majestic-tube' ),
			esc_html__( 'Reported Videos', 'majestic-tube' ),
			array( 'response' => 403 )
		);
	}

	majestic_tube_clear_reports( $post_id );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'    => 'majestic-tube-reports',
				'cleared' => 1,
			),
			admin_url( 'edit.php' )
		)
	);
	exit;
}
add_action( 'admin_post_majestic_tube_clear_reports', 'majestic_tube_handle_clear_reports' );

/**
 * Render the reported videos screen.
 *
 * @return void
 */
function majestic_tube_render_reports_page() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}

	$post_ids = majestic_tube_get_reported_post_ids();
	$reasons  = majestic_tube_report_reasons();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Reported Videos', 'majestic-tube' ); ?></h1>

		<?php if ( isset( $_GET['cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Reports cleared for that video.', 'majestic-tube' ); ?></p>
			</div>
		<?php endif; ?>

		<?php
		if ( ! $post_ids ) {
			printf( '<p>%s</p></div>', esc_html__( 'No video has been reported yet.', 'majestic-tube' ) );
			return;
		}
		?>

		<p class="description">
			<?php esc_html_e( 'Reports are kept in post meta (reported_count, reported_reasons, reported_ips). Clear a video once it has been fixed to reset its counter.', 'majestic-tube' ); ?>
		</p>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Video', 'majestic-tube' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Reports', 'majestic-tube' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Last reason', 'majestic-tube' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Reported', 'majestic-tube' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'majestic-tube' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $post_ids as $post_id ) :
					$post     = get_post( $post_id );
					$last     = majestic_tube_get_last_report( $post_id );
					$reason   = ( $last && isset( $reasons[ $last['reason'] ] ) ) ? $reasons[ $last['reason'] ] : __( 'Not specified', 'majestic-tube' );
					$message  = ( $last && ! empty( $last['message'] ) ) ? $last['message'] : '';
					$clear    = wp_nonce_url(
						admin_url( 'admin-post.php?action=majestic_tube_clear_reports&post=' . $post_id ),
						'majestic_tube_clear_reports_' . $post_id
					);
					?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></strong>
							<?php if ( 'publish' !== $post->post_status ) : ?>
								<em><?php echo esc_html( ucfirst( $post->post_status ) ); ?></em>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( number_format_i18n( majestic_tube_get_report_count( $post_id ) ) ); ?></td>
						<td>
							<?php echo esc_html( $reason ); ?>
							<?php if ( $message ) : ?>
								<br><small><?php echo esc_html( wp_trim_words( $message, 20 ) ); ?></small>
							<?php endif; ?>
						</td>
						<td>
							<?php
							if ( $last && ! empty( $last['time'] ) ) {
								printf(
									'<time datetime="%1$s">%2$s</time>',
									esc_attr( gmdate( DATE_W3C, (int) $last['time'] ) ),
									esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last['time'] ) )
								);
							}
							?>
						</td>
						<td>
							<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php esc_html_e( 'View', 'majestic-tube' ); ?></a> |
							<a href="<?php echo esc_url( $clear ); ?>"><?php esc_html_e( 'Clear reports', 'majestic-tube' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Dashboard widget listing the most reported videos.
 *
 * @return void
 */
function majestic_tube_register_reports_widget() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}

	wp_add_dashboard_widget(
		'majestic_tube_reported_videos',
		__( 'Reported videos', 'majestic-tube' ),
		'majestic_tube_render_reports_widget'
	);
}
add_action( 'wp_dashboard_setup', 'majestic_tube_register_reports_widget' );

/**
 * Render the dashboard widget.
 *
 * @return void
 */
function majestic_tube_render_reports_widget() {
	$post_ids = majestic_tube_get_reported_post_ids( 5 );
	$total    = majestic_tube_count_reported_videos();
	$reasons  = majestic_tube_report_reasons();

	if ( ! $post_ids ) {
		printf( '<p>%s</p>', esc_html__( 'No video has been reported yet.', 'majestic-tube' ) );
		return;
	}

	printf(
		'<p>%s</p>',
		esc_html(
			sprintf(
				/* translators: %s: number of reported videos. */
				_n( '%s video has pending reports.', '%s videos have pending reports.', $total, 'majestic-tube' ),
				number_format_i18n( $total )
			)
		)
	);

	echo '<ul>';

	foreach ( $post_ids as $post_id ) {
		$last   = majestic_tube_get_last_report( $post_id );
		$reason = ( $last && isset( $reasons[ $last['reason'] ] ) ) ? $reasons[ $last['reason'] ] : '';

		printf(
			'<li><a href="%1$s">%2$s</a> (%3$s)%4$s</li>',
			esc_url( get_edit_post_link( $post_id ) ),
			esc_html( get_the_title( $post_id ) ),
			esc_html( number_format_i18n( majestic_tube_get_report_count( $post_id ) ) ),
			$reason ? ' &ndash; ' . esc_html( $reason ) : ''
		);
	}

	echo '</ul>';

	printf(
		'<p><a class="button" href="%s">%s</a></p>',
		esc_url( admin_url( 'edit.php?page=majestic-tube-reports' ) ),
		esc_html__( 'Review all reports', 'majestic-tube' )
	);
}
