#!/usr/bin/env python3
"""Plan A – NFC privjesak za 3D printanje (prema slici „Dodir avanture”).

Pokretanje:  pip install trimesh shapely manifold3d numpy matplotlib
             python3 privjesak.py
Izlaz (u istoj mapi):
  plan-a-privjesak-visebojni.3mf  – svi dijelovi zajedno (AMS / MMU)
  stl/…                            – isti dijelovi kao zasebni STL-ovi
  plan-a-privjesak-jednobojni.stl  – izbočeni logo, za promjenu boje na visini (M600)
  pregled-*.png                    – slike prednje i stražnje strane
Sve mjere su u milimetrima; mijenjaju se u odjeljku MJERE.
"""
import os
import numpy as np
import shapely
from shapely.geometry import Polygon, Point, box
from shapely.ops import unary_union
from shapely import affinity
import manifold3d as m3d
import trimesh
from matplotlib.textpath import TextPath
from matplotlib.font_manager import FontProperties

# ---------------- MJERE ----------------
SIRINA = 42.0          # širina tijela
VISINA = 44.0          # visina tijela (bez ušice)
DEBLJINA = 5.6         # ukupna debljina
ZAOBLJENJE = 1.6       # polumjer zaobljenja ruba (gore i dolje)
INLAY = 0.6            # dubina umetnutih boja (3 sloja po 0,2 mm)
RUPA = 5.2             # promjer rupe za kolutić / uzicu
NFC_PROMJER = 25.6     # NFC naljepnica/disk 25 mm (NTAG213/215) + zazor
NFC_DNO = 1.4          # visina dna udubljenja za NFC
NFC_DUBINA = 1.0       # dubina udubljenja (pauza ispisa na NFC_DNO + NFC_DUBINA)
NFC_POMAK_Y = -1.5     # NFC malo niže od sredine (dalje od ušice)
USICA = (-16.0, 21.5)  # središte ušice s rupom

BOJE = {               # samo za pregled i 3MF
    'tijelo': (0x1b, 0x23, 0x4a),   # tamnoplava
    'plava': (0x29, 0xb6, 0xf0),    # svijetloplava (cyan)
    'roza': (0xe6, 0x1e, 0x6e),     # magenta / roza
}
OUT = os.path.dirname(os.path.abspath(__file__))


# ---------------- 2D OBLICI ----------------
def obris():
    """Zaobljeni „jastučić” (superelipsa) + ušica gore lijevo, spoj zaobljen."""
    t = np.linspace(0, 2 * np.pi, 240, endpoint=False)
    n = 3.2
    a, b = SIRINA / 2, VISINA / 2
    x = a * np.sign(np.cos(t)) * np.abs(np.cos(t)) ** (2 / n)
    y = b * np.sign(np.sin(t)) * np.abs(np.sin(t)) ** (2 / n)
    # dno malo šire od vrha (kao kapljica na slici)
    x = x * (1.0 - 0.06 * (y / b))
    tijelo = Polygon(np.c_[x, y])
    usica = Point(*USICA).buffer(6.0, quad_segs=32)
    return unary_union([tijelo, usica]).buffer(4, quad_segs=32).buffer(-4, quad_segs=32).simplify(0.01)


def rupa():
    return Point(*USICA).buffer(RUPA / 2, quad_segs=32)


def logo():
    """Stilizirani Plan A logo (trokuti) – vraća (plavi, rozi) poligoni."""
    plavo = [
        Polygon([(0, 0), (12.6, 27.5), (12.6, 15.0), (8.0, 10.2), (8.0, 0)]),   # lijeva noga
        Polygon([(9.6, 0), (13.4, 0), (11.5, 3.6)]),                              # mali trokut dolje
        Polygon([(9.6, 8.2), (12.6, 11.2), (12.6, 5.2)]),                         # trokut u zarezu
        Polygon([(-1.8, 12.5), (1.6, 12.5), (-0.1, 9.2)]),                        # mali lijevo
    ]
    rozo = [
        Polygon([(14.2, 30.5), (31.0, 0), (24.4, 0), (14.2, 18.6)]),             # desna noga
        Polygon([(14.2, 16.0), (14.2, 6.5), (20.2, 6.5)]),                        # unutarnji klin
        Polygon([(15.8, 0), (22.6, 0), (19.2, 4.6)]),                             # trokut dolje
        Polygon([(20.4, 31.0), (24.2, 31.0), (22.3, 27.4)]),                      # mali gore desno
        Polygon([(27.6, 13.4), (31.4, 13.4), (29.5, 9.8)]),                       # mali desno
    ]
    def smjesti(lst):
        g = unary_union(lst)
        return affinity.translate(g, -15.2, -14.3)   # centriranje na tijelu
    return smjesti(plavo), smjesti(rozo)


