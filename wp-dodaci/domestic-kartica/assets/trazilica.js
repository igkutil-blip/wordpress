/* Domestic – prijedlozi pretrage dok se tipka (sličica, marka, naziv, cijena); strelice, Enter, Escape */
(function () {
  'use strict';
  if (!window.dskTrazi) return;
  var cache = {};
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  function spoji(inp) {
    if (inp.dataset.dsk) return;
    inp.dataset.dsk = '1';
    inp.setAttribute('autocomplete', 'off');
    var form = inp.closest('form'); if (!form) return;
    form.classList.add('dsk-trazi');
    var box = document.createElement('div');
    box.className = 'dsk-prijedlozi'; box.hidden = true;
    box.id = 'dsk-prijedlozi-' + Math.random().toString(36).slice(2, 8);
    box.setAttribute('role', 'listbox');
    inp.setAttribute('role', 'combobox'); inp.setAttribute('aria-controls', box.id); inp.setAttribute('aria-expanded', 'false');
    form.appendChild(box);
    var t, zadnji = '', sel = -1;

    function zatvori() { box.hidden = true; inp.setAttribute('aria-expanded', 'false'); sel = -1; }
    function oznaci(i) {
      var a = box.querySelectorAll('a.dsk-p'); if (!a.length) return;
      sel = (i + a.length) % a.length;
      a.forEach(function (x, k) { x.classList.toggle('on', k === sel); x.setAttribute('aria-selected', k === sel ? 'true' : 'false'); });
      inp.setAttribute('aria-activedescendant', a[sel].id);
    }
    function prikazi(q, d) {
      if (q !== inp.value.trim()) return;
      var h = '';
      if (!d.proizvodi.length) {
        h = '<div class="dsk-p-prazno">Nema proizvoda za „' + esc(q) + '”.</div>';
      } else {
        d.proizvodi.forEach(function (p, i) {
          h += '<a class="dsk-p' + (p.nema ? ' dsk-p-nema' : '') + '" id="' + box.id + '-' + i + '" role="option" href="' + esc(p.url) + '">'
            + '<span class="dsk-p-slika"><img src="' + esc(p.slika) + '" alt="" loading="lazy"></span>'
            + '<span class="dsk-p-tekst">' + (p.marka ? '<small>' + esc(p.marka) + '</small>' : '') + '<b>' + esc(p.naziv) + '</b></span>'
            + '<span class="dsk-p-cijena">' + (p.nema ? '<small>Rasprodano</small>' : esc(p.cijena)) + '</span></a>';
        });
        h += '<a class="dsk-p-sve" href="' + esc(d.sve) + '">Prikaži sve rezultate (' + d.ukupno + ') →</a>';
      }
      box.innerHTML = h; box.hidden = false; inp.setAttribute('aria-expanded', 'true'); sel = -1;
    }
    function trazi() {
      var q = inp.value.trim();
      if (q.length < 2) { zatvori(); zadnji = q; return; }
      if (q === zadnji && !box.hidden) return;
      zadnji = q;
      if (cache[q]) { prikazi(q, cache[q]); return; }
      box.classList.add('ucitava');
      fetch(dskTrazi.url + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) { cache[q] = d; box.classList.remove('ucitava'); prikazi(q, d); })
        .catch(function () { box.classList.remove('ucitava'); });
    }
    inp.addEventListener('input', function () { clearTimeout(t); t = setTimeout(trazi, 180); });
    inp.addEventListener('focus', function () { if (inp.value.trim().length >= 2 && box.innerHTML) { box.hidden = false; inp.setAttribute('aria-expanded', 'true'); } });
    inp.addEventListener('keydown', function (e) {
      if (box.hidden) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); oznaci(sel + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); oznaci(sel - 1); }
      else if (e.key === 'Escape') { zatvori(); }
      else if (e.key === 'Enter' && sel >= 0) { e.preventDefault(); box.querySelectorAll('a.dsk-p')[sel].click(); }
    });
    document.addEventListener('click', function (e) { if (!form.contains(e.target)) zatvori(); });
  }

  function sve() { document.querySelectorAll('form.searchform input[name="s"], form.woocommerce-product-search input[name="s"]').forEach(spoji); }
  sve();
  document.addEventListener('focusin', function (e) { if (e.target.matches && e.target.matches('input[name="s"]')) spoji(e.target); });
})();
