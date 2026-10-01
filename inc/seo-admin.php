<?php
/**
 * A small theme-native SEO workspace, built with ordinary WordPress forms.
 *
 * @package Majestic Tube
 * @version 2.2.26
 */
defined( 'ABSPATH' ) || exit;

function majestic_tube_seo_admin_menu() {
	add_menu_page( __( 'SEO', 'majestic-tube' ), __( 'SEO', 'majestic-tube' ), 'edit_theme_options', 'majestic-tube-seo', 'majestic_tube_seo_admin_page', 'dashicons-search', 59 );
}
add_action( 'admin_menu', 'majestic_tube_seo_admin_menu' );

function majestic_tube_seo_admin_url( $tab = 'overview', $type = '' ) {
	return add_query_arg( array( 'page' => 'majestic-tube-seo', 'tab' => $tab, 'collection' => $type ), admin_url( 'admin.php' ) );
}

function majestic_tube_seo_admin_assets( $hook ) {
	if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && majestic_tube_seo_owned() ) {
		wp_enqueue_script( 'majestic-tube-seo-admin', MAJESTIC_TUBE_URI . '/assets/js/seo-admin.js', array(), MAJESTIC_TUBE_VERSION, true );
	}
	if ( 'toplevel_page_majestic-tube-seo' !== $hook ) { return; }
	wp_enqueue_style( 'majestic-tube-seo-admin', MAJESTIC_TUBE_URI . '/assets/css/seo-admin.css', array(), MAJESTIC_TUBE_VERSION );
	wp_enqueue_script( 'majestic-tube-seo-admin', MAJESTIC_TUBE_URI . '/assets/js/seo-admin.js', array(), MAJESTIC_TUBE_VERSION, true );
}
add_action( 'admin_enqueue_scripts', 'majestic_tube_seo_admin_assets' );

/** Keep a bounded revision history of each collection, never delete source data. */
function majestic_tube_seo_save_collection( $type, $config ) {
	$all = (array) get_option( 'majestic_tube_collections', array() );
	$history = (array) get_option( 'majestic_tube_collection_history', array() );
	$history[ $type ] = isset( $history[ $type ] ) ? $history[ $type ] : array();
	array_unshift( $history[ $type ], array( 'time' => time(), 'config' => majestic_tube_collection_config( $type ) ) );
	$history[ $type ] = array_slice( $history[ $type ], 0, 10 );
	update_option( 'majestic_tube_collection_history', $history, false );
	$all[ $type ] = $config;
	update_option( 'majestic_tube_collections', $all, false );
	majestic_tube_seo_index_dirty();
}

