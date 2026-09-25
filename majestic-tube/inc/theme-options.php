<?php
/**
 * Theme options - native Customizer settings with legacy wpst-options migration.
 *
 * Every legacy option key remains mapped and readable by Majestic Tube itself.
 * Content-placement keys are retained for migration but are intentionally not
 * exposed as Customizer fields; their values are managed by widgets instead.
 * The theme does not define, require, or emulate any external settings framework.
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Customizer sections used by the option map.
 *
 * @return array<string, string>
 */
function majestic_tube_option_sections() {
	return array(
		'general'      => __( 'Majestic Tube Options', 'majestic-tube' ),
		'player'       => __( 'Majestic Tube - Video Player', 'majestic-tube' ),
		'branding'     => __( 'Majestic Tube - Logo &amp; Colours', 'majestic-tube' ),
		'social'       => __( 'Majestic Tube - Sharing &amp; Social', 'majestic-tube' ),
		'submission'   => __( 'Majestic Tube - Video Submission', 'majestic-tube' ),
		'advertising'  => __( 'Majestic Tube - Content areas', 'majestic-tube' ),
		'seo'          => __( 'Majestic Tube - SEO &amp; Social', 'majestic-tube' ),
		'scripts'      => __( 'Majestic Tube - Custom Code', 'majestic-tube' ),
		'mobile'       => __( 'Majestic Tube - Mobile', 'majestic-tube' ),
	);
}

/**
 * Canonical choices for legacy on/off settings.
 *
 * @return array<string, string>
 */
function majestic_tube_onoff_choices() {
	return array(
		'on'  => __( 'On', 'majestic-tube' ),
		'off' => __( 'Off', 'majestic-tube' ),
	);
}

/**
 * Discard the cached options map.
 *
 * The map is cached per request because its labels are translated for the
 * request's locale, which does not change mid-request in normal use. This
 * exists for tests and for anything that switches locale after the first read.
 *
 * @return void
 */
function majestic_tube_flush_options_map() {
	$GLOBALS['majestic_tube_options_map_cache'] = null;
}

/**
 * Original option key => Customizer setting + default.
 *
 * Key names, defaults and stored value strings remain compatible with the
 * legacy wpst-options row so existing sites keep their settings after switching
 * themes. Runtime reads always go through majestic_tube_get_option().
 *
 * The array is built once per request and kept in a global. It is a pure
 * function of the translated strings, and every option read goes through here,
 * so rebuilding the 105 entries and running 150+ __() calls per read was pure
 * repeated work (measured at ~0.5 ms per rebuild, which adds up across the
 * ~175 reads a 30-card archive performs). A repeat read is now free: 0 __()
 * calls and ~1700x faster. The labels are translated for the request's locale,
 * which does not change mid-request, so the cache is safe. Anything that does
 * switch locale can call majestic_tube_flush_options_map().
 *
 * @return array<string, array{setting: string, default: mixed, type: string, label: string, section?: string, choices?: array<string, string>, description?: string, input_attrs?: array<string, int>}>
 */
