# AI Akademija – pravila za rad (pročitaj prije svega)

Korisnik je Igor. Odgovaraj **na hrvatskom**. Ovo je **zaseban projekt**: web stranica **AI Akademija** (aiakademija.hr,
online AI tečajevi). **Nema veze s Planom A** (srd-plan-a.hr, dodaci plan-a-*, sustav izleta) – ništa iz Plana A ne
dolazi u ovu granu i ništa iz ove grane ne ide u grane Plana A.

## Grane

| Grana | Projekt |
| --- | --- |
| `ai-akademija` | **AI Akademija** – samo ova grana (od 19.10.2026., odvojena od Plana A, vlastita povijest) |
| `claude/optimistic-knuth-7myrcm`, `prijave` | Plan A – **ne dirati iz ovog projekta** |

Sav rad na AI Akademiji: samo u grani `ai-akademija`. Nikad ne spajati (merge/cherry-pick) s granama Plana A.

## Projekt

Igorova nova web stranica za online AI tečajeve, **AI Akademija** (s K, ne „AIAC”). WordPress + **Flatsome**
(HTML blok / Custom CSS / po potrebi naši dodaci). Sve u mapi `ai-akademija/` (vidi njezin README).
Dizajn prema Igorovoj slici: font Plus Jakarta Sans, tamnoplavi naslovi #0B1033, svijetla podloga #F6F8FF, boje iz logotipa
plava #1F5BFF → ljubičasta #7B2FFF → ružičasta #E21EFF → cijan #00D2FF. **Gumbi (i istaknute riječi) se uvijek preljevaju iz
boje u boju** (animacija `aka-flow`) – Igorov zahtjev. Klase s prefiksom `aka-`.

- **0.1.0:** naslovnica (hero + popularni programi), Custom CSS, logotip očišćen (prozirno) + logo s natpisom.
- **0.2.0 (19.10.2026.):** `ai-akademija/` je DODATAK (zip `ai-akademija.zip`): kratki kod `[ai-akademija-naslovnica]`
  (foto="…" po želji), globalni CSS + font iz dodatka. HTML blok se na aiakademija.hr raspao – wpautop ubacuje <p>/<br>
  (i u <style>). **Pravilo: veći HTML za ovu stranicu uvijek kao kratki kod iz dodatka, ne zalijepljen u HTML blok.**
  Lokalni test: WordPress s GitHuba + SQLite u privremenoj mapi.
- **0.3.0 (19.10.2026.):** struktura cijele stranice – kratki kodovi `ai-akademija-{naslovnica,programi,tecaj,mentori,tvrtke,
  o-nama,kontakt,cijene,pitanja,uvjeti,podnozje}` (popis i adrese u `ai-akademija/readme.txt`). Svi podaci u
  `includes/podaci.php` (AKA_Podaci) – IZMIŠLJENI (mentori Ana Horvat… su izmišljene osobe, cijene, kontakt, OIB 000…) –
  zamijeniti stvarnima. Tečaj: podstranica /programi/<slug>/ prepoznaje se po adresi. Obrazac: admin-post `aka_kontakt`,
  mail na admin_email. Uvjeti korištenja: lorem ipsum (pravi tekst – pravnik). Privatnost: WordPressova stranica privatnosti.
- **0.4.0:** poveznice se NE pišu kao adrese nego preko `AKA_Linkovi` (stranica se traži po kratkom kodu, cache
  `aka_linkovi`, briše se pri spremanju stranice); u predlošcima `AKA_UI::url('/programi/')` i dalje radi (prevodi se).
  Postavke → AI Akademija: stanje stranica, „Napravi stranice koje nedostaju”, „Postavi glavni izbornik” (primary + primary_mobile).
  Do aiakademija.hr se iz sesije ne može (proxy odbija) – stanje stranica Igor vidi u adminu.
- **0.5.0:** mobilni izbornik Flatsomea stiliziran u `assets/ai-akademija.css` (logo kroz ::before, tražilica, podizbornik,
  gumb). Elemente izbornika (tražilica, newsletter, razdjelnik, gumb) Igor bira u Prilagodi → Zaglavlje → Mobile Menu.
- Brojke 12.000+ polaznika i 4.9/5 su iz dizajna – zamijeniti stvarnima prije objave. Fotografija u heroju: čeka Igorovu sliku.

## Način rada

- Za svaku pravu promjenu: podigni verziju (i readme), napravi zip `ai-akademija.zip` u korijenu, commit, push na
  `ai-akademija`, pošalji zip Igoru. Samo odgovor na pitanje → bez nove verzije.
- Testiraj lokalno prije slanja; reci što je provjereno, a što nije.
- Lozinke i ključeve Igor upisuje u postavke okruženja; nikad ih ne traži u chatu.
