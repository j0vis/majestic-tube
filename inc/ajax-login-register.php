<?php
/**
 * Membership: login / register / reset-password modal and AJAX handlers.
 *
 * Uses the original action names (wpst_login_member, wpst_register_member,
 * wpst_reset_password) so existing front-end tooling keeps working.
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render one membership form field.
 *
 * @param string $name        Field name without the `wpst_` prefix.
 * @param string $label       Visible label.
 * @param string $type        Input type.
 * @param string $id        Optional input ID.
 * @param int    $minlength Optional minimum password length.
 * @return void
 */
function majestic_tube_membership_field( $name, $label, $type = 'text', $id = '', $minlength = 0 ) {
	$field_id       = $id ? ' for="' . esc_attr( $id ) . '"' : '';
	$minlength_attr = $minlength ? sprintf( ' minlength="%d"', absint( $minlength ) ) : '';
	?>
	<div class="form-field">
		<label<?php echo $field_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped ID. ?>><?php echo esc_html( $label ); ?></label>
		<input class="required" name="wpst_<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( $type ); ?>"<?php echo $id ? ' id="' . esc_attr( $id ) . '"' : ''; ?> required<?php echo $minlength_attr; ?> />
	</div>
	<?php
}

/**
 * Whether reCAPTCHA is enabled for membership forms.
 *
 * @return bool
 */
function majestic_tube_recaptcha_enabled() {
	return 'on' === majestic_tube_get_option( 'wpst-options', 'enable-recaptcha' );
}

/**
 * Return the configured reCAPTCHA site key.
 *
 * @return string
 */
function majestic_tube_recaptcha_site_key() {
	return (string) majestic_tube_get_option( 'wpst-options', 'recaptcha-site-key' );
}

/**
 * Print the login/register/reset modal for logged-out visitors.
 */
