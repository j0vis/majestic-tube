<?php
/**
 * Theme activation routine.
 *
 * Creates the core pages (with the right page templates), built-in legal
 * pages, a default main menu, footer legal links, and redirects to a welcome
 * screen.
 *
 * @package Majestic Tube
 * @version 2.0.8
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pages created on activation: title => page template file.
 *
 * @return array<string, string>
 */
function majestic_tube_activation_pages() {
	return array(
		'Submit a Video' => 'template-video-submit.php',
		'Profile'        => 'template-my-profile.php',
		'Actors'         => 'template-actors.php',
		'Categories'     => 'template-categories.php',
		'Tags'           => 'template-tags.php',
	);
}

/**
 * Return the site's default WordPress administrative email.
 *
 * Theme contact integrations should use this address instead of storing a
 * second recipient in a page, widget, or form setting.
 *
 * @return string Valid email address, or an empty string when unavailable.
 */
function majestic_tube_default_email() {
	$email = get_option( 'admin_email' );

	return is_email( $email ) ? sanitize_email( $email ) : '';
}

/**
 * Render the default email as shortcode text.
 *
 * @return string
 */
function majestic_tube_default_email_shortcode() {
	$email = majestic_tube_default_email();

	return $email ? esc_html( $email ) : '';
}

/**
 * Render the default email as a safe mail link for built-in legal pages.
 *
 * @return string
 */
function majestic_tube_default_email_link_shortcode() {
	$email = majestic_tube_default_email();

	if ( ! $email ) {
		return esc_html__( 'the site administrator', 'majestic-tube' );
	}

	return '<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>';
}
add_shortcode( 'majestic_tube_default_email', 'majestic_tube_default_email_shortcode' );
add_shortcode( 'majestic_tube_default_email_link', 'majestic_tube_default_email_link_shortcode' );

/**
 * Send a theme contact message to the default WordPress administrative email.
 *
 * @param string $subject     Message subject.
 * @param string $message     Message body.
 * @param array  $headers     Optional mail headers.
 * @param array  $attachments Optional attachment paths.
 * @return bool
 */
function majestic_tube_send_contact_email( $subject, $message, $headers = array(), $attachments = array() ) {
	$email = majestic_tube_default_email();

	if ( ! $email ) {
		return false;
	}

	return wp_mail(
		$email,
		sanitize_text_field( $subject ),
		wp_kses_post( $message ),
		$headers,
		$attachments
	);
}

/**
 * Built-in legal and policy pages created on activation.
 *
 * The copy is deliberately a practical starting point rather than legal
 * advice. Placeholder fields remain visible so the site operator can supply
 * the correct records custodian, notice agent, and privacy contact before
 * publishing the site.
 *
 * @return array<string, array{slug: string, legacy_slug?: string, content: string}>
 */
