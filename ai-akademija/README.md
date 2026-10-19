# AI Akademija – web stranica (WordPress + Flatsome)

Novi projekt, grana `claude/ai-akademija-homepage-jie92k`. Online AI tečajevi. Naziv: **AI Akademija** (s K).

## Datoteke

Mapa je **WordPress dodatak** `ai-akademija` (zip `ai-akademija.zip` u korijenu repozitorija).

| Datoteka | Što je |
| --- | --- |
| `ai-akademija.php` | dodatak: kratki kod `[ai-akademija-naslovnica]`, učitava CSS/JS i font |
| `templates/naslovnica.php` | HTML naslovnice |
| `assets/naslovnica.css`, `naslovnica.js` | izgled naslovnice, strelice tečajeva |
| `assets/ai-akademija.css` | cijela stranica: font, boje, gumbi koji se preljevaju, zaglavlje |
| `includes/podaci.php` | **svi podaci** (tečajevi, mentori, cijene, kontakt, pitanja) – trenutno izmišljeni |
| `includes/ui.php` | zajednički dijelovi (zaglavlje stranice, kartice, pitanja, poziv na akciju) |
| `templates/*.php` | stranice (popis kratkih kodova u `readme.txt`) |
| `assets/stranice.css` | izgled podstranica, obrasca i podnožja |
| `assets/slike/` | znak (prozirno), logo s natpisom, ikona stranice |

**Zašto kratki kod, a ne HTML blok:** WordPress (`wpautop`) u HTML bloku/sadržaju stranice ubacuje `<p>` i `<br>`
između oznaka i u CSS – naslovnica se raspala (0.1.0 na aiakademija.hr, 19.10.2026.). Izlaz kratkog koda ne dira.

## Dizajn (dogovoreno)

- Font **Plus Jakarta Sans** (Google Fonts).
- Boje: tamni naslovi `#0B1033`, tekst `#4A5272`, podloga `#F6F8FF`, crte `#E6E9F5`;
  plava `#1F5BFF`, ljubičasta `#7B2FFF`, ružičasta `#E21EFF`, cijan `#00D2FF` (iz logotipa).
- **Gumbi i istaknute riječi se polako preljevaju iz boje u boju** (plava → ljubičasta → ružičasta → cijan),
  animacija `aka-flow`; isključeno kad korisnik u sustavu traži manje animacija.
- Sve klase počinju s `aka-` da se ne miješaju s Flatsomeom.

## Postavljanje na WordPress

1. Dodaci → Dodaj novi → Učitaj `ai-akademija.zip` → Aktiviraj.
2. Ako je u Flatsome → Napredno → Custom CSS zalijepljen CSS iz 0.1.0, obrisati ga (sada je u dodatku).
3. Stranica „Naslovnica”: predložak **Page – Full Width**; u sadržaju samo `[ai-akademija-naslovnica]`
   (u UX Builderu: obrisati stari HTML element, dodati element *Text* ili *HTML* samo s kratkim kodom).
4. Zaglavlje (Prilagodi → Zaglavlje): logo = `assets/slike/ai-akademija-logo.png` (širina ~230 px); HTML element
   `<span class="aka-slogan">Uči. Primijeni. Napreduj.</span>`; izbornik Programi ▾, Mentori, Za tvrtke, Blog;
   ikona pretraživanja; gumb „Prijava →” (stil Primary – sam dobiva prijelaz boja).
5. Postavke → Čitanje → statična naslovnica. Nakon svake nove verzije očistiti predmemoriju (ako je ima).

## Verzije

- **0.5.0 (19.10.2026.)** – mobilni izbornik Flatsomea (CSS na klasama .mobile-sidebar / .nav-sidebar / .off-canvas, bez diranja teme).
  Provjereno na lokalnoj kopiji strukture Flatsomeova izbornika (ne na pravom Flatsomeu).

- **0.4.0 (19.10.2026.)** – poveznice po kratkom kodu (AKA_Linkovi), Postavke → AI Akademija (stanje stranica, izrada
  stranica koje nedostaju, glavni izbornik), mentori ↔ tečajevi, ostali tečajevi, kontakt s predmetom iz poveznice.
  Provjereno lokalno: svih 28 poveznica 200 i sva sidra postoje, i kad stranica ima drugu adresu (mentori → nasi-mentori).

- **0.3.0 (19.10.2026.)** – struktura cijele stranice: Programi, Tečaj, Mentori, Za tvrtke, O nama, Kontakt, Cijene,
  Česta pitanja, Uvjeti, Podnožje + dodatni dijelovi naslovnice. Podaci izmišljeni (lorem ipsum). Kontakt obrazac:
  mail administratoru, Reply-To pošiljatelj, zaštita (skriveno polje, 3 s, nonce). Provjereno lokalno (sve stranice
  200, bez PHP/JS grešaka, bez vodoravnog pomicanja na 1440 i 390 px, obrazac šalje mail, bot ne).

- **0.2.0 (19.10.2026.)** – dodatak s kratkim kodom (HTML blok se raspadao zbog wpautop); CSS i font iz dodatka;
  laptop: brojke 2 × 2, tečajevi po dva u redu do 1200 px. Provjereno na lokalnom WordPressu (sadržaj bez ijednog
  ubačenog `<p>`/`<br>`, 1440/1280/390 px, bez JS grešaka). Na Flatsomeu nije provjereno (tema nije dostupna lokalno).

- **0.1.0 (19.10.2026.)** – prva naslovnica: hero (znak umjesto fotografije, plutajuće kartice), popularni programi
  (5 tečajeva, strelice), Custom CSS, logotip.
