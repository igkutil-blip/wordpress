#!/usr/bin/env python3
"""Plan A – NFC privjesak za 3D ispis (verzija 2, prema paketu za modeliranje).

Pokretanje:  pip install trimesh shapely manifold3d numpy matplotlib
             python3 privjesak.py
Ulaz:  logo/logo-jednostavni.svg (dvobojni znak, 30 mm)
Izlaz (u istoj mapi):
  plan-a-privjesak-visebojni.3mf  – jedan predmet, 3 dijela/materijala (AMS, MMU …)
  stl/                            – ista 3 tijela kao STL, isto ishodište (poravnata)
  ploce/                          – „ispis po bojama”: svaka boja svoja ploča, pa se sastavi
  pregled-*.png                   – prednja, stražnja strana, presjek i ploče
Sve mjere su u milimetrima; mijenjaju se u odjeljku MJERE.
"""
import os
import re
import zipfile
import numpy as np
from shapely.geometry import Polygon, Point, box
from shapely.geometry.polygon import orient
from shapely.ops import unary_union
from shapely import affinity
import manifold3d as m3d
import trimesh
from matplotlib.textpath import TextPath
from matplotlib.font_manager import FontProperties

# ---------------- MJERE ----------------
VISINA = 48.0            # visina tijela
SIRINA = 38.0            # širina tijela
DEBLJINA = 6.0           # ukupna debljina osnovnog tijela (bez izbočenog znaka)
ZAOBLJENJE = 1.8         # zaobljenje vanjskog ruba (prednja i stražnja strana)
RAZMAK = 3.0             # najmanji razmak znaka do ruba i do otvora; materijal oko otvora

OTVOR_SIRINA = 5.0       # slobodna širina otvora za vezicu (zaobljeni trokut)

UMETAK_U_BAZI = 0.4      # prednji znak: 0,4 mm u tijelu …
UMETAK_IZNAD = 0.4       # … i 0,4 mm iznad plohe (ukupno 0,8 mm)
STRAGA_DUBINA = 0.8      # poleđina: NFC znak i natpis ravno s plohom (da leži ravno na podlozi)

LINIJA_SIRINA = 1.2      # bočne ukrasne linije: visina na rubu
LINIJA_DUBINA = 0.8      # … i dubina u rub
LINIJA_PREKID = 2.0      # prekid između plave i magenta linije (gore i dolje)

# NFC – za probni ispis isključen (puno tijelo, bez pauze). True = šupljina za čip.
NFC = False
# OBAVEZNO upisati izmjereni čip (ovo je samo pretpostavka 25 mm)
NFC_PROMJER = 25.0
NFC_DEBLJINA = 0.8
NFC_ZAZOR_PROMJER = 0.4  # šupljina = promjer + 0,4
NFC_ZAZOR_VISINA = 0.2   # šupljina = debljina + 0,2
NFC_OD_STRAGE = 1.2      # plastike između čipa i poleđine (strana očitanja); sloj 0,2 → višekratnik 0,2

ZAZOR_SASTAVLJANJE = 0.1  # umeci za lijepljenje su sa svake strane 0,1 mm manji

BOJE = {
    'crno': '#1A1A1A',
    'svjetloplavo': '#00B9E8',
    'magenta': '#D4145A',
}
OUT = os.path.dirname(os.path.abspath(__file__))


# ---------------- 2D ----------------
def catmull_rom(tocke, n=24):
    p = np.array(tocke, float)
    k = len(p)
    rez = []
    for i in range(k):
        p0, p1, p2, p3 = p[i - 1], p[i], p[(i + 1) % k], p[(i + 2) % k]
        for t in np.linspace(0, 1, n, endpoint=False):
            t2, t3 = t * t, t * t * t
            rez.append(0.5 * ((2 * p1) + (-p0 + p2) * t + (2 * p0 - 5 * p1 + 4 * p2 - p3) * t2
                              + (-p0 + 3 * p1 - 3 * p2 + p3) * t3))
    return np.array(rez)


