<?php
/**
 * Consent surfaces: the terms a visitor is asked to accept, and the cookie
 * notice.
 *
 * Four places in this theme ask a visitor to accept something rather than to
 * read something: the terms checkbox on the login, registration and video
 * submission forms, and the cookie notice. They are one subject - a question
 * put to the visitor before they continue - so they live in one module and are
 * answered by one set of helpers.
 *
 * Two rules hold for all of them.
 *
 *  1. Nothing is asked for that the site does not publish. Both the checkbox
 *     and the notice link the documents the site actually has, and a site with
 *     no terms and no privacy policy prints no question at all: a visitor
 *     cannot accept a document that does not exist, and a checkbox that asks
 *     for it is worse than no checkbox.
 *  2. They are widget-free. The page whose own body is the submission form
 *     prints none of the Code and ads areas, the way a legal page does, and
 *     the login/registration panel and the notice never contain one. An ad tag
 *     or a promo block beside a question about terms reads as the site
 *     answering a legal question with a promotion. See
 *     majestic_tube_widget_areas_withheld().
 *
 * @package Majestic Tube
 * @version 2.5.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * The form field the acceptance travels in.
 *
 * One name for every form, because one answer is being recorded. Each form
 * still prints its own element id: the login and registration forms sit in the
 * same modal at the same time, and two elements sharing an id is invalid HTML
 * that also breaks label targeting - the lesson the password fields in the
 * modal already taught.
 *
 * @return string
 */
function majestic_tube_consent_field_name() {
	/** Filter the name of the acceptance form field. */
	return (string) apply_filters( 'majestic_tube_consent_field_name', 'wpst_accept_terms' );
}

/**
 * Whether the theme asks visitors to accept the terms.
 *
 * On by default, because the theme asks a visitor for the acceptance the
 * moment it gives them something to accept - see majestic_tube_consent_is_required()
 * for the other half of that answer, which is whether there is anything to
 * accept at all. A site that would rather not put a checkbox in front of every
 * login switches this off.
 *
 * @return bool
 */
function majestic_tube_consent_ask_enabled() {
	/**
	 * Filter whether the terms checkbox is printed and enforced.
	 *
	 * @param bool $enabled Whether the theme asks for acceptance.
	 */
	return (bool) apply_filters( 'majestic_tube_consent_ask_enabled', majestic_tube_option_is_on( 'ask-to-accept-terms' ) );
}

/**
 * URL of the first published page among a list of slugs.
 *
 * Only a page a visitor can open counts. A draft or trashed copy resolves
 * through get_page_by_path() just like a published one, and linking a terms
 * page nobody can read would ask the visitor to accept a 404.
 *
 * @param string[] $slugs Candidate slugs, in preference order.
 * @return string Permalink, or an empty string when there is no such page.
 */
function majestic_tube_consent_page_url( $slugs ) {
	if ( ! function_exists( 'get_page_by_path' ) ) {
		return '';
	}

	foreach ( (array) $slugs as $slug ) {
		$page = get_page_by_path( $slug );

		if ( $page && isset( $page->ID ) && majestic_tube_page_is_viewable( $page ) ) {
			return (string) get_permalink( $page->ID );
		}
	}

	return '';
}

/**
 * The documents a visitor is asked to accept, in the order they are named.
 *
 * This is the whole list of what the theme will ask for, and it is built from
 * what the site has rather than from what the theme ships: the privacy policy
 * comes from WordPress's own setting when one is configured, and the terms and
 * cookie pages are looked up under the slugs a site ordinarily uses. A site
 * that published none of them gets an empty list, and then nothing is asked.
 *
 * @return array<string, array{label: string, url: string}>
 */