/** A GET never mutates settings or runs imports. */
function majestic_tube_seo_admin_submission( $tab, $type ) {
	$result = array( 'notice' => '', 'error' => '', 'preview' => null );
	if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) { return $result; }
	if ( ! current_user_can( 'edit_theme_options' ) ) { return $result; }
	check_admin_referer( 'majestic_tube_seo_workspace' );
	$action = isset( $_POST['seo_action'] ) && is_string( $_POST['seo_action'] ) ? sanitize_key( wp_unslash( $_POST['seo_action'] ) ) : '';
	if ( 'rebuild' === $action ) {
		delete_option( 'majestic_tube_seo_index_error' );
		if ( ! get_option( 'majestic_tube_seo_index_job', false ) ) { majestic_tube_seo_index_dirty(); }
		majestic_tube_seo_index_tick();
		$result['notice'] = __( 'Index refresh started. Existing collection URLs keep working while it builds.', 'majestic-tube' );
	} elseif ( in_array( $action, array( 'preview', 'publish', 'restore' ), true ) && isset( majestic_tube_collection_defaults()[ $type ] ) ) {
		if ( 'restore' === $action ) {
			$history = (array) get_option( 'majestic_tube_collection_history', array() );
			$revision = isset( $_POST['revision'] ) && is_scalar( $_POST['revision'] ) ? absint( $_POST['revision'] ) : 0;
			$input = isset( $history[ $type ][ $revision ]['config'] ) ? $history[ $type ][ $revision ]['config'] : null;
		} else { $input = isset( $_POST['config'] ) && is_array( $_POST['config'] ) ? wp_unslash( $_POST['config'] ) : array(); }
		$config = null === $input ? new WP_Error( 'revision', __( 'That revision is no longer available.', 'majestic-tube' ) ) : majestic_tube_collection_validate( $type, $input );
		if ( is_wp_error( $config ) ) { $result['error'] = $config->get_error_message(); }
		elseif ( 'preview' === $action ) { $result['preview'] = $config; }
		elseif ( empty( $_POST['confirm'] ) ) { $result['error'] = __( 'Confirm that you want to apply this change to the collection.', 'majestic-tube' ); $result['preview'] = $config; }
		else { majestic_tube_seo_save_collection( $type, $config ); $result['notice'] = __( 'Collection saved. Search text is updated; the discovery index will refresh in the background.', 'majestic-tube' ); }
	} elseif ( 'settings' === $action && 'settings' === $tab ) {
		$settings = array();
		foreach ( array( 'home_title', 'home_description' ) as $key ) { $settings[ $key ] = sanitize_textarea_field( isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '' ); }
		update_option( 'majestic_tube_search_settings', $settings, false );
		$result['notice'] = __( 'Search defaults saved.', 'majestic-tube' );
	} elseif ( 'csv_preview' === $action && 'data' === $tab ) {
		if ( ! current_user_can( 'manage_categories' ) ) { $result['error'] = __( 'You cannot manage taxonomy data.', 'majestic-tube' ); return $result; }
		$result = majestic_tube_seo_csv_preview( $result );
	} elseif ( 'csv_apply' === $action && 'data' === $tab ) {
		if ( ! majestic_tube_seo_owned() || ! current_user_can( 'manage_categories' ) || empty( $_POST['confirm'] ) ) { $result['error'] = __( 'Confirm the reviewed import before applying it.', 'majestic-tube' ); return $result; }
		$stage = get_transient( 'mt_seo_csv_' . get_current_user_id() );
		if ( ! is_array( $stage ) ) { $result['error'] = __( 'The preview expired. Upload the CSV again.', 'majestic-tube' ); return $result; }
		// Validate every term and capability before any write; CSV never creates/deletes terms.
		foreach ( $stage as $row ) {
			$term = get_term_by( 'slug', $row['slug'], $row['taxonomy'] );
			$tax = get_taxonomy( $row['taxonomy'] );
			if ( ! $term || is_wp_error( $term ) || ! $tax || ! in_array( $row['taxonomy'], majestic_tube_term_seo_supported_taxonomies(), true ) || ! current_user_can( $tax->cap->manage_terms ) ) { $result['error'] = __( 'A term is missing or no longer editable. Nothing was imported; preview again.', 'majestic-tube' ); return $result; }
		}
		foreach ( $stage as $row ) {
			$term = get_term_by( 'slug', $row['slug'], $row['taxonomy'] );
			majestic_tube_term_seo_write( $term->term_id, $row['title'], $row['description'] );
		}
		delete_transient( 'mt_seo_csv_' . get_current_user_id() );
		$result['notice'] = sprintf( __( 'Search details saved for %s terms. No videos, terms or URLs were created or deleted.', 'majestic-tube' ), number_format_i18n( count( $stage ) ) );
	}
	return $result;
}

/** Small, explicit CSV adapter; no arbitrary remote feeds or live API fetches. */
function majestic_tube_seo_csv_preview( $result ) {
	delete_transient( 'mt_seo_csv_' . get_current_user_id() );
	$file = isset( $_FILES['seo_csv'] ) && is_array( $_FILES['seo_csv'] ) ? $_FILES['seo_csv'] : array();
	if ( empty( $file['tmp_name'] ) || ! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) || ! isset( $file['error'] ) || UPLOAD_ERR_OK !== $file['error'] || filesize( $file['tmp_name'] ) > 262144 ) { $result['error'] = __( 'Upload a CSV smaller than 256 KB.', 'majestic-tube' ); return $result; }
	$stream = fopen( $file['tmp_name'], 'r' );
	if ( ! $stream ) { $result['error'] = __( 'Could not read the upload.', 'majestic-tube' ); return $result; }
	try {
		$stage = majestic_tube_seo_csv_read( $stream );
		set_transient( 'mt_seo_csv_' . get_current_user_id(), $stage, 15 * MINUTE_IN_SECONDS );
		$result['notice'] = __( 'Preview ready. Review every row below; empty fields will clear saved overrides.', 'majestic-tube' );
	} catch ( Throwable $error ) { $result['error'] = $error->getMessage(); }
	finally { fclose( $stream ); }
	return $result;
}

