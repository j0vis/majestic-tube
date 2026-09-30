<?php
/**
 * Site-wide settings for the generated pages.
 *
 * Everything the pSEO engine does was already adjustable, but only in code:
 * every threshold sat behind a filter, so turning a page type off meant
 * editing PHP. This module files the switches a site owner actually needs
 * into the Customizer, beside the rest of the theme's options, and makes the
 * engine read them.
 *
 * The switches are few and blunt on purpose. Per-page control is what the
 * index gate already does: a facet earns its place from the catalogue behind
 * it, and the right way to thin the set is to add videos, not to hand-pick
 * URLs from an admin screen. What the gate cannot decide is whether a whole
 * kind of page suits the site, and that is a single yes/no per kind.
 *
 * A disabled kind is removed everywhere at once - no pages, no rewrite rules,
 * no links from the browse index, nothing in the sitemap - so an old URL
 * simply 404s instead of rendering. Nothing is stored per page, which is why
 * there is nothing to un-do one page at a time.
 *
 * @package Majestic Tube
 * @version 2.2.21
 */

defined( 'ABSPATH' ) || exit;

/**
 * File the generated-page switches into the theme's options map.
 *
 * The entry format is the theme's own, so the Customizer renders these like
 * every other control and the stored values sanitise the same way.
 *
 * @param array $map The theme's option fields.
 * @return array
 */