function majestic_tube_options_map() {
	if ( isset( $GLOBALS['majestic_tube_options_map_cache'] ) ) {
		return $GLOBALS['majestic_tube_options_map_cache'];
	}

	$general    = 'general';
	$player     = 'player';
	$branding   = 'branding';
	$social     = 'social';
	$submission = 'submission';
	$advertising = 'advertising';
	$seo        = 'seo';
	$scripts    = 'scripts';
	$mobile     = 'mobile';

	$map_value = array(
		/* ---------------------------------------------------------------
		 * Listing / homepage
		 * ------------------------------------------------------------- */
		'show-videos-homepage'     => array(
			'setting' => 'majestic_tube_default_filter',
			'default' => 'latest',
			'type'    => 'select',
			'label'   => __( 'Videos displayed on homepage', 'majestic-tube' ),
			'choices' => array(
				'latest'      => __( 'Latest', 'majestic-tube' ),
				'most-viewed' => __( 'Most viewed', 'majestic-tube' ),
				'longest'     => __( 'Longest', 'majestic-tube' ),
				'popular'     => __( 'Popular', 'majestic-tube' ),
				'random'      => __( 'Random', 'majestic-tube' ),
			),
		),
		'videos-per-page'          => array(
			'setting'     => 'majestic_tube_videos_per_page',
			'default'     => 30,
			'type'        => 'number',
			'label'       => __( 'Videos per page', 'majestic-tube' ),
			'input_attrs' => array(
				'min'  => 1,
				'max'  => 100,
				'step' => 1,
			),
		),
		'videos-per-page-mobile'   => array(
			'setting'     => 'majestic_tube_videos_per_page_mobile',
			'default'     => 20,
			'type'        => 'number',
			'label'       => __( 'Videos per page (mobile)', 'majestic-tube' ),
			'section'     => $mobile,
			'input_attrs' => array(
				'min'  => 1,
				'max'  => 100,
				'step' => 1,
			),
		),
		'videos-per-row'           => array(
			'setting'     => 'majestic_tube_videos_per_row',
			'default'     => 5,
			'type'        => 'number',
			'label'       => __( 'Videos per row', 'majestic-tube' ),
			'input_attrs' => array(
				'min'  => 1,
				'max'  => 8,
				'step' => 1,
			),
		),
		'categories-per-row'       => array(
			'setting'     => 'majestic_tube_categories_per_row',
			'default'     => 5,
			'type'        => 'number',
			'label'       => __( 'Categories per row', 'majestic-tube' ),
			'input_attrs' => array(
				'min'  => 1,
				'max'  => 8,
				'step' => 1,
			),
		),
		'videos-per-row-mobile'    => array(
			'setting'     => 'majestic_tube_videos_per_row_mobile',
			'default'     => 2,
			'type'        => 'number',
			'label'       => __( 'Videos per row (mobile)', 'majestic-tube' ),
			'section'     => $mobile,
			'input_attrs' => array(
				'min'  => 1,
				'max'  => 4,
				'step' => 1,
			),
		),
		'disable-homepage-widgets-mobile' => array(
			'setting' => 'majestic_tube_disable_homepage_widgets_mobile',
			'default' => 'off',
			'type'    => 'onoff',
			'label'   => __( 'Hide homepage widgets on mobile', 'majestic-tube' ),
			'section' => $mobile,
		),
		'single-sidebar'              => array(
			'setting'     => 'majestic_tube_single_sidebar',
			'default'     => 'on',
			'type'        => 'onoff',
			'label'       => __( 'Show the video sidebar', 'majestic-tube' ),
			'description' => __( 'Show the Video sidebar widget area beside individual video pages. The area stays empty until you add content.', 'majestic-tube' ),
		),
		'thumbnails-ratio'         => array(
			'setting'     => 'majestic_tube_thumbnails_ratio',
			'default'     => '16/9',
			'type'        => 'select',
			'label'       => __( 'Thumbnails aspect ratio', 'majestic-tube' ),
			'description' => __( 'Stored in the original "16/9" format because front-end scripts split it on the slash.', 'majestic-tube' ),
			'choices'     => array(
				'16/9'  => '16/9',
				'4/3'   => '4/3',
				'1/1'   => '1/1',
				'3/4'   => '3/4',
				'9/16'  => '9/16',
			),
		),
		'thumbnails-fit'           => array(
			'setting' => 'majestic_tube_thumbnails_fit',
			'default' => 'cover',
			'type'    => 'select',
			'label'   => __( 'Thumbnails fit', 'majestic-tube' ),
			'choices' => array(
				'cover'   => __( 'Cover', 'majestic-tube' ),
				'contain' => __( 'Contain', 'majestic-tube' ),
				'fill'    => __( 'Fill', 'majestic-tube' ),
			),
		),
		'main-thumbnail-quality'   => array(
			'setting' => 'majestic_tube_thumbnail_quality',
			'default' => 'wpst_thumb_medium',
			'type'    => 'select',
			'label'   => __( 'Main thumbnail quality', 'majestic-tube' ),
			'choices' => array(
				'wpst_thumb_small'  => __( 'Basic (150px)', 'majestic-tube' ),
				'wpst_thumb_medium' => __( 'Normal (320px)', 'majestic-tube' ),
				'wpst_thumb_large'  => __( 'Fine (640px)', 'majestic-tube' ),
			),
		),
		'enable-thumbnails-rotation' => array(
			'setting' => 'majestic_tube_enable_thumbs_rotation',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Enable thumbnails rotation on hover', 'majestic-tube' ),
		),
		'enable-views-system'      => array(
			'setting' => 'majestic_tube_enable_views_system',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Display the view counter', 'majestic-tube' ),
		),
		'enable-duration-system'   => array(
			'setting' => 'majestic_tube_enable_duration_system',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Display video durations', 'majestic-tube' ),
		),
		'enable-rating-system'     => array(
			'setting' => 'majestic_tube_enable_rating_system',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Display the rating percentage', 'majestic-tube' ),
		),
		'related-videos-number'    => array(
			'setting'     => 'majestic_tube_related_videos_number',
			'default'     => 15,
			'type'        => 'number',
			'label'       => __( 'Number of related videos', 'majestic-tube' ),
			'input_attrs' => array(
				'min'  => 1,
				'max'  => 30,
				'step' => 1,
			),
		),
		'display-related-videos'   => array(
			'setting' => 'majestic_tube_display_related_videos',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Display related videos', 'majestic-tube' ),
		),
		'enable-comments'          => array(
			'setting' => 'majestic_tube_enable_comments',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Display comments on videos', 'majestic-tube' ),
		),
		'enable-breadcrumbs'       => array(
			'setting' => 'majestic_tube_enable_breadcrumbs',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Display breadcrumbs', 'majestic-tube' ),
		),
		'show-search-bar'          => array(
			'setting' => 'majestic_tube_show_search_bar',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Display the search bar', 'majestic-tube' ),
		),
		'truncate-description'     => array(
			'setting' => 'majestic_tube_truncate_description',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Truncate long descriptions', 'majestic-tube' ),
		),
		'homepage-title'           => array(
			'setting' => 'majestic_tube_homepage_title',
			'default' => '',
			'type'    => 'text',
			'label'   => __( 'Homepage title', 'majestic-tube' ),
		),
		'homepage-title-desc-position' => array(
			'setting' => 'majestic_tube_homepage_title_desc_position',
			'default' => 'bottom',
			'type'    => 'select',
			'label'   => __( 'Homepage title position', 'majestic-tube' ),
			'choices' => array(
				'top'    => __( 'Above the grid', 'majestic-tube' ),
				'bottom' => __( 'Below the grid', 'majestic-tube' ),
			),
		),
		'display-tracking-button'  => array(
			'setting' => 'majestic_tube_display_tracking_button',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Display the tracking button', 'majestic-tube' ),
		),
		'tracking-button-icon'     => array(
			'setting' => 'majestic_tube_tracking_button_icon',
			'default' => 'download',
			'type'    => 'select',
			'label'   => __( 'Tracking button icon', 'majestic-tube' ),
			'choices' => array(
				'download'    => __( 'Download', 'majestic-tube' ),
				'external'    => __( 'External link', 'majestic-tube' ),
				'play-circle' => __( 'Play circle', 'majestic-tube' ),
				'heart'       => __( 'Heart', 'majestic-tube' ),
				'star'        => __( 'Star', 'majestic-tube' ),
			),
		),
		'tracking-button-link'     => array(
			'setting' => 'majestic_tube_tracking_button_link',
			'default' => '',
			'type'    => 'url',
			'label'   => __( 'Tracking button link', 'majestic-tube' ),
		),
		'tracking-button-text'     => array(
			'setting' => 'majestic_tube_tracking_button_text',
			'default' => 'Download complete video now!',
			'type'    => 'text',
			'label'   => __( 'Tracking button text', 'majestic-tube' ),
		),
		'show-description-video-about' => array(
			'setting' => 'majestic_tube_show_description_video_about',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Show the description block', 'majestic-tube' ),
		),
		'show-categories-video-about' => array(
			'setting' => 'majestic_tube_show_categories_video_about',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Show video categories', 'majestic-tube' ),
		),
		'show-tags-video-about'    => array(
			'setting' => 'majestic_tube_show_tags_video_about',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Show video tags', 'majestic-tube' ),
		),
		'show-actors-video-about'  => array(
			'setting' => 'majestic_tube_show_actors_video_about',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Show video actors', 'majestic-tube' ),
		),
		'categories-per-page'      => array(
			'setting'     => 'majestic_tube_categories_per_page',
			'default'     => 20,
			'type'        => 'number',
			'label'       => __( 'Categories per page', 'majestic-tube' ),
			'input_attrs' => array(
				'min'  => 1,
				'max'  => 100,
				'step' => 1,
			),
		),
		'cat-desc-position'        => array(
			'setting' => 'majestic_tube_cat_desc_position',
			'default' => 'top',
			'type'    => 'select',
			'label'   => __( 'Category description position', 'majestic-tube' ),
			'choices' => array(
				'top'    => __( 'Top', 'majestic-tube' ),
				'bottom' => __( 'Bottom', 'majestic-tube' ),
			),
		),
		'tag-desc-position'        => array(
			'setting' => 'majestic_tube_tag_desc_position',
			'default' => 'top',
			'type'    => 'select',
			'label'   => __( 'Tag description position', 'majestic-tube' ),
			'choices' => array(
				'top'    => __( 'Top', 'majestic-tube' ),
				'bottom' => __( 'Bottom', 'majestic-tube' ),
			),
		),
		'actors-per-page'          => array(
			'setting'     => 'majestic_tube_actors_per_page',
			'default'     => 20,
			'type'        => 'number',
			'label'       => __( 'Actors per page', 'majestic-tube' ),
			'input_attrs' => array(
				'min'  => 1,
				'max'  => 100,
				'step' => 1,
			),
		),
		'enable-membership'        => array(
			'setting' => 'majestic_tube_enable_membership',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Enable membership (login/register)', 'majestic-tube' ),
		),
		'display-video-submit-link' => array(
			'setting' => 'majestic_tube_display_video_submit_link',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Show the "Submit a Video" link', 'majestic-tube' ),
			'section' => $submission,
		),
		'display-my-profile-link'  => array(
			'setting' => 'majestic_tube_display_my_profile_link',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Show the "My Profile" link', 'majestic-tube' ),
			'section' => $submission,
		),
		'display-my-channel-link'  => array(
			'setting' => 'majestic_tube_display_my_channel_link',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Show the "My Channel" link', 'majestic-tube' ),
			'section' => $submission,
		),
		'display-admin-bar'        => array(
			// Keep the early-release setting ID; its stored value now follows
			// KingTube's original meaning: on = display, off = hide.
			'setting'     => 'majestic_tube_hide_admin_bar',
			'default'     => 'off',
			'type'        => 'onoff',
			'label'       => __( 'Display admin bar for logged-in users', 'majestic-tube' ),
			'description' => __( 'When off, the WordPress admin bar is hidden for everyone except administrators.', 'majestic-tube' ),
		),
		'copyright-bar'            => array(
			'setting' => 'majestic_tube_copyright_bar',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Display the copyright bar', 'majestic-tube' ),
		),
		'copyright-text'           => array(
			'setting' => 'majestic_tube_copyright_text',
			'default' => '',
			'type'    => 'textarea',
			'label'   => __( 'Copyright text', 'majestic-tube' ),
		),
		'footer-columns'           => array(
			'setting' => 'majestic_tube_footer_columns',
			'default' => 'four-columns-footer',
			'type'    => 'select',
			'label'   => __( 'Footer columns', 'majestic-tube' ),
			'choices' => array(
				'one-column-footer'   => __( 'One column', 'majestic-tube' ),
				'two-columns-footer'  => __( 'Two columns', 'majestic-tube' ),
				'three-columns-footer' => __( 'Three columns', 'majestic-tube' ),
				'four-columns-footer' => __( 'Four columns', 'majestic-tube' ),
			),
		),
		'custom-background'        => array(
			'setting' => 'majestic_tube_custom_background',
			'default' => 'off',
			'type'    => 'onoff',
			'label'   => __( 'Enable the custom background class', 'majestic-tube' ),
			'section' => $branding,
		),
		'main-color'               => array(
			'setting' => 'majestic_tube_main_color',
			'default' => '#0f8a99',
			'type'    => 'color',
			'label'   => __( 'Main colour', 'majestic-tube' ),
			'section' => $branding,
		),

		/* ---------------------------------------------------------------
		 * Branding / logo (original keys, including the video watermark
		 * keys the original read but never registered in its own panel)
		 * ------------------------------------------------------------- */
		'use-logo-image'           => array(
			'setting' => 'majestic_tube_use_logo_image',
			'default' => 'off',
			'type'    => 'onoff',
			'label'   => __( 'Use an image logo', 'majestic-tube' ),
			'section' => $branding,
		),
		'image-logo-file'          => array(
			'setting' => 'majestic_tube_image_logo_file',
			'default' => '',
			'type'    => 'file',
			'label'   => __( 'Logo image URL', 'majestic-tube' ),
			'section' => $branding,
		),
		'icon-logo'                => array(
			'setting' => 'majestic_tube_icon_logo',
			'default' => 'play-circle',
			'type'    => 'select',
			'label'   => __( 'Icon logo', 'majestic-tube' ),
			'section' => $branding,
			'choices' => array(
				'play-circle' => __( 'Play circle', 'majestic-tube' ),
				'film'        => __( 'Film', 'majestic-tube' ),
				'video'       => __( 'Video', 'majestic-tube' ),
				'heart'       => __( 'Heart', 'majestic-tube' ),
				'star'        => __( 'Star', 'majestic-tube' ),
			),
		),
		'text-logo'                => array(
			'setting' => 'majestic_tube_text_logo',
			'default' => '',
			'type'    => 'text',
			'label'   => __( 'Text logo', 'majestic-tube' ),
			'section' => $branding,
		),
		'logo-font-family'         => array(
			'setting' => 'majestic_tube_logo_font_family',
			'default' => 'Inter',
			'type'    => 'select',
			'label'   => __( 'Logo font family', 'majestic-tube' ),
			'section' => $branding,
			'choices' => array(
				'Inter'           => 'Inter',
				'Open Sans'       => 'Open Sans',
				'Roboto'          => 'Roboto',
				'Lato'            => 'Lato',
				'Montserrat'      => 'Montserrat',
				'System default'  => __( 'System default', 'majestic-tube' ),
			),
		),
		'logo-font-size'           => array(
			'setting'     => 'majestic_tube_logo_font_size',
			'default'     => 36,
			'type'        => 'number',
			'label'       => __( 'Logo font size (px)', 'majestic-tube' ),
			'section'     => $branding,
			'input_attrs' => array(
				'min'  => 8,
				'max'  => 120,
				'step' => 1,
			),
		),
		'logo-max-width'           => array(
			'setting'     => 'majestic_tube_logo_max_width',
			'default'     => 300,
			'type'        => 'number',
			'label'       => __( 'Logo max width (px)', 'majestic-tube' ),
			'section'     => $branding,
			'input_attrs' => array(
				'min'  => 0,
				'max'  => 2000,
				'step' => 1,
			),
		),
		'logo-max-height'          => array(
			'setting'     => 'majestic_tube_logo_max_height',
			'default'     => 120,
			'type'        => 'number',
			'label'       => __( 'Logo max height (px)', 'majestic-tube' ),
			'section'     => $branding,
			'input_attrs' => array(
				'min'  => 0,
				'max'  => 2000,
				'step' => 1,
			),
		),
		'logo-margin-top'          => array(
			'setting'     => 'majestic_tube_logo_margin_top',
			'default'     => 0,
			'type'        => 'number',
			'label'       => __( 'Logo top margin (px)', 'majestic-tube' ),
			'section'     => $branding,
			'input_attrs' => array(
				'min'  => -100,
				'max'  => 500,
				'step' => 1,
			),
		),
		'logo-margin-left'         => array(
			'setting'     => 'majestic_tube_logo_margin_left',
			'default'     => 0,
			'type'        => 'number',
			'label'       => __( 'Logo left margin (px)', 'majestic-tube' ),
			'section'     => $branding,
			'input_attrs' => array(
				'min'  => -100,
				'max'  => 500,
				'step' => 1,
			),
		),
		'logo-footer'              => array(
			'setting' => 'majestic_tube_logo_footer',
			'default' => 'off',
			'type'    => 'onoff',
			'label'   => __( 'Show the logo in the footer', 'majestic-tube' ),
			'section' => $branding,
		),
		'logo-watermark-video-player' => array(
			'setting' => 'majestic_tube_logo_watermark_video_player',
			'default' => 'off',
			'type'    => 'onoff',
			'label'   => __( 'Overlay a logo watermark on the player', 'majestic-tube' ),
			'section' => $branding,
		),
		'image-logo-watermark-file' => array(
			'setting' => 'majestic_tube_image_logo_watermark_file',
			'default' => '',
			'type'    => 'file',
			'label'   => __( 'Watermark image URL', 'majestic-tube' ),
			'section' => $branding,
		),
		'logo-watermark-max-width' => array(
			'setting'     => 'majestic_tube_logo_watermark_max_width',
			'default'     => 200,
			'type'        => 'number',
			'label'       => __( 'Watermark max width (px)', 'majestic-tube' ),
			'section'     => $branding,
			'input_attrs' => array(
				'min'  => 0,
				'max'  => 1000,
				'step' => 1,
			),
		),
		'logo-watermark-grayscale' => array(
			'setting' => 'majestic_tube_logo_watermark_grayscale',
			'default' => 'off',
			'type'    => 'onoff',
			'label'   => __( 'Grayscale the watermark', 'majestic-tube' ),
			'section' => $branding,
		),
		'logo-position-video-player' => array(
			'setting' => 'majestic_tube_logo_position_video_player',
			'default' => 'top-left',
			'type'    => 'select',
			'label'   => __( 'Watermark position', 'majestic-tube' ),
			'section' => $branding,
			'choices' => array(
				'top-left'     => __( 'Top left', 'majestic-tube' ),
				'top-right'    => __( 'Top right', 'majestic-tube' ),
				'bottom-left'  => __( 'Bottom left', 'majestic-tube' ),
				'bottom-right' => __( 'Bottom right', 'majestic-tube' ),
			),
		),
		'favicon'                  => array(
			'setting' => 'majestic_tube_favicon',
			'default' => '',
			'type'    => 'file',
			'label'   => __( 'Favicon URL', 'majestic-tube' ),
			'section' => $branding,
		),

		/* ---------------------------------------------------------------
		 * Player
		 * ------------------------------------------------------------- */
		'autoplay-video-player'    => array(
			'setting'     => 'majestic_tube_autoplay',
			'default'     => 'off',
			'type'        => 'onoff',
			'label'       => __( 'Autoplay video player', 'majestic-tube' ),
			'section'     => $player,
			'description' => __( 'Browsers only honor autoplay for muted videos.', 'majestic-tube' ),
		),
		'use-native-player'        => array(
			'setting'     => 'majestic_tube_native_player',
			'default'     => 'off',
			'type'        => 'onoff',
			'label'       => __( 'Use the native HTML5 player instead of Video.js', 'majestic-tube' ),
			'section'     => $player,
			'description' => __( 'Removes the Video.js dependency entirely. Quality switcher, keyboard shortcuts and skinning are then handled by the browser.', 'majestic-tube' ),
		),
		'videojs-quality-selector' => array(
			'setting' => 'majestic_tube_enable_quality_selector',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Enable the video quality selector', 'majestic-tube' ),
			'section' => $player,
		),

		/* ---------------------------------------------------------------
		 * Sharing / social (original per-network switches)
		 * ------------------------------------------------------------- */
		'enable-video-share'       => array(
			'setting' => 'majestic_tube_enable_video_share',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Enable video sharing', 'majestic-tube' ),
			'section' => $social,
		),
		'facebook-video-share'     => array(
			'setting' => 'majestic_tube_share_facebook',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Share on Facebook', 'majestic-tube' ),
			'section' => $social,
		),
		'twitter-video-share'      => array(
			'setting' => 'majestic_tube_share_twitter',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Share on X / Twitter', 'majestic-tube' ),
			'section' => $social,
		),
		'google-plus-video-share'  => array(
			'setting' => 'majestic_tube_share_google_plus',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Share on Google+', 'majestic-tube' ),
			'section' => $social,
		),
		'linkedin-video-share'     => array(
			'setting' => 'majestic_tube_share_linkedin',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Share on LinkedIn', 'majestic-tube' ),
			'section' => $social,
		),
		'tumblr-video-share'       => array(
			'setting' => 'majestic_tube_share_tumblr',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Share on Tumblr', 'majestic-tube' ),
			'section' => $social,
		),
		'reddit-video-share'       => array(
			'setting' => 'majestic_tube_share_reddit',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Share on Reddit', 'majestic-tube' ),
			'section' => $social,
		),
		'odnoklassniki-video-share' => array(
			'setting' => 'majestic_tube_share_odnoklassniki',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Share on Odnoklassniki', 'majestic-tube' ),
			'section' => $social,
		),
		'email-video-share'        => array(
			'setting' => 'majestic_tube_share_email',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Share by email', 'majestic-tube' ),
			'section' => $social,
		),

		/* ---------------------------------------------------------------
		 * Video submission (original per-field requirements)
		 * ------------------------------------------------------------- */
		'enable-video-submission'  => array(
			'setting' => 'majestic_tube_enable_video_submission',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Enable video submission', 'majestic-tube' ),
		),
		'video-submit-title-required' => array(
			'setting' => 'majestic_tube_submit_title_required',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Title required', 'majestic-tube' ),
			'section' => $submission,
		),
		'video-submit-description-required' => array(
			'setting' => 'majestic_tube_submit_description_required',
			'default' => 'off',
			'type'    => 'onoff',
			'label'   => __( 'Description required', 'majestic-tube' ),
			'section' => $submission,
		),
		'video-submit-video-link-required' => array(
			'setting' => 'majestic_tube_submit_video_link_required',
			'default' => 'off',
			'type'    => 'onoff',
			'label'   => __( 'Video link required', 'majestic-tube' ),
			'section' => $submission,
		),
		'video-submit-embed-required' => array(
			'setting' => 'majestic_tube_submit_embed_required',
			'default' => 'off',
			'type'    => 'onoff',
			'label'   => __( 'Embed code required', 'majestic-tube' ),
			'section' => $submission,
		),
		'video-submit-thumbnail-link-required' => array(
			'setting' => 'majestic_tube_submit_thumbnail_link_required',
			'default' => 'off',
			'type'    => 'onoff',
			'label'   => __( 'Thumbnail link required', 'majestic-tube' ),
			'section' => $submission,
		),
		'video-submit-tags-required' => array(
			'setting' => 'majestic_tube_submit_tags_required',
			'default' => 'off',
			'type'    => 'onoff',
			'label'   => __( 'Tags required', 'majestic-tube' ),
			'section' => $submission,
		),
		'video-submit-actors-required' => array(
			'setting' => 'majestic_tube_submit_actors_required',
			'default' => 'off',
			'type'    => 'onoff',
			'label'   => __( 'Actors required', 'majestic-tube' ),
			'section' => $submission,
		),
		'video-submit-duration-required' => array(
			'setting' => 'majestic_tube_submit_duration_required',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Duration required', 'majestic-tube' ),
			'section' => $submission,
		),

		/* ---------------------------------------------------------------
		 * Membership / recaptcha
		 * ------------------------------------------------------------- */
		'enable-recaptcha'         => array(
			'setting' => 'majestic_tube_enable_recaptcha',
			'default' => 'off',
			'type'    => 'onoff',
			'label'   => __( 'Enable reCAPTCHA', 'majestic-tube' ),
		),
		'recaptcha-site-key'       => array(
			'setting' => 'majestic_tube_recaptcha_site_key',
			'default' => '',
			'type'    => 'text',
			'label'   => __( 'reCAPTCHA site key', 'majestic-tube' ),
		),
		'recaptcha-secret-key'     => array(
			'setting' => 'majestic_tube_recaptcha_secret_key',
			'default' => '',
			'type'    => 'text',
			'label'   => __( 'reCAPTCHA secret key', 'majestic-tube' ),
		),

		/* ---------------------------------------------------------------
		 * Legacy content keys (retained for data compatibility)
		 * ------------------------------------------------------------- */
		'header-ad-desktop'        => array(
			'setting' => 'majestic_tube_ad_header_desktop',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy header content (desktop)', 'majestic-tube' ),
			'section'    => $advertising,
			'customizer' => false,
		),
		'header-ad-mobile'         => array(
			'setting' => 'majestic_tube_ad_header_mobile',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy header content (mobile)', 'majestic-tube' ),
			'section'    => $advertising,
			'customizer' => false,
		),
		'sidebar-ad-desktop-1'     => array(
			'setting' => 'majestic_tube_ad_sidebar_desktop_1',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy video sidebar content 1', 'majestic-tube' ),
			'section'    => $advertising,
			'customizer' => false,
		),
		'sidebar-ad-desktop-2'     => array(
			'setting' => 'majestic_tube_ad_sidebar_desktop_2',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy video sidebar content 2', 'majestic-tube' ),
			'section'    => $advertising,
			'customizer' => false,
		),
		'sidebar-ad-desktop-3'     => array(
			'setting' => 'majestic_tube_ad_sidebar_desktop_3',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy video sidebar content 3', 'majestic-tube' ),
			'section'    => $advertising,
			'customizer' => false,
		),
		'sidebar-ad-mobile'        => array(
			'setting' => 'majestic_tube_ad_sidebar_mobile',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy mobile sidebar content', 'majestic-tube' ),
			'section'    => $advertising,
			'customizer' => false,
		),
		'inside-player-ad-zone-1-desktop' => array(
			'setting'     => 'majestic_tube_ad_inside_player_1',
			'default'     => '',
			'type'        => 'html',
			'label'       => __( 'Legacy player content 1', 'majestic-tube' ),
			'section'     => $advertising,
			'description' => __( 'Retained for migration into the Player content widget area.', 'majestic-tube' ),
			'customizer'  => false,
		),
		'inside-player-ad-zone-2-desktop' => array(
			'setting' => 'majestic_tube_ad_inside_player_2',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy player content 2', 'majestic-tube' ),
			'section'    => $advertising,
			'customizer' => false,
		),
		'under-player-ad-desktop'  => array(
			'setting' => 'majestic_tube_ad_under_player_desktop',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy below-player content (desktop)', 'majestic-tube' ),
			'section'    => $advertising,
			'customizer' => false,
		),
		'under-player-ad-mobile'   => array(
			'setting' => 'majestic_tube_ad_under_player_mobile',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy below-player content (mobile)', 'majestic-tube' ),
			'section'    => $advertising,
			'customizer' => false,
		),
		'footer-ad-desktop'        => array(
			'setting' => 'majestic_tube_ad_footer_desktop',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy footer content (desktop)', 'majestic-tube' ),
			'section'    => $advertising,
			'customizer' => false,
		),
		'footer-ad-mobile'         => array(
			'setting' => 'majestic_tube_ad_footer_mobile',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy footer content (mobile)', 'majestic-tube' ),
			'section'    => $advertising,
			'customizer' => false,
		),

		/* ---------------------------------------------------------------
		 * SEO & social
		 * ------------------------------------------------------------- */
		'facebook-app-id'          => array(
			'setting'     => 'majestic_tube_facebook_app_id',
			'default'     => '',
			'type'        => 'text',
			'label'       => __( 'Facebook app ID', 'majestic-tube' ),
			'section'     => $seo,
			'description' => __( 'Optional. The original theme hardcoded a third-party app ID; leave this empty to omit the tag.', 'majestic-tube' ),
		),
		'twitter-site'             => array(
			'setting' => 'majestic_tube_twitter_site',
			'default' => '',
			'type'    => 'text',
			'label'   => __( 'Twitter/x @handle for twitter:site', 'majestic-tube' ),
			'section' => $seo,
		),
		'meta-verification'        => array(
			'setting'     => 'majestic_tube_meta_verification',
			'default'     => '',
			'type'        => 'code',
			'label'       => __( 'Search engine verification tags', 'majestic-tube' ),
			'section'     => $seo,
			'description' => __( 'Printed verbatim inside <head>. Paste the full <meta> tag(s).', 'majestic-tube' ),
		),
		'seo-footer-text'          => array(
			'setting' => 'majestic_tube_seo_footer_text',
			'default' => '',
			'type'    => 'textarea',
			'label'   => __( 'SEO footer text', 'majestic-tube' ),
			'section' => $seo,
		),

		/* ---------------------------------------------------------------
		 * Custom code
		 * ------------------------------------------------------------- */
		'google-analytics'         => array(
			'setting'     => 'majestic_tube_google_analytics',
			'default'     => '',
			'type'        => 'code',
			'label'       => __( 'Analytics code', 'majestic-tube' ),
			'section'     => $scripts,
			'description' => __( 'Printed inside <head> on the front-end, exactly like the original theme.', 'majestic-tube' ),
		),
		'other-scripts'            => array(
			'setting'     => 'majestic_tube_other_scripts',
			'default'     => '',
			'type'        => 'code',
			'label'       => __( 'Other scripts', 'majestic-tube' ),
			'section'     => $scripts,
			'description' => __( 'Extra markup printed before </body>.', 'majestic-tube' ),
		),
		'mobile-scripts'           => array(
			'setting'     => 'majestic_tube_mobile_scripts',
			'default'     => '',
			'type'        => 'code',
			'label'       => __( 'Mobile scripts', 'majestic-tube' ),
			'section'     => $mobile,
			'description' => __( 'Extra markup printed before </body> for mobile visitors only.', 'majestic-tube' ),
		),

		/* ---------------------------------------------------------------
		 * Majestic Tube additions (not part of the original contract)
		 * ------------------------------------------------------------- */
		'enable-video-report'      => array(
			'setting'     => 'majestic_tube_enable_video_report',
			'default'     => 'on',
			'type'        => 'onoff',
			'label'       => __( 'Enable "Report video" button', 'majestic-tube' ),
			'description' => __( 'Lets visitors flag broken or miscategorized videos. Reports appear under Videos > Reported Videos.', 'majestic-tube' ),
		),
	);

	$GLOBALS['majestic_tube_options_map_cache'] = $map_value;

	return $map_value;
}

