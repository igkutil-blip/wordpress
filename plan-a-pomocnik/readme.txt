=== Plan A – pomoćnik za kopiju ===
Stable tag: 1.0.0
License: GPLv2 or later

SAMO ZA KOPIJU STRANICE (staging). Na srd-plan-a.hr odbija sve i prikazuje upozorenje da ga treba obrisati.

Claude (administrator prijavljen lozinkom aplikacije) preko REST API-ja (plan-a-pomocnik/v1) smije:
* /status – verzije, dodaci, je li uključen Disable Emails, je li kopija;
* /plugin – postaviti ili zamijeniti NAŠ dodatak iz zipa (samo mapa plan-a-*);
* /plugin/{plan-a-…}/activate|deactivate;
* /cron – odmah pokrenuti našu zakazanu radnju (samo plan_a_*);
* /log – zadnji redovi debug.log;
* /meta/{id} – čitati i upisivati podatke uz zapis (izlet, narudžba…);
* /option/{ime} – čitati naše postavke (plan_a*, ttbm*, pan_*, pac_*).

Ne može: dirati druge dodatke, temu ni WordPress, slati mailove, raditi na živoj stranici.