function majestic_tube_legal_pages() {
	return array(
		'18 USC 2257' => array(
			'slug'        => '2257',
			'legacy_slug' => '18-usc-2257',
			'content'     => <<<'HTML'
<h2>18 U.S.C. § 2257</h2>
<p>This site is operated by <strong>[Site Name]</strong>. The operator is responsible for maintaining the records required by 18 U.S.C. § 2257 and its implementing regulations for any visual depiction of a real person where applicable.</p>
<h3>Records custodian</h3>
<ul>
<li>Name and title: [Records custodian]</li>
<li>Postal address: [Street address, city, region, postal code, country]</li>
<li>Telephone: [Telephone number]</li>
<li>Email: [majestic_tube_default_email]</li>
</ul>
<h3>Visitor responsibilities</h3>
<p>Visitors must not upload, publish, or distribute content that violates applicable law. The operator may remove content and may cooperate with lawful requests concerning records, identification, or reported content.</p>
<h3>Contact</h3>
<p>Questions about records or this notice may be sent to [majestic_tube_default_email_link].</p>
<hr />
<p><strong>Template notice:</strong> This page is a starting template and does not certify compliance. The site operator is responsible for reviewing the policy against the site’s actual content, hosting, age-verification, and recordkeeping practices and for obtaining legal advice where needed.</p>
HTML,
		),
		'DMCA' => array(
			'slug'    => 'dmca',
			'content' => <<<'HTML'
<h2>DMCA Copyright Policy</h2>
<p><strong>[Site Name]</strong> respects intellectual-property rights and expects users to submit valid takedown notices. This starter policy must be updated with the site operator’s correct legal name, mailing address, designated-agent details, and contact email before use.</p>
<h3>Submitting a notice</h3>
<p>Send a notice to [majestic_tube_default_email_link] with the following information:</p>
<ol>
<li>Your physical or electronic signature.</li>
<li>Identification of the copyrighted work you claim has been infringed.</li>
<li>The URL or other precise information needed to locate the material.</li>
<li>Your name, address, telephone number, and email address.</li>
<li>A statement that you have a good-faith belief that the use is not authorized by the copyright owner, its agent, or the law.</li>
<li>A statement, under penalty of perjury, that the information in the notice is accurate and that you are the owner or authorized to act on the owner’s behalf.</li>
</ol>
<h3>Response and repeat infringers</h3>
<p>The operator may review and remove alleged infringing material and may restrict access to repeat infringers. A notice does not guarantee removal. The operator may forward a valid notice to the person who posted the material and may disclose necessary information to comply with law.</p>
<p><strong>Template notice:</strong> This page is a general starting point, not legal advice or a substitute for a review of the site’s actual content and hosting practices.</p>
HTML,
		),
		'Privacy Policy' => array(
			'slug'    => 'privacy-policy',
			'content' => <<<'HTML'
<h2>Privacy Policy</h2>
<p><strong>Last updated:</strong> [Month DD, YYYY]</p>
<p><strong>[Site Name]</strong> (“we,” “us,” or “our”) provides this site and related membership, submission, and video features. This starter policy describes the categories of information that commonly require review. The operator must adapt it to the site’s actual analytics, advertising, hosting, payment, and account providers before publishing.</p>
<h3>Information you provide</h3>
<p>When you submit a video, create an account, contact us, or report content, you may provide an email address, display name, profile details, messages, and other information you choose to submit. Please do not submit sensitive information unless it is necessary for the requested feature.</p>
<h3>Information collected automatically</h3>
<p>The site and its service providers may record IP addresses, browser and device information, pages requested, timestamps, referral information, video playback events, and similar technical data for security, delivery, analytics, and troubleshooting. Cookies or similar technologies may be used where permitted by applicable law and the site’s configuration.</p>
<h3>How information is used</h3>
<p>Information may be used to operate and secure the site, process submissions and accounts, personalize features, measure performance, prevent abuse, communicate with users, and comply with legal obligations. Information is not sold as a general rule, but it may be shared with service providers, professional advisers, authorities, or a successor when necessary for those purposes.</p>
<h3>Retention, choices, and rights</h3>
<p>Information is kept only as long as reasonably necessary for the purposes described here, including legal, security, and dispute requirements. Depending on your location, you may have rights to access, correct, delete, restrict, or object to processing, or to withdraw consent. You may also opt out of non-essential cookies through available browser or site controls.</p>
<h3>Children and changes</h3>
<p>The site is not directed to children under 13, and we do not knowingly collect personal information from them. We may update this policy when the site’s practices or legal requirements change. Material changes will be posted on this page with an updated date.</p>
<h3>Contact</h3>
<p>Privacy questions and requests may be sent to [majestic_tube_default_email_link].</p>
<hr />
<p><strong>Template notice:</strong> This page is a starting point, not legal advice. The operator is responsible for documenting the site’s actual data practices, vendors, retention periods, legal bases, and region-specific rights before relying on it.</p>
HTML,
		),
	);
}

