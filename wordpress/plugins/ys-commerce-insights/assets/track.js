/**
 * YS Commerce Insights — third-party event bridge.
 *
 * Loads GA4 and/or the Meta Pixel, then replays the events the server printed
 * into #ys-insights-events plus anything the theme dispatches at runtime.
 *
 * The vendor scripts are inserted by this file rather than hardcoded into the
 * page, which is what makes the consent gate real: with consent required,
 * nothing is requested from Google or Meta until the site calls
 * window.ysConsentGranted().
 */
(function () {
	'use strict';

	var cfg = window.ysTrack || {};
	var queue = [];
	var ready = false;

	/* =========================================================================
	   Loaders
	   ====================================================================== */

	function loadGA4(id) {
		var script = document.createElement('script');
		script.async = true;
		script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
		document.head.appendChild(script);

		window.dataLayer = window.dataLayer || [];

		window.gtag = function () {
			window.dataLayer.push(arguments);
		};

		window.gtag('js', new Date());
		window.gtag('config', id);
	}

	function loadPixel(id) {
		/* eslint-disable */
		!(function (f, b, e, v, n, t, s) {
			if (f.fbq) return;
			n = f.fbq = function () {
				n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);
			};
			if (!f._fbq) f._fbq = n;
			n.push = n;
			n.loaded = !0;
			n.version = '2.0';
			n.queue = [];
			t = b.createElement(e);
			t.async = !0;
			t.src = v;
			s = b.getElementsByTagName(e)[0];
			s.parentNode.insertBefore(t, s);
		})(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
		/* eslint-enable */

		window.fbq('init', id);
		window.fbq('track', 'PageView');
	}

	/* =========================================================================
	   Event mapping
	   GA4 names are used as the canonical form because they are the ones the
	   server prints. Meta's vocabulary differs, so it is translated here rather
	   than having the server emit two shapes of the same event.
	   ====================================================================== */

	var META_NAMES = {
		view_item: 'ViewContent',
		view_item_list: 'ViewContent',
		add_to_cart: 'AddToCart',
		begin_checkout: 'InitiateCheckout',
		purchase: 'Purchase'
	};

	function toMeta(name, params) {
		var mapped = META_NAMES[name];

		if (!mapped) {
			return null;
		}

		var items = params.items || [];

		return {
			name: mapped,
			params: {
				content_type: 'product',
				content_ids: items.map(function (item) {
					return item.item_id;
				}),
				contents: items.map(function (item) {
					return { id: item.item_id, quantity: item.quantity };
				}),
				value: params.value || 0,
				currency: params.currency || cfg.currency
			}
		};
	}

	function send(name, params) {
		if (!ready) {
			queue.push([name, params]);
			return;
		}

		if (cfg.ga4 && window.gtag) {
			window.gtag('event', name, params);
		}

		if (cfg.pixel && window.fbq) {
			var meta = toMeta(name, params);

			if (meta) {
				// Purchase deduplication: GA4 gets the transaction id in the
				// params, Meta needs it as an eventID so a server-side
				// Conversions API call for the same order collapses into one.
				var options = params.transaction_id ? { eventID: params.transaction_id } : undefined;

				window.fbq('track', meta.name, meta.params, options);
			}
		}
	}

	/* Exposed so a theme, a plugin or a tag manager can push a custom event
	   through the same consent gate. */
	window.ysTrackEvent = send;

	/* =========================================================================
	   Boot
	   ====================================================================== */

	function start() {
		if (ready) {
			return;
		}

		ready = true;

		if (cfg.ga4) {
			loadGA4(cfg.ga4);
		}

		if (cfg.pixel) {
			loadPixel(cfg.pixel);
		}

		var pending = queue.slice();
		queue = [];

		pending.forEach(function (entry) {
			send(entry[0], entry[1]);
		});
	}

	window.ysConsentGranted = start;

	/* ---- Server-printed events --------------------------------------------- */

	function replayServerEvents() {
		var node = document.getElementById('ys-insights-events');

		if (!node) {
			return;
		}

		var events;

		try {
			events = JSON.parse(node.textContent);
		} catch (e) {
			return;
		}

		(events || []).forEach(function (event) {
			send(event.name, event.params || {});
		});
	}

	/* ---- Runtime events ----------------------------------------------------
	   The theme's cart.js fires this after WooCommerce confirms the add, so the
	   event is never sent for an add that failed validation. */

	document.addEventListener('ys:add_to_cart', function (e) {
		var d = e.detail || {};

		send('add_to_cart', {
			currency: d.currency || cfg.currency,
			value: (Number(d.price) || 0) * (Number(d.quantity) || 1),
			items: [
				{
					item_id: d.id ? String(d.id) : '',
					item_name: d.name || '',
					price: Number(d.price) || 0,
					quantity: Number(d.quantity) || 1
				}
			]
		});
	});

	function init() {
		replayServerEvents();

		if (!cfg.needsConsent) {
			start();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
