#!/usr/bin/env python3
"""Plan A – NFC privjesak v4: JEDAN višebojni ispis (Bambu AMS), izgled prema fotografiji.

Oblutak gotovo kvadratnog obrisa s ušicom uklopljenom u gornji lijevi kut. Prednja strana je zaobljena
(rub + blaga kupola), stražnja ravna (leži na podlozi, bez potpora; donji rub skošen 45°).
Boje su dijelovi istog predmeta:
  sprijeda – logo izdignut 0,3 mm i obojen 0,8 mm u dubinu, pruge u ravnini po zaobljenom rubu;
  straga   – NFC lukovi, natpis PLAN A i pruge u ravnini plohe (prvi slojevi ispisa).

Pokretanje:  pip install manifold3d shapely trimesh numpy matplotlib
             python3 izradi_v4.py
"""
import json
import os
import sys

import numpy as np
import manifold3d as m3d
import trimesh
from shapely.geometry import Polygon, Point
from shapely.ops import unary_union, substring
from shapely import affinity

AQ = os.path.dirname(os.path.abspath(__file__))
KORIJEN = os.path.dirname(AQ)
sys.path.insert(0, KORIJEN)
from privjesak import svg_logo, spremi_3mf, BOJE as _B  # noqa: E402

P = json.load(open(os.path.join(AQ, 'parametri.json'), encoding='utf-8'))
BOJE = {'crno': '#1A1A1A', 'svjetloplavo': '#00B9E8', 'magenta': '#D4145A'}


# ------------------------------------------------------------------ 2D
def obris():
    """Superelipsa (zaobljeni kvadrat, dolje malo šira); gornji lijevi kut izvučen u ušicu (konveksna ovojnica)."""
    a, b, n = P['sirina'] / 2, P['visina_tijela'] / 2, P['superelipsa_n']
    t = np.linspace(0, 2 * np.pi, 720, endpoint=False)
    x = a * np.sign(np.cos(t)) * np.abs(np.cos(t)) ** (2 / n)
    y = b * np.sign(np.sin(t)) * np.abs(np.sin(t)) ** (2 / n)
    x = x * (1.0 - P['suzenje_prema_vrhu'] * (y / b))
    tijelo = Polygon(np.c_[x, y])
    usica = Point(*P['usica_sredina']).buffer(P['usica_polumjer'], quad_segs=48)
    r = P['usica_spoj']
    g = unary_union([tijelo, usica]).convex_hull
    return g.buffer(-r, quad_segs=48).buffer(r, quad_segs=48).simplify(0.005)


def otvor():
    """Zaobljeni trokut, vrh prema kutu ušice."""
    r = P['otvor_zaobljenje']
    s = P['otvor_sirina'] + 2 * r * (np.sqrt(3) - 1)
    h = s * np.sqrt(3) / 2
    tri = Polygon([(-s / 2, -h / 3), (s / 2, -h / 3), (0, 2 * h / 3)]).buffer(-r, quad_segs=24).buffer(r, quad_segs=24)
    tri = affinity.rotate(tri, P['otvor_kut'], origin=(0, 0))
    return affinity.translate(tri, *P['otvor_sredina'])


def dijelovi(g):
    return [p for p in getattr(g, 'geoms', [g]) if not p.is_empty and p.area > 1e-6]


def traka(o, od_ruba, tocke, sirina):
    """Traka na udaljenosti 'od_ruba' od obrisa, od prve do zadnje točke, kroz srednje."""
    prsten = o.buffer(-od_ruba, quad_segs=48).exterior
    L = prsten.length
    s = [prsten.project(Point(p)) for p in tocke]
    a, b = s[0], s[-1]
    naprijed = all((x - a) % L <= (b - a) % L for x in s[1:-1])
    if naprijed:
        dij = [substring(prsten, a, b)] if b > a else [substring(prsten, a, L), substring(prsten, 0, b)]
    else:
        dij = [substring(prsten, b, a)] if a > b else [substring(prsten, b, L), substring(prsten, 0, a)]
    return unary_union(dij).buffer(sirina / 2, cap_style='round', quad_segs=12)


def nfc_lukovi():
    kut = np.radians(P['nfc_kut'])
    out = []
    for r0, r1 in P['nfc_polumjeri']:
        t = np.linspace(-kut, kut, 72)
        luk = Polygon(np.vstack([np.c_[r1 * np.cos(t), r1 * np.sin(t)], np.c_[r0 * np.cos(t[::-1]), r0 * np.sin(t[::-1])]]))
        z = (r1 - r0) / 2 * 0.95
        out.append(luk.buffer(-z, quad_segs=12).buffer(z, quad_segs=12))
    g = unary_union(out)
    minx, miny, maxx, maxy = g.bounds
    m = P['nfc_visina'] / (maxy - miny)
    g = affinity.scale(g, m, m, origin=(0, 0))
    minx, miny, maxx, maxy = g.bounds
    return affinity.translate(g, P['nfc_mjesto'][0] - (minx + maxx) / 2, P['nfc_mjesto'][1] - (miny + maxy) / 2)


