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
  `tour-booking-manager` (WpTravelly), `cron`, `log`,
  `meta/{id}`, `option/{ime}`), prijava `PROBA_WP_USER` + `PROBA_WP_APP_PASSWORD`. Na živoj stranici odbija sve.
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

## Za živu stranicu – na čekanju (odraditi zajedno pri prelasku)

Igor ne pamti ništa od ovoga: kad završimo s kopijom, **ja** pripremim sve zipove za živu stranicu i
napišem redoslijed (što isključiti, što instalirati, što provjeriti). Do tada na živoj ništa ne tražiti.

- **plan-a-clanstvo 1.14.7** (zadana grana i `prijave`, isti zip): druga rata = puna cijena − prva rata po
  osobi, osiguranje se ne odbija; ispravak pune cijene (`ticket_type_price`, do 1.14.5 ostatak je bio 0 € i
  mail za drugu ratu nije odlazio). Nakon instalacije se propušteni mail jednom ponovi – na živoj to je
  narudžba **#8989** (prva rata 12.10.2026.). Prije instalacije pitati Igora želi li to slanje.
  1.14.7: kvačica Otkazao → mail kupcu (+ kopija nama za pojedinog sudionika), osiguranje po osobi,
  zamjena bez međukoraka, vraćanje iz tablice ne šalje „Nova prijava”.
- **Prije uključivanja plaćanja karticom** (sada je na živoj samo uplata na račun): popraviti dvostruki mail
  „Cijelokupni iznos je uplaćen” (U obradi → Završeno) i prvu ratu plaćenu karticom (krivi mail, nema maila
  s ostatkom ni podsjetnika). Ostatak druge rate računa se po današnjoj cijeni pune karte (bolje zapamtiti
  cijenu pri prijavi).
- **Upute (PDF) za agenciju i Krešu** napraviti nove **nakon prelaska, prema živoj stranici** (dogovor s Igorom; popis razlika napisan mu 12.10.2026.): naslov maila
  „Cijelokupni iznos je uplaćen…”, novi izlet samo za termine u 120 dana (najviše 3), zaštićeni stupci pitaju
  „jeste li sigurni”, mail s ostatkom 1–2 h nakon kvačice, stupac 2. rata postoji svugdje (siv), POPUNJEN /
  skoro pun (≤3) / nitko nije platio (10 dana), Krešo: kopija podsjetnika i 3 dana prije izleta; novo:
  mail kupcu pri otkazivanju.
- **plan-a-prijave** (novi sustav, faza 5): zajednička mjesta za sve karte (karta „prve rate” nema svoja
  mjesta). Prije prelaska pregledati izlete s prvom ratom – npr. Doček Nove godine (7935) ima Uplata 5 +
  prva rata 20: ako treba 25 mjesta, upisati 25 kod Uplate. Usluge (osiguranje) nisu ograničene.
- Pri prelasku: ostali dodaci iz `prijave` sa oznakom `-prijave` u verziji, WpTravelly isključiti (ne
  brisati), SEO se popunjava pri spremanju izleta, ikonice aktivnosti postave se pri prvom ulasku u admin.
