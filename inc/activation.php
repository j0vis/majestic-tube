<?php
/**
 * Theme activation routine.
 *
 * Creates the core pages (with the right page templates), built-in legal
 * pages, a default main menu, footer legal links, and redirects to a welcome
 * screen.
 *
 * @package Majestic Tube
 * @version 2.2.22
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
 * The 18 U.S.C. § 2257 page is written for a site that distributes material
 * produced by other people, which is the posture this theme's submission and
 * membership features put an operator in. That framing is a starting point
 * and not a finding: the template says outright that it does not decide which
 * obligations apply, and it tells the operator that a site which produces any
 * of its own material needs primary-producer wording instead. A page that
 * asserted the operator held the full producer duty would be wrong for most
 * installs of this theme, and wronger still for anyone who published it
 * without reading it.
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
<p>This site is operated by <strong>[Site Name]</strong>. It publishes and distributes material produced by third parties.</p>
<p>Where a site distributes or markets material it did not produce, it may fall within the definition of a <em>secondary producer</em> under 18 U.S.C. § 2257 and its implementing regulations at 28 C.F.R. part 75. Obligations attaching to that role can include keeping certain records, publishing a statement describing where those records are held, and producing them for lawful inspection. Which, if any, of those obligations apply to this site depends on the site’s content, the operator’s role in producing it, and the law applicable where the operator and the material are located.</p>
<p><strong>Before publishing, confirm the site’s own position.</strong> The wording below is deliberately generic. It does not state which obligations apply to this site, and it is not a notice required by the statute, a certification of compliance, or legal advice.</p>
<h3>Records, where the operator holds any</h3>
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
<p><strong>Template notice:</strong> This page is a starting template and does not certify compliance. <strong>[Site Name]</strong> and the site contact address fill themselves in from Settings &rarr; General; every other bracketed field is the operator’s to complete. If the operator produces any material rather than only distributing material produced by others, obligations attach to the primary producer instead, and generic secondary-producer wording is not sufficient. The site operator is responsible for establishing which role the site falls in, reviewing this policy against the site’s actual content, hosting, age-verification, and recordkeeping practices, completing the placeholder fields, and obtaining legal advice where needed.</p>
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
<p><strong>Template notice:</strong> This page is a general starting point, not legal advice or a substitute for a review of the site’s actual content and hosting practices. <strong>[Site Name]</strong> and the site contact address fill themselves in from Settings &rarr; General; the mailing address, designated-agent details, and legal name are the operator’s to complete.</p>
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
<p><strong>Template notice:</strong> This page is a starting point, not legal advice. <strong>[Site Name]</strong> and the site contact address fill themselves in from Settings &rarr; General. The operator is responsible for documenting the site’s actual data practices, vendors, retention periods, legal bases, and region-specific rights before relying on it.</p>
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
	// that page instead of creating a second privacy document - but only
	// when it is actually viewable. A trashed setting must fall through to
	// the slug lookup, or the footer would link at a page nobody can open.
	if ( 'privacy-policy' === $definition['slug'] ) {
		$configured_id = absint( get_option( 'wp_page_for_privacy_policy' ) );

		if ( $configured_id ) {
			$configured_page = get_post( $configured_id );

			if ( $configured_page && isset( $configured_page->ID ) && ( ! isset( $configured_page->post_type ) || 'page' === $configured_page->post_type ) && majestic_tube_page_is_viewable( $configured_page ) ) {
				return $configured_page;
			}
		}
	}

	return get_page_by_path( $definition['slug'] );
}

/**
 * Whether a legal-page row is a page visitors can actually open.
 *
 * get_page_by_path() and get_page_by_title() match rows in any status, so a
 * trashed or draft copy looks "found" unless somebody checks. Only publish
 * (and private) count here. A row without a status field - which real
 * WordPress always sets - is treated as viewable so unit stubs that predate
 * the field keep working.
 *
 * @param object|false $page Page row.
 * @return bool
 */
function majestic_tube_page_is_viewable( $page ) {
	if ( ! $page || ! isset( $page->ID ) ) {
		return false;
	}

	if ( ! isset( $page->post_status ) ) {
		return true;
	}

	return in_array( $page->post_status, array( 'publish', 'private' ), true );
}

/**
 * Find a built-in legal page without changing anything.
 *
 * The read-only counterpart to majestic_tube_get_or_create_legal_page(). The
 * status check on the welcome screen needs to answer "is this missing?" and
 * must not migrate or write as a side effect of merely looking.
 *
 * The legacy 2257 slug counts as found. That page is not missing, it is
 * simply on its old name, and recreating it would leave the site with two
 * pages for one document.
 *
 * Only viewable pages count: a trashed copy is reported missing so the
 * welcome screen offers the restore, and the restore then untrashes that
 * same copy instead of inserting a second one. An exact title match is the
 * last resort, for the copy a previous duplicate left behind under a
 * suffixed slug (2257-2) - finding it stops the third copy.
 *
 * @param array  $definition Legal page definition.
 * @param string $title      Page title, for the renamed-copy fallback.
 * @return object|false Page object, or false when no viewable page exists.
 */
