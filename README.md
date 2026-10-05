# Plan A izleti – WordPress dodatak

Shortcode `[plan-a-izleti]` prikazuje izlete iz dodatka **WpTravelly** (Tour Booking Manager, MagePeople) u mreži s gumbima za kategorije. Kod dodatka nalazi se u mapi [`plan-a-izleti/`](plan-a-izleti/).

## Upute za instalaciju

1. **Napravite ZIP datoteku.** Sažmite mapu `plan-a-izleti` (cijelu mapu, ne samo njezin sadržaj) u `plan-a-izleti.zip`. Gotov ZIP nalazi se i u korijenu repozitorija: [`plan-a-izleti.zip`](plan-a-izleti.zip).
2. **Prenesite dodatak.** U WordPressu otvorite **Dodaci → Dodaj novi → Prenesi dodatak**, odaberite `plan-a-izleti.zip` i kliknite **Instaliraj sada**.
   (Drugi način: preko FTP-a kopirajte mapu `plan-a-izleti` u `wp-content/plugins/`.)
3. **Aktivirajte** dodatak „Plan A izleti”. WpTravelly i WooCommerce moraju ostati aktivni.
4. **Umetnite shortcode** na željenu stranicu. U Flatsome UX Builderu dodajte element **Text** ili **HTML**, a u klasičnom ili blok uređivaču blok **Shortcode**, i upišite:
   ```
   [plan-a-izleti]
   ```
   Želite li manje izleta, upišite npr. `[plan-a-izleti show="9"]` (zadano je 18, najviše 100). Ograničenje vrijedi samo za početni prikaz („Sve ture” + „Svi mjeseci”); uz odabranu kategoriju ili mjesec prikazuju se svi odgovarajući budući izleti.
5. **Spremite stranicu** i provjerite je na računalu, tabletu i mobitelu.

### Što trebate znati

- **Kategorije** su iste one koje WpTravellyjev filtar prikazuje pod naslovom „Category”, a to su **aktivnosti** izleta (WpTravelly → Activities, npr. Alpinizam, Ferrate, Jedrenje). Ako nijedan izlet nema aktivnosti, koriste se kategorije izleta (`ttbm_tour_cat`). Gumb se prikazuje samo za kategoriju koja ima barem jedan prikazani izlet s budućim terminom.
- **Filteri:** dva izbornika jedan do drugog: „Vrsta izleta” (plavi) i „Termin” (narančasti). Klik na izbornik otvara panel s opcijama ispod oba izbornika („Što želiš doživjeti?” / „Kada želiš putovati?”). Istovremeno je otvoren samo jedan panel, a zatvara se odabirom, klikom izvan panela, ponovnim klikom na izbornik ili tipkom Escape. Termini su mjeseci iz sljedećih 12 mjeseci (od tekućeg) s barem jednim budućim izletom, npr. „Stu 2026.”. Filtri vrste i termina rade zajedno, a opcije bez izleta za drugi odabrani filtar su zasivljene i neaktivne. Izlet s više termina pojavljuje se u svakom svom mjesecu i pokazuje datum iz odabranog mjeseca. Ispod izbornika piše „Prikazano izleta: X”. Opcije su u 2 stupca na mobitelu i 4 na računalu.
- **Oznake na slici kartice:** gore lijevo naziv prve kategorije izleta, gore desno „Popunjeno”.
- **Uvod:** `[plan-a-izleti intro="yes"]` iznad izbornika prikazuje „KRENI S PLANOM A”, „Pronađi svoj izlet.” i „Odaberi aktivnost i termin izleta.” (zadano isključeno).
- **Dijagnostika:** `[plan-a-izleti debug="yes"]` prijavljenom administratoru iznad filtera prikazuje broj pronađenih izleta, izvor kategorija, broj izleta po mjesecu te kategorije i izvor države za svaki izlet. Posjetitelji i urednici je ne vide. Zadano je isključena.
- **Datum** je prvi budući termin. Kod izleta s više termina ispod datuma piše „(i drugi termini)”. Izleti kojima su svi termini prošli automatski nestaju, a izleti bez datuma prikazuju se na kraju s natpisom „Termin uskoro”.
- **Država** se prikazuje ispod trajanja, iz istog izvora kao na WpTravelly karticama (`get_full_location()`): prvo država lokacije (term meta `ttbm_country_location`), zatim naziv lokacije (`ttbm_location_name`), pa `ttbm_country_name` i `ttbm_full_location_name`. Kao u WpTravellyju, ne prikazuje se ako je lokacija izleta isključena (`ttbm_display_location` = off).
- **Popunjeno** se prikazuje kad WpTravelly prema prodanim kartama izračuna da nema slobodnih mjesta ni na jednom terminu. Za izlete bez upisanog broja mjesta ta se oznaka ne prikazuje.
- **Boja** gumba i cijene preuzima se iz primarne boje teme Flatsome.
- **Ažuriranje:** popis se sprema u privremeni cache na najviše sat vremena. Cache se briše odmah kad spremite ili obrišete izlet, kad stigne ili se promijeni narudžba, te svaki novi dan.

