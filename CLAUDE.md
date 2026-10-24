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

- BOGATI Čokolada 0,70 l (1589, 23.10.): 4062 ambijent (`2018/03/bogati-cokolada-07-domestic-spirits-prednja.png`, PNG 256 + paleta), 4972 studio,
  4973 posluživanje, ikone 4974 čašica, 4975 led, 4976 sladoled. **Uvoz paketa od sada dodatkom domestic-slike** (Domestic → Slike proizvoda ili
  `alati/uvoz-slika/uvoz.py paket.zip`); stara Python obrada zamijenjena.

- BOGATI Karamela 1 l (1348, 23.10.): 4072 ambijent (`2018/03/liker-karamela-bogati-domestic-spirits.png`; alt bio prazan → popunjen, `alt_glavna`),
  4977 studio, 4978 posluživanje, ikone 4979 led, 4980 espresso, 4981 sladoled.

- 5 paketa (23.10.): **Orahovac 1 l** (1353): 4074 ambijent, 4982 studio, 4983 posluživanje, ikone 4984 aperitiv, 4985 slastica, 4986 digestiv.
  **Orahovac 0,20 l** (4272): 4278 ambijent, 4987, 4988, ikone iste (4984–4986). **Medica 1 l** (1352): 4073 ambijent (alt popunjen), 4989, 4990.
  **Medica 0,70 l** (1600): dijelila je 4073 s 1 l → glavna sada **4063** (stara slika Medice 0,70 `2018/03/bogati-medica-07-domestic-spirits.png`,
  ista adresa; koristi je i naslovnica 247×296), 4991 `studio-bogati-medica-07-…`, 4992 `posluzivanje-bogati-medica-07-…`; ikone Medice 4969–4971
  za sve tri zapremnine. **Čokolada 1 l** (1345, **privatan** proizvod): 3545 `cokolada-scaled.jpg` (izvornik cokolada.jpg ostaje star), 4993, 4994,
  ikone 4974–4976. uvoz.py: `ikone[].id` = već učitana ista ikona (ne reže se ni ne učitava).

- Dodatkom domestic-slike 0.2.0 (23.10.): **Teranino 0,70 l** (1952): 4217 ambijent, 4995 studio, 4996 posluživanje, ikone 4997–4999;
  **Teranino 0,20 l** (2594): 4205, 5000, 5001, iste ikone; **Višnjevac 0,20 l** (2999): 3000 ambijent, 5002, 5003, **3003 i 3004 zamijenjene
  na istim adresama** (dodatne slike iz paketa istog naziva), ikone 5004–5006; **Vanilija 1 l** (1358): 4076, 5007, 5008, ikone 5009–5011;
  **Ruža 1 l** (1359): 4075, 5012, 5013, ikone 5014–5016. Opisi stavki upisani prema karticama (`--postavke`; prijedlog dodatka bi uzeo Medicine).

- domestic-slike 0.3.x (23.10.): **Gavran Raven's 23** (3405): 3406 `gavran-3-scaled.jpg` ambijent, 5017 `studio-gavran-3-scaled-1.jpg` (WP dodao -1),
  5018, ikone 5019 aperitiv, 5020 tonik, 5021 gin fizz; **Driver's Holiday Gin** (3132, privatan): 3134 `2022/10/IMG-20221018-WA0010.jpg`, 5022
  `studio-IMG-20221018-WA0010.jpg`, 5023 (paket imao 1-ambijent/2-studio/3-posluzivanje), ikone Gavrana; **Višnjevac 1 l** (1354): 4077, 5024, 5025;
  **Višnjevac 0,70 l** (1595): 4066, 5026, 5027; oba ikone Višnjevca 0,20 (5004–5006). Nove slike idu u mapu datuma proizvoda (media_handle_sideload).

