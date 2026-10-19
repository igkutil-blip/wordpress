/* Plan A naslovnica: odbrojavanje, prelistavanje „Sljedeći polazak”, „još N dana”, skrivanje isteklog (stranica može biti iz predmemorije). */
(function () {
	'use strict';
	function plural(n, f) {
		var a = n % 10, b = n % 100;
		if (a === 1 && b !== 11) return f[0];
		return a >= 2 && a <= 4 && (b < 12 || b > 14) ? f[1] : f[2];
	}
	function pad(n) { return (n < 10 ? '0' : '') + n; }
	function now() { return Math.floor(Date.now() / 1000); }

	function initNext(box) {
		var slides = [].slice.call(box.querySelectorAll('.pab-next__slide'));
		var dots = [].slice.call(box.querySelectorAll('.pab-dots i'));
		// Polasci koji su već krenuli ne prikazuju se.
		slides.forEach(function (s, i) {
			if (+s.dataset.start && +s.dataset.start <= now()) { s.remove(); if (dots[i]) dots[i].remove(); }
		});
		slides = [].slice.call(box.querySelectorAll('.pab-next__slide'));
		dots = [].slice.call(box.querySelectorAll('.pab-dots i'));
		if (!slides.length) { box.remove(); return; }
		if (slides.length < 2) { var n = box.querySelector('.pab-next__nav'), d = box.querySelector('.pab-dots'); if (n) n.remove(); if (d) d.remove(); }
		var cur = 0;
		function show(i) {
			cur = (i + slides.length) % slides.length;
			slides.forEach(function (s, k) { s.hidden = k !== cur; });
			dots.forEach(function (d, k) { d.classList.toggle('is-on', k === cur); });
			tick();
		}
		function tick() {
			var s = slides[cur], left = Math.max(0, (+s.dataset.start || 0) - now());
			var v = { d: Math.floor(left / 86400), h: Math.floor(left % 86400 / 3600), m: Math.floor(left % 3600 / 60), s: left % 60 };
			['d', 'h', 'm', 's'].forEach(function (k) {
				var el = s.querySelector('[data-cd="' + k + '"]');
				if (el) el.textContent = k === 'd' ? v[k] : pad(v[k]);
			});
			var dl = s.querySelector('[data-cd="d"]');
			if (dl && dl.nextElementSibling) dl.nextElementSibling.textContent = plural(v.d, ['dan', 'dana', 'dana']);
		}
		box.addEventListener('click', function (e) {
			var b = e.target.closest('[data-dir]');
			if (b) show(cur + (+b.dataset.dir));
		});
		var x0 = null;
		box.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
		box.addEventListener('touchend', function (e) {
			if (x0 === null || slides.length < 2) return;
			var dx = e.changedTouches[0].clientX - x0; x0 = null;
			if (Math.abs(dx) > 40 && !e.target.closest('a,button')) show(cur + (dx < 0 ? 1 : -1));
		});
		show(0);
		setInterval(tick, 1000);
	}

	function initRibs(root) {
		root.querySelectorAll('.pab-rib[data-until]').forEach(function (r) {
			var left = +r.dataset.until - now();
			if (left <= 0) { r.remove(); return; }
			var d = Math.ceil(left / 86400), el = r.querySelector('[data-pab-left]');
			if (el) el.textContent = d + ' ' + plural(d, ['dan', 'dana', 'dana']);
		});
		root.querySelectorAll('.pab-rib[data-start]').forEach(function (r) {
			if (+r.dataset.start <= now()) r.remove();
		});
		var wrap = root.querySelector('.pab-ribs');
		if (wrap) {
			var n = wrap.querySelectorAll('.pab-rib').length;
			if (!n) wrap.remove(); else wrap.classList.toggle('pab-ribs--one', n === 1);
		}
	}

	function initStrip(root) {
		var row = root.querySelector('.pab-cards');
		if (!row) return;
		row.querySelectorAll('.pab-card[data-start]').forEach(function (c) {
			if (+c.dataset.start <= now()) c.remove();
		});
		root.querySelectorAll('.pab-arr [data-dir]').forEach(function (b) {
			b.addEventListener('click', function () {
				var card = row.querySelector('.pab-card');
				var step = card ? card.getBoundingClientRect().width + 20 : 300;
				row.scrollBy({ left: step * (+b.dataset.dir), behavior: 'smooth' });
			});
		});
	}

	function init() {
		document.querySelectorAll('.pab').forEach(function (root) {
			root.querySelectorAll('[data-pab-next]').forEach(initNext);
			initRibs(root);
			initStrip(root);
		});
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