def obris():
    """Asimetrični oblutak/kapljica: šire dolje, zaobljeni vrh s otvorom malo lijevo od sredine."""
    tocke = [(-2, 24), (3.5, 22.8), (9.5, 19), (15.5, 12.5), (18.7, 3.5), (18.6, -7.5), (16.2, -16.8),
             (9.5, -22.6), (0.5, -24.2), (-8.8, -23.2), (-15.8, -18.6), (-19, -9.5), (-18.6, 1.5),
             (-15.8, 10.5), (-10, 18.2), (-5.6, 22.6)]
    g = Polygon(catmull_rom(tocke)).buffer(0)
    minx, miny, maxx, maxy = g.bounds
    g = affinity.scale(g, SIRINA / (maxx - minx), VISINA / (maxy - miny), origin=((minx + maxx) / 2, (miny + maxy) / 2))
    minx, miny, maxx, maxy = g.bounds
    return affinity.translate(g, -(minx + maxx) / 2, -(miny + maxy) / 2).simplify(0.005)


def otvor(o):
    """Zaobljeni trokut (vrh gore, kao vrh privjeska); spušta se dok oko njega nema RAZMAK materijala."""
    r = 1.1
    s = OTVOR_SIRINA + 2 * r * (np.sqrt(3) - 1) + 0.6     # stranica tako da je slobodna širina ≈ OTVOR_SIRINA
    h = s * np.sqrt(3) / 2
    tri = Polygon([(-s / 2, -h / 3), (s / 2, -h / 3), (0, 2 * h / 3)])
    tri = tri.buffer(-r, quad_segs=24).buffer(r, quad_segs=24)
    vrh_x = -2.0 * SIRINA / 38
    for y in np.arange(VISINA / 2, 0, -0.1):
        t = affinity.translate(tri, vrh_x, y)
        if o.exterior.distance(t) >= RAZMAK and o.contains(t):
            return t
    raise RuntimeError('otvor ne stane')


def svg_logo(put):
    """Čita logo-jednostavni.svg (samo M/L/Z putanje) → (plavo, magenta) u mm, y prema gore."""
    s = open(put, encoding='utf-8').read()
    vb = [float(v) for v in re.search(r'viewBox="([^"]+)"', s).group(1).split()]
    plavo, magenta = [], []
    for boja, d in re.findall(r'<path[^>]*fill="(#[0-9A-Fa-f]{6})"[^>]*d="([^"]+)"', s):
        br = [float(v) for v in re.findall(r'-?\d+\.?\d*', d)]
        pts = [(br[i], vb[3] - br[i + 1]) for i in range(0, len(br), 2)]
        (plavo if boja.upper() == '#00B9E8' else magenta).append(Polygon(pts))
    return unary_union(plavo), unary_union(magenta)


def smjesti(geoms, slobodno, max_mjerilo=1.0, cilj=(0, 0)):
    """Najveće mjerilo (≤ max) i položaj najbliži cilju tako da cijeli znak stane u 'slobodno'."""
    sve = unary_union(geoms)
    minx, miny, maxx, maxy = sve.bounds
    cx, cy = (minx + maxx) / 2, (miny + maxy) / 2
    for m in np.arange(max_mjerilo, 0.3, -0.01):
        sc = affinity.scale(sve, m, m, origin=(cx, cy))
        pomaci = sorted(((dx, dy) for dx in np.arange(-8, 8.01, 0.25) for dy in np.arange(-10, 10.01, 0.25)),
                        key=lambda p: (p[0] - cilj[0]) ** 2 + (p[1] - cilj[1]) ** 2)
        for dx, dy in pomaci:
            if slobodno.contains(affinity.translate(sc, dx - cx, dy - cy)):
                return [affinity.translate(affinity.scale(g, m, m, origin=(cx, cy)), dx - cx, dy - cy) for g in geoms], m
    raise RuntimeError('znak ne stane')


def tekst(s, visina):
    fp = FontProperties(family='DejaVu Sans', weight='bold')
    rez = Polygon()
    for verts in TextPath((0, 0), s, size=visina, prop=fp).to_polygons():
        if len(verts) >= 3:
            rez = rez.symmetric_difference(Polygon(verts).buffer(0))
    minx, miny, maxx, maxy = rez.bounds
    return affinity.translate(rez, -(minx + maxx) / 2, -(miny + maxy) / 2)


def nfc_znak():
    """Simbol beskontaktnog očitanja: točka + tri luka."""
    dijelovi = [Point(0, 0).buffer(1.25, quad_segs=16)]
    klin = Polygon([(0, 0), (14, 11), (14, -11)])
    for r in (3.6, 6.3, 9.0):
        luk = Point(0, 0).buffer(r + 0.7).difference(Point(0, 0).buffer(r - 0.7))
        dijelovi.append(luk.intersection(klin).buffer(0.35).buffer(-0.35))
    g = unary_union(dijelovi)
    minx, miny, maxx, maxy = g.bounds
    return affinity.translate(g, -(minx + maxx) / 2, -(miny + maxy) / 2)


