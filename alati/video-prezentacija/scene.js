// Scene videa „Likeri i rakije – prije i sada” (1920×1080), HTML → PNG
const { chromium } = require('playwright');
const fs = require('fs');
const V = '/tmp/claude-0/-home-user-wordpress/e67ebf1c-c702-5261-b108-342a29a28be8/scratchpad/video/';
const f = (p) => 'file://' + V + p;

const CSS = `
@font-face{font-family:Marcellus;src:url(${f('fontovi/marcellus-latin-ext-400-normal.woff2')}) format('woff2');unicode-range:U+0100-024F,U+1E00-1EFF}
@font-face{font-family:Marcellus;src:url(${f('fontovi/marcellus-latin-400-normal.woff2')}) format('woff2')}
@font-face{font-family:Jost;font-weight:400;src:url(${f('fontovi/jost-latin-ext-400-normal.woff2')}) format('woff2');unicode-range:U+0100-024F,U+1E00-1EFF}
@font-face{font-family:Jost;font-weight:400;src:url(${f('fontovi/jost-latin-400-normal.woff2')}) format('woff2')}
@font-face{font-family:Jost;font-weight:500;src:url(${f('fontovi/jost-latin-ext-500-normal.woff2')}) format('woff2');unicode-range:U+0100-024F,U+1E00-1EFF}
@font-face{font-family:Jost;font-weight:500;src:url(${f('fontovi/jost-latin-500-normal.woff2')}) format('woff2')}
*{box-sizing:border-box;margin:0;padding:0}
html,body{width:1920px;height:1080px;overflow:hidden}
body{background:#F7F3EB;color:#29231F;font-family:Jost,sans-serif;position:relative}
.okvir{position:absolute;inset:0;padding:64px 96px}
.nad{font:500 20px Jost;letter-spacing:.22em;text-transform:uppercase;color:#AD8D53}
h1{font:400 58px/1.1 Marcellus,serif;letter-spacing:.04em;text-transform:uppercase;margin-top:12px}
h2{font:400 46px/1.15 Marcellus,serif;letter-spacing:.04em;text-transform:uppercase;margin-top:10px}
.crta{width:84px;height:3px;background:#AD8D53;margin:22px 0}
.oznaka{display:inline-block;font:500 18px Jost;letter-spacing:.2em;text-transform:uppercase;padding:8px 18px;border-radius:999px}
.prije{background:#e4ddd2;color:#5c534b}.sada{background:#702833;color:#F7F3EB}
.ekran{border-radius:14px;overflow:hidden;box-shadow:0 18px 50px rgba(41,35,31,.18);border:1px solid #e2d6c3;background:#fff}
.ekran img{display:block;width:100%}
.opis{font:400 24px/1.45 Jost;color:#5c534b}
.opis b{font-weight:500;color:#29231F}
.tocke{list-style:none;font:400 23px/1.5 Jost;color:#3d3530}
.tocke li{padding-left:30px;position:relative;margin:6px 0}
.tocke li:before{content:'';position:absolute;left:4px;top:13px;width:10px;height:10px;border-radius:50%;background:#AD8D53}
`;

function stranica(sadrzaj, extra = '') {
  return `<!doctype html><html lang="hr"><head><meta charset="utf-8"><style>${CSS}${extra}</style></head><body>${sadrzaj}</body></html>`;
}

const split = (nad, naslov, stara, nova, lijevo, desno) => stranica(`
<div class="okvir">
  <div class="nad">${nad}</div><h2>${naslov}</h2>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:56px;margin-top:34px">
    <div><span class="oznaka prije">Prije</span><div class="ekran" style="margin-top:18px"><img src="${f(stara)}"></div><ul class="tocke" style="margin-top:22px">${lijevo.map((x) => `<li>${x}</li>`).join('')}</ul></div>
    <div><span class="oznaka sada">Sada</span><div class="ekran" style="margin-top:18px"><img src="${f(nova)}"></div><ul class="tocke" style="margin-top:22px">${desno.map((x) => `<li>${x}</li>`).join('')}</ul></div>
  </div>
</div>`);

const parovi = (ids, nazivi) => stranica(`
<div class="okvir">
  <div class="nad">Fotografije proizvoda</div><h2>Svaki proizvod u svom ambijentu</h2>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:40px;margin-top:40px">
  ${ids.map((id, i) => `<div style="text-align:center">
     <div style="display:flex;align-items:center;gap:16px;justify-content:center">
       <div style="width:150px;height:150px;background:#fff;border:1px solid #e2d6c3;border-radius:10px;display:flex;align-items:center;justify-content:center;overflow:hidden"><img src="${f('par/' + id + '-stara.' + (fs.existsSync(V + 'par/' + id + '-stara.png') ? 'png' : 'jpg'))}" style="max-width:92%;max-height:92%"></div>
       <div style="font:400 40px Jost;color:#AD8D53">→</div>
     </div>
     <div class="ekran" style="margin-top:20px;width:100%;aspect-ratio:1"><img src="${f('par/' + id + '-nova.' + (fs.existsSync(V + 'par/' + id + '-nova.png') ? 'png' : 'jpg'))}" style="width:100%;height:100%;object-fit:cover"></div>
     <div style="font:400 24px Marcellus;margin-top:16px">${nazivi[i]}</div>
   </div>`).join('')}
  </div>
  <div class="opis" style="margin-top:26px;text-align:center">Nove fotografije postavljene su <b>na iste adrese</b> kao stare – sve poveznice i Google rezultati ostaju.</div>
</div>`);

