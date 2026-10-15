#!/usr/bin/env python3
"""Plan A – privjesak „VRH”: topografska planina na medaljonu (višebojni ispis, boje po visini).

Masiv s dva vrha (kao trokuti iz Plan A znaka) izrađen je u terasama poput reljefne karte. Boja ovisi samo o
visini sloja: crna podloga i padine, plave izohipse (svaka n-ta terasa), magenta gornji dio, bijeli snijeg na vrhu.
Straga je ravno (leži na podlozi) s natpisom PLAN A u prvim slojevima.

Pokretanje: pip install manifold3d shapely trimesh numpy scipy contourpy matplotlib
            python3 izradi_vrh.py
"""
import json
import os
import sys

import numpy as np
import contourpy
import manifold3d as m3d
import trimesh
from scipy.ndimage import gaussian_filter
from shapely.geometry import Polygon, Point, MultiPolygon
from shapely.geometry.polygon import orient
from shapely.ops import unary_union
from shapely import affinity

AQ = os.path.dirname(os.path.abspath(__file__))
KORIJEN = os.path.dirname(AQ)
sys.path.insert(0, KORIJEN)
from privjesak import spremi_3mf, BOJE as _B  # noqa: E402

P = json.load(open(os.path.join(AQ, 'parametri.json'), encoding='utf-8'))
BOJE = {'crno': '#151517', 'svjetloplavo': '#00B9E8', 'magenta': '#D4145A', 'bijelo': '#F2F2F0'}


def dijelovi(g):
    return [p for p in getattr(g, 'geoms', [g]) if not p.is_empty and p.geom_type == 'Polygon' and p.area > 1e-4]


def cs(g):
    k = []
    for p in dijelovi(g):
        p = orient(p, 1.0)
        k.append(np.array(p.exterior.coords[:-1]))
        k += [np.array(r.coords[:-1]) for r in p.interiors]
    return m3d.CrossSection(k, m3d.FillRule.EvenOdd)


def prizma(g, z0, z1):
    return m3d.Manifold.extrude(cs(g), z1 - z0).translate((0, 0, z0))


def obris():
    r = P['promjer'] / 2
    disk = Point(0, 0).buffer(r, quad_segs=96)
    usica = Point(*P['usica_sredina']).buffer(P['usica_polumjer'], quad_segs=48)
    s = P['usica_spoj']
    return unary_union([disk, usica]).buffer(s, quad_segs=48).buffer(-s, quad_segs=48)


def visina_terena():
    """Visinska karta masiva (mm iznad podloge): ovojnica vrhova × greben-šum (oštri grebeni, jaruge)."""
    n = P['mreza']
    r = P['promjer'] / 2
    x = np.linspace(-r, r, n)
    X, Y = np.meshgrid(x, x)
    rng = np.random.default_rng(P['sjeme'])
    piks = 2 * r / n

    def sum_(skala):
        z = gaussian_filter(rng.normal(0, 1, X.shape), skala / piks)
        return z / (np.abs(z).max() + 1e-9)

    # iskrivljenje prostora da vrhovi ne budu okrugli
    wx, wy = sum_(P['iskrivljenje'][0]) * P['iskrivljenje'][1], sum_(P['iskrivljenje'][0]) * P['iskrivljenje'][1]
    Xw, Yw = X + wx, Y + wy
    env = np.zeros_like(X)
    for (cx, cy, H, R, smjer, izduzenje, oblik) in P['vrhovi']:
        a = np.radians(smjer)
        u = (Xw - cx) * np.cos(a) + (Yw - cy) * np.sin(a)
        v = -(Xw - cx) * np.sin(a) + (Yw - cy) * np.cos(a)
        d = (np.abs(u / izduzenje) ** 1.5 + np.abs(v) ** 1.5) ** (1 / 1.5) / R   # malo „uglati” vrh
        env = np.maximum(env, H * np.clip(1 - d, 0, None) ** oblik)
    greben = np.zeros_like(X)
    ukupno = 0
    for skala, amp in P['grebeni']:
        greben += amp * (1 - np.abs(sum_(skala))) ** 2
        ukupno += amp
    greben /= ukupno
    h = env * (P['greben_baza'] + (1 - P['greben_baza']) * greben)
    rr = np.sqrt(X ** 2 + Y ** 2)
    h = h * np.clip((P['masiv_polumjer'] - rr) / P['masiv_prijelaz'], 0, 1)
    return x, gaussian_filter(h, 0.25 / piks)


