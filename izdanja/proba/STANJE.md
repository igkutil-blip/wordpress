# Probna kopija – https://proba.domesticspirits.hr

Softaculous klon žive stranice (22.10.2026.), isti hosting (MyDataKnox cp064). Mailovi se ne šalju kupcima (pomoćnik), noindex.

## Naši dodaci

| Dodatak | Verzija | Stanje | Napomena |
|---|---|---|---|
| domestic-pomocnik | 0.3.3 | aktivan | učitao Igor 23.10.2026.; mailovi preusmjereni na Igorovu adresu; 0.2.0 postavke teme, 0.3.0 zamjena slike na istoj adresi, 0.3.1 ?v= za zamijenjene slike (nginx cache) ; 0.3.2 `paleta=1` (PNG izvedenice na 256 boja); 0.3.3 stranice probe bez predmemorije |
| domestic-kartica | 0.6.0 | aktivan, nova kartica **uključena** | 23.10.2026.; krem pozadina cijele stranice #f6f1e9, kartice na kremi, bordo #702833; 0.2.2 fontovi Marcellus + Jost na cijeloj stranici; 0.2.3 kvadratna slika (woocommerce_single + srcset) – ambijentalne od ruba do ruba ; 0.3.0 raspored trgovine (filteri gore, 4 u redu, nedavno dolje, stranice), krem kartice; 0.3.1 novi gumb Brzi pregled; 0.3.2 zbijeni izbornik kategorija, sitnije nedavno pregledano; 0.3.3 jednobrazne oznake; 0.4.0 pretraga s prijedlozima, novo polje pretrage i ikona košarice; 0.4.1 krem izbornik na mobitelu, stroža pretraga kratkih riječi; 0.5.0 novo zaglavlje (tamna gornja traka, krem kategorije, izbornik na mobitelu); 0.5.2 kategorije na mobitelu = mreža 4 × 2 s ikonama; 0.5.3 spojena traka filtera; 0.5.4 otvoreno polje ispunjava kućicu; 0.5.5 pri listanju na mobitelu tanja traka kategorija bez ikona; 0.6.0 krem traka na dnu mobitela (Početna, Ponuda, Pokloni, Traži, Košarica) |
| domestic-proizvod | 0.7.2 | aktivan, novi izgled za **sve** proizvode | 23.10.2026.; UX prvi ekran: 2 stupca, cijena i gumb odmah, traka sličica, puni naziv ; 0.6.0 brzi pregled u novom izgledu; 0.6.2 bez oznaka ispod sličica; 0.6.3 oznake iz domestic-kartica; 0.6.4 manji povezani proizvodi, 6 u redu; 0.7.x ilustracije u Kako poslužiti, Dostava i plaćanje iz WooCommercea |
| domestic-blog | 0.2.0 | aktivan | 23.10.2026.; 0.2.0 izbornik regija s ikonama (kao kategorije proizvoda), novi gumb „Pročitaj priču”; skrivene Blog i Savjeti; blog u izgledu shopa: /vinarije/ = „Vinarije i proizvođači” (filter po regiji, istaknuta priča, kartice 3 u redu, poziv u trgovinu), objava (tablica „Za stolom”, savjet, „U našoj ponudi” preko cijele širine, Još priča), stare objave očišćene samo za prikaz; hrvatski natpisi; skrivena kategorija Blog (228) |
| domestic-slike | 0.3.1 | aktivan | 23.10.2026.; 0.3.0 uloge po ključnoj riječi, opisi samo iste marke; 0.3.1 bez -scaled u nazivu; 0.2.0 dodatne slike istog naziva = zamjena na istoj adresi, alt „količina naziv”; Domestic → Slike proizvoda: uvoz paketa slika (pregled pa uvoz), zamjena slike na istoj adresi, Za provjeru (prenesene natuknice za 9 proizvoda), dnevnik; REST domestic-slike/v1 |
| domestic-provjera-dobi | 0.1.0 | aktivan, provjera **isključena** | 23.10.2026.; test okruženje, kategorije: vina, jaka alkoholna pića, pivo, horeca, poklon paketi (bez aloe vere i suvenira); čeka client_id i redirect URI za probu kod ministarstva |

## Promjene u odnosu na živu (samo na probi)

- Novi logotipi upisani u **stare datoteke na istim adresama** (identitet/README.md): zaglavlje 300 px, ikona stranice, mailovi, Rank Math; zaglavlje krem #F7F3EB.
- Slike: Avellana 0,70 l – nove fotografije (ambijentalna na staroj adresi glavne slike, studio-/posluzivanje- nove, medalje zamijenjene).
- Tekstovi (domestic-proizvod 0.5.0): nazivi, kategorije i opisi jednobrazni samo pri prikazu; baza i adrese netaknute.
- Blog: 16 novih objava o partnerima (4895–4910) + naslovne slike 4915–4945, 4 stare dopunjene (proizvodi, „Za stolom”), kategorije 304 i 305, opisi kategorija, „Style” preimenovan u „Savjeti” (adresa /category/style/ ista). Za živu: prenijeti objave skriptama iz `alati/blog-partneri/`.
- WooCommerce: decimalni separator „,” i tisuće „.” (12,60 €) – na živoj je još „.”.
- Isključeni: KEKS Pay (pravo plaćanje), Google Site Kit, CTX Feed, IT-podrška Age Gate (NIAS) – zamijenjen našim dodatkom.

## Svi dodaci na probi (23.10.2026.)

| Dodatak | Verzija | Stanje |
|---|---|---|
| Age Gate | 3.7.3 | aktivan |
| Aircash for WooCommerce | 2.0.9 | aktivan |
| Aplitap Sidrena Cijena i Cjenik | 2.7.10 | aktivan |
| Better Search Replace | 1.4.11 | isključen |
| Classic Editor | 1.7.0 | aktivan |
| Complianz | GDPR/CCPA Cookie Consent | 7.5.6 | aktivan |
| Contact Form 7 | 6.1.7 | aktivan |
| Prilagođeni brojevi narudžbi za WooCommerce. | 2.2.0 | aktivan |
| Domestic – pomoćnik probne kopije | 0.1.0 | aktivan |
| Domestic – provjera dobi (mGrađani) | 0.1.0 | aktivan |
| Site Kit by Google | 1.190.0 | isključen |
| IT-podrška Age Gate (NIAS) | 1.2.2 | isključen |
| KEKS Pay for WooCommerce | 2.1.1 | aktivan |
| Loco Translate | 2.8.9 | isključen |
| City/ZIP Autofill for WooCommerce | 1.2 | aktivan |
| Really Simple Security | 9.8.3 | aktivan |
| Rank Math SEO | 1.0.280 | aktivan |
| CTX Feed | 8.0.38 | isključen |
| Woocommerce Minimum and Maximum Quantity | 2.1.8 | aktivan |
| Table Rate Shipping for WooCommerce | 2.30 | aktivan |
| WooCommerce Legacy REST API | 1.0.6 | aktivan |
| WooCommerce | 11.2.1 | aktivan |
| WP Crontrol | 2.0.0 | isključen |