function majestic_tube_login_register_modal() {
	if ( is_user_logged_in() ) {
		return;
	}

	$recaptcha_on = majestic_tube_recaptcha_enabled();
	$site_key     = majestic_tube_recaptcha_site_key();
	?>
	<div class="majestic-tube-user-modal" id="wpst-user-modal" hidden>
		<div class="majestic-tube-modal-dialog" data-active-tab="">
			<div class="majestic-tube-modal-content">

				<button type="button" class="majestic-tube-modal-close" aria-label="<?php esc_attr_e( 'Close', 'majestic-tube' ); ?>">&times;</button>

				<!-- Register form -->
				<div class="wpst-register">
					<?php if ( get_option( 'users_can_register' ) ) : ?>
						<h3><?php printf( esc_html__( 'Join %s', 'majestic-tube' ), esc_html( get_bloginfo( 'name' ) ) ); ?></h3>

						<form id="wpst_registration_form" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="POST">
							<?php
							majestic_tube_membership_field( 'user_login', __( 'Username', 'majestic-tube' ) );
							majestic_tube_membership_field( 'user_email', __( 'Email', 'majestic-tube' ), 'email', 'wpst_user_email' );
							majestic_tube_membership_field( 'user_pass', __( 'Password', 'majestic-tube' ), 'password', 'wpst_user_pass', 8 );
							?>
							<?php if ( $recaptcha_on && $site_key ) : ?>
								<div class="g-recaptcha" data-sitekey="<?php echo esc_attr( $site_key ); ?>"></div>
							<?php endif; ?>
							<input type="hidden" name="action" value="wpst_register_member" />
							<button type="submit"><?php esc_html_e( 'Sign up', 'majestic-tube' ); ?></button>
							<?php wp_nonce_field( 'ajax-login-nonce', 'register-security' ); ?>
						</form>
						<div class="wpst-errors" role="alert"></div>
					<?php else : ?>
						<p><?php esc_html_e( 'Registration is disabled.', 'majestic-tube' ); ?></p>
					<?php endif; ?>
				</div>

				<!-- Login form -->
				<div class="wpst-login">
					<h3><?php printf( esc_html__( 'Login to %s', 'majestic-tube' ), esc_html( get_bloginfo( 'name' ) ) ); ?></h3>

					<form id="wpst_login_form" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="post">
						<?php
						majestic_tube_membership_field( 'user_login', __( 'Username', 'majestic-tube' ) );
						majestic_tube_membership_field( 'user_pass', __( 'Password', 'majestic-tube' ), 'password', 'wpst_user_pass' );
						?>
						<div class="form-field lost-password">
							<input type="hidden" name="action" value="wpst_login_member" />
							<button type="submit"><?php esc_html_e( 'Login', 'majestic-tube' ); ?></button>
							<a href="#wpst-reset-password"><?php esc_html_e( 'Lost Password?', 'majestic-tube' ); ?></a>
						</div>
						<?php wp_nonce_field( 'ajax-login-nonce', 'login-security' ); ?>
					</form>
					<div class="wpst-errors" role="alert"></div>
				</div>

				<!-- Reset password form -->
				<div class="wpst-reset-password">
					<h3><?php esc_html_e( 'Reset Password', 'majestic-tube' ); ?></h3>
					<p><?php esc_html_e( 'Enter the username or e-mail you used in your profile. A password reset link will be sent to you by email.', 'majestic-tube' ); ?></p>

					<form id="wpst_reset_password_form" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="post">
						<?php majestic_tube_membership_field( 'user_or_email', __( 'Username or E-mail', 'majestic-tube' ), 'text', 'wpst_user_or_email' ); ?>
						<input type="hidden" name="action" value="wpst_reset_password" />
						<button type="submit"><?php esc_html_e( 'Get new password', 'majestic-tube' ); ?></button>
						<?php wp_nonce_field( 'ajax-login-nonce', 'password-security' ); ?>
					</form>
					<div class="wpst-errors" role="alert"></div>
				</div>

				<div class="majestic-tube-modal-footer">
					<span class="wpst-register-footer">
						<?php esc_html_e( "Don't have an account?", 'majestic-tube' ); ?> <a href="#wpst-register"><?php esc_html_e( 'Sign up', 'majestic-tube' ); ?></a>
					</span>
					<span class="wpst-login-footer">
						<?php esc_html_e( 'Already have an account?', 'majestic-tube' ); ?> <a href="#wpst-login"><?php esc_html_e( 'Login', 'majestic-tube' ); ?></a>
					</span>
				</div>

			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'majestic_tube_login_register_modal' );

/**
 * Sanitize and validate recaptcha token via Google's siteverify API.
 *
 * @param string $response g-recaptcha-response token.
 * @return bool
 */
function majestic_tube_verify_recaptcha( $response ) {
	$secret = majestic_tube_get_option( 'wpst-options', 'recaptcha-secret-key' );

	if ( ! $secret || ! $response ) {
		return false;
	}

	$resp = wp_remote_post(
		'https://www.google.com/recaptcha/api/siteverify',
		array(
			'timeout' => 10,
			'body'    => array(
				'secret'   => $secret,
				'response' => $response,
			),
		)
	);

	if ( is_wp_error( $resp ) ) {
		return false;
	}

	$body = json_decode( wp_remote_retrieve_body( $resp ) );

	return ! empty( $body->success );
}

/**
 * Shared membership JSON responders.
 *
 * KingTube clients branch on a boolean `error` key and inject `message` as the
 * alert markup shipped by the original endpoints. Keep that two-key shape for
 * both outcomes; replacing it with a WordPress success/error envelope or a
 * string-valued `error` breaks existing membership forms silently.
 *
 * @param string $message Error message.
 */
function majestic_tube_ajax_error( $message ) {
	wp_send_json(
		array(
			'error'   => true,
			'message' => '<div class="alert alert-danger">' . esc_html( $message ) . '</div>',
		)
	);
}