def pruge(o):
    """Zakrivljene pruge uz rub: lijevo plava, desno roza."""
    traka = o.buffer(-2.5, quad_segs=24).difference(o.buffer(-3.7, quad_segs=24))
    lijevo = traka.intersection(Polygon([(-40, -15), (-12, -15), (-12, 3), (-40, 12)]))
    desno = traka.intersection(Polygon([(40, -17), (12, -17), (12, 14), (40, 22)]))
    # zaobljeni krajevi
    lijevo = lijevo.buffer(0.45).buffer(-0.45)
    desno = desno.buffer(0.45).buffer(-0.45)
    return lijevo, desno


def tekst(s, visina, font='DejaVu Sans', bold=True):
    fp = FontProperties(family=font, weight='bold' if bold else 'normal')
    tp = TextPath((0, 0), s, size=visina, prop=fp)
    polys = []
    for verts in tp.to_polygons():
        if len(verts) >= 3:
            polys.append(Polygon(verts).buffer(0))
    # parne/neparne konture → slova s rupama (A)
    rez = Polygon()
    for p in sorted(polys, key=lambda p: -p.area):
        rez = rez.symmetric_difference(p)
    minx, miny, maxx, maxy = rez.bounds
    return affinity.translate(rez, -(minx + maxx) / 2, -(miny + maxy) / 2)


def nfc_znak():
    """Tri luka + točka (simbol za dodir)."""
    dijelovi = [Point(0, 0).buffer(1.3, quad_segs=16)]
    klin = Polygon([(0, 0), (12, 9.5), (12, -9.5)])
    for r in (3.6, 6.3, 9.0):
        luk = Point(0, 0).buffer(r + 0.65).difference(Point(0, 0).buffer(r - 0.65))
        dijelovi.append(luk.intersection(klin).buffer(0.3).buffer(-0.3))
    return affinity.translate(unary_union(dijelovi), -4.0, 0)


def straznja_strana():
    """Za stražnju stranu (gledano straga) – zrcali po x jer model gledamo sprijeda."""
    znak = affinity.translate(nfc_znak(), 0, 5.0)
    natpis = affinity.translate(tekst('PLAN A', 6.0), 0, -9.0)
    return affinity.scale(znak, -1, 1, origin=(0, 0)), affinity.scale(natpis, -1, 1, origin=(0, 0))


# ---------------- 3D ----------------
def cs(g):
    """shapely → manifold CrossSection."""
    konture = []
    geoms = getattr(g, 'geoms', [g])
    for p in geoms:
        if p.is_empty:
            continue
        p = shapely.geometry.polygon.orient(p, 1.0)
        konture.append(np.array(p.exterior.coords[:-1]))
        for h in p.interiors:
            konture.append(np.array(h.coords[:-1]))
    return m3d.CrossSection(konture, m3d.FillRule.EvenOdd)


def ekstrudiraj(g, z0, h):
    return m3d.Manifold.extrude(cs(g), h).translate((0, 0, z0))