function majestic_tube_consent_documents() {
	$documents = array();

	$terms = majestic_tube_consent_page_url( array( 'terms', 'terms-of-use', 'terms-and-conditions', 'terms-of-service' ) );

	if ( $terms ) {
		$documents['terms'] = array(
			'label' => __( 'Terms of Use', 'majestic-tube' ),
			'url'   => $terms,
		);
	}

	/*
	 * WordPress owns the privacy policy: get_privacy_policy_url() answers with
	 * whichever page Settings -> Privacy points at, which is where core's own
	 * tools also link. The slug lookup is the fallback for a site whose policy
	 * predates that setting.
	 */
	$privacy = function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '';

	if ( ! $privacy ) {
		$privacy = majestic_tube_consent_page_url( array( 'privacy-policy', 'privacy' ) );
	}

	if ( $privacy ) {
		$documents['privacy-policy'] = array(
			'label' => __( 'Privacy Policy', 'majestic-tube' ),
			'url'   => $privacy,
		);
	}

	$cookie = majestic_tube_consent_page_url( array( 'cookie-policy', 'cookies' ) );

	if ( $cookie ) {
		$documents['cookie-policy'] = array(
			'label' => __( 'Cookie Policy', 'majestic-tube' ),
			'url'   => $cookie,
		);
	}

	/**
	 * Filter the documents a visitor is asked to accept.
	 *
	 * @param array<string, array{label: string, url: string}> $documents Document slug => label and URL.
	 */
	return (array) apply_filters( 'majestic_tube_consent_documents', $documents );
}

/**
 * The document links as escaped HTML, "A, B and C".
 *
 * The labels are the theme's own words and the URLs come from the site's pages,
 * so both halves are escaped here and the result is markup a caller may print.
 *
 * @return string Escaped anchor list, or an empty string when there is none.
 */
function majestic_tube_consent_policy_links() {
	$links = array();

	foreach ( majestic_tube_consent_documents() as $document ) {
		$links[] = sprintf( '<a href="%s">%s</a>', esc_url( $document['url'] ), esc_html( $document['label'] ) );
	}

	if ( ! $links ) {
		return '';
	}

	if ( 1 === count( $links ) ) {
		return $links[0];
	}

	$last = array_pop( $links );

	return implode( ', ', $links ) . ' ' . __( 'and', 'majestic-tube' ) . ' ' . $last;
}

/**
 * Whether the acceptance is being requested on this site.
 *
 * Both halves have to be true: the theme has to be switched on, and the site
 * has to publish at least one document to accept. The second half is what keeps
 * a checkbox off a form nobody can complete.
 *
 * @return bool
 */
function majestic_tube_consent_is_required() {
	if ( ! majestic_tube_consent_ask_enabled() ) {
		return false;
	}

	return '' !== majestic_tube_consent_policy_links();
}

/**
 * Print the terms checkbox, when there is something to accept.
 *
 * @param string $context Form the field belongs to, for a unique element id:
 *                        register, login or submit.
 * @return void
 */
function majestic_tube_consent_field( $context ) {
	/*
	 * The same question the handlers ask. A checkbox that is printed but never
	 * enforced is the worst of both: it asks the visitor to agree and does
	 * nothing with the answer.
	 */
	if ( ! majestic_tube_consent_is_required() ) {
		return;
	}

	$links = majestic_tube_consent_policy_links();

	$id = 'wpst-accept-terms-' . sanitize_html_class( $context );
	?>
	<div class="form-field majestic-tube-consent-field">
		<label class="majestic-tube-consent-label">
			<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( majestic_tube_consent_field_name() ); ?>" value="1" required />
			<span>
				<?php
				printf(
					/* translators: %s: links to the documents being accepted. */
					esc_html__( 'I have read and accept the %s.', 'majestic-tube' ),
					$links // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in majestic_tube_consent_policy_links().
				);
				?>
			</span>
		</label>
	</div>
	<?php
}

/**
 * Whether the acceptance was actually given on this request.
 *
 * The checkbox is also marked required, so a browser blocks the form first.
 * That attribute is advice, not a rule: it disappears on any request that was
 * not built by the form, which is why every handler asks here instead of
 * trusting the markup.
 *
 * @return bool
 */