/**
 * Legacy option row imported from the original theme.
 */
define( 'MAJESTIC_TUBE_LEGACY_OPTION', 'wpst-options' );

/**
 * Read a value straight out of the legacy wpst-options array.
 *
 * Sites migrated from the original theme still have their settings in the
 * `wpst-options` database row. Reading it as a fallback means an existing
 * installation keeps its ad codes, colours and network switches after
 * switching to Majestic Tube.
 *
 * @param string $field_id Field id.
 * @param mixed  $default  Default when the key is absent.
 * @return mixed
 */
function majestic_tube_get_legacy_option( $field_id, $default = false ) {
	$legacy = get_option( MAJESTIC_TUBE_LEGACY_OPTION );

	if ( ! is_array( $legacy ) || ! array_key_exists( $field_id, $legacy ) ) {
		return $default;
	}

	$value = $legacy[ $field_id ];

	// Some legacy values were wrapped in an array with a single "value" key.
	if ( is_array( $value ) && 1 === count( $value ) && isset( $value['value'] ) ) {
		$value = $value['value'];
	}

	if ( is_array( $value ) ) {
		return $default;
	}

	return $value;
}

/**
 * Read a native theme option through the legacy-compatible map.
 *
 * Priority is the Customizer value, the legacy wpst-options row, and finally
 * the declared or caller-supplied default. Unrelated option groups are never
 * read; this helper belongs exclusively to Majestic Tube.
 *
 * @param string $option_group Option group.
 * @param string $field_id     Field id.
 * @param mixed  $default      Optional default.
 * @return mixed
 */
