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
				: 'Tvoja pristupnica još nije potvrđena – gumb za potvrdu stiže ti u e-mailu o prijavi.'];
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
					var optional = (f === 'billing_company' || f === 'billing_address_2') && !$(this).hasClass('validate-required');
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
		timers[key] = setTimeout(fn, 450);
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
		// Woo nakon osvježavanja ponovno iscrta dio stranice; stanje se vraća.
		$(document.body).on('updated_checkout', function () { buyer(); });
		if ($('#billing_email').val()) { buyer(); }
		$('[data-pac-person]').each(function () { if ($(this).find('[data-pac-email]').val()) { person($(this)); } });
	});
})(jQuery);
