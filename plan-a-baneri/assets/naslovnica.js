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

	function money(v) {
		return Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' €';
	}

	/* Slider: slajd se mijenja svakih N s, staje kad je miš iznad ili kad netko koristi karticu (kalkulator, kviz). */
	function initSlider(hero) {
		var slides = [].slice.call(hero.querySelectorAll('.pab-slide'));
		var bgs = [].slice.call(hero.querySelectorAll('.pab-bg'));
		var tabs = [].slice.call(hero.querySelectorAll('.pab-tab'));
		if (slides.length < 2) return;
		var ms = (+hero.dataset.interval || 8) * 1000, cur = 0, timer = null, hover = false, stopped = false;
		var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var seen = true; // vrti se samo dok je vrh stranice vidljiv (na mobitelu se tada ne pomiče ono što se čita ispod)
		hero.style.setProperty('--pab-int', ms / 1000 + 's');
		function loadBgs() {
			bgs.forEach(function (b, k) {
				if (!b.dataset.pabSrc) return;
				var img = document.createElement('img');
				img.className = b.className + ' no-lazy skip-lazy';
				img.alt = b.dataset.pabAlt || '';
				img.decoding = 'async';
				if (b.dataset.pabSrcset) { img.srcset = b.dataset.pabSrcset; img.sizes = '100vw'; }
				img.src = b.dataset.pabSrc;
				b.parentNode.replaceChild(img, b);
				bgs[k] = img;
			});
		}
		if (document.readyState === 'complete') setTimeout(loadBgs, 300); else window.addEventListener('load', function () { setTimeout(loadBgs, 300); });
		function run() {
			clearTimeout(timer);
			tabs.forEach(function (t) { t.classList.remove('is-run'); });
			if (stopped || hover || still || !seen) return;
			var t = tabs[cur];
			if (t) { void t.offsetWidth; t.classList.add('is-run'); }
			timer = setTimeout(function () { go(cur + 1); }, ms);
		}
		function go(i) {
			loadBgs();
			cur = (i + slides.length) % slides.length;
			slides.forEach(function (s, k) {
				var on = k === cur;
				s.classList.toggle('is-on', on);
				if (on) { s.removeAttribute('aria-hidden'); s.removeAttribute('inert'); } else { s.setAttribute('aria-hidden', 'true'); s.setAttribute('inert', ''); }
			});
			bgs.forEach(function (b, k) { b.classList.toggle('is-on', k === cur); });
			tabs.forEach(function (t, k) { t.classList.toggle('is-on', k === cur); t.setAttribute('aria-selected', k === cur ? 'true' : 'false'); });
			run();
		}
		tabs.forEach(function (t, k) { t.addEventListener('click', function () { go(k); }); });
		// Zastane samo dok je miš na bijeloj kartici (ne na cijeloj slici – miš na slici stoji gotovo uvijek).
		hero.querySelectorAll('.pab-w,.pab-next').forEach(function (c) {
			c.addEventListener('mouseenter', function () { hover = true; run(); });
			c.addEventListener('mouseleave', function () { hover = false; run(); });
		});
		var tabsBox = hero.querySelector('.pab-tabs'), helloed = false;
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (en) {
				seen = en[0].isIntersecting;
				// Kad se slider prvi put vidi, tablete kratko poskoče (poziv da se klikne).
				if (seen && !helloed && tabsBox) { helloed = true; setTimeout(function () { tabsBox.classList.add('is-hello'); }, 900); }
				run();
			}, { threshold: 0.35 }).observe(hero.querySelector('.pab-slides'));
		}
		// Rad u kartici (klizač, kviz, odabir tjedna, prelistavanje polazaka) zaustavi vrtnju.
		hero.addEventListener('pointerdown', function (e) { if (e.target.closest('.pab-w,.pab-next')) { stopped = true; run(); } });
		hero.addEventListener('focusin', function (e) { if (e.target.closest('.pab-w')) { stopped = true; run(); } });
		var x0 = null, y0 = null;
		hero.querySelector('.pab-slides').addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; }, { passive: true });
		hero.querySelector('.pab-slides').addEventListener('touchend', function (e) {
			if (x0 === null) return;
			var dx = e.changedTouches[0].clientX - x0, dy = e.changedTouches[0].clientY - y0; x0 = null;
			if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5 && !e.target.closest('.pab-w,.pab-next,input')) { stopped = true; go(cur + (dx < 0 ? 1 : -1)); }
		});
		document.addEventListener('visibilitychange', function () { if (document.hidden) clearTimeout(timer); else run(); });
		run();
	}

	/* Jedrenje: klizač broja osoba → cijena po osobi, odabir tjedna → cijena broda i poveznica na zahtjev. */
	function initSail(box) {
		var range = box.querySelector('input[type=range]'), sel = box.querySelector('.pab-wk.is-sel'), go = box.querySelector('[data-go]');
		function upd() {
			var n = +range.value, price = sel ? +sel.dataset.price : 0;
			box.querySelector('[data-n]').textContent = n;
			box.querySelector('[data-pp]').textContent = money(price / n);
			box.querySelector('[data-boat-price]').textContent = money(price);
			if (sel && go) go.href = box.dataset.url + (box.dataset.url.indexOf('?') < 0 ? '?' : '&') + 'tjedan=' + sel.dataset.start + '&osoba=' + n;
		}
		range.addEventListener('input', upd);
		box.querySelectorAll('.pab-wk:not([disabled])').forEach(function (b) {
			b.addEventListener('click', function () {
				if (sel) sel.classList.remove('is-sel');
				sel = b; b.classList.add('is-sel'); upd();
			});
		});
		upd();
	}

	/* Kviz: 3 pitanja → 1–2 izleta iz stvarnog popisa (ako ništa ne odgovara, najbliži po popuštanju uvjeta). */
	function initQuiz(box) {
		var tours = [];
		try { tours = JSON.parse(box.dataset.tours || '[]'); } catch (e) { return; }
		tours = tours.filter(function (t) { return !t.s || t.s > now(); });
		var qs = [].slice.call(box.querySelectorAll('.pab-quiz__q')), steps = [].slice.call(box.querySelectorAll('.pab-quiz__steps i'));
		var res = box.querySelector('.pab-quiz__res'), list = box.querySelector('.pab-quiz__list'), ans = [];
		function month(off) {
			var p = box.dataset.month.split('-'), y = +p[0], m = +p[1] + off;
			if (m > 12) { m -= 12; y++; }
			return y + '-' + (m < 10 ? '0' : '') + m;
		}
		function fits(t, a, use) {
			if (use[0]) { var d = +a[0]; if (d === 1 ? t.n !== 1 : d === 2 ? (t.n < 2 || t.n > 3) : t.n < 4) return false; }
			if (use[1] && t.g.indexOf(a[1]) < 0) return false;
			if (use[2] && a[2] !== '*' && t.d.slice(0, 7) !== month(+a[2])) return false;
			return true;
		}
		function card(t) {
			var a = document.createElement('a');
			a.className = 'pab-res'; a.href = t.u;
			var info = t.dl + (t.p ? ' · od ' + t.p : '') + (t.x ? ' · <em>popunjeno – lista čekanja</em>' : t.f > 0 && t.f <= 5 ? ' · <em>još ' + t.f + ' ' + plural(t.f, ['mjesto', 'mjesta', 'mjesta']) + '</em>' : '');
			a.innerHTML = (t.i ? '<img src="' + t.i + '" alt="" loading="lazy">' : '') + '<span><b></b><small>' + info + '</small></span>';
			a.querySelector('b').textContent = t.t;
			return a;
		}
		function show(k) {
			qs.forEach(function (q, i) { q.hidden = i !== k; });
			steps.forEach(function (s, i) { s.classList.toggle('is-on', i <= k); });
			res.hidden = k < qs.length;
		}
		function result() {
			var tries = [[1, 1, 1], [1, 1, 0], [0, 1, 1], [0, 1, 0], [1, 0, 0], [0, 0, 0]], hit = [], exact = true;
			for (var i = 0; i < tries.length && !hit.length; i++) {
				hit = tours.filter(function (t) { return fits(t, ans, tries[i]); });
				exact = i === 0;
			}
			hit.sort(function (a, b) { return a.x - b.x || a.s - b.s; });
			list.textContent = '';
			hit.slice(0, 2).forEach(function (t) { list.appendChild(card(t)); });
			box.querySelector('[data-res-h]').textContent = exact ? 'Ovo je za tebe:' : 'Najbliže tvom izboru:';
			show(qs.length);
		}
		box.addEventListener('click', function (e) {
			var o = e.target.closest('.pab-opt');
			if (o) {
				var k = +o.closest('.pab-quiz__q').dataset.q;
				ans[k] = o.dataset.v;
				if (k + 1 < qs.length) show(k + 1); else result();
				return;
			}
			if (e.target.closest('.pab-quiz__again')) { ans = []; show(0); }
		});
	}

	function init() {
		document.querySelectorAll('.pab').forEach(function (root) {
			root.querySelectorAll('[data-pab-slider]').forEach(initSlider);
			root.querySelectorAll('[data-pab-sail]').forEach(initSail);
			root.querySelectorAll('[data-pab-quiz]').forEach(initQuiz);
			root.querySelectorAll('[data-pab-next]').forEach(initNext);
			initRibs(root);
			initStrip(root);
		});
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
