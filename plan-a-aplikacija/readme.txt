=== Plan A aplikacija ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later

Pretvara stranicu u instalabilnu web aplikaciju (PWA). Upute: README.md.

* manifest (Plan A, hr, standalone, ikone iz logotipa),
* service worker: statične datoteke iz predmemorije; /izleti/ i izleti s mreže, predmemorija samo bez interneta;
  košarica, plaćanje, račun, administracija, AJAX, REST i adrese s parametrima nikad se ne spremaju,
* izvanmrežna stranica, poziv na instalaciju (mobitel, pri svakoj posjeti ili od druge posjete), donja navigacija u aplikaciji,
* anonimna statistika instalacija, korištenja i rezervacija izleta iz aplikacije (samo za administratore): Postavke > Plan A aplikacija > Statistika,
* postavke: Postavke > Plan A aplikacija.

Deaktivacija ili isključivanje u postavkama: service worker se kod posjetitelja sam odjavljuje i briše predmemoriju.

== Promjene ==

= 1.5.0 =
* Nova postavka „Podloga ikone i početnog zaslona” (boja iza logotipa na ikoni i na zaslonu dok se aplikacija otvara; npr. tamnoplava #13304a za logotip bez pozadine). Ikone se same izrade iznova.
* Brže otvaranje: ako stranica (popis izleta, izlet) ne stigne s mreže u 2,5 s, odmah se pokaže zadnja spremljena verzija, a nova se sprema u pozadini.
