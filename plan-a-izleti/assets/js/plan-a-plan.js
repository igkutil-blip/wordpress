/*
 * Plan A izleti – plan izleta: "Javi mi kad bude objavljen" i izbornik kalendara.
 */
(function () {
	'use strict';

	var cfg = window.planAPlan || {};

	function initNotify(root) {
		root.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-papl-notify]');
			if (!btn || !root.contains(btn)) {
				return;
			}
			var form = document.getElementById(btn.getAttribute('aria-controls'));
			if (!form) {
				return;
			}
			var open = btn.getAttribute('aria-expanded') === 'true';
			btn.setAttribute('aria-expanded', open ? 'false' : 'true');
			form.hidden = open;
			if (!open) {
				var input = form.querySelector('input[type="email"]');
				if (input && !form.classList.contains('is-done')) {
					input.focus();
				}
			}
		});

		root.addEventListener('submit', function (e) {
			var form = e.target.closest('[data-papl-form]');
			if (!form) {
				return;
			}
			e.preventDefault();
			var msg = form.querySelector('.papl-notify__msg');
			var input = form.querySelector('input[type="email"]');
			var submit = form.querySelector('button[type="submit"]');
			var show = function (text, ok) {
				msg.textContent = text;
				msg.className = 'papl-notify__msg ' + (ok ? 'is-ok' : 'is-error');
			};
			if (!input.value || !input.checkValidity()) {
				show('Upiši ispravnu e-mail adresu.', false);
				input.setAttribute('aria-invalid', 'true');
				input.focus();
				return;
			}
			input.removeAttribute('aria-invalid');
			if (!cfg.ajax || !window.fetch || !window.FormData) {
				show('Obavijest trenutno nije dostupna. Pokušaj kasnije.', false);
				return;
			}
			var data = new FormData(form);
			data.append('action', 'paiz_plan_notify');
			submit.disabled = true;
			fetch(cfg.ajax, { method: 'POST', body: data, credentials: 'same-origin' })
				.then(function (r) {
					return r.json().catch(function () {
						return { ok: false, message: 'Nešto nije u redu. Pokušaj ponovno.' };
					});
				})
				.then(function (res) {
					show(res.message || '', !!res.ok);
					if (res.ok) {
						form.classList.add('is-done');
					}
				})
				.catch(function () {
					show('Nema veze s poslužiteljem. Pokušaj ponovno.', false);
				})
				.then(function () {
					submit.disabled = false;
				});
		});
	}

	/* Izbornik kalendara: zatvori klikom izvan njega ili tipkom Esc. */
	function initCalendar(root) {
		var cal = root.querySelector('.papl-cal');
		if (!cal) {
			return;
		}
		document.addEventListener('click', function (e) {
			if (cal.open && !cal.contains(e.target)) {
				cal.open = false;
			}
		});
		cal.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && cal.open) {
				cal.open = false;
				cal.querySelector('summary').focus();
			}
		});
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-papl]'), function (root) {
			if (root.hasAttribute('data-papl-ready')) {
				return;
			}
			root.setAttribute('data-papl-ready', '');
			initNotify(root);
			initCalendar(root);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}());
