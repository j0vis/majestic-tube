<?php
/**
 * Hand the retired browser-tab icon setting over to WordPress's Site Icon.
 *
 * The original theme offered a "Browser tab icon (favicon)" field and printed
 * it as <link rel="icon">. WordPress owns that picture - Appearance -> Customize
 * -> Site Identity -> Site Icon - and the field is retired, so there is one
 * source of truth for one image instead of two. That leaves a site that had
 * already set it: the value is moved, so the icon it had is the icon it keeps.
 *
 * The stored value is a URL, because the field was a text box sanitized with
 * esc_url_raw, while a Site Icon is an attachment. The move therefore has three
 * cases, and only the first two end with an icon in place:
 *
 *   1. The address is an image already in the media library: point the Site
 *      Icon at that attachment.
 *   2. It is an address the library does not hold - an icon hosted somewhere
 *      else, or a file dropped into uploads/ by hand: fetch it once and keep it
 *      in the library, because an attachment is the only thing a Site Icon can
 *      be.
 *   3. Neither works - a .ico the media library refuses, a host that no longer
 *      answers, a value that is not an address at all: say so on the dashboard,
 *      and leave the stored value exactly where it is so the owner can upload
 *      the same image under Site Identity in a minute.
 *
 * Nothing is deleted. The stored value stays in the database, which is what an
 * older release that still reads it needs, and the Site Icon being set is what
 * stops the move from running again.
 *
 * The move happens on an admin request from a user who could set a Site Icon in
 * the first place, and never while a visitor is loading a page: it writes an
 * option and it may fetch a file, and neither belongs on the front end. A
 * request that is not allowed to make the move changes nothing and the next
 * admin page load finishes the job.
 *
 * @package Majestic Tube
 * @version 2.5.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * The value the retired browser-tab icon field holds.
 *
 * Read through the option map like every other setting, so both places a value
 * can live are covered: the Customizer setting a site saved it in, and the
 * legacy wpst-options row a site that installed the theme earlier keeps it in.
 * An empty string means there is nothing to move, and an explicitly cleared
 * setting is honored as the "no icon" it was meant to be.
 *
 * @return string
 */
function majestic_tube_retired_favicon() {
	$value = majestic_tube_get_option( 'wpst-options', 'favicon', '' );

	if ( is_array( $value ) ) {
		return '';
	}

	return trim( (string) $value );
}

/**
 * The absolute address a stored favicon points at.
 *
 * A field that asked for a URL also accepted what WordPress prints for an
 * upload, which is a root-relative path like /wp-content/uploads/icon.png.
 * Both the attachment lookup and the downloader need the address whole, so a
 * relative one is completed against home_url() first. Anything else - a bare
 * filename, an email address, an empty string - is not an address and comes
 * back empty rather than being handed to a lookup that cannot resolve it.
 *
 * @param string $value Stored value.
 * @return string URL, or an empty string when the value is not one.
 */
function majestic_tube_favicon_url( $value ) {
	$value = trim( (string) $value );

	if ( '' === $value ) {
		return '';
	}

	if ( preg_match( '#^(https?:)?//#i', $value ) ) {
		return $value;
	}

	if ( 0 === strpos( $value, '/' ) ) {
		return (string) home_url( $value );
	}

	return '';
}

/**
 * Whether an attachment id names an image a Site Icon can use.
 *
 * @param int $attachment_id Attachment id.
 * @return bool
 */
function majestic_tube_favicon_is_image( $attachment_id ) {
	if ( (int) $attachment_id < 1 ) {
		return false;
	}

	return (bool) wp_attachment_is_image( (int) $attachment_id );
}

/**
 * The attachment a stored favicon already is, or 0 when it is not one yet.
 *
 * Two shapes reach this: a URL, and a bare number. The number is taken as an
 * attachment id because that is what an options row imported from a previous
 * theme can hold, and it is only accepted when it names an image - a stale or
 * unrelated id would otherwise become a Site Icon that renders as nothing, and
 * a missing icon is worse than an icon in the wrong place, which the owner can
 * see and change.
 *
 * @param string $value Stored value.
 * @return int Attachment id, or 0.
 */
