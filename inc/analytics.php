<?php
/**
 * Shared analytics layer for views, likes and reports.
 *
 * One place that reads and writes the three per-video counters the theme shows
 * everywhere (cards, the single view, the schema block, the AJAX responses and
 * the admin report screens) plus the two site-wide report aggregates.
 *
 * Why this exists: every one of those surfaces used to call get_post_meta()
 * directly and independently, so there was no single place to cache or
 * invalidate the counters. This module reads all of a post's counters in ONE
 * get_post_meta() call and memoises the result in the object cache.
 *
 * Honest accounting of the saving, because it is easy to overstate:
 *   - WordPress already primes the meta cache for every post in the main query,
 *     so the old per-key reads were cache hits rather than separate queries.
 *     Batching them cuts the meta API calls 5:1 (150 -> 30 on a 30-card
 *     archive), which is a modest CPU win, not a query-count win.
 *   - The real query win is the reported-videos aggregates, which are a
 *     meta_query join over wp_postmeta. The object cache now fronts their
 *     transients, so the admin bar costs no query at all on a warm cache
 *     instead of a transient read.
 *   - With a persistent object cache (Redis/Memcached) the per-post bundle is
 *     served without touching the meta cache at all. Without one, wp_cache_*
 *     is request-scoped and the benefit is limited to request memoisation.
 *   - The durable benefit is correctness: one invalidation point, and the meta
 *     hooks below that keep it correct for writes made outside this theme.
 *
 * Correctness rules that must not be broken:
 *   1. The stored contract is unchanged - `post_views_count`, `likes_count`,
 *      `dislikes_count`, `rate`, `reported_count` keep their exact names and
 *      meanings, and the reads below are the only ones that should touch them.
 *   2. Every write invalidates the cache for that post. Writes funnel through
 *      majestic_tube_analytics_* helpers or call the flush explicitly.
 *   3. The view counter stays atomic (a single UPDATE in SQL). Caching a
 *      read-modify-write would reintroduce the lost-update race that the SQL
 *      increment exists to prevent, so the increment deliberately bypasses
 *      the cache and repopulates it with the value the database reports.
 *   4. Reads are tolerant of a missing row: an absent counter is 0, never an
 *      error, which is what the bare get_post_meta() calls did before.
 *
 * The meta hooks at the bottom of this file make rule 2 hold even for writes
 * that come from outside the theme (an importer, or the original theme's own
 * AJAX endpoints running alongside this one).
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Object cache group for analytics values.
 *
 * A dedicated group (rather than the `post_meta` group) means a flush for one
 * video cannot evict unrelated cached data, and the whole group can be
 * invalidated in one call when a counter is edited by hand.
 */
const MAJESTIC_TUBE_ANALYTICS_GROUP = 'majestic_tube_analytics';

/**
 * The visitor's IP address, for rate limiting and de-duplication.
 *
 * REMOTE_ADDR is the only value used directly, because every other
 * client-identifying header (X-Forwarded-For, CF-Connecting-IP,
 * True-Client-IP, ...) is attacker-controlled and must only be trusted when a
 * proxy in front of the site sets it. Sites behind a CDN or reverse proxy MUST
 * hook the filter below, otherwise REMOTE_ADDR is the proxy's address and every
 * visitor collapses into a single identity - which, for view de-duplication,
 * would mean only the first visitor ever registers a view.
 *
 * @return string Validated IP address, or an empty string when unknown.
 */
function majestic_tube_get_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	// Guard against a spoofed or malformed value reaching storage.
	if ( '' === $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		$ip = '';
	}

	/**
	 * Filter the client IP used for rate limiting and de-duplication.
	 *
	 * Return a trusted header's value here when the site sits behind a proxy
	 * that overwrites (not appends to) the forwarding headers.
	 *
	 * @param string $ip Validated REMOTE_ADDR, or an empty string.
	 */
	$filtered = apply_filters( 'majestic_tube_client_ip', $ip );

	return ( is_string( $filtered ) && filter_var( $filtered, FILTER_VALIDATE_IP ) ) ? $filtered : $ip;
}

