/**
 * Customiser live preview.
 *
 * Only the text settings are wired here. The colour settings deliberately fall
 * back to a full refresh: they are printed as a <style> block by
 * ys_store_customizer_css(), and the per-character gradient painting in app.js
 * has to re-run against the new ramp — which a live swap of one variable would
 * not trigger.
 */
(function ($) {
	'use strict';

	function bind(setting, selector, transform) {
		wp.customize(setting, function (value) {
			value.bind(function (next) {
				var el = document.querySelector(selector);

				if (el) {
					el.textContent = transform ? transform(next) : next;
				}
			});
		});
	}

	bind('blogname', '.ys-brand span');
	bind('ys_hero_eyebrow', '[data-ys-hero-eyebrow]');
	bind('ys_hero_text', '[data-ys-hero-text]');

	/*
	 * The headline has been split into per-character spans by the time the
	 * Customiser sends an update, so writing textContent would collapse the
	 * split and leave the characters at their pre-animation transform. Rebuild
	 * the element as flat text and mark it done instead.
	 */
	wp.customize('ys_hero_title', function (value) {
		value.bind(function (next) {
			var el = document.querySelector('[data-ys-hero-title]');

			if (!el) {
				return;
			}

			el.classList.remove('ys-split');
			el.classList.remove('is-painted');
			el.textContent = next;
			el.style.opacity = '1';
			el.style.transform = 'none';
		});
	});
})(window.jQuery);
