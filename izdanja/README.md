# Izdanja dodataka – proba i živa stranica

| Mapa | Što je unutra |
|---|---|
| `wp-dodaci/<naziv>/` | **izvorni kod** naših dodataka (ovdje se radi) |
| `izdanja/proba/` | zipovi koji su **trenutno na probnoj kopiji** https://proba.domesticspirits.hr + `STANJE.md` |
| `izdanja/ziva/` | zipovi **odobreni i instalirani na živoj stranici** https://domesticspirits.hr + `STANJE.md` |

## Postupak

1. Promjena u `wp-dodaci/<naziv>/`, povećati `Version` u zaglavlju.
2. `alati/zip-dodatak.sh <naziv>` → `izdanja/proba/<naziv>-<verzija>.zip` (stari zip istog dodatka obrisati).
3. Učitavanje na probu: REST pomoćnika (`POST /wp-json/domestic-pomocnik/v1/plugin`, polje `zip`), upisati u `proba/STANJE.md`.
4. Igor isproba i odobri → isti zip se **kopira** u `izdanja/ziva/`, Igor ga instalira na živoj (Dodaci → Učitaj dodatak),
   upisati u `ziva/STANJE.md` (datum, tko je odobrio).

Na živu stranicu se iz sesije ništa ne učitava – živa je samo za čitanje. `domestic-pomocnik` se na živu **ne** instalira.
