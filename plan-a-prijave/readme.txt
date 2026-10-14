=== Plan A izleti – prijave ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 0.7.14
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

= 0.7.14 =
* Unos izleta (kolege): osiguranje se uvijek upisuje samo – do 355 € cijene 9,95 €, iznad toga 2,8 % cijene zaokruženo na cijeli euro naviše (npr. 480 € → 14 €). Iznos je i u mailu Igoru; nema više napomene „osiguranje upiši sam”.

= 0.7.13 =
* Ispod gumba Rezervirajte: „▶ Pogledaj kako se prijaviti (1 min)” – kratki video u prozorčiću kao poruka na WhatsAppu (pokreće se bez zvuka, gumb za zvuk).

= 0.7.12 =
* Admin, Važno: blok o granicama prikazuje pitanja po zemljama točno kako će biti na stranici („… (BiH)”, „… (Hrvatska)”), odmah pri promjeni zemlje u Osnovnim podacima (bez spremanja); zemlja bez teksta jasno označena.
* Razmaci u tekstu ostaju: prazan red = novi odlomak (Važno za izlet, Važno), s razmakom između odlomaka i popisa; poveznice i telefoni i u Važno.

= 0.7.11 =
* Unos izleta (kolege): u zalijepljenom tekstu poveznice ostaju (web, mail, telefon); adresa napisana kao tekst (www…, https://…) postaje poveznica.
* Već objavljeni izleti jednom dobivaju izračunat minimalni broj sudionika i rok otkaza (70 % mjesta, rok po trajanju) i zaključaju se; novi izleti i dalje pri prvoj objavi.
* Osnovni podaci: Lokacija i Druga zemlja u istoj liniji.

= 0.7.10 =
* Granične formalnosti: za svaku zemlju poseban blok „Granične, vizne i zdravstvene formalnosti (Zemlja)” u Važno (i kad je samo jedna zemlja). U adminu upozorenje kad odabrana zemlja nema upisan tekst.
* Stranica izleta: sav upisani tekst (opis, Važno za izlet, Važno, Zašto s nama) iste veličine 16 px i prored na računalu i mobitelu; podebljano i kurziv ostaju.

= 0.7.9 =
* Lokacija „Italija - Francuska” razdvojena na dvije zemlje (Italija i Francuska): izleti s tom lokacijom dobivaju lokaciju Italija i drugu zemlju Francuska, pa se u Graničnim formalnostima ispisuju obje zemlje.

= 0.7.8 =
* Minimalni broj sudionika i rok obavijesti o otkazu računaju se sami: minimum = 70 % mjesta (zaokruženo na niže), rok prema trajanju (manje od 2 dana → 48 sati, 2–6 dana → 7 dana, više od 6 dana → 20 dana). Blok u Važno se ne uređuje ručno; pri prvoj objavi se zaključa i više se ne mijenja (ugovor s agencijom). Izleti objavljeni prije ovoga ostaju kakvi jesu.

= 0.7.7 =
* Granične, vizne i zdravstvene formalnosti: nova kartica u uređivanju izleta – jedan tekst po zemlji, zajednički za sve izlete (+ Dodaj zemlju). Na stranici izleta blok „Granične, vizne i zdravstvene formalnosti” u Važno dobiva tekst zemlje iz lokacije (i druge zemlje); zemlja bez teksta ne mijenja ništa. Prvo punjenje iz najnovijeg izleta u svakoj zemlji.
* Druga zemlja (nije obavezno) u Osnovnim podacima i u obrascu „Unos izleta” za kolege.

= 0.7.6 =
* Osnovni podaci izleta: samo lokacija i trajanje u danima (maknuti noćenja, izbor sati/minuta, „Najviše osoba” i kvačica slobodnih mjesta). „Najviše osoba” na stranici = mjesta na kartama; slobodna mjesta (npr. 3/25) uvijek se prikazuju.
* Lijeva traka: „Uključeno i aktivnosti” ispod „Važno”.

= 0.7.5 =
* „Popunjeno”: izlet na popisu izleta dobiva crvenu oznaku POPUNJENO i ide na kraj popisa (filtar plan_a_izleti_sold_out); gumb osvježi popis i predmemoriju stranice izleta. Na stranici izleta uz cijenu piše „Popunjeno” (umjesto „Sold out” / crtice).
* Lijeva traka izleta: Vodiči i Galerija odmah ispod „Važno za izlet”.

= 0.7.4 =
* Ekran izleta na mobitelu: gornja traka (naziv, stanje, 4 gumba u redu), izbornik dijelova u jednom redu koji se pomiče prstom i ostaje na vrhu (aktivni dio u sredini), polja u jednom ili dva stupca, karte/usluge/termini s natpisom iznad svakog polja.
* Termini u adminu: upisuje se samo početak (i sat) – kraj se računa iz trajanja u danima, prijave su otvorene do početka izleta (polja „Kraj”, „Prijave do” i „Datum povratka” maknuta).
* Popis izleta: gumb „Popunjeno” zatvara prijave (izlet na stranici 0 slobodnih / popunjen, košarica odbija prijavu), „Otvori prijave” vraća; broj mjesta karata se ne dira.

= 0.7.3 =
* Unos izleta: galerija nije ograničena na 4 slike – gumb „+ Dodaj sliku” dodaje okvir (najviše 20 slika u galeriji), dodatni okviri imaju × za brisanje. Najmanje 4 slike ostaju obavezne; ispravak učita sve slike izleta.

= 0.7.2 =
* Unos izleta: kad kolega odabere „Tko unosi izlet”, na dnu vidi samo svoje poslane izlete. Dok izlet čeka pregled, gumb „Ispravi” vraća ga u obrazac sa svim podacima i slikama; „Spremi ispravke” sprema isti izlet (i dalje na čekanju), Igoru stiže mail „Ispravljen izlet na pregled”. Nepromijenjene slike ostaju iste, zamijenjene ili pomaknute se ponovno spreme, a stare brišu iz medija. Objavljeni izlet kolege više ne mogu mijenjati.

= 0.7.1 =
* Unos izleta: kolege upisuju samo početak i sat polaska – kraj se računa iz broja dana, prijave su otvorene do početka izleta.
* Brojevi telefona u tekstu izleta i odgovorima „Važno za izlet” postaju poveznice za poziv (tel:), riječ „pristupnica” (PRISTUPNICA, pristupnicu…) poveznica na stranicu pristupnice. Kod unosa kolega sprema se u tekst; za sve izlete (i postojeće) radi i pri prikazu stranice. IBAN, OIB, datumi i postojeće poveznice se ne diraju.

= 0.7.0 =
* Novo: „Unos izleta” za kolege – stranica s kratkim kodom [plan-a-unos-izleta] (Plan A → Unos izleta (kolege): šifra, mail, stranica, predložak). Zajednička šifra (prijava vrijedi 60 dana), noindex, izvan sitemapa i predmemorije.
* Obrazac za kolege: tko unosi, naslov i tekst (samo zalijepi – podebljano, odlomci i popisi ostaju, font i veličina se izjednače), lokacija, trajanje u danima, jedan termin (datumi 21.11.2026., sat 24 h), cijena, broj mjesta, prva rata (opcija), uključeno/nije uključeno i aktivnosti (bez dodavanja), odgovori na fiksna pitanja „Važno za izlet” (obavezni), vodiči, glavna slika + 4 slike u okvirima 3:2 (povlačenje mišem/prstom, povećanje; sprema se 1800 × 1200 JPG), napomena za Igora.
* Izlet se sprema kao „Na čekanju” s istim podacima kao obrazac u adminu; Važno, Zašto s nama, kontakt i naziv osiguranja iz predloška; osiguranje 9,95 € do 350 €, iznad toga napomena; kategorija prema trajanju; opis slika (alt) iz naslova. Igoru mail s gumbom „Pregledaj i objavi”, na ekranu izleta tko je unio, napomena i upozorenja.
* Predložak za nove izlete: skica koja se nikad ne objavljuje (ne nudi se ni među povezanim izletima).

= 0.6.4 =
* Uređivanje izleta: „Osnovni podaci” (lokacija, trajanje, noćenja, najviše osoba, slobodna mjesta) imaju svoju karticu u lijevom izborniku, između „Opis” i „Termini”.

= 0.6.3 =
* SEO naslov izleta završava s „ – Plan A planinarski izleti” (ako stane u 60 znakova, inače „ – Plan A” ili bez), umjesto „- Plan A”. Uz dodatak Plan A SEO.

= 0.6.2 =
* SEO (Rank Math) se popuni čim se izlet otvori za uređivanje, ne tek pri spremanju (samo prazna polja). U kartici „Ostalo” okvir „SEO iz podataka izleta” pokazuje složenu ključnu riječ, naslov i opis i ima gumb „Upiši ovo u SEO”, koji prepiše i ranije ručno upisano.

= 0.6.1 =
* Ikona iznad naslova maila je okrugla slika (u Gmailu na mobitelu se krug više ne razvlači u elipsu), ista u svim mailovima.

= 0.6.0 =
* Upiti s izleta: mailovi na hrvatskom u zajedničkom Plan A izgledu (nama, potvrda kupcu, odgovor kupcu) umjesto WpTravellyjevih na engleskom.

= 0.5.8 =
* Velika slika izleta: uz lokaciju i datum termina (npr. „21.–22.11.2026.”, uz broj ostalih termina); strelice za slike u gornjem dijelu slike – naslov u dva reda ne preklapa se ni na mobitelu.
* Standard za slike: vodoravno 3:2, 1800 × 1200 px (najmanje 1200 × 800), motiv u sredini. Upute u kartici Galerija, slike koje odstupaju označene žutim rubom. Za veliku sliku nova veličina 1800 px (brže učitavanje).

= 0.5.7 =
* Stranica izleta: tekst u opisu, „Važno za izlet”, „Važno” i „Zašto s nama” crn; „Važno za izlet” iste veličine kao „Važno” (16 px).

= 0.5.6 =
* Lokacija (zemlja): padajući izbornik svih postojećih lokacija i „+ Dodaj novu lokaciju…”.
* Tekst uvijek u fontu i veličini stranice: pri spremanju se iz opisa izleta, blokova „Važno”/„Važno za izlet” i opisa vodiča miču font, veličina, prored, boja i pozadina (iz Worda, maila, weba); podebljano, kurziv, popisi, naslovi, poveznice i poravnanje ostaju. Lijepljenje u uređivač također bez stilova. Za već upisane izlete isto na stranici izleta radi CSS.

= 0.5.5 =
* Zajednička mjesta: sve karte izleta troše ista mjesta (zbroj mjesta karata s punom cijenom); karta „prve rate” nema svoja mjesta. Na stranici izleta odabir svih karata zajedno ne može prijeći slobodna mjesta, a košarica to provjerava i na poslužitelju.
* Dodatne usluge (osiguranje) više nemaju količinu – nisu ograničene.
* Obrazac: kod karte za prvu ratu umjesto mjesta piše „zajednička”, ispod karata „Mjesta na izletu: N”.

= 0.5.4 =
* Zaslon izleta: „Važno i FAQ” razdvojeno na kartice „Važno za izlet” (bivši FAQ) i „Važno”.
* Stranica izleta (bez WpTravellyja): prvo „Važno za izlet”, zatim „Važno”; svi blokovi u „Važno” zatvoreni, otvaraju se klikom.

= 0.5.3 =
* SEO (Rank Math) iz izleta: pri spremanju se popune ključna riječ (naziv izleta + aktivnosti), SEO naslov (naziv, trajanje, termin) i opis za Google (uvod iz teksta, termin, trajanje, cijena) te opis (alt) glavne slike ako ga nema. Ručno upisano se ne mijenja.
* Nove Plan A ikonice: penjačka kaciga, oprema (kaciga i karabiner), ruksak; „Oprema” jednom dobiva kacigu.

= 0.5.2 =
* Vlastite Plan A ikonice (SVG): alpinizam, ferata, kajak, povijest (tvrđava), sportsko penjanje, špiljarenje, zimski uspon (cepin) – prve u biraču, rade i na stranici izleta.
* Aktivnostima Plan A jednom postavljene odgovarajuće ikonice (i biciklizam, jedrenje, planinarenje).
* Obrazac izleta prikazuje i starije ikonice „mi mi-…” (WpTravelly).

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
