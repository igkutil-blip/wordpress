# Domestic Spirit – pravila za rad

Korisnik je Igor. Odgovaraj **na hrvatskom**.

- Ovo je zaseban projekt u grani `domestic-spirit` (bez zajedničke povijesti s Plan A granama).
- Ništa iz Plan A projekta (dodaci, pravila, stranice srd-plan-a.hr) ne vrijedi ovdje, osim ako Igor to izričito kaže.
- Promjene za Domestic Spirit idu samo u ovu granu.

## ⛔ ADRESE SE NE MIJENJAJU – STROGO ZABRANJENO (Igor 23.10.2026., SEO godinama gradi)

- Sve adrese ostaju iste: proizvodi (`/proizvod/<slug>/`), kategorije, stranice, blog, **i adrese slika** (`/wp-content/uploads/…`).
  Ne mijenjati slugove, permalinke, nazive datoteka slika ni strukturu stalnih veza.
- Slike (logotipi, proizvodi): stari naziv datoteke, naslov i alt tekst **ostaju**. Nova slika se postavlja **zamjenom datoteke na istoj
  adresi** (pomoćnik `POST domestic-pomocnik/v1/medij/<id>`, polje `datoteka`, isti format), ne novim prilogom.
- Nove adrese smiju nastati samo za nove stvari (npr. `/age-verification/callback`). Kod prijenosa na živu: nakon zamjene slika očistiti
  **nginx predmemoriju (cPanel → NGINX Manager → Clear Cache; poslužitelj drži stare slike pod istom adresom)** i Cloudflare (root i www Proxied).
  Na probi pomoćnik 0.3.1 zamijenjenim slikama dodaje `?v=<vrijeme zamjene>` (opcija `domestic_pomocnik_verzije`) – datoteka i naziv isti.

## Slike proizvoda – uvoz (Igor 23.10.2026.)

- Redoslijed u galeriji: **1. ambijentalna (glavna slika), 2. studijska, 3. kako poslužiti**, zatim ostale (medalje i dr.).
- Ambijentalna ide u **staru glavnu sliku na istoj adresi** (pomoćnik `medij/<id>`, isti format – npr. .png ostaje .png).
  Nove slike dobivaju prefiks: `studio-<stari naziv>.jpg`, `posluzivanje-<stari naziv>.jpg`. Postojeće (medalje) se zamjenjuju na istim adresama.
- Stari alt/naslov ostaju; novima se upisuje alt u istom stilu. Sve slike prije uvoza ponovno spremiti (PIL) – paketi imaju C2PA/OpenAI
  zapise; UPUTE/PROMPTI iz paketa se nikad ne učitavaju.
- `2025/03/liker-domesticspirits.jpg` (4233) je zajednički natpis u opisima 43 proizvoda – NE zamjenjivati slikom za jedan proizvod.
- Napravljeno: Avellana 0,70 l (751): 4276 ambijent (staro ime liker-od-ljesnjaka-07L.png), 4879 studio, 4880 posluživanje, 2047 i 3311
  medalje (obrađene; koriste ih i 0,20 l, 1 l, Avellana poklon paket). 4276 je i u galeriji „Avellana u pratnji” (4841).
  23.10. druga verzija po pravilima (paket s alt.txt): 4276/4879/4880 zamijenjeni, alt 4880 „… – kako poslužiti, boca uz čašu likera”;
  medalje iste (nisu dirane). Ambijent PNG: PIL quantize MEDIANCUT 256 + Floyd-Steinberg (2,5 MB → 0,83 MB), izvedenice `paleta=1`
  (510 px 380 → 160 KB). **Postupak za sve PNG fotografije na starim adresama.** Pravila za izradu slika dana Igoru (format 1:1 1600, JPG 85 %,
  redoslijed, kompozicija, nazivi studio-/posluzivanje-, alt.txt, bez izvora).
  Igor: druga verzija loša (boca 68 % visine, zatamnjeno, posluživanje = foto s čašom) → vraćena prva verzija (avellana-cisto, ambijent
  kvantiziran). Važeća pravila: `identitet/PRAVILA-ZA-SLIKE.txt` (zamjenjuju sva prethodna): studijska boca 85 % visine (vrh 6 %, dno 91 %),
  ambijent 75–80 % (vrh 10 %, dno 88 %), sve isto postavljeno, bez zatamnjivanja, posluživanje = ilustrirana kartica s ikonama, sve JPG.
  domestic-proizvod 0.6.2: oznake ispod sličica isključene (`slike` = '').
- Avellana 0,20 l (2734): 3829 ambijent (PNG 256 boja + paleta), 4881 studio, 4882 posluživanje, 3311 medalja; **2815 (zadnja strana boce,
  stara firma) maknuta iz galerije – ne vraćati** (prilog ostaje). Avellana 1 l (748): 3891 ambijent, 4883 studio, 4884 posluživanje,
  2047 + 3311 medalje (Igor: dodati stare medalje). Ispravak (veća boca): 3829, 3891, 4881, 4883 zamijenjeni na istim adresama.