/**
 * A stable, non-reversible stand-in for a visitor address.
 *
 * Every place the theme remembers a visitor stores this instead of the address
 * itself: the theme has to recognise a returning visitor, not know where they
 * are. The hash is keyed with the site's auth salt, which lives in wp-config.php
 * and never in the database, so a copy of wp_postmeta or wp_options contains no
 * address list that can be turned back into addresses without the server's
 * secrets.
 *
 * The address is canonicalised through inet_pton() first, so the several legal
 * spellings of one IPv6 address (::1, 0:0:0:0:0:0:0:1, ::0001) cannot each slip
 * past a de-duplication window as though they were different visitors.
 *
 * Two consequences of being keyed and therefore only as stable as the salt:
 * regenerating the salts in wp-config.php changes every fingerprint, so past
 * votes and reports stop matching and each visitor may act once more; and a
 * row written under the old salt stops being recognisable at all, which is why
 * the cleanup below drops anything that is neither an address nor one of these.
 * Rotating salts is worth that much, but it should be a decision rather than a
 * surprise.
 *
 * The result is pseudonymous, not anonymous - one address always yields one
 * fingerprint - so it remains personal data under GDPR. That is why the history
 * expires instead of accumulating.
 *
 * @param string $ip Validated client IP.
 * @return string 32 lowercase hex characters, or an empty string when there is no usable address.
 */
function majestic_tube_ip_fingerprint( $ip ) {
	$ip = is_string( $ip ) ? trim( $ip ) : '';

	if ( '' === $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return '';
	}

	// inet_pton() returns false, quietly, for anything it cannot parse.
	$packed = inet_pton( $ip );
	$source = false === $packed ? $ip : $packed;

	/**
	 * Filter the stored stand-in for a visitor address.
	 *
	 * Return a stable value of your own - it is used as an array key in post
	 * meta and must not contain anything a meta value can misread.
	 *
	 * @param string $fingerprint 32 hex characters.
	 * @param string $ip          The validated address being fingerprinted.
	 */
	return substr( hash_hmac( 'sha256', $source, wp_salt( 'auth' ) ), 0, 32 );
}

/**
 * Whether a stored identity is already one of our fingerprints.
 *
 * Used to tell our own keys from the raw addresses older releases stored.
 *
 * @param mixed $value Stored array key.
 * @return bool
 */
function majestic_tube_is_fingerprint( $value ) {
	return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{32}$/', $value );
}

/**
 * The most recent visitors one video's IP history may hold.
 *
 * A busy video can be voted on from thousands of addresses. Past this many the
 * oldest entries go, so one meta row cannot grow without bound.
 */
const MAJESTIC_TUBE_IP_HISTORY_MAX = 500;

/**
 * The meta keys that hold per-visitor history, and the window each one keeps.
 *
 * One place for both, so the check and the write cannot drift apart the way two
 * independently hardcoded DAY_IN_SECONDS constants eventually would.
 *
 * @return array<string,int>
 */
function majestic_tube_ip_history_windows() {
	$windows = array(
		'voted_IP'     => DAY_IN_SECONDS,
		'reported_ips' => DAY_IN_SECONDS,
	);

	/**
	 * Filter how long a visitor stays on a video's per-visitor history.
	 *
	 * @param array<string,int> $windows Meta key => window in seconds.
	 */
	$windows = (array) apply_filters( 'majestic_tube_ip_history_windows', $windows );

	$resolved = array();

	foreach ( array( 'voted_IP', 'reported_ips' ) as $meta_key ) {
		$resolved[ $meta_key ] = isset( $windows[ $meta_key ] ) ? max( 1, absint( $windows[ $meta_key ] ) ) : DAY_IN_SECONDS;
	}

	return $resolved;
}

/**
 * How long one IP history meta key keeps its entries.
 *
 * @param string $meta_key IP history meta key.
 * @return int Seconds.
 */
function majestic_tube_ip_history_window( $meta_key ) {
	$windows = majestic_tube_ip_history_windows();

	return isset( $windows[ $meta_key ] ) ? $windows[ $meta_key ] : DAY_IN_SECONDS;
}

/**
 * Drop what has expired, re-hash what an older release stored raw, and cap.
 *
 * Shared by the write path and the scheduled sweep so the rules cannot differ.
 *
 * Rows written before keyed hashing held addresses verbatim. They are rehashed
 * here rather than dropped, which does two things at once: a visitor who has
 * already voted inside this window stays recognised instead of getting a
 * second vote, and the plain-text address leaves the database the next time
 * anyone acts on that video.
 *
 * @param array  $stored      History as read from post meta.
 * @param int    $window      Window in seconds.
 * @param int    $now         Timestamp to measure against. Defaults to now.
 * @param string $fingerprint Optional visitor to record at the end.
 * @param int    $stamp       Optional timestamp for that visitor.
 * @return array<string,int>
 */
