/* Domestic Spirits – trgovina (zaglavlje, košarica, stranice) */
(function () {
  'use strict';
  var D = window.DS, P = D.proizvodi, S = D.postavke;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var page = document.body.getAttribute('data-page');
  var qs = new URLSearchParams(location.search);

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
  var I = {
    search: '<svg class="i" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>',
    user: '<svg class="i" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4.5 20.5c1.2-4 4-6 7.5-6s6.3 2 7.5 6"/></svg>',
    bag: '<svg class="i" viewBox="0 0 24 24"><path d="M5 7.5h14l-1 13H6l-1-13Z"/><path d="M9 7.5V6a3 3 0 0 1 6 0v1.5"/></svg>',
    arrow: '<svg class="i" viewBox="0 0 24 24"><path d="M4 12h15m-5-5 5 5-5 5"/></svg>'
  };

  /* ---------- košarica (u pregledniku) ---------- */
  var cart = store('ds_cart') || {};
  function cartItems() { return Object.keys(cart).filter(byId).map(function (id) { return { p: byId(id), q: cart[id] }; }); }
  function cartCount() { return cartItems().reduce(function (s, i) { return s + i.q; }, 0); }
  function cartTotal() { return cartItems().reduce(function (s, i) { return s + i.q * i.p.cijena; }, 0); }
  function setQty(id, q) { q = Math.max(0, Math.min(99, q | 0)); if (q) cart[id] = q; else delete cart[id]; store('ds_cart', cart); refresh(); }
  function add(id, q) { setQty(id, (cart[id] || 0) + (q || 1)); var p = byId(id); toast((q || 1) + ' × ' + ime(p) + ' dodano u košaricu.'); }
  function refresh() {
    $$('.badge').forEach(function (b) { b.textContent = cartCount(); b.setAttribute('data-n', cartCount()); });
    renderDrawer();
    if (page === 'kosarica') renderCart();
  }

  /* ---------- zaglavlje i podnožje ---------- */
  var nav = [['vina', 'Vina'], ['rakije', 'Rakije'], ['likeri', 'Likeri'], ['poklon-paketi', 'Poklon paketi'], ['proizvodjaci', 'Proizvođači'], ['akcije', 'Akcije']];
  var kat = qs.get('kat');
  $('#ds-header').innerHTML =
    '<div class="topbar">Okusi Hrvatske, na jednom mjestu.</div>' +
    '<header class="wrap header"><a class="brand" href="index.html" aria-label="Domestic Spirits – početna"><img src="assets/img/logo.png" alt="Domestic Spirits" width="900" height="137"></a>' +
    '<form class="search" action="trgovina.html" role="search">' + I.search + '<input name="q" aria-label="Pretraži" placeholder="Pronađi vino, rakiju ili proizvođača" value="' + esc(qs.get('q') || '') + '"></form>' +
    '<div class="hicons"><a href="kosarica.html#podaci" aria-label="Moji podaci">' + I.user + '</a><button type="button" id="open-cart" aria-label="Košarica">' + I.bag + '<span class="badge" data-n="0">0</span></button></div></header>' +
    '<nav class="nav" aria-label="Glavni izbornik"><div class="wrap">' +
    nav.map(function (n) { return '<a href="trgovina.html?kat=' + n[0] + '"' + (kat === n[0] ? ' class="on"' : '') + '>' + n[1] + '</a>'; }).join('') +
    '<a class="horeca" href="kontakt.html?tema=ugostitelji">Za ugostitelje</a></div></nav>';
  $('#ds-footer').innerHTML =
    '<footer class="foot"><div class="wrap"><small>© ' + new Date().getFullYear() + ' ' + esc(S.tvrtka) + '</small>' +
    '<a class="brand" href="index.html" aria-label="Domestic Spirits"><img src="assets/img/logo-svijetli.png" alt="Domestic Spirits"></a>' +
    '<nav><a href="kontakt.html#dostava">Dostava i plaćanje</a><a href="kontakt.html">Kontakt</a><span>18+ · Uživajte odgovorno.</span></nav></div></footer>' +
    '<div class="drawer" id="drawer" aria-hidden="true"><div class="shade"></div><aside role="dialog" aria-label="Košarica"><button class="x" type="button" aria-label="Zatvori">×</button><div class="eyebrow">Vaš odabir</div><h2>Košarica</h2><div class="items"></div><div class="dfoot"></div></aside></div>' +
    '<dialog id="lightbox"><button class="x" type="button" aria-label="Zatvori">×</button><img alt=""><p></p></dialog>' +
    '<div class="toast" role="status" aria-live="polite"></div>';

  var tt;
  function toast(t) { var e = $('.toast'); e.textContent = t; e.classList.add('on'); clearTimeout(tt); tt = setTimeout(function () { e.classList.remove('on'); }, 2800); }

  function openDrawer() { $('#drawer').classList.add('open'); $('#drawer').setAttribute('aria-hidden', 'false'); }
  function closeDrawer() { $('#drawer').classList.remove('open'); $('#drawer').setAttribute('aria-hidden', 'true'); }
  $('#open-cart').addEventListener('click', function () { if (page === 'kosarica') return; openDrawer(); });
  $('#drawer .shade').addEventListener('click', closeDrawer);
  $('#drawer .x').addEventListener('click', closeDrawer);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeDrawer(); });
  function renderDrawer() {
    var it = cartItems();
    $('#drawer .items').innerHTML = it.length ? it.map(function (i) {
      return '<div class="ditem"><img src="' + i.p.slika + '" alt=""><div><b>' + esc(ime(i.p)) + '</b><small>' + l(i.p.zapremnina) + ' · ' + i.q + ' kom.</small></div><strong>' + eur(i.q * i.p.cijena) + '</strong></div>';
    }).join('') : '<p class="note">Košarica je prazna.</p>';
    $('#drawer .dfoot').innerHTML = it.length ? '<div class="tot big"><span>Ukupno</span><span>' + eur(cartTotal()) + '</span></div><a class="btn" style="width:100%;margin-top:12px" href="kosarica.html">Pregledaj i naruči</a>' : '<a class="btn ghost" style="width:100%" href="trgovina.html">Pogledaj ponudu</a>';
  }

  /* dodavanje iz kartica (bilo gdje na stranici) */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-add]'); if (!b) return;
    e.preventDefault(); add(b.getAttribute('data-add'), 1); openDrawer();
  });

  /* povećanje slike */
  function zoom(src, cap) { var d = $('#lightbox'); $('img', d).src = src; $('img', d).alt = cap || ''; $('p', d).textContent = cap || ''; d.showModal(); }
  $('#lightbox .x').addEventListener('click', function () { $('#lightbox').close(); });
  $('#lightbox').addEventListener('click', function (e) { if (e.target === this) this.close(); });

  /* provjera dobi */
  if (!store('ds_18')) {
    var g = document.createElement('dialog'); g.id = 'age';
    g.innerHTML = '<img src="assets/img/logo.png" alt="Domestic Spirits"><h2>Dobro došli</h2><p>Stranica sadrži alkoholna pića. Imate li najmanje 18 godina?</p><div class="btns"><button class="btn" value="da">Da, imam 18+</button><button class="btn ghost" value="ne">Nemam</button></div>';
    document.body.appendChild(g);
    g.addEventListener('cancel', function (e) { e.preventDefault(); });
    $$('button', g).forEach(function (b) { b.addEventListener('click', function () { if (b.value === 'da') { store('ds_18', 1); g.close(); } else { location.href = 'https://www.google.com/'; } }); });
    g.showModal();
  }

  /* ---------- kartice proizvoda ---------- */
  function card(p) {
    return '<article class="pcard"><a class="ph" href="' + url(p) + '"><img src="' + p.slika + '" alt="' + esc(ime(p)) + '" loading="lazy" width="900" height="900"></a>' +
      '<h3><a href="' + url(p) + '">' + esc(ime(p)) + '</a></h3><div class="meta">' + l(p.zapremnina) + '<i>·</i>' + esc(p.vrsta) + '</div>' +
      '<div class="row"><span class="price">' + eur(p.cijena) + '</span><button class="btn sm" data-add="' + p.id + '">U košaricu</button></div></article>';
  }
  function hcard(p) {
    return '<article class="pcard hcard"><a class="ph" href="' + url(p) + '"><img src="' + p.slika + '" alt="' + esc(ime(p)) + '" loading="lazy"></a><div>' +
      '<h3><a href="' + url(p) + '">' + esc(ime(p)) + '</a></h3><div class="meta">' + l(p.zapremnina) + '<i>·</i>' + esc(p.vrsta) + '</div>' +
      '<span class="price">' + eur(p.cijena) + '</span><button class="btn sm" data-add="' + p.id + '">U košaricu</button></div></article>';
  }

  /* ---------- naslovnica ---------- */
  if (page === 'naslovnica') {
    $('#cats').innerHTML = D.kategorije.map(function (k) {
      return '<a class="cat" href="trgovina.html?kat=' + k.id + '"><img src="' + k.slika + '" alt="" loading="lazy"><span>' + k.naziv + ' ' + I.arrow + '</span></a>';
    }).join('');
    $('#izdvojeno').innerHTML = P.filter(function (p) { return p.izdvojeno === 1; }).map(card).join('');
    $('#izdvojeno2').innerHTML = P.filter(function (p) { return p.izdvojeno === 2; }).map(hcard).join('');
  }

  /* ---------- trgovina (popis) ---------- */
  if (page === 'trgovina') {
    var q = (qs.get('q') || '').trim().toLowerCase(), marka_ = qs.get('marka'), naslov = 'Sva ponuda', list = P.slice();
    var norm = function (s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd'); };
    if (q) { naslov = 'Rezultati: „' + qs.get('q') + '”'; list = list.filter(function (p) { return norm([ime(p), p.vrsta, p.kategorija].join(' ')).indexOf(norm(q)) > -1; }); }
    else if (marka_) { list = list.filter(function (p) { return p.marka === marka_; }); naslov = (D.proizvodjaci.filter(function (m) { return m.id === marka_; })[0] || {}).naziv || naslov; }
    else if (kat === 'proizvodjaci') naslov = 'Proizvođači';
    else if (kat === 'akcije') { naslov = 'Akcije'; list = list.filter(function (p) { return p.akcija; }); }
    else if (kat) { var k = D.kategorije.filter(function (c) { return c.id === kat; })[0]; if (k) { naslov = k.naziv; list = list.filter(function (p) { return p.kategorija === kat || (p.takodjer || []).indexOf(kat) > -1; }); } }
    document.title = naslov + ' | Domestic Spirits';
    $('#crumb-now').textContent = naslov;
    $('#shop-title').textContent = naslov;
    $('#filters').innerHTML = '<a class="chip' + (!kat && !q && !marka_ ? ' on' : '') + '" href="trgovina.html">Sve</a>' +
      (kat === 'proizvodjaci' || marka_ ? D.proizvodjaci.map(function (m) { return '<a class="chip' + (marka_ === m.id ? ' on' : '') + '" href="trgovina.html?marka=' + m.id + '">' + m.naziv + '</a>'; })
        : D.kategorije.map(function (c) { return '<a class="chip' + (kat === c.id ? ' on' : '') + '" href="trgovina.html?kat=' + c.id + '">' + c.naziv + '</a>'; })).join('');
    var draw = function () {
      var s = $('#sort').value, l2 = list.slice();
      if (s === 'jeftinije') l2.sort(function (a, b) { return a.cijena - b.cijena; });
      if (s === 'skuplje') l2.sort(function (a, b) { return b.cijena - a.cijena; });
      if (s === 'naziv') l2.sort(function (a, b) { return ime(a).localeCompare(ime(b), 'hr'); });
      $('#list').innerHTML = l2.length ? l2.map(card).join('') : '';
      $('#empty').hidden = !!l2.length;
      $('#empty-text').textContent = kat === 'akcije' ? 'Trenutno nema proizvoda na akciji. Pogledajte cijelu ponudu.' : kat === 'poklon-paketi' ? 'Poklon paketi uskoro stižu. Do tada složite svoj poklon od boca iz ponude.' : 'Nema proizvoda za taj upit.';
    };
    if (kat === 'proizvodjaci' && !marka_) {
      $('#sort').hidden = true;
      $('#list').innerHTML = D.proizvodjaci.map(function (m) {
        var ps = P.filter(function (p) { return p.marka === m.id; });
        return '<article class="pcard"><a class="ph" href="trgovina.html?marka=' + m.id + '"><img src="' + ps[0].slika + '" alt="" loading="lazy"></a><h3><a href="trgovina.html?marka=' + m.id + '">' + m.naziv + '</a></h3><div class="meta">' + ps.length + ' ' + (ps.length === 1 ? 'proizvod' : ps.length < 5 ? 'proizvoda' : 'proizvoda') + '</div><div class="row" style="justify-content:center"><a class="link" href="trgovina.html?marka=' + m.id + '">Pogledaj ' + I.arrow + '</a></div></article>';
      }).join('');
    } else { $('#sort').addEventListener('change', draw); draw(); }
  }

  /* ---------- proizvod ---------- */
  var ICON = {
    casa: '<path d="M21 12h30l-3 24c-.7 7-5.3 12-12 12s-11.3-5-12-12l-3-24Z"/><path d="M24 27h24M36 48v13m-12 0h24"/>',
    kava: '<path d="M13 29h40v12a17 17 0 0 1-17 17h-6a17 17 0 0 1-17-17V29Z"/><path d="M53 31h4a8 8 0 0 1 0 16h-5M9 62h52M25 10c-7 6 7 8 0 14m13-14c-7 6 7 8 0 14"/>',
    kolac: '<path d="m14 29 34-14 12 19v24H14V29Zm0 0 46 5M14 43h46M9 63h55"/><path d="M31 29c-3-6 0-12 5-12s8 6 5 12M36 17c0-5 4-7 7-7"/>',
    tanjur: '<circle cx="37" cy="37" r="21"/><circle cx="37" cy="37" r="14"/><path d="M7 10v19m5-19v19M3 10v14c0 8 13 8 13 0V10M10 31v30m54-51v51m0-51c-7 9-7 18 0 21"/>',
    riba: '<path d="M14 35C26 16 45 16 60 35 45 54 26 54 14 35Zm0 0L4 23v24l10-12ZM35 20l7-9 4 14m-11 25 6 10 6-14"/><circle cx="49" cy="32" r="1.5"/>',
    sir: '<path d="m12 30 32-16 16 16v28H12V30Zm0 0h48M44 14v16"/><circle cx="25" cy="44" r="4"/><circle cx="47" cy="48" r="3"/><path d="M35 30v6"/>',
    koktel: '<path d="M12 21h48L36 46 12 21Zm24 25v16m-12 0h24M38 21l12-13h9M20 29h32"/><path d="M14 21a12 12 0 1 1 19-10"/>',
    medalja: '<circle cx="36" cy="44" r="17"/><circle cx="36" cy="44" r="11"/><path d="m25 30-9-20h14l6 12m11 8 9-20H42l-6 12"/>',
    temp: '<path d="M31 41V15a7 7 0 0 1 14 0v26a13 13 0 1 1-14 0Z"/><path d="M38 26v25m11-31h8m-8 10h5m-5 10h8"/><circle cx="38" cy="53" r="5"/>'
  };
  function svg(k) { return '<svg class="i" viewBox="0 0 72 72">' + ICON[k] + '</svg>'; }
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
    if (p.nagrade) tabs.push(['nagrade', 'Nagrade i priznanja']);
    tabs.push(['detalji', 'Podaci o proizvodu']);
    var podaci = p.podaci || [['Naziv', ime(p) + ' ' + l(p.zapremnina)], ['Vrsta', p.vrsta], ['Zapremnina boce', l(p.zapremnina)], ['Na tržište stavlja', S.tvrtka]];
    var h = '<div class="crumb"><a href="index.html">Početna</a><span>/</span>' + (kk ? '<a href="trgovina.html?kat=' + kk.id + '">' + kk.naziv + '</a><span>/</span>' : '') + '<span>' + esc(ime(p)) + '</span></div>' +
      '<section class="product"><div><div class="photo"><img id="main-img" src="' + p.slika + '" alt="' + esc(ime(p)) + '" width="900" height="900"><span class="photo-cap" id="cap">' + esc(gal[0].opis) + '</span></div>' +
      (gal.length > 1 ? '<div class="thumbs">' + gal.map(function (g, i) { return '<button type="button" class="thumb' + (i ? '' : ' on') + '" data-i="' + i + '"><img src="' + g.slika + '" alt=""><span>' + esc(g.naziv) + '</span></button>'; }).join('') + '</div>' : '') + '</div>' +
      '<div class="pinfo"><div class="eyebrow">' + esc(p.eyebrow || p.vrsta) + '</div><h1>' + esc(marka(p)) + '<span>' + esc(p.naziv) + '</span></h1>' +
      (p.kratko ? '<p class="intro">' + esc(p.kratko) + '</p>' : '') +
      '<div class="facts"><div><strong>' + l(p.zapremnina) + '</strong><span>Zapremnina</span></div>' + (p.alkohol ? '<div><strong>' + esc(p.alkohol) + '</strong><span>Alkohol</span></div>' : p.temperatura ? '<div><strong>' + esc(p.temperatura) + '</strong><span>Posluživanje</span></div>' : '') + '<div><strong>' + esc(p.podrijetlo || 'Hrvatska') + '</strong><span>Podrijetlo</span></div></div>' +
      (p.nagrade ? '<div class="awards">' + p.nagrade.map(function (n) { return '<a href="#t-nagrade" data-tab-go="nagrade">' + (n.slika ? '<img src="' + n.slika + '" alt="">' : '<span class="medal">' + svg('medalja') + '</span>') + '<span><b>' + esc(n.naslov) + '</b><small>' + esc(n.mjesto) + '</small></span></a>'; }).join('') + '</div>' : '') +
      '<div class="buy"><div><span class="price">' + eur(p.cijena) + '</span><span class="unit">' + eur(p.cijena / p.zapremnina) + '/l</span></div>' +
      (p.sidrena ? '<p class="anchor">' + esc(p.sidrena) + '</p>' : '<p class="anchor">PDV uključen.</p>') +
      '<div class="buyrow"><div class="qty"><button type="button" id="minus" aria-label="Manje">−</button><input id="qty" type="number" min="1" max="99" value="1" aria-label="Količina"><button type="button" id="plus" aria-label="Više">+</button></div><button class="btn" id="add">' + I.bag + 'Dodaj u košaricu</button></div>' +
      '<div class="plinks"><span>' + (p.pakiranje ? 'Pakiranje: ' + esc(p.pakiranje) : 'Dostava na kućnu adresu') + '</span><a href="kontakt.html#dostava">Dostava i plaćanje</a></div></div>' +
      (p.mini ? '<div class="serve-mini">' + p.mini.map(function (s) { return '<div><span>' + esc(s[0]) + '</span><b>' + esc(s[1]) + '</b></div>'; }).join('') + '<a href="#t-posluzivanje" data-tab-go="posluzivanje">Kako poslužiti →</a></div>' : '') +
      '</div></section>' +
      '<section id="info"><div class="tabs" role="tablist">' + tabs.map(function (t, i) { return '<button type="button" role="tab" id="t-' + t[0] + '" aria-selected="' + (i === 0) + '" data-tab="' + t[0] + '">' + t[1] + '</button>'; }).join('') + '</div>' +
      '<div class="panel" id="opis"><div class="cols"><div><div class="eyebrow">Domaća priča</div><h2>' + esc(p.naslovOpisa || ime(p)) + '</h2>' +
      (p.opis || [p.vrsta + ' iz ponude hrvatskih proizvođača, ' + l(p.zapremnina) + '.', 'Za dodatne informacije o proizvodu slobodno nam se javite.']).map(function (t) { return '<p>' + esc(t) + '</p>'; }).join('') + (p.dodatno ? '<p class="extra">' + esc(p.dodatno) + '</p>' : '') + '</div>' +
      (p.razlozi ? '<div class="reasons"><h3>' + esc(p.razloziNaslov || 'Zašto odabrati ovaj proizvod?') + '</h3><ul>' + p.razlozi.map(function (r) { return '<li>' + esc(r) + '</li>'; }).join('') + '</ul>' + (p.zakljucak ? '<p class="closing">' + esc(p.zakljucak) + '</p>' : '') + '</div>' : ukratko(p)) + '</div></div>' +
      (p.posluzivanje ? '<div class="panel" id="posluzivanje" hidden><div class="eyebrow">' + esc(marka(p)) + ' · Gastro preporuka</div><h2>' + esc(p.posluzivanjeNaslov || 'Kako poslužiti.') + '</h2><div class="serve-cards">' +
        p.posluzivanje.map(function (s, i) { return '<article class="serve-card"><div class="top">' + svg(s.ikona) + '<span class="num">0' + (i + 1) + '</span></div><h3>' + esc(s.naziv) + '</h3>' + (s.kolicina ? '<div class="dose">' + esc(s.kolicina) + '</div>' : '') + '<p>' + esc(s.opis) + '</p></article>'; }).join('') +
        '</div>' + (p.temperatura ? '<div class="serve-temp">' + svg('temp') + '<div><span>Temperatura serviranja</span><strong>' + esc(p.temperatura) + '</strong></div><p>Odaberite svoj način posluživanja.</p></div>' : '') + '</div>' : '') +
      (p.nagrade ? '<div class="panel" id="nagrade" hidden><div class="eyebrow">Izvorna priznanja</div><h2>Nagrađena kvaliteta.</h2><div class="award-list">' +
        p.nagrade.map(function (n) { return '<article>' + (n.slika ? '<button type="button" data-zoom="' + n.slika + '" data-cap="' + esc(n.naslov + ' · ' + n.mjesto) + '"><img src="' + n.slika + '" alt="' + esc(n.naslov) + '"></button>' : '<span class="medal big">' + svg('medalja') + '</span>') + '<div><h3>' + esc(n.naslov) + '</h3><p>' + esc(n.tekst) + '</p></div></article>'; }).join('') + '</div></div>' : '') +
      '<div class="panel" id="detalji" hidden><div class="eyebrow">Sve na jednom mjestu</div><h2>Podaci o proizvodu.</h2><table class="data"><tbody>' +
        podaci.map(function (r) { return '<tr><th>' + esc(r[0]) + '</th><td>' + esc(r[1]) + '</td></tr>'; }).join('') + '</tbody></table></div></section>' +
      '<section class="sec related"><div class="sec-head"><h2>Možda će vam se svidjeti</h2><a class="link" href="trgovina.html">Pogledaj sve ' + I.arrow + '</a></div><div class="grid4">' +
        P.filter(function (x) { return x.id !== p.id; }).sort(function (a, b) { return (b.kategorija === p.kategorija) - (a.kategorija === p.kategorija); }).slice(0, 4).map(card).join('') + '</div></section>';
    $('#product').innerHTML = h;

    var showTab = function (n, go) { $$('[data-tab]').forEach(function (b) { b.setAttribute('aria-selected', String(b.getAttribute('data-tab') === n)); }); $$('.panel').forEach(function (x) { x.hidden = x.id !== n; }); if (go) $('#info').scrollIntoView({ behavior: 'smooth' }); };
    $$('[data-tab]').forEach(function (b) { b.addEventListener('click', function () { showTab(b.getAttribute('data-tab')); }); });
    $$('[data-tab-go]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); showTab(a.getAttribute('data-tab-go'), true); }); });
    $$('.thumb').forEach(function (b) { b.addEventListener('click', function () { var g = gal[+b.getAttribute('data-i')]; $('#main-img').src = g.slika; $('#cap').textContent = g.opis; $$('.thumb').forEach(function (t) { t.classList.toggle('on', t === b); }); }); });
    $('#main-img').addEventListener('click', function () { zoom(this.src, $('#cap').textContent); });
    $$('[data-zoom]').forEach(function (b) { b.addEventListener('click', function () { zoom(b.getAttribute('data-zoom'), b.getAttribute('data-cap')); }); });
    var qv = function () { return Math.max(1, Math.min(99, parseInt($('#qty').value, 10) || 1)); };
    $('#minus').addEventListener('click', function () { $('#qty').value = Math.max(1, qv() - 1); });
    $('#plus').addEventListener('click', function () { $('#qty').value = Math.min(99, qv() + 1); });
    $('#add').addEventListener('click', function () { add(p.id, qv()); openDrawer(); });
  }

  /* ---------- košarica i narudžba ---------- */
  function shippingNote(t) {
    var od = S.besplatnaDostavaOd;
    if (t >= od) return '<div class="free">Ostvarili ste <b>besplatnu dostavu</b> na području ' + esc(S.besplatnaDostavaPodrucje) + '.<div class="bar"><i style="width:100%"></i></div></div>';
    return '<div class="free">Još <b>' + eur(od - t) + '</b> do besplatne dostave na području ' + esc(S.besplatnaDostavaPodrucje) + '.<div class="bar"><i style="width:' + Math.round(t / od * 100) + '%"></i></div></div>';
  }
  function renderCart() {
    var it = cartItems(), t = cartTotal();
    if (!it.length) { $('#cart-wrap').innerHTML = '<div class="empty"><h2>Košarica je prazna</h2><p>Odaberite nešto iz naše ponude.</p><a class="btn" href="trgovina.html">Pogledaj ponudu</a></div>'; return; }
    if (!$('#cart-form')) build();
    $('#lines').innerHTML = it.map(function (i) {
      return '<div class="citem" data-id="' + i.p.id + '"><a href="' + url(i.p) + '"><img src="' + i.p.slika + '" alt=""></a><div><h3><a href="' + url(i.p) + '">' + esc(ime(i.p)) + '</a></h3><div class="meta">' + l(i.p.zapremnina) + ' · ' + eur(i.p.cijena) + '</div><button type="button" class="rm">Ukloni</button></div>' +
        '<div class="qty"><button type="button" class="m" aria-label="Manje">−</button><input type="number" min="0" max="99" value="' + i.q + '" aria-label="Količina"><button type="button" class="p" aria-label="Više">+</button></div><div class="sum">' + eur(i.q * i.p.cijena) + '</div></div>';
    }).join('');
    $('#totals').innerHTML = '<div class="tot"><span>Proizvodi (' + cartCount() + ')</span><span>' + eur(t) + '</span></div><div class="tot"><span>Dostava</span><span>' + (t >= S.besplatnaDostavaOd ? 'Besplatno' : 'Prema cjeniku') + '</span></div><div class="tot big"><span>Ukupno</span><span>' + eur(t) + '</span></div><p class="note">Cijene uključuju PDV.</p>' + shippingNote(t);
  }
  function build() {
    $('#cart-wrap').innerHTML = '<div class="cart-page"><div><h1>Košarica</h1><div id="lines"></div>' +
      '<h2 class="serif" id="podaci" style="font-size:34px;margin:34px 0 12px">Podaci za dostavu</h2>' +
      '<form class="checkout" id="cart-form" novalidate>' +
      '<label>Ime<input name="ime" required autocomplete="given-name"></label><label>Prezime<input name="prezime" required autocomplete="family-name"></label>' +
      '<label>E-mail<input name="email" type="email" required autocomplete="email"></label><label>Mobitel<input name="tel" type="tel" required autocomplete="tel"></label>' +
      '<label class="full">Ulica i kućni broj<input name="adresa" required autocomplete="street-address"></label>' +
      '<label>Poštanski broj<input name="pbr" required inputmode="numeric" autocomplete="postal-code"></label><label>Mjesto<input name="mjesto" required autocomplete="address-level2"></label>' +
      '<label class="full">Napomena (nije obavezno)<textarea name="napomena"></textarea></label>' +
      '<div class="full pay"><span class="note">Način plaćanja</span><label><input type="radio" name="placanje" value="Uplata na račun" checked> Uplata na račun (podaci stižu e-mailom)</label><label><input type="radio" name="placanje" value="Pouzećem"> Plaćanje pouzećem</label></div>' +
      '<label class="chk full"><input type="checkbox" name="dob" required> Potvrđujem da imam najmanje 18 godina.</label>' +
      '<label class="chk full"><input type="checkbox" name="uvjeti" required> Prihvaćam uvjete kupnje i obradu podataka za isporuku narudžbe.</label>' +
      '<p class="note full" id="err" style="color:#a3243a" hidden></p></form></div>' +
      '<aside class="box" style="position:sticky;top:16px"><h2>Pregled narudžbe</h2><div id="totals"></div><button class="btn" form="cart-form" type="submit" style="width:100%;margin-top:6px">Potvrdi narudžbu</button>' +
      (S.probno ? '<p class="note" style="margin-top:10px">Probna trgovina: narudžba se ne šalje i ne naplaćuje.</p>' : '') + '</aside></div>';
    var saved = store('ds_kupac') || {};
    $$('#cart-form input:not([type=radio]):not([type=checkbox]), #cart-form textarea').forEach(function (i) { if (saved[i.name]) i.value = saved[i.name]; });
    $('#lines').addEventListener('click', function (e) {
      var row = e.target.closest('.citem'); if (!row) return; var id = row.getAttribute('data-id');
      if (e.target.closest('.rm')) setQty(id, 0); else if (e.target.closest('.m')) setQty(id, cart[id] - 1); else if (e.target.closest('.p')) setQty(id, cart[id] + 1);
    });
    $('#lines').addEventListener('change', function (e) { var row = e.target.closest('.citem'); if (row) setQty(row.getAttribute('data-id'), parseInt(e.target.value, 10) || 0); });
    $('#cart-form').addEventListener('submit', function (e) {
      e.preventDefault(); var f = this, bad = $$('[required]', f).filter(function (i) { return i.type === 'checkbox' ? !i.checked : !i.value.trim() || (i.type === 'email' && !/^\S+@\S+\.\S+$/.test(i.value)); });
      $$('[required]', f).forEach(function (i) { i.style.borderColor = bad.indexOf(i) > -1 ? '#a3243a' : ''; });
      if (bad.length) { $('#err').hidden = false; $('#err').textContent = 'Molimo ispunite označena polja i potvrdite oba okvira.'; bad[0].focus(); return; }
      var data = {}; new FormData(f).forEach(function (v, k) { data[k] = v; }); store('ds_kupac', data);
      var br = 'DS-' + new Date().getFullYear() + '-' + String(Date.now()).slice(-5), it = cartItems(), t = cartTotal();
      $('#cart-wrap').innerHTML = '<div class="done"><div class="eyebrow">Hvala!</div><h1>Narudžba je zaprimljena</h1><p>Broj narudžbe: <b>' + br + '</b>. Potvrdu ćemo poslati na <b>' + esc(data.email) + '</b>.</p>' +
        '<div class="box">' + it.map(function (i) { return '<div class="tot"><span>' + i.q + ' × ' + esc(ime(i.p)) + '</span><span>' + eur(i.q * i.p.cijena) + '</span></div>'; }).join('') +
        '<div class="tot big"><span>Ukupno</span><span>' + eur(t) + '</span></div><p class="note">Plaćanje: ' + esc(data.placanje) + '<br>Dostava: ' + esc(data.ime + ' ' + data.prezime + ', ' + data.adresa + ', ' + data.pbr + ' ' + data.mjesto) + '</p>' +
        (S.probno ? '<p class="note">Probna trgovina: narudžba nije poslana ni naplaćena.</p>' : '') + '</div><p style="margin-top:24px"><a class="btn" href="index.html">Natrag na naslovnicu</a></p></div>';
      cart = {}; store('ds_cart', cart); $$('.badge').forEach(function (b) { b.textContent = 0; b.setAttribute('data-n', 0); }); renderDrawer(); scrollTo(0, 0);
    });
  }

  refresh();
})();

/* kontakt */
(function () {
  var f = document.getElementById('kontakt-form'); if (!f) return;
  var t = new URLSearchParams(location.search).get('tema'); if (t && f.tema.querySelector('option[value="' + t + '"]')) f.tema.value = t;
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    var bad = Array.prototype.filter.call(f.querySelectorAll('[required]'), function (i) { return i.type === 'checkbox' ? !i.checked : !i.value.trim() || (i.type === 'email' && !/^\S+@\S+\.\S+$/.test(i.value)); });
    Array.prototype.forEach.call(f.querySelectorAll('[required]'), function (i) { i.style.borderColor = bad.indexOf(i) > -1 ? '#a3243a' : ''; });
    var err = document.getElementById('kerr');
    if (bad.length) { err.hidden = false; err.textContent = 'Molimo ispunite označena polja.'; bad[0].focus(); return; }
    f.outerHTML = '<div class="box"><h2>Hvala na poruci!</h2><p>Javit ćemo vam se na ' + f.email.value.replace(/[<>&"]/g, '') + ' u najkraćem roku.</p>' + (window.DS.postavke.probno ? '<p class="note">Probna trgovina: poruka nije poslana.</p>' : '') + '</div>';
  });
})();
