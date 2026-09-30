/**
 * Majestic Tube main front-end script.
 *
 * Vanilla JS, no jQuery. Talks to the original WP-Script ajax endpoints:
 * action=post-views, post-like, get-post-data and report-video, all signed with
 * the shared `ajax-nonce` the original theme used.
 *
 * @package Majestic Tube
 * @version 2.2.19
 */

( function () {
	'use strict';

	var data = window.majesticTubeData || {};
	var options = data.options || {};
	var i18n = data.i18n || {};
	var THUMBS_INTERVAL = 750; // Original rotation speed.
	var THUMBS_FIRST_DELAY = 150;
	var HOVER_INTENT = 100;

	// How far outside the viewport a card starts being prepared.
	var ROOT_MARGIN = '250px 0px';

	/**
	 * Query helpers.
	 *
	 * @param {string} selector CSS selector.
	 * @param {Element} context Optional context.
	 * @return {Array} Matching elements.
	 */
	function findAll( selector, context ) {
		return Array.prototype.slice.call( ( context || document ).querySelectorAll( selector ) );
	}

	/**
	 * Build a human readable count (1K, 2M) like the PHP helper.
	 *
	 * @param {number} value Number.
	 * @return {string} Formatted number.
	 */
	function humanNumber( value ) {
		value = parseInt( value, 10 ) || 0;

		if ( value < 1000 ) {
			return String( value );
		}

		var units = [
			[ 1000000000000, 'T' ],
			[ 1000000000, 'B' ],
			[ 1000000, 'M' ],
			[ 1000, 'K' ]
		];

		for ( var i = 0; i < units.length; i++ ) {
			if ( value >= units[ i ][ 0 ] ) {
				return Math.floor( value / units[ i ][ 0 ] ).toLocaleString() + units[ i ][ 1 ];
			}
		}

		return String( value );
	}

	/**
	 * Mobile menu toggle.
	 */
	function initMenuToggle() {
		var navigation = document.getElementById( 'site-navigation' );
		var toggle = navigation ? navigation.querySelector( '.button-nav, .menu-toggle' ) : document.querySelector( '.menu-toggle' );
		var nav = navigation ? ( document.getElementById( 'primary-menu' ) || navigation.querySelector( 'ul' ) ) : document.getElementById( 'primary-menu' );
		var label = toggle ? toggle.querySelector( '.menu-toggle-label' ) : null;

		if ( ! toggle || ! nav ) {
			return;
		}

		var container = navigation || nav.closest( '.main-navigation' ) || nav.parentNode;
		var menuId = nav.id || 'primary-menu';
		toggle.setAttribute( 'aria-controls', menuId );

		function setOpen( open ) {
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			nav.classList.toggle( 'is-open', open );
			// KingTube's original navigation.js uses `.open`; keep that hook as
			// well as the accessible class so existing child-theme rules continue
			// to work with the corrected markup.
			nav.classList.toggle( 'open', open );
			container.classList.toggle( 'is-open', open );
			toggle.classList.toggle( 'menu-opened', open );

			if ( label ) {
				label.textContent = open ? ( i18n.closeMenu || 'Close' ) : ( i18n.openMenu || 'Menu' );
			}
		}

		toggle.addEventListener( 'click', function () {
			setOpen( toggle.getAttribute( 'aria-expanded' ) !== 'true' );
		} );

		// A normal link should close the mobile drawer after navigation. Keep
		// dropdown/hash links usable, but do not leave the drawer covering the
		// page when a visitor chooses a directory item.
		nav.addEventListener( 'click', function ( event ) {
			var target = event.target;
			var link = target && target.closest ? target.closest( 'a' ) : null;
			if ( link && link.getAttribute( 'href' ) && link.getAttribute( 'href' ) !== '#' ) {
				setOpen( false );
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( toggle.getAttribute( 'aria-expanded' ) === 'true' && ! container.contains( event.target ) ) {
				setOpen( false );
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && toggle.getAttribute( 'aria-expanded' ) === 'true' ) {
				setOpen( false );
				toggle.focus();
			}
		} );
	}

	/**
	 * Send a request body to admin-ajax.php and parse its response.
	 *
	 * @param {URLSearchParams|FormData} body Request body.
	 * @param {boolean} encoded Whether the body has an encoded content type.
	 * @param {boolean} [retried] Whether this is already a nonce-refreshed replay.
	 * @return {Promise<Object>} Parsed JSON response.
	 */
	function ajaxRequest( body, encoded, retried ) {
		var options = {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		};

		if ( encoded ) {
			options.headers = {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			};
		}

		return fetch( data.url || data.ajaxUrl, options ).then( function ( response ) {
			// The original endpoints answer with a plain JSON object; a failed
			// nonce check used to reply with the text "Busted!", so read the
			// body as text first instead of assuming parseable JSON.
			return response.text().then( function ( text ) {
				return { status: response.status, text: text };
			} );
		} ).then( function ( result ) {
			var json;

			try {
				json = JSON.parse( result.text );
			} catch ( error ) {
				json = { success: false, message: result.text };
			}

			/*
			 * 403 is how every endpoint answers a failed nonce check (and
			 * nothing else the front end triggers). A page served from a
			 * full-page cache can be older than the 12-24 hours a nonce lives,
			 * so fetch fresh nonces and replay the request once rather than
			 * fail the visitor with "Security check failed." over something
			 * they cannot help.
			 */
			if ( ! retried && 403 === result.status ) {
				return refreshNonces( body ).then( function () {
					return ajaxRequest( body, encoded, true );
				} );
			}

			return json;
		} );
	}

	/**
	 * Post a request to admin-ajax.php with urlencoded form data.
	 *
	 * @param {Object} payload Key/value payload including action and nonce.
	 * @return {Promise<Object>} Parsed JSON response.
	 */
	function ajaxPost( payload ) {
		var body = new URLSearchParams();

		Object.keys( payload ).forEach( function ( key ) {
			body.append( key, payload[ key ] );
		} );

		// Pass the live URLSearchParams, not body.toString(): a failed nonce
		// check patches the body in place before replaying it, which a frozen
		// string cannot absorb.
		return ajaxRequest( body, true );
	}

	/**
	 * Fetch live nonces and patch them into a request body and the page forms.
	 *
	 * The hidden nonce fields in a cached page go stale after 12-24 hours, so
	 * after a failed security check the request body is patched in place (both
	 * URLSearchParams and FormData expose has()/set()) and replayed. The form
	 * fields are kept current too, so a rebuilt FormData is fresh as well.
	 *
	 * @param {URLSearchParams|FormData|string} [body] Request body to patch in place.
	 * @return {Promise<void>} Resolved once fresh nonces are in place.
	 */
	function refreshNonces( body ) {
		var refresh = new URLSearchParams();

		refresh.append( 'action', 'majestic_tube_refresh_nonces' );

		// The refresh call itself must never retry through ajaxRequest.
		return ajaxRequest( refresh.toString(), true, true ).then( function ( json ) {
			if ( ! json || ! json.ajaxNonce ) {
				return;
			}

			data.nonce = json.ajaxNonce;

			if ( body && typeof body.set === 'function' && typeof body.has === 'function' ) {
				if ( body.has( 'nonce' ) ) {
					body.set( 'nonce', json.ajaxNonce );
				}

				[ 'login-security', 'register-security', 'password-security' ].forEach( function ( field ) {
					if ( body.has( field ) ) {
						body.set( field, json.loginNonce );
					}
				} );
			}

			findAll( 'input[name="login-security"], input[name="register-security"], input[name="password-security"]' ).forEach( function ( input ) {
				input.value = json.loginNonce;
			} );
		} ).catch( function () {
			// The replay below surfaces the original error if this failed.
		} );
	}

	/**
	 * Unwrap an AJAX response.
	 *
	 * WP-Script endpoints answer with a flat object ({ views, likes, ... }),
	 * which is the original contract other scripts rely on. WordPress helpers
	 * such as wp_send_json_success() wrap the payload instead, so accept both
	 * shapes here: `{ success, data }` and the flat form.
	 *
	 * @param {Object} json Parsed response.
	 * @return {Object} Payload.
	 */
	function unwrap( json ) {
		if ( ! json || typeof json !== 'object' ) {
			return {};
		}

		if ( json.data && typeof json.data === 'object' ) {
			return json.data;
		}

		return json;
	}

	/**
	 * Whether a response represents a failure.
	 *
	 * @param {Object} json Parsed response.
	 * @return {boolean} True when the request failed.
	 */
	function responseFailed( json ) {
		if ( ! json || typeof json !== 'object' ) {
			return true;
		}

		if ( json.success === false || json.error ) {
			return true;
		}

		var payload = unwrap( json );

		if ( payload.error === false ) {
			return false;
		}

		return payload.message !== undefined && payload.percentage === undefined && payload.views === undefined && payload.count === undefined;
	}

	/**
	 * Human-readable error message of a failed response.
	 *
	 * @param {Object} json Parsed response.
	 * @return {string} Message or an empty string.
	 */
	function responseMessage( json ) {
		var payload = unwrap( json );

		return payload.message || json.error || '';
	}

	/**
	 * Count the view on single video pages (original post-views action).
	 *
	 * Guarded by sessionStorage so a refresh, a back/forward navigation or a
	 * second tab in the same session does not inflate the counter. The key is
	 * per post, so moving to another video still records a view. Storage access
	 * is wrapped because it throws in Safari private mode and in some
	 * cross-origin iframes; when it is unavailable the view is simply counted.
	 */
	function countView() {
		var player = document.querySelector( '.video-player' );
		var postId = player ? player.getAttribute( 'data-post-id' ) : data.postId;

		if ( ! postId ) {
			return;
		}

		// A6: play-anchored counting. When the option is on, the view is only
		// recorded after three seconds of actual playback, so a bounce that
		// never presses play does not register. The default (off) keeps the
		// original load-time behavior.
		if ( options.countViewsOnPlay ) {
			initPlayAnchoredView( postId );
			return;
		}

		sendView( postId );
	}

	/**
	 * Send the post-views request for one post.
	 *
	 * Split out of countView() so both the load-time path and the play-anchored
	 * path share the sessionStorage guard and the response handling.
	 *
	 * @param {string|number} postId Post id.
	 */
	function sendView( postId ) {
		var storageKey = 'majesticTubeViewed_' + postId;

		try {
			if ( window.sessionStorage && window.sessionStorage.getItem( storageKey ) ) {
				return;
			}

			window.sessionStorage.setItem( storageKey, '1' );
		} catch ( e ) {
			// Storage unavailable: fall through and count the view.
		}

		ajaxPost( {
			action: 'post-views',
			nonce: data.nonce,
			post_id: postId
		} ).then( function ( json ) {
			var payload = unwrap( json );

			if ( payload.views !== undefined ) {
				applyStats( {
					views: payload.views
				} );
			}
		} ).catch( function () {
			// A failed request should not block a later attempt.
			try {
				window.sessionStorage.removeItem( storageKey );
			} catch ( e ) {
				// Ignore.
			}
		} );
	}

	/**
	 * Count the view once playback has actually started.
	 *
	 * Listens for the first timeupdate past three seconds on the native
		 * <video> element (Video.js drives the same element, so both players are
		 * covered by one listener), then removes itself and posts the view. If
		 * the video never plays, the listener is discarded with the page.
	 *
	 * @param {string|number} postId Post id.
	 */
	function initPlayAnchoredView( postId ) {
		var video = document.getElementById( 'wpst-video' );

		if ( ! video ) {
			return;
		}

		video.addEventListener( 'timeupdate', function onTimeUpdate() {
			if ( video.currentTime < 3 ) {
				return;
			}

			video.removeEventListener( 'timeupdate', onTimeUpdate );

			sendView( postId );
		} );
	}

	/**
	 * Paint fresh stats into the page.
	 *
	 * @param {Object} stats Stats returned by the async endpoints.
	 */
	function applyStats( stats ) {
		if ( ! stats ) {
			return;
		}

		if ( stats.views !== undefined ) {
			// Original markup: .video-views span. Ours also mirrors the value
			// into .entry-views (single header) and the player data attribute.
			findAll( '.video-views span, .entry-views' ).forEach( function ( el ) {
				el.textContent = humanNumber( stats.views );
			} );

			findAll( '.video-player[data-views]' ).forEach( function ( el ) {
				el.setAttribute( 'data-views', stats.views );
			} );
		}

		if ( stats.likes !== undefined ) {
			findAll( '.likes .likes_count, .post-like .like-count' ).forEach( function ( el ) {
				el.textContent = humanNumber( stats.likes );
			} );
		}

		if ( stats.dislikes !== undefined ) {
			findAll( '.post-dislike .dislike-count' ).forEach( function ( el ) {
				el.textContent = humanNumber( stats.dislikes );
			} );
		}

		var rate = stats.progressbar !== undefined ? stats.progressbar : stats.rate;

		if ( rate !== undefined ) {
			findAll( '.video-rate-bar' ).forEach( function ( bar ) {
				bar.style.width = rate + '%';
			} );

			findAll( '.video-rate, .rating-result' ).forEach( function ( el ) {
				el.setAttribute( 'aria-valuenow', rate );
			} );

			findAll( '.rating-result .percentage, .video-rate-label' ).forEach( function ( label ) {
				label.textContent = rate + '%';
				label.style.display = '';
			} );
		}
	}

	/**
	 * Refresh views / likes from the original get-post-data endpoint.
	 *
	 * @param {boolean} delayed Wait a moment before asking, to let the view be recorded.
	 */
	function refreshStats( delayed ) {
		var player = document.querySelector( '.video-player' );
		var postId = player ? player.getAttribute( 'data-post-id' ) : data.postId;

		if ( ! postId ) {
			return;
		}

		window.setTimeout( function () {
			ajaxPost( {
				action: 'get-post-data',
				nonce: data.nonce,
				post_id: postId
			} ).then( function ( json ) {
				if ( ! responseFailed( json ) ) {
					applyStats( unwrap( json ) );
				}
			} ).catch( function () {} );
		}, delayed ? 8000 : 0 );
	}

	/**
	 * Like / dislike buttons (original post-like action).
	 */
	function initLikeButtons() {
		// Original binding: the like action is an <a> inside .post-like; the
		// dislike control carries the same data attributes on a <button>.
		var triggers = findAll( '.post-like a[data-post_like], .post-dislike[data-post_like]' );

		if ( ! triggers.length ) {
			return;
		}

		function handleVote( trigger, vote ) {
			var postId = trigger.getAttribute( 'data-post_id' );
			var wrapper = trigger.closest( '.post-like' ) || trigger;

			if ( ! postId ) {
				return;
			}

			trigger.classList.add( 'disabled' );

			ajaxPost( {
				action: 'post-like',
				nonce: data.nonce,
				post_id: postId,
				post_like: vote
			} ).then( function ( json ) {
				trigger.classList.remove( 'disabled' );

				if ( responseFailed( json ) ) {
					if ( i18n.likeError ) {
						trigger.setAttribute( 'title', i18n.likeError );
					}
					return;
				}

				var payload = unwrap( json );

				applyStats( payload );

				if ( payload.alreadyrate ) {
					// Already voted in the last 24h: swap the link for the
					// original "Thank you!" state so it cannot be clicked again.
					wrapper.innerHTML = '<span class="button disabled">' + escHtml( i18n.alreadyRated || 'Thank you!' ) + '</span>';
				} else if ( payload.button ) {
					wrapper.innerHTML = '<span class="button disabled">' + escHtml( payload.button ) + '</span>';
				}
			} ).catch( function () {
				trigger.classList.remove( 'disabled' );
			} );
		}

		triggers.forEach( function ( trigger ) {
			trigger.addEventListener( 'click', function ( event ) {
				event.preventDefault();

				handleVote( trigger, trigger.getAttribute( 'data-post_like' ) || 'like' );
			} );
		} );
	}

	/**
	 * Escape text before it is injected as HTML.
	 *
	 * @param {string} text Raw text.
	 * @return {string} Escaped text.
	 */
	function escHtml( text ) {
		var div = document.createElement( 'div' );

		div.textContent = text === undefined || text === null ? '' : String( text );

		return div.innerHTML;
	}

	/**
	 * Mount any Turnstile widget inside a container, at most once each.
	 *
	 * Turnstile is loaded in explicit-render mode, and the sign-up form lives
	 * in a modal that stays hidden until a visitor opens it. A widget mounted
	 * into a hidden element measures itself as zero and never recovers, so
	 * mounting is deferred until the container is actually on screen.
	 *
	 * @param {Element|Document} root Container to search.
	 */
	function initCaptcha( root ) {
		if ( ! window.turnstile || typeof window.turnstile.render !== 'function' ) {
			return;
		}

		findAll( '.cf-turnstile[data-mt-turnstile]', root || document ).forEach( function ( mount ) {
			if ( '1' === mount.getAttribute( 'data-mt-mounted' ) ) {
				return;
			}

			/*
			 * Skip anything still inside a hidden container, and leave it
			 * unmarked so a later call can mount it once revealed. This is
			 * what keeps the pass over the whole document from mounting the
			 * sign-up widget inside the closed modal.
			 */
			if ( mount.closest( '[hidden]' ) ) {
				return;
			}

			mount.setAttribute( 'data-mt-mounted', '1' );

			var widget = window.turnstile.render( mount, {
				sitekey: mount.getAttribute( 'data-sitekey' ),
				theme: mount.getAttribute( 'data-theme' ) || 'auto'
			} );

			if ( widget ) {
				mount.setAttribute( 'data-mt-widget', widget );
			}
		} );
	}

	/**
	 * Clear a solved Turnstile widget so the next attempt starts over.
	 *
	 * A token is good once. Without this, a visitor who fails for an
	 * unrelated reason - a username already taken, say - is left looking at a
	 * completed challenge that no longer verifies, and the form can never
	 * succeed without a full page reload.
	 *
	 * @param {Element} form Form that was submitted.
	 */
	function resetCaptcha( form ) {
		if ( ! window.turnstile || typeof window.turnstile.reset !== 'function' ) {
			return;
		}

		findAll( '.cf-turnstile[data-mt-widget]', form ).forEach( function ( mount ) {
			window.turnstile.reset( mount.getAttribute( 'data-mt-widget' ) );
		} );
	}

	/**
	 * Membership modal (login / register / reset).
	 */
	function initUserModal() {
		var modal = document.getElementById( 'wpst-user-modal' );

		if ( ! modal ) {
			return;
		}

		var close = modal.querySelector( '.majestic-tube-modal-close' );

		function showTab( tab ) {
			modal.removeAttribute( 'hidden' );
			modal.setAttribute( 'data-active-tab', tab );
			findAll( '.wpst-register, .wpst-login, .wpst-reset-password', modal ).forEach( function ( el ) {
				el.style.display = 'none';
			} );

			var target = modal.querySelector( tab );

			if ( target ) {
				target.style.display = 'block';

				// The sign-up panel was display:none until this moment, which is
				// exactly when a spam widget inside it can finally be mounted.
				initCaptcha( target );
			}

			var footer = modal.querySelector( '.majestic-tube-modal-footer' );

			if ( footer ) {
				footer.setAttribute( 'data-active-tab', tab );
			}
		}

		/*
		 * The modal markup is printed in wp_footer on every page, but a few
		 * guest-only templates (the video submit form) link at a specific
		 * panel from outside it, with href="#wpst-login" / "#wpst-register".
		 * The delegated handler on the modal below never sees those clicks, so
		 * they only changed the URL hash. Bind them here as well and open the
		 * panel the hash actually names.
		 */
		findAll( 'a[href="#wpst-user-modal"], a[href="#wpst-login"], a[href="#wpst-register"]' ).forEach( function ( opener ) {
			opener.addEventListener( 'click', function ( e ) {
				e.preventDefault();

				showTab( '#wpst-register' === opener.getAttribute( 'href' ) ? '.wpst-register' : '.wpst-login' );
			} );
		} );

		modal.addEventListener( 'click', function ( e ) {
			var link = e.target.closest( 'a[href^="#wpst-"]' );

			if ( ! link ) {
				return;
			}

			e.preventDefault();

			var href = link.getAttribute( 'href' );

			if ( href === '#wpst-register' ) {
				showTab( '.wpst-register' );
			} else if ( href === '#wpst-reset-password' ) {
				showTab( '.wpst-reset-password' );
			} else {
				showTab( '.wpst-login' );
			}
		} );

		if ( close ) {
			close.addEventListener( 'click', function () {
				modal.setAttribute( 'hidden', '' );
			} );
		}

		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) {
				modal.setAttribute( 'hidden', '' );
			}
		} );

		[ 'wpst_login_form', 'wpst_registration_form', 'wpst_reset_password_form' ].forEach( function ( id ) {
			var form = document.getElementById( id );

			if ( ! form ) {
				return;
			}

			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();

				var errors = form.parentNode.querySelector( '.wpst-errors' );				if ( errors ) {
						errors.textContent = '';
					}

					ajaxRequest( new FormData( form ), false ).then( function ( json ) {
					if ( json.error ) {
						if ( errors ) {
							errors.innerHTML = json.message || '';
						}

						// The token was consumed by the failed attempt, so hand
						// the visitor a fresh challenge instead of a dead form.
						resetCaptcha( form );
						return;
					}

					if ( 'wpst_login_form' === form.id ) {
						window.location.reload();
					} else if ( json.message && errors ) {
						errors.innerHTML = json.message;
					}
				} ).catch( function () {} );
			} );
		} );
	}

	/**
	 * Find the image used by a card's thumbnail and preview layers.
	 *
	 * @param {Element} card Card element.
	 * @return {Element|null} Card image.
	 */
	function getCardImage( card ) {
		return card.querySelector( '.video-main-thumb' ) || card.querySelector( 'img' );
	}

	/**
	 * Bind a per-card setup only once a card is near the viewport.
	 *
	 * The hover previews are the only per-card listeners on an archive page,
	 * and a page can hold a hundred cards, of which a visitor sees perhaps
	 * twenty. Setting all of them up up front cost a listener and a closure
	 * per card, and - for trailers - left a muted video element playing off
	 * screen whenever the page scrolled out from under a stationary pointer.
	 *
	 * The setup function is called when a card comes within ROOT_MARGIN of the
	 * viewport and may return a teardown, which runs when it leaves again. The
	 * teardown restores the card to its resting state, so a card scrolled back
	 * into view behaves exactly as it did the first time.
	 *
	 * Without IntersectionObserver, or with it unavailable, every card is set
	 * up immediately and no teardown ever runs: the previous behaviour, which
	 * is the safe fallback.
	 *
	 * @param {string}   selector Cards to watch.
	 * @param {Function} setup    Per-card setup; may return a teardown.
	 */
	function observeCards( selector, setup ) {
		var cards = findAll( selector );

		if ( ! cards.length ) {
			return;
		}

		if ( ! ( 'IntersectionObserver' in window ) ) {
			cards.forEach( function ( card ) {
				setup( card );
			} );

			return;
		}

		var teardowns = new WeakMap();

		var observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				var card = entry.target;

				if ( entry.isIntersecting ) {
					if ( ! teardowns.has( card ) ) {
						teardowns.set( card, setup( card ) || null );
					}

					return;
				}

				var teardown = teardowns.get( card );

				if ( teardown ) {
					teardown();
					teardowns.delete( card );
				}
			} );
		}, { rootMargin: ROOT_MARGIN } );

		cards.forEach( function ( card ) {
			observer.observe( card );
		} );
	}

	/**
	 * Light/dark toggle.
	 *
	 * The site default is already on the root element by the time this runs,
	 * possibly replaced by the head script from this visitor's stored choice.
	 * Pressing the button stores the next scheme locally and swaps the
	 * attribute; nothing is sent to the server, so the choice follows the
	 * browser rather than the account.
	 *
	 * "System" is only offered as a step when the site itself is set to
	 * follow the system, so a pinned light or dark site does not hand a
	 * visitor a third state it never asked for.
	 */
	function initThemeToggle() {
		var button = document.querySelector( '[data-majestic-tube-theme-toggle]' );

		if ( ! button ) {
			return;
		}

		var order = 'system' === button.getAttribute( 'data-state' ) ?
			[ 'system', 'light', 'dark' ] :
			[ 'light', 'dark' ];

		var names = {
			light: i18n.themeLight || 'Light',
			dark: i18n.themeDark || 'Dark',
			system: i18n.themeSystem || 'Follow system'
		};

		function current() {
			var state = document.documentElement.getAttribute( 'data-theme' );

			return order.indexOf( state ) > -1 ? state : order[ 0 ];
		}

		function render() {
			var state = current();
			var next = order[ ( order.indexOf( state ) + 1 ) % order.length ];

			button.setAttribute( 'data-state', state );
			button.setAttribute( 'aria-label', names[ next ] );
			button.setAttribute( 'title', names[ next ] );

			var label = button.querySelector( '.screen-reader-text' );

			if ( label ) {
				label.textContent = names[ next ];
			}
		}

		button.addEventListener( 'click', function () {
			var next = order[ ( order.indexOf( current() ) + 1 ) % order.length ];

			document.documentElement.setAttribute( 'data-theme', next );

			try {
				window.localStorage.setItem( 'majestic_tube_theme', next );
			} catch ( e ) {
				// Storage unavailable. The choice still applies to this page.
			}

			render();
		} );

		render();
	}

	/**
	 * Thumbnail rotation on hover (original `thumbs` meta, `data-thumbs`).
	 *
	 * The card template writes `data-thumbs` / `data-trailer` on the
	 * `.video-card` article, not on the inner `.video-card-thumbnail` div, so
	 * that is the element to bind to. The image and overlay lookups below go
	 * through querySelector, so they still find the descendants they need.
	 */
	function initThumbRotation() {
		if ( false === options.rotateThumbs || 'off' === options.rotateThumbs ) {
			return;
		}

		observeCards( '.video-card[data-thumbs]', function ( card ) {
			/*
			 * A trailer outranks rotation. The card template only ever emits
			 * one of the two attributes, so this is belt-and-braces for a
			 * child theme or plugin that sets both: without it the rotation
			 * would start swapping the thumbnail on mouseenter while the
			 * trailer was still inside its hover-intent delay.
			 */
			if ( card.hasAttribute( 'data-trailer' ) ) {
				return null;
			}

			// .video-main-thumb is the original class of the card image; the
			// generic img lookup stays as a fallback for older child themes.
			var img = getCardImage( card );
			var raw = card.getAttribute( 'data-thumbs' );

			if ( ! img || ! raw ) {
				return null;
			}

			var thumbs = raw.split( ',' ).map( function ( url ) {
				return url.trim();
			} ).filter( Boolean );

			if ( thumbs.length < 2 ) {
				return null;
			}

			var mainSrc = card.getAttribute( 'data-main-thumb' ) || img.getAttribute( 'src' );
			var index = 1;
			var timer = null;

			function restore() {
				window.clearTimeout( timer );
				timer = null;
				index = 1;

				if ( mainSrc ) {
					img.setAttribute( 'src', mainSrc );
				}
			}

			function onEnter() {
				img.setAttribute( 'src', thumbs[ index ] );
				index = ( index + 1 ) % thumbs.length;

				function cycle() {
					if ( ! timer ) {
						return;
					}

					img.setAttribute( 'src', thumbs[ index ] );
					index = ( index + 1 ) % thumbs.length;
					timer = window.setTimeout( cycle, THUMBS_INTERVAL );
				}

				timer = window.setTimeout( cycle, THUMBS_FIRST_DELAY );
			}

			card.addEventListener( 'mouseenter', onEnter );
			card.addEventListener( 'mouseleave', restore );

			// Runs when the card scrolls out of range: stops a rotation that
			// is still ticking and puts the main thumbnail back, so a card
			// scrolled back into view never resumes mid-sequence.
			return function () {
				card.removeEventListener( 'mouseenter', onEnter );
				card.removeEventListener( 'mouseleave', restore );
				restore();
			};
		} );
	}

	/**
	 * Fade the card's own thumbnail out while a trailer is playing.
	 *
	 * @param {Element} img Card thumbnail.
	 */
	function hideMainThumb( img ) {
		if ( img ) {
			img.style.visibility = 'hidden';
		}
	}

	/**
	 * Restore the card thumbnail after a trailer stops.
	 *
	 * @param {Element} img Card thumbnail.
	 */
	function showMainThumb( img ) {
		if ( img ) {
			img.style.visibility = '';
		}
	}

	/**
	 * Trailer preview on hover (original behavior).
	 *
	 * Video trailers (.mp4/.webm) play inline, image trailers (.gif/.webp) are
	 * shown as an overlay. Trailers take precedence over thumb rotation: the
	 * card template only emits `data-thumbs` when there is no trailer, so a
	 * card never carries both and the two previews never fight.
	 */
	function initTrailerPreview() {
		observeCards( '.video-card[data-trailer]', function ( card ) {
			var trailerUrl = card.getAttribute( 'data-trailer' );

			if ( ! trailerUrl ) {
				return null;
			}

			var isVideo = /\.(mp4|webm)(\?.*)?$/i.test( trailerUrl );
			var isImage = /\.(gif|webp)(\?.*)?$/i.test( trailerUrl );

			if ( ! isVideo && ! isImage ) {
				return null;
			}

			// The overlay div is original markup; trailers are injected into it.
			var overlayTarget = card.querySelector( '.video-overlay' );
			var overlay = null;
			var intentTimer = null;
			var img = getCardImage( card );
			var originalSrc = img ? img.getAttribute( 'src' ) : '';

			function start() {
				if ( overlay ) {
					if ( isVideo && overlay.play ) {
						overlay.play().catch( function () {} );
					}
					return;
				}

				overlay = document.createElement( isVideo ? 'video' : 'img' );

				// wpst-trailer marks theme-owned preview media: the theme CSS
				// sizes it and Clean Tube Player skips it when it replaces the
				// page's players. preview-thumb is the original image class.
				overlay.className = isVideo ? 'wpst-trailer' : 'wpst-trailer preview-thumb';

				if ( isVideo ) {
					overlay.muted = true;
					overlay.loop = true;
					overlay.playsInline = true;
					overlay.autoplay = true;
					overlay.preload = 'metadata';
					overlay.setAttribute( 'muted', '' );
					overlay.setAttribute( 'playsinline', '' );
					overlay.src = trailerUrl;
				} else {
					overlay.alt = '';
					overlay.setAttribute( 'aria-hidden', 'true' );
					overlay.src = trailerUrl;
				}

				if ( overlayTarget ) {
					overlayTarget.appendChild( overlay );
					overlayTarget.style.display = 'block';
				} else {
					card.appendChild( overlay );
				}

				if ( isVideo ) {
					overlay.play().catch( function () {} );
				}

				hideMainThumb( img );
				card.classList.add( 'has-trailer' );
			}

			function stop() {
				window.clearTimeout( intentTimer );

				if ( overlay ) {
					if ( overlay.tagName === 'VIDEO' ) {
						overlay.pause();
					}

					overlay.remove();
					overlay = null;
				}

				if ( overlayTarget ) {
					overlayTarget.style.display = '';
				}

				card.classList.remove( 'has-trailer' );
				showMainThumb( img );

				if ( img && originalSrc ) {
					img.setAttribute( 'src', originalSrc );
				}
			}

			function onEnter() {
				window.clearTimeout( intentTimer );
				intentTimer = window.setTimeout( start, HOVER_INTENT );
			}

			function onTouchStart() {
				window.clearTimeout( intentTimer );
				intentTimer = window.setTimeout( start, HOVER_INTENT );
			}

			card.addEventListener( 'mouseenter', onEnter );
			card.addEventListener( 'mouseleave', stop );
			card.addEventListener( 'touchstart', onTouchStart, { passive: true } );
			card.addEventListener( 'touchend', stop );

			/*
			 * Runs when the card scrolls out of range. This is not just
			 * tidiness: a page scrolled out from under a stationary pointer
			 * fires no mouseleave, so without this the muted trailer kept
			 * playing off screen and the real thumbnail stayed hidden.
			 */
			return function () {
				card.removeEventListener( 'mouseenter', onEnter );
				card.removeEventListener( 'mouseleave', stop );
				card.removeEventListener( 'touchstart', onTouchStart );
				card.removeEventListener( 'touchend', stop );
				stop();
			};
		} );
	}

	/**
	 * Collapse long video descriptions (original .video-description .more).
	 *
	 * PHP adds the `more` class from the truncate-description option; this
	 * clamps the block and appends a toggle button.
	 */
	function initReadMore() {
		findAll( '.video-description .desc.more' ).forEach( function ( block ) {
			// Only add a toggle when the text actually overflows the clamp.
			block.classList.add( 'is-clamped' );

			if ( block.scrollHeight <= block.clientHeight + 4 ) {
				block.classList.remove( 'is-clamped' );
				return;
			}

			var toggle = document.createElement( 'button' );

			toggle.type = 'button';
			toggle.className = 'video-description-toggle';
			toggle.setAttribute( 'aria-expanded', 'false' );
			toggle.textContent = i18n.readMore || 'Read more';

			toggle.addEventListener( 'click', function () {
				var expanded = block.classList.toggle( 'is-expanded' );

				block.classList.toggle( 'is-clamped', ! expanded );
				toggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
				toggle.textContent = expanded ? ( i18n.readLess || 'Read less' ) : ( i18n.readMore || 'Read more' );
			} );

			block.parentNode.insertBefore( toggle, block.nextSibling );
		} );
	}

	/**
	 * Close button for the 300x250 player overlay.
	 *
	 * The zone is taken out of the document rather than hidden: it is
	 * rebuilt on every page load, so nothing has to remember that a visitor
	 * already dismissed it, and a leftover display:none box would still be
	 * picked up by ad scripts that scan the player.
	 */
	function initPlayerOverlay() {
		findAll( '.player-overlay' ).forEach( function ( zone ) {
			var button = zone.querySelector( '.player-overlay-close' );

			if ( ! button ) {
				return;
			}

			button.addEventListener( 'click', function () {
				zone.remove();
			} );
		} );
	}

	/**
	 * Report a video (action: report-video).
	 */
	function initReportVideo() {
		findAll( '.video-report' ).forEach( function ( container ) {
			var toggle = container.querySelector( '.video-report-toggle' );
			var form = container.querySelector( '.video-report-form' );

			if ( ! toggle || ! form ) {
				return;
			}

			var feedback = form.querySelector( '.video-report-feedback' );

			toggle.addEventListener( 'click', function () {
				var open = ! form.hasAttribute( 'hidden' );

				if ( open ) {
					form.setAttribute( 'hidden', '' );
				} else {
					form.removeAttribute( 'hidden' );
				}

				toggle.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
			} );

			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();

				var button = form.querySelector( '.video-report-submit' );
				var postId = form.getAttribute( 'data-post_id' );

				if ( button ) {
					button.disabled = true;
				}

				if ( feedback ) {
					feedback.textContent = '';
				}

				ajaxPost( {
					action: 'report-video',
					nonce: data.nonce,
					post_id: postId,
					reason: form.elements.reason ? form.elements.reason.value : '',
					message: form.elements.message ? form.elements.message.value : ''
				} ).then( function ( json ) {
					if ( button ) {
						button.disabled = false;
					}

					if ( responseFailed( json ) ) {
						if ( feedback ) {
							feedback.textContent = responseMessage( json ) || i18n.reportError || '';
						}
						return;
					}

					if ( feedback ) {
						feedback.textContent = unwrap( json ).message || i18n.reported || '';
					}

					form.reset();
					window.setTimeout( function () {
						form.setAttribute( 'hidden', '' );
						toggle.setAttribute( 'aria-expanded', 'false' );
					}, 2500 );
				} ).catch( function () {
					if ( button ) {
						button.disabled = false;
					}

					if ( feedback ) {
						feedback.textContent = i18n.reportError || '';
					}
				} );
			} );
		} );
	}

	/**
	 * Source list of the current player, highest quality first.
	 *
	 * @param {Element} video Video element.
	 * @return {Array} Source descriptors.
	 */
	function getSources( video ) {
		return findAll( 'source', video ).map( function ( source ) {
			return {
				url: source.getAttribute( 'src' ),
				type: source.getAttribute( 'type' ),
				label: source.getAttribute( 'label' ) || source.getAttribute( 'data-res' ) || ''
			};
		} ).filter( function ( source ) {
			return source.url && source.label;
		} );
	}

	/**
	 * Swap the playing source, keeping the playback position.
	 *
	 * @param {Object} player Video.js player or the video element.
	 * @param {Object} source Source descriptor.
	 */
	function switchQuality( player, source ) {
		var isPlayer = typeof player.currentTime === 'function';
		var current = isPlayer ? player.currentTime() : player.currentTime;
		var wasPlaying = isPlayer ? ! player.paused() : ! player.paused;

		if ( isPlayer ) {
			player.src( {
				src: source.url,
				type: source.type || undefined
			} );
		} else {
			player.src = source.url;
		}

		if ( current ) {
			try {
				if ( isPlayer ) {
					player.currentTime( current );
				} else {
					player.currentTime = current;
				}
			} catch ( e ) {}
		}

		if ( wasPlaying && typeof player.play === 'function' ) {
			var promise = player.play();

			if ( promise && promise.catch ) {
				promise.catch( function () {} );
			}
		}
	}

	/**
	 * Quality selector entry appended to the player.
	 *
	 * @param {Object}  player    Video.js player or video element.
	 * @param {Element} container Where the control lives.
	 * @param {Array}   sources   Source descriptors.
	 * @param {boolean} isVjs     Whether a Video.js player is in use.
	 */
	function buildQualityControl( player, container, sources, isVjs ) {
		var wrapper = document.createElement( 'div' );
		var button = document.createElement( 'button' );
		var menu = document.createElement( 'div' );

		wrapper.className = 'mt-quality' + ( isVjs ? ' mt-quality-vjs' : ' mt-quality-native' );
		button.type = 'button';
		button.className = 'mt-quality-toggle';
		button.setAttribute( 'aria-expanded', 'false' );
		button.textContent = ( i18n.quality || 'Quality' ) + ': ' + sources[ 0 ].label;

		menu.className = 'mt-quality-menu';
		menu.setAttribute( 'hidden', '' );

		sources.forEach( function ( source ) {
			var item = document.createElement( 'button' );

			item.type = 'button';
			item.className = 'mt-quality-item';
			item.textContent = source.label;
			item.setAttribute( 'data-url', source.url );

			item.addEventListener( 'click', function () {
				switchQuality( player, source );
				button.textContent = ( i18n.quality || 'Quality' ) + ': ' + source.label;
				menu.setAttribute( 'hidden', '' );
				button.setAttribute( 'aria-expanded', 'false' );

				findAll( '.mt-quality-item', menu ).forEach( function ( other ) {
					other.classList.toggle( 'is-active', other === item );
				} );
			} );

			menu.appendChild( item );
		} );

		button.addEventListener( 'click', function () {
			var open = ! menu.hasAttribute( 'hidden' );

			if ( open ) {
				menu.setAttribute( 'hidden', '' );
			} else {
				menu.removeAttribute( 'hidden' );
			}

			button.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! wrapper.contains( e.target ) ) {
				menu.setAttribute( 'hidden', '' );
				button.setAttribute( 'aria-expanded', 'false' );
			}
		} );

		wrapper.appendChild( button );
		wrapper.appendChild( menu );
		container.appendChild( wrapper );
	}

	/**
	 * Local storage that cannot throw.
	 *
	 * Private browsing, a blocked cookie policy and a partitioned frame all
	 * make `localStorage` raise on access rather than return null, and a
	 * player feature is never worth an exception. Every call is guarded, so a
	 * site where storage is unavailable simply loses the conveniences.
	 *
	 * @param {string}   key     Storage key.
	 * @param {*}        defaultValue Value to return when absent.
	 * @return {*} Stored value or the default.
	 */
	function readStore( key, defaultValue ) {
		try {
			var value = window.localStorage.getItem( key );

			return null === value ? defaultValue : value;
		} catch ( e ) {
			return defaultValue;
		}
	}

	/**
	 * Write to local storage, swallowing a refusal.
	 *
	 * @param {string} key   Storage key.
	 * @param {string} value Value to store.
	 * @return {void}
	 */
	function writeStore( key, value ) {
		try {
			window.localStorage.setItem( key, value );
		} catch ( e ) {
			// Storage unavailable: the feature degrades, nothing breaks.
		}
	}

	/**
	 * The underlying media element behind a player or a video tag.
	 *
	 * Video.js wraps the element and exposes most of the same API, but the
	 * element is needed for events and for `playbackRate`, which Video.js
	 * spells `playbackRate()`.
	 *
	 * @param {Object} player Video.js player or media element.
	 * @return {HTMLMediaElement} The media element.
	 */
	function mediaElement( player ) {
		return player && typeof player.el === 'function' ? player.el() : player;
	}

	/**
	 * Read a playback rate from either kind of player.
	 *
	 * @param {Object} player Video.js player or media element.
	 * @return {number} Current rate.
	 */
	function currentRate( player ) {
		if ( player && typeof player.playbackRate === 'function' ) {
			return parseFloat( player.playbackRate() ) || 1;
		}

		var element = mediaElement( player );

		return element ? parseFloat( element.playbackRate ) || 1 : 1;
	}

	/**
	 * Set a playback rate on either kind of player.
	 *
	 * @param {Object} player Video.js player or media element.
	 * @param {number} rate   Target rate.
	 * @return {void}
	 */
	function setRate( player, rate ) {
		if ( player && typeof player.playbackRate === 'function' ) {
			player.playbackRate( rate );

			return;
		}

		var element = mediaElement( player );

		if ( element ) {
			element.playbackRate = rate;
		}
	}

	/**
	 * Whether playback is paused.
	 *
	 * Video.js spells this as a method and the element as a property, so
	 * `player.paused()` throws a TypeError on the native player. Every call
	 * goes through here instead.
	 *
	 * @param {Object} player Video.js player or media element.
	 * @return {boolean} True when paused.
	 */
	function isPaused( player ) {
		if ( player && typeof player.paused === 'function' ) {
			return !! player.paused();
		}

		return ! player || true === player.paused;
	}

	/**
	 * Read the current playback position.
	 *
	 * @param {Object} player Video.js player or media element.
	 * @return {number} Seconds.
	 */
	function getTime( player ) {
		if ( player && typeof player.currentTime === 'function' ) {
			return parseFloat( player.currentTime() ) || 0;
		}

		return player ? parseFloat( player.currentTime ) || 0 : 0;
	}

	/**
	 * Seek, clamped to the media so a keypress can never park the playhead
	 * past the end or before the start.
	 *
	 * @param {Object} player Video.js player or media element.
	 * @param {number} time   Target position in seconds.
	 * @return {void}
	 */
	function setTime( player, time ) {
		if ( ! player ) {
			return;
		}

		var target = Math.max( 0, time );

		var total = durationOf( mediaElement( player ) );

		if ( total > 0 ) {
			target = Math.min( target, total );
		}

		if ( typeof player.currentTime === 'function' ) {
			player.currentTime( target );

			return;
		}

		player.currentTime = target;
	}

	/**
	 * Seek by a signed number of seconds.
	 *
	 * @param {Object} player Video.js player or media element.
	 * @param {number} delta  Seconds to add, may be negative.
	 * @return {void}
	 */
	function seekBy( player, delta ) {
		setTime( player, getTime( player ) + delta );
	}

	/**
	 * Play or pause, whichever is the opposite of what is happening now.
	 *
	 * @param {Object} player Video.js player or media element.
	 * @return {void}
	 */
	function togglePlay( player ) {
		if ( isPaused( player ) ) {
			player.play();
		} else {
			player.pause();
		}
	}

	/**
	 * Format a number of seconds as m:ss, the way a player does.
	 *
	 * @param {number} seconds Seconds.
	 * @return {string} Formatted time.
	 */
	function formatTime( seconds ) {
		seconds = Math.max( 0, Math.floor( seconds ) || 0 );

		var hours = Math.floor( seconds / 3600 );
		var minutes = Math.floor( ( seconds % 3600 ) / 60 );
		var rest = seconds % 60;

		if ( hours > 0 ) {
			return hours + ':' + ( minutes < 10 ? '0' : '' ) + minutes + ':' + ( rest < 10 ? '0' : '' ) + rest;
		}

		return minutes + ':' + ( rest < 10 ? '0' : '' ) + rest;
	}

	/**
	 * Speed control: a button and menu, styled like the quality selector and
	 * deliberately built the same way rather than as a native <select>, so it
	 * matches the rest of the player chrome in both skins.
	 *
	 * @param {Object} player  Video.js player or media element.
	 * @param {Element} container Element the control is appended to.
	 * @param {boolean} isVjs  Whether the control sits in the Video.js bar.
	 * @return {void}
	 */
	function buildSpeedControl( player, container, isVjs ) {
		var speeds = Array.isArray( options.speeds ) && options.speeds.length ? options.speeds : [ 1 ];
		var stored = parseFloat( readStore( 'majestic_tube_speed', '' ) );
		var index = speeds.indexOf( stored );

		if ( -1 === index ) {
			index = speeds.indexOf( 1 );
		}

		if ( -1 === index ) {
			index = 0;
		}

		// Apply the remembered rate immediately, so the first frame a returning
		// visitor sees is already at the speed they chose.
		setRate( player, speeds[ index ] );

		var wrapper = document.createElement( 'div' );
		var button = document.createElement( 'button' );
		var menu = document.createElement( 'div' );

		wrapper.className = 'mt-speed' + ( isVjs ? ' mt-speed-vjs' : ' mt-speed-native' );
		button.type = 'button';
		button.className = 'mt-speed-toggle';
		button.setAttribute( 'aria-expanded', 'false' );
		button.setAttribute( 'aria-label', i18n.speed || 'Speed' );
		button.textContent = speeds[ index ] + 'x';

		menu.className = 'mt-speed-menu';
		menu.setAttribute( 'hidden', '' );

		function closeMenu() {
			menu.setAttribute( 'hidden', '' );
			button.setAttribute( 'aria-expanded', 'false' );
		}

		speeds.forEach( function ( speed, speedIndex ) {
			var item = document.createElement( 'button' );

			item.type = 'button';
			item.className = 'mt-speed-item';
			item.textContent = speed + 'x';
			item.setAttribute( 'data-speed', speed );

			if ( speedIndex === index ) {
				item.classList.add( 'is-active' );
				item.setAttribute( 'aria-current', 'true' );
			}

			item.addEventListener( 'click', function () {
				setRate( player, speed );
				button.textContent = speed + 'x';
				writeStore( 'majestic_tube_speed', String( speed ) );

				findAll( '.mt-speed-item', menu ).forEach( function ( other ) {
					other.classList.toggle( 'is-active', other === item );
					other.removeAttribute( 'aria-current' );
				} );

				item.setAttribute( 'aria-current', 'true' );
				closeMenu();
			} );

			menu.appendChild( item );
		} );

		button.addEventListener( 'click', function () {
			var open = ! menu.hasAttribute( 'hidden' );

			if ( open ) {
				closeMenu();
			} else {
				menu.removeAttribute( 'hidden' );
				button.setAttribute( 'aria-expanded', 'true' );
			}
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! wrapper.contains( e.target ) ) {
				closeMenu();
			}
		} );

		wrapper.appendChild( button );
		wrapper.appendChild( menu );
		container.appendChild( wrapper );
	}

	/**
	 * Theater mode: widen the player across the page and dim everything else.
	 *
	 * A class on the body rather than a scroll lock, so the visitor can still
	 * scroll to the description or the comments. The state is remembered, since
	 * a visitor who prefers watching this way prefers it everywhere.
	 *
	 * @param {Element} wrapper The .video-player element.
	 * @return {void}
	 */
	function initTheaterMode( wrapper ) {
		var button = document.createElement( 'button' );

		button.type = 'button';
		button.className = 'mt-theater-toggle';
		button.setAttribute( 'aria-pressed', 'false' );

		function label( on ) {
			button.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			button.setAttribute( 'aria-label', ( on ? i18n.exitTheater : i18n.theater ) || 'Theater mode' );
			button.setAttribute( 'title', ( on ? i18n.exitTheater : i18n.theater ) || 'Theater mode' );
			button.classList.toggle( 'is-active', on );
		}

		function set( on, remember ) {
			// Both, not just the body: the legacy parity layer paints the
			// html element as well, and its background is what shows at the
			// page edges. The class also has to be on an ancestor for the
			// layout rules to reach the content column.
			document.body.classList.toggle( 'mt-theater', on );
			document.documentElement.classList.toggle( 'mt-theater', on );
			label( on );

			if ( remember ) {
				writeStore( 'majestic_tube_theater', on ? '1' : '0' );
			}
		}

		if ( '1' === readStore( 'majestic_tube_theater', '0' ) ) {
			set( true, false );
		}

		button.addEventListener( 'click', function () {
			set( ! document.documentElement.classList.contains( 'mt-theater' ), true );
		} );

		wrapper.appendChild( button );

		// The keyboard shortcut is handled here rather than in the hotkey
		// table so that the T key works with the shortcuts off too.
		document.addEventListener( 'keydown', function ( event ) {
			if ( 't' !== event.key && 'T' !== event.key ) {
				return;
			}

			if ( isTypingTarget( event.target ) || event.metaKey || event.ctrlKey || event.altKey ) {
				return;
			}

			event.preventDefault();
			set( ! document.documentElement.classList.contains( 'mt-theater' ), true );
		} );
	}

	/**
	 * Whether a key event came from somewhere the visitor is typing.
	 *
	 * Shortcuts must never eat a keystroke destined for a form field, a
	 * contenteditable region or anything with a role that takes text. This is
	 * the single guard every keyboard feature in the player consults.
	 *
	 * @param {Element} target Event target.
	 * @return {boolean} True when the keystroke belongs to the visitor.
	 */
	function isTypingTarget( target ) {
		if ( ! target || ! target.tagName ) {
			return false;
		}

		var tag = target.tagName.toLowerCase();

		if ( 'input' === tag || 'textarea' === tag || 'select' === tag ) {
			return true;
		}

		if ( target.isContentEditable ) {
			return true;
		}

		return false;
	}

	/**
	 * Keyboard shortcuts.
	 *
	 * Video.js already binds space, the arrows, M and F when the player has
	 * focus, so this handler deliberately stays out of their way: it ignores
	 * any event that carries a modifier, comes from a text field, or was
	 * already handled. Only the keys Video.js does not cover on its own -
	 * the digits, J/L, and K for play/pause when focus is outside the player -
	 * are handled here, which is what makes the set feel identical whether
	 * the site runs Video.js or the native player.
	 *
	 * @param {Object} player  Video.js player or media element.
	 * @return {void}
	 */
	function initPlayerHotkeys( player ) {
		var element = mediaElement( player );

		if ( ! element ) {
			return;
		}

		// Video.js duplicates the document-level key only when focus is
		// outside its own element; inside, its own binding wins and pressing
		// the key would otherwise seek twice.
		var vjsOwnsFocus = !! player && typeof player.el === 'function';

		document.addEventListener( 'keydown', function ( event ) {
			if ( event.metaKey || event.ctrlKey || event.altKey || event.defaultPrevented ) {
				return;
			}

			if ( isTypingTarget( event.target ) ) {
				return;
			}

			// A key that reaches a button or a link belongs to that control:
			// space and enter activate it, and stealing them breaks the page.
			if ( ' ' === event.key && event.target && event.target.closest ) {
				var control = event.target.closest( 'button, a[href], [role="button"]' );

				if ( control ) {
					return;
				}
			}

			var key = event.key;
			var handled = true;

			switch ( key ) {
				case ' ':
				case 'k':
				case 'K':
					togglePlay( player );
					break;

				case 'ArrowRight':
					if ( vjsOwnsFocus ) {
						return;
					}

					seekBy( player, 5 );
					break;

				case 'ArrowLeft':
					if ( vjsOwnsFocus ) {
						return;
					}

					seekBy( player, -5 );
					break;

				case 'l':
				case 'L':
					seekBy( player, 10 );
					break;

				case 'j':
				case 'J':
					seekBy( player, -10 );
					break;

				case '0':
				case '1':
				case '2':
				case '3':
				case '4':
				case '5':
				case '6':
				case '7':
				case '8':
				case '9':
					var total = durationOf( element );

					if ( total > 0 ) {
						setTime( player, total * ( parseInt( key, 10 ) / 10 ) );
					}
					break;

				default:
					handled = false;
			}

			if ( handled ) {
				event.preventDefault();
			}
		} );
	}

	/**
	 * The media duration, guarding against the live NaN and Infinity a
	 * not-yet-loaded media element reports.
	 *
	 * @param {HTMLMediaElement} element Media element.
	 * @return {number} Duration in seconds, or 0.
	 */
	function durationOf( element ) {
		var duration = element && element.duration;

		return isFinite( duration ) && duration > 0 ? duration : 0;
	}

	/**
	 * Offer to resume where this visitor stopped.
	 *
	 * Two thresholds, both deliberate. Anything under 30 seconds is not worth
	 * interrupting someone for - they have barely started, and the bar would
	 * cover the very controls they are reaching for. And a position within the
	 * last 15 seconds of the video is no resume at all, it is the end.
	 *
	 * The position is written on a timer and on the way out of the page, not
	 * on every timeupdate, because a write per tick is a lot of storage
	 * traffic for a number that changes visibly only every few seconds.
	 *
	 * @param {Object} player Video.js player or media element.
	 * @param {Element} wrapper The .video-player element.
	 * @return {void}
	 */
	function initResumeOffer( player, wrapper ) {
		var container = document.querySelector( '.video-player' );
		var postId = container ? container.getAttribute( 'data-post-id' ) : '';

		if ( ! postId ) {
			return;
		}

		var key = 'majestic_tube_pos_' + postId;
		var element = mediaElement( player );
		var offer = null;
		var lastSaved = -1;

		// 15 seconds is a long video; 15 percent is a short one. Either is far
		// enough in that the visitor has a real reason to come back.
		function isWorthResuming( time, total ) {
			if ( ! isFinite( time ) || time < 30 ) {
				return false;
			}

			return total > 0 && time < total - 15 && time / total < 0.95;
		}

		function hideOffer() {
			if ( offer && offer.parentNode ) {
				offer.parentNode.removeChild( offer );
			}

			offer = null;
		}

		function showOffer( time ) {
			if ( offer ) {
				return;
			}

			offer = document.createElement( 'div' );
			offer.className = 'mt-resume';

			var resume = document.createElement( 'button' );
			var dismiss = document.createElement( 'button' );

			offer.setAttribute( 'role', 'group' );
			offer.setAttribute( 'aria-label', i18n.resume || 'Resume' );

			resume.type = 'button';
			resume.className = 'mt-resume-play';
			resume.textContent = ( i18n.resume || 'Resume' ) + ' ' + formatTime( time );

			dismiss.type = 'button';
			dismiss.className = 'mt-resume-dismiss';
			dismiss.setAttribute( 'aria-label', i18n.dismiss || 'Dismiss' );
			dismiss.textContent = '×';

			resume.addEventListener( 'click', function () {
				setTime( player, time );
				player.play();
				hideOffer();
			} );

			// Declining clears the stored position, so the offer does not come
			// back on every visit to a video they chose to start again.
			dismiss.addEventListener( 'click', function () {
				writeStore( key, '0' );
				hideOffer();
			} );

			offer.appendChild( resume );
			offer.appendChild( dismiss );
			wrapper.appendChild( offer );
		}

		element.addEventListener( 'loadedmetadata', function () {
			var stored = parseFloat( readStore( key, '0' ) ) || 0;
			var total = durationOf( element );

			if ( isWorthResuming( stored, total ) ) {
				showOffer( stored );
			}
		} );

		function savePosition( force ) {
			var total = durationOf( element );
			var time = element.currentTime;

			// Never write past the end: a finished video should not resume into
			// its last frame.
			if ( ! isWorthResuming( time, total ) ) {
				if ( lastSaved > 0 ) {
					writeStore( key, '0' );
					lastSaved = -1;
				}

				return;
			}

			if ( ! force && Math.abs( time - lastSaved ) < 5 ) {
				return;
			}

			writeStore( key, String( Math.floor( time ) ) );
			lastSaved = time;
		}

		element.addEventListener( 'timeupdate', function () {
			savePosition( false );
		} );

		element.addEventListener( 'pause', function () {
			savePosition( true );
		} );

		element.addEventListener( 'ended', function () {
			writeStore( key, '0' );
			lastSaved = -1;
		} );

		// Leaving the page by closing the tab is the one case no media event
		// covers, and it is exactly when the position is most likely to be
		// wanted on the next visit.
		window.addEventListener( 'pagehide', function () {
			savePosition( true );
		} );
	}

	/**
	 * Initialise the player: Video.js when available, native video otherwise,
	 * plus whichever extras the site has switched on.
	 *
	 * The extras are set up before the quality selector's early return, which
	 * is keyed on there being at least two sources. A single-source video is
	 * still a video, and hotkeys or a speed control on it are exactly as
	 * useful as on a multi-quality one - so that return must stay last.
	 */
	function initPlayer() {
		var video = document.getElementById( 'wpst-video' );

		if ( ! video ) {
			return;
		}

		var sources = getSources( video );
		var controlBar = null;
		var player = video;

		if ( window.videojs && ! options.nativePlayer ) {
			var playerOptions = {
				controlBar: {
					children: [
						'playToggle',
						'progressControl',
						'durationDisplay',
						'volumePanel',
						'fullscreenToggle'
					]
				},
				playsinline: true
			};

			player = window.videojs( 'wpst-video', playerOptions );

			if ( player && typeof player.addClass === 'function' ) {
				player.addClass( 'mt-videojs' );
			}

			if ( player && typeof player.getChild === 'function' ) {
				controlBar = player.getChild( 'controlBar' );
			}
		}

		var bar = controlBar && controlBar.el() ? controlBar.el() : null;
		var wrapper = video.closest( '.video-player' ) || video.parentNode;

		/*
		 * Where a control goes depends on the player: inside the Video.js
		 * control bar when there is one, over the video itself otherwise. The
		 * native path also needs a class so the overlay is positioned, which
		 * the quality selector already sets.
		 */
		function mount( builder, vjs ) {
			if ( bar ) {
				builder( player, bar, vjs );

				return;
			}

			if ( wrapper ) {
				wrapper.classList.add( 'has-native-quality' );
				builder( player, wrapper, false );
			}
		}

		if ( options.playerTheater && wrapper ) {
			initTheaterMode( wrapper );
		}

		if ( options.playerHotkeys ) {
			initPlayerHotkeys( player );
		}

		if ( options.playerResume ) {
			initResumeOffer( player, wrapper );
		}

		if ( options.playerSpeed ) {
			mount( buildSpeedControl, true );
		}

		if ( sources.length < 2 || false === options.qualitySelector || 'off' === options.qualitySelector ) {
			return;
		}

		mount( function ( target, container, vjs ) {
			buildQualityControl( target, container, sources, vjs );
		}, true );
	}

	/**
	 * Wire up the popular tags bar.
	 *
	 * The bar scrolls on its own - overflow-x plus scroll-snap - so all this
	 * adds is the two arrow buttons, and only once it has measured that the
	 * tags actually overflow the row. The markup ships with both buttons
	 * hidden, which is the state a visitor without JavaScript is left in: a
	 * row that scrolls, and no control on it that does nothing.
	 */
	function initTagSlider() {
		findAll( '[data-tags-slider]' ).forEach( function ( slider ) {
			var track = slider.querySelector( '[data-tags-slider-track]' );
			var prev = slider.querySelector( '[data-tags-slider-prev]' );
			var next = slider.querySelector( '[data-tags-slider-next]' );

			if ( ! track || ! prev || ! next ) {
				return;
			}

			// An engine that cannot measure the row cannot be told whether
			// there is anything to scroll, so it keeps the CSS-only bar.
			if ( 'number' !== typeof track.scrollWidth ) {
				return;
			}

			var rtl = 'rtl' === window.getComputedStyle( track ).direction;

			/**
			 * Whether the row has more track left to travel, and how far.
			 *
			 * @return {number} Remaining scroll distance.
			 */
			function maxScroll() {
				return Math.max( 0, track.scrollWidth - track.clientWidth );
			}

			/**
			 * Match each arrow to the end of the row it leads to.
			 */
			function update() {
				var max = maxScroll();

				// Browsers snap a scroll offset to whole device pixels while
				// scrollWidth stays fractional, so the last pixel of travel
				// is never quite the value the arithmetic expects. Without
				// the tolerance the arrow one pixel from the end still looks
				// like it has somewhere to go.
				var atStart = rtl ? track.scrollLeft >= -1 : track.scrollLeft <= 1;
				var atEnd = rtl ? track.scrollLeft <= -( max - 1 ) : track.scrollLeft >= max - 1;

				prev.disabled = atStart;
				next.disabled = atEnd;
			}

			/**
			 * Move the row one click's worth in a direction.
			 *
			 * Slightly less than a full row, so the last chip lands
			 * comfortably inside the row instead of flush against its edge.
			 * No behaviour is passed: the sheet owns that, and the
			 * reduced-motion rule turns it off with everything else.
			 *
			 * @param {number} direction -1 for back, 1 for forward.
			 */
			function nudge( direction ) {
				var step = track.clientWidth * 0.8;

				// Travelling forward in a right-to-left row moves towards the
				// scroll origin, so the sign of the step flips with it.
				track.scrollBy( { left: ( rtl ? -direction : direction ) * step } );
			}

			prev.addEventListener( 'click', function () {
				nudge( -1 );
			} );

			next.addEventListener( 'click', function () {
				nudge( 1 );
			} );

			track.addEventListener( 'scroll', update, { passive: true } );

			/**
			 * Show the arrows only while the tags overflow, and hide them
			 * again when a resize or a short tag list leaves nothing to
			 * scroll - a permanently dead pair of arrows reads as a bug.
			 */
			function sync() {
				var overflowing = track.scrollWidth > track.clientWidth + 1;

				prev.hidden = ! overflowing;
				next.hidden = ! overflowing;

				if ( overflowing ) {
					update();
				}
			}

			sync();

			if ( 'undefined' !== typeof ResizeObserver ) {
				new ResizeObserver( sync ).observe( track );
			} else {
				window.addEventListener( 'resize', sync, { passive: true } );
			}
		} );
	}

	/**
	 * Show the back-to-top link once the visitor has scrolled past the fold.
	 */
	function initBackToTop() {
		var link = document.getElementById( 'back-to-top' );

		if ( ! link ) {
			return;
		}

		function toggle() {
			if ( window.pageYOffset > 300 ) {
				link.classList.add( 'is-visible' );
			} else {
				link.classList.remove( 'is-visible' );
			}
		}

		window.addEventListener( 'scroll', toggle, { passive: true } );
		toggle();

		link.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			window.scrollTo( { top: 0, behavior: 'smooth' } );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initMenuToggle();
		initBackToTop();
		initTagSlider();
		countView();
		initLikeButtons();
		initReadMore();
		initCaptcha( document );
		initUserModal();
		initThemeToggle();
		initThumbRotation();
		initTrailerPreview();
		initPlayerOverlay();
		initReportVideo();
		initPlayer();
		refreshStats( true );
	} );
}() );