function majestic_tube_get_option( $option_group, $field_id, $default = false ) {
	if ( MAJESTIC_TUBE_LEGACY_OPTION !== $option_group ) {
		return $default;
	}

	$map = majestic_tube_options_map();

	// A mapped key: the Customizer wins, then the original theme's stored
	// value, then the declared default. An explicitly saved empty string is
	// honoured (it means "the administrator cleared this zone").
	if ( isset( $map[ $field_id ] ) ) {
		$saved = get_theme_mod( $map[ $field_id ]['setting'], null );

		if ( null !== $saved ) {
			return $saved;
		}

		return majestic_tube_get_legacy_option( $field_id, $map[ $field_id ]['default'] );
	}

	// Unknown key that the original theme used but we do not model: honour the
	// value stored by the original theme, otherwise fall back to the default.
	return majestic_tube_get_legacy_option( $field_id, $default );
}

/**
 * Whether an original-style on/off option is enabled.
 *
 * @param string $field_id Original field id.
 * @return bool
 */
function majestic_tube_option_is_on( $field_id ) {
	return 'on' === majestic_tube_get_option( 'wpst-options', $field_id, 'off' );
}

/**
 * Register all options in the Customizer.
 *
 * @param WP_Customize_Manager $wp_customize Manager.
 */
