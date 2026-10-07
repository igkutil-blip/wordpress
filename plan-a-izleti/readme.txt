=== Plan A izleti ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.10.1
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
* otvara WhatsApp (wa.me) s porukom: naziv, datum, mjesto ili država, cijena i poveznica,
* na mobitelu s Web Share API-jem otvara sustavni izbornik za dijeljenje, uz istaknutu
  sliku izleta ako uređaj podržava dijeljenje datoteka; ako dijeljenje ne uspije, otvara wa.me,
* poveznica nosi utm_source=whatsapp&utm_medium=share&utm_campaign=predlozi_ekipi,
* na stranici izleta ispisuje Open Graph oznake (og:title, og:description, og:image…)
  samo ako ih već ne ispisuje SEO dodatak (Yoast, Rank Math, AIOSEO, SEOPress,
  The SEO Framework, Slim SEO, Squirrly, Jetpack). Filtar: plan_a_izleti_og_handled.

Kategorije: aktivnosti (ttbm_tour_activities, kao WpTravelly filtar "Category"),
a ako ih nema, kategorije izleta (ttbm_tour_cat).

Zahtijeva aktivne dodatke WpTravelly (tour-booking-manager) i WooCommerce.
Ne mijenja WpTravelly, temu ni bazu podataka; sprema samo privremeni cache
(transient `plan_a_izleti_cache`) koji se briše pri deaktivaciji i brisanju dodatka.
