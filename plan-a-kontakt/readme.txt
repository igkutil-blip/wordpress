=== Plan A kontakt ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Obrazac za upite umjesto Contact Form 7 / WPForms. Poruka stiže e-mailom u istom izgledu kao
ostali Plan A mailovi (odgovara se izravno iz maila) i sprema se u admin → Poruke.

== Upotreba ==

* Stranica Kontakt: umjesto [contact-form-7 …] upiši [plan-a-kontakt]
  (stranica već ima bijelu karticu „Pošalji nam upit”).
* Bilo gdje drugdje s bijelom karticom: [plan-a-kontakt okvir="da"]
* Stalna tema (npr. anketa u objavi „Poziv na avanturu”), bez izbora izleta:
  [plan-a-kontakt predmet="Zanimanje za izlet s boravkom u divljini" gumb="Pošalji" okvir="da"]
* Bez izleta / bez mobitela: [plan-a-kontakt izlet="ne" telefon="ne"]
* Poveznica /kontakt/?izlet=ID unaprijed odabere izlet.

Polja: ime i prezime, e-mail, mobitel (nije obavezno), izlet (nadolazeći izleti, nije obavezno), poruka.

== Postavke (Poruke → Postavke) ==

* Primatelj (prazno = adresa pošiljatelja WooCommerceovih mailova).
* Potvrda pošiljatelju „Hvala na poruci” (tekst se može promijeniti; poruka pošiljatelja se ne ponavlja).
* Koliko mjeseci se čuvaju poruke (zadano 24), starije se brišu automatski.

== Zaštita od spama ==

Bez vanjskih servisa i kolačića: skriveno polje, slanje samo s JavaScriptom i ne prije 3 s,
najviše 5 poruka u 10 minuta s iste adrese i 40 na sat ukupno, najviše 2 poveznice u poruci.
Radi i na stranicama iz predmemorije (SpeedyCache).

== Promjene ==

= 1.0.0 =
* Prvo izdanje.
