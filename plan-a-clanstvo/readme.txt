=== Plan A članstvo ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.3.1
License: GPLv2 or later

Pristupnica za članstvo u udruzi Plan A, potvrda klikom u e-mailu, 2D kod za članarinu,
automatski upis u Google tablicu i provjera članstva pri prijavi na izlet.

== Postavljanje ==

1. Instaliraj i aktiviraj dodatak.
2. Na stranici /pristupnica/ obriši sav stari sadržaj i stavi samo [plan-a-pristupnica]
   u Flatsome HTML blok (stranica se složi sama).
3. Članovi → Postavke i tablica: slijedi upute za Google tablicu (skripta, Deploy, URL),
   klikni "Provjeri vezu" pa "Pošalji sve članove u tablicu".
4. SpeedyCache: izuzmi adrese s parametrima potvrda i pristupnica (npr. /uclanjenje/?potvrda=…).

== Kako radi ==

1. Član ispuni pristupnicu (OIB i datum se provjeravaju, za mlađe od 18 polja za roditelja).
2. Stiže e-mail s gumbom "Potvrđujem pristupnicu". Klik otvara stranicu "Pristupnica je
   potvrđena!" (potvrda se šalje sama s te stranice, pa je ne potvrđuju programi koji u pošti
   provjeravaju poveznice).
3. Nakon potvrde stiže e-mail s podacima za članarinu i 2D kodom na račun udruge.
4. Svaka pristupnica i potvrda sama se upisuje u Google tablicu (stupci, Status, Datum
   potvrde). Stupce "Članarina GGGG" s kvačicama ispunjavate ručno; stranica ih ne dira.
5. Nepotvrđeni dobiju jedan podsjetnik 3 dana nakon e-maila za potvrdu. Nikad se ne brišu sami:
   ostaju sa statusom "Čeka potvrdu".
6. Pri prijavi na izlet e-mail kupca uspoređuje se s potvrđenim pristupnicama. Ako ga nema,
   kupac na stranici "Hvala" i u e-mailu dobiva poveznicu na pristupnicu, a u narudžbama piše
   "Član: ne". Prijava se ne blokira.

Zaštita: podatke vide samo administratori; skriveno polje i ograničenje po IP adresi protiv
botova; poveznica za potvrdu je nasumična i vrijedi 7 dana; GDPR izvoz i brisanje po e-mailu.

== Promjene ==

= 1.3.1 =
* Uvoz članova je zasebna stavka izbornika: Članovi → Uvoz članova.

= 1.3.0 =
* Uvoz članova iz CSV-a (Postavke i tablica → 4. Uvoz članova). Uvoz ne šalje e-mailove i ne duplira članove.
* Stupac "Napomena" u tablici (iza "Iskaznica uručena"); popunjava se samo pri uvozu i samo ako je prazan.
* Slanje u tablicu ide u paketima; gumb "Pošalji neposlane u tablicu" nastavlja gdje je stalo.
* Potrebno: kopirati novu skriptu u tablicu i objaviti novu verziju (Deploy → Manage deployments → Edit → New version).

= 1.2.2 =
* Nepotvrđene pristupnice se nikad ne brišu same; ostaju sa statusom "Čeka potvrdu".
  Podsjetnik se može isključiti (0 dana).

= 1.2.1 =
* Podsjetnik i brisanje nepotvrđenih računaju se od e-maila za potvrdu, ne od datuma prijave;
  uvezeni članovi koji još nisu dobili e-mail ostaju netaknuti.
* Skupna radnja u popisu članova: "Pošalji e-mail za potvrdu (nepotvrđenima)".

= 1.2.0 =
* Google tablica: stupac "Iskaznica uručena" s kvačicama, uvijek na kraju (stupac za novu godinu
  članarine dodaje se ispred njega). Kvačice se označavaju ručno; pri uvozu se popune iz starih tablica.
  Postojeću skriptu treba zamijeniti novom i objaviti novu verziju (Manage deployments → Edit → New version).

= 1.1.3 =
* Ista osoba se ne duplira: isti OIB, ili (kod krivo upisanog OIB-a) isti e-mail, ime i datum
  rođenja. Djeca s roditeljskim e-mailom ostaju zasebni članovi.

= 1.1.2 =
* E-mailovi se šalju s adrese pošiljatelja iz WooCommercea (npr. info@srd-plan-a.hr) umjesto
  wordpress@…, koju poslužitelji pošte često odbace. Gumb "Pošalji mi probni e-mail" i zapis
  zadnjeg poslanog e-maila u Postavkama.

= 1.1.1 =
* Poveznica za potvrdu i napomena na naplati vode na objavljenu stranicu sa shortcodeom
  (npr. /pristupnica/), i prije nego je itko otvori.
* Provjera članstva pri prijavi na izlet zadano je isključena; uključi je u Postavkama nakon
  uvoza postojećih članova (inače bi svi kupci dobili napomenu da nisu članovi).

= 1.1.0 =
* [plan-a-pristupnica] prikazuje cijelu stranicu: uvod s pogodnostima i članarinom, "Kako postati
  član" u tri koraka i obrazac. Na računalu dva stupca, na mobitelu jedno ispod drugog s gumbom
  "Ispuni pristupnicu". Tekstovi se uređuju u Postavkama. Samo obrazac: [plan-a-pristupnica izgled="obrazac"].

= 1.0.0 =
* Prva inačica.
