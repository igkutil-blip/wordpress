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

	/* Izbornici kalendara: otvoren je samo jedan; zatvara se klikom izvan njega ili tipkom Esc. */
	function initCalendar(root) {
		var menus = function () {
			return root.querySelectorAll('details.papl-cal, details.papl-addcal');
		};
		root.addEventListener('toggle', function (e) {
			var opened = e.target;
			if (!opened.open || !opened.matches || !opened.matches('details.papl-cal, details.papl-addcal')) {
				return;
			}
			Array.prototype.forEach.call(menus(), function (d) {
				if (d !== opened) {
					d.open = false;
				}
			});
		}, true);
		document.addEventListener('click', function (e) {
			Array.prototype.forEach.call(menus(), function (d) {
				if (d.open && !d.contains(e.target)) {
					d.open = false;
				}
			});
		});
		root.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape') {
				return;
			}
			Array.prototype.forEach.call(menus(), function (d) {
				if (d.open) {
					d.open = false;
					d.querySelector('summary').focus();
				}
			});
		});
	}

	/* Brzi odabir: Svi, Prijave otvorene, Uskoro, Jednodnevni, Višednevni. */
	function initFilter(root) {
		var bar = root.querySelector('.papl-filter');
		if (!bar) {
			return;
		}
		var empty = root.querySelector('[data-papl-empty]');
		bar.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-papl-filter]');
			if (!btn) {
				return;
			}
			var f = btn.getAttribute('data-papl-filter');
			Array.prototype.forEach.call(bar.querySelectorAll('[data-papl-filter]'), function (b) {
				b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
			});
			var shown = 0;
			Array.prototype.forEach.call(root.querySelectorAll('[data-papl-section]'), function (sec) {
				var n = 0;
				Array.prototype.forEach.call(sec.querySelectorAll('.papl-row'), function (row) {
					var ok = f === 'all' || row.getAttribute('data-status') === f || row.getAttribute('data-days') === f;
					row.hidden = !ok;
					n += ok ? 1 : 0;
				});
				sec.hidden = !n;
				shown += n;
				var chip = root.querySelector('[data-papl-month="' + sec.getAttribute('data-papl-section') + '"]');
				if (chip) {
					chip.hidden = !n;
					var c = chip.querySelector('span');
					if (c) {
						c.textContent = n;
					}
				}
			});
			if (empty) {
				empty.hidden = shown > 0;
			}
		});
	}

	/*
	 * Ako je shortcode upisan u blok s oblikovanim tekstom (<pre>, <code>…), tema mu daje
	 * font pisaćeg stroja; plan tada preuzima font okolnog sadržaja stranice.
	 */
	function fixFont(root) {
		var mono = /mono|courier|consol|menlo|typewriter/i;
		var host = root.parentElement && root.parentElement.closest('pre, code, kbd, samp, tt');
		if (!host && !mono.test(getComputedStyle(root).fontFamily)) {
			return;
		}
		var src = host ? host.parentElement : root.parentElement;
		while (src && src !== document.body && (src.closest('pre, code, kbd, samp, tt') || mono.test(getComputedStyle(src).fontFamily))) {
			src = src.parentElement;
		}
		var font = getComputedStyle(src || document.body).fontFamily;
		if (mono.test(font)) {
			font = 'Lato, "Helvetica Neue", Arial, sans-serif';
		}
		root.style.fontFamily = font;
	}

	/* Za administratora: odakle plan dobiva font (pomaže kod neobičnih postavki teme). */
	function diag(root) {
		var out = root.querySelector('[data-papl-diag]');
		if (!out) {
			return;
		}
		var desc = function (el) {
			return el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '');
		};
		var text = root.querySelector('.papl-summary') || root;
		var parts = ['Font teksta: ' + getComputedStyle(text).fontFamily.split(',')[0]];
		var el = root.parentElement;
		var mono = /mono|courier|consol|menlo|typewriter/i;
		while (el && el !== document.documentElement) {
			if (mono.test(getComputedStyle(el).fontFamily) || /^(PRE|CODE|KBD|SAMP|TT)$/.test(el.tagName)) {
				parts.push('font pisaćeg stroja dolazi od: ' + desc(el));
				break;
			}
			el = el.parentElement;
		}
		parts.push('font plana: ' + (root.style.fontFamily ? root.style.fontFamily.split(',')[0] : 'nije postavljen'));
		/* CSS pravila koja tekstu plana zadaju font, s datotekom iz koje dolaze. */
		var found = [];
		var check = function (rules, href) {
			Array.prototype.forEach.call(rules || [], function (rule) {
				if (found.length >= 4) {
					return;
				}
				if (rule.cssRules && !rule.selectorText) {
					check(rule.cssRules, href);
					return;
				}
				if (!rule.selectorText || !rule.style || !/font/.test(rule.style.cssText)) {
					return;
				}
				var ff = rule.style.getPropertyValue('font-family') || '';
				if (!mono.test(ff) && !/monospace|courier/i.test(rule.style.getPropertyValue('font') || '')) {
					return;
				}
				try {
					if (text.matches(rule.selectorText) || root.matches(rule.selectorText)) {
						found.push(rule.selectorText.slice(0, 80) + ' {' + (ff || rule.style.getPropertyValue('font')) + (rule.style.getPropertyPriority('font-family') ? ' !important' : '') + '} u ' + (href ? href.split('/').slice(-3).join('/').split('?')[0] : 'stilu u stranici'));
					}
				} catch (e) {}
			});
		};
		Array.prototype.forEach.call(document.styleSheets, function (sheet) {
			try {
				check(sheet.cssRules, sheet.href);
			} catch (e) {}
		});
		if (found.length) {
			parts.push('pravilo: ' + found.join(' | '));
		}
		/* Prikaži samo kad nešto nije u redu. */
		if (parts.length > 2 || mono.test(parts[0])) {
			out.textContent = parts.join(' · ');
		}
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-papl]'), function (root) {
			if (root.hasAttribute('data-papl-ready')) {
				return;
			}
			root.setAttribute('data-papl-ready', '');
			diag(root);
			fixFont(root);
			initNotify(root);
			initCalendar(root);
			initFilter(root);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}());
