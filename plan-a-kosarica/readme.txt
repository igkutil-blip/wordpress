=== Plan A košarica ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Novi izgled košarice i stranice za plaćanje (WooCommerce) za izlete iz WpTravellyja.

== Opis ==

* koraci Košarica → Podaci → Plaćanje (na plaćanju se korak "Plaćanje" označi kad
  se dođe do načina plaćanja),
* košarica: kartica izleta sa slikom, nazivom, mjestom (ili državom), datumom i vremenom,
  brojem sudionika po vrsti karte, cijenom po osobi, dodatnim uslugama i smještajem;
  ikona za uklanjanje; "Imaš kod za popust?"; sažetak rezervacije s retcima karata i
  usluga, kuponima, naknadama, porezima i iznosom "Ukupno za uplatu"; gumb
  "Nastavi na plaćanje"; "Pogledaj još izleta" i "Trebaš pomoć? Javi nam se" (WhatsApp),
* plaćanje: "Povratak u košaricu", kartice "Podaci kupca" i "Napomena", "Tvoja rezervacija"
  (slika, mjesto, datum, sudionici, retci cijene, ukupno) i "Način plaćanja",
* na računalu dva stupca (sadržaj i sažetak), na mobitelu jedan; prilagođava se širini
  sadržaja teme.

Ništa se ne izostavlja: predlošci zadržavaju sve kuke i filtre WooCommercea, polja drugih
dodataka, kupone, naknade, poreze, dostavu (ako postoji) i sve načine plaćanja. Podaci
WpTravellyja (blok "Booking Details") prikazuju se strukturirano, a kuke WpTravellyja za
dodatke (npr. podaci o sudionicima) i dalje se ispisuju. Obični proizvodi (ne izleti) imaju
polje za količinu i gumb "Ažuriraj košaricu".

Zamijenjeni predlošci WooCommercea (i teme, npr. Flatsome):
cart/cart.php, cart/cart-totals.php, cart/proceed-to-checkout-button.php,
checkout/form-checkout.php, checkout/review-order.php.
Stranica zahvale, e-poruke i "Moj račun" se ne mijenjaju.

== Postavke ==

Postavke → Plan A košarica: uključivanje, koraci (koraci teme Flatsome se tada skrivaju),
adresa "Pogledaj još izleta", WhatsApp broj za pomoć, tekst pomoći, boje.

Košarica i plaćanje moraju biti klasične stranice sa shortcodeom [woocommerce_cart] i
[woocommerce_checkout] (tako ih koristi Flatsome). Ako stranica koristi WooCommerce
blokove, postavke to prikažu i novi izgled se na njoj ne koristi.

== Uklanjanje ==

Odznačite "Uključen" u postavkama ili deaktivirajte dodatak: odmah se vraća izgled teme.
Brisanje dodatka uklanja samo njegove postavke.
