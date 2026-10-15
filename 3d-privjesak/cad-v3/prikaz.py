#!/usr/bin/env python3
"""Prikazi iz STL datoteka CAD-a v3 (vlastiti z-buffer renderer; ništa se ne crta „na ruke”).
Pokretanje: python3 prikaz.py  →  prikaz-sklop.png, prikaz-rastavljeno.png, prikaz-ispis.png"""
import os
import numpy as np
import trimesh
from PIL import Image, ImageDraw, ImageFilter, ImageFont

AQ = os.path.dirname(os.path.abspath(__file__))
BOJE = {'crno': (0.075, 0.075, 0.08), 'plavo': (0.0, 0.70, 0.93), 'magenta': (0.92, 0.10, 0.38)}
DIJELOVI = [('1_crna_prednja_polovica', 'crno'), ('2_crna_straznja_polovica', 'crno'),
            ('3_plavi_prednji_umetak', 'plavo'), ('4_magenta_prednji_umetak', 'magenta'),
            ('5_plavi_straznji_umetak', 'plavo'), ('6_magenta_straznji_umetak', 'magenta')]
FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
FONT_B = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'


def rot(yaw, pitch, roll=0.0):
    a, b, c = np.radians([yaw, pitch, roll])
    ry = np.array([[np.cos(a), 0, np.sin(a)], [0, 1, 0], [-np.sin(a), 0, np.cos(a)]])
    rx = np.array([[1, 0, 0], [0, np.cos(b), -np.sin(b)], [0, np.sin(b), np.cos(b)]])
    rz = np.array([[np.cos(c), -np.sin(c), 0], [np.sin(c), np.cos(c), 0], [0, 0, 1]])
    return rx @ ry @ rz


