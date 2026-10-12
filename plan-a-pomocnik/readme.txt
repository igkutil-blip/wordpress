=== Plan A – pomoćnik za kopiju ===
Stable tag: 1.1.0
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
* /mail – stanje preusmjeravanja i zadnji mailovi; /mail/test – probni mail na izmišljenu adresu;
* /plugin – postaviti ili zamijeniti NAŠ dodatak iz zipa (samo mapa plan-a-*);
* /plugin/{plan-a-…}/activate|deactivate;
* /cron – odmah pokrenuti našu zakazanu radnju (samo plan_a_*);
* /log – zadnji redovi debug.log;
* /meta/{id} – čitati i upisivati podatke uz zapis (izlet, narudžba…);
* /option/{ime} – čitati naše postavke (plan_a*, ttbm*, pan_*, pac_*).

Ne može: dirati druge dodatke, temu ni WordPress, mijenjati adresu za mailove, raditi na živoj stranici.

== Promjene ==
= 1.1.0 =
* Svi mailovi kopije preusmjeravaju se na jednu adresu (ili se blokiraju).
= 1.0.0 =
* Prva verzija.