function majestic_tube_clean_ip_history( $stored, $window, $now = 0, $fingerprint = '', $stamp = 0 ) {
	$now     = $now > 0 ? (int) $now : time();
	$window  = max( 1, absint( $window ) );
	$history = array();

	foreach ( (array) $stored as $identity => $timestamp ) {
		$timestamp = (int) $timestamp;

		// Expiry: past its window an entry is deleted, not archived.
		if ( $now - $timestamp >= $window ) {
			continue;
		}

		$key = ( is_string( $identity ) && majestic_tube_is_fingerprint( $identity ) )
			? $identity
			: majestic_tube_ip_fingerprint( $identity );

		// Neither an address nor one of ours: junk, so it goes too.
		if ( '' === $key ) {
			continue;
		}

		$history[ $key ] = $timestamp;
	}

	if ( '' !== $fingerprint ) {
		$history[ $fingerprint ] = $stamp > 0 ? (int) $stamp : $now;
	}

	if ( count( $history ) > MAJESTIC_TUBE_IP_HISTORY_MAX ) {
		// Oldest first, so the tail is the most recent and survives.
		asort( $history );
		$history = array_slice( $history, -MAJESTIC_TUBE_IP_HISTORY_MAX, null, true );
	}

	return $history;
}

/**
 * Read the current visitor's timestamp from a post meta IP history.
 *
 * Vote and report de-duplication intentionally store different meta keys, but
 * both use the same identity and time-window rules. Keeping that algorithm in
 * one place prevents the two endpoints from drifting while preserving their
 * separate stored keys.
 *
 * The lookup is by fingerprint, so nothing here needs the stored value to be a
 * readable address, and the same key survives if the salt is ever rotated only
 * in the sense that it stops matching - never that it starts leaking.
 *
 * @param int    $post_id  Post ID.
 * @param string $meta_key IP-history meta key.
 * @param int    $window   Window in seconds. 0 uses the key's own window.
 * @return bool
 */
function majestic_tube_ip_was_recorded_recently( $post_id, $meta_key, $window = 0 ) {
	$fingerprint = majestic_tube_ip_fingerprint( majestic_tube_get_client_ip() );

	if ( '' === $fingerprint ) {
		return false;
	}

	$history = get_post_meta( absint( $post_id ), $meta_key, true );

	if ( ! is_array( $history ) || ! isset( $history[ $fingerprint ] ) ) {
		return false;
	}

	$window = $window > 0 ? max( 1, absint( $window ) ) : majestic_tube_ip_history_window( $meta_key );

	return ( time() - (int) $history[ $fingerprint ] ) < $window;
}

/**
 * Record the current visitor in a post meta IP history.
 *
 * Writes a keyed fingerprint rather than the address, and takes the chance to
 * expire the row: every write drops what has aged out, converts any raw address
 * an older release left behind, and caps the size.
 *
 * @param int    $post_id  Post ID.
 * @param string $meta_key IP-history meta key.
 * @param int    $window   Window in seconds. 0 uses the key's own window.
 * @return bool Whether the history was written.
 */
function majestic_tube_record_ip_history( $post_id, $meta_key, $window = 0 ) {
	$post_id     = absint( $post_id );
	$fingerprint = majestic_tube_ip_fingerprint( majestic_tube_get_client_ip() );

	if ( ! $post_id || '' === $fingerprint ) {
		return false;
	}

	$window = $window > 0 ? max( 1, absint( $window ) ) : majestic_tube_ip_history_window( $meta_key );
	$stored = get_post_meta( $post_id, $meta_key, true );

	$history = majestic_tube_clean_ip_history( $stored, $window, 0, $fingerprint );

	// An emptied row is deleted rather than kept as an empty array: the point
	// of the sweep is that the data stops existing, not that it stops being read.
	if ( $history ) {
		update_post_meta( $post_id, $meta_key, $history );
	} else {
		delete_post_meta( $post_id, $meta_key );
	}

	return true;
}

