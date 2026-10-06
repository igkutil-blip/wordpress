=== Plan A izleti ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.8.0
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

Kategorije: aktivnosti (ttbm_tour_activities, kao WpTravelly filtar "Category"),
a ako ih nema, kategorije izleta (ttbm_tour_cat).

Zahtijeva aktivne dodatke WpTravelly (tour-booking-manager) i WooCommerce.
Ne mijenja WpTravelly, temu ni bazu podataka; sprema samo privremeni cache
(transient `plan_a_izleti_cache`) koji se briše pri deaktivaciji i brisanju dodatka.
