/* Plan A – Unos izleta (kolege): lijepljenje teksta, datumi, okviri za slike, slanje. */
(function () {
	'use strict';
	var cfg = window.papUnos || {};
	var form = document.querySelector('.papu-form');
	if (!form) { return; }
	var $ = function (s, el) { return (el || document).querySelector(s); };
	var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };

	/* ---------- Zalijepljeni tekst: samo odlomci, podebljano, kurziv, popisi ---------- */
	var KEEP = { P: 'p', DIV: 'p', BR: 'br', STRONG: 'strong', B: 'strong', EM: 'em', I: 'em', UL: 'ul', OL: 'ol', LI: 'li', H1: 'h3', H2: 'h3', H3: 'h3', H4: 'h4', H5: 'h4', H6: 'h4' };
	function clean(html) {
		var src = document.createElement('div');
		src.innerHTML = html.replace(/<!--[\s\S]*?-->/g, '').replace(/<(style|script|meta|link|xml|title)[\s\S]*?<\/\1>/gi, '');
		var out = document.createElement('div');
		function walk(node, to) {
			Array.prototype.forEach.call(node.childNodes, function (n) {
				if (n.nodeType === 3) { to.appendChild(document.createTextNode(n.nodeValue.replace(/\s+/g, ' '))); return; }
				if (n.nodeType !== 1) { return; }
				var tag = n.tagName.toUpperCase();
				var st = (n.getAttribute('style') || '').toLowerCase();
				var t = KEEP[tag];
				if (!t && /font-weight\s*:\s*(bold|[6-9]00)/.test(st)) { t = 'strong'; }
				if (!t && /font-style\s*:\s*italic/.test(st)) { t = 'em'; }
				if (t === 'br') { to.appendChild(document.createElement('br')); return; }
				if (t) { var el = document.createElement(t); walk(n, el); to.appendChild(el); } else { walk(n, to); }
			});
		}
		walk(src, out);
		// Tekst izvan odlomka (npr. iz maila) u odlomak.
		var buf = null;
		Array.prototype.slice.call(out.childNodes).forEach(function (n) {
			var block = n.nodeType === 1 && /^(P|UL|OL|H3|H4)$/.test(n.tagName);
			if (block) { buf = null; return; }
			if (n.nodeType === 1 && n.tagName === 'BR') { if (buf) { buf = null; } n.remove(); return; }
			if (!buf) { buf = document.createElement('p'); out.insertBefore(buf, n); }
			buf.appendChild(n);
		});
		// Bez praznih oznaka i bez <p> u <p>.
		$$('p p', out).forEach(function (p) { p.outerHTML = p.innerHTML + '<br>'; });
		$$('strong,em,p,li,h3,h4', out).forEach(function (e) { if (!e.textContent.trim() && !e.querySelector('br')) { e.remove(); } });
		return out.innerHTML.trim();
	}
	function text2html(t) {
		return t.split(/\r?\n\s*\r?\n/).map(function (p) {
			return '<p>' + p.replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; }).replace(/\r?\n/g, '<br>') + '</p>';
		}).join('');
	}
	$$('.papu-rich', form).forEach(function (box) {
		box.addEventListener('paste', function (e) {
			e.preventDefault();
			var cd = e.clipboardData || window.clipboardData;
			var html = cd.getData('text/html');
			var ins = html ? clean(html) : text2html(cd.getData('text/plain') || '');
			document.execCommand('insertHTML', false, ins || ' ');
			box.classList.remove('is-err');
		});
		// Bez prečaca za oblikovanje (samo zalijepi); brisanje i pisanje ostaju.
		box.addEventListener('keydown', function (e) {
			if ((e.ctrlKey || e.metaKey) && /^[biu]$/i.test(e.key)) { e.preventDefault(); }
		});
		box.addEventListener('drop', function (e) { e.preventDefault(); });
	});

	/* ---------- Ispravljeno polje: makni oznaku greške ---------- */
	function unErr(e) {
		var box = e.target.closest && e.target.closest('.papu-f.is-err, .papu-img.is-err');
		if (box) { box.classList.remove('is-err'); var m = box.querySelector('.papu-e'); if (m) { m.textContent = ''; } }
	}
	form.addEventListener('input', unErr);
	form.addEventListener('change', unErr);

	/* ---------- Uključeno / nije uključeno: ista stavka samo u jednom stupcu ---------- */
	form.addEventListener('change', function (e) {
		var t = e.target;
		if (t.matches('input[name="inc[]"],input[name="exc[]"]') && t.checked) {
			var other = t.name === 'inc[]' ? 'exc[]' : 'inc[]';
			$$('input[name="' + other + '"]', form).forEach(function (o) { if (o.value === t.value) { o.checked = false; } });
		}
		if (t.name === 'who' && t.value) {
			var g = $('input[name="guides[]"][value="' + t.value + '"]', form);
			if (g) { g.checked = true; }
		}
	});

	/* ---------- Datumi dd.mm.gggg. (+ kalendar preglednika) ---------- */
	function toHr(iso) { var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso); return m ? m[3] + '.' + m[2] + '.' + m[1] + '.' : ''; }
	function toIso(hr) {
		var m = /^\s*(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})\.?\s*$/.exec(hr || '');
		if (!m) { return ''; }
		var d = ('0' + m[1]).slice(-2), mo = ('0' + m[2]).slice(-2);
		var t = new Date(m[3] + '-' + mo + '-' + d + 'T12:00:00');
		return isNaN(t) || t.getDate() !== +m[1] ? '' : m[3] + '-' + mo + '-' + d;
	}
	$$('.papu-date', form).forEach(function (w) {
		var txt = $('input[type=text]', w), nat = $('.papu-native', w), btn = $('.papu-cal', w);
		btn.addEventListener('click', function () {
			nat.value = toIso(txt.value) || '';
			try { if (nat.showPicker) { nat.showPicker(); return; } } catch (e) {}
			nat.focus(); nat.click();
		});
		nat.addEventListener('change', function () { txt.value = toHr(nat.value); txt.dispatchEvent(new Event('input')); });
		txt.addEventListener('blur', function () { var iso = toIso(txt.value); if (iso) { txt.value = toHr(iso); } });
	});
	var timeIn = $('input[name=time]', form);
	timeIn.addEventListener('blur', function () {
		var m = /^\s*(\d{1,2})(?:[:.,h]?(\d{2}))?\s*$/.exec(timeIn.value);
		if (m && +m[1] < 24 && (!m[2] || +m[2] < 60)) { timeIn.value = ('0' + m[1]).slice(-2) + ':' + (m[2] || '00'); }
	});

	/* ---------- Okviri za slike (3:2): povlačenje i povećanje ---------- */
	var slots = {};
	var imgsWrap = $('.papu-imgs', form), addBtn = $('.papu-addimg', form), tpl = $('.papu-img-tpl', form);
	var GAL_MIN = 4, GAL_MAX = 20;
	// Okviri galerije uvijek se zovu g1…gN redom kako stoje (glavna je „main”).
	function renumber() {
		slots = {};
		var i = 0;
		$$('.papu-img', imgsWrap).forEach(function (box) {
			var k = box.getAttribute('data-slot') === 'main' ? 'main' : 'g' + (++i);
			box.setAttribute('data-slot', k);
			if (k !== 'main') { $('.papu-img-n', box).textContent = 'Slika ' + i; }
			slots[k] = box._st;
		});
		if (addBtn) { addBtn.parentNode.hidden = i >= GAL_MAX; }
		return i;
	}
	function addSlot() {
		var box = tpl.content.firstElementChild.cloneNode(true);
		imgsWrap.appendChild(box);
		initSlot(box);
		renumber();
		return box;
	}
	if (addBtn) {
		addBtn.addEventListener('click', function () { var b = addSlot(); b.scrollIntoView({ behavior: 'smooth', block: 'center' }); $('.papu-file', b).click(); });
	}
	imgsWrap.addEventListener('click', function (e) {
		var del = e.target.closest('.papu-delimg');
		if (!del) { return; }
		var box = del.closest('.papu-img');
		if (box._st && box._st.url && box._st.url.indexOf('blob:') === 0) { URL.revokeObjectURL(box._st.url); }
		box.remove();
		renumber();
	});
	$$('.papu-img', form).forEach(initSlot);
	renumber();
	function initSlot(box) {
		var frame = $('.papu-frame', box), img = $('img', frame), file = $('.papu-file', box);
		var zoom = $('.papu-zoom', box), tools = $('.papu-img-tools', box), err = $('.papu-e', box), smallNote = $('.papu-small', box);
		var st = { ok: false, w: 0, h: 0, z: 1, cx: 0, cy: 0, url: '' };
		box._st = st;

		function size() { return { w: frame.clientWidth, h: frame.clientHeight }; }
		function scale() { var f = size(); return Math.max(f.w / st.w, f.h / st.h) * st.z; }
		function draw() {
			if (!st.ok) { return; }
			var f = size(), s = scale();
			// Središte okvira u koordinatama slike; ograniči da okvir uvijek bude pun.
			var hw = f.w / 2 / s, hh = f.h / 2 / s;
			st.cx = Math.min(Math.max(st.cx, hw), st.w - hw);
			st.cy = Math.min(Math.max(st.cy, hh), st.h - hh);
			img.style.width = (st.w * s) + 'px';
			img.style.height = (st.h * s) + 'px';
			img.style.transform = 'translate(' + (f.w / 2 - st.cx * s) + 'px,' + (f.h / 2 - st.cy * s) + 'px)';
			var sw = f.w / s;
			st.small = st.keep ? !!st.keepSmall : sw < (cfg.min || 1200);
			smallNote.hidden = !st.small;
		}
		st.draw = draw;
		st.crop = function () {
			var f = size(), s = scale();
			return { sx: st.cx - f.w / 2 / s, sy: st.cy - f.h / 2 / s, sw: f.w / s, sh: f.h / s };
		};
		st.img = img;

		function pick() { file.click(); }
		$('.papu-pick', box).addEventListener('click', pick);
		$('.papu-change', box).addEventListener('click', pick);
		// Učitaj sliku u okvir (nova datoteka ili već spremljena slika izleta kod ispravka).
		function load(url, keepId, revoke) {
			var test = new Image();
			test.onload = function () {
				if (st.url && st.url.indexOf('blob:') === 0) { URL.revokeObjectURL(st.url); }
				st.url = url; st.ok = true; st.w = test.naturalWidth; st.h = test.naturalHeight; st.z = 1;
				st.cx = st.w / 2; st.cy = st.h / 2; zoom.value = 1; st.keep = keepId || 0;
				img.src = url; img.hidden = false;
				frame.classList.add('has-img'); tools.hidden = false; box.classList.remove('is-err'); err.textContent = '';
				draw();
				if (keepId) { smallNote.hidden = !st.keepSmall; st.small = !!st.keepSmall; }
			};
			test.onerror = function () {
				if (revoke) { URL.revokeObjectURL(url); }
				err.textContent = 'Ova slika se ne može otvoriti (npr. HEIC s iPhonea na računalu). Spremi je kao JPG ili je pošalji s mobitela.';
			};
			test.src = url;
		}
		st.load = load;
		file.addEventListener('change', function () {
			var f = file.files && file.files[0];
			if (!f) { return; }
			err.textContent = '';
			box.classList.remove('is-err');
			load(URL.createObjectURL(f), 0, true);
			file.value = '';
		});
		zoom.addEventListener('input', function () { st.z = parseFloat(zoom.value) || 1; st.keep = 0; draw(); });

		// Povlačenje mišem ili prstom.
		var drag = null;
		frame.addEventListener('pointerdown', function (e) {
			if (!st.ok || e.target.closest('.papu-pick')) { return; }
			drag = { x: e.clientX, y: e.clientY, cx: st.cx, cy: st.cy };
			st.keep = 0; // pomaknuta slika se ponovno sprema
			frame.setPointerCapture(e.pointerId);
			frame.classList.add('is-drag');
			e.preventDefault();
		});
		frame.addEventListener('pointermove', function (e) {
			if (!drag) { return; }
			var s = scale();
			st.cx = drag.cx - (e.clientX - drag.x) / s;
			st.cy = drag.cy - (e.clientY - drag.y) / s;
			draw();
		});
		function end() { drag = null; frame.classList.remove('is-drag'); }
		frame.addEventListener('pointerup', end);
		frame.addEventListener('pointercancel', end);
		frame.addEventListener('wheel', function (e) {
			if (!st.ok) { return; }
			e.preventDefault();
			st.z = Math.min(3, Math.max(1, st.z * (e.deltaY < 0 ? 1.06 : 1 / 1.06))); st.keep = 0;
			zoom.value = st.z; draw();
		}, { passive: false });
	}
	window.addEventListener('resize', function () { Object.keys(slots).forEach(function (k) { slots[k].draw(); }); });

	function blob(st) {
		return new Promise(function (resolve) {
			var c = st.crop();
			var w = Math.round(Math.min(cfg.w || 1800, c.sw)), h = Math.round(w / 1.5);
			var cv = document.createElement('canvas');
			cv.width = w; cv.height = h;
			var ctx = cv.getContext('2d');
			ctx.imageSmoothingQuality = 'high';
			ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, w, h);
			ctx.drawImage(st.img, c.sx, c.sy, c.sw, c.sh, 0, 0, w, h);
			cv.toBlob(function (b) { resolve(b); }, 'image/jpeg', 0.86);
		});
	}

	/* ---------- Provjera i slanje ---------- */
	var btn = $('.papu-send button', form), status = $('.papu-status', form);
	function clearErr() {
		$$('.papu-f.is-err, .papu-img.is-err', form).forEach(function (f) { f.classList.remove('is-err'); });
		$$('.papu-e', form).forEach(function (e) { e.textContent = ''; });
		status.textContent = ''; status.className = 'papu-status';
	}
	function showErr(field, msg) {
		var box = field ? (field.indexOf('img_') === 0 ? $('.papu-img[data-slot="' + field.slice(4) + '"]', form) : $('.papu-f[data-f="' + field + '"]', form)) : null;
		if (box) {
			box.classList.add('is-err');
			var e = $('.papu-e', box); if (e) { e.textContent = msg; }
			box.scrollIntoView({ behavior: 'smooth', block: 'center' });
			var inp = $('input:not([type=hidden]):not(.papu-native),select,textarea,.papu-rich', box);
			if (inp && inp.focus) { setTimeout(function () { inp.focus({ preventScroll: true }); }, 350); }
		} else {
			status.textContent = msg; status.className = 'papu-status is-err';
		}
	}
	function check() {
		var need = [['who', 'Odaberi tko unosi izlet.'], ['title', 'Upiši naslov izleta.'], ['content', 'Zalijepi tekst izleta.'], ['location', 'Odaberi lokaciju.'], ['days', 'Upiši trajanje (broj dana).'],
			['start', 'Upiši datum početka (npr. 21.11.2026.).'], ['time', 'Upiši sat polaska (npr. 06:30).'], ['price', 'Upiši cijenu po osobi.'], ['seats', 'Upiši broj mjesta.']];
		for (var i = 0; i < need.length; i++) {
			var n = need[i][0], el = n === 'content' ? $('.papu-rich[data-name=content]', form) : form.elements[n];
			var v = n === 'content' ? el.textContent.trim() : (el && el.value || '').trim();
			if (!v) { return [n, need[i][1]]; }
		}
		if (!toIso(form.elements.start.value)) { return ['start', 'Datum upiši ovako: 21.11.2026.']; }
		var faqs = $$('.papu-rich[data-name^="faq"]', form);
		for (var j = 0; j < faqs.length; j++) {
			if (!faqs[j].textContent.trim()) { return [faqs[j].closest('.papu-f').getAttribute('data-f'), 'Upiši odgovor.']; }
		}
		if (!$$('input[name="guides[]"]:checked', form).length) { return ['guides', 'Označi barem jednog vodiča.']; }
		var miss = Object.keys(slots).filter(function (k) { return !slots[k].ok; });
		if (miss.length) { return ['img_' + miss[0], miss[0] === 'main' ? 'Dodaj glavnu sliku.' : (+miss[0].slice(1) > GAL_MIN ? 'Dodaj sliku ili makni ovaj prazan okvir (×).' : 'Dodaj sve 4 slike za galeriju.')]; }
		return null;
	}

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		if (btn.disabled) { return; }
		clearErr();
		var bad = check();
		if (bad) { showErr(bad[0], bad[1]); return; }
		btn.disabled = true; btn.textContent = 'Pripremam slike…';
		var fd = new FormData();
		fd.append('action', 'pap_unos_send');
		$$('input,select,textarea', form).forEach(function (el) {
			if (!el.name || el.type === 'file' || el.classList.contains('papu-native')) { return; }
			if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) { return; }
			fd.append(el.name, el.value);
		});
		$$('.papu-rich', form).forEach(function (b) { fd.append(b.getAttribute('data-name'), clean(b.innerHTML)); });
		fd.append('gcount', renumber());
		var keys = Object.keys(slots);
		Promise.all(keys.map(function (k) { return slots[k].keep ? null : blob(slots[k]); })).then(function (blobs) {
			keys.forEach(function (k, i) {
				if (slots[k].keep) { fd.append('keep_' + k, slots[k].keep); return; } // nepromijenjena slika ostaje
				fd.append('img_' + k, blobs[i], k + '.jpg');
				if (slots[k].small) { fd.append('small[]', k); }
			});
			btn.textContent = 'Šaljem…';
			return fetch(cfg.ajax, { method: 'POST', body: fd, credentials: 'same-origin' });
		}).then(function (r) { return r.json(); }).then(function (res) {
			if (res && res.success) {
				var done = document.createElement('div');
				done.className = 'papu-done';
				done.innerHTML = '<i class="fas fa-circle-check" aria-hidden="true"></i><p></p><a href="' + location.pathname + '">' + (editId.value ? 'Natrag na unos izleta' : 'Unesi još jedan izlet') + '</a>';
				$('p', done).textContent = res.data.message;
				form.parentNode.replaceChild(done, form);
				window.scrollTo({ top: done.getBoundingClientRect().top + window.scrollY - 120, behavior: 'smooth' });
				return;
			}
			var d = (res && res.data) || {};
			showErr(d.field || '', d.message || 'Nije poslano. Pokušaj ponovno.');
		}).catch(function () {
			showErr('', 'Nije poslano (veza je prekinuta). Pokušaj ponovno – ništa se nije izgubilo.');
		}).then(function () {
			if (btn.isConnected) { btn.disabled = false; btn.textContent = editId.value ? 'Spremi ispravke' : 'Pošalji na pregled'; }
		});
	});

	/* ---------- Tvoji poslani izleti (prema „Tko unosi”) i ispravak ---------- */
	var sent = document.querySelector('.papu-sent');
	var editId = form.elements.edit_id;
	var bar = $('.papu-editbar', form);
	function showSent(who) {
		if (!sent) { return; }
		var n = 0;
		$$('li', sent).forEach(function (li) { var on = li.getAttribute('data-by') === String(who); li.hidden = !on; n += on ? 1 : 0; });
		sent.hidden = !who;
		$('.papu-sent-none', sent).hidden = n > 0;
	}
	form.elements.who.addEventListener('change', function () { showSent(this.value); });
	showSent(form.elements.who.value);

	function setRich(name, html) { var b = $('.papu-rich[data-name="' + name + '"]', form); if (b) { b.innerHTML = html || ''; } }
	function setChecks(name, vals) { vals = (vals || []).map(String); $$('input[name="' + name + '[]"]', form).forEach(function (c) { c.checked = vals.indexOf(c.value) >= 0; }); }

	if (sent) {
		sent.addEventListener('click', function (e) {
			var b = e.target.closest('.papu-edit');
			if (!b) { return; }
			b.disabled = true;
			var fd = new FormData(); fd.append('action', 'pap_unos_get'); fd.append('id', b.getAttribute('data-id'));
			fetch(cfg.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (res) {
				b.disabled = false;
				if (!res || !res.success) { alert((res && res.data && res.data.message) || 'Izlet se ne može otvoriti.'); return; }
				var d = res.data, f = form.elements;
				clearErr();
				editId.value = d.id;
				f.who.value = d.who; f.title.value = d.title; f.location.value = d.location; f.country2.value = d.country2 || ''; f.days.value = d.days;
				f.start.value = d.start; f.time.value = d.time; f.price.value = d.price; f.seats.value = d.seats; f.rate.value = d.rate; f.note.value = d.note;
				setRich('content', d.content);
				Object.keys(d.faq || {}).forEach(function (i) { setRich('faq[' + i + ']', d.faq[i]); });
				setChecks('inc', d.inc); setChecks('exc', d.exc); setChecks('act', d.act); setChecks('guides', d.guides);
				var need = Object.keys(d.imgs || {}).filter(function (k) { return k !== 'main'; }).length;
				while (renumber() < need) { addSlot(); }
				Object.keys(slots).forEach(function (k) {
					var im = (d.imgs || {})[k];
					if (im) { slots[k].keepSmall = (d.small || []).indexOf(k) >= 0; slots[k].load(im.url, im.id, false); }
				});
				$('strong', bar).textContent = d.title;
				bar.hidden = false;
				btn.textContent = 'Spremi ispravke';
				window.scrollTo({ top: form.getBoundingClientRect().top + window.scrollY - 100, behavior: 'smooth' });
			}).catch(function () { b.disabled = false; alert('Izlet se ne može otvoriti (veza).'); });
		});
	}
	$('.papu-edit-cancel', form).addEventListener('click', function () { location.href = location.pathname; });
})();
