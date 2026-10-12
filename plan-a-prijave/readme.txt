=== Plan A izleti – prijave ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 0.5.1
License: GPLv2 or later

Vlastiti sustav izleta i prijava za Plan A, po uzoru na WpTravelly i s istim podacima.

== Faza 3 (ova verzija) ==

Stranica izleta, prijava i narudžbe rade i kad je WpTravelly isključen. Dok je WpTravelly
uključen, on ima prednost i sve radi kao dosad (ovaj dio se tada uopće ne učitava).

U mapi ttbm/ je skraćena kopija WpTravellyja 2.3.4 (GPL, mage-people.com), samo ono što
Plan A koristi:
* vrste sadržaja i taksonomije s istim imenima i adresama (/planinarenje-i-avantura/…);
* stranica izleta s predloškom „smart”: isti HTML i CSS kao kod WpTravellyja (galerija, info
  kartice, opis, uključeno/isključeno, aktivnosti, Važno, FAQ, prijava, Zašto s nama, upit,
  vodiči, „Odaberi sljedeću avanturu”), isti tekstovi i prijevodi (domena tour-booking-manager);
* prijava u WooCommerce košaricu i narudžba s istim podacima (_ttbm_id, _ttbm_date,
  _ttbm_ticket_info, _ttbm_service_info …), nakon „Rezervirajte” ravno na blagajnu;
* zapisi ttbm_booking (po osobi) i ttbm_service_booking, promjena stanja narudžbe i kuka
  ttbm_wc_order_status_change, pa se slobodna mjesta i „Popunjeno” računaju isto;
* klase TTBM_Function, TTBM_Global_Function, TTBM_Filter_Pagination i konstante
  TTBM_PLUGIN_DIR/URL/VERSION pod istim imenima (za naše dodatke);
* skriveni WooCommerce proizvod za nove izlete, upiti („Pošaljite upit”) s popisom u adminu,
  klasični uređivač za izlete, „Duplicate”, HTML oblik mailova (kao WpTravelly), dnevno
  osvježavanje datuma izleta, bočna traka stranice izleta;
* stari WpTravellyjevi kratki kodovi ([travel-list] i sl.) prikazuju naš popis izleta
  ([plan-a-izleti]), a hotelski ne prikazuju ništa.

Namjerno izostavljeno: popis želja (srce na karticama), hoteli, WpTravellyjeve postavke,
vlastita blagajna, tražilica i ostali kratki kodovi, prikupljanje podataka o korištenju.

Povratak: WpTravelly se može ponovno uključiti bilo kad (podaci su isti) i odmah preuzima.

== Faza 2 ==

Obrazac za uređivanje izleta na hrvatskom umjesto WpTravellyjevog. Sprema iste podatke
kao WpTravelly, pa WpTravellyjeva stranica izleta, prijava i naši dodaci rade bez promjena.

* Osnovno: lokacija, trajanje, noćenja, najviše osoba, prikaz slobodnih mjesta.
* Termini: jedan ili više datuma, ili jedan termin od–do. Termin s prijavama ne može se
  promijeniti ni obrisati.
* Karte i cijene (i karta „Uplata prve rate” za dvije rate), dodatne usluge (osiguranje).
  Karta ili usluga s prijavama ne može se preimenovati ni obrisati.
* Uključeno / isključeno, aktivnosti, Važno, FAQ, Zašto s nama, kontakt, vodiči,
  galerija, povezani izleti.
* Naslov, opis, glavna slika, kategorija i objava kao na običnim WordPress stranicama.
* „Kopiraj izlet (novi termin)”: nova skica s istim sadržajem i kartama, bez termina.
* Novi izlet preuzima postavke prikaza od zadnjeg objavljenog izleta.

Dok je WpTravelly uključen, njegov obrazac i automatsko spremanje na tom ekranu su
isključeni (bez svog obrasca bi brisali podatke). Isključi li se ovaj dodatak, vraća se
WpTravellyjev obrazac s istim podacima.

Za probu: instalirati samo na kopiji stranice (staging).

== Promjene ==

= 0.5.1 =
* Birač ikonica: sve ikonice Font Awesome 6 Free (1400), traženje i hrvatskim riječima (npr. planina, autobus, hrana); prvo izbor za izlete.
* Postojećim aktivnostima i stavkama uključeno/isključeno bez ikonice jednom se dodijeli odgovarajuća (postojeće se ne mijenjaju).
* Opis vodiča: uređivač s karticama Vizualno i Kod (HTML).

= 0.5.0 =
* Zaslon izleta: nova kartica „Vodiči” – kartice vodiča sa slikom i opisom, kvačica tko vodi izlet, „+ Dodaj vodiča” i „Uredi” (ime, slika, opis) bez napuštanja izleta.
* Aktivnosti i Uključeno/isključeno: dodavanje nove stavke s ikonicom (gumb „+ Dodaj”), ikonice uz stavke, klik na ikonicu je mijenja (isti podatak kao WpTravelly).

= 0.4.2 =
* Zaslon izleta, gornja traka: „U skicu” (objavljeni izlet, uz potvrdu) ili „Spremi skicu” (skica) i „Smeće” (uz potvrdu).

= 0.4.1 =
* Popis izleta: uz stanje gumbi „U skicu” / „Objavi” i „Smeće” (uz potvrdu); engleski tekstovi u poljima obrasca za upit na hrvatskom.

= 0.4.0 =
* Pregledniji zaslon za uređivanje izleta: gornja traka (naziv, stanje, Pregledaj, Spremi), lijevi
  izbornik s karticama (Opis, Termini, Cijene i usluge, Uključeno i aktivnosti, Važno i FAQ, Kontakt
  i vodiči, Galerija, Povezani izleti, Ostalo), desno objava, slika i kategorije. Nakon spremanja
  otvara se ista kartica; obavezno polje u skrivenoj kartici otvori tu karticu.
* Kad je WpTravelly isključen: izbornik na hrvatskom (Svi izleti, Novi izlet, Vodiči, Upiti),
  popis izleta sa slikom, terminom, slobodnim mjestima, prijavljenima, cijenom i stanjem, zbirne
  kartice iznad popisa, kategorije/oznake i upiti na hrvatskom (i obrazac „Pošaljite upit” na
  stranici izleta), kod izleta samo „Kopiraj izlet” (bez WpTravellyjevog „Duplicate”).

= 0.3.0 =
* Faza 3: stranica izleta, prijava i narudžbe bez WpTravellyja (kad je on isključen).

= 0.2.0 =
* Faza 2: obrazac za uređivanje izleta.