def zrcali(g):
    return affinity.scale(g, -1, 1, origin=(0, 0))


# ---------------- 3D ----------------
def cs(g):
    konture = []
    for p in getattr(g, 'geoms', [g]):
        if p.is_empty:
            continue
        p = orient(p, 1.0)
        konture.append(np.array(p.exterior.coords[:-1]))
        konture += [np.array(h.coords[:-1]) for h in p.interiors]
    return m3d.CrossSection(konture, m3d.FillRule.EvenOdd)


def ekstrudiraj(g, z0, h):
    return m3d.Manifold.extrude(cs(g), h).translate((0, 0, z0))


def tijelo_3d(o):
    """Ravna lica, rub zaobljen polumjerom ZAOBLJENJE (stepenice 0,1 mm – finije od sloja)."""
    r, n = ZAOBLJENJE, 18
    dijelovi = [ekstrudiraj(o, r, DEBLJINA - 2 * r)]
    for k in range(n):
        z = r * k / n
        zs = z + r / (2 * n)
        g = o.buffer(-(r - np.sqrt(max(r * r - (r - zs) ** 2, 0))), quad_segs=24)
        dijelovi.append(ekstrudiraj(g, z, r / n + 1e-4))
        dijelovi.append(ekstrudiraj(g, DEBLJINA - z - r / n - 1e-4, r / n + 1e-4))
    return m3d.Manifold.batch_boolean(dijelovi, m3d.OpType.Add)


def u_mesh(m):
    mm = m.to_mesh()
    return trimesh.Trimesh(vertices=np.array(mm.vert_properties)[:, :3], faces=np.array(mm.tri_verts), process=True)


def spremi_3mf(put, dijelovi, ime_predmeta, pomak=(0, 0, 0)):
    """Jedan predmet od više dijelova, svaki sa svojim materijalom (PrusaSlicer / Bambu / Orca)."""
    mat = ''.join(f'<base name="{ime}" displaycolor="{BOJE[boja]}"/>' for ime, _, boja in dijelovi)
    objekti, komp = [], []
    for i, (ime, t, _) in enumerate(dijelovi):
        v = ''.join(f'<vertex x="{x + pomak[0]:.4f}" y="{y + pomak[1]:.4f}" z="{z + pomak[2]:.4f}"/>' for x, y, z in t.vertices)
        f = ''.join(f'<triangle v1="{a}" v2="{b}" v3="{c}"/>' for a, b, c in t.faces)
        objekti.append(f'<object id="{i + 2}" name="{ime}" type="model" pid="1" pindex="{i}">'
                       f'<mesh><vertices>{v}</vertices><triangles>{f}</triangles></mesh></object>')
        komp.append(f'<component objectid="{i + 2}"/>')
    nid = len(dijelovi) + 2
    model = ('<?xml version="1.0" encoding="UTF-8"?>'
             '<model unit="millimeter" xml:lang="hr-HR" xmlns="http://schemas.microsoft.com/3dmanufacturing/core/2015/02">'
             f'<metadata name="Title">{ime_predmeta}</metadata>'
             f'<resources><basematerials id="1">{mat}</basematerials>{"".join(objekti)}'
             f'<object id="{nid}" name="{ime_predmeta}" type="model"><components>{"".join(komp)}</components></object>'
             f'</resources><build><item objectid="{nid}" transform="1 0 0 0 1 0 0 0 1 128 128 0"/></build></model>')
    with zipfile.ZipFile(put, 'w', zipfile.ZIP_DEFLATED) as z:
        z.writestr('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="model" ContentType="application/vnd.ms-package.3dmanufacturing-3dmodel+xml"/></Types>')
        z.writestr('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Target="/3D/3dmodel.model" Id="rel0" Type="http://schemas.microsoft.com/3dmanufacturing/2013/01/3dmodel"/></Relationships>')
        z.writestr('3D/3dmodel.model', model)


