/**
 * Plan A – pristupnice u Google tablici.
 *
 * Skriptu je napravio dodatak "Plan A članstvo" (WordPress). Stranica šalje svaku novu
 * pristupnicu i potvrdu u ovu tablicu. Stupce "Članarina GGGG" i "Iskaznica uručena"
 * stranica ne mijenja: kvačice označavate ručno. "Napomena" se popunjava samo pri uvozu
 * i samo ako je prazna, pa je možete slobodno mijenjati. Stupac "Br." nemojte brisati ni mijenjati
 * (po njemu se pronalazi red), a tablicu smijete sortirati i filtrirati.
 */
var SECRET = '{{SECRET}}';
var SCRIPT_VERSION = 16;
var SITE = '{{SITE}}';
var AG_ID = '{{AG}}';
var SHEET_NAME = 'Članovi';
var HEAD = ['Br.', 'Datum prijave', 'Ime', 'Prezime', 'Datum rođenja', 'OIB', 'Adresa', 'Mjesto, poštanski broj', 'E-mail', 'Mobitel', 'Roditelj ili skrbnik', 'Status', 'Datum potvrde'];
var KEYS = ['broj', 'prijava', 'ime', 'prezime', 'datum', 'oib', 'adresa', 'mjesto', 'email', 'mobitel', 'roditelj', 'status', 'potvrda'];
var YEAR_PREFIX = 'Članarina ';
var CARD = 'Iskaznica uručena';
var NOTE = 'Napomena';

function doPost(e) {
  var out;
  try {
    var d = JSON.parse(e.postData.contents);
    if (d.secret !== SECRET) {
      return json_({ ok: false, error: 'Pogrešan ključ.' });
    }
    if (d.ag) agRemember_(d.ag);
    var lock = LockService.getScriptLock();
    lock.waitLock(25000);
    try {
      var sh = sheet_();
      if (d.action === 'ping') {
        out = { ok: true, name: SpreadsheetApp.getActive().getName() };
      } else if (d.action === 'upsert') {
        upsertMany_(sh, [d]);
        out = { ok: true };
      } else if (d.action === 'bulk') {
        out = { ok: true, n: upsertMany_(sh, d.rows || []) };
      } else if (d.action === 'ag_tours') {
        out = { ok: true, n: agTours_(agSheet_(d.ag), d.tours || []) };
      } else if (d.action === 'ag_add') {
        out = { ok: true, n: agAdd_(agSheet_(d.ag), d.rows || []) };
      } else if (d.action === 'ag_status') {
        out = { ok: true, n: agStatus_(agSheet_(d.ag), d.items || []) };
      } else if (d.action === 'ag_paid') {
        var ash = agSheet_(d.ag);
        out = { ok: true, orders: agPaid_(ash, null), local: agLocalRefresh_(ash) };
        agLinks_(ash);
        out.archived = agArchive_(ash);
        out.cleaned = agGdpr_(agArhSheet_(ash.getParent()));
      } else if (d.action === 'ag_list') {
        out = { ok: true, blocks: agList_(agSheet_(d.ag), String(d.date || '')) };
      } else if (d.action === 'ag_summary') {
        out = { ok: true, blocks: agSummary_(agSheet_(d.ag), String(d.from || ''), String(d.to || '')) };
      } else if (d.action === 'ag_people') {
        out = { ok: true, people: agPeople_(agSheet_(d.ag), String(d.from || '')) };
      } else if (d.action === 'ag_rates') {
        out = { ok: true, n: agRates_(agSheet_(d.ag), d.ids || [], d.rt || []) };
      } else if (d.action === 'ag_note') {
        out = { ok: true, n: agNoteOrders_(agSheet_(d.ag), d.items || []) };
      } else if (d.action === 'read') {
        out = { ok: true, rows: read_(sh) };
      } else if (d.action === 'renumber') {
        out = { ok: true, n: renumber_(sh, d.map || {}) };
      } else if (d.action === 'delete') {
        var r = findRow_(sh, d.broj);
        if (r) sh.deleteRow(r);
        out = { ok: true };
      } else {
        out = { ok: false, error: 'Nepoznata radnja.' };
      }
      SpreadsheetApp.flush();
    } finally {
      lock.releaseLock();
    }
  } catch (err) {
    out = { ok: false, error: String(err) };
  }
  out.v = SCRIPT_VERSION;
  return json_(out);
}

function json_(o) {
  return ContentService.createTextOutput(JSON.stringify(o)).setMimeType(ContentService.MimeType.JSON);
}

function sheet_() {
  var ss = SpreadsheetApp.getActive();
  var sh = ss.getSheetByName(SHEET_NAME) || ss.insertSheet(SHEET_NAME, 0);
  if (sh.getLastRow() === 0) {
    sh.getRange(1, 1, 1, HEAD.length).setValues([HEAD]);
    sh.setFrozenRows(1);
    sh.getRange('F:F').setNumberFormat('@'); // OIB kao tekst (vodeće nule)
    sh.getRange('J:J').setNumberFormat('@'); // mobitel kao tekst
  }
  cardCol_(sh);
  noteCol_(sh);
  yearCol_(sh, new Date().getFullYear());
  styleHead_(sh);
  return sh;
}

function styleHead_(sh) {
  var head = sh.getRange(1, 1, 1, sh.getLastColumn());
  head.setFontWeight('bold').setBackground('#12304b').setFontColor('#ffffff').setWrap(true).setVerticalAlignment('middle');
}

/** Stupac "Članarina GGGG" (s kvačicama); vraća broj stupca. */
function yearCol_(sh, year) {
  var last = sh.getLastColumn();
  var heads = sh.getRange(1, 1, 1, last).getValues()[0];
  var name = YEAR_PREFIX + year;
  var at = heads.indexOf(name);
  if (at >= 0) return at + 1;
  // Godine idu redom: novi stupac prije prve veće godine, a uvijek ispred stupca
  // "Iskaznica uručena" i vlastitih stupaca na kraju.
  var col = last + 1;
  for (var i = HEAD.length; i < heads.length; i++) {
    var h = String(heads[i]);
    if (h.indexOf(YEAR_PREFIX) !== 0) { col = i + 1; break; }
    var y = parseInt(h.replace(YEAR_PREFIX, ''), 10);
    if (y > year) { col = i + 1; break; }
  }
  if (col <= last) sh.insertColumnBefore(col); else sh.insertColumnAfter(last);
  sh.getRange(1, col).setValue(name);
  var rows = sh.getLastRow() - 1;
  if (rows > 0) sh.getRange(2, col, rows, 1).insertCheckboxes();
  sh.setColumnWidth(col, 110);
  return col;
}

/** Stupac "Iskaznica uručena" (s kvačicama), na kraju; vraća broj stupca. */
function cardCol_(sh) {
  var last = sh.getLastColumn();
  var heads = sh.getRange(1, 1, 1, last).getValues()[0];
  var at = heads.indexOf(CARD);
  if (at >= 0) return at + 1;
  var col = last + 1;
  sh.insertColumnAfter(last);
  sh.getRange(1, col).setValue(CARD);
  var rows = sh.getLastRow() - 1;
  if (rows > 0) sh.getRange(2, col, rows, 1).insertCheckboxes();
  sh.setColumnWidth(col, 120);
  return col;
}

/** Stupac "Napomena" (običan tekst), iza stupca za iskaznicu; vraća broj stupca. */
function noteCol_(sh) {
  var last = sh.getLastColumn();
  var heads = sh.getRange(1, 1, 1, last).getValues()[0];
  var at = heads.indexOf(NOTE);
  if (at >= 0) return at + 1;
  var col = heads.indexOf(CARD) + 2;
  if (col <= last) sh.insertColumnBefore(col); else sh.insertColumnAfter(last);
  sh.getRange(1, col).setValue(NOTE);
  sh.setColumnWidth(col, 220);
  return col;
}

/** Stupci s kvačicama: "Članarina GGGG" i "Iskaznica uručena". */
function isBox_(h) {
  h = String(h);
  return h === CARD || h.indexOf(YEAR_PREFIX) === 0;
}

/** Kvačice iz tablice (stranica ih čita): za svaki broj označene godine i iskaznica. */
function read_(sh) {
  var last = sh.getLastRow(), lastCol = sh.getLastColumn();
  if (last < 2) return [];
  var heads = sh.getRange(1, 1, 1, lastCol).getValues()[0].map(String);
  var data = sh.getRange(2, 1, last - 1, lastCol).getValues();
  var years = [];
  heads.forEach(function (h, c) {
    if (h.indexOf(YEAR_PREFIX) === 0) years.push([c, parseInt(h.replace(YEAR_PREFIX, ''), 10)]);
  });
  var cardC = heads.indexOf(CARD);
  var out = [];
  data.forEach(function (r) {
    var b = parseInt(r[0], 10);
    if (!b) return;
    var y = [];
    years.forEach(function (p) { if (r[p[0]] === true) y.push(p[1]); });
    out.push({ b: b, y: y, k: cardC >= 0 && r[cardC] === true ? 1 : 0 });
  });
  return out;
}

