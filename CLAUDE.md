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
  Na kopiji je uključen dodatak **Disable Emails** – ne isključivati.
- Kopija koristi **kopije Google tablica** („Kopija datoteke Članovi Plan A nova” i
  „Kopija datoteke Prijave na izlete nova”), nikad prave tablice.
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
3. **Stranica izleta i prijava bez WpTravellyja** – sljedeće. Isti izgled kao sada; prijava u WooCommerce
   košaricu s istim podacima u narudžbi (`_ttbm_id`, `_ttbm_date`, `_ttbm_ticket_info`,
   `_ttbm_service_info`); zapisi `ttbm_booking` / `ttbm_service_booking` za brojanje mjesta;
   zamjenske klase `TTBM_Function` i dr. pod istim imenima jer ih koriste naši dodaci.
4. **Usporedni rad na kopiji stranice.**
5. **Prelazak** (mjesec dogovoriti s korisnikom); WpTravelly ostaje instaliran za povratak.

Dvije rate: karta čiji naziv sadrži „prve rate” (npr. „Uplata prve rate”); druga rata 30 dana prije izleta.
