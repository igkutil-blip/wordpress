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