/**
 * Whether view de-duplication is active.
 *
 * @return bool
 */
function majestic_tube_view_dedup_enabled() {
	/**
	 * Filter whether a visitor may count one view per video per window.
	 *
	 * @param bool $enabled Defaults to true.
	 */
	return (bool) apply_filters( 'majestic_tube_view_dedup_enabled', true );
}

/**
 * How long one view per visitor per video is remembered, in seconds.
 *
 * @return int
 */
function majestic_tube_view_dedup_window() {
	/**
	 * Filter the view de-duplication window.
	 *
	 * @param int $window Seconds. Defaults to 24 hours, matching the window the
	 *                    original theme used for vote de-duplication.
	 */
	$window = (int) apply_filters( 'majestic_tube_view_dedup_window', DAY_IN_SECONDS );

	return $window > 0 ? $window : DAY_IN_SECONDS;
}

/**
 * Transient name holding one visitor's recent view history.
 *
 * The address is hashed rather than stored in the option name: transient names
 * are visible in wp_options, and a plain address there is personal data that
 * also invites prefix collisions. The same keyed fingerprint the post meta
 * history uses applies here, so the one visitor identity is the one the whole
 * theme recognises. Views written under the old md5 key are simply not found
 * and age out of wp_options on their own.
 *
 * @param string $ip Validated client IP.
 * @return string
 */
function majestic_tube_view_dedup_key( $ip ) {
	$fingerprint = majestic_tube_ip_fingerprint( $ip );

	// No usable address means the caller has already bailed out before here;
	// the fallback only keeps the function total.
	if ( '' === $fingerprint ) {
		return 'mtviews_' . md5( (string) $ip );
	}

	return 'mtviews_' . $fingerprint;
}

/**
 * The most recent videos one visitor's history is allowed to hold.
 *
 * Bounds the array for a visitor who browses a lot of videos, so the transient
 * cannot grow without limit. Oldest entries are dropped first.
 */
const MAJESTIC_TUBE_VIEW_DEDUP_MAX = 200;

/**
 * Record that this visitor has viewed a video, or report that they already have.
 *
 * Best effort by design: two simultaneous requests for the same video can both
 * pass the check, so a determined flood can still add a view or two. That is
 * an acceptable trade for avoiding a lock on the hottest write in the theme,
 * and it is still a large improvement over counting every request.
 *
 * @param int $post_id Post ID.
 * @return bool True when this view was already counted inside the window.
 */
function majestic_tube_visitor_already_counted_view( $post_id ) {
	if ( ! majestic_tube_view_dedup_enabled() ) {
		return false;
	}

	$post_id = absint( $post_id );
	$ip      = majestic_tube_get_client_ip();

	// Without a usable address there is nothing to de-duplicate against.
	// Counting the view is the safe failure mode: refusing to count would lose
	// legitimate views from misconfigured proxies, which is worse than the
	// inflation this guards against.
	if ( ! $post_id || '' === $ip ) {
		return false;
	}

	$key   = majestic_tube_view_dedup_key( $ip );
	$now   = time();
	$seen  = get_transient( $key );
	$seen  = is_array( $seen ) ? $seen : array();
	$window = majestic_tube_view_dedup_window();

	// Drop anything that has aged out, so a visitor who returns months later is
	// not permanently blocked and the array shrinks on its own.
	foreach ( $seen as $seen_post_id => $timestamp ) {
		if ( $now - (int) $timestamp >= $window ) {
			unset( $seen[ $seen_post_id ] );
		}
	}

	$already = isset( $seen[ $post_id ] );

	$seen[ $post_id ] = $now;

	if ( count( $seen ) > MAJESTIC_TUBE_VIEW_DEDUP_MAX ) {
		// $seen is ordered oldest-first because a brand new post ID is always
		// appended, so slicing the tail keeps the most recent entries.
		$seen = array_slice( $seen, -MAJESTIC_TUBE_VIEW_DEDUP_MAX, null, true );
	}

	// The extra hour means the entry cannot expire before the window it
	// represents has actually elapsed.
	set_transient( $key, $seen, $window + HOUR_IN_SECONDS );

	return $already;
}

/**
 * Counter keys held in a post's meta row, in the order they are read.
 *
 * @return string[]
 */
function majestic_tube_analytics_meta_keys() {
	return array_keys( majestic_tube_analytics_meta_map() );
}

