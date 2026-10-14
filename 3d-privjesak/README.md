# Plan A – NFC privjesak (3D ispis), verzija 2

Izrađen prema paketu za modeliranje (logo `logo/logo-jednostavni.svg`, upute) i slikama iz više pogleda.
Program: `privjesak.py` (sve mjere na vrhu datoteke). STEP se ne izvozi (program radi mrežom, ne CAD-om);
uređivi „model” je sam program.

## Mjere
- Tijelo **38 × 48 × 6 mm**, asimetrični oblutak, ravna lica, rub zaobljen 1,8 mm.
- Otvor za vezicu: zaobljeni trokut, slobodna širina ~5,6 mm, ≥ 3 mm materijala do ruba, jedna rupa kroz debljinu.
- Prednji znak iz SVG-a, širok **29,4 mm** (mjerilo 0,98 da ostane ≥ 3 mm do ruba i otvora),
  umetak 0,8 mm: **0,4 mm u tijelu + 0,4 mm iznad plohe**.
- Poleđina: svjetloplavi NFC simbol i magenta „PLAN A” – **ravno s plohom** (0,8 mm duboko), da poleđina
  leži potpuno ravno na podlozi (izbočenje bi na podlozi trebalo potpore).
- Bočne linije 1,2 mm (visina na rubu) × 0,8 mm (dubina): plava lijevo, magenta desno (gledano sprijeda),
  prekid 2 mm gore i dolje.
- NFC šupljina **Ø 25,4 × 1,0 mm** (čip 25 × 0,8 mm + zazor), 1,2 mm plastike do poleđine (strana očitanja).
  **Izmjeri svoj čip** i upiši `NFC_PROMJER` / `NFC_DEBLJINA` u program.

## Način A – višebojni pisač (AMS, MMU): jedan ispis
`plan-a-privjesak-visebojni.3mf` – jedan predmet, 3 dijela s bojama (crno, svjetloplavo #00B9E8, magenta #D4145A).
Isto kao 3 poravnata STL-a u `stl/`. Poleđinom na podlogu, sloj 0,2 mm, bez potpora.
**Pauza na 2,2 mm** → umetni čip → nastavi.

## Način B – ispis po bojama, pa sastavljanje (bez AMS-a)
Mapa `ploce/`:
1. `ploca-1-crno-tijelo.stl` – tijelo s udubljenjima. Poleđinom na podlogu, **pauza na 2,2 mm** za čip.
2. `ploca-2-svjetloplavo.stl` – svi plavi dijelovi (logo, NFC simbol, bočna traka).
3. `ploca-3-magenta.stl` – svi magenta dijelovi (logo, slova P L A N A, bočna traka).

Umeci su sa svake strane 0,1 mm manji (`ZAZOR_SASTAVLJANJE`). Zalijepiti kapljicom sekundnog ljepila (pinceta):
prednji dijelovi strše 0,4 mm, stražnji su ravni s plohom. Bočne trake su ravne trake 1,0 × 0,7 mm koje se
utisnu u žlijeb uz rub (najzahtjevniji dio – za prvu probu mogu se izostaviti). Kakav je raspored, vidi `pregled-ploce.png`.
Ploče 2 i 3 ispisuju se brzo (0,8 mm visine); najmanji trokutići imaju stranicu ~2,2 mm.

## Ispis
PLA ili PETG, mlaznica 0,4 mm, sloj 0,2 mm, 3 obodne linije, ispuna 20–30 %. Prvi primjerak je proba:
provjeri otvor, dosjed umetaka i čitanje NFC-a telefonom.

## Ponovna izrada
```
pip install trimesh shapely manifold3d numpy matplotlib
python3 privjesak.py
```
