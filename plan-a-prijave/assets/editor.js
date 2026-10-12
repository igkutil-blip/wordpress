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
			'<button type="button" class="button pap-bar-draft"></button>' +
			'<button type="button" class="button pap-bar-trash">Smeće</button>' +
			'<button type="button" class="button pap-bar-preview">Pregledaj</button>' +
			'<button type="button" class="button button-primary pap-bar-save"></button></span></div>');
		bar.find('.pap-bar-title').text(title.val() || 'Novi izlet');
		bar.find('.pap-badge').text(status).toggleClass('is-pub', isPub);
		bar.find('.pap-bar-save').text($('#publish').val() || 'Spremi');
		title.on('input', function () { bar.find('.pap-bar-title').text(this.value || 'Novi izlet'); });
		bar.on('click', '.pap-bar-save', function () { $('#publish').trigger('click'); });
		bar.on('click', '.pap-bar-preview', function () { $('#post-preview').trigger('click'); });
		// U skicu (objavljeni izlet) / Spremi skicu (skica) i Smeće – isto kao u okviru Objava.
		var draftBtn = bar.find('.pap-bar-draft');
		if (isPub) {
			draftBtn.text('U skicu');
			draftBtn.on('click', function () {
				if (!window.confirm('Prebaciti izlet u skicu? Više se neće vidjeti na stranici (prijave ostaju).')) { return; }
				$('#post_status').val('draft');
				$('#hidden_post_status').val('draft');
				$('#publish').trigger('click');
			});
		} else if ($('#save-post').length) {
			draftBtn.text('Spremi skicu');
			draftBtn.on('click', function () { $('#save-post').trigger('click'); });
		} else {
			draftBtn.remove();
		}
		var trash = $('#delete-action a.submitdelete').attr('href');
		if (trash) {
			bar.find('.pap-bar-trash').on('click', function () {
				if (window.confirm('Premjestiti izlet u smeće? Može se vratiti iz Smeća.')) { window.location.href = trash; }
			});
		} else {
			bar.find('.pap-bar-trash').remove();
		}
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
			tinymce: { wpautop: true, toolbar1: 'bold,italic,bullist,numlist,link,unlink,undo,redo', height: 160, paste_webkit_styles: 'none', paste_remove_styles_if_webkit: true, paste_retain_style_properties: 'none' },
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
	// Standard 3:2, najmanje 1200 px širine: ostale slike žuti rub i opis.
	function checkSizes() {
		gal.children().each(function () {
			var w = parseInt($(this).attr('data-w'), 10) || 0, h = parseInt($(this).attr('data-h'), 10) || 0, msg = '';
			if (w && h) {
				if (h >= w) { msg = 'Uspravna slika (' + w + ' × ' + h + ') – na vrhu izleta će biti jako obrezana. Bolje vodoravna 3:2.'; }
				else if (w < 1200) { msg = 'Mala slika (' + w + ' × ' + h + ') – može biti mutna. Preporuka 1800 × 1200.'; }
				else if (w / h > 2 || w / h < 1.3) { msg = 'Omjer ' + w + ' × ' + h + ' – bolje 3:2 (npr. 1800 × 1200).'; }
			}
			$(this).toggleClass('pap-gwarn', !!msg).attr('title', msg || (w ? w + ' × ' + h + ' – u redu' : ''));
		});
	}
	checkSizes();
	box.on('click', '.pap-gdel', function () { $(this).closest('li').remove(); sync(); });
	box.on('click', '.pap-gallery-add', function (e) {
		e.preventDefault();
		var frame = wp.media({ title: 'Slike za galeriju', button: { text: 'Dodaj u galeriju' }, multiple: true, library: { type: 'image' } });
		frame.on('select', function () {
			frame.state().get('selection').each(function (a) {
				a = a.toJSON();
				var src = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
				gal.append($('<li>').attr({ 'data-id': a.id, 'data-w': a.width || 0, 'data-h': a.height || 0 }).data('id', a.id).append($('<img alt="">').attr('src', src), '<button type="button" class="pap-gdel" title="Makni">×</button>'));
			});
			sync();
			checkSizes();
		});
		frame.open();
	});

	/* ---------- Ikonice, nove aktivnosti i stavke uključeno/isključeno ---------- */
	var ICONS = ['person-hiking', 'mountain', 'mountain-sun', 'tree', 'leaf', 'seedling', 'water', 'person-swimming', 'sailboat', 'ship',
		'anchor', 'fish', 'umbrella-beach', 'sun', 'cloud-sun', 'snowflake', 'person-skiing', 'campground', 'tent', 'fire',
		'bicycle', 'person-biking', 'person-walking', 'shoe-prints', 'route', 'map', 'map-location-dot', 'compass', 'location-dot', 'flag',
		'binoculars', 'camera', 'landmark', 'church', 'monument', 'city', 'bus', 'van-shuttle', 'car', 'train',
		'plane', 'ticket', 'id-card', 'shield-halved', 'kit-medical', 'user-tie', 'users', 'child', 'dog', 'paw',
		'utensils', 'mug-hot', 'wine-glass', 'beer-mug-empty', 'bread-slice', 'apple-whole', 'bottle-water', 'bed', 'hotel', 'house',
		'wifi', 'square-parking', 'toilet', 'shower', 'hand-holding-dollar', 'euro-sign', 'gift', 'star', 'heart', 'music',
		'graduation-cap', 'book', 'clock', 'calendar-days', 'circle-info', 'triangle-exclamation', 'check', 'xmark', 'plus', 'minus'];
	// Hrvatske riječi za traženje (nazivi ikonica su engleski).
	var HR = { planin: 'mountain hiking', vrh: 'mountain', hod: 'walking hiking', penj: 'hand mountain', alpin: 'mountain flag', ferat: 'helmet link', ferr: 'helmet link', klin: 'link', cepin: 'snowflake', spilj: 'dungeon', tvrđav: 'chess-rook landmark', tvrdav: 'chess-rook landmark', dvorac: 'chess-rook landmark',
		more: 'water umbrella-beach ship sailboat anchor', jedr: 'sailboat ship anchor wind', kajak: 'water', plivan: 'swimming water', ronj: 'water fish',
		snijeg: 'snowflake skiing', zim: 'snowflake temperature', skij: 'skiing', bicik: 'bicycle biking', biciklizam: 'bicycle biking',
		sunce: 'sun', kiša: 'cloud umbrella', kisa: 'cloud umbrella', vjetar: 'wind', šuma: 'tree', suma: 'tree', drvo: 'tree', cvijet: 'seedling leaf', priroda: 'leaf tree seedling',
		kamp: 'campground tent', šator: 'tent', sator: 'tent', vatra: 'fire', karta: 'map ticket', kompas: 'compass', zastav: 'flag', dalekozor: 'binoculars',
		foto: 'camera', povijest: 'landmark monument', crkv: 'church', grad: 'city', muzej: 'landmark building-columns',
		autobus: 'bus', bus: 'bus', kombi: 'van-shuttle', auto: 'car', vlak: 'train', avion: 'plane', brod: 'ship ferry', prijevoz: 'bus van-shuttle car',
		ulaznic: 'ticket', osigur: 'shield', ljekar: 'kit-medical', prva: 'kit-medical', vodič: 'user compass', vodic: 'user compass', skiper: 'anchor ship',
		ljudi: 'users people', grupa: 'users people-group', dijete: 'child', djeca: 'child children', pas: 'dog paw',
		hrana: 'utensils bowl-food', ručak: 'utensils', rucak: 'utensils', večer: 'utensils moon', vecer: 'utensils moon', doručak: 'mug-hot bread-slice', dorucak: 'mug-hot bread-slice',
		piće: 'wine-glass beer-mug-empty bottle-water mug-hot', pice: 'wine-glass beer-mug-empty bottle-water mug-hot', voda: 'bottle-water water', kava: 'mug-hot', vino: 'wine',
		smješt: 'bed hotel house', smjest: 'bed hotel house', spava: 'bed', noć: 'moon bed', noc: 'moon bed', hotel: 'hotel', kuć: 'house', kuc: 'house',
		oprema: 'helmet toolbox bag kaciga ruksak karabiner', ruksak: 'bag ruksak', kaciga: 'helmet kaciga', kacig: 'helmet kaciga', uže: 'link', uze: 'link',
		novac: 'euro money coins', cijen: 'euro tag', poklon: 'gift', glazb: 'music', knjig: 'book', škol: 'graduation-cap school', skol: 'graduation-cap school',
		vrijeme: 'clock', sat: 'clock', datum: 'calendar', info: 'circle-info', upozor: 'triangle-exclamation', kvačic: 'check', kvacic: 'check', križ: 'xmark', kriz: 'xmark', srce: 'heart', zvijezd: 'star' };
	var pick = null, pickFor = null;
	function fill(q) {
		var grid = pick.find('.pap-icons-grid').empty(), list = q ? [] : ICONS.slice(), own = window.papOwnIcons || {};
		q = $.trim(q || '').toLowerCase();
		var mine = Object.keys(own).filter(function (c) { return !q || own[c].toLowerCase().indexOf(q) >= 0 || c.indexOf(q) >= 0; });
		if (q) {
			var words = [q.replace(/\s+/g, '-')];
			$.each(HR, function (k, v) { if (q.length > 1 && (q.indexOf(k) === 0 || k.indexOf(q) === 0)) { words = words.concat(v.split(' ')); } });
			list = (window.papIcons || ICONS).filter(function (n) { return words.some(function (w) { return n.indexOf(w) >= 0; }); });
		} else {
			// Prvo izbor za izlete, zatim sve ostale ikonice.
			list = list.concat((window.papIcons || []).filter(function (n) { return ICONS.indexOf(n) < 0; }));
		}
		var html = mine.map(function (c) { return '<button type="button" class="is-own" data-icon="' + c + '" title="' + own[c] + ' (Plan A)"><i class="' + c + '"></i></button>'; }).join('') +
			list.map(function (n) { return '<button type="button" data-icon="fas fa-' + n + '" title="' + n + '"><i class="fas fa-' + n + '"></i></button>'; }).join('');
		grid.html(html || '<p class="pap-icons-none">Nema ikonice za tu riječ – probaj drugu (i engleski, npr. „boat”).</p>');
		pick.find('.pap-icons-count').text((mine.length + list.length) + ' ikonica');
	}
	function picker(btn) {
		if (!pick) {
			pick = $('<div class="pap-icons" role="dialog" aria-label="Odaberi ikonicu"><input type="search" class="pap-icons-q" placeholder="Traži (npr. planina, autobus, hrana, boat)"><div class="pap-icons-grid"></div><p><span class="pap-icons-count"></span><button type="button" class="button-link pap-icons-close">Zatvori</button></p></div>').appendTo(document.body);
			var t = null;
			pick.on('input', '.pap-icons-q', function () { var v = this.value; clearTimeout(t); t = setTimeout(function () { fill(v); }, 120); });
			pick.on('keydown', '.pap-icons-q', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
			pick.on('click', '.pap-icons-close', function () { pick.hide(); });
			pick.on('click', '[data-icon]', function () { choose($(this).data('icon')); });
			$(document).on('mousedown keydown', function (e) {
				if (!pick.is(':visible')) { return; }
				if (e.type === 'keydown' && e.key !== 'Escape') { return; }
				if (e.type === 'mousedown' && $(e.target).closest('.pap-icons, .pap-ico').length) { return; }
				pick.hide();
			});
			fill('');
		}
		pickFor = $(btn);
		var o = pickFor.offset();
		pick.css({ top: o.top + pickFor.outerHeight() + 4, left: Math.max(8, Math.min(o.left, $(window).width() - 440)) }).show();
		pick.find('.pap-icons-q').val('').trigger('focus');
		fill('');
		pick.find('.pap-icons-grid').scrollTop(0);
	}
	function setIcon(btn, icon) { btn.removeClass('is-empty').find('i').attr('class', icon); }
	function choose(icon) {
		var btn = pickFor;
		pick.hide();
		if (!btn) { return; }
		if (btn.hasClass('pap-ico-new')) { btn.data('icon', icon); setIcon(btn, icon); return; }
		var term = btn.data('term'), old = btn.find('i').attr('class');
		// Ista ikonica u stupcima Uključeno i Nije uključeno.
		var all = box.find('.pap-ico[data-term="' + term + '"]');
		all.each(function () { setIcon($(this), icon); });
		$.post(papEd.ajax, { action: 'pap_term_icon', nonce: papEd.nonce, tax: btn.data('tax'), term: term, icon: icon }).fail(function () {
			all.find('i').attr('class', old);
			window.alert('Ikonica nije spremljena.');
		});
	}
	box.on('click', '.pap-ico', function (e) { e.preventDefault(); e.stopPropagation(); picker(this); });

	function addTerm(row) {
		var name = $.trim(row.find('.pap-addterm-name').val()), btn = row.find('.pap-addterm-btn');
		if (!name) { row.find('.pap-addterm-name').trigger('focus'); return; }
		btn.prop('disabled', true);
		$.post(papEd.ajax, { action: 'pap_add_term', nonce: papEd.nonce, tax: row.data('tax'), name: name, icon: row.find('.pap-ico-new').data('icon') })
			.done(function (r) {
				if (!r || !r.success) { window.alert((r && r.data && r.data.message) || 'Nije dodano.'); return; }
				$.each(r.data.html, function (field, html) {
					var list = box.find('[data-terms="' + field + '"]');
					var old = list.find('.pap-ico[data-term="' + r.data.id + '"]').closest('label');
					var el = old.length ? old : $(html).appendTo(list);
					if (field === row.data('field')) { el.find('input').prop('checked', true); el.addClass('is-new'); }
				});
				row.find('.pap-addterm-name').val('').trigger('focus');
			})
			.fail(function (x) { window.alert((x.responseJSON && x.responseJSON.data && x.responseJSON.data.message) || 'Nije dodano.'); })
			.always(function () { btn.prop('disabled', false); });
	}
	box.on('click', '.pap-addterm-btn', function () { addTerm($(this).closest('.pap-addterm')); });
	box.on('keydown', '.pap-addterm-name, .pap-gf-name', function (e) {
		if (e.key !== 'Enter') { return; }
		e.preventDefault(); // inače bi Enter spremio cijeli izlet
		if ($(this).hasClass('pap-addterm-name')) { addTerm($(this).closest('.pap-addterm')); } else { box.find('.pap-gf-save').trigger('click'); }
	});

	/* ---------- Vodiči ---------- */
	var gf = box.find('.pap-guide-form');
	function gfImg(id, url) {
		gf.find('.pap-gf-img').val(id || 0);
		gf.find('.pap-gf-prev').html(url ? $('<img alt="">').attr('src', url) : '<span class="dashicons dashicons-admin-users"></span>');
		gf.find('.pap-gf-noimg').toggle(!!id);
	}
	function gfOpen(card) {
		var c = card ? $(card) : null;
		gf.find('h4').text(c ? 'Uredi vodiča' : 'Novi vodič');
		gf.find('.pap-gf-id').val(c ? c.data('id') : 0);
		gf.find('.pap-gf-name').val(c ? c.attr('data-name') : '');
		gfImg(c ? c.data('img') : 0, c ? c.find('.pap-guide-img img').attr('src') : '');
		gf.find('.pap-gf-msg').text('');
		gf.prop('hidden', false);
		// Opis: uređivač s karticama Vizualno / Tekst (HTML), kao kod običnih stranica.
		var ta = document.getElementById('pap-gf-desc');
		if (window.wp && wp.editor) { wp.editor.remove('pap-gf-desc'); }
		ta.value = c ? c.attr('data-desc') : '';
		if (window.wp && wp.editor) {
			wp.editor.initialize('pap-gf-desc', {
				tinymce: { wpautop: true, height: 220, paste_webkit_styles: 'none', paste_remove_styles_if_webkit: true, paste_retain_style_properties: 'none', toolbar1: 'formatselect,bold,italic,bullist,numlist,alignleft,aligncenter,alignright,link,unlink,undo,redo' },
				quicktags: { buttons: 'strong,em,link,ul,ol,li,p' },
				mediaButtons: false
			});
		}
		gf.find('.pap-gf-name').trigger('focus');
	}
	box.on('click', '.pap-guide-new', function () { gfOpen(null); });
	box.on('click', '.pap-guide-edit', function () { gfOpen($(this).closest('.pap-guide')); });
	box.on('click', '.pap-gf-cancel', function () { gf.prop('hidden', true); });
	box.on('click', '.pap-gf-noimg', function () { gfImg(0, ''); });
	box.on('click', '.pap-gf-pick', function (e) {
		e.preventDefault();
		var frame = wp.media({ title: 'Slika vodiča', button: { text: 'Odaberi' }, multiple: false, library: { type: 'image' } });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			gfImg(a.id, (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url);
		});
		frame.open();
	});
	box.on('click', '.pap-gf-save', function () {
		var btn = $(this), id = parseInt(gf.find('.pap-gf-id').val(), 10) || 0;
		if (!$.trim(gf.find('.pap-gf-name').val())) { gf.find('.pap-gf-name').trigger('focus'); return; }
		btn.prop('disabled', true);
		gf.find('.pap-gf-msg').text('Spremam …');
		$.post(papEd.ajax, { action: 'pap_save_guide', nonce: papEd.nonce, id: id, name: gf.find('.pap-gf-name').val(), desc: (window.wp && wp.editor && wp.editor.getContent) ? wp.editor.getContent('pap-gf-desc') : $('#pap-gf-desc').val(), img: gf.find('.pap-gf-img').val() })
			.done(function (r) {
				if (!r || !r.success) { gf.find('.pap-gf-msg').text((r && r.data && r.data.message) || 'Nije spremljeno.'); return; }
				var card = $(r.data.html), old = box.find('.pap-guide[data-id="' + r.data.id + '"]');
				if (old.length) {
					card.find('input').prop('checked', old.find('input').prop('checked'));
					old.replaceWith(card);
				} else {
					box.find('[data-rows="guides"]').append(card.addClass('is-new'));
					box.find('.pap-guides-empty').remove();
				}
				gf.prop('hidden', true);
			})
			.fail(function (x) { gf.find('.pap-gf-msg').text((x.responseJSON && x.responseJSON.data && x.responseJSON.data.message) || 'Nije spremljeno.'); })
			.always(function () { btn.prop('disabled', false); });
	});

	/* ---------- Zajednička mjesta: karta „prve rate” nema svoja mjesta ---------- */
	function isRate(v) { return /prve rate/i.test(v || ''); }
	function seats() {
		var sum = 0;
		box.find('[data-rows="tickets"] tr').each(function () {
			var tr = $(this), rate = isRate(tr.find('input[name$="[name]"]').val());
			tr.toggleClass('pap-rate', rate);
			tr.find('input[name$="[qty]"]').prop('required', !rate);
			if (!rate) { sum += parseInt(tr.find('input[name$="[qty]"]').val(), 10) || 0; }
		});
		box.find('.pap-pool-n').text(sum);
	}
	box.on('input change', '[data-rows="tickets"] input', seats);
	box.on('click', '.pap-add, .pap-add-rate, .pap-del', function () { setTimeout(seats, 0); });
	seats();

	/* ---------- Lokacija: nova zemlja u padajućem izborniku ---------- */
	var locSel = box.find('.pap-loc'), locPrev = locSel.val();
	locSel.on('change', function () {
		var isNew = this.value === '__new__';
		box.find('.pap-loc-new').prop('hidden', !isNew);
		if (isNew) { box.find('.pap-loc-name').val('').trigger('focus'); } else { locPrev = this.value; }
	});
	function addLoc() {
		var name = $.trim(box.find('.pap-loc-name').val());
		if (!name) { box.find('.pap-loc-name').trigger('focus'); return; }
		var found = locSel.find('option').filter(function () { return this.value.toLowerCase() === name.toLowerCase(); });
		if (!found.length) { found = $('<option>').val(name).text(name).insertBefore(locSel.find('option[value="__new__"]')); }
		locSel.val(found.val());
		locPrev = found.val();
		box.find('.pap-loc-new').prop('hidden', true);
	}
	box.on('click', '.pap-loc-add', addLoc);
	box.on('keydown', '.pap-loc-name', function (e) { if (e.key === 'Enter') { e.preventDefault(); addLoc(); } if (e.key === 'Escape') { locSel.val(locPrev).trigger('change'); } });

	// Prije spremanja: sadržaj iz uređivača u polja.
	$('#post').on('submit', function () { if (window.tinymce) { tinymce.triggerSave(); } });
});
