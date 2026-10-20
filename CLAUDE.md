# Plan A – dogovorena pravila za rad (pročitaj prije svega)

Korisnik je Igor (S.R.D. Plan A, srd-plan-a.hr). Odgovaraj **na hrvatskom**. Prije većih promjena
prvo predloži tekstualno, kad to traži. Shortcodeovi i HTML idu u Flatsome HTML blok.

## Grane

| Grana | Što je u njoj |
| --- | --- |
| `claude/optimistic-knuth-7myrcm` (zadana) | postojeći dodaci za **živu** stranicu (članstvo, košarica, izleti, jedrenje, newsletter, aplikacija, kalkulator, poklon-bon) |
| `prijave` | **novi sustav izleta** `plan-a-prijave` (zamjena za WpTravelly) + kopija postojećih dodataka |

- Ispravke za živu stranicu: samo u zadanoj grani; zatim ih prenijeti i u `prijave`.
- Sve što se prilagođava za novi sustav (i izmjene postojećih dodataka zbog njega): **samo u `prijave`**.
  Stari dodaci u zadanoj grani ostaju netaknuti.
- Zip prilagođen za novi sustav dobiva oznaku u verziji, npr. `plan-a-clanstvo 1.14.5-prijave`, i ide
  **samo na kopiju stranice**. Zip bez oznake je za živu stranicu.

## Stranice i pristup

