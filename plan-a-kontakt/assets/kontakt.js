/* Plan A kontakt: slanje bez ponovnog učitavanja stranice. */
(function () {
	'use strict';
	var start = Date.now();

	function setup(form) {
		var btn = form.querySelector('button[type=submit]');
		var status = form.querySelector('.pakon-poruka');
		var label = btn ? btn.textContent : '';

		function clear() {
			form.querySelectorAll('.pakon-polje').forEach(function (p) {
				p.classList.remove('pakon-krivo');
				var e = p.querySelector('.pakon-greska');
				if (e) { e.textContent = ''; }
			});
			status.textContent = '';
			status.classList.remove('pakon-ne');
		}

		function error(msg, field) {
			var p = field ? form.querySelector('.pakon-polje[data-field="' + field + '"]') : null;
			if (p) {
				p.classList.add('pakon-krivo');
				p.querySelector('.pakon-greska').textContent = msg;
				var input = p.querySelector('input,select,textarea');
				if (input) { input.focus(); }
			} else {
				status.textContent = msg;
				status.classList.add('pakon-ne');
			}
		}

		form.addEventListener('submit', function (ev) {
			ev.preventDefault();
			if (btn && btn.disabled) { return; }
			clear();
			if (btn) { btn.disabled = true; btn.textContent = 'Šaljem…'; }
			// Ljudi ne šalju u prve 3 sekunde; ako je ipak tako, malo pričekaj.
			setTimeout(send, Math.max(0, 3200 - (Date.now() - start)));
		});

		function send() {
			form.querySelector('[name=pakon_js]').value = 'pa' + Math.floor((Date.now() - start) / 1000);
			fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (res && res.success) {
						var box = document.createElement('div');
						box.className = 'pakon-hvala';
						box.setAttribute('role', 'status');
						box.textContent = res.data.message;
						form.parentNode.replaceChild(box, form);
						return;
					}
					var d = (res && res.data) || {};
					error(d.message || 'Poruka nije poslana. Pokušaj ponovno.', d.field || '');
				})
				.catch(function () {
					error('Poruka nije poslana (nema veze). Pokušaj ponovno.', '');
				})
				.finally(function () {
					if (btn) { btn.disabled = false; btn.textContent = label; }
				});
		}
	}

	function init() {
		document.querySelectorAll('.pakon-form').forEach(setup);
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
