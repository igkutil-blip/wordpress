/* Domestic – stranica proizvoda: galerija, kartice, povećanje slike, traka „U košaricu” */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var root = $('.dsp'); if (!root) return;

  /* galerija */
  var main = $('.dsp-main');
  $$('.dsp-thumb').forEach(function (b) {
    b.addEventListener('click', function () {
      if (!main) return;
      main.removeAttribute('srcset'); main.src = b.getAttribute('data-src'); main.setAttribute('data-full', b.getAttribute('data-full'));
      $$('.dsp-thumb').forEach(function (t) { t.classList.toggle('on', t === b); });
    });
  });

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
  if (main) main.addEventListener('click', function () { zoom(main.getAttribute('data-full') || main.src); });
  var zb = $('.dsp-zoom'); if (zb && main) zb.addEventListener('click', function () { zoom(main.getAttribute('data-full') || main.src); });
  $$('.dsp-zoomable').forEach(function (i) { i.addEventListener('click', function () { zoom(i.getAttribute('data-full') || i.src); }); });

  /* kartice */
  function show(name, go) {
    var tab = $('[role=tab][data-dsp-tab="' + name + '"]'); if (!tab) return;
    $$('[role=tab]').forEach(function (b) { b.setAttribute('aria-selected', String(b === tab)); });
    $$('.dsp-panel').forEach(function (p) { p.hidden = p.id !== 'dsp-' + name; });
    if (go) $('#dsp-info').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  $$('[data-dsp-tab]').forEach(function (b) {
    b.addEventListener('click', function (e) { e.preventDefault(); show(b.getAttribute('data-dsp-tab'), b.getAttribute('role') !== 'tab'); });
  });
  $$('[role=tab]').forEach(function (b, i, all) {
    b.addEventListener('keydown', function (e) {
      var n = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : null; if (n === null) return;
      var t = all[(n + all.length) % all.length]; t.focus(); show(t.getAttribute('data-dsp-tab'));
    });
  });
  if (/^#(tab-reviews|reviews|comment-\d+|dsp-recenzije)$/.test(location.hash)) show('recenzije');

  /* traka „U košaricu” kad glavni gumb nije vidljiv */
  var st = $('.dsp-sticky'), btn = $('.dsp-cart .single_add_to_cart_button');
  if (st && btn && 'IntersectionObserver' in window) {
    new IntersectionObserver(function (en) {
      var on = !en[0].isIntersecting && en[0].boundingClientRect.top < 0;
      st.classList.toggle('on', on); st.setAttribute('aria-hidden', String(!on)); $('button', st).tabIndex = on ? 0 : -1;
    }).observe(btn);
    $('button', st).addEventListener('click', function () {
      if (btn.classList.contains('disabled') || btn.disabled) { btn.scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
      btn.click();
    });
  }
})();
