# Plan A privjesak v4 – jedan višebojni ispis (Bambu AMS)

Izgled prema fotografiji „Plan A – Dodir avanture”: oblutak 43 × 45 × 9 mm, gornji lijevi kut izvučen u ušicu
s otvorom (zaobljeni trokut ~4,5 mm, ≥ 4 mm materijala oko njega), prednja strana zaobljena kao kamenčić,
logo izdignut 0,3 mm i prati zaobljenost, pruge po zaobljenom rubu. Stražnja strana je ravna (leži na podlozi):
NFC lukovi, natpis PLAN A i pruge u ravnini plohe. Nema sastavljanja ni ljepila.

## Datoteke
- `plan-a-privjesak-v4.3mf` – jedan predmet s 3 dijela (crno, svjetloplavo, magenta).
- `stl/plan-a-privjesak-v4-crno.stl`, `…-svjetloplavo.stl`, `…-magenta.stl` – ista 3 dijela, isto ishodište.
- `prikaz-v4.png` – prikaz iz tih STL-ova. `provjere.json` – provjere. `parametri.json` + `izradi_v4.py` – program.

## Bambu Studio
1. Otvori `plan-a-privjesak-v4.3mf` (ili povuci sva 3 STL-a odjednom → „Učitati kao jedan predmet s više dijelova?” → **Da**).
2. U popisu predmeta svakom dijelu dodijeli filament: crno / svjetloplavo / magenta.
3. Ravna (stražnja) strana na podlozi – tako je već postavljeno. **Bez potpora.**
4. Sloj 0,12–0,16 mm za glađu kupolu (0,2 mm radi, ali se vide stepenice). Za izgled kao na slici:
   teksturirana PEI ploča (straga) i po želji „Fuzzy skin” samo za vanjski zid.
5. Prime tower ostaje uključen (AMS).

## Provjereno
Tri dijela su zatvorene mreže, bez preklapanja (0 mm³). Najtanji dio (ušica) je debeo ~6,3 mm.
Nije provjereno u Bambu Studiju i nije isprintano.