def slozi_plocu(komadi, sirina=110, razmak=3.0):
    """Komade (2D oblik, debljina) složi u redove na ploču; vraća manifold i popis položaja."""
    x = y = red_h = 0.0
    rez, polozaji = [], []
    for g, h in sorted(komadi, key=lambda k: -(k[0].bounds[3] - k[0].bounds[1])):
        minx, miny, maxx, maxy = g.bounds
        w, v = maxx - minx, maxy - miny
        if x > 0 and x + w > sirina:
            x, y, red_h = 0.0, y + red_h + razmak, 0.0
        gg = affinity.translate(g, x - minx, y - miny)
        rez.append(ekstrudiraj(gg, 0, h))
        polozaji.append(gg)
        x += w + razmak
        red_h = max(red_h, v)
    m = m3d.Manifold.batch_boolean(rez, m3d.OpType.Add)
    (minx, miny, _), (maxx, maxy, _) = m.bounding_box()[:3], m.bounding_box()[3:]
    return m.translate((-(minx + maxx) / 2, -(miny + maxy) / 2, 0)), polozaji


def main():
    o = obris()
    rupa = otvor(o)
    lice = o.buffer(-ZAOBLJENJE, quad_segs=24)           # ravni dio lica
    slobodno = o.buffer(-RAZMAK, quad_segs=24).difference(rupa.buffer(RAZMAK, quad_segs=24))

    # --- prednja strana: logo iz SVG-a ---
    plavo_svg, magenta_svg = svg_logo(os.path.join(OUT, 'logo', 'logo-jednostavni.svg'))
    (logo_plavo, logo_magenta), mjerilo = smjesti([plavo_svg, magenta_svg], slobodno, cilj=(0, -3))

    # --- poleđina: NFC znak + PLAN A (gledano straga, zato zrcaljeno po x) ---
    slobodno_straga = zrcali(slobodno)
    (znak, natpis), mj_straga = smjesti([affinity.translate(nfc_znak(), 0, 4.5),
                                         affinity.translate(tekst('PLAN A', 5.2), 0, -8.5)],
                                        slobodno_straga, cilj=(0, -2))
    znak, natpis = zrcali(znak), zrcali(natpis)

    # --- bočne linije: plava lijevo, magenta desno (gledano sprijeda) ---
    prsten = o.difference(o.buffer(-LINIJA_DUBINA, quad_segs=24))
    rupa_x = rupa.centroid.x
    vrh = Point(rupa_x, 30)
    dno = Point(o.centroid.x, -30)
    lijevo_polje = Polygon([(-60, -60), (dno.x - LINIJA_PREKID / 2, -60), (dno.x - LINIJA_PREKID / 2, 0),
                            (vrh.x - LINIJA_PREKID / 2, 0), (vrh.x - LINIJA_PREKID / 2, 60), (-60, 60)])
    desno_polje = Polygon([(60, -60), (dno.x + LINIJA_PREKID / 2, -60), (dno.x + LINIJA_PREKID / 2, 0),
                           (vrh.x + LINIJA_PREKID / 2, 0), (vrh.x + LINIJA_PREKID / 2, 60), (60, 60)])
    linija_l = prsten.intersection(lijevo_polje)
    linija_d = prsten.intersection(desno_polje)
    z_linija = (DEBLJINA - LINIJA_SIRINA) / 2

    # --- NFC šupljina ---
    nfc_r = (NFC_PROMJER + NFC_ZAZOR_PROMJER) / 2
    nfc_h = NFC_DEBLJINA + NFC_ZAZOR_VISINA
    nfc_c = (0.0, -2.5)
    nfc_g = Point(*nfc_c).buffer(nfc_r, quad_segs=64)
    nfc_z0 = NFC_OD_STRAGE
    nfc_z1 = nfc_z0 + nfc_h
    provjere = {
        'NFC ne siječe otvor': nfc_g.distance(rupa) >= 1.5,
        'NFC unutar tijela (≥1,5 mm do bočne linije)': o.buffer(-(LINIJA_DUBINA + 1.5)).contains(nfc_g),
        'NFC ispod prednjeg umetka': nfc_z1 <= DEBLJINA - UMETAK_U_BAZI - 1.0,
        'NFC iznad stražnjeg umetka': nfc_z0 >= STRAGA_DUBINA + 0.4 - 1e-9,
    }
    for k, v in provjere.items():
        assert v, k

    # --- tijela (bez preklapanja, zajedničke dodirne plohe) ---
    gore = DEBLJINA
    prednje_pl = ekstrudiraj(logo_plavo, gore - UMETAK_U_BAZI, UMETAK_U_BAZI + UMETAK_IZNAD)
    prednje_mg = ekstrudiraj(logo_magenta, gore - UMETAK_U_BAZI, UMETAK_U_BAZI + UMETAK_IZNAD)
    straga_pl = ekstrudiraj(znak, 0, STRAGA_DUBINA)
    straga_mg = ekstrudiraj(natpis, 0, STRAGA_DUBINA)
    bok_pl = ekstrudiraj(linija_l, z_linija, LINIJA_SIRINA)
    bok_mg = ekstrudiraj(linija_d, z_linija, LINIJA_SIRINA)

    tijelo = tijelo_3d(o)
    tijelo -= ekstrudiraj(rupa, -1, DEBLJINA + 2)
    if NFC:
        tijelo -= ekstrudiraj(nfc_g, nfc_z0, nfc_h)
    for m in (prednje_pl, prednje_mg, straga_pl, straga_mg, bok_pl, bok_mg):
        tijelo -= m
    plavo = prednje_pl + straga_pl + bok_pl
    magenta = prednje_mg + straga_mg + bok_mg

    dijelovi = [('tijelo-crno', u_mesh(tijelo), 'crno'),
                ('svjetloplavo', u_mesh(plavo), 'svjetloplavo'),
                ('magenta', u_mesh(magenta), 'magenta')]
    os.makedirs(os.path.join(OUT, 'stl'), exist_ok=True)
    for ime, t, _ in dijelovi:
        assert t.is_watertight and t.is_winding_consistent, ime
        t.export(os.path.join(OUT, 'stl', f'plan-a-privjesak-{ime}.stl'))
    preklop = (tijelo ^ plavo).volume() + (tijelo ^ magenta).volume() + (plavo ^ magenta).volume()
    assert preklop < 1e-3, f'preklapanje volumena {preklop}'
    spremi_3mf(os.path.join(OUT, 'plan-a-privjesak-visebojni.3mf'), dijelovi, 'Plan A NFC privjesak')

    # --- ispis po bojama (sastavljanje) ---
    z = ZAZOR_SASTAVLJANJE
    os.makedirs(os.path.join(OUT, 'ploce'), exist_ok=True)
    tijelo_t = dijelovi[0][1]
    tijelo_t.export(os.path.join(OUT, 'ploce', 'ploca-1-crno-tijelo.stl'))

    def umeci(prednji, straznji, bocni):
        komadi = []
        for g in getattr(prednji, 'geoms', [prednji]):
            komadi.append((g.buffer(-z, join_style='mitre'), UMETAK_U_BAZI + UMETAK_IZNAD))
        for g in getattr(zrcali(straznji), 'geoms', [zrcali(straznji)]):   # čitljivo, kako se vidi straga
            komadi.append((g.buffer(-z, join_style='mitre'), STRAGA_DUBINA))
        return komadi

    def bocna_traka(linija):
        """Bočna linija za umetanje: ravna traka (ispis položena), savitljiva, gura se u žlijeb."""
        duljina = linija.exterior.length / 2 - LINIJA_DUBINA
        return box(0, 0, duljina - 1.0, LINIJA_SIRINA - 2 * z), LINIJA_DUBINA - z

    ploce = {}
    for broj, ime, pred, stra, lin in ((2, 'svjetloplavo', logo_plavo, znak, linija_l),
                                       (3, 'magenta', logo_magenta, natpis, linija_d)):
        komadi = umeci(pred, stra, lin)
        komadi.append(bocna_traka(lin))
        m, polozaji = slozi_plocu(komadi)
        t = u_mesh(m)
        assert t.is_watertight
        t.export(os.path.join(OUT, 'ploce', f'ploca-{broj}-{ime}.stl'))
        ploce[ime] = polozaji

    print(f'logo: mjerilo {mjerilo:.2f} → širina {logo_plavo.union(logo_magenta).bounds[2] - logo_plavo.union(logo_magenta).bounds[0]:.1f} mm')
    print(f'poleđina: mjerilo {mj_straga:.2f}')
    print(f'otvor: širina {rupa.bounds[2] - rupa.bounds[0]:.2f} mm, materijal do ruba {o.exterior.distance(rupa):.2f} mm')
    if NFC:
        print(f'NFC šupljina Ø{2 * nfc_r:.1f} × {nfc_h:.1f} mm, z {nfc_z0:.1f}–{nfc_z1:.1f} → PAUZA na {nfc_z1:.1f} mm')
    for ime, t, _ in dijelovi:
        print(f'{ime:14s} {t.volume / 1000:5.2f} cm³  {t.extents.round(2)}')
    pregled(o, rupa, logo_plavo, logo_magenta, znak, natpis, nfc_g, linija_l, linija_d, z_linija, ploce)


