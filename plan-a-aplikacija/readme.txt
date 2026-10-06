=== Plan A aplikacija ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Pretvara stranicu u instalabilnu web aplikaciju (PWA). Upute: README.md.

* manifest (Plan A, hr, standalone, ikone iz logotipa),
* service worker: statične datoteke iz predmemorije; /izleti/ i izleti s mreže, predmemorija samo bez interneta;
  košarica, plaćanje, račun, administracija, AJAX, REST i adrese s parametrima nikad se ne spremaju,
* izvanmrežna stranica, poziv na instalaciju (mobitel, od druge posjete), donja navigacija u aplikaciji,
* postavke: Postavke > Plan A aplikacija.

Deaktivacija ili isključivanje u postavkama: service worker se kod posjetitelja sam odjavljuje i briše predmemoriju.