function majestic_tube_favicon_attachment_id( $value ) {
	$value = trim( (string) $value );

	if ( '' === $value ) {
		return 0;
	}

	if ( ctype_digit( $value ) ) {
		$attachment_id = (int) $value;

		return majestic_tube_favicon_is_image( $attachment_id ) ? $attachment_id : 0;
	}

	$url = majestic_tube_favicon_url( $value );

	if ( '' === $url ) {
		return 0;
	}

	$attachment_id = (int) attachment_url_to_postid( $url );

	return majestic_tube_favicon_is_image( $attachment_id ) ? $attachment_id : 0;
}

/**
 * Whether this request is allowed to make the move.
 *
 * It writes an option and it may fetch a file, so it takes an admin request
 * from a user who could set a Site Icon by hand. A visitor request never
 * triggers a write, and one from a role that cannot change the Site Icon is
 * not turned into a data migration by the theme.
 *
 * @return bool
 */
function majestic_tube_favicon_move_allowed() {
	return is_admin() && current_user_can( 'edit_theme_options' );
}

/**
 * Fetch an icon the media library does not hold and keep it there.
 *
 * media_sideload_image() is core's own importer, which is the point: it
 * downloads through wp_safe_remote_get(), so a stored value cannot be aimed at
 * a private address, and it validates the file type before it creates an
 * attachment. The new attachment is named after the file, as any sideload is.
 *
 * @param string $url Absolute URL of the image.
 * @return int Attachment id, or 0 when the file could not be imported.
 */
function majestic_tube_import_favicon( $url ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$attachment_id = media_sideload_image( $url, 0, null, 'id' );

	if ( is_wp_error( $attachment_id ) ) {
		return 0;
	}

	return (int) $attachment_id;
}

/**
 * Move the retired favicon into the Site Icon, once.
 *
 * A Site Icon that is already set ends the move before it starts: that is the
 * owner's own choice now, and a setting this theme retired does not get to
 * overwrite it. A value that could not be moved is remembered rather than
 * retried on every page load, because the fetch is a network request and the
 * dashboard is not a place to repeat it - and remembering the value rather than
 * a "tried" flag means a value the owner changes gets a fresh attempt.
 *
 * @return void
 */
function majestic_tube_migrate_favicon_to_site_icon() {
	if ( ! majestic_tube_favicon_move_allowed() ) {
		return;
	}

	if ( 0 < (int) get_option( 'site_icon' ) ) {
		return;
	}

	$value = majestic_tube_retired_favicon();

	if ( '' === $value ) {
		return;
	}

	if ( $value === (string) get_option( 'majestic_tube_favicon_unmoved', '' ) ) {
		return;
	}

	$attachment_id = majestic_tube_favicon_attachment_id( $value );
	$url           = majestic_tube_favicon_url( $value );

	if ( ! $attachment_id && '' !== $url ) {
		$attachment_id = majestic_tube_import_favicon( $url );
	}

	if ( $attachment_id ) {
		update_option( 'site_icon', $attachment_id );
		delete_option( 'majestic_tube_favicon_unmoved' );

		return;
	}

	update_option( 'majestic_tube_favicon_unmoved', $value );
}

/*
 * after_switch_theme covers the activation request and admin_init covers the
 * update, which is the case that matters: a theme update never switches theme,
 * so a migration wired only to activation would wait for the owner to
 * deactivate and reactivate the theme to run.
 */
add_action( 'after_switch_theme', 'majestic_tube_migrate_favicon_to_site_icon', 27 );
add_action( 'admin_init', 'majestic_tube_migrate_favicon_to_site_icon', 30 );

/**
 * The key identifying the icon that is waiting to be moved by hand.
 *
 * Derived from the pending value, so an owner who changes it is told about the
 * new one rather than left with a dismissal they made about the old one.
 *
 * @return string
 */
