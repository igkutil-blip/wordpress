=== Plan A Tinder i planinarenje ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

Dodatak za izlete koje označiš (npr. Tinder i planinarenje, Tinder i jedrenje). Ostali izleti ostaju isti.

Uključuje se na ekranu izleta: okvir desno „Tinder – Tko dolazi” → „Uključi za ovaj izlet” + WhatsApp grupa izleta.

== Što radi ==

* Stranica izleta: na vrhu kućice za prijavu (iznad „Prijavite se” i „Rezervirajte”) kartica „Tko dolazi” –
  broj prijavljenih muškaraca i žena, raspon godina u zagradi, traka popunjenosti (X od Y mjesta, slobodno još Z)
  i zeleni gumb „Pridruži se WhatsApp grupi izleta”. Imena i ostali podaci se ne prikazuju.
* Raspon godina prikazuje se tek kad je u skupini barem 3 osobe (postavka) – s jednom osobom raspon bi otkrio njezine godine.
* Košarica (blagajna): za svaku osobu u prijavi obavezno spol (Muško / Žensko) i godina rođenja.
  Redoslijed je isti kao „Ostali sudionici” (1. osoba = kupac).
* Stranica „Hvala” nakon prijave: gumb za WhatsApp grupu izleta.
* Admin: Izleti → Tinder izleti – popis prijavljenih (ime, spol, godina, dob, mobitel, e-mail,
  narudžba i stanje, vrijeme prijave). Osoba se ne broji ako je narudžba otkazana/vraćena/neuspjela; „isključi”
  za pojedinu osobu kad otkaže, a narudžba ostaje.
* Prijava i dalje ide u tablicu za agenciju kao za sve izlete (dodatak tu ništa ne mijenja).

== Postavke ==

* Na ekranu izleta (okvir „Tinder – Tko dolazi”): uključi za ovaj izlet (može biti i neobjavljen – skica) i WhatsApp grupa izleta
  (poveznica za poziv https://chat.whatsapp.com/…; dodaci iza „?” se brišu).
* Izleti → Tinder izleti: popis prijavljenih za svaki uključeni izlet, zadana WhatsApp grupa (za izlete bez svoje),
* Naslov kartice (zadano „Tko dolazi”) i od koliko osoba se prikazuje raspon godina (zadano 3).

== Podaci ==

Uz izlet: _pat_on (uključeno), _pat_wa (WhatsApp grupa). Uz stavku narudžbe: _pat_osobe (spol, godina rođenja po osobi), _pat_x (osobe koje se ne broje).
Brojke na stranici spremaju se do promjene neke narudžbe (najviše 6 sati). Opcija: plan_a_tinder.

== Promjene ==

= 1.1.0 =
* Izlet se uključuje kvačicom na ekranu izleta (okvir „Tinder – Tko dolazi”), svaki izlet ima svoju WhatsApp grupu – isti dodatak za Tinder i planinarenje, Tinder i jedrenje i druge.
* Izleti odabrani u 1.0.0 automatski ostaju uključeni. Izbornik: Izleti → Tinder izleti.

= 1.0.0 =
* Prva verzija.