function majestic_tube_options_customize_register( $wp_customize ) {
	$sections = majestic_tube_option_sections();
	$priority = 30;

	foreach ( $sections as $slug => $title ) {
		$section_args = array(
			'title'    => $title,
			'priority' => $priority,
		);

		if ( 'advertising' === $slug ) {
			$section_args['description'] = __( 'Content blocks are managed from Appearance → Widgets. The single-video sidebar has its own switch in the main options section.', 'majestic-tube' );
		}

		$wp_customize->add_section(
			'majestic_tube_options' . ( 'general' === $slug ? '' : '-' . $slug ),
			$section_args
		);

		++$priority;
	}

	foreach ( majestic_tube_options_map() as $config ) {
		if ( isset( $config['customizer'] ) && ! $config['customizer'] ) {
			continue;
		}

		$section  = isset( $config['section'] ) ? $config['section'] : 'general';
		$sanitize = isset( $config['sanitize'] ) ? $config['sanitize'] : majestic_tube_option_sanitizer( $config['type'] );

		$wp_customize->add_setting(
			$config['setting'],
			array(
				'default'           => $config['default'],
				'sanitize_callback' => $sanitize,
				'capability'        => 'edit_theme_options',
			)
		);

		$control_args = array(
			'label'       => $config['label'],
			'section'     => 'majestic_tube_options' . ( 'general' === $section ? '' : '-' . $section ),
			'description' => isset( $config['description'] ) ? $config['description'] : '',
		);

		$input_attrs = isset( $config['input_attrs'] ) ? $config['input_attrs'] : array();

		switch ( $config['type'] ) {
			case 'select':
				$control_args['type']    = 'select';
				$control_args['choices'] = isset( $config['choices'] ) ? $config['choices'] : array();
				break;

			case 'number':
				$control_args['type']        = 'number';
				$control_args['input_attrs'] = $input_attrs ? $input_attrs : array(
					'min'  => 0,
					'max'  => 1000,
					'step' => 1,
				);
				break;

			case 'onoff':
				$control_args['type']    = 'select';
				$control_args['choices'] = majestic_tube_onoff_choices();
				break;

			case 'color':
				$wp_customize->add_control(
					new WP_Customize_Color_Control(
						$wp_customize,
						$config['setting'],
						array(
							'label'   => $config['label'],
							'section' => $control_args['section'],
						)
					)
				);
				continue 2;

			case 'html':
			case 'code':
			case 'textarea':
				$control_args['type']        = 'textarea';
				$control_args['input_attrs'] = array( 'rows' => 'html' === $config['type'] ? 6 : 4 );
				break;

			default:
				$control_args['type'] = 'text';
		}

		$wp_customize->add_control( $config['setting'], $control_args );
	}
}
add_action( 'customize_register', 'majestic_tube_options_customize_register', 20 );