/**
 * Counter meta keys mapped to their cached field names.
 *
 * @return array<string, string>
 */
function majestic_tube_analytics_meta_map() {
	return array(
		'post_views_count' => 'views',
		'likes_count'      => 'likes',
		'dislikes_count'   => 'dislikes',
		'rate'             => 'rate',
		'reported_count'   => 'reported',
	);
}

/**
 * Object cache key for one post's counters.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function majestic_tube_analytics_cache_key( $post_id ) {
	return 'post_' . absint( $post_id );
}

/**
 * Read every analytics counter for a post in a single query.
 *
 * A single get_post_meta( $post_id ) call returns the post's whole meta array,
 * so five counters cost one meta API call instead of five. The result is
 * memoised in the object cache; a cache hit avoids even that call.
 *
 * @param int $post_id Post ID.
 * @return array{views:int,likes:int,dislikes:int,rate:int,reported:int}
 */
function majestic_tube_analytics_get( $post_id ) {
	$post_id = absint( $post_id );
	$key     = majestic_tube_analytics_cache_key( $post_id );

	$cached = wp_cache_get( $key, MAJESTIC_TUBE_ANALYTICS_GROUP );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$meta = get_post_meta( $post_id );

	/*
	 * Imported posts carry their rating as a `thumbs_up` / `thumbs_down` pair
	 * rather than the native `likes_count` / `dislikes_count` pair. The values
	 * are a percentage that sums to 100, not vote totals, but the display is a
	 * ratio - likes / (likes + dislikes) - so the two forms produce the same
	 * number and can be read as the starting figures.
	 *
	 * The native keys win whenever they are present, so a post that already
	 * has votes is never overwritten by the imported baseline. Because the vote
	 * handler increments whatever this returns, the first vote on an imported
	 * post moves the imported rating by one rather than replacing it with a
	 * bare 100%.
	 */
	$likes    = isset( $meta['likes_count'] ) ? absint( $meta['likes_count'][0] ) : null;
	$dislikes = isset( $meta['dislikes_count'] ) ? absint( $meta['dislikes_count'][0] ) : null;

	if ( null === $likes || null === $dislikes ) {
		$likes    = ( null === $likes && isset( $meta['thumbs_up'] ) ) ? absint( $meta['thumbs_up'][0] ) : $likes;
		$dislikes = ( null === $dislikes && isset( $meta['thumbs_down'] ) ) ? absint( $meta['thumbs_down'][0] ) : $dislikes;
	}

	$data = array(
		'views'    => isset( $meta['post_views_count'] ) ? absint( $meta['post_views_count'][0] ) : 0,
		'likes'    => null === $likes ? 0 : $likes,
		'dislikes' => null === $dislikes ? 0 : $dislikes,
		'rate'     => isset( $meta['rate'] ) ? absint( $meta['rate'][0] ) : 0,
		'reported' => isset( $meta['reported_count'] ) ? absint( $meta['reported_count'][0] ) : 0,
	);

	wp_cache_set( $key, $data, MAJESTIC_TUBE_ANALYTICS_GROUP );

	return $data;
}

/**
 * Read one counter for a post.
 *
 * @param int    $post_id Post ID.
 * @param string $field   One of views, likes, dislikes, rate, reported.
 * @return int
 */
function majestic_tube_analytics_get_field( $post_id, $field ) {
	$data = majestic_tube_analytics_get( $post_id );

	return isset( $data[ $field ] ) ? (int) $data[ $field ] : 0;
}

/**
 * Store a post's counters, keeping the cache in step with the database.
 *
 * The caller is responsible for having written the meta; this only refreshes
 * the cached view of it. Pass the full $data array when several counters moved
 * at once so one write settles them all.
 *
 * @param int   $post_id Post ID.
 * @param array $data    Full counter array, or a partial one to merge in.
 * @return void
 */
function majestic_tube_analytics_set( $post_id, $data ) {
	$post_id = absint( $post_id );

	if ( ! is_array( $data ) ) {
		return;
	}

	// A partial update merges over the current values so unrelated counters are
	// not reset to 0 by accident.
	$current = majestic_tube_analytics_get( $post_id );
	$merged  = array_merge( $current, array_intersect_key( $data, $current ) );

	foreach ( $merged as $field => $value ) {
		$merged[ $field ] = (int) $value;
	}

	wp_cache_set( majestic_tube_analytics_cache_key( $post_id ), $merged, MAJESTIC_TUBE_ANALYTICS_GROUP );
}