/**
 * Return a successful membership response in the original flat shape.
 *
 * @param string $message Display message.
 * @param string $tag     Optional HTML wrapper, `div` or `p`.
 */
function majestic_tube_ajax_membership_success( $message, $tag = 'div' ) {
	$tag = 'p' === $tag ? 'p' : 'div';

	wp_send_json(
		array(
			'error'   => false,
			'message' => '<' . $tag . ' class="alert alert-success">' . esc_html( $message ) . '</' . $tag . '>',
		)
	);
}

/**
 * Handle login requests (original action: wpst_login_member).
 */
function majestic_tube_ajax_login() {
	check_ajax_referer( 'ajax-login-nonce', 'login-security', false ) || majestic_tube_ajax_error( __( 'Security check failed.', 'majestic-tube' ) );

	$login    = isset( $_POST['wpst_user_login'] ) ? sanitize_user( wp_unslash( $_POST['wpst_user_login'] ) ) : '';
	$password = isset( $_POST['wpst_user_pass'] ) ? (string) wp_unslash( $_POST['wpst_user_pass'] ) : '';

	if ( ! $login || ! $password ) {
		majestic_tube_ajax_error( __( 'Please fill in both username and password.', 'majestic-tube' ) );
	}

	$creds = array(
		'user_login'    => $login,
		'user_password' => $password,
		'remember'      => true,
	);

	$user = wp_signon( $creds, is_ssl() );

	if ( is_wp_error( $user ) ) {
		majestic_tube_ajax_error( $user->get_error_message() );
	}

	majestic_tube_ajax_membership_success( __( 'Login successful, reloading page...', 'majestic-tube' ) );
}
add_action( 'wp_ajax_nopriv_wpst_login_member', 'majestic_tube_ajax_login' );

/**
 * Handle registration requests (original action: wpst_register_member).
 */
function majestic_tube_ajax_register() {
	check_ajax_referer( 'ajax-login-nonce', 'register-security', false ) || majestic_tube_ajax_error( __( 'Security check failed.', 'majestic-tube' ) );

	if ( ! get_option( 'users_can_register' ) ) {
		majestic_tube_ajax_error( __( 'Registration is disabled.', 'majestic-tube' ) );
	}

	if ( majestic_tube_recaptcha_enabled() ) {
		$token = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '';

		if ( ! majestic_tube_verify_recaptcha( $token ) ) {
			majestic_tube_ajax_error( __( 'Captcha verification failed, please try again.', 'majestic-tube' ) );
		}
	}

	$login    = isset( $_POST['wpst_user_login'] ) ? sanitize_user( wp_unslash( $_POST['wpst_user_login'] ) ) : '';
	$email    = isset( $_POST['wpst_user_email'] ) ? sanitize_email( wp_unslash( $_POST['wpst_user_email'] ) ) : '';
	$password = isset( $_POST['wpst_user_pass'] ) ? (string) wp_unslash( $_POST['wpst_user_pass'] ) : '';

	if ( ! $login || ! $email || ! $password ) {
		majestic_tube_ajax_error( __( 'All fields are required.', 'majestic-tube' ) );
	}

	if ( username_exists( $login ) ) {
		majestic_tube_ajax_error( __( 'This username is already taken.', 'majestic-tube' ) );
	}

	if ( ! is_email( $email ) ) {
		majestic_tube_ajax_error( __( 'The email address is not valid.', 'majestic-tube' ) );
	}

	if ( email_exists( $email ) ) {
		majestic_tube_ajax_error( __( 'This email is already registered.', 'majestic-tube' ) );
	}

	$user_id = wp_create_user( $login, $password, $email );

	if ( is_wp_error( $user_id ) ) {
		majestic_tube_ajax_error( $user_id->get_error_message() );
	}

	// Notify both the new member and the site administrator through the
	// non-deprecated API available throughout the theme's supported WP range.
	wp_send_new_user_notifications( $user_id, 'both' );

	majestic_tube_ajax_membership_success( __( 'Registration complete. You can now log in.', 'majestic-tube' ) );
}
add_action( 'wp_ajax_nopriv_wpst_register_member', 'majestic_tube_ajax_register' );

