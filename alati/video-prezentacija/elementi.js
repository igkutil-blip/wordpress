const { chromium } = require('playwright');
const O = '../video/snimke/', N = process.env.DS_PROBA_URL;
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell' });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 });
  const p = await ctx.newPage();
  const css = '#cmplz-cookiebanner-container,.cmplz-cookiebanner,.cmplz-manage-consent,.dsp-sticky{display:none!important}';
  for (const [put, sel, ime] of [['/proizvod/san-montano-teranino/', '#dsp-posluzivanje', 'el-posl'], ['/proizvod/strava-glava-075l/', '.dsp-buybox', 'el-kupnja'], ['/proizvod/san-montano-smokva/', '#dsp-posluzivanje', 'el-posl2'], ['/proizvod/promex-brusnica/', '#dsp-posluzivanje', 'el-posl3']]) {
    await p.goto(N + put + '?x=' + Date.now(), { waitUntil: 'networkidle', timeout: 120000 });
    await p.addStyleTag({ content: css });
    let e = await p.$(sel);
    if (!e) { const ids = await p.$$eval('section,details,[id]', a => a.filter(x => /posl/i.test(x.id || x.className)).map(x => x.tagName + '#' + x.id + '.' + x.className).slice(0, 10)); console.log('nema', sel, ids); continue; }
    await e.scrollIntoViewIfNeeded(); await p.waitForTimeout(1500);
    await e.screenshot({ path: O + ime + '.png' }); console.log('ok', ime);
  }
  await b.close();
})();
