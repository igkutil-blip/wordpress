const fs = require('fs');
const path = require('path');
const {
  Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell, WidthType, ShadingType,
  AlignmentType, BorderStyle, Header, Footer, PageNumber, HeightRule, TableLayoutType,
} = require('docx');

const D = __dirname;
const c = JSON.parse(fs.readFileSync(path.join(D, 'sadrzaj.json'), 'utf8'));
const izlaz = process.argv[2];

const BORDO = '702833', ZLATNA = 'AD8D53', TAMNA = '29231F', KREM = 'F7F3EB', CRTA = 'D9CEBC', SIVA = '6F6760';
const SERIF = 'Georgia', SANS = 'Calibri';
const SIRINA = 9638;
const STUPCI = [2700, 4000, 2938];

const rub = { style: BorderStyle.SINGLE, size: 4, color: CRTA };
const rubovi = { top: rub, bottom: rub, left: rub, right: rub };

const t = (text, o = {}) => new TextRun({ text, font: o.font || SANS, size: o.size || 20, bold: o.bold, italics: o.italics, color: o.color || TAMNA });
const p = (runs, o = {}) => new Paragraph({ children: Array.isArray(runs) ? runs : [runs], spacing: { before: o.before || 0, after: o.after ?? 80, line: o.line || 264 }, alignment: o.align, keepNext: o.keepNext, border: o.border });

function celija(sadrzaj, sirina, o = {}) {
  return new TableCell({
    width: { size: sirina, type: WidthType.DXA },
    borders: rubovi,
    shading: o.fill ? { type: ShadingType.CLEAR, color: 'auto', fill: o.fill } : undefined,
    margins: { top: 90, bottom: 90, left: 120, right: 120 },
    children: sadrzaj.length ? sadrzaj : [p(t(''))],
  });
}

function zaglavljeTablice(nazivi) {
  return new TableRow({
    tableHeader: true,
    cantSplit: true,
    children: nazivi.map((n, i) => celija([p(t(n, { bold: true, size: 18, color: BORDO }), { after: 0 })], STUPCI[i], { fill: KREM })),
  });
}

function tablica(nazivi, redovi) {
  return new Table({
    width: { size: SIRINA, type: WidthType.DXA },
    columnWidths: STUPCI,
    layout: TableLayoutType.FIXED,
    rows: [zaglavljeTablice(nazivi), ...redovi],
  });
}

function redPitanja(proizvod, pitanje) {
  return new TableRow({
    cantSplit: true,
    height: { value: 700, rule: HeightRule.ATLEAST },
    children: [
      celija([p(t(proizvod, { bold: true, size: 19 }), { after: 0 })], STUPCI[0]),
      celija([p(t(pitanje, { size: 19 }), { after: 0 })], STUPCI[1]),
      celija([], STUPCI[2]),
    ],
  });
}

function redPosluzivanja(r) {
  return new TableRow({
    cantSplit: true,
    children: [
      celija(r.proizvodi.map((n) => p(t(n, { bold: true, size: 18 }), { after: 30 })), STUPCI[0]),
      celija(r.stavke.map((s) => {
        const i = s.indexOf(':');
        const glava = i > 0 ? s.slice(0, i) : s;
        const ostalo = i > 0 ? s.slice(i + 1).trim() : '';
        return p([t(glava, { bold: true, size: 18, color: BORDO }), ...(ostalo ? [t(' · ' + ostalo, { size: 18 })] : [])], { after: 50 });
      }), STUPCI[1]),
      celija([], STUPCI[2]),
    ],
  });
}

const djeca = [
  p(t('DOMESTIC SPIRITS', { size: 18, bold: true, color: ZLATNA }), { after: 60 }),
  p(t(c.naslov, { font: SERIF, size: 40, color: TAMNA }), { after: 60 }),
  p(t(c.podnaslov, { size: 20, color: SIVA }), { after: 200, border: { bottom: { style: BorderStyle.SINGLE, size: 8, color: ZLATNA, space: 8 } } }),
  ...c.uvod.map((u) => p(t(u, { size: 21 }), { after: 100 })),
];

for (const o of c.odjeljci) {
  djeca.push(p(t(o.naslov, { font: SERIF, size: 28, color: BORDO }), { before: 320, after: 80, keepNext: true }));
  djeca.push(p(t(o.uvod, { size: 19, color: SIVA, italics: true }), { after: 140, keepNext: true }));
  const nazivi = [...o.stupci, 'Vaš odgovor'];
  if (o.posluzivanje) {
    djeca.push(tablica(nazivi, o.posluzivanje.map(redPosluzivanja)));
  } else {
    djeca.push(tablica(nazivi, o.redovi.map(([a, b]) => redPitanja(a, b))));
  }
}

djeca.push(p(t('6. Vaše dodatne stavke', { font: SERIF, size: 28, color: BORDO }), { before: 320, after: 80, keepNext: true }));
djeca.push(p(t('Ovdje upišite sve što smo propustili ili želite promijeniti na proizvodima.', { size: 19, color: SIVA, italics: true }), { after: 140, keepNext: true }));
const prazni = Array.from({ length: 10 }, () => new TableRow({
  cantSplit: true,
  height: { value: 620, rule: HeightRule.ATLEAST },
  children: STUPCI.map((s) => celija([], s)),
}));
djeca.push(tablica(['Proizvod', 'Napomena ili pitanje', 'Odgovor / dogovor'], prazni));

const doc = new Document({
  creator: 'Vertikal',
  title: c.naslov,
  styles: { default: { document: { run: { font: SANS, size: 20, color: TAMNA } } } },
  sections: [{
    properties: { page: { size: { width: 11906, height: 16838 }, margin: { top: 1134, bottom: 1134, left: 1134, right: 1134, header: 567, footer: 567 } } },
    headers: { default: new Header({ children: [p(t('Domestic Spirits · ' + c.naslov, { size: 16, color: SIVA }), { align: AlignmentType.RIGHT, after: 0 })] }) },
    footers: { default: new Footer({ children: [new Paragraph({ alignment: AlignmentType.CENTER, children: [
      new TextRun({ children: ['Stranica ', PageNumber.CURRENT, ' od ', PageNumber.TOTAL_PAGES], font: SANS, size: 16, color: SIVA }),
    ] })] }) },
    children: djeca,
  }],
});

Packer.toBuffer(doc).then((b) => { fs.writeFileSync(izlaz, b); console.log('spremljeno', izlaz, b.length); });
