<?php
/**
 * Theme options - native Customizer settings with wpst-options migration.
 *
 * Every option key stays mapped and readable by Majestic Tube itself.
 * Content-placement keys are retained for migration but are intentionally not
 * exposed as Customizer fields; their values are managed by widgets instead.
 * The theme does not define, require, or emulate any external settings framework.
 *
 * The Customizer is organised by the template each setting affects, so an
 * editor finds homepage settings in one panel and single-video-page settings
 * in another. See majestic_tube_option_sections() for the full structure.
 *
 * @package Majestic Tube
 * @version 2.2.7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Customizer panels, and the sections inside each one, in display order.
 *
 * The first two panels are the ones an editor visits most, so they are split
 * by the template they affect: the homepage and the listing archives on one
 * side, the single video page on the other. A setting is filed under the
 * template that reads it, which is why the video sidebar sits in "Video Page"
 * while the grid density sits in "Homepage".
 *
 * Each entry is: section key, section title, panel key, panel title.
 *
 * @return array<string, array{title: string, panel: string, panel_title: string, description?: string}>
 */
function majestic_tube_option_sections() {
	return array(
		'home'         => array(
			'title'       => __( 'Homepage', 'majestic-tube' ),
			'panel'       => 'listings',
			'panel_title' => __( 'Homepage &amp; Listings', 'majestic-tube' ),
			'description' => __( 'Controls the video grid on the site&rsquo;s front page.', 'majestic-tube' ),
		),
		'home_mobile'  => array(
			'title'       => __( 'Homepage on Mobile', 'majestic-tube' ),
			'panel'       => 'listings',
			'panel_title' => __( 'Homepage &amp; Listings', 'majestic-tube' ),
		),
		'archives'     => array(
			'title'       => __( 'Category, Tag &amp; Actor Archives', 'majestic-tube' ),
			'panel'       => 'listings',
			'panel_title' => __( 'Homepage &amp; Listings', 'majestic-tube' ),
		),
		'single'       => array(
			'title'       => __( 'Video Page Layout', 'majestic-tube' ),
			'panel'       => 'video',
			'panel_title' => __( 'Video Page', 'majestic-tube' ),
			'description' => __( 'Controls what a single video page shows around the player.', 'majestic-tube' ),
		),
		'player'       => array(
			'title'       => __( 'Player', 'majestic-tube' ),
			'panel'       => 'video',
			'panel_title' => __( 'Video Page', 'majestic-tube' ),
		),
		'social'       => array(
			'title'       => __( 'Sharing', 'majestic-tube' ),
			'panel'       => 'video',
			'panel_title' => __( 'Video Page', 'majestic-tube' ),
		),
		'colours'      => array(
			'title'       => __( 'Colours &amp; Typography', 'majestic-tube' ),
			'panel'       => 'design',
			'panel_title' => __( 'Site Design', 'majestic-tube' ),
		),
		'logo'         => array(
			'title'       => __( 'Logo', 'majestic-tube' ),
			'panel'       => 'design',
			'panel_title' => __( 'Site Design', 'majestic-tube' ),
		),
		'watermark'    => array(
			'title'       => __( 'Player Watermark', 'majestic-tube' ),
			'panel'       => 'design',
			'panel_title' => __( 'Site Design', 'majestic-tube' ),
		),
		'thumbnails'   => array(
			'title'       => __( 'Thumbnails', 'majestic-tube' ),
			'panel'       => 'design',
			'panel_title' => __( 'Site Design', 'majestic-tube' ),
		),
		'chrome'       => array(
			'title'       => __( 'Header, Footer &amp; Search', 'majestic-tube' ),
			'panel'       => 'features',
			'panel_title' => __( 'Site Features', 'majestic-tube' ),
		),
		'members'      => array(
			'title'       => __( 'Accounts &amp; Spam Protection', 'majestic-tube' ),
			'panel'       => 'features',
			'panel_title' => __( 'Site Features', 'majestic-tube' ),
		),
		'submission'   => array(
			'title'       => __( 'Video Submission', 'majestic-tube' ),
			'panel'       => 'features',
			'panel_title' => __( 'Site Features', 'majestic-tube' ),
		),
		'advertising'  => array(
			'title'       => __( 'Advertising', 'majestic-tube' ),
			'panel'       => 'ads',
			'panel_title' => __( 'Advertising', 'majestic-tube' ),
			'description' => __( 'The ad slots this theme prints itself are switched on here. The remaining page areas are managed from Appearance &rarr; Widgets.', 'majestic-tube' ),
		),
		'seo'          => array(
			'title'       => __( 'SEO &amp; Social', 'majestic-tube' ),
			'panel'       => 'seo',
			'panel_title' => __( 'SEO &amp; Analytics', 'majestic-tube' ),
		),
		'code'         => array(
			'title'       => __( 'Custom Code', 'majestic-tube' ),
			'panel'       => 'seo',
			'panel_title' => __( 'SEO &amp; Analytics', 'majestic-tube' ),
		),
	);
}

/**
 * Panels in display order, derived from the section list.
 *
 * @return array<string, string>
 */
