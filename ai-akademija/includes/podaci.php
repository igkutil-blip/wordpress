<?php
/**
 * Privremeni (izmišljeni) podaci za sve stranice – zamijeniti stvarnima prije objave.
 * Tečajevi, mentori, cijene, pitanja i kontakt na jednom mjestu, da se sve stranice slažu.
 */
defined( 'ABSPATH' ) || exit;

final class AKA_Podaci {

	/** Kontakt i tvrtka – PRIMJER. */
	public static function kontakt() {
		return array(
			'email'   => 'info@aiakademija.hr',
			'telefon' => '+385 1 234 5678',
			'adresa'  => 'Ulica primjera 12, 10000 Zagreb',
			'radno'   => 'Pon – pet, 9 – 17 h',
			'tvrtka'  => 'AI Akademija d.o.o. (primjer)',
			'oib'     => '00000000000',
		);
	}

	/** Tečajevi. thumb = boja sličice (1–5), level = boja oznake razine (1–4). */
	public static function tecajevi() {
		return array(
			'uvod-u-ai'          => array(
				'naslov'  => 'Uvod u AI',
				'razina'  => 'Početnik',
				'level'   => 1,
				'thumb'   => 1,
				'ikona'   => 'robot',
				'sati'    => 12,
				'lekcija' => 8,
				'cijena'  => 89,
				'mentor'  => 'ana-horvat',
				'opis'    => 'Razumij osnove umjetne inteligencije i nauči kako je koristiti na jednostavan i praktičan način.',
				'ishodi'  => array( 'Razumjeti što AI može, a što ne može', 'Koristiti ChatGPT, Claude i Gemini za svakodnevne zadatke', 'Prepoznati dobre i loše odgovore AI-ja', 'Sigurno i odgovorno koristiti AI alate' ),
				'moduli'  => array(
					array( 'Što je umjetna inteligencija', array( 'Kratka povijest AI-ja', 'Kako rade jezični modeli', 'Mitovi i stvarnost' ) ),
					array( 'Prvi koraci s AI alatima', array( 'Otvaranje računa i sučelje', 'Prvi razgovor s AI-jem', 'Spremanje i dijeljenje razgovora' ) ),
					array( 'AI u svakodnevnom životu', array( 'Pisanje i ispravljanje teksta', 'Planiranje i organizacija', 'Učenje uz AI' ) ),
					array( 'Sigurnost i privatnost', array( 'Što ne dijeliti s AI-jem', 'Provjera točnosti', 'Završni projekt' ) ),
				),
			),
			'prompt-engineering' => array(
				'naslov'  => 'Prompt Engineering',
				'razina'  => 'Početnik',
				'level'   => 2,
				'thumb'   => 2,
				'ikona'   => 'dokument',
				'sati'    => 10,
				'lekcija' => 8,
				'cijena'  => 99,
				'mentor'  => 'marko-kovac',
				'opis'    => 'Nauči pisati bolje upite i postići vrhunske rezultate s AI alatima poput ChatGPT-a.',
				'ishodi'  => array( 'Pisati jasne upite koji daju točne odgovore', 'Koristiti uloge, primjere i strukturu', 'Izraditi vlastitu biblioteku upita', 'Ispraviti i doraditi loš odgovor' ),
				'moduli'  => array(
					array( 'Osnove dobrog upita', array( 'Kontekst, zadatak, oblik', 'Najčešće pogreške', 'Vježba: prije i poslije' ) ),
					array( 'Napredne tehnike', array( 'Uloge i ton', 'Primjeri u upitu', 'Razmišljanje korak po korak' ) ),
					array( 'Upiti za posao', array( 'E-mailovi i izvještaji', 'Analiza teksta i tablica', 'Biblioteka upita' ) ),
				),
			),
			'ai-za-posao'        => array(
				'naslov'  => 'AI za posao',
				'razina'  => 'Početnik',
				'level'   => 3,
				'thumb'   => 3,
				'ikona'   => 'laptop',
				'sati'    => 12,
				'lekcija' => 8,
				'cijena'  => 119,
				'mentor'  => 'ivana-babic',
				'opis'    => 'Otkrij kako koristiti AI alate za veću produktivnost i bolje rezultate u svakodnevnom poslu.',
				'ishodi'  => array( 'Ubrzati pisanje e-mailova, ponuda i izvještaja', 'Sažimati sastanke i dokumente', 'Analizirati podatke bez formula', 'Uvesti AI u svoj tim' ),
				'moduli'  => array(
					array( 'AI na radnom mjestu', array( 'Gdje AI štedi najviše vremena', 'Pravila tvrtke i privatnost', 'Odabir alata' ) ),
					array( 'Pisanje i komunikacija', array( 'E-mailovi i ponude', 'Prezentacije', 'Prijevodi' ) ),
					array( 'Podaci i odluke', array( 'Tablice uz AI', 'Sažeci i izvještaji', 'Završni projekt' ) ),
				),
			),
			'ai-za-kreativce'    => array(
				'naslov'  => 'AI za kreativce',
				'razina'  => 'Srednja razina',
				'level'   => 4,
				'thumb'   => 4,
				'ikona'   => 'slika',
				'sati'    => 14,
				'lekcija' => 10,
				'cijena'  => 129,
				'mentor'  => 'luka-novak',
				'opis'    => 'Izradi slike, video i tekstove uz pomoć AI alata – od ideje do gotovog sadržaja.',
				'ishodi'  => array( 'Izraditi slike za društvene mreže i web', 'Napraviti kratki video uz AI', 'Pisati tekstove u svom stilu', 'Poznavati autorska prava i AI' ),
				'moduli'  => array(
					array( 'Slike uz AI', array( 'Alati za slike', 'Opis slike koji radi', 'Uređivanje i dorada' ) ),
					array( 'Video i zvuk', array( 'Kratki video iz teksta', 'Glas i titlovi', 'Montaža' ) ),
					array( 'Tekst i stil', array( 'Tvoj stil pisanja', 'Objave za mreže', 'Autorska prava' ) ),
				),
			),
			'automatizacija'     => array(
				'naslov'  => 'Automatizacija s AI-jem',
				'razina'  => 'Srednja razina',
				'level'   => 2,
				'thumb'   => 5,
				'ikona'   => 'zupcanik',
				'sati'    => 12,
				'lekcija' => 9,
				'cijena'  => 149,
				'mentor'  => 'marko-kovac',
				'opis'    => 'Poveži alate i prepusti ponavljajuće zadatke AI asistentima – bez programiranja.',
				'ishodi'  => array( 'Prepoznati zadatke za automatizaciju', 'Povezati e-mail, tablice i AI', 'Izraditi vlastitog AI asistenta', 'Pratiti i popravljati automatizacije' ),
				'moduli'  => array(
					array( 'Osnove automatizacije', array( 'Okidači i radnje', 'Alati bez programiranja', 'Prva automatizacija' ) ),
					array( 'AI u automatizaciji', array( 'Razvrstavanje e-mailova', 'Sažeci i odgovori', 'Vlastiti asistent' ) ),
					array( 'U stvarnom poslu', array( 'Praćenje i greške', 'Sigurnost podataka', 'Završni projekt' ) ),
				),
			),
		);
	}

