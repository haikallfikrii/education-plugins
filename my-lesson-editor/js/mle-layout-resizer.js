/**
 * Draggable column resizers for the 4-panel lesson editor layout.
 * Persists widths in localStorage.
 */
(function() {
	'use strict';

	var STORAGE_KEY = 'mle_layout_widths_v1';
	var layout = document.getElementById('mle-resizable-layout');
	if (!layout) {
		return;
	}

	var defaults = { nav: 168, ai: 300, drafts: 360 };
	var min = { nav: 112, ai: 220, drafts: 240 };
	var max = { nav: 360, ai: 560, drafts: 560 };

	function loadWidths() {
		try {
			var raw = localStorage.getItem(STORAGE_KEY);
			if (!raw) {
				return Object.assign({}, defaults);
			}
			var saved = JSON.parse(raw);
			if (!saved || typeof saved !== 'object') {
				return Object.assign({}, defaults);
			}
			return {
				nav: clamp(saved.nav, min.nav, max.nav, defaults.nav),
				ai: clamp(saved.ai, min.ai, max.ai, defaults.ai),
				drafts: clamp(saved.drafts, min.drafts, max.drafts, defaults.drafts)
			};
		} catch (err) {
			return Object.assign({}, defaults);
		}
	}

	function clamp(value, lo, hi, fallback) {
		var n = parseInt(value, 10);
		if (isNaN(n)) {
			return fallback;
		}
		return Math.min(hi, Math.max(lo, n));
	}

	function applyWidths(widths) {
		layout.style.setProperty('--mle-w-nav', widths.nav + 'px');
		layout.style.setProperty('--mle-w-ai', widths.ai + 'px');
		layout.style.setProperty('--mle-w-drafts', widths.drafts + 'px');
	}

	function saveWidths(widths) {
		try {
			localStorage.setItem(STORAGE_KEY, JSON.stringify(widths));
		} catch (err) { /* noop */ }
	}

	var widths = loadWidths();
	applyWidths(widths);

	layout.querySelectorAll('.mle-layout-resizer').forEach(function(handle) {
		handle.addEventListener('mousedown', function(e) {
			if (window.matchMedia('(max-width: 1280px)').matches) {
				return;
			}
			e.preventDefault();
			var key = handle.getAttribute('data-resize');
			if (!key || !widths[key]) {
				return;
			}

			var startX = e.clientX;
			var startW = widths[key];

			document.body.classList.add('mle-is-resizing');
			handle.classList.add('is-active');
			layout.classList.add('is-resizing');

			function onMove(ev) {
				var dx = ev.clientX - startX;
				if (key === 'nav') {
					widths.nav = clamp(startW + dx, min.nav, max.nav, defaults.nav);
				} else {
					widths[key] = clamp(startW - dx, min[key], max[key], defaults[key]);
				}
				applyWidths(widths);
			}

			function onUp() {
				document.body.classList.remove('mle-is-resizing');
				handle.classList.remove('is-active');
				layout.classList.remove('is-resizing');
				saveWidths(widths);
				document.removeEventListener('mousemove', onMove);
				document.removeEventListener('mouseup', onUp);
			}

			document.addEventListener('mousemove', onMove);
			document.addEventListener('mouseup', onUp);
		});

		handle.addEventListener('dblclick', function(e) {
			e.preventDefault();
			var key = handle.getAttribute('data-resize');
			if (!key || !defaults[key]) {
				return;
			}
			widths[key] = defaults[key];
			applyWidths(widths);
			saveWidths(widths);
		});
	});

	var resetBtn = document.getElementById('mle_reset_layout');
	if (resetBtn) {
		resetBtn.addEventListener('click', function(e) {
			e.preventDefault();
			widths = Object.assign({}, defaults);
			applyWidths(widths);
			saveWidths(widths);
		});
	}
})();
