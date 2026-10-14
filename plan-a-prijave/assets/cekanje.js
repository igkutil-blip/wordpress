/* Plan A – lista čekanja: prozorčić s obrascem za popunjeni izlet. */
(function () {
	'use strict';
	if (window.papWaitInit) { return; }
	window.papWaitInit = true;

	var box = null, form = null, last = null, opened = 0;
	var X = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>';

	function esc(s) { return String(s || '').replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

	function field(name, label, type, extra) {
		return '<div class="pap-wl__f" data-f="' + name + '"><label for="pw-' + name + '">' + label + '</label>'
			+ '<input id="pw-' + name + '" type="' + type + '" name="pw_' + name + '" ' + (extra || '') + '><span class="pap-wl__err"></span></div>';
	}

	function build() {
		box = document.createElement('div');
		box.className = 'pap-wl';
		box.setAttribute('role', 'dialog');
		box.setAttribute('aria-modal', 'true');
		box.setAttribute('aria-label', 'Lista čekanja');
		var osobe = '';
		for (var i = 1; i <= 4; i++) { osobe += '<option value="' + i + '">' + i + '</option>'; }
		box.innerHTML = '<div class="pap-wl__bg" data-close></div><div class="pap-wl__win" tabindex="-1">'
			+ '<div class="pap-wl__head"><h3>Lista čekanja</h3><button type="button" class="pap-wl__x" data-close aria-label="Zatvori">' + X + '</button></div>'
			+ '<p class="pap-wl__tour"></p>'
			+ '<form novalidate>'
			+ '<div class="pap-wl__row">' + field('ime', 'Ime', 'text', 'autocomplete="given-name" maxlength="40" required') + field('prezime', 'Prezime', 'text', 'autocomplete="family-name" maxlength="60" required') + '</div>'
			+ field('mobitel', 'Mobitel', 'tel', 'autocomplete="tel" inputmode="tel" maxlength="30" placeholder="091 234 5678" required')
			+ field('email', 'E-mail <small>(nije obavezno)</small>', 'email', 'autocomplete="email" maxlength="190"')
			+ '<div class="pap-wl__f" data-f="osobe"><label for="pw-osobe">Broj osoba</label><select id="pw-osobe" name="pw_osobe">' + osobe + '</select><span class="pap-wl__err"></span></div>'
			+ '<div class="pap-wl__f" data-f="ok"><label class="pap-wl__ok"><input type="checkbox" name="pw_ok" value="1"><span>Slažem se da Plan A moje podatke koristi samo za javljanje o slobodnom mjestu na ovom izletu. Podaci se brišu 30 dana nakon izleta.</span></label><span class="pap-wl__err"></span></div>'
			+ '<div class="pap-wl__hp" aria-hidden="true"><input type="text" name="pw_web" tabindex="-1" autocomplete="off"></div>'
			+ '<input type="hidden" name="action" value="pap_wait"><input type="hidden" name="pw_tour"><input type="hidden" name="pw_date"><input type="hidden" name="pw_js">'
			+ '<button type="submit" class="pap-wl__send">Upiši me na listu čekanja</button><p class="pap-wl__msg" role="alert"></p>'
			+ '</form></div>';
		document.body.appendChild(box);
		form = box.querySelector('form');
		box.addEventListener('click', function (e) { if (e.target.closest('[data-close]')) { close(); } });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && box.classList.contains('is-open')) { close(); } });
		form.addEventListener('submit', submit);
	}

	function close() {
		box.classList.remove('is-open');
		document.documentElement.classList.remove('pap-wl-lock');
		if (last) { last.focus({ preventScroll: true }); }
	}

	function clear() {
		form.querySelectorAll('.pap-wl__f').forEach(function (f) { f.classList.remove('is-bad'); f.querySelector('.pap-wl__err').textContent = ''; });
		form.querySelector('.pap-wl__msg').textContent = '';
	}

	function error(msg, f) {
		var el = f ? form.querySelector('.pap-wl__f[data-f="' + f + '"]') : null;
		if (el) {
			el.classList.add('is-bad');
			el.querySelector('.pap-wl__err').textContent = msg;
			var inp = el.querySelector('input,select');
			if (inp) { inp.focus(); }
		} else {
			form.querySelector('.pap-wl__msg').textContent = msg;
		}
	}

	function submit(e) {
		e.preventDefault();
		var btn = form.querySelector('.pap-wl__send');
		if (btn.disabled) { return; }
		clear();
		var v = function (n) { return (form.elements[n].value || '').trim(); };
		if (v('pw_ime').length < 2) { return error('Upiši ime.', 'ime'); }
		if (v('pw_prezime').length < 2) { return error('Upiši prezime.', 'prezime'); }
		if (v('pw_mobitel').replace(/\D/g, '').length < 8) { return error('Upiši broj mobitela (npr. 091 234 5678).', 'mobitel'); }
		if (!form.elements.pw_ok.checked) { return error('Označi privolu za podatke.', 'ok'); }
		btn.disabled = true;
		btn.textContent = 'Šaljem…';
		setTimeout(function () {
			form.elements.pw_js.value = 'pa' + Math.floor((Date.now() - opened) / 1000);
			fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					var d = (res && res.data) || {};
					if (res && res.success) {
						var done = document.createElement('div');
						done.className = 'pap-wl__done';
						done.setAttribute('role', 'status');
						done.innerHTML = '<b>Upisan/a si na listu čekanja ✓</b>' + esc(d.message);
						form.parentNode.replaceChild(done, form);
						if (last && d.n) {
							var n = last.parentNode.querySelector('.pap-wait__n');
							if (!n) { n = document.createElement('p'); n.className = 'pap-wait__n'; last.parentNode.appendChild(n); }
							n.innerHTML = 'Na listi čekanja: <b>' + parseInt(d.n, 10) + '</b>';
						}
						return;
					}
					error(d.message || 'Upis nije poslan. Pokušaj ponovno.', d.field || '');
					if (d.reload) { setTimeout(function () { location.reload(); }, 2500); }
				})
				.catch(function () { error('Upis nije poslan (nema veze). Pokušaj ponovno.', ''); })
				.finally(function () { btn.disabled = false; btn.textContent = 'Upiši me na listu čekanja'; });
		}, Math.max(0, 3200 - (Date.now() - opened)));
	}

	function open(btn) {
		last = btn;
		if (!box) { build(); }
		if (!box.querySelector('form')) { // nakon uspješnog upisa: novi obrazac
			box.parentNode.removeChild(box);
			build();
		}
		opened = Date.now();
		form.setAttribute('action', btn.getAttribute('data-url'));
		form.elements.pw_tour.value = btn.getAttribute('data-pap-wait');
		form.elements.pw_date.value = btn.getAttribute('data-date');
		var t = btn.getAttribute('data-title') || '', d = btn.getAttribute('data-date') || '', m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(d);
		box.querySelector('.pap-wl__tour').textContent = t + (m ? ' · ' + parseInt(m[3], 10) + '.' + parseInt(m[2], 10) + '.' + m[1] + '.' : '');
		clear();
		box.classList.add('is-open');
		document.documentElement.classList.add('pap-wl-lock');
		box.querySelector('.pap-wl__win').focus({ preventScroll: true });
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest && e.target.closest('[data-pap-wait]');
		if (!btn) { return; }
		e.preventDefault();
		open(btn);
	});
})();
