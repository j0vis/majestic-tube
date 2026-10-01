<?php
/**
 * Bulk editing for the per-term SEO fields.
 *
 * The single-term form is right for one term and hopeless for sixty. Naming
 * every category on a site one box at a time is the kind of task that gets
 * half-finished and then abandoned, and a half-finished catalogue of SEO
 * titles is worth very little - the pages that were named compete with the
 * ones that were not.
 *
 * So the term list grows an SEO column and a bulk action, and the action
 * opens one screen holding every selected term at once: what each one has now,
 * what it will fall back to, and both boxes. One Save, one nonce, one pass.
 *
 * The page is deliberately not in the menu. It is somewhere you arrive from,
 * carrying a selection, and go back to when you are done - a permanent menu
 * entry for a screen that is meaningless without a selection would be a
 * worse piece of navigation than none.
 *
 * Saving goes through majestic_tube_term_seo_write(), the same function the
 * single-term form uses, so clearing a box here means exactly what clearing
 * it there means.
 *
 * @package Majestic Tube
 * @version 2.2.25
 */

defined( 'ABSPATH' ) || exit;

/**
 * The bulk action's name in the term list's dropdown.
 */
const MAJESTIC_TUBE_TERM_SEO_BULK_ACTION = 'majestic_tube_term_seo_bulk';

/**
 * The admin page slug.
 */
const MAJESTIC_TUBE_TERM_SEO_BULK_PAGE = 'majestic-tube-term-seo';

/**
 * How many terms one screen will edit.
 *
 * A hundred rows of two fields is already a long form; past that the browser
 * starts to feel it and the owner stops reading. The cap is generous enough
 * for any real category or tag pass, and the screen says so plainly rather
 * than silently trimming the list.
 */
const MAJESTIC_TUBE_TERM_SEO_BULK_LIMIT = 100;

/**
 * Offer the bulk action on every term list that has the fields.
 *
 * Registered on init for the same reason the columns are: the taxonomy has to
 * exist first. The list is empty when an SEO plugin owns this, which is the
 * same answer the columns give.
 *
 * @return void
 */
function majestic_tube_term_seo_bulk_add_action() {

	foreach ( majestic_tube_term_seo_supported_taxonomies() as $taxonomy ) {
		add_filter(
			'bulk_actions-edit-' . $taxonomy,
			function ( $actions ) {
				$actions[ MAJESTIC_TUBE_TERM_SEO_BULK_ACTION ] = __( 'Edit SEO title & description', 'majestic-tube' );

				return $actions;
			}
		);
	}
}
add_action( 'init', 'majestic_tube_term_seo_bulk_add_action', 20 );

/**
 * Register the editor screen, then take it out of the menu.
 *
 * add_submenu_page() is what makes the page exist and be reachable by its
 * slug; removing the entry immediately afterwards is what keeps it out of the
 * Videos menu, where it would otherwise sit next to Add Video promising
 * something it cannot do on its own.
 *
 * @return void
 */
function majestic_tube_register_term_seo_bulk_page() {

	add_submenu_page(
		'edit.php',
		__( 'SEO titles and descriptions', 'majestic-tube' ),
		__( 'SEO titles and descriptions', 'majestic-tube' ),
		'manage_categories',
		MAJESTIC_TUBE_TERM_SEO_BULK_PAGE,
		'majestic_tube_render_term_seo_bulk_page'
	);

	remove_submenu_page( 'edit.php', MAJESTIC_TUBE_TERM_SEO_BULK_PAGE );
}
add_action( 'admin_menu', 'majestic_tube_register_term_seo_bulk_page' );

/**
 * The taxonomy this request is about, if it is one we handle.
 *
 * @return string Taxonomy key, or an empty string.
 */
