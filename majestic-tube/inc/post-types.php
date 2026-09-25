<?php
/**
 * Post types and taxonomies - WP-Script Majestic Tube compatible data model.
 *
 * Videos are regular posts ("post" type) relabelled as Videos, exactly like
 * the original theme, so all WP-Script plugins and tools keep working.
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the actors taxonomy on posts (non-hierarchical, like the original).
 */
function majestic_tube_create_actors_taxonomy() {
	$labels = array(
		'name'                       => _x( 'Actors', 'taxonomy general name', 'majestic-tube' ),
		'singular_name'              => _x( 'Actor', 'taxonomy singular name', 'majestic-tube' ),
		'search_items'               => __( 'Search Actors', 'majestic-tube' ),
		'popular_items'              => __( 'Popular Actors', 'majestic-tube' ),
		'all_items'                  => __( 'All Actors', 'majestic-tube' ),
		'parent_item'                => null,
		'parent_item_colon'          => null,
		'edit_item'                  => __( 'Edit Actor', 'majestic-tube' ),
		'update_item'                => __( 'Update Actor', 'majestic-tube' ),
		'add_new_item'               => __( 'Add New Actor', 'majestic-tube' ),
		'new_item_name'              => __( 'New Actor Name', 'majestic-tube' ),
		'separate_items_with_commas' => __( 'Separate Actors with commas', 'majestic-tube' ),
		'add_or_remove_items'        => __( 'Add or remove Actors', 'majestic-tube' ),
		'choose_from_most_used'      => __( 'Choose from the most used Actors', 'majestic-tube' ),
		'not_found'                  => __( 'No actors found', 'majestic-tube' ),
		'menu_name'                  => __( 'Actors', 'majestic-tube' ),
	);

	register_taxonomy(
		'actors',
		'post',
		array(
			'hierarchical'          => false,
			'labels'                => $labels,
			'show_ui'               => true,
			'show_admin_column'     => true,
			'update_count_callback' => '_update_post_term_count',
			'query_var'             => true,
			'show_in_rest'          => true,
			'rewrite'               => array(
				'slug'       => 'actor',
				'with_front' => false,
			),
		)
	);
}
add_action( 'init', 'majestic_tube_create_actors_taxonomy', 0 );

/**
 * Relabel the built-in post type as "Videos" in the admin, like the original.
 */
function majestic_tube_relabel_post_type() {
	global $menu, $submenu;

	if ( ! post_type_exists( 'post' ) ) {
		return;
	}

	$labels             = get_post_type_object( 'post' )->labels;
	$labels->name       = __( 'Videos', 'majestic-tube' );
	$labels->menu_name  = __( 'Videos', 'majestic-tube' );
	$labels->all_items  = __( 'All Videos', 'majestic-tube' );
	$labels->add_new    = __( 'Add Video', 'majestic-tube' );
	$labels->add_new_item = __( 'Add New Video', 'majestic-tube' );
	$labels->edit_item  = __( 'Edit Video', 'majestic-tube' );
	$labels->new_item   = __( 'New Video', 'majestic-tube' );
	$labels->view_item  = __( 'View Video', 'majestic-tube' );
	$labels->search_items = __( 'Search Videos', 'majestic-tube' );
	$labels->not_found  = __( 'No videos found', 'majestic-tube' );
	$labels->not_found_in_trash = __( 'No videos found in Trash', 'majestic-tube' );

	if ( isset( $menu[5] ) ) {
		$menu[5][0] = __( 'Videos', 'majestic-tube' );
	}
	if ( isset( $submenu['edit.php'] ) ) {
		if ( isset( $submenu['edit.php'][5] ) ) {
			$submenu['edit.php'][5][0] = __( 'All Videos', 'majestic-tube' );
		}
		if ( isset( $submenu['edit.php'][10] ) ) {
			$submenu['edit.php'][10][0] = __( 'Add Video', 'majestic-tube' );
		}
		if ( isset( $submenu['edit.php'][15] ) ) {
			$submenu['edit.php'][15][0] = __( 'Video Categories', 'majestic-tube' );
		}
		if ( isset( $submenu['edit.php'][16] ) ) {
			$submenu['edit.php'][16][0] = __( 'Video Tags', 'majestic-tube' );
		}
	}
}
add_action( 'admin_menu', 'majestic_tube_relabel_post_type' );

/**
 * Flush rewrite rules on activation.
 */
function majestic_tube_activate() {
	majestic_tube_create_actors_taxonomy();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'majestic_tube_activate' );