function majestic_tube_consent_accepted() {
	$name = majestic_tube_consent_field_name();
	$raw  = isset( $_POST[ $name ] ) ? $_POST[ $name ] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the form's own nonce is verified by the handler; this only reads a checkbox.

	if ( ! is_string( $raw ) ) {
		return false;
	}

	return '' !== trim( sanitize_text_field( wp_unslash( $raw ) ) );
}

/**
 * Whether the request may continue: required means accepted means allowed.
 *
 * A site that asks for nothing passes trivially, so every handler can call
 * this one function without first deciding whether the question applies.
 *
 * @return bool
 */
function majestic_tube_consent_satisfied() {
	if ( ! majestic_tube_consent_is_required() ) {
		return true;
	}

	return majestic_tube_consent_accepted();
}

/**
 * The message a handler returns when the acceptance is missing.
 *
 * @return string
 */
function majestic_tube_consent_error_message() {
	return __( 'Please accept the terms to continue.', 'majestic-tube' );
}

/**
 * Whether the video submission form is being shown on this request.
 *
 * The form prints only while the feature is on and the visitor is signed in.
 * Both facts live in one function because three callers now need the same
 * answer - the template that prints the form, the template that decides
 * whether to create the post, and the guard that holds the widget areas - and
 * three copies of one condition is how a page ends up showing a form the rest
 * of the theme believes is not there.
 *
 * @return bool
 */
function majestic_tube_video_submission_form_is_shown() {
	if ( ! majestic_tube_option_is_on( 'enable-video-submission' ) ) {
		return false;
	}

	return function_exists( 'is_user_logged_in' ) && is_user_logged_in();
}

/**
 * Whether a page renders the theme's own submission form.
 *
 * Matched on the page template first, then on the slug the setup creates, so a
 * page that lost its template assignment - which the theme repairs on the next
 * admin request - is still recognized as the submission page rather than
 * quietly regaining its ad areas.
 *
 * @param int $post_id Page ID.
 * @return bool
 */
function majestic_tube_is_video_submission_page( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : ( function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0 );

	if ( ! $post_id ) {
		return false;
	}

	$template = function_exists( 'get_post_meta' ) ? (string) get_post_meta( $post_id, '_wp_page_template', true ) : '';

	if ( 'template-video-submit.php' === $template ) {
		return true;
	}

	if ( function_exists( 'is_page_template' ) && is_page_template( 'template-video-submit.php' ) ) {
		return true;
	}

	$post = function_exists( 'get_post' ) ? get_post( $post_id ) : null;

	if ( ! $post || ! isset( $post->post_name ) ) {
		return false;
	}

	if ( isset( $post->post_type ) && 'page' !== $post->post_type ) {
		return false;
	}

	return 'submit-a-video' === (string) $post->post_name;
}

/**
 * Whether this request is a page whose own body asks the visitor to accept
 * something.
 *
 * The submission form is one: the visitor cannot send the video without
 * ticking the checkbox, so the page is the question.
 *
 * The answer is deliberately independent of what happens after the POST. A
 * guard that changed its mind between the GET that printed the form and the
 * POST that followed would give one page two different shapes, and the areas
 * the visitor never saw on the form would appear on the reply - which is
 * exactly where an ad tag beside a confirmation reads worst.
 *
 * @return bool
 */
function majestic_tube_is_consent_page() {
	if ( ! majestic_tube_consent_is_required() ) {
		return false;
	}

	if ( ! majestic_tube_is_video_submission_page() ) {
		return false;
	}

	return majestic_tube_video_submission_form_is_shown();
}

