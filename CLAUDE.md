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

## WordPress dodaci za Mladenov shop – PRAVILO (Igor 21.10.2026.)

Svi dodaci koje radimo za Domestic Spirits nalaze se pod **jednim izbornikom „Domestic”** u lijevoj traci WordPress admina:
prvi napravljeni dodatak stvara izbornik, svaki sljedeći samo dodaje svoju podstavku (ništa po drugim izbornicima).
- Zajednička datoteka `wp-dodaci/zajednicko/domestic-menu.php` (klasa `Domestic_Menu`) kopira se u svaki dodatak kao
  `includes/domestic-menu.php` i mora biti ista u svima (kao `Plan_A_Email` kod Plan A). Podstavka: `Domestic_Menu::add( slug, naslov, callback, poredak )`.
- **Proizvođač svih dodataka je obrt Vertikal** (Igor 21.10.2026.): u zaglavlju dodatka `Author: Vertikal, obrt` (nikad osobno ime ni alat).
- Dodaci: mapa `wp-dodaci/domestic-<naziv>/`, glavna datoteka `domestic-<naziv>.php`, prefiks klasa `Domestic_`, prava `manage_woocommerce`.
- Popis dodataka (dopunjavati):
  - **domestic-proizvod 0.1.0** (u izradi, 21.10.2026.): stranica proizvoda u novom izgledu (template_include za označene ili sve proizvode),
    kartica „Domestic” na proizvodu (meta `_dsp_*`: eyebrow, naslov, razlozi, posluživanje, nagrade, omiljeno, sidrena), podaci iz atributa
    (marka, vrsta, zapremnina, alkohol, podrijetlo, temperatura, gastro, pakiranje), €/l, najniža cijena 30 dana kod akcije (`_dsp_cijene`),
    Domestic → Proizvodi – provjera (+ „Podaci iz opisa” → atributi i EAN), Domestic → Stranica proizvoda (postavka `domestic_proizvod`),
    kratki kod `[domestic_proizvod dio=…]` za UX Builder. Na kopiji Plan A kao `plan-a-ds-proizvod` + 7 skrivenih probnih proizvoda
    (kategorija „Domestic proba”, SKU DSP-…; uvoz skriptom preko WC REST). Nova adresa za pregled: stranica https://test.srd-plan-a.hr/domestic-proizvod/
    (WP stranica 9095, popis 7 proizvoda s poveznicama); stari demo /domestic-spirits/ (plan-a-ds-proba) ostaje netaknut. OTVORENO: fontovi na kopiji se ne učitavaju (status error),
    lokalno rade. Lokalna proba: WP 7.1.3 + WC 11.2.1 + SQLite (git klon), WooCommerce „coming soon” isključiti.

## Provjera dobi – mGrađani (Zakon o trgovini NN 59/26, rok bio 15.9.2026.)

Domestic Spirits registriran u e-Građani (client ID u mailu Emiliji; NE u git – upisuje se u postavke dodatka). OIDC: response_type code,
PKCE S256, token_endpoint_auth `none` (bez tajne), RS256 (JWKS), subject pairwise, scope **`age.alcohol`** (potvrđeno iz well-known i tehničke upute), claim
**`age.alcohol`** true/false; ostali claimovi sub(pairwise)/iss/aud/exp/iat/nonce. Odgovor tokena samo `id_token` (JWT RS256). Test: https://mgradjani-test.gov.hr/idp,
produkcija: https://mgradjani.gov.hr/idp (produkcija tek nakon odobrenja). **Registrirani redirect URI (iz obrasca): `https://domesticspirits.hr/age-verification/callback`**
– jedini; za kopiju stranice treba od ministarstva tražiti dodatni URI. Provjera prije plaćanja, za svaku narudžbu; odbijanje = nema narudžbe;
na narudžbu samo „provjereno + vrijeme”. Iz sesije su gov.hr i domesticspirits.hr blokirani (Igor dodaje domene u postavke okruženja).
Plan: dodaci domestic-pomocnik (kopija: mailovi preusmjereni, noindex, REST za zip) i domestic-provjera-dobi. Rad samo na kopiji Mladenove stranice
(Igor radi klon); živa = samo čitanje (DS_URL / DS_WP_USER / DS_WP_APP_PASSWORD), kopija DS_PROBA_*.
Tehnička uputa MinGo (PDF, 7 str., /UserDocsImages/Trgovina/Tehnicka_uputa_provjera punoljetnosti.pdf): authorize s response_type=code, client_id,
redirect_uri, scope=age.alcohol, state, nonce, code_challenge(S256); povratak ?code&state (state obavezno provjeriti); POST /idp/token
(grant_type, code, redirect_uri, client_id, code_verifier) iz backenda; provjeriti potpis (JWKS), aud, nonce, exp, iss; code_verifier nikad u
pregledniku/URL-u/localStorage; ne pohranjivati osobne podatke. Testni korisnik: JEDAN e-mail (Google Play / Apple račun) javiti s registrirane
adrese; nakon testiranja službena potvrda produkcijske spremnosti. Kontakti: tehnika **m-gradjani@mpudt.hr**, poslovno evidencija-trgovci@mingo.hr.

## Živa Mladenova stranica – javno pročitano 22.10.2026. (bez prijave)

WP 7.1.3, Flatsome + **već postoji podtema `flatsome-domestic-spirits`**, WooCommerce, Rank Math, Site Kit (GA), CTX Feed (Google feed), Jetpack,
Complianz, Contact Form 7, **Age Gate** (samo prozorčić 18+ – NE zadovoljava zakon). Solo REST namespace se ne vidi (provjeriti s prijavom).
`/age-verification/callback` = 404 (ništa još nije instalirano). 149 proizvoda (uključujući poklon pakete, npr. „Baš domaći”, „Mini STRAVA”),
**bez atributa** (podaci u opisima → alat „Podaci iz opisa”), adrese `/proizvod/<slug>/`. Blagajna **klasična** (`/checkout/` kratki kod),
košarica `/cart/` (Flatsome tekst o besplatnoj dostavi iznad 60 € u Zagrebačkoj županiji).

## DNS i probna kopija (22.10.2026.)

DNS domesticspirits.hr preseljen na **Igorov Cloudflare račun** (Free): stari nameserveri `jason`/`kim.ns.cloudflare.com` (nepoznat stari
račun – za povratak), novi `elly`/`lochlan.ns.cloudflare.com` upisani kod registrara (sučelje za .hr domenu, „Spremi i produži domenu”
je besplatno). DNSSEC isključen (nema DS). Poslužitelj MyDataKnox cp064, IP **185.62.73.116**. Svi A zapisi → 185.62.73.116; Proxied samo
root i www, ostalo (mail, ftp, cpanel, whm, webmail, webdisk, autoconfig, autodiscover, cpcalendars, cpcontacts, **proba**) DNS only.
MX/SPF/DKIM/SRV uvezeni iz živog DNS-a. SSL/TLS = Full. cPanel Zone Editor se NE koristi (nije autoritativan).
Probna kopija: proba.domesticspirits.hr (Softaculous klon) RADI od 22.10. 21 h: Let's Encrypt (AutoSSL), sve poveznice na proba (0 na živu),
noindex/nofollow (Rank Math), REST /wp-json 200, nginx. Pristup iz sesije dopušten. Čeka: DS_PROBA_* (aplikacijska lozinka) za rad preko REST-a.