/** Novi brojevi članova (stari → novi), pa redovi poredani po broju; kvačice idu s redom. */
function renumber_(sh, map) {
  var last = sh.getLastRow();
  if (last < 2) return 0;
  var col = sh.getRange(2, 1, last - 1, 1).getValues();
  var n = 0;
  col.forEach(function (r) {
    var k = String(r[0]);
    if (Object.prototype.hasOwnProperty.call(map, k)) { r[0] = map[k]; n++; }
  });
  sh.getRange(2, 1, last - 1, 1).setValues(col);
  sh.getRange(2, 1, last - 1, sh.getLastColumn()).sort({ column: 1, ascending: true });
  return n;
}

function findRow_(sh, broj) {
  if (!broj || sh.getLastRow() < 2) return 0;
  var f = sh.getRange(2, 1, sh.getLastRow() - 1, 1).createTextFinder(String(broj)).matchEntireCell(true).findNext();
  return f ? f.getRow() : 0;
}

function clean_(v) {
  v = v === undefined || v === null ? '' : String(v);
  // Tekst koji počinje s = + - @ ne smije postati formula.
  return /^[=+\-@]/.test(v) ? "'" + v : v;
}

/**
 * Upis cijelog paketa odjednom: tablica se pročita jednom, novi redovi upišu se jednim
 * potezom na kraj, a postojeći dobiju samo svoje podatke (i kvačice koje nedostaju).
 */
function upsertMany_(sh, items) {
  items = (items || []).filter(function (it) { return it && it.row && it.row.broj; });
  if (!items.length) return 0;
  var years = {};
  items.forEach(function (it) { (it.godine || []).forEach(function (y) { years[parseInt(y, 10)] = 1; }); });
  Object.keys(years).sort().forEach(function (y) { yearCol_(sh, parseInt(y, 10)); });

  var lastCol = sh.getLastColumn();
  var heads = sh.getRange(1, 1, 1, lastCol).getValues()[0].map(String);
  var cardC = heads.indexOf(CARD), noteC = heads.indexOf(NOTE), statusC = KEYS.indexOf('status');
  var lastRow = sh.getLastRow();
  var ids = lastRow > 1 ? sh.getRange(2, 1, lastRow - 1, 1).getValues() : [];
  var index = {};
  ids.forEach(function (r, i) { if (r[0] !== '') index[String(r[0])] = i + 2; });

  var fresh = [], freshAt = {};
  items.forEach(function (it) {
    var key = String(it.row.broj);
    var keys = KEYS.map(function (k) { return clean_(it.row[k]); });
    var boxes = [];
    (it.godine || []).forEach(function (y) { var c = heads.indexOf(YEAR_PREFIX + parseInt(y, 10)); if (c >= 0) boxes.push(c); });
    if (it.iskaznica && cardC >= 0) boxes.push(cardC);
    var note = it.napomena && noteC >= 0 ? clean_(it.napomena) : '';
    var r = index[key];
    if (r) {
      // Postojeći red: osnovni podaci, kvačice samo dodati, napomena samo ako je prazna.
      sh.getRange(r, 1, 1, KEYS.length).setValues([keys]);
      sh.getRange(r, statusC + 1).setBackground(it.row.status === 'Potvrđeno' ? '#d9f2e3' : '#fdebd3');
      if (boxes.length || note) {
        var line = sh.getRange(r, 1, 1, lastCol).getValues()[0];
        boxes.forEach(function (c) { if (line[c] !== true) sh.getRange(r, c + 1).insertCheckboxes().setValue(true); });
        if (note && line[noteC] === '') sh.getRange(r, noteC + 1).setValue(note);
      }
      return;
    }
    var row = freshAt[key];
    if (row === undefined) {
      row = heads.map(function (h, c) { return c >= HEAD.length && isBox_(h) ? false : ''; });
      freshAt[key] = row;
      fresh.push(row);
    }
    keys.forEach(function (v, c) { row[c] = v; });
    boxes.forEach(function (c) { row[c] = true; });
    if (note && row[noteC] === '') row[noteC] = note;
  });

  if (fresh.length) {
    var start = lastRow + 1;
    heads.forEach(function (h, c) {
      if (c >= HEAD.length && isBox_(h)) sh.getRange(start, c + 1, fresh.length, 1).insertCheckboxes();
    });
    sh.getRange(start, 1, fresh.length, lastCol).setValues(fresh);
    sh.getRange(start, statusC + 1, fresh.length, 1).setBackgrounds(fresh.map(function (row) {
      return [row[statusC] === 'Potvrđeno' ? '#d9f2e3' : '#fdebd3'];
    }));
  }
  return items.length;
}

/* ==========================================================================
 * Tablica za agenciju: "Prijave na izlete" (posebna Google tablica, list "Prijave").
 * Svaki izlet je blok (naslov, nazivi stupaca, osobe, prazan red); novi izlet ide na vrh.
 * Skriveni zadnji stupac "ključ" povezuje redove sa stranicom. Stranica upisuje svaku
 * osobu samo jednom; obrisani red se ne vraća.
 * - Uplaćeno: kad su označene sve osobe iz narudžbe (osim otkazanih), narudžba postaje
 *   "Završeno" i kupac dobiva e-mail.
 * - Otkazao: red posivi; kad otkažu svi iz narudžbe, narudžba se otkazuje (e-mail ide
 *   samo vama).
 * - Ime i prezime: zamjena u istom redu ili nova osoba u novom redu. Ostali podaci
 *   upisuju se iz tablice članova, a brojevi (Br.) se slože ispočetka.
 * Napomena i sve što dopišete ostaje.
 * ======================================================================== */
var AG_SHEET = 'Prijave';
var AG_HEAD = ['Br.', 'Ime', 'Prezime', 'OIB', 'Datum rođenja', 'Adresa', 'Mjesto', 'Mobitel', 'E-mail', 'Prijavio/la', 'Iznos', 'Osiguranje', 'Uplaćeno', '2. rata', 'Otkazao', 'Ugovor', 'Polica', 'Pristupnica', 'Članarina', 'Iskaznica', 'Napomena', 'Narudžba', 'ključ'];
var AG_KEYS = ['', 'ime', 'prezime', 'oib', 'datum', 'adresa', 'mjesto', 'mobitel', 'email', 'prijavio', 'iznos', 'osiguranje', 'uplaceno', 'rata2', 'otkazao', 'ugovor', 'polica', 'pristupnica', 'clanarina', 'iskaznica', 'napomena', 'narudzba'];
var AG_BOX = { osiguranje: 1, uplaceno: 1, rata2: 1, otkazao: 1, ugovor: 1, polica: 1, clanarina: 1, iskaznica: 1 };
var AG_PERSON = ['oib', 'datum', 'adresa', 'mjesto', 'mobitel', 'email']; // iz tablice članova
var AG_KEY_COL = AG_HEAD.length; // skriveni stupac
var AG_LAST = AG_HEAD.length - 1; // zadnji vidljivi stupac
var AG_GREY = '#e6e6e6';
var AG_AUTO = /^(nije član – |više članova s tim imenom|zamjena za )/; // napomene koje piše skripta

/** Broj stupca za ključ iz AG_KEYS. */
function agC_(k) {
  return AG_KEYS.indexOf(k) + 1;
}

/**
 * Brzo osvježavanje: pokreni JEDNOM u uređivaču (Run) i dopusti pristup. Nakon toga:
 * - kvačice "Članarina GGGG" i "Iskaznica uručena" u tablici članova odmah idu na stranicu
 *   i u tablicu za agenciju;
 * - u tablici za agenciju kvačice "Uplaćeno" i "Otkazao" odmah mijenjaju narudžbu, a
 *   upisano ime i prezime povlači ostale podatke iz tablice članova.
 * Ponovno pokretanje ne pravi duple okidače.
 */
