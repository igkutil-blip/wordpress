# Domestic Spirit – pravila za rad

Korisnik je Igor. Odgovaraj **na hrvatskom**.

- Ovo je zaseban projekt u grani `domestic-spirit` (bez zajedničke povijesti s Plan A granama).
- Ništa iz Plan A projekta (dodaci, pravila, stranice srd-plan-a.hr) ne vrijedi ovdje, osim ako Igor to izričito kaže.
- Promjene za Domestic Spirit idu samo u ovu granu.

## Probna trgovina (`shop/`, zip `domestic-spirits-shop-<verzija>.zip` u korijenu)

Statička trgovina (HTML/CSS/JS, bez poslužitelja): naslovnica prema Igorovu uzorku, trgovina.html (kategorije, proizvođači,
pretraga, poredak), proizvod.html?id=, kosarica.html (košarica u localStorage + podaci za dostavu + potvrda), kontakt.html
(dostava i plaćanje). Podaci u `shop/assets/js/data.js` (`postavke.probno` = narudžbe se ne šalju). Provjera dobi 18+.
Fontovi lokalno (EB Garamond + Inter, fontsource) – bez Googlea. **Nigdje na stranici ni u slikama ne smije pisati izvor
(AI/alat)** – Igor prodaje pod svojim imenom; prije svakog zipa grep i provjera metapodataka slika.
Slike: 7 proizvoda iz Igorova HTML paketa (900×900 JPG, + Limoncello straga, ilustracije posluživanja SVG); naslovnica
iz Igorovih 7 fotografija (hero 1800 + hero-900 za mobitel, 4 kategorije, proizvođači, poklon). Igorovi zipovi imaju
PROCITAJ-ME/PROMPTOVI s opisom izvora – NIKAD ih ne kopirati u shop; slike se uvijek ponovno spremaju (PIL) da nestanu metapodaci.
0.2.0: podaci svih 7 proizvoda iz podaci-o-proizvodima.json (opis, EAN, alkohol, temperatura, posluživanje, mini red, srebrne
medalje Medica/Biska bez slike – ikona). Biska je gorki biljni liker (kategorija likeri) + `takodjer: ['rakije']` da Rakije nisu prazne.
0.3.0: `postavke.usporedba` (true) – gumb „Usporedi s originalom ↗” na proizvodu (`proizvod.original`, nova kartica) i „Pogledaj originalnu
stranicu ↗” ispod gumba na naslovnici; false = nigdje. Adrese iz Igorova paketa (domesticspirits.hr iz sesije blokiran – neprovjerene).

## Pregled na kopiji Plan A (Igor 20.10.2026.)

Shop je na kopiji Plan A stranice kao dodatak `plan-a-ds-proba` (pomoćnik prima samo mape `plan-a-*`): `proba-wp/plan-a-ds-proba.php`
+ mapa `shop/` → zip u privremenoj mapi → POST `https://test.srd-plan-a.hr/wp-json/plan-a-pomocnik/v1/plugin` (zip, activate=1;
PROBA_URL preusmjerava 301, pa POST ide na kanonsku adresu iz `status.home`). Adresa: https://test.srd-plan-a.hr/domestic-spirits/
(→ /wp-content/plugins/plan-a-ds-proba/shop/index.html). Nova verzija shopa = isti postupak (zamijeni). NIKAD na živu Plan A.
0.4.0 (mobitel ≤ 720 px, naslovnica): razmak zaglavlja ispravljen; prijelaz desno na izborniku; vrh = tekst na krem + slika boca 320 px
ispod (preklapanje prijelazom); kategorije i „Izdvajamo” vodoravni niz (scroll-snap); Teran/Biska/Limoncello kompaktni retci; proizvođači
i poklon s tekstom preko slike. Naslovnica 390 px: 3270 → 2270 px. Računalo i stranice proizvoda nepromijenjeni.
0.5.0 (Igor: „napravi sve”, podatke kupaca/dostave izmisli – prezentacija): traka prednosti, oznake (znacke: nagradeno/omiljeno/novo),
srce (lista želja `ds_fav`, trgovina.html?kat=favoriti), brzi pregled, letenje u košaricu, pojava pri listanju (reduced-motion poštovan),
prijedlozi pretrage, kviz (okus/prilika u data.js), filtri (uz=, do=), mini košarica s prijedlogom do besplatne dostave, poklon.html
(kutije iz postavke.poklon, pokloni u `ds_pokloni`, ključ košarice poklon-…), info.html, novo podnožje + novosti, donji izbornik i traka
„U košaricu” na mobitelu, tamniji sivi tekst. Izmišljeno (PRIMJER): kontakt, dostava 4,90 € (besplatno ≥ 60 € samo pbr 10…), pouzeće +1 €,
kutije 4,50/12 €, čestitka 1,50 €, 4 recenzije (`recenzije.primjer` → skrivene kad probno=false).
0.6.0 (Igor: „trešenje artikala mi smeta”): NIKAKVIH pokreta artikala/sličica – maknuto pojavljivanje pri listanju, podizanje kartica,
povećanje slika, letenje u košaricu, poskakivanje broja, „pop”. Ostaje samo: ladica i traka „U košaricu” klize, toast, Limoncello stražnja
etiketa pri prelasku mišem (računalo). Mobitel zbijen (zaglavlje, vrh 392 px, prednosti kao tablete, kategorije 31 %, „Izdvajamo” 46 %,
kviz u jednom redu, manja traka/poklon, proizvod: slika 270 px, gumb u jednom redu). scroll-padding na nizovima (inače snap prilijepi uz rub).

## Klijent i ponuda (21.10.2026.)

Klijent: Mladen (Domestic Spirits, supruga Emilija). Njegov pravi shop domesticspirits.hr = WordPress + **Flatsome** + WooCommerce, izradio ga
Igor prije par godina → redizajn = Flatsome podtema + WooCommerce predlošci, isti URL-ovi. Igor je paušalni obrt (bez PDV-a).
Ponuda po fazama (PDF, izvor HTML samo u privremenoj mapi sesije, nije u gitu): P1 redizajn, P2 košarica+SEO, P3 alati, P4 pokloni/blagdani/
tvrtke, P5 otkrivanje proizvoda, P6 marketing i vjernost, P7 plaćanje i dostava, P8 automatski fiskalizirani računi preko **Sola** (Igor ga koristi, ima API; NE predlagati drugi program) + mail
knjigovodstvu i Emiliji. Uvjeti: 50 % predujam, 50 % po isporuci; Faza 1 rok 1–2 tjedna. Održavanje 100 €/mj.: do 10 novih artikala + obrada fotografija,
briga o radu shopa, mjesečni izvještaj (posjetitelji → košarica → kupnja) s prijedlozima za poboljšanje.
