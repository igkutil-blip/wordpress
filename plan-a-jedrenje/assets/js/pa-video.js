/* Plan A – kratki video „kako se prijaviti” u prozorčiću kao poruka na WhatsAppu.
 * Ista datoteka je u plan-a-prijave i plan-a-jedrenje; ako se učita dvaput, radi samo jednom. */
(function () {
	'use strict';
	if (window.paVideoInit) { return; }
	window.paVideoInit = true;

	var box = null, video = null, last = null;

	function el(tag, cls, html) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (html) { e.innerHTML = html; } return e; }
	function esc(s) { return String(s || '').replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	function now() { var d = new Date(); return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }

	function close() {
		if (!box) { return; }
		if (video) { video.pause(); }
		box.classList.remove('is-open');
		document.documentElement.classList.remove('pa-vid-lock');
		if (last) { last.focus(); }
	}

	function open(btn) {
		last = btn;
		if (!box) {
			box = el('div', 'pa-vid');
			box.setAttribute('role', 'dialog');
			box.setAttribute('aria-modal', 'true');
			box.innerHTML = '<div class="pa-vid__bg" data-close></div>'
				+ '<div class="pa-vid__win">'
				+ '<div class="pa-vid__head"><span class="pa-vid__ava"></span><span class="pa-vid__who"><b>Plan A</b><small>na mreži</small></span>'
				+ '<button type="button" class="pa-vid__x" data-close aria-label="Zatvori"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg></button></div>'
				+ '<div class="pa-vid__chat"><div class="pa-vid__msg"><p class="pa-vid__txt"></p>'
				+ '<div class="pa-vid__frame"><video playsinline controls preload="metadata"></video>'
				+ '<button type="button" class="pa-vid__sound">Uključi zvuk</button>'
				+ '<button type="button" class="pa-vid__x2" data-close aria-label="Zatvori video"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg></button></div>'
				+ '<span class="pa-vid__time"></span></div></div></div>';
			document.body.appendChild(box);
			video = box.querySelector('video');
			box.addEventListener('click', function (e) { if (e.target.closest('[data-close]')) { close(); } });
			document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); } });
			var snd = box.querySelector('.pa-vid__sound');
			snd.addEventListener('click', function () { video.muted = false; video.play(); snd.hidden = true; });
			video.addEventListener('volumechange', function () { snd.hidden = !video.muted; });
		}
		var ava = btn.getAttribute('data-pa-avatar');
		box.querySelector('.pa-vid__ava').innerHTML = ava ? '<img src="' + esc(ava) + '" alt="">' : 'A';
		box.querySelector('.pa-vid__txt').innerHTML = esc(btn.getAttribute('data-pa-text') || 'Pogledaj kako to ide:');
		box.querySelector('.pa-vid__time').textContent = now() + ' ✓✓';
		box.setAttribute('aria-label', btn.getAttribute('data-pa-title') || 'Video');
		var src = btn.getAttribute('data-pa-video');
		if (video.getAttribute('src') !== src) {
			video.setAttribute('src', src);
			video.setAttribute('poster', btn.getAttribute('data-pa-poster') || '');
		}
		box.querySelector('.pa-vid__sound').hidden = false;
		video.muted = true; // preglednici puštaju sam od sebe samo bez zvuka
		box.classList.add('is-open');
		document.documentElement.classList.add('pa-vid-lock');
		var p = video.play();
		if (p && p.catch) { p.catch(function () {}); }
		var win = box.querySelector('.pa-vid__win'); win.setAttribute('tabindex', '-1'); win.focus({ preventScroll: true });
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest && e.target.closest('[data-pa-video]');
		if (!btn) { return; }
		e.preventDefault();
		open(btn);
	});
})();