function majestic_tube_find_legal_page( $definition, $title = '' ) {
	$page = majestic_tube_get_legal_page( $definition );

	if ( majestic_tube_page_is_viewable( $page ) ) {
		return $page;
	}

	$legacy_slug = ( is_array( $definition ) && ! empty( $definition['legacy_slug'] ) ) ? $definition['legacy_slug'] : '';

	if ( $legacy_slug ) {
		$legacy = get_page_by_path( $legacy_slug );

		if ( majestic_tube_page_is_viewable( $legacy ) ) {
			return $legacy;
		}
	}

	if ( '' !== $title ) {
		foreach ( majestic_tube_legal_pages_by_title( $title ) as $by_title ) {
			if ( majestic_tube_page_is_viewable( $by_title ) ) {
				return $by_title;
			}
		}
	}

	return false;
}

/**
 * Every page row carrying an exact title, in any status.
 *
 * get_page_by_title() returns a single row, so a second live copy hiding
 * under a suffixed slug is invisible to it - which is exactly the duplicate
 * this machinery exists to find. An exact-title query returns them all.
 *
 * @param string $title Exact page title.
 * @return array<int, object> Page rows, oldest first.
 */
function majestic_tube_legal_pages_by_title( $title ) {
	if ( '' === $title || ! function_exists( 'get_posts' ) ) {
		return array();
	}

	$found = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'trash' ),
			'title'          => $title,
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);

	return is_array( $found ) ? array_values( $found ) : array();
}

/**
 * Every viewable copy of one built-in legal document, canonical first.
 *
 * Normally this is a single page. When an earlier duplicate left two live
 * copies behind (for example slugs 2257 and 2257-2), all of them are
 * returned so the status screen can name the extras instead of pretending
 * the document is fine while the Pages list shows it twice.
 *
 * @param array  $definition Legal page definition.
 * @param string $title      Page title, for the renamed-copy fallback.
 * @return array<int, object> Viewable page rows, keyed by page ID.
 */
function majestic_tube_find_legal_copies( $definition, $title = '' ) {
	$copies = array();

	$take = function ( $page ) use ( &$copies ) {
		if ( majestic_tube_page_is_viewable( $page ) ) {
			$copies[ (int) $page->ID ] = $page;
		}
	};

	$take( majestic_tube_get_legal_page( $definition ) );

	$legacy_slug = ( is_array( $definition ) && ! empty( $definition['legacy_slug'] ) ) ? $definition['legacy_slug'] : '';

	if ( $legacy_slug ) {
		$take( get_page_by_path( $legacy_slug ) );
	}

	foreach ( majestic_tube_legal_pages_by_title( $title ) as $by_title ) {
		$take( $by_title );
	}

	return $copies;
}

/**
 * Which built-in legal pages exist, which do not, and which exist twice.
 *
 * @return array{found: array<string,int>, missing: array<int,string>, duplicates: array<string,array<int>>}
 */
