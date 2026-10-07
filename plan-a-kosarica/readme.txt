=== Plan A košarica ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later

Novi izgled košarice, stranice za plaćanje, završne stranice narudžbe i e-mailova kupcu
(WooCommerce) za izlete iz WpTravellyja.

== Opis ==

* koraci Košarica → Podaci → Plaćanje (na plaćanju se korak "Plaćanje" označi kad
  se dođe do načina plaćanja),
* košarica: kartica izleta sa slikom, nazivom, mjestom (ili državom), datumom i vremenom,
  brojem sudionika po vrsti karte, cijenom po osobi, dodatnim uslugama i smještajem;
  ikona za uklanjanje; "Imaš kod za popust?"; sažetak rezervacije s retcima karata i
  usluga, kuponima, naknadama, porezima i iznosom "Ukupno za uplatu"; gumb
  "Nastavi na plaćanje"; "Pogledaj još izleta" i "Trebaš pomoć? Javi nam se" (WhatsApp);
  gumb teme "Nastavite kupnju" postaje sekundarni gumb "Nastavi s odabirom izleta" (vodi na
  adresu "Pogledaj još izleta", zadano /izleti/),
* prazna košarica (1.2.0): ikona ruksaka, "Tvoja košarica je prazna", gumb "Pogledaj izlete"
  (adresa "Pogledaj još izleta", zadano /izleti/), "Najbliži izleti" (shortcode
  [plan-a-izleti show="3" all_url="/izleti/"], samo ako je aktivan Plan A izleti) i poveznica
  "Javi nam se na WhatsApp" (broj iz postavki, zadano 385959060556); bez koraka; ispod
  "Pogledaj izlete" kuka plan_a_kosarica_empty_actions za dodatne gumbe (1.2.1, npr. "Daruj
  poklon bon" iz dodatka Plan A poklon bon),
* plaćanje (1.3.0): polje "Imaš kod za popust?" u sažetku "Tvoja rezervacija", iznad
  "Ukupno za uplatu" (kod se primjenjuje bez slanja obrasca; zadana poveznica "Imate kupon?"
  na vrhu stranice se ne prikazuje); gumb "Povratak u košaricu", kraći tekst u polju napomena, kartice "Podaci kupca" i "Napomena", "Tvoja rezervacija"
  (slika, mjesto, datum, sudionici, retci cijene, ukupno) i "Način plaćanja",
* na računalu dva stupca (sadržaj i sažetak), na mobitelu jedan; prilagođava se širini
  sadržaja teme.

Ništa se ne izostavlja: predlošci zadržavaju sve kuke i filtre WooCommercea, polja drugih
dodataka, kupone, naknade, poreze, dostavu (ako postoji) i sve načine plaćanja. Podaci
WpTravellyja (blok "Booking Details") prikazuju se strukturirano, a kuke WpTravellyja za
dodatke (npr. podaci o sudionicima) i dalje se ispisuju. Obični proizvodi (ne izleti) imaju
polje za količinu i gumb "Ažuriraj košaricu".

Zamijenjeni predlošci WooCommercea (i teme, npr. Flatsome):
cart/cart.php, cart/cart-empty.php, cart/cart-totals.php, cart/proceed-to-checkout-button.php,
checkout/form-checkout.php, checkout/review-order.php.
Od verzije 1.1.0 i: checkout/thankyou.php, emails/customer-on-hold-order.php,
emails/customer-processing-order.php, emails/customer-completed-order.php.
"Moj račun", e-mailovi administratoru i e-mailovi u obliku običnog teksta se ne mijenjaju.

== Završna stranica i e-mailovi (1.1.0) ==

* koraci Košarica → Podaci → Prijava završena, zelena kvačica, "Hvala, [ime]! Vaša prijava
  je zaprimljena." i broj narudžbe,
* "Što sada?" s tri koraka (uređuju se u postavkama); kod plaćanja karticom prvi korak je
  "Plaćanje je uspješno" i nema bloka za plaćanje,
* "Podaci za plaćanje": 2D kod (HUB3) i podatke za uplatu i dalje ispisuje postojeći dodatak
  za uplatnicu; ovaj dodatak ih ne mijenja, samo kod premješta na vrh bloka (pune širine na
  mobitelu) s natpisom "Skenirajte i platite". Rok plaćanja ispisuje se samo ako je upisan
  u postavkama,
* "Vaš izlet" (izlet, datum, lokacija, broj osoba, cijena, dodatno, ukupno, bilješka),
  "Vaši podaci" i gumb "Predloži ekipi" (ako je aktivan Plan A izleti),
* e-mailovi istog sadržaja: tamnoplavo zaglavlje s logotipom, širina do 600 px;
  "Uplata je zaprimljena, vidimo se na izletu" nakon uplate (bez bloka za plaćanje),
* gumb "Pošalji probni e-mail" u postavkama šalje oba e-maila za zadnju narudžbu na adresu
  administratora.

Usklađeno s dodatkom WSB HUB3 (Branko Borilović): njegov barkod (slika alt="barcode")
prikazuje se na vrhu bloka i na stranici i u e-mailu (ugrađene slike cid: ostaju kakve jesu),
ispod je njegova uplatnica HUB-3A i tekst; gumb "Prikaži veći barkod" se skriva jer je kod
uvijek vidljiv. Ako je u WSB HUB3 odabran prikaz bez teksta (samo uplatnica ili samo
barkod), ispod se dodaju podaci za ručnu uplatu koje ispisuje sam WSB HUB3.

Ako dodatak za uplatnicu u e-mail ne ispisuje podatke za ručnu uplatu kao tekst, e-mail
sadrži poveznicu na stranicu narudžbe s tim podacima.

== Postavke ==

Postavke → Plan A košarica: uključivanje, koraci (koraci teme Flatsome se tada skrivaju),
adresa "Pogledaj još izleta", WhatsApp broj za pomoć, tekst pomoći, boje; završna stranica i
e-mailovi (uključivanje, koraci "Što sada?", rok plaćanja, logotip za e-mail).

Košarica i plaćanje moraju biti klasične stranice sa shortcodeom [woocommerce_cart] i
[woocommerce_checkout] (tako ih koristi Flatsome). Ako stranica koristi WooCommerce
blokove, postavke to prikažu i novi izgled se na njoj ne koristi.

== Uklanjanje ==

Odznačite "Uključen" u postavkama ili deaktivirajte dodatak: odmah se vraća izgled teme.
Brisanje dodatka uklanja samo njegove postavke.
