# Plan A aplikacija

WordPress dodatak koji stranicu srd-plan-a.hr pretvara u web aplikaciju (PWA) koju posjetitelji mogu instalirati na mobitel. Ne mijenja temu Flatsome, WpTravelly, WooCommerce ni dodatak „Plan A izleti”.

## Što radi

- **Manifest:** naziv „Plan A”, jezik hr, način prikaza standalone, boja teme (zadano boja zaglavlja iz Flatsomea), početna adresa `/izleti/`. Ikone 192 px, 512 px i maskable 512 px te ikona za iPhone (180 px) generiraju se iz logotipa odabranog u postavkama.
- **Service worker:**
  - **Statične datoteke** (CSS, JS, fontovi, slike) uzimaju se iz predmemorije i osvježavaju u pozadini.
  - **`/izleti/` i stranice izleta** uvijek se učitavaju s mreže. Spremljena kopija koristi se samo kad nema interneta i sprema se samo za neprijavljene posjetitelje.
  - **Nikad se ne spremaju** i uvijek idu na mrežu: košarica, plaćanje, korisnički račun (i njihove stvarne WooCommerce adrese), `/wp-admin/`, `/wp-login.php`, AJAX (`admin-ajax.php`, `?wc-ajax=`), REST (`/wp-json/`), `/wc-api/` te svaka stranica s parametrima u adresi (narudžba, plaćanje i slično).
  - **Bez interneta** stranica koja nije spremljena prikazuje izvanmrežnu stranicu: „Trenutno nema internetske veze. Rezervacije su moguće samo uz vezu.” s gumbom „Pokušaj ponovno”.
- **Poziv na instalaciju:**
  - Na mobitelu se pri svakoj posjeti na dnu prikazuje traka „Instaliraj aplikaciju Plan A” s gumbima „Instaliraj” i „Ne sada”. „Ne sada” traku skriva do sljedeće posjete, a na iPhoneu na 30 dana (Safari ne može saznati je li aplikacija već instalirana).
  - U postavkama se može odabrati i „od druge posjete”, gdje „Ne sada” traku skriva na 30 dana.
  - Na iPhoneu traka umjesto gumba „Instaliraj” daje uputu „Podijeli → Dodaj na početni zaslon”.
  - Traka se ne prikazuje na košarici, plaćanju ni korisničkom računu.
- **Donja navigacija** (samo kad je stranica otvorena kao instalirana aplikacija): Izleti, Plan izleta, Košarica (s brojem stavki) i Kontakt.

## Statistika (samo za administratore)

**Postavke → Plan A aplikacija → kartica Statistika** prikazuje:
- **Instalacije** ukupno i u zadnjih 30 dana (Android / iPhone / ostalo),
- **aktivne uređaje** po danu: koliko je uređaja taj dan barem jednom otvorilo aplikaciju,
- **otvaranja aplikacije** u zadnjih 7 i 30 dana,
- **učestalost:** otvaranja po aktivnom danu i aktivne dane po instalaciji,
- **tablicu za zadnjih 30 dana**,
- **rezervacije izleta iz aplikacije:** poslane (klik na konačni gumb za plaćanje) i plaćene/potvrđene u zadnjih 30 dana s iznosom, udio aplikacije u svim rezervacijama izleta te popis zadnjih narudžbi iz aplikacije.

Kako se broji:
- **Instalacija** je prvo otvaranje aplikacije s početnog zaslona na nekom uređaju.
- **Brisanje se ne vidi:** Android i iPhone ne javljaju stranici ni instalaciju ni brisanje aplikacije, pa je „aktivni uređaji” najbolji pokazatelj stvarnog korištenja.
- **Što se ne broji:** posjete preko preglednika i administratori.
- **Rezervacija iz aplikacije:** dok je stranica otvorena kao instalirana aplikacija, postavlja se kolačić sesije `plan_a_app_src` (samo platforma: android/ios/other). U običnom pregledniku se briše. Kad kupac klikne konačni gumb za plaćanje i WooCommerce stvori narudžbu s izletom (WpTravelly), narudžbi se dodaje oznaka izvora. Na stranici narudžbe u administraciji tada piše „Izvor: aplikacija Plan A (Android)”.
- **„Plaćene / potvrđene”** su narudžbe sa statusom U obradi, Završeno ili Na čekanju. Narudžbe koje čekaju plaćanje, otkazane i neuspjele broje se samo kao poslane.
- **Privatnost:** osim kolačića sesije za izvor rezervacije ne koriste se kolačići, IP adrese ni identifikatori. Uređaj samo pamti je li već javio prvo otvaranje i današnji dan, a na poslužitelju se sprema samo dnevni zbroj po platformi. Ipak je dobro u politici privatnosti spomenuti anonimnu statistiku korištenja aplikacije.
- **Isključivanje:** u postavkama odznačite „Statistika”.