- **Živa stranica** `LIVE_URL` (https://srd-plan-a.hr): **samo čitanje**. WooCommerce REST ključ
  `LIVE_WC_KEY` / `LIVE_WC_SECRET` s dozvolom Read. Nikad ništa ne upisuj, ne naručuj, ne šalji obrasce.
  U novoj sesiji prvo provjeri da je ključ samo za čitanje (upis mora biti odbijen).
- **Kopija stranice (staging)** `PROBA_URL`: puni pristup, korisnik `PROBA_WP_USER` +
  `PROBA_WP_APP_PASSWORD`, FTP `PROBA_FTP_*` ograničen na mapu kopije (ako su upisani).
  Mailovi na kopiji: **plan-a-pomocnik** (od 1.1.0) sve mailove preusmjerava na Igorovu adresu upisanu u
  Alati → Plan A – mailovi kopije (izvorni primatelji u naslovu); bez upisane adrese sve blokira.
  Pomoćnik se na kopiji nikad ne isključuje. Disable Emails se više ne koristi (blokirao bi i preusmjerene mailove).
- Na kopiji je dodatak **plan-a-pomocnik** (grana `prijave`): REST `PROBA_URL/wp-json/plan-a-pomocnik/v1/`
  (`status`, `mail`, `mail/test`, `plugin` – postavljanje našeg zipa, `plugin/{slug}/activate|deactivate` – i za
  `tour-booking-manager` (WpTravelly), `plugin/{slug}/update` (version=…, dodatak treće strane na točnu verziju s wordpress.org; bez version = najnovija), `updates`, `errors` (PHP greške/upozorenja iz dodataka i teme, DELETE briše), `cron`, `log`,
  `meta/{id}`, `option/{ime}`), prijava `PROBA_WP_USER` + `PROBA_WP_APP_PASSWORD`. Na živoj stranici odbija sve.
  Od 1.4.4 kopija je uvijek noindex (meta, Rank Math, X-Robots-Tag, robots.txt bez sitemapa).
- Iz ove sesije internet ide samo preko HTTPS-a (FTP/SSH ne rade).
- Kopija koristi **kopije Google tablica** („Kopija datoteke Članovi Plan A nova” i
  „Kopija datoteke Prijave na izlete nova”), nikad prave tablice. Spojeno 12.10.2026.: kopija stranice ima
  svoj ključ (Članstvo → „Novi ključ”), vlastitu objavu skripte u kopiji tablice članova (Web app URL
  `…AKfycbwb1mz…/exec`) i tablicu za agenciju `…/d/1fIOcuG71XDa…`. Prava tablica za agenciju je `…/d/1z53LwhYgw87…`.
  **Prije svakog slanja u tablice provjeri** (`option/plan_a_clanstvo`: `agency_url`, `sheet_url`) da nije upisana prava.
  Iz sesije se do Googlea ne može (script.google.com je blokiran); vezu provjerava Igor gumbom „Provjeri vezu”.
- Na kopiji je probni izlet 9013 „PROBNI IZLET – test tablice (ne prijavljivati)”, 22.11.2026., proizvod 9014.
- Lozinke i ključeve korisnik upisuje u postavke okruženja; **nikad ih ne traži u chatu**.

## Osobni podaci i sigurnost

- Podaci članova (CSV, imena, OIB…) samo u privremenoj mapi sesije, **nikad u git/GitHub**.
- E-mailovi vodiča i osoblja upisani su u postavkama WordPressa, ne u kodu.
- Nepotvrđenim članovima ne slati nove mailove za potvrdu bez izričitog dogovora (za četiri postojeća je dogovoreno da se ne šalju).
- Nepotvrđene članove nikad ne brisati automatski.
- Ne mijenjati WpTravelly, WooCommerce, temu ni dodatke trećih strana (Mage Partial Payment Pro se ne koristi).

## Način rada

- Za svaku pravu promjenu koda: podigni verziju (i readme), napravi zip u korijenu repozitorija,
  commit, push na pravu granu, pošalji zip korisniku. **Uz svaku novu verziju pošalji sve promijenjene
  zipove** i reci je li neki zip isti kao prije (tada ga ne treba ponovno instalirati).
- Samo odgovor na pitanje → bez nove verzije.
- Testiraj na lokalnoj probnoj stranici prije slanja; reci što je provjereno, a što nije.

## Novi sustav izleta (`plan-a-prijave`, grana `prijave`)

Dokument s popisom i planom: https://claude.ai/code/artifact/e688755a-5ea4-46bb-821a-bb93322913df

1. **Popis** – gotovo.
2. **Obrazac za uređivanje izleta** – gotovo (0.2.0). Sprema iste podatke kao WpTravelly (`ttbm_*`),
   zaključava termine/karte/usluge s prijavama, gasi WpTravellyjev obrazac i automatsko spremanje na
   ekranu izleta, vraća standardne WordPressove okvire, „Kopiraj izlet”.
3. **Stranica izleta i prijava bez WpTravellyja** – gotovo (0.3.0). Mapa `plan-a-prijave/ttbm/` je skraćena
   kopija WpTravellyja 2.3.4 (isti HTML/CSS/JS, klase `TTBM_*`, kuke, tekstovi u domeni `tour-booking-manager`),
   učitava se samo kad je WpTravelly isključen; dok je uključen, on ima prednost. Provjereno lokalno
   (WordPress + SQLite + WooCommerce + WpTravelly s GitHuba u privremenoj mapi): HTML stranica izleta jednak
   (osim namjerno izbačenog srca za popis želja), ista narudžba (`_ttbm_*`), isti `ttbm_booking`/
   `ttbm_service_booking`, ista slobodna mjesta i promjena stanja, isti ispis naših dodataka.
   Važno: WpTravelly svim mailovima stavlja HTML oblik (`wp_mail_content_type`) – zadržano.
   Stari WpTravellyjevi kratki kodovi (`[travel-list]` …) prikazuju `[plan-a-izleti]`.
   Izvorni kod WpTravellyja: `github.com/magepeopleteam/tour-booking-manager` (2.3.4 = commit prije „Bump to 2.3.5”).
4. **Usporedni rad na kopiji stranice.**
5. **Prelazak** (mjesec dogovoriti s korisnikom); WpTravelly ostaje instaliran za povratak.

Dvije rate: karta čiji naziv sadrži „prve rate” (npr. „Uplata prve rate”); druga rata 30 dana prije izleta.

## „Unos izleta” za kolege – IZRAĐENO u plan-a-prijave 0.7.0 (13.10.2026.)

Stranica `[plan-a-unos-izleta]` u plan-a-prijave: noindex, izvan sitemapa, bez predmemorije; pristup **jednom zajedničkom
šifrom** (Igorova odluka, dva kolege); skraćeni obrazac → izlet „Na čekanju” + mail Igoru → Igor pregleda i objavi.
Klasa `Plan_A_Prijave_Unos`, assets/unos.js|css; postavke Plan A → Unos izleta (kolege) (opcija `pap_unos`: hash, to, pages, template);
predložak = skica s `_pap_template`; izlet kolege = pending s `_pap_unos` (by, by_name, note, warn). Lokalno sve isprobano. Kolege upisuju i **cijenu**.
Predložak (Važno, uvjeti, agencija, izjava…) bit će **poseban predložak, nevezan za ijedan izlet** – radimo ga zajedno.
Struktura od Igora (redom kako šalje):
1. **Opis:** samo dva polja – naslov izleta i tekst izleta; bez alata za uređivanje, samo zalijepi (copy-paste);
   tekst se sprema jednoobrazno (Plan_A_Prijave_Text::clean – font, veličina, boje van). Odabrano: polje bez gumba
   koje pri lijepljenju **čuva podebljano, odlomke i popise** (contenteditable + čišćenje), ne goli tekst.
2. **Osnovni podaci:** samo lokacija – padajući izbornik postojećih (bez dodavanja nove) i trajanje – samo broj **dana**
   (bez sati/minuta). **Bez noćenja i bez „najviše osoba”** – broj osoba kolege upisuju samo na karti (mjesta); „najviše
   osoba” se pri spremanju upiše isto kao mjesta na karti. Kvačica „slobodna mjesta” se ne prikazuje (ostaje uključena).
3. **Termini (0.7.1):** samo **početak (datum) i sat polaska**; kraj = početak + (broj dana − 1), prijave do početka izleta.
   Datumi hrvatski (21.11.2026.), sat 24 h.
   **Poveznice (0.7.1):** telefoni → tel:, „pristupnica…” → stranica pristupnice (Plan_A_Prijave_Text::links; sprema se kod
   unosa kolega, a za sve izlete i pri prikazu opisa i „Važno za izlet”).
   **Ispravak (0.7.2):** popis „Tvoji poslani izleti” samo za odabranog „Tko unosi”; izlet na čekanju → „Ispravi” (AJAX
   pap_unos_get) → isti izlet se ažurira (edit_id, keep_<slot> za nepromijenjene slike), mail „Ispravljen izlet…”.
   Ispravak prepisuje i ono što je Igor u međuvremenu promijenio u adminu dok je izlet na čekanju.
   **Galerija (0.7.3):** najmanje 4, „+ Dodaj sliku” do 20 (gcount; okviri g1…gN po redu, × briše dodatni okvir).
**0.7.4:** admin ekran izleta prilagođen mobitelu (editor.css @media 782/600, natpisi polja tablica iz zaglavlja – JS labelTables);
admin Termini: samo početak, kraj = početak + (dani − 1), prijave do početka (i za „više datuma”); popis izleta: gumb
„Popunjeno” / „Otvori prijave” (meta `_pap_full`; filtri u Plan_A_Prijave_Seats: dostupno 0, kapacitet = prodano, košarica odbija).
**0.7.5:** Popunjeno i na popisu izleta (filtar `plan_a_izleti_sold_out` → oznaka + na kraj; gumb briše cache popisa i radi wp_update_post),
na karti „Popunjeno” umjesto „Sold out”.
**0.7.6:** lijeva traka: Opis, Osnovni, Termini, Cijene, Važno za izlet, Vodiči, Galerija, Važno, Uključeno i aktivnosti, Zašto s nama,
Povezani, Ostalo; Osnovni podaci = lokacija + trajanje (dana); max_people = mjesta karata; noćenja off; slobodna mjesta uvijek on.
**0.7.7:** Granične, vizne i zdravstvene formalnosti – kartica u adminu izleta (ispod Važno): jedan tekst po zemlji, zajednički za sve
izlete (opcija `pap_granice`, prvo punjenje iz najnovijeg izleta u zemlji; „+ Dodaj zemlju” stvara i lokaciju). Na stranici izleta blok
čiji naslov sadrži „granič”/„vizn” u Važno dobiva tekst zemlje iz lokacije + druge zemlje (`_pap_country2`, nije obavezna – admin
Osnovni podaci i obrazac kolega); bez bloka → doda se na kraj; zemlja bez teksta → ostaje tekst izleta (filtar `plan_a_prijave_daywise`).
**0.7.8:** blok „Minimalni broj sudionika i rok obavijesti o otkazu” (naslov sadrži „minimaln”) računa se sam (Plan_A_Prijave_Uvjeti):
minimum = floor(70 % mjesta karata bez „prve rate”), najmanje 1; rok po trajanju: < 2 dana → 48 sati, 2–6 → 7 dana, > 6 → 20 dana
(Igor može poslati svoju tablicu ako se razlikuje). U adminu se ne uređuje (samo položaj). Dok nije objavljen – računa se iznova;
pri prvoj objavi (transition → shutdown) zaključa se u `_pap_uvjeti` + upiše u ttbm_daywise_details i više se ne mijenja (ugovor
s agencijom; Igor smije mijenjati samo mjesta na karti). Izleti objavljeni prije 0.7.8 bez `_pap_uvjeti` ostaju s ručnim tekstom.
„Kopiraj izlet” ne kopira `_pap_uvjeti`. Granične formalnosti se NE zaključavaju (Igor: smiju se mijenjati i nakon objave).
**0.7.9:** jednokratno (opcija `pap_granice_split`) lokacije „A - B” (Italija - Francuska) razdvojene: izleti → lokacija A + druga zemlja B, tekst ide zemlji A, pojam obrisan.
**0.7.10:** granice: za svaku zemlju poseban blok „Granične, vizne i zdravstvene formalnosti (Zemlja)” na mjestu bloka o granicama
(i za jednu zemlju); zemlja bez teksta se ne prikazuje → crveno upozorenje u adminu (Osnovni podaci i Važno). Stranica izleta: sav
upisani tekst (opis, Važno za izlet, Važno, Zašto s nama) 16 px / prored 1.65 na svim širinama (Text::black_css); naslovi pitanja (h5) ostaju.
Na kopiji/živoj nakon zipa očistiti SpeedyCache (inače stara stranica).
**0.7.11:** obrazac kolega čuva poveznice (JS KEEP A + kses a[href] http/https/mailto/tel, nevaljane → tekst, make_clickable bez nofollow);
jednokratno (opcija `pap_uvjeti_objavljeni`, Igorova odluka 14.10.) svi tada objavljeni izleti dobili izračunat minimum/rok i zaključani
(kopija: Doček 25 → 17, Hvar 8 → 5); Osnovni podaci: Lokacija i Druga zemlja u jednoj liniji.
**0.7.12:** admin Važno: blok o granicama = granice_rows (pitanja po zemljama kao na stranici, samo za čitanje, JS odmah osvježi pri
promjeni zemlje – papEd.granice); prikaz Važno za izlet i Važno kroz wpautop (+ Text::links) → razmaci/odlomci iz upisa ostaju;
CSS razmak između odlomaka .9em, unutar stavke popisa .35em. Pravilo za tekstove: jednoobrazno, ali bold, poveznice i razmaci ostaju.
**0.7.13 + plan-a-jedrenje 1.9.3 (14.10., Igor potvrdio, zipovi poslani za živu):** ispod „Rezervirajte” (izlet) i ispod kalendara + gumba
„Pošalji zahtjev” (jedrenje) poveznica „▶ Pogledaj kako se prijaviti/rezervirati (1 min)” → prozorčić na istoj stranici u WhatsApp stilu
s videom (assets/pa-video.js|css – iste datoteke u oba dodatka; video 720×1280 MP4 u assets/video/; pokreće se bez zvuka + „Uključi zvuk”;
zatvara × u zaglavlju i × na videu, Esc, klik van; na mobitelu prozorčić od vrha (100dvh, × se ne skriva iza adresne trake); CSS/JS verzija = verzija.filemtime). Video izleta = „prijava-na-izlet-3” od Igora. Testni Chromium ne pušta H.264 – puštanje provjerava Igor na mobitelu.
**0.7.16:** na stranici „Maksimalno osoba” uvijek = zbroj mjesta karata bez „prve rate” (filtar get_post_metadata za ttbm_travel_max_people_allow, samo izvan admina) – Dinara je imala 9 uz 10 mjesta.
**0.8.0 + plan-a-clanstvo 1.15.8 (14.10., Igor odobrio prijedlog A–G):** LISTA ČEKANJA – popunjen termin (slobodno 0 ili `_pap_full`) → u book_now_smart
umjesto „Rezervirajte” blok „Izlet je popunjen” + gumb „Prijavi se na listu čekanja” (assets/cekanje.js|css, prozorčić: ime, prezime, mobitel, e-mail nije obavezan,
broj osoba 1–4, privola; zaštita kao plan-a-kontakt; isti mobitel (zadnjih 8 znamenki) se ne upisuje dvaput). Klasa Plan_A_Prijave_Cekanje, CPT `pap_cekanje`
(post_parent = izlet, meta `_pap_w`). Mail o novom upisu i „oslobodilo se mjesto” (provjera nakon promjene narudžbe/karata/_pap_full + svaki sat, opcija
`pap_wait_free`) idu **vodičima izleta** (Igor: ne njemu) – adrese iz Članstvo → Postavke → Vodiči (svaka riječ naziva vodiča), bez adrese → adresa trgovine.
Izleti → Liste čekanja (Javljeno, Obriši). Narudžba s istim mobitelom/e-mailom miče osobu s liste. Brisanje 30 dana nakon izleta.
Tablica: akcija `ag_wait` (red čekanja `pap_wait_queue`, ponavlja svaki sat), skripta **v21**: redovi ključa `W|w<ID>` ispod sudionika, žuti, Br. Č1, Č2…,
„LISTA ČEKANJA” u Prijavio/la, napomena datum · osobe · javljeno; ne broje se nigdje, u naslovu „· lista čekanja N”, arhiva ih briše.
Na kopiji: probni izlet 9013 ostavljen Popunjeno s jednim probnim upisom (PROBA Lista čekanja). Skriptu v21 treba zalijepiti u kopiju i u pravu tablicu članova.
**0.8.1:** bez natpisa ispod gumba (Licencirano vođenje / Sigurnost je prva / Fleksibilnost). **0.8.2 (14.10., brzina + hrvatski):** slider (TTBM_Custom_Slider)
slike `large`, `data-pap-big` = pap_hero za ekrane > 1400 px (JS u ttbm_plugin_global.js), slajdovi 2+ `data-pap-lazy` (bez inline pozadine, čeka se samo prva);
showcase i kartice (grid_list_style) `medium_large`; mage-icon: mali font `mage-icon-pap.woff2` (23 ikone, unicode-range) + puni za ostale; smart.php bez
`placeholderLoader` (kostura), shimmer keyframes transform; inline skripta nakon </aside> premješta prijavu na mobitelu; Admin::FRONT_HR – prijevodi samo kad
Loco (.mo na živoj, languages/plugins/tour-booking-manager-hr.mo) nema prijevoda. Kopija Dinara mobitel: ocjena 27–38 → 52–62, težina 6,1 → 3,1 MB, CLS 0,5–0,8 → 0,02–0,19.
**0.8.3 (14.10.):** Plan_A_Prijave_Brzina – preload prve slike izleta (large) u wp_head; slider čeka samo prvu sliku (ne .sliderShowcase/.sliderPopup);
prvi i zadnji slajd bez lazy (zadnji viri lijevo i bio je LCP); posjetiteljima bez admin trake dequeue admin-bar + dashicons. srcset za Flatsome banner ISPROBAN
I ODBAČEN (Flatsome tada preuzme i izvornik i manju sliku – 9,8 → 15 MB). Mjerenje žive 14.10. (mob/desk): naslovnica 70/96, izlet 65/83, izleti 77/93,
jedrenje 79/95, kontakt 89/98; na mobitelu LCP često kolačić-traka Complianz (prikazuje se JS-om) i TTFB 1–2,5 s (hosting/SpeedyCache) – nije naš kod.
Naslovnica: 3 PNG bannera 2,2–2,8 MB + logotipi 250 KB → Igoru poslani JPG/PNG (naslovnica-slike.zip) za zamjenu u Flatsome banneru (nije kod).
**plan-a-aplikacija 1.5.0 (14.10., obje grane, isti zip):** postavka `icon_bg` „Podloga ikone i početnog zaslona” (ikone GD + manifest background_color);
SW: stranice s X-Plan-A-Offline (izleti, izlet) – ako mreža ne odgovori u 2,5 s, spremljena verzija (nova se sprema u pozadini). Novi logo (crni → prozirno,
`plan-a-logo-prozirno.png` poslan Igoru) na tamnoplavoj #13304a; Igor u Postavke → Plan A aplikacija odabire logo + podlogu. Instalirane aplikacije: Android
ikonu osvježi sam (do ~dan), iPhone tek nakon ponovne instalacije.
4. **Cijene:** karta „Uplata” (naziv fiksan) – kolege upisuju cijenu i broj mjesta (= najviše osoba); bez rezervirano/opis/
   prodano/dodaj kartu. **Prva rata:** polje „Iznos prve rate (€)” ispod cijene, s oznakom „opcija” – prazno = nema rata;
   upisano → karta „Uplata prve rate” s tim iznosom (dijeli mjesta s „Uplata”). **Osiguranje kolege ne vide** – računa se samo (0.7.14,
   podaci agencije 14.10.2026.): cijena **≤ 355 € → 9,95 €**, **> 355 € → točno 2,8 % cijene, na cent** (0.8.5, Igor 19.10.; prije 0.7.15–0.8.4 naviše na 50 centi; 480 → 13,44 €, 400 → 11,20 €);
   iznos u mailu Igoru (Plan_A_Prijave_Unos::insurance).
5. **Uključeno / nije uključeno i aktivnosti:** sve postojeće stavke s kvačicama i ikonicama, **bez dodavanja novih**
   i bez mijenjanja ikonica (to radi Igor u adminu). Kvačica u jednom stupcu miče istu stavku iz drugog.
6. **Važno za izlet:** pitanja su **fiksna** (iz predloška, uvijek istim redoslijedom), kolege ih ne mogu mijenjati,
   premještati ni brisati; svi blokovi otvoreni; ispod svakog pitanja polje samo za zalijepiti (isto kao opis).
   Polja su **prazna i obavezna** – obrazac se ne može predati dok svi odgovori nisu upisani.
7. **Važno (uvjeti, agencija, izjava):** kolege **ne vide**; dolazi iz predloška. Sadržaj predloška Igor i Claude uređuju
   **kasnije, kad sve proradi** (standardno, sitne izmjene).
8. **Zašto s nama i kontakt:** kolege **ne vide**; uvijek isto – popunjava se iz predloška.
9. **Vodiči:** samo **imena s kvačicama** (bez slika, opisa i dodavanja novog); popis se sam puni iz Plan A → Vodiči
   (novi vodič u adminu → odmah na popisu). Barem jedan vodič obavezan (zajednička šifra – ne znamo tko unosi).
10. **Povezani izleti:** kolege ne vide – automatski izbor (kao sada kad nije ništa označeno). **SEO** automatski (plan-a-seo/prijave).
11. **Kategorija:** sama prema trajanju (1 → Izlet 1 dan, 2 → Izlet 2 dana, 3 → Izlet 3 dana, 4+ → Višednevni izleti).
12. **Slike:** obavezno **glavna slika + najmanje 4 slike galerije** (do 20, gumb „+ Dodaj sliku”); kolege učitavaju **bilo kakve slike**
   (bilo koji omjer/veličina, s mobitela ili računala) – **sustav sam obreže na 3:2 (sredina) i smanji na 1800×1200 JPG**
   (u pregledniku prije slanja + na poslužitelju), okrene prema EXIF-u; premale (< 1200 px) prihvati, ali napomena Igoru.
   Alt/SEO slika automatski (plan-a-seo). Igor dobiva draft s već pripremljenim slikama.
   **Okvir 3:2 za svaku sliku:** kolega mišem (ili prstom na mobitelu) **povlači sliku u okviru** i bira koji dio ostaje
   (+ klizač za povećanje); izrez koji vidi = izrez koji se sprema (rezanje u pregledniku na 1800×1200).
0. **Na vrhu stranice: „Tko unosi izlet”** – obavezan izbor, **isti popis kao Vodiči** (Krešimir Gracin, Igor Kutil,
   Ivan Videk, Sonja Papeš; novi vodič → sam se doda); ime ide u mail Igoru i u izlet; taj vodič se sam označi u Vodičima.

**plan-a-prijave 0.8.4 (19.10.2026., obje grane):** dok izlet nije objavljen (draft/pending/future), svako spremanje (wp_after_insert_post) iznova:
SEO (Seo::fill force – i preko ručno upisanog), osiguranje (usluga s „osiguranj” = Unos::insurance(cijena „Uplata”)), kategorija trajanja (ttbm_tour_org,
ostali pojmovi ostaju) – klasa Plan_A_Prijave_Skica; predložak (_pap_template) se ne dira. Nakon objave ništa samo (SEO: ručno ostaje, usporedba tolerantnija – Seo::same).
Na kopiji provjereno s pravim Rank Mathom (probna skica 9076, obrisana).
**0.8.5:** osiguranje iznad 355 € točno 2,8 % na cent. **0.8.6 + plan-a-tinder 1.2.1 (19.10.):** osiguranje se računa pri SVAKOM spremanju izleta (i objavljenog),
jednom (opcija `pap_ins_028` = {time, changed: {id: stari iznos}}) usklađeni svi postojeći izleti; postojeće narudžbe se ne mijenjaju. Tinder gumb: „WhatsApp grupa Plana A”.

**plan-a-prijave 0.8.7 (19.10.2026., obje grane) – NARUDŽBA 0 € (#9115 Zimsko III, Zrinka V.):** SpeedyCache služi stranice izleta iz predmemorije danima
(živa 19.10.: sve od 15./16.10.), ttbm_form_nonce vrijedi ≤ 24 h → s isteklim kodom TTBM add_cart_item_data preskoči karte → stavka bez karte/termina, 0 €,
„U obradi/plaćeno”. Popravak Plan_A_Prijave_Zastita: wp_loaded 5 istekli nonce → svjež (POST add-to-cart = proizvod izleta + ticket_qty>0); add_to_cart_validation
bez karte → odbij; checkout: stavka izleta bez karte → makni + greška. Lokalno: istekli kod → 150 € (prije 0 €). #9115 na živoj ispravlja Igor (živa samo čitanje).

**plan-a-clanstvo 1.15.9 + skripta v22 (19.10.2026., obje grane):** tablica agencije – blok = datum_izlet; promjena datuma objavljenog izleta ostavljala je
prazne blokove (Tinder 11./12./14.12.) → Agency::drop_old šalje ag_drop za poslane ključeve budućih termina kojih više nema; skripta briše samo PRAZNE
blokove (s osobama/listom čekanja ostaju), vraća obrisane → makni iz `plan_a_clanstvo_ag_tours`. order_rows preskače stavku bez _ttbm_date (blok „_7730”
bez datuma nastao od #9115 – Zrinka; taj blok agencija/Igor briše ručno). Test lažnom tablicom (gstest/run6.js) prošao. Igor: skriptu v22 u tablicu članova (+ kopija).
**1.15.10 + skripta v23 (19.10.):** sheet_() od 1. rujna pravi i stupac „Članarina <sljedeća godina>” (Zrinka #9118 Zimsko III 15.1.2027. → mail
„Članarina 2027 nije plaćena” je točan: izlet 2027 traži članarinu 2027, a stupca 2027 nije bilo, kvačica je bila u 2026). Test gstest/run7.js.
**1.15.11 (19.10.):** članarina po godini – postavka `iznosi` (Postavke → 2. Članarina → „Iznos za pojedinu godinu”, redovi `godina=iznos`, zadano `2026=5`; Igor: do 31.12.2026. 5 €, za izlete 2027.+ 15 €). Data::fee_amount($year) → Hub3::payment, mail/uplatnica/2D kod, blagajna, pristupnica (tekuća godina). 2D kod se pravi iznova ako se iznos promijenio (`_pac_barcode_amt`); zajednička slika (rezerva/„slika”) samo za redovni iznos. Već poslani mailovi s 15 € za 2026. se NE šalju ponovno. llms.txt (plan-a-seo) još piše 15 €.

**BRZINA 20.10.2026. – plan-a-prijave 0.8.8 + plan-a-izleti 1.24.2 + plan-a-jedrenje 1.9.5 (obje grane, isti zipovi):** izlet: prvi `.sliderItem` ima
`activeSlide` već u HTML-u (prije translateX(100%) dok JS ne krene – LCP render delay); `[data-bg-image]` > 300 px ispod ekrana (povezani izleti, vodiči) →
IntersectionObserver (`ttbm_pap_defer_bg`, data-pap-io 1/2, rootMargin 400); vodič `medium_large`. Izleti: ikonice `fas fa-*` iz ICON_GUESS kao ugrađeni SVG
(Plan_A_Izleti_Fa, oblici iz fa-solid-900.ttf), pap-ic bez fontova, FA/mage CSS samo kad ikonica nije pokrivena. Jedrenje: zamućene pozadine slajdova
(`data-pajd-bg`) tek za trenutni ± 1. Kopija mob (prije → poslije): izlet 64 → 77 (LCP 14,5 → 4,2 s), izleti 74 → 80, jedrenje 75 → 87 (2,4 → 0,7 MB).
Igoru poslane optimizirane slike (slike-brzina.zip: Sonja, vodič s amblemima, Hvar-1, Igor, Krešimir – PNG 2 MB → JPG ~200 KB) za zamjenu u medijima.
Ostaje: SpeedyCache postavke (C) zajedno s Igorom, naslovnica na živoj = PNG banneri (nije kod).

## plan-a-tinder 1.0.0 (19.10.2026., obje grane, isti zip) – „Tinder i planinarenje – vikend u Ramića dvorima” (14.–15.12.2026.)

Igor unio izlet na živu kao neobjavljen (skica). Dodatak samo za izlete označene u Izleti → Tinder i planinarenje (opcija `plan_a_tinder`:
tours, wa, min, title): kartica „Tko dolazi” na vrhu kućice za prijavu (hook ttbm_smart_registration_controls prio 1 – Igor: „iznad rezervacije”):
broj muškaraca/žena, raspon godina tek od `min` (3) osoba po skupini, traka X od Y mjesta (karte bez „prve rate”), gumb WhatsApp grupe
(nova grupa „Izleti Plan A 2”, https://chat.whatsapp.com/K8g93lFVNIE0EwlmQ7NFX); blagajna: spol + godina rođenja obavezno za svaku osobu
(meta stavke `_pat_osobe`, isti redoslijed kao `_pac_osobe`), „Hvala” s WhatsApp gumbom; admin popis (ime, spol, godina, dob, mobitel, e-mail,
narudžba, „isključi” → `_pat_x`). Broje se narudžbe on-hold/processing/completed/pending; transient `pat_stats_{tour}`. Tablica agencije nepromijenjena.
Na kopiji instaliran bez odabranog izleta (opciju ne može postaviti pomoćnik). Tekst objave poslan Igoru (s [NEDOSTAJE] mjestima).
**1.1.0 (19.10.):** izlet se uključuje kvačicom na ekranu izleta (okvir „Tinder – Tko dolazi”, meta `_pat_on`, vlastita WhatsApp grupa `_pat_wa`,
inače zadana iz postavki) – isti dodatak za Tinder i planinarenje, Tinder i jedrenje (kao običan izlet) itd. Opcija `tours` iz 1.0.0 jednom prenesena u meta.
Izbornik Izleti → Tinder izleti.
**1.1.1:** WhatsApp grupa je JEDNA zajednička grupa Plan A za sve izlete (Igor: „Izleti Plan A 2”, kad se napuni mijenja se samo poveznica u postavkama) – polje grupe kod izleta maknuto, tekst „Pridruži se WhatsApp grupi Plan A”.
**1.2.0:** Izleti → Tinder izleti: kvačice izleta i u Postavkama (sinkronizirano s `_pat_on`), na vrhu „Uključeni izleti (N)” s brojkama; „Naslov iznad brojača” (Igor je upisao naziv izleta – treba „Tko dolazi”).
**1.3.0 (19.10.):** POTICAJ po izletu (kartica izleta u Izleti → Tinder izleti, meta `_pat_seed` = on, m, m_min, m_max, z, z_min, z_max): dodani ♂/♀ s rasponom godina pribrajaju se pravim prijavama samo na stranici (Plan_A_Tinder::shown); upisani raspon se prikazuje uvijek (prag `min` samo za prave); poticaj nikad ne pokaže „popunjeno” dok ima pravih slobodnih mjesta (ukupno se ograniči na mjesta − 1). Ne rezervira mjesta, nije u narudžbama ni tablici. Kvačica isključuje, brojke ostaju.
**1.4.0 (19.10.):** poticaj umanjuje i PRIKAZ slobodnih mjesta (hero `.ttbm_available_seat` – assets/tinder.js, MutationObserver jer TTBM JS osvježava broj; kartica „slobodno još” = TTBM get_total_available − poticaj), najmanje 1 dok ima stvarnih. Skriveni #ttbm_total_available, max količine i košarica ostaju stvarni – prijaviti se mogu svi stvarno slobodni (Igor: marketinški trik, ne smije blokirati prijave).
**1.4.1 (19.10.):** kartica 403 → ~195 px (Igor: na računalu se gumb Rezervirajte vidio tek na kraju stranice – desni stupac `.ttbm_content__right` je sticky, booking kartica 820 px > ekran laptopa); napomena o privatnosti sada samo `title` kartice.
**1.5.0 (19.10.):** košarica samo spol; godina = Plan_A_Tinder::birth_year(_pac_osobe[i]) – datum iz pristupnice u košarici ili zapis člana po e-mailu (kupac: billing e-mail); u people() i za stare narudžbe bez `god`.

## plan-a-baneri 1.0.0 (19.10.2026., obje grane, isti zip) – vrh naslovnice umjesto slidera

Igor odobrio sliku (hero/mock.html u privremenoj mapi). Kratki kod `[plan-a-naslovnica]`; postavke Postavke → Plan A naslovnica (opcija `plan_a_baneri`).
Dijelovi: velika slika (odabir iz medija; bez slike = slika najbližeg izleta) + natpis `{broj}` izleta + naslov/podnaslov + 2 gumba (Pogledaj izlete /izleti/,
Plan izleta 2026. /plan-izleta-2026/) + kvačice; kartica „Sljedeći polazak” (do 3 nepopunjena, odbrojavanje do sata polaska ttbm_travel_start_time /
particular, slobodna mjesta – Tinder s poticajem umanjeno); traka akcije (članarina `{iznos}` iz Clanstvo::fee_amount, `{do}`, `{dana}`, nestaje nakon
promo_until 2026-12-31); traka Tinder (najbliži izlet s _pat_on, ♂/♀ Plan_A_Tinder::shown); kartice izleta (60 dana, min 4, max 8; oznake Još N mjesta ≤5,
Popunjeno · lista čekanja, ♂/♀). Podaci iz Plan_A_Izleti_Data/Shortcode, transient `pab_tours` 5 min. JS: odbrojavanje, skrivanje isteklog (predmemorija).
Full-bleed: width 100vw + margin calc(50% - 50vw). Ikone SVG (WP emoji → slike s s.w.org). Na kopiji stranica 9077 /proba-naslovnice/ (za pregled).
**1.1.0 + plan-a-jedrenje 1.9.4 (19.10., Igor odobrio prijedlog):** SLIDER – slajd 1 izleti (nepromijenjen), slajd 2 jedrenje (kalkulator: klizač min–max osoba,
cijena broda odabranog tjedna / n; slobodni tjedni iz Plan_A_Jedrenje_Data::public_weeks, zadano 3; „Pošalji zahtjev” → stranica jedrenja ?tjedan=&osoba= – jedrenje.js
1.9.4 sam označi tjedan i osobe; slika = s2_image ili Jedrenje_Slider::block_images()[0]), slajd 3 kviz (dani 1/2–3/4+, vrsta planina/ferrata/zima/more iz naziva+pojmova,
mjesec; popuštanje uvjeta → „Najbliže tvom izboru”). Pozadine se pretapaju, slike 2/3 tek nakon load; tablete s crtom; 8 s; staje na hover i rad u kartici; na mobitelu
samo odabrani slajd (display none) i BEZ automatske vrtnje (visina). Trake i kartice zajedničke. Kartice: popunjeni na kraj (Igor).
**1.1.1 (19.10.):** Igor: „ne vrte se” – zastajalo je dok je miš bilo gdje na slici (uvijek) → zastaje samo na bijeloj kartici; vrti se i na mobitelu, ali samo dok je
vrh vidljiv (IntersectionObserver 35 %); kratki kod se prikazuje samo jednom (na živoj je bio upisan dvaput – Flatsome Text + još jednom u sadržaju).
**1.1.2 (19.10.):** Igor je u zaglavlju teme isključio gumbe „Plan izleta 2026.” i „WhatsApp” (prelazili preko kartice Sljedeći polazak) → mali gumbi u slideru
(`.pab-bar2`: tablete lijevo, gumbi desno; mobitel ispod tableta), postavke links_on, link1/link1_url, link2/link2_url (prazno = Plan_A_Tinder::wa_url()).
**1.1.3 (19.10.):** tablete vidljivije (Igor: „presijavanje”): ikonica, bijeli rub, odsjaj `pab-shine` 4,5 s preko neaktivnih, `is-hello` poskok kad se slider prvi put vidi.
**1.1.4 (19.10.):** na živoj (Flatsome „lazy load” uključen, na kopiji nije) slike 2./3. slajda bile prazne – Flatsome prepiše `<img data-src>` u prazni SVG → sada `<span class="pab-bg" data-pab-src/srcset>`, JS nakon load napravi `<img class="no-lazy skip-lazy">`.
**1.1.5 (19.10.):** mobitel kompaktnije (Igor): naslov 31 px, gumbi jedan pored drugog, manja kartica polaska/odbrojavanje, tablete, gumbi, trake; računalo isto. Vodiči HTML (5 vodiča + Ivor Joksović 099 478 80 76) i „Zašto s Planom A?” (5 licenciranih) poslani kao datoteke (nisu u gitu).
**1.1.6 (19.10.):** mobitel: kvačice u jednom redu (nowrap 11 px), tablete se JS-om premještaju u `.pab-hero__text` aktivnog slajda (ispod kvačica, iznad kartice); računalo u `.pab-bar2`.
**1.1.7 (19.10.):** mobitel: tablete centrirane, bez podnaslova, manji razmaci – dno bijele kartice ~610 px od vrha slidera (Igor: kartica vidljiva bez listanja; ekran ~700 px).

## NA ČEKANJU (Igor 16.10.2026.): „Popis za sutra” – Super Ferrata Dinara 17.10.

Igor misli da popis vodiču nije točan (agencija dodavala ljude/mijenjala u tablici). ISPRAVAK moje prve procjene: „Popis za sutra”
SE VEĆ SASTAVLJA IZ TABLICE (ag_list, stanje u trenutku slanja – 16.10. 08:29), uključuje ručno dodane redove u bloku izleta.
Moguće razlike: izmjene u tablici nakon 08:29; osobe upisane izvan bloka (ispod naslova drugog izleta / drugi list); „Vodiči: –” jer
izlet nije u Planu izleta → popis ide samo Igoru (admin), ne vodiču. S weba su samo slobodna mjesta (Ops::free_seats, Cekanje) i lista čekanja.
Čeka se Igor: što točno ne odgovara (tko fali / koji podatak). Ideje: ponovno slanje popisa na zahtjev (gumb), vodiči iz izleta kad nema u planu.

## STANJE ŽIVE 14.10.2026. (20:45, provjereno)

prijave 0.8.1, clanstvo 1.15.8, jedrenje 1.9.3, seo 1.0.3, kontakt 1.0.0, ostali isti; WpTravelly isključen, ali se sam nadogradio na 2.3.6.
Na svih 6 izleta: video da, natpisa ispod gumba nema. Kopija tablice na kopiji stranice javlja još skriptu v20 (ag_wait „Nepoznata radnja”) –
Igor treba Deploy → New version. Skriptu v21 u pravoj tablici iz sesije nije moguće provjeriti (Članstvo → Postavke → „Skripta u tablici”).

## STANJE ŽIVE 13.10.2026. (navečer, provjereno)

WooCommerce 11.2.1 (baza 11.2.1), plan-a-kontakt 1.0.0 (Kontakt HTML v3 s `[plan-a-kontakt]`), seo 1.0.0, prijave 0.6.3,
clanstvo 1.15.7, kosarica 1.6.5, izleti 1.22.1, jedrenje 1.9.2, aplikacija 1.4.0, kalkulator 1.0.1, **newsletter 1.1.1 aktivan**
(Igor ga je sam instalirao). Obrisani CF7, WPForms, CFDB7, YITH, Nextend, smtp-mailer, KEKS, Borgun, Imsanity, Say What,
poklon-bon (stranica /poklon-bon/ još pokazuje `[plan-a-poklon-bon]`). Kopija = živa (+ pomoćnik).

## PRELAZAK NA ŽIVU – NAPRAVLJEN 13.10.2026. (noću, Igor)

Živa sada: clanstvo 1.15.7, kosarica 1.6.5, izleti 1.22.1, jedrenje 1.9.2, plan-a-prijave 0.6.1 (aktivan; 0.6.2 poslan – SEO pri otvaranju izleta + gumb),
WpTravelly 2.3.4 isključen (ne brisati – povratak). Stranice izleta služi plan-a-prijave (provjereno). #8989 je dobila
mail s ostatkom (400 €) 13.10. u 06:22. Zimsko III: 12 mjesta. Doček nakon prelaska pokazivao 0 slobodnih – Igoru
rečeno da kod „Uplata” upiše 25. Grana za živu stranicu sada ima i plan-a-prijave (razlika prema `prijave` samo pomoćnik).

## Plan A SEO (13.10.2026.) – `plan-a-seo` 1.0.0 (obje grane, isti zip) + plan-a-prijave 0.6.3

Uz Rank Math: ključna riječ, SEO naslov („Naslov – Plan A planinarenje”; izleti „– Plan A planinarski izleti”, jedrenje
„– Plan A jedrenje”) i opis za stranice/objave/vodiče; alt svih slika (iz stranice gdje se koristi, inače naziv izleta
pogođen iz imena datoteke, inače ime datoteke); noindex + izvan sitemapa: probne stranice (popis u Alati → Plan A SEO),
košarica/blagajna/račun, blokovi teme, portfolio, mjesta/hoteli WpTravellyja, skriveni proizvodi; JSON-LD: udruga
(adresa, kontakt, mreže), izlet = Event po terminu (cijena, slobodna mjesta) + FAQPage; /llms.txt za AI.
Ručno upisano se ne dira (osim: opis = naslov/prekratak, ostatak predloška, duplikat s druge stranice; vlasnik
duplikata = najstarija stranica). Naslovnica se ne dira. „Vrati sve” vraća sve upisano. REST za provjeru:
/wp-json/plan-a-seo/v1/{stats,preview,images-preview,llms,run,undo}. Na kopiji popunjeno i provjereno 13.10.
Živa: instalirati plan-a-seo + prijave 0.6.3, Alati → Plan A SEO → 1, 2, 3, SpeedyCache očistiti, u Search Consoleu ponovno sitemap.

**plan-a-seo 1.0.1 (14.10.2026., obje grane):** noindex + izvan sitemapa i llms.txt i za stranicu s `[plan-a-unos-izleta]` i stranice
s našim kratkim kodom koji više ne postoji (Poklon bon); llms.txt: duplikat opisa → opis iz teksta (description_for), vodiči s licencama
iz opisa vodiča; uvod (good_lead) preskače redove Vodiči/Važno/Mjesto polaska/telefon/plaćanje i rate; pri novoj verziji briše se
Rank Math sitemap cache (option `plan_a_seo_ver`); llms.txt dio „Ukratko” (Igor potvrdio 14.10.: SGVH/UIMLA, za koga, gdje, Prečko/kombiji, posudba opreme, osiguranje, članarina 15 € + Iglu sport, privatne ture, jedrenje); ORG description sa SGVH i UIMLA-e. 1.0.2: osnovano 2023. (llms „Osnovano” + foundingDate). Poklon bon u mirovanju (Igor 14.10.) – stranica ostaje skrivena (noindex). 1.0.3: vodiči – sažetak sa stranice Vodiči (Schema::GUIDES za llms, GUIDES_SHORT za Google, po slugu vodiča; Sonja = sonja-papes). Stara adresa pristupnice u Googleu izbrisana (Igor) – preusmjeravanje ne treba. Na živoj opisi Jedrenje/O nama/Ustrojstvo isti → Igor treba pokrenuti Alati → Plan A SEO.

### Plan prelaska (arhiva)

Stanje žive 13.10.2026. (WC system_status): clanstvo 1.14.5, kosarica 1.5.5, izleti 1.21.1, jedrenje 1.8.2,
aplikacija 1.4.0, kalkulator 1.0.1, poklon-bon 1.3.0 (isključen), newsletter NIJE instaliran, WpTravelly 2.3.4,
WSB HUB3 3.0.2, Rank Math, Backuply, SpeedyCache. Plaćanje: samo bankovni prijenos (Teya/KEKS isključeni).
Zipovi (zadana grana = prijave, isti): clanstvo 1.15.7, kosarica 1.6.5, izleti 1.22.1, jedrenje 1.9.2 + plan-a-prijave 0.6.1.
plan-a-pomocnik NIKAD na živu. Newsletter ne instalirati bez Igorova dogovora.

Mjesta kod izleta s ratama (nova pravila: mjesta su samo na karti „Uplata”, prva rata ih dijeli):
7935 Doček – prodano 20 prve rate + 4 Uplata = 24 → kod „Uplata” upisati 25 (ili koliko je stvarno mjesta) PRIJE gašenja WpTravellyja;
7730 Zimsko III – Uplata 12 + prva rata 12 (prodano 3) → Igor odlučuje 12 ili 24; 9001 Hvar – 8 + 8 (prodano 2) → 8.

Redoslijed: 0) Backuply puna kopija · 1) dodaci (clanstvo, kosarica, izleti, jedrenje) zajedno · 2) skripta v20 u tablici
članova + ukljuciBrzoOsvjezavanje + Provjeri vezu (prave tablice!) · 3) #8989: odluka o ponovnom mailu (ide automatski
nakon instalacije clanstva unutar sat-dva, sada s uplatnicom na agenciju) · 4) mjesta kod rata · 5) plan-a-prijave
instalirati i uključiti (WpTravelly još uključen) · 6) isključiti WpTravelly (ne brisati) · 7) SpeedyCache očistiti
· 8) provjere · Povratak: uključiti WpTravelly, isključiti plan-a-prijave (podaci su isti ttbm_*).