function majestic_tube_legal_page_status() {
	$found      = array();
	$missing    = array();
	$duplicates = array();

	foreach ( majestic_tube_legal_pages() as $title => $definition ) {
		$copies = majestic_tube_find_legal_copies( $definition, $title );

		if ( ! $copies ) {
			$missing[] = $title;
			continue;
		}

		$ids             = array_keys( $copies );
		$found[ $title ] = $ids[0];

		if ( count( $ids ) > 1 ) {
			$duplicates[ $title ] = array_slice( $ids, 1 );
		}
	}

	return array(
		'found'      => $found,
		'missing'    => $missing,
		'duplicates' => $duplicates,
	);
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

	/*
	 * A trashed or draft canonical still occupies the slug: renaming the
	 * legacy page onto it would make WordPress mint a suffixed 2257-2
	 * second copy. Leave the legacy page serving the document instead -
	 * the status check counts it as found, so nothing else is created.
	 */
	if ( $canonical && isset( $canonical->ID ) && ! majestic_tube_page_is_viewable( $canonical ) ) {
		return $legacy;
	}

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
 * Repair a trashed, draft, or renamed legal-page copy instead of duplicating it.
 *
 * Deleting a page sends it to the trash, where the slug lookup cannot tell it
 * apart from a page that never existed - so the old code inserted a fresh
 * copy and the site ended up with two 18 USC 2257 pages. Trashing is also
 * never a deliberate "keep" state for these infrastructure pages: the footer
 * legal menu links at them, and a trashed target is a dead link.
 *
 * @param string $title      Page title/menu label.
 * @param array  $definition Legal page definition.
 * @param bool   $publish    Publish drafts as well as untrashing. The welcome
 *                           screen restore passes true (the administrator
 *                           explicitly asked for the page back); background
 *                           setup passes false and reuses a draft untouched.
 * @return int Page ID of the repaired copy, or 0 when none exists.
 */
function majestic_tube_repair_unviewable_legal_page( $title, $definition, $publish = false ) {
	$candidates = array();
	$seen       = array();

	foreach ( array( 'slug', 'legacy_slug' ) as $key ) {
		if ( empty( $definition[ $key ] ) ) {
			continue;
		}

		$found = get_page_by_path( $definition[ $key ] );

		if ( $found && isset( $found->ID ) && ! isset( $seen[ (int) $found->ID ] ) ) {
			$seen[ (int) $found->ID ] = true;
			$candidates[]             = (int) $found->ID;
		}
	}

	foreach ( majestic_tube_legal_pages_by_title( $title ) as $by_title ) {
		if ( isset( $by_title->ID ) && ! isset( $seen[ (int) $by_title->ID ] ) ) {
			$seen[ (int) $by_title->ID ] = true;
			$candidates[]               = (int) $by_title->ID;
		}
	}

	foreach ( $candidates as $candidate_id ) {
		$candidate = get_post( $candidate_id );

		if ( ! $candidate || ! isset( $candidate->ID ) ) {
			continue;
		}

		$status = isset( $candidate->post_status ) ? $candidate->post_status : '';

		if ( 'trash' === $status && function_exists( 'wp_untrash_post' ) ) {
			wp_untrash_post( $candidate_id );

		$refreshed = get_post( $candidate_id );

		if ( $refreshed && isset( $refreshed->ID ) ) {
			$candidate = $refreshed;
			$status    = isset( $candidate->post_status ) ? $candidate->post_status : '';
		}
		}

		if ( $publish && in_array( $status, array( 'draft', 'pending' ), true ) && function_exists( 'wp_update_post' ) ) {
			wp_update_post(
				array(
					'ID'          => $candidate_id,
					'post_status' => 'publish',
				),
			true
			);
		}

		// Whatever remains - untrashed, published, or a draft the caller
		// chose not to publish - is the page. Returning it is what stops
		// the insert below from minting the second copy.
		return $candidate_id;
	}

	return 0;
}

/**
 * Get or create one built-in legal page, preserving existing page content.
 *
 * A page that exists in any form - live, trashed, draft, or sitting under a
 * suffixed slug from an earlier duplicate - is reused, never duplicated. Only
 * a page that exists nowhere is created, and a created page is explicitly put
 * on the default page template so it renders through page.php like any
 * ordinary prose page.
 *
 * @param string $title               Page title/menu label.
 * @param array  $definition          Legal page definition.
 * @param bool   $publish_unviewable  Publish drafts as well as untrashing.
 * @return int Page ID, or 0 on failure.
 */
function majestic_tube_get_or_create_legal_page( $title, $definition, $publish_unviewable = false ) {
	$live = majestic_tube_find_legal_page( $definition, $title );

	if ( $live && isset( $live->ID ) ) {
		$page_id = (int) $live->ID;
	} else {
		$page_id = majestic_tube_repair_unviewable_legal_page( $title, $definition, $publish_unviewable );

		if ( ! $page_id ) {
			$page = ! empty( $definition['legacy_slug'] )
				? majestic_tube_migrate_2257_page( $definition )
				: majestic_tube_get_legal_page( $definition );

			if ( majestic_tube_page_is_viewable( $page ) ) {
				$page_id = (int) $page->ID;
			} else {
				$page_id = majestic_tube_create_page( $title, 'default', $definition['content'], $definition['slug'] );
			}
		}
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
 * Resolve the legal document being rendered, if this request is one.
 *
 * Shared by the placeholder filters below so each of them answers the same
 * question the same way, and so adding a second placeholder does not mean a
 * second copy of this lookup.
 *
 * @return object|false The page object, or false when this is not one.
 */
function majestic_tube_current_legal_document() {
	$post_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;

	if ( ! $post_id && isset( $GLOBALS['post'] ) && is_object( $GLOBALS['post'] ) ) {
		$post_id = isset( $GLOBALS['post']->ID ) ? (int) $GLOBALS['post']->ID : 0;
	}

	$post = $post_id ? get_post( $post_id ) : false;

	return ( $post && majestic_tube_is_legal_document( $post ) ) ? $post : false;
}

/**
 * Fill in the site name wherever a legal page asks for it.
 *
 * The starter legal pages carry a literal [Site Name] rather than the site's
 * actual name, so an editor reading the page can see at a glance which parts
 * are a template and which parts they are responsible for. Expanding it at
 * render time instead of baking the name into the stored content means it
 * follows Settings -> General, and - because this is a filter rather than a
 * content rewrite - it starts working on the pages earlier versions of the
 * theme already created, without touching what is stored.
 *
 * An unset or blank site title deliberately leaves the placeholder visible.
 * Substituting an empty string would publish "This site is operated by ." to
 * the public, which is worse than showing an editor an unfilled gap.
 *
 * @param string $content Post content.
 * @return string
 */
function majestic_tube_filter_legal_document_site_name( $content ) {
	if ( ! is_string( $content ) || false === strpos( $content, '[Site Name]' ) ) {
		return $content;
	}

	if ( ! majestic_tube_current_legal_document() ) {
		return $content;
	}

	$name = trim( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) );

	if ( '' === $name ) {
		return $content;
	}

	return str_replace( '[Site Name]', esc_html( $name ), $content );
}
// Ahead of the contact filter, so a page that has been emptied of its contact
// line still gets one, and both land before core's do_shortcode() pass.
add_filter( 'the_content', 'majestic_tube_filter_legal_document_site_name', 8 );

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

	if ( ! majestic_tube_current_legal_document() ) {
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
		/*
		 * A trashed row is not a page. Returning its ID reported trash as
		 * created - the welcome screen listed pages nobody could open.
		 * Untrash it instead; the administrator's content comes back and no
		 * second copy is needed. A draft is reused untouched.
		 */
		if ( majestic_tube_page_is_viewable( $existing ) ) {
			$page_id = (int) $existing->ID;
		} elseif ( isset( $existing->post_status ) && 'trash' === $existing->post_status && function_exists( 'wp_untrash_post' ) ) {
			wp_untrash_post( (int) $existing->ID );

			$refreshed = get_post( (int) $existing->ID );

			if ( $refreshed && isset( $refreshed->ID ) && majestic_tube_page_is_viewable( $refreshed ) ) {
				$page_id = (int) $refreshed->ID;
			} else {
				// The slug is still occupied: inserting now would mint a
				// suffixed duplicate, so report failure and retry later.
				return 0;
			}
		} else {
			$page_id = (int) $existing->ID;
		}
	} else {
		/*
		 * `page` is registered by core on init priority 0, and wp_insert_post()
		 * refuses any post type that is not registered yet - it answers with a
		 * WP_Error and inserts nothing. Callers must therefore run after init.
		 * Checking it here keeps this function from reporting a page WordPress
		 * never created, which is what made the whole activation routine look
		 * like it had succeeded while the site had no pages at all.
		 */
		if ( ! post_type_exists( 'page' ) ) {
			return 0;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => $page_slug,
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => get_current_user_id(),
			),
			true
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
 * Create missing pages and assign page templates.
 *
 * Existing pages are reused without overwriting administrator-edited content.
 *
 * @return string[] Titles that could not be created, empty when nothing failed.
 */
function majestic_tube_create_initial_pages() {
	$created = array();
	$failed  = array();

	foreach ( majestic_tube_activation_pages() as $title => $template ) {
		$page_id = majestic_tube_create_page( $title, $template );

		if ( $page_id ) {
			$created[ $title ] = $page_id;
		} else {
			$failed[] = $title;
		}
	}

	foreach ( majestic_tube_legal_pages() as $title => $page ) {
		$page_id = majestic_tube_get_or_create_legal_page( $title, $page );

		if ( $page_id ) {
			$created[ $title ] = $page_id;
		} else {
			$failed[] = $title;
		}
	}

	update_option( 'majestic_tube_created_pages', array_keys( $created ) );

	return $failed;
}

/**
 * Revision of the automatic site setup.
 *
 * Bumping this number makes every existing installation run the setup once
 * more. It is the release-time switch; the retry counter below is separate so
 * a site that genuinely cannot create pages stops retrying.
 *
 * @return int
 */
function majestic_tube_setup_revision() {
	/**
	 * Filter the revision of the automatic site setup.
	 *
	 * @param int $revision Current revision.
	 */
	return (int) apply_filters( 'majestic_tube_setup_revision', 4 );
}

/**
 * How many times the setup may be retried before it stops trying.
 *
 * @return int
 */
function majestic_tube_setup_attempt_limit() {
	return 5;
}

/**
 * Whether the setup already finished for the current revision.
 *
 * @return bool
 */
function majestic_tube_setup_is_complete() {
	return (int) get_option( 'majestic_tube_setup_revision', 0 ) >= majestic_tube_setup_revision();
}

/**
 * Create the pages, menus and rewrite rules the theme needs.
 *
 * Every step is idempotent, so this is safe to call more than once - on the
 * request that activates the theme and again on the next one - and safe to
 * call again after a failure. Nothing here overwrites administrator edits:
 * existing pages are only re-used, never rewritten.
 *
 * The full set of steps is only recorded as done when every page was created.
 * A site where one insert fails keeps retrying up to
 * majestic_tube_setup_attempt_limit(), then stops and reports what is missing
 * on the welcome screen instead of looping forever.
 *
 * @return bool Whether the setup is now complete.
 */
function majestic_tube_run_site_setup() {
	$failed_pages = majestic_tube_create_initial_pages();
	$menu_created = majestic_tube_create_default_menu();

	majestic_tube_migrate_widgets();
	flush_rewrite_rules();

	/*
	 * The single option the previous release used to gate the legal-page setup.
	 * It is still written so an installation that downgrades reads the value it
	 * expects, but it no longer decides anything here.
	 */
	update_option( 'majestic_tube_legal_setup_version', 2 );

	$failed = $failed_pages;

	if ( ! $menu_created ) {
		$failed[] = __( 'Main Menu', 'majestic-tube' );
	}

	if ( $failed ) {
		update_option( 'majestic_tube_setup_missing', $failed );
		update_option( 'majestic_tube_setup_attempts', (int) get_option( 'majestic_tube_setup_attempts', 0 ) + 1 );

		return false;
	}

	delete_option( 'majestic_tube_setup_missing' );
	update_option( 'majestic_tube_setup_attempts', 0 );
	update_option( 'majestic_tube_setup_revision', majestic_tube_setup_revision() );

	return true;
}

/**
 * Run the setup when the theme is switched, and once per setup revision.
 *
 * This used to live on after_setup_theme, which runs before init. Core
 * registers the built-in `page` post type and the `nav_menu` taxonomy on init
 * priority 0, so every wp_insert_post() and wp_create_nav_menu() call on that
 * hook failed - and the handler then stored its one-time "already done" marker
 * anyway, which meant the pages and menus were never created on any request
 * that did not go through after_switch_theme. That is why a theme update, a
 * restored database, or a site whose switched-theme transient was gone ended
 * up with no pages at all and no way to recover short of deleting the option.
 *
 * after_switch_theme is dispatched by core's check_theme_switched() on init
 * priority 99, so the switch request itself is a valid place to work; the
 * revision check on init covers everything that never fires it.
 *
 * @return void
 */
function majestic_tube_maybe_setup_site() {
	if ( majestic_tube_setup_is_complete() ) {
		return;
	}

	if ( (int) get_option( 'majestic_tube_setup_attempts', 0 ) >= majestic_tube_setup_attempt_limit() ) {
		return;
	}

	majestic_tube_run_site_setup();
}
add_action( 'init', 'majestic_tube_maybe_setup_site', 20 );
add_action( 'admin_init', 'majestic_tube_maybe_setup_site', 5 );
add_action( 'after_switch_theme', 'majestic_tube_maybe_setup_site', 40 );

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
				if ( $menu_item_id && function_exists( 'wp_delete_post' ) ) {
					/*
					 * A menu item is a `nav_menu_item` post, and core's own
					 * wp_delete_nav_menu() removes one with wp_delete_post().
					 * WordPress has no wp_delete_nav_menu_item(), so the
					 * function_exists() guard this call used to carry was always
					 * false and the duplicate item was never removed.
					 */
					wp_delete_post( $menu_item_id, true );
				}

				continue;
			}

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

			if ( $menu_item_id && function_exists( 'wp_delete_post' ) ) {
				// Same as above: core deletes a menu item as a post.
				wp_delete_post( $menu_item_id, true );
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

		$updated = majestic_tube_update_page_menu_item( $menu_id, $title, $page->ID );

		// Only remember a page that really was added, so a failed insert is
		// retried on the next run instead of being counted as done.
		if ( ! is_wp_error( $updated ) && $updated ) {
			$have_ids[] = (int) $page->ID;
		}
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
 *
 * @return bool Whether the menu exists and its locations were assigned.
 */
function majestic_tube_create_default_menu() {
	$menu_id = majestic_tube_get_or_create_nav_menu( 'Main Menu' );

	if ( ! $menu_id ) {
		// wp_create_nav_menu() fails with WP_Error( 'invalid_taxonomy' ) until
		// the nav_menu taxonomy is registered on init priority 0.
		return false;
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

	// All five theme pages belong on the main header menu: the video-submit
	// and profile pages as well as the three directory pages. Pages that
	// are still missing are skipped here; the setup creates them first, and
	// the next run links whatever appears.
	foreach ( majestic_tube_activation_pages() as $menu_title => $menu_template ) {
		$item_page = get_page_by_path( sanitize_title( $menu_title ) );

		if ( ! majestic_tube_page_is_viewable( $item_page ) || in_array( (int) $item_page->ID, $have_ids, true ) ) {
			continue;
		}

		majestic_tube_update_page_menu_item( $menu_id, $menu_title, $item_page->ID );
		$have_ids[] = (int) $item_page->ID;
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

	return true;
}

/**
 * Revision of the footer legal-link repair.
 *
 * Deliberately separate from majestic_tube_setup_revision() so the footer menu
 * can be repaired on an installation whose full setup already completed. Bumping
 * this makes every existing site check its footer menu once.
 *
 * @return int
 */
function majestic_tube_footer_legal_revision() {
	/**
	 * Filter the revision of the footer legal-link repair.
	 *
	 * @param int $revision Current revision.
	 */
	return (int) apply_filters( 'majestic_tube_footer_legal_revision', 1 );
}

/**
 * Make sure every legal page exists and sits in the assigned footer menu.
 *
 * The full site setup only runs once per revision, so a legal page that was
 * created later, a page whose slug was migrated, or a footer menu item that
 * was deleted by hand was never repaired: the link simply stayed missing for
 * good. That is how the 18 USC 2257 page ended up unlinked while DMCA and the
 * Privacy Policy were still there.
 *
 * This runs on admin requests only, and stops after it finds nothing to do, so
 * it costs a single option read per admin page load.
 *
 * @return void
 */
function majestic_tube_repair_footer_legal_links() {
	$revision = majestic_tube_footer_legal_revision();

	if ( (int) get_option( 'majestic_tube_footer_legal_revision', 0 ) >= $revision ) {
		return;
	}

	$locations = get_theme_mod( 'nav_menu_locations', array() );
	$locations = is_array( $locations ) ? $locations : array();
	$menu_id   = isset( $locations['majestic_tube_footer_menu'] ) ? absint( $locations['majestic_tube_footer_menu'] ) : 0;
	$menu      = $menu_id ? wp_get_nav_menu_object( $menu_id ) : false;

	if ( $menu && ! is_wp_error( $menu ) ) {
		$menu_id = (int) $menu->term_id;
	} else {
		// Either the setup never assigned the location or the assigned menu was
		// deleted. Recreate the same menu the setup would have produced, rather
		// than writing legal links into a menu nothing points at.
		$menu_id = majestic_tube_get_or_create_nav_menu( 'Footer Legal Menu' );

		if ( ! $menu_id ) {
			// The full setup owns the location assignment; it will retry.
			return;
		}

		$locations['majestic_tube_footer_menu'] = (int) $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	foreach ( majestic_tube_legal_pages() as $title => $definition ) {
		if ( ! majestic_tube_get_or_create_legal_page( $title, $definition ) ) {
			// Leave the marker unset so the next admin request tries again.
			return;
		}
	}

	majestic_tube_add_legal_pages_to_menu( $menu_id );
	update_option( 'majestic_tube_footer_legal_revision', $revision );
}
add_action( 'admin_init', 'majestic_tube_repair_footer_legal_links', 20 );
add_action( 'after_switch_theme', 'majestic_tube_repair_footer_legal_links', 50 );

/**
 * Revision of the page-template repair.
 *
 * @return int
 */
function majestic_tube_page_template_revision() {
	/**
	 * Filter the revision of the page-template repair.
	 *
	 * @param int $revision Current revision.
	 */
	return (int) apply_filters( 'majestic_tube_page_template_revision', 1 );
}

/**
 * Put the theme's own page templates back on the pages that need them.
 *
 * A page template lives in the `_wp_page_template` meta row of the page, not
 * in the theme. Anything that clears it - switching themes, a restore, an
 * import - leaves the page rendering through page.php instead, which prints
 * the page's own content and none of the theme's form. The full site setup
 * assigns the templates but only runs once per revision, so a cleared
 * assignment was never restored and the page stayed broken for good.
 *
 * Only a missing or unknown assignment is touched. A page deliberately put
 * back on the default template is left alone, because the empty string and
 * 'default' both mean the administrator chose it.
 *
 * @return void
 */
function majestic_tube_repair_page_templates() {
	$revision = majestic_tube_page_template_revision();

	if ( (int) get_option( 'majestic_tube_page_template_revision', 0 ) >= $revision ) {
		return;
	}

	$available = wp_get_theme()->get_page_templates();
	$complete  = true;

	foreach ( majestic_tube_activation_pages() as $title => $template ) {
		// A template the installed theme no longer ships cannot be applied.
		if ( ! isset( $available[ $template ] ) ) {
			continue;
		}

		$page = get_page_by_path( sanitize_title( $title ) );

		// The page does not exist yet. The full setup creates it and assigns
		// the template itself, so leave the marker unset and try again later.
		if ( ! $page || ! isset( $page->ID ) ) {
			$complete = false;
			continue;
		}

		$assigned = get_post_meta( $page->ID, '_wp_page_template', true );
		$assigned = is_string( $assigned ) ? $assigned : '';

		if ( '' === $assigned || 'default' === $assigned ) {
			update_post_meta( $page->ID, '_wp_page_template', $template );
		}
	}

	// Stop retrying only once every page carries a template of its own.
	if ( $complete ) {
		update_option( 'majestic_tube_page_template_revision', $revision );
	}
}
add_action( 'admin_init', 'majestic_tube_repair_page_templates', 25 );
add_action( 'after_switch_theme', 'majestic_tube_repair_page_templates', 55 );

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

/*
 * The page, menu, widget and rewrite-rule steps used to be separate
 * after_switch_theme callbacks at priorities 20, 25 and 30. They are now the
 * body of majestic_tube_run_site_setup() so that the switch request, a theme
 * update and a manual retry all take exactly the same path - and so that the
 * rewrite rules are only ever flushed once the actors taxonomy is registered.
 */

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
 * Run the site setup again from the welcome screen.
 *
 * A site whose automatic setup failed would otherwise stay broken forever:
 * the one-time marker stopped it from retrying. The link below is the escape
 * hatch, and it resets the attempt counter first so a manual run always tries.
 *
 * @return void
 */
function majestic_tube_handle_setup_retry() {
	if ( ! isset( $_GET['majestic-tube-setup'] ) || 'retry' !== $_GET['majestic-tube-setup'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is verified on the next line.
		return;
	}

	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	check_admin_referer( 'majestic_tube_setup_retry' );

	update_option( 'majestic_tube_setup_attempts', 0 );
	$complete = majestic_tube_run_site_setup();

	wp_safe_redirect(
		add_query_arg(
			'majestic-tube-setup',
			$complete ? 'done' : 'failed',
			admin_url( 'themes.php?page=majestic-tube-welcome' )
		)
	);
	exit;
}
add_action( 'admin_init', 'majestic_tube_handle_setup_retry', 4 );

/**
 * Recreate the built-in legal pages an administrator has deleted.
 *
 * The footer repair below recreates missing pages, but only when a theme
 * update advances its revision marker, so a page deleted after that stayed
 * deleted - and a legal document that is gone is not a cosmetic problem, so
 * there has to be a way to bring it back without waiting for a release.
 *
 * This is deliberately an explicit button rather than an automatic repair.
 * The automatic repairs in this file restore *references* - a menu item, a
 * page template assignment - where a missing target is unambiguous. A page is
 * content: an operator who removed their 2257 page may have done it on purpose,
 * and quietly restoring it, and its footer link, would be arguing with them.
 *
 * Only pages that are actually missing are touched. A page that exists keeps
 * its content, its title and its URL, because that content is the
 * administrator's, not the theme's.
 *
 * @return void
 */
function majestic_tube_handle_legal_page_restore() {
	if ( ! isset( $_GET['majestic-tube-legal'] ) || 'restore' !== $_GET['majestic-tube-legal'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is verified on the next line.
		return;
	}

	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die(
			esc_html__( 'You are not allowed to manage legal pages.', 'majestic-tube' ),
			esc_html__( 'Legal pages', 'majestic-tube' ),
			array( 'response' => 403 )
		);
	}

	check_admin_referer( 'majestic_tube_legal_restore' );

	$definitions = majestic_tube_legal_pages();
	$missing     = majestic_tube_legal_page_status()['missing'];
	$restored    = array();
	$failed      = array();

	foreach ( $missing as $title ) {
		if ( ! isset( $definitions[ $title ] ) ) {
			continue;
		}

		/*
		 * Re-check immediately before writing: the status above was computed
		 * moments ago, and another path in this same request (the automatic
		 * setup runs on init and admin_init around this handler) may have
		 * created the page since. Creating blindly here is how one click
		 * minted the second copy.
		 */
		if ( majestic_tube_find_legal_page( $definitions[ $title ], $title ) ) {
			continue;
		}

		if ( majestic_tube_get_or_create_legal_page( $title, $definitions[ $title ], true ) ) {
			$restored[] = $title;
		} else {
			$failed[] = $title;
		}
	}

	/*
	 * Recreating the page is only half the job. The footer legal menu may
	 * still hold a dead item, or no link at all for the page that just came
	 * back. Resetting the revision marker and running the existing repair is
	 * better than reimplementing the menu logic here: that repair already
	 * knows how to relink without duplicating items, and it is the same code
	 * path a theme update uses.
	 */
	update_option( 'majestic_tube_footer_legal_revision', 0 );
	majestic_tube_repair_footer_legal_links();

	$state = $failed
		? ( $restored ? 'partial' : 'failed' )
		: ( $restored ? 'restored' : 'none' );

	$args = array( 'majestic-tube-legal' => $state );

	if ( $restored ) {
		$args['restored'] = implode( ',', $restored );
	}

	if ( $failed ) {
		$args['failed'] = implode( ',', $failed );
	}

	wp_safe_redirect( add_query_arg( $args, admin_url( 'themes.php?page=majestic-tube-welcome' ) ) );
	exit;
}
add_action( 'admin_init', 'majestic_tube_handle_legal_page_restore', 3 );

/**
 * Move one duplicate legal-page copy to the trash from the welcome screen.
 *
 * When two live copies of a document exist, the footer can only link one and
 * the Pages list shows both - this is the state the duplicate reports come
 * from. Trashing (never deleting) is reversible, and the handler refuses
 * when the copy is the last one standing, so the document itself is safe.
 *
 * @return void
 */
function majestic_tube_handle_legal_page_trash() {
	if ( ! isset( $_GET['majestic-tube-legal'] ) || 'trash' !== $_GET['majestic-tube-legal'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is verified on the next line.
		return;
	}

	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die(
			esc_html__( 'You are not allowed to manage legal pages.', 'majestic-tube' ),
			esc_html__( 'Legal pages', 'majestic-tube' ),
			array( 'response' => 403 )
		);
	}

	check_admin_referer( 'majestic_tube_legal_trash' );

	$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
	$trashed = false;

	if ( $post_id && function_exists( 'wp_trash_post' ) ) {
		$post = get_post( $post_id );

		if ( $post && isset( $post->ID, $post->post_type ) && 'page' === $post->post_type ) {
			foreach ( majestic_tube_legal_pages() as $title => $definition ) {
			$copies = majestic_tube_find_legal_copies( $definition, $title );

			if ( isset( $copies[ $post_id ] ) && count( $copies ) > 1 ) {
				$trashed = (bool) wp_trash_post( $post_id );
				break;
		}
		}
		}
	}

	if ( ! $trashed ) {
		wp_die(
			esc_html__( 'That page cannot be moved to the trash. It may be the only copy of its document, or it may already be gone.', 'majestic-tube' ),
			esc_html__( 'Legal pages', 'majestic-tube' ),
			array( 'response' => 400 )
		);
	}

	wp_safe_redirect(
		add_query_arg(
			'majestic-tube-legal',
			'trashed',
			admin_url( 'themes.php?page=majestic-tube-welcome' )
		)
	);
	exit;
}
add_action( 'admin_init', 'majestic_tube_handle_legal_page_trash', 3 );

/**
 * Tell an administrator when the automatic setup did not finish.
 *
 * @return void
 */
function majestic_tube_setup_notice() {
	if ( majestic_tube_setup_is_complete() ) {
		return;
	}

	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	$missing = get_option( 'majestic_tube_setup_missing', array() );
	$missing = is_array( $missing ) ? $missing : array();
	?>
	<div class="notice notice-warning">
		<p>
			<strong><?php esc_html_e( 'Majestic Tube setup is incomplete.', 'majestic-tube' ); ?></strong>
			<?php esc_html_e( 'The theme could not create every page and menu it ships with.', 'majestic-tube' ); ?>
			<?php if ( $missing ) : ?>
				<?php
				printf(
					/* translators: %s: comma-separated list of page titles. */
					esc_html__( 'Still missing: %s.', 'majestic-tube' ),
					esc_html( implode( ', ', $missing ) )
				);
				?>
			<?php endif; ?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'majestic-tube-setup', 'retry', admin_url( 'themes.php?page=majestic-tube-welcome' ) ), 'majestic_tube_setup_retry' ) ); ?>">
				<?php esc_html_e( 'Run the setup again', 'majestic-tube' ); ?>
			</a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'majestic_tube_setup_notice' );

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
	$created        = get_option( 'majestic_tube_created_pages', array() );
	$menu           = get_option( 'majestic_tube_menu_created', false );
	$setup_complete = majestic_tube_setup_is_complete();
	$retry_state    = isset( $_GET['majestic-tube-setup'] ) ? sanitize_key( wp_unslash( $_GET['majestic-tube-setup'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display state.
	$legal          = majestic_tube_legal_page_status();
	$legal_state    = isset( $_GET['majestic-tube-legal'] ) ? sanitize_key( wp_unslash( $_GET['majestic-tube-legal'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display state.
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Welcome to Majestic Tube', 'majestic-tube' ); ?></h1>

		<?php if ( 'done' === $retry_state ) : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'Setup finished. Every page and menu the theme ships with now exists.', 'majestic-tube' ); ?></p></div>
		<?php elseif ( 'failed' === $retry_state ) : ?>
			<div class="notice notice-error inline"><p><?php esc_html_e( 'Setup still could not create everything. Check that your database user may create pages, then try again.', 'majestic-tube' ); ?></p></div>
		<?php endif; ?>

		<p>
			<?php
			if ( $setup_complete ) {
				esc_html_e( 'Thanks for activating Majestic Tube. The following setup steps ran:', 'majestic-tube' );
			} else {
				esc_html_e( 'Thanks for activating Majestic Tube. Setup has not finished yet, so the site may be missing pages or menus:', 'majestic-tube' );
			}
			?>
		</p>

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

		<?php if ( ! $setup_complete ) : ?>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'majestic-tube-setup', 'retry', admin_url( 'themes.php?page=majestic-tube-welcome' ) ), 'majestic_tube_setup_retry' ) ); ?>">
					<?php esc_html_e( 'Run the setup again', 'majestic-tube' ); ?>
				</a>
			</p>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Legal pages', 'majestic-tube' ); ?></h2>

		<?php if ( 'restored' === $legal_state ) : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'The missing legal pages have been recreated, and the footer legal links now point at them.', 'majestic-tube' ); ?></p></div>
		<?php elseif ( 'partial' === $legal_state ) : ?>
			<div class="notice notice-warning inline"><p>
				<?php
				printf(
					/* translators: %s: comma-separated list of page titles. */
					esc_html__( 'Some legal pages could not be recreated: %s. Check that your database user may create pages, then try again.', 'majestic-tube' ),
					esc_html( isset( $_GET['failed'] ) ? sanitize_text_field( wp_unslash( $_GET['failed'] ) ) : '' ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of our own redirect value.
				);
				?>
			</p></div>
		<?php elseif ( 'failed' === $legal_state ) : ?>
			<div class="notice notice-error inline"><p><?php esc_html_e( 'No legal page could be recreated. Check that your database user may create pages, then try again.', 'majestic-tube' ); ?></p></div>			<?php elseif ( 'none' === $legal_state ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Every legal page is already present, so nothing was changed.', 'majestic-tube' ); ?></p></div>
			<?php elseif ( 'trashed' === $legal_state ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'The duplicate copy was moved to the trash. The remaining page keeps its content and its footer link.', 'majestic-tube' ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! empty( $legal['duplicates'] ) ) : ?>
				<div class="notice notice-warning inline"><p>
					<?php
					printf(
						/* translators: %s: comma-separated list of page titles. */
						esc_html__( 'Two live copies of %s exist. The footer links at the first; move the other to the trash below and its content stays recoverable there.', 'majestic-tube' ),
						esc_html( implode( ', ', array_keys( $legal['duplicates'] ) ) )
					);
					?>
				</p></div>
			<?php endif; ?>

		<p><?php esc_html_e( 'These are the compliance pages the theme creates. If one has been deleted, the footer link to it is dead and the document is no longer published.', 'majestic-tube' ); ?></p>

		<table class="widefat striped" style="max-width: 40em;">
			<tbody>
				<?php foreach ( $legal['found'] as $legal_title => $legal_id ) : ?>
					<tr>
						<td><?php echo esc_html( $legal_title ); ?></td>
						<td>
							<a href="<?php echo esc_url( (string) get_permalink( $legal_id ) ); ?>"><?php esc_html_e( 'View', 'majestic-tube' ); ?></a>
							&middot;
							<a href="<?php echo esc_url( (string) get_edit_post_link( $legal_id ) ); ?>"><?php esc_html_e( 'Edit', 'majestic-tube' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>					<?php foreach ( $legal['missing'] as $legal_title ) : ?>
					<tr>
						<td><?php echo esc_html( $legal_title ); ?></td>
						<td><strong><?php esc_html_e( 'Missing', 'majestic-tube' ); ?></strong></td>
					</tr>
				<?php endforeach; ?>

					<?php foreach ( $legal['duplicates'] as $legal_title => $extra_ids ) : ?>
						<?php foreach ( $extra_ids as $extra_id ) : ?>
							<tr>
								<td>
									<?php echo esc_html( $legal_title ); ?>
									<strong><?php esc_html_e( '(duplicate)', 'majestic-tube' ); ?></strong>
								</td>
								<td>
									<a href="<?php echo esc_url( (string) get_permalink( $extra_id ) ); ?>"><?php esc_html_e( 'View', 'majestic-tube' ); ?></a>
									&middot;
									<a href="<?php echo esc_url( (string) get_edit_post_link( $extra_id ) ); ?>"><?php esc_html_e( 'Edit', 'majestic-tube' ); ?></a>
									&middot;
									<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'majestic-tube-legal' => 'trash', 'post' => $extra_id ), admin_url( 'themes.php?page=majestic-tube-welcome' ) ), 'majestic_tube_legal_trash' ) ); ?>"><?php esc_html_e( 'Move to trash', 'majestic-tube' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endforeach; ?>
			</tbody>
		</table>

		<p style="margin-top: 1em;">
			<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'majestic-tube-legal', 'restore', admin_url( 'themes.php?page=majestic-tube-welcome' ) ), 'majestic_tube_legal_restore' ) ); ?>">
				<?php
				echo ! empty( $legal['missing'] )
					? esc_html__( 'Recreate the missing legal pages', 'majestic-tube' )
					: esc_html__( 'Check the legal pages again', 'majestic-tube' );
				?>
			</a>
		</p>
		<p style="color: #646970;">
			<?php esc_html_e( 'Only pages that are missing are created, using the starter wording the theme ships with. A page that already exists is never overwritten, so any text you have written stays.', 'majestic-tube' ); ?>
		</p>

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