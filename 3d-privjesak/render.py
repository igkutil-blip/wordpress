#!/usr/bin/env python3
"""Fotorealistični pregled privjeska iz STL-ova (vlastiti z-buffer renderer, bez vanjskih 3D programa).
Pokretanje: python3 render.py  →  render-privjesak.png"""
import os
import numpy as np
import trimesh
from PIL import Image, ImageDraw, ImageFilter, ImageFont

OUT = os.path.dirname(os.path.abspath(__file__))
DIJELOVI = [('tijelo-crno', (0.11, 0.11, 0.12)), ('svjetloplavo', (0.0, 0.66, 0.88)), ('magenta', (0.80, 0.06, 0.33))]


def rot(yaw, pitch, roll=0):
    a, b, c = np.radians([yaw, pitch, roll])
    ry = np.array([[np.cos(a), 0, np.sin(a)], [0, 1, 0], [-np.sin(a), 0, np.cos(a)]])
    rx = np.array([[1, 0, 0], [0, np.cos(b), -np.sin(b)], [0, np.sin(b), np.cos(b)]])
    rz = np.array([[np.cos(c), -np.sin(c), 0], [np.sin(c), np.cos(c), 0], [0, 0, 1]])
    return rx @ ry @ rz


def render(mreze, R, W=900, H=900, ss=2, zoom=13.5):
    W2, H2 = W * ss, H * ss
    zbuf = np.full((H2, W2), -np.inf)
    img = np.zeros((H2, W2, 3))
    mask = np.zeros((H2, W2), bool)
    svjetla = [(np.array([-0.4, 0.6, 0.7]), 0.85), (np.array([0.7, 0.1, 0.5]), 0.35), (np.array([0.0, -0.6, -0.3]), 0.25)]
    svjetla = [(d / np.linalg.norm(d), k) for d, k in svjetla]
    pogled = np.array([0, 0, 1.0])
    sredina = np.vstack([m.vertices for m, _ in mreze]).mean(0)
    for m, boja in mreze:
        v = (m.vertices - sredina) @ R.T
        n = m.face_normals @ R.T
        f = m.faces
        boja = np.array(boja)
        # sjenčanje po plohi: ambijent + difuzno + odsjaj (plastika, polumat)
        sv = 0.18 + sum(k * np.clip(n @ d, 0, None) for d, k in svjetla)
        h = (svjetla[0][0] + pogled); h /= np.linalg.norm(h)
        spec = 0.35 * np.clip(n @ h, 0, None) ** 30
        rim = 0.12 * (1 - np.abs(n[:, 2])) ** 3
        col = np.clip(boja[None] * sv[:, None] + (spec + rim)[:, None], 0, 1)
        P = np.c_[v[:, 0] * zoom * ss + W2 / 2, -v[:, 1] * zoom * ss + H2 / 2, v[:, 2]]
        vidljivo = n[:, 2] > -0.05
        for i in np.nonzero(vidljivo)[0]:
            a, b, c = P[f[i]]
            x0, x1 = int(max(min(a[0], b[0], c[0]), 0)), int(min(max(a[0], b[0], c[0]) + 1, W2))
            y0, y1 = int(max(min(a[1], b[1], c[1]), 0)), int(min(max(a[1], b[1], c[1]) + 1, H2))
            if x0 >= x1 or y0 >= y1:
                continue
            xs, ys = np.meshgrid(np.arange(x0, x1) + 0.5, np.arange(y0, y1) + 0.5)
            d = (b[1] - c[1]) * (a[0] - c[0]) + (c[0] - b[0]) * (a[1] - c[1])
            if abs(d) < 1e-12:
                continue
            l1 = ((b[1] - c[1]) * (xs - c[0]) + (c[0] - b[0]) * (ys - c[1])) / d
            l2 = ((c[1] - a[1]) * (xs - c[0]) + (a[0] - c[0]) * (ys - c[1])) / d
            l3 = 1 - l1 - l2
            unutra = (l1 >= -1e-6) & (l2 >= -1e-6) & (l3 >= -1e-6)
            z = l1 * a[2] + l2 * b[2] + l3 * c[2]
            zb = zbuf[y0:y1, x0:x1]
            bolje = unutra & (z > zb)
            zb[bolje] = z[bolje]
            img[y0:y1, x0:x1][bolje] = col[i]
            mask[y0:y1, x0:x1][bolje] = True
    # pozadina + mekana sjena
    yy = np.linspace(0, 1, H2)[:, None, None]
    bg = (np.array([0.97, 0.97, 0.98]) * (1 - 0.08 * yy)) * np.ones((H2, W2, 3))
    sjena = Image.fromarray((mask * 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(18 * ss))
    sjena = np.roll(np.array(sjena) / 255.0, (int(14 * ss), int(10 * ss)), (0, 1))[..., None]
    bg = bg * (1 - 0.28 * sjena)
    out = np.where(mask[..., None], img, bg)
    im = Image.fromarray((out * 255).astype(np.uint8)).resize((W, H), Image.LANCZOS)
    return im


def main():
    mreze = [(trimesh.load(os.path.join(OUT, 'stl', f'plan-a-privjesak-{ime}.stl')), b) for ime, b in DIJELOVI]
    pogledi = [('Sprijeda', rot(-25, 12)), ('Straga', rot(155, 12)),
               ('Lijevi bok', rot(-80, 8)), ('Odozgo (rub s otvorom)', rot(-20, 70))]
    W = 900
    platno = Image.new('RGB', (2 * W, 2 * W + 90), (247, 247, 249))
    d = ImageDraw.Draw(platno)
    try:
        font = ImageFont.truetype('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', 30)
        mali = ImageFont.truetype('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', 24)
    except OSError:
        font = mali = ImageFont.load_default()
    for k, (ime, R) in enumerate(pogledi):
        im = render(mreze, R, W, W)
        x, y = (k % 2) * W, (k // 2) * W + 90
        platno.paste(im, (x, y))
        d.text((x + 30, y + 24), ime, fill=(40, 40, 50), font=mali)
        print('gotovo:', ime)
    d.text((30, 28), 'Plan A NFC privjesak v2 – 38 × 48 × 6 mm (prikaz iz STL datoteka za ispis)', fill=(20, 20, 30), font=font)
    platno.save(os.path.join(OUT, 'render-privjesak.png'))


if __name__ == '__main__':
    main()