/**
 * Resolve a built-in legal page, honoring WordPress's configured privacy page.
 *
 * @param array $definition Legal page definition.
 * @return object|false Page object, or false when unavailable.
 */
function majestic_tube_get_legal_page( $definition ) {
	if ( ! is_array( $definition ) || empty( $definition['slug'] ) ) {
		return false;
	}

	// WordPress can point the privacy-policy setting at a custom page. Reuse
	// that page instead of creating a second privacy document.
	if ( 'privacy-policy' === $definition['slug'] ) {
		$configured_id = absint( get_option( 'wp_page_for_privacy_policy' ) );

		if ( $configured_id ) {
			$configured_page = get_post( $configured_id );

			if ( $configured_page && isset( $configured_page->ID ) && ( ! isset( $configured_page->post_type ) || 'page' === $configured_page->post_type ) ) {
				return $configured_page;
			}
		}
	}

	return get_page_by_path( $definition['slug'] );
}

/**
 * Move the original 2257 page to the canonical slug when it is the only page.
 *
 * WordPress records the previous slug in `_wp_old_slug` when wp_update_post()
 * changes a published page name. The explicit redirect below also covers sites
 * where a second page already occupies the canonical slug.
 *
 * @param array $definition Legal page definition.
 * @return object|false Canonical page object, or false when unavailable.
 */
function majestic_tube_migrate_2257_page( $definition ) {
	$canonical = majestic_tube_get_legal_page( $definition );
	$legacy_slug = ! empty( $definition['legacy_slug'] ) ? $definition['legacy_slug'] : '';
	$legacy      = $legacy_slug ? get_page_by_path( $legacy_slug ) : false;

	if ( ! $legacy || ! isset( $legacy->ID ) ) {
		return $canonical;
	}

	$legacy_id = (int) $legacy->ID;

	if ( ! $canonical && function_exists( 'wp_update_post' ) ) {
		// Supplying only the name preserves the page ID and all administrator
		// edits to the title, content, status, and metadata.
		$updated = wp_update_post(
			array(
				'ID'        => $legacy_id,
				'post_name' => $definition['slug'],
			),
			true
		);

		if ( ! is_wp_error( $updated ) && $updated ) {
			$canonical = get_page_by_path( $definition['slug'] );
		}
	}

	if ( $canonical && isset( $canonical->ID ) && $legacy_id !== (int) $canonical->ID && function_exists( 'update_post_meta' ) ) {
		// This marker is useful when a site has both slugs. The request-level
		// redirect remains the source of truth, so editing the old page is safe.
		update_post_meta( $legacy_id, '_majestic_tube_legal_redirect', $definition['slug'] );
	}

	return $canonical;
}

/**
 * Get or create one built-in legal page, preserving existing page content.
 *
 * @param string $title      Page title/menu label.
 * @param array  $definition Legal page definition.
 * @return int Page ID, or 0 on failure.
 */
function majestic_tube_get_or_create_legal_page( $title, $definition ) {
	$page = ! empty( $definition['legacy_slug'] )
		? majestic_tube_migrate_2257_page( $definition )
		: majestic_tube_get_legal_page( $definition );

	if ( $page && isset( $page->ID ) ) {
		$page_id = (int) $page->ID;
	} else {
		$page_id = majestic_tube_create_page( $title, '', $definition['content'], $definition['slug'] );
	}

	if ( $page_id ) {
		majestic_tube_migrate_legal_page_content( $page_id );
	}

	return $page_id;
}

/**
 * Replace only the legacy theme contact placeholders in an existing page.
 * Administrator-authored wording and all unrelated content are left intact.
 * A render-time filter below adds the dynamic contact line when an edited page
 * has no theme contact shortcode at all.
 *
 * @param int $page_id Page ID.
 * @return void
 */
