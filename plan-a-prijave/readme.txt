=== Plan A izleti – prijave ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later

Vlastiti sustav izleta i prijava za Plan A, po uzoru na WpTravelly i s istim podacima.

== Faza 2 (ova verzija) ==

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

= 0.2.0 =
* Faza 2: obrazac za uređivanje izleta.
