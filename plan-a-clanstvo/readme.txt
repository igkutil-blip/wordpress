=== Plan A članstvo ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.6.3
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

= 1.6.3 =
* E-mail je prvi i kad drugi dodatak mijenja redoslijed polja; članu se skrivaju samo polja iz pristupnice te tvrtka i dodatak adrese (npr. "Trebam R1 račun" ostaje).

= 1.6.2 =
* Poruka nakon radnje prenosi se i u adresi stranice (potpisano); uz naslov se vidi broj verzije.

= 1.6.1 =
* Poruke nakon radnji (Provjeri vezu, uvoz, osvježavanje …) ispisuju se izravno na stranici dodatka, da ih drugi dodaci ne sakriju.

= 1.6.0 =
* Članstvo u košarici (Postavke → "Članstvo u košarici"): e-mail je prvo polje i prepoznaje člana (podaci iz pristupnice upisuju se na poslužitelju, u preglednik se ne šalju); nečlan ispunjava pristupnicu u košarici (Čeka potvrdu, gumb za potvrdu u e-mailu o narudžbi); za više osoba traže se ostali sudionici (nečlanovi dobivaju svoj e-mail za potvrdu); članarina se provjerava za godinu izleta i šalje se e-mail s 2D kodom (jednom po godini).
* Blok "Članstvo Plan A" u e-mailu kupcu i na stranici "Hvala"; stara narančasta napomena je uklonjena.
* Tablica za agenciju "Prijave na izlete": jedan list, blok po izletu (novi na vrhu), osoba po redu sa svim podacima; svaka osoba se upisuje jednom (obrisani red se ne vraća); Uplaćeno, Pristupnica, Članarina i Iskaznica se osvježavaju. Početni popis iz CSV-a.
* Skripta u tablici v6 (potrebno kopirati i objaviti novu verziju te jednom pokrenuti "ovlasti").

= 1.5.1 =
* Blok "Članstvo Plan A" u e-mailu "Nova narudžba": pristupnica, članarina za godinu izleta, iskaznica; za kupca bez pristupnice moguće podudaranje po imenu.

= 1.5.0 =
* Stranica svaki sat čita kvačice "Članarina GGGG" i "Iskaznica uručena" iz tablice (i gumb "Osvježi kvačice iz tablice"). Potrebna nova skripta (v5).

= 1.4.0 =
* Gumb "Poredaj brojeve po datumu prijave" (Članovi → Uvoz članova): Br. redom po datumu prijave, na stranici i u tablici; kvačice idu sa svojim redom.
* Uvoz ažurira datum prijave i potvrde i postojećim članovima.
* Potrebno: nova skripta u tablici (v4) – kopirati i objaviti kao New version.

= 1.3.5 =
* Ispravak: veći paketi za tablicu dobivali su od Googlea HTTP 400. Preusmjeravanje Google skripte sada se otvara kao GET.

= 1.3.4 =
* "Pošalji neposlane u tablicu" i "Pošalji sve članove" rade s trakom napretka, paket po paket.
* "Provjeri vezu" javlja je li u tablici stara skripta; uz staru ili sporu skriptu šalje se u malim paketima.

= 1.3.3 =
* Skripta u tablici upisuje cijeli paket odjednom (prije red po red), pa slanje više ne istekne.
  Potrebno: kopirati novu skriptu u tablicu i objaviti novu verziju.

= 1.3.2 =
* Uvoz ide u dijelovima s trakom napretka (ne prekida ga vremensko ograničenje poslužitelja) i nastavlja gdje je stao.
* Na stranici uvoza vidi se broj članova i rezultat zadnjeg uvoza.

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
