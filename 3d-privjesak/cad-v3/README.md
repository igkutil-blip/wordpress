# Plan A privjesak – CAD v3 (6 dijelova, ispis bez potpora)

Konstrukcija prati upute četverodijelnog CAD prototipa (iste mjere 46 × 60 × 8 mm, lice 1,6 mm, rub 2 mm,
zazori 0,22 / 0,25 / vodilice Ø2,2 u Ø2,7), a izgled je prema fotografiji: oblutak s ušicom gore lijevo,
zaobljeni rub, ukrasi strše 0,4 mm s tankim crnim rubom oko svakog, rubne trake uz rub lica, šav po sredini.

Razlika: svaka strana ima svoj plavi i svoj magenta umetak (6 dijelova umjesto 4) → **nijedan dio ne treba potpore**,
a i dalje su samo **3 ispisa** (jedna boja = jedan ispis).

| # | Dio | Boja |
| --- | --- | --- |
| 1 | prednja polovica (lice s prorezima, 2 vodilice, 3 stupića za središta slova P, A, A) | crna |
| 2 | stražnja polovica (lice s prorezima, ležišta vodilica) | crna |
| 3 | prednji umetak: plavi dio loga + lijeva traka | plava |
| 4 | prednji umetak: magenta dio loga + desna traka (prolazi kroz ploču 3) | magenta |
| 5 | stražnji umetak: NFC lukovi + dvije trake | plava |
| 6 | stražnji umetak: natpis PLAN A + traka (prolazi kroz ploču 5) | magenta |

## Datoteke
- `ispis/1_ispis_crno.stl`, `2_ispis_plavo.stl`, `3_ispis_magenta.stl` – **za printanje**, dijelovi već položeni ravnom plohom na podlogu.
- `stl/` – svih 6 dijelova u položaju sklopa (isto ishodište).
- `plan-a-privjesak-v3-step.zip` – CAD (STEP) svih dijelova i sklopa s bojama; otvara se u FreeCAD-u, Fusionu, Onshapeu …
- `prikaz-sklop.png`, `prikaz-rastavljeno.png`, `prikaz-ispis.png` – prikazi iz STL-ova.
- `provjere.json` – rezultati provjera. `parametri.json` – sve mjere i zazori. `izradi_cad.py` – program (CadQuery).

## Provjereno (u CAD-u, nominalne mjere)
- Svaki dio je jedno valjano zatvoreno tijelo; STEP se ponovno učita kao 1 valjano tijelo; STL zatvoren.
- Svih 15 parova dijelova: 0 mm³ preklapanja u sklopljenom položaju.
- Između svaka dva ukrasa ostaje ≥ 0,6 mm crnog; svaki ukras sjedi na svojoj ploči.
- **Nije** provjereno: stvarni ispis, zazori na tvom printeru, čvrstoća stupića (≈1 mm) i vodilica.

## Ispis
PLA, mlaznica 0,4, sloj 0,2 mm, bez potpora. Crne polovice licem na podlogu (najbolje glatka ili teksturirana PEI
ploča – daje izgled kao na slici); umetci pločom na podlogu, ukrasi gore.

## Sastavljanje
1. Stražnja polovica licem dolje → umetak 5 (plavi, lukovi kroz proreze) → umetak 6 (magenta, slova kroz ploču 5 i proreze).
2. Prednja polovica licem dolje → umetak 3 (plavi) → umetak 4 (magenta).
   Po kap ljepila na ploče da ne ispadnu pri okretanju.
3. Prednju polovicu okreni i spusti na stražnju: vodilice ulaze u ležišta, tri stupića ulaze u središta slova P, A, A.
4. Prije lijepljenja sve složi „na suho”. Zatim tanki sloj sekundnog ljepila po rubu spoja i stisni.

Ako umetci idu preteško ili prelabavo: promijeni `zazor_ukras` / `zazor_ploca` u `parametri.json` i pokreni
`python3 izradi_cad.py` pa `python3 prikaz.py` (izrada traje ~6 min).
