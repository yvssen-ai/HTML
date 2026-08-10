/**
 * YS Store — motion layer.
 *
 * Everything here is additive. GSAP may be absent (see ys_store_gsap_source),
 * the visitor may have asked for reduced motion, and either way the store has
 * to stay usable: the fallback path clears the pre-animation states written by
 * animations.css and stops, rather than leaving elements at opacity 0.
 *
 * No module system, no build step. The theme ships the file WordPress enqueues.
 */
(function () {
	'use strict';

	var cfg = window.ysStore || {};
	var hasGSAP = typeof window.gsap !== 'undefined';
	var gsap = window.gsap;
	var ScrollTrigger = window.ScrollTrigger;
	var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* =========================================================================
	   Utilities
	   ====================================================================== */

	function $(sel, ctx) {
		return (ctx || document).querySelector(sel);
	}

	function $$(sel, ctx) {
		return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
	}

	function on(el, type, fn, opts) {
		if (el) {
			el.addEventListener(type, fn, opts || false);
		}
	}

	/**
	 * Reveal everything at once and give up on animating.
	 *
	 * Called when GSAP is missing or motion is reduced. The elements are still
	 * in the DOM and still styled — only the entrance is skipped.
	 */
	function revealAll() {
		$$('[data-anim]').forEach(function (el) {
			el.classList.add('is-in');
			el.style.opacity = '';
			el.style.transform = '';
			el.style.clipPath = '';
		});

		$$('.ys-split').forEach(function (el) {
			el.classList.remove('ys-split');
		});
	}

	/* =========================================================================
	   Preloader
	   Runs once per tab. sessionStorage rather than a cookie: a returning
	   visitor in the same session should land on the catalogue, not sit through
	   the intro again, but a genuinely new visit is worth the first impression.
	   ====================================================================== */

	function preloader(done) {
		var el = $('#ys-preloader');

		if (!el) {
			done();
			return;
		}

		var seen = false;

		try {
			seen = window.sessionStorage.getItem('ys_intro') === '1';
		} catch (e) {
			// Private mode, or storage disabled. Show it; that is the safe side.
		}

		if (seen || reduced || !hasGSAP) {
			el.remove();
			done();
			return;
		}

		try {
			window.sessionStorage.setItem('ys_intro', '1');
		} catch (e) {
			/* Nothing to do — the intro simply plays again next time. */
		}

		document.body.classList.add('ys-locked');

		var bar = $('#ys-preloader-bar');
		var count = $('#ys-preloader-count');
		var chars = $$('.ys-preloader__mark span', el);
		var progress = { value: 0 };

		var tl = gsap.timeline({
			onComplete: function () {
				el.remove();
				document.body.classList.remove('ys-locked');
				done();
			}
		});

		tl.from(chars, {
			yPercent: 120,
			duration: 0.7,
			stagger: 0.045,
			ease: 'power3.out'
		})
			.to(
				progress,
				{
					value: 100,
					duration: 1.1,
					ease: 'power2.inOut',
					onUpdate: function () {
						var v = Math.round(progress.value);
						if (bar) {
							bar.style.width = v + '%';
						}
						if (count) {
							count.textContent = v;
						}
					}
				},
				'-=0.35'
			)
			.to(chars, {
				yPercent: -120,
				duration: 0.5,
				stagger: 0.03,
				ease: 'power3.in'
			})
			.to(el, { autoAlpha: 0, duration: 0.4 }, '-=0.2');
	}

	/* =========================================================================
	   Split text
	   Wraps each word (and, for short headings, each character) in a span so a
	   heading can stagger. Only runs on elements marked .ys-split, and it walks
	   text nodes only — an <a> or <em> inside a heading survives untouched.
	   ====================================================================== */

	function splitText(el) {
		var text = el.textContent;

		// Long strings get word-level splitting; per-character on a paragraph
		// creates hundreds of spans for an effect nobody can perceive.
		var perChar = text.trim().length <= 48;

		var frag = document.createDocumentFragment();
		var line = document.createElement('span');
		line.className = 'ys-line';

		text.split(/(\s+)/).forEach(function (chunk) {
			if (!chunk) {
				return;
			}

			if (/^\s+$/.test(chunk)) {
				line.appendChild(document.createTextNode(' '));
				return;
			}

			var word = document.createElement('span');
			word.className = 'ys-word';

			if (perChar) {
				chunk.split('').forEach(function (ch) {
					var span = document.createElement('span');
					span.className = 'ys-char';
					span.textContent = ch;
					word.appendChild(span);
				});
			} else {
				word.textContent = chunk;
			}

			line.appendChild(word);
		});

		frag.appendChild(line);
		el.textContent = '';
		el.appendChild(frag);

		return $$(perChar ? '.ys-char' : '.ys-word', el);
	}

	/**
	 * Paint a gradient across the characters of a heading.
	 *
	 * background-clip:text cannot survive the per-character transforms the
	 * stagger applies, so once the heading is split the technique is swapped:
	 * each character gets a solid colour sampled from the same ramp, and
	 * .is-painted turns the background-clip rule off.
	 */
	function paintGradient(el, chars) {
		if (!hasGSAP || !chars.length || !el.classList.contains('grad')) {
			return;
		}

		var styles = getComputedStyle(document.documentElement);
		var ramp = [
			styles.getPropertyValue('--cyan').trim() || '#00e5ff',
			styles.getPropertyValue('--violet').trim() || '#8b5cf6',
			styles.getPropertyValue('--pink').trim() || '#ff3d81'
		];

		var interpolate = gsap.utils.interpolate(ramp);

		chars.forEach(function (char, i) {
			char.style.color = interpolate(chars.length > 1 ? i / (chars.length - 1) : 0);
		});

		el.classList.add('is-painted');
	}

	/* =========================================================================
	   Scroll reveals
	   ====================================================================== */

	function reveals() {
		var offsets = {
			up: { y: 38 },
			down: { y: -30 },
			left: { x: 40 },
			right: { x: -40 },
			scale: { scale: 0.94 },
			clip: { clipPath: 'inset(0 0 100% 0)' }
		};

		$$('[data-anim]').forEach(function (el) {
			var kind = el.getAttribute('data-anim') || 'up';
			var from = Object.assign({ opacity: 0 }, offsets[kind] || offsets.up);

			var to = {
				opacity: 1,
				x: 0,
				y: 0,
				scale: 1,
				clipPath: 'inset(0 0 0% 0)',
				duration: 0.9,
				ease: 'power3.out',
				overwrite: 'auto',
				onStart: function () {
					el.classList.add('is-in');
				},
				scrollTrigger: {
					trigger: el,
					start: 'top 88%',
					once: true
				}
			};

			// clipPath on an element that never had one produces a tween from
			// "none", which browsers refuse to interpolate.
			if ('clip' !== kind) {
				delete to.clipPath;
			}

			gsap.fromTo(el, from, to);
		});

		// Headings, staggered on top of their own container reveal.
		$$('.ys-split').forEach(function (el) {
			var chars = splitText(el);
			paintGradient(el, chars);

			gsap.to(chars, {
				yPercent: 0,
				duration: 1,
				stagger: 0.022,
				ease: 'power4.out',
				scrollTrigger: {
					trigger: el,
					start: 'top 90%',
					once: true
				}
			});
		});
	}

	/* =========================================================================
	   Chrome: progress bar, sticky nav, burger
	   ====================================================================== */

	function chrome() {
		var bar = $('#ys-progress-bar');
		var nav = $('#ys-nav');

		if (bar || nav) {
			var tick = function () {
				var max = document.documentElement.scrollHeight - window.innerHeight;
				var y = window.scrollY || window.pageYOffset;

				if (bar) {
					bar.style.transform = 'scaleX(' + (max > 0 ? Math.min(1, y / max) : 0) + ')';
				}

				if (nav) {
					nav.classList.toggle('is-stuck', y > 40);
				}
			};

			var queued = false;
			on(
				window,
				'scroll',
				function () {
					if (queued) {
						return;
					}
					queued = true;
					window.requestAnimationFrame(function () {
						tick();
						queued = false;
					});
				},
				{ passive: true }
			);

			tick();
		}

		/* ---- Search panel -------------------------------------------------- */
		var searchToggle = $('#ys-search-toggle');
		var searchPanel = $('#ys-search-panel');

		if (searchToggle && searchPanel) {
			var setSearch = function (open) {
				searchToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
				searchPanel.hidden = !open;

				if (open) {
					var input = $('input[type="search"]', searchPanel);

					if (input) {
						input.focus();
					}
				}
			};

			on(searchToggle, 'click', function () {
				setSearch(searchToggle.getAttribute('aria-expanded') !== 'true');
			});

			on(document, 'keydown', function (e) {
				if ('Escape' === e.key && searchToggle.getAttribute('aria-expanded') === 'true') {
					setSearch(false);
					searchToggle.focus();
				}
			});

			// A click anywhere else dismisses it. The header itself is excluded
			// so the toggle's own click is not counted twice.
			on(document, 'click', function (e) {
				if (
					searchToggle.getAttribute('aria-expanded') === 'true' &&
					!e.target.closest('#ys-search-panel') &&
					!e.target.closest('#ys-search-toggle')
				) {
					setSearch(false);
				}
			});
		}

		/* ---- Mobile menu -------------------------------------------------- */
		var burger = $('#ys-burger');
		var menu = $('#ys-mobile-menu');

		if (burger && menu) {
			var setOpen = function (open) {
				burger.setAttribute('aria-expanded', open ? 'true' : 'false');
				menu.classList.toggle('is-open', open);
				document.body.classList.toggle('ys-locked', open);
			};

			on(burger, 'click', function () {
				setOpen(burger.getAttribute('aria-expanded') !== 'true');
			});

			// A tap on a link inside the menu navigates; the menu must not be
			// left open behind the new page on a same-page anchor.
			$$('a', menu).forEach(function (link) {
				on(link, 'click', function () {
					setOpen(false);
				});
			});

			on(document, 'keydown', function (e) {
				if ('Escape' === e.key && burger.getAttribute('aria-expanded') === 'true') {
					setOpen(false);
					burger.focus();
				}
			});
		}
	}

	/* =========================================================================
	   Custom cursor
	   ====================================================================== */

	function cursor() {
		var root = $('#ys-cursor');

		if (!root || !hasGSAP || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
			return;
		}

		var dot = $('.ys-cursor__dot', root);
		var ring = $('.ys-cursor__ring', root);
		var label = $('.ys-cursor__label', root);

		document.body.classList.add('ys-has-cursor');

		// quickTo keeps one tween alive per property instead of creating a new
		// one on every mousemove — the difference is visible on a slow machine.
		var dx = gsap.quickTo(dot, 'x', { duration: 0.12, ease: 'power3' });
		var dy = gsap.quickTo(dot, 'y', { duration: 0.12, ease: 'power3' });
		var rx = gsap.quickTo(ring, 'x', { duration: 0.42, ease: 'power3' });
		var ry = gsap.quickTo(ring, 'y', { duration: 0.42, ease: 'power3' });

		on(
			window,
			'mousemove',
			function (e) {
				dx(e.clientX);
				dy(e.clientY);
				rx(e.clientX);
				ry(e.clientY);
			},
			{ passive: true }
		);

		// Delegated: product cards and drawer contents are replaced by AJAX, so
		// listeners bound at load would be lost on the first add-to-cart.
		on(document, 'mouseover', function (e) {
			var target = e.target.closest('[data-cursor], a, button');

			if (!target) {
				return;
			}

			var text = target.getAttribute('data-cursor');

			if (text) {
				label.textContent = text;
				gsap.to(label, { opacity: 1, duration: 0.25 });
				gsap.to(ring, { scale: 2.2, duration: 0.35 });
				gsap.to(dot, { scale: 0, duration: 0.25 });
			} else {
				gsap.to(ring, { scale: 1.6, duration: 0.3 });
			}
		});

		on(document, 'mouseout', function (e) {
			if (!e.target.closest('[data-cursor], a, button')) {
				return;
			}

			gsap.to(label, { opacity: 0, duration: 0.18 });
			gsap.to(ring, { scale: 1, duration: 0.35 });
			gsap.to(dot, { scale: 1, duration: 0.25 });
		});

		on(window, 'mousedown', function () {
			gsap.to(ring, { scale: 0.75, duration: 0.18 });
		});

		on(window, 'mouseup', function () {
			gsap.to(ring, { scale: 1, duration: 0.28 });
		});
	}

	/* =========================================================================
	   Pointer effects: magnetic buttons, card highlight, tilt
	   ====================================================================== */

	function pointer() {
		if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
			return;
		}

		if (hasGSAP) {
			$$('[data-magnet]').forEach(function (el) {
				var x = gsap.quickTo(el, 'x', { duration: 0.5, ease: 'elastic.out(1, 0.4)' });
				var y = gsap.quickTo(el, 'y', { duration: 0.5, ease: 'elastic.out(1, 0.4)' });

				on(el, 'mousemove', function (e) {
					var r = el.getBoundingClientRect();
					x((e.clientX - (r.left + r.width / 2)) * 0.32);
					y((e.clientY - (r.top + r.height / 2)) * 0.32);
				});

				on(el, 'mouseleave', function () {
					x(0);
					y(0);
				});
			});
		}

		// The radial highlight. Delegated so AJAX-inserted cards get it too.
		on(
			document,
			'mousemove',
			function (e) {
				var card = e.target.closest('.card, .ys-product-card');

				if (!card) {
					return;
				}

				var r = card.getBoundingClientRect();
				card.style.setProperty('--mx', ((e.clientX - r.left) / r.width) * 100 + '%');
				card.style.setProperty('--my', ((e.clientY - r.top) / r.height) * 100 + '%');
			},
			{ passive: true }
		);
	}

	/* =========================================================================
	   Hero particle field
	   A cheap constellation: points drift, near neighbours are joined. Capped by
	   viewport area so a phone draws roughly a fifth of what a desktop does.
	   ====================================================================== */

	function field() {
		var canvas = $('#ys-field');

		if (!canvas || reduced) {
			return;
		}

		var ctx = canvas.getContext('2d');
		var dpr = Math.min(window.devicePixelRatio || 1, 2);
		var points = [];
		var raf = null;
		var w = 0;
		var h = 0;

		function build() {
			var count = Math.min(90, Math.round((w * h) / 22000));
			points = [];

			for (var i = 0; i < count; i++) {
				points.push({
					x: Math.random() * w,
					y: Math.random() * h,
					vx: (Math.random() - 0.5) * 0.22,
					vy: (Math.random() - 0.5) * 0.22
				});
			}
		}

		function resize() {
			var rect = canvas.getBoundingClientRect();
			w = rect.width;
			h = rect.height;
			canvas.width = w * dpr;
			canvas.height = h * dpr;
			ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
			build();
		}

		function draw() {
			ctx.clearRect(0, 0, w, h);

			for (var i = 0; i < points.length; i++) {
				var p = points[i];
				p.x += p.vx;
				p.y += p.vy;

				if (p.x < 0 || p.x > w) {
					p.vx *= -1;
				}
				if (p.y < 0 || p.y > h) {
					p.vy *= -1;
				}

				ctx.beginPath();
				ctx.arc(p.x, p.y, 1.1, 0, Math.PI * 2);
				ctx.fillStyle = 'rgba(0,229,255,.55)';
				ctx.fill();

				for (var j = i + 1; j < points.length; j++) {
					var q = points[j];
					var dx = p.x - q.x;
					var dy = p.y - q.y;
					var d2 = dx * dx + dy * dy;

					if (d2 < 15000) {
						ctx.beginPath();
						ctx.moveTo(p.x, p.y);
						ctx.lineTo(q.x, q.y);
						ctx.strokeStyle = 'rgba(139,92,246,' + (0.16 * (1 - d2 / 15000)) + ')';
						ctx.lineWidth = 1;
						ctx.stroke();
					}
				}
			}

			raf = window.requestAnimationFrame(draw);
		}

		// Stop the loop the moment the hero leaves the viewport. A canvas
		// animating behind three screens of catalogue is pure battery cost.
		var io = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting && !raf) {
						raf = window.requestAnimationFrame(draw);
					} else if (!entry.isIntersecting && raf) {
						window.cancelAnimationFrame(raf);
						raf = null;
					}
				});
			},
			{ threshold: 0 }
		);

		resize();
		io.observe(canvas);
		on(window, 'resize', resize);
	}

	/* =========================================================================
	   Stat count-up
	   ====================================================================== */

	function counters() {
		if (!hasGSAP || reduced) {
			return;
		}

		$$('[data-count-to]').forEach(function (el) {
			var target = parseFloat(el.getAttribute('data-count-to'));

			if (isNaN(target)) {
				return;
			}

			var suffix = el.textContent.replace(/[0-9.,\s]/g, '');
			var obj = { value: 0 };
			var decimals = target % 1 !== 0 ? 1 : 0;

			gsap.to(obj, {
				value: target,
				duration: 1.6,
				ease: 'power2.out',
				scrollTrigger: { trigger: el, start: 'top 90%', once: true },
				onUpdate: function () {
					el.textContent = obj.value.toFixed(decimals) + suffix;
				}
			});
		});
	}

	/* =========================================================================
	   Marquee
	   CSS drives it by default. GSAP takes over only to add scroll-velocity
	   skew, and sets .is-driven so the two never both own the transform.
	   ====================================================================== */

	function marquee() {
		if (!hasGSAP || reduced || !ScrollTrigger) {
			return;
		}

		$$('.ys-marquee').forEach(function (wrap) {
			var track = $('.ys-marquee__track', wrap);

			if (!track) {
				return;
			}

			track.classList.add('is-driven');

			var loop = gsap.to(track, {
				xPercent: -50,
				duration: 26,
				ease: 'none',
				repeat: -1
			});

			ScrollTrigger.create({
				trigger: wrap,
				start: 'top bottom',
				end: 'bottom top',
				onUpdate: function (self) {
					// Direction follows the scroll, which reads as the strip
					// reacting to the reader rather than running on its own.
					loop.timeScale(self.direction === -1 ? -1 : 1);
				}
			});
		});
	}

	/* =========================================================================
	   Boot
	   ====================================================================== */

	function start() {
		// Read by the safety-net timer in header.php: once this is set, the
		// pre-animation states are this file's responsibility to clear.
		window.ysBooted = true;

		chrome();
		field();
		pointer();

		// Cart and checkout keep the chrome and lose the choreography.
		var quiet = document.body.classList.contains('ys-no-reveal');

		if (!hasGSAP || reduced || quiet) {
			revealAll();

			if (!hasGSAP || reduced) {
				return;
			}
		}

		gsap.registerPlugin(ScrollTrigger);
		gsap.defaults({ ease: 'power3.out' });

		if (!quiet) {
			reveals();
			counters();
		}

		cursor();
		marquee();

		// Images finish loading after the triggers are created, which moves
		// every element below them. Without this the reveals fire at the wrong
		// scroll positions on a slow connection.
		on(window, 'load', function () {
			ScrollTrigger.refresh();
		});
	}

	function boot() {
		preloader(start);
	}

	if ('loading' === document.readyState) {
		on(document, 'DOMContentLoaded', boot);
	} else {
		boot();
	}

	/* Exposed so cart.js can flash a toast without duplicating the markup. */
	window.ysToast = function (message) {
		var el = $('#ys-toast');

		if (!el) {
			return;
		}

		el.textContent = message;
		el.classList.add('is-visible');

		window.clearTimeout(el._timer);
		el._timer = window.setTimeout(function () {
			el.classList.remove('is-visible');
		}, 2600);
	};
})();
