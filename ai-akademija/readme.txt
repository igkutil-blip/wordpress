=== AI Akademija ===
Stable tag: 0.4.0
Requires at least: 6.0
Requires PHP: 7.4

Naslovnica i izgled stranice AI Akademija (Flatsome).

== Upotreba ==
Na stranicu (predložak Page – Full Width) upiši samo kratki kod:
[ai-akademija-naslovnica]
Fotografija umjesto znaka (kasnije): [ai-akademija-naslovnica foto="https://…/slika.jpg"]

== Kratki kodovi (svaka stranica: predložak Page – Full Width, u sadržaju samo kratki kod) ==
Naslovnica          [ai-akademija-naslovnica]
Programi            [ai-akademija-programi]           (adresa /programi/)
Tečaj               [ai-akademija-tecaj]              (podstranica od Programi, adresa /programi/uvod-u-ai/ – tečaj se prepozna po adresi; ili slug="uvod-u-ai")
Mentori             [ai-akademija-mentori]            (/mentori/)
Za tvrtke           [ai-akademija-tvrtke]             (/za-tvrtke/)
O nama              [ai-akademija-o-nama]             (/o-nama/)
Kontakt             [ai-akademija-kontakt]            (/kontakt/)
Cijene              [ai-akademija-cijene]             (/cijene/)
Česta pitanja       [ai-akademija-pitanja]            (/cesta-pitanja/)
Uvjeti korištenja   [ai-akademija-uvjeti]             (/uvjeti-koristenja/)
Podnožje            [ai-akademija-podnozje]           (Flatsome → Footer → blok)

== Changelog ==
= 0.4.0 =
* Poveznice same pronalaze stranicu po kratkom kodu (adresa i naslov mogu biti bilo kakvi); osvježe se pri spremanju stranice.
* Tečaj bez svoje stranice vodi na Kontakt s upisanim tečajem; Blog i Pravila privatnosti prikazuju se samo kad postoje.
* Postavke → AI Akademija: popis stranica (postoji / nedostaje), „Napravi stranice koje nedostaju”, „Postavi glavni izbornik”.
* Mentori: kartice vode na mentora na stranici Mentori, tamo popis njegovih tečajeva s poveznicama; tečaj → mentor.
* Tečaj: „Ostali tečajevi”; pitanja: „Sva pitanja”; podnožje: Programi, Kontakt.
* Kontakt: ?paket=…, ?predmet=mentor i ?tecaj=… sami popune predmet i poruku.
= 0.3.0 =
* Nove stranice: Programi (s filtrom razine), Tečaj (5 tečajeva), Mentori, Za tvrtke (s obrascem za ponudu), O nama, Kontakt (obrazac šalje mail na adresu administratora), Cijene, Česta pitanja, Uvjeti korištenja, Podnožje.
* Naslovnica dobila: Kako funkcionira, Zašto AI Akademija, Mentori, Cijene, Česta pitanja, poziv na akciju.
* Svi podaci (tečajevi, mentori, cijene, kontakt, pitanja) na jednom mjestu: includes/podaci.php – trenutno izmišljeni.
= 0.2.0 =
* Naslovnica kao kratki kod – WordPress joj više ne ubacuje <p> i <br> (raspadala se u HTML bloku).
* Font, boje i gumbi koji se preljevaju učitavaju se sami – Custom CSS u Flatsomeu više ne treba.
* Znak se učitava iz dodatka (ne treba ga ručno upisivati).
* Laptop: brojke 2 × 2, tečajevi po dva u redu do 1200 px.
= 0.1.0 =
* Prva naslovnica (HTML blok + Custom CSS).