function majestic_tube_term_seo_bulk_taxonomy() {

	if ( ! isset( $_GET['taxonomy'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		return '';
	}

	$taxonomy = sanitize_key( wp_unslash( $_GET['taxonomy'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.

	if ( ! in_array( $taxonomy, majestic_tube_term_seo_supported_taxonomies(), true ) ) {
		return '';
	}

	return $taxonomy;
}

/**
 * Clean a raw list of term IDs into a trustworthy selection.
 *
 * Values are cast before they are tested rather than after, because
 * absint(-1) is 1 - and the list table's "select all" sends exactly that,
 * which would quietly turn "everything on every page" into a request to edit
 * whichever term happens to have ID 1.
 *
 * @param mixed $raw Raw IDs: an array from the list table, or a comma list.
 * @return int[]
 */
function majestic_tube_term_seo_bulk_clean_ids( $raw ) {

	$ids = array();

	foreach ( (array) $raw as $id ) {
		$id = (int) $id;

		if ( $id > 0 ) {
			$ids[ $id ] = $id;
		}
	}

	return array_slice( array_values( $ids ), 0, MAJESTIC_TUBE_TERM_SEO_BULK_LIMIT );
}

/**
 * The terms ticked in the list table's own checkboxes.
 *
 * @return int[]
 */
function majestic_tube_term_seo_bulk_selection() {

	if ( ! isset( $_GET['checked'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		return array();
	}

	return majestic_tube_term_seo_bulk_clean_ids( wp_unslash( $_GET['checked'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
}

/**
 * The term IDs this screen was opened with.
 *
 * @return int[]
 */
function majestic_tube_term_seo_bulk_ids() {

	if ( ! isset( $_GET['ids'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		return array();
	}

	$raw = wp_unslash( $_GET['ids'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.

	return majestic_tube_term_seo_bulk_clean_ids( is_array( $raw ) ? $raw : explode( ',', (string) $raw ) );
}

/**
 * Turn the bulk action into a trip to the editor.
 *
 * This runs on load-edit-tags.php rather than on handle_bulk_actions, because
 * the term list's own handler would run first and has no idea what the
 * action means: it would fall through and reload the list having done
 * nothing, which is a dead end rather than an error. Intercepting here, with
 * the redirect happening before any output, is the one place that is reliably
 * ahead of it.
 *
 * @return void
 */
function majestic_tube_term_seo_bulk_redirect() {

	$action = '';

	foreach ( array( 'action', 'action2' ) as $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing; the destination carries the capability check.
		if ( isset( $_GET[ $key ] ) && MAJESTIC_TUBE_TERM_SEO_BULK_ACTION === $_GET[ $key ] ) {
			$action = $key;
			break;
		}
	}

	if ( ! $action ) {
		return;
	}

	$taxonomy = majestic_tube_term_seo_bulk_taxonomy();
	$ids      = majestic_tube_term_seo_bulk_selection();

	if ( ! $taxonomy || ! $ids ) {
		return;
	}

	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	$url = add_query_arg(
		array(
			'page'     => MAJESTIC_TUBE_TERM_SEO_BULK_PAGE,
			'taxonomy' => $taxonomy,
			'ids'      => implode( ',', $ids ),
		),
		admin_url( 'admin.php' )
	);

	wp_safe_redirect( $url );
	exit;
}
add_action( 'load-edit-tags.php', 'majestic_tube_term_seo_bulk_redirect' );

/**
 * Confirm a completed bulk save, back on the term list.
 *
 * @return void
 */
function majestic_tube_term_seo_bulk_saved_notice() {

	if ( ! isset( $_GET['mt_seo_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		return;
	}

	$count = isset( $_GET['mt_seo_count'] ) ? absint( $_GET['mt_seo_count'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.

	printf(
		'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: %s: number of terms. */
				_n( 'SEO details saved for %s term.', 'SEO details saved for %s terms.', $count, 'majestic-tube' ),
				number_format_i18n( $count )
			)
		)
	);
}
add_action( 'admin_notices', 'majestic_tube_term_seo_bulk_saved_notice' );

/**
 * Load the terms this screen is editing, in the order they were selected.
 *
 * Every one is re-checked against the taxonomy rather than trusted: the list
 * travelled through a URL, and a term ID from another taxonomy would
 * otherwise receive a title meant for this one.
 *
 * @param string $taxonomy Taxonomy key.
 * @param int[]  $ids      Requested term IDs.
 * @return WP_Term[]
 */
function majestic_tube_term_seo_bulk_terms( $taxonomy, $ids ) {

	$terms = array();

	foreach ( $ids as $id ) {
		$term = get_term( $id );

		if ( ! $term || is_wp_error( $term ) ) {
			continue;
		}

		if ( $taxonomy !== $term->taxonomy ) {
			continue;
		}

		$terms[ $term->term_id ] = $term;
	}

	return $terms;
}

/**
 * Save a submitted bulk form.
 *
 * Posts to this page and redirects back to the term list, so a refresh cannot
 * save twice and the owner lands back where their selection still makes sense.
 *
 * @param string $taxonomy Taxonomy key.
 * @param int[]  $ids      Requested term IDs.
 * @return void
 */
function majestic_tube_handle_term_seo_bulk_save( $taxonomy, $ids ) {

	if ( ! isset( $_POST['majestic_tube_term_seo_bulk_nonce'] )
		|| ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['majestic_tube_term_seo_bulk_nonce'] ) ),
			MAJESTIC_TUBE_TERM_SEO_BULK_ACTION
		)
	) {
		return;
	}

	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	/*
	 * The posted term list is the authority, not the one in the URL: a form
	 * that posts an ID nobody asked for must not be able to write to it.
	 * Intersecting with the URL's list is what makes both halves true.
	 */
	$posted = isset( $_POST['majestic_tube_term_ids'] )
		? array_map( 'absint', array_map( 'trim', explode( ',', wp_unslash( $_POST['majestic_tube_term_ids'] ) ) ) )
		: array();

	$wanted = array_intersect( array_map( 'absint', $ids ), array_filter( $posted ) );

	if ( ! $wanted ) {
		return;
	}

	$titles       = isset( $_POST['majestic_tube_seo_title'] ) ? (array) wp_unslash( $_POST['majestic_tube_seo_title'] ) : array();
	$descriptions = isset( $_POST['majestic_tube_seo_description'] ) ? (array) wp_unslash( $_POST['majestic_tube_seo_description'] ) : array();

	$saved = 0;

	foreach ( majestic_tube_term_seo_bulk_terms( $taxonomy, $wanted ) as $term_id => $term ) {
		$title       = isset( $titles[ $term_id ] ) ? sanitize_text_field( $titles[ $term_id ] ) : '';
		$description = isset( $descriptions[ $term_id ] ) ? sanitize_textarea_field( $descriptions[ $term_id ] ) : '';

		majestic_tube_term_seo_write( $term_id, $title, $description );

		++$saved;
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'mt_seo_saved' => 1,
				'mt_seo_count' => $saved,
			),
			admin_url( 'edit-tags.php?taxonomy=' . $taxonomy )
		)
	);
	exit;
}

/**
 * Render the editor, and handle its form submission.
 *
 * @return void
 */
function majestic_tube_render_term_seo_bulk_page() {

	if ( ! current_user_can( 'manage_categories' ) ) {
		wp_die( esc_html__( 'You do not have permission to edit these terms.', 'majestic-tube' ) );
	}

	$taxonomy = majestic_tube_term_seo_bulk_taxonomy();

	if ( ! $taxonomy ) {
		wp_die( esc_html__( 'That is not a term type with SEO fields.', 'majestic-tube' ) );
	}

	$ids = majestic_tube_term_seo_bulk_ids();

	if ( ! $ids ) {
		wp_die( esc_html__( 'No terms were selected.', 'majestic-tube' ) );
	}

	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) ) {
		majestic_tube_handle_term_seo_bulk_save( $taxonomy, $ids );
	}

	$terms = majestic_tube_term_seo_bulk_terms( $taxonomy, $ids );

	if ( ! $terms ) {
		wp_die( esc_html__( 'None of those terms could be found.', 'majestic-tube' ) );
	}

	$object = get_taxonomy( $taxonomy );
	$plural = ( $object && isset( $object->labels->name ) ) ? $object->labels->name : $taxonomy;
	$count  = count( $terms );
	$list   = admin_url( 'edit-tags.php?taxonomy=' . $taxonomy );

	// The form posts back to itself, so the selection has to travel in the
	// action URL rather than only in the address bar the owner arrived on.
	$self = add_query_arg(
		array(
			'page'     => MAJESTIC_TUBE_TERM_SEO_BULK_PAGE,
			'taxonomy' => $taxonomy,
			'ids'      => implode( ',', $ids ),
		),
		admin_url( 'admin.php' )
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'SEO titles and descriptions', 'majestic-tube' ); ?></h1>

		<p class="description">
			<?php
			printf(
				/* translators: 1: number of terms, 2: plural term type name. */
				esc_html( _n( 'Editing the title and description of %1$d term from %2$s. Leave a box empty to clear it and go back to the default.', 'Editing the title and description of %1$d terms from %2$s. Leave a box empty to clear it and go back to the default.', $count, 'majestic-tube' ) ),
				(int) $count,
				esc_html( $plural )
			);
			?>
		</p>

		<?php if ( $count < count( $ids ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'Some of the terms you selected no longer exist, so they have been left out.', 'majestic-tube' ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $self ); ?>">
			<?php wp_nonce_field( MAJESTIC_TUBE_TERM_SEO_BULK_ACTION, 'majestic_tube_term_seo_bulk_nonce' ); ?>
			<input type="hidden" name="majestic_tube_term_ids" value="<?php echo esc_attr( implode( ',', array_keys( $terms ) ) ); ?>" />

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Term', 'majestic-tube' ); ?></th>
						<th scope="col"><?php esc_html_e( 'SEO title', 'majestic-tube' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Meta description', 'majestic-tube' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $terms as $term_id => $term ) :
						$title_id       = 'majestic_tube_seo_title_' . $term_id;
						$description_id = 'majestic_tube_seo_description_' . $term_id;
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $term->name ); ?></strong>
								<?php if ( ! empty( $term->count ) ) : ?>
									<br /><span class="description">
										<?php
										printf(
											/* translators: %s: number of videos. */
											esc_html( _n( '%s video', '%s videos', (int) $term->count, 'majestic-tube' ) ),
											esc_html( number_format_i18n( (int) $term->count ) )
										);
										?>
									</span>
								<?php endif; ?>
							</td>
							<td>
								<input
									type="text"
									name="majestic_tube_seo_title[<?php echo esc_attr( $term_id ); ?>]"
									id="<?php echo esc_attr( $title_id ); ?>"
									class="majestic-tube-seo-field regular-text"
									data-max="60"
									maxlength="120"
									autocomplete="off"
									value="<?php echo esc_attr( majestic_tube_term_seo_title( $term_id ) ); ?>"
									placeholder="<?php echo esc_attr( majestic_tube_term_seo_title_placeholder( $term ) ); ?>" />
								<span id="<?php echo esc_attr( $title_id ); ?>-counter" class="description majestic-tube-seo-counter"></span>
							</td>
							<td>
								<textarea
									name="majestic_tube_seo_description[<?php echo esc_attr( $term_id ); ?>]"
									id="<?php echo esc_attr( $description_id ); ?>"
									class="majestic-tube-seo-field"
									data-max="160"
									rows="3"
									placeholder="<?php echo esc_attr( majestic_tube_term_seo_description_placeholder( $term ) ); ?>"><?php echo esc_textarea( majestic_tube_term_seo_description( $term_id ) ); ?></textarea>
								<span id="<?php echo esc_attr( $description_id ); ?>-counter" class="description majestic-tube-seo-counter"></span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php majestic_tube_term_seo_counter_script(); ?>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save SEO details', 'majestic-tube' ); ?></button>
				<a class="button" href="<?php echo esc_url( $list ); ?>"><?php esc_html_e( 'Cancel', 'majestic-tube' ); ?></a>
			</p>
		</form>
	</div>
	<?php
}