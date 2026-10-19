=== Plan A baneri – vrh naslovnice ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.1.7
License: GPLv2 or later

Vrh naslovnice umjesto slidera: kratki kod [plan-a-naslovnica].

== Što prikazuje ==

* Velika slika s natpisom („Prijave otvorene · N izleta…”), naslovom, podnaslovom, dva gumba (Pogledaj izlete, Plan izleta 2026.) i kvačicama povjerenja.
* Kartica „Sljedeći polazak”: najbliži izleti koji nisu popunjeni (strelice / povlačenje prstom), odbrojavanje do sata polaska, slobodna mjesta i popunjenost, cijena, gumb Prijavi se.
* Traka akcije (npr. „Članarina samo 5 € do 31.12.2026.” – iznos iz Plan A članstva, „još N dana”), sama nestaje nakon zadanog datuma.
* Traka Tinder izleta (najbliži izlet uključen u Izleti → Tinder izleti): brojač ♂/♀ s poticajem, nestaje kad izlet prođe ili se popuni.
* Kartice sljedećih izleta (zadano 60 dana, najmanje 4): datum, trajanje, država, cijena; oznake „Još N mjesta”, „Popunjeno · lista čekanja”, ♂/♀ za Tinder izlet. Na mobitelu se listaju prstom.

Izleti, datumi, cijene i slike dolaze iz dodatka Plan A izleti (isti popis kao stranica Izleti), slobodna mjesta iz sustava izleta
(za Tinder izlet s poticajem umanjena kao na stranici izleta). Odbrojavanje, „još N dana” i skrivanje isteklog radi u pregledniku,
pa je točno i kad je stranica iz predmemorije (SpeedyCache). Slobodna mjesta osvježavaju se kad se očisti predmemorija.

== Postavke ==

Postavke → Plan A naslovnica: slika, tekstovi, gumbi i poveznice, uključivanje svakog dijela, tekst i rok trake akcije, razdoblje i broj kartica, razmak od vrha (prozirno zaglavlje teme).

== Promjene ==

= 1.1.7 =
* Mobitel: tablete slajdova na sredini; bez podnaslova i s manjim razmacima, tako da je bijela kartica (Sljedeći polazak, jedrenje, kviz) cijela vidljiva na prvom ekranu.

= 1.1.6 =
* Mobitel: kvačice (Licencirani vodiči · Male grupe · Polazak iz Zagreba) u jednom redu; tablete Izleti · Jedrenje · Koji izlet? ispod teksta slajda (iznad kartice), na računalu i dalje u redu iznad traka.

= 1.1.5 =
* Mobitel kompaktnije i preglednije: manji naslov i podnaslov, dva gumba jedan pored drugog, manja kartica „Sljedeći polazak” (slika, odbrojavanje, cijena), manje tablete, gumbi Plan izleta/WhatsApp i trake (članarina, Tinder). Na računalu bez promjene.

= 1.1.4 =
* Popravak: slike 2. i 3. slajda nisu se prikazivale (plava podloga) kad je u Flatsomeu uključeno lijeno učitavanje slika – tema je prepisivala adresu slike. Slike se sada dodaju tek u pregledniku, pa ih tema ne dira.

= 1.1.3 =
* Tablete slajdova (Izleti · Jedrenje · Koji izlet?) vidljivije: ikonica, bijeli rub, malo veće, svjetlosni odsjaj („presijavanje”) prelazi preko neaktivnih svakih ~4,5 s, a kad se slider prvi put pojavi, tablete kratko poskoče.

= 1.1.2 =
* Mali gumbi „Plan izleta 2026.” (plavi) i „WhatsApp grupa” (zeleni) u slideru – desno u redu s tabletama slajdova, isti na svim slajdovima (umjesto gumba u zaglavlju teme koji su prelazili preko kartice „Sljedeći polazak”). WhatsApp: upisana poveznica ili zajednička grupa iz Plan A Tinder.

= 1.1.1 =
* Slider se vrti i kad je miš na slici (zastane samo dok je miš na bijeloj kartici ili dok netko koristi kalkulator/kviz).
* Vrti se i na mobitelu, ali samo dok je vrh stranice vidljiv.
* Kratki kod upisan dvaput na istoj stranici prikazuje se samo jednom.

= 1.1.0 =
* Slider: vrh se izmjenjuje (zadano 8 s, pretapanje): 1. izleti (kao do sada), 2. jedrenje – kalkulator „Koliko je po osobi?” (klizač broja osoba, cijena broda odabranog tjedna) i slobodni tjedni iz Plan A jedrenja; „Pošalji zahtjev” otvara stranicu jedrenja s odabranim tjednom i brojem osoba (plan-a-jedrenje 1.9.4), 3. kviz „Koji izlet je za tebe?” (3 pitanja → 1–2 prava izleta).
* Tablete za odabir slajda s crtom koja se puni; staje kad je miš iznad ili kad netko koristi karticu; na mobitelu povlačenje prstom. Slike 2. i 3. slajda učitavaju se tek nakon stranice.
* Trake (članarina, Tinder) i kartice izleta ispod ostaju iste na svim slajdovima.
* Kartice izleta: popunjeni izleti na kraj trake.

= 1.0.0 =
* Prva verzija (19.10.2026.).
