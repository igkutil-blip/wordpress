/* Plan A izleti – obrazac za uređivanje izleta */
jQuery(function ($) {
	var box = $('.pap');
	if (!box.length) { return; }
	var seq = Date.now();
	// WpTravelly na ovaj ekran ubacuje drugu formu, koja zatvori WordPressovu prije vremena:
	// polja bez forme (naslov, opis, objava, kategorije, ovaj obrazac) vežu se na formu #post.
	function bind(el) {
		$(el).find('input, select, textarea, button').addBack('input, select, textarea, button').each(function () {
			if (!this.form && !this.closest('form')) { this.setAttribute('form', 'post'); }
		});
		return el;
	}
	var stuff = document.getElementById('poststuff') || box[0];
	bind(stuff);
	new MutationObserver(function (list) {
		list.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) { bind(n); } }); });
	}).observe(stuff, { childList: true, subtree: true });
	/* ---------- Izgled zaslona: gornja traka, lijevi izbornik, kartice ---------- */
	var body = $('#post-body');
	var nav = box.find('.pap-nav');
	if (body.length && nav.length) {
		$('body').addClass('pap-layout');
		nav.prependTo(body);
		// Glavni stupac u jednom omotaču, da visina desnog stupca ne razvlači redove mreže.
		$('#post-body-content, #postbox-container-2').wrapAll('<div class="pap-main"></div>');
		// Gornja traka: naziv, stanje, Pregledaj, Spremi.
		var title = $('#title'), status = $.trim($('#post-status-display').text()) || 'Skica';
		var isPub = $('#original_post_status').val() === 'publish';
		var bar = $('<div class="pap-bar"><a class="pap-back" href="edit.php?post_type=ttbm_tour">← Svi izleti</a>' +
			'<strong class="pap-bar-title"></strong><span class="pap-badge"></span><span class="pap-bar-btns">' +
			'<button type="button" class="button pap-bar-preview">Pregledaj</button>' +
			'<button type="button" class="button button-primary pap-bar-save"></button></span></div>');
		bar.find('.pap-bar-title').text(title.val() || 'Novi izlet');
		bar.find('.pap-badge').text(status).toggleClass('is-pub', isPub);
		bar.find('.pap-bar-save').text($('#publish').val() || 'Spremi');
		title.on('input', function () { bar.find('.pap-bar-title').text(this.value || 'Novi izlet'); });
		bar.on('click', '.pap-bar-save', function () { $('#publish').trigger('click'); });
		bar.on('click', '.pap-bar-preview', function () { $('#post-preview').trigger('click'); });
		$('#poststuff').before(bar);

		// Ostali okviri u glavnom stupcu (npr. Rank Math SEO) idu na karticu „Ostalo”.
		var others = $('#postbox-container-2 .postbox').not('#pap_editor');
		others.attr('data-tab', 'ostalo');
		if (!others.length) { nav.find('[data-tab="ostalo"]').remove(); }
		var wpOpis = $('#titlediv, #postdivrich, #postexcerpt').attr('data-tab', 'opis');

		var tabs = nav.find('a').map(function () { return $(this).data('tab'); }).get();
		function show(tab, scroll) {
			if (tabs.indexOf(tab) < 0) { tab = 'opis'; }
			nav.find('a').removeClass('is-active').filter('[data-tab="' + tab + '"]').addClass('is-active');
			box.find('.pap-sec').each(function () { this.hidden = $(this).data('tab') !== tab; });
			others.toggle(tab === 'ostalo');
			$('#pap_editor').toggle(tab !== 'ostalo');
			wpOpis.toggle(tab === 'opis');
			try { sessionStorage.setItem('pap-tab', tab); } catch (e) {}
			if (history.replaceState) { history.replaceState(null, '', '#' + tab); }
			$(window).trigger('resize'); // uređivači teksta u dotad skrivenoj kartici
			if (scroll) { window.scrollTo({ top: 0, behavior: 'smooth' }); }
		}
		nav.on('click', 'a', function (e) { e.preventDefault(); show($(this).data('tab'), true); });
		$(window).on('hashchange', function () { show((location.hash || '').replace('#', ''), false); });
		var start = (location.hash || '').replace('#', '');
		if (tabs.indexOf(start) < 0) { try { start = sessionStorage.getItem('pap-tab') || 'opis'; } catch (e) { start = 'opis'; } }
		show(start, false);

		// Obavezno polje u skrivenoj kartici: preglednik bi šutke odbio spremanje – otvori tu karticu.
		document.getElementById('post').addEventListener('invalid', function (e) {
			var t = $(e.target).closest('[data-tab]').data('tab');
			if (t) { show(t, false); setTimeout(function () { e.target.focus(); }, 50); }
		}, true);
	}

	function key() { seq += 1; return 'n' + seq; }
	function tpl(name) { return $('#tmpl-' + name).html().replace(/__K__/g, key()); }

	function rich(el) {
		if (!window.wp || !wp.editor || !el.id) { return; }
		wp.editor.initialize(el.id, {
			tinymce: { wpautop: true, toolbar1: 'bold,italic,bullist,numlist,link,unlink,undo,redo', height: 160 },
			quicktags: { buttons: 'strong,em,link,ul,ol,li' },
			mediaButtons: false
		});
	}
	function unrich(el) { if (window.wp && wp.editor && el.id) { wp.editor.remove(el.id); } }
	box.find('textarea.pap-rich').each(function () { rich(this); });

	// Vrsta termina.
	box.on('change', 'input[name="pap[travel_type]"]', function () {
		box.find('.pap-type').attr('hidden', true);
		box.find('.pap-type-' + this.value).attr('hidden', false);
	});

	// Novi red tablice.
	box.on('click', '.pap-add', function () {
		$('[data-rows="' + $(this).data('to') + '"]').append(tpl($(this).data('tpl')));
	});
	box.on('click', '.pap-add-rate', function () {
		var row = $(tpl('pap-tpl-ticket'));
		row.find('input[name$="[name]"]').val('Uplata prve rate');
		box.find('[data-rows="tickets"]').append(row);
		row.find('input[name$="[price]"]').trigger('focus');
	});
	box.on('click', '.pap-add-block', function () {
		var to = $(this).data('to'), el = $(tpl('pap-tpl-' + to));
		$('[data-rows="' + to + '"]').append(el);
		rich(el.find('textarea.pap-rich')[0]);
		el.find('input[type=text]').first().trigger('focus');
	});
	box.on('click', '.pap-add-why', function () {
		var last = box.find('[data-rows="why"] .pap-li').last().clone();
		last.find('textarea').val('');
		box.find('[data-rows="why"]').append(last);
	});

	// Gore / dolje / obriši (red tablice, blok ili stavka).
	function item(btn) { return $(btn).closest('tr, .pap-block, .pap-li'); }
	function move(el, up) {
		var ta = el.find('textarea.pap-rich')[0];
		if (ta) { unrich(ta); }
		if (up) { el.prev().before(el); } else { el.next().after(el); }
		if (ta) { rich(ta); }
	}
	box.on('click', '.pap-up', function () { var el = item(this); if (el.prev().length) { move(el, true); } });
	box.on('click', '.pap-down', function () { var el = item(this); if (el.next().length) { move(el, false); } });
	box.on('click', '.pap-del', function () {
		var el = item(this), t = el.find('textarea.pap-rich')[0];
		if (!window.confirm('Obrisati ovo?')) { return; }
		if (t) { unrich(t); }
		el.remove();
	});

	// Galerija.
	var gal = box.find('.pap-gallery'), ids = box.find('.pap-gallery-ids');
	function sync() {
		ids.val(gal.children().map(function () { return $(this).data('id'); }).get().join(','));
		gal.next().find('.pap-hint').text(gal.children().length + ' slika');
	}
	if ($.fn.sortable) { gal.sortable({ update: sync }); }
	box.on('click', '.pap-gdel', function () { $(this).closest('li').remove(); sync(); });
	box.on('click', '.pap-gallery-add', function (e) {
		e.preventDefault();
		var frame = wp.media({ title: 'Slike za galeriju', button: { text: 'Dodaj u galeriju' }, multiple: true, library: { type: 'image' } });
		frame.on('select', function () {
			frame.state().get('selection').each(function (a) {
				a = a.toJSON();
				var src = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
				gal.append($('<li>').attr('data-id', a.id).data('id', a.id).append($('<img alt="">').attr('src', src), '<button type="button" class="pap-gdel" title="Makni">×</button>'));
			});
			sync();
		});
		frame.open();
	});

	// Prije spremanja: sadržaj iz uređivača u polja.
	$('#post').on('submit', function () { if (window.tinymce) { tinymce.triggerSave(); } });
});