	public static function tecaj( $slug ) {
		$t = self::tecajevi();
		return isset( $t[ $slug ] ) ? $t[ $slug ] + array( 'slug' => $slug ) : null;
	}

	/** Mentori – izmišljene osobe (PRIMJER). */
	public static function mentori() {
		return array(
			'ana-horvat'  => array( 'ime' => 'Ana Horvat', 'uloga' => 'AI edukatorica', 'grad' => 1, 'opis' => 'Deset godina predaje digitalne vještine. Vjeruje da AI može naučiti svatko – korak po korak.' ),
			'marko-kovac' => array( 'ime' => 'Marko Kovač', 'uloga' => 'Stručnjak za automatizaciju', 'grad' => 2, 'opis' => 'Pomaže tvrtkama da ponavljajuće zadatke prepuste AI asistentima i uštede sate tjedno.' ),
			'ivana-babic' => array( 'ime' => 'Ivana Babić', 'uloga' => 'Konzultantica za poslovne procese', 'grad' => 3, 'opis' => 'Uvodila je AI alate u timove od pet do petsto ljudi. Fokus: konkretni rezultati.' ),
			'luka-novak'  => array( 'ime' => 'Luka Novak', 'uloga' => 'Kreativni direktor', 'grad' => 4, 'opis' => 'Spaja dizajn, video i AI. Polaznicima pokazuje kako od ideje do objave stići u jednom danu.' ),
		);
	}

	/** Paketi cijena – PRIMJER. */
	public static function paketi() {
		return array(
			array( 'naziv' => 'Pojedinačni tečaj', 'cijena' => 'od 89 €', 'nap' => 'jednokratno', 'istaknut' => false, 'stavke' => array( 'Jedan tečaj po izboru', 'Pristup lekcijama 12 mjeseci', 'Vježbe i materijali', 'Certifikat' ), 'gumb' => 'Odaberi tečaj', 'url' => '/programi/' ),
			array( 'naziv' => 'Sve uključeno', 'cijena' => '19 €', 'nap' => 'mjesečno', 'istaknut' => true, 'stavke' => array( 'Svi tečajevi', 'Novi tečajevi čim izađu', 'Mjesečna radionica uživo', 'Zajednica polaznika', 'Certifikati' ), 'gumb' => 'Započni učenje', 'url' => '/kontakt/?paket=sve-ukljuceno' ),
			array( 'naziv' => 'Za tvrtke', 'cijena' => 'po dogovoru', 'nap' => 'od 5 polaznika', 'istaknut' => false, 'stavke' => array( 'Program prilagođen timu', 'Radionice uživo ili online', 'Praćenje napretka', 'Račun na tvrtku' ), 'gumb' => 'Zatraži ponudu', 'url' => '/za-tvrtke/#upit' ),
		);
	}

