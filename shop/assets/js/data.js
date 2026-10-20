/* Domestic Spirits – podaci trgovine (proizvodi, kategorije, postavke)
   postavke.probno: true = narudžba se ne šalje nikamo, samo se prikaže potvrda
   postavke.usporedba: true = gumbi „Usporedi s originalom” (proizvod.original, originalnaStranica); false = nigdje se ne prikazuju
   proizvod.izdvojeno: 1 = gornji red naslovnice, 2 = donji red; takodjer = dodatne kategorije */
window.DS = {
  "postavke": {
    "probno": true,
    "besplatnaDostavaOd": 60,
    "besplatnaDostavaPodrucje": "Zagrebačke županije",
    "tvrtka": "Domestic Spirits d.o.o.",
    "usporedba": true,
    "originalnaStranica": "https://domesticspirits.hr/"
  },
  "kategorije": [
    {
      "id": "vina",
      "naziv": "Vina",
      "slika": "assets/img/naslovnica/kat-vina.jpg"
    },
    {
      "id": "rakije",
      "naziv": "Rakije",
      "slika": "assets/img/naslovnica/kat-rakije.jpg"
    },
    {
      "id": "likeri",
      "naziv": "Likeri",
      "slika": "assets/img/naslovnica/kat-likeri.jpg"
    },
    {
      "id": "poklon-paketi",
      "naziv": "Poklon paketi",
      "slika": "assets/img/naslovnica/kat-poklon.jpg"
    }
  ],
  "proizvodjaci": [
    {
      "id": "avellana",
      "naziv": "AVELLANA"
    },
    {
      "id": "san-montano",
      "naziv": "San MONTANO"
    },
    {
      "id": "kravata",
      "naziv": "KRAVATA"
    },
    {
      "id": "bogati",
      "naziv": "BOGATI"
    },
    {
      "id": "patrik",
      "naziv": "PATRIK"
    }
  ],
  "proizvodi": [
    {
      "id": "avellana-ljesnjak",
      "marka": "avellana",
      "naziv": "Lješnjak",
      "kategorija": "likeri",
      "vrsta": "Liker od lješnjaka",
      "zapremnina": 0.7,
      "cijena": 12.6,
      "izdvojeno": 1,
      "slika": "assets/img/proizvodi/avellana-ljesnjak.jpg",
      "eyebrow": "Hrvatski liker od lješnjaka",
      "kratko": "Neodoljivo sladak i primamljiv liker kojem je teško odoljeti.",
      "naslovOpisa": "Lješnjak u glavnoj ulozi.",
      "alkohol": "20 % vol.",
      "podrijetlo": "Hrvatska",
      "pakiranje": "6 boca u kartonu",
      "temperatura": "Nema pravila.",
      "galerija": [
        {
          "slika": "assets/img/proizvodi/zlato-2020.jpg",
          "naziv": "Zlato 2020.",
          "opis": "Zlatna medalja · Dionizijana 2020."
        },
        {
          "slika": "assets/img/proizvodi/sampion-2022.jpg",
          "naziv": "Šampion 2022.",
          "opis": "Šampion likera · Zagreb 2022."
        },
        {
          "slika": "assets/img/proizvodi/posluzivanje-redizajn.svg",
          "naziv": "Posluživanje",
          "opis": "AVELLANA · Tri načina posluživanja"
        }
      ],
      "mini": [
        [
          "U čašici",
          "0,05 l"
        ],
        [
          "U kavici",
          "0,03 l"
        ],
        [
          "U slastici",
          "0,1 l"
        ]
      ],
      "posluzivanjeNaslov": "Tri načina za uživanje.",
      "posluzivanje": [
        {
          "naziv": "U čašici",
          "kolicina": "0,05 l",
          "opis": "Samostalno posluživanje",
          "ikona": "casa"
        },
        {
          "naziv": "U kavici",
          "kolicina": "0,03 l",
          "opis": "Kao dodatak kavici",
          "ikona": "kava"
        },
        {
          "naziv": "U slastici",
          "kolicina": "0,1 l",
          "opis": "Kao sastojak slastice",
          "ikona": "kolac"
        }
      ],
      "opis": [
        "Neodoljivo sladak i primamljiv liker kojem je teško odoljeti.",
        "Avellana liker od lješnjaka s ponosom nosi prestižne nagrade koje potvrđuju njegovu izvrsnost i jedinstveni okus.",
        "Ova priznanja rezultat su posvećenosti vrhunskoj kvaliteti, pažljivo biranim sastojcima i stručnoj izradi koja svaku kap Avellana likera čini posebnim doživljajem."
      ],
      "razloziNaslov": "Zašto odabrati Avellanu?",
      "zakljucak": "Isprobajte Avellanu i otkrijte zašto je šampion među likerima!",
      "razlozi": [
        "Profinjen i uravnotežen okus prirodnog lješnjaka.",
        "Savršen izbor za svečane prilike, poklone ili uživanje u posebnom trenutku.",
        "Nagrađena kvaliteta prepoznata na međunarodnoj razini."
      ],
      "nagrade": [
        {
          "slika": "assets/img/proizvodi/sampion-2022.jpg",
          "naslov": "Veliko zlato i šampionska titula",
          "mjesto": "Zagreb, 2022.",
          "tekst": "Osvojeni na 4. Međunarodnom ocjenjivanju rakija i likera održanom 24. i 25. studenog 2022. godine u Zagrebu. Ovo natjecanje organizirao je Gospodarski list u suradnji s Agronomskim i Prehrambeno-biotehnološkim fakultetom u Zagrebu."
        },
        {
          "slika": "assets/img/proizvodi/zlato-2020.jpg",
          "naslov": "Zlatna medalja",
          "mjesto": "Dionizijana, 2020.",
          "tekst": "Dodijeljena na 25. međunarodnom ocjenjivanju vina i jakih alkoholnih pića: Dionizijana, održanom na Sajmu Gast u Splitu 2020. godine."
        }
      ],
      "podaci": [
        [
          "Naziv likera",
          "Avellana liker od lješnjaka 0,70 l"
        ],
        [
          "Vrsta",
          "Liker od lješnjaka"
        ],
        [
          "Na tržište stavlja",
          "Domestic Spirits d.o.o."
        ],
        [
          "Geografsko podrijetlo",
          "Hrvatska"
        ],
        [
          "Zapremnina boce",
          "0,7 l"
        ],
        [
          "Alkohol",
          "20 % vol."
        ],
        [
          "Gastro preporuka",
          "0,05 l u čašici, 0,03 l u kavici ili 0,1 l u slastici."
        ],
        [
          "Temperatura serviranja",
          "Nema pravila"
        ],
        [
          "EAN kod",
          "3859889132312"
        ],
        [
          "Pakiranje",
          "6 boca u kartonu"
        ],
        [
          "Ambalaža",
          "Staklena boca, navojni čep"
        ]
      ],
      "sidrena": "Sidrena cijena na dan 02.05.2025.: 12,60 €",
      "original": "https://domesticspirits.hr/proizvod/avellana-ljesnjak-liker-od-lesnjaka/"
    },
    {
      "id": "san-montano-teranino",
      "marka": "san-montano",
      "naziv": "Teranino",
      "kategorija": "likeri",
      "vrsta": "Vinski liker",
      "zapremnina": 0.7,
      "cijena": 25.0,
      "izdvojeno": 1,
      "slika": "assets/img/proizvodi/san-montano-teranino.jpg",
      "eyebrow": "Hrvatski liker od terana",
      "kratko": "Vrhunsko vino Teran, aromatično bilje i bogata voćna aroma.",
      "naslovOpisa": "Teran u drukčijem izdanju.",
      "podrijetlo": "Hrv. primorje",
      "alkohol": "18 % vol.",
      "temperatura": "5 °C",
      "pakiranje": "6 boca u kartonu",
      "galerija": [
        {
          "slika": "assets/img/proizvodi/posluzivanje-san-montano-teranino.svg",
          "naziv": "Posluživanje",
          "opis": "San MONTANO Teranino · Kako poslužiti"
        }
      ],
      "mini": [
        [
          "Posluživanje",
          "Aperitiv"
        ],
        [
          "Uz hranu",
          "Slastice"
        ],
        [
          "Temperatura",
          "5 °C"
        ]
      ],
      "posluzivanje": [
        {
          "naziv": "Kao aperitiv",
          "opis": "Prije obroka.",
          "ikona": "casa"
        },
        {
          "naziv": "Uz slastice",
          "opis": "Prema gastro preporuci proizvoda.",
          "ikona": "kolac"
        }
      ],
      "opis": [
        "Teranino San Montano je premium voćni liker spravljen od vrhunskog vina Teran, obogaćen pažljivo biranim aromatičnim biljem. Njegova glatka tekstura i bogata voćna aroma osvajaju na prvo kušanje. Ovaj najpopularniji liker u Hrvatskoj idealan je za uživanje u posebnim trenucima. Ne pitajte zašto – probajte naš teranino, uvjerite se i razmazite svoja osjetila!"
      ],
      "podaci": [
        [
          "Naziv likera",
          "Teranino San Montano 0,7L"
        ],
        [
          "Vrsta",
          "Domaći liker od vrhunskog vina sorte Teran"
        ],
        [
          "Geografsko podrijetlo",
          "Primorska Hrvatska, Hrvatsko primorje"
        ],
        [
          "Gastro preporuka",
          "Kao aperitiv ili uz slastice"
        ],
        [
          "Temperatura serviranja",
          "5 C"
        ],
        [
          "Zapremnina boce",
          "0,70L"
        ],
        [
          "Alkohol",
          "18 vol. %"
        ],
        [
          "EAN kod",
          "3859889132176"
        ],
        [
          "Pakiranje",
          "6 boca u kartonu"
        ],
        [
          "Ambalaža",
          "staklena boca, silikonski čep"
        ]
      ],
      "sidrena": "Sidrena cijena na dan 02.05.2025.: 25,00 €",
      "original": "https://domesticspirits.hr/proizvod/san-montano-teranino/"
    },
    {
      "id": "kravata-malvazija",
      "marka": "kravata",
      "naziv": "Malvazija istarska",
      "kategorija": "vina",
      "vrsta": "Bijelo vino",
      "zapremnina": 0.75,
      "cijena": 9.2,
      "izdvojeno": 1,
      "slika": "assets/img/proizvodi/kravata-malvazija.jpg",
      "eyebrow": "Suho bijelo vino iz istre",
      "kratko": "Slamnato žuta boja, prepoznatljiva sortna aroma i zaokružen okus.",
      "naslovOpisa": "Istra u čaši Malvazije.",
      "podrijetlo": "Istra",
      "temperatura": "10–12 °C",
      "pakiranje": "6 boca u kartonu",
      "galerija": [
        {
          "slika": "assets/img/proizvodi/posluzivanje-kravata-malvazija.svg",
          "naziv": "Posluživanje",
          "opis": "KRAVATA Malvazija istarska · Kako poslužiti"
        }
      ],
      "mini": [
        [
          "Podrijetlo",
          "Zapadna Istra"
        ],
        [
          "Vrsta",
          "Suho bijelo"
        ],
        [
          "Temperatura",
          "10–12 °C"
        ]
      ],
      "posluzivanje": [
        {
          "naziv": "Uz predjela",
          "opis": "Povrće i tjestenina s umacima na bazi sira i vrhnja.",
          "ikona": "tanjur"
        },
        {
          "naziv": "Uz ribu",
          "opis": "Dimljena riba te kuhana i pečena bijela riba.",
          "ikona": "riba"
        },
        {
          "naziv": "Uz meso",
          "opis": "Kuhana teletina i svinjetina.",
          "ikona": "tanjur"
        }
      ],
      "opis": [
        "Vino malvazija je slamnato žute boje, krepko i puno, prepoznatljive sortne arome, izraženog mirisa, ugodnog i zaokruženog okusa."
      ],
      "dodatno": "Dugo se vjerovalo da malvazija nije pogodna za čuvanje i da ju je najbolje popiti u prvoj godini. No, unatrag nekoliko godina, niz osvojenih međunarodnih priznanja i nagrada na svjetskim vinskim sajmovima potvrđuje da istarski vinari od malvazije mogu proizvesti i visokokvalitetna vina pogodna za višegodišnje odležavanje.",
      "podaci": [
        [
          "Naziv vina",
          "KRAVATA Malvazija istarska 0,75L"
        ],
        [
          "Vrsta",
          "Vrhunsko suho bijelo vino KZP"
        ],
        [
          "Na tržište stavlja",
          "Domestic Spirits d.o.o."
        ],
        [
          "Geografsko podrijetlo",
          "Primorska Hrvatska, podregija Hrvatska Istra, vinogorje Zapadna Istra"
        ],
        [
          "Gastro preporuka",
          "uz predjela s povrćem i tjesteninom s umacima na bazi sira te vrhnja, dimljenu ribu, kuhanu i pečenu bijelu ribu, kuhanu teletinu i svinjetinu."
        ],
        [
          "Temperatura serviranja",
          "10 do 12°C."
        ],
        [
          "Zapremnina boce",
          "0,75L"
        ],
        [
          "EAN kod",
          "3859889132558"
        ],
        [
          "Pakiranje",
          "6 boca u kartonu"
        ],
        [
          "Ambalaža",
          "staklena boca, pluteni čep"
        ]
      ],
      "sidrena": "Sidrena cijena na dan 02.05.2025.: 9,20 €",
      "original": "https://domesticspirits.hr/proizvod/kravata-malvazija-075l/"
    },
    {
      "id": "bogati-medica",
      "marka": "bogati",
      "naziv": "Medica",
      "kategorija": "likeri",
      "vrsta": "Liker od meda",
      "zapremnina": 0.7,
      "cijena": 11.3,
      "izdvojeno": 1,
      "slika": "assets/img/proizvodi/bogati-medica.jpg",
      "eyebrow": "Hrvatski liker od meda",
      "kratko": "Lijepa zlatna boja i blag miris tekućeg meda.",
      "naslovOpisa": "Med u glavnoj ulozi.",
      "podrijetlo": "Hrvatska",
      "alkohol": "22 % vol.",
      "temperatura": "Po želji",
      "pakiranje": "6 boca u kartonu",
      "galerija": [
        {
          "slika": "assets/img/proizvodi/posluzivanje-bogati-medica.svg",
          "naziv": "Posluživanje",
          "opis": "BOGATI Medica · Kako poslužiti"
        }
      ],
      "mini": [
        [
          "Uz hranu",
          "Slastice"
        ],
        [
          "Posluživanje",
          "Aperitiv"
        ],
        [
          "Temperatura",
          "Po želji"
        ]
      ],
      "posluzivanje": [
        {
          "naziv": "Uz slastice",
          "opis": "Prema gastro preporuci proizvoda.",
          "ikona": "kolac"
        },
        {
          "naziv": "Kao aperitiv",
          "opis": "Prije obroka.",
          "ikona": "casa"
        }
      ],
      "opis": [
        "Napitak kojeg karakterizira izrazito lijepa boja s blagim mirisom tekućeg meda. Ljekoviti i slatki aperitiv savršena je ideja za dozu opuštanja nakon stresnog dana ili tijekom druženja s dragim ljudima."
      ],
      "nagrade": [
        {
          "naslov": "Srebrna medalja",
          "mjesto": "CROSPIRIT, Zagreb, 2026.",
          "tekst": "Na 8. Međunarodnom ocjenjivanju rakija, likera i gin-ova CROSPIRIT u Zagrebu, u organizaciji Gospodarskog lista u suradnji s Agronomskim i Prehrambeno-biotehnološkim fakultetom u Zagrebu, ovaj liker osvojio je srebrnu medalju."
        }
      ],
      "podaci": [
        [
          "Naziv likera",
          "Bogati Medica 0,70L"
        ],
        [
          "Vrsta",
          "Liker od meda"
        ],
        [
          "Na tržište stavlja",
          "Domestic Spirits d.o.o."
        ],
        [
          "Geografsko podrijetlo",
          "Hrvatska"
        ],
        [
          "Gastro preporuka",
          "Uz slastice ili kao aperitiv"
        ],
        [
          "Temperatura serviranja",
          "Po želji"
        ],
        [
          "Zapremnina boce",
          "0,70L"
        ],
        [
          "Alkohol",
          "22 vol. %"
        ],
        [
          "EAN kod",
          "3859889132336"
        ],
        [
          "Pakiranje",
          "6 boca u kartonu"
        ],
        [
          "Ambalaža",
          "staklena boca, navojni čep"
        ]
      ],
      "sidrena": "Sidrena cijena na dan 02.05.2025.: 11,30 €",
      "original": "https://domesticspirits.hr/proizvod/bogati-medica-070l/"
    },
    {
      "id": "kravata-teran",
      "marka": "kravata",
      "naziv": "Teran",
      "kategorija": "vina",
      "vrsta": "Crno vino",
      "zapremnina": 0.75,
      "cijena": 11.9,
      "izdvojeno": 2,
      "slika": "assets/img/proizvodi/kravata-teran.jpg",
      "eyebrow": "Suho crveno vino iz istre",
      "kratko": "Autohtona istarska sorta, rubin crvena boja i prepoznatljiv miris maline.",
      "naslovOpisa": "Rubin crvena priča Istre.",
      "podrijetlo": "Istra",
      "temperatura": "18–20 °C",
      "pakiranje": "6 boca u kartonu",
      "galerija": [
        {
          "slika": "assets/img/proizvodi/posluzivanje-kravata-teran.svg",
          "naziv": "Posluživanje",
          "opis": "KRAVATA Teran · Kako poslužiti"
        }
      ],
      "mini": [
        [
          "Podrijetlo",
          "Istra"
        ],
        [
          "Vrsta",
          "Suho crveno"
        ],
        [
          "Temperatura",
          "18–20 °C"
        ]
      ],
      "posluzivanje": [
        {
          "naziv": "Pršut i sir",
          "opis": "Istarski pršut i ovčji sir.",
          "ikona": "sir"
        },
        {
          "naziv": "Uz meso",
          "opis": "Jela od divljači i svinjetina.",
          "ikona": "tanjur"
        },
        {
          "naziv": "Uz ribu",
          "opis": "Plava riba.",
          "ikona": "riba"
        }
      ],
      "opis": [
        "Ova autohtona istarska sorta karakteristične je rubin crvene boje s ljubičastim tonovima prilikom rotiranja vina u čaši. Aroma mu je tipična i prepoznatljiva po mirisu maline."
      ],
      "podaci": [
        [
          "Naziv vina",
          "KRAVATA Teran 0,75L"
        ],
        [
          "Vrsta",
          "suho crveno vino KZP"
        ],
        [
          "Na tržište stavlja",
          "Domestic Spirits d.o.o."
        ],
        [
          "Geografsko podrijetlo",
          "Hrvatska Istra"
        ],
        [
          "Gastro preporuka",
          "uz istarski pršut i ovčji sir, kao i s jelima od divljači, svinjetinom, plavom ribom…"
        ],
        [
          "Temperatura serviranja",
          "18-20°C"
        ],
        [
          "Zapremnina boce",
          "0,75L"
        ],
        [
          "EAN kod",
          "3859889132565"
        ],
        [
          "Pakiranje",
          "6 boca u kartonu"
        ],
        [
          "Ambalaža",
          "staklena boca, pluteni čep"
        ]
      ],
      "sidrena": "Sidrena cijena na dan 02.05.2025.: 11,90 €",
      "original": "https://domesticspirits.hr/proizvod/primores-plavac-mali-postup/"
    },
    {
      "id": "san-montano-biska",
      "marka": "san-montano",
      "naziv": "Biska",
      "kategorija": "likeri",
      "takodjer": [
        "rakije"
      ],
      "vrsta": "Gorki biljni liker",
      "zapremnina": 0.7,
      "cijena": 24.0,
      "izdvojeno": 2,
      "slika": "assets/img/proizvodi/san-montano-biska.jpg",
      "eyebrow": "Tradicionalni gorki biljni liker",
      "kratko": "Bijela imela i kvalitetna lozovača u tradicionalnom biljnom likeru.",
      "naslovOpisa": "Karakter bijele imele.",
      "podrijetlo": "Hrvatska",
      "alkohol": "30 % vol.",
      "pakiranje": "6 boca u kartonu",
      "galerija": [
        {
          "slika": "assets/img/proizvodi/posluzivanje-san-montano-biska.svg",
          "naziv": "Posluživanje",
          "opis": "San MONTANO Biska · Kako poslužiti"
        }
      ],
      "mini": [
        [
          "Prije obroka",
          "Aperitiv"
        ],
        [
          "Nakon obroka",
          "Digestiv"
        ],
        [
          "Posluživanje",
          "S ledom / čisto"
        ]
      ],
      "posluzivanje": [
        {
          "naziv": "Kao aperitiv",
          "opis": "Prije obroka.",
          "ikona": "casa"
        },
        {
          "naziv": "Kao digestiv",
          "opis": "Nakon obroka.",
          "ikona": "casa"
        }
      ],
      "opis": [
        "San MONTANO Biska je tradicionalni gorki biljni liker, pripremljen namakanjem listova bijele imele u visoko kvalitetnoj lozovači. “Tajna” recepture ostaje zaštićena, čineći svaki gutljaj jedinstvenim iskustvom. Imela, koja se stoljećima koristi za pripremu ljekovitih napitaka, daje ovom liker posebne blagodati, a umjerena konzumacija može imati pozitivne učinke na zdravlje.",
        "Savršen kao aperitiv ili digestiv, San MONTANO Biska je piće koje opušta. Poslužite ga sa kockicom leda ili uživajte u njegovoj čistoći, bilo u društvu, bilo uz vlastite misli. Isprobajte ga i uvjerite se sami u njegovu sofisticiranu eleganciju."
      ],
      "nagrade": [
        {
          "naslov": "Srebrna medalja",
          "mjesto": "CROSPIRIT, Zagreb",
          "tekst": "Na 8. Međunarodnom ocjenjivanju rakija, likera i gin-ova CROSPIRIT u Zagrebu, u organizaciji Gospodarskog lista u suradnji s Agronomskim i Prehrambeno-biotehnološkim fakultetom u Zagrebu, ovaj liker osvojio je srebrnu medalju."
        }
      ],
      "podaci": [
        [
          "Naziv artikla",
          "San MONTANO Biska 0,70L"
        ],
        [
          "Vrsta",
          "Gorki biljni liker"
        ],
        [
          "Geografsko podrijetlo",
          "Primorsko Goranska Hrvatska"
        ],
        [
          "Gastro preporuka",
          "Kao aperitiv ili digestiv"
        ],
        [
          "Zapremnina boce",
          "0,7L"
        ],
        [
          "Alkohol",
          "30 vol. %"
        ],
        [
          "EAN kod",
          "3859889132169"
        ],
        [
          "Pakiranje",
          "6 boca u kartonu"
        ],
        [
          "Ambalaža",
          "staklena boca, silikonski čep"
        ]
      ],
      "sidrena": "Sidrena cijena na dan 02.05.2025.: 24,00 €",
      "original": "https://domesticspirits.hr/proizvod/san-montano-biska/"
    },
    {
      "id": "patrik-limoncello",
      "marka": "patrik",
      "naziv": "Limoncello",
      "kategorija": "likeri",
      "vrsta": "Liker od limuna",
      "zapremnina": 0.5,
      "cijena": 15.5,
      "izdvojeno": 2,
      "slika": "assets/img/proizvodi/patrik-limoncello.jpg",
      "eyebrow": "Tradicionalni liker od limuna",
      "kratko": "Maceracija kora citrusa u voćnoj rakiji, slatkoća i prepoznatljiv okus limuna.",
      "naslovOpisa": "Okus koji podsjeća na ljeto.",
      "podrijetlo": "Hrvatska",
      "temperatura": "Dobro ohlađeno",
      "pakiranje": "pojedinačno",
      "galerija": [
        {
          "slika": "assets/img/proizvodi/patrik-limoncello-straga.jpg",
          "naziv": "Straga",
          "opis": "PATRIK Limoncello · stražnja etiketa"
        },
        {
          "slika": "assets/img/proizvodi/posluzivanje-patrik-limoncello.svg",
          "naziv": "Posluživanje",
          "opis": "PATRIK Limoncello · Kako poslužiti"
        }
      ],
      "mini": [
        [
          "Proizvođač",
          "PATRIK obrt"
        ],
        [
          "U čaši",
          "Kokteli / digestiv"
        ],
        [
          "Temperatura",
          "Ohlađeno"
        ]
      ],
      "posluzivanje": [
        {
          "naziv": "Kao aperitiv",
          "opis": "Prije obroka.",
          "ikona": "casa"
        },
        {
          "naziv": "Uz slastice",
          "opis": "Kao pratnja slasticama.",
          "ikona": "kolac"
        },
        {
          "naziv": "U koktelima",
          "opis": "Kao sastojak koktela.",
          "ikona": "koktel"
        },
        {
          "naziv": "Kao digestiv",
          "opis": "Nakon obroka.",
          "ikona": "casa"
        }
      ],
      "opis": [
        "Nesvakidašnji liker koji već samim nazivom podsjeća na ljeto i uživanje u blagodatima mora i sunca. Ovaj prirodni voćni liker, pripremljen je na tradicionalan način, maceracijom kora citrusa u voćnoj rakiji. Slatki i bogat prepoznatljivim okusom, te savršeno prirodno osvježenje u bilo koje doba dana."
      ],
      "podaci": [
        [
          "Naziv artikla",
          "Patrik Limoncello 0,50L"
        ],
        [
          "Vrsta",
          "Liker od limuna"
        ],
        [
          "Proizvođač",
          "PATRIK obrt"
        ],
        [
          "Geografsko podrijetlo",
          "Hrvatska"
        ],
        [
          "Gastro preporuka",
          "Kao aperitiv, uz slastice, u koktelima ili kao digestiv"
        ],
        [
          "Temperatura serviranja",
          "Dobro ohlađeno"
        ],
        [
          "Zapremnina boce",
          "0,50L"
        ],
        [
          "EAN kod",
          "3859890672814"
        ],
        [
          "Pakiranje",
          "pojedinačno"
        ],
        [
          "Ambalaža",
          "staklena boca, silikonski čep"
        ]
      ],
      "sidrena": "Sidrena cijena na dan 02.05.2025.: 15,50 €",
      "original": "https://domesticspirits.hr/proizvod/patrik-limoncello-050l/"
    }
  ]
};