/** Parse and validate the whole bounded import before storing a preview. */
function majestic_tube_seo_csv_read( $stream ) {
	$header = fgetcsv( $stream, 0, ',', '"', '' );
	if ( is_array( $header ) ) { $header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header[0] ); }
	if ( array( 'taxonomy', 'slug', 'title', 'description' ) !== $header ) { throw new RuntimeException( __( 'CSV headers must be: taxonomy,slug,title,description', 'majestic-tube' ) ); }
	$stage = array(); $seen = array();
	while ( false !== ( $row = fgetcsv( $stream, 0, ',', '"', '' ) ) ) {
		if ( array( null ) === $row ) { continue; }
		if ( count( $row ) !== 4 || count( $stage ) >= 100 ) { throw new RuntimeException( __( 'Use four columns and at most 100 terms per import.', 'majestic-tube' ) ); }
		$row = array_map( 'strval', $row );
		$taxonomy = sanitize_key( $row[0] ); $slug = sanitize_title( $row[1] );
		$tax = get_taxonomy( $taxonomy );
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( ! in_array( $taxonomy, majestic_tube_term_seo_supported_taxonomies(), true ) || ! $tax || ! current_user_can( $tax->cap->manage_terms ) || ! $term || is_wp_error( $term ) ) { throw new RuntimeException( __( 'Every row must name an existing, editable term. Check taxonomy and slug.', 'majestic-tube' ) ); }
		$key = $taxonomy . ':' . $slug;
		if ( isset( $seen[ $key ] ) ) { throw new RuntimeException( __( 'The CSV contains the same term twice.', 'majestic-tube' ) ); }
		$seen[ $key ] = true;
		$stage[] = array( 'taxonomy' => $taxonomy, 'slug' => $slug, 'title' => sanitize_text_field( $row[2] ), 'description' => sanitize_textarea_field( $row[3] ) );
	}
	if ( ! $stage ) { throw new RuntimeException( __( 'The CSV contains no data rows.', 'majestic-tube' ) ); }
	return $stage;
}

