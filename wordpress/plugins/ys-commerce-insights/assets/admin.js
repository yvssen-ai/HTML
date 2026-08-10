/**
 * YS Commerce Insights — dashboard.
 *
 * One fetch, one render. The chart is drawn on a canvas rather than pulled from
 * a charting library: the whole thing is a line, an area fill and a hover
 * readout, and shipping 60KB of Chart.js into wp-admin to draw it would be the
 * heaviest thing on the page by an order of magnitude.
 */
(function () {
	'use strict';

	var cfg = window.ysInsights || {};
	var t = cfg.i18n || {};
	var app = document.getElementById('ys-ins-app');
	var picker = document.getElementById('ys-ins-range');
	var rangeLabel = document.getElementById('ys-ins-range-label');

	var STORE_KEY = 'ys_insights_range';
	var state = { range: '30d', data: null };

	/* =========================================================================
	   Formatting
	   ====================================================================== */

	var currency = { symbol: '', decimals: 2, position: 'left' };

	function money(value) {
		var n = Number(value) || 0;

		var formatted = n.toLocaleString(cfg.locale || 'en', {
			minimumFractionDigits: currency.decimals,
			maximumFractionDigits: currency.decimals
		});

		switch (currency.position) {
			case 'right':
				return formatted + currency.symbol;
			case 'left_space':
				return currency.symbol + ' ' + formatted;
			case 'right_space':
				return formatted + ' ' + currency.symbol;
			default:
				return currency.symbol + formatted;
		}
	}

	function count(value) {
		return (Number(value) || 0).toLocaleString(cfg.locale || 'en');
	}

	function percent(value) {
		return (Number(value) || 0).toFixed(1) + '%';
	}

	function escapeHtml(value) {
		var div = document.createElement('div');
		div.textContent = value == null ? '' : String(value);

		return div.innerHTML;
	}

	/**
	 * A delta chip.
	 *
	 * `invert` is for metrics where up is bad — refunds. Without it the tile
	 * would paint a 40% rise in refunds green, which is worse than showing no
	 * colour at all.
	 */
	function delta(kpi, invert) {
		if (!kpi || kpi.change === null || typeof kpi.change === 'undefined') {
			return '<div class="ys-ins__delta ys-ins__delta--flat">— ' + escapeHtml(t.noPrevious || '') + '</div>';
		}

		var change = Number(kpi.change);
		var rounded = Math.abs(change) < 0.05 ? 0 : change;
		var good = invert ? rounded < 0 : rounded > 0;
		var tone = rounded === 0 ? 'flat' : good ? 'good' : 'bad';
		var arrow = rounded === 0 ? '→' : rounded > 0 ? '↑' : '↓';

		return (
			'<div class="ys-ins__delta ys-ins__delta--' +
			tone +
			'">' +
			arrow +
			' ' +
			Math.abs(rounded).toFixed(1) +
			'% <span style="opacity:.7">' +
			escapeHtml(t.vsPrevious || '') +
			'</span></div>'
		);
	}

	function tile(kpi, label, format, invert) {
		return (
			'<div class="ys-ins__kpi">' +
			'<div class="ys-ins__kpi-value">' +
			format(kpi ? kpi.value : 0) +
			'</div>' +
			'<div class="ys-ins__kpi-label">' +
			escapeHtml(label) +
			'</div>' +
			delta(kpi, invert) +
			'</div>'
		);
	}

	/* =========================================================================
	   Render
	   ====================================================================== */

	function render(data) {
		currency = data.currency || currency;

		if (rangeLabel) {
			rangeLabel.textContent = data.range.label;
		}

		var k = data.kpis;
		var html = '';

		// A conversion rate with no traffic behind it is a lie, so the notice
		// goes above the tiles rather than in a corner.
		if (!data.tracking.collecting) {
			html +=
				'<div class="ys-ins__notice">' +
				escapeHtml(t.collectorOff || '') +
				'<a href="' +
				escapeHtml(cfg.settings) +
				'">' +
				escapeHtml(t.openSettings || '') +
				'</a></div>';
		} else if (!data.tracking.hasData) {
			html += '<div class="ys-ins__notice">' + escapeHtml(t.collectorNew || '') + '</div>';
		}

		html += '<div class="ys-ins__kpis">';
		html += tile(k.revenue, t.revenue, money);
		html += tile(k.orders, t.orders, count);
		html += tile(k.aov, t.aov, money);
		html += tile(k.cvr, t.cvr, percent);
		html += tile(k.items, t.items, count);
		html += tile(k.customers, t.customers, count);
		html += tile(k.net, t.net, money);
		html += tile(k.refunds, t.refunds, money, true);
		html += '</div>';

		/* ---- Chart + funnel ---------------------------------------------- */
		html +=
			'<div class="ys-ins__grid">' +
			'<div class="ys-ins__panel" style="grid-column:span 2;min-width:0">' +
			'<h2>' +
			escapeHtml(t.revenueOverTime || '') +
			'</h2>' +
			'<div class="ys-ins__chart" id="ys-ins-chart">' +
			'<canvas></canvas><div class="ys-ins__chart-tip"></div>' +
			'</div></div>' +
			'<div class="ys-ins__panel"><h2>' +
			escapeHtml(t.funnel || '') +
			'</h2>' +
			funnelHtml(data.funnel) +
			'</div></div>';

		/* ---- Tables -------------------------------------------------------- */
		html += '<div class="ys-ins__grid">';
		html += panel(t.topProducts, topProductsHtml(data.top));
		html += panel(t.lowStock, lowStockHtml(data.lowStock));
		html += panel(t.recentOrders, recentHtml(data.recent));
		html += panel(t.sources, sourcesHtml(data.sources));
		html += '</div>';

		app.innerHTML = html;

		drawChart(data.series);
	}

	function panel(title, body) {
		return '<div class="ys-ins__panel"><h2>' + escapeHtml(title || '') + '</h2>' + body + '</div>';
	}

	function empty() {
		return '<p class="ys-ins__empty">' + escapeHtml(t.nothing || '') + '</p>';
	}

	function funnelHtml(funnel) {
		var steps = [
			[t.sessions, funnel.sessions],
			[t.viewedProduct, funnel.viewed],
			[t.addedToCart, funnel.added],
			[t.reachedCheckout, funnel.checkout],
			[t.purchased, funnel.purchased]
		];

		var top = funnel.sessions || 1;
		var html = '<div class="ys-ins__funnel">';

		steps.forEach(function (step, i) {
			var value = step[1] || 0;
			var width = Math.max(0, Math.min(100, (value / top) * 100));
			var previous = i > 0 ? steps[i - 1][1] : null;

			html +=
				'<div class="ys-ins__step">' +
				'<span class="ys-ins__step-label">' +
				escapeHtml(step[0] || '') +
				'</span>' +
				'<span class="ys-ins__step-value">' +
				count(value) +
				'</span>' +
				'<span class="ys-ins__step-bar"><i style="width:' +
				width.toFixed(1) +
				'%"></i></span>';

			// The drop between steps is the actionable part: it names the one
			// place in the journey losing the most people.
			if (previous !== null && previous > 0) {
				html +=
					'<span class="ys-ins__step-drop">' +
					((value / previous) * 100).toFixed(1) +
					'% of previous step</span>';
			}

			html += '</div>';
		});

		return html + '</div>';
	}

	function topProductsHtml(rows) {
		if (!rows || !rows.length) {
			return empty();
		}

		var html = '<table class="ys-ins__table"><tbody>';

		rows.forEach(function (row) {
			html +=
				'<tr><td>' +
				(row.edit
					? '<a class="ys-ins__name" href="' + escapeHtml(row.edit) + '">' + escapeHtml(row.name) + '</a>'
					: '<span class="ys-ins__name">' + escapeHtml(row.name) + '</span>') +
				'<span style="color:var(--fg-faint);font-size:11px">' +
				count(row.units) +
				' ' +
				escapeHtml(t.units || '') +
				'</span></td><td>' +
				money(row.revenue) +
				'</td></tr>';
		});

		return html + '</tbody></table>';
	}

	function lowStockHtml(rows) {
		if (!rows || !rows.length) {
			return empty();
		}

		var html = '<table class="ys-ins__table"><tbody>';

		rows.forEach(function (row) {
			var tone = row.stock <= 0 ? 'var(--danger)' : 'var(--warn)';

			html +=
				'<tr><td><a class="ys-ins__name" href="' +
				escapeHtml(row.edit) +
				'">' +
				escapeHtml(row.name) +
				'</a></td><td style="color:' +
				tone +
				'">' +
				count(row.stock) +
				' ' +
				escapeHtml(t.left || '') +
				'</td></tr>';
		});

		return html + '</tbody></table>';
	}

	function recentHtml(rows) {
		if (!rows || !rows.length) {
			return empty();
		}

		var html = '<table class="ys-ins__table"><tbody>';

		rows.forEach(function (row) {
			html +=
				'<tr><td><a href="' +
				escapeHtml(row.edit) +
				'">#' +
				escapeHtml(row.number) +
				'</a> ' +
				'<span style="color:var(--fg-dim)">' +
				escapeHtml(row.customer) +
				'</span><br>' +
				'<span class="ys-ins__pill ys-ins__pill--' +
				escapeHtml(row.status) +
				'">' +
				escapeHtml(row.label) +
				'</span> ' +
				'<span style="color:var(--fg-faint);font-size:11px">' +
				escapeHtml(row.date) +
				'</span></td>' +
				'<td>' +
				money(row.total) +
				'</td></tr>';
		});

		return html + '</tbody></table>';
	}

	function sourcesHtml(rows) {
		if (!rows || !rows.length) {
			return empty();
		}

		var html =
			'<table class="ys-ins__table"><thead><tr><th>Source</th><th>' +
			escapeHtml(t.revenue || '') +
			'</th></tr></thead><tbody>';

		rows.forEach(function (row) {
			html +=
				'<tr><td><span class="ys-ins__name">' +
				escapeHtml(row.source) +
				'</span><span style="color:var(--fg-faint);font-size:11px">' +
				count(row.sessions) +
				' ' +
				escapeHtml(t.sessions || '').toLowerCase() +
				'</span></td><td>' +
				money(row.revenue) +
				'</td></tr>';
		});

		return html + '</tbody></table>';
	}

	/* =========================================================================
	   Chart
	   ====================================================================== */

	function drawChart(series) {
		var wrap = document.getElementById('ys-ins-chart');

		if (!wrap || !series || !series.length) {
			return;
		}

		var canvas = wrap.querySelector('canvas');
		var tip = wrap.querySelector('.ys-ins__chart-tip');
		var ctx = canvas.getContext('2d');
		var points = [];

		function paint(progress) {
			var dpr = Math.min(window.devicePixelRatio || 1, 2);
			var w = wrap.clientWidth;
			var h = wrap.clientHeight;

			canvas.width = w * dpr;
			canvas.height = h * dpr;
			ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
			ctx.clearRect(0, 0, w, h);

			var pad = { top: 14, right: 8, bottom: 22, left: 8 };
			var plotW = w - pad.left - pad.right;
			var plotH = h - pad.top - pad.bottom;

			var max = Math.max.apply(
				null,
				series.map(function (d) {
					return d.revenue;
				})
			);

			// A flat zero series would divide by zero and draw the line off the
			// top of the box; 1 keeps it pinned to the baseline instead.
			max = max > 0 ? max * 1.15 : 1;

			points = series.map(function (d, i) {
				return {
					x: pad.left + (series.length > 1 ? (i / (series.length - 1)) * plotW : plotW / 2),
					y: pad.top + plotH - (d.revenue / max) * plotH,
					datum: d
				};
			});

			/* ---- Gridlines ------------------------------------------------ */
			ctx.strokeStyle = 'rgba(255,255,255,.06)';
			ctx.lineWidth = 1;

			for (var g = 0; g <= 3; g++) {
				var y = pad.top + (plotH / 3) * g;
				ctx.beginPath();
				ctx.moveTo(pad.left, y);
				ctx.lineTo(w - pad.right, y);
				ctx.stroke();
			}

			var visible = points.slice(0, Math.max(2, Math.ceil(points.length * progress)));

			if (visible.length < 2) {
				return;
			}

			/* ---- Area ------------------------------------------------------ */
			var fill = ctx.createLinearGradient(0, pad.top, 0, pad.top + plotH);
			fill.addColorStop(0, 'rgba(0,229,255,.28)');
			fill.addColorStop(1, 'rgba(139,92,246,0)');

			ctx.beginPath();
			ctx.moveTo(visible[0].x, pad.top + plotH);
			visible.forEach(function (p) {
				ctx.lineTo(p.x, p.y);
			});
			ctx.lineTo(visible[visible.length - 1].x, pad.top + plotH);
			ctx.closePath();
			ctx.fillStyle = fill;
			ctx.fill();

			/* ---- Line ------------------------------------------------------ */
			var stroke = ctx.createLinearGradient(pad.left, 0, w - pad.right, 0);
			stroke.addColorStop(0, '#00e5ff');
			stroke.addColorStop(0.55, '#8b5cf6');
			stroke.addColorStop(1, '#ff3d81');

			ctx.beginPath();
			visible.forEach(function (p, i) {
				if (i === 0) {
					ctx.moveTo(p.x, p.y);
				} else {
					ctx.lineTo(p.x, p.y);
				}
			});
			ctx.strokeStyle = stroke;
			ctx.lineWidth = 2;
			ctx.lineJoin = 'round';
			ctx.stroke();

			/* ---- Endpoint --------------------------------------------------- */
			var last = visible[visible.length - 1];
			ctx.beginPath();
			ctx.arc(last.x, last.y, 3.5, 0, Math.PI * 2);
			ctx.fillStyle = '#ff3d81';
			ctx.fill();
		}

		// A single 550ms draw-in. Long enough to read as a chart arriving,
		// short enough that nobody waits for it before scanning the number.
		var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		if (reduced) {
			paint(1);
		} else {
			var startedAt = null;

			var step = function (now) {
				if (!startedAt) {
					startedAt = now;
				}

				var progress = Math.min(1, (now - startedAt) / 550);
				paint(progress);

				if (progress < 1) {
					window.requestAnimationFrame(step);
				}
			};

			window.requestAnimationFrame(step);
		}

		/* ---- Hover readout --------------------------------------------------- */
		canvas.addEventListener('mousemove', function (e) {
			if (!points.length) {
				return;
			}

			var rect = canvas.getBoundingClientRect();
			var x = e.clientX - rect.left;

			var nearest = points.reduce(function (best, p) {
				return Math.abs(p.x - x) < Math.abs(best.x - x) ? p : best;
			}, points[0]);

			tip.innerHTML =
				'<b>' +
				escapeHtml(nearest.datum.date) +
				'</b>' +
				money(nearest.datum.revenue) +
				' · ' +
				count(nearest.datum.orders) +
				' ' +
				escapeHtml((t.orders || '').toLowerCase());

			tip.classList.add('is-visible');

			// Keep the tooltip inside the panel rather than letting it hang off
			// the right edge on the last few points.
			var tipW = tip.offsetWidth;
			var left = Math.min(Math.max(0, nearest.x - tipW / 2), wrap.clientWidth - tipW);

			tip.style.left = left + 'px';
			tip.style.top = Math.max(0, nearest.y - tip.offsetHeight - 10) + 'px';
		});

		canvas.addEventListener('mouseleave', function () {
			tip.classList.remove('is-visible');
		});

		var resizeTimer = null;

		window.addEventListener('resize', function () {
			window.clearTimeout(resizeTimer);
			resizeTimer = window.setTimeout(function () {
				paint(1);
			}, 150);
		});
	}

	/* =========================================================================
	   Load
	   ====================================================================== */

	function load() {
		app.innerHTML = '<p class="ys-ins__loading">' + escapeHtml(t.loading || '') + '</p>';

		window
			.fetch(cfg.root + '?range=' + encodeURIComponent(state.range), {
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': cfg.nonce }
			})
			.then(function (res) {
				if (!res.ok) {
					throw new Error('HTTP ' + res.status);
				}

				return res.json();
			})
			.then(function (data) {
				state.data = data;
				render(data);
			})
			.catch(function () {
				app.innerHTML =
					'<p class="ys-ins__loading">' +
					escapeHtml(t.error || '') +
					' <button type="button" class="button" id="ys-ins-retry">' +
					escapeHtml(t.retry || '') +
					'</button></p>';

				var retry = document.getElementById('ys-ins-retry');

				if (retry) {
					retry.addEventListener('click', load);
				}
			});
	}

	function init() {
		if (!app || !picker) {
			return;
		}

		try {
			var saved = window.localStorage.getItem(STORE_KEY);

			if (saved && cfg.ranges && cfg.ranges[saved]) {
				state.range = saved;
			}
		} catch (e) {
			/* Storage unavailable — the default range is fine. */
		}

		Object.keys(cfg.ranges || {}).forEach(function (key) {
			var option = document.createElement('option');
			option.value = key;
			option.textContent = cfg.ranges[key];
			option.selected = key === state.range;
			picker.appendChild(option);
		});

		picker.addEventListener('change', function () {
			state.range = picker.value;

			try {
				window.localStorage.setItem(STORE_KEY, state.range);
			} catch (e) {
				/* Not worth surfacing. */
			}

			load();
		});

		load();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