/**
 * Whether this request prints none of the theme's widget areas.
 *
 * Two kinds of request qualify, for the same reason. A legal page is a document
 * a visitor reads on its own terms. A consent page is a question they answer on
 * its own terms. Administrator markup - an ad tag, a network script, a promo
 * banner - placed in either one reads as the site's own wording or its own
 * answer, so the areas are withheld rather than hidden and nothing from them
 * reaches the page. Everything the theme prints itself (header, menus, footer
 * legal links, copyright) is untouched.
 *
 * @return bool
 */
function majestic_tube_widget_areas_withheld() {
	$withheld = majestic_tube_is_legal_page() || majestic_tube_is_consent_page();

	/**
	 * Filter whether the widget areas are withheld on this request.
	 *
	 * @param bool $withheld Whether every content area and the footer widgets
	 *                       columns are withheld.
	 */
	return (bool) apply_filters( 'majestic_tube_widget_areas_withheld', $withheld );
}

/**
 * Whether the site shows a cookie notice.
 *
 * Off by default. A notice is the site telling its visitors what it does with
 * cookies; whether it ships one is the operator's decision, not the theme's,
 * and a notice that appeared on an upgrade would be a change to the site's
 * public copy nobody asked for.
 *
 * @return bool
 */
function majestic_tube_cookie_notice_enabled() {
	/**
	 * Filter whether the cookie notice is printed.
	 *
	 * @param bool $enabled Whether the notice prints.
	 */
	return (bool) apply_filters( 'majestic_tube_cookie_notice_enabled', majestic_tube_option_is_on( 'cookie-notice' ) );
}

/**
 * The cookie notice copy, exactly as the administrator wrote it.
 *
 * @return string
 */
function majestic_tube_cookie_notice_text() {
	$text = majestic_tube_get_option( 'wpst-options', 'cookie-notice-text', '' );

	return is_string( $text ) ? trim( $text ) : '';
}

/**
 * Print the cookie notice.
 *
 * The notice is printed hidden and revealed by the theme's own script, which
 * also stores the dismissal in the visitor's browser - the same way the color
 * scheme choice is kept. The theme therefore records a cookie notice choice
 * without setting a cookie, and a returning visitor never sees the bar flash.
 *
 * It carries no widget area, and no placement helper is called from here: the
 * panel is the theme's own sentence, the documents it links and one button.
 * The document links are printed whatever the copy says, so a site that writes
 * its own wording still links the pages the notice refers to.
 *
 * @return void
 */
function majestic_tube_cookie_notice() {
	if ( ! majestic_tube_cookie_notice_enabled() ) {
		return;
	}

	$text  = majestic_tube_cookie_notice_text();
	$links = majestic_tube_consent_documents();
	?>
	<div id="majestic-tube-cookie-notice" class="majestic-tube-cookie-notice" role="region" aria-label="<?php esc_attr_e( 'Cookie notice', 'majestic-tube' ); ?>" hidden>
		<p class="majestic-tube-cookie-notice__text">
			<?php
			if ( '' !== $text ) {
				echo wp_kses_post( $text );
			} else {
				esc_html_e( 'This site uses cookies to keep it working and to measure what is used.', 'majestic-tube' );
			}
			?>
		</p>

		<p class="majestic-tube-cookie-notice__actions">
			<button type="button" class="majestic-tube-cookie-notice__dismiss" data-majestic-tube-cookie-notice-dismiss>
				<?php esc_html_e( 'Got it', 'majestic-tube' ); ?>
			</button>

			<?php foreach ( $links as $document ) : ?>
				<a class="majestic-tube-cookie-notice__link" href="<?php echo esc_url( $document['url'] ); ?>"><?php echo esc_html( $document['label'] ); ?></a>
			<?php endforeach; ?>
		</p>
	</div>
	<?php
}
/*
 * Priority 9, so the notice is in the document before the login/registration
 * modal that shares the footer. The modal is the higher overlay of the two and
 * covers the notice while it is open, which is what makes them safe to print
 * together.
 */
add_action( 'wp_footer', 'majestic_tube_cookie_notice', 9 );
