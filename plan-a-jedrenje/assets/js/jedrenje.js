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
		var book = root.querySelector('[data-pajd-book]');
		var form = root.querySelector('[data-pajd-form]');
		var msg = root.querySelector('[data-pajd-msg]');
		var done = root.querySelector('[data-pajd-done]');
		var persons = root.querySelector('[data-pajd-persons]');
		var share = root.querySelector('[data-pajd-share]');
		var weeks = cfg.weeks || [];
		var months = [];
		var selected = null;

		/* "15.–22. 5." ili "28. 8.–4. 9." */
		function span(w) {
			var a = w.start.split('-');
			var b = w.end.split('-');
			var d1 = parseInt(a[2], 10);
			var m1 = parseInt(a[1], 10);
			var d2 = parseInt(b[2], 10);
			var m2 = parseInt(b[1], 10);
			return m1 === m2 ? d1 + '.–' + d2 + '. ' + m1 + '.' : d1 + '. ' + m1 + '.–' + d2 + '. ' + m2 + '.';
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

		/* Cijela sezona odjednom: svaki mjesec sa svim svojim tjednima. */
		function render() {
			monthsBox.textContent = '';
			if (!months.length) {
				monthsBox.appendChild(el('p', 'pajd-loading', 'Trenutno nema slobodnih tjedana u sezoni. Javi nam se za dogovor.'));
				return;
			}
			var years = {};
			months.forEach(function (k) {
				years[k.slice(0, 4)] = true;
			});
			var manyYears = Object.keys(years).length > 1;
			months.forEach(function (key) {
				var box = el('div', 'pajd-month');
				box.appendChild(el('h4', 'pajd-month__title', monthTitle(key, manyYears)));
				var list = el('div', 'pajd-weeks');
				weeks.filter(function (w) {
					return w.month === key;
				}).forEach(function (w) {
					var b = el('button', 'pajd-week is-' + w.state);
					var isSel = selected && selected.start === w.start;
					b.type = 'button';
					b.setAttribute('data-start', w.start);
					b.setAttribute('aria-pressed', isSel ? 'true' : 'false');
					if (isSel) {
						b.classList.add('is-selected');
					}
					b.appendChild(el('span', 'pajd-week__from', span(w)));
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


		buildMonths();
		render();
		refresh();
	}


	/*
	 * Slajder fotografija: listanje prstom (scroll-snap), strelice, točke, samostalno izmjenjivanje
	 * (zaustavlja se kad je posjetitelj na slajderu, kad nije vidljiv ili uz smanjeno kretanje)
	 * i prikaz preko cijelog zaslona.
	 */
	function initSlider(box) {
		var track = box.querySelector('[data-pajd-track]');
		var slides = Array.prototype.slice.call(box.querySelectorAll('.pajd-slide'));
		var dots = Array.prototype.slice.call(box.querySelectorAll('[data-pajd-dot]'));
		var counter = box.querySelector('[data-pajd-count]');
		var dialog = box.querySelector('[data-pajd-lightbox]');
		var lbImg = box.querySelector('[data-pajd-lb-img]');
		var lbCount = box.querySelector('[data-pajd-lb-count]');
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var index = 0;
		var lbIndex = 0;
		var timer = null;
		var paused = false;
		var visible = true;
		if (!track || !slides.length) {
			return;
		}

		function setActive(i) {
			if (i === index && slides[i].classList.contains('is-active')) {
				return;
			}
			index = i;
			slides.forEach(function (s, k) {
				s.classList.toggle('is-active', k === i);
			});
			dots.forEach(function (d, k) {
				d.classList.toggle('is-active', k === i);
				if (k === i) {
					d.setAttribute('aria-current', 'true');
				} else {
					d.removeAttribute('aria-current');
				}
			});
			if (counter) {
				counter.textContent = i + 1;
			}
		}

		function go(i, smooth) {
			var n = slides.length;
			i = ((i % n) + n) % n;
			track.scrollTo({ left: i * track.clientWidth, behavior: smooth === false || reduce ? 'auto' : 'smooth' });
			setActive(i);
		}

		var ticking = false;
		track.addEventListener('scroll', function () {
			if (ticking) {
				return;
			}
			ticking = true;
			window.requestAnimationFrame(function () {
				ticking = false;
				var i = Math.round(track.scrollLeft / Math.max(1, track.clientWidth));
				setActive(Math.max(0, Math.min(slides.length - 1, i)));
			});
		}, { passive: true });

		function stop() {
			if (timer) {
				window.clearInterval(timer);
				timer = null;
			}
		}

		function start() {
			stop();
			if (reduce || slides.length < 2 || paused || !visible || document.hidden || (dialog && dialog.open)) {
				return;
			}
			timer = window.setInterval(function () {
				go(index + 1);
			}, 5500);
		}

		var prev = box.querySelector('[data-pajd-slide-prev]');
		var next = box.querySelector('[data-pajd-slide-next]');
		if (prev) {
			prev.addEventListener('click', function () {
				go(index - 1);
				start();
			});
		}
		if (next) {
			next.addEventListener('click', function () {
				go(index + 1);
				start();
			});
		}
		dots.forEach(function (d) {
			d.addEventListener('click', function () {
				go(parseInt(d.getAttribute('data-pajd-dot'), 10));
				start();
			});
		});
		track.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
				e.preventDefault();
				go(index + (e.key === 'ArrowRight' ? 1 : -1));
			}
		});

		['mouseenter', 'focusin', 'touchstart'].forEach(function (ev) {
			box.addEventListener(ev, function () {
				paused = true;
				stop();
			}, { passive: true });
		});
		['mouseleave', 'focusout'].forEach(function (ev) {
			box.addEventListener(ev, function () {
				paused = false;
				start();
			});
		});
		box.addEventListener('touchend', function () {
			window.setTimeout(function () {
				paused = false;
				start();
			}, 6000);
		}, { passive: true });
		document.addEventListener('visibilitychange', start);
		if (window.IntersectionObserver) {
			new IntersectionObserver(function (entries) {
				visible = entries[0].isIntersecting;
				start();
			}, { threshold: 0.3 }).observe(box);
		}
		window.addEventListener('resize', function () {
			go(index, false);
		});

		/* Preko cijelog zaslona */
		function lbShow(i) {
			var n = slides.length;
			lbIndex = ((i % n) + n) % n;
			var s = slides[lbIndex];
			var img = s.querySelector('img');
			lbImg.src = s.getAttribute('data-full') || (img && img.currentSrc) || '';
			lbImg.alt = img ? img.alt : '';
			lbCount.textContent = (lbIndex + 1) + ' / ' + n;
		}
		function lbOpen(i) {
			if (!dialog || typeof dialog.showModal !== 'function') {
				return;
			}
			stop();
			lbShow(i);
			dialog.showModal();
		}
		if (dialog) {
			box.querySelector('[data-pajd-full]').addEventListener('click', function () {
				lbOpen(index);
			});
			track.addEventListener('click', function (e) {
				if (e.target.closest('.pajd-slide')) {
					lbOpen(index);
				}
			});
			dialog.querySelector('[data-pajd-lb-close]').addEventListener('click', function () {
				dialog.close();
			});
			var lp = dialog.querySelector('[data-pajd-lb-prev]');
			var ln = dialog.querySelector('[data-pajd-lb-next]');
			if (lp) {
				lp.addEventListener('click', function () {
					lbShow(lbIndex - 1);
				});
			}
			if (ln) {
				ln.addEventListener('click', function () {
					lbShow(lbIndex + 1);
				});
			}
			dialog.addEventListener('keydown', function (e) {
				if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
					lbShow(lbIndex + (e.key === 'ArrowRight' ? 1 : -1));
				}
			});
			dialog.addEventListener('click', function (e) {
				if (e.target === dialog) {
					dialog.close();
				}
			});
			var x0 = null;
			dialog.addEventListener('touchstart', function (e) {
				x0 = e.touches[0].clientX;
			}, { passive: true });
			dialog.addEventListener('touchend', function (e) {
				if (x0 === null) {
					return;
				}
				var dx = e.changedTouches[0].clientX - x0;
				x0 = null;
				if (Math.abs(dx) > 40) {
					lbShow(lbIndex + (dx < 0 ? 1 : -1));
				}
			}, { passive: true });
			dialog.addEventListener('close', function () {
				go(lbIndex, false);
				start();
			});
		}

		start();
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-pajd-slider]'), function (box) {
			if (!box.hasAttribute('data-pajd-ready')) {
				box.setAttribute('data-pajd-ready', '');
				initSlider(box);
			}
		});
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