### Deinstalacija

Otvorite **Dodaci**, kliknite **Deaktiviraj**, pa **Obriši**. Dodatak sa sobom briše i svoj jedini podatak (privremeni cache). Izleti, kategorije, WpTravelly i tema ostaju netaknuti. Shortcode na stranici nakon toga više ništa ne prikazuje, pa ga uklonite sa stranice.

## Tehničke napomene (kako WpTravelly sprema podatke)

Provjereno u izvornom kodu WpTravellyja **v2.3.4** (službeni repozitorij [magepeopleteam/tour-booking-manager](https://github.com/magepeopleteam/tour-booking-manager), ista verzija kao „Stable tag” na wordpress.org):

| Podatak | Gdje |
|---|---|
| Izlet | post type `ttbm_tour` |
| Kategorija u filtru „Category” | taksonomija `ttbm_tour_activities` + meta polje `ttbm_tour_activities` (ID-evi termina; stariji izleti: nazivi) |
| Kategorija izleta („Tour Type”) | taksonomija `ttbm_tour_cat` (rezervni izvor) |
| Vrsta rasporeda | meta `ttbm_travel_type`: `fixed`, `particular` (više termina) ili `repeated` |
| Fiksni datum | `ttbm_travel_start_date` (Y-m-d), `ttbm_travel_start_time` |
| Više termina | `ttbm_particular_dates`: niz redaka `ttbm_particular_start_date` / `_end_date` / `_start_time` |
| Ponavljanje | `ttbm_travel_repeated_start_date`, `ttbm_travel_repeated_end_date`, `ttbm_travel_repeated_after`, `ttbm_repeat_type`, neradni dani `mep_ticket_offdays` / `mep_ticket_off_dates` |
| Trajanje | `ttbm_travel_duration`, `ttbm_travel_duration_type` (day/hour/min), `ttbm_travel_duration_night` |
| Država | term meta `ttbm_country_location` lokacije `ttbm_location_name` (kopija: `ttbm_country_name`) |
| Prodane karte | post type `ttbm_booking` (`ttbm_id`, `ttbm_date`, `ttbm_ticket_qty`) |

Dodatak ne računa termine sam, nego koristi WpTravellyjevu funkciju `TTBM_Function::get_date()`. Zbog toga se ponavljanja, neradni dani, dodatni termini i WPML prijevodi poštuju točno kao na stranici izleta. Cijena i popunjenost računaju se istim funkcijama kao u WpTravellyju (`get_tour_start_price`, `get_total_available`, `get_any_date_seat_available`). Dodatak ne koristi izravne SQL upite, a sav ispis prolazi kroz escaping (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`).

Filtri za prilagodbu: `plan_a_izleti_cache_ttl`, `plan_a_izleti_max_tours`, `plan_a_izleti_sold_out`, `plan_a_izleti_country`, `plan_a_izleti_duration`.
