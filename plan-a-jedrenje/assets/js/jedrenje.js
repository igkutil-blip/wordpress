/*
 * Plan A jedrenje – kalendar tjedana i zahtjev za rezervaciju.
 * Stanja tjedana i nonce dohvaćaju se svježe (admin-ajax), a sve se ponovno provjerava na poslužitelju.
 */
(function () {
	'use strict';

	var MONTHS = ['Siječanj', 'Veljača', 'Ožujak', 'Travanj', 'Svibanj', 'Lipanj', 'Srpanj', 'Kolovoz', 'Rujan', 'Listopad', 'Studeni', 'Prosinac'];

	function money(v) {
		v = Math.round(v * 100) / 100;
		var dec = Math.abs(v - Math.round(v)) < 0.005 ? 0 : 2;
		var parts = v.toFixed(dec).split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
		return parts.join(',') + ' €';
	}

	function el(tag, cls, text) {
		var e = document.createElement(tag);
		if (cls) {
			e.className = cls;
		}
		if (text !== undefined && text !== null) {
			e.textContent = text;
		}
		return e;
	}

	function init(root) {
		var cfgEl = root.querySelector('[data-pajd-config]');
		if (!cfgEl) {
			return;
		}
		var cfg;
		try {
			cfg = JSON.parse(cfgEl.textContent);
		} catch (e) {
			return;
		}
		var monthsBox = root.querySelector('[data-pajd-months]');
		var prev = root.querySelector('[data-pajd-prev]');
		var next = root.querySelector('[data-pajd-next]');
		var range = root.querySelector('[data-pajd-range]');
		var book = root.querySelector('[data-pajd-book]');
		var form = root.querySelector('[data-pajd-form]');
		var msg = root.querySelector('[data-pajd-msg]');
		var done = root.querySelector('[data-pajd-done]');
		var persons = root.querySelector('[data-pajd-persons]');
		var share = root.querySelector('[data-pajd-share]');
		var weeks = cfg.weeks || [];
		var months = [];
		var index = 0;
		var selected = null;

		function visibleCount() {
			return root.getBoundingClientRect().width >= 860 ? 3 : 1;
		}

		function buildMonths() {
			var seen = {};
			months = [];
			weeks.forEach(function (w) {
				if (!seen[w.month]) {
					seen[w.month] = true;
					months.push(w.month);
				}
			});
		}

		function monthTitle(key, withYear) {
			var p = key.split('-');
			return MONTHS[parseInt(p[1], 10) - 1] + (withYear ? ' ' + p[0] + '.' : '');
		}

		function render() {
			var count = visibleCount();
			if (index > Math.max(0, months.length - count)) {
				index = Math.max(0, months.length - count);
			}
			monthsBox.textContent = '';
			monthsBox.classList.toggle('is-multi', count > 1);
			if (!months.length) {
				monthsBox.appendChild(el('p', 'pajd-loading', 'Trenutno nema slobodnih tjedana u sezoni. Javi nam se za dogovor.'));
				range.textContent = '';
				prev.disabled = next.disabled = true;
				return;
			}
			var shown = months.slice(index, index + count);
			shown.forEach(function (key) {
				var box = el('div', 'pajd-month');
				box.appendChild(el('h4', 'pajd-month__title', monthTitle(key, true)));
				var list = el('div', 'pajd-weeks');
				weeks.filter(function (w) {
					return w.month === key;
				}).forEach(function (w) {
					var b = el('button', 'pajd-week is-' + w.state);
					b.type = 'button';
					b.setAttribute('data-start', w.start);
					b.setAttribute('aria-pressed', selected && selected.start === w.start ? 'true' : 'false');
					if (selected && selected.start === w.start) {
						b.classList.add('is-selected');
					}
					b.appendChild(el('span', 'pajd-week__from', w.from));
					b.appendChild(el('span', 'pajd-week__to', 'do ' + w.to));
					var price = el('span', 'pajd-week__price', money(w.price));
					if (w.regular > w.price) {
						price.appendChild(el('del', '', money(w.regular)));
					}
					b.appendChild(price);
					if (w.state === 'request') {
						b.appendChild(el('span', 'pajd-week__tag', 'Na upitu'));
					} else if (w.state === 'booked') {
						b.appendChild(el('span', 'pajd-week__tag', 'Zauzeto'));
						b.disabled = true;
					}
					b.setAttribute('aria-label', w.label + ', ' + money(w.price) + ' za cijeli brod' + (w.state === 'request' ? ', na upitu' : w.state === 'booked' ? ', zauzeto' : ', slobodno'));
					list.appendChild(b);
				});
				box.appendChild(list);
				monthsBox.appendChild(box);
			});
			var first = shown[0];
			var last = shown[shown.length - 1];
			range.textContent = shown.length > 1
				? monthTitle(first, first.slice(0, 4) !== last.slice(0, 4)) + ' – ' + monthTitle(last, true)
				: monthTitle(first, true);
			prev.disabled = index <= 0;
			next.disabled = index + count >= months.length;
		}

		function sumRow(dl, label, value) {
			dl.appendChild(el('dt', '', label));
			dl.appendChild(el('dd', '', value));
		}

		function updatePerPerson() {
			if (!selected) {
				return;
			}
			var n = parseInt(persons.value, 10) || cfg.max;
			root.querySelector('[data-pajd-pp]').textContent = 'Za ' + n + ' osoba to je ' + money(selected.price / n) + ' po osobi.';
			updateShare(n);
		}

		function updateShare(n) {
			if (!share) {
				return;
			}
			var lines = ['Hej, predlažem da idemo zajedno na jedrenje ⛵', '*' + cfg.title + '*'];
			if (selected) {
				lines.push('📅 ' + selected.label + ' ' + selected.start.slice(0, 4) + '.');
			}
			lines.push('🛥️ ' + cfg.boat + ', ' + cfg.marina);
			if (selected) {
				lines.push('💶 ' + money(selected.price) + ' za cijeli brod (za ' + n + ' osoba ' + money(selected.price / n) + ' po osobi)');
			}
			lines.push('Ideš i ti? Javi pa rezerviramo zajedno 👇');
			var text = lines.join('\n');
			share.setAttribute('data-paiz-share-text', text);
			share.setAttribute('href', 'https://wa.me/?text=' + encodeURIComponent(text + '\n' + cfg.url));
		}

		function select(start) {
			var w = weeks.filter(function (x) {
				return x.start === start;
			})[0];
			if (!w || w.state === 'booked') {
				return;
			}
			selected = w;
			render();
			form.week.value = w.start;
			var dl = root.querySelector('[data-pajd-sum]');
			dl.textContent = '';
			sumRow(dl, 'Termin', w.long);
			sumRow(dl, 'Polazak', cfg.marina + ', ukrcaj od ' + cfg.embark + ' h');
			sumRow(dl, 'Brod', cfg.boat + ', najviše ' + cfg.max + ' gostiju i skiper');
			if (w.full) {
				sumRow(dl, 'Uplata', 'cijeli iznos ' + money(w.price) + ' (do ukrcaja je manje od ' + cfg.restDays + ' dana)');
			} else {
				sumRow(dl, 'Akontacija ' + cfg.pct + ' %', money(w.deposit) + ', u roku ' + cfg.depositDays + ' dana od potvrde');
				sumRow(dl, 'Ostatak', money(w.rest) + ' do ' + w.restDue);
			}
			if (w.state === 'request') {
				sumRow(dl, 'Stanje', 'Na upitu – za ovaj tjedan već postoji zahtjev, ali možeš poslati i svoj.');
			}
			root.querySelector('[data-pajd-price]').textContent = money(w.price);
			var reg = root.querySelector('[data-pajd-regular]');
			reg.hidden = !(w.regular > w.price);
			reg.textContent = w.regular > w.price ? money(w.regular) : '';
			updatePerPerson();
			book.hidden = false;
			form.hidden = false;
			done.hidden = true;
			msg.hidden = true;
			if (book.getBoundingClientRect().top > window.innerHeight - 80) {
				book.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		}

		function refresh() {
			if (!window.fetch) {
				return;
			}
			fetch(cfg.ajax + '?action=paj_weeks&t=' + Date.now(), { credentials: 'same-origin', cache: 'no-store' })
				.then(function (r) {
					return r.json();
				})
				.then(function (res) {
					if (!res || !res.success) {
						return;
					}
					weeks = res.data.weeks || [];
					cfg.nonce = res.data.nonce;
					buildMonths();
					if (selected) {
						var still = weeks.filter(function (x) {
							return x.start === selected.start;
						})[0];
						selected = still && still.state !== 'booked' ? still : null;
						if (!selected) {
							book.hidden = true;
						}
					}
					render();
				})
				.catch(function () {});
		}

		monthsBox.addEventListener('click', function (e) {
			var b = e.target.closest('.pajd-week');
			if (b && !b.disabled) {
				select(b.getAttribute('data-start'));
			}
		});
		prev.addEventListener('click', function () {
			index = Math.max(0, index - visibleCount());
			render();
		});
		next.addEventListener('click', function () {
			index = Math.min(Math.max(0, months.length - visibleCount()), index + visibleCount());
			render();
		});
		persons.addEventListener('change', updatePerPerson);

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			msg.hidden = true;
			Array.prototype.forEach.call(form.querySelectorAll('[aria-invalid]'), function (x) {
				x.removeAttribute('aria-invalid');
			});
			if (!selected) {
				msg.textContent = 'Odaberi tjedan u kalendaru.';
				msg.hidden = false;
				return;
			}
			var data = new FormData(form);
			data.append('action', 'paj_request');
			data.append('nonce', cfg.nonce);
			var btn = form.querySelector('[data-pajd-submit]');
			btn.disabled = true;
			btn.classList.add('is-busy');
			fetch(cfg.ajax, { method: 'POST', body: data, credentials: 'same-origin' })
				.then(function (r) {
					return r.json();
				})
				.then(function (res) {
					if (!res || !res.success) {
						var d = (res && res.data) || {};
						msg.textContent = d.message || 'Zahtjev nije poslan. Pokušaj ponovno.';
						msg.hidden = false;
						var field = d.field && form.querySelector('[name="' + d.field + '"]');
						if (field && field.type !== 'hidden') {
							field.setAttribute('aria-invalid', 'true');
							field.focus();
						}
						if (d.field === 'week') {
							refresh();
						}
						return;
					}
					form.hidden = true;
					done.textContent = '';
					done.appendChild(el('p', 'pajd-done__title', res.data.title));
					done.appendChild(el('p', '', res.data.message));
					done.appendChild(el('p', 'pajd-done__week', res.data.week + ' · ' + res.data.price));
					done.hidden = false;
					done.focus();
					refresh();
				})
				.catch(function () {
					msg.textContent = 'Nema veze s poslužiteljem. Pokušaj ponovno.';
					msg.hidden = false;
				})
				.then(function () {
					btn.disabled = false;
					btn.classList.remove('is-busy');
				});
		});

		var lastCount = visibleCount();
		window.addEventListener('resize', function () {
			if (visibleCount() !== lastCount) {
				lastCount = visibleCount();
				render();
			}
		});

		buildMonths();
		render();
		refresh();
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-pajd]'), function (root) {
			if (!root.hasAttribute('data-pajd-ready')) {
				root.setAttribute('data-pajd-ready', '');
				init(root);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}());