- Avellana Premium 0,70 l (3697): 3828 ambijent (PNG + paleta), 4886 studio, 4887 posluživanje, zatim 3700 2.jpg, 3701 3.jpg, 3702 2x.jpg
  (detalji etikete, zamijenjeni na istim adresama; Igor: 3700 i 3701 maknuti iz galerije Premiuma – ostaje samo 3702 na mahovini) i 4532 Best in Class 2025 (zamijenjena; koriste je i Premium box 3727 i „Gospodski” 4527).
  Prazni altovi popunjeni (alt.txt; medalja: „Zlatna medalja Best in Class – CroSpirit Best Spirit Awards 2025.”). Količine posluživanja
  Ispravak (paket „ispravak”): 3828 ambijent (unutrašnjost, luk), 4886 studio, 4887 nova kartica posluživanja (Na ledu 0,03 l, Espresso koktel
  0,015 l + 0,045 l votke + 0,045 l espressa + 0,0075 l šeć. sirupa, Sladoled s kavom 0,02 l) zamijenjeni na istim adresama; isti tekst upisan u
  meta `_dsp_posluzivanje` (kartica „Domestic”, ikone casa/koktel/kolac) → sekcija „Kako poslužiti” na stranici. Količine = autorski prijedlozi
  (napomena iz paketa: recepti prilagođeni prema Frangelico) – Mladen treba potvrditi.

- BOGATI Medica 0,20 l (3005, 23.10.): 4203 ambijent (`2022/02/medica-bogati-02L.png`, PNG 256 boja + paleta=1), 4967 studio, 4968 posluživanje;
  ikone s kartice 4969 aperitiv, 4970 slastica, 4971 digestiv (puni krug r 176 oko (322|799|1277, 628), izvan kruga prozirno) → `_dsp_posluzivanje`
  (Kao aperitiv / Uz slasticu / Kao digestiv, 0,03 l). Paket: količine 0,03 l i medenjaci = autorski prijedlozi – Mladen da potvrdi. Medalje nema u paketu
  (opis spominje srebro CROSPIRIT 2026.).

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

## Logotipi i boje (Igor 23.10.2026.)

`identitet/` = očišćeni logotipi + README gdje koji ide. Na probi (23.10.) novi logotipi su upisani **u stare datoteke** (iste adrese):
prilog 4047 `2025/03/Why-We-Do-This-Photoroom.png` = logo zaglavlja (Flatsome `site_logo`, `logo_width` 300, ujedno slika u mailovima),
3653 `2024/03/domestic-spirits-logo.jpg` = logo na kremi 1200×201 (Flatsome `site_logo_dark`, Rank Math slika za dijeljenje i logo organizacije),
3766 `2024/11/cropped-domestic-spirits.png` = ikona stranice (+ 32/180/192/270). Novi prilozi 4871–4876 obrisani.
Službene boje: tamna #29231F, zlatna #AD8D53, krem #F7F3EB, bordo #702833.
Napomena za Igora: Rank Math „sameAs” Facebook pokazuje na facebook.com/primores.hr (vjerojatno pogrešno).

## Za provjeru s Mladenom (Igor 23.10.2026.)

Sve nejasnoće s proizvoda (medalje bez slike, godine, autorske količine posluživanja, podaci iz bloga) upisivati u `izdanja/ZA-PROVJERU.md`
– Igor na kraju obrade svih proizvoda traži popis za provjeru.

## Izdanja: proba i živa (Igor 23.10.2026.)

`izdanja/proba/` = zipovi trenutno na proba.domesticspirits.hr (+ STANJE.md), `izdanja/ziva/` = odobreni i instalirani na živoj (+ STANJE.md),
izvorni kod u `wp-dodaci/`. Pakiranje: `alati/zip-dodatak.sh <naziv>`. Postupak u `izdanja/README.md`. Uvijek ažurirati STANJE.md.

## WordPress dodaci za Mladenov shop – PRAVILO (Igor 21.10.2026.)

Svi dodaci koje radimo za Domestic Spirits nalaze se pod **jednim izbornikom „Domestic”** u lijevoj traci WordPress admina:
prvi napravljeni dodatak stvara izbornik, svaki sljedeći samo dodaje svoju podstavku (ništa po drugim izbornicima).
- Zajednička datoteka `wp-dodaci/zajednicko/domestic-menu.php` (klasa `Domestic_Menu`) kopira se u svaki dodatak kao
  `includes/domestic-menu.php` i mora biti ista u svima (kao `Plan_A_Email` kod Plan A). Podstavka: `Domestic_Menu::add( slug, naslov, callback, poredak )`.