function majestic_tube_seo_admin_form_start( $tab, $type = '', $upload = false ) {
	echo '<form method="post" action="' . esc_url( majestic_tube_seo_admin_url( $tab, $type ) ) . '"' . ( $upload ? ' enctype="multipart/form-data"' : '' ) . '>';
	wp_nonce_field( 'majestic_tube_seo_workspace' );
}
function majestic_tube_seo_admin_page() {
	if ( ! current_user_can( 'edit_theme_options' ) ) { wp_die( esc_html__( 'You cannot manage theme settings.', 'majestic-tube' ) ); }
	$tabs = array( 'overview' => __( 'Overview', 'majestic-tube' ), 'collections' => __( 'Collections', 'majestic-tube' ), 'templates' => __( 'Templates', 'majestic-tube' ), 'data' => __( 'Data sources', 'majestic-tube' ), 'settings' => __( 'Settings', 'majestic-tube' ) );
	$tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
	if ( ! isset( $tabs[ $tab ] ) ) { $tab = 'overview'; }
	$type = isset( $_GET['collection'] ) && is_string( $_GET['collection'] ) ? sanitize_key( wp_unslash( $_GET['collection'] ) ) : '';
	$result = majestic_tube_seo_admin_submission( $tab, $type );
	echo '<div class="wrap mt-seo-workspace"><h1>' . esc_html__( 'SEO', 'majestic-tube' ) . '</h1><p>' . esc_html__( 'Useful pages, clear search details, no scores or keyword checklists. Built into Majestic Tube.', 'majestic-tube' ) . '</p><nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'SEO sections', 'majestic-tube' ) . '">';
	foreach ( $tabs as $key => $label ) { echo '<a class="nav-tab' . ( $key === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( majestic_tube_seo_admin_url( $key ) ) . '">' . esc_html( $label ) . '</a>'; }
	echo '</nav>';
	foreach ( array( 'notice' => 'success', 'error' => 'error' ) as $key => $class ) { if ( $result[ $key ] ) { echo '<div class="notice notice-' . esc_attr( $class ) . '"><p>' . esc_html( $result[ $key ] ) . '</p></div>'; } }
	if ( ! majestic_tube_seo_owned() ) { echo '<div class="notice notice-warning"><p>' . esc_html__( 'An SEO plugin is active. Its metadata owns the front end; collection discovery still uses the theme index. Disable it to use theme-native search output. Preview text here does not configure that plugin.', 'majestic-tube' ) . '</p></div>'; }
	if ( 'overview' === $tab ) { majestic_tube_seo_admin_overview(); }
	elseif ( in_array( $tab, array( 'collections', 'templates' ), true ) ) { majestic_tube_seo_admin_collections( $tab, $type, $result['preview'] ); }
	elseif ( 'data' === $tab ) { majestic_tube_seo_admin_data(); }
	else { majestic_tube_seo_admin_settings(); }
	echo '</div>';
}

function majestic_tube_seo_admin_overview() {
	$active = (array) get_option( 'majestic_tube_seo_index_active', array() );
	$job = (array) get_option( 'majestic_tube_seo_index_job', array() );
	$error = get_option( 'majestic_tube_seo_index_error', '' );
	echo '<div class="mt-seo-cards"><section class="mt-seo-card"><h2>' . esc_html__( 'Collection discovery', 'majestic-tube' ) . '</h2><p class="mt-seo-number">' . esc_html( number_format_i18n( majestic_tube_seo_index_count() ) ) . '</p><p>' . esc_html__( 'Eligible collection pages in the discovery index—not a count of pages indexed by Google.', 'majestic-tube' ) . '</p></section><section class="mt-seo-card"><h2>' . esc_html__( 'Search appearance', 'majestic-tube' ) . '</h2><p>' . esc_html__( 'Edit a video, page or taxonomy term to change its search title and description. Empty fields use automatic defaults.', 'majestic-tube' ) . '</p><a href="' . esc_url( admin_url( 'edit.php' ) ) . '">' . esc_html__( 'Edit videos', 'majestic-tube' ) . '</a></section></div>';
	echo '<section class="mt-seo-card"><h2>' . esc_html__( 'Index status', 'majestic-tube' ) . '</h2>';
	if ( $error ) { echo '<p role="alert">' . esc_html( $error ) . '</p>'; }
	if ( ! empty( $active['revision'] ) && $active['revision'] !== get_option( 'majestic_tube_seo_data_revision', 'initial' ) ) { echo '<p>' . esc_html__( 'Catalogue changes are waiting for the next complete index. Discovery counts may temporarily lag the live video listings.', 'majestic-tube' ) . '</p>'; }
	if ( $job ) { echo '<p>' . esc_html( sprintf( __( 'Building: %s primary terms processed. The last complete index remains available.', 'majestic-tube' ), number_format_i18n( $job['processed'] ) ) ) . '</p>'; }
	elseif ( empty( $active['updated'] ) ) { echo '<p>' . esc_html__( 'The first index is waiting to build. Start it below; scheduled batches will finish it.', 'majestic-tube' ) . '</p>'; }
	else { echo '<p>' . esc_html( sprintf( __( 'Last completed: %s', 'majestic-tube' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $active['updated'] ) ) ) . '</p>'; }
	majestic_tube_seo_admin_form_start( 'overview' );
	echo '<input type="hidden" name="seo_action" value="rebuild">'; submit_button( __( 'Refresh collection index', 'majestic-tube' ), 'secondary' ); echo '</form><p class="description">' . esc_html__( 'Batches run through WordPress scheduled tasks. On low-traffic sites, configure a real scheduler for wp-cron.php. Refreshing again also advances an existing build.', 'majestic-tube' ) . '</p></section>';
}

function majestic_tube_seo_admin_collections( $tab, $type, $preview ) {
	$defaults = majestic_tube_collection_defaults();
	if ( ! isset( $defaults[ $type ] ) ) {
		echo '<h2>' . esc_html__( 'Collections from your catalogue', 'majestic-tube' ) . '</h2><p>' . esc_html__( 'Choose a collection to preview its wording. Studios and series remain ordinary taxonomy archives, editable from their term screens.', 'majestic-tube' ) . '</p><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Collection', 'majestic-tube' ) . '</th><th>' . esc_html__( 'Search eligibility', 'majestic-tube' ) . '</th><th>' . esc_html__( 'Discovery pages', 'majestic-tube' ) . '</th><th>' . esc_html__( 'Action', 'majestic-tube' ) . '</th></tr></thead><tbody>';
		foreach ( $defaults as $key => $definition ) { echo '<tr><td>' . esc_html( $definition['label'] ) . '</td><td>' . esc_html( majestic_tube_collection_enabled( $key ) ? __( 'Enabled', 'majestic-tube' ) : __( 'Excluded from search', 'majestic-tube' ) ) . '</td><td>' . esc_html( number_format_i18n( majestic_tube_seo_index_count( $key ) ) ) . '</td><td><a href="' . esc_url( majestic_tube_seo_admin_url( $tab, $key ) ) . '">' . esc_html__( 'Edit and preview', 'majestic-tube' ) . '</a></td></tr>'; }
		echo '</tbody></table>'; return;
	}
	$config = $preview ?: majestic_tube_collection_config( $type );
	echo '<h2>' . esc_html( $defaults[ $type ]['label'] ) . '</h2><p>' . esc_html__( 'One template serves every matching collection. URLs and source videos are unchanged. Only real combinations with enough published videos are eligible; there is no word-count target.', 'majestic-tube' ) . '</p>';
	majestic_tube_seo_admin_form_start( $tab, $type );
	echo '<div class="mt-seo-editor"><section class="mt-seo-card"><h3>' . esc_html__( 'Wording', 'majestic-tube' ) . '</h3>';
	foreach ( array( 'heading' => __( 'Visible heading', 'majestic-tube' ), 'intro' => __( 'Visible introduction (optional)', 'majestic-tube' ), 'title' => __( 'Search title', 'majestic-tube' ), 'description' => __( 'Meta description', 'majestic-tube' ) ) as $field => $label ) {
		echo '<p><label for="mt-' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label></p><textarea class="large-text mt-seo-template" id="mt-' . esc_attr( $field ) . '" name="config[' . esc_attr( $field ) . ']" rows="2">' . esc_textarea( $config[ $field ] ) . '</textarea>';
	}
	preg_match_all( '/\{[^}]+\}/', implode( ' ', $defaults[ $type ] ), $matches );
	echo '<p class="description">' . esc_html__( 'Click a variable to insert it into the last focused field.', 'majestic-tube' ) . '</p><div class="mt-seo-tokens">';
	foreach ( array_unique( $matches[0] ) as $token ) { echo '<button type="button" class="button mt-seo-token" data-token="' . esc_attr( $token ) . '">' . esc_html( $token ) . '</button> '; }
	echo '</div><p><label><input type="checkbox" name="config[enabled]" value="1" ' . checked( $config['enabled'], true, false ) . '> ' . esc_html__( 'Allow eligible pages in search results and discovery', 'majestic-tube' ) . '</label></p><p class="description">' . esc_html__( 'Turning this off removes this collection from browse and sitemap output and adds noindex to its pages. Existing visitor URLs still work.', 'majestic-tube' ) . '</p></section><section class="mt-seo-card"><h3>' . esc_html__( 'Preview', 'majestic-tube' ) . '</h3>';
	echo '<p>' . esc_html( sprintf( __( 'This collection currently has %s discovery pages. Saving changes all matching pages; excluding it removes its indexed discovery links, not its visitor URLs.', 'majestic-tube' ), number_format_i18n( majestic_tube_seo_index_count( $type ) ) ) ) . '</p>';
	$samples = majestic_tube_seo_index_facets( $type, 3 );
	if ( ! $samples ) {
		$samples = array( majestic_tube_seo_example( $type ) );
		echo '<p>' . esc_html__( 'Illustrative example only: there is no indexed sample yet. This does not create a page or invent catalogue data.', 'majestic-tube' ) . '</p>';
	}
	foreach ( $samples as $facet ) {
		$title = majestic_tube_collection_text( $config['title'], $facet );
		$description = majestic_tube_collection_text( $config['description'], $facet );
		echo '<div class="mt-seo-preview"><p class="mt-seo-preview-title">' . esc_html( $title ) . '</p><p class="mt-seo-preview-url">' . esc_html( isset( $facet['url'] ) ? $facet['url'] : majestic_tube_facet_url( $type, $facet['term'], $facet['term2'], $facet['band'] ) ) . '</p><p>' . esc_html( $description ) . '</p><hr><strong>' . esc_html( majestic_tube_collection_text( $config['heading'], $facet ) ) . '</strong><p>' . esc_html( majestic_tube_collection_text( $config['intro'], $facet ) ) . '</p></div>';
	}
	echo '<p class="description">' . esc_html__( 'Search previews are illustrative. Google may rewrite titles and snippets. Preview changes before applying them to every page.', 'majestic-tube' ) . '</p></section></div><p><label><input type="checkbox" name="confirm" value="1"> ' . esc_html__( 'Apply this wording and search eligibility to the entire collection.', 'majestic-tube' ) . '</label></p><p><button class="button" name="seo_action" value="preview">' . esc_html__( 'Preview changes', 'majestic-tube' ) . '</button> <button class="button button-primary" name="seo_action" value="publish">' . esc_html__( 'Save collection', 'majestic-tube' ) . '</button></p></form>';
	$history = (array) get_option( 'majestic_tube_collection_history', array() );
	if ( ! empty( $history[ $type ] ) ) {
		echo '<details class="mt-seo-card"><summary>' . esc_html__( 'Previous versions', 'majestic-tube' ) . '</summary>';
		majestic_tube_seo_admin_form_start( $tab, $type );
		echo '<p><label for="mt-revision">' . esc_html__( 'Restore version', 'majestic-tube' ) . '</label> <select id="mt-revision" name="revision">';
		foreach ( $history[ $type ] as $i => $revision ) { echo '<option value="' . esc_attr( $i ) . '">' . esc_html( wp_date( 'Y-m-d H:i:s', $revision['time'] ) ) . '</option>'; }
		echo '</select></p><p><label><input type="checkbox" name="confirm" value="1"> ' . esc_html__( 'Restore this version for the whole collection.', 'majestic-tube' ) . '</label></p><button class="button" name="seo_action" value="restore">' . esc_html__( 'Restore', 'majestic-tube' ) . '</button></form></details>';
	}
}
function majestic_tube_seo_example( $type ) {
	$actor = (object) array( 'name' => 'Alex', 'slug' => 'alex', 'term_id' => 1, 'taxonomy' => 'actors' );
	$category = (object) array( 'name' => 'Comedy', 'slug' => 'comedy', 'term_id' => 2, 'taxonomy' => 'category' );
	$second = 'actor_actor' === $type ? (object) array( 'name' => 'Jordan', 'slug' => 'jordan', 'term_id' => 3, 'taxonomy' => 'actors' ) : ( 'category_tag' === $type ? (object) array( 'name' => 'Classic', 'slug' => 'classic', 'term_id' => 4, 'taxonomy' => 'post_tag' ) : $category );
	return array( 'type' => $type, 'term' => 'category_tag' === $type ? $category : $actor, 'term2' => 'actor_length' === $type ? null : $second, 'band' => 'actor_length' === $type ? 'short' : '', 'count' => 12 );
}