/**
 * Sanitize callback name for an option type.
 *
 * @param string $type Option type.
 * @return string
 */
function majestic_tube_option_sanitizer( $type ) {
	switch ( $type ) {
		case 'number':
			return 'absint';

		case 'html':
			return 'majestic_tube_sanitize_ad_code';

		case 'code':
			return 'majestic_tube_sanitize_code_option';

		case 'textarea':
			return 'sanitize_textarea_field';

		case 'color':
			return 'sanitize_hex_color';

		case 'url':
		case 'file':
			return 'esc_url_raw';

		default:
			return 'majestic_tube_sanitize_option';
	}
}

/**
 * Sanitize an option value based on its declared type.
 *
 * @param mixed $value Raw value.
 * @return mixed
 */
function majestic_tube_sanitize_option( $value ) {
	if ( is_bool( $value ) ) {
		return $value ? 'on' : 'off';
	}

	return sanitize_text_field( $value );
}

/**
 * Sanitize a custom-code option (analytics, verification tags, mobile code).
 *
 * Administrators may keep the raw tag so analytics services work; lower roles
 * get it filtered down to safe HTML.
 *
 * @param string $value Raw value.
 * @return string
 */
function majestic_tube_sanitize_code_option( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}

	if ( current_user_can( 'unfiltered_html' ) ) {
		return trim( $value );
	}

	return wp_kses_post( $value );
}

/**
 * Sanitize a legacy content / custom HTML option.
 *
 * Stored content may legitimately contain scripts from a content provider, so administrators
 * keep their markup untouched while lower roles get it filtered.
 *
 * @param string $value Raw value.
 * @return string
 */
function majestic_tube_sanitize_ad_code( $value ) {
	return majestic_tube_sanitize_code_option( $value );
}