function majestic_tube_migrate_legal_page_content( $page_id ) {
	$page = get_post( $page_id );

	if ( ! $page || ! isset( $page->post_content ) || ! is_string( $page->post_content ) ) {
		return;
	}

	$updated = str_replace(
		array( '[records-email]', '[dmca-email]', '[privacy-email]' ),
		'[majestic_tube_default_email_link]',
		$page->post_content
	);

	if ( $updated !== $page->post_content && function_exists( 'wp_update_post' ) ) {
		wp_update_post(
			array(
				'ID'           => (int) $page_id,
				'post_content' => $updated,
			),
			true
		);
	}
}

/**
 * Decide whether a page is a built-in or commonly used legal document.
 *
 * @param object $post Page/post object.
 * @return bool
 */
function majestic_tube_is_legal_document( $post ) {
	if ( ! is_object( $post ) || ( isset( $post->post_type ) && 'page' !== $post->post_type ) ) {
		return false;
	}

	$post_id = isset( $post->ID ) ? (int) $post->ID : 0;
	$privacy_id = absint( get_option( 'wp_page_for_privacy_policy' ) );

	if ( $post_id && $post_id === $privacy_id ) {
		return true;
	}

	$slug = isset( $post->post_name ) ? strtolower( trim( (string) $post->post_name, '/' ) ) : '';
	$legal_slugs = array(
		'2257',
		'18-usc-2257',
		'dmca',
		'privacy-policy',
		'terms',
		'terms-of-use',
		'terms-and-conditions',
		'cookie-policy',
		'legal-notice',
		'acceptable-use',
		'acceptable-use-policy',
		'refund-policy',
		'returns-policy',
		'disclaimer',
		'compliance',
	);

	if ( in_array( $slug, $legal_slugs, true ) ) {
		return true;
	}

	$title = isset( $post->post_title ) ? strtolower( (string) $post->post_title ) : '';

	return (bool) preg_match( '/\b(privacy|dmca|2257|terms|legal|cookie|acceptable|refund|returns|disclaimer|compliance)\b/i', $title );
}

/**
 * Ensure an edited legal page still exposes the current WordPress admin email.
 * This is a front-end safety net; it does not write over page content.
 *
 * @param string $content Post content.
 * @return string
 */
function majestic_tube_filter_legal_document_contact( $content ) {
	if ( ! is_string( $content ) ) {
		return $content;
	}

	$post_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;

	if ( ! $post_id && isset( $GLOBALS['post'] ) && is_object( $GLOBALS['post'] ) ) {
		$post_id = isset( $GLOBALS['post']->ID ) ? (int) $GLOBALS['post']->ID : 0;
	}

	$post = $post_id ? get_post( $post_id ) : false;

	if ( ! majestic_tube_is_legal_document( $post ) ) {
		return $content;
	}

	$content = str_replace(
		array( '[records-email]', '[dmca-email]', '[privacy-email]' ),
		'[majestic_tube_default_email_link]',
		$content
	);

	if ( false !== strpos( $content, '[majestic_tube_default_email' ) ) {
		return $content;
	}

	return $content . '<p class="majestic-tube-legal-contact"><strong>' .
		esc_html__( 'Site contact', 'majestic-tube' ) . ':</strong> [majestic_tube_default_email_link]</p>';
}
// Run before core's do_shortcode() pass so the appended/replaced contact
// shortcode is expanded in the same request.
add_filter( 'the_content', 'majestic_tube_filter_legal_document_contact', 9 );

/**
 * Return the canonical destination for the retired 2257 URL.
 *
 * @return string Canonical permalink, or an empty string when not applicable.
 */
