/**
 * YS Store — cart drawer.
 *
 * jQuery is a dependency because WooCommerce's AJAX add-to-cart communicates
 * over jQuery custom events (`added_to_cart`, `wc_fragments_refreshed`) and
 * exposes no other hook. Everything this file does itself is plain DOM.
 */
(function ($) {
	'use strict';

	var cfg = window.ysCart || {};
	var strings = (window.ysStore && window.ysStore.i18n) || {};
	var openOnAdd = window.ysStore ? window.ysStore.openDrawer !== false : true;

	var drawer = document.getElementById('ys-drawer');
	var toggle = document.getElementById('ys-cart-toggle');
	var lastFocus = null;

	/* =========================================================================
	   Open / close
	   ====================================================================== */

	function open() {
		if (!drawer) {
			return;
		}

		lastFocus = document.activeElement;
		drawer.hidden = false;

		// One frame between removing `hidden` and adding `is-open`, or the
		// browser has nothing to transition the transform from.
		window.requestAnimationFrame(function () {
			drawer.classList.add('is-open');
		});

		document.body.classList.add('ys-locked');

		if (toggle) {
			toggle.setAttribute('aria-expanded', 'true');
		}

		var focusable = drawer.querySelector('button, a[href], input');
		if (focusable) {
			focusable.focus();
		}
	}

	function close() {
		if (!drawer) {
			return;
		}

		drawer.classList.remove('is-open');
		document.body.classList.remove('ys-locked');

		if (toggle) {
			toggle.setAttribute('aria-expanded', 'false');
		}

		// Wait for the slide-out before hiding, so the panel does not vanish
		// mid-transition. 600ms matches --dur-slow with room to spare.
		window.setTimeout(function () {
			if (!drawer.classList.contains('is-open')) {
				drawer.hidden = true;
			}
		}, 600);

		if (lastFocus && document.contains(lastFocus)) {
			lastFocus.focus();
		}
	}

	if (toggle) {
		toggle.addEventListener('click', function (e) {
			e.preventDefault();
			open();
		});
	}

	if (drawer) {
		drawer.addEventListener('click', function (e) {
			if (e.target.closest('[data-ys-drawer-close]')) {
				e.preventDefault();
				close();
			}
		});

		document.addEventListener('keydown', function (e) {
			if ('Escape' === e.key && drawer.classList.contains('is-open')) {
				close();
			}
		});

		/*
		 * Focus trap. A dialog with aria-modal="true" that lets Tab walk out
		 * into the page behind it is worse than no dialog at all for anyone
		 * navigating by keyboard.
		 */
		drawer.addEventListener('keydown', function (e) {
			if ('Tab' !== e.key || !drawer.classList.contains('is-open')) {
				return;
			}

			var items = Array.prototype.filter.call(
				drawer.querySelectorAll('a[href], button:not([disabled]), input:not([disabled])'),
				function (el) {
					return el.offsetParent !== null;
				}
			);

			if (!items.length) {
				return;
			}

			var first = items[0];
			var last = items[items.length - 1];

			if (e.shiftKey && document.activeElement === first) {
				e.preventDefault();
				last.focus();
			} else if (!e.shiftKey && document.activeElement === last) {
				e.preventDefault();
				first.focus();
			}
		});
	}

	/* =========================================================================
	   Quantity steppers
	   Delegated at the document level: these buttons exist on the product page,
	   the cart page and inside a drawer that is replaced on every update.
	   ====================================================================== */

	document.addEventListener('click', function (e) {
		var button = e.target.closest('.ys-qty-btn');

		if (!button) {
			return;
		}

		e.preventDefault();

		var wrap = button.closest('.quantity');
		var input = wrap ? wrap.querySelector('input.qty') : null;

		if (!input) {
			return;
		}

		var step = parseFloat(input.getAttribute('step')) || 1;
		var min = input.hasAttribute('min') ? parseFloat(input.getAttribute('min')) : 0;
		var max = input.getAttribute('max') ? parseFloat(input.getAttribute('max')) : Infinity;
		var delta = parseFloat(button.getAttribute('data-qty')) * step;
		var next = Math.min(max, Math.max(min, (parseFloat(input.value) || 0) + delta));

		if (next === parseFloat(input.value)) {
			return;
		}

		input.value = next;

		// Woo's cart page listens for `change` on the quantity field to enable
		// its update button; the drawer listens below to fire its own request.
		input.dispatchEvent(new Event('change', { bubbles: true }));
	});

	/* =========================================================================
	   Drawer line changes
	   ====================================================================== */

	function post(action, data, onDone) {
		var body = new URLSearchParams(
			Object.assign({ action: action, nonce: cfg.nonce }, data)
		);

		if (drawer) {
			drawer.classList.add('is-busy');
		}

		window
			.fetch(cfg.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			})
			.then(function (res) {
				return res.json();
			})
			.then(function (payload) {
				if (!payload || !payload.success) {
					var message = payload && payload.data && payload.data.message;
					if (window.ysToast) {
						window.ysToast(message || strings.error || 'Error');
					}
					return;
				}

				applyFragments(payload.data.fragments);

				if (payload.data.message && window.ysToast) {
					window.ysToast(payload.data.message);
				}

				if (onDone) {
					onDone(payload.data);
				}
			})
			.catch(function () {
				if (window.ysToast) {
					window.ysToast(strings.error || 'Error');
				}
			})
			.finally(function () {
				if (drawer) {
					drawer.classList.remove('is-busy');
				}
			});
	}

	/**
	 * Swap in the markup WooCommerce returned.
	 *
	 * The fragment map is keyed by CSS selector, exactly as Woo's own
	 * `wc_fragments_refreshed` handler expects, so a plugin that adds its own
	 * fragment keeps working.
	 */
	function applyFragments(fragments) {
		if (!fragments) {
			return;
		}

		Object.keys(fragments).forEach(function (selector) {
			var target = document.querySelector(selector);

			if (!target) {
				return;
			}

			var holder = document.createElement('div');
			holder.innerHTML = fragments[selector];

			if (holder.firstElementChild) {
				target.replaceWith(holder.firstElementChild);
			}
		});

		$(document.body).trigger('wc_fragments_refreshed');
	}

	document.addEventListener('click', function (e) {
		var remove = e.target.closest('[data-ys-remove]');

		if (!remove || !drawer || !drawer.contains(remove)) {
			return;
		}

		e.preventDefault();

		var line = remove.closest('.ys-line');

		if (line) {
			line.style.opacity = '0.4';
			post('ys_remove_item', { key: line.getAttribute('data-key') });
		}
	});

	document.addEventListener('change', function (e) {
		var input = e.target;

		if (!input.classList || !input.classList.contains('qty')) {
			return;
		}

		var line = input.closest('.ys-line');

		if (!line || !drawer || !drawer.contains(line)) {
			return;
		}

		post('ys_set_quantity', {
			key: line.getAttribute('data-key'),
			quantity: input.value
		});
	});

	/* =========================================================================
	   WooCommerce AJAX add-to-cart
	   ====================================================================== */

	$(document.body).on('added_to_cart', function (event, fragments, cartHash, button) {
		if (openOnAdd) {
			open();
		} else if (window.ysToast) {
			window.ysToast(strings.added || 'Added to cart');
		}

		// The analytics layer (YS Commerce Insights) listens for this. Emitted
		// as a plain CustomEvent so a store that swaps the plugin out for GTM
		// can subscribe without loading jQuery.
		var node = button && button.length ? button[0] : null;

		document.dispatchEvent(
			new CustomEvent('ys:add_to_cart', {
				detail: {
					id: node ? node.getAttribute('data-product_id') : null,
					name: node ? node.getAttribute('data-ys-name') : null,
					price: node ? parseFloat(node.getAttribute('data-ys-price')) : null,
					quantity: node ? parseFloat(node.getAttribute('data-quantity')) || 1 : 1,
					currency: cfg.currency || null
				}
			})
		);
	});

	/*
	 * A non-AJAX add — the single-product form, or a visitor with JS disabled
	 * during the POST — reloads the page. inc/woocommerce.php leaves a one-shot
	 * flag in the session for exactly that case.
	 */
	if (window.ysOpenCartOnLoad && openOnAdd) {
		open();
	}

	/*
	 * Woo removes and re-adds its AJAX handlers when fragments refresh, but it
	 * does not re-bind the theme's own steppers — which is why every listener
	 * above is delegated on `document` rather than bound to an element.
	 */
})(window.jQuery);
