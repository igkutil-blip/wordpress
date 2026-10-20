/* Domestic Spirits – trgovina (zaglavlje, košarica, pokloni, stranice) */
(function () {
  'use strict';
  var D = window.DS, P = D.proizvodi, S = D.postavke, DOST = S.dostava, K = S.kontakt;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var page = document.body.getAttribute('data-page');
  var qs = new URLSearchParams(location.search);
  var mobile = window.matchMedia('(max-width:720px)');
  var calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- pomoćno ---------- */
  var fmt = new Intl.NumberFormat('hr-HR', { style: 'currency', currency: 'EUR' });
  function eur(n) { return fmt.format(n); }
  function l(z) { return String(z).replace('.', ',') + ' l'; }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function byId(id) { for (var i = 0; i < P.length; i++) if (P[i].id === id) return P[i]; return null; }
  function marka(p) { for (var i = 0; i < D.proizvodjaci.length; i++) if (D.proizvodjaci[i].id === p.marka) return D.proizvodjaci[i].naziv; return ''; }
  function ime(p) { return marka(p) + ' ' + p.naziv; }
  function url(p) { return 'proizvod.html?id=' + encodeURIComponent(p.id); }
  function store(k, v) { try { if (v === undefined) return JSON.parse(localStorage.getItem(k)); localStorage.setItem(k, JSON.stringify(v)); } catch (e) { return null; } }
  function norm(s) { return String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd'); }
  function komada(n) { return n % 10 === 1 && n % 100 !== 11 ? 'boca' : (n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20)) ? 'boce' : 'boca'; }

  var I = {
    search: '<svg class="i" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>',
    bag: '<svg class="i" viewBox="0 0 24 24"><path d="M5 7.5h14l-1 13H6l-1-13Z"/><path d="M9 7.5V6a3 3 0 0 1 6 0v1.5"/></svg>',
    heart: '<svg class="i" viewBox="0 0 24 24"><path d="M12 20s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.3a4.3 4.3 0 0 1 7.5 2.5C19.5 15.4 12 20 12 20Z"/></svg>',
    eye: '<svg class="i" viewBox="0 0 24 24"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/></svg>',
    arrow: '<svg class="i" viewBox="0 0 24 24"><path d="M4 12h15m-5-5 5 5-5 5"/></svg>',
    home: '<svg class="i" viewBox="0 0 24 24"><path d="M4 11 12 4l8 7v9h-5v-6H9v6H4v-9Z"/></svg>',
    grid: '<svg class="i" viewBox="0 0 24 24"><rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="4" width="7" height="7" rx="1"/><rect x="4" y="13" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/></svg>',
    gift: '<svg class="i" viewBox="0 0 24 24"><rect x="4" y="9" width="16" height="11" rx="1"/><path d="M3 9h18v-3H3v3Zm9 0v11M12 6c-1.5-3-5-3.2-5-1s3.2 1 5 1Zm0 0c1.5-3 5-3.2 5-1s-3.2 1-5 1Z"/></svg>',
    star: '<svg class="i" viewBox="0 0 24 24"><path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9L12 3.5Z"/></svg>',
    check: '<svg class="i" viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>',
    phone: '<svg class="i" viewBox="0 0 24 24"><path d="M6.5 3.5h3l1.5 4-2 1.5a11 11 0 0 0 6 6l1.5-2 4 1.5v3a2 2 0 0 1-2 2A16 16 0 0 1 4.5 5.5a2 2 0 0 1 2-2Z"/></svg>',
    mail: '<svg class="i" viewBox="0 0 24 24"><rect x="3.5" y="5.5" width="17" height="13" rx="1.5"/><path d="m4 6.5 8 6 8-6"/></svg>',
    clock: '<svg class="i" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>',
    pin: '<svg class="i" viewBox="0 0 24 24"><path d="M12 21s-6.5-6.2-6.5-11a6.5 6.5 0 0 1 13 0c0 4.8-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.3"/></svg>'
  };
  var ICON = {
    casa: '<path d="M21 12h30l-3 24c-.7 7-5.3 12-12 12s-11.3-5-12-12l-3-24Z"/><path d="M24 27h24M36 48v13m-12 0h24"/>',
    kava: '<path d="M13 29h40v12a17 17 0 0 1-17 17h-6a17 17 0 0 1-17-17V29Z"/><path d="M53 31h4a8 8 0 0 1 0 16h-5M9 62h52M25 10c-7 6 7 8 0 14m13-14c-7 6 7 8 0 14"/>',
    kolac: '<path d="m14 29 34-14 12 19v24H14V29Zm0 0 46 5M14 43h46M9 63h55"/><path d="M31 29c-3-6 0-12 5-12s8 6 5 12M36 17c0-5 4-7 7-7"/>',
    tanjur: '<circle cx="37" cy="37" r="21"/><circle cx="37" cy="37" r="14"/><path d="M7 10v19m5-19v19M3 10v14c0 8 13 8 13 0V10M10 31v30m54-51v51m0-51c-7 9-7 18 0 21"/>',
    riba: '<path d="M14 35C26 16 45 16 60 35 45 54 26 54 14 35Zm0 0L4 23v24l10-12ZM35 20l7-9 4 14m-11 25 6 10 6-14"/><circle cx="49" cy="32" r="1.5"/>',
    sir: '<path d="m12 30 32-16 16 16v28H12V30Zm0 0h48M44 14v16"/><circle cx="25" cy="44" r="4"/><circle cx="47" cy="48" r="3"/><path d="M35 30v6"/>',
    koktel: '<path d="M12 21h48L36 46 12 21Zm24 25v16m-12 0h24M38 21l12-13h9M20 29h32"/><path d="M14 21a12 12 0 1 1 19-10"/>',
    medalja: '<circle cx="36" cy="44" r="17"/><circle cx="36" cy="44" r="11"/><path d="m25 30-9-20h14l6 12m11 8 9-20H42l-6 12"/>',
    temp: '<path d="M31 41V15a7 7 0 0 1 14 0v26a13 13 0 1 1-14 0Z"/><path d="M38 26v25m11-31h8m-8 10h5m-5 10h8"/><circle cx="38" cy="53" r="5"/>',
    bacva: '<ellipse cx="22" cy="36" rx="11" ry="20"/><path d="M22 16h28c6 0 11 9 11 20s-5 20-11 20H22M14 24h45M14 48h45M17 16v40"/><path d="M27 18v36M33 18v36"/>',
    kutija: '<path d="M10 24 36 12l26 12v26L36 62 10 50V24Z"/><path d="M10 24l26 12 26-12M36 36v26M23 18l26 12v9"/>',
    kamion: '<path d="M6 18h36v30H6zM42 28h12l10 10v10H42z"/><circle cx="18" cy="52" r="6"/><circle cx="52" cy="52" r="6"/>'
  };
  function svg(k) { return '<svg class="i" viewBox="0 0 72 72">' + (ICON[k] || '') + '</svg>'; }
  function stars(n) { var s = ''; for (var i = 1; i <= 5; i++) s += '<span class="st' + (i <= n ? ' on' : '') + '">' + I.star + '</span>'; return '<span class="stars" aria-label="Ocjena ' + n + ' od 5">' + s + '</span>'; }

  /* ---------- dostava ---------- */
  function shipping(t, pbr, pouzece) {
    if (!t) return 0;
    var free = t >= DOST.besplatnoOd && (!pbr || String(pbr).trim().indexOf(DOST.pbrPrefiks) === 0);
    return (free ? 0 : DOST.cijena) + (pouzece ? DOST.pouzece : 0);
  }

  /* ---------- košarica: proizvodi (id) i složeni pokloni (poklon-…) ---------- */
  var cart = store('ds_cart') || {}, gifts = store('ds_pokloni') || {}, favs = store('ds_fav') || [];
  function item(key) {
    var p = byId(key);
    if (p) return { key: key, p: p, naziv: ime(p), opis: l(p.zapremnina) + ' · ' + p.vrsta, slika: p.slika, cijena: p.cijena, href: url(p) };
    var g = gifts[key]; if (!g) return null;
    return { key: key, gift: g, naziv: 'Poklon: ' + g.kutijaNaziv, opis: g.boce.map(function (b) { var x = byId(b); return x ? ime(x) : ''; }).join(', ') + (g.poruka ? ' · čestitka' : ''), slika: 'assets/img/naslovnica/kat-poklon.jpg', cijena: g.cijena, href: 'poklon.html' };
  }
  function cartItems() { return Object.keys(cart).map(function (k) { var it = item(k); if (it) it.q = cart[k]; return it; }).filter(Boolean); }
  function cartCount() { return cartItems().reduce(function (s, i) { return s + i.q; }, 0); }
  function cartTotal() { return cartItems().reduce(function (s, i) { return s + i.q * i.cijena; }, 0); }
  function save() { store('ds_cart', cart); store('ds_pokloni', gifts); }
  function setQty(key, q) { q = Math.max(0, Math.min(99, q | 0)); if (q) cart[key] = q; else { delete cart[key]; delete gifts[key]; } save(); refresh(); }
  function add(key, q, from) {
    q = q || 1; setQty(key, (cart[key] || 0) + q);
    var it = item(key); toast(q + ' × ' + it.naziv + ' – dodano u košaricu.');
  }
  function refresh() {
    var n = cartCount();
    $$('.badge').forEach(function (b) { b.textContent = n; b.setAttribute('data-n', n); });
    $$('.fav-n').forEach(function (b) { b.textContent = favs.length; b.setAttribute('data-n', favs.length); });
    renderDrawer();
    if (page === 'kosarica') renderCart();
  }
  /* lista želja */
  function isFav(id) { return favs.indexOf(id) > -1; }
  function toggleFav(id, btn) {
    if (isFav(id)) favs.splice(favs.indexOf(id), 1); else favs.push(id);
    store('ds_fav', favs);
    $$('[data-fav="' + id + '"]').forEach(function (b) { b.classList.toggle('on', isFav(id)); b.setAttribute('aria-pressed', String(isFav(id))); });
    toast(isFav(id) ? 'Spremljeno u omiljene.' : 'Uklonjeno iz omiljenih.');
    refresh();
    if (page === 'trgovina' && kat === 'favoriti') location.reload();
  }

  /* ---------- zaglavlje, podnožje, donji izbornik ---------- */
  var nav = [['trgovina.html?kat=vina', 'Vina', 'vina'], ['trgovina.html?kat=rakije', 'Rakije', 'rakije'], ['trgovina.html?kat=likeri', 'Likeri', 'likeri'], ['poklon.html', 'Poklon paketi', 'poklon'], ['trgovina.html?kat=proizvodjaci', 'Proizvođači', 'proizvodjaci'], ['trgovina.html?kat=akcije', 'Akcije', 'akcije']];
  var kat = qs.get('kat'), here = page === 'poklon' ? 'poklon' : kat;
  $('#ds-header').innerHTML =
    '<a class="skip" href="#main">Preskoči na sadržaj</a>' +
    '<div class="topbar"><span>Okusi Hrvatske, na jednom mjestu.</span><span class="tb2">Šaljemo u ' + esc(DOST.rok) + ' · Besplatna dostava iznad ' + DOST.besplatnoOd + ' €</span></div>' +
    '<header class="wrap header"><a class="brand" href="index.html" aria-label="Domestic Spirits – početna"><img src="assets/img/logo.png" alt="Domestic Spirits" width="900" height="137"></a>' +
    '<form class="search" action="trgovina.html" role="search" autocomplete="off">' + I.search + '<input name="q" id="q" aria-label="Pretraži" placeholder="Pronađi vino, rakiju ili proizvođača" value="' + esc(qs.get('q') || '') + '" aria-controls="sugg" aria-autocomplete="list"><div class="sugg" id="sugg" role="listbox" hidden></div></form>' +
    '<div class="hicons"><a href="trgovina.html?kat=favoriti" aria-label="Omiljeni proizvodi">' + I.heart + '<span class="badge fav-n" data-n="0">0</span></a><button type="button" id="open-cart" aria-label="Košarica">' + I.bag + '<span class="badge" data-n="0">0</span></button></div></header>' +
    '<nav class="nav" aria-label="Glavni izbornik"><div class="wrap">' +
    nav.map(function (n) { return '<a href="' + n[0] + '"' + (here === n[2] ? ' class="on" aria-current="page"' : '') + '>' + n[1] + '</a>'; }).join('') +
    '<a class="horeca" href="kontakt.html?tema=ugostitelji">Za ugostitelje</a></div></nav>';

  var main = $('main'); if (main && !main.id) main.id = 'main';
  $('#ds-footer').innerHTML =
    '<footer class="foot">' +
    '<div class="wrap foot-news"><div><h2>Budite prvi koji kušaju.</h2><p>Novi proizvodi, poklon ideje i posebne ponude – jednom mjesečno.</p></div>' +
    '<form id="news" novalidate><input type="email" name="email" placeholder="Vaša e-mail adresa" aria-label="E-mail za novosti" required><button class="btn light" type="submit">Prijavi se</button></form></div>' +
    '<div class="wrap foot-cols">' +
    '<div><img class="flogo" src="assets/img/logo-svijetli.png" alt="Domestic Spirits" width="900" height="137"><p>Vina, rakije i likeri hrvatskih proizvođača – odabrani s pažnjom i dostavljeni na vaša vrata.</p></div>' +
    '<div><h3>Ponuda</h3><a href="trgovina.html?kat=vina">Vina</a><a href="trgovina.html?kat=rakije">Rakije</a><a href="trgovina.html?kat=likeri">Likeri</a><a href="poklon.html">Složi svoj poklon</a><a href="trgovina.html?kat=proizvodjaci">Proizvođači</a></div>' +
    '<div><h3>Pomoć</h3><a href="info.html#dostava">Dostava</a><a href="info.html#placanje">Načini plaćanja</a><a href="info.html#povrat">Povrat i reklamacije</a><a href="info.html#uvjeti">Uvjeti kupnje</a><a href="info.html#privatnost">Privatnost</a></div>' +
    '<div><h3>Kontakt</h3><a href="tel:' + esc(K.telLink) + '">' + I.phone + esc(K.tel) + '</a><a href="mailto:' + esc(K.email) + '">' + I.mail + esc(K.email) + '</a><span>' + I.clock + esc(K.radnoVrijeme) + '</span><span>' + I.pin + esc(K.adresa) + '</span></div>' +
    '</div>' +
    '<div class="wrap foot-pay"><span>Plaćanje:</span><b>Visa</b><b>Mastercard</b><b>Maestro</b><b>Uplatnica</b><b>Pouzeće</b></div>' +
    '<div class="foot-bottom"><div class="wrap"><small>© ' + new Date().getFullYear() + ' ' + esc(S.tvrtka) + '</small><small>18+ · Uživajte odgovorno.</small></div></div></footer>' +
    '<nav class="bnav" aria-label="Brzi izbornik"><a href="index.html"' + (page === 'naslovnica' ? ' class="on"' : '') + '>' + I.home + '<span>Početna</span></a><a href="trgovina.html"' + (page === 'trgovina' ? ' class="on"' : '') + '>' + I.grid + '<span>Ponuda</span></a><a href="poklon.html"' + (page === 'poklon' ? ' class="on"' : '') + '>' + I.gift + '<span>Poklon</span></a><button type="button" class="findlink">' + I.search + '<span>Traži</span></button><a href="kosarica.html" class="cartlink' + (page === 'kosarica' ? ' on' : '') + '">' + I.bag + '<span class="badge" data-n="0">0</span><span>Košarica</span></a></nav>' +
    '<div class="drawer" id="drawer" aria-hidden="true"><div class="shade"></div><aside role="dialog" aria-label="Košarica"><button class="x" type="button" aria-label="Zatvori">×</button><div class="eyebrow">Vaš odabir</div><h2>Košarica</h2><div class="items"></div><div class="dfoot"></div></aside></div>' +
    '<dialog id="lightbox"><button class="x" type="button" aria-label="Zatvori">×</button><img alt=""><p></p></dialog>' +
    '<dialog id="qv" class="qv"><button class="x" type="button" aria-label="Zatvori">×</button><div class="qv-body"></div></dialog>' +
    '<dialog id="quiz" class="quiz"><button class="x" type="button" aria-label="Zatvori">×</button><div class="quiz-body"></div></dialog>' +
    '<div class="toast" role="status" aria-live="polite"></div>';

  var tt;
  function toast(t) { var e = $('.toast'); e.textContent = t; e.classList.add('on'); clearTimeout(tt); tt = setTimeout(function () { e.classList.remove('on'); }, 2600); }

  /* novosti (probno) */
  $('#news').addEventListener('submit', function (e) {
    e.preventDefault(); var i = this.email;
    if (!/^\S+@\S+\.\S+$/.test(i.value)) { i.focus(); i.classList.add('bad'); return; }
    this.innerHTML = '<p class="news-ok">' + I.check + ' Hvala! Prijavljeni ste na novosti.</p>';
  });

  /* ladica košarice */
  function openDrawer() { $('#drawer').classList.add('open'); $('#drawer').setAttribute('aria-hidden', 'false'); setTimeout(function () { $('#drawer .x').focus(); }, 50); }
  function closeDrawer() { $('#drawer').classList.remove('open'); $('#drawer').setAttribute('aria-hidden', 'true'); }
  $('#open-cart').addEventListener('click', function () { if (page === 'kosarica') return; openDrawer(); });
  $('#drawer .shade').addEventListener('click', closeDrawer);
  $('#drawer .x').addEventListener('click', closeDrawer);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeDrawer(); hideSugg(); } });
  function suggestion(t) {
    var miss = DOST.besplatnoOd - t; if (miss <= 0) return null;
    var c = P.filter(function (p) { return !cart[p.id]; }).sort(function (a, b) { return Math.abs(a.cijena - miss) - Math.abs(b.cijena - miss); });
    var up = c.filter(function (p) { return p.cijena >= miss; })[0];
    return up || c[0] || null;
  }
  function freeBar(t, pbr) {
    var od = DOST.besplatnoOd;
    if (t >= od && pbr && String(pbr).trim().length >= 2 && String(pbr).trim().indexOf(DOST.pbrPrefiks) !== 0) return '<div class="free"><span>Besplatna dostava vrijedi za područje ' + esc(DOST.besplatnoPodrucje) + '. Za vašu adresu dostava je ' + eur(DOST.cijena) + '.</span></div>';
    if (t >= od) return '<div class="free ok">' + I.check + '<span>Ostvarili ste <b>besplatnu dostavu</b> na području ' + esc(DOST.besplatnoPodrucje) + '.</span><div class="bar"><i style="width:100%"></i></div></div>';
    return '<div class="free"><span>Još <b>' + eur(od - t) + '</b> do besplatne dostave (' + esc(DOST.besplatnoPodrucje) + ').</span><div class="bar"><i style="width:' + Math.round(t / od * 100) + '%"></i></div></div>';
  }
  function renderDrawer() {
    var it = cartItems(), t = cartTotal();
    $('#drawer .items').innerHTML = it.length ? it.map(function (i) {
      return '<div class="ditem"><img src="' + i.slika + '" alt=""><div><b>' + esc(i.naziv) + '</b><small>' + esc(i.opis) + '</small><small>' + i.q + ' × ' + eur(i.cijena) + '</small></div><strong>' + eur(i.q * i.cijena) + '</strong></div>';
    }).join('') : '<div class="dempty">' + I.bag + '<p>Košarica je prazna.</p></div>';
    var sg = it.length ? suggestion(t) : null;
    $('#drawer .dfoot').innerHTML = it.length ? freeBar(t) +
      (sg ? '<div class="upsell"><img src="' + sg.slika + '" alt=""><div><small>Dodajte i ovo</small><b>' + esc(ime(sg)) + '</b><span>' + eur(sg.cijena) + '</span></div><button class="btn sm" data-add="' + sg.id + '">Dodaj</button></div>' : '') +
      '<div class="tot big"><span>Ukupno</span><span>' + eur(t) + '</span></div><a class="btn wide" href="kosarica.html">Pregledaj i naruči ' + I.arrow + '</a>'
      : '<a class="btn wide" href="trgovina.html">Pogledaj ponudu</a><a class="btn ghost wide" href="poklon.html" style="margin-top:8px">Složi poklon</a>';
  }

  /* klikovi s bilo kojeg mjesta */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-add]');
    if (b) { e.preventDefault(); add(b.getAttribute('data-add'), 1, b); if ($('#qv').open) $('#qv').close(); if (!$('#drawer').classList.contains('open') && !mobile.matches) openDrawer(); return; }
    b = e.target.closest('[data-fav]'); if (b) { e.preventDefault(); toggleFav(b.getAttribute('data-fav'), b); return; }
    b = e.target.closest('[data-qv]'); if (b) { e.preventDefault(); quickView(byId(b.getAttribute('data-qv'))); return; }
    b = e.target.closest('[data-quiz]'); if (b) { e.preventDefault(); quiz(); return; }
    b = e.target.closest('dialog .x'); if (b) { b.closest('dialog').close(); }
  });
  $$('dialog').forEach(function (d) { d.addEventListener('click', function (e) { if (e.target === d) d.close(); }); });
  $('.findlink').addEventListener('click', function () { window.scrollTo({ top: 0, behavior: calm ? 'auto' : 'smooth' }); setTimeout(function () { $('#q').focus(); }, 250); });

  /* povećanje slike */
  function zoom(src, cap) { var d = $('#lightbox'); $('img', d).src = src; $('img', d).alt = cap || ''; $('p', d).textContent = cap || ''; d.showModal(); }

  /* ---------- pretraga s prijedlozima ---------- */
  var sug = $('#sugg'), qin = $('#q');
  function hideSugg() { sug.hidden = true; qin.setAttribute('aria-expanded', 'false'); }
  qin.addEventListener('input', function () {
    var v = norm(qin.value.trim());
    if (v.length < 2) { hideSugg(); return; }
    var r = P.filter(function (p) { return norm([ime(p), p.vrsta, p.kategorija, p.eyebrow || ''].join(' ')).indexOf(v) > -1; }).slice(0, 5);
    sug.innerHTML = r.length ? r.map(function (p) { return '<a role="option" href="' + url(p) + '"><img src="' + p.slika + '" alt=""><span><b>' + esc(ime(p)) + '</b><small>' + l(p.zapremnina) + ' · ' + esc(p.vrsta) + '</small></span><em>' + eur(p.cijena) + '</em></a>'; }).join('') + '<a class="all" href="trgovina.html?q=' + encodeURIComponent(qin.value.trim()) + '">Svi rezultati ' + I.arrow + '</a>'
      : '<p class="none">Nema proizvoda za „' + esc(qin.value.trim()) + '”.</p>';
    sug.hidden = false; qin.setAttribute('aria-expanded', 'true');
  });
  qin.addEventListener('keydown', function (e) {
    var o = $$('a', sug); if (sug.hidden || !o.length) return;
    var i = o.indexOf(document.activeElement);
    if (e.key === 'ArrowDown') { e.preventDefault(); o[Math.min(o.length - 1, i + 1)].focus(); }
  });
  sug.addEventListener('keydown', function (e) {
    var o = $$('a', sug), i = o.indexOf(document.activeElement);
    if (e.key === 'ArrowDown') { e.preventDefault(); o[Math.min(o.length - 1, i + 1)].focus(); }
    if (e.key === 'ArrowUp') { e.preventDefault(); if (i <= 0) qin.focus(); else o[i - 1].focus(); }
  });
  document.addEventListener('click', function (e) { if (!e.target.closest('.search')) hideSugg(); });

  /* ---------- provjera dobi ---------- */
  if (!store('ds_18')) {
    var g = document.createElement('dialog'); g.id = 'age';
    g.innerHTML = '<img src="assets/img/logo.png" alt="Domestic Spirits"><h2>Dobro došli</h2><p>Stranica sadrži alkoholna pića. Imate li najmanje 18 godina?</p><div class="btns"><button class="btn" value="da">Da, imam 18+</button><button class="btn ghost" value="ne">Nemam</button></div>';
    document.body.appendChild(g);
    g.addEventListener('cancel', function (e) { e.preventDefault(); });
    $$('button', g).forEach(function (b) { b.addEventListener('click', function () { if (b.value === 'da') { store('ds_18', 1); g.close(); } else { location.href = 'https://www.google.com/'; } }); });
    g.showModal();
  }

  /* ---------- kartice ---------- */
  function badges(p) { return (p.znacke || []).map(function (z) { return '<span class="tag tag-' + z + '">' + (z === 'nagradeno' ? svg('medalja') : '') + esc(D.znacke[z]) + '</span>'; }).join(''); }
  function tools(p) {
    return '<button type="button" class="ico fav' + (isFav(p.id) ? ' on' : '') + '" data-fav="' + p.id + '" aria-pressed="' + isFav(p.id) + '" aria-label="Spremi u omiljene: ' + esc(ime(p)) + '">' + I.heart + '</button>' +
      '<button type="button" class="ico qvb" data-qv="' + p.id + '" aria-label="Brzi pregled: ' + esc(ime(p)) + '">' + I.eye + '</button>';
  }
  function photo(p) { return '<a class="ph" href="' + url(p) + '" tabindex="-1" aria-hidden="true"><img src="' + p.slika + '" alt="" loading="lazy" width="900" height="900">' + (p.slika2 ? '<img class="alt2" src="' + p.slika2 + '" alt="" loading="lazy">' : '') + '</a>'; }
  function card(p) {
    return '<article class="pcard"><div class="phw">' + photo(p) + '<div class="tags">' + badges(p) + '</div>' + tools(p) + '</div>' +
      '<h3><a href="' + url(p) + '">' + esc(ime(p)) + '</a></h3><div class="meta">' + l(p.zapremnina) + '<i>·</i>' + esc(p.vrsta) + '</div>' +
      '<div class="row"><span class="price">' + eur(p.cijena) + '</span><button class="btn sm" data-add="' + p.id + '" aria-label="U košaricu: ' + esc(ime(p)) + '">U košaricu</button></div></article>';
  }
  function hcard(p) {
    return '<article class="pcard hcard"><div class="phw">' + photo(p) + '<div class="tags">' + badges(p) + '</div></div><div>' +
      '<h3><a href="' + url(p) + '">' + esc(ime(p)) + '</a></h3><div class="meta">' + l(p.zapremnina) + '<i>·</i>' + esc(p.vrsta) + '</div>' +
      '<span class="price">' + eur(p.cijena) + '</span><button class="btn sm" data-add="' + p.id + '" aria-label="U košaricu: ' + esc(ime(p)) + '">U košaricu</button>' +
      '<button type="button" class="ico fav' + (isFav(p.id) ? ' on' : '') + '" data-fav="' + p.id + '" aria-pressed="' + isFav(p.id) + '" aria-label="Spremi u omiljene: ' + esc(ime(p)) + '">' + I.heart + '</button></div></article>';
  }

  /* brzi pregled */
  function quickView(p) {
    if (!p) return;
    $('#qv .qv-body').innerHTML = '<div class="qv-img"><img src="' + p.slika + '" alt="' + esc(ime(p)) + '"><div class="tags">' + badges(p) + '</div></div><div class="qv-info"><div class="eyebrow">' + esc(p.eyebrow || p.vrsta) + '</div><h2>' + esc(ime(p)) + '</h2>' +
      '<p>' + esc(p.kratko || '') + '</p><div class="facts"><div><strong>' + l(p.zapremnina) + '</strong><span>Zapremnina</span></div>' + (p.alkohol ? '<div><strong>' + esc(p.alkohol) + '</strong><span>Alkohol</span></div>' : '') + (p.temperatura ? '<div><strong>' + esc(p.temperatura) + '</strong><span>Serviranje</span></div>' : '') + '</div>' +
      '<div class="price">' + eur(p.cijena) + '</div><div class="qv-btns"><button class="btn" data-add="' + p.id + '">' + I.bag + 'Dodaj u košaricu</button><button type="button" class="ico fav big' + (isFav(p.id) ? ' on' : '') + '" data-fav="' + p.id + '" aria-label="Spremi u omiljene">' + I.heart + '</button></div>' +
      '<a class="link" href="' + url(p) + '">Sve o proizvodu ' + I.arrow + '</a></div>';
    $('#qv').showModal();
  }

  /* ---------- kviz „Pronađi svoju kapljicu” ---------- */
  var Q = [
    { k: 'za', pitanje: 'Za koga tražite?', opcije: [['sebe', 'Za sebe', 'casa'], ['poklon', 'Za poklon', 'kutija'], ['drustvo', 'Za društvo', 'koktel']] },
    { k: 'okus', pitanje: 'Što vam je najdraže?', opcije: [['slatko', 'Slatko i kremasto', 'kolac'], ['vocno', 'Voćno i svježe', 'koktel'], ['gorko', 'Gorko-biljno', 'casa'], ['vino', 'Dobro vino', 'tanjur']] },
    { k: 'prilika', pitanje: 'Kada ćete ga posluživati?', opcije: [['aperitiv', 'Prije jela', 'casa'], ['desert', 'Uz desert ili kavu', 'kava'], ['jelo', 'Uz jelo', 'tanjur'], ['digestiv', 'Nakon jela', 'casa']] }
  ];
  function quiz() {
    var a = {}, step = 0, box = $('#quiz .quiz-body');
    function draw() {
      if (step < Q.length) {
        var q = Q[step];
        box.innerHTML = '<div class="eyebrow">Pronađi svoju kapljicu · ' + (step + 1) + '/' + Q.length + '</div><div class="dots">' + Q.map(function (x, i) { return '<i class="' + (i <= step ? 'on' : '') + '"></i>'; }).join('') + '</div><h2>' + q.pitanje + '</h2><div class="opts">' +
          q.opcije.map(function (o) { return '<button type="button" class="opt" data-v="' + o[0] + '">' + svg(o[2]) + '<span>' + o[1] + '</span></button>'; }).join('') + '</div>' +
          (step ? '<button type="button" class="back link">← Natrag</button>' : '');
        $$('.opt', box).forEach(function (b) { b.addEventListener('click', function () { a[q.k] = b.getAttribute('data-v'); step++; draw(); }); });
        var bk = $('.back', box); if (bk) bk.addEventListener('click', function () { step--; draw(); });
        var f = $('.opt', box); if (f) f.focus();
        return;
      }
      var sc = P.map(function (p) { return { p: p, s: (p.okus === a.okus ? 3 : 0) + ((p.prilika || []).indexOf(a.prilika) > -1 ? 2 : 0) + ((p.znacke || []).length ? 0.5 : 0) }; })
        .sort(function (x, y) { return y.s - x.s || y.p.cijena - x.p.cijena; }).slice(0, 3);
      box.innerHTML = '<div class="eyebrow">Naša preporuka</div><h2>Ovo bi vam se moglo svidjeti.</h2><div class="quiz-res">' +
        sc.map(function (x, i) { return '<div class="qr' + (i ? '' : ' best') + '">' + (i ? '' : '<span class="tag tag-nagradeno">Najbolji izbor</span>') + '<img src="' + x.p.slika + '" alt=""><b><a href="' + url(x.p) + '">' + esc(ime(x.p)) + '</a></b><small>' + esc(x.p.vrsta) + '</small><span class="price">' + eur(x.p.cijena) + '</span><button class="btn sm" data-add="' + x.p.id + '">U košaricu</button></div>'; }).join('') + '</div>' +
        (a.za === 'poklon' ? '<a class="btn ghost wide" href="poklon.html">' + I.gift + 'Složite ih u poklon kutiju</a>' : '') +
        '<button type="button" class="again link">↻ Ponovi kviz</button>';
      $('.again', box).addEventListener('click', function () { a = {}; step = 0; draw(); });
    }
    draw(); $('#quiz').showModal();
  }

  /* ---------- naslovnica ---------- */
  if (page === 'naslovnica') {
    if (S.usporedba && S.originalnaStranica) $('.hero .actions').insertAdjacentHTML('afterend', '<a class="compare-link" href="' + esc(S.originalnaStranica) + '" target="_blank" rel="noopener">Pogledaj originalnu stranicu ↗</a>');
    $('#prednosti').innerHTML = D.prednosti.map(function (x) { return '<div class="perk">' + svg(x.ikona) + '<div><b>' + esc(x.naslov) + '</b><span>' + esc(x.tekst) + '</span></div></div>'; }).join('');
    $('#cats').innerHTML = D.kategorije.map(function (k) {
      var n = k.id === 'poklon-paketi' ? 0 : P.filter(function (p) { return p.kategorija === k.id || (p.takodjer || []).indexOf(k.id) > -1; }).length;
      return '<a class="cat" href="' + (k.id === 'poklon-paketi' ? 'poklon.html' : 'trgovina.html?kat=' + k.id) + '"><img src="' + k.slika + '" alt="" loading="lazy"><span>' + k.naziv + ' ' + I.arrow + '</span><small>' + (n ? n + ' ' + (n === 1 ? 'proizvod' : n < 5 ? 'proizvoda' : 'proizvoda') : 'Složi svoj') + '</small></a>';
    }).join('');
    $('#izdvojeno').innerHTML = P.filter(function (p) { return p.izdvojeno === 1; }).map(card).join('');
    $('#izdvojeno2').innerHTML = P.filter(function (p) { return p.izdvojeno === 2; }).map(hcard).join('');
    var aw = [];
    P.forEach(function (p) { (p.nagrade || []).forEach(function (n) { aw.push({ p: p, n: n }); }); });
    $('#priznanja-list').innerHTML = aw.map(function (x) { return '<a class="aw" href="' + url(x.p) + '">' + (x.n.slika ? '<img src="' + x.n.slika + '" alt="">' : '<span class="medal">' + svg('medalja') + '</span>') + '<span><b>' + esc(x.n.naslov) + '</b><small>' + esc(x.n.mjesto) + '</small><em>' + esc(ime(x.p)) + '</em></span></a>'; }).join('');
    var R = D.recenzije;
    if (R && R.popis.length && (!R.primjer || S.probno)) {
      $('#recenzije-list').innerHTML = R.popis.map(function (r) { var p = byId(r.proizvod); return '<figure class="rev">' + stars(r.ocjena) + '<blockquote>„' + esc(r.tekst) + '”</blockquote><figcaption><b>' + esc(r.ime) + '</b>, ' + esc(r.mjesto) + (p ? '<a href="' + url(p) + '">' + esc(ime(p)) + '</a>' : '') + '</figcaption></figure>'; }).join('');
    } else $('#recenzije').remove();
  }

  /* ---------- trgovina (popis, filtri) ---------- */
  if (page === 'trgovina') {
    var q = (qs.get('q') || '').trim(), marka_ = qs.get('marka'), naslov = 'Sva ponuda', list = P.slice(), uz = qs.get('uz') || '', cmax = qs.get('do') || '';
    if (q) { naslov = 'Rezultati: „' + q + '”'; list = list.filter(function (p) { return norm([ime(p), p.vrsta, p.kategorija, p.eyebrow || ''].join(' ')).indexOf(norm(q)) > -1; }); }
    else if (marka_) { list = list.filter(function (p) { return p.marka === marka_; }); naslov = (D.proizvodjaci.filter(function (m) { return m.id === marka_; })[0] || {}).naziv || naslov; }
    else if (kat === 'favoriti') { naslov = 'Omiljeni proizvodi'; list = list.filter(function (p) { return isFav(p.id); }); }
    else if (kat === 'proizvodjaci') naslov = 'Proizvođači';
    else if (kat === 'akcije') { naslov = 'Akcije'; list = list.filter(function (p) { return p.akcija; }); }
    else if (kat === 'poklon-paketi') { location.replace('poklon.html'); return; }
    else if (kat) { var k = D.kategorije.filter(function (c) { return c.id === kat; })[0]; if (k) { naslov = k.naziv; list = list.filter(function (p) { return p.kategorija === kat || (p.takodjer || []).indexOf(kat) > -1; }); } }
    document.title = naslov + ' | Domestic Spirits';
    $('#crumb-now').textContent = naslov;
    $('#shop-title').textContent = naslov;
    var link = function (o) { var u = new URLSearchParams(location.search); Object.keys(o).forEach(function (x) { if (o[x]) u.set(x, o[x]); else u.delete(x); }); var s = u.toString(); return 'trgovina.html' + (s ? '?' + s : ''); };
    $('#filters').innerHTML = '<a class="chip' + (!kat && !q && !marka_ ? ' on' : '') + '" href="trgovina.html">Sve</a>' +
      (kat === 'proizvodjaci' || marka_ ? D.proizvodjaci.map(function (m) { return '<a class="chip' + (marka_ === m.id ? ' on' : '') + '" href="trgovina.html?marka=' + m.id + '">' + m.naziv + '</a>'; })
        : D.kategorije.map(function (c) { return '<a class="chip' + (kat === c.id ? ' on' : '') + '" href="' + (c.id === 'poklon-paketi' ? 'poklon.html' : 'trgovina.html?kat=' + c.id) + '">' + c.naziv + '</a>'; })).join('');
    if (kat !== 'proizvodjaci') {
      $('#filters2').innerHTML = '<span class="flabel">Uz što:</span>' + Object.keys(D.prilike).map(function (x) { return '<a class="chip sm' + (uz === x ? ' on' : '') + '" href="' + link({ uz: uz === x ? '' : x }) + '">' + esc(D.prilike[x]) + '</a>'; }).join('') +
        '<label class="flabel">Cijena: <select id="do" class="sort"><option value="">sve</option><option value="10">do 10 €</option><option value="15">do 15 €</option><option value="20">do 20 €</option></select></label>';
      $('#do').value = cmax;
      $('#do').addEventListener('change', function () { location.href = link({ do: this.value }); });
    }
    if (uz) list = list.filter(function (p) { return (p.prilika || []).indexOf(uz) > -1; });
    if (cmax) list = list.filter(function (p) { return p.cijena <= +cmax; });
    var draw = function () {
      var s = $('#sort').value, l2 = list.slice();
      if (s === 'jeftinije') l2.sort(function (a, b) { return a.cijena - b.cijena; });
      if (s === 'skuplje') l2.sort(function (a, b) { return b.cijena - a.cijena; });
      if (s === 'naziv') l2.sort(function (a, b) { return ime(a).localeCompare(ime(b), 'hr'); });
      $('#count').textContent = l2.length + ' ' + (l2.length === 1 ? 'proizvod' : l2.length > 1 && l2.length < 5 ? 'proizvoda' : 'proizvoda');
      $('#list').innerHTML = l2.map(card).join('');
      $('#empty').hidden = !!l2.length;
      $('#empty-text').textContent = kat === 'akcije' ? 'Trenutno nema proizvoda na akciji. Prijavite se na novosti u podnožju i prvi saznajte za sljedeću.' : kat === 'favoriti' ? 'Još niste spremili nijedan proizvod. Dodirnite srce na kartici proizvoda.' : 'Nema proizvoda za odabrane uvjete.';
    };
    if (kat === 'proizvodjaci' && !marka_) {
      $('#sort').hidden = true;
      $('#list').innerHTML = D.proizvodjaci.map(function (m) {
        var ps = P.filter(function (p) { return p.marka === m.id; });
        return '<article class="pcard"><div class="phw"><a class="ph" href="trgovina.html?marka=' + m.id + '"><img src="' + ps[0].slika + '" alt="" loading="lazy"></a></div><h3><a href="trgovina.html?marka=' + m.id + '">' + m.naziv + '</a></h3><div class="meta">' + ps.length + ' ' + (ps.length === 1 ? 'proizvod' : 'proizvoda') + '</div><div class="row" style="justify-content:center"><a class="link" href="trgovina.html?marka=' + m.id + '">Pogledaj ' + I.arrow + '</a></div></article>';
      }).join('');
      $('#count').textContent = D.proizvodjaci.length + ' proizvođača';
    } else { $('#sort').addEventListener('change', draw); draw(); }
  }

  /* ---------- proizvod ---------- */
  function ukratko(p) {
    var rows = (p.podaci || []).filter(function (r) { return /^(Vrsta|Geografsko podrijetlo|Gastro preporuka|Proizvođač)$/.test(r[0]); });
    return rows.length ? '<div class="reasons"><h3>Ukratko o proizvodu</h3><dl>' + rows.map(function (r) { return '<dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1].charAt(0).toUpperCase() + r[1].slice(1)) + '</dd>'; }).join('') + '</dl></div>' : '';
  }
  if (page === 'proizvod') {
    var p = byId(qs.get('id')) || P[0], kk = D.kategorije.filter(function (c) { return c.id === p.kategorija; })[0];
    document.title = ime(p) + ' · ' + p.vrsta + ' | Domestic Spirits';
    var gal = [{ slika: p.slika, naziv: 'Boca', opis: ime(p).toUpperCase() + ' · ' + l(p.zapremnina) }].concat(p.galerija || []);
    var tabs = [['opis', 'O proizvodu']];
    if (p.posluzivanje) tabs.push(['posluzivanje', 'Kako poslužiti']);
    if (p.nagrade) tabs.push(['nagrade', 'Nagrade']);
    tabs.push(['detalji', 'Podaci']);
    var podaci = p.podaci || [['Naziv', ime(p) + ' ' + l(p.zapremnina)], ['Vrsta', p.vrsta], ['Zapremnina boce', l(p.zapremnina)], ['Na tržište stavlja', S.tvrtka]];
    var h = '<div class="crumb"><a href="index.html">Početna</a><span>/</span>' + (kk ? '<a href="trgovina.html?kat=' + kk.id + '">' + kk.naziv + '</a><span>/</span>' : '') + '<span>' + esc(ime(p)) + '</span></div>' +
      '<section class="product"><div><div class="photo"><img id="main-img" src="' + p.slika + '" alt="' + esc(ime(p)) + '" width="900" height="900"><div class="tags">' + badges(p) + '</div><span class="photo-cap" id="cap">' + esc(gal[0].opis) + '</span></div>' +
      (gal.length > 1 ? '<div class="thumbs">' + gal.map(function (g, i) { return '<button type="button" class="thumb' + (i ? '' : ' on') + '" data-i="' + i + '" aria-label="' + esc(g.naziv) + '"><img src="' + g.slika + '" alt=""><span>' + esc(g.naziv) + '</span></button>'; }).join('') + '</div>' : '') + '</div>' +
      '<div class="pinfo"><div class="eyebrow">' + esc(p.eyebrow || p.vrsta) + '</div><h1>' + esc(marka(p)) + '<span>' + esc(p.naziv) + '</span></h1>' +
      (p.kratko ? '<p class="intro">' + esc(p.kratko) + '</p>' : '') +
      '<div class="facts"><div><strong>' + l(p.zapremnina) + '</strong><span>Zapremnina</span></div>' + (p.alkohol ? '<div><strong>' + esc(p.alkohol) + '</strong><span>Alkohol</span></div>' : p.temperatura ? '<div><strong>' + esc(p.temperatura) + '</strong><span>Posluživanje</span></div>' : '') + '<div><strong>' + esc(p.podrijetlo || 'Hrvatska') + '</strong><span>Podrijetlo</span></div></div>' +
      (p.nagrade ? '<div class="awards">' + p.nagrade.map(function (n) { return '<a href="#t-nagrade" data-tab-go="nagrade">' + (n.slika ? '<img src="' + n.slika + '" alt="">' : '<span class="medal">' + svg('medalja') + '</span>') + '<span><b>' + esc(n.naslov) + '</b><small>' + esc(n.mjesto) + '</small></span></a>'; }).join('') + '</div>' : '') +
      '<div class="buy"><div><span class="price">' + eur(p.cijena) + '</span><span class="unit">' + eur(p.cijena / p.zapremnina) + '/l</span></div>' +
      (p.sidrena ? '<p class="anchor">' + esc(p.sidrena) + '</p>' : '<p class="anchor">PDV uključen.</p>') +
      '<div class="buyrow"><div class="qty"><button type="button" id="minus" aria-label="Manje">−</button><input id="qty" type="number" min="1" max="99" value="1" aria-label="Količina"><button type="button" id="plus" aria-label="Više">+</button></div><button class="btn" id="add">' + I.bag + 'Dodaj u košaricu</button>' +
      '<button type="button" class="ico fav big' + (isFav(p.id) ? ' on' : '') + '" data-fav="' + p.id + '" aria-pressed="' + isFav(p.id) + '" aria-label="Spremi u omiljene">' + I.heart + '</button></div>' +
      '<ul class="ship"><li>' + I.check + 'Na stanju · šaljemo u ' + esc(DOST.rok) + '</li><li>' + I.check + 'Besplatna dostava iznad ' + DOST.besplatnoOd + ' € (' + esc(DOST.besplatnoPodrucje) + ')</li><li>' + I.check + 'Sigurno pakiranje' + (p.pakiranje ? ' · ' + esc(p.pakiranje) : '') + '</li></ul>' +
      (S.usporedba && p.original ? '<a class="btn ghost compare" href="' + esc(p.original) + '" target="_blank" rel="noopener">Usporedi s originalom ↗</a>' : '') +
      '</div>' +
      (p.mini ? '<div class="serve-mini">' + p.mini.map(function (s) { return '<div><span>' + esc(s[0]) + '</span><b>' + esc(s[1]) + '</b></div>'; }).join('') + '<a href="#t-posluzivanje" data-tab-go="posluzivanje">Kako poslužiti →</a></div>' : '') +
      '<a class="giftcta" href="poklon.html?boca=' + p.id + '">' + I.gift + '<span><b>Poklanjate?</b> Složite ' + esc(ime(p)) + ' u poklon kutiju s čestitkom.</span>' + I.arrow + '</a>' +
      '</div></section>' +
      '<section id="info"><div class="tabs" role="tablist">' + tabs.map(function (t, i) { return '<button type="button" role="tab" id="t-' + t[0] + '" aria-selected="' + (i === 0) + '" data-tab="' + t[0] + '">' + t[1] + '</button>'; }).join('') + '</div>' +
      '<div class="panel" id="opis"><div class="cols"><div><div class="eyebrow">Domaća priča</div><h2>' + esc(p.naslovOpisa || ime(p)) + '</h2>' +
      (p.opis || [p.vrsta + ' iz ponude hrvatskih proizvođača, ' + l(p.zapremnina) + '.']).map(function (t) { return '<p>' + esc(t) + '</p>'; }).join('') + (p.dodatno ? '<p class="extra">' + esc(p.dodatno) + '</p>' : '') + '</div>' +
      (p.razlozi ? '<div class="reasons"><h3>' + esc(p.razloziNaslov || 'Zašto odabrati ovaj proizvod?') + '</h3><ul>' + p.razlozi.map(function (r) { return '<li>' + esc(r) + '</li>'; }).join('') + '</ul>' + (p.zakljucak ? '<p class="closing">' + esc(p.zakljucak) + '</p>' : '') + '</div>' : ukratko(p)) + '</div></div>' +
      (p.posluzivanje ? '<div class="panel" id="posluzivanje" hidden><div class="eyebrow">' + esc(marka(p)) + ' · Gastro preporuka</div><h2>' + esc(p.posluzivanjeNaslov || 'Kako poslužiti.') + '</h2><div class="serve-cards">' +
        p.posluzivanje.map(function (s, i) { return '<article class="serve-card"><div class="top">' + svg(s.ikona) + '<span class="num">0' + (i + 1) + '</span></div><h3>' + esc(s.naziv) + '</h3>' + (s.kolicina ? '<div class="dose">' + esc(s.kolicina) + '</div>' : '') + '<p>' + esc(s.opis) + '</p></article>'; }).join('') +
        '</div>' + (p.temperatura ? '<div class="serve-temp">' + svg('temp') + '<div><span>Temperatura serviranja</span><strong>' + esc(p.temperatura) + '</strong></div><p>Odaberite svoj način posluživanja.</p></div>' : '') + '</div>' : '') +
      (p.nagrade ? '<div class="panel" id="nagrade" hidden><div class="eyebrow">Izvorna priznanja</div><h2>Nagrađena kvaliteta.</h2><div class="award-list">' +
        p.nagrade.map(function (n) { return '<article>' + (n.slika ? '<button type="button" data-zoom="' + n.slika + '" data-cap="' + esc(n.naslov + ' · ' + n.mjesto) + '"><img src="' + n.slika + '" alt="' + esc(n.naslov) + '"></button>' : '<span class="medal big">' + svg('medalja') + '</span>') + '<div><h3>' + esc(n.naslov) + '</h3><p>' + esc(n.tekst) + '</p></div></article>'; }).join('') + '</div></div>' : '') +
      '<div class="panel" id="detalji" hidden><div class="eyebrow">Sve na jednom mjestu</div><h2>Podaci o proizvodu.</h2><table class="data"><tbody>' +
        podaci.map(function (r) { return '<tr><th>' + esc(r[0]) + '</th><td>' + esc(r[1]) + '</td></tr>'; }).join('') + '</tbody></table></div></section>' +
      '<section class="sec related"><div class="sec-head"><h2>Možda će vam se svidjeti</h2><a class="link" href="trgovina.html">Pogledaj sve ' + I.arrow + '</a></div><div class="grid4">' +
        P.filter(function (x) { return x.id !== p.id; }).sort(function (a, b) { return (b.kategorija === p.kategorija) - (a.kategorija === p.kategorija); }).slice(0, 4).map(card).join('') + '</div></section>' +
      '<div class="sticky-buy" id="sticky" aria-hidden="true"><img src="' + p.slika + '" alt=""><div><b>' + esc(ime(p)) + '</b><span>' + eur(p.cijena) + '</span></div><button class="btn" data-add="' + p.id + '" tabindex="-1">' + I.bag + 'U košaricu</button></div>';
    $('#product').innerHTML = h;

    var showTab = function (n, go) { $$('[data-tab]').forEach(function (b) { b.setAttribute('aria-selected', String(b.getAttribute('data-tab') === n)); }); $$('.panel').forEach(function (x) { x.hidden = x.id !== n; }); if (go) $('#info').scrollIntoView({ behavior: calm ? 'auto' : 'smooth' }); };
    $$('[data-tab]').forEach(function (b) { b.addEventListener('click', function () { showTab(b.getAttribute('data-tab')); }); });
    $$('[data-tab-go]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); showTab(a.getAttribute('data-tab-go'), true); }); });
    $$('.thumb').forEach(function (b) { b.addEventListener('click', function () { var g = gal[+b.getAttribute('data-i')]; $('#main-img').src = g.slika; $('#cap').textContent = g.opis; $$('.thumb').forEach(function (t) { t.classList.toggle('on', t === b); }); }); });
    $('#main-img').addEventListener('click', function () { zoom(this.src, $('#cap').textContent); });
    $$('[data-zoom]').forEach(function (b) { b.addEventListener('click', function () { zoom(b.getAttribute('data-zoom'), b.getAttribute('data-cap')); }); });
    var qv = function () { return Math.max(1, Math.min(99, parseInt($('#qty').value, 10) || 1)); };
    $('#minus').addEventListener('click', function () { $('#qty').value = Math.max(1, qv() - 1); });
    $('#plus').addEventListener('click', function () { $('#qty').value = Math.min(99, qv() + 1); });
    $('#add').addEventListener('click', function () { add(p.id, qv(), this); if (!mobile.matches) openDrawer(); });
    /* donja traka „U košaricu” kad glavni gumb nije vidljiv */
    if ('IntersectionObserver' in window) {
      var st = $('#sticky');
      new IntersectionObserver(function (en) { var show = !en[0].isIntersecting && en[0].boundingClientRect.top < 0; st.classList.toggle('on', show); st.setAttribute('aria-hidden', String(!show)); $('button', st).tabIndex = show ? 0 : -1; }).observe($('#add'));
    }
  }

  /* ---------- složi svoj poklon ---------- */
  if (page === 'poklon') {
    var PK = S.poklon, sel = { kutija: PK.kutije[0].id, boce: [], cestitka: false, poruka: '' };
    var pre = byId(qs.get('boca')); if (pre) sel.boce.push(pre.id);
    var box = PK.kutije[0];
    var gp = $('#poklon');
    gp.innerHTML = '<div class="gift-page"><div class="gsteps">' +
      '<section class="gstep"><h2><span>1</span>Odaberite kutiju</h2><div class="boxes">' + PK.kutije.map(function (k) { return '<label class="boxopt"><input type="radio" name="kutija" value="' + k.id + '"' + (k.id === sel.kutija ? ' checked' : '') + '><span class="bo-in">' + svg(k.id === 'drvo' ? 'bacva' : 'kutija') + '<b>' + esc(k.naziv) + '</b><small>' + esc(k.opis) + '</small><em>+ ' + eur(k.cijena) + '</em></span></label>'; }).join('') + '</div></section>' +
      '<section class="gstep"><h2><span>2</span>Odaberite boce <small id="gcount"></small></h2><div class="gbottles">' + P.map(function (p) { return '<div class="gb" data-id="' + p.id + '"><img src="' + p.slika + '" alt=""><b>' + esc(ime(p)) + '</b><small>' + l(p.zapremnina) + ' · ' + eur(p.cijena) + '</small><div class="qty sm"><button type="button" class="m" aria-label="Manje: ' + esc(ime(p)) + '">−</button><output>0</output><button type="button" class="p" aria-label="Više: ' + esc(ime(p)) + '">+</button></div></div>'; }).join('') + '</div></section>' +
      '<section class="gstep"><h2><span>3</span>Čestitka <small>(nije obavezno)</small></h2><label class="chk"><input type="checkbox" id="gcard"> Dodaj ručno pisanu čestitku (+ ' + eur(PK.cestitka) + ')</label><textarea id="gmsg" maxlength="160" placeholder="Vaša poruka (do 160 znakova)" hidden></textarea><small class="note" id="gleft" hidden></small></section>' +
      '</div><aside class="box gsum"><div class="eyebrow">Vaš poklon</div><h2>Pregled</h2><div class="gvis" id="gvis"></div><div id="glines"></div><div class="tot big"><span>Ukupno</span><span id="gtot"></span></div><button class="btn wide" id="gadd" type="button">' + I.gift + 'Dodaj poklon u košaricu</button><p class="note" id="gwarn"></p></aside></div>';
    var gdraw = function () {
      box = PK.kutije.filter(function (k) { return k.id === sel.kutija; })[0];
      if (sel.boce.length > box.mjesta) sel.boce = sel.boce.slice(0, box.mjesta);
      $$('.gb', gp).forEach(function (el) { var n = sel.boce.filter(function (b) { return b === el.getAttribute('data-id'); }).length; $('output', el).textContent = n; el.classList.toggle('on', n > 0); $('.p', el).disabled = sel.boce.length >= box.mjesta; $('.m', el).disabled = !n; });
      $('#gcount').textContent = '(' + sel.boce.length + ' od ' + box.mjesta + ')';
      var bottles = sel.boce.map(byId), sum = box.cijena + bottles.reduce(function (s, p) { return s + p.cijena; }, 0) + (sel.cestitka ? PK.cestitka : 0);
      $('#gvis').innerHTML = '<div class="gbox gbox-' + box.id + '">' + (bottles.length ? bottles.map(function (p) { return '<img src="' + p.slika + '" alt="' + esc(ime(p)) + '">'; }).join('') : '<span>Dodajte boce</span>') + '</div>';
      $('#glines').innerHTML = '<div class="tot"><span>' + esc(box.naziv) + '</span><span>' + eur(box.cijena) + '</span></div>' + bottles.map(function (p) { return '<div class="tot"><span>' + esc(ime(p)) + '</span><span>' + eur(p.cijena) + '</span></div>'; }).join('') + (sel.cestitka ? '<div class="tot"><span>Čestitka</span><span>' + eur(PK.cestitka) + '</span></div>' : '');
      $('#gtot').textContent = eur(sum);
      $('#gadd').disabled = sel.boce.length < PK.najmanjeBoca;
      $('#gwarn').textContent = sel.boce.length < PK.najmanjeBoca ? 'Odaberite barem jednu bocu.' : sel.boce.length < box.mjesta ? 'Stane još ' + (box.mjesta - sel.boce.length) + ' ' + komada(box.mjesta - sel.boce.length) + '.' : 'Kutija je puna.';
      sel.sum = sum;
      $('#gbar').innerHTML = '<div><b>' + sel.boce.length + ' / ' + box.mjesta + ' ' + komada(box.mjesta) + '</b><span>' + eur(sum) + '</span></div><button class="btn" type="button"' + (sel.boce.length < PK.najmanjeBoca ? ' disabled' : '') + '>' + I.gift + 'Dodaj poklon</button>';
    };
    gp.insertAdjacentHTML('beforeend', '<div class="gbar" id="gbar"></div>');
    $('#gbar').addEventListener('click', function (e) { if (e.target.closest('button')) $('#gadd').click(); });
    $$('input[name=kutija]', gp).forEach(function (r) { r.addEventListener('change', function () { sel.kutija = this.value; gdraw(); }); });
    $('.gbottles', gp).addEventListener('click', function (e) {
      var el = e.target.closest('.gb'); if (!el) return; var id = el.getAttribute('data-id');
      if (e.target.closest('.p') && sel.boce.length < box.mjesta) sel.boce.push(id);
      else if (e.target.closest('.m')) { var i = sel.boce.lastIndexOf(id); if (i > -1) sel.boce.splice(i, 1); }
      else if (!e.target.closest('button') && sel.boce.length < box.mjesta) sel.boce.push(id);
      gdraw();
    });
    $('#gcard').addEventListener('change', function () { sel.cestitka = this.checked; $('#gmsg').hidden = $('#gleft').hidden = !this.checked; if (this.checked) $('#gmsg').focus(); gdraw(); });
    $('#gmsg').addEventListener('input', function () { sel.poruka = this.value; $('#gleft').textContent = 'Preostalo znakova: ' + (160 - this.value.length); });
    $('#gadd').addEventListener('click', function () {
      if (sel.boce.length < PK.najmanjeBoca) return;
      var key = 'poklon-' + Date.now().toString(36);
      gifts[key] = { kutija: box.id, kutijaNaziv: box.naziv, boce: sel.boce.slice(), poruka: sel.cestitka ? (sel.poruka || '(bez poruke)') : '', cijena: Math.round(sel.sum * 100) / 100 };
      add(key, 1, this); openDrawer();
      sel.boce = []; sel.cestitka = false; sel.poruka = ''; $('#gcard').checked = false; $('#gmsg').value = ''; $('#gmsg').hidden = $('#gleft').hidden = true; gdraw();
    });
    gdraw();
  }

  /* ---------- košarica i narudžba ---------- */
  function renderCart() {
    var it = cartItems();
    if (!it.length) { $('#cart-wrap').innerHTML = '<div class="empty"><h2>Košarica je prazna</h2><p>Odaberite nešto iz naše ponude ili složite poklon.</p><a class="btn" href="trgovina.html">Pogledaj ponudu</a> <a class="btn ghost" href="poklon.html">Složi poklon</a></div>'; return; }
    if (!$('#cart-form')) build();
    $('#lines').innerHTML = it.map(function (i) {
      return '<div class="citem" data-id="' + i.key + '"><a href="' + i.href + '"><img src="' + i.slika + '" alt=""></a><div><h3><a href="' + i.href + '">' + esc(i.naziv) + '</a></h3><div class="meta">' + esc(i.opis) + ' · ' + eur(i.cijena) + '</div>' + (i.gift && i.gift.poruka ? '<div class="meta">Čestitka: „' + esc(i.gift.poruka) + '”</div>' : '') + '<button type="button" class="rm">Ukloni</button></div>' +
        '<div class="qty"><button type="button" class="m" aria-label="Manje">−</button><input type="number" min="0" max="99" value="' + i.q + '" aria-label="Količina"><button type="button" class="p" aria-label="Više">+</button></div><div class="sum">' + eur(i.q * i.cijena) + '</div></div>';
    }).join('');
    totals();
  }
  function totals() {
    var t = cartTotal(), f = $('#cart-form'), pbr = f ? f.pbr.value : '', pz = f && f.placanje.value === 'Pouzećem', sh = shipping(t, pbr, pz);
    $('#totals').innerHTML = '<div class="tot"><span>Proizvodi (' + cartCount() + ')</span><span>' + eur(t) + '</span></div><div class="tot"><span>Dostava' + (pz ? ' + pouzeće' : '') + '</span><span>' + (sh ? eur(sh) : 'Besplatno') + '</span></div><div class="tot big"><span>Ukupno</span><span>' + eur(t + sh) + '</span></div><p class="note">Cijene uključuju PDV. Šaljemo u ' + esc(DOST.rok) + '.</p>' + freeBar(t, pbr);
  }
  function build() {
    $('#cart-wrap').innerHTML = '<ol class="progress"><li class="on">Košarica</li><li class="on">Podaci</li><li>Potvrda</li></ol><div class="cart-page"><div><h1>Košarica</h1><div id="lines"></div>' +
      '<h2 class="serif" id="podaci">Podaci za dostavu</h2>' +
      '<form class="checkout" id="cart-form" novalidate>' +
      '<label>Ime<input name="ime" required autocomplete="given-name"></label><label>Prezime<input name="prezime" required autocomplete="family-name"></label>' +
      '<label>E-mail<input name="email" type="email" required autocomplete="email" inputmode="email"></label><label>Mobitel<input name="tel" type="tel" required autocomplete="tel" inputmode="tel"></label>' +
      '<label class="full">Ulica i kućni broj<input name="adresa" required autocomplete="street-address"></label>' +
      '<label>Poštanski broj<input name="pbr" required inputmode="numeric" autocomplete="postal-code" maxlength="5"></label><label>Mjesto<input name="mjesto" required autocomplete="address-level2"></label>' +
      '<label class="full">Napomena (nije obavezno)<textarea name="napomena"></textarea></label>' +
      '<fieldset class="full pay"><legend>Način plaćanja</legend><label><input type="radio" name="placanje" value="Kartica" checked><span><b>Kartica</b><small>Visa, Mastercard, Maestro – sigurno plaćanje</small></span></label><label><input type="radio" name="placanje" value="Uplata na račun"><span><b>Uplata na račun</b><small>Uplatnica i 2D kod stižu e-mailom</small></span></label><label><input type="radio" name="placanje" value="Pouzećem"><span><b>Pouzećem</b><small>Plaćanje dostavljaču (+ ' + eur(DOST.pouzece) + ')</small></span></label></fieldset>' +
      '<label class="chk full"><input type="checkbox" name="dob" required> Potvrđujem da imam najmanje 18 godina.</label>' +
      '<label class="chk full"><input type="checkbox" name="uvjeti" required> Prihvaćam <a href="info.html#uvjeti" target="_blank">uvjete kupnje</a> i obradu podataka za isporuku narudžbe.</label>' +
      '<p class="err full" id="err" role="alert" hidden></p></form></div>' +
      '<aside class="box sum-box"><h2>Pregled narudžbe</h2><div id="totals"></div><button class="btn wide" form="cart-form" type="submit">Potvrdi narudžbu</button>' +
      (S.probno ? '<p class="note">Probna trgovina: narudžba se ne šalje i ne naplaćuje.</p>' : '') + '<ul class="ship"><li>' + I.check + 'Sigurna kupnja</li><li>' + I.check + 'Povrat u 14 dana</li></ul></aside></div>';
    var saved = store('ds_kupac') || {}, f = $('#cart-form');
    $$('input:not([type=radio]):not([type=checkbox]), textarea', f).forEach(function (i) { if (saved[i.name]) i.value = saved[i.name]; });
    f.pbr.addEventListener('input', totals);
    $$('input[name=placanje]', f).forEach(function (r) { r.addEventListener('change', totals); });
    $('#lines').addEventListener('click', function (e) {
      var row = e.target.closest('.citem'); if (!row) return; var id = row.getAttribute('data-id');
      if (e.target.closest('.rm')) setQty(id, 0); else if (e.target.closest('.m')) setQty(id, cart[id] - 1); else if (e.target.closest('.p')) setQty(id, cart[id] + 1);
    });
    $('#lines').addEventListener('change', function (e) { var row = e.target.closest('.citem'); if (row) setQty(row.getAttribute('data-id'), parseInt(e.target.value, 10) || 0); });
    f.addEventListener('submit', function (e) {
      e.preventDefault(); var bad = $$('[required]', f).filter(function (i) { return i.type === 'checkbox' ? !i.checked : !i.value.trim() || (i.type === 'email' && !/^\S+@\S+\.\S+$/.test(i.value)); });
      $$('[required]', f).forEach(function (i) { i.classList.toggle('bad', bad.indexOf(i) > -1); i.setAttribute('aria-invalid', String(bad.indexOf(i) > -1)); });
      if (bad.length) { $('#err').hidden = false; $('#err').textContent = 'Molimo ispunite označena polja i potvrdite oba okvira.'; bad[0].focus(); return; }
      var data = {}; new FormData(f).forEach(function (v, k) { data[k] = v; }); store('ds_kupac', data);
      var br = 'DS-' + new Date().getFullYear() + '-' + String(Date.now()).slice(-5), it = cartItems(), t = cartTotal(), sh = shipping(t, data.pbr, data.placanje === 'Pouzećem');
      $('#cart-wrap').innerHTML = '<ol class="progress"><li class="on">Košarica</li><li class="on">Podaci</li><li class="on">Potvrda</li></ol><div class="done"><div class="okmark">' + I.check + '</div><div class="eyebrow">Hvala!</div><h1>Narudžba je zaprimljena</h1><p>Broj narudžbe: <b>' + br + '</b>. Potvrdu smo poslali na <b>' + esc(data.email) + '</b>.</p>' +
        '<div class="box">' + it.map(function (i) { return '<div class="tot"><span>' + i.q + ' × ' + esc(i.naziv) + '</span><span>' + eur(i.q * i.cijena) + '</span></div>'; }).join('') +
        '<div class="tot"><span>Dostava</span><span>' + (sh ? eur(sh) : 'Besplatno') + '</span></div><div class="tot big"><span>Ukupno</span><span>' + eur(t + sh) + '</span></div><p class="note">Plaćanje: ' + esc(data.placanje) + '<br>Dostava na: ' + esc(data.ime + ' ' + data.prezime + ', ' + data.adresa + ', ' + data.pbr + ' ' + data.mjesto) + '<br>Očekivana isporuka: ' + esc(DOST.rok) + '.</p>' +
        (S.probno ? '<p class="note">Probna trgovina: narudžba nije poslana ni naplaćena.</p>' : '') + '</div><p class="done-btns"><a class="btn" href="index.html">Natrag na naslovnicu</a></p></div>';
      cart = {}; gifts = {}; save(); refresh(); scrollTo(0, 0);
    });
  }

  /* ---------- kontakt i info ---------- */
  $$('[data-k]').forEach(function (e) { var k = e.getAttribute('data-k'); e.textContent = k === 'cijena' ? eur(DOST.cijena) : k === 'pouzece' ? eur(DOST.pouzece) : k === 'od' ? DOST.besplatnoOd + ' €' : k === 'rok' ? DOST.rok : K[k] || ''; });
  $$('a[data-href]').forEach(function (a) { var k = a.getAttribute('data-href'); a.href = k === 'tel' ? 'tel:' + K.telLink : 'mailto:' + K.email; });
  var kf = $('#kontakt-form');
  if (kf) {
    var tema = qs.get('tema'); if (tema && kf.tema.querySelector('option[value="' + tema + '"]')) kf.tema.value = tema;
    kf.addEventListener('submit', function (e) {
      e.preventDefault();
      var bad = $$('[required]', kf).filter(function (i) { return i.type === 'checkbox' ? !i.checked : !i.value.trim() || (i.type === 'email' && !/^\S+@\S+\.\S+$/.test(i.value)); });
      $$('[required]', kf).forEach(function (i) { i.classList.toggle('bad', bad.indexOf(i) > -1); });
      if (bad.length) { $('#kerr').hidden = false; $('#kerr').textContent = 'Molimo ispunite označena polja.'; bad[0].focus(); return; }
      kf.outerHTML = '<div class="box"><div class="okmark">' + I.check + '</div><h2>Hvala na poruci!</h2><p>Javit ćemo vam se na ' + esc(kf.email.value) + ' u najkraćem roku.</p>' + (S.probno ? '<p class="note">Probna trgovina: poruka nije poslana.</p>' : '') + '</div>';
    });
  }

  refresh();
})();
