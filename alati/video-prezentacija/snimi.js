const { chromium } = require('playwright');
const O = '../video/snimke/';
const STARA = 'https://domesticspirits.hr', NOVA = process.env.DS_PROBA_URL;
async function ocisti(p) {
  for (const s of ['.age-gate__submit--yes', 'button.age-gate__submit--yes', '.cmplz-btn.cmplz-accept', '.cmplz-accept']) {
    try { const e = await p.$(s); if (e && await e.isVisible()) { await e.click(); await p.waitForTimeout(700); } } catch (e) {}
  }
  await p.addStyleTag({ content: '.age-gate,.age-gate-wrapper,#cmplz-cookiebanner-container,.cmplz-cookiebanner,.cmplz-manage-consent,#proba-traka,.domestic-pomocnik-traka{display:none!important} html{margin-top:0!important}' });
}
async function lijeno(p) {
  await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); } window.scrollTo(0, 0); });
  await p.waitForTimeout(1200);
}
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell' });
  const posao = [
    ['kat', '/kategorija-proizvoda/jaka-alkoholna-pica/', 1440, 900, false],
    ['ter', '/proizvod/san-montano-teranino/', 1440, 900, false],
    ['ter-mob', '/proizvod/san-montano-teranino/', 390, 844, false],
    ['kat-mob', '/kategorija-proizvoda/jaka-alkoholna-pica/', 390, 844, false],
    ['glava', '/proizvod/strava-glava-075l/', 1440, 900, false],
  ];
  for (const [ime, put, w, h] of posao) {
    for (const [str, baza] of [['stara', STARA], ['nova', NOVA]]) {
      const ctx = await b.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: w < 500 ? 2 : 1.3333, isMobile: w < 500, hasTouch: w < 500 });
      const p = await ctx.newPage();
      try {
        await p.goto(baza + put + (str === 'nova' ? '?x=' + Date.now() : ''), { waitUntil: 'networkidle', timeout: 120000 });
        await ocisti(p); await lijeno(p); await ocisti(p);
        await p.screenshot({ path: O + ime + '-' + str + '.png' });
        if (str === 'nova' && (ime === 'ter' || ime === 'glava')) await p.screenshot({ path: O + ime + '-' + str + '-cijela.png', fullPage: true });
        console.log('ok', ime, str);
      } catch (e) { console.log('GREŠKA', ime, str, e.message.slice(0, 120)); }
      await ctx.close();
    }
  }
  await b.close();
})();