/**
 * Drop a post's cached counters.
 *
 * Called after any write. The next read repopulates from the database.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function majestic_tube_analytics_flush_post( $post_id ) {
	wp_cache_delete( majestic_tube_analytics_cache_key( $post_id ), MAJESTIC_TUBE_ANALYTICS_GROUP );
}

/**
 * Like percentage for a post.
 *
 * Lives here rather than in video-meta.php so every rating calculation shares
 * one implementation; the AJAX handlers, the stored `rate` meta and the card
 * badges all route through majestic_tube_get_post_like_rate(), which delegates
 * here.
 *
 * @param int $post_id Post ID.
 * @return int Percentage 0-100.
 */
function majestic_tube_analytics_like_rate( $post_id ) {
	$data  = majestic_tube_analytics_get( $post_id );
	$total = $data['likes'] + $data['dislikes'];

	return 0 === $total ? 0 : (int) round( $data['likes'] / $total * 100 );
}

/**
 * Increment the view counter atomically and refresh the cache.
 *
 * The counter is incremented with a single UPDATE so concurrent requests
 * cannot lose a view, which means the new value must come back from the
 * database rather than from a cached read. The cache is then repopulated with
 * exactly what the database reported, so the response and the next read agree.
 *
 * @param int $post_id Post ID.
 * @return int New count.
 */
function majestic_tube_analytics_increment_views( $post_id ) {
	global $wpdb;

	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		return 0;
	}

	if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- an atomic counter cannot be expressed through the metadata API.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = ( CAST( meta_value AS UNSIGNED ) + 1 ) WHERE post_id = %d AND meta_key = 'post_views_count'",
				$post_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( false !== $updated ) {
			if ( ! $updated ) {
				// No counter row yet: create it at 1.
				add_post_meta( $post_id, 'post_views_count', 1, true );
			}

			clean_post_cache( $post_id );
			majestic_tube_analytics_flush_post( $post_id );

			$views = majestic_tube_analytics_field_from_db( $post_id, 'post_views_count' );
			majestic_tube_analytics_set( $post_id, array( 'views' => $views ) );

			return $views;
		}
	}

	$data  = majestic_tube_analytics_get( $post_id );
	$views = $data['views'] + 1;

	update_post_meta( $post_id, 'post_views_count', $views );
	majestic_tube_analytics_set( $post_id, array( 'views' => $views ) );

	return $views;
}

/**
 * Read a single meta value straight from the database, bypassing the cache.
 *
 * Used only after an atomic write, where the cached copy is known to be stale.
 *
 * @param int    $post_id Post ID.
 * @param string $meta_key Meta key.
 * @return int
 */
function majestic_tube_analytics_field_from_db( $post_id, $meta_key ) {
	global $wpdb;

	if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) ) {
		return majestic_tube_analytics_get_field( $post_id, majestic_tube_analytics_field_for_meta( $meta_key ) );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- deliberately reading past a stale cache entry.
	$value = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
			$post_id,
			$meta_key
		)
	);

	return null === $value ? 0 : absint( $value );
}

/**
 * Map a meta key to its field name in the cached array.
 *
 * @param string $meta_key Meta key.
 * @return string
 */
function majestic_tube_analytics_field_for_meta( $meta_key ) {
	$map = majestic_tube_analytics_meta_map();

	return isset( $map[ $meta_key ] ) ? $map[ $meta_key ] : 'views';
}

/**
 * Invalidate the cached counters when any analytics meta is written.
 *
 * This is what makes the cache safe when a write comes from outside this
 * module: an importer calling update_post_meta() directly, or the original
 * theme's AJAX handlers if this theme runs beside it. Only the five keys this
 * layer caches are acted on, so ordinary post meta edits stay free.
 *
 * @param int    $meta_id    Meta row ID.
 * @param int    $object_id  Post ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value Meta value.
 * @return void
 */
