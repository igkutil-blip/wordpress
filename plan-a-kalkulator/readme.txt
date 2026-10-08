=== Plan A kalkulator ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Planinarski kalkulator: procjena trajanja ture s odmorima, povratka, vode i energije.

== Upotreba ==

* Na stranicu (npr. /planinarski-kalkulator/) umjesto starog HTML bloka stavi shortcode
  [plan-a-kalkulator]. U Flatsome UX Builderu: element "Shortcode" ili "Text".
* Na stranici izleta s unaprijed upisanom rutom i bez naslova:
  [plan-a-kalkulator km="12" uspon="800" spust="800" naslov="no"]
* Poveznica s upisanom rutom: /planinarski-kalkulator/?km=12&uspon=800&spust=800

== Kako računa ==

* Vrijeme hoda: DIN 33466 (4 km/h po ravnom, 300 m uspona i 500 m spusta na sat; veće od
  dvaju vremena plus pola manjeg), prilagođeno kondiciji, grupi, podlozi, ruksaku, vrućini,
  hladnoći i vjetru. Odmori se dodaju posebno, uz raspon (oko −10 % do +15 %).
* Povratak: polazak + ukupno vrijeme; usporedba sa zalaskom sunca za odabrani datum
  (sredina Hrvatske, točnost desetak minuta).
* Energija: potrošnja pri hodu uzbrdo i nizbrdo prema nagibu (Minetti i sur. 2002), za težinu
  tijela i ruksaka, s dodatkom za podlogu i osnovnom potrošnjom tijela.
* Voda: procjena znojenja iz snage, temperature i sunca; preporuka nadoknaditi oko 80 %.
* Napomene: vrućina, hladnoća, jak vjetar, mrak, puno vode za nošenje, dug dan, prestrm unos.

Sve se računa u pregledniku; ništa se ne sprema ni ne šalje. Stilovi su samo unutar
kalkulatora i ne mijenjaju temu. Raspored se prilagođava širini sadržaja (i uz bočnu traku).