def tekst():
    from matplotlib.textpath import TextPath
    from matplotlib.font_manager import FontProperties
    fp = FontProperties(fname=P['font'])
    g = Polygon()
    for v in TextPath((0, 0), 'PLAN A', size=1.0, prop=fp).to_polygons():
        if len(v) >= 3:
            g = g.symmetric_difference(Polygon(v).buffer(0))
    minx, miny, maxx, maxy = g.bounds
    m = P['natpis_visina'] / (maxy - miny)
    g = affinity.scale(g, m, m, origin=(0, 0))
    minx, miny, maxx, maxy = g.bounds
    return affinity.translate(g, P['natpis_mjesto'][0] - (minx + maxx) / 2, P['natpis_mjesto'][1] - (miny + maxy) / 2)


def logo():
    lp, lm = svg_logo(os.path.join(KORIJEN, 'logo', 'logo-jednostavni.svg'))
    m = P['logo_sirina'] / 30.0
    lp, lm = affinity.scale(lp, m, m, origin=(0, 0)), affinity.scale(lm, m, m, origin=(0, 0))
    minx, miny, maxx, maxy = unary_union([lp, lm]).bounds
    dx, dy = P['logo_mjesto'][0] - (minx + maxx) / 2, P['logo_mjesto'][1] - (miny + maxy) / 2
    lp, lm = affinity.translate(lp, dx, dy), affinity.translate(lm, dx, dy)
    # najmanji crni razmak između dijelova loga (vidljiv tanki obrub, kao na fotografiji)
    d = P['logo_razmak']
    svi = sorted([(g, 'p') for g in dijelovi(lp)] + [(g, 'm') for g in dijelovi(lm)], key=lambda x: -x[0].area)
    gotovi = []
    for g, b in svi:
        for v, _ in gotovi:
            if g.distance(v) < d:
                g = g.difference(v.buffer(d + 0.01, quad_segs=12))
        gotovi.append((max(dijelovi(g.buffer(0)), key=lambda p: p.area), b))
    return unary_union([g for g, b in gotovi if b == 'p']), unary_union([g for g, b in gotovi if b == 'm'])


def zrcali(g):
    return affinity.scale(g, -1, 1, origin=(0, 0))


# ------------------------------------------------------------------ 3D
def cs(g):
    from shapely.geometry.polygon import orient
    k = []
    for p in dijelovi(g):
        p = orient(p, 1.0)
        k.append(np.array(p.exterior.coords[:-1]))
        k += [np.array(r.coords[:-1]) for r in p.interiors]
    return m3d.CrossSection(k, m3d.FillRule.EvenOdd)


def prizma(g, z0=-5, z1=30):
    return m3d.Manifold.extrude(cs(g), z1 - z0).translate((0, 0, z0))


def uvlaka_gore(z):
    """Koliko je presjek na visini z uvučen od obrisa (gornji dio: zaobljeni rub pa kupola)."""
    zs, R, D, L = P['visina_boka'], P['polumjer_ruba'], P['kupola'], P['kupola_sirina']
    if z <= zs:
        return 0.0
    if z <= zs + R:
        u = (z - zs) / R
        return R * (1 - np.sqrt(max(1 - u * u, 0)))
    v = min((z - zs - R) / D, 1.0)
    return R + L * (1 - np.sqrt(max(1 - v, 0)))


def tijelo(o):
    c = P['skosenje_dna']
    dz = P['korak_z']
    zs, R, D = P['visina_boka'], P['polumjer_ruba'], P['kupola']
    vrh = zs + R + D
    sl = []
    # donje skošenje 45° (ispisivo bez potpora)
    n = max(int(round(c / dz)), 1)
    for i in range(n):
        z0 = c * i / n
        g = o.buffer(-(c - (z0 + c / n / 2)), quad_segs=24)
        sl.append(prizma(g, z0, z0 + c / n + 1e-4))
    sl.append(prizma(o, c, zs))
    z = zs
    while z < vrh - 1e-9:
        z1 = min(z + dz, vrh)
        g = o.buffer(-uvlaka_gore((z + z1) / 2), quad_segs=24)
        if g.is_empty:
            break
        sl.append(prizma(g, z, z1 + 1e-4))
        z = z1
    return m3d.Manifold.batch_boolean(sl, m3d.OpType.Add), vrh


def u_mesh(m):
    mm = m.to_mesh()
    return trimesh.Trimesh(vertices=np.array(mm.vert_properties)[:, :3], faces=np.array(mm.tri_verts), process=True)


