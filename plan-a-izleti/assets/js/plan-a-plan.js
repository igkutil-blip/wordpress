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

	/*
	 * Dvije kućice s padajućim izbornikom: "Izleti" (svi, prijave otvorene, uskoro,
	 * jednodnevni, višednevni) i "Termin" (mjesec). Odabiri se kombiniraju.
	 */
	function initFilter(root) {
		var box = root.querySelector('[data-papl-filters]');
		if (!box) {
			return;
		}
		var empty = root.querySelector('[data-papl-empty]');
		var state = { kind: 'all', month: 'all' };
		var rows = root.querySelectorAll('[data-papl-section] .papl-row');

		function toggle(name, open) {
			Array.prototype.forEach.call(box.querySelectorAll('[data-papl-toggle]'), function (t) {
				var n = t.getAttribute('data-papl-toggle');
				var on = open && n === name;
				t.setAttribute('aria-expanded', on ? 'true' : 'false');
				t.classList.toggle('is-open', on);
				box.querySelector('[data-papl-panel="' + n + '"]').hidden = !on;
			});
		}

		function matchKind(row, kind) {
			return kind === 'all' || row.getAttribute('data-status') === kind || row.getAttribute('data-days') === kind;
		}

		function monthOf(row) {
			return row.closest('[data-papl-section]').getAttribute('data-papl-section');
		}

		function apply() {
			var shown = 0;
			Array.prototype.forEach.call(rows, function (row) {
				var ok = matchKind(row, state.kind) && (state.month === 'all' || monthOf(row) === state.month);
				row.hidden = !ok;
				shown += ok ? 1 : 0;
			});
			Array.prototype.forEach.call(root.querySelectorAll('[data-papl-section]'), function (sec) {
				sec.hidden = !sec.querySelector('.papl-row:not([hidden])');
			});
			if (empty) {
				empty.hidden = shown > 0;
			}
			/* Brojevi uz opcije prema drugom odabiru; opcije bez izleta su zasivljene. */
			Array.prototype.forEach.call(box.querySelectorAll('[data-papl-panel="month"] [data-papl-opt]'), function (opt) {
				var m = opt.getAttribute('data-papl-opt');
				var n = 0;
				Array.prototype.forEach.call(rows, function (row) {
					n += matchKind(row, state.kind) && (m === 'all' || monthOf(row) === m) ? 1 : 0;
				});
				opt.querySelector('[data-papl-n]').textContent = n;
				opt.disabled = !n && m !== 'all';
				opt.classList.toggle('is-disabled', opt.disabled);
			});
			Array.prototype.forEach.call(box.querySelectorAll('[data-papl-panel="kind"] [data-papl-opt]'), function (opt) {
				var k = opt.getAttribute('data-papl-opt');
				var n = 0;
				Array.prototype.forEach.call(rows, function (row) {
					n += matchKind(row, k) && (state.month === 'all' || monthOf(row) === state.month) ? 1 : 0;
				});
				opt.querySelector('[data-papl-n]').textContent = n;
				opt.disabled = !n && k !== 'all';
				opt.classList.toggle('is-disabled', opt.disabled);
			});
		}

		box.addEventListener('click', function (e) {
			var t = e.target.closest('[data-papl-toggle]');
			if (t) {
				toggle(t.getAttribute('data-papl-toggle'), t.getAttribute('aria-expanded') !== 'true');
				return;
			}
			var opt = e.target.closest('[data-papl-opt]');
			if (!opt || opt.disabled) {
				return;
			}
			var panel = opt.closest('[data-papl-panel]');
			var name = panel.getAttribute('data-papl-panel');
			state[name] = opt.getAttribute('data-papl-opt');
			Array.prototype.forEach.call(panel.querySelectorAll('[data-papl-opt]'), function (o) {
				o.classList.toggle('is-active', o === opt);
				o.setAttribute('aria-pressed', o === opt ? 'true' : 'false');
			});
			box.querySelector('[data-papl-toggle="' + name + '"] [data-papl-value]').textContent = opt.getAttribute('data-papl-label');
			toggle(name, false);
			apply();
		});
		document.addEventListener('click', function (e) {
			if (!box.contains(e.target)) {
				toggle('', false);
			}
		});
		box.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				var open = box.querySelector('[data-papl-toggle][aria-expanded="true"]');
				toggle('', false);
				if (open) {
					open.focus();
				}
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