function majestic_tube_option_panels() {
	$panels = array();

	foreach ( majestic_tube_option_sections() as $section ) {
		if ( ! isset( $panels[ $section['panel'] ] ) ) {
			$panels[ $section['panel'] ] = $section['panel_title'];
		}
	}

	return $panels;
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
 * Key names, defaults and stored value strings stay compatible with the
 * wpst-options row, so a site that installed the theme earlier keeps its
 * settings. Runtime reads always go through majestic_tube_get_option().
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

	// Section keys, in the same order as majestic_tube_option_sections(). The
	// map is grouped to match, so the file reads top to bottom in the same
	// order an editor sees in the Customizer.
	$home         = 'home';
	$home_mobile  = 'home_mobile';
	$archives     = 'archives';
	$single       = 'single';
	$player       = 'player';
	$social       = 'social';
	$colours      = 'colours';
	$logo         = 'logo';
	$watermark    = 'watermark';
	$thumbnails   = 'thumbnails';
	$chrome       = 'chrome';
	$members      = 'members';
	$submission   = 'submission';
	$advertising  = 'advertising';
	$seo          = 'seo';
	$code         = 'code';

	$map_value = array(
		/* ------------------------------------------------------------------
		 * Homepage - the video grid on the site front page (index.php)
		 * ------------------------------------------------------------------ */
		'show-videos-homepage'     => array(
			'setting' => 'majestic_tube_default_filter',
			'default' => 'latest',
			'type'    => 'select',
			'label'   => __( 'Videos displayed on homepage', 'majestic-tube' ),
			'section' => $home,
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
			'section' => $home,
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
			'section' => $home,
			'input_attrs' => array(
				'min'  => 1,
				'max'  => 8,
				'step' => 1,
			),
		),
		'homepage-title'           => array(
			'setting' => 'majestic_tube_homepage_title',
			'default' => '',
			'type'    => 'text',
			'label'   => __( 'Homepage title', 'majestic-tube' ),
			'section' => $home,
		),
		'homepage-title-desc-position' => array(
			'setting' => 'majestic_tube_homepage_title_desc_position',
			'default' => 'bottom',
			'type'    => 'select',
			'label'   => __( 'Homepage title position', 'majestic-tube' ),
			'section' => $home,
			'choices' => array(
				'top'    => __( 'Above the grid', 'majestic-tube' ),
				'bottom' => __( 'Below the grid', 'majestic-tube' ),
			),
		),
		/* ------------------------------------------------------------------
		 * Homepage on mobile visitors
		 * ------------------------------------------------------------------ */
		'videos-per-page-mobile'   => array(
			'setting'     => 'majestic_tube_videos_per_page_mobile',
			'default'     => 20,
			'type'        => 'number',
			'label'       => __( 'Videos per page (mobile)', 'majestic-tube' ),
			'section' => $home_mobile,
			'input_attrs' => array(
				'min'  => 1,
				'max'  => 100,
				'step' => 1,
			),
		),
		'videos-per-row-mobile'    => array(
			'setting'     => 'majestic_tube_videos_per_row_mobile',
			'default'     => 2,
			'type'        => 'number',
			'label'       => __( 'Videos per row (mobile)', 'majestic-tube' ),
			'section' => $home_mobile,
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
			'section' => $home_mobile,
		),
		/* ------------------------------------------------------------------
		 * Listing archives - category, tag and actor pages
		 * ------------------------------------------------------------------ */
		'categories-per-row'       => array(
			'setting'     => 'majestic_tube_categories_per_row',
			'default'     => 5,
			'type'        => 'number',
			'label'       => __( 'Categories per row', 'majestic-tube' ),
			'section' => $archives,
			'input_attrs' => array(
				'min'  => 1,
				'max'  => 8,
				'step' => 1,
			),
		),
		'categories-per-page'      => array(
			'setting'     => 'majestic_tube_categories_per_page',
			'default'     => 20,
			'type'        => 'number',
			'label'       => __( 'Categories per page', 'majestic-tube' ),
			'section' => $archives,
			'input_attrs' => array(
				'min'  => 1,
				'max'  => 100,
				'step' => 1,
			),
		),
		'category-card-description' => array(
			'setting'     => 'majestic_tube_category_card_description',
			'default'     => 'Free {description} videos',
			'type'        => 'text',
			'label'       => __( 'Category card text', 'majestic-tube' ),
			'section' => $archives,
			'description' => __( 'A phrase shown on every category card, built from the tokens below. Leave it blank to use each category\'s own description instead.', 'majestic-tube' ),
			'input_attrs' => array(
				'placeholder' => 'Free {description} videos',
			),
		),
		'actor-card-description'  => array(
			'setting'     => 'majestic_tube_actor_card_description',
			'default'     => 'Watch {description} videos',
			'type'        => 'text',
			'label'       => __( 'Actor card text', 'majestic-tube' ),
			'section' => $archives,
			'description' => __( 'The same idea for actor cards. Leave it blank to use each actor\'s own description instead.', 'majestic-tube' ),
			'input_attrs' => array(
				'placeholder' => 'Watch {description} videos',
			),
		),
		'cat-desc-position'        => array(
			'setting' => 'majestic_tube_cat_desc_position',
			'default' => 'top',
			'type'    => 'select',
			'label'   => __( 'Category description position', 'majestic-tube' ),
			'section' => $archives,
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
			'section' => $archives,
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
			'section' => $archives,
			'input_attrs' => array(
				'min'  => 1,
				'max'  => 100,
				'step' => 1,
			),
		),
		/* ------------------------------------------------------------------
		 * Single video page - everything around the player (single.php)
		 * ------------------------------------------------------------------ */
		'single-sidebar'              => array(
			'setting'     => 'majestic_tube_single_sidebar',
			'default'     => 'on',
			'type'        => 'onoff',
			'label'       => __( 'Show the video sidebar', 'majestic-tube' ),
			'section' => $single,
			'description' => __( 'Show the Video sidebar widget area beside individual video pages. The area stays empty until you add content.', 'majestic-tube' ),
		),
		'enable-comments'          => array(
			'setting' => 'majestic_tube_enable_comments',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Display comments on videos', 'majestic-tube' ),
			'section' => $single,
		),
		'enable-breadcrumbs'       => array(
			'setting' => 'majestic_tube_enable_breadcrumbs',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Display breadcrumbs', 'majestic-tube' ),
			'section' => $single,
		),
		'truncate-description'     => array(
			'setting' => 'majestic_tube_truncate_description',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Truncate long descriptions', 'majestic-tube' ),
			'section' => $single,
		),
		'show-description-video-about' => array(
			'setting' => 'majestic_tube_show_description_video_about',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Show the description block', 'majestic-tube' ),
			'section' => $single,
		),
		'show-categories-video-about' => array(
			'setting' => 'majestic_tube_show_categories_video_about',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Show video categories', 'majestic-tube' ),
			'section' => $single,
		),
		'show-tags-video-about'    => array(
			'setting' => 'majestic_tube_show_tags_video_about',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Show video tags', 'majestic-tube' ),
			'section' => $single,
		),
		'show-actors-video-about'  => array(
			'setting' => 'majestic_tube_show_actors_video_about',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Show video actors', 'majestic-tube' ),
			'section' => $single,
		),
		'display-tracking-button'  => array(
			'setting' => 'majestic_tube_display_tracking_button',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Show the outbound video button', 'majestic-tube' ),
			'section' => $single,
			'description' => __( 'An optional call-to-action button under the player that sends the visitor off this site - to a partner, a network, or your own download page. It only appears when there is a link to send them to, and the three settings below control that button.', 'majestic-tube' ),
		),
		'tracking-button-icon'     => array(
			'setting' => 'majestic_tube_tracking_button_icon',
			'default' => 'download',
			'type'    => 'select',
			'label'   => __( 'Outbound button icon', 'majestic-tube' ),
			'section' => $single,
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
			'label'   => __( 'Outbound button link', 'majestic-tube' ),
			'section' => $single,
			'description' => __( 'Where the button sends the visitor. Leave it blank to use each video&rsquo;s own tracking link, and the button then appears only on videos that have one.', 'majestic-tube' ),
		),
		'tracking-button-text'     => array(
			'setting' => 'majestic_tube_tracking_button_text',
			'default' => 'Download complete video now!',
			'type'    => 'text',
			'label'   => __( 'Outbound button text', 'majestic-tube' ),
			'section' => $single,
			'description' => __( 'The words on the button. Leave it blank for &ldquo;Watch the full video&rdquo;.', 'majestic-tube' ),
		),
		'enable-views-system'      => array(
			'setting' => 'majestic_tube_enable_views_system',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Display the view counter', 'majestic-tube' ),
			'section' => $single,
		),
		'enable-duration-system'   => array(
			'setting' => 'majestic_tube_enable_duration_system',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Display video durations', 'majestic-tube' ),
			'section' => $single,
		),
		'enable-rating-system'     => array(
			'setting' => 'majestic_tube_enable_rating_system',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Display the rating percentage', 'majestic-tube' ),
			'section' => $single,
		),
		'enable-video-report'      => array(
			'setting'     => 'majestic_tube_enable_video_report',
			'default'     => 'on',
			'type'        => 'onoff',
			'label'       => __( 'Enable "Report video" button', 'majestic-tube' ),
			'section' => $single,
			'description' => __( 'Lets visitors flag broken or miscategorized videos. Reports appear under Videos > Reported Videos.', 'majestic-tube' ),
		),
		'display-related-videos'   => array(
			'setting' => 'majestic_tube_display_related_videos',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Display related videos', 'majestic-tube' ),
			'section' => $single,
		),
		'related-videos-number'    => array(
			'setting'     => 'majestic_tube_related_videos_number',
			'default'     => 15,
			'type'        => 'number',
			'label'       => __( 'Number of related videos', 'majestic-tube' ),
			'section' => $single,
			'input_attrs' => array(
				'min'  => 1,
				'max'  => 30,
				'step' => 1,
			),
		),
		/* ------------------------------------------------------------------
		 * Single video page - the player itself
		 * ------------------------------------------------------------------ */
		'autoplay-video-player'    => array(
			'setting'     => 'majestic_tube_autoplay',
			'default'     => 'off',
			'type'        => 'onoff',
			'label'       => __( 'Autoplay video player', 'majestic-tube' ),
			'section' => $player,
			'description' => __( 'Browsers only honor autoplay for muted videos.', 'majestic-tube' ),
		),
		'use-native-player'        => array(
			'setting'     => 'majestic_tube_native_player',
			'default'     => 'off',
			'type'        => 'onoff',
			'label'       => __( 'Use the native HTML5 player instead of Video.js', 'majestic-tube' ),
			'section' => $player,
			'description' => __( 'Removes the Video.js dependency entirely. Quality switcher, keyboard shortcuts and skinning are then handled by the browser.', 'majestic-tube' ),
		),
		/*
		 * Play-anchored view counting. Earlier versions counted a view the
		 * moment the page loaded; this switch keeps that default but lets a
		 * site count only plays that actually reach three seconds. Off by
		 * default so the stored counters keep their historical meaning until
		 * the operator opts in.
		 */
		'count-views-on-play'      => array(
			'setting'     => 'majestic_tube_count_views_on_play',
			'default'     => 'off',
			'type'        => 'onoff',
			'label'       => __( 'Count a view only after playback starts', 'majestic-tube' ),
			'section' => $player,
			'description' => __( 'The view is recorded once the video has played for three seconds instead of on page load. Applies to new views only.', 'majestic-tube' ),
		),
		'videojs-quality-selector' => array(
			'setting' => 'majestic_tube_enable_quality_selector',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Enable the video quality selector', 'majestic-tube' ),
			'section' => $player,
		),
		/*
		 * Player UX, added in 2.1.7. All four are off by default so an upgrade
		 * changes nothing a visitor can see, and each is independent: a site
		 * can offer the speed control without the keyboard shortcuts, and so
		 * on. None of them store anything on the server, so they work on a
		 * site with no accounts at all.
		 */
		'player-hotkeys'            => array(
			'setting'     => 'majestic_tube_player_hotkeys',
			'default'     => 'off',
			'type'        => 'onoff',
			'label'       => __( 'Keyboard shortcuts on the video page', 'majestic-tube' ),
			'section' => $player,
			'description' => __( 'Adds the usual player keys: space to play and pause, the arrow keys to seek and change volume, M to mute, F for full screen, 0-9 to jump to a point, and T for theater mode. Shortcuts never fire while a visitor is typing in a form field.', 'majestic-tube' ),
		),
		'player-speed'              => array(
			'setting'     => 'majestic_tube_player_speed',
			'default'     => 'off',
			'type'        => 'onoff',
			'label'       => __( 'Playback speed control', 'majestic-tube' ),
			'section' => $player,
			'description' => __( 'Adds a speed button to the player with the usual range from 0.5x to 2x. The chosen speed is remembered in that visitor\'s own browser and applied to every video they open.', 'majestic-tube' ),
		),
		'player-resume'             => array(
			'setting'     => 'majestic_tube_player_resume',
			'default'     => 'off',
			'type'        => 'onoff',
			'label'       => __( 'Offer to resume where the visitor stopped', 'majestic-tube' ),
			'section' => $player,
			'description' => __( 'After a visitor watches a few seconds of a video, a bar offers to continue from where they left off on their next visit. The position is kept in that visitor\'s own browser, never on your server, and finishing a video clears it.', 'majestic-tube' ),
		),
		'player-theater'            => array(
			'setting'     => 'majestic_tube_player_theater',
			'default'     => 'off',
			'type'        => 'onoff',
			'label'       => __( 'Theater mode', 'majestic-tube' ),
			'section' => $player,
			'description' => __( 'Adds a button that widens the player across the page and dims the surrounding content, for watching without distractions. Also available on the keyboard with T while the shortcuts are on.', 'majestic-tube' ),
		),
		/* ------------------------------------------------------------------
		 * Single video page - sharing
		 * ------------------------------------------------------------------ */
		'enable-video-share'       => array(
			'setting' => 'majestic_tube_enable_video_share',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Enable video sharing', 'majestic-tube' ),
			'section' => $social,
		),
		'twitter-video-share'      => array(
			'setting' => 'majestic_tube_share_twitter',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Share on X / Twitter', 'majestic-tube' ),
			'section' => $social,
		),
		'reddit-video-share'       => array(
			'setting' => 'majestic_tube_share_reddit',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Share on Reddit', 'majestic-tube' ),
			'section' => $social,
		),
		'email-video-share'        => array(
			'setting' => 'majestic_tube_share_email',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Share by email', 'majestic-tube' ),
			'section' => $social,
		),
		'facebook-video-share'     => array(
			'setting' => 'majestic_tube_share_facebook',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Share on Facebook', 'majestic-tube' ),
			'section' => $social,
			'customizer' => false,
		),
		'google-plus-video-share'  => array(
			'setting' => 'majestic_tube_share_google_plus',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Share on Google+', 'majestic-tube' ),
			'section' => $social,
			'customizer' => false,
		),
		'linkedin-video-share'     => array(
			'setting' => 'majestic_tube_share_linkedin',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Share on LinkedIn', 'majestic-tube' ),
			'section' => $social,
			'customizer' => false,
		),
		'tumblr-video-share'       => array(
			'setting' => 'majestic_tube_share_tumblr',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Share on Tumblr', 'majestic-tube' ),
			'section' => $social,
			'customizer' => false,
		),
		'odnoklassniki-video-share' => array(
			'setting' => 'majestic_tube_share_odnoklassniki',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Share on Odnoklassniki', 'majestic-tube' ),
			'section' => $social,
			'customizer' => false,
		),
		/* ------------------------------------------------------------------
		 * Site design - colour scheme and body font
		 * ------------------------------------------------------------------ */
		'color-scheme'             => array(
			'setting'     => 'majestic_tube_color_scheme',
			'default'     => 'light',
			'type'        => 'select',
			'label'       => __( 'Colour scheme', 'majestic-tube' ),
			'section' => $colours,
			'description' => __( 'The skin the site uses by default. "Follow system" follows each visitor\'s operating-system setting and needs no script. Visitors can still override this with the header toggle, and that choice is stored only in their own browser.', 'majestic-tube' ),
			'choices'     => array(
				'light'  => __( 'Light', 'majestic-tube' ),
				'dark'   => __( 'Dark', 'majestic-tube' ),
				'system' => __( 'Follow system', 'majestic-tube' ),
			),
		),
		'show-theme-toggle'        => array(
			'setting' => 'majestic_tube_show_theme_toggle',
			'default' => 'off',
			'type'    => 'onoff',
			'label'   => __( 'Show the light/dark toggle in the header', 'majestic-tube' ),
			'section' => $colours,
		),
		'main-color'               => array(
			'setting' => 'majestic_tube_main_color',
			'default' => '#0f8a99',
			'type'    => 'color',
			'label'   => __( 'Accent colour', 'majestic-tube' ),
			'section' => $colours,
			'description' => __( 'The highlight colour: buttons, links, the active menu item, and the play badge over a thumbnail.', 'majestic-tube' ),
		),
		'custom-background'        => array(
			'setting' => 'majestic_tube_custom_background',
			'default' => 'off',
			'type'    => 'onoff',
			'label'   => __( 'Ignore the site background image', 'majestic-tube' ),
			'section' => $colours,
			'description' => __( 'Turn this on to drop any background image set under Appearance &rarr; Customize &rarr; Background. The theme then paints its own flat background colour instead. Leave it off to let that image show through.', 'majestic-tube' ),
		),
		'site-font-family'         => array(
			'setting'     => 'majestic_tube_site_font_family',
			'default'     => 'Inter',
			'type'        => 'select',
			'label'       => __( 'Site font', 'majestic-tube' ),
			'section' => $colours,
			'description' => __( 'Inter is bundled with the theme, so every visitor - Windows, macOS, Android, iOS - sees the same letterforms. The system option renders in whatever interface font the visitor already has, which costs no download but looks different on each platform.', 'majestic-tube' ),
			'choices'     => array(
				'Inter'     => __( 'Inter (bundled with the theme)', 'majestic-tube' ),
				'System UI' => __( 'System UI (the visitor\'s own font)', 'majestic-tube' ),
			),
		),
		/* ------------------------------------------------------------------
		 * Site design - header and footer logo
		 * ------------------------------------------------------------------ */
		'use-logo-image'           => array(
			'setting' => 'majestic_tube_use_logo_image',
			'default' => 'off',
			'type'    => 'onoff',
			'label'   => __( 'Use an image logo', 'majestic-tube' ),
			'section' => $logo,
		),
		'image-logo-file'          => array(
			'setting' => 'majestic_tube_image_logo_file',
			'default' => '',
			'type'    => 'file',
			'label'   => __( 'Logo image URL', 'majestic-tube' ),
			'section' => $logo,
		),
		'icon-logo'                => array(
			'setting' => 'majestic_tube_icon_logo',
			'default' => 'film',
			'type'    => 'select',
			'label'   => __( 'Icon logo', 'majestic-tube' ),
			'section' => $logo,
			'choices' => array(
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
			'section' => $logo,
		),
		'logo-font-family'         => array(
			'setting'     => 'majestic_tube_logo_font_family',
			'default'     => 'Inter',
			'type'        => 'select',
			'label'       => __( 'Logo font family', 'majestic-tube' ),
			'section' => $logo,
			'description' => __( 'A text logo can use its own voice here. "Inter" and "System UI" match the rest of the site; the serif and monospace choices are fonts already installed on each visitor device and are never downloaded.', 'majestic-tube' ),
			'choices'     => array(
				'Inter'            => __( 'Inter (same as the site)', 'majestic-tube' ),
				'System UI'        => __( 'System UI', 'majestic-tube' ),
				'System Serif'     => __( 'System serif', 'majestic-tube' ),
				'System Monospace' => __( 'System monospace', 'majestic-tube' ),
			),
		),
		'logo-font-size'           => array(
			'setting'     => 'majestic_tube_logo_font_size',
			'default'     => 36,
			'type'        => 'number',
			'label'       => __( 'Logo font size (px)', 'majestic-tube' ),
			'section' => $logo,
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
			'section' => $logo,
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
			'section' => $logo,
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
			'section' => $logo,
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
			'section' => $logo,
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
			'section' => $logo,
		),
		'favicon'                  => array(
			'setting' => 'majestic_tube_favicon',
			'default' => '',
			'type'    => 'file',
			'label'   => __( 'Browser tab icon (favicon)', 'majestic-tube' ),
			'section' => $logo,
			'description' => __( 'The small picture visitors see on the browser tab. Leave it blank to keep the icon set under Appearance &rarr; Customize &rarr; Site Identity &rarr; Site Icon.', 'majestic-tube' ),
		),
		/* ------------------------------------------------------------------
		 * Site design - logo overlaid on the player
		 * ------------------------------------------------------------------ */
		'logo-watermark-video-player' => array(
			'setting' => 'majestic_tube_logo_watermark_video_player',
			'default' => 'off',
			'type'    => 'onoff',
			'label'   => __( 'Overlay a logo watermark on the player', 'majestic-tube' ),
			'section' => $watermark,
		),
		'image-logo-watermark-file' => array(
			'setting' => 'majestic_tube_image_logo_watermark_file',
			'default' => '',
			'type'    => 'file',
			'label'   => __( 'Watermark image URL', 'majestic-tube' ),
			'section' => $watermark,
		),
		'logo-watermark-max-width' => array(
			'setting'     => 'majestic_tube_logo_watermark_max_width',
			'default'     => 200,
			'type'        => 'number',
			'label'       => __( 'Watermark max width (px)', 'majestic-tube' ),
			'section' => $watermark,
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
			'section' => $watermark,
		),
		'logo-position-video-player' => array(
			'setting' => 'majestic_tube_logo_position_video_player',
			'default' => 'top-left',
			'type'    => 'select',
			'label'   => __( 'Watermark position', 'majestic-tube' ),
			'section' => $watermark,
			'choices' => array(
				'top-left'     => __( 'Top left', 'majestic-tube' ),
				'top-right'    => __( 'Top right', 'majestic-tube' ),
				'bottom-left'  => __( 'Bottom left', 'majestic-tube' ),
				'bottom-right' => __( 'Bottom right', 'majestic-tube' ),
			),
		),
		/* ------------------------------------------------------------------
		 * Site design - video thumbnails
		 * ------------------------------------------------------------------ */
		'thumbnails-ratio'         => array(
			'setting'     => 'majestic_tube_thumbnails_ratio',
			'default'     => '16/9',
			'type'        => 'select',
			'label'       => __( 'Thumbnails aspect ratio', 'majestic-tube' ),
			'section' => $thumbnails,
			'description' => __( 'Written as width/height. The front end splits this value on the slash, so keep the "16/9" form.', 'majestic-tube' ),
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
			'section' => $thumbnails,
			'description' => __( 'How an image that does not match the chosen aspect ratio is handled. Cover fills the box and crops the overflow, Contain fits the whole image and leaves bars, Fill stretches the image to the box.', 'majestic-tube' ),
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
			'section' => $thumbnails,
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
			'section' => $thumbnails,
		),
		/* ------------------------------------------------------------------
		 * Site chrome - header, footer and search
		 * ------------------------------------------------------------------ */
		'show-search-bar'          => array(
			'setting' => 'majestic_tube_show_search_bar',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Display the search bar', 'majestic-tube' ),
			'section' => $chrome,
		),
		'footer-columns'           => array(
			'setting' => 'majestic_tube_footer_columns',
			'default' => 'four-columns-footer',
			'type'    => 'select',
			'label'   => __( 'Footer widget columns', 'majestic-tube' ),
			'section' => $chrome,
			'description' => __( 'How the Footer widgets area is arranged. It does not affect the Footer: code and ads area, which is always full width.', 'majestic-tube' ),
			'choices' => array(
				'one-column-footer'   => __( 'One column', 'majestic-tube' ),
				'two-columns-footer'  => __( 'Two columns', 'majestic-tube' ),
				'three-columns-footer' => __( 'Three columns', 'majestic-tube' ),
				'four-columns-footer' => __( 'Four columns', 'majestic-tube' ),
			),
		),
		'copyright-bar'            => array(
			'setting' => 'majestic_tube_copyright_bar',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Display the copyright bar', 'majestic-tube' ),
			'section' => $chrome,
		),
		'copyright-text'           => array(
			'setting' => 'majestic_tube_copyright_text',
			'default' => '',
			'type'    => 'textarea',
			'label'   => __( 'Copyright text', 'majestic-tube' ),
			'section' => $chrome,
		),
		'display-admin-bar'        => array(
			// Keep the original setting ID so a stored value keeps its
			// meaning: on = display the bar, off = hide it.
			'setting'     => 'majestic_tube_hide_admin_bar',
			'default'     => 'off',
			'type'        => 'onoff',
			'label'       => __( 'Display admin bar for logged-in users', 'majestic-tube' ),
			'section' => $chrome,
			'description' => __( 'When off, the WordPress admin bar is hidden for everyone except administrators.', 'majestic-tube' ),
		),
		/* ------------------------------------------------------------------
		 * Accounts and spam protection
		 * ------------------------------------------------------------------ */
		'enable-membership'        => array(
			'setting' => 'majestic_tube_enable_membership',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Enable membership (login/register)', 'majestic-tube' ),
			'section' => $members,
		),
		'enable-captcha'           => array(
			'setting'     => 'majestic_tube_enable_captcha',
			'default'     => 'off',
			'type'        => 'onoff',
			'label'       => __( 'Enable spam protection', 'majestic-tube' ),
			'description' => __( 'Asks visitors to prove they are human with Cloudflare Turnstile on the sign-up and video submission forms. Nothing is shown until this is on, and both Turnstile keys below must be filled in.', 'majestic-tube' ),
			'section' => $members,
		),
		'turnstile-site-key'      => array(
			'setting'     => 'majestic_tube_turnstile_site_key',
			'default'     => '',
			'type'        => 'text',
			'label'       => __( 'Turnstile site key', 'majestic-tube' ),
			'description' => __( 'The Site Key shown in the Turnstile widget you created at dash.cloudflare.com.', 'majestic-tube' ),
			'section' => $members,
		),
		'turnstile-secret-key'    => array(
			'setting'     => 'majestic_tube_turnstile_secret_key',
			'default'     => '',
			'type'        => 'text',
			'label'       => __( 'Turnstile secret key', 'majestic-tube' ),
			'description' => __( 'The Secret Key from the same widget. This one is sent to Cloudflare from the server and must never appear on the page.', 'majestic-tube' ),
			'section' => $members,
		),
		/* ------------------------------------------------------------------
		 * Visitor video submission
		 * ------------------------------------------------------------------ */
		'enable-video-submission'  => array(
			'setting' => 'majestic_tube_enable_video_submission',
			'default' => 'on',
			'type'    => 'onoff',
			'label'   => __( 'Enable video submission', 'majestic-tube' ),
			'section' => $submission,
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
		/* ------------------------------------------------------------------
		 * Advertising
		 * ------------------------------------------------------------------ */
		/*
		 * In-feed advertising (2.1.0). One code blob repeated through the video
		 * grid after every Nth card. The zone reuses the ad sanitizer and the
		 * same content-output filters as every other placement.
		 */
		'enable-infeed-ad'         => array(
			'setting' => 'majestic_tube_enable_infeed_ad',
			'default' => 'off',
			'type'    => 'onoff',
			'label'   => __( 'Enable in-feed advertising', 'majestic-tube' ),
			'section' => $advertising,
		),
		'infeed-ad-frequency'      => array(
			'setting'     => 'majestic_tube_infeed_ad_frequency',
			'default'     => 9,
			'type'        => 'number',
			'label'       => __( 'In-feed ad every N videos', 'majestic-tube' ),
			'section' => $advertising,
			'description' => __( 'A card-sized content block is inserted after this many video cards. A value below 3 behaves like 3.', 'majestic-tube' ),
		),
		'infeed-ad-code'           => array(
			'setting' => 'majestic_tube_infeed_ad_code',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'In-feed ad code', 'majestic-tube' ),
			'section' => $advertising,
			'customizer' => false,
		),
		/*
		 * Popunder / interstitial zone (2.1.0). The code is the administrator's,
		 * sanitized on input like every other ad field and printed once per
		 * page load from wp_footer.
		 */
		'enable-popunder-ad'       => array(
			'setting' => 'majestic_tube_enable_popunder_ad',
			'default' => 'off',
			'type'    => 'onoff',
			'label'   => __( 'Enable popunder / interstitial code', 'majestic-tube' ),
			'section' => $advertising,
		),
		'popunder-ad-code'         => array(
			'setting' => 'majestic_tube_popunder_ad_code',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Popunder / interstitial code', 'majestic-tube' ),
			'section' => $advertising,
			'customizer' => false,
		),
		/*
		 * Consent gate (2.1.0). Off by default: advertising prints as before.
		 * When switched on, every theme ad placement waits for the consent
		 * filter below to be driven by a cookie banner or CMP plugin.
		 */
		'gate-ads-on-consent'      => array(
			'setting'     => 'majestic_tube_gate_ads_on_consent',
			'default'     => 'off',
			'type'        => 'onoff',
			'label'       => __( 'Only load advertising after consent', 'majestic-tube' ),
			'section' => $advertising,
			'description' => __( 'Hold every ad back until the visitor has agreed to cookies. This needs a consent banner that reports the visitor&rsquo;s choice back to the theme - a cookie banner plugin, or a short snippet of your own. Until consent is recorded, no ad placement prints anything at all.', 'majestic-tube' ),
		),
		'header-ad-desktop'        => array(
			'setting' => 'majestic_tube_ad_header_desktop',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy header content (desktop)', 'majestic-tube' ),
			'section' => $advertising,
			'customizer' => false,
		),
		'header-ad-mobile'         => array(
			'setting' => 'majestic_tube_ad_header_mobile',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy header content (mobile)', 'majestic-tube' ),
			'section' => $advertising,
			'customizer' => false,
		),
		'sidebar-ad-desktop-1'     => array(
			'setting' => 'majestic_tube_ad_sidebar_desktop_1',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy video sidebar content 1', 'majestic-tube' ),
			'section' => $advertising,
			'customizer' => false,
		),
		'sidebar-ad-desktop-2'     => array(
			'setting' => 'majestic_tube_ad_sidebar_desktop_2',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy video sidebar content 2', 'majestic-tube' ),
			'section' => $advertising,
			'customizer' => false,
		),
		'sidebar-ad-desktop-3'     => array(
			'setting' => 'majestic_tube_ad_sidebar_desktop_3',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy video sidebar content 3', 'majestic-tube' ),
			'section' => $advertising,
			'customizer' => false,
		),
		'sidebar-ad-mobile'        => array(
			'setting' => 'majestic_tube_ad_sidebar_mobile',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy mobile sidebar content', 'majestic-tube' ),
			'section' => $advertising,
			'customizer' => false,
		),
		'inside-player-ad-zone-1-desktop' => array(
			'setting'     => 'majestic_tube_ad_inside_player_1',
			'default'     => '',
			'type'        => 'html',
			'label'       => __( 'Legacy player content 1', 'majestic-tube' ),
			'section' => $advertising,
			'description' => __( 'Retained for migration into the Player content widget area.', 'majestic-tube' ),
			'customizer'  => false,
		),
		'inside-player-ad-zone-2-desktop' => array(
			'setting' => 'majestic_tube_ad_inside_player_2',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy player content 2', 'majestic-tube' ),
			'section' => $advertising,
			'customizer' => false,
		),
		'under-player-ad-desktop'  => array(
			'setting' => 'majestic_tube_ad_under_player_desktop',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy below-player content (desktop)', 'majestic-tube' ),
			'section' => $advertising,
			'customizer' => false,
		),
		'under-player-ad-mobile'   => array(
			'setting' => 'majestic_tube_ad_under_player_mobile',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy below-player content (mobile)', 'majestic-tube' ),
			'section' => $advertising,
			'customizer' => false,
		),
		'footer-ad-desktop'        => array(
			'setting' => 'majestic_tube_ad_footer_desktop',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy footer content (desktop)', 'majestic-tube' ),
			'section' => $advertising,
			'customizer' => false,
		),
		'footer-ad-mobile'         => array(
			'setting' => 'majestic_tube_ad_footer_mobile',
			'default' => '',
			'type'    => 'html',
			'label'   => __( 'Legacy footer content (mobile)', 'majestic-tube' ),
			'section' => $advertising,
			'customizer' => false,
		),
		/* ------------------------------------------------------------------
		 * SEO and social metadata
		 * ------------------------------------------------------------------ */
		'facebook-app-id'          => array(
			'setting'     => 'majestic_tube_facebook_app_id',
			'default'     => '',
			'type'        => 'text',
			'label'       => __( 'Facebook app ID', 'majestic-tube' ),
			'section' => $seo,
			'description' => __( 'Optional. Leave this empty to omit the Facebook tag entirely.', 'majestic-tube' ),
		),
		'twitter-site'             => array(
			'setting' => 'majestic_tube_twitter_site',
			'default' => '',
			'type'    => 'text',
			'label'   => __( 'Twitter/x @handle for twitter:site', 'majestic-tube' ),
			'section' => $seo,
		),
		/*
		 * twitter:player card (2.1.0). A player card needs an HTTPS URL that
		 * returns a bare HTML page with the video embedded, because the card
		 * iframe is only ~435px wide. The theme cannot render one on its own
		 * template reliably for every permalink structure, so the administrator
		 * provides the base URL and the theme appends ?post={id}.
		 */
		'twitter-player-url'       => array(
			'setting'     => 'majestic_tube_twitter_player_url',
			'default'     => '',
			'type'        => 'url',
			'label'       => __( 'Twitter player URL base (optional)', 'majestic-tube' ),
			'section' => $seo,
			'description' => __( 'HTTPS URL of a page that embeds a video when given ?post={id}. Leave empty to keep the summary_large_image card.', 'majestic-tube' ),
		),
		'meta-verification'        => array(
			'setting'     => 'majestic_tube_meta_verification',
			'default'     => '',
			'type'        => 'code',
			'label'       => __( 'Search engine verification tags', 'majestic-tube' ),
			'section' => $seo,
			'description' => __( 'Printed verbatim inside <head>. Paste the full <meta> tag(s).', 'majestic-tube' ),
		),
		'seo-footer-text'          => array(
			'setting' => 'majestic_tube_seo_footer_text',
			'default' => '',
			'type'    => 'textarea',
			'label'   => __( 'Homepage intro text', 'majestic-tube' ),
			'section' => $seo,
			'description' => __( 'A short paragraph printed directly under the homepage title. Despite the name this option comes from, it appears on the front page and not in the footer. Search engines often use it as the site description, so one or two sentences works best.', 'majestic-tube' ),
		),
		/* ------------------------------------------------------------------
		 * Custom code
		 * ------------------------------------------------------------------ */
		'google-analytics'         => array(
			'setting'     => 'majestic_tube_google_analytics',
			'default'     => '',
			'type'        => 'code',
			'label'       => __( 'Analytics code', 'majestic-tube' ),
			'section' => $code,
			'description' => __( 'Printed inside the document head on every front-end page.', 'majestic-tube' ),
		),
		'other-scripts'            => array(
			'setting'     => 'majestic_tube_other_scripts',
			'default'     => '',
			'type'        => 'code',
			'label'       => __( 'Other scripts', 'majestic-tube' ),
			'section' => $code,
			'description' => __( 'Extra markup printed before </body>.', 'majestic-tube' ),
		),
		'mobile-scripts'           => array(
			'setting'     => 'majestic_tube_mobile_scripts',
			'default'     => '',
			'type'        => 'code',
			'label'       => __( 'Mobile scripts', 'majestic-tube' ),
			'section' => $code,
			'description' => __( 'Extra markup printed before </body> for mobile visitors only.', 'majestic-tube' ),
		),
	);

	$GLOBALS['majestic_tube_options_map_cache'] = $map_value;

	return $map_value;
}

/**
 * The stored option row that predates the Customizer settings.
 */
define( 'MAJESTIC_TUBE_LEGACY_OPTION', 'wpst-options' );

/**
 * Read a value straight out of the wpst-options array.
 *
 * A site that installed the theme before the Customizer settings existed
 * still has its settings in this database row. Reading it as a fallback means
 * such a site keeps its ad codes, colours and network switches.
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

	// A mapped key: the Customizer wins, then the value stored in the
	// wpst-options row, then the declared default. An explicitly saved empty
	// string is honoured (it means "the administrator cleared this zone").
	if ( isset( $map[ $field_id ] ) ) {
		$saved = get_theme_mod( $map[ $field_id ]['setting'], null );

		if ( null !== $saved ) {
			return $saved;
		}

		return majestic_tube_get_legacy_option( $field_id, $map[ $field_id ]['default'] );
	}

	// A key this theme no longer models but an earlier install may still
	// have stored: honour the value in the wpst-options row, otherwise fall
	// back to the caller's default.
	return majestic_tube_get_legacy_option( $field_id, $default );
}

/**
 * Whether an on/off option is enabled.
 *
 * @param string $field_id Field id.
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
	$priority = 20;

	foreach ( majestic_tube_option_panels() as $slug => $title ) {
		$wp_customize->add_panel(
			'majestic_tube_' . $slug,
			array(
				'title'    => $title,
				'priority' => $priority,
			)
		);

		$priority += 2;
	}

	$section_priority = 10;

	foreach ( majestic_tube_option_sections() as $slug => $section ) {
		$section_args = array(
			'title'    => $section['title'],
			'panel'    => 'majestic_tube_' . $section['panel'],
			'priority' => $section_priority,
		);

		if ( isset( $section['description'] ) ) {
			$section_args['description'] = $section['description'];
		}

		$wp_customize->add_section( 'majestic_tube_options-' . $slug, $section_args );

		++$section_priority;
	}

	foreach ( majestic_tube_options_map() as $config ) {
		if ( isset( $config['customizer'] ) && ! $config['customizer'] ) {
			continue;
		}

		$section  = isset( $config['section'] ) ? $config['section'] : 'home';
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
			'section'     => 'majestic_tube_options-' . $section,
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

/**
 * The colour scheme the site renders in by default.
 *
 * One of "light", "dark" or "system". Anything else - a hand-edited option
 * row, a filter returning nonsense - falls back to "light" so a bad value can
 * never leave the page with no usable colours.
 *
 * @return string
 */
function majestic_tube_color_scheme() {
	$scheme = majestic_tube_get_option( 'wpst-options', 'color-scheme', 'light' );

	if ( ! in_array( $scheme, array( 'light', 'dark', 'system' ), true ) ) {
		$scheme = 'light';
	}

	/**
	 * Filters the default colour scheme.
	 *
	 * @param string $scheme One of light, dark, system.
	 */
	$scheme = apply_filters( 'majestic_tube_color_scheme', $scheme );

	return in_array( $scheme, array( 'light', 'dark', 'system' ), true ) ? $scheme : 'light';
}

/**
 * Whether the header light/dark toggle is shown.
 *
 * @return bool
 */
function majestic_tube_theme_toggle_enabled() {
	/**
	 * Filters whether the header colour-scheme toggle is rendered.
	 *
	 * @param bool $enabled Whether to render the toggle.
	 */
	return (bool) apply_filters( 'majestic_tube_theme_toggle_enabled', majestic_tube_option_is_on( 'show-theme-toggle' ) );
}

/**
 * Apply the visitor's stored skin before the first paint.
 *
 * The server can only know the site's default, and the browser can only know
 * the operating-system preference. Neither is the same thing as what this
 * visitor last chose, so a few lines run in the head - before the body is
 * painted - to put the stored value on the root element. Without them a
 * visitor who chose dark would see one light frame on every page load.
 *
 * The stored value never leaves the visitor's browser, and the script is
 * skipped entirely on a site that neither offers the toggle nor differs from
 * the default, so it costs nothing there.
 *
 * @return void
 */
function majestic_tube_color_scheme_script() {
	if ( 'light' === majestic_tube_color_scheme() && ! majestic_tube_theme_toggle_enabled() ) {
		return;
	}
	?>
	<script>
	( function () {
		var key = 'majestic_tube_theme';
		var valid = { light: 1, dark: 1, system: 1 };

		try {
			var stored = window.localStorage.getItem( key );

			if ( stored && valid[ stored ] ) {
				document.documentElement.setAttribute( 'data-theme', stored );
			}
		} catch ( e ) {
			// Private browsing or a blocked storage partition. The site's
			// default scheme, already on the element, stands.
		}
	}() );
	</script>
	<?php
}
add_action( 'wp_head', 'majestic_tube_color_scheme_script', 1 );

/**
 * Render the header light/dark toggle.
 *
 * All three glyphs are printed and CSS reveals the one matching data-state, so
 * the button is legible before the script runs. The initial state matches the
 * site default, which the head script may already have overridden.
 *
 * @return void
 */
function majestic_tube_theme_toggle() {
	if ( ! majestic_tube_theme_toggle_enabled() ) {
		return;
	}

	$labels = array(
		'light'  => __( 'Light', 'majestic-tube' ),
		'dark'   => __( 'Dark', 'majestic-tube' ),
		'system' => __( 'Follow system', 'majestic-tube' ),
	);
	?>
	<button
		type="button"
		class="majestic-tube-theme-toggle"
		data-majestic-tube-theme-toggle
		data-state="<?php echo esc_attr( majestic_tube_color_scheme() ); ?>"
		aria-label="<?php esc_attr_e( 'Switch colour scheme', 'majestic-tube' ); ?>"
		title="<?php esc_attr_e( 'Switch colour scheme', 'majestic-tube' ); ?>"
	>
		<span class="mt-theme-icon mt-theme-icon-light" aria-hidden="true">&#9788;</span>
		<span class="mt-theme-icon mt-theme-icon-dark" aria-hidden="true">&#9789;</span>
		<span class="mt-theme-icon mt-theme-icon-system" aria-hidden="true">&#9686;</span>
		<span class="screen-reader-text"><?php echo esc_html( $labels[ majestic_tube_color_scheme() ] ); ?></span>
	</button>
	<?php
}

/**
 * The playback speeds offered by the speed control.
 *
 * A fixed, ordered list. The values are deliberately plain numbers rather than
 * strings so the script can hand them straight to `playbackRate` without
 * parsing, and the list is filterable so a site can drop 0.5x (which is slow
 * enough to be a mistake) or add a 2.5x.
 *
 * @return float[]
 */
function majestic_tube_player_speeds() {
	$speeds = array( 0.5, 0.75, 1, 1.25, 1.5, 1.75, 2 );

	/**
	 * Filters the playback speeds offered by the player speed control.
	 *
	 * @param float[] $speeds Ordered list of playback rates.
	 */
	$speeds = apply_filters( 'majestic_tube_player_speeds', $speeds );

	if ( ! is_array( $speeds ) || array() === $speeds ) {
		return array( 1 );
	}

	$clean = array();

	foreach ( $speeds as $speed ) {
		$value = (float) $speed;

		// A rate of zero or below is not a speed; anything absurd is clamped
		// to the range browsers actually implement.
		if ( $value <= 0 ) {
			continue;
		}

		$clean[] = min( 4, $value );
	}

	return array() === $clean ? array( 1 ) : array_values( array_unique( $clean ) );
}

/**
 * Whether each player-UX feature is enabled.
 *
 * One helper per feature rather than a shared lookup, so each has a
 * same-named filter and a caller never has to remember a key string. All four
 * default to off, which is what an upgrade should do.
 *
 * @return bool
 */
function majestic_tube_player_hotkeys_enabled() {
	/**
	 * Filters whether the player's keyboard shortcuts are active.
	 *
	 * @param bool $enabled Whether to bind the player hotkeys.
	 */
	return (bool) apply_filters( 'majestic_tube_player_hotkeys_enabled', majestic_tube_option_is_on( 'player-hotkeys' ) );
}

/**
 * Whether the playback speed control is shown.
 *
 * @return bool
 */
function majestic_tube_player_speed_enabled() {
	/**
	 * Filters whether the playback speed control is rendered.
	 *
	 * @param bool $enabled Whether to render the speed control.
	 */
	return (bool) apply_filters( 'majestic_tube_player_speed_enabled', majestic_tube_option_is_on( 'player-speed' ) );
}

/**
 * Whether the resume-where-you-left-off offer is shown.
 *
 * @return bool
 */
function majestic_tube_player_resume_enabled() {
	/**
	 * Filters whether the resume offer is rendered.
	 *
	 * @param bool $enabled Whether to offer to resume.
	 */
	return (bool) apply_filters( 'majestic_tube_player_resume_enabled', majestic_tube_option_is_on( 'player-resume' ) );
}

/**
 * Whether the theater-mode button is shown.
 *
 * @return bool
 */
function majestic_tube_player_theater_enabled() {
	/**
	 * Filters whether the theater-mode button is rendered.
	 *
	 * @param bool $enabled Whether to render the theater button.
	 */
	return (bool) apply_filters( 'majestic_tube_player_theater_enabled', majestic_tube_option_is_on( 'player-theater' ) );
}
