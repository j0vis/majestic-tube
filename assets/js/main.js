/**
 * Majestic Tube main front-end script.
 *
 * Vanilla JS, no jQuery. Talks to the original WP-Script ajax endpoints:
 * action=post-views, post-like, get-post-data and report-video, all signed with
 * the shared `ajax-nonce` the original theme used.
 *
 * @package Majestic Tube
 * @version 2.0.9
 */

( function () {
	'use strict';

	var data = window.majesticTubeData || {};
	var options = data.options || {};
	var i18n = data.i18n || {};
	var THUMBS_INTERVAL = 750; // Original rotation speed.
	var THUMBS_FIRST_DELAY = 150;
	var HOVER_INTENT = 100;

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
	 * @return {Promise<Object>} Parsed JSON response.
	 */
	function ajaxRequest( body, encoded ) {
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
			return response.text();
		} ).then( function ( text ) {
			try {
				return JSON.parse( text );
			} catch ( error ) {
				return { success: false, message: text };
			}
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

		return ajaxRequest( body.toString(), true );
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
			}

			var footer = modal.querySelector( '.majestic-tube-modal-footer' );

			if ( footer ) {
				footer.setAttribute( 'data-active-tab', tab );
			}
		}

		findAll( 'a[href="#wpst-user-modal"]' ).forEach( function ( opener ) {
			opener.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				showTab( '.wpst-login' );
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
	 * Thumbnail rotation on hover (original `thumbs` meta, `data-thumbs`).
	 */
	function initThumbRotation() {
		if ( false === options.rotateThumbs || 'off' === options.rotateThumbs ) {
			return;
		}

		findAll( '.video-card-thumbnail[data-thumbs]' ).forEach( function ( card ) {
			// .video-main-thumb is the original class of the card image; the
			// generic img lookup stays as a fallback for older child themes.
			var img = getCardImage( card );
			var raw = card.getAttribute( 'data-thumbs' );

			if ( ! img || ! raw ) {
				return;
			}

			var thumbs = raw.split( ',' ).map( function ( url ) {
				return url.trim();
			} ).filter( Boolean );

			if ( thumbs.length < 2 ) {
				return;
			}

			var mainSrc = card.getAttribute( 'data-main-thumb' ) || img.getAttribute( 'src' );
			var index = 1;
			var timer = null;

			card.addEventListener( 'mouseenter', function () {
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
			} );

			card.addEventListener( 'mouseleave', function () {
				window.clearTimeout( timer );
				timer = null;
				index = 1;

				if ( mainSrc ) {
					img.setAttribute( 'src', mainSrc );
				}
			} );
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
	 * shown as an overlay. Trailers take precedence over thumb rotation.
	 */
	function initTrailerPreview() {
		findAll( '.video-card-thumbnail[data-trailer]' ).forEach( function ( card ) {
			var trailerUrl = card.getAttribute( 'data-trailer' );

			if ( ! trailerUrl ) {
				return;
			}

			var isVideo = /\.(mp4|webm)(\?.*)?$/i.test( trailerUrl );
			var isImage = /\.(gif|webp)(\?.*)?$/i.test( trailerUrl );

			if ( ! isVideo && ! isImage ) {
				return;
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

			card.addEventListener( 'mouseenter', function () {
				window.clearTimeout( intentTimer );
				intentTimer = window.setTimeout( start, HOVER_INTENT );
			} );

			card.addEventListener( 'mouseleave', stop );
			card.addEventListener( 'touchstart', function () {
				window.clearTimeout( intentTimer );
				intentTimer = window.setTimeout( start, HOVER_INTENT );
			}, { passive: true } );
			card.addEventListener( 'touchend', stop );
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
	 * Close button for the optional player content overlay.
	 */
	function initContentClose() {
		findAll( '.happy-inside-player .close' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var zone = button.closest( '.happy-inside-player' );

				if ( zone ) {
					zone.style.display = 'none';
				}
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
	 * Initialise the player: Video.js when available, native video otherwise,
	 * plus the quality selector.
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

		if ( sources.length < 2 || false === options.qualitySelector || 'off' === options.qualitySelector ) {
			return;
		}

		if ( controlBar && controlBar.el() ) {
			buildQualityControl( player, controlBar.el(), sources, true );
		} else {
			var wrapper = video.closest( '.video-player' ) || video.parentNode;

			if ( wrapper ) {
				wrapper.classList.add( 'has-native-quality' );
				buildQualityControl( video, wrapper, sources, false );
			}
		}
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
		countView();
		initLikeButtons();
		initReadMore();
		initUserModal();
		initThumbRotation();
		initTrailerPreview();
		initContentClose();
		initReportVideo();
		initPlayer();
		refreshStats( true );
	} );
}() );
