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
 * Whether spam protection is switched on.
 *
 * @return bool
 */
function majestic_tube_captcha_enabled() {
	return 'on' === majestic_tube_get_option( 'wpst-options', 'enable-captcha' );
}

/**
 * The public Turnstile key.
 *
 * @return string
 */
function majestic_tube_captcha_site_key() {
	return (string) majestic_tube_get_option( 'wpst-options', 'turnstile-site-key' );
}

/**
 * Name of the form field Turnstile posts its token in.
 *
 * @return string
 */
function majestic_tube_captcha_token_field() {
	return 'cf-turnstile-response';
}

/**
 * Whether a spam check is active and fully configured.
 *
 * A site that switches protection on but leaves a key blank is not blocked:
 * an unconfigured check that silently rejects every visitor is worse than no
 * check at all, so the form simply prints without a widget.
 *
 * @return bool
 */
function majestic_tube_captcha_is_configured() {
	return majestic_tube_captcha_enabled() && '' !== majestic_tube_captcha_site_key() && '' !== majestic_tube_captcha_secret_key();
}

/**
 * The private Turnstile key.
 *
 * @return string
 */
function majestic_tube_captcha_secret_key() {
	return (string) majestic_tube_get_option( 'wpst-options', 'turnstile-secret-key' );
}

/**
 * Print the Turnstile widget.
 *
 * The widget is mounted by the theme's own script rather than by
 * auto-rendering, because the sign-up form lives inside a modal that is
 * hidden until a visitor opens it. A widget rendered into a hidden container
 * measures itself as zero and never recovers.
 *
 * @return void
 */
function majestic_tube_captcha_widget() {
	if ( ! majestic_tube_captcha_is_configured() ) {
		return;
	}
	?>
	<div class="cf-turnstile majestic-tube-captcha"
		data-sitekey="<?php echo esc_attr( majestic_tube_captcha_site_key() ); ?>"
		data-theme="auto"
		data-mt-turnstile="1"></div>
	<?php
}

/**
 * Print the login/register/reset modal for logged-out visitors.
 */
