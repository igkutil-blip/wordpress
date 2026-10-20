/* Domestic Spirits – podaci trgovine (proizvodi, kategorije, postavke) */
window.DS = {
  postavke: {
    // true = narudžba se ne šalje nikamo, samo se prikaže potvrda (probna trgovina)
    probno: true,
    besplatnaDostavaOd: 60,
    besplatnaDostavaPodrucje: 'Zagrebačke županije',
    tvrtka: 'Domestic Spirits d.o.o.'
  },

  kategorije: [
    { id: 'vina', naziv: 'Vina', slika: 'assets/img/naslovnica/kat-vina.jpg' },
    { id: 'rakije', naziv: 'Rakije', slika: 'assets/img/naslovnica/kat-rakije.jpg' },
    { id: 'likeri', naziv: 'Likeri', slika: 'assets/img/naslovnica/kat-likeri.jpg' },
    { id: 'poklon-paketi', naziv: 'Poklon paketi', slika: 'assets/img/naslovnica/kat-poklon.jpg' }
  ],

  proizvodjaci: [
    { id: 'avellana', naziv: 'AVELLANA' },
    { id: 'san-montano', naziv: 'San MONTANO' },
    { id: 'kravata', naziv: 'KRAVATA' },
    { id: 'bogati', naziv: 'BOGATI' },
    { id: 'patrik', naziv: 'PATRIK' }
  ],

  // izdvojeno: redoslijed na naslovnici (1 = gornji red, 2 = donji red)
  proizvodi: [
    {
      id: 'avellana-ljesnjak', marka: 'avellana', naziv: 'Lješnjak', kategorija: 'likeri',
      vrsta: 'Liker od lješnjaka', zapremnina: 0.7, cijena: 12.60, izdvojeno: 1,
      slika: 'assets/img/proizvodi/avellana-ljesnjak.jpg',
      kratko: 'Neodoljivo sladak i primamljiv liker kojem je teško odoljeti.',
      alkohol: '20 % vol.', podrijetlo: 'Hrvatska', pakiranje: '6 boca u kartonu',
      galerija: [
        { slika: 'assets/img/proizvodi/zlato-2020.jpg', naziv: 'Zlato 2020.', opis: 'Zlatna medalja · Dionizijana 2020.' },
        { slika: 'assets/img/proizvodi/sampion-2022.jpg', naziv: 'Šampion 2022.', opis: 'Šampion likera · Zagreb 2022.' },
        { slika: 'assets/img/proizvodi/posluzivanje-redizajn.svg', naziv: 'Posluživanje', opis: 'AVELLANA · Tri načina posluživanja' }
      ],
      posluzivanje: [
        { naziv: 'U čašici', kolicina: '0,05 l', opis: 'Samostalno posluživanje', ikona: 'casa' },
        { naziv: 'U kavici', kolicina: '0,03 l', opis: 'Kao dodatak kavici', ikona: 'kava' },
        { naziv: 'U slastici', kolicina: '0,1 l', opis: 'Kao sastojak slastice', ikona: 'kolac' }
      ],
      temperatura: 'Nema pravila.',
      opis: [
        'Neodoljivo sladak i primamljiv liker kojem je teško odoljeti.',
        'Avellana liker od lješnjaka s ponosom nosi prestižne nagrade koje potvrđuju njegovu izvrsnost i jedinstveni okus.',
        'Ova priznanja rezultat su posvećenosti vrhunskoj kvaliteti, pažljivo biranim sastojcima i stručnoj izradi koja svaku kap Avellana likera čini posebnim doživljajem.'
      ],
      razloziNaslov: 'Zašto odabrati Avellanu?',
      zakljucak: 'Isprobajte Avellanu i otkrijte zašto je šampion među likerima!',
      razlozi: [
        'Profinjen i uravnotežen okus prirodnog lješnjaka.',
        'Savršen izbor za svečane prilike, poklone ili uživanje u posebnom trenutku.',
        'Nagrađena kvaliteta prepoznata na međunarodnoj razini.'
      ],
      nagrade: [
        { slika: 'assets/img/proizvodi/sampion-2022.jpg', naslov: 'Veliko zlato i šampionska titula', mjesto: 'Zagreb, 2022.',
          tekst: 'Osvojeni na 4. Međunarodnom ocjenjivanju rakija i likera održanom 24. i 25. studenog 2022. godine u Zagrebu. Ovo natjecanje organizirao je Gospodarski list u suradnji s Agronomskim i Prehrambeno-biotehnološkim fakultetom u Zagrebu.' },
        { slika: 'assets/img/proizvodi/zlato-2020.jpg', naslov: 'Zlatna medalja', mjesto: 'Dionizijana, 2020.',
          tekst: 'Dodijeljena na 25. međunarodnom ocjenjivanju vina i jakih alkoholnih pića: Dionizijana, održanom na Sajmu Gast u Splitu 2020. godine.' }
      ],
      podaci: [
        ['Naziv likera', 'Avellana liker od lješnjaka 0,70 l'], ['Vrsta', 'Liker od lješnjaka'],
        ['Na tržište stavlja', 'Domestic Spirits d.o.o.'], ['Geografsko podrijetlo', 'Hrvatska'],
        ['Zapremnina boce', '0,7 l'], ['Alkohol', '20 % vol.'],
        ['Gastro preporuka', '0,05 l u čašici, 0,03 l u kavici ili 0,1 l u slastici.'],
        ['Temperatura serviranja', 'Nema pravila'], ['EAN kod', '3859889132312'],
        ['Pakiranje', '6 boca u kartonu'], ['Ambalaža', 'Staklena boca, navojni čep'],
        ['Sidrena cijena na dan 02.05.2025.', '12,60 €']
      ],
      sidrena: 'Sidrena cijena na dan 02.05.2025.: 12,60 €'
    },
    { id: 'san-montano-teranino', marka: 'san-montano', naziv: 'Teranino', kategorija: 'likeri',
      vrsta: 'Vinski liker', zapremnina: 0.7, cijena: 25.00, izdvojeno: 1,
      slika: 'assets/img/proizvodi/san-montano-teranino.jpg' },
    { id: 'kravata-malvazija', marka: 'kravata', naziv: 'Malvazija', kategorija: 'vina',
      vrsta: 'Bijelo vino', zapremnina: 0.75, cijena: 9.20, izdvojeno: 1,
      slika: 'assets/img/proizvodi/kravata-malvazija.jpg' },
    { id: 'bogati-medica', marka: 'bogati', naziv: 'Medica', kategorija: 'likeri',
      vrsta: 'Liker od meda', zapremnina: 0.7, cijena: 11.30, izdvojeno: 1,
      slika: 'assets/img/proizvodi/bogati-medica.jpg' },
    { id: 'kravata-teran', marka: 'kravata', naziv: 'Teran', kategorija: 'vina',
      vrsta: 'Crno vino', zapremnina: 0.75, cijena: 11.90, izdvojeno: 2,
      slika: 'assets/img/proizvodi/kravata-teran.jpg' },
    { id: 'san-montano-biska', marka: 'san-montano', naziv: 'Biska', kategorija: 'rakije',
      vrsta: 'Rakija', zapremnina: 0.7, cijena: 24.00, izdvojeno: 2,
      slika: 'assets/img/proizvodi/san-montano-biska.jpg' },
    { id: 'patrik-limoncello', marka: 'patrik', naziv: 'Limoncello', kategorija: 'likeri',
      vrsta: 'Liker', zapremnina: 0.5, cijena: 15.50, izdvojeno: 2,
      slika: 'assets/img/proizvodi/patrik-limoncello.jpg' }
  ]
};