function majestic_tube_legacy_2257_redirect_url() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$legacy_request = false;

	if ( is_string( $request_uri ) && '' !== $request_uri ) {
		$path = wp_parse_url( $request_uri, PHP_URL_PATH );
		$legacy_request = is_string( $path ) && (bool) preg_match( '#(?:^|/)18-usc-2257/?$#', $path );
	}

	// Also cover plain-permalink requests such as ?page_id=123.
	if ( ! $legacy_request && function_exists( 'get_query_var' ) ) {
		$requested_id = absint( get_query_var( 'page_id' ) );
		$requested    = $requested_id ? get_post( $requested_id ) : false;

		if ( $requested && isset( $requested->post_name ) && '18-usc-2257' === $requested->post_name ) {
			$legacy_request = true;
		}
	}

	if ( ! $legacy_request ) {
		return '';
	}

	$canonical = get_page_by_path( '2257' );

	if ( ! $canonical || ! isset( $canonical->ID ) || ! function_exists( 'get_permalink' ) ) {
		return '';
	}

	return (string) get_permalink( $canonical );
}

/**
 * Preserve /18-usc-2257/ as a permanent alias for /2257/.
 *
 * @return void
 */
function majestic_tube_legacy_2257_redirect() {
	if ( ( function_exists( 'is_admin' ) && is_admin() ) || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
		return;
	}

	$target = majestic_tube_legacy_2257_redirect_url();

	if ( ! $target || ! function_exists( 'wp_safe_redirect' ) ) {
		return;
	}

	wp_safe_redirect( $target, 301 );
	exit;
}
add_action( 'template_redirect', 'majestic_tube_legacy_2257_redirect', 1 );

/**
 * Create a page if it does not already exist.
 *
 * @param string $title     Page title.
 * @param string $template  Template filename (may be empty).
 * @param string $content   Initial page content for a new page.
 * @param string $slug      Optional explicit page slug.
 * @return int Page ID (0 on failure).
 */
function majestic_tube_create_page( $title, $template = '', $content = '', $slug = '' ) {
	$page_slug = $slug ? sanitize_title( $slug ) : sanitize_title( $title );
	$existing  = get_page_by_path( $page_slug );

	if ( $existing && isset( $existing->ID ) ) {
		$page_id = (int) $existing->ID;
	} else {
		$page_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => $page_slug,
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => get_current_user_id(),
			)
		);

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return 0;
		}
	}

	if ( $template ) {
		update_post_meta( $page_id, '_wp_page_template', $template );
	}

	return $page_id;
}

/**
 * Create missing pages and assign page templates on activation.
 * Existing pages are reused without overwriting administrator-edited content.
 */
function majestic_tube_create_initial_pages() {
	$created = array();

	foreach ( majestic_tube_activation_pages() as $title => $template ) {
		$page_id = majestic_tube_create_page( $title, $template );

		if ( $page_id ) {
			$created[ $title ] = $page_id;
		}
	}

	foreach ( majestic_tube_legal_pages() as $title => $page ) {
		$page_id = majestic_tube_get_or_create_legal_page( $title, $page );

		if ( $page_id ) {
			$created[ $title ] = $page_id;
		}
	}

	update_option( 'majestic_tube_created_pages', array_keys( $created ) );
}
add_action( 'after_switch_theme', 'majestic_tube_create_initial_pages' );

/**
 * Run the built-in page/menu setup once for new installs and theme updates.
 *
 * This covers an existing active site receiving an updated theme package,
 * where WordPress does not fire after_switch_theme. All operations are
 * idempotent, so the normal activation callbacks remain safe to run too.
 *
 * @return void
 */
function majestic_tube_maybe_setup_legal_pages() {
	if ( 2 <= (int) get_option( 'majestic_tube_legal_setup_version', 0 ) ) {
		return;
	}

	majestic_tube_create_initial_pages();
	majestic_tube_create_default_menu();
	update_option( 'majestic_tube_legal_setup_version', 2 );
}
add_action( 'after_setup_theme', 'majestic_tube_maybe_setup_legal_pages', 15 );

/**
 * Get a menu by name, creating it when necessary.
 *
 * @param string $name Menu name.
 * @return int Menu term ID, or 0 on failure.
 */