const telefoni = stranica(`
<div class="okvir">
  <div class="nad">Mobitel</div><h2>Trgovina u džepu</h2>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:80px;margin-top:30px">
  ${[['Prije', 'prije', 'kat-mob-stara', 'ter-mob-stara'], ['Sada', 'sada', 'kat-mob-nova', 'ter-mob-nova']].map(([t, k, a, b]) => `
    <div><span class="oznaka ${k}">${t}</span>
      <div style="display:flex;gap:34px;margin-top:20px;justify-content:center">
        ${[a, b].map((x) => `<div style="width:318px;height:688px;border-radius:44px;background:#29231F;padding:12px;box-shadow:0 18px 50px rgba(41,35,31,.25)"><div style="width:100%;height:100%;border-radius:33px;overflow:hidden;background:#fff"><img src="${f('snimke/' + x + '.png')}" style="width:100%;display:block"></div></div>`).join('')}
      </div></div>`).join('')}
  </div>
</div>`);

const posluzivanje = stranica(`
<div class="okvir" style="display:grid;grid-template-columns:560px 1fr;gap:70px;align-items:center">
  <div><div class="nad">Na svakoj stranici proizvoda</div><h1>Kako poslužiti</h1><div class="crta"></div>
    <ul class="tocke"><li>Tri načina posluživanja s količinom po porciji</li><li>Originalne ilustracije izrezane s kartice svakog proizvoda</li><li>Temperatura serviranja iz podataka proizvoda</li><li><b>56 proizvoda</b> – likeri, rakije, gin i votka</li></ul></div>
  <div class="ekran" style="background:#F7F3EB;padding:30px"><img src="${f('snimke/el-posl2.png')}"></div>
</div>`);

const zapremnine = stranica(`
<div class="okvir" style="display:grid;grid-template-columns:600px 1fr;gap:80px;align-items:center">
  <div><div class="nad">STRAVA glava</div><h1>Sve zapremnine na jednom mjestu</h1><div class="crta"></div>
    <ul class="tocke"><li>0,10 l · 0,38 l · 0,75 l povezani na stranici proizvoda</li><li>Zapremnine koje se ne prodaju označene su kao rasprodane</li><li>Cijena po litri točna i za pakiranja 4 × 0,05 l</li></ul></div>
  <div class="ekran" style="background:#efe7dc;padding:10px"><img src="${f('snimke/el-kupnja.png')}"></div>
</div>`);

const glava = stranica(`
<div class="okvir">
  <div class="nad">STRAVA glava · usklađene boje</div><h2>Svaka zapremnina – svoja fotografija</h2>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:40px;margin-top:40px">
  ${[['4124', '0,75 l'], ['4125', '0,38 l'], ['4126', '0,10 l']].map(([id, z]) => `<div><div class="ekran" style="aspect-ratio:1"><img src="${f('par/' + id + '-nova.png')}" style="width:100%;height:100%;object-fit:cover"></div><div style="font:400 30px Marcellus;margin-top:16px;text-align:center">${z}</div></div>`).join('')}
  </div>
</div>`);

const dijeljenje = stranica(`
<div class="okvir" style="display:grid;grid-template-columns:640px 1fr;gap:90px;align-items:center">
  <div><div class="nad">Dijeljenje poveznica</div><h1>Kategorija s vlastitom slikom</h1><div class="crta"></div>
    <div class="opis">Kad se poveznica na <b>Likere i rakije</b> pošalje porukom ili objavi na društvenim mrežama, prikazuje se nova fotografija – na samoj stranici ništa se ne mijenja.</div></div>
  <div style="background:#e9e2d6;border-radius:28px;padding:44px;max-width:700px">
    <div style="background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 30px rgba(41,35,31,.15)">
      <img src="${f('kat-og.jpg')}" style="display:block;width:100%;height:330px;object-fit:cover;object-position:50% 55%">
      <div style="padding:20px 24px 24px"><div style="font:400 18px Jost;color:#8a8178">domesticspirits.hr</div><div style="font:500 26px Jost;margin-top:4px">Likeri i rakije – Domestic Spirits</div></div>
    </div>
    <div style="font:400 22px Jost;color:#29231F;background:#fff;display:inline-block;padding:12px 20px;border-radius:16px;margin-top:18px">Pogledaj našu ponudu likera i rakija 🥃</div>
  </div>
</div>`);