function majestic_tube_pseo_options_map( $map ) {

	if ( ! function_exists( 'majestic_tube_option_sections' ) ) {
		return $map;
	}

	$sections = majestic_tube_option_sections();

	// File the new section after SEO & Analytics if it is there, otherwise
	// last, so an unusual section list cannot push these controls somewhere
	// that reads as unrelated.
	$order = array_keys( $sections );
	$after = array_search( 'code', $order, true );
	$index = false === $after ? count( $order ) : $after + 1;

	$sections = array_slice( $sections, 0, $index, true )
		+ array(
			'generated' => array(
				'title'       => __( 'Generated Pages', 'majestic-tube' ),
				'panel'       => 'seo',
				'panel_title' => __( 'SEO &amp; Analytics', 'majestic-tube' ),
				'description' => __( 'The extra listing pages the theme builds from your catalogue, such as an actor with a category or two actors together. Turn a kind off and its pages disappear, its web addresses stop working, and it leaves the sitemap. The settings for categories and actors themselves live under Appearance &rarr; Customize, where they always have been.', 'majestic-tube' ),
			),
		)
		+ array_slice( $sections, $index, null, true );

	$map['generated-pages']         = array(
		'setting'     => 'majestic_tube_generated_pages',
		'default'     => 'on',
		'type'        => 'onoff',
		'label'       => __( 'Generated pages', 'majestic-tube' ),
		'section'     => 'generated',
		'description' => __( 'The main switch for every page the theme generates from your catalogue. Turn this off and only the ordinary categories and actor pages remain.', 'majestic-tube' ),
	);
	$map['generated-actor-category'] = array(
		'setting'     => 'majestic_tube_generated_actor_category',
		'default'     => 'on',
		'type'        => 'onoff',
		'label'       => __( 'Actor in category pages', 'majestic-tube' ),
		'section'     => 'generated',
	);
	$map['generated-actor-actor']   = array(
		'setting'     => 'majestic_tube_generated_actor_actor',
		'default'     => 'on',
		'type'        => 'onoff',
		'label'       => __( 'Actor pairing pages', 'majestic-tube' ),
		'section'     => 'generated',
	);
	$map['generated-actor-length']  = array(
		'setting'     => 'majestic_tube_generated_actor_length',
		'default'     => 'on',
		'type'        => 'onoff',
		'label'       => __( 'Actor duration band pages', 'majestic-tube' ),
		'section'     => 'generated',
	);
	$map['generated-category-tag']  = array(
		'setting'     => 'majestic_tube_generated_category_tag',
		'default'     => 'on',
		'type'        => 'onoff',
		'label'       => __( 'Tag in category pages', 'majestic-tube' ),
		'section'     => 'generated',
	);
	$map['generated-studio']        = array(
		'setting'     => 'majestic_tube_generated_studio',
		'default'     => 'on',
		'type'        => 'onoff',
		'label'       => __( 'Studio pages', 'majestic-tube' ),
		'section'     => 'generated',
	);
	$map['generated-series']        = array(
		'setting'     => 'majestic_tube_generated_series',
		'default'     => 'on',
		'type'        => 'onoff',
		'label'       => __( 'Series pages', 'majestic-tube' ),
		'section'     => 'generated',
	);
	$map['generated-browse']        = array(
		'setting'     => 'majestic_tube_generated_browse',
		'default'     => 'on',
		'type'        => 'onoff',
		'label'       => __( 'Browse all collections index', 'majestic-tube' ),
		'section'     => 'generated',
		'description' => __( 'The page at /browse/ that lists every enabled collection so search engines can find them.', 'majestic-tube' ),
	);
	$map['generated-sitemap']       = array(
		'setting'     => 'majestic_tube_generated_sitemap',
		'default'     => 'on',
		'type'        => 'onoff',
		'label'       => __( 'Include generated pages in the sitemap', 'majestic-tube' ),
		'section'     => 'generated',
		'description' => __( 'Adds the enabled pages to the sitemap at /wp-sitemap.xml. This switches itself off while an SEO plugin is providing the sitemap.', 'majestic-tube' ),
	);

	/*
	 * Text templates. Each generated page has three pieces of writing: the
	 * title the browser tab and Google show, the description Google shows
	 * under it, and the paragraph that opens the page. Every one is a format
	 * with named slots, and every slot is replaced with something real from
	 * the catalogue before the text is used. An empty field keeps the
	 * theme's own writing, so a site only overrides what it cares about.
	 */
	$token_help = __( 'Placeholders: {name} the page’s main name, {other} the second name, {count} number of videos, {band} length group, {runtime} total length, {related} most common co-star, {site} your site name.', 'majestic-tube' );

	$text_kinds = array(
		'actor-category' => array( 'type' => 'actor_category', 'label' => __( 'Actor in category pages', 'majestic-tube' ) ),
		'actor-actor'    => array( 'type' => 'actor_actor', 'label' => __( 'Actor pairing pages', 'majestic-tube' ) ),
		'actor-length'   => array( 'type' => 'actor_length', 'label' => __( 'Actor duration band pages', 'majestic-tube' ) ),
		'category-tag'   => array( 'type' => 'category_tag', 'label' => __( 'Tag in category pages', 'majestic-tube' ) ),
		'studio'         => array( 'type' => 'studio', 'label' => __( 'Studio pages', 'majestic-tube' ) ),
		'series'         => array( 'type' => 'series', 'label' => __( 'Series pages', 'majestic-tube' ) ),
	);

	foreach ( $text_kinds as $slug => $kind ) {
		$type = $kind['type'];
		$map[ 'generated-title-' . $slug ] = array(
			'setting'     => 'majestic_tube_generated_title_' . $type,
			'default'     => '',
			'type'        => 'text',
			'label'       => sprintf( /* translators: %s: page kind. */ __( '%s — browser title format', 'majestic-tube' ), $kind['label'] ),
			'section'     => 'generated',
			'description' => __( 'Blank = the theme\'s own format.', 'majestic-tube' ) . ' ' . $token_help,
		);
		$map[ 'generated-desc-' . $slug ] = array(
			'setting'     => 'majestic_tube_generated_desc_' . $type,
			'default'     => '',
			'type'        => 'text',
			'label'       => sprintf( /* translators: %s: page kind. */ __( '%s — search description format', 'majestic-tube' ), $kind['label'] ),
			'section'     => 'generated',
			'description' => __( 'Blank = the theme\'s own format.', 'majestic-tube' ) . ' ' . $token_help,
		);
		$map[ 'generated-intro-' . $slug ] = array(
			'setting'     => 'majestic_tube_generated_intro_' . $type,
			'default'     => '',
			'type'        => 'textarea',
			'label'       => sprintf( /* translators: %s: page kind. */ __( '%s — opening paragraph format', 'majestic-tube' ), $kind['label'] ),
			'section'     => 'generated',
			'description' => __( 'Blank = the theme\'s own paragraph.', 'majestic-tube' ) . ' ' . $token_help,
		);
	}

	return $map;
}
add_filter( 'majestic_tube_options_map', 'majestic_tube_pseo_options_map', 20 );

