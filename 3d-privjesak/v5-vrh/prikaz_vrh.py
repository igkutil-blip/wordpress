#!/usr/bin/env python3
"""Prikaz privjeska VRH iz STL-ova → prikaz-vrh.png"""
import os
import sys
import numpy as np
import trimesh
from PIL import Image, ImageDraw, ImageFont

AQ = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(os.path.dirname(AQ), 'cad-v3'))
from prikaz import render, rot, FONT, FONT_B  # noqa: E402

B = {'crno': (0.07, 0.07, 0.075), 'svjetloplavo': (0.0, 0.70, 0.93), 'magenta': (0.90, 0.09, 0.37), 'bijelo': (0.93, 0.93, 0.92)}
m = []
for b in B:
    f = os.path.join(AQ, 'stl', f'vrh-{b}.stl')
    if os.path.exists(f):
        m.append((trimesh.load(f), B[b], b == 'crno'))
sve = np.vstack([t.vertices for t, _, _ in m])
sred = (sve.max(0) + sve.min(0)) / 2
W = 1000
pogledi = [('Pogled na vrh', rot(15, -58, 0)), ('Iz drugog kuta', rot(-35, -50, 0)),
           ('Odozgo', rot(0, 0, 0)), ('Straga', rot(180, 0, 0))]
platno = Image.new('RGB', (2 * W, 2 * W + 120), (236, 236, 238))
for k, (ime, R) in enumerate(pogledi):
    im = render(m, R, W, W, zoom=18.0, sredina=sred)
    x, y = (k % 2) * W, (k // 2) * W + 120
    platno.paste(im, (x, y))
    ImageDraw.Draw(platno).text((x + 40, y + 20), ime, fill=(60, 60, 70), font=ImageFont.truetype(FONT, 26))
e = sve.max(0) - sve.min(0)
d = ImageDraw.Draw(platno)
d.text((40, 30), 'Plan A „VRH” – planina na dlanu', fill=(25, 25, 35), font=ImageFont.truetype(FONT_B, 34))
d.text((40, 78), f'{e[0]:.0f} × {e[1]:.0f} × {e[2]:.1f} mm · prikaz iz STL datoteka za ispis (Bambu AMS, 4 boje)', fill=(80, 80, 95), font=ImageFont.truetype(FONT, 24))
platno.save(os.path.join(AQ, 'prikaz-vrh.png'))
print('ok')
