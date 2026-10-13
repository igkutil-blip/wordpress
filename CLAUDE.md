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

## U PRIPREMI: „Unos izleta” za kolege (13.10.2026.)

Stranica `[plan-a-unos-izleta]` u plan-a-prijave: noindex, izvan sitemapa, bez predmemorije; pristup **jednom zajedničkom
šifrom** (Igorova odluka, dva kolege); skraćeni obrazac → izlet „Na čekanju” + mail Igoru → Igor pregleda i objavi.
Igor šalje slike i opise željenog izgleda – **ne kretati u izradu dok ne pošalje strukturu.** Kolege upisuju i **cijenu**.
Predložak (Važno, uvjeti, agencija, izjava…) bit će **poseban predložak, nevezan za ijedan izlet** – radimo ga zajedno.
Struktura od Igora (redom kako šalje):
1. **Opis:** samo dva polja – naslov izleta i tekst izleta; bez alata za uređivanje, samo zalijepi (copy-paste);
   tekst se sprema jednoobrazno (Plan_A_Prijave_Text::clean – font, veličina, boje van). Odabrano: polje bez gumba
   koje pri lijepljenju **čuva podebljano, odlomke i popise** (contenteditable + čišćenje), ne goli tekst.
2. **Osnovni podaci:** samo lokacija – padajući izbornik postojećih (bez dodavanja nove) i trajanje – samo broj **dana**
   (bez sati/minuta). **Bez noćenja i bez „najviše osoba”** – broj osoba kolege upisuju samo na karti (mjesta); „najviše
   osoba” se pri spremanju upiše isto kao mjesta na karti. Kvačica „slobodna mjesta” se ne prikazuje (ostaje uključena).
3. **Termini:** samo **jedan termin od–do** (bez „više datuma”): početak (datum), sat polaska, kraj (prazno = isti dan),
   prijave do (prazno = do početka). Datumi hrvatski (21.11.2026.), sat 24 h.
4. **Cijene:** karta „Uplata” (naziv fiksan) – kolege upisuju cijenu i broj mjesta (= najviše osoba); bez rezervirano/opis/
   prodano/dodaj kartu. **Prva rata:** polje „Iznos prve rate (€)” ispod cijene, s oznakom „opcija” – prazno = nema rata;
   upisano → karta „Uplata prve rate” s tim iznosom (dijeli mjesta s „Uplata”). **Osiguranje kolege ne vide**: cijena aranžmana **≤ 350 € →
   usluga „Osiguranje” 9,95 €** automatski; **> 350 €** iznos računaju kolegice (agencija) → Igoru napomena u mailu, upisuje sam.
   Detalje unosa osiguranja Igor dogovara poslije, kad završimo prednji dio.
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
12. **Slike:** obavezno **glavna slika + točno/barem 4 slike galerije (ukupno 5)**; kolege učitavaju **bilo kakve slike**
   (bilo koji omjer/veličina, s mobitela ili računala) – **sustav sam obreže na 3:2 (sredina) i smanji na 1800×1200 JPG**
   (u pregledniku prije slanja + na poslužitelju), okrene prema EXIF-u; premale (< 1200 px) prihvati, ali napomena Igoru.
   Alt/SEO slika automatski (plan-a-seo). Igor dobiva draft s već pripremljenim slikama.
   **Okvir 3:2 za svaku sliku:** kolega mišem (ili prstom na mobitelu) **povlači sliku u okviru** i bira koji dio ostaje
   (+ klizač za povećanje); izrez koji vidi = izrez koji se sprema (rezanje u pregledniku na 1800×1200).
0. **Na vrhu stranice: „Tko unosi izlet”** – obavezan izbor, **isti popis kao Vodiči** (Krešimir Gracin, Igor Kutil,
   Ivan Videk, Sonja Papeš; novi vodič → sam se doda); ime ide u mail Igoru i u izlet; taj vodič se sam označi u Vodičima.

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