/**
 * Register the Generated Pages section in the Customizer.
 *
 * Runs BEFORE the theme's own registration (priority 20), because the theme
 * adds its controls in the same pass as its sections and every control in
 * the options map - these included - needs its section to exist first. The
 * section files itself under the same SEO & Analytics panel the theme shows;
 * the panel itself is registered later in the theme's pass, which is fine,
 * as a section's panel pointer is resolved at render time.
 *
 * @param WP_Customize_Manager $wp_customize Manager.
 */
function majestic_tube_pseo_register_section( $wp_customize ) {

	$wp_customize->add_section(
		'majestic_tube_options-generated',
		array(
			'title'       => __( 'Generated Pages', 'majestic-tube' ),
			'panel'       => 'majestic_tube_seo',
			'priority'    => 26,
			'description' => __( 'The extra listing pages the theme builds from your catalogue, such as an actor with a category or two actors together. Turn a kind off and its pages disappear, its web addresses stop working, and it leaves the sitemap.', 'majestic-tube' ),
		)
	);
}
add_action( 'customize_register', 'majestic_tube_pseo_register_section', 15 );

/**
 * Whether the whole generated-pages system is switched on.
 *
 * @return bool
 */
function majestic_tube_generated_pages_enabled() {
	return majestic_tube_option_is_on( 'generated-pages' );
}

/**
 * Whether one kind of generated page is switched on.
 *
 * The master switch comes first: turning generated pages off takes every
 * kind with it, which is the switch's entire point. A kind this theme has
 * never heard of follows the master switch alone, so a site adding its own
 * kind in code is governed by the same switch and never locked out by it.
 *
 * @param string $type Facet type key.
 * @return bool
 */
function majestic_tube_facet_type_enabled( $type ) {

	if ( ! majestic_tube_generated_pages_enabled() ) {
		return false;
	}

	$fields = array(
		'actor_category' => 'generated-actor-category',
		'actor_actor'    => 'generated-actor-actor',
		'actor_length'   => 'generated-actor-length',
		'category_tag'   => 'generated-category-tag',
		'studio'         => 'generated-studio',
		'series'         => 'generated-series',
	);

	if ( ! isset( $fields[ $type ] ) ) {
		return true;
	}

	return majestic_tube_option_is_on( $fields[ $type ] );
}

/**
 * Whether the facet kind the current URL asks for is switched on.
 *
 * @return bool
 */
function majestic_tube_current_facet_type_enabled() {
	$type = sanitize_key( (string) get_query_var( 'mt_facet' ) );

	if ( '' === $type ) {
		return false;
	}

	return majestic_tube_facet_type_enabled( $type );
}

/**
 * Whether the /browse/ index is switched on.
 *
 * @return bool
 */
function majestic_tube_browse_page_enabled() {
	return majestic_tube_generated_pages_enabled() && majestic_tube_option_is_on( 'generated-browse' );
}

/**
 * Whether the generated pages belong in the sitemap.
 *
 * @return bool
 */
function majestic_tube_sitemap_enabled() {
	return majestic_tube_generated_pages_enabled() && majestic_tube_option_is_on( 'generated-sitemap' );
}

/**
 * Remove the switched-off kinds from the facet vocabulary.
 *
 * Everything downstream reads this one table - the URL resolver, the gate,
 * the browse index, the sitemap - so unsetting a kind here makes it stop
 * existing rather than stop being listed.
 *
 * @param array $types Facet type => definition.
 * @return array
 */
function majestic_tube_pseo_enabled_facet_types( $types ) {

	foreach ( array_keys( (array) $types ) as $type ) {
		if ( ! majestic_tube_facet_type_enabled( $type ) ) {
			unset( $types[ $type ] );
		}
	}

	return $types;
}
add_filter( 'majestic_tube_facet_types', 'majestic_tube_pseo_enabled_facet_types', 20 );

/**
 * Remove browse groups whose facet kind is switched off.
 *
 * @param array $groups Group key => definition.
 * @return array
 */
