=== Plan A – pomoćnik za kopiju ===
Stable tag: 1.4.1
License: GPLv2 or later

SAMO ZA KOPIJU STRANICE (staging). Na srd-plan-a.hr odbija sve i prikazuje upozorenje da ga treba obrisati.

Mailovi na kopiji (Alati → Plan A – mailovi kopije):
* svi mailovi (kupcima, članovima, vodičima, osoblju; To, Cc i Bcc) idu na JEDNU upisanu adresu;
* izvorni primatelji pišu u naslovu: [KOPIJA → kupac@…] Naslov;
* ako adresa nije upisana, svi se mailovi blokiraju;
* popis zadnjih 50 mailova i gumb za probni mail.
Dok je pomoćnik isključen, ova zaštita ne radi!

Claude (administrator prijavljen lozinkom aplikacije) preko REST API-ja (plan-a-pomocnik/v1) smije:
* /status – verzije, dodaci, je li uključen Disable Emails, je li upisana adresa za mailove, je li kopija;
* /mail – stanje preusmjeravanja i zadnji mailovi (GET), postavljanje adrese {"to": …} (POST); /mail/test – probni mail na izmišljenu adresu;
* /plugin – postaviti ili zamijeniti NAŠ dodatak iz zipa (samo mapa plan-a-*);
* /plugin/{plan-a-…}/activate|deactivate, i WpTravelly (tour-booking-manager) – za usporedni rad s novim sustavom izleta;
* /cron – odmah pokrenuti našu zakazanu radnju (samo plan_a_*);
* /log – zadnji redovi debug.log;
* /meta/{id} – čitati i upisivati podatke uz zapis (izlet, narudžba…);
* /option/{ime} – čitati naše postavke (plan_a*, ttbm*, pan_*, pac_*).

Ne može: dirati druge dodatke (osim uključivanja i isključivanja WpTravellyja), temu ni WordPress, raditi na živoj stranici.

== Promjene ==
= 1.4.1 =
* Ikona iznad naslova maila je okrugla slika (u Gmailu na mobitelu se krug više ne razvlači u elipsu), ista u svim mailovima.

= 1.4.0 =
* Primjerci svih mailova (REST mail/samples, samo kopija); probni mail u Plan A izgledu.

= 1.3.0 =
* Smije uključiti i isključiti WpTravelly (samo na kopiji), za probu novog sustava izleta.
= 1.2.0 =
* Adresa za mailove kopije može se postaviti i preko REST-a (POST /mail).
* Ako je pošiljatelj (From) prazan, uzima se WooCommerceova adresa pošiljatelja ili administratorova.
* Probni mail vraća grešku slanja.
= 1.1.0 =
* Svi mailovi kopije preusmjeravaju se na jednu adresu (ili se blokiraju).
= 1.0.0 =
* Prva verzija.
