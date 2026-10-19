=== Plan A Tinder i planinarenje ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later

Dodatak za izlete koje označiš (npr. Tinder i planinarenje, Tinder i jedrenje). Ostali izleti ostaju isti.

Uključuje se na ekranu izleta: okvir desno „Tinder – Tko dolazi” → „Uključi za ovaj izlet”. WhatsApp grupa je jedna, zajednička grupa Plan A za sve izlete.

== Što radi ==

* Stranica izleta: na vrhu kućice za prijavu (iznad „Prijavite se” i „Rezervirajte”) kartica „Tko dolazi” –
  broj prijavljenih muškaraca i žena, raspon godina u zagradi, traka popunjenosti (X od Y mjesta, slobodno još Z)
  i zeleni gumb „WhatsApp grupa Plana A”. Imena i ostali podaci se ne prikazuju.
* Raspon godina prikazuje se tek kad je u skupini barem 3 osobe (postavka) – s jednom osobom raspon bi otkrio njezine godine.
* Košarica (blagajna): za svaku osobu u prijavi obavezno spol (Muško / Žensko) i godina rođenja.
  Redoslijed je isti kao „Ostali sudionici” (1. osoba = kupac).
* Stranica „Hvala” nakon prijave: gumb za WhatsApp grupu izleta.
* Admin: Izleti → Tinder izleti – popis prijavljenih (ime, spol, godina, dob, mobitel, e-mail,
  narudžba i stanje, vrijeme prijave). Osoba se ne broji ako je narudžba otkazana/vraćena/neuspjela; „isključi”
  za pojedinu osobu kad otkaže, a narudžba ostaje.
* Prijava i dalje ide u tablicu za agenciju kao za sve izlete (dodatak tu ništa ne mijenja).

== Postavke ==

* Na ekranu izleta (okvir „Tinder – Tko dolazi”): uključi za ovaj izlet (može biti i neobjavljen – skica).
* Izleti → Tinder izleti: popis prijavljenih za svaki uključeni izlet, WhatsApp grupa Plan A (jedna za sve izlete; kad se napuni, upiše se poveznica nove grupe; dodaci iza „?” se brišu),
* Naslov iznad brojača (zadano „Tko dolazi”) i od koliko osoba se prikazuje raspon godina (zadano 3).

== Podaci ==

Uz izlet: _pat_on (uključeno). Uz stavku narudžbe: _pat_osobe (spol, godina rođenja po osobi), _pat_x (osobe koje se ne broje).
Brojke na stranici spremaju se do promjene neke narudžbe (najviše 6 sati). Opcija: plan_a_tinder.

== Promjene ==

= 1.5.0 =
* Košarica traži samo spol (Muško/Žensko); godina rođenja uzima se iz pristupnice: datum rođenja upisan u košarici (novi član) ili iz zapisa člana u Plan A članstvu (po e-mailu). Vrijedi i za ranije narudžbe bez godine.

= 1.4.1 =
* Kartica „Tko dolazi” upola niža (naslov i ukupno u jednom redu, brojke muškaraca/žena u jednom redu, manji WhatsApp gumb, napomena o privatnosti kao opis kartice): na računalu kućica za prijavu opet stane na ekran s gumbom Rezervirajte.

= 1.4.0 =
* Poticaj umanjuje i prikaz slobodnih mjesta izleta (npr. 7/20 umjesto 11/20) i „slobodno još” u kartici – samo prikaz: prijava i dalje ide prema stvarnim mjestima (moguće prijaviti sve stvarno slobodne). Dok ima stvarnih mjesta, prikaz nikad ne pada ispod 1.

= 1.3.0 =
* Poticaj po izletu (Izleti → Tinder izleti, kartica izleta): dodani muškarci i žene s rasponom godina pribrajaju se pravim prijavama na brojaču stranice izleta; kvačicom se uključuje/isključuje. Ne rezervira mjesta.

= 1.2.1 =
* Na gumbu (stranica izleta i „Hvala”) piše samo „WhatsApp grupa Plana A”.

= 1.2.0 =
* Izleti → Tinder izleti: izleti se uključuju i kvačicama u Postavkama (isto kao kvačica na ekranu izleta); na vrhu popis „Uključeni izleti” s brojkama i poveznicama.
* „Naslov kartice” preimenovan u „Naslov iznad brojača” s objašnjenjem (isti na svim izletima, npr. Tko dolazi).

= 1.1.1 =
* WhatsApp grupa je jedna, zajednička grupa Plan A za sve izlete (postavke) – polje za grupu kod izleta maknuto, tekst „WhatsApp grupa Plana A”.

= 1.1.0 =
* Izlet se uključuje kvačicom na ekranu izleta (okvir „Tinder – Tko dolazi”), svaki izlet ima svoju WhatsApp grupu – isti dodatak za Tinder i planinarenje, Tinder i jedrenje i druge.
* Izleti odabrani u 1.0.0 automatski ostaju uključeni. Izbornik: Izleti → Tinder izleti.

= 1.0.0 =
* Prva verzija.