function majestic_tube_pseo_enabled_browse_groups( $groups ) {

	foreach ( (array) $groups as $key => $group ) {
		$type = isset( $group['type'] ) ? $group['type'] : '';

		if ( ! $type || ! majestic_tube_facet_type_enabled( $type ) ) {
			unset( $groups[ $key ] );
		}
	}

	return $groups;
}
add_filter( 'majestic_tube_browse_groups', 'majestic_tube_pseo_enabled_browse_groups', 20 );

/**
 * Honour the sitemap switch.
 *
 * Runs after the theme's own stand-down-for-SEO-plugins check, so the two
 * answers multiply: the page is submitted only when the switch is on and no
 * SEO plugin owns sitemaps.
 *
 * @param bool $owned Whether the theme owns sitemaps.
 * @return bool
 */
function majestic_tube_pseo_sitemap_switch( $owned ) {

	if ( ! majestic_tube_sitemap_enabled() ) {
		return false;
	}

	return $owned;
}
add_filter( 'majestic_tube_output_facet_sitemap', 'majestic_tube_pseo_sitemap_switch', 20 );

/**
 * Read a stored text template for one kind of generated page.
 *
 * @param string $kind  Facet type key (actor_category, studio, ...).
 * @param string $field Which template: title, desc or intro.
 * @return string The stored template, or an empty string when the field was
 *                left blank - which means "use the theme's own writing".
 */
function majestic_tube_pseo_template( $kind, $field ) {

	$slugs = array(
		'actor_category' => 'actor-category',
		'actor_actor'    => 'actor-actor',
		'actor_length'   => 'actor-length',
		'category_tag'   => 'category-tag',
		'studio'         => 'studio',
		'series'         => 'series',
	);

	if ( ! isset( $slugs[ $kind ] ) ) {
		return '';
	}

	return trim( (string) majestic_tube_get_option( 'wpst-options', 'generated-' . $field . '-' . $slugs[ $kind ], '' ) );
}

/**
 * The real values behind a template's placeholders.
 *
 * Every slot comes from the catalogue or the measurement layer, never from
 * the template itself, so a page cannot claim a number nothing measured.
 *
 * @param string $type  Facet type key.
 * @param array  $facet Facet descriptor (term, term2, band, count).
 * @param array  $stats Optional facet stats, for runtime and co-star.
 * @return array<string, string>
 */
function majestic_tube_pseo_tokens( $type, $facet, $stats = array() ) {

	$bands      = function_exists( 'majestic_tube_length_bands' ) ? majestic_tube_length_bands() : array();
	$band       = isset( $facet['band'] ) ? (string) $facet['band'] : '';
	$band_label = ( $band && isset( $bands[ $band ] ) ) ? (string) $bands[ $band ]['label'] : '';

	$tokens = array(
		'name'    => isset( $facet['term'], $facet['term']->name ) ? (string) $facet['term']->name : '',
		'other'   => ( ! empty( $facet['term2'] ) && isset( $facet['term2']->name ) ) ? (string) $facet['term2']->name : '',
		'count'   => number_format_i18n( isset( $facet['count'] ) ? (int) $facet['count'] : 0 ),
		'band'    => $band_label,
		'runtime' => isset( $stats['runtime_label'] ) ? (string) $stats['runtime_label'] : '',
		'related' => ( isset( $stats['related'] ) && is_array( $stats['related'] ) && isset( $stats['related'][0]['label'] ) ) ? (string) $stats['related'][0]['label'] : '',
		'site'    => (string) get_bloginfo( 'name' ),
	);

	return $tokens;
}

/**
 * Fill a template in.
 *
 * Unknown placeholders are removed rather than printed, a doubled blank is
 * collapsed, and a template that reduces to nothing is reported as empty so
 * the caller falls back to the theme's own writing.
 *
 * @param string                $template The stored format.
 * @param array<string, string> $tokens   Slot values.
 * @return string
 */
function majestic_tube_pseo_render_template( $template, $tokens ) {

	$filled = preg_replace_callback(
		'/\{([a-z]+)\}/',
		function ( $m ) use ( $tokens ) {
			return isset( $tokens[ $m[1] ] ) ? $tokens[ $m[1] ] : '';
		},
		(string) $template
	);

	$filled = trim( preg_replace( '/\s+/', ' ', (string) $filled ) );

	return (string) $filled;
}