- (23.10.) **Gothic Vodka Premium** (2732): 3130 `2021/05/IMG-20221018-WA0009.jpg`, 5028, 5029, ikone 5030–5032 (samostalno, led, Moscow Mule);
  **Good Spirit Kova** (1519): 3129 `Kova.jpg`, 5033, 5034, ikone 5035–5037 (samostalno, slastica, Espresso Martini); **Golfer liker** (1524,
  **privatan – ostaje skriven**, paket 1-ambijent…): 3128 `Golfer.jpg`, 5038 `studio-Golfer.jpg`, 5039, ikone 5040–5042.
  Pravilo (Igor 23.10.): skriveni proizvodi dobivaju nove slike, ali status se NE mijenja (Driver's Holiday 3132 i Golfer 1524 ostaju privatni).

- (23.10.) **Patrik Hawk's Gin** (2565): 3744 `HAWKS-1.jpg`, 5043, 5044, **ikone Gavrana** 5019–5021 (ista kartica); **Borovnica 1 l** (1537):
  3412, 5045, 5046, 3413 (stražnja, zamijenjena), ikone 5047–5049; **Viljamovka sa zlatom 0,70 l** (3776): 3780, 5050, 5051, 3781 (zamijenjena),
  ikone 5052–5054; **Viljamovka 0,20 l** (1533): 1534 `patrik-voljamovka.png`, 5055, 5056, ikone 5052–5054; **Limoncello 0,50 l** (3525): 3526,
  5057, 5058, 3527 stražnja (zamijenjena; hover stražnje etikete iz probnog shopa), ikone 5059–5061.

- (23.10.) **Promex Borovnica 1 l** (1107): 1108 `promex-borovnica.png`, 5062, 5063, ikone Patrik Borovnice 5047–5049; **Navy Strength Gin** (3745):
  3746, 5064, 5065, ikone 5066–5068 (tonik, Gimlet, Ginger Mule); **Mix rakija** (1630): 1631 (glavna), 5069, 5070, 1632 stražnja zamijenjena (PNG),
  ikone 5071–5073; **Mix likeri** (1641): 1639, 5074, 5075, 1640 zamijenjena, ikone 5076–5078; **Loza Muškatna** (2758): 2759, 5079, 5080, 2760
  zamijenjena, ikone Viljamovke 5052–5054. domestic-slike **0.3.2**: ambijentalna = slika istog naziva kao GLAVNA slika proizvoda (ne prva po zipu).
- (23.10.) **Patrik Šljivovica 0,20 l** (3084): stara glavna 1943 `2020/02/patrik-piramide-1.jpg` = grupna slika (Travarica, Medovača, Višnja,
  Borovnica – bez Šljivovice) i glavna je **Travarici 1942** → NE zamijenjena; Šljivovica dobila NOVU glavnu 5082 `2022/07/patrik-sljivovica-020l.jpg`
  (paket preimenovan, uvoz s `cilj: 0`), 5083 studio, 5084 posluživanje, ikone 5085–5087 (čaše); 1943 maknuta iz njezine galerije (prilog ostaje).
  **Patrik Travarica 0,20 l** (1942): 1943 tada samo njezina (nije ni u objavama) → ambijentalna zamijenjena na ISTOJ adresi (prazan alt popunjen),
  5088 `studio-patrik-piramide-1.jpg`, 5089 `posluzivanje-…`, ikone 5090–5092 (dvije niske čaše + čaša za destilate; Šljivovičine su druge).
- (23.10.) **San MONTANO Teranino 1,50 l Limited** (4632): 4633 ambijent, 5094 `studio-1000300153.jpg`, 5095, 4635–4639 detalji zamijenjeni na istim
  adresama, ikone 5096–5098 (čaša, kolač, čaša za destilate); **Šokac Divlja kruška** (1636, **privatan – ostaje**): 1638 PNG ambijent, 5099, 5100,
  1637 stražnja PNG zamijenjena, altovi popunjeni ručno (paket prazan), ikone 5101–5103; **STRAVA 0,10 l** (815): 4130 PNG, 5104, 5105, ikone 5106–5108
  (tri niske čaše); **STRAVA 1 l** (754): 4712, 5109, 5110, 4713 zamijenjena, ikone 0,10 l (`ikone_izvor` 815); **STRAVA glava 0,75 l** (4751): 4124 PNG
  (lubanja – zajednička i 1647 glava 0,75 „primores”, privatnima 1929 0,38 l i 1928 0,10 l; ista boca → zamjena na istoj adresi), 5111, 5112, ikone 815.
  **1647 = stara adresa iste glave 0,75 l** (objavljen, `catalog_visibility` hidden): dobio istu galeriju i `_dsp_posluzivanje` preko WC REST.
  **Usklađena boja** (paketi „Strava-Uskladene-Fotografije” i „…Glava-Fotografije-Za-Potvrdu”, Igor: zamijeni): na istim adresama 4124 (PNG paleta),
  5111, STRAVA 1 l 4712, 5109, 4713; glava kut 2 → **4125** `strava-glava-domestic-spirits-2.png`, kut 3 → **4126** `-3.png` (PNG paleta); nove studijske
  5152 `studio-strava-glava-domestic-spirits-2.jpg`, 5153 `-3.jpg`. Svaka zapremnina svoja ambijentalna: 0,75 l (4751, 1647) [4124, 5111, 5112, 4125, 4126],
  0,38 l (1929, privatan) [4125, 5152, 5112, 4124, 4126], 0,10 l (1928, privatan) [4126, 5153, 5112, 4124, 4125]; obje dobile `_dsp_posluzivanje`.
  Paket „Strava-Glava-075” ponovno poslan = isti kao prvi (preskočen). curl `-F datoteka=@…` ne podnosi zarez u putanji („0,75 l”) → kopirati.
- (23.10.) **Promex Medica 1 l** (1115): 1116 PNG, 5113, 5114, ikone 5115–5117; **Promex Pelin 1 l** (1113): 1114 PNG, 5118, 5119, ikone 5120–5122
  (S tonikom = čaša s limunom); **San MONTANO Biska** (739): 740 PNG, 5123, 5124, ikone 5125–5127 (S ledom); **Smokva** (718): 729 PNG, 5128, 5129,
  ikone 5130–5132; **Teranino 0,70 l** (717): 732 PNG, 5133, 5134, ikone Teranina Limited 5096–5098 (ista kartica). Glavne slike nisu dijeljene.
- (23.10.) **Promex Brusnica 1 l** (1105): 1106 PNG, 5135, 5136, ikone 5137–5139 (čaša, tanjur „Uz divljač”, čaša za destilate).
- (23.10.) **TRAVA specijalna rakija 0,50 l** (834, slug primores-travarica): 2612, 5140, 5141, ikone Patrik Travarice 5090–5092 (ista kartica).
  **TERROAR Viljamovka/Šljivovica/Marelica/Dunja 0,70 l** (1985/1982/1984/1983): glavne 1981/1980/1977/1976 (koriste ih i poklon paketi – ista boca),
  studio+posl 5142–5149, ikone Šokca 5101–5103 (ista kartica). Grupne **1978 `terroar-skupna-1.jpg` i 1979 `-2.jpg`** (iste u sva 4 paketa, zajedničke
  svim TERROAR-ima + 1979 u „TERROAR Premium” 2360) zamijenjene JEDNOM (uvoz Viljamovke), ostali `dodatne: false`; alt grupnih ručno (dodatak
  bi upisao naziv jednog proizvoda). Ključ ikone `led` ne postoji (casa, kava, kolac, tanjur, riba, sir, koktel) – ilustracija ga pokriva.
- (23.10.) **Vinovica prirodna rakija od vina 0,70 l** (3159): 3165 ambijent, 5150, 5151, 3166 stražnja zamijenjena (alt ručno), ikone Promex Brusnice
  5137–5139 (ista mjesta: čaša, tanjur, čaša za destilate). Naziv u bazi „Vinovica-prirodna-rakija-070L” → na probi opcija `domestic_tekst.rjecnik`
  „Vinovica-prirodna-rakija-070L => Vinovica prirodna rakija od vina 0,70 l” (samo prikaz; **prenijeti i na živu**).

- **BRZI UVOZ (Igor 24.10.: „puno resursa troši ubacivanje slika”) – OBAVEZAN POSTUPAK od domestic-slike 0.4.0:**
  `python3 -I alati/uvoz-slika/uvoz.py <svi zipovi odjednom>` → jedan redak po paketu (OK / STOP / GREŠKA). Slike, kartice i stranice
  se NE gledaju i ne snimaju; gleda se samo paket sa STOP (zajednička glavna slika, dva jednaka kandidata, neprepoznat proizvod, nema
  ambijentalne, kartica bez teksta) – za njega `--detalji` / `--postavke`. „Bez opisa ispod stavke” = info (uvozi se; opis tek ako Igor
  traži ili paket ima retke STAVKA). Bilješke u CLAUDE.md jednim retkom po skupini, jedan commit na kraju, kratak odgovor.
  0.4.0: `alt.txt` retci `STAVKA = naziv | količina | opis [| ikona]` (pravila za pakete dopunjena); ilustracije se uspoređuju otiskom
  24×24 (meta `_dss_ikona_hash`, postojeće jednom izračunate, opcija `dss_ikone_hash`) – ista (≤ 3 % razlike) se ponovno koristi, ne učitava;
  analiza vraća `upozorenja` [stop|info] (i u adminu iznad pregleda); prepoznavanje stane tek ispod 2 boda (2 = stara glavna slika istog naziva).
  **0.4.1** (Igor: „ukloni sve podatke sa slika koje stavlja AI”): klasa `Domestic_Slike_Meta` – čišćenje BEZ ponovnog sažimanja (pikseli i adrese isti):
  JPEG ostaju samo APP0 JFIF, ICC (APP2) i Adobe (APP14); brišu se EXIF (osim orijentacije ≠ 1), XMP, C2PA/JUMBF, ostali APPn, komentari (GD je
  upisivao „CREATOR: gd-jpeg”) i podaci iza kraja slike; PNG ostaju IHDR/PLTE/tRNS/IDAT/IEND. Automatski: filtar `wp_generate_attachment_metadata`
  (svaka nova/zamijenjena slika + izvedenice, i ručno učitane u WP) i nakon `spremi_cisto`. Cijela biblioteka: REST POST `ocisti` {stranica, po}
  → `python3 -I alati/uvoz-slika/ocisti.py [--ziva]`. **Na živoj pokrenuti nakon instalacije.**

- (24.10.) **Vina – serija 1** (12 vina, jedan zip s mapama, alt.txt „datoteka / ALT:”, kartica vina = temperatura · poslužite uz · izbor čaše, bez
  3 kruga): `alati/uvoz-slika/serija.py serija.zip --case case.json [--slug mapa=slug]` (proizvod po adresi iz „IZVOR”, prva domesticspirits.hr;
  podmape izvorne-fotografije i napomene s nazivom alata se NE prenose; STAVKA Temperatura serviranja/Poslužite uz/Izbor čaše; uvoz
  `dodatne:false, bez_ilustracija:true` – domestic-slike **0.4.2**). Uvezeno: 1594, 1062, 1507, 1092, 1827, 1828, 2795, 870 (skriven, slug ručno),
  867, 779, 3075, 2808; nove 5164–5187. **domestic-proizvod 0.7.5**: redak „Temperatura serviranja” skriven kad je temperatura već kućica.

- (24.10.) **Vina – serija 2** (Grgurević 9, Kravata 3): 1606, 3068 (+ panorama 5192), 1601, 1479, 1612, 809, 1607, 3072, 1618 Varenik (kartica „uporaba” =
  Za kuhanje + Preporuka, stavke zadane u case.json kao popis), 849 Graševina (skrivena), 821, 852 Teran (slug primores-plavac-mali-postup); nove 5188–5212.
  serija.py: kartica i „uporaba”, case.json može biti popis stavki, `dodatne: true` (nove slike iz glavne mape u galeriju, podmape ne).

- (24.10.) **Vina – serija 3** (Kurtalj 2, Lagena 7, Linija 3): 1058, 1075, 2051, 1613, 3177, 2049 (skriven), 858, 843, 1623, 1986, 1990, 824; nove 5214–5240.
  **Greška i popravak**: Škrlet Iconic – kad nijedna slika nema naziv stare glavne, dodatak je uzimao PRVU u zipu → medalja otišla na 2314.
  Ispravljeno ručno (2314 ← ambijentalna, 5233 obrisan, medalja 5240 nova u galeriji) i u **domestic-slike 0.4.3**: ambijentalna po altu
  („ambijent”), inače prva koja nije medalja/poleđina/detalj/etiketa/panorama/kutija.

- (24.10.) **Vina – serija 4** (Malvasia BiB 3 l, Mikša, Misna vina 4, Pavlomir 4, Petrač 2): 2250, 3343, 1532, 818, 831 (skriven), 846, 4655, 4709, 4660,
  4662, 3079, 1543; nove 5241–5264. Alt kartice „kartica posluživanja:” (serija.py prihvaća i to). Pavlomir Rosé glavna 4714 je **WebP**
  (`2026/06/IMG_20260608_093941_135.webp`) → **domestic-slike 0.4.4**: zamjena i za .webp (GD imagewebp 85).

- (24.10.) **Vina – serija 5** (Pilato 6, Primores Graševina = **ZEBRICA 837 skrivena**, slug primores-grasevina; Škrlet 7 vinara 3154 skriven, Trdenić 2,
  Vranac 0,75/1 l): 3513, 2654, 1538 (+ medalja 5274), 1550, 1544, 2652, 837, 3154, 1572, 1583, 1619, 2222; nove 5268–5292.
  **Pilato Chardonnay 0,75 l** (1551, skriven; zaseban od sur lie 3513): 4117 ambijent, 5295, 5296 (paket ravni zip → omotan u mapu za serija.py, stavke iz case.json).

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
i za proizvode u dodatak (Domestic → Slike proizvoda → Za provjeru; REST `domestic-slike/v1/provjera`; uvoz paketa sam upisuje napomene)
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
  - **domestic-slike 0.1.0** (Igor: skriptu za slike pretvoriti u dodatak, temelj za daljnja proširenja; radi i na ŽIVOJ – nije vezan uz probu):
    Domestic → Slike proizvoda, kartice **Uvoz paketa** (više zipova odjednom → pregled po paketu → Uvezi/Odbaci), **Zamjena slike** (ID + nova datoteka,
    ista adresa), **Za provjeru** (meta `_dss_za_provjeru` na proizvodu: napomene iz paketa + ručne, „Riješeno” s odgovorom), **Dnevnik** (opcija
    `domestic_slike_dnevnik`, 100 zapisa). Jezgra `Domestic_Slike`: `spremi_cisto` (GD ponovno spremanje = bez metapodataka; jpg 85 progresivno,
    PNG fotografija paleta 256 + dither), `zamijeni($id,$izvor)` (format stare datoteke, briše stare izvedenice i stari izvornik „-scaled”,
    izvedenice PNG s paletom, `?v=` iz opcije `domestic_slike_verzije` + čita i `domestic_pomocnik_verzije`), `nova()` (media_handle_sideload).
    `Domestic_Slike_Paket`: zip se čita po stavkama (samo slike + alt.txt; ostalo se ne čita i ne raspakira), radna mapa
    `uploads/domestic-slike-rad/<token>/` (+ .htaccess deny, pregledi kao data: slike, briše se nakon uvoza ili za 2 dana – cron dss_ciscenje),
    priprema u opciji `dss_paket_<token>`. Prepoznavanje proizvoda: glavna slika istog naziva (+2) i naziv mape = izvorni naslov (+3; **ne
    get_the_title – skriveni dobivaju „Privatno:”**). Upozorenje kad je glavna slika zajednička s drugim proizvodima (polje „Ambijentalna ide u
    sliku (ID)”). „Kako poslužiti” iz alt teksta kartice (dijeli se iza „… l”), opis s drugih proizvoda s istim nazivom + količinom, iste ikone
    = ista marka i vrsta (prve 2 riječi naslova) i isti nazivi stavki → `ikone_izvor`; inače GD izrezivanje (isti algoritam kao Python: krugovi
    322/799/1277, y≈627, r≈176 na 1600 px, razmjerno za druge veličine). Uvoz: ambijentalna na staru adresu (prazan alt popuni), studio/posluživanje/
    dodatne nove JPG, galerija = nove + označene postojeće, `_dsp_posluzivanje`, napomene → Za provjeru; ponovni uvoz istog paketa odbijen.
    REST `domestic-slike/v1` (manage_woocommerce + upload_files): POST `paket` (multipart zip → analiza), POST `paket/<token>` (JSON izmjene →
    uvoz), DELETE `paket/<token>`, POST `medij/<id>` (datoteka, paleta), POST `provjera` {proizvod, tekst, izvor}, GET `dnevnik`.
    Klijent `alati/uvoz-slika/uvoz.py paket.zip [--samo-analiza] [--postavke izmjene.json] [--ziva]`. Lokalni test (scratchpad lokal + dss-test):
    analiza 1,4 s, uvoz ~9 s, PNG 1600 s paletom GD 1,27 MB (PIL 0,87), vizualno jednako. Na probi u Za provjeru prenesene natuknice za
    3005, 1600, 1352, 1589, 1345, 1348, 4272, 1353, 3697 (blog ostaje u ZA-PROVJERU.md).
    **0.2.0**: dodatna slika iz paketa istog naziva kao slika proizvoda (glavna/galerija) → zamjena na istoj adresi (ostaje u galeriji iza
    novih); „Kako poslužiti” i u zapisu „0,03 l kao aperitiv, …” (količina ispred naziva). **0.3.0**: uloge i po ključnoj riječi
    (`1-ambijent`, `2-studio`, `3-posluzivanje`) – tada nove dobivaju naziv `studio-/posluzivanje-<naziv stare glavne>`; opisi stavki SAMO s iste
    marke i vrste (prve 2 riječi); traženje proizvoda po najduljoj riječi mape (oblí apostrof). **0.3.1**: bez „-scaled” u nazivu novih slika.
  - **domestic-kartica 0.6.3** (Igor: slika kategorije „NE na stranici, samo u linkovima koje dijelim”): 0.6.2 traka sa slikom uz naslov kategorije
    VRAĆENA (0.6.3 = kod 0.6.1). Slika kategorije služi samo za dijeljenje: Rank Math je sam stavlja u `og:image` (Facebook, WhatsApp, Viber,
    LinkedIn); `twitter:image` ostaje logo. Kategorija 82 „Likeri i rakije” (`jaka-alkoholna-pica`): slika **2392**
    `2020/12/strong-spirits-set-hard-alcoholic-drinks-glasses_127657-850.jpg` zamijenjena Igorovom (1600², ista adresa, alt popunjen).
    Nove slike kategorija = samo zamjena slike kategorije, ništa na stranici. Na živoj nakon zamjene: Facebook Sharing Debugger → „Scrape Again”.
  - **domestic-proizvod 0.7.4** (Igor: STRAVA glava ima tri zapremnine – „prikaži u izboru ali stavi da su rasprodane”): Data::siblings uzima i
    PRIVATNE proizvode istog naziva/kategorije → u izboru zapremnine `span.dsp-vol-opt.dsp-vol-nema` (crtkano, precrtana zapremnina, „RASPRODANO”,
    bez poveznice, aria-disabled); objavljeni koji nisu na stanju = isti izgled, ali s poveznicom. Status se ne mijenja. Ista zapremnina: ostaje
    trenutni, odnosno objavljeni ispred skrivenog; objavljeni skriveni iz kataloga se preskaču. Element niza: [proizvod, litre, ima, skriven].
  - **domestic-proizvod 0.7.3 / domestic-kartica 0.6.1** (greška: Mix paketi „4 l” i 3,88 €/l): pakiranje „4 × 0,05 l” / „4x0,05L” = ukupno 0,20 l
    (Data::pack, litres, litres_from_name; Kartica::litre) → cijena po litri 77,50 €; oznaka `Data::volume_label()` „4 × 0,05 l” (čipovi, izbor
    zapremnine, povezani, traka na dnu).
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
Igorove fotografije 1920×1080 (23.10.) zamijenile složene bannere na istim adresama: Patrik 4933, Promex 4935, Gavran 4937, Good Spirit (Kova) 4939,
Gothic 4941 – altovi prepisani prema novim fotografijama. Preskočeni: Kosovo Vranac (nije hrvatski), Aloe Vera sokovi. Fotografije sa stranica vinarija NE mogu se skinuti (mreža sesije blokira
vanjske domene) – Igor treba dodati domene u Allowed domains. Stranica /vinarije/ = stranica objava (sve objave, i rakije).
Igorov paket „Blog-Vina” (24.10., 15 fotografija 1920×1080, PIL ponovno spremljene): zamijenjene na istim adresama 4915–4931 (osim Ferenca), 4943,
stare objave Pilato 2712, Lagena 2016, Grgurević 4095, „zagrebački restorani” (1761) 4244 – altovi iz paketa. **Ferenac**: stara 2526 `gf1.jpg` je i u
tekstu priče → ostala; nova naslovna **5294** `2026/10/blog-giulio-ferenac.jpg`.
**Naziv bloga „Iza etikete”** (Igor 24.10.; adresa `/vinarije/` ISTA): stavka izbornika 377, stranica 88 (naslov → SEO naslov „Iza etikete – Domestic spirits”),
`domestic_blog.naslov` (H1), widget podnožja recent-posts-10. Na naslovnici odjeljak „Vinarije s kojima surađujemo” ostaje (stvarno o vinarijama).
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
