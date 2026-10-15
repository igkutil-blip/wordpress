#!/usr/bin/env python3
"""Prikaz v4 iz STL-ova (isti renderer kao cad-v3/prikaz.py). → prikaz-v4.png"""
import os
import sys
import numpy as np
import trimesh
from PIL import Image, ImageDraw, ImageFont

AQ = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(os.path.dirname(AQ), 'cad-v3'))
from prikaz import render, rot, BOJE, FONT, FONT_B  # noqa: E402

m = [(trimesh.load(os.path.join(AQ, 'stl', f'plan-a-privjesak-v4-{i}.stl')), BOJE[b], b == 'crno')
     for i, b in (('crno', 'crno'), ('svjetloplavo', 'plavo'), ('magenta', 'magenta'))]
sve = np.vstack([t.vertices for t, _, _ in m])
sred = (sve.max(0) + sve.min(0)) / 2
W = 1000
pogledi = [('Sprijeda', rot(-22, 12, 3)), ('Straga', rot(158, 12, -3)), ('Bok', rot(-75, 8, 0)), ('Odozgo', rot(-15, 55, 0))]
platno = Image.new('RGB', (2 * W, 2 * W + 120), (236, 236, 238))
for k, (ime, R) in enumerate(pogledi):
    im = render(m, R, W, W, zoom=17.5, sredina=sred)
    x, y = (k % 2) * W, (k // 2) * W + 120
    platno.paste(im, (x, y))
    ImageDraw.Draw(platno).text((x + 40, y + 20), ime, fill=(60, 60, 70), font=ImageFont.truetype(FONT, 26))
d = ImageDraw.Draw(platno)
e = sve.max(0) - sve.min(0)
d.text((40, 30), 'Plan A privjesak v4 – jedan višebojni ispis (Bambu AMS)', fill=(25, 25, 35), font=ImageFont.truetype(FONT_B, 34))
d.text((40, 78), f'{e[0]:.0f} × {e[1]:.0f} × {e[2]:.1f} mm · prikaz iz STL datoteka za ispis', fill=(80, 80, 95), font=ImageFont.truetype(FONT, 24))
platno.save(os.path.join(AQ, 'prikaz-v4.png'))
print('prikaz-v4.png')