function majestic_tube_get_or_create_nav_menu( $name ) {
	$menu = wp_get_nav_menu_object( $name );

	if ( is_wp_error( $menu ) || ! $menu ) {
		$menu_id = wp_create_nav_menu( $name );

		if ( is_wp_error( $menu_id ) || ! $menu_id ) {
			return 0;
		}

		return (int) $menu_id;
	}

	return isset( $menu->term_id ) ? (int) $menu->term_id : 0;
}

/**
 * Add or update one page item in a navigation menu.
 *
 * @param int    $menu_id Menu term ID.
 * @param string $title   Menu item title.
 * @param int    $page_id Page ID.
 * @param int    $item_id Existing menu item ID, or 0 for a new item.
 * @return int|false
 */
function majestic_tube_update_page_menu_item( $menu_id, $title, $page_id, $item_id = 0 ) {
	return wp_update_nav_menu_item(
		(int) $menu_id,
		(int) $item_id,
		array(
			'menu-item-title'     => $title,
			'menu-item-object'    => 'page',
			'menu-item-object-id' => (int) $page_id,
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
		)
	);
}

/**
 * Add each built-in legal page to a menu once.
 *
 * @param int $menu_id Menu term ID.
 * @return void
 */
function majestic_tube_add_legal_pages_to_menu( $menu_id ) {
	$items = wp_get_nav_menu_items( $menu_id );
	$items = is_array( $items ) ? $items : array();
	$have_ids = array();
	$legacy_ids = array();
	$canonical_ids = array();

	// Resolve canonical pages first, including a custom WordPress privacy page.
	foreach ( majestic_tube_legal_pages() as $title => $legal_page ) {
		$page = majestic_tube_get_legal_page( $legal_page );

		if ( ! $page || ! isset( $page->ID ) ) {
			continue;
		}

		$canonical_id = (int) $page->ID;
		$canonical_ids[ $canonical_id ] = $title;

		if ( ! empty( $legal_page['legacy_slug'] ) ) {
			$legacy_page = get_page_by_path( $legal_page['legacy_slug'] );

			if ( $legacy_page && isset( $legacy_page->ID ) && $canonical_id !== (int) $legacy_page->ID ) {
				$legacy_ids[ (int) $legacy_page->ID ] = array(
					'canonical_id' => $canonical_id,
					'title'        => $title,
				);
			}
		}
	}

	// Migrate an old 2257 menu item to the canonical page, and remove only
	// duplicate legacy items. Custom titles on the surviving item are retained.
	foreach ( $items as $item ) {
		if ( ! isset( $item->type, $item->object_id ) || 'post_type' !== $item->type ) {
			continue;
		}

		$item_id       = (int) $item->object_id;
		$canonical_map = isset( $legacy_ids[ $item_id ] ) ? $legacy_ids[ $item_id ] : null;

		if ( $canonical_map ) {
			$menu_item_id = isset( $item->ID ) ? (int) $item->ID : 0;

			if ( in_array( (int) $canonical_map['canonical_id'], $have_ids, true ) ) {
				if ( $menu_item_id && function_exists( 'wp_delete_nav_menu_item' ) ) {
					wp_delete_nav_menu_item( (int) $menu_id, $menu_item_id );
				}
				continue;				}

				if ( $menu_item_id && function_exists( 'wp_update_nav_menu_item' ) ) {
					$updated = majestic_tube_update_page_menu_item(
						$menu_id,
						isset( $item->title ) && '' !== $item->title ? $item->title : $canonical_map['title'],
						$canonical_map['canonical_id'],
						$menu_item_id
					);

					if ( ! is_wp_error( $updated ) ) {
						$have_ids[] = (int) $canonical_map['canonical_id'];
					}
				}

			continue;
		}

		// Keep the first canonical item and discard a second one if both the
		// old and new slugs were already present in a hand-edited menu.
		if ( isset( $canonical_ids[ $item_id ] ) && in_array( $item_id, $have_ids, true ) ) {
			$menu_item_id = isset( $item->ID ) ? (int) $item->ID : 0;

			if ( $menu_item_id && function_exists( 'wp_delete_nav_menu_item' ) ) {
				wp_delete_nav_menu_item( (int) $menu_id, $menu_item_id );
			}
			continue;
		}

		$have_ids[] = $item_id;
	}

	foreach ( majestic_tube_legal_pages() as $title => $legal_page ) {
		$page = majestic_tube_get_legal_page( $legal_page );

		if ( ! $page || ! isset( $page->ID ) || in_array( (int) $page->ID, $have_ids, true ) ) {
			continue;
		}

		majestic_tube_update_page_menu_item( $menu_id, $title, $page->ID );

		$have_ids[] = (int) $page->ID;
	}
}