## Upute za instalaciju

1. Prenesite `plan-a-aplikacija.zip` (iz korijena repozitorija) preko **Dodaci → Dodaj novi → Prenesi dodatak**, zatim kliknite **Instaliraj sada** i **Aktiviraj**.
2. Otvorite **Postavke → Plan A aplikacija**.
3. Kliknite **Odaberi logotip** i odaberite kvadratnu PNG sliku, najmanje 512 × 512 px. Nakon spremanja ispod gumba vidjet ćete generirane ikone.
4. Provjerite **boju aplikacije** (zadana je boja zaglavlja teme) i **adrese navigacije**:
   - Izleti: `/izleti/`
   - Plan izleta: `/plan-izleta-2026/` (svake godine samo promijenite adresu)
   - Košarica: `/cart/` (ili stvarna adresa košarice, npr. `/kosarica/`)
   - Kontakt: `/kontakt/`
5. Kliknite **Spremi promjene**.
6. Ako koristite dodatak za predmemoriju (npr. LiteSpeed Cache, WP Rocket) ili Cloudflare, očistite predmemoriju. Iz predmemoriranja izuzmite adrese koje sadrže `plan-a-app=`.

Stranica mora raditi preko **HTTPS-a**, inače se aplikacija ne može instalirati.

### Uklanjanje

- **Postavke → Plan A aplikacija → odznačite „Uključena”**, ili **deaktivirajte dodatak**. Service worker kod svakog posjetitelja pri sljedećoj posjeti sam briše svoju predmemoriju i odjavljuje se. Ako service worker u tom pregledniku već radi, to se dogodi najkasnije za oko 10 minuta. Ako koristite predmemoriju stranica, nakon deaktivacije je očistite.
- **Brisanje dodatka** uklanja i njegove postavke te generirane ikone.

## Što provjeriti na Androidu (Chrome)

1. Otvorite `https://srd-plan-a.hr/izleti/`. Na dnu se odmah pojavljuje traka „Instaliraj aplikaciju Plan A”.
2. „Ne sada” sakriva traku do kraja posjete. Kad zatvorite karticu i stranicu otvorite ponovno, traka se opet pojavljuje.
3. „Instaliraj” otvara Chromeov prozor za instalaciju. Nakon instalacije na početnom zaslonu je ikona Plan A s logotipom, bez bijelog ruba izrezanog na krivom mjestu.
4. Otvorite aplikaciju s početnog zaslona:
   - otvara se `/izleti/` bez adresne trake,
   - gornja traka je u boji aplikacije,
   - na dnu je navigacija Izleti / Plan izleta / Košarica / Kontakt.
5. Dodajte izlet u košaricu: broj na ikoni košarice se poveća.
6. Prođite cijelu kupnju: košarica, plaćanje, potvrda narudžbe. Sve radi kao na webu, bez starih podataka.
7. Uključite način rada u zrakoplovu:
   - `/izleti/` i izleti koje ste već otvarali se prikazuju,
   - košarica i neotvarani izleti prikazuju poruku „Trenutno nema internetske veze…”,
   - „Pokušaj ponovno” radi kad se veza vrati.
8. U Chromeu na računalu: **F12 → Application → Manifest** ne smije prikazivati greške. U **Service Workers** treba biti aktivan `?plan-a-app=sw`.

## Što provjeriti na iPhoneu (Safari)

1. Otvorite stranicu. Na dnu se pojavljuje uputa „Dodirnite Podijeli, zatim ‚Dodaj na početni zaslon’”.
2. Podijeli → **Dodaj na početni zaslon**. Naziv mora biti „Plan A”, a ikona logotip na bijeloj podlozi.
3. Otvorite aplikaciju s početnog zaslona. Nema Safarijeve adresne trake, a na dnu je navigacija s 4 stavke koja ne prekriva sadržaj ni crtu za povratak na početni zaslon.
4. Kupnja (košarica i plaćanje) radi, a nakon plaćanja aplikacija se vraća na potvrdu narudžbe.
5. U načinu rada u zrakoplovu otvaraju se ranije otvoreni izleti, a ostalo prikazuje izvanmrežnu poruku.

Napomena: na iPhoneu Safari može obrisati spremljene podatke aplikacije koja se dulje vrijeme ne koristi. To je normalno i aplikacija ih pri sljedećem otvaranju ponovno preuzme.