## Plan A kontakt (13.10.2026.) – `plan-a-kontakt` 1.0.0 (obje grane, isti zip)

Zamjena za obrisane Contact Form 7 / WPForms / CFDB7. `[plan-a-kontakt]` (atributi predmet, izlet, telefon, gumb,
poruka, okvir; `?izlet=ID`). Mail na primatelja (prazno = WooCommerce adresa pošiljatelja, info@), Reply-To pošiljatelj,
potvrda pošiljatelju bez njegova teksta; poruke spremljene u admin → Poruke (privatni `plan_a_poruka`, brišu se nakon
24 mj.). Spam: skriveno polje, JS + 3 s, 5/10 min po IP-u, 40/h ukupno, ≤2 poveznice. Na kopiji instaliran i probano.
Živa: instalirati, Kontakt (624) zamijeniti `[contact-form-7 …]` s `[plan-a-kontakt]`, objava „Poziv na avanturu”
`[wpforms id="3136"]` → `[plan-a-kontakt predmet="…" okvir="da"]`, SpeedyCache očistiti.

**Kopija ujednačena sa živom 13.10.2026.:** obrisani isti dodaci kao na živoj (CF7 + dodaci, CFDB7, WPForms, YITH, Nextend,
smtp-mailer, KEKS, Borgun, Aircash, Imsanity, Say What, poklon-bon, …), verzije dodataka iste kao na živoj (pomoćnik 1.4.5),
u smeću iste stranice (aaa, find, grid, wishlist, hotel-search…), „Poziv na avanturu” bez ankete, Doček Uplata 25.
Na kopiji ostaju i pomoćnik, plan-a-newsletter i plan-a-kontakt (Kontakt već ima `[plan-a-kontakt]`, HTML v3).
**WooCommerce 11.2.1 na kopiji 13.10.2026.:** baza se sama nadogradila; prije i poslije iste provjere (13 stranica, narudžba
bankovnom uplatom #9057/#9060 → Završeno, 31 primjerak maila, 0 PHP grešaka) – bez razlike. Predlošci 11.2 se mijenjaju samo
za varijante proizvoda/dostavu (ne utječe na naše). Flatsome ima zastarjele kopije predložaka (samo obavijest). Živa: može se nadograditi.

## Za živu stranicu – na čekanju (odraditi zajedno pri prelasku)

Igor ne pamti ništa od ovoga: kad završimo s kopijom, **ja** pripremim sve zipove za živu stranicu i
napišem redoslijed (što isključiti, što instalirati, što provjeriti). Do tada na živoj ništa ne tražiti.

- **Jednak izgled svih mailova (12.10.2026.)** – zadana grana i `prijave`, isti zipovi: plan-a-kosarica 1.6.5,
  plan-a-clanstvo 1.15.7, plan-a-jedrenje 1.9.2, plan-a-newsletter 1.1.1, plan-a-izleti 1.22.1 (zajednička
  datoteka `includes/plan-a-email.php`, klasa `Plan_A_Email`, mora biti ista u svim dodacima). Na kopiji
  primjerci: pomoćnik REST `mail/samples` (grupa all|wc|clanstvo|jedrenje|ostalo, order, rate_order,
  member_email, samo, pokazi=1 = samo HTML bez slanja). 1.6.2/1.15.2: ostali WooCommerceovi mailovi (otkaz,
  povrat, napomena, račun) imaju sažetak umjesto tablice sa sličicama, uplatnica WSB samo u „Prijava zaprimljena”
  i računu, slike nikad šire od kartice; druga rata i članarina imaju pravu sliku uplatnice HUB-3A (GD, predložak
  i fontovi se čitaju iz dodatka WSB HUB3).
- **PRAVILO (13.10.2026.): račun udruge (S.R.D. Plan A, HR33…) je SAMO za članarinu.** Sve ostale uplatnice
  (izleti, druga rata, jedrenje) glase na agenciju Adventure Donkey j.d.o.o. (podaci iz dodatka WSB HUB3).
  Ispravljeno u plan-a-clanstvo 1.15.4 (do tada je druga rata išla na račun udruge – i na živoj!).
- **Skripta tablice v20 (1.15.7):** v20 – boja višestruke narudžbe i na kućicama Uplaćeno i 2. rata; v18 – ručno dodana osoba dobiva napomenu „unesen ručno”; v19 – novi stupac „Iznos 2. rate” iza „Iznos” (stara tablica i Arhiva se same prošire, provjereno na lažnoj tablici). Na živoj nakon instalacije zamijeniti skriptu u tablici članova (Kopiraj skriptu → Apps Script → New version).
- **Pravopis**: „Nova godina” (blagdan) velikim slovom, „Cjelokupni” (ne „Cijelokupni”, ispravljeno u 1.6.3/1.15.4).
- **plan-a-clanstvo (sada 1.15.7)** (zadana grana i `prijave`, isti zip): druga rata = puna cijena − prva rata po
  osobi, osiguranje se ne odbija; ispravak pune cijene (`ticket_type_price`, do 1.14.5 ostatak je bio 0 € i
  mail za drugu ratu nije odlazio). Nakon instalacije se propušteni mail jednom ponovi – na živoj to je
  narudžba **#8989** (prva rata 12.10.2026.). Prije instalacije pitati Igora želi li to slanje.
  1.14.8: 2D kod u mailu za drugu ratu. 1.14.7: kvačica Otkazao → mail kupcu (+ kopija nama za pojedinog sudionika), osiguranje po osobi,
  zamjena bez međukoraka, vraćanje iz tablice ne šalje „Nova prijava”.
- **Prije uključivanja plaćanja karticom** (sada je na živoj samo uplata na račun): popraviti dvostruki mail
  „Cjelokupni iznos je uplaćen” (U obradi → Završeno) i prvu ratu plaćenu karticom (krivi mail, nema maila
  s ostatkom ni podsjetnika). Ostatak druge rate računa se po današnjoj cijeni pune karte (bolje zapamtiti
  cijenu pri prijavi).
- **Pravilo za otkaz (12.10.2026.):** agencija ne vraća novac za otkazani izlet/jedrenje; s osiguranjem od otkaza kupac
  se obraća osiguranju. Nigdje ne smije pisati da vraćamo novac ili postotak (mailovi, uvjeti jedrenja). U novim
  uputama za agenciju maknuti „povrat radite kao i dosad”.
- **Upute (PDF) za agenciju i Krešu – NAPRAVLJENE 13.10.2026.** (izvor HTML u privremenoj mapi sesije, upute/), prije: napraviti nove **nakon prelaska, prema živoj stranici** (dogovor s Igorom; popis razlika napisan mu 12.10.2026.): naslov maila
  „Cjelokupni iznos je uplaćen…”, novi izlet samo za termine u 120 dana (najviše 3), zaštićeni stupci pitaju
  „jeste li sigurni”, mail s ostatkom 1–2 h nakon kvačice, stupac 2. rata postoji svugdje (siv), POPUNJEN /
  skoro pun (≤3) / nitko nije platio (10 dana), Krešo: kopija podsjetnika i 3 dana prije izleta; novo:
  mail kupcu pri otkazivanju.
- **plan-a-prijave** (novi sustav, faza 5): zajednička mjesta za sve karte (karta „prve rate” nema svoja
  mjesta). Prije prelaska pregledati izlete s prvom ratom – npr. Doček Nove godine (7935) ima Uplata 5 +
  prva rata 20: ako treba 25 mjesta, upisati 25 kod Uplate. Usluge (osiguranje) nisu ograničene.
- Pri prelasku: ostali dodaci iz `prijave` sa oznakom `-prijave` u verziji, WpTravelly isključiti (ne
  brisati), SEO se popunjava pri spremanju izleta, ikonice aktivnosti postave se pri prvom ulasku u admin.