def pregled(o, rupa, lp, lm, znak, natpis, nfc_g, linija_l, linija_d, z_linija, ploce):
    import matplotlib
    matplotlib.use('Agg')
    import matplotlib.pyplot as plt
    from matplotlib.patches import PathPatch
    from matplotlib.path import Path as MPath

    def crtaj(ax, g, boja, **kw):
        for p in getattr(g, 'geoms', [g]):
            if p.is_empty or p.geom_type != 'Polygon':
                continue
            verts, codes = [], []
            for ring in [p.exterior, *p.interiors]:
                c = np.array(ring.coords)
                verts += list(c)
                codes += [MPath.MOVETO] + [MPath.LINETO] * (len(c) - 2) + [MPath.CLOSEPOLY]
            ax.add_patch(PathPatch(MPath(verts, codes), fc=boja, ec=kw.pop('ec', 'none'), **kw))

    fig, axs = plt.subplots(1, 3, figsize=(13, 5.6), dpi=150, gridspec_kw={'width_ratios': [1, 1, 0.45]})
    for ax, naslov, sl in ((axs[0], 'sprijeda', [(lp, 'svjetloplavo'), (lm, 'magenta')]),
                           (axs[1], 'straga', [(znak, 'svjetloplavo'), (natpis, 'magenta')])):
        f = (lambda g: g) if naslov == 'sprijeda' else zrcali
        crtaj(ax, f(o.difference(rupa)), BOJE['crno'])
        crtaj(ax, f(linija_l), BOJE['svjetloplavo'])
        crtaj(ax, f(linija_d), BOJE['magenta'])
        crtaj(ax, f(o.buffer(-ZAOBLJENJE).exterior.buffer(0.08)), '#555555')
        if naslov == 'straga' and NFC:
            c = f(nfc_g)
            ax.add_patch(plt.Circle((c.centroid.x, c.centroid.y), (NFC_PROMJER + NFC_ZAZOR_PROMJER) / 2,
                                    fill=False, ls='--', ec='#9aa', lw=0.8))
        for g, b in sl:
            crtaj(ax, f(g), BOJE[b])
        ax.set_xlim(-22, 22); ax.set_ylim(-26, 26); ax.set_aspect('equal'); ax.grid(alpha=0.25)
        ax.set_title(f'{naslov} (mm)', fontsize=10)
    # bočni presjek (lijevi rub, gledano slijeva)
    ax = axs[2]
    y0, y1 = o.bounds[1], o.bounds[3]
    ax.add_patch(plt.Rectangle((0, y0), DEBLJINA, y1 - y0, fc=BOJE['crno']))
    ax.add_patch(plt.Rectangle((z_linija, y0 + 3), LINIJA_SIRINA, y1 - y0 - 6, fc=BOJE['svjetloplavo']))
    ax.add_patch(plt.Rectangle((DEBLJINA, y0 + 14), UMETAK_IZNAD, 16, fc=BOJE['svjetloplavo']))
    ax.set_xlim(-2, 8.5); ax.set_ylim(-26, 26); ax.set_aspect('equal')
    ax.set_title('bok (pojednostavljeno)', fontsize=10); ax.set_xlabel('debljina (mm)')
    fig.suptitle('Plan A NFC privjesak v2', fontsize=12)
    fig.tight_layout()
    fig.savefig(os.path.join(OUT, 'pregled-privjesak.png'))
    plt.close(fig)

    fig, axs = plt.subplots(1, 2, figsize=(12, 4.2), dpi=150)
    for ax, ime in zip(axs, ('svjetloplavo', 'magenta')):
        for g in ploce[ime]:
            crtaj(ax, g, BOJE[ime])
        ax.set_aspect('equal'); ax.autoscale(); ax.grid(alpha=0.25)
        ax.set_title(f'ploča – {ime} (mm)', fontsize=10)
    fig.tight_layout()
    fig.savefig(os.path.join(OUT, 'pregled-ploce.png'))
    plt.close(fig)


if __name__ == '__main__':
    main()
