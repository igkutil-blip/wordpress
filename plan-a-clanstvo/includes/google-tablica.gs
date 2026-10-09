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
var SCRIPT_VERSION = 4;
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