function majestic_tube_login_register_modal() {
	if ( is_user_logged_in() ) {
		return;
	}

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
						<?php majestic_tube_captcha_widget(); ?>
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
 * Validate a Turnstile token against Cloudflare's siteverify API.
 *
 * @param string $response cf-turnstile-response token.
 * @return bool
 */
function majestic_tube_verify_captcha( $response ) {
	$secret = majestic_tube_captcha_secret_key();

	if ( ! $secret || ! $response ) {
		return false;
	}

	$body = array(
		'secret'   => $secret,
		'response' => $response,
	);

	// Optional, but it lets Cloudflare score the request against the address
	// the token was issued to rather than trusting the token alone.
	$remote_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	if ( $remote_ip ) {
		$body['remoteip'] = $remote_ip;
	}

	$resp = wp_remote_post(
		'https://challenges.cloudflare.com/turnstile/v0/siteverify',
		array(
			'timeout' => 10,
			'body'    => $body,
		)
	);

	if ( is_wp_error( $resp ) ) {
		return false;
	}

	$result = json_decode( wp_remote_retrieve_body( $resp ) );

	return ! empty( $result->success );
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
 * @param int    $status  Optional HTTP status. 403 marks a failed security
 *                        check so the front end can recover with a fresh
 *                        nonce without parsing translated message text.
 */
function majestic_tube_ajax_error( $message, $status = 200 ) {
	wp_send_json(
		array(
			'error'   => true,
			'message' => '<div class="alert alert-danger">' . esc_html( $message ) . '</div>',
		),
		$status
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
 * Hand out fresh front-end nonces (action: majestic_tube_refresh_nonces).
 *
 * A page held by a full-page cache keeps the nonces it was rendered with, and
 * WordPress nonces expire after 12-24 hours, so on a cached site every AJAX
 * form eventually submits a dead nonce and dies on its security check. The
 * front end calls this endpoint when that happens to swap in live nonces and
 * replay the request.
 *
 * The endpoint is open to logged-out visitors on purpose: the values it
 * returns are the same public, logged-out nonces the cached page HTML already
 * carried, and admin-ajax.php sends no CORS headers, so a foreign origin
 * cannot read the response. It issues nothing that identifies a user.
 */
function majestic_tube_refresh_nonces() {
	wp_send_json(
		array(
			'ajaxNonce'  => wp_create_nonce( 'ajax-nonce' ),
			'loginNonce' => wp_create_nonce( 'ajax-login-nonce' ),
		)
	);
}
add_action( 'wp_ajax_majestic_tube_refresh_nonces', 'majestic_tube_refresh_nonces' );
add_action( 'wp_ajax_nopriv_majestic_tube_refresh_nonces', 'majestic_tube_refresh_nonces' );

/**
 * Per-IP throttle for membership endpoints.
 *
 * @param string $action login|register|reset.
 * @return bool True when the caller must back off.
 */
function majestic_tube_auth_throttled( $action ) {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'majestic_tube_auth_' . $action . '_' . md5( $ip );

	return (int) get_transient( $key ) > 10;
}

/**
 * Record one failed membership attempt.
 *
 * @param string $action login|register|reset.
 */
function majestic_tube_auth_note_failure( $action ) {
	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key   = 'majestic_tube_auth_' . $action . '_' . md5( $ip );
	$count = (int) get_transient( $key );

	set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );
}

/**
 * Handle login requests (original action: wpst_login_member).
 */
function majestic_tube_ajax_login() {
	check_ajax_referer( 'ajax-login-nonce', 'login-security', false ) || majestic_tube_ajax_error( __( 'Security check failed.', 'majestic-tube' ), 403 );

	if ( majestic_tube_auth_throttled( 'login' ) ) {
		majestic_tube_ajax_error( __( 'Too many attempts. Please try again later.', 'majestic-tube' ), 429 );
	}

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
		majestic_tube_auth_note_failure( 'login' );
		// Generic on purpose: distinct "bad user" vs "bad password"
		// strings let scanners enumerate accounts.
		majestic_tube_ajax_error( __( 'Invalid username or password.', 'majestic-tube' ) );
	}

	majestic_tube_ajax_membership_success( __( 'Login successful, reloading page...', 'majestic-tube' ) );
}
add_action( 'wp_ajax_nopriv_wpst_login_member', 'majestic_tube_ajax_login' );

/**
 * Handle registration requests (original action: wpst_register_member).
 */
function majestic_tube_ajax_register() {
	check_ajax_referer( 'ajax-login-nonce', 'register-security', false ) || majestic_tube_ajax_error( __( 'Security check failed.', 'majestic-tube' ), 403 );

	if ( majestic_tube_auth_throttled( 'register' ) ) {
		majestic_tube_ajax_error( __( 'Too many attempts. Please try again later.', 'majestic-tube' ), 429 );
	}

	if ( ! get_option( 'users_can_register' ) ) {
		majestic_tube_ajax_error( __( 'Registration is disabled.', 'majestic-tube' ) );
	}

	if ( majestic_tube_captcha_is_configured() ) {
		$field = majestic_tube_captcha_token_field();
		$token = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';

		if ( ! majestic_tube_verify_captcha( $token ) ) {
			majestic_tube_ajax_error( __( 'Captcha verification failed, please try again.', 'majestic-tube' ) );
		}
	}

	$login    = isset( $_POST['wpst_user_login'] ) ? sanitize_user( wp_unslash( $_POST['wpst_user_login'] ) ) : '';
	$email    = isset( $_POST['wpst_user_email'] ) ? sanitize_email( wp_unslash( $_POST['wpst_user_email'] ) ) : '';
	$password = isset( $_POST['wpst_user_pass'] ) ? (string) wp_unslash( $_POST['wpst_user_pass'] ) : '';

	if ( ! $login || ! $email || ! $password ) {
		majestic_tube_ajax_error( __( 'All fields are required.', 'majestic-tube' ) );
	}

	// The 8-character floor the form advertises through minlength. That
	// attribute is browser-side advice only and vanishes on any direct POST,
	// so the real check lives here.
	if ( strlen( $password ) < 8 ) {
		majestic_tube_ajax_error( __( 'The password must be at least 8 characters long.', 'majestic-tube' ) );
	}

	if ( ! is_email( $email ) ) {
		majestic_tube_ajax_error( __( 'The email address is not valid.', 'majestic-tube' ) );
	}

	// One generic message for both collisions: distinct "username taken"
	// vs "email registered" strings let scanners enumerate accounts.
	if ( username_exists( $login ) || email_exists( $email ) ) {
		majestic_tube_auth_note_failure( 'register' );
		majestic_tube_ajax_error( __( 'This account cannot be created. Try a different username or email.', 'majestic-tube' ) );
	}

	$user_id = wp_create_user( $login, $password, $email );

	if ( is_wp_error( $user_id ) ) {
		majestic_tube_auth_note_failure( 'register' );
		majestic_tube_ajax_error( __( 'This account cannot be created. Try a different username or email.', 'majestic-tube' ) );
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
	check_ajax_referer( 'ajax-login-nonce', 'password-security', false ) || majestic_tube_ajax_error( __( 'Security check failed.', 'majestic-tube' ), 403 );

	if ( majestic_tube_auth_throttled( 'reset' ) ) {
		majestic_tube_ajax_error( __( 'Too many attempts. Please try again later.', 'majestic-tube' ), 429 );
	}

	$user_or_email = isset( $_POST['wpst_user_or_email'] ) ? sanitize_text_field( wp_unslash( $_POST['wpst_user_or_email'] ) ) : '';

	if ( ! $user_or_email ) {
		majestic_tube_ajax_error( __( 'Enter a username or email address.', 'majestic-tube' ) );
	}

	/*
	 * Hand the whole flow to core instead of rebuilding it here.
	 * retrieve_password() resolves the user, honours the allow_password_reset
	 * filter (membership plugins rely on it to keep some accounts from
	 * resetting), generates the key and sends mail through the
	 * retrieve_password_message / _title / _headers filters - all of which a
	 * hand-rolled wp_mail() silently skipped.
	 */
	$result = retrieve_password( $user_or_email );

	if ( true === $result ) {
		majestic_tube_ajax_membership_success( __( 'A password reset link has been sent to your email address.', 'majestic-tube' ), 'p' );
	}

	if ( is_wp_error( $result ) && 'invalidcombo' === $result->get_error_code() ) {
		// Do not reveal whether the account exists, but retain the original
		// `{ error: false, message: HTML }` success shape.
		majestic_tube_ajax_membership_success( __( 'If an account exists, a reset link has been sent.', 'majestic-tube' ), 'p' );
	}

	if ( is_wp_error( $result ) && 'no_password_reset' === $result->get_error_code() ) {
		// A deliberate block (allow_password_reset filter): say so plainly,
		// the same way core's lost-password flow does.
		majestic_tube_ajax_error( $result->get_error_message() );
	}

	majestic_tube_auth_note_failure( 'reset' );
	majestic_tube_ajax_error( __( 'Could not create reset key. Please try again.', 'majestic-tube' ) );
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

		/*
		 * Each of the three links honours its own switch in the Visitor video
		 * submission section; before that they were printed unconditionally and
		 * the switches changed nothing. Logout is not optional and is always
		 * printed, so the sub-menu is never empty.
		 */
		if ( majestic_tube_option_is_on( 'display-video-submit-link' ) ) {
			$items .= '<li><a href="' . esc_url( home_url( '/submit-a-video' ) ) . '">' . esc_html__( 'Submit a Video', 'majestic-tube' ) . '</a></li>';
		}

		if ( majestic_tube_option_is_on( 'display-my-channel-link' ) ) {
			$items .= '<li><a href="' . esc_url( get_author_posts_url( get_current_user_id() ) ) . '">' . esc_html__( 'My Channel', 'majestic-tube' ) . '</a></li>';
		}

		if ( majestic_tube_option_is_on( 'display-my-profile-link' ) ) {
			$items .= '<li><a href="' . esc_url( home_url( '/my-profile' ) ) . '">' . esc_html__( 'My Profile', 'majestic-tube' ) . '</a></li>';
		}
		$items .= '<li><a href="' . esc_url( wp_logout_url( is_home() ? home_url() : get_permalink() ) ) . '">' . esc_html__( 'Logout', 'majestic-tube' ) . '</a></li>';
		$items .= '</ul></li>';
	} else {
		$items .= '<li><a href="#wpst-user-modal">' . esc_html__( 'Login', 'majestic-tube' ) . '</a></li>';
	}

	return $items;
}
add_filter( 'wp_nav_menu_items', 'majestic_tube_membership_nav', 10, 2 );