function ukljuciBrzoOsvjezavanje() {
  var ss = SpreadsheetApp.getActive();
  PropertiesService.getScriptProperties().setProperty('MEM', ss.getId());
  ScriptApp.getProjectTriggers().forEach(function (t) {
    var f = t.getHandlerFunction();
    if (f === 'naIzmjenu' || f === 'naPromjenu') ScriptApp.deleteTrigger(t);
  });
  ScriptApp.newTrigger('naIzmjenu').forSpreadsheet(ss).onEdit().create();
  var ag = agId_();
  if (!ag) return 'Brzo osvježavanje je uključeno za tablicu članova. Tablica za agenciju još nije poznata: na stranici klikni "Provjeri vezu" i ponovno pokreni ovu funkciju.';
  ScriptApp.newTrigger('naIzmjenu').forSpreadsheet(ag).onEdit().create();
  ScriptApp.newTrigger('naPromjenu').forSpreadsheet(ag).onChange().create();
  var ash = agSheet_(ag); // dodaje stupce ako ih još nema
  agPolicyRule_(ash);
  if (PropertiesService.getScriptProperties().getProperty('AGRATES')) agFixRates_(ash); // popis rata šalje stranica
  agLinks_(ash);
  zastiti_(ss, ash);
  return 'Brzo osvježavanje je uključeno (tablica članova i tablica za agenciju), stupci koje puni web su zaštićeni.';
}

function agRemember_(id) {
  id = String(id);
  var p = PropertiesService.getScriptProperties();
  if (p.getProperty('AG') !== id) p.setProperty('AG', id);
  var mem = SpreadsheetApp.getActive();
  if (mem && p.getProperty('MEM') !== mem.getId()) p.setProperty('MEM', mem.getId());
}

function agId_() {
  return PropertiesService.getScriptProperties().getProperty('AG') || (AG_ID.indexOf('{{') === 0 ? '' : AG_ID);
}

function post_(payload) {
  payload.secret = SECRET;
  UrlFetchApp.fetch(SITE, {
    method: 'post',
    contentType: 'application/json',
    payload: JSON.stringify(payload),
    muteHttpExceptions: true
  });
}

/** Okidač za obje tablice (promjena ćelije). */
function naIzmjenu(e) {
  try {
    if (!e || !e.range || !SITE || SITE.indexOf('{{') === 0) return;
    var sh = e.range.getSheet();
    if (sh.getName() === AG_SHEET) return agNaIzmjenu_(e, sh);
    if (sh.getName() !== SHEET_NAME) return;
    var r0 = Math.max(2, e.range.getRow()), r1 = e.range.getLastRow();
    if (r1 < r0) return;
    var lastCol = sh.getLastColumn();
    var heads = sh.getRange(1, 1, 1, lastCol).getValues()[0].map(String);
    var touched = false;
    for (var c = e.range.getColumn(); c <= e.range.getLastColumn(); c++) {
      if (isBox_(heads[c - 1])) touched = true;
    }
    if (!touched) return;
    var years = [];
    heads.forEach(function (h, c) { if (h.indexOf(YEAR_PREFIX) === 0) years.push([c, parseInt(h.replace(YEAR_PREFIX, ''), 10)]); });
    var cardC = heads.indexOf(CARD);
    var data = sh.getRange(r0, 1, r1 - r0 + 1, lastCol).getValues();
    var rows = [];
    data.forEach(function (r) {
      var b = parseInt(r[0], 10);
      if (!b) return;
      var y = [];
      years.forEach(function (p) { if (r[p[0]] === true) y.push(p[1]); });
      rows.push({ b: b, y: y, k: cardC >= 0 && r[cardC] === true ? 1 : 0 });
    });
    if (!rows.length) return;
    post_({ rows: rows });
    // Ručno upisane osobe i zamjene u tablici za agenciju vodi skripta.
    var ag = agId_();
    if (ag) {
      var lock = LockService.getScriptLock();
      lock.waitLock(25000);
      try {
        agLocalRefresh_(agSheet_(ag));
      } finally {
        lock.releaseLock();
      }
    }
  } catch (err) {
    console.error(err);
  }
}

/** Okidač za tablicu za agenciju (dodan ili obrisan red): brojevi i zbrojevi ispočetka. */
function naPromjenu(e) {
  try {
    if (!e || (e.changeType !== 'INSERT_ROW' && e.changeType !== 'REMOVE_ROW')) return;
    var sh = e.source.getSheetByName(AG_SHEET);
    if (!sh) return;
    var lock = LockService.getScriptLock();
    lock.waitLock(25000);
    try {
      agMigrate_(sh);
      var ix = agIndex_(sh);
      ix.blocks.forEach(function (b) { agNumber_(sh, ix, b); agCount_(sh, ix, b); });
      agLinks_(sh);
    } finally {
      lock.releaseLock();
    }
  } catch (err) {
    console.error(err);
  }
}

/** Ovo pokreni jednom u uređivaču (Run), da Google dopusti pristup tablici za agenciju. */
function ovlasti() {
  return SpreadsheetApp.getActive().getName();
}

function str_(v) {
  if (v instanceof Date) return Utilities.formatDate(v, Session.getScriptTimeZone(), 'd.M.yyyy.');
  return v === null || v === undefined ? '' : String(v).trim();
}

function agSheet_(id) {
  if (!id) throw new Error('Tablica za agenciju nije upisana u postavkama.');
  var ss = SpreadsheetApp.openById(id);
  var sh = ss.getSheetByName(AG_SHEET);
  if (!sh) {
    sh = ss.insertSheet(AG_SHEET, 0);
    sh.getRange(1, 1).setValue('Prijave na izlete – Plan A · novi izlet je na vrhu · Uplaćeno: kad su označene sve osobe iz narudžbe, kupac dobiva potvrdu · Otkazao: osoba ne ide (kad otkažu svi, narudžba se otkazuje) · zamjena: upišite novo ime i prezime u isti red, ostalo se upiše iz tablice članova · Napomena je vaša');
    sh.getRange(1, 1, 1, AG_LAST).merge().setFontStyle('italic').setFontColor('#5f6b77').setWrap(true);
    sh.setFrozenRows(1);
    sh.getRange(1, AG_KEY_COL).setValue('ključ');
    sh.hideColumns(AG_KEY_COL);
    var w = [45, 110, 130, 105, 95, 170, 130, 115, 190, 140, 80, 85, 80, 80, 80, 80, 80, 115, 90, 85, 260, 80];
    w.forEach(function (px, i) { sh.setColumnWidth(i + 1, px); });
    sh.getRange('D:D').setNumberFormat('@');
    sh.getRange('H:H').setNumberFormat('@');
  }
  agMigrate_(sh);
  return sh;
}

/**
 * Starija tablica (bez stupca "2. rata"): umetni ga ispred "Otkazao". Redovi se ne mijenjaju,
 * a zaglavlja blokova dobivaju naziv. Radi se samo jednom.
 */
function agMigrate_(sh) {
  if (String(sh.getRange(1, AG_KEY_COL).getValue()) === 'ključ') return;   // novi raspored
  var kc = 0;
  for (var c = 1; c <= AG_KEY_COL; c++) { if (String(sh.getRange(1, c).getValue()) === 'ključ') { kc = c; break; } }
  if (!kc) return;
  var missing = AG_KEY_COL - kc;                  // koliko stupaca fali
  var rc = agC_('rata2');
  var rows = Math.max(sh.getMaxRows() - 1, 1);
  // Novi stupac preuzima kvačice susjednog stupca – makni ih, kućice se dodaju samo gdje trebaju.
  if (missing >= 3) { sh.insertColumnBefore(rc); sh.setColumnWidth(rc, 80); sh.getRange(2, rc, rows, 1).clearDataValidations().clearContent(); }
  var uc = agC_('ugovor');
  sh.insertColumnsBefore(uc, 2);
  sh.setColumnWidth(uc, 80); sh.setColumnWidth(uc + 1, 80);
  sh.getRange(2, uc, rows, 2).clearDataValidations().clearContent();
  var last = sh.getLastRow();
  var data = last > 0 ? sh.getRange(1, 1, last, AG_KEY_COL).getValues() : [];
  data.forEach(function (row, i) {
    var r = i + 1, k = String(row[AG_KEY_COL - 1] || '');
    if (r < 2) return;
    if (k.indexOf('H|') === 0) {
      if (missing >= 3) sh.getRange(r, rc).setValue('2. rata');
      sh.getRange(r, uc).setValue('Ugovor');
      sh.getRange(r, uc + 1).setValue('Polica');
    } else if (k.indexOf('P|') === 0 || k.indexOf('M|') === 0) {
      sh.getRange(r, uc).insertCheckboxes().setValue(false);
      sh.getRange(r, uc + 1).insertCheckboxes().setValue(false);
    }
  });
  agPolicyRule_(sh);
}

/**
 * Kućica „Polica” je aktivna (bijela) samo kad je u stupcu „Osiguranje” označeno; inače je siva.
 * Pravilo se osvježava pri svakom pokretanju, pa radi i kad se osiguranje označi ručno.
 */
function agPolicyRule_(sh) {
  var pc = agC_('polica'), last = Math.max(sh.getMaxRows(), 2);
  var range = sh.getRange(2, pc, last - 1, 1);
  var rules = sh.getConditionalFormatRules().filter(function (rule) {
    return !rule.getRanges().some(function (rg) { return rg.getColumn() === pc && rg.getNumColumns() === 1; });
  });
  var oc = colA1_(agC_('osiguranje')), br = colA1_(1);
  rules.push(SpreadsheetApp.newConditionalFormatRule()
    .whenFormulaSatisfied('=AND(NOT($' + oc + '2=TRUE),ISNUMBER($' + br + '2))')
    .setBackground('#e6e6e6')
    .setRanges([range])
    .build());
  sh.setConditionalFormatRules(rules);
}

/* ---------- Druga rata samo na izletima s dvije rate ---------- */

var AG_BLACK = '#000000';

/** Kućica „2. rata” isključena: prazno, crno, ne prima upis. */
function agRateOff_(cell) {
  cell.clearDataValidations().clearContent().setBackground(AG_BLACK)
    .setDataValidation(SpreadsheetApp.newDataValidation().requireFormulaSatisfied('=FALSE').setAllowInvalid(false)
      .setHelpText('Ovaj izlet nema drugu ratu.').build());
}

function agRateOn_(cell) {
  cell.clearDataValidations().setBackground(null).insertCheckboxes().setValue(false);
}

/** ID-jevi izleta s dvije rate (zadnje poslano sa stranice). */
function agRateIds_() {
  try { return JSON.parse(PropertiesService.getScriptProperties().getProperty('AGRATES') || '[]'); } catch (e) { return []; }
}

/** Blok (ključ datum_ID) pripada izletu s dvije rate. */
function agRateBlock_(b, ids) {
  var id = String(b.key).split('_').pop();
  return ids.map(String).indexOf(id) >= 0;
}

function agIsBox_(dv) {
  return !!dv && dv.getCriteriaType() === SpreadsheetApp.DataValidationCriteria.CHECKBOX;
}

/** Sa stranice: ids = izleti s dvije rate, rt = osobe koje su platile prvu ratu. */
function agRates_(sh, ids, rt) {
  var p = PropertiesService.getScriptProperties();
  p.setProperty('AGRATES', JSON.stringify(ids.map(String)));
  p.setProperty('AGRT', JSON.stringify(rt.map(String)));
  return agFixRates_(sh, rt);
}

/**
 * Stupci „2. rata”, „Ugovor” i „Polica”: u nazivima stupaca i praznim redovima bez kućica,
 * „Ugovor” i „Polica” kućica kod svake osobe, a „2. rata” samo na izletima s dvije rate
 * (osobe s prvom ratom i ručno dodani) – inače je ćelija crna i zaključana. Piše samo promjene.
 */
function agFixRates_(sh, rt) {
  var ix = agIndex_(sh), ids = agRateIds_(), n = 0;
  if (ix.last < 2) return 0;
  var rc = agC_('rata2'), uc = agC_('ugovor'), pc = agC_('polica');
  var rtSet = {};
  if (!rt) { try { rt = JSON.parse(PropertiesService.getScriptProperties().getProperty('AGRT') || '[]'); } catch (e) { rt = []; } }
  rt.forEach(function (k) { rtSet[String(k).replace(/\|/g, '_')] = 1; });
  var dv = sh.getRange(1, rc, ix.last, 1).getDataValidations();
  var dvU = sh.getRange(1, uc, ix.last, 2).getDataValidations();
  var bg = sh.getRange(1, rc, ix.last, 1).getBackgrounds();
  var heads = { rata2: '2. rata', ugovor: 'Ugovor', polica: 'Polica' };
  for (var r = 2; r <= ix.last; r++) {
    var row = ix.data[r - 1], info = ix.info[r] || {};
    if (info.kind === 'T') continue;
    var cells = [[rc, dv[r - 1][0], 'rata2'], [uc, dvU[r - 1][0], 'ugovor'], [pc, dvU[r - 1][1], 'polica']];
    if (info.kind === 'H') {
      cells.forEach(function (c) {
        if (agIsBox_(c[1])) { sh.getRange(r, c[0]).clearDataValidations(); n++; }
        if (str_(row[c[0] - 1]) !== heads[c[2]]) { sh.getRange(r, c[0]).setValue(heads[c[2]]); n++; }
      });
      continue;
    }
    if (!agIsPerson_(ix, r)) {
      cells.forEach(function (c) {
        if (agIsBox_(c[1]) || typeof row[c[0] - 1] === 'boolean' || (c[2] === 'rata2' && bg[r - 1][0] === AG_BLACK)) {
          sh.getRange(r, c[0]).clearDataValidations().clearContent();
          if (c[2] === 'rata2') sh.getRange(r, c[0]).setBackground(null);
          n++;
        }
      });
      continue;
    }
    [[uc, dvU[r - 1][0]], [pc, dvU[r - 1][1]]].forEach(function (c) {
      if (!agIsBox_(c[1]) || typeof row[c[0] - 1] !== 'boolean') {
        sh.getRange(r, c[0]).insertCheckboxes().setValue(row[c[0] - 1] === true);
        n++;
      }
    });
    var b = agBlockOf_(ix, r);
    var on = info.kind === 'P' ? !!rtSet[info.pkey] : !!b && agRateBlock_(b, ids);
    if (on) {
      if (!agIsBox_(dv[r - 1][0]) || typeof row[rc - 1] !== 'boolean' || bg[r - 1][0] === AG_BLACK) {
        var was = row[rc - 1] === true;
        agRateOn_(sh.getRange(r, rc));
        if (was) sh.getRange(r, rc).setValue(true);
        n++;
      }
    } else if (bg[r - 1][0] !== AG_BLACK || agIsBox_(dv[r - 1][0]) || row[rc - 1] !== '') {
      agRateOff_(sh.getRange(r, rc));
      n++;
    }
  }
  return n;
}