/**
 * Handle password reset requests (original action: wpst_reset_password).
 */
function majestic_tube_ajax_reset_password() {
	check_ajax_referer( 'ajax-login-nonce', 'password-security', false ) || majestic_tube_ajax_error( __( 'Security check failed.', 'majestic-tube' ) );

	$user_or_email = isset( $_POST['wpst_user_or_email'] ) ? sanitize_text_field( wp_unslash( $_POST['wpst_user_or_email'] ) ) : '';

	if ( ! $user_or_email ) {
		majestic_tube_ajax_error( __( 'Enter a username or email address.', 'majestic-tube' ) );
	}

	if ( is_email( $user_or_email ) ) {
		$user = get_user_by( 'email', $user_or_email );
	} else {
		$user = get_user_by( 'login', $user_or_email );
	}

	if ( ! $user ) {
		// Do not reveal whether the account exists, but retain the original
		// `{ error: false, message: HTML }` success shape.
		majestic_tube_ajax_membership_success( __( 'If an account exists, a reset link has been sent.', 'majestic-tube' ), 'p' );
	}

	$key = get_password_reset_key( $user );

	if ( is_wp_error( $key ) ) {
		majestic_tube_ajax_error( __( 'Could not create reset key. Please try again.', 'majestic-tube' ) );
	}

	$reset_url = network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user->user_login ), 'login' );

	$subject = sprintf( '[%s] %s', wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), __( 'Password Reset', 'majestic-tube' ) );
	$body    = sprintf(
		/* translators: 1: site name, 2: reset URL. */
		__( 'Someone requested a password reset for %1$s. To set a new password, visit: %2$s', 'majestic-tube' ),
		get_option( 'blogname' ),
		$reset_url
	);

	wp_mail( $user->user_email, $subject, $body );

	majestic_tube_ajax_membership_success( __( 'A password reset link has been sent to your email address.', 'majestic-tube' ), 'p' );
}
add_action( 'wp_ajax_nopriv_wpst_reset_password', 'majestic_tube_ajax_reset_password' );

/**
 * Membership nav: My Account dropdown / Login link appended to the main menu.
 */
function majestic_tube_membership_nav( $items, $args ) {
	if ( 'majestic_tube_main_menu' !== $args->theme_location || 'off' === majestic_tube_get_option( 'wpst-options', 'enable-membership' ) ) {
		return $items;
	}

	if ( is_user_logged_in() ) {
		$items .= '<li class="my-account"><a href="#">' . esc_html__( 'My Account', 'majestic-tube' ) . '</a>';
		$items .= '<ul class="sub-menu">';
		$items .= '<li><a href="' . esc_url( home_url( '/submit-a-video' ) ) . '">' . esc_html__( 'Submit a Video', 'majestic-tube' ) . '</a></li>';
		$items .= '<li><a href="' . esc_url( get_author_posts_url( get_current_user_id() ) ) . '">' . esc_html__( 'My Channel', 'majestic-tube' ) . '</a></li>';
		$items .= '<li><a href="' . esc_url( home_url( '/my-profile' ) ) . '">' . esc_html__( 'My Profile', 'majestic-tube' ) . '</a></li>';
		$items .= '<li><a href="' . esc_url( wp_logout_url( is_home() ? home_url() : get_permalink() ) ) . '">' . esc_html__( 'Logout', 'majestic-tube' ) . '</a></li>';
		$items .= '</ul></li>';
	} else {
		$items .= '<li><a href="#wpst-user-modal">' . esc_html__( 'Login', 'majestic-tube' ) . '</a></li>';
	}

	return $items;
}
add_filter( 'wp_nav_menu_items', 'majestic_tube_membership_nav', 10, 2 );