=== Plan A izleti ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.17.0
License: GPLv2 or later

Shortcode [plan-a-izleti] prikazuje nadolazeće izlete iz dodatka WpTravelly (Tour Booking Manager) u mreži s filtrom po kategorijama i mjesecima.

== Opis ==

* mreža od 3 stupca na računalu, 2 na tabletu i 1 na mobitelu (prijelomne točke kao u temi Flatsome),
* izbornici "Vrsta izleta" (kategorije) i "Termin" (mjeseci sljedećih 12 mjeseci s izletima) s panelom opcija,
  kombiniraju se, filtriranje bez ponovnog učitavanja stranice,
* izleti složeni po prvom budućem terminu; izleti kojima su svi termini prošli ne prikazuju se,
* izleti bez datuma prikazuju se na kraju ("Termin uskoro"), a iza njih popunjeni izleti,
* kartica: slika s ikonama aktivnosti, naziv, datum, trajanje, država, početna cijena i oznaka "Popunjeno".

Upotreba:

    [plan-a-izleti]            – prvih 18 izleta i gumb "Prikaži još izleta"
    [plan-a-izleti show="9"]   – prvih 9 izleta (1–100); ograničenje vrijedi i za rezultat filtra
    [plan-a-izleti step="6"]   – "Prikaži još izleta" dodaje 6 izleta (zadano jednako show)
    [plan-a-izleti more="no"]  – bez gumba "Prikaži još izleta"
    [plan-a-izleti all_url="/izleti/"] – gumb "Pogledaj sve izlete"; ne prikazuje se na toj istoj stranici
    [plan-a-izleti intro="yes"] – uvod "Pronađi svoj izlet." iznad izbornika
    [plan-a-izleti debug="yes"] – dijagnostika (vidi je samo administrator)

Primjeri:

    Naslovnica:     [plan-a-izleti show="3" all_url="/izleti/"]
    Stranica Izleti: [plan-a-izleti show="18"]

Gumbi ispod mreže vide se samo kad trenutni rezultat ima više izleta od početnog
prikaza. Promjena filtra vraća prikaz na show izleta. "Pogledaj sve izlete" prenosi
odabrane filtre u adresu, npr. /izleti/?vrsta=ferrate&termin=2026-11 (vrsta je slug
kategorije, termin mjesec GGGG-MM), a shortcode na toj stranici ih odmah primijeni.

Predloži ekipi (WhatsApp):

* gumb "Predloži ekipi" na stranici izleta u bloku za rezervaciju, iznad njegova naslova
  (predložak "smart": kuka ttbm_smart_registration_controls, isti redoslijed na mobitelu
  i računalu; ostali predlošci: iza gumba za rezervaciju),
* na karticama bijela oznaka "Predloži ekipi" u gornjem lijevom kutu slike; ako natpis
  ne stane (uska kartica ili oznaka "Popunjeno"), prikazuje se samo ikona,
* otvara WhatsApp (wa.me) s porukom: naziv, datum, mjesto ili država, trajanje, cijena i poveznica,
* na mobitelu s Web Share API-jem otvara sustavni izbornik za dijeljenje, uz istaknutu
  sliku izleta ako uređaj podržava dijeljenje datoteka; ako dijeljenje ne uspije, otvara wa.me,
* poveznica nosi utm_source=whatsapp&utm_medium=share&utm_campaign=predlozi_ekipi,
* na stranici izleta ispisuje Open Graph oznake (og:title, og:description, og:image…)
  samo ako ih već ne ispisuje SEO dodatak (Yoast, Rank Math, AIOSEO, SEOPress,
  The SEO Framework, Slim SEO, Squirrly, Jetpack). Filtar: plan_a_izleti_og_handled.

Slika kartice za dijeljenje (od verzije 1.13.0):

* uz poruku se dijeli slika kartice izleta (JPG, 1080 × 1350 px): istaknuta slika, oznaka
  "Idemo zajedno?", naziv, datum, mjesto, trajanje, cijena, ikone aktivnosti, logotip i adresa,
* izrađuje se na poslužitelju (GD s FreeTypeom), font Lato (assets/fonts, licenca OFL),
* sprema se u wp-content/uploads/plan-a-izleti/kartice/izlet-<ID>-v<verzija>.jpg i sama se ponovno izrađuje
  kad se promijeni naziv, datum, mjesto, trajanje, cijena, slika ili aktivnosti izleta,
* ako izrada ne uspije (ili slika još nije izrađena), dijeli se istaknuta slika izleta,
* raspored (1.14.0): sigurna margina 80 px lijevo, desno i dolje, oznaka 60 px od vrha i lijevo;
  naziv ~64 px (dva reda, po potrebi manji font), podaci ~36 px u dva stupca, aktivnosti 72 px,
  logotip ~70 px; pri ažuriranju na novu verziju sve se slike izrađuju ponovno pod novim nazivom,
* Postavke → Plan A izleti: logotip (bez odabira logotip teme), boja donjeg dijela
  (bez odabira tamna boja zaglavlja teme), pregled i "Ponovno izradi sve slike kartica",
* dijeljenje: mobitel s podrškom za datoteke = slika + poruka; iPhone ili bez podrške =
  samo poruka s poveznicom; računalo = wa.me s porukom.

Pregled prije slanja (od verzije 1.15.0):

* klik na "Predloži ekipi" najprije pokaže "Ovo ćeš poslati ekipi:" sa slikom kartice i
  tekstom poruke (kao WhatsApp poruka, *zvjezdice* podebljano); jedini gumb
  "Pošalji u WhatsApp" pokreće dijeljenje (pravila za iPhone i računalo ostaju),
* mobitel: panel s dna ekrana (najviše 85 % visine, gumb uvijek na dnu), zatvara se dodirom
  izvan panela ili povlačenjem prema dolje; računalo: prozor na sredini, zatvara se klikom
  izvan njega i tipkom Esc; na oba je i gumb × u gornjem desnom kutu (od 1.15.2),
* Postavke → Plan A izleti → "Pregled prije slanja": uvijek, pri svakom kliku (zadano), ili nikad.
  (Od 1.15.1 nema ograničenja "prva 3 puta".)

Kategorije: aktivnosti (ttbm_tour_activities, kao WpTravelly filtar "Category"),
a ako ih nema, kategorije izleta (ttbm_tour_cat).

Zahtijeva aktivne dodatke WpTravelly (tour-booking-manager) i WooCommerce.
Ne mijenja WpTravelly, temu ni bazu podataka; sprema samo privremeni cache
(transient `plan_a_izleti_cache`) koji se briše pri deaktivaciji i brisanju dodatka.

== Plan izleta (od 1.17.0) ==

Shortcode [plan-a-plan-izleta] zamjenjuje ručno pisani popis na stranici plana izleta.

* Administracija → Plan izleta: najava izleta = naziv, datum od–do, vodiči, kratka napomena i
  "Izlet na webu". Uvoz popisa: zalijepi stari popis (datum, naziv, "Vodiči: …") i izleti se
  sami dodaju; isti naziv i datum se ne dodaju dvaput.
* Izlet na webu se pronalazi sam: objavljeni izlet u WpTravellyju s istim datumom početka i
  sličnim nazivom (zajednička riječ). Može se odabrati ručno ili "Ne povezuj".
* Objavljeni izleti koji nemaju najavu dolaze u plan sami (osim izleta s više od 24 termina).
* Plan po mjesecima: blok s datumom (dan u tjednu, dani, mjesec), naziv, raspon, vodiči,
  trajanje, cijena i status: Prijave otvorene (gumb "Prijavi se"), Popunjeno, Uskoro (gumb
  "Javi mi kad bude objavljen") i Održano (sklopivi popis održanih izleta ove godine).
* Traka mjeseci s brojem izleta, sažetak "U planu je još N izleta…".
* "Plan u mom kalendaru": pretplata (Google kalendar, iPhone/Mac/Outlook preko webcal) ili
  .ics datoteka; svaki izlet ima i svoj gumb za kalendar. Adresa: /?plan_a_ics=plan
* "Javi mi kad bude objavljen": e-mail adresa se sprema uz najavu; čim se izlet poveže s
  objavljenim izletom, šalje se jedan e-mail i adresa se briše (provjera minutu nakon
  spremanja izleta i jednom dnevno). Za prošle izlete adrese se brišu bez slanja.
  Zaštita: skriveno polje, najviše 6 prijava u 10 minuta po IP adresi, 1000 adresa po izletu.
  Uključeno u WordPressov alat za brisanje osobnih podataka.
* Atributi: godina="2026" (samo ta godina), odrzani="no", izleti_url="/izleti/" (prazno = bez gumba).

== Promjene ==

= 1.17.0 =
* Plan izleta (vidi gore).


* 1.16.0 – kuke plan_a_izleti_before_grid (iznad kartica) i plan_a_izleti_after_list (ispod popisa) za dodatne sadržaje, npr. traku poklon bona; atribut bon="no" isključuje traku poklon bona na tom popisu.