/**
 * Prepare the footer menu without adding legal links to the primary menu.
 *
 * Existing custom footer menus are preserved. When the current footer slot
 * points at the primary menu (the theme's former default), a separate Footer
 * Legal Menu is created so legal links do not appear in the site header.
 *
 * @param int   $main_menu_id Primary menu term ID.
 * @param array $locations    Existing menu-location assignments.
 * @return int Footer menu term ID, or 0 on failure.
 */
function majestic_tube_prepare_footer_legal_menu( $main_menu_id, $locations ) {
	$locations       = is_array( $locations ) ? $locations : array();
	$main_menu_id    = absint( $main_menu_id );
	$current_menu_id = isset( $locations['majestic_tube_footer_menu'] ) ? absint( $locations['majestic_tube_footer_menu'] ) : 0;
	$footer_menu     = $current_menu_id ? wp_get_nav_menu_object( $current_menu_id ) : false;

	if ( is_wp_error( $footer_menu ) || ! $footer_menu || $current_menu_id === $main_menu_id ) {
		$footer_menu_id = majestic_tube_get_or_create_nav_menu( 'Footer Legal Menu' );
	} else {
		$footer_menu_id = $current_menu_id;
	}

	if ( ! $footer_menu_id ) {
		return 0;
	}

	majestic_tube_add_legal_pages_to_menu( $footer_menu_id );
	$locations['majestic_tube_footer_menu'] = (int) $footer_menu_id;

	return (int) $footer_menu_id;
}

/**
 * Create the default main menu, populate it, and assign locations.
 */
function majestic_tube_create_default_menu() {
	$menu_id = majestic_tube_get_or_create_nav_menu( 'Main Menu' );

	if ( ! $menu_id ) {
		return;
	}

	// Existing menu items, so re-activating the theme never duplicates links.
	$existing = wp_get_nav_menu_items( $menu_id );
	$existing = is_array( $existing ) ? $existing : array();

	$have_home = false;
	$have_ids  = array();

	foreach ( $existing as $item ) {
		if ( '' === $item->url || home_url( '/' ) === $item->url ) {
			$have_home = true;
		}

		if ( 'post_type' === $item->type ) {
			$have_ids[] = (int) $item->object_id;
		}
	}

	if ( ! $have_home ) {
		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'  => __( 'Home', 'majestic-tube' ),
				'menu-item-url'    => home_url( '/' ),
				'menu-item-status' => 'publish',
			)
		);
	}

	// Match KingTube's default top-level navigation. Submit-a-video and account
	// links live in the membership dropdown, not in the primary navigation.
	foreach ( majestic_tube_directory_pages() as $title => $path ) {
		$page = get_page_by_path( $path );

		if ( ! $page || ! isset( $page->ID ) || in_array( (int) $page->ID, $have_ids, true ) ) {
			continue;
		}

		majestic_tube_update_page_menu_item( $menu_id, $title, $page->ID );
	}

	// Keep legal links out of the primary navigation. Existing custom footer
	// menus are retained; a separate footer menu is used when the old shared
	// Main Menu assignment is found.
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	$locations = is_array( $locations ) ? $locations : array();
	$locations['majestic_tube_main_menu'] = (int) $menu_id;
	$footer_menu_id                        = majestic_tube_prepare_footer_legal_menu( $menu_id, $locations );

	if ( $footer_menu_id ) {
		$locations['majestic_tube_footer_menu'] = $footer_menu_id;
	}

	set_theme_mod( 'nav_menu_locations', $locations );

	update_option( 'majestic_tube_menu_created', true );
}
add_action( 'after_switch_theme', 'majestic_tube_create_default_menu', 20 );

