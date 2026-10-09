/* Plan A članstvo: polja za maloljetne, provjera OIB-a, automatska potvrda. */
(function () {
	'use strict';

	function parseDate(v) {
		var m = String(v).trim().match(/^(\d{1,2})\s*[.\/-]\s*(\d{1,2})\s*[.\/-]\s*(\d{4})\.?$/);
		if (!m) { return null; }
		var d = new Date(+m[3], +m[2] - 1, +m[1]);
		return d.getDate() === +m[1] && d.getMonth() === +m[2] - 1 ? d : null;
	}

	function age(d) {
		var t = new Date();
		var a = t.getFullYear() - d.getFullYear();
		if (t.getMonth() < d.getMonth() || (t.getMonth() === d.getMonth() && t.getDate() < d.getDate())) { a--; }
		return a;
	}

	function validOib(o) {
		if (!/^\d{11}$/.test(o)) { return false; }
		var a = 10;
		for (var i = 0; i < 10; i++) {
			a = (a + +o[i]) % 10;
			a = ((a === 0 ? 10 : a) * 2) % 11;
		}
		var c = 11 - a;
		return (c === 10 ? 0 : c) === +o[10];
	}

	function hint(input, ok, text) {
		var box = input.parentNode;
		var el = box.querySelector('.pacl-live');
		if (!el) {
			el = document.createElement('small');
			el.className = 'pacl-live';
			box.appendChild(el);
		}
		el.className = 'pacl-live ' + (ok ? 'pacl-ok-hint' : 'pacl-err');
		el.textContent = text;
	}

	function init(root) {
		root.classList.add('is-js');
		var focus = root.querySelector('[data-pacl-focus]');
		if (focus) { focus.focus({ preventScroll: false }); }

		var auto = root.querySelector('[data-pacl-auto]');
		if (auto) {
			var b = auto.querySelector('button');
			if (b) { b.classList.add('is-busy'); b.textContent = 'Potvrđujem…'; }
			setTimeout(function () { auto.submit(); }, 400);
		}

		var form = root.querySelector('[data-pacl-form]');
		if (!form) { return; }
		var date = form.querySelector('[data-pacl-date]');
		var minor = form.querySelector('[data-pacl-minor]');
		var oib = form.querySelector('[data-pacl-oib]');

		function checkDate() {
			var d = parseDate(date.value);
			var isMinor = d && age(d) < 18;
			minor.classList.toggle('is-on', !!isMinor);
			if (date.value.trim().length >= 8) {
				if (!d) { hint(date, false, 'Upiši datum kao 15.3.1990.'); }
				else if (isMinor) { hint(date, true, 'Mlađi od 18: ispod upiši roditelja ili skrbnika.'); }
				else { hint(date, true, '✔'); }
			}
		}
		if (date && minor) {
			date.addEventListener('input', checkDate);
			date.addEventListener('blur', checkDate);
			checkDate();
			if (minor.querySelector('.pacl-err')) { minor.classList.add('is-on'); }
		}
		if (oib) {
			oib.addEventListener('input', function () {
				oib.value = oib.value.replace(/\D/g, '').slice(0, 11);
				if (oib.value.length === 11) {
					hint(oib, validOib(oib.value), validOib(oib.value) ? '✔ OIB je ispravan' : 'OIB nije ispravan, provjeri znamenke.');
				} else if (oib.parentNode.querySelector('.pacl-live')) {
					oib.parentNode.querySelector('.pacl-live').textContent = '';
				}
			});
		}
		form.addEventListener('submit', function () {
			var btn = form.querySelector('button[type="submit"]');
			if (btn) { btn.classList.add('is-busy'); btn.textContent = 'Šaljem…'; }
		});
	}

	document.querySelectorAll('[data-pacl]').forEach(init);
})();
