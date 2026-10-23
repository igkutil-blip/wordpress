/* Domestic – stranica proizvoda: klizač slika s oznakama, povećanje, količina −/+, sekcije, traka „Dodaj u košaricu” */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var root = $('.dsp'); if (!root) return;
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* klizač */
  var track = $('.dsp-track'), slides = $$('.dsp-slide'), cur = 0;
  function go(i) {
    if (!track || !slides.length) return;
    i = (i + slides.length) % slides.length;
    track.scrollTo({ left: i * track.clientWidth, behavior: reduce ? 'auto' : 'smooth' });
  }
  var strip = $('.dsp-thumbs');
  function mark(i) {
    cur = i;
    var n = $('.dsp-count-n');
    if (n) n.textContent = String(i + 1);
    $$('.dsp-thumb').forEach(function (t, k) {
      t.classList.toggle('on', k === i); t.setAttribute('aria-current', k === i ? 'true' : 'false');
      if (k === i && strip) {
        var l = t.offsetLeft - strip.offsetLeft, r = l + t.offsetWidth;
        if (l < strip.scrollLeft || r > strip.scrollLeft + strip.clientWidth) strip.scrollTo({ left: l - 4, behavior: reduce ? 'auto' : 'smooth' });
      }
    });
  }
  /* traka sličica: strelice listaju po tri */
  var sp = $('.dsp-strip-prev'), sn = $('.dsp-strip-next');
  function stripBtns() {
    if (!strip || !sp) return;
    sp.hidden = strip.scrollLeft < 4;
    sn.hidden = strip.scrollLeft + strip.clientWidth >= strip.scrollWidth - 4;
  }
  if (strip && sp) {
    sp.addEventListener('click', function () { strip.scrollBy({ left: -strip.clientWidth, behavior: reduce ? 'auto' : 'smooth' }); });
    sn.addEventListener('click', function () { strip.scrollBy({ left: strip.clientWidth, behavior: reduce ? 'auto' : 'smooth' }); });
    strip.addEventListener('scroll', stripBtns, { passive: true }); window.addEventListener('resize', stripBtns); stripBtns();
  }
  if (track) {
    var tm;
    track.addEventListener('scroll', function () {
      clearTimeout(tm);
      tm = setTimeout(function () { var i = Math.round(track.scrollLeft / track.clientWidth); if (i !== cur && slides[i]) mark(i); }, 60);
    }, { passive: true });
    track.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight') { e.preventDefault(); go(cur + 1); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); go(cur - 1); }
    });
    window.addEventListener('resize', function () { track.scrollLeft = cur * track.clientWidth; });
  }
  var pv = $('.dsp-prev'), nx = $('.dsp-next');
  if (pv) pv.addEventListener('click', function () { go(cur - 1); });
  if (nx) nx.addEventListener('click', function () { go(cur + 1); });
  $$('.dsp-thumb').forEach(function (t) { t.addEventListener('click', function () { var i = +t.getAttribute('data-i'); mark(i); go(i); }); });

  /* povećanje */
  var dlg;
  function zoom(src) {
    if (!dlg) {
      dlg = document.createElement('dialog'); dlg.className = 'dsp-lightbox';
      dlg.innerHTML = '<button type="button" class="dsp-x" aria-label="Zatvori">×</button><img alt="">';
      document.body.appendChild(dlg);
      dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('.dsp-x')) dlg.close(); });
    }
    $('img', dlg).src = src; dlg.showModal();
  }
  function curImg() { var im = slides[cur] && $('img', slides[cur]); return im ? (im.getAttribute('data-full') || im.currentSrc || im.src) : ''; }
  slides.forEach(function (s) { var im = $('img', s); if (im) im.addEventListener('click', function () { zoom(curImg()); }); });
  var zb = $('.dsp-zoom'); if (zb) zb.addEventListener('click', function () { var s = curImg(); if (s) zoom(s); });
  $$('.dsp-zoomable').forEach(function (i) { i.addEventListener('click', function () { zoom(i.getAttribute('data-full') || i.src); }); });

  /* količina −/+ */
  $$('.dsp-cart .quantity').forEach(function (q) {
    var inp = $('input.qty', q); if (!inp || inp.type === 'hidden' || $('.dsp-step', q)) return;
    function step(d) {
      var min = parseFloat(inp.min) || 1, max = parseFloat(inp.max) || Infinity, st = parseFloat(inp.step) || 1;
      var v = Math.min(max, Math.max(min, (parseFloat(inp.value) || min) + d * st));
      inp.value = v; inp.dispatchEvent(new Event('change', { bubbles: true })); upd();
    }
    var m = document.createElement('button'), p = document.createElement('button');
    m.type = p.type = 'button'; m.className = p.className = 'dsp-step';
    m.textContent = '−'; p.textContent = '+'; m.setAttribute('aria-label', 'Manje'); p.setAttribute('aria-label', 'Više');
    q.insertBefore(m, inp); q.appendChild(p);
    function upd() { var min = parseFloat(inp.min) || 1, max = parseFloat(inp.max) || Infinity, v = parseFloat(inp.value) || min; m.disabled = v <= min; p.disabled = v >= max; }
    m.addEventListener('click', function () { step(-1); }); p.addEventListener('click', function () { step(1); });
    inp.addEventListener('input', upd); upd();
  });

  /* sekcije: poveznice otvaraju sekciju */
  function open(name) {
    var d = $('#dsp-' + name); if (!d) return;
    d.open = true;
    d.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
  }
  $$('[data-dsp-open]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); open(a.getAttribute('data-dsp-open')); }); });
  if (/^#(tab-reviews|reviews|comment-\d+|dsp-recenzije)$/.test(location.hash)) open('recenzije');
  /* na mobitelu otvoren samo opis */
  if (window.matchMedia && matchMedia('(max-width: 760px)').matches) $$('.dsp-sec').forEach(function (d) { d.open = d.id === 'dsp-opis'; });
  if (/^#dsp-[a-z]+$/.test(location.hash)) open(location.hash.slice(5));

  /* traka na dnu kad glavni gumb nije vidljiv */
  var st = $('.dsp-sticky'), btn = $('.dsp-cart .single_add_to_cart_button');
  if (st && btn && 'IntersectionObserver' in window) {
    new IntersectionObserver(function (en) {
      var on = !en[0].isIntersecting;
      st.classList.toggle('on', on); st.setAttribute('aria-hidden', String(!on)); $('button', st).tabIndex = on ? 0 : -1;
    }).observe(btn);
    $('button', st).addEventListener('click', function () {
      if (btn.classList.contains('disabled') || btn.disabled) { btn.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' }); return; }
      btn.click();
    });
  }
})();