- **Proizvođač svih dodataka je Vertikal** (Igor 21. i 23.10.2026.): u zaglavlju dodatka `Author: Vertikal` (bez „obrt”; nikad osobno ime ni alat).
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
  - **domestic-proizvod 0.2.0** (23.10.2026., Igorov mobilni dizajn s chatgpt.site uzorka): krem #f6f1e9, okvir #efe7dc, crte #e2d6c3,
    bordo #6b2b35, zlatna #9b7b45; putanja „Svi proizvodi / kategorija / MARKA”; naslov MARKA + podnaslov (bez atributa Marka: velika slova
    na početku naziva), red „0,70 l · % vol. · podrijetlo”; galerija = klizač (scroll-snap) s oznakom „U ambijentu · 1 / 3”, strelice, povećanje,
    sličice s oznakama iz postavke `slike` („U ambijentu|Studijska|Kako poslužiti” = glavna slika + galerija redom); okvir kupnje: „Odaberi
    zapremninu” = srodni proizvodi istog naziva bez zapremnine u istoj kategoriji (Data::siblings, filtar domestic_proizvod_zapremnine),
    velika cijena + €/l + PDV, sidrena cijena izvučena iz price_html ispod, količina −/+ (Flatsomeovi −/+ skriveni), „Dodaj u košaricu” s
    ikonom, dostava (+ `dostava_url`); uvodna rečenica (preskače „Naziv: vrijednost”), red nagrada, poveznice na sekcije, <details> harmonika
    (O proizvodu otvoreno, Podaci, Kako poslužiti, Nagrade + godine, Recenzije); traka na dnu (≤720 px) kad gumb nije vidljiv.
    Računalo: galerija lijevo (sticky), naslov i kupnja desno. Zapremnina i iz naziva („0,70L”). Flatsome „Filtriraj” skriven.
  - **domestic-proizvod 0.3.0** (23.10., Igorov HTML prijedlog `index.html`): računalo 3 stupca (galerija | naslov + sažetak: uvod,
    Vrsta/Podrijetlo, nagrade, „Kako poslužiti ↓” | okvir kupnje), ≤1150 px 2 stupca, ≤760 px: naslov, galerija 240 px, kupnja, uvod.
    Georgia + Arial, boje #f7f3eb/#f0e9de/#702833/#96703c. „Na ovoj stranici” + sekcije u 2 stupca (Opis, Podaci | Kako poslužiti
    s 3. slikom kao uputama, Nagrade, Dostava i plaćanje); na mobitelu otvoren samo Opis. Povezani: 3 kartice „Još domaćih priča.”
    **Podaci iz opisa samo za prikaz** (Data::opis: kratki+dugi opis iz baze 'edit' + wpautop – klasični uređivač sprema retke bez <br>;
    retci „Oznaka: vrijednost” → tablica Podaci i attr() kad nema atributa; „Opis:” → tekst; slike i spanovi izbačeni iz priče).
  - **domestic-proizvod 0.4.0** (23.10., Igor: dugački nazivi i puno slika ružni; „najvažnije na prvom ekranu”): računalo 2 stupca
    (galerija ljepljiva lijevo, visina slike clamp(320px, 100vh−400px, 600px) | desno: marka · kategorija, **puni naziv bez zapremnine**
    jednim H1 (clamp 26–36 px, text-wrap balance), oznake 0,70 l / % vol. / podrijetlo, okvir: **cijena prva**, zapremnine, količina + gumb,
    povjerenje (na stanju + rok, besplatna dostava, sigurno pakiranje), zatim uvod, nagrade, „Kako poslužiti ↓”). Sličice = vodoravna traka
    (3 vidljive, strelice listaju po 3, ostale lazy). Mobitel: slika preko cijele širine min(46vh,360px), traka 3,6 sličice, naslov, cijena,
    gumb; traka „Dodaj u košaricu” na dnu kad gumb nije vidljiv (prvi ekran uvijek ima cijenu i gumb). Sekcije i povezani kao 0.3.
  - **domestic-proizvod 0.6.0** (Igor: brzi pregled imao stari izgled): Flatsome „Brzi pregled” (AJAX `flatsome_quickview` →
    `content-single-product-lightbox`) preuzet filtrima wc_get_template_part/wc_get_template → `templates/quickview.php` =
    `Front::quickview()`: galerija + naslov (h2, poveznica), cijena, zapremnine, količina + gumb, povjerenje, uvod, „Sve o proizvodu →”.
    CSS dolazi kao <link> u odgovoru; JS (proizvod.js) na Flatsome stranicama, init(root) + MutationObserver za ubačeni sadržaj.
  - **domestic-proizvod 0.5.0** (23.10., Igor: jednobrazni tekstovi, „neka unose kako hoće”): klasa `Domestic_Proizvod_Tekst` –
    SAMO PRIKAZ (front; baza, admin i slugovi netaknuti). Filtri: the_title (product), woocommerce_product_get_name, short_description,
    the_content (product), get_term/get_terms (product_cat/tag). Naziv: razmaci, zvjezdica na kraju van, „0,75” bez jedinice → 0,75 l,
    zapremnine „0,75 l”/„1 l”/„4 × 0,05 l”, navodnici „…”, „ – ”, rječnik NAZIVI (Malvazija istarska, Pinot bijeli/sivi, sur lie, Rosé,
    Cuvée, Exclusive, Mix…). Tekst (opis, podaci; adrese/e-mail netaknute): rječnik ISPRAVCI (tipfeleri nađeni hunspell-hr provjerom:
    vrunsko, dugačije, đem, bariq, Ganitno→Granatno…), „sa”→„s” osim ispred s/z/š/ž/ps/ks/mn, „biti će”→„bit će”, °C, „% vol.”, razmaci.
    Podaci: oznake ujednačene (Naziv vina/likera→Naziv, NAPOMENA→Napomena, Temperatura posluživanja→serviranja), vrijednost velikim
    početnim slovom bez točke. Domestic → Tekstovi: uključi/isključi, vlastiti rječnik „pogrešno => ispravno”, pregled naziva prije/poslije.
  - **domestic-proizvod 0.7.1** (Igor: originalne ikone s kartice posluživanja, velika slika ne treba, prostor za dostavu/plaćanje):
    stavka `_dsp_posluzivanje` ima `slika` (ID ilustracije; admin polje „ID ilustracije”) → u kućici slika 112 px umjesto SVG ikone; mobitel =
    retci (ilustracija 76 px lijevo). Velika slika posluživanja (3. slika galerije) u sekciji SAMO kad nema kućica. „Dostava i plaćanje” otvoreno,
    iz WooCommercea: zone (≤3 županije poimence, inače „Ostatak Hrvatske”), flat_rate cijena + free_shipping „besplatno iznad X”, rok slanja,
    uključeni načini plaćanja (proba: bankovni prijenos, pouzeće; živa će sama pokazati KEKS Pay), napomena o pakiranju, poveznica na uvjete.
    **PRAVILO (Igor): uz svaki proizvod uvijek izrezati originalne ilustracije s njegove kartice posluživanja** (krug r≈167 oko (322|799|1277, 587)
    na 1600 px, isječak 392 px, krem pozadina #F7F3EB → prozirna, PNG 280 px, naziv `posluzivanje-ikona-<…>.png`) i upisati tekst s kartice
    u `_dsp_posluzivanje`. Klasične Avellane (0,20/0,70/1 l): 4888 čašica, 4889 kava, 4890 slastica; Premium: 4891 led, 4892 espresso koktel,
    4893 sladoled s kavom.
  - **domestic-kartica 0.6.0** (Igor: krem traka na dnu mobitela iz probnog shopa 0.5.0): postavka `donja_traka` (yes), ≤849 px, wp_footer
    `nav.dsk-dno`: Početna · Ponuda · Pokloni (prva kategorija proizvoda „poklon”) · Traži · Košarica (broj = WC fragment `span.dsk-dno-broj`);
    trenutna bordo + crta gore; nije na blagajni. Traži = vlastiti panel na vrhu (`#dsk-dno-panel`, form.searchform → prijedlozi iz trazilica.js),
    Košarica = Flatsomeova bočna košarica (klik na .header-cart-link.off-canvas-toggle), bez JS-a vodi na /cart/. body.dsk-ima-dno padding 58 px,
    traka „Dodaj u košaricu” (.dsp-sticky) podignuta iznad nje.
  - **domestic-kartica 0.5.5** (Igor: traka kategorija pri listanju na mobitelu prevelika): kad Flatsome zalijepi zaglavlje (`.header-wrapper.stuck`)
    mreža 4 × 2 bez ikona, polja 34 px (126 → 70 px); na vrhu stranice nepromijenjeno.
  - **domestic-kartica 0.5.4**: otvoreno/označeno polje trake filtera ispunjava cijelu kućicu (summary bez zaobljenja, samo krajnja polja
    zaobljena uz rub trake). **domestic-proizvod 0.7.2**: „Na ovoj stranici” + „Dostava i plaćanje” (#dsp-dostava).
  - **domestic-kartica 0.5.3** (Igor: kućice filtera prije otvaranja ljepše): jedna spojena traka (krem #fbf8f3, obrub #d9cebc, r 12 px) s 3 polja
    Kategorija | Cijena | Poredaj (crte između), u polju zlatna ikona (const FIKONE), malo ime velikim slovima (zlatno-smeđe) i ispod
    trenutni izbor („Sve”, „Sve cijene”, „Preporučeno”; odabrano bordo). Broj proizvoda + oznaka filtra × desno (mobitel ispod trake).
    Mobitel: 3 polja preko cijele širine, ≤420 px bez ikona. Paneli nepromijenjeni.
  - **domestic-kartica 0.5.2** (Igor: tablete kategorija na mobitelu ružne): mobitel = mreža 4 × 2 (62 px), zlatna linijska ikona (JS po adresi:
    vin/vinarij/likeri-alkohol/pivo/horeca/sok/poklon) + naziv normalnim slovima, tanke crte između, trenutna krem + bordo crta dolje;
    8. polje „Sve” (trgovina, samo mobitel). Računalo nepromijenjeno (ikone skrivene).
  - **domestic-kartica 0.5.1** (Igor: sve kategorije na prvom ekranu mobitela): traka kategorija na mobitelu u 2 retka (flex-wrap, sredina,
    tablete 11 px), bez listanja. **domestic-proizvod 0.6.4**: „Još domaćih priča” = 6 malih kartica u redu (kvadratna slika woocommerce_single,
    kategorija · zapremnina, naziv 2 retka, cijena + Pogledaj), ≤1000 px 3 u redu, mobitel vodoravno 2,4 vidljive.
  - **domestic-kartica 0.5.0** (Igor: izbornik na mobitelu, siva traka kategorija, zlatna gornja traka): klasa `Domestic_Kartica_Zaglavlje`.
    Gornja traka #29231F, krem tekst, zlatni kamion; filtar theme_mod_topbar_left: računalo = Flatsomeov tekst (bez velikih slova), mobitel =
    postavka `traka_kratka` („Besplatna dostava iznad 60 € · Zagrebačka županija”) u jednom retku. Traka kategorija (#wide-nav, Flatsome
    nav_position_bg #424242) → krem s crtama #e2d6c3, Jost 500 13 px razmak .12em, sredina, bordo + zlatna crta pri prelasku/trenutna (.dsk-sad
    iz JS-a po putanji); na mobitelu prikazana (inače hide-for-medium) kao vodoravni niz tableta, trenutna bordo i pomaknuta u vidik.
    Izbornik na mobitelu (JS u #main-menu): logo gore, strelice uz kategorije, trenutna s bordo crtom, Prijava odvojena, dolje poveznice iz
    gornje trake (O nama, Blog, O shopu) + kartica dostave. top-divider skriven.
  - **domestic-kartica 0.4.1**: izbornik na mobitelu (Flatsome off-canvas) krem #f7f3eb, crte #e2d6c3, Jost 500, pretraga u njemu kao tableta;
    pretraga: riječ do 2 slova samo na početku riječi, usred riječi tek od 3 slova („av” 35 → 8 rezultata).
  - **domestic-kartica 0.4.0** (Igor: pretraga i košarica u zaglavlju, prijedlozi sa sličicama dok se tipka): klasa `Domestic_Kartica_Trazilica`
    (postavke `trazilica`, `zaglavlje`): REST GET `domestic-kartica/v1/trazi?q=` (javno, 6 proizvoda: sličica thumbnail, marka, naziv, cijena,
    rasprodano + ukupno + poveznica „Prikaži sve”); traži bez kvačica (remove_accents, đ→d) po nazivu (početak riječi više), marki, kategoriji, SKU;
    popis proizvoda u transientu `dsk_trazilica_popis` (briše se pri spremanju). Ista pretraga za stranicu rezultata (posts_search → ID IN,
    poredak po važnosti osim ?orderby). Flatsome live_search isključen filtrom, placeholder „Pretraži likere, vina, rakije…”. assets/trazilica.js:
    od 2 slova, 180 ms, strelice/Enter/Escape, combobox ARIA. Zaglavlje (body.dsk-zaglavlje): polje = krem tableta 44 px s bordo povećalom,
    košarica = SVG torba (mask) s bordo brojem u krugu. Mobitel: prijedlozi s cijenom ispod naziva.
  - **domestic-kartica 0.3.3** (Igor: oznake jednobrazne u stilu shopa): `Domestic_Kartica::oznake_html( $p, 3, klasa )` – tablete 24 px,
    Jost 500 10,5 px velikim slovima, ikona 13 px (const IKONE), najviše 3 po važnosti (const VAZNOST: rasprodano, akcija, malo, novo,
    ograničeno, nagrađeno, omiljeno, izdvajamo, po narudžbi). Boje: akcija bordo, novo tamna, nagrađeno zlatna, rasprodano siva #6f6760
    (+ slika izblijeđena); krem s obrubom: ograničeno (zlatna), omiljeno (bordo), izdvajamo (tamna), malo „Još samo N” (terakota, `malo_do` 5),
    po narudžbi (maslinasta). Ograničeno = oznaka proizvoda (product_tag) `ograniceno-izdanje`. domestic-proizvod 0.6.3 koristi iste oznake
    na galeriji i u brzom pregledu.
  - **domestic-kartica 0.3.2** (Igor: izbornik razveden, nedavno manje): Kategorija = „Sve: …” cijelim redom gore, zatim 2 stupca abecedno,
    redak 40 px, broj u maloj tableti desno, odabrano = krem + bordo točka (ne puni bordo blok). Nedavno pregledano = vlastiti mali niz
    (slika thumbnail 280 kvadrat, naziv 2 retka, cijena), 6 u redu, mobitel vodoravno listanje 3,4 vidljiva.
  - **domestic-kartica 0.3.1**: „Brzi pregled” = tamna tableta (#29231F 86 %) s ikonom oka u sredini dna slike, pojavi se pri prelasku
    mišem (samo prozirnost, bez pomaka), bordo na prelazak; na dodirnim zaslonima (hover:none) uvijek vidljiv okrugli krem gumb s okom dolje desno.
  - **domestic-kartica 0.3.0** (Igor: bijelo ispod artikala, filteri gore kao Plan A, nedavno dolje, 4 u redu, ljepše stranice):
    klasa `Domestic_Kartica_Trgovina` (trgovina, kategorije, pretraga proizvoda; postavka `raspored` gore|tema, `stupci` 4, `nedavno`):
    filtri theme_mod_category_sidebar → none, category_row_count → 4, tablet 3, category_shadow → 0 (has-shadow bojao karticu bijelo);
    Flatsomeova traka naslova skrivena → vlastita putanja + **H1 kategorije** (prije nije bilo H1); traka filtera = <details> izbornici
    Kategorija (podkategorije ili iste razine, broj), Cijena (do 10, 10–20, 20–30, 30–50, iznad 50 € → ?min_price/?max_price, WC filtrira sam;
    prazni rasponi zasivljeni, brojevi iz wc_product_meta_lookup, transient 1 h), Poredaj (?orderby), broj proizvoda, oznaka odabranog filtra ×;
    jedan izbornik otvoren, klik izvan/Escape zatvara; mobitel 2 izbornika u redu, panel preko cijele širine. Stranice = krugovi 44 px, aktivna bordo.
    „Nedavno pregledano” ispod stranica (kolačić woocommerce_recently_viewed; puni ga i dodatak kad widget nije aktivan). Kartica: pozadina
    #f2ebe0, slika #ebe2d4; marka iz VELIKIH slova na početku naziva kad nema marke (AVELLANA Premium); „Brzi pregled” krem tableta.
  - **domestic-kartica 0.2.3** (Igor: ambijentalna od ruba do ruba): slika kartice = `woocommerce_single` (kvadrat 510, + srcset do izvornika)
    umjesto Flatsomeova `woocommerce_thumbnail` 247×296 (uspravno rezan → bijeli rubovi u okviru 1:1). Nova postavka `ispuna`:
    cijela (contain, zadano) | ispuni (cover, reže rubove). Kvadratne fotografije 1600×1600 dovoljne – veći format ne treba.
  - **domestic-kartica 0.2.2** (Igor izabrao br. 3 iz usporedbe 5 parova): **Marcellus** (naslovi, nazivi, cijene; samo 400, kao slova
    logotipa) + **Jost** 400/500 (tekst; datoteka 500 za težine 500–700). Naslov proizvoda, sekcije i „Još domaćih priča” VELIKIM slovima
    s razmakom .05–.06em; kartice u trgovini normalna slova. Caslon/Inter/Cormorant izbačeni. Usporedba (scratchpad fontovi-usporedba.png):
    0 Caslon+Inter, 1 Bodoni Moda+Jost (rezerva), 2 Playfair+Jost, 3 Marcellus+Jost ✔, 4 Gilda+Jost.
  - **domestic-kartica 0.2.1** (Igor: zamijeni): serif = **Libre Caslon Text** (OFL, fontsource 5.3.0): datoteka 400 za težine
    „400 500” (naslovi, nazivi), 700 za „600 700” (cijene); Cormorant izbačen. Naslovi u .dsp i „cijela” CSS na 400. Preload Caslon 700 + Inter 400.
  - **domestic-kartica 0.2.0** (23.10., fontovi): **Cormorant Garamond 600/700** (naslovi, nazivi, cijene; OFL, fontsource 5.3.0 preko
    npm – jsDelivr blokiran) + **Inter 400/600** (tekst), lokalno u `assets/fonts`, samo latin + latin-ext (č ć đ š ž), @font-face s punom
    adresom, font-display swap, preload 2 datoteke (~47 KB prvi prikaz). CSS varijable `--ds-serif`/`--ds-sans` (koristi i domestic-proizvod
    0.4.1). Postavka `fontovi`: cijela (Flatsome tekst, izbornik, naslovi) | shop | ne. Na probi: cijela.
  - **domestic-kartica 0.1.1**: + `stranica` = pozadina cijele stranice (body, #wrapper, #main, .header-main).
  - **domestic-kartica 0.1.0** (23.10.2026.): kartica proizvoda u petlji (trgovina, kategorije, povezani, Flatsome blokovi) – filter
    `wc_get_template_part` (content/product) → `templates/content-product.php`; u Flatsomeu omot `div.product-small.col > .col-inner`
    (mreža .products.row), inače `li`. Opcija `domestic_kartica`: uključeno, redoslijed elemenata (kategorija, marka, naziv, podaci =
    zapremnina·alkohol iz atributa, kratki, cijena preko get_price_html – sidrena cijena ostaje, jedinična €/l, gumb), oznake (akcija −%,
    novo N dana, nagrađeno/omiljeno iz `_dsp_*`, istaknuto, rasprodano), omjer slike, tekst gumba, Flatsome brzi pregled, boje (CSS varijable),
    zaobljenje/okvir/sjena/poravnanje, dodatni CSS. Domestic → Kartica proizvoda s pregledom 4 proizvoda. Bez animacija pomicanja.
    Na probi uključena 23.10. (zadane postavke) – čeka Igorovu fotografiju.
  - **domestic-pomocnik 0.1.0** (23.10.2026.): SAMO za probnu kopiju – na domesticspirits.hr/www sam se isključi (samo obavijest).
    Mailovi zadržani (`pre_wp_mail`, zadnjih 50 u `domestic_pomocnik_mailovi`) ili preusmjereni na jednu adresu „[PROBA → primatelj]”;
    noindex + blog_public 0; crvena traka „PROBNA KOPIJA”. REST `domestic-pomocnik/v1` (install_plugins): GET status, POST plugin
    (multipart `zip` s jednom mapom domestic-*, `activate=1`, zamjenjuje postojeću), GET/POST `opcija/domestic_*` ({"vrijednost"} ili {"spoji"}),
    GET mailovi; 0.3.3: stranice probe šalju nocache + CDN-Cache-Control: no-store (Igor je vidio staru stranicu i u anonimnoj kartici na drugoj mreži – kopija između njega i poslužitelja); 0.3.2: `medij/<id>` + `paleta=1` = PNG izvedenice sažete na 256 boja (GD, isti nazivi); 0.3.1: `?v=` na zamijenjenim slikama; 0.3.0: POST `medij/<id>` (zamjena datoteke slike na istoj adresi, izvedenice i site-icon veličine); 0.2.0: GET/POST `tema` (theme mods, skalarne), opcije i `rank-math-options-titles|general`, `woocommerce_email_header_image`.
    Domestic → Pomoćnik (proba). Igor ga jednom ručno učita; dalje sve dodatke učitavam sam.
  - **domestic-provjera-dobi 0.1.0** (23.10.2026.): provjera punoljetnosti preko e-Građana/mGrađana (čl. 11.a). Domestic → Provjera dobi
    (opcija `domestic_provjera_dobi`: uključeno, okruženje test/prod, client_id + issuer/authorize/token/jwks po okruženju – zadano iz
    mgradjani(-test).gov.hr/idp, opseg svi/kategorije, trajanje potvrde, ručni redirect, dijagnostika; gumb „Test veze” = well-known + JWKS).
    Rute kao stranice (rewrite), ne REST: `/age-verification/start?_dsd=nonce` i **`/age-verification/callback` = registrirani URI** (WC sesija
    dostupna). Transakcija u transientu `dsd_tx_` + sha256(state), 15 min, jednokratna, vezana na WC customer id; verifier/nonce samo na
    poslužitelju. JWT: samo RS256, JWK→PEM (≥ 2048 bita), potpis prije claimova, iss/aud(/azp)/exp obavezan/iat/nbf/nonce, tolerancija 60 s.
    age.alcohol ravno ili ugniježđeno, true/1/"true"; sve ostalo blokira. Callback parametre čita iz QUERY_STRING (WP briše $_GET['error']!).
    Blagajna: klasična (kartica + CSS skriva obrazac + `woocommerce_after_checkout_validation`) i blok (render_block + store_api_cart_errors).
    Meta narudžbe `_dsd_provjereno/_dsd_vrijeme/_dsd_okruzenje`; potvrda se briše nakon narudžbe (svaka narudžba nova provjera); sub se odbacuje.
    Uzor: tuđi „IT-podrška Age Gate (NIAS)” (GPL; readme + DOCUMENTATION.md javno čitljivi na kopiji, PHP ne). Lokalni test (scratchpad
    test_dob.py + mu-plugin mock-idp.php, pravi RS256 tokeni): 29/29 + blok blagajna/Store API odbija. Na kopiji: Igor ručno učitava zip
    (aplikacijska lozinka ne radi za wp-admin upload), zatim isključiti IT-podrška dodatak. Za pravi test na kopiji ministarstvo mora dodati
    redirect `https://proba.domesticspirits.hr/age-verification/callback`.

## Provjera dobi – mGrađani (Zakon o trgovini NN 59/26, rok bio 15.9.2026.)

Domestic Spirits registriran u e-Građani (client ID u mailu Emiliji; NE u git – upisuje se u postavke dodatka). OIDC: response_type code,
PKCE S256, token_endpoint_auth `none` (bez tajne), RS256 (JWKS), subject pairwise, scope **`age.alcohol`** (potvrđeno iz well-known i tehničke upute), claim
**`age.alcohol`** true/false; ostali claimovi sub(pairwise)/iss/aud/exp/iat/nonce. Odgovor tokena samo `id_token` (JWT RS256). Test: https://mgradjani-test.gov.hr/idp,
produkcija: https://mgradjani.gov.hr/idp (produkcija tek nakon odobrenja). **Registrirani redirect URI (iz obrasca): `https://domesticspirits.hr/age-verification/callback`**
– jedini; za kopiju stranice treba od ministarstva tražiti dodatni URI. Provjera prije plaćanja, za svaku narudžbu; odbijanje = nema narudžbe;
na narudžbu samo „provjereno + vrijeme”. Iz sesije su gov.hr i domesticspirits.hr blokirani (Igor dodaje domene u postavke okruženja).
Dodaci: domestic-pomocnik (vidi popis dodataka) i domestic-provjera-dobi. Rad samo na kopiji Mladenove stranice
(Igor radi klon); živa = samo čitanje (DS_URL / DS_WP_USER / DS_WP_APP_PASSWORD), kopija DS_PROBA_*.
Tehnička uputa MinGo (PDF, 7 str., /UserDocsImages/Trgovina/Tehnicka_uputa_provjera punoljetnosti.pdf): authorize s response_type=code, client_id,
redirect_uri, scope=age.alcohol, state, nonce, code_challenge(S256); povratak ?code&state (state obavezno provjeriti); POST /idp/token
(grant_type, code, redirect_uri, client_id, code_verifier) iz backenda; provjeriti potpis (JWKS), aud, nonce, exp, iss; code_verifier nikad u
pregledniku/URL-u/localStorage; ne pohranjivati osobne podatke. Testni korisnik: JEDAN e-mail (Google Play / Apple račun) javiti s registrirane
adrese; nakon testiranja službena potvrda produkcijske spremnosti. Kontakti: tehnika **m-gradjani@mpudt.hr**, poslovno evidencija-trgovci@mingo.hr.
**23.10.2026. odgovor ministarstva:** povratna adresa probe dodana. Testna aplikacija mGrađani: iOS javni TestFlight (poveznica u mailu),
Android – javiti e-mail testera (Google Play račun) pa šalju poveznicu. Testni korisnici ePASS (maloljetni i punoljetni; podaci u mailu –
NE u git). Za produkciju moraju na testnoj bazi vidjeti barem jedan uspješno autoriziran session (cijeli tok + izdan token).
Stanje probe: dodatak aktivan, `ukljuceno` no, okruženje test, **client_id još nije upisan** (Igor upisuje u Domestic → Provjera dobi),
rute /age-verification/start i /callback rade (302).

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
noindex/nofollow (Rank Math), REST /wp-json 200, nginx. Pristup iz sesije dopušten. DS_PROBA_* rade (administrator, 23.10.).
Na kopiji ISKLJUČENO (23.10.): KEKS Pay (pravo plaćanje), Google Site Kit, CTX Feed. Solo i Jetpack nisu instalirani kao dodaci.
**VAŽNO: na živoj i kopiji već je aktivan tuđi dodatak „IT-podrška Age Gate (NIAS)” 1.2.2** (IT-podrška d.o.o., provjerapunoljetnosti.hr) –
e-Građani/mGrađani OIDC+PKCE, REST `nias/v1/start` i `nias/v1/callback`. Registrirani redirect `/age-verification/callback` na živoj = 404,
tj. ne poklapa se s REST callbackom → vjerojatno nije podešen/ne radi. Prije izrade domestic-provjera-dobi Igor odlučuje: podesiti njihov ili naš.

## Blog – partneri (Igor 23.10.2026.: vinarije i domaći proizvođači)

Sve 4 stare objave (Pilato 2710, Ferenac 2525, Lagena 2012, Grgurević 1183) su trenutni partneri – ništa nije uklonjeno; tekst i adrese
isti, dodan blok „U našoj ponudi” ([products ids=… orderby="post__in" columns="4"]) i kategorija regije. Nove kategorije: 304 „Vinarije Istra
i Kvarner”, 305 „Domaće rakije, likeri i pivo”. 16 novih objava 4895–4910 (permalink /%postname%/): Pavlomir, Franković, Petrač, Trdenić,
Dubrovački podrumi, Kurtalj, Mikša, Antunović, OPG Čuković, Patrik, Promex, Gavran, Good Spirit, Gothic (Golden Steam), Misna vina Đakovo/Vinovica,
Slavonica. Tekst iz opisa proizvoda + javnih izvora (oprezno). Naslovne slike 4915–4945 `2026/10/blog-<slug>.jpg` 1600×900 složene iz slika
proizvoda (bijela pozadina → krem multiply, crna → tamna #29231F lighten, mješovito → kvadratne pločice). Skripte: `alati/blog-partneri/`.
Preskočeni: Kosovo Vranac (nije hrvatski), Aloe Vera sokovi. Fotografije sa stranica vinarija NE mogu se skinuti (mreža sesije blokira
vanjske domene) – Igor treba dodati domene u Allowed domains. Stranica /vinarije/ = stranica objava (sve objave, i rakije).
Proširenja (dodaci.py: regija, tablica „Za stolom”/„Kako poslužiti” class dsb-stol, savjet class dsb-savjet; stare 4 dobile „Za stolom”),
upis `prosiri.py`; redoslijed `redoslijed.py` (vinarije noviji datum → prve na popisu). Opisi kategorija = uvod na stranici regije; Style → „Savjeti”.
  - **domestic-blog 0.1.0** (Igor: blog po standardima novog weba, hrvatski, gumbi): template_include za is_home/kategorije/oznake/objave →
    `Domestic_Blog_Prikaz::arhiva()/objava()`. Popis: putanja, nadnaslov, H1 (postavka „Vinarije i proizvođači”), uvod, tablete kategorija s brojem
    („Vinarije …” bez prefiksa, regije prve; skrivene u postavci, na probi 228 Blog), 1. priča istaknuta (vodoravno), kartice 3 u redu (16:9, kategorija,
    naslov, sažetak 3 retka, „N proizvoda u ponudi · Pročitaj priču →”), krugovi stranica, tamni poziv u trgovinu; 13 po stranici (1 + 12).
    Objava: tableta kategorije, H1, „N min čitanja · N proizvoda u ponudi · Pogledaj ponudu ↓”, slika 16:9, tekst 760 px (h2 sa zlatnom crtom,
    citat krem, zlatne točke), „U našoj ponudi” izvučen iz sadržaja ([products] iza `<h2>U našoj ponudi</h2>`) u traku preko cijele širine + „Svi
    proizvodi”, Još priča (3 iz iste kategorije). SAMO PRIKAZ za stare objave: [ux_slider]/nizovi <img> → galerija (oznaka DSBGALERIJAnX dok prođe
    wpautop), [ux_products] → [products] široko, prvi naslov VELIKIM SLOVIMA maknut, style/align/span izbačeni, tablica dobiva data-l za mobitel.
    gettext za Flatsome (Continue reading → Pročitaj više …). Domestic → Blog (postavke).
  - **domestic-blog 0.2.0** (Igor: izbornik regija kao izbor proizvoda, ljepši gumb): tablete → spojena traka polja (`.dsb-regije`, kao filteri/
    kategorije proizvoda): zlatna linijska ikona po vrsti (const IKONE: sve, dalmacija = sunce i more, istra = maslina, slavonija = klas,
    kontinent = brijeg s grožđem, rakije = boca), naziv, „N priča”; trenutna krem #f0e9de + bordo crta dolje. Redoslijed: Dalmacija, Istra i
    Kvarner, Slavonija, Kontinentalna, Rakije. Mobitel mreža 3 × 2. Na probi skrivene 228 Blog i 15 Savjeti. „Pročitaj priču” = bordo obrubljena
    tableta velikim slovima + strelica u bordo krugu (pri prelasku ispunjena); lijevo ikona boce + „N proizvoda u ponudi”.