const brojke = stranica(`
<div class="okvir" style="text-align:center;padding-top:110px">
  <div class="nad">Likeri i rakije · danas</div><h1>Što je napravljeno</h1>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:34px;margin-top:70px">
  ${[['56', 'proizvoda s novim fotografijama i karticom „Kako poslužiti”'], ['109', 'novih fotografija – studijske i kartice posluživanja'], ['90', 'ilustracija posluživanja izrezanih s kartica'], ['0', 'promijenjenih adresa – SEO ostaje netaknut']].map(([n, t]) => `<div style="background:#efe7dc;border:1px solid #e2d6c3;border-radius:18px;padding:46px 30px"><div style="font:400 96px Marcellus;color:#702833">${n}</div><div class="opis" style="margin-top:14px">${t}</div></div>`).join('')}
  </div>
  <div class="opis" style="margin-top:56px">+ popis pitanja za provjeru podataka (PDF i Word) · slika kategorije za dijeljenje</div>
</div>`);

const naslov = stranica(`
<div class="okvir" style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center">
  <img src="${f('logo.png')}" style="width:560px">
  <div class="crta" style="margin:40px auto"></div>
  <div class="nad">Novi web · pregled radova</div>
  <h1 style="font-size:84px;margin-top:20px">Likeri i rakije</h1>
  <div class="opis" style="margin-top:22px;font-size:28px">Prije i sada · 23. listopada 2026.</div>
</div>`);

const kraj = stranica(`
<div class="okvir" style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center">
  <img src="${f('logo.png')}" style="width:520px">
  <div class="crta" style="margin:40px auto"></div>
  <div style="font:400 44px Marcellus;letter-spacing:.04em;text-transform:uppercase">Domaći okusi, novi izlog</div>
  <div class="opis" style="margin-top:20px">Sve izmjene su na probnoj stranici – objava nakon vaše potvrde</div>
</div>`);

const scene = {
  '01-naslov': naslov,
  '02-kategorija': split('Kategorija', 'Likeri i rakije', 'snimke/kat-stara.png', 'snimke/kat-nova.png',
    ['Bočni izbornik, bijele kartice', 'Fotografije na bijeloj podlozi'],
    ['Filteri u jednoj traci: kategorija, cijena, poredak', 'Ambijentalne fotografije, četiri u redu']),
  '03-proizvod': split('Stranica proizvoda', 'San MONTANO Teranino', 'snimke/ter-stara.png', 'snimke/ter-nova.png',
    ['Podaci kao dugi tekst', 'Jedna fotografija'],
    ['Cijena i košarica odmah na prvom ekranu', 'Galerija: ambijent · studio · kako poslužiti']),
  '05-foto1': parovi(['732', '4276', '4712', '1976'], ['San MONTANO Teranino', 'AVELLANA', 'STRAVA 1 l', 'TERROAR Dunja']),
  '06-foto2': parovi(['1114', '4063', '4124', '1981'], ['Promex Pelin', 'BOGATI Medica', 'STRAVA glava', 'TERROAR Viljamovka']),
  '07-posluzivanje': posluzivanje,
  '08-zapremnine': zapremnine,
  '09-glava': glava,
  '10-mobitel': telefoni,
  '11-dijeljenje': dijeljenje,
  '12-brojke': brojke,
  '13-kraj': kraj,
};

(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell' });
  const p = await b.newPage({ viewport: { width: 1920, height: 1080 } });
  for (const [ime, html] of Object.entries(scene)) {
    fs.writeFileSync(V + 'scene/' + ime + '.html', html);
    await p.goto(f('scene/' + ime + '.html'));
    await p.evaluate(() => document.fonts.ready);
    await p.waitForTimeout(400);
    await p.screenshot({ path: V + 'scene/' + ime + '.png' });
    console.log('ok', ime);
  }
  // natpis za scenu listanja (prozirna pozadina)
  fs.writeFileSync(V + 'scene/natpis.html', stranica(`<div style="position:absolute;left:96px;bottom:70px;background:rgba(41,35,31,.88);color:#F7F3EB;padding:26px 36px;border-radius:16px;max-width:900px"><div style="font:500 18px Jost;letter-spacing:.22em;text-transform:uppercase;color:#d8bf8c">Nova stranica proizvoda</div><div style="font:400 36px Marcellus;margin-top:8px;letter-spacing:.03em">Sve važno na jednom mjestu</div><div style="font:400 22px Jost;margin-top:8px;color:#e6dccb">Opis · podaci · kako poslužiti · dostava i plaćanje</div></div>`, 'html,body{background:transparent!important}'));
  await p.goto(f('scene/natpis.html')); await p.evaluate(() => document.fonts.ready); await p.waitForTimeout(300);
  await p.screenshot({ path: V + 'scene/natpis.png', omitBackground: true });
  await b.close();
})();