	/** Česta pitanja. */
	public static function pitanja() {
		return array(
			array( 'Trebam li predznanje?', 'Ne. Tečajevi za početnike kreću od samih osnova – dovoljno je znati koristiti računalo ili mobitel.' ),
			array( 'Koliko dugo imam pristup tečaju?', 'Lorem ipsum: pristup lekcijama traje 12 mjeseci od kupnje, a uz paket „Sve uključeno” dok je pretplata aktivna.' ),
			array( 'Dobivam li certifikat?', 'Da. Nakon završenih lekcija i završnog projekta dobivaš certifikat AI Akademije s tvojim imenom.' ),
			array( 'Mogu li učiti s mobitela?', 'Možeš. Lekcije su prilagođene mobitelu, tabletu i računalu, a napredak se sprema automatski.' ),
			array( 'Koje AI alate koristimo?', 'Lorem ipsum: ChatGPT, Claude, Gemini i druge alate – uvijek objasnimo i besplatne mogućnosti.' ),
			array( 'Mogu li dobiti račun na tvrtku?', 'Da. Pri kupnji upiši podatke tvrtke ili nam se javi za ponudu za cijeli tim.' ),
		);
	}

	/** Ikone (SVG, crte). */
	public static function ikona( $ime ) {
		$i = array(
			'robot'    => '<svg viewBox="0 0 48 48"><rect x="10" y="14" width="28" height="22" rx="8"/><circle cx="19" cy="25" r="2.5"/><circle cx="29" cy="25" r="2.5"/><path d="M24 14V8M21 6h6M6 22v6M42 22v6"/></svg>',
			'dokument' => '<svg viewBox="0 0 48 48"><rect x="8" y="10" width="26" height="30" rx="4"/><path d="M14 18h14M14 24h14M14 30h8"/><path d="M38 6l1.6 4.4L44 12l-4.4 1.6L38 18l-1.6-4.4L32 12l4.4-1.6z"/></svg>',
			'laptop'   => '<svg viewBox="0 0 48 48"><rect x="8" y="10" width="32" height="22" rx="3"/><path d="M4 38h40M15 26v-4M21 26v-8M27 26v-6M33 26v-10"/></svg>',
			'slika'    => '<svg viewBox="0 0 48 48"><rect x="6" y="10" width="36" height="28" rx="4"/><circle cx="17" cy="20" r="3.5"/><path d="M6 32l10-9 8 7 6-5 12 10"/></svg>',
			'zupcanik' => '<svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="6"/><path d="M24 6v6M24 36v6M6 24h6M36 24h6M11.3 11.3l4.2 4.2M32.5 32.5l4.2 4.2M11.3 36.7l4.2-4.2M32.5 15.5l4.2-4.2"/></svg>',
			'sat'      => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
			'stupci'   => '<svg viewBox="0 0 24 24"><path d="M5 20v-6M12 20V9M19 20V4"/></svg>',
			'cert'     => '<svg viewBox="0 0 24 24"><path d="M14 3H6.5A1.5 1.5 0 0 0 5 4.5v15A1.5 1.5 0 0 0 6.5 21h11a1.5 1.5 0 0 0 1.5-1.5V8z"/><path d="M14 3v5h5"/></svg>',
			'kvacica'  => '<svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>',
			'strelica' => '<svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
			'mail'     => '<svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5L12 13l8.5-6.5"/></svg>',
			'telefon'  => '<svg viewBox="0 0 24 24"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>',
			'mjesto'   => '<svg viewBox="0 0 24 24"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>',
			'meta'     => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5"/></svg>',
			'ljudi'    => '<svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.2"/><path d="M3 19c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><circle cx="17" cy="9" r="2.5"/><path d="M16 13.6c2.8.2 5 2.2 5 5.4"/></svg>',
			'munja'    => '<svg viewBox="0 0 24 24"><path d="M13 3L5 13.5h6L10 21l8-10.5h-6z"/></svg>',
			'srce'     => '<svg viewBox="0 0 24 24"><path d="M12 20s-7.5-4.6-9-9.5C2 6.9 4.4 4.5 7.3 4.5c1.9 0 3.4 1 4.7 2.6 1.3-1.6 2.8-2.6 4.7-2.6 2.9 0 5.3 2.4 4.3 6C19.5 15.4 12 20 12 20z"/></svg>',
			'stit'     => '<svg viewBox="0 0 24 24"><path d="M12 3l7.5 3v5.5c0 4.6-3.1 8.2-7.5 9.5-4.4-1.3-7.5-4.9-7.5-9.5V6z"/><path d="M8.5 12l2.5 2.5 4.5-5"/></svg>',
		);
		return isset( $i[ $ime ] ) ? $i[ $ime ] : '';
	}
}
