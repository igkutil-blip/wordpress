/*
 * Plan A kalkulator – procjena vremena, vode i energije za planinarsku turu.
 * Sve se računa ovdje, u pregledniku; ništa se ne sprema i ne šalje.
 */
(function () {
	'use strict';

	/* Vrijeme hoda: DIN 33466 (4 km/h po ravnom, 300 m/h uspona, 500 m/h spusta). */
	var FLAT_KMH = 4;
	var UP_MH = 300;
	var DOWN_MH = 500;

	/* Brzina u odnosu na prosječnog planinara. */
	var FITNESS = { slaba: 0.8, prosjek: 1, dobra: 1.15, odlicna: 1.3 };
	/* Grupa hoda sporije (čekanje, uska mjesta, tempo najsporijeg). */
	var GROUP = { sam: 1, mala: 1.07, velika: 1.15 };
	/* t = vrijeme, e = potrošnja energije u odnosu na glatku podlogu. */
	var TERRAIN = {
		cesta: { t: 0.92, e: 1 },
		staza: { t: 1, e: 1.1 },
		kamenjar: { t: 1.15, e: 1.25 },
		zahtjevno: { t: 1.35, e: 1.4 },
		snijeg: { t: 1.6, e: 1.7 }
	};
	var WIND = { slab: 1, umjeren: 1.04, jak: 1.12 };
	var SUN = { hlad: 0.9, djel: 1, jako: 1.15 };
	/* Odmori: minuta na sat hoda, dulji odmor i od koliko sati hoda se uzima. */
	var BREAKS = {
		kratki: { perHour: 5, main: 0, after: 0 },
		uobicajeni: { perHour: 10, main: 30, after: 4 },
		opusteni: { perHour: 15, main: 45, after: 3 }
	};

	/* Za izračun zalaska sunca: sredina Hrvatske. */
	var LAT = 44.6;
	var LON = 16.0;

	var LIMITS = {
		km: [0.1, 200],
		uspon: [0, 9000],
		spust: [0, 9000],
		tezina: [25, 250],
		ruksak: [0, 40],
		temp: [-30, 45]
	};
	var LABELS = {
		km: 'duljinu rute',
		uspon: 'uspon',
		spust: 'spust',
		tezina: 'težinu',
		ruksak: 'težinu ruksaka',
		temp: 'temperaturu'
	};

	function clamp(v, min, max) {
		return Math.min(max, Math.max(min, v));
	}

	function parseNum(str) {
		str = String(str || '').trim().replace(/\s/g, '');
		if (/^\d{1,3}(\.\d{3})+$/.test(str)) {
			str = str.replace(/\./g, ''); /* "1.200" = tisuću dvjesto */
		}
		str = str.replace(',', '.');
		if (str === '' || !/^-?\d*\.?\d+$/.test(str)) {
			return NaN;
		}
		return parseFloat(str);
	}

	function fmtNum(v, digits) {
		return v.toLocaleString('hr-HR', { minimumFractionDigits: digits || 0, maximumFractionDigits: digits || 0 });
	}

	/* "5 h 45 min", zaokruženo na 5 minuta. */
	function fmtDuration(hours) {
		var min = Math.max(0, Math.round(hours * 60 / 5) * 5);
		var h = Math.floor(min / 60);
		var m = min % 60;
		if (!h) {
			return m + ' min';
		}
		return h + ' h' + (m ? ' ' + m + ' min' : '');
	}

	function fmtClock(minutes) {
		var day = Math.floor(minutes / 1440);
		minutes = ((Math.round(minutes) % 1440) + 1440) % 1440;
		var h = Math.floor(minutes / 60);
		var m = minutes % 60;
		return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + (day > 0 ? ' (sljedeći dan)' : '');
	}

	function fmtLitres(l) {
		return fmtNum(l, l % 1 ? 2 : 0).replace(/,(\d)0$/, ',$1') + ' L';
	}

	/* Minetti i sur. (2002): energija hoda u J po kg i metru za nagib i (−0,45 … 0,45). */
	function minetti(i) {
		i = clamp(i, -0.45, 0.45);
		return 280.5 * Math.pow(i, 5) - 58.7 * Math.pow(i, 4) - 76.8 * Math.pow(i, 3) + 51.9 * i * i + 19.6 * i + 2.5;
	}

	/* Zalazak sunca (NOAA, pojednostavljeno) u minutama od ponoći po zagrebačkom vremenu. */
	function sunsetMinutes(dateStr) {
		var p = /^(\d{4})-(\d{2})-(\d{2})$/.exec(dateStr || '');
		if (!p) {
			return null;
		}
		var y = +p[1];
		var mo = +p[2];
		var d = +p[3];
		var start = Date.UTC(y, 0, 1);
		var n = Math.floor((Date.UTC(y, mo - 1, d) - start) / 864e5) + 1;
		var g = 2 * Math.PI / 365 * (n - 1);
		var eq = 229.18 * (0.000075 + 0.001868 * Math.cos(g) - 0.032077 * Math.sin(g) - 0.014615 * Math.cos(2 * g) - 0.040849 * Math.sin(2 * g));
		var decl = 0.006918 - 0.399912 * Math.cos(g) + 0.070257 * Math.sin(g) - 0.006758 * Math.cos(2 * g) + 0.000907 * Math.sin(2 * g) - 0.002697 * Math.cos(3 * g) + 0.00148 * Math.sin(3 * g);
		var lat = LAT * Math.PI / 180;
		var cosHa = Math.cos(90.833 * Math.PI / 180) / (Math.cos(lat) * Math.cos(decl)) - Math.tan(lat) * Math.tan(decl);
		if (cosHa < -1 || cosHa > 1) {
			return null;
		}
		var ha = Math.acos(cosHa) * 180 / Math.PI;
		var utc = 720 - 4 * (LON - ha) - eq;
		var when = new Date(Date.UTC(y, mo - 1, d, 0, 0) + utc * 60000);
		try {
			var parts = new Intl.DateTimeFormat('en-GB', { timeZone: 'Europe/Zagreb', hour: '2-digit', minute: '2-digit', hour12: false }).formatToParts(when);
			var hh = 0;
			var mm = 0;
			parts.forEach(function (x) {
				if (x.type === 'hour') {
					hh = +x.value % 24;
				} else if (x.type === 'minute') {
					mm = +x.value;
				}
			});
			return hh * 60 + mm;
		} catch (e) {
			return null;
		}
	}

	/*
	 * Glavni izračun. Ulaz: brojevi i odabiri iz obrasca. Izlaz: sati, litre, kcal i napomene.
	 */
	function calculate(v) {
		var res = { notes: [] };
		var body = v.tezina;
		var load = v.ruksak;
		var mass = body + load;
		var terrain = TERRAIN[v.teren] || TERRAIN.staza;

		/* 1. Vrijeme hoda */
		var hFlat = v.km / FLAT_KMH;
		var hVert = v.uspon / UP_MH + v.spust / DOWN_MH;
		var base = Math.max(hFlat, hVert) + Math.min(hFlat, hVert) / 2;

		var pack = clamp(1 + 0.6 * Math.max(0, load / body - 0.1), 1, 1.3);
		var heat = 1;
		if (v.temp > 25) {
			heat = 1 + 0.015 * (v.temp - 25);
		} else if (v.temp < 0) {
			heat = 1 + 0.01 * -v.temp;
		}
		var env = clamp(heat * (WIND[v.vjetar] || 1), 1, 1.3);

		var walk = base / (FITNESS[v.kondicija] || 1) * (GROUP[v.grupa] || 1) * terrain.t * pack * env;

		/* 2. Odmori */
		var b = BREAKS[v.odmori] || BREAKS.uobicajeni;
		var breaksMin = b.perHour * walk + (b.main && walk >= b.after ? b.main : 0);
		var breaks = Math.round(breaksMin / 5) * 5 / 60;
		var total = walk + breaks;

		res.walk = walk;
		res.breaks = breaks;
		res.total = total;
		res.low = walk * 0.9 + breaks;
		res.high = walk * 1.15 + breaks;

		/* 3. Energija: uspon i spust raspodijeljeni po duljini rute */
		var dist = v.km * 1000;
		var vert = v.uspon + v.spust;
		var dUp = vert ? dist * v.uspon / vert : dist;
		var dDown = dist - dUp;
		var iUp = dUp > 0 && vert ? v.uspon / dUp : 0;
		var iDown = dDown > 0 ? v.spust / dDown : 0;
		var steep = Math.max(iUp, iDown);
		var walkJ = mass * (minetti(iUp) * dUp + minetti(-iDown) * dDown) * terrain.e;
		var restKcal = 1.0 * body * total; /* 1 kcal po kg na sat (osnovna potrošnja) */
		var kcal = walkJ / 4184 + restKcal;
		res.kcal = Math.round(kcal / 50) * 50;
		/* Usput nadoknaditi otprilike 40–60 % potrošnje; ostatak nakon ture. */
		res.foodLow = Math.round(kcal * 0.4 / 50) * 50;
		res.foodHigh = Math.round(kcal * 0.6 / 50) * 50;

		/* 4. Voda: znojenje iz snage, temperature i sunca */
		var watts = walk > 0 ? walkJ / (walk * 3600) + body * 1.163 : 0;
		var tempF = clamp(1 + 0.04 * (v.temp - 20), 0.6, 1.8);
		var sweat = clamp((0.2 + 0.0013 * watts) * tempF * (SUN[v.sunce] || 1), 0.2, 1.5);
		var drink = Math.max(0.5, Math.ceil(sweat * walk * 0.8 * 4) / 4);
		var perHour = Math.min(1, sweat * 0.8);
		res.water = drink;
		res.waterHour = perHour;
		res.sip = Math.max(1, Math.round(perHour * 10 / 3 * 2) / 2); /* dl svakih 20 min, na pola dl */

		/* 5. Povratak i zalazak sunca */
		var start = /^(\d{1,2}):(\d{2})/.exec(v.polazak || '');
		res.back = null;
		if (start) {
			var startMin = +start[1] * 60 + +start[2];
			var backMin = startMin + Math.round(total * 60 / 5) * 5;
			res.back = backMin;
			var sunset = sunsetMinutes(v.datum);
			if (sunset !== null) {
				var spare = sunset - backMin;
				if (spare < 0) {
					res.notes.push({ warn: true, text: 'Povratak je nakon zalaska sunca (oko ' + fmtClock(sunset) + '). Kreni ranije ili skrati turu, a čeona svjetiljka neka bude u ruksaku.' });
				} else if (spare < 60) {
					res.notes.push({ warn: true, text: 'Povratak je blizu zalaska sunca (oko ' + fmtClock(sunset) + '). Ponesi čeonu svjetiljku i ne gubi vrijeme na odmorima.' });
				} else {
					res.notes.push({ text: 'Sunce zalazi oko ' + fmtClock(sunset) + ', pa imaš oko ' + fmtDuration(spare / 60) + ' rezerve do mraka.' });
				}
			}
		}

		if (v.temp >= 28) {
			res.notes.push({ warn: true, text: 'Vruće je: kreni rano, izbjegavaj podnevno sunce na otvorenom i uz vodu pij i elektrolite.' });
		} else if (v.temp <= 3) {
			res.notes.push({ text: 'Hladno je: slojevita odjeća, kapa i rukavice, topli napitak u termosici. Pij i kad nisi žedan.' });
		}
		if (v.vjetar === 'jak') {
			res.notes.push({ warn: true, text: 'Jak vjetar na grebenima i vrhovima može biti opasan: provjeri prognozu i izbjegavaj izložene dijelove.' });
		}
		if (drink > 3) {
			res.notes.push({ text: 'To je puno vode za nošenje: provjeri ima li usput izvora ili planinarskog doma.' });
		}
		if (walk >= 8) {
			res.notes.push({ text: 'Dug dan u planini: dogovori mjesta za odmor i mogući raniji silazak s rute.' });
		}
		if (steep > 0.3) {
			res.notes.push({ warn: true, text: 'Prosječan nagib je vrlo velik (' + fmtNum(steep * 100) + ' %). Provjeri duljinu i visinsku razliku.' });
		}
		return res;
	}

	function init(root, useUrl) {
		var form = root.querySelector('[data-pakl-form]');
		var msg = root.querySelector('[data-pakl-msg]');
		var notes = root.querySelector('[data-pakl-notes]');
		var result = root.querySelector('[data-pakl-result]');
		var bar = root.querySelector('[data-pakl-bar]');
		var out = {};
		Array.prototype.forEach.call(root.querySelectorAll('[data-pakl-out]'), function (el) {
			out[el.getAttribute('data-pakl-out')] = el;
		});

		function field(name) {
			return form.querySelector('[data-pakl-field="' + name + '"]');
		}

		function choice(name) {
			var el = form.querySelector('[data-pakl-field="' + name + '"]:checked');
			return el ? el.value : '';
		}

		/* Poveznica može unaprijed upisati rutu: ?km=12&uspon=800&spust=800 */
		if (useUrl && window.URLSearchParams) {
			var q = new URLSearchParams(window.location.search);
			['km', 'uspon', 'spust'].forEach(function (name) {
				var val = parseNum(q.get(name));
				if (!isNaN(val) && val >= LIMITS[name][0] && val <= LIMITS[name][1]) {
					field(name).value = fmtNum(val, val % 1 ? 1 : 0).replace(/\./g, '');
				}
			});
		}

		function set(name, text) {
			if (out[name]) {
				out[name].textContent = text;
			}
		}

		function update() {
			var values = {};
			var errors = [];
			['km', 'uspon', 'spust', 'tezina', 'ruksak', 'temp'].forEach(function (name) {
				var input = field(name);
				var raw = input.value;
				var val = parseNum(raw);
				var bad = false;
				if (name === 'spust' && String(raw).trim() === '') {
					val = values.uspon;
				} else if (isNaN(val) || val < LIMITS[name][0] || val > LIMITS[name][1]) {
					bad = true;
				}
				if (bad) {
					errors.push(LABELS[name]);
					input.setAttribute('aria-invalid', 'true');
				} else {
					input.removeAttribute('aria-invalid');
				}
				values[name] = val;
			});
			['teren', 'kondicija', 'grupa', 'sunce', 'vjetar', 'odmori'].forEach(function (name) {
				values[name] = choice(name);
			});
			values.polazak = field('polazak').value;
			values.datum = field('datum').value;

			if (errors.length) {
				msg.textContent = 'Provjeri ' + errors.join(', ') + '.';
				msg.hidden = false;
				root.classList.add('is-invalid');
				return;
			}
			msg.hidden = true;
			root.classList.remove('is-invalid');

			var r = calculate(values);
			set('total', fmtDuration(r.total));
			set('range', 'realno ' + fmtDuration(r.low) + ' – ' + fmtDuration(r.high));
			set('walk', fmtDuration(r.walk));
			set('breaks', fmtDuration(r.breaks));
			set('back', r.back === null ? '–' : fmtClock(r.back));
			set('water', fmtLitres(r.water));
			set('water-hint', 'oko ' + fmtNum(r.waterHour, 1) + ' L na sat hoda – ' + fmtNum(r.sip, r.sip % 1 ? 1 : 0) + ' dl svakih 20 minuta');
			set('kcal', '≈ ' + fmtNum(r.kcal) + ' kcal');
			set('food-hint', r.walk >= 2
				? 'usput pojedi oko ' + fmtNum(r.foodLow) + '–' + fmtNum(r.foodHigh) + ' kcal, ostalo nakon ture'
				: 'za kraću turu dovoljna je užina');
			set('bar', fmtDuration(r.total) + ' · ' + fmtLitres(r.water) + ' · ' + fmtNum(r.kcal) + ' kcal');

			notes.textContent = '';
			r.notes.forEach(function (n) {
				var li = document.createElement('li');
				li.className = 'pakl-note' + (n.warn ? ' pakl-note--warn' : '');
				li.textContent = n.text;
				notes.appendChild(li);
			});
			notes.hidden = !r.notes.length;
		}

		form.addEventListener('input', update);
		form.addEventListener('change', update);
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			update();
		});
		update();

		/* Mobitel: traka s rezultatom dok rezultat nije na zaslonu. */
		if (bar && window.IntersectionObserver) {
			/* Samo kad je rezultat ispod obrasca (jedan stupac). */
			var narrow = {
				get matches() {
					return result.getBoundingClientRect().top > form.querySelector('.pakl-form').getBoundingClientRect().top + 40;
				}
			};
			var formSeen = false;
			var resultSeen = false;
			var refresh = function () {
				bar.hidden = !(narrow.matches && formSeen && !resultSeen && !root.classList.contains('is-invalid'));
			};
			new IntersectionObserver(function (entries) {
				entries.forEach(function (en) {
					if (en.target === result) {
						resultSeen = en.isIntersecting;
					} else {
						formSeen = en.isIntersecting;
					}
				});
				refresh();
			}, { threshold: 0 }).observe(result);
			var io = new IntersectionObserver(function (entries) {
				formSeen = entries[0].isIntersecting;
				refresh();
			});
			io.observe(form.querySelector('.pakl-form'));
			form.addEventListener('input', refresh);
			window.addEventListener('resize', refresh);
		}
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-pakl]'), function (root, index) {
			if (!root.hasAttribute('data-pakl-ready')) {
				root.setAttribute('data-pakl-ready', '');
				init(root, index === 0);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}());
