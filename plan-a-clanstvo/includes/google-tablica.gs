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
        upsert_(sh, d.row, d.godine || [], d.iskaznica, d.napomena);
        out = { ok: true };
      } else if (d.action === 'bulk') {
        (d.rows || []).forEach(function (r) { upsert_(sh, r.row, r.godine || [], r.iskaznica, r.napomena); });
        out = { ok: true, n: (d.rows || []).length };
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

function findRow_(sh, broj) {
  if (!broj || sh.getLastRow() < 2) return 0;
  var f = sh.getRange(2, 1, sh.getLastRow() - 1, 1).createTextFinder(String(broj)).matchEntireCell(true).findNext();
  return f ? f.getRow() : 0;
}

function upsert_(sh, row, godine, iskaznica, napomena) {
  if (!row || !row.broj) return;
  var r = findRow_(sh, row.broj);
  if (!r) {
    r = sh.getLastRow() + 1;
    var heads = sh.getRange(1, 1, 1, sh.getLastColumn()).getValues()[0];
    for (var i = HEAD.length; i < heads.length; i++) {
      if (isBox_(heads[i])) sh.getRange(r, i + 1).insertCheckboxes();
    }
  }
  var values = KEYS.map(function (k) {
    var v = row[k] === undefined || row[k] === null ? '' : String(row[k]);
    // Tekst koji počinje s = + - @ ne smije postati formula.
    return /^[=+\-@]/.test(v) ? "'" + v : v;
  });
  sh.getRange(r, 1, 1, KEYS.length).setValues([values]);
  var st = sh.getRange(r, KEYS.indexOf('status') + 1);
  st.setBackground(row.status === 'Potvrđeno' ? '#d9f2e3' : '#fdebd3');
  (godine || []).forEach(function (y) {
    var c = yearCol_(sh, parseInt(y, 10));
    sh.getRange(r, c).insertCheckboxes().setValue(true);
  });
  if (iskaznica) sh.getRange(r, cardCol_(sh)).insertCheckboxes().setValue(true);
  if (napomena) {
    var n = sh.getRange(r, noteCol_(sh));
    if (n.getValue() === '') n.setValue(/^[=+\-@]/.test(napomena) ? "'" + napomena : String(napomena));
  }
}
