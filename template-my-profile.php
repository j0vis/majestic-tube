<?php
/**
 * Template Name: Profile
 *
 * Front-end user profile editor.
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
	get_header();
	?>
	<div id="primary" class="content-area">
		<main id="main" class="site-main">
			<p><?php esc_html_e( 'You must be logged in to view your profile.', 'majestic-tube' ); ?></p>
			<a href="#wpst-user-modal" class="majestic-tube-open-login"><?php esc_html_e( 'Login', 'majestic-tube' ); ?></a>
		</main>
	</div>
	<?php
	get_footer();
	return;
}

$current_user   = wp_get_current_user();
$errors         = array();
$updated        = ( isset( $_GET['updated'] ) && is_string( $_GET['updated'] ) ) ? sanitize_key( wp_unslash( $_GET['updated'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$request_method = ( isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

// Handle profile save before get_header() so wp_set_auth_cookie() and
// wp_safe_redirect() run before any HTML output is sent.
if ( 'POST' === $request_method && isset( $_POST['majestic-tube-profile-nonce'] ) ) {

	$nonce = is_string( $_POST['majestic-tube-profile-nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['majestic-tube-profile-nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'majestic_tube_update_profile' ) ) {
		// A stale nonce previously discarded the whole save silently.
		$errors[] = __( 'Your session expired. Please reload the page and try again.', 'majestic-tube' );
	} else {
		$password_changed = false;

		// Password.
		$pass1 = ( isset( $_POST['pass1'] ) && is_string( $_POST['pass1'] ) ) ? (string) wp_unslash( $_POST['pass1'] ) : '';
		$pass2 = ( isset( $_POST['pass2'] ) && is_string( $_POST['pass2'] ) ) ? (string) wp_unslash( $_POST['pass2'] ) : '';

		if ( $pass1 || $pass2 ) {
			if ( $pass1 !== $pass2 ) {
				$errors[] = __( 'The passwords you entered do not match. Your password was not updated.', 'majestic-tube' );
			} elseif ( strlen( $pass1 ) < 8 ) {
				// Server-side twin of the minlength attribute below: the markup
				// alone is advisory and is dropped by any direct form post.
				$errors[] = __( 'The password must be at least 8 characters long.', 'majestic-tube' );
			} else {
				$result = wp_update_user(
					array(
						'ID'        => $current_user->ID,
						'user_pass' => $pass1,
					)
				);

				if ( is_wp_error( $result ) ) {
					$errors[] = $result->get_error_message();
				} else {
					$password_changed = true;
				}
			}
		}

		// Email.
		$email = ( isset( $_POST['email'] ) && is_string( $_POST['email'] ) ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( $email && ! is_email( $email ) ) {
			$errors[] = __( 'The email you entered is not valid. Please try again.', 'majestic-tube' );
		} elseif ( $email && email_exists( $email ) && email_exists( $email ) !== $current_user->ID ) {
			$errors[] = __( 'This email is already used by another user. Try a different one.', 'majestic-tube' );
		} elseif ( $email ) {
			$result = wp_update_user(
				array(
					'ID'         => $current_user->ID,
					'user_email' => $email,
				)
			);

			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			}
		}

		// URL.
		$url = ( isset( $_POST['url'] ) && is_string( $_POST['url'] ) ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

		if ( isset( $_POST['url'] ) ) {
			$result = wp_update_user(
				array(
					'ID'       => $current_user->ID,
					'user_url' => $url,
				)
			);

			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			}
		}

		// Names.
		$first_name = ( isset( $_POST['first-name'] ) && is_string( $_POST['first-name'] ) ) ? sanitize_text_field( wp_unslash( $_POST['first-name'] ) ) : '';
		$last_name  = ( isset( $_POST['last-name'] ) && is_string( $_POST['last-name'] ) ) ? sanitize_text_field( wp_unslash( $_POST['last-name'] ) ) : '';
		$display    = ( isset( $_POST['display_name'] ) && is_string( $_POST['display_name'] ) ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
		$desc       = ( isset( $_POST['description'] ) && is_string( $_POST['description'] ) ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

		// Presence, not truthiness: the fields are always posted, and an empty
		// value is a deliberate "clear this" - a truthy check made names and
		// bio impossible to empty once set.
		if ( isset( $_POST['first-name'] ) ) {
			update_user_meta( $current_user->ID, 'first_name', $first_name );
		}
		if ( isset( $_POST['last-name'] ) ) {
			update_user_meta( $current_user->ID, 'last_name', $last_name );
		}
		if ( $display ) {
			// Free-text display_name allowed impersonation ("Admin"). Only
			// the user's own stored or just-submitted identities are acceptable,
			// mirroring the offered-names list on WP's own profile screen.
			$stored_first  = isset( $current_user->first_name ) ? trim( (string) $current_user->first_name ) : '';
			$stored_last   = isset( $current_user->last_name ) ? trim( (string) $current_user->last_name ) : '';
			$stored_nick   = isset( $current_user->nickname ) ? trim( (string) $current_user->nickname ) : '';
			$stored_login  = isset( $current_user->user_login ) ? trim( (string) $current_user->user_login ) : '';
			$eff_first     = isset( $_POST['first-name'] ) ? trim( $first_name ) : $stored_first;
			$eff_last      = isset( $_POST['last-name'] ) ? trim( $last_name ) : $stored_last;
			$allowed_names = array_unique(
				array_filter(
					array(
						$stored_login,
						$stored_nick,
						trim( $stored_first . ' ' . $stored_last ),
						trim( $stored_last . ' ' . $stored_first ),
						$stored_first,
						$stored_last,
						trim( $eff_first . ' ' . $eff_last ),
						trim( $eff_last . ' ' . $eff_first ),
						$eff_first,
						$eff_last,
					)
				)
			);

			if ( ! $allowed_names || ! in_array( $display, $allowed_names, true ) ) {
				$errors[] = __( 'Please choose one of the offered display names.', 'majestic-tube' );
			} else {
				$result = wp_update_user(
					array(
						'ID'           => $current_user->ID,
						'display_name' => $display,
					)
				);

				if ( is_wp_error( $result ) ) {
					$errors[] = $result->get_error_message();
				}
			}
		}
		if ( isset( $_POST['description'] ) ) {
			update_user_meta( $current_user->ID, 'description', $desc );
		}

		if ( ! $errors ) {
			// Changing user_pass invalidates the current auth cookie; re-issue
			// it before redirecting so the user stays signed in.
			if ( $password_changed && function_exists( 'wp_set_auth_cookie' ) ) {
				wp_set_auth_cookie( $current_user->ID, true );
			}

			// Action hook for plugins and extra field saving, like the original.
			do_action( 'edit_user_profile_update', $current_user->ID );
			// add_query_arg(), not string concatenation: under plain or query
			// permalinks get_permalink() already ends in a query string
			// (?page_id=45), so appending '?updated=true' built a URL with two
			// question marks and WordPress dropped the flag.
			wp_safe_redirect( add_query_arg( 'updated', 'true', get_permalink() ) );
			exit;
		}
	}
}

get_header();
?>

<div id="primary" class="content-area">
	<main id="main" class="site-main">

		<header class="entry-header">
			<h1 class="entry-title"><?php esc_html_e( 'My Profile', 'majestic-tube' ); ?></h1>
		</header>

		<?php if ( $errors ) : ?>
			<div class="majestic-tube-alert error" role="alert">
				<?php foreach ( $errors as $error ) : ?>
					<p><?php echo esc_html( $error ); ?></p>
				<?php endforeach; ?>
			</div>
		<?php elseif ( $updated ) : ?>
			<div class="majestic-tube-alert success" role="status"><?php esc_html_e( 'Profile updated.', 'majestic-tube' ); ?></div>
		<?php endif; ?>

		<form method="post" class="majestic-tube-profile-form">

			<div class="form-field">
				<label for="display_name"><?php esc_html_e( 'Display name', 'majestic-tube' ); ?></label>
				<input type="text" id="display_name" name="display_name" value="<?php echo esc_attr( $current_user->display_name ); ?>" />
			</div>

			<div class="form-field">
				<label for="first-name"><?php esc_html_e( 'First name', 'majestic-tube' ); ?></label>
				<input type="text" id="first-name" name="first-name" value="<?php echo esc_attr( $current_user->first_name ); ?>" />
			</div>

			<div class="form-field">
				<label for="last-name"><?php esc_html_e( 'Last name', 'majestic-tube' ); ?></label>
				<input type="text" id="last-name" name="last-name" value="<?php echo esc_attr( $current_user->last_name ); ?>" />
			</div>

			<div class="form-field">
				<label for="email"><?php esc_html_e( 'Email', 'majestic-tube' ); ?></label>
				<input type="email" id="email" name="email" value="<?php echo esc_attr( $current_user->user_email ); ?>" required />
			</div>

			<div class="form-field">
				<label for="url"><?php esc_html_e( 'Website', 'majestic-tube' ); ?></label>
				<input type="url" id="url" name="url" value="<?php echo esc_attr( $current_user->user_url ); ?>" />
			</div>

			<div class="form-field">
				<label for="description"><?php esc_html_e( 'Bio', 'majestic-tube' ); ?></label>
				<textarea id="description" name="description" rows="4"><?php echo esc_textarea( $current_user->description ); ?></textarea>
			</div>

			<div class="form-field">
				<label for="pass1"><?php esc_html_e( 'New password (leave blank to keep current)', 'majestic-tube' ); ?></label>
				<input type="password" id="pass1" name="pass1" minlength="8" />
			</div>

			<div class="form-field">
				<label for="pass2"><?php esc_html_e( 'Repeat new password', 'majestic-tube' ); ?></label>
				<input type="password" id="pass2" name="pass2" minlength="8" />
			</div>

			<div class="form-field">
				<button type="submit"><?php esc_html_e( 'Save profile', 'majestic-tube' ); ?></button>
			</div>

			<?php wp_nonce_field( 'majestic_tube_update_profile', 'majestic-tube-profile-nonce' ); ?>
		</form>

	</main>
</div>

<?php
get_footer();