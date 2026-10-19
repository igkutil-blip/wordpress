=== Plan A Tinder i planinarenje ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Dodatak samo za odabrane izlete (npr. „Tinder i planinarenje – vikend u Ramića dvorima”). Ostali izleti ostaju isti.

== Što radi ==

* Stranica izleta: na vrhu kućice za prijavu (iznad „Prijavite se” i „Rezervirajte”) kartica „Tko dolazi” –
  broj prijavljenih muškaraca i žena, raspon godina u zagradi, traka popunjenosti (X od Y mjesta, slobodno još Z)
  i zeleni gumb „Pridruži se WhatsApp grupi izleta”. Imena i ostali podaci se ne prikazuju.
* Raspon godina prikazuje se tek kad je u skupini barem 3 osobe (postavka) – s jednom osobom raspon bi otkrio njezine godine.
* Košarica (blagajna): za svaku osobu u prijavi obavezno spol (Muško / Žensko) i godina rođenja.
  Redoslijed je isti kao „Ostali sudionici” (1. osoba = kupac).
* Stranica „Hvala” nakon prijave: gumb za WhatsApp grupu izleta.
* Admin: Izleti → Tinder i planinarenje – postavke i popis prijavljenih (ime, spol, godina, dob, mobitel, e-mail,
  narudžba i stanje, vrijeme prijave). Osoba se ne broji ako je narudžba otkazana/vraćena/neuspjela; „isključi”
  za pojedinu osobu kad otkaže, a narudžba ostaje.
* Prijava i dalje ide u tablicu za agenciju kao za sve izlete (dodatak tu ništa ne mijenja).

== Postavke (Izleti → Tinder i planinarenje) ==

* Izleti: kvačica uz izlet (može biti i neobjavljen – skica).
* WhatsApp grupa: poveznica za poziv (https://chat.whatsapp.com/…); dodaci iza „?” se brišu.
* Naslov kartice (zadano „Tko dolazi”) i od koliko osoba se prikazuje raspon godina (zadano 3).

== Podaci ==

Uz stavku narudžbe: _pat_osobe (spol, godina rođenja po osobi), _pat_x (osobe koje se ne broje).
Brojke na stranici spremaju se do promjene neke narudžbe (najviše 6 sati). Opcija: plan_a_tinder.

== Promjene ==

= 1.0.0 =
* Prva verzija.