/**
 * Carry the original footer widget assignment over to this theme.
 *
 * The old homepage widget area is intentionally not migrated: it represented
 * an inline homepage block in KingTube, not a page sidebar.
 */
function majestic_tube_migrate_widgets() {
	$sidebars = get_option( 'sidebars_widgets' );

	if ( ! is_array( $sidebars ) || empty( $sidebars['footer'] ) || ! is_array( $sidebars['footer'] ) ) {
		return;
	}

	if ( ! empty( $sidebars['majestic-tube-footer'] ) ) {
		return;
	}

	$sidebars['majestic-tube-footer'] = $sidebars['footer'];
	$sidebars['footer']               = array();
	update_option( 'sidebars_widgets', $sidebars );
}
add_action( 'after_switch_theme', 'majestic_tube_migrate_widgets', 25 );

/**
 * Flush rewrite rules after pages/CPT setup on activation.
 */
function majestic_tube_activation_flush() {
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'majestic_tube_activation_flush', 30 );

/**
 * Redirect to the welcome screen after activation.
 */
function majestic_tube_activation_redirect() {
	global $pagenow;

	if ( 'themes.php' === $pagenow && isset( $_GET['activated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check on core screen.
		wp_safe_redirect( admin_url( 'themes.php?page=majestic-tube-welcome' ) );
		exit;
	}
}
add_action( 'admin_init', 'majestic_tube_activation_redirect' );

/**
 * Register the welcome screen (informational only, no data changes).
 */
function majestic_tube_welcome_page() {
	add_theme_page(
		__( 'Welcome to Majestic Tube', 'majestic-tube' ),
		__( 'Majestic Tube', 'majestic-tube' ),
		'edit_theme_options',
		'majestic-tube-welcome',
		'majestic_tube_render_welcome_page'
	);
}
add_action( 'admin_menu', 'majestic_tube_welcome_page' );

/**
 * Render the welcome screen.
 */
function majestic_tube_render_welcome_page() {
	$created = get_option( 'majestic_tube_created_pages', array() );
	$menu    = get_option( 'majestic_tube_menu_created', false );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Welcome to Majestic Tube', 'majestic-tube' ); ?></h1>

		<p><?php esc_html_e( 'Thanks for activating Majestic Tube. The following setup steps ran automatically:', 'majestic-tube' ); ?></p>

		<ul style="list-style: disc; padding-left: 1.5em;">
			<?php foreach ( $created as $title ) : ?>
				<li>
					<?php
					printf(
						/* translators: %s: page title. */
						esc_html__( 'Created page: %s', 'majestic-tube' ),
						esc_html( $title )
					);
					?>
				</li>
			<?php endforeach; ?>

			<?php if ( $menu ) : ?>
				<li><?php esc_html_e( 'Created the Main Menu and assigned the legal footer links', 'majestic-tube' ); ?></li>
			<?php endif; ?>
		</ul>

		<h2><?php esc_html_e( 'Next steps', 'majestic-tube' ); ?></h2>
		<ul style="list-style: disc; padding-left: 1.5em;">
			<li>
				<a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>"><?php esc_html_e( 'Customize the menus', 'majestic-tube' ); ?></a>
			</li>
			<li>
				<a href="<?php echo esc_url( admin_url( 'customize.php' ) ); ?>"><?php esc_html_e( 'Configure theme options', 'majestic-tube' ); ?></a>
			</li>
			<li>
				<a href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>"><?php esc_html_e( 'Add videos', 'majestic-tube' ); ?></a>
			</li>
		</ul>
	</div>
	<?php
}