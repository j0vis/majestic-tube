<?php
/**
 * Theme activation routine.
 *
 * Creates the core pages (with the right page templates), built-in legal
 * pages, a default main menu, footer legal links, and redirects to a welcome
 * screen.
 *
 * @package Majestic Tube
 * @version 2.5.0
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
 * Activation pages that belong inside My Account, never in the top-level menu.
 *
 * These two are members' account pages, and the theme prints a My Account
 * dropdown holding both of them at render time (majestic_tube_membership_nav).
 * Adding them as top-level items as well - which is what activation used to
 * do - put "Submit a Video" beside Home and Categories, linking at the same
 * place twice. The pages are still created; only their menu place changes.
 *
 * @return string[] Page titles, matching majestic_tube_activation_pages().
 */
function majestic_tube_account_page_titles() {
	/**
	 * Filter which activation pages are kept out of the top-level main menu.
	 *
	 * @param string[] $titles Page titles.
	 */
	return (array) apply_filters(
		'majestic_tube_account_page_titles',
		array( 'Submit a Video', 'Profile' )
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
 * These three pages no longer end with the bolded "Template notice" paragraph
 * an earlier release shipped. That paragraph was aimed at the administrator,
 * but it was part of the page body, so a visitor reading the policy was told
 * by the policy itself that it was a template - the site appearing to
 * disclaim its own document. The warning still belongs on this screen and in
 * the setup notes, not on the page a visitor is asked to read, so the shipped
 * copy carries the policy and nothing else. Pages an earlier release already
 * created are cleaned up by majestic_tube_strip_template_notice().
 *
 * The address each document is asked for matters more than it looks, because
 * WordPress decides it: wp_unique_post_slug() refuses a slug that is a number,
 * with or without the pagination base in front of it, since /2257/ would be
 * indistinguishable from a page number in a URL the site itself generates.
 * Asking for /2257/ therefore never produced /2257/ - it produced /2257-2/, and
 * then /2257-3/ on the site after that. The 2257 page keeps the address it has
 * been linked at since the first release, /18-usc-2257/, and the numbered forms
 * are the retired ones (majestic_tube_retired_2257_redirect_url() sends them
 * on). Everything else here is a word, and words are always available.
 *
 * The title of a legal document is not only a label. It is what the footer link
 * says, what a lookup by title searches for, and - on a site built by an earlier
 * release - what is already written in the database, because a page row keeps
 * the title it was created with. Renaming a document therefore cannot be "change
 * the key and move on": the old name has to keep finding the page that is
 * already there, or the next setup pass fails to recognize it and inserts a
 * second copy of a legal document. That is what former_titles records. It is an
 * admission that an existing page answers to its old name until the rename
 * lands, and majestic_tube_legal_page_former_titles() is the one place that list
 * is read, so a rename is described in one array rather than in every lookup.
 *
 * @return array<string, array{slug: string, legacy_slug?: string, former_titles?: string[], content: string}>
 */
function majestic_tube_legal_pages() {
	return array(
		'18 U.S.C. § 2257' => array(
			'slug'          => '18-usc-2257',
			'legacy_slug'   => '2257',
			'former_titles' => array( '18 USC 2257' ),
			'content'       => <<<'HTML'
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
 * same copy instead of inserting a second one. A lookup by title is the last
 * resort, for the copy a previous duplicate left behind under a suffixed slug
 * (2257-2) - finding it stops the third copy - and it searches the document's
 * former names as well as its current one, because the page a previous release
 * created is titled with the name that release used and nothing has renamed it
 * yet. That is what keeps a rename from looking like a missing page.
 *
 * @param array  $definition Legal page definition.
 * @param string $title      Page title, for the title lookup and the former
 *                           names it includes.
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
		foreach ( majestic_tube_legal_pages_by_title( $title, $definition ) as $by_title ) {
			if ( majestic_tube_page_is_viewable( $by_title ) ) {
				return $by_title;
			}
		}
	}

	return false;
}

/**
 * Every name a built-in legal document has ever been published under.
 *
 * The list is the document's current title followed by the titles it carried in
 * earlier releases. It is what makes a rename safe: a page row created before
 * the rename still answers to the old name, so the lookup that prevents a second
 * copy keeps finding it, and once the rename has landed the page answers to the
 * new name instead. Only names the theme itself shipped are listed - a title an
 * administrator typed is not in here, and is therefore never treated as the
 * theme's own to change.
 *
 * @param array $definition Legal page definition.
 * @return string[] Former titles, empty when the document was never renamed.
 */
function majestic_tube_legal_page_former_titles( $definition ) {
	if ( ! is_array( $definition ) || empty( $definition['former_titles'] ) ) {
		return array();
	}

	$former = array();

	foreach ( (array) $definition['former_titles'] as $title ) {
		$title = (string) $title;

		if ( '' !== $title ) {
			$former[] = $title;
		}
	}

	return $former;
}

/**
 * Every title one legal document answers to, its current name first.
 *
 * @param string $title      Current page title.
 * @param array  $definition Legal page definition.
 * @return string[] Titles, empty when neither argument names the document.
 */
function majestic_tube_legal_page_titles( $title, $definition = array() ) {
	$titles = array();

	foreach ( array_merge( array( (string) $title ), majestic_tube_legal_page_former_titles( $definition ) ) as $candidate ) {
		if ( '' !== $candidate && ! in_array( $candidate, $titles, true ) ) {
			$titles[] = $candidate;
		}
	}

	return $titles;
}

/**
 * Every page row carrying one of a document's titles, in any status.
 *
 * get_page_by_title() returns a single row, so a second live copy hiding
 * under a suffixed slug is invisible to it - which is exactly the duplicate
 * this machinery exists to find. An exact-title query returns them all.
 *
 * A document's former titles are searched in the same pass as its current one.
 * A page created before a rename is a copy of this document in every sense that
 * matters here, and a lookup that could not see it would report the document
 * missing and then insert the duplicate it exists to prevent. Rows come back
 * oldest first across every title, which is the order the single-title query
 * returned them in.
 *
 * @param string $title      Exact page title.
 * @param array  $definition Legal page definition, for the former names.
 * @return array<int, object> Page rows, oldest first.
 */
function majestic_tube_legal_pages_by_title( $title, $definition = array() ) {
	if ( ! function_exists( 'get_posts' ) ) {
		return array();
	}

	$rows = array();

	foreach ( majestic_tube_legal_page_titles( $title, $definition ) as $form ) {
		$found = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'trash' ),
				'title'          => $form,
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		foreach ( is_array( $found ) ? $found : array() as $row ) {
			if ( isset( $row->ID ) ) {
				$rows[ (int) $row->ID ] = $row;
			}
		}
	}

	ksort( $rows );

	return array_values( $rows );
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

	foreach ( majestic_tube_legal_pages_by_title( $title, $definition ) as $by_title ) {
		$take( $by_title );
	}

	return $copies;
}

/**
 * Put a single legal page back on the slug the theme links at.
 *
 * WordPress appends a number when a slug is already taken, so a slug like
 * 2257-2 is not a choice anyone made: it is the fingerprint of a second row
 * holding /2257/ when the page was inserted. The document works from that
 * address, but a page the theme created should not carry an address nobody
 * chose - and the address is wrong for the document too, since the canonical
 * one is where the footer, the sitemap and anything the operator wrote point.
 *
 * Only the name changes: the page ID, its content, its author, its comments and
 * every menu item pointing at it are untouched, and WordPress's own old-slug
 * redirect keeps the suffixed address working for anyone who has it.
 *
 * Two things stop it, both deliberate. A document with more than one live copy
 * is left alone until the administrator has settled which copy to keep, since
 * renaming onto a slug a second live page holds would simply mint the next
 * number; the status screen names the extra copy and offers to trash it, and
 * the pass after that repairs the slug. And a slug the administrator chose is
 * not the theme's to change: only the canonical slug, the legacy slug and a
 * numbered variant of the canonical slug are recognized as this document's own.
 *
 * @param string $title      Legal page title.
 * @param array  $definition Legal page definition.
 * @return int Page ID whose slug now sits on the canonical name, 0 when there
 *         was nothing to repair.
 */
function majestic_tube_repair_legal_slug( $title, $definition ) {
	if ( ! is_array( $definition ) || empty( $definition['slug'] ) || ! function_exists( 'wp_update_post' ) ) {
		return 0;
	}

	$canonical = (string) $definition['slug'];
	$copies    = majestic_tube_find_legal_copies( $definition, $title );

	// Two live copies, or none: not a repair this pass can make.
	if ( 1 !== count( $copies ) ) {
		return 0;
	}

	$page = reset( $copies );

	if ( ! $page || ! isset( $page->ID, $page->post_name ) ) {
		return 0;
	}

	$current = (string) $page->post_name;

	if ( $current === $canonical ) {
		return 0;
	}

	$legacy = ! empty( $definition['legacy_slug'] ) ? (string) $definition['legacy_slug'] : '';
	$own    = false;

	/*
	 * Either name this document has ever had, and any numbered copy of either:
	 * 18-usc-2257-2 is what WordPress would have made of a second attempt at
	 * the same slug, and 2257-2 is what it made of the retired numeric one.
	 */
	foreach ( array( $canonical, $legacy ) as $family ) {
		if ( '' === $family ) {
			continue;
		}

		if ( $current === $family || preg_match( '/^' . preg_quote( $family, '/' ) . '-\d+$/', $current ) ) {
			$own = true;
			break;
		}
	}

	if ( ! $own ) {
		return 0;
	}

	/*
	 * The canonical slug has to be free in every status, not just among live
	 * pages: a trashed or draft row still reserves it, and WordPress would
	 * answer this rename with 2257-2 - the very name being repaired.
	 */
	if ( get_page_by_path( $canonical ) ) {
		return 0;
	}

	$updated = wp_update_post(
		array(
			'ID'        => (int) $page->ID,
			'post_name' => $canonical,
		),
		true
	);

	if ( is_wp_error( $updated ) || ! $updated ) {
		return 0;
	}

	// Believe the stored row rather than the request: the next pass retries.
	$refreshed = get_post( (int) $page->ID );

	return ( $refreshed && isset( $refreshed->post_name ) && $canonical === $refreshed->post_name ) ? (int) $page->ID : 0;
}

/**
 * Put an existing legal page back on the name the theme gives it now.
 *
 * A page carries the title it was created with, and it keeps that title until
 * something renames it - so a document renamed in the theme is still titled
 * with its old name on every site that already exists. The title is not
 * cosmetic: it is what a visitor reads at the top of the policy, what the
 * footer link says, and part of what the lookup searching for the page was
 * looking for.
 *
 * Only the title changes: the page ID, its slug, its content, its author, its
 * comments and every menu item pointing at it stay exactly as they are.
 *
 * One thing stops it, and it is the same judgment the by-title lookup makes.
 * Only a name the theme itself shipped is the theme's to change; a title the
 * administrator chose is theirs. That is what former_titles is for - a page
 * whose title matches neither the current name nor a former one is left alone,
 * which is also what keeps this harmless on a site where an operator renamed
 * their own 2257 page deliberately.
 *
 * @param int    $page_id    Page ID.
 * @param string $title      Current page title.
 * @param array  $definition Legal page definition.
 * @return int Page ID once the title sits on the current name, 0 when there was
 *         nothing to repair.
 */
function majestic_tube_repair_legal_page_title( $page_id, $title, $definition ) {
	$title = (string) $title;

	if ( ! $page_id || '' === $title || ! function_exists( 'wp_update_post' ) ) {
		return 0;
	}

	$page = get_post( $page_id );

	if ( ! $page || ! isset( $page->ID, $page->post_title ) ) {
		return 0;
	}

	$current = (string) $page->post_title;

	if ( $current === $title ) {
		return 0;
	}

	if ( ! in_array( $current, majestic_tube_legal_page_former_titles( $definition ), true ) ) {
		return 0;
	}

	$updated = wp_update_post(
		array(
			'ID'         => (int) $page_id,
			'post_title' => $title,
		),
		true
	);

	if ( is_wp_error( $updated ) || ! $updated ) {
		return 0;
	}

	// Believe the stored row rather than the request: the next pass retries.
	$refreshed = get_post( (int) $page_id );

	return ( $refreshed && isset( $refreshed->post_title ) && $title === $refreshed->post_title ) ? (int) $page_id : 0;
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
 * Find the code, embeds and ad blocks inside a legal page.
 *
 * The theme withholds every widget area on a legal page, so nothing an
 * administrator placed in a Code and ads area reaches the document. Markup
 * saved *inside the page body* is the other door: an editor can paste an ad
 * tag, a Custom HTML block or an embed into the policy itself, and the theme
 * neither wrote it nor may take it out - the wording on that page belongs to
 * the administrator, and a theme that silently deleted it would be the worse
 * bug. This is the detector the two warnings below are built on, so the
 * guarantee stays honest rather than assumed.
 *
 * It looks for the shapes such markup actually arrives in: a script tag, an
 * embed or ad frame, a network ad marker, the editor blocks an ad is pasted
 * into, an advertising block from a plugin, and an ad or embed shortcode. It
 * reports what it found rather than only that something is there, so a screen
 * can say which page and which block.
 *
 * The one thing it leaves alone is what a page only quotes. Plenty of privacy
 * policies name the script they load - "we use Google's adsbygoogle.js" - and
 * a passage saying so is not an ad. A Core Code block is exactly that kind of
 * quotation, so its contents are left out of the marker search, which is why
 * the code sample in this comment does not flag this theme:
 *
 *     <!-- wp:code --><pre>adsbygoogle</pre><!-- /wp:code -->
 *
 * The search for markup that would actually run covers the whole page, code
 * block or not, because a raw tag hand-pasted between those two comments still
 * prints. The rule is to flag what runs and to leave what is quoted alone.
 *
 * @param string $content Page content.
 * @return string[] Kinds found, empty when the page is clean.
 */
function majestic_tube_legal_content_blocks( $content ) {
	if ( ! is_string( $content ) || '' === $content ) {
		return array();
	}

	/*
	 * The page with every Core Code block emptied, which is the text a visitor
	 * reads as prose rather than as live markup. Only the marker search below
	 * uses it; a tag is looked for everywhere.
	 */
	$unquoted = preg_replace( '#<!--\s*wp:code\b.*?<!--\s*/wp:code\s*-->#is', ' ', $content );
	$prose    = is_string( $unquoted ) ? $unquoted : $content;

	$patterns = array(
		'/<!--\s*wp:html\b/i'       => __( 'a Custom HTML block', 'majestic-tube' ),
		'/<!--\s*wp:embed\b/i'      => __( 'an Embed block', 'majestic-tube' ),
		'/<!--\s*wp:script\b/i'     => __( 'a Script block', 'majestic-tube' ),
		'/<script\b/i'              => __( 'a script tag', 'majestic-tube' ),
		'/<(?:iframe|embed|object|ins)\b/i' => __( 'an embed or ad frame', 'majestic-tube' ),
	);

	$markers = array(
		'/(?:\badsbygoogle\b|\bdata-ad[-_a-z]*\s*=)/i' => __( 'a network ad marker', 'majestic-tube' ),
		'/\[\s*(?:ads?|adsense|advert[a-z_]*|embed|iframe|google_ads[a-z_]*)\b[^\]]*\]/i' => __( 'an ad or embed shortcode', 'majestic-tube' ),
	);

	$found = array();

	foreach ( $patterns as $pattern => $label ) {
		if ( preg_match( $pattern, $content ) ) {
			$found[ $label ] = $label;
		}
	}

	foreach ( $markers as $pattern => $label ) {
		if ( preg_match( $pattern, $prose ) ) {
			$found[ $label ] = $label;
		}
	}

	/*
	 * A block from an advertising plugin is otherwise an ordinary-looking
	 * block: its name is the only clue. Every block name in the page is split
	 * into its words and checked against the ones those plugins use, which is
	 * why `wp:heading` is not an ad and `wp:ads/leaderboard` is.
	 */
	if ( preg_match_all( '/<!--\s*wp:([a-z0-9-]+(?:\/[a-z0-9-]+)?)/i', $prose, $blocks ) ) {
		$ad_words = array( 'ad', 'ads', 'advert', 'adverts', 'advertising', 'adsense', 'admanager', 'dfp', 'doubleclick' );

		foreach ( $blocks[1] as $block_name ) {
			foreach ( preg_split( '/[-_\/]/', strtolower( $block_name ) ) as $segment ) {
				if ( in_array( $segment, $ad_words, true ) ) {
					$found[ __( 'an advertising block', 'majestic-tube' ) ] = __( 'an advertising block', 'majestic-tube' );
					break 2;
				}
			}
		}
	}

	return array_values( $found );
}

/**
 * Legal pages whose body carries code, an embed or an ad block.
 *
 * Every live copy of a document is checked, so a duplicate that kept the ad
 * tag is reported even when the canonical page is clean.
 *
 * @return array<string, array{id: int, kinds: string[]}> Page title => the first
 *         flagged copy's ID, and what was found on it.
 */
function majestic_tube_legal_pages_with_code_blocks() {
	$flagged = array();

	foreach ( majestic_tube_legal_pages() as $title => $definition ) {
		foreach ( majestic_tube_find_legal_copies( $definition, $title ) as $page ) {
			$kinds = majestic_tube_legal_content_blocks( isset( $page->post_content ) ? $page->post_content : '' );

			if ( $kinds ) {
				$flagged[ $title ] = array(
					'id'    => (int) $page->ID,
					'kinds' => $kinds,
				);

				break;
			}
		}
	}

	return $flagged;
}

/**
 * The value a dismissal is remembered against.
 *
 * It changes only when the set of problems changes - a different page flagged,
 * or a different kind of block found on one - so an administrator who has read
 * the warning is left alone while the site is unchanged and is told again when
 * there is something new to tell. The page's modification date is deliberately
 * not part of it: editing a policy to fix its wording must not resurrect a
 * warning that has already been answered.
 *
 * @param array<string, array{id: int, kinds: string[]}> $flagged Flagged pages.
 * @return string
 */
function majestic_tube_legal_code_notice_key( $flagged ) {
	$parts = array();

	foreach ( (array) $flagged as $title => $page ) {
		$parts[] = (string) $title . ':' . (int) $page['id'] . ':' . implode( '+', (array) $page['kinds'] );
	}

	return md5( implode( '|', $parts ) );
}

/**
 * Whether this administrator has already dismissed the warning for this state.
 *
 * Kept per user, so one administrator reading it does not hide it from the
 * next one, and kept in user meta rather than an option, because a dismissal is
 * a decision about a person's attention rather than about the site.
 *
 * @param string $key Notice key.
 * @return bool
 */
function majestic_tube_legal_code_notice_is_dismissed( $key ) {
	if ( ! function_exists( 'get_user_meta' ) || ! function_exists( 'get_current_user_id' ) ) {
		return false;
	}

	return (string) get_user_meta( get_current_user_id(), 'majestic_tube_legal_code_notice', true ) === (string) $key;
}

/**
 * Repair a trashed, draft, or renamed legal-page copy instead of duplicating it.
 *
 * Deleting a page sends it to the trash, where the slug lookup cannot tell it
 * apart from a page that never existed - so the old code inserted a fresh
 * copy and the site ended up with two 18 U.S.C. § 2257 pages. Trashing is also
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

	foreach ( majestic_tube_legal_pages_by_title( $title, $definition ) as $by_title ) {
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
 * A page that exists in any form - live, trashed, draft, or on a retired slug
 * from an earlier release - is reused, never duplicated, and a page that does
 * not exist anywhere is created exactly once. The search runs by slug, by
 * retired slug, and by title - a title that means every name the document has
 * answered to, including the one an earlier release gave it - so a copy that
 * ended up on a numbered address (2257-2), or under a name the theme has since
 * changed, is still found rather than duplicated: the lookups below are the
 * only ones, so "does this document exist?" has a single answer. A page that is
 * found is also brought onto the document's current title, so the rename and
 * the lookup that has to survive it move together. A created page is explicitly
 * put on the default page template so it renders through page.php like any
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
			$page_id = majestic_tube_create_page( $title, 'default', $definition['content'], $definition['slug'] );
		}
	}

	if ( $page_id ) {
		majestic_tube_migrate_legal_page_content( $page_id );
		majestic_tube_repair_legal_slug( $title, $definition );
		majestic_tube_repair_legal_page_title( $page_id, $title, $definition );
	}

	return $page_id;
}

/**
 * Put an existing legal page back in step with the shipped copy.
 *
 * Two things happen here and nothing else does: the legacy theme contact
 * placeholders are replaced with the current one, and the retired template
 * notice is removed. Administrator-authored wording and all unrelated content
 * are left intact. A render-time filter below adds the dynamic contact line
 * when an edited page has no theme contact shortcode at all.
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

	$updated = majestic_tube_strip_template_notice( $updated );

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
 * Remove the retired "Template notice" paragraph from a legal page.
 *
 * Every legal page this theme used to create ended with a bolded paragraph
 * beginning "Template notice:". It is no longer part of the shipped copy (see
 * majestic_tube_legal_pages()), because it printed inside the page body where
 * a visitor reads it. An install that already published one of those pages
 * still has the old markup stored, since the theme never rewrites a page an
 * administrator has edited.
 *
 * Only that paragraph is removed - and the `<hr />` that separated it from the
 * policy above it - so wording the administrator wrote around it survives. The
 * whole-content search means a page with no notice at all is returned byte for
 * byte, which is what keeps this a no-op on every page that never had one.
 *
 * @param string $content Page content.
 * @return string
 */
function majestic_tube_strip_template_notice( $content ) {
	if ( ! is_string( $content ) || false === strpos( $content, '<strong>Template notice:</strong>' ) ) {
		return is_string( $content ) ? $content : '';
	}

	$stripped = preg_replace(
		'#(?:\s*<hr\s*/?>)?\s*<p>\s*<strong>Template notice:</strong>.*?</p>#is',
		'',
		$content
	);

	return is_string( $stripped ) ? $stripped : $content;
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
 * majestic_tube_is_legal_page() asks the same question for the widget areas,
 * so "is this a legal document" has one answer for the whole theme.
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
 * Whether this request is rendering one of the theme's legal documents.
 *
 * A legal page is a document a visitor may be shown, or asked to accept, on
 * its own terms: a policy, a takedown procedure, a recordkeeping notice. Ad or
 * tracking markup placed around it belongs to whoever placed it and is not the
 * site's own wording, so on these pages the theme prints none of it - the
 * document is the page. Everything the theme itself prints (header, menus,
 * footer legal links, copyright) is untouched; only the widget areas are
 * withheld.
 *
 * The lookup is the same one the placeholder filters use, so "legal page" has
 * a single definition everywhere in the theme.
 *
 * @return bool
 */
function majestic_tube_is_legal_page() {
	return (bool) majestic_tube_current_legal_document();
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
 * Return the canonical destination for a retired 2257 address.
 *
 * The document lives on /18-usc-2257/, because WordPress will not publish a
 * page on a numeric slug - wp_unique_post_slug() reserves numbers for the
 * pagination it generates itself. Every numbered address (2257, 2257-2, 2257-3)
 * therefore comes from a release that asked for one and was handed something
 * else, and each of them is answered here rather than left serving a second
 * copy of the policy or a 404 for a link somebody published.
 *
 * Three things are deliberately not redirected. The document answering its own
 * address - which on a site that has not repaired yet is the numbered one - or
 * the redirect would be a loop. A page with an unrelated title that happens to
 * sit on a retired address: that page belongs to whoever made it. And a request
 * when no 2257 document exists at all, since there is then nothing to point at.
 *
 * @return string Canonical permalink, or an empty string when not applicable.
 */
function majestic_tube_retired_2257_redirect_url() {
	$request_uri   = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$requested     = '';
	$requested_row = false;

	if ( is_string( $request_uri ) && '' !== $request_uri ) {
		$path = wp_parse_url( $request_uri, PHP_URL_PATH );

		if ( is_string( $path ) && preg_match( '#(?:^|/)((?:18-usc-)?2257(?:-\d+)?)/?$#i', $path, $match ) ) {
			$requested     = strtolower( $match[1] );
			$requested_row = get_page_by_path( $requested );
		}
	}

	// Also cover plain-permalink requests such as ?page_id=123.
	if ( '' === $requested && function_exists( 'get_query_var' ) ) {
		$requested_id = absint( get_query_var( 'page_id' ) );
		$candidate    = $requested_id ? get_post( $requested_id ) : false;

		if ( $candidate && isset( $candidate->post_name ) && preg_match( '/^(?:18-usc-)?2257(?:-\d+)?$/i', (string) $candidate->post_name ) ) {
			$requested     = strtolower( (string) $candidate->post_name );
			$requested_row = $candidate;
		}
	}

	if ( '' === $requested || ! function_exists( 'get_permalink' ) ) {
		return '';
	}

	$title      = '18 U.S.C. § 2257';
	$pages      = majestic_tube_legal_pages();
	$definition = isset( $pages[ $title ] ) ? $pages[ $title ] : array();
	$canonical  = $definition ? majestic_tube_find_legal_page( $definition, $title ) : false;

	if ( ! $canonical || ! isset( $canonical->ID, $canonical->post_name ) ) {
		return '';
	}

	// The document is already on the address being asked for: this is the page.
	if ( $requested === strtolower( (string) $canonical->post_name ) ) {
		return '';
	}

	/*
	 * A different row answering the address is only redirected when it is a
	 * copy of this same document; anything else there is not the theme's.
	 */
	if ( $requested_row && isset( $requested_row->ID ) && (int) $requested_row->ID !== (int) $canonical->ID ) {
		$same_document = false;

		foreach ( majestic_tube_legal_pages_by_title( $title, $definition ) as $row ) {
			if ( isset( $row->ID ) && (int) $row->ID === (int) $requested_row->ID ) {
				$same_document = true;
				break;
			}
		}

		if ( ! $same_document ) {
			return '';
		}
	}

	return (string) get_permalink( (int) $canonical->ID );
}

/**
 * Send a retired numeric 2257 address to the document's own address.
 *
 * @return void
 */
function majestic_tube_retired_2257_redirect() {
	if ( ( function_exists( 'is_admin' ) && is_admin() ) || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
		return;
	}

	$target = majestic_tube_retired_2257_redirect_url();

	if ( ! $target || ! function_exists( 'wp_safe_redirect' ) ) {
		return;
	}

	wp_safe_redirect( $target, 301 );
	exit;
}
add_action( 'template_redirect', 'majestic_tube_retired_2257_redirect', 1 );

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

	/*
	 * A stock WordPress install already contains a "Privacy Policy" page that
	 * core created as a **draft** and pointed wp_page_for_privacy_policy at.
	 * The theme adopts that page rather than duplicating it, but adoption used
	 * to leave it a draft - and the footer legal menu then linked a document
	 * visitors cannot open. Publishing it here is what makes that link live.
	 *
	 * The publish is deliberately limited to a site whose setup has never
	 * completed. On a later revision bump the marker is already set, so a page
	 * an administrator has since returned to draft is reused untouched, exactly
	 * as before.
	 */
	$publish_unviewable = 0 === (int) get_option( 'majestic_tube_setup_revision', 0 );

	foreach ( majestic_tube_legal_pages() as $title => $page ) {
		$page_id = majestic_tube_get_or_create_legal_page( $title, $page, $publish_unviewable );

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
 * a site that genuinely cannot create pages stops retrying. Revision 7 is the
 * release that renamed the 2257 document, so the bump is how a page created
 * under the old name is brought onto the new one without the administrator
 * having to touch anything.
 *
 * @return int
 */
function majestic_tube_setup_revision() {
	/**
	 * Filter the revision of the automatic site setup.
	 *
	 * @param int $revision Current revision.
	 */
	return (int) apply_filters( 'majestic_tube_setup_revision', 7 );
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
 * An item that is already there keeps its place and its own label. The one label
 * this touches is one an earlier release of this function wrote under a name the
 * document no longer uses, so the footer does not keep reading an old name after
 * the document itself has been renamed.
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
	$canonical_definitions = array();

	/*
	 * Resolve the page each footer link should point at. find_legal_page() is
	 * deliberate here rather than get_legal_page(): it also falls back to an
	 * exact title match, so a page that ended up on a suffixed slug is still
	 * linked. That is precisely how the 18 U.S.C. § 2257 link went missing - an
	 * earlier duplicate pushed the page to 2257-2, get_legal_page() looked only
	 * at the canonical slug, found nothing, and skipped it for good. The page
	 * was present the whole time, and the Pages list showed it.
	 */
	foreach ( majestic_tube_legal_pages() as $title => $legal_page ) {
		$page = majestic_tube_find_legal_page( $legal_page, $title );

		if ( ! $page || ! isset( $page->ID ) ) {
			continue;
		}

		$canonical_id = (int) $page->ID;
		$canonical_ids[ $canonical_id ] = $title;
		$canonical_definitions[ $canonical_id ] = $legal_page;

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
	// duplicate legacy items. Labels are left alone here, including a custom one
	// on the surviving item; the only label rewritten in this function is one of
	// the theme's own former names, which the pass at the end of the loop does.
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

		if ( isset( $canonical_ids[ $item_id ], $canonical_definitions[ $item_id ] ) ) {
			majestic_tube_repair_legal_menu_item_label( $menu_id, $item, $canonical_ids[ $item_id ], $canonical_definitions[ $item_id ] );
		}
	}

	foreach ( majestic_tube_legal_pages() as $title => $legal_page ) {
		// Same resolve as above, so a page on a suffixed slug is linked here
		// rather than silently dropped from the footer menu.
		$page = majestic_tube_find_legal_page( $legal_page, $title );

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
 * Bring a footer legal link's label onto the document's current name.
 *
 * The label a link shows is the title the theme gave the item when it was
 * created, and an item created before a rename keeps it - so the footer would
 * read "18 USC 2257" while the page it points at, and every link created since,
 * reads "18 U.S.C. § 2257". The link itself is not touched, only the label.
 *
 * The same judgment as the page title repair applies, for the same reason. Only
 * a label that is one of the document's former names was written by the theme;
 * anything else, including a deliberate "2257", was typed by an administrator
 * and is left exactly as it is.
 *
 * @param int    $menu_id    Menu term ID.
 * @param object $item       Existing menu item.
 * @param string $title      Current document title.
 * @param array  $definition Legal page definition.
 * @return bool Whether the label was rewritten.
 */
function majestic_tube_repair_legal_menu_item_label( $menu_id, $item, $title, $definition ) {
	if ( ! is_object( $item ) || ! isset( $item->ID, $item->title ) || ! function_exists( 'wp_update_nav_menu_item' ) ) {
		return false;
	}

	$label = (string) $item->title;
	$title = (string) $title;

	if ( '' === $label || $label === $title ) {
		return false;
	}

	if ( ! in_array( $label, majestic_tube_legal_page_former_titles( $definition ), true ) ) {
		return false;
	}

	$updated = majestic_tube_update_page_menu_item(
		$menu_id,
		$title,
		isset( $item->object_id ) ? (int) $item->object_id : 0,
		(int) $item->ID
	);

	return ! is_wp_error( $updated ) && (bool) $updated;
}

/**
 * Remove top-level main-menu items that point at an account page.
 *
 * Only an item that is both top level and targeted at the Submit a Video or
 * Profile page is removed, because those are the theme's own defaults and they
 * duplicate the My Account dropdown. Anything nested, or aimed anywhere else,
 * is left exactly as the administrator arranged it.
 *
 * @param int         $menu_id Menu term ID.
 * @param array|null  $items   Existing items, or null to fetch them.
 * @return int How many items were removed.
 */
function majestic_tube_remove_top_level_account_items( $menu_id, $items = null ) {
	$items = is_array( $items ) ? $items : wp_get_nav_menu_items( $menu_id );
	$items = is_array( $items ) ? $items : array();
	$ids   = array();

	foreach ( majestic_tube_account_page_titles() as $title ) {
		$page = get_page_by_path( sanitize_title( $title ) );

		if ( $page && isset( $page->ID ) ) {
			$ids[] = (int) $page->ID;
		}
	}

	if ( ! $ids ) {
		return 0;
	}

	$removed = 0;

	foreach ( $items as $item ) {
		if ( ! isset( $item->type, $item->object_id, $item->ID ) ) {
			continue;
		}

		if ( 'post_type' !== $item->type || 0 !== (int) $item->menu_item_parent ) {
			continue;
		}

		if ( in_array( (int) $item->object_id, $ids, true ) && function_exists( 'wp_delete_post' ) ) {
			wp_delete_post( (int) $item->ID, true );
			++$removed;
		}
	}

	return $removed;
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

	// Home plus the three directory pages belong on the main header menu.
	// Submit a Video and Profile do not: they are account pages, and the theme
	// prints them inside the My Account dropdown at render time, so a top-level
	// item is a second link to the same destination. Pages that are still
	// missing are skipped here; the setup creates them first, and the next run
	// links whatever appears.
	$account_pages = majestic_tube_account_page_titles();

	foreach ( majestic_tube_activation_pages() as $menu_title => $menu_template ) {
		if ( in_array( $menu_title, $account_pages, true ) ) {
			continue;
		}

		$item_page = get_page_by_path( sanitize_title( $menu_title ) );

		if ( ! majestic_tube_page_is_viewable( $item_page ) || in_array( (int) $item_page->ID, $have_ids, true ) ) {
			continue;
		}

		majestic_tube_update_page_menu_item( $menu_id, $menu_title, $item_page->ID );
		$have_ids[] = (int) $item_page->ID;
	}

	// A site activated before this rule kept the top-level copies. Remove a
	// top-level item that points at either account page; items nested under
	// something else are left alone, so an administrator who deliberately
	// placed them is not disturbed.
	majestic_tube_remove_top_level_account_items( $menu_id, $existing );

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
	return (int) apply_filters( 'majestic_tube_footer_legal_revision', 3 );
}

/**
 * Make sure every legal page exists and sits in the assigned footer menu.
 *
 * The full site setup only runs once per revision, so a legal page that was
 * created later, a page whose slug was migrated, or a footer menu item that
 * was deleted by hand was never repaired: the link simply stayed missing for
 * good. That is how the 18 U.S.C. § 2257 page ended up unlinked while DMCA and the
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

	/*
	 * $publish_unviewable is true here, unlike the background setup, because a
	 * live footer link is the whole reason this repair exists: a page that is
	 * still a draft - most often WordPress's own stock Privacy Policy page,
	 * which core creates as a draft and the theme then adopts - is a link
	 * visitors cannot open. Publishing it is what makes the footer link real.
	 */
	foreach ( majestic_tube_legal_pages() as $title => $definition ) {
		if ( ! majestic_tube_get_or_create_legal_page( $title, $definition, true ) ) {
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

					if ( $trashed ) {
						// With the extra copy out of the way the document can go back
						// on its own slug: the duplicate holding that slug is exactly
						// why the remaining page was left on a suffixed one.
						majestic_tube_repair_legal_slug( $title, $definition );
					}

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
 * Warn on the dashboard when a legal page carries code, an embed or an ad.
 *
 * The welcome screen repeats this beside the page list, but the dashboard is
 * where an administrator actually lands, so the warning starts there and names
 * the pages it is about. It is dismissible per user and per state - see
 * majestic_tube_legal_code_notice_key() - and it never changes a page: the
 * words on a legal page belong to the administrator, and a theme that quietly
 * deleted them would be the worse bug.
 *
 * The theme's own widget areas are already withheld on a legal page; this is
 * the other door, the markup an editor pastes into the policy itself.
 *
 * @return void
 */
function majestic_tube_legal_code_notice() {
	if ( ! is_admin() || ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen || 'dashboard' !== $screen->id ) {
		return;
	}

	$flagged = majestic_tube_legal_pages_with_code_blocks();

	if ( ! $flagged ) {
		return;
	}

	$key = majestic_tube_legal_code_notice_key( $flagged );

	if ( majestic_tube_legal_code_notice_is_dismissed( $key ) ) {
		return;
	}

	$named = array();

	foreach ( $flagged as $title => $page ) {
		$link = get_edit_post_link( $page['id'] );

		$named[] = sprintf(
			'%s (%s)',
			$link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ),
			esc_html( implode( ', ', $page['kinds'] ) )
		);
	}

	$dismiss = wp_nonce_url(
		add_query_arg( 'majestic-tube-legal-notice', 'dismiss', admin_url( 'index.php' ) ),
		'majestic_tube_legal_code_notice'
	);
	?>
	<div class="notice notice-warning">
		<p>
			<strong><?php esc_html_e( 'Majestic Tube:', 'majestic-tube' ); ?></strong>
			<?php
			printf(
				/* translators: %s: comma-separated list of legal pages and what each one contains. */
				esc_html__( 'A legal page carries code, an embed or an ad block: %s.', 'majestic-tube' ),
				implode( ', ', $named ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each entry escaped where it was built.
			);
			?>
			<?php esc_html_e( 'The theme prints none of its widget areas on a legal page, but markup saved inside the page body still prints inside the document a visitor is reading, where it reads as the site’s own wording. Remove it from the page, or move it to a Code and ads area.', 'majestic-tube' ); ?>
		</p>
		<p>
			<a class="button button-secondary" href="<?php echo esc_url( $dismiss ); ?>">
				<?php esc_html_e( 'Dismiss this warning', 'majestic-tube' ); ?>
			</a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'majestic_tube_legal_code_notice' );

/**
 * Remember that this administrator has read the warning.
 *
 * The dismissal is stored against the state of the pages rather than as a
 * plain "seen it" flag, so a site whose legal pages are left alone is left
 * alone, and one where a different page or a different kind of block turns up
 * is told again. It is user meta, not an option: a dismissal is a decision
 * about one person's attention, and hiding the warning from the next
 * administrator would be the wrong kind of silence.
 *
 * @return void
 */
function majestic_tube_handle_legal_code_notice_dismissal() {
	if ( ! isset( $_GET['majestic-tube-legal-notice'] ) || 'dismiss' !== $_GET['majestic-tube-legal-notice'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is verified below.
		return;
	}

	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die(
			esc_html__( 'You are not allowed to dismiss this warning.', 'majestic-tube' ),
			esc_html__( 'Legal pages', 'majestic-tube' ),
			array( 'response' => 403 )
		);
	}

	check_admin_referer( 'majestic_tube_legal_code_notice' );

	update_user_meta(
		get_current_user_id(),
		'majestic_tube_legal_code_notice',
		majestic_tube_legal_code_notice_key( majestic_tube_legal_pages_with_code_blocks() )
	);

	$back = wp_get_referer();

	wp_safe_redirect( $back ? remove_query_arg( array( 'majestic-tube-legal-notice', '_wpnonce' ), $back ) : admin_url( 'index.php' ) );
	exit;
}
add_action( 'admin_init', 'majestic_tube_handle_legal_code_notice_dismissal', 4 );

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
	$legal_code     = majestic_tube_legal_pages_with_code_blocks();
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

			<?php if ( $legal_code ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<strong><?php esc_html_e( 'A legal page carries code, an embed or an ad block.', 'majestic-tube' ); ?></strong>
						<?php esc_html_e( 'The theme prints none of its widget areas on a legal page, but markup saved inside the page body still prints inside the document a visitor is reading, where it reads as the site’s own wording. Remove it from the page, and place it in a Code and ads area instead if you still need it.', 'majestic-tube' ); ?>
						<?php esc_html_e( 'The theme never edits content you have written, so this one is yours to change.', 'majestic-tube' ); ?>
					</p>
					<ul style="list-style: disc; padding-left: 1.5em;">
						<?php foreach ( $legal_code as $legal_code_title => $legal_code_page ) : ?>
							<li>
								<a href="<?php echo esc_url( (string) get_edit_post_link( $legal_code_page['id'] ) ); ?>"><?php echo esc_html( $legal_code_title ); ?></a>:
								<?php echo esc_html( implode( ', ', $legal_code_page['kinds'] ) ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
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
							<?php if ( isset( $legal_code[ $legal_title ] ) ) : ?>
								<br />
								<em><?php echo esc_html( sprintf( /* translators: %s: what was found in the page. */ __( 'Contains %s', 'majestic-tube' ), implode( ', ', $legal_code[ $legal_title ]['kinds'] ) ) ); ?></em>
							<?php endif; ?>
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