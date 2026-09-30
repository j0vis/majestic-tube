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

get_header();

if ( ! is_user_logged_in() ) {
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
$updated        = isset( $_GET['updated'] ) ? sanitize_key( wp_unslash( $_GET['updated'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

// Handle profile save.
if ( 'POST' === $request_method && isset( $_POST['majestic-tube-profile-nonce'] ) ) {

	if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['majestic-tube-profile-nonce'] ) ), 'majestic_tube_update_profile' ) ) {

		// Password.
		$pass1 = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : '';
		$pass2 = isset( $_POST['pass2'] ) ? (string) wp_unslash( $_POST['pass2'] ) : '';

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
				}
			}
		}

		// Email.
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

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
		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

		if ( $url ) {
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
		$first_name = isset( $_POST['first-name'] ) ? sanitize_text_field( wp_unslash( $_POST['first-name'] ) ) : '';
		$last_name  = isset( $_POST['last-name'] ) ? sanitize_text_field( wp_unslash( $_POST['last-name'] ) ) : '';
		$display    = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
		$desc       = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

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
		if ( isset( $_POST['description'] ) ) {
			update_user_meta( $current_user->ID, 'description', $desc );
		}

		if ( ! $errors ) {
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