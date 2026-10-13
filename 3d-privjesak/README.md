# Plan A – NFC privjesak (3D ispis)

Model napravljen prema slici „Plan A – Dodir avanture”. Veličina **43 × 50 × 5,6 mm**
(jednobojni s izbočenim logom 6,2 mm), zaobljeni rubovi, rupa Ø 5,2 mm za kolutić/uzicu,
**skriveno udubljenje za NFC naljepnicu Ø 25 mm** (NTAG213/215).

| Datoteka | Za što |
| --- | --- |
| `plan-a-privjesak-visebojni.3mf` | višebojni ispis (Bambu AMS, Prusa MMU…) – jedan predmet s 3 dijela i bojama |
| `stl/…-tijelo-tamnoplavo.stl`, `…-logo-plavo.stl`, `…-logo-rozo.stl` | isti dijelovi kao STL (učitati sva tri zajedno → „jedan predmet s više dijelova”) |
| `plan-a-privjesak-jednobojni.stl` | pisač s jednom bojom: logo izbočen 0,6 mm, straga utisnuto |
| `pregled-sprijeda.png`, `pregled-straga.png` | kako izgleda |
| `privjesak.py` | program koji sve to izrađuje (mjere se mijenjaju na vrhu datoteke) |

## Postavke ispisa
- PLA ili PETG, mlaznica 0,4 mm, **sloj 0,2 mm**, ispuna 20–30 %, 3 obodne linije, bez potpora.
- Postaviti **stražnjom stranom (NFC znak i natpis) na podlogu** – tako je modelirano (z = 0 je stražnja strana).
- Boje: tijelo tamnoplava, plava (cyan) – lijevi dio loga, lijeva pruga i NFC znak, roza – desni dio loga, desna pruga i natpis „PLAN A”.

## NFC naljepnica
U slicer dodaj **pauzu na visini 2,4 mm** (početak sloja na 2,4 mm). Kad pisač stane, umetni NFC
naljepnicu/disk Ø 25 mm (debljina do 1 mm) u udubljenje i nastavi ispis – naljepnica ostaje zatvorena unutra.
Bez pauze se privjesak isprinta normalno, samo bez NFC-a.

## Jednobojni pisač
Ispis `plan-a-privjesak-jednobojni.stl`; za dvobojni izgled dodaj **promjenu filamenta na 5,6 mm**
(sve iznad je logo i pruge u drugoj boji). Pauza za NFC ista: 2,4 mm.

## Ponovna izrada
```
pip install trimesh shapely manifold3d numpy matplotlib
python3 privjesak.py
```