/**
 * Fill the stored browser-title template, when there is one.
 *
 * Runs before the theme's own writer (default priority 10), which detects
 * the changed value and stops - its built-in formats then never apply, and
 * the stored wording wins. An empty result falls through to the writer.
 *
 * @param string $title Current title.
 * @param array  $facet Facet descriptor.
 * @return string
 */
function majestic_tube_pseo_template_title( $title, $facet ) {

	if ( ! is_array( $facet ) || ! isset( $facet['type'] ) ) {
		return $title;
	}

	$template = majestic_tube_pseo_template( $facet['type'], 'title' );

	if ( '' === $template ) {
		return $title;
	}

	$filled = majestic_tube_pseo_render_template( $template, majestic_tube_pseo_tokens( $facet['type'], $facet ) );

	return ( '' !== $filled ) ? $filled : $title;
}
add_filter( 'majestic_tube_facet_title', 'majestic_tube_pseo_template_title', 5, 2 );

/**
 * Fill the stored search-description template, when there is one.
 *
 * @param string $description Current description.
 * @param array  $facet       Facet descriptor.
 * @return string
 */
function majestic_tube_pseo_template_description( $description, $facet ) {

	if ( ! is_array( $facet ) || ! isset( $facet['type'] ) ) {
		return $description;
	}

	$template = majestic_tube_pseo_template( $facet['type'], 'desc' );

	if ( '' === $template ) {
		return $description;
	}

	$stats = majestic_tube_facet_stats( $facet['type'], $facet['term'], isset( $facet['term2'] ) ? $facet['term2'] : null, isset( $facet['band'] ) ? $facet['band'] : '' );
	$filled = majestic_tube_pseo_render_template( $template, majestic_tube_pseo_tokens( $facet['type'], $facet, $stats ) );

	return ( '' !== $filled ) ? $filled : $description;
}
add_filter( 'majestic_tube_facet_description', 'majestic_tube_pseo_template_description', 5, 2 );

/**
 * Fill the stored opening-paragraph template, when there is one.
 *
 * @param string $summary Current summary.
 * @param string $type    Facet type key.
 * @param WP_Term|null $term  Primary term.
 * @param WP_Term|null $term2 Secondary term.
 * @param string       $band  Duration band key.
 * @param array        $stats Facet stats.
 * @return string
 */
function majestic_tube_pseo_template_summary( $summary, $type, $term = null, $term2 = null, $band = '', $stats = array() ) {

	$template = majestic_tube_pseo_template( $type, 'intro' );

	if ( '' === $template || ! $term ) {
		return $summary;
	}

	$facet = array(
		'type'  => $type,
		'term'  => $term,
		'term2' => $term2,
		'band'  => $band,
		'count' => isset( $stats['count'] ) ? (int) $stats['count'] : 0,
	);

	$filled = majestic_tube_pseo_render_template( $template, majestic_tube_pseo_tokens( $type, $facet, $stats ) );

	return ( '' !== $filled ) ? $filled : $summary;
}
add_filter( 'majestic_tube_facet_summary', 'majestic_tube_pseo_template_summary', 5, 6 );

/**
 * Leave rewrite rules for the switched-off kinds out of the set.
 *
 * A rule left behind would still route old URLs onto the facet template,
 * which is the one outcome the switch must not have: the pages are meant to
 * stop existing, not stop being linked. With the rules gone WordPress 404s
 * the address, which is the honest answer.
 *
 * @param array $rules The site's rewrite rules.
 * @return array
 */
function majestic_tube_pseo_filter_rewrite_rules( $rules ) {

	if ( ! is_array( $rules ) ) {
		return $rules;
	}

	foreach ( $rules as $regex => $query ) {
		$query_vars = array();
		parse_str( parse_url( $query, PHP_URL_QUERY ) ?: $query, $query_vars );

		if ( isset( $query_vars['mt_browse'] ) ) {
			if ( ! majestic_tube_browse_page_enabled() ) {
				unset( $rules[ $regex ] );
			}

			continue;
		}

		if ( isset( $query_vars['mt_facet'] ) && ! majestic_tube_facet_type_enabled( (string) $query_vars['mt_facet'] ) ) {
			unset( $rules[ $regex ] );
		}
	}

	return $rules;
}
add_filter( 'rewrite_rules_array', 'majestic_tube_pseo_filter_rewrite_rules', 20 );