def render(mreze, R, W=1000, H=1000, ss=2, zoom=None, sredina=None, pozadina=(0.93, 0.93, 0.94)):
    """mreze: lista (trimesh, boja_rgb, tekstura_bool). Vraća PIL sliku."""
    W2, H2 = W * ss, H * ss
    zbuf = np.full((H2, W2), -np.inf)
    img = np.zeros((H2, W2, 3))
    tex = np.zeros((H2, W2), bool)
    mask = np.zeros((H2, W2), bool)
    svjetla = [(np.array([-0.45, 0.65, 0.65]), 0.95), (np.array([0.75, 0.15, 0.45]), 0.35),
               (np.array([0.1, -0.7, 0.3]), 0.18)]
    svjetla = [(d / np.linalg.norm(d), k) for d, k in svjetla]
    sve = np.vstack([m.vertices for m, _, _ in mreze])
    if sredina is None:
        sredina = (sve.max(0) + sve.min(0)) / 2
    if zoom is None:
        v = (sve - sredina) @ R.T
        zoom = 0.82 * min(W / np.ptp(v[:, 0]), H / np.ptp(v[:, 1]))
    pola = svjetla[0][0] + np.array([0, 0, 1.0])
    pola /= np.linalg.norm(pola)
    for m, boja, tekstura in mreze:
        v = (m.vertices - sredina) @ R.T
        n = m.face_normals @ R.T
        f = m.faces
        boja = np.array(boja)
        sv = 0.20 + sum(k * np.clip(n @ d, 0, None) for d, k in svjetla)
        spec = (0.18 if tekstura else 0.30) * np.clip(n @ pola, 0, None) ** (18 if tekstura else 40)
        rub = 0.10 * (1 - np.abs(n[:, 2])) ** 3
        col = np.clip(boja[None] * sv[:, None] + (spec + rub)[:, None], 0, 1)
        Pt = np.c_[v[:, 0] * zoom * ss + W2 / 2, -v[:, 1] * zoom * ss + H2 / 2, v[:, 2]]
        for i in np.nonzero(n[:, 2] > -0.02)[0]:
            a, b, c = Pt[f[i]]
            x0, x1 = int(max(min(a[0], b[0], c[0]), 0)), int(min(max(a[0], b[0], c[0]) + 1, W2))
            y0, y1 = int(max(min(a[1], b[1], c[1]), 0)), int(min(max(a[1], b[1], c[1]) + 1, H2))
            if x0 >= x1 or y0 >= y1:
                continue
            d = (b[1] - c[1]) * (a[0] - c[0]) + (c[0] - b[0]) * (a[1] - c[1])
            if abs(d) < 1e-12:
                continue
            xs, ys = np.meshgrid(np.arange(x0, x1) + 0.5, np.arange(y0, y1) + 0.5)
            l1 = ((b[1] - c[1]) * (xs - c[0]) + (c[0] - b[0]) * (ys - c[1])) / d
            l2 = ((c[1] - a[1]) * (xs - c[0]) + (a[0] - c[0]) * (ys - c[1])) / d
            l3 = 1 - l1 - l2
            unutra = (l1 >= -1e-6) & (l2 >= -1e-6) & (l3 >= -1e-6)
            z = l1 * a[2] + l2 * b[2] + l3 * c[2]
            zb = zbuf[y0:y1, x0:x1]
            ok = unutra & (z > zb)
            zb[ok] = z[ok]
            img[y0:y1, x0:x1][ok] = col[i]
            tex[y0:y1, x0:x1][ok] = tekstura
            mask[y0:y1, x0:x1][ok] = True
    # fina tekstura ispisa na crnim dijelovima (vodoravne linije slojeva/podloge)
    rng = np.random.default_rng(3)
    linije = rng.normal(0, 1, (H2, 1)) * 0.6 + rng.normal(0, 1, (H2, W2)) * 0.4
    linije = np.array(Image.fromarray(((linije + 3) * 40).clip(0, 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(0.7))) / 40 - 3
    img = np.where(tex[..., None], np.clip(img * (1 + 0.10 * linije[..., None]), 0, 1), img)
    yy = np.linspace(0, 1, H2)[:, None, None]
    bg = np.array(pozadina) * (1 - 0.06 * yy) * np.ones((H2, W2, 3))
    sj = Image.fromarray((mask * 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(16 * ss))
    sj = np.roll(np.array(sj) / 255.0, (int(18 * ss), int(10 * ss)), (0, 1))[..., None]
    bg = bg * (1 - 0.30 * sj)
    out = np.where(mask[..., None], img, bg)
    return Image.fromarray((out * 255).astype(np.uint8)).resize((W, H), Image.LANCZOS)


def ucitaj():
    return {ime: (trimesh.load(os.path.join(AQ, 'stl', f'{ime}.stl')), BOJE[b], b == 'crno') for ime, b in DIJELOVI}


def naslov(platno, tekst, podnaslov=None):
    d = ImageDraw.Draw(platno)
    d.text((40, 30), tekst, fill=(25, 25, 35), font=ImageFont.truetype(FONT_B, 34))
    if podnaslov:
        d.text((40, 78), podnaslov, fill=(80, 80, 95), font=ImageFont.truetype(FONT, 24))


def main():
    m = ucitaj()
    sve = list(m.values())
    sredina = (np.vstack([x[0].vertices for x in sve]).max(0) + np.vstack([x[0].vertices for x in sve]).min(0)) / 2
    # 1) sklop sprijeda i straga
    Wv = 1000
    platno = Image.new('RGB', (2 * Wv, Wv + 120), (236, 236, 238))
    for k, (ime, R) in enumerate((('Sprijeda', rot(-24, 10, 4)), ('Straga', rot(160, 10, -4)))):
        im = render(sve, R, Wv, Wv, zoom=15.5, sredina=sredina)
        platno.paste(im, (k * Wv, 120))
        ImageDraw.Draw(platno).text((k * Wv + 40, 140), ime, fill=(60, 60, 70), font=ImageFont.truetype(FONT, 26))
    naslov(platno, 'Plan A privjesak – CAD v3 (6 dijelova)', '46 × 60 × 8 mm · prikaz iz STL datoteka za ispis')
    platno.save(os.path.join(AQ, 'prikaz-sklop.png'))
    print('prikaz-sklop.png')

    # 2) rastavljeno (dijelovi razmaknuti po Z)
    pomak = {'2_crna_straznja_polovica': -27, '5_plavi_straznji_umetak': -17, '6_magenta_straznji_umetak': -9,
             '4_magenta_prednji_umetak': 9, '3_plavi_prednji_umetak': 17, '1_crna_prednja_polovica': 27}
    rast = []
    for ime, (t, b, tx) in m.items():
        t2 = t.copy()
        t2.apply_translation([0, 0, pomak[ime]])
        rast.append((t2, b, tx))
    im = render(rast, rot(-58, 22, 0), 1600, 1100)
    platno = Image.new('RGB', (1600, 1220), (236, 236, 238))
    platno.paste(im, (0, 120))
    naslov(platno, 'Rastavljeno: redoslijed slaganja', 'straga ← crna stražnja · plavi · magenta | magenta · plavi · crna prednja → sprijeda')
    platno.save(os.path.join(AQ, 'prikaz-rastavljeno.png'))
    print('prikaz-rastavljeno.png')

    # 3) tri ispisa (kako leže na podlozi)
    platno = Image.new('RGB', (3 * 700, 820), (236, 236, 238))
    for k, (dat, b, ime) in enumerate((('1_ispis_crno', 'crno', '1. ispis – crno'),
                                       ('2_ispis_plavo', 'plavo', '2. ispis – plavo'),
                                       ('3_ispis_magenta', 'magenta', '3. ispis – magenta'))):
        t = trimesh.load(os.path.join(AQ, 'ispis', f'{dat}.stl'))
        im = render([(t, BOJE[b], b == 'crno')], rot(0, -50, 0), 700, 700)
        platno.paste(im, (k * 700, 120))
        ImageDraw.Draw(platno).text((k * 700 + 30, 130), ime, fill=(60, 60, 70), font=ImageFont.truetype(FONT, 26))
    naslov(platno, 'Ispis po bojama – svaki dio leži ravno, bez potpora')
    platno.save(os.path.join(AQ, 'prikaz-ispis.png'))
    print('prikaz-ispis.png')


if __name__ == '__main__':
    main()