function agNorm_(t) {
  return String(t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd').replace(/[^a-z0-9]+/g, ' ').trim();
}

/**
 * Pregled lista: blokovi (naslovni redovi), osobe (po ključu) i vrsta svakog reda:
 * T naslov, H nazivi stupaca, P osoba sa stranice (pkey, izvorno ime, narudžba),
 * M osoba koju ste dodali ručno. Red s imenom bez ključa je također osoba.
 */
function agIndex_(sh) {
  var last = sh.getLastRow();
  var data = last > 0 ? sh.getRange(1, 1, last, AG_HEAD.length).getValues() : [];
  var blocks = [], persons = {}, info = {};
  data.forEach(function (r, i) {
    var k = String(r[AG_KEY_COL - 1] || ''), p = k.split('|');
    if (p[0] === 'T' && p.length > 1) {
      blocks.push({ key: p[1], date: p[2] || '', title: p[3] || '', base: p.slice(4).join('|'), row: i + 1 });
      info[i + 1] = { kind: 'T' };
    } else if (p[0] === 'H' && p.length > 1) {
      info[i + 1] = { kind: 'H' };
    } else if (p[0] === 'P' && p.length > 1) {
      persons[p[1]] = i + 1;
      var m = /^o(\d+)-/.exec(p[1]);
      info[i + 1] = { kind: 'P', pkey: p[1], orig: p.length > 2 ? p.slice(2).join('|') : undefined, o: m ? parseInt(m[1], 10) : 0 };
    } else if (p[0] === 'M') {
      info[i + 1] = { kind: 'M' };
    }
  });
  return { last: last, data: data, blocks: blocks, persons: persons, info: info };
}

function agIsPerson_(ix, r) {
  var row = ix.data[r - 1] || [], info = ix.info[r] || {};
  if (info.kind === 'T' || info.kind === 'H') return false;
  return info.kind === 'P' || info.kind === 'M' || String(row[1] || '') + String(row[2] || '') !== '';
}

function agName_(row) {
  return (str_(row[1]) + ' ' + str_(row[2])).trim();
}

/** Red koji vodi skripta (ručno dodan ili zamjena): podaci i stanje iz tablice članova. */
function agIsLocal_(ix, r) {
  var info = ix.info[r] || {};
  if (info.kind === 'M') return true;
  if (!info.kind) return agIsPerson_(ix, r);
  if (info.kind !== 'P' || info.orig === undefined) return false;
  return info.orig === '?' || agNorm_(info.orig) !== agNorm_(agName_(ix.data[r - 1]));
}

/** Redovi osoba u bloku: [prvi, zadnji]; prazan blok: zadnji = red s nazivima stupaca. */
function agBlockRows_(ix, b) {
  var next = ix.last + 1;
  ix.blocks.forEach(function (o) { if (o.row > b.row && o.row < next) next = o.row; });
  var end = b.row + 1, n = 0;
  for (var r = b.row + 2; r < next; r++) {
    if (agIsPerson_(ix, r)) { end = r; n++; }
  }
  return { first: b.row + 2, end: end, n: n };
}

function agBlockOf_(ix, r) {
  var b = null;
  ix.blocks.forEach(function (o) { if (o.row < r && (!b || o.row > b.row)) b = o; });
  return b;
}

/** Godina izleta (za stupac "Članarina GGGG"). */
function agYear_(b) {
  var y = parseInt(String(b.date || '').substring(0, 4), 10);
  return y > 2000 ? y : new Date().getFullYear();
}

function agTitleText_(base, n, paid, x) {
  return base + ' · prijavljeno ' + n + ' · uplaćeno ' + paid + (x ? ' · otkazalo ' + x : '');
}

/** Osvježi brojke u naslovu bloka (otkazani se ne broje u prijavljene). */
function agCount_(sh, ix, b) {
  var br = agBlockRows_(ix, b), n = 0, paid = 0, x = 0, pc = agC_('uplaceno') - 1, xc = agC_('otkazao') - 1;
  for (var r = br.first; r <= br.end; r++) {
    if (!agIsPerson_(ix, r)) continue;
    var row = ix.data[r - 1];
    if (row[xc] === true) { x++; continue; }
    n++;
    if (row[pc] === true) paid++;
  }
  var text = agTitleText_(b.base, n, paid, x);
  if (String(ix.data[b.row - 1][0]) !== text) {
    sh.getRange(b.row, 1).setValue(text);
    ix.data[b.row - 1][0] = text;
  }
}

/** Brojevi osoba u bloku redom 1, 2, 3 … */
function agNumber_(sh, ix, b) {
  var br = agBlockRows_(ix, b);
  if (br.end < br.first) return;
  var vals = [], n = 0, changed = false;
  for (var r = br.first; r <= br.end; r++) {
    var cur = ix.data[r - 1][0];
    if (agIsPerson_(ix, r)) {
      n++;
      if (Number(cur) !== n) changed = true;
      vals.push([n]);
    } else {
      vals.push([cur]);
    }
  }
  if (!changed) return;
  sh.getRange(br.first, 1, vals.length, 1).setValues(vals);
  vals.forEach(function (v, i) { ix.data[br.first + i - 1][0] = v[0]; });
}

function agRefresh_(sh, ix) {
  var nix = agIndex_(sh);
  ix.last = nix.last; ix.data = nix.data; ix.blocks = nix.blocks; ix.persons = nix.persons; ix.info = nix.info;
}

function agFind_(ix, key) {
  var b = null;
  ix.blocks.forEach(function (o) { if (!b && o.key === key) b = o; });
  return b;
}

/** Nađe blok izleta ili ga napravi na vrhu. t: {key, date, title, base, year} */
function agBlock_(sh, ix, t) {
  t.key = String(t.key).replace(/\|/g, '_');
  t.base = String(t.base || t.title || '');
  t.date = String(t.date || '').replace(/\|/g, '');
  var b = agFind_(ix, t.key);
  if (!b) {
    // Blok iz starog popisa: isti datum (ili mjesec) i sličan naziv.
    var nt = agNorm_(t.title), a = nt.split(' ').slice(0, 2).join(' ');
    ix.blocks.forEach(function (o) {
      if (b || o.key.indexOf('old-') !== 0 || !o.date || String(t.date).indexOf(o.date) !== 0) return;
      var c = o.title.split(' ').slice(0, 2).join(' ');
      if (a && (o.title.indexOf(a) === 0 || nt.indexOf(c) === 0)) b = o;
    });
    if (b) {
      sh.getRange(b.row, AG_KEY_COL).setValue(['T', t.key, t.date, b.title, b.base].join('|'));
      agRefresh_(sh, ix);
      return agFind_(ix, t.key);
    }
  }
  if (b) return b;
  sh.insertRowsBefore(2, 3);
  sh.getRange(2, 1, 3, AG_HEAD.length).breakApart().clearContent().clearFormat().clearDataValidations();
  sh.getRange(2, 1, 1, AG_LAST).merge().setValue(agTitleText_(t.base, 0, 0, 0)).setFontWeight('bold').setFontSize(12).setBackground('#12304b').setFontColor('#ffffff').setVerticalAlignment('middle');
  sh.setRowHeight(2, 30);
  sh.getRange(2, AG_KEY_COL).setValue(['T', t.key, t.date, agNorm_(t.title), t.base].join('|'));
  var head = AG_HEAD.slice(0, AG_LAST).map(function (h) { return h === 'Članarina' && t.year ? 'Članarina ' + t.year : h; });
  sh.getRange(3, 1, 1, AG_LAST).setValues([head]).setFontWeight('bold').setBackground('#e8eef4').setFontColor('#12304b').setWrap(true);
  sh.getRange(3, AG_KEY_COL).setValue('H|' + t.key);
  agRefresh_(sh, ix);
  return agFind_(ix, t.key);
}

function agTours_(sh, tours) {
  var ix = agIndex_(sh), n = 0;
  tours.forEach(function (t) { if (t && t.key && agBlock_(sh, ix, t)) n++; });
  return n;
}

function agPristupnica_(cell, v) {
  v = String(v || '');
  var c = v.indexOf('potvrđ') === 0 ? '#1e7d3a' : (v.indexOf('nije') === 0 ? '#b26200' : '#b32d2e');
  cell.setValue(v).setFontColor(v ? c : null).setFontWeight(v ? 'bold' : 'normal');
}

function agGrey_(sh, r, on) {
  var bg = sh.getRange(r, agC_('rata2')).getBackground();
  sh.getRange(r, 1, 1, AG_LAST).setBackground(on ? AG_GREY : null).setFontColor(on ? '#777777' : null);
  if (bg === AG_BLACK) sh.getRange(r, agC_('rata2')).setBackground(AG_BLACK); // isključena „2. rata” ostaje crna
}

/** Nove osobe: rows [{tour: {...}, pkey, v: {ime, prezime, …}}]. Postojeći ključ se preskače. */
function agAdd_(sh, rows) {
  var ix = agIndex_(sh), n = 0, done = {};
  rows.forEach(function (it) {
    if (!it || !it.pkey || !it.tour) return;
    it.pkey = String(it.pkey).replace(/\|/g, '_');
    if (ix.persons[it.pkey]) return;
    var b = agBlock_(sh, ix, it.tour);
    if (!b) return;
    var br = agBlockRows_(ix, b);
    sh.insertRowAfter(br.end);
    var r = br.end + 1, v = it.v || {};
    v.otkazao = !!(v.otkazao || v.otkazano);
    var line = AG_KEYS.map(function (k) {
      if (k === '') return br.n + 1;
      if (k === 'rata2') return v.rate ? !!v.rata2 : '';
      if (AG_BOX[k]) return !!v[k];
      var x = v[k] === undefined || v[k] === null ? '' : String(v[k]);
      return /^[=+\-@]/.test(x) ? "'" + x : x;
    });
    sh.getRange(r, 1, 1, AG_HEAD.length).breakApart().clearFormat().clearDataValidations();
    Object.keys(AG_BOX).forEach(function (k) { if (k !== 'rata2' || v.rate) sh.getRange(r, agC_(k)).insertCheckboxes(); });
    sh.getRange(r, 1, 1, AG_LAST).setValues([line]);
    if (!v.rate) agRateOff_(sh.getRange(r, agC_('rata2')));
    agPristupnica_(sh.getRange(r, agC_('pristupnica')), v.pristupnica);
    var orig = (String(v.ime || '') + ' ' + String(v.prezime || '')).trim().replace(/\|/g, ' ');
    sh.getRange(r, AG_KEY_COL).setValue('P|' + it.pkey + '|' + orig);
    if (v.otkazao) agGrey_(sh, r, true);
    n++;
    agRefresh_(sh, ix);
    done[b.key] = 1;
  });
  Object.keys(done).forEach(function (k) { var b = agFind_(ix, k); if (b) agCount_(sh, ix, b); });
  if (n) agLinks_(sh);
  return n;
}

/**
 * Osvježavanje sa stranice: items [{k, u, p, c, i, x}] (null = ne mijenjaj). Obrisani redovi
 * se preskaču, a zamjene (drugo ime nego na stranici) zadržavaju stanje iz tablice članova.
 */
function agStatus_(sh, items) {
  var ix = agIndex_(sh), n = 0, touched = {};
  items.forEach(function (it) {
    var r = ix.persons[String(it.k).replace(/\|/g, '_')];
    if (!r) return;
    var row = ix.data[r - 1], local = agIsLocal_(ix, r);
    var set = function (k, val) {
      if (val === null || val === undefined || row[agC_(k) - 1] === !!val) return;
      sh.getRange(r, agC_(k)).setValue(!!val);
      row[agC_(k) - 1] = !!val;
    };
    if (it.u === true) set('uplaceno', true);
    if (it.rt && typeof row[agC_('rata2') - 1] !== 'boolean') {
      agRateOn_(sh.getRange(r, agC_('rata2')));
      row[agC_('rata2') - 1] = false;
    }
    if (!local) {
      set('clanarina', it.c); set('iskaznica', it.i);
      if (it.p !== null && it.p !== undefined && String(row[agC_('pristupnica') - 1]) !== String(it.p)) agPristupnica_(sh.getRange(r, agC_('pristupnica')), it.p);
    }
    if (it.x && row[agC_('otkazao') - 1] !== true) {
      set('otkazao', true);
      agGrey_(sh, r, true);
    }
    n++;
    var b = agBlockOf_(ix, r);
    if (b) touched[b.key] = b;
  });
  Object.keys(touched).forEach(function (k) { agCount_(sh, ix, touched[k]); });
  agLinks_(sh);
  return n;
}

/* ---------- Tablica članova kao baza za tablicu agencije ---------- */

function memSheet_() {
  var id = PropertiesService.getScriptProperties().getProperty('MEM');
  var ss = id ? SpreadsheetApp.openById(id) : SpreadsheetApp.getActive();
  return ss ? ss.getSheetByName(SHEET_NAME) : null;
}

/** Članovi po imenu (popis), OIB-u i e-mailu. */
function memIndex_() {
  var out = { name: {}, oib: {}, email: {} };
  var sh = memSheet_();
  if (!sh || sh.getLastRow() < 2) return out;
  var lastCol = sh.getLastColumn();
  var heads = sh.getRange(1, 1, 1, lastCol).getValues()[0].map(String);
  var data = sh.getRange(2, 1, sh.getLastRow() - 1, lastCol).getValues();
  var c = function (k) { return KEYS.indexOf(k); };
  var cardC = heads.indexOf(CARD);
  data.forEach(function (r) {
    if (!parseInt(r[0], 10)) return;
    var m = {
      oib: str_(r[c('oib')]), datum: str_(r[c('datum')]), adresa: str_(r[c('adresa')]),
      mjesto: str_(r[c('mjesto')]), mobitel: str_(r[c('mobitel')]), email: str_(r[c('email')]),
      potvrdeno: str_(r[c('status')]) === 'Potvrđeno', iskaznica: cardC >= 0 && r[cardC] === true, godine: {}
    };
    heads.forEach(function (h, i) { if (h.indexOf(YEAR_PREFIX) === 0 && r[i] === true) m.godine[parseInt(h.replace(YEAR_PREFIX, ''), 10)] = 1; });
    var n = agNorm_(str_(r[c('ime')]) + ' ' + str_(r[c('prezime')]));
    if (n) (out.name[n] = out.name[n] || []).push(m);
    var o = m.oib.replace(/\D/g, '');
    if (o) out.oib[o] = m;
    if (m.email) out.email[m.email.toLowerCase()] = m;
  });
  return out;
}

/** Član po OIB-u, e-mailu ili imenu i prezimenu. byName = samo po imenu (zamjena). */
function memFind_(mi, row, byName) {
  if (!byName) {
    var oib = str_(row[agC_('oib') - 1]).replace(/\D/g, ''), email = str_(row[agC_('email') - 1]).toLowerCase();
    if (oib.length === 11 && mi.oib[oib]) return { m: mi.oib[oib] };
    if (email && mi.email[email]) return { m: mi.email[email] };
  }
  var l = mi.name[agNorm_(agName_(row))] || [];
  return l.length === 1 ? { m: l[0] } : { m: null, more: l.length > 1 };
}

/** Napomena: makni stare automatske dijelove (drop) i dodaj tekst. Ostalo ostaje. */
function agNote_(sh, r, row, text, drop) {
  var nc = agC_('napomena'), cur = str_(row[nc - 1]);
  var parts = cur ? cur.split(' · ') : [];
  if (drop) parts = parts.filter(function (p) { return !drop.test(p); });
  if (text && parts.indexOf(text) < 0) parts.push(text);
  var nv = parts.join(' · ');
  if (nv === cur) return;
  sh.getRange(r, nc).setValue(/^[=+\-@]/.test(nv) ? "'" + nv : nv);
  row[nc - 1] = nv;
}

/** Kvačice u ručno dodanom redu (gdje ih još nema). */
function agBoxes_(sh, r, row, rate) {
  Object.keys(AG_BOX).forEach(function (k) {
    if (k === 'rata2' && !rate) {
      if (sh.getRange(r, agC_(k)).getBackground() !== AG_BLACK || row[agC_(k) - 1] !== '') agRateOff_(sh.getRange(r, agC_(k)));
      row[agC_(k) - 1] = '';
      return;
    }
    if (row[agC_(k) - 1] === '') {
      sh.getRange(r, agC_(k)).insertCheckboxes();
      row[agC_(k) - 1] = false;
    }
  });
}

function agBox_(sh, r, row, k, val) {
  if (row[agC_(k) - 1] === val) return;
  sh.getRange(r, agC_(k)).setValue(val);
  row[agC_(k) - 1] = val;
}

/**
 * Podaci iz tablice članova. overwrite: nova osoba u redu (zamjena), pa se prepišu i
 * popunjena polja; inače se popunjavaju samo prazna. Pristupnica, Članarina i Iskaznica
 * uvijek prema tablici članova.
 */
function agApply_(sh, r, row, f, year, overwrite) {
  var m = f.m;
  AG_PERSON.forEach(function (k) {
    var c = agC_(k), cur = str_(row[c - 1]), nv = m ? m[k] : '';
    if (overwrite ? cur === nv : (cur !== '' || nv === '')) return;
    sh.getRange(r, c).setValue(/^[=+\-@]/.test(nv) ? "'" + nv : nv);
    row[c - 1] = nv;
  });
  var p = m ? (m.potvrdeno ? 'potvrđena' : 'nije potvrđena') : 'nema';
  if (str_(row[agC_('pristupnica') - 1]) !== p) {
    agPristupnica_(sh.getRange(r, agC_('pristupnica')), p);
    row[agC_('pristupnica') - 1] = p;
  }
  agBox_(sh, r, row, 'clanarina', !!(m && m.godine[year]));
  agBox_(sh, r, row, 'iskaznica', !!(m && m.iskaznica));
  var drop = /^(nije član – |više članova s tim imenom)/;
  if (m) agNote_(sh, r, row, '', drop);
  else agNote_(sh, r, row, f.more ? 'više članova s tim imenom – upiši OIB ili e-mail' : 'nije član – treba ispuniti pristupnicu', drop);
}

/** Promijenjeno ime ili prezime u redu: zamjena (red sa stranice) ili nova osoba (ručno). */
function agRename_(sh, ix, r, mi, year, oldValue, col) {
  var row = ix.data[r - 1], info = ix.info[r] || {}, name = agName_(row);
  if (info.kind === 'P' && info.orig === undefined) {
    // Stariji red bez zapisanog izvornog imena: iz prethodne vrijednosti ćelije.
    info.orig = oldValue === undefined ? '?' : (col === agC_('ime') ? str_(oldValue) + ' ' + str_(row[2]) : str_(row[1]) + ' ' + str_(oldValue)).trim();
    sh.getRange(r, AG_KEY_COL).setValue('P|' + info.pkey + '|' + info.orig.replace(/\|/g, ' '));
  }
  if (!info.kind) {
    info = { kind: 'M' };
    sh.getRange(r, AG_KEY_COL).setValue('M|');
  }
  ix.info[r] = info;
  var b = agBlockOf_(ix, r);
  agBoxes_(sh, r, row, !!(b && agRateBlock_(b, agRateIds_())));
  var same = info.kind === 'P' && info.orig !== '?' && agNorm_(info.orig) === agNorm_(name);
  var f = memFind_(mi, row, true);
  if (!same || f.m) agApply_(sh, r, row, f, year, true);
  if (info.kind === 'P') {
    var ph = /^\d+\. osoba/.test(info.orig);
    agNote_(sh, r, row, same || ph ? '' : 'zamjena za ' + (info.orig === '?' ? 'osobu s prijave' : info.orig), /^zamjena za /);
  }
}

/**
 * Stanje po narudžbi: paid = sve označeno Uplaćeno (prva rata kod rata), rate = ima dvije rate,
 * rate2 = sve druge rate označene (null ako nema rata). Otkazane osobe se ne računaju.
 */
function agPaid_(sh, only, ix) {
  ix = ix || agIndex_(sh);
  var pc = agC_('uplaceno') - 1, xc = agC_('otkazao') - 1, rc = agC_('rata2') - 1, by = {};
  ix.data.forEach(function (row, i) {
    var info = ix.info[i + 1];
    if (!info || !info.o || (only && !only[info.o])) return;
    var o = by[info.o] = by[info.o] || { o: info.o, paid: true, cancel: true, rate: false, rate2: true, rows: [], n: 0 };
    var x = row[xc] === true, rt = typeof row[rc] === 'boolean';
    o.rows.push({ k: info.pkey, n: agName_(row), f: info.orig && info.orig !== '?' ? info.orig : '', x: x, rt: rt });
    if (x) return;
    o.cancel = false;
    o.n++;
    if (row[pc] !== true) o.paid = false;
    if (rt) {
      o.rate = true;
      if (row[rc] !== true) o.rate2 = false;
    }
  });
  return Object.keys(by).map(function (k) {
    var o = by[k];
    if (!o.n) o.paid = false;
    if (!o.rate) o.rate2 = null;
    delete o.n;
    return o;
  });
}

/** Ručno dodane osobe i zamjene u nadolazećim izletima: podaci i stanje iz tablice članova. */
function agLocalRefresh_(sh, ix, mi) {
  ix = ix || agIndex_(sh);
  var from = new Date(Date.now() - 10 * 864e5).toISOString().substring(0, 10), n = 0;
  ix.blocks.forEach(function (b) {
    if (!b.date || b.date < from.substring(0, b.date.length)) return;
    var br = agBlockRows_(ix, b), year = agYear_(b);
    for (var r = br.first; r <= br.end; r++) {
      if (!agIsLocal_(ix, r) || agName_(ix.data[r - 1]) === '') continue;
      mi = mi || memIndex_();
      agApply_(sh, r, ix.data[r - 1], memFind_(mi, ix.data[r - 1], false), year, false);
      n++;
    }
  });
  return n;
}

/**
 * Okidač u tablici za agenciju: Ime/Prezime (zamjena ili nova osoba), OIB/E-mail (traži
 * člana), Uplaćeno i Otkazao (javi stranici stanje tih narudžbi).
 */
function agNaIzmjenu_(e, sh) {
  var c0 = e.range.getColumn(), c1 = e.range.getLastColumn();
  var hit = function (k) { var c = agC_(k); return c >= c0 && c <= c1; };
  var name = hit('ime') || hit('prezime'), ids = hit('oib') || hit('email'), pay = hit('uplaceno') || hit('rata2'), cx = hit('otkazao');
  if (!name && !ids && !pay && !cx) return;
  var lock = LockService.getScriptLock();
  lock.waitLock(25000);
  var list = [];
  try {
    agMigrate_(sh);
    var ix = agIndex_(sh), mi = name || ids ? memIndex_() : null, blocks = {}, orders = {};
    var r0 = Math.max(2, e.range.getRow()), r1 = Math.min(e.range.getLastRow(), ix.last);
    var single = r0 === r1 && c0 === c1;
    for (var r = r0; r <= r1; r++) {
      var info = ix.info[r] || {};
      if (info.kind === 'T' || info.kind === 'H' || !agIsPerson_(ix, r)) continue;
      var b = agBlockOf_(ix, r);
      if (!b) continue;
      blocks[b.key] = b;
      var row = ix.data[r - 1];
      if (name && agName_(row) !== '') agRename_(sh, ix, r, mi, agYear_(b), single ? e.oldValue : undefined, c0);
      else if (ids && agIsLocal_(ix, r)) agApply_(sh, r, row, memFind_(mi, row, false), agYear_(b), false);
      var x = row[agC_('otkazao') - 1] === true;
      if (cx) agGrey_(sh, r, x);
      if (pay && x && row[agC_('uplaceno') - 1] === true) agNote_(sh, r, row, 'otkazano – uplata se ne računa', null);
      info = ix.info[r] || {};
      if (info.o) orders[info.o] = 1;
    }
    Object.keys(blocks).forEach(function (k) { agNumber_(sh, ix, blocks[k]); agCount_(sh, ix, blocks[k]); });
    if (Object.keys(orders).length) list = agPaid_(sh, orders, ix);
    if (name || cx) agLinks_(sh);
  } finally {
    lock.releaseLock();
  }
  if (list.length) post_({ ag: list });
}

/* ---------- Povezane osobe iz iste narudžbe ---------- */

var AG_LINK_COLORS = ['#fff2cc', '#d9ead3', '#cfe2f3', '#f4cccc', '#d9d2e9', '#fce5cd', '#d0e0e3', '#ead1dc'];

/** 1 osoba, 2 osobe, 5 osoba (hrvatska množina). */
function agPl_(n, one, few, many) {
  var d = n % 10, h = n % 100;
  return n + ' ' + (d === 1 && h !== 11 ? one : (d >= 2 && d <= 4 && (h < 12 || h > 14) ? few : many));
}

function agMoney_(v) {
  var t = String(v || '').replace(/[^0-9,.\-]/g, '');
  if (!t) return null;
  if (t.indexOf(',') >= 0) t = t.replace(/\./g, '').replace(',', '.');
  var n = parseFloat(t);
  return isNaN(n) ? null : n;
}

/**
 * Narudžba s više osoba ili izleta: u stupcu "Narudžba" piše "#1234 · 3 osobe · 2 izleta",
 * ćelija ima boju narudžbe (ista u svim izletima), a bilješka (prelazak mišem) pokazuje tko
 * je platio, ukupni iznos i sve izlete s osobama. Mijenja se samo ono što nije isto.
 */
function agLinks_(sh) {
  var ix = agIndex_(sh);
  if (!ix.last) return;
  var nc = agC_('narudzba'), xc = agC_('otkazao') - 1, ic = agC_('iznos') - 1, pc = agC_('prijavio') - 1;
  var notes = sh.getRange(1, nc, ix.last, 1).getNotes();
  var bgs = sh.getRange(1, nc, ix.last, 1).getBackgrounds();
  var by = {};
  for (var r = 2; r <= ix.last; r++) {
    var info = ix.info[r];
    if (!info || !info.o) continue;
    var row = ix.data[r - 1], b = agBlockOf_(ix, r);
    var o = by[info.o] = by[info.o] || { rows: [], tours: [], per: {}, sum: 0, money: false, payer: '', num: '' };
    o.rows.push(r);
    var tk = b ? b.key : '?';
    if (!o.per[tk]) {
      o.per[tk] = [];
      o.tours.push({ key: tk, label: b ? b.base.split(' · ').slice(0, 2).join(' ') : '' });
    }
    o.per[tk].push(agName_(row) + (row[xc] === true ? ' (otkazao/la)' : ''));
    var m = agMoney_(row[ic]);
    if (m !== null) { o.sum += m; o.money = true; }
    if (!o.payer && str_(row[pc]) === '') o.payer = info.orig && info.orig !== '?' ? info.orig : agName_(row);
    if (!o.payer && str_(row[pc]) !== '') o.payer = str_(row[pc]);
    if (!o.num) o.num = (str_(row[nc - 1]).split(' · ')[0] || '').trim() || '#' + info.o;
  }
  Object.keys(by).forEach(function (id) {
    var o = by[id], many = o.rows.length > 1;
    var text = o.num, note = '', color = null;
    if (many) {
      text += ' · ' + agPl_(o.rows.length, 'osoba', 'osobe', 'osoba');
      if (o.tours.length > 1) text += ' · ' + agPl_(o.tours.length, 'izlet', 'izleta', 'izleta');
      color = AG_LINK_COLORS[parseInt(id, 10) % AG_LINK_COLORS.length];
      var lines = ['Narudžba ' + o.num + (o.payer ? ' · platio/la: ' + o.payer : '') + (o.money ? ' · ukupno ' + o.sum.toFixed(2).replace('.', ',') + ' €' : '')];
      o.tours.forEach(function (t) { lines.push((t.label || 'izlet') + ': ' + o.per[t.key].join(', ')); });
      lines.push('Narudžba je plaćena kad je „Uplaćeno” označeno kod svih osoba u svim izletima.');
      note = lines.join('\n');
    }
    o.rows.forEach(function (r) {
      var cell = null;
      var get = function () { return cell || (cell = sh.getRange(r, nc)); };
      if (str_(ix.data[r - 1][nc - 1]) !== text) get().setValue(text);
      if (String(notes[r - 1][0] || '') !== note) get().setNote(note);
      var bg = String(bgs[r - 1][0] || '').toLowerCase();
      if (color && bg !== color) get().setBackground(color);
      if (!color && AG_LINK_COLORS.indexOf(bg) >= 0) get().setBackground(ix.data[r - 1][xc] === true ? AG_GREY : null);
    });
  });
}

/* ---------- Popis za vodiča, dnevni sažetak, bilješke ---------- */

/** Osobe iz blokova izleta s datumom date (YYYY-MM-DD): za popis vodiču. */
function agList_(sh, date) {
  var ix = agIndex_(sh), out = [];
  ix.blocks.forEach(function (b) {
    if (!date || b.date !== date) return;
    var br = agBlockRows_(ix, b), rows = [];
    for (var r = br.first; r <= br.end; r++) {
      if (!agIsPerson_(ix, r)) continue;
      var row = ix.data[r - 1], v = {};
      AG_KEYS.forEach(function (k, i) { if (k) v[k] = k === 'rata2' ? (typeof row[i] === 'boolean' ? row[i] : null) : AG_BOX[k] ? row[i] === true : str_(row[i]); });
      v.br = str_(row[0]);
      v.narudzba = (v.narudzba.split(' · ')[0] || '').trim();
      rows.push(v);
    }
    out.push({ key: b.key, date: b.date, base: b.base, rows: rows });
  });
  return out;
}

/** Brojke blokova s datumom od from do to: prijavljeno (bez otkazanih), uplaćeno, otkazalo. */
function agSummary_(sh, from, to) {
  var ix = agIndex_(sh), out = [], pc = agC_('uplaceno') - 1, xc = agC_('otkazao') - 1;
  ix.blocks.forEach(function (b) {
    if (!b.date || b.date < from || b.date > to) return;
    var br = agBlockRows_(ix, b), n = 0, paid = 0, x = 0, rate = 0, rate2 = 0, rc = agC_('rata2') - 1;
    var ug = 0, pol = 0, polN = 0, uc = agC_('ugovor') - 1, pq = agC_('polica') - 1, oc = agC_('osiguranje') - 1;
    for (var r = br.first; r <= br.end; r++) {
      if (!agIsPerson_(ix, r)) continue;
      var row = ix.data[r - 1];
      if (row[xc] === true) { x++; continue; }
      n++;
      if (row[pc] === true) paid++;
      if (typeof row[rc] === 'boolean') { rate++; if (row[rc] === true) rate2++; }
      if (row[uc] === true) ug++;
      if (row[oc] === true) { polN++; if (row[pq] === true) pol++; }
    }
    out.push({ key: b.key, date: b.date, base: b.base, n: n, paid: paid, x: x, rate: rate, rate2: rate2, ug: ug, pol: pol, polN: polN });
  });
  return out;
}

/** Osobe na popisima izleta od datuma from (bez otkazanih): OIB i e-mail za siječanjsku članarinu. */
function agPeople_(sh, from) {
  var ix = agIndex_(sh), out = [], xc = agC_('otkazao') - 1;
  ix.blocks.forEach(function (b) {
    if (!b.date || agBefore_(b.date, from)) return;
    var br = agBlockRows_(ix, b);
    for (var r = br.first; r <= br.end; r++) {
      var row = ix.data[r - 1];
      if (!agIsPerson_(ix, r) || row[xc] === true) continue;
      out.push({ oib: str_(row[agC_('oib') - 1]), email: str_(row[agC_('email') - 1]).toLowerCase() });
    }
  });
  return out;
}

/** Napomena kod svih osoba narudžbe: items [{o, t}] (npr. "podsjetnik poslan 12.10."). */
function agNoteOrders_(sh, items) {
  var ix = agIndex_(sh), n = 0, by = {};
  items.forEach(function (it) { if (it && it.o && it.t) by[parseInt(it.o, 10)] = String(it.t); });
  for (var r = 2; r <= ix.last; r++) {
    var info = ix.info[r];
    if (!info || !info.o || !by[info.o]) continue;
    agNote_(sh, r, ix.data[r - 1], by[info.o], /^podsjetnik poslan/);
    n++;
  }
  return n;
}

/* ---------- Arhiva i brisanje osobnih podataka ---------- */

var ARH_SHEET = 'Arhiva';
var AG_GDPR = ['oib', 'datum', 'adresa', 'mobitel']; // brišu se 12 mjeseci nakon izleta
var AG_PROTECT = 'Plan A: stupce puni web';

/** Datum za n dana od danas (YYYY-MM-DD). */
function agDay_(n) {
  return new Date(Date.now() + n * 864e5).toISOString().substring(0, 10);
}

/** Je li datum bloka (YYYY-MM-DD ili YYYY-MM iz staroga popisa) prije cut. */
function agBefore_(date, cut) {
  var d = String(date || '');
  if (!/^\d{4}/.test(d)) return false;
  if (d.length === 4) d += '-12-31';
  else if (d.length === 7) d += '-31';
  return d < cut;
}

function agArhSheet_(ss) {
  var a = ss.getSheetByName(ARH_SHEET);
  if (a) return a;
  a = ss.insertSheet(ARH_SHEET);
  a.getRange(1, 1).setValue('Arhiva – izleti stariji od 30 dana (najnoviji na vrhu) · 12 mjeseci nakon izleta brišu se OIB, datum rođenja, adresa i mobitel');
  a.getRange(1, 1, 1, AG_LAST).merge().setFontStyle('italic').setFontColor('#5f6b77').setWrap(true);
  a.setFrozenRows(1);
  a.getRange(1, AG_KEY_COL).setValue('ključ');
  a.hideColumns(AG_KEY_COL);
  var w = [45, 110, 130, 105, 95, 170, 130, 115, 190, 140, 80, 85, 80, 80, 115, 90, 85, 260, 80];
  w.forEach(function (px, i) { a.setColumnWidth(i + 1, px); });
  a.getRange('D:D').setNumberFormat('@');
  a.getRange('H:H').setNumberFormat('@');
  a.protect().setDescription(AG_PROTECT).setWarningOnly(true);
  return a;
}

/** Blokovi izleta starijih od 30 dana sele na list "Arhiva" (najnoviji na vrh). */
function agArchive_(sh) {
  var ix = agIndex_(sh), cut = agDay_(-30);
  var old = ix.blocks.filter(function (b) { return agBefore_(b.date, cut); });
  if (!old.length) return 0;
  var arh = agArhSheet_(sh.getParent());
  old.sort(function (a, b) { return b.row - a.row; }); // odozdo: brojevi redova iznad se ne mijenjaju
  old.forEach(function (b) {
    var next = ix.last + 1;
    ix.blocks.forEach(function (o) { if (o.row > b.row && o.row < next) next = o.row; });
    var n = next - b.row;
    if (n < 1) return;
    arh.insertRowsBefore(2, n);
    sh.getRange(b.row, 1, n, AG_HEAD.length).copyTo(arh.getRange(2, 1, n, AG_HEAD.length));
    if (sh.getMaxRows() - n < 2) sh.insertRowsAfter(sh.getMaxRows(), 1);
    sh.deleteRows(b.row, n);
  });
  return old.length;
}

/** U arhivi: 12 mjeseci nakon izleta obriši OIB, datum rođenja, adresu i mobitel. */
function agGdpr_(arh) {
  var ix = agIndex_(arh), cut = agDay_(-365), n = 0;
  ix.blocks.forEach(function (b) {
    if (!agBefore_(b.date, cut)) return;
    var br = agBlockRows_(ix, b);
    for (var r = br.first; r <= br.end; r++) {
      if (!agIsPerson_(ix, r)) continue;
      AG_GDPR.forEach(function (k) {
        if (str_(ix.data[r - 1][agC_(k) - 1]) === '') return;
        arh.getRange(r, agC_(k)).clearContent();
        ix.data[r - 1][agC_(k) - 1] = '';
        n++;
      });
    }
  });
  return n;
}

/* ---------- Zaštita stupaca (samo upozorenje) ---------- */

function colA1_(c) {
  var s = '';
  for (; c > 0; c = Math.floor((c - 1) / 26)) s = String.fromCharCode(65 + (c - 1) % 26) + s;
  return s;
}

function protect_(sh, ranges) {
  sh.getProtections(SpreadsheetApp.ProtectionType.RANGE).forEach(function (p) {
    if (p.getDescription() === AG_PROTECT) p.remove();
  });
  ranges.forEach(function (a1) { sh.getRange(a1).protect().setDescription(AG_PROTECT).setWarningOnly(true); });
}

/**
 * Stupci koje puni web: kod promjene Google pita "jeste li sigurni" (promjena je i dalje
 * moguća). Tablica članova: Br. i osobni podaci. Tablica za agenciju: podaci osobe, Prijavio/la,
 * Iznos, Narudžba i skriveni ključ. Ime, Prezime, kvačice i Napomena ostaju slobodni.
 */
function zastiti_(ss, ash) {
  var mem = ss.getSheetByName(SHEET_NAME);
  if (mem) protect_(mem, ['A:' + colA1_(HEAD.length)]);
  protect_(ash, [colA1_(agC_('oib')) + ':' + colA1_(agC_('iznos')), colA1_(agC_('narudzba')) + ':' + colA1_(AG_KEY_COL)]);
}
