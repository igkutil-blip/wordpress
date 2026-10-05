=== Plan A izleti ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later

Shortcode [plan-a-izleti] prikazuje nadolazeće izlete iz dodatka WpTravelly (Tour Booking Manager) u mreži s filtrom po kategorijama i mjesecima.

== Opis ==

* mreža od 3 stupca na računalu, 2 na tabletu i 1 na mobitelu (prijelomne točke kao u temi Flatsome),
* izbornici "Vrsta izleta" (kategorije) i "Termin" (mjeseci sljedećih 12 mjeseci s izletima) s panelom opcija,
  kombiniraju se, filtriranje bez ponovnog učitavanja stranice,
* izleti složeni po prvom budućem terminu; izleti kojima su svi termini prošli ne prikazuju se,
* izleti bez datuma prikazuju se na kraju ("Termin uskoro"),
* kartica: slika s ikonama aktivnosti, naziv, datum, trajanje, država, početna cijena i oznaka "Popunjeno".

Upotreba:

    [plan-a-izleti]            – najviše 18 izleta
    [plan-a-izleti show="9"]   – najviše 9 izleta u početnom prikazu (1–100); uz filtar se vide svi odgovarajući
    [plan-a-izleti intro="yes"] – uvod "Pronađi svoj izlet." iznad izbornika
    [plan-a-izleti debug="yes"] – dijagnostika (vidi je samo administrator)

Kategorije: aktivnosti (ttbm_tour_activities, kao WpTravelly filtar "Category"),
a ako ih nema, kategorije izleta (ttbm_tour_cat).

Zahtijeva aktivne dodatke WpTravelly (tour-booking-manager) i WooCommerce.
Ne mijenja WpTravelly, temu ni bazu podataka; sprema samo privremeni cache
(transient `plan_a_izleti_cache`) koji se briše pri deaktivaciji i brisanju dodatka.
