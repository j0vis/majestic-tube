<?php
/**
 * Video widget - grid of latest, most-viewed or random videos.
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Video listing widget.
 */
class MajesticTube_Video_Widget extends WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'majestic_tube_video_widget',
			__( 'Majestic Tube Videos', 'majestic-tube' ),
			array(
				'description' => __( 'Displays a list of videos: latest, most viewed or random.', 'majestic-tube' ),
			)
		);
	}

	/**
	 * Render the widget output.
	 *
	 * @param array $args     Widget args.
	 * @param array $instance Saved values.
	 */
	public function widget( $args, $instance ) {
		$title   = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Latest videos', 'majestic-tube' );
		$orderby = ! empty( $instance['orderby'] ) ? $instance['orderby'] : 'date';
		$count   = ! empty( $instance['count'] ) ? absint( $instance['count'] ) : 6;

		$query_args = array(
			'post_type'           => 'post',
			'posts_per_page'      => $count,
			'post_status'         => 'publish',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'orderby'             => $orderby,
		);

		if ( 'meta_value_num' === $orderby ) {
			$query_args['meta_key'] = 'post_views_count';
		}

		$videos = new WP_Query( $query_args );

		if ( ! $videos->have_posts() ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core widget wrapper.

		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core widget wrapper.
		}

		echo '<ul class="majestic-tube-widget-videos">';

		while ( $videos->have_posts() ) :
			$videos->the_post();
			?>
			<li class="majestic-tube-widget-video">
				<a class="majestic-tube-widget-thumb" href="<?php the_permalink(); ?>">
					<?php
					$thumb = majestic_tube_get_thumb_url( get_the_ID(), 'majestic-tube-thumb-small' );

					if ( $thumb ) {
						printf(
							'<img src="%1$s" alt="%2$s" loading="lazy" width="150" height="84" />',
							esc_url( $thumb ),
							esc_attr( the_title_attribute( array( 'echo' => false ) ) )
						);
					}

					$duration = majestic_tube_get_video_duration( majestic_tube_get_duration_seconds( get_the_ID() ) );

					if ( $duration ) {
						printf( '<span class="video-card-duration">%s</span>', esc_html( $duration ) );
					}
					?>
				</a>
				<div class="majestic-tube-widget-video-info">
					<a class="majestic-tube-widget-title" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					<span class="majestic-tube-widget-views"><?php echo esc_html( majestic_tube_get_human_number( majestic_tube_get_post_views( get_the_ID() ) ) ); ?></span>
				</div>
			</li>
			<?php
		endwhile;

		echo '</ul>';

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core widget wrapper.

		wp_reset_postdata();
	}

	/**
	 * Render the admin form.
	 *
	 * @param array $instance Saved values.
	 */
	public function form( $instance ) {
		$title   = isset( $instance['title'] ) ? $instance['title'] : __( 'Latest videos', 'majestic-tube' );
		$orderby = isset( $instance['orderby'] ) ? $instance['orderby'] : 'date';
		$count   = isset( $instance['count'] ) ? absint( $instance['count'] ) : 6;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'majestic-tube' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'orderby' ) ); ?>"><?php esc_html_e( 'Sort by:', 'majestic-tube' ); ?></label>
			<select id="<?php echo esc_attr( $this->get_field_id( 'orderby' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'orderby' ) ); ?>" class="widefat">
				<option value="date" <?php selected( $orderby, 'date' ); ?>><?php esc_html_e( 'Latest', 'majestic-tube' ); ?></option>
				<option value="meta_value_num" <?php selected( $orderby, 'meta_value_num' ); ?>><?php esc_html_e( 'Most viewed', 'majestic-tube' ); ?></option>
				<option value="rand" <?php selected( $orderby, 'rand' ); ?>><?php esc_html_e( 'Random', 'majestic-tube' ); ?></option>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( 'Number of videos:', 'majestic-tube' ); ?></label>
			<input id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>" type="number" min="1" max="24" value="<?php echo esc_attr( $count ); ?>" class="tiny-text" />
		</p>
		<?php
	}

	/**
	 * Save widget settings.
	 *
	 * @param array $new_instance New values.
	 * @param array $old_instance Old values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance            = array();
		$instance['title']   = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['orderby'] = isset( $new_instance['orderby'] ) && in_array( $new_instance['orderby'], array( 'date', 'meta_value_num', 'rand' ), true ) ? $new_instance['orderby'] : 'date';
		$instance['count']   = isset( $new_instance['count'] ) ? absint( $new_instance['count'] ) : 6;

		return $instance;
	}
}

/**
 * Register the widget.
 */
function majestic_tube_register_video_widget() {
	register_widget( 'MajesticTube_Video_Widget' );
}
add_action( 'widgets_init', 'majestic_tube_register_video_widget' );