<?php
/**
 * Widget areas and the neutral content widget.
 *
 * Content is managed through ordinary WordPress widgets. The placement names
 * and wrappers deliberately use neutral language so common content blockers
 * do not mistake the theme's widget chrome for a specific promotional format.
 *
 * @package Majestic Tube
 * @version 2.0.9
 */

defined( 'ABSPATH' ) || exit;

/**
 * Theme content areas available in the WordPress Widgets screen.
 *
 * These are placement areas, not a second page-sidebar system. The video
 * sidebar is rendered only by single.php; the other areas are used by the
 * header, player, and footer templates.
 *
 * @return array<string, array<string, string>>
 */
function majestic_tube_content_widget_areas() {
	return array(
		'header'        => array(
			'id'          => 'majestic-tube-header-content',
			'name'        => esc_html__( 'Header content', 'majestic-tube' ),
			'description' => esc_html__( 'Content displayed below the site header.', 'majestic-tube' ),
		),
		'player'        => array(
			'id'          => 'majestic-tube-player-content',
			'name'        => esc_html__( 'Player content', 'majestic-tube' ),
			'description' => esc_html__( 'Content displayed over a desktop video player.', 'majestic-tube' ),
		),
		'under-player'  => array(
			'id'          => 'majestic-tube-below-player',
			'name'        => esc_html__( 'Below-player content', 'majestic-tube' ),
			'description' => esc_html__( 'Content displayed below a video player.', 'majestic-tube' ),
		),
		'video-sidebar' => array(
			'id'          => 'majestic-tube-video-sidebar',
			'name'        => esc_html__( 'Video sidebar', 'majestic-tube' ),
			'description' => esc_html__( 'Content displayed beside individual video pages.', 'majestic-tube' ),
		),
		'footer'        => array(
			'id'          => 'majestic-tube-footer-content',
			'name'        => esc_html__( 'Footer content', 'majestic-tube' ),
			'description' => esc_html__( 'Content displayed at the top of the site footer.', 'majestic-tube' ),
		),
	);
}

/**
 * A small, neutral widget for administrator-provided markup and shortcodes.
 *
 * The widget is intentionally generic: it can be placed in any theme content
 * area and its instance data is not tied to a particular content network.
 */
class Majestic_Tube_Content_Widget extends WP_Widget {
	/**
	 * Register the widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'majestic_tube_content',
			__( 'Content Block', 'majestic-tube' ),
			array(
				'classname'                   => 'majestic-tube-content-widget',
				'description'                 => __( 'Add administrator-provided markup or shortcodes to a theme content area.', 'majestic-tube' ),
				'customize_selective_refresh' => true,
			)
		);
	}

	/**
	 * Render one content block on the front end.
	 *
	 * @param array $args     Sidebar arguments.
	 * @param array $instance Saved widget instance.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		$raw_content = isset( $instance['content'] ) ? $instance['content'] : '';
		$device      = isset( $instance['device'] ) ? $instance['device'] : 'all';
		$is_mobile   = majestic_tube_is_mobile();

		if ( 'desktop' === $device && $is_mobile ) {
			return;
		}

		if ( 'mobile' === $device && ! $is_mobile ) {
			return;
		}

		$content = majestic_tube_prepare_content( $raw_content, 'widget' );

		if ( '' === trim( $content ) ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core sidebar markup.

		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		if ( '' !== trim( (string) $title ) ) {
			echo $args['before_title'] . $title . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core title markup and filtered title.
		}

		printf(
			'<div class="majestic-tube-content-block">%s</div>',
			$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- administrator-provided markup.
		);
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core sidebar markup.
	}

	/**
	 * Render the widget form in the Customizer and Widgets screens.
	 *
	 * @param array $instance Saved widget instance.
	 * @return void
	 */
	public function form( $instance ) {
		$title   = isset( $instance['title'] ) ? $instance['title'] : '';
		$content = isset( $instance['content'] ) ? $instance['content'] : '';
		$device  = isset( $instance['device'] ) ? $instance['device'] : 'all';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Optional title', 'majestic-tube' ); ?></label>
			<input class="widefat" type="text" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'content' ) ); ?>"><?php esc_html_e( 'Content', 'majestic-tube' ); ?></label>
			<textarea class="widefat" rows="8" id="<?php echo esc_attr( $this->get_field_id( 'content' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'content' ) ); ?>"><?php echo esc_textarea( $content ); ?></textarea>
			<span class="description"><?php esc_html_e( 'Administrator-provided markup and shortcodes are supported.', 'majestic-tube' ); ?></span>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'device' ) ); ?>"><?php esc_html_e( 'Display on', 'majestic-tube' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'device' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'device' ) ); ?>">
				<option value="all" <?php selected( $device, 'all' ); ?>><?php esc_html_e( 'All devices', 'majestic-tube' ); ?></option>
				<option value="desktop" <?php selected( $device, 'desktop' ); ?>><?php esc_html_e( 'Desktop only', 'majestic-tube' ); ?></option>
				<option value="mobile" <?php selected( $device, 'mobile' ); ?>><?php esc_html_e( 'Mobile only', 'majestic-tube' ); ?></option>
			</select>
		</p>
		<?php
	}

	/**
	 * Sanitize a widget instance before WordPress saves it.
	 *
	 * @param array $new_instance Submitted values.
	 * @param array $old_instance Previously saved values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array(
			'title'   => isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '',
			'content' => isset( $new_instance['content'] ) ? majestic_tube_sanitize_ad_code( $new_instance['content'] ) : '',
			'device'  => isset( $new_instance['device'] ) && in_array( $new_instance['device'], array( 'all', 'desktop', 'mobile' ), true ) ? $new_instance['device'] : 'all',
		);

		return $instance;
	}
}

/**
 * Capture the rendered output of a registered widget area.
 *
 * Buffering lets placement helpers distinguish an area containing widgets from
 * an area whose device-targeted widgets all returned no output.
 *
 * @param string $area_id Registered widget-area id.
 * @return string
 */
function majestic_tube_widget_area_content( $area_id ) {
	if ( ! is_active_sidebar( $area_id ) ) {
		return '';
	}

	ob_start();
	dynamic_sidebar( $area_id );

	return (string) ob_get_clean();
}

/**
 * Register the ordinary footer area and all theme content areas.
 */
function majestic_tube_widgets_init() {
	$shared = array(
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	);

	register_sidebar(
		array_merge(
			$shared,
			array(
				'name'        => esc_html__( 'Footer', 'majestic-tube' ),
				'id'          => 'majestic-tube-footer',
				'description' => esc_html__( 'Display widgets in your footer.', 'majestic-tube' ),
			)
		)
	);

	$content_before_widget = '<section id="%1$s" class="widget majestic-tube-content-widget %2$s">';

	foreach ( majestic_tube_content_widget_areas() as $area ) {
		register_sidebar(
			array(
				'name'          => $area['name'],
				'id'            => $area['id'],
				'description'   => $area['description'],
				'before_widget' => $content_before_widget,
				'after_widget'  => '</section>',
				'before_title'  => '<h2 class="widget-title">',
				'after_title'   => '</h2>',
			)
		);
	}

	register_widget( 'Majestic_Tube_Content_Widget' );
}
add_action( 'widgets_init', 'majestic_tube_widgets_init' );

/**
 * Migrate saved legacy content into Content Block widget instances once.
 *
 * Existing values are copied, never deleted. New installs and later edits are
 * managed in the Widgets screen, while the original wpst-options keys remain
 * available to data tools and legacy integrations.
 *
 * @return void
 */
function majestic_tube_migrate_content_widgets() {
	if ( 1 <= (int) get_option( 'majestic_tube_content_widgets_migrated', 0 ) ) {
		return;
	}

	$instances = get_option( 'widget_majestic_tube_content', array() );
	$sidebars  = get_option( 'sidebars_widgets', array() );
	$instances = is_array( $instances ) ? $instances : array();
	$sidebars  = is_array( $sidebars ) ? $sidebars : array();
	$areas     = majestic_tube_content_widget_areas();
	$next_id   = 1;
	$changed   = false;

	foreach ( majestic_tube_legacy_content_widget_map() as $location => $legacy_values ) {
		if ( ! isset( $areas[ $location ]['id'] ) ) {
			continue;
		}

		$area_id = $areas[ $location ]['id'];

		if ( ! isset( $sidebars[ $area_id ] ) || ! is_array( $sidebars[ $area_id ] ) ) {
			$sidebars[ $area_id ] = array();
		}

		foreach ( $legacy_values as $legacy_key => $device ) {
			$content = majestic_tube_get_ad( $legacy_key );

			if ( '' === $content ) {
				continue;
			}

			while ( isset( $instances[ $next_id ] ) ) {
				++$next_id;
			}

			$instances[ $next_id ] = array(
				'title'   => '',
				'content' => $content,
				'device'  => $device,
			);
			$sidebars[ $area_id ][] = 'majestic_tube_content-' . $next_id;
			++$next_id;
			$changed = true;
		}
	}

	if ( $changed ) {
		update_option( 'widget_majestic_tube_content', $instances );
	}

	update_option( 'sidebars_widgets', $sidebars );
	update_option( 'majestic_tube_content_widgets_migrated', 1 );
}
add_action( 'after_setup_theme', 'majestic_tube_migrate_content_widgets', 25 );
add_action( 'after_switch_theme', 'majestic_tube_migrate_content_widgets', 25 );