def main():
    o = obris()
    rupa = otvor()
    assert o.contains(rupa) and o.exterior.distance(rupa) >= P['materijal_oko_otvora'] - 1e-6, \
        f'oko otvora samo {o.exterior.distance(rupa):.2f} mm materijala'

    lp, lm = logo()
    F_traka_pl = traka(o, P['traka_sprijeda_od_ruba'], P['traka_sprijeda_plava'], P['traka_sirina'])
    F_traka_mg = traka(o, P['traka_sprijeda_od_ruba'], P['traka_sprijeda_magenta'], P['traka_sirina'])
    # straga: crta se gledano straga pa zrcali
    os_ = zrcali(o)
    B_pl = zrcali(unary_union([nfc_lukovi(),
                               traka(os_, P['traka_straga_od_ruba'], P['traka_straga_plava_gore'], P['traka_sirina']),
                               traka(os_, P['traka_straga_od_ruba'], P['traka_straga_plava_dolje'], P['traka_sirina'])]))
    B_mg = zrcali(unary_union([tekst(), traka(os_, P['traka_straga_od_ruba'], P['traka_straga_magenta'], P['traka_sirina'])]))

    # sve oznake moraju biti dalje od otvora i unutar obrisa
    zab = rupa.buffer(P['razmak_od_otvora'])
    F_traka_pl, F_traka_mg = F_traka_pl.difference(zab), F_traka_mg.difference(zab)
    B_pl, B_mg = B_pl.difference(zab), B_mg.difference(zab)
    for ime, g in (('logo', unary_union([lp, lm])), ('stražnji znak', unary_union([B_pl, B_mg]))):
        assert o.buffer(-1.0).contains(g), f'{ime} izlazi preblizu rubu'
    assert lp.distance(lm) > 0.3 and F_traka_pl.distance(lm) > 1 and F_traka_mg.distance(lp) > 1
    assert B_pl.distance(B_mg) > 0.3

    print('gradim tijelo …')
    tij, vrh = tijelo(o)
    tij = tij - prizma(rupa)
    dub = P['dubina_boje']
    gore_koza = tij - tij.translate((0, 0, -dub))          # sloj ispod gornje plohe
    dolje_koza = tij - tij.translate((0, 0, dub))          # sloj iznad donje plohe
    izboc = tij.translate((0, 0, P['izbocenje_loga'])) - tij  # ljuska iznad gornje plohe (za izdignuti logo)

    plavo = (prizma(lp) ^ (gore_koza + izboc)) + (prizma(F_traka_pl) ^ gore_koza) + (prizma(B_pl) ^ dolje_koza)
    magenta = (prizma(lm) ^ (gore_koza + izboc)) + (prizma(F_traka_mg) ^ gore_koza) + (prizma(B_mg) ^ dolje_koza)
    crno = tij - plavo - magenta

    dijelovi3d = [('crno', crno, 'crno'), ('svjetloplavo', plavo, 'svjetloplavo'), ('magenta', magenta, 'magenta')]
    preklop = (crno ^ plavo).volume() + (crno ^ magenta).volume() + (plavo ^ magenta).volume()
    assert preklop < 1e-3, preklop

    os.makedirs(os.path.join(AQ, 'stl'), exist_ok=True)
    mreze = []
    izv = {'dijelovi': {}}
    for ime, m, b in dijelovi3d:
        t = u_mesh(m)
        assert t.is_watertight and t.is_winding_consistent, ime
        t.export(os.path.join(AQ, 'stl', f'plan-a-privjesak-v4-{ime}.stl'))
        mreze.append((ime, t, b))
        izv['dijelovi'][ime] = {'volumen_mm3': round(t.volume, 1), 'trokuta': len(t.faces), 'zatvoren': True}
    # spremi_3mf iz privjesak.py koristi svoje BOJE (crno, svjetloplavo, magenta)
    _B.update(BOJE)
    spremi_3mf(os.path.join(AQ, 'plan-a-privjesak-v4.3mf'), mreze, 'Plan A privjesak v4')

    sve = trimesh.util.concatenate([t for _, t, _ in mreze])
    izv['mjere_mm'] = [round(v, 2) for v in sve.extents]
    izv['preklapanje_mm3'] = round(preklop, 6)
    izv['materijal_oko_otvora_mm'] = round(o.exterior.distance(rupa), 2)
    izv['otvor_sirina_mm'] = round(rupa.bounds[2] - rupa.bounds[0], 2)
    izv['logo_sirina_mm'] = round(unary_union([lp, lm]).bounds[2] - unary_union([lp, lm]).bounds[0], 2)
    # najtanji dio ušice (debljina tijela na mjestu ušice)
    izv['debljina_usice_mm'] = round(zs_usice(o, rupa), 2)
    json.dump(izv, open(os.path.join(AQ, 'provjere.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    print(json.dumps(izv, ensure_ascii=False, indent=1))


def zs_usice(o, rupa):
    """Debljina tijela uz otvor (najmanja udaljenost od ruba oko otvora → visina profila)."""
    d = o.exterior.distance(rupa)
    zs, R, D, L = P['visina_boka'], P['polumjer_ruba'], P['kupola'], P['kupola_sirina']
    if d < R:
        return zs + R * np.sqrt(1 - (1 - d / R) ** 2)
    w = min((d - R) / L, 1)
    return zs + R + D * (1 - (1 - w) ** 2)


if __name__ == '__main__':
    main()
