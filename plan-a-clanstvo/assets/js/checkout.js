/* Plan A članstvo u košarici: provjera e-maila (samo stanje, bez osobnih podataka). */
(function ($) {
	'use strict';
	var cfg = window.pacCheckout || {};
	var timers = {};

	function check(email, done) {
		$.post(cfg.ajax, { action: 'pac_member_check', nonce: cfg.nonce, email: email })
			.done(function (r) { done(r && r.success ? r.data : null); })
			.fail(function () { done(null); });
	}

	function valid(email) {
		return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
	}

	function msg(state, d, other) {
		var year = d && d.year ? d.year : cfg.year;
		if (state === 'clan') {
			var t = other ? '✔ Član Plan A.' : '✔ Član si Plan A – nastavi s prijavom, podatke iz tvoje pristupnice upisat ćemo sami.';
			if (d && !d.fee) {
				t += other
					? ' Članarina za ' + year + '. još nije plaćena – uplatnicu šaljemo na e-mail te osobe.'
					: ' Članarina za ' + year + '. još nije plaćena – nakon prijave stiže ti uplatnica s 2D kodom (' + cfg.fee + ').';
			}
			return ['ok', t];
		}
		if (state === 'ceka') {
			return ['wait', other
				? 'Pristupnica ove osobe još nije potvrđena – na njezin e-mail šaljemo novu poveznicu za potvrdu.'
				: 'Tvoja pristupnica je zaprimljena, ali još nije potvrđena. Nastavi s prijavom – ostale podatke upisat ćemo iz pristupnice. U e-mailu o prijavi dobit ćeš gumb „Potvrđujem pristupnicu”: klikni ga i članstvo je potvrđeno.'];
		}
		return ['new', other
			? 'Osoba još nije član Plan A – upiši njezine podatke za pristupnicu.'
			: 'Još nisi član Plan A – ispuni pristupnicu ispod (podaci se upisuju i u prijavu).'];
	}

	function show($status, state, d, other) {
		var m = msg(state, d, other);
		$status.attr('data-state', m[0]).text(m[1]).prop('hidden', false);
	}

	/* Kupac */
	function buyer() {
		var $email = $('#billing_email');
		var email = $.trim($email.val() || '');
		var $status = $('#pac-buyer-status');
		var $join = $('#pac-buyer-join');
		var fields = cfg.fields || [];
		var $rows = $('.woocommerce-billing-fields .form-row').not('#billing_email_field');
		function reset() {
			$rows.removeClass('pac-hidden');
		}
		if (!valid(email)) {
			reset();
			$join.prop('hidden', true);
			$status.prop('hidden', true).text('');
			return;
		}
		$status.attr('data-state', 'busy').text('Provjeravam članstvo…').prop('hidden', false);
		check(email, function (d) {
			if (email !== $.trim($email.val() || '')) { return; }
			if (!d) { $status.prop('hidden', true); reset(); return; }
			reset();
			if (d.state === 'nema') {
				$join.prop('hidden', false);
			} else {
				$join.prop('hidden', true);
				// Skriva se sve što upisujemo iz pristupnice i neobavezna polja (tvrtka, dodatak adrese);
				// obavezna polja koja pristupnica nema (npr. županija) i ona koja članu nedostaju ostaju.
				var missing = d.missing || [];
				$rows.each(function () {
					var f = (this.id || '').replace(/_field$/, '');
					var ours = fields.indexOf(f) >= 0;
					var optional = f === 'billing_address_2' && !$(this).hasClass('validate-required');
					if ((ours && missing.indexOf(f) < 0) || optional) {
						$(this).addClass('pac-hidden');
					}
				});
			}
			show($status, d.state, d, false);
		});
	}

	/* Ostali sudionici */
	function person($p) {
		var $email = $p.find('[data-pac-email]');
		var email = $.trim($email.val() || '');
		var $status = $p.find('[data-pac-status]');
		var $join = $p.find('[data-pac-join]');
		if (!valid(email)) { $join.prop('hidden', true); $status.prop('hidden', true).text(''); return; }
		$status.attr('data-state', 'busy').text('Provjeravam…').prop('hidden', false);
		check(email, function (d) {
			if (email !== $.trim($email.val() || '')) { return; }
			if (!d) { $status.prop('hidden', true); return; }
			$join.prop('hidden', d.state !== 'nema');
			show($status, d.state, d, true);
		});
	}

	function later(key, fn) {
		clearTimeout(timers[key]);
		timers[key] = setTimeout(fn, 700);
	}

	/* Maloljetni kupac: polja roditelja */
	function minor() {
		var v = $.trim($('[name="pac_datum"]').val() || '');
		var m = v.match(/^(\d{1,2})\s*[.\/-]\s*(\d{1,2})\s*[.\/-]\s*(\d{4})/);
		var show = false;
		if (m) {
			var b = new Date(+m[3], +m[2] - 1, +m[1]), n = new Date();
			var age = n.getFullYear() - b.getFullYear() - ((n.getMonth() < b.getMonth() || (n.getMonth() === b.getMonth() && n.getDate() < b.getDate())) ? 1 : 0);
			show = age >= 0 && age < 18;
		}
		$('#pac-buyer-minor').prop('hidden', !show);
	}

	/* R1 račun (drugi dodatak): kad je označen, njegova polja su obavezna. */
	var r1 = { $box: $(), rows: [] };
	function r1Find() {
		r1.$box = $('.woocommerce-billing-fields .form-row').filter(function () {
			return /\bR1\b/.test($(this).text()) && $(this).find('input[type=checkbox]').length;
		}).first().find('input[type=checkbox]').first();
	}
	function r1Mark(rows, on) {
		rows.forEach(function (row) {
			var $l = $(row).find('label').first();
			$l.find('.optional').toggle(!on);
			$l.find('.pac-req').remove();
			if (on) { $l.append('<abbr class="required pac-req" title="obavezno">*</abbr>'); }
		});
	}
	function r1Guess() {
		return $('.woocommerce-billing-fields .form-row').filter(function () {
			var t = $(this).find('label').first().text();
			return this.offsetParent && $(this).find('.optional').length && (/r1/i.test(this.id) || /tvrtk|OIB|adres/i.test(t)) && this !== r1.$box.closest('.form-row')[0];
		}).toArray();
	}
	function r1Watch() {
		r1Find();
		if (!r1.$box.length) { return; }
		if (r1.$box.is(':checked')) { r1.rows = r1Guess(); r1Mark(r1.rows, true); }
		r1.$box.off('change.pac').on('change.pac', function () {
			var $all = $('.woocommerce-billing-fields .form-row');
			var before = $all.filter(function () { return !!this.offsetParent; }).toArray();
			var on = $(this).is(':checked');
			setTimeout(function () {
				if (on) {
					var now = $all.filter(function () { return !!this.offsetParent; }).toArray();
					r1.rows = now.filter(function (r) { return before.indexOf(r) < 0; });
					if (!r1.rows.length) { r1.rows = r1Guess(); }
				}
				r1Mark(r1.rows, on);
				if (!on) { r1.rows = []; }
			}, 60);
		});
	}
	function r1Check() {
		if (!r1.$box.length || !r1.$box.is(':checked')) { return true; }
		var miss = [];
		r1.rows.forEach(function (row) {
			var $i = $(row).find('input, select, textarea').first();
			var v = $.trim($i.val() || '');
			var label = $.trim($(row).find('label').first().clone().children().remove().end().text());
			if (!v) { miss.push(label); }
			else if (/OIB/i.test(label) && !/^\d{11}$/.test(v)) { miss.push(label + ' (11 znamenki)'); }
			$(row).toggleClass('woocommerce-invalid', !v);
		});
		if (!miss.length) { return true; }
		$('.pac-r1-err').remove();
		var $err = $('<ul class="woocommerce-error pac-r1-err" role="alert"><li>Za R1 račun upiši: ' + $('<span>').text(miss.join(', ')).html() + '.</li></ul>');
		$('form.checkout').prepend($err);
		$('html, body').animate({ scrollTop: $err.offset().top - 120 }, 300);
		return false;
	}

	/* OIB: provjera kontrolne znamenke odmah ispod polja. */
	function validOib(v) {
		if (!/^\d{11}$/.test(v)) { return false; }
		var a = 10;
		for (var i = 0; i < 10; i++) { a = (a + +v[i]) % 10; a = (a === 0 ? 10 : a) * 2 % 11; }
		var c = 11 - a;
		return (c === 10 ? 0 : c) === +v[10];
	}
	function oibHint(el) {
		var v = (el.value || '').replace(/\D/g, '');
		var $row = $(el).closest('.form-row');
		$row.find('.pac-oib-hint').remove();
		if (!v) { return; }
		var ok = validOib(v);
		var msg = ok ? '✔ OIB je ispravan' : (v.length !== 11 ? 'OIB ima 11 znamenki (upisano ' + v.length + ').' : 'OIB nije ispravan – provjeri znamenke (kontrolna znamenka ne odgovara).');
		$row.append($('<span class="pac-oib-hint">').attr('data-ok', ok ? '1' : '0').text(msg));
	}

	$(function () {
		var $form = $('form.checkout');
		// E-mail na vrh (i kad drugi dodatak promijeni redoslijed polja).
		function emailFirst() {
			var $e = $('#billing_email_field');
			var $wrap = $e.parent();
			$e.attr('data-priority', 1);
			if ($wrap.children('.form-row').first()[0] !== $e[0]) {
				$e.prependTo($wrap);
			}
		}
		emailFirst();
		$(document.body).on('updated_checkout country_to_state_changed', function () { setTimeout(emailFirst, 0); });
		// Poruka o članstvu odmah ispod polja za e-mail.
		$('#pac-buyer-status').appendTo('#billing_email_field');
		$form.on('input change', '#billing_email', function () { later('buyer', buyer); });
		$form.on('input change', '[data-pac-email]', function () {
			var $p = $(this).closest('[data-pac-person]');
			later('p' + $p.index(), function () { person($p); });
		});
		$form.on('input change', '[name="pac_datum"]', minor);
		$form.on('input blur', 'input[name="pac_oib"], input[name$="[oib]"]', function () { oibHint(this); });
		// Woo nakon osvježavanja ponovno iscrta dio stranice; stanje se vraća.
		$(document.body).on('updated_checkout', function () { buyer(); });
		r1Watch();
		$form.on('checkout_place_order', function () { $('.pac-r1-err').remove(); return r1Check(); });
		if ($('#billing_email').val()) { buyer(); }
		$('[data-pac-person]').each(function () { if ($(this).find('[data-pac-email]').val()) { person($(this)); } });
	});
})(jQuery);