function majestic_tube_seo_admin_data() {
	echo '<h2>' . esc_html__( 'Your catalogue is the dataset', 'majestic-tube' ) . '</h2><p>' . esc_html__( 'Collections read published videos and their taxonomy relationships. No duplicate dataset or generated posts are needed.', 'majestic-tube' ) . '</p><ul>';
	foreach ( array( 'actors' => __( 'Actors', 'majestic-tube' ), 'category' => __( 'Video categories', 'majestic-tube' ), 'post_tag' => __( 'Video tags', 'majestic-tube' ), 'studio' => __( 'Studios', 'majestic-tube' ), 'series' => __( 'Series', 'majestic-tube' ) ) as $taxonomy => $label ) { echo '<li><a href="' . esc_url( add_query_arg( array( 'taxonomy' => $taxonomy, 'post_type' => 'post' ), admin_url( 'edit-tags.php' ) ) ) . '">' . esc_html( $label ) . '</a></li>'; }
	echo '</ul><section class="mt-seo-card"><h3>' . esc_html__( 'Import term search details', 'majestic-tube' ) . '</h3><p>' . esc_html__( 'Supplement existing terms with search titles and descriptions. Preview first; imports never create or delete terms, videos or URLs. Maximum 100 terms per upload.', 'majestic-tube' ) . '</p><pre>taxonomy,slug,title,description
category,comedy,Comedy videos,Browse our comedy collection.</pre>';
	if ( ! majestic_tube_seo_owned() ) { echo '<p>' . esc_html__( 'CSV metadata import is unavailable while an SEO plugin owns these fields.', 'majestic-tube' ) . '</p></section>'; return; }
	majestic_tube_seo_admin_form_start( 'data', '', true );
	echo '<p><label for="mt-seo-csv">' . esc_html__( 'CSV file', 'majestic-tube' ) . '</label> <input id="mt-seo-csv" type="file" name="seo_csv" accept=".csv,text/csv" required></p><button class="button" name="seo_action" value="csv_preview">' . esc_html__( 'Validate and preview', 'majestic-tube' ) . '</button></form>';
	$stage = get_transient( 'mt_seo_csv_' . get_current_user_id() );
	if ( is_array( $stage ) ) {
		echo '<h3>' . esc_html__( 'Review import', 'majestic-tube' ) . '</h3><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Term', 'majestic-tube' ) . '</th><th>' . esc_html__( 'Search title', 'majestic-tube' ) . '</th><th>' . esc_html__( 'Description', 'majestic-tube' ) . '</th></tr></thead><tbody>';
		foreach ( $stage as $row ) { echo '<tr><td>' . esc_html( $row['taxonomy'] . ':' . $row['slug'] ) . '</td><td>' . esc_html( $row['title'] ?: __( '(clear override)', 'majestic-tube' ) ) . '</td><td>' . esc_html( $row['description'] ?: __( '(clear override)', 'majestic-tube' ) ) . '</td></tr>'; }
		echo '</tbody></table>'; majestic_tube_seo_admin_form_start( 'data' );
		echo '<p><label><input type="checkbox" name="confirm" value="1"> ' . esc_html__( 'Apply these reviewed values. Empty fields clear overrides.', 'majestic-tube' ) . '</label></p><button class="button button-primary" name="seo_action" value="csv_apply">' . esc_html__( 'Apply import', 'majestic-tube' ) . '</button></form>';
	}
	echo '</section>';
}
function majestic_tube_seo_admin_settings() {
	$settings = (array) get_option( 'majestic_tube_search_settings', array() );
	echo '<h2>' . esc_html__( 'Homepage search appearance', 'majestic-tube' ) . '</h2><p>' . esc_html__( 'These fields describe the homepage in search and social previews. They do not replace its visible heading or introduction.', 'majestic-tube' ) . '</p>';
	majestic_tube_seo_admin_form_start( 'settings' );
	echo '<input type="hidden" name="seo_action" value="settings">';
	foreach ( array( 'home_title' => __( 'Search title', 'majestic-tube' ), 'home_description' => __( 'Meta description', 'majestic-tube' ) ) as $key => $label ) { echo '<p><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></p><textarea class="large-text mt-seo-counter" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" rows="2">' . esc_textarea( isset( $settings[ $key ] ) ? $settings[ $key ] : '' ) . '</textarea>'; }
	echo '<p class="description">' . esc_html__( 'Leave empty to use the site title and visible introduction. No hard character limits or ranking scores.', 'majestic-tube' ) . '</p>'; submit_button( __( 'Save search defaults', 'majestic-tube' ) ); echo '</form><h2>' . esc_html__( 'Appearance and integrations', 'majestic-tube' ) . '</h2><p><a href="' . esc_url( admin_url( 'customize.php' ) ) . '">' . esc_html__( 'Customize homepage placement, archive card wording, social handles and verification tags', 'majestic-tube' ) . '</a></p><p>' . esc_html__( 'The theme supplies canonical URLs, robots directives, schema and XML sitemaps automatically. No plugin is required. Theme changes leave all saved data in the database, but this SEO workspace and virtual collection routing belong to Majestic Tube.', 'majestic-tube' ) . '</p>';
}