def main():
    o = obris()
    rupa = Point(*P['usica_sredina']).buffer(P['otvor_promjer'] / 2, quad_segs=48)
    zb = P['podloga']
    sk = P['skosenje']
    korak = P['terasa']
    x, h = visina_terena()
    gen = contourpy.contour_generator(x, x, h, fill_type=contourpy.FillType.OuterCode)
    hmax = float(h.max())
    nivoi = np.arange(korak, hmax + 1e-9, korak)

    tijela = {'crno': [], 'svjetloplavo': [], 'magenta': [], 'bijelo': []}
    # podloga (medaljon) sa skošenim donjim rubom
    n_sk = 6
    for i in range(n_sk):
        z0 = sk * i / n_sk
        g = o.buffer(-(sk - (z0 + sk / n_sk / 2)), quad_segs=32).difference(rupa)
        tijela['crno'].append(prizma(g, z0, z0 + sk / n_sk + 1e-4))
    baza = o.difference(rupa)
    tijela['crno'].append(prizma(baza, sk, zb))
    # natpis straga (prvi slojevi, gledano straga → zrcaljeno)
    natpis = tekst_poligon()
    natpis_m = affinity.scale(natpis, -1, 1, origin=(0, 0))

    # terase
    plave = set(P['plave_terase'])
    gornji = P['magenta_od']
    snijeg = P['snijeg_od']
    for k, z in enumerate(nivoi, start=1):
        polys = []
        filled = gen.filled(z, hmax + 10)
        for pts, codes in zip(*filled):
            starts = np.nonzero(codes == 1)[0].tolist() + [len(pts)]
            prst = [pts[starts[i]:starts[i + 1]] for i in range(len(starts) - 1)]
            if len(prst[0]) < 4:
                continue
            p = Polygon(prst[0], [r for r in prst[1:] if len(r) >= 4]).buffer(0)
            polys.append(p)
        g = unary_union(polys).buffer(0.05).buffer(-0.05)
        g = unary_union([p for p in dijelovi(g) if p.area > P['najmanja_terasa']])
        if g.is_empty:
            continue
        z0, z1 = zb + (k - 1) * korak, zb + k * korak
        rel = z / hmax
        if rel >= snijeg:
            b = 'bijelo'
        elif rel >= gornji:
            b = 'magenta'
        elif k in plave or (P['plava_svaka'] and k % P['plava_svaka'] == 0):
            b = 'svjetloplavo'
        else:
            b = 'crno'
        tijela[b].append(prizma(g, z0, z1))

    mreze = []
    gotovo = {}
    for b, lista in tijela.items():
        if lista:
            gotovo[b] = m3d.Manifold.batch_boolean(lista, m3d.OpType.Add)
    # natpis: oduzmi od crnog, dodaj kao magenta (dubina P['natpis_dubina'])
    nat = prizma(natpis_m, 0, P['natpis_dubina']) ^ gotovo['crno']
    gotovo['crno'] = gotovo['crno'] - nat
    gotovo['magenta'] = gotovo.get('magenta', m3d.Manifold()) + nat
    # obojene terase se međusobno ne preklapaju, ali dodiruju – provjera
    imena = list(gotovo)
    for i in range(len(imena)):
        for j in range(i + 1, len(imena)):
            v = (gotovo[imena[i]] ^ gotovo[imena[j]]).volume()
            assert v < 0.05, (imena[i], imena[j], v)
    os.makedirs(os.path.join(AQ, 'stl'), exist_ok=True)
    for b, m in gotovo.items():
        mm = m.to_mesh()
        t = trimesh.Trimesh(np.array(mm.vert_properties)[:, :3], np.array(mm.tri_verts), process=True)
        assert t.is_watertight, b
        t.export(os.path.join(AQ, 'stl', f'vrh-{b}.stl'))
        mreze.append((b, t, b))
    _B.update(BOJE)
    spremi_3mf(os.path.join(AQ, 'plan-a-vrh.3mf'), mreze, 'Plan A VRH')
    sve = trimesh.util.concatenate([t for _, t, _ in mreze])
    info = {'mjere_mm': [round(v, 1) for v in sve.extents], 'terasa': len(nivoi),
            'vrh_mm': round(zb + len(nivoi) * korak, 2)}
    json.dump(info, open(os.path.join(AQ, 'provjere.json'), 'w'), indent=1)
    print(info)


def tekst_poligon():
    from matplotlib.textpath import TextPath
    from matplotlib.font_manager import FontProperties
    fp = FontProperties(fname=P['font'])
    out = []
    for i, (s, vis, y) in enumerate(P['natpis_straga']):
        g = Polygon()
        for v in TextPath((0, 0), s, size=1.0, prop=fp).to_polygons():
            if len(v) >= 3:
                g = g.symmetric_difference(Polygon(v).buffer(0))
        minx, miny, maxx, maxy = g.bounds
        m = vis / (maxy - miny)
        g = affinity.scale(g, m, m, origin=(0, 0))
        minx, miny, maxx, maxy = g.bounds
        out.append(affinity.translate(g, -(minx + maxx) / 2, y - (miny + maxy) / 2))
    return unary_union(out)


if __name__ == '__main__':
    main()