def tijelo_3d(o):
    """Zaobljeni rubovi: tanke stepenice od 0,1 mm (finije od sloja ispisa)."""
    r = ZAOBLJENJE
    n = 16
    dijelovi = [ekstrudiraj(o, r, DEBLJINA - 2 * r)]
    for k in range(n):
        z = r * k / n
        zs = z + r / (2 * n)
        uvlaka = r - np.sqrt(max(r * r - (r - zs) ** 2, 0))
        g = o.buffer(-uvlaka, quad_segs=24)
        dijelovi.append(ekstrudiraj(g, z, r / n + 0.0005))
        dijelovi.append(ekstrudiraj(g, DEBLJINA - z - r / n - 0.0005, r / n + 0.0005))
    return m3d.Manifold.batch_boolean(dijelovi, m3d.OpType.Add)


def u_mesh(m, boja):
    mm = m.to_mesh()
    t = trimesh.Trimesh(vertices=np.array(mm.vert_properties)[:, :3], faces=np.array(mm.tri_verts), process=True)
    t.visual.face_colors = list(boja) + [255]
    return t


def spremi_3mf(put, mreze):
    """Jedan objekt sastavljen od dijelova (komponenti), svaki sa svojom bojom.
    PrusaSlicer / Bambu Studio / OrcaSlicer ga učitaju kao jedan predmet s dijelovima."""
    import zipfile
    mat = ''.join(f'<base name="{ime}" displaycolor="#{b[0]:02X}{b[1]:02X}{b[2]:02X}"/>' for ime, _, b in mreze)
    objekti, komponente = [], []
    for i, (ime, t, _) in enumerate(mreze):
        oid = i + 2
        v = ''.join(f'<vertex x="{x:.4f}" y="{y:.4f}" z="{z:.4f}"/>' for x, y, z in t.vertices)
        f = ''.join(f'<triangle v1="{a}" v2="{b}" v3="{c}"/>' for a, b, c in t.faces)
        objekti.append(f'<object id="{oid}" name="{ime}" type="model" pid="1" pindex="{i}">'
                       f'<mesh><vertices>{v}</vertices><triangles>{f}</triangles></mesh></object>')
        komponente.append(f'<component objectid="{oid}"/>')
    nid = len(mreze) + 2
    model = ('<?xml version="1.0" encoding="UTF-8"?>'
             '<model unit="millimeter" xml:lang="hr-HR" xmlns="http://schemas.microsoft.com/3dmanufacturing/core/2015/02">'
             '<metadata name="Title">Plan A NFC privjesak</metadata>'
             f'<resources><basematerials id="1">{mat}</basematerials>{"".join(objekti)}'
             f'<object id="{nid}" name="Plan A privjesak" type="model"><components>{"".join(komponente)}</components></object>'
             f'</resources><build><item objectid="{nid}" transform="1 0 0 0 1 0 0 0 1 100 100 0"/></build></model>')
    ct = ('<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
          '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
          '<Default Extension="model" ContentType="application/vnd.ms-package.3dmanufacturing-3dmodel+xml"/></Types>')
    rels = ('<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            '<Relationship Target="/3D/3dmodel.model" Id="rel0" Type="http://schemas.microsoft.com/3dmanufacturing/2013/01/3dmodel"/></Relationships>')
    with zipfile.ZipFile(put, 'w', zipfile.ZIP_DEFLATED) as z:
        z.writestr('[Content_Types].xml', ct)
        z.writestr('_rels/.rels', rels)
        z.writestr('3D/3dmodel.model', model)


def main():
    o = obris()
    plavi_logo, rozi_logo = logo()
    pruga_l, pruga_d = pruge(o)
    znak, natpis = straznja_strana()

    sprijeda_plavo = unary_union([plavi_logo, pruga_l])
    sprijeda_rozo = unary_union([rozi_logo, pruga_d])
    rupa_g = rupa()
    nfc = Point(0, NFC_POMAK_Y).buffer(NFC_PROMJER / 2, quad_segs=64)

    tijelo = tijelo_3d(o)
    tijelo -= ekstrudiraj(rupa_g, -1, DEBLJINA + 2)
    tijelo -= ekstrudiraj(nfc, NFC_DNO, NFC_DUBINA)
    puno_tijelo = tijelo   # za jednobojnu inačicu

    # --- višebojna inačica: boje umetnute u ravninu (ravna površina) ---
    gore = DEBLJINA - INLAY
    plavo = ekstrudiraj(sprijeda_plavo, gore, INLAY) + ekstrudiraj(znak, 0, INLAY)
    rozo = ekstrudiraj(sprijeda_rozo, gore, INLAY) + ekstrudiraj(natpis, 0, INLAY)
    tijelo_vb = tijelo - plavo - rozo

    os.makedirs(os.path.join(OUT, 'stl'), exist_ok=True)
    mreze = []
    dijelovi = {
        'tijelo-tamnoplavo': (tijelo_vb, BOJE['tijelo']),
        'logo-plavo': (plavo, BOJE['plava']),
        'logo-rozo': (rozo, BOJE['roza']),
    }
    for ime, (m, boja) in dijelovi.items():
        t = u_mesh(m, boja)
        assert t.is_watertight, ime
        t.export(os.path.join(OUT, 'stl', f'plan-a-privjesak-{ime}.stl'))
        mreze.append((ime, t, boja))
        print(f'{ime:20s} volumen {t.volume / 1000:6.2f} cm³')
    spremi_3mf(os.path.join(OUT, 'plan-a-privjesak-visebojni.3mf'), mreze)

    # --- jednobojna inačica: logo izbočen 0,6 mm, straga utisnuto ---
    izbocenje = ekstrudiraj(unary_union([sprijeda_plavo, sprijeda_rozo]), DEBLJINA - 0.01, INLAY + 0.01)
    utisnuto = ekstrudiraj(unary_union([znak, natpis]), -1, 1 + INLAY)
    jb = (puno_tijelo + izbocenje) - utisnuto
    t = u_mesh(jb, BOJE['tijelo'])
    assert t.is_watertight
    t.export(os.path.join(OUT, 'plan-a-privjesak-jednobojni.stl'))
    print(f'jednobojni            volumen {t.volume / 1000:6.2f} cm³')

    pregled(o, rupa_g, nfc, sprijeda_plavo, sprijeda_rozo, znak, natpis)


def pregled(o, rupa_g, nfc, sp_pl, sp_ro, znak, natpis):
    import matplotlib
    matplotlib.use('Agg')
    import matplotlib.pyplot as plt
    from matplotlib.patches import PathPatch
    from matplotlib.path import Path as MPath

    def crtaj(ax, g, boja, **kw):
        for p in getattr(g, 'geoms', [g]):
            if p.is_empty:
                continue
            verts, codes = [], []
            for ring in [p.exterior, *p.interiors]:
                c = np.array(ring.coords)
                verts += list(c)
                codes += [MPath.MOVETO] + [MPath.LINETO] * (len(c) - 2) + [MPath.CLOSEPOLY]
            ax.add_patch(PathPatch(MPath(verts, codes), fc=np.array(boja) / 255, ec='none', **kw))

    for ime, zrcali, slojevi in (
        ('sprijeda', False, [(sp_pl, 'plava'), (sp_ro, 'roza')]),
        ('straga', True, [(znak, 'plava'), (natpis, 'roza')]),
    ):
        fig, ax = plt.subplots(figsize=(5, 5.4), dpi=160)
        sx = -1 if zrcali else 1
        f = lambda g: affinity.scale(g, sx, 1, origin=(0, 0))
        crtaj(ax, f(o.difference(rupa_g)), BOJE['tijelo'])
        if zrcali:
            ax.add_patch(plt.Circle((0, NFC_POMAK_Y), NFC_PROMJER / 2, fill=False, ls='--', ec='#8899bb', lw=0.8))
        for g, b in slojevi:
            crtaj(ax, f(g), BOJE[b])
        ax.set_xlim(-26, 26); ax.set_ylim(-26, 30); ax.set_aspect('equal')
        ax.set_title(f'Plan A privjesak – {ime} (mm)', fontsize=10)
        ax.grid(alpha=0.25)
        fig.tight_layout()
        fig.savefig(os.path.join(OUT, f'pregled-{ime}.png'))
        plt.close(fig)


if __name__ == '__main__':
    main()