function majestic_tube_analytics_invalidate_on_meta_change( $meta_id, $object_id, $meta_key ) {
	if ( in_array( $meta_key, majestic_tube_analytics_meta_keys(), true ) ) {
		majestic_tube_analytics_flush_post( $object_id );
	}
}
add_action( 'updated_post_meta', 'majestic_tube_analytics_invalidate_on_meta_change', 10, 3 );
add_action( 'added_post_meta', 'majestic_tube_analytics_invalidate_on_meta_change', 10, 3 );
add_action( 'deleted_post_meta', 'majestic_tube_analytics_invalidate_on_meta_change', 10, 3 );

/**
 * Drop a post's cached counters when the post itself is deleted.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function majestic_tube_analytics_invalidate_on_delete( $post_id ) {
	majestic_tube_analytics_flush_post( $post_id );
}
add_action( 'deleted_post', 'majestic_tube_analytics_invalidate_on_delete' );
add_action( 'trashed_post', 'majestic_tube_analytics_invalidate_on_delete' );

/**
 * Cron hook that expires the per-visitor history.
 *
 * Named in a constant so the schedule, the callback and the cleanup all refer
 * to the same string.
 */
const MAJESTIC_TUBE_IP_PRUNE_HOOK = 'majestic_tube_prune_ip_history';

/**
 * Expire stored visitor history on posts nobody interacts with again.
 *
 * Writing already expires a row, which covers every video still being voted on
 * or reported. What it cannot reach is the video nobody touches again: its row
 * would sit in wp_postmeta holding fingerprints that are pseudonymous personal
 * data for as long as the post exists, with no write left to trigger a clean-up.
 * That residue is what this sweep exists to collect.
 *
 * Runs once a day over a bounded batch per meta key, so a large library is
 * walked rather than scanned. Anything the visitor has done since the last run
 * pushes the rest forward a batch at a time.
 *
 * @return int Number of rows changed.
 */
function majestic_tube_prune_expired_ip_history() {
	// Cron only. This walks posts, and nothing about a visitor's history
	// belongs in a front-end page load or an admin screen.
	if ( ! function_exists( 'wp_doing_cron' ) || ! wp_doing_cron() ) {
		return 0;
	}

	/**
	 * Filter how many posts each IP history meta key is swept per run.
	 *
	 * @param int $batch Post IDs per meta key.
	 */
	$batch = max( 1, absint( apply_filters( 'majestic_tube_ip_prune_batch', 200 ) ) );
	$now   = time();
	$rows  = 0;

	foreach ( array_keys( majestic_tube_ip_history_windows() ) as $meta_key ) {
		$window = majestic_tube_ip_history_window( $meta_key );

		$post_ids = get_posts(
			array(
				'post_type'           => 'post',
				'post_status'         => 'any',
				'posts_per_page'      => $batch,
				'fields'              => 'ids',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'meta_key'            => $meta_key,
			)
		);

		foreach ( $post_ids as $post_id ) {
			$stored = get_post_meta( $post_id, $meta_key, true );

			if ( ! is_array( $stored ) || ! $stored ) {
				continue;
			}

			$history = majestic_tube_clean_ip_history( $stored, $window, $now );

			// Only write when the sweep actually changed something.
			if ( $history === $stored ) {
				continue;
			}

			if ( $history ) {
				update_post_meta( $post_id, $meta_key, $history );
			} else {
				delete_post_meta( $post_id, $meta_key );
			}

			++$rows;
		}
	}

	return $rows;
}
add_action( MAJESTIC_TUBE_IP_PRUNE_HOOK, 'majestic_tube_prune_expired_ip_history' );

/**
 * Schedule the daily sweep when the theme is activated.
 *
 * @return void
 */
function majestic_tube_schedule_ip_history_prune() {
	if ( ! wp_next_scheduled( MAJESTIC_TUBE_IP_PRUNE_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', MAJESTIC_TUBE_IP_PRUNE_HOOK );
	}
}
add_action( 'after_switch_theme', 'majestic_tube_schedule_ip_history_prune' );

/**
 * Drop the schedule when another theme takes over.
 *
 * Without this the event keeps firing for a theme that is no longer installed,
 * which is what leaves sites with a cron entry pointing at nothing.
 *
 * @return void
 */
function majestic_tube_unschedule_ip_history_prune() {
	wp_clear_scheduled_hook( MAJESTIC_TUBE_IP_PRUNE_HOOK );
}
add_action( 'switch_theme', 'majestic_tube_unschedule_ip_history_prune' );