function majestic_tube_favicon_notice_key() {
	return md5( (string) get_option( 'majestic_tube_favicon_unmoved', '' ) );
}

/**
 * Whether this administrator has already dismissed the notice for this value.
 *
 * Kept per user, like the legal-page warning: a dismissal is a decision about
 * one person's attention, and hiding the notice from the next administrator
 * would be the wrong kind of silence.
 *
 * @param string $key Notice key.
 * @return bool
 */
function majestic_tube_favicon_notice_is_dismissed( $key ) {
	if ( ! function_exists( 'get_user_meta' ) || ! function_exists( 'get_current_user_id' ) ) {
		return false;
	}

	return (string) get_user_meta( get_current_user_id(), 'majestic_tube_favicon_notice', true ) === (string) $key;
}

/**
 * Tell an administrator that the browser-tab icon could not be moved.
 *
 * The alternative to this notice is the silence that started the whole change:
 * a visitor-facing icon disappears on update and nothing on any screen says
 * why. It names the address the theme could not fetch, points at the one screen
 * where the icon belongs, and offers to stop being read.
 *
 * @return void
 */
function majestic_tube_favicon_notice() {
	if ( ! is_admin() || ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	$pending = (string) get_option( 'majestic_tube_favicon_unmoved', '' );

	if ( '' === $pending ) {
		return;
	}

	$key = majestic_tube_favicon_notice_key();

	if ( majestic_tube_favicon_notice_is_dismissed( $key ) ) {
		return;
	}

	$site_identity = admin_url( 'customize.php?autofocus[section]=title_tagline' );
	$dismiss       = wp_nonce_url(
		add_query_arg( 'majestic-tube-favicon-notice', 'dismiss', admin_url( 'index.php' ) ),
		'majestic_tube_favicon_notice'
	);
	?>
	<div class="notice notice-warning">
		<p>
			<strong><?php esc_html_e( 'Majestic Tube:', 'majestic-tube' ); ?></strong>
			<?php
			printf(
				/* translators: %s: the address of the browser-tab icon the theme could not move. */
				esc_html__( 'The browser-tab icon you had set could not be moved to the WordPress Site Icon: %s.', 'majestic-tube' ),
				'<code>' . esc_html( $pending ) . '</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped here.
			);
			?>
			<?php esc_html_e( 'Your setting has not been touched. Upload that image once under Appearance → Customize → Site Identity → Site Icon and the tab icon is back.', 'majestic-tube' ); ?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( $site_identity ); ?>">
				<?php esc_html_e( 'Open Site Identity', 'majestic-tube' ); ?>
			</a>
			<a class="button button-secondary" href="<?php echo esc_url( $dismiss ); ?>">
				<?php esc_html_e( 'Dismiss this warning', 'majestic-tube' ); ?>
			</a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'majestic_tube_favicon_notice' );

/**
 * Remember that this administrator has read the notice.
 *
 * @return void
 */
function majestic_tube_handle_favicon_notice_dismissal() {
	if ( ! isset( $_GET['majestic-tube-favicon-notice'] ) || 'dismiss' !== $_GET['majestic-tube-favicon-notice'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is verified below.
		return;
	}

	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die(
			esc_html__( 'You are not allowed to dismiss this warning.', 'majestic-tube' ),
			esc_html__( 'Browser tab icon', 'majestic-tube' ),
			array( 'response' => 403 )
		);
	}

	check_admin_referer( 'majestic_tube_favicon_notice' );

	update_user_meta( get_current_user_id(), 'majestic_tube_favicon_notice', majestic_tube_favicon_notice_key() );

	$back = wp_get_referer();

	wp_safe_redirect( $back ? remove_query_arg( array( 'majestic-tube-favicon-notice', '_wpnonce' ), $back ) : admin_url( 'index.php' ) );
	exit;
}
add_action( 'admin_init', 'majestic_tube_handle_favicon_notice_dismissal', 4 );
