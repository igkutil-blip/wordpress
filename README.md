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
   Želite li manje izleta, upišite npr. `[plan-a-izleti show="9"]` (zadano je 18, najviše 100).
5. **Spremite stranicu** i provjerite je na računalu, tabletu i mobitelu.

### Što trebate znati

- **Kategorije** su kategorije izleta iz WpTravellyja. Prikazuju se samo kategorije koje imaju barem jedan prikazani izlet. Izlet iz podkategorije vidi se i pod nadređenom kategorijom.
- **Datum** je prvi budući termin. Kod izleta s više termina ispod datuma piše „(i drugi termini)”. Izleti kojima su svi termini prošli automatski nestaju, a izleti bez datuma prikazuju se na kraju s natpisom „Termin uskoro”.
- **Država** je država lokacije izleta (WpTravelly → Lokacija). Ako lokacija nema upisanu državu, red se ne prikazuje.
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
| Kategorija | taksonomija `ttbm_tour_cat` |
| Vrsta rasporeda | meta `ttbm_travel_type`: `fixed`, `particular` (više termina) ili `repeated` |
| Fiksni datum | `ttbm_travel_start_date` (Y-m-d), `ttbm_travel_start_time` |
| Više termina | `ttbm_particular_dates`: niz redaka `ttbm_particular_start_date` / `_end_date` / `_start_time` |
| Ponavljanje | `ttbm_travel_repeated_start_date`, `ttbm_travel_repeated_end_date`, `ttbm_travel_repeated_after`, `ttbm_repeat_type`, neradni dani `mep_ticket_offdays` / `mep_ticket_off_dates` |
| Trajanje | `ttbm_travel_duration`, `ttbm_travel_duration_type` (day/hour/min), `ttbm_travel_duration_night` |
| Država | term meta `ttbm_country_location` lokacije `ttbm_location_name` (kopija: `ttbm_country_name`) |
| Prodane karte | post type `ttbm_booking` (`ttbm_id`, `ttbm_date`, `ttbm_ticket_qty`) |

Dodatak ne računa termine sam, nego koristi WpTravellyjevu funkciju `TTBM_Function::get_date()`. Zbog toga se ponavljanja, neradni dani, dodatni termini i WPML prijevodi poštuju točno kao na stranici izleta. Cijena i popunjenost računaju se istim funkcijama kao u WpTravellyju (`get_tour_start_price`, `get_total_available`, `get_any_date_seat_available`). Dodatak ne koristi izravne SQL upite, a sav ispis prolazi kroz escaping (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`).

Filtri za prilagodbu: `plan_a_izleti_cache_ttl`, `plan_a_izleti_max_tours`, `plan_a_izleti_sold_out`, `plan_a_izleti_country`, `plan_a_izleti_duration`.
