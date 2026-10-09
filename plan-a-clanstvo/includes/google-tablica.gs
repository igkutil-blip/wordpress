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
var SCRIPT_VERSION = 7;
var SITE = '{{SITE}}';
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
      } else if (d.action === 'ag_tours') {
        out = { ok: true, n: agTours_(agSheet_(d.ag), d.tours || []) };
      } else if (d.action === 'ag_add') {
        out = { ok: true, n: agAdd_(agSheet_(d.ag), d.rows || []) };
      } else if (d.action === 'ag_status') {
        out = { ok: true, n: agStatus_(agSheet_(d.ag), d.items || []) };
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
 * osobu samo jednom; obrisani red se ne vraća. Kasnije mijenja samo stupce Uplaćeno,
 * Pristupnica, Članarina i Iskaznica. Napomena i sve što dopišete ostaje.
 * ======================================================================== */
var AG_SHEET = 'Prijave';
var AG_HEAD = ['Br.', 'Ime', 'Prezime', 'OIB', 'Datum rođenja', 'Adresa', 'Mjesto', 'Mobitel', 'E-mail', 'Prijavio/la', 'Iznos', 'Osiguranje', 'Uplaćeno', 'Pristupnica', 'Članarina', 'Iskaznica', 'Napomena', 'Narudžba', 'ključ'];
var AG_KEYS = ['', 'ime', 'prezime', 'oib', 'datum', 'adresa', 'mjesto', 'mobitel', 'email', 'prijavio', 'iznos', 'osiguranje', 'uplaceno', 'pristupnica', 'clanarina', 'iskaznica', 'napomena', 'narudzba'];
var AG_BOX = { osiguranje: 1, uplaceno: 1, clanarina: 1, iskaznica: 1 };
var AG_KEY_COL = AG_HEAD.length; // skriveni stupac
var AG_LAST = AG_HEAD.length - 1; // zadnji vidljivi stupac

/**
 * Brzo osvježavanje: pokreni JEDNOM u uređivaču (Run) i dopusti pristup. Nakon toga, čim
 * netko označi kvačicu "Članarina GGGG" ili "Iskaznica uručena", tablica javi stranici
 * (a stranica osvježi tablicu za agenciju). Ponovno pokretanje ne pravi duple okidače.
 */
function ukljuciBrzoOsvjezavanje() {
  var ss = SpreadsheetApp.getActive();
  ScriptApp.getProjectTriggers().forEach(function (t) {
    if (t.getHandlerFunction() === 'naIzmjenu') ScriptApp.deleteTrigger(t);
  });
  ScriptApp.newTrigger('naIzmjenu').forSpreadsheet(ss).onEdit().create();
  return 'Brzo osvježavanje je uključeno.';
}

/** Okidač: promjena kvačica za članarinu ili iskaznicu → javi stranici za te retke. */
function naIzmjenu(e) {
  try {
    if (!e || !e.range || !SITE || SITE.indexOf('{{') === 0) return;
    var sh = e.range.getSheet();
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
    UrlFetchApp.fetch(SITE, {
      method: 'post',
      contentType: 'application/json',
      payload: JSON.stringify({ secret: SECRET, rows: rows }),
      muteHttpExceptions: true
    });
  } catch (err) {
    console.error(err);
  }
}

/** Ovo pokreni jednom u uređivaču (Run), da Google dopusti pristup tablici za agenciju. */
function ovlasti() {
  return SpreadsheetApp.getActive().getName();
}

function agSheet_(id) {
  if (!id) throw new Error('Tablica za agenciju nije upisana u postavkama.');
  var ss = SpreadsheetApp.openById(id);
  var sh = ss.getSheetByName(AG_SHEET);
  if (!sh) {
    sh = ss.insertSheet(AG_SHEET, 0);
    sh.getRange(1, 1).setValue('Prijave na izlete – Plan A · novi izlet je na vrhu · Uplaćeno, Pristupnica, Članarina i Iskaznica puni stranica (Članarina i Iskaznica iz tablice članova) · Napomena je vaša');
    sh.getRange(1, 1, 1, AG_LAST).merge().setFontStyle('italic').setFontColor('#5f6b77').setWrap(true);
    sh.setFrozenRows(1);
    sh.getRange(1, AG_KEY_COL).setValue('ključ');
    sh.hideColumns(AG_KEY_COL);
    var w = [45, 110, 130, 105, 95, 170, 130, 115, 190, 140, 80, 85, 80, 115, 90, 85, 260, 80];
    w.forEach(function (px, i) { sh.setColumnWidth(i + 1, px); });
    sh.getRange('D:D').setNumberFormat('@');
    sh.getRange('H:H').setNumberFormat('@');
  }
  return sh;
}

function agNorm_(t) {
  return String(t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd').replace(/[^a-z0-9]+/g, ' ').trim();
}

/**
 * Pregled lista: blokovi (naslovni redovi) i osobe (po ključu). Red osobe je i red koji ste
 * dodali ručno (ima ime ili prezime), pa se nove osobe upisuju ispod njega.
 */
function agIndex_(sh) {
  var last = sh.getLastRow();
  var data = last > 0 ? sh.getRange(1, 1, last, AG_HEAD.length).getValues() : [];
  var blocks = [], persons = {};
  data.forEach(function (r, i) {
    var k = String(r[AG_KEY_COL - 1] || '');
    if (k.indexOf('T|') === 0) {
      var p = k.split('|');
      blocks.push({ key: p[1], date: p[2] || '', title: p[3] || '', base: p.slice(4).join('|'), row: i + 1 });
    } else if (k.indexOf('P|') === 0) {
      persons[k.substring(2)] = i + 1;
    }
  });
  return { last: last, data: data, blocks: blocks, persons: persons };
}

function agIsPerson_(ix, r) {
  var row = ix.data[r - 1] || [];
  var k = String(row[AG_KEY_COL - 1] || '');
  return k.indexOf('P|') === 0 || String(row[1] || '') + String(row[2] || '') !== '';
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

function agTitleText_(base, n, paid) {
  return base + ' · prijavljeno ' + n + ' · uplaćeno ' + paid;
}

/** Osvježi brojke u naslovu bloka. */
function agCount_(sh, ix, b) {
  var br = agBlockRows_(ix, b), n = 0, paid = 0, pc = AG_KEYS.indexOf('uplaceno');
  for (var r = br.first; r <= br.end; r++) {
    if (!agIsPerson_(ix, r)) continue;
    n++;
    if (ix.data[r - 1][pc] === true) paid++;
  }
  sh.getRange(b.row, 1).setValue(agTitleText_(b.base, n, paid));
}

function agRefresh_(sh, ix) {
  var nix = agIndex_(sh);
  ix.last = nix.last; ix.data = nix.data; ix.blocks = nix.blocks; ix.persons = nix.persons;
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
  sh.getRange(2, 1, 1, AG_LAST).merge().setValue(agTitleText_(t.base, 0, 0)).setFontWeight('bold').setFontSize(12).setBackground('#12304b').setFontColor('#ffffff').setVerticalAlignment('middle');
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
    var line = AG_KEYS.map(function (k) {
      if (k === '') return br.n + 1;
      if (AG_BOX[k]) return !!v[k];
      var x = v[k] === undefined || v[k] === null ? '' : String(v[k]);
      return /^[=+\-@]/.test(x) ? "'" + x : x;
    });
    sh.getRange(r, 1, 1, AG_HEAD.length).breakApart().clearFormat().clearDataValidations();
    Object.keys(AG_BOX).forEach(function (k) { sh.getRange(r, AG_KEYS.indexOf(k) + 1).insertCheckboxes(); });
    sh.getRange(r, 1, 1, AG_LAST).setValues([line]);
    agPristupnica_(sh.getRange(r, AG_KEYS.indexOf('pristupnica') + 1), v.pristupnica);
    sh.getRange(r, AG_KEY_COL).setValue('P|' + it.pkey);
    if (v.otkazano) sh.getRange(r, 1, 1, AG_LAST).setBackground('#e6e6e6').setFontColor('#777777');
    n++;
    agRefresh_(sh, ix);
    done[b.key] = 1;
  });
  Object.keys(done).forEach(function (k) { var b = agFind_(ix, k); if (b) agCount_(sh, ix, b); });
  return n;
}

/** Osvježavanje: items [{k, u, p, c, i, x}] (null = ne mijenjaj). Obrisani redovi se preskaču. */
function agStatus_(sh, items) {
  var ix = agIndex_(sh), n = 0, touched = {};
  var col = function (k) { return AG_KEYS.indexOf(k); };
  items.forEach(function (it) {
    var r = ix.persons[String(it.k).replace(/\|/g, '_')];
    if (!r) return;
    var row = ix.data[r - 1];
    var set = function (k, val) {
      if (val === null || val === undefined || row[col(k)] === !!val) return;
      sh.getRange(r, col(k) + 1).setValue(!!val);
      row[col(k)] = !!val;
    };
    set('uplaceno', it.u); set('clanarina', it.c); set('iskaznica', it.i);
    if (it.p !== null && it.p !== undefined && String(row[col('pristupnica')]) !== String(it.p)) agPristupnica_(sh.getRange(r, col('pristupnica') + 1), it.p);
    if (it.x) sh.getRange(r, 1, 1, AG_LAST).setBackground('#e6e6e6').setFontColor('#777777');
    n++;
    var b = agBlockOf_(ix, r);
    if (b) touched[b.key] = b;
  });
  Object.keys(touched).forEach(function (k) { agCount_(sh, ix, touched[k]); });
  return n;
}
