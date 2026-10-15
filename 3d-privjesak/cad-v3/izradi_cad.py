#!/usr/bin/env python3
"""Plan A – NFC privjesak, CAD v3 (6 dijelova, ispis bez potpora).

Konstrukcija (prema uputama iz četverodijelnog CAD prototipa, ali izgled prema fotografiji):
  1 crna prednja polovica  – lice s prorezima za ukrase, vodilice, stupići za središta slova
  2 crna stražnja polovica – lice s prorezima, ležišta vodilica
  3 plavi prednji umetak   – ploča + plavi dio loga + plava rubna traka
  4 magenta prednji umetak – ploča + magenta dio loga + magenta rubna traka (prolazi kroz plavu ploču)
  5 plavi stražnji umetak  – ploča + NFC lukovi + plave rubne trake
  6 magenta stražnji umetak – ploča + natpis PLAN A + magenta rubna traka
Umetci se ulažu iznutra; ukrasi prolaze kroz proreze lica i strše 0,4 mm. Polovice se poravnaju
vodilicama i zalijepe po rubu. Svi dijelovi leže ravno na podlozi – bez potpora.

Koordinate: X desno, Y prema otvoru za vezicu, Z debljina; stražnja ploha Z=0, prednja Z=DEBLJINA.

Pokretanje:  pip install cadquery==2.8.0 shapely numpy matplotlib trimesh
             python3 izradi_cad.py
"""
import json
import os
import sys

import numpy as np
import cadquery as cq
from shapely.geometry import Polygon, Point, LineString, MultiPolygon
from shapely.geometry.polygon import orient
from shapely.ops import unary_union, substring
from shapely import affinity

AQ = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.dirname(AQ))
from privjesak import svg_logo, catmull_rom  # noqa: E402  (isti logo i krivulje kao v2)

P = json.load(open(os.path.join(AQ, 'parametri.json'), encoding='utf-8'))
W, H, T = P['sirina'], P['visina'], P['debljina']
LICE = P['debljina_lica']            # 1,6
ZID = P['debljina_ruba']             # 2,0
R_RUB = P['zaobljenje_ruba']         # 2,5
IZBOC = P['izbocenje_ukrasa']        # 0,4
Z_UKRAS = P['zazor_ukras']           # 0,22 po strani
Z_PLOCA = P['zazor_ploca']           # 0,25 po strani
Z_OS = P['zazor_aksijalno']          # 0,1 po strani (ukupno 0,2)
PLOCA_BLIZU = P['ploca_blizu']       # 0,8
VOD_D = P['vodilica_promjer']        # 2,2
VOD_RUPA = VOD_D + 2 * P['zazor_vodilica']
TRAKA_S = P['sirina_trake']          # 1,4
TRAKA_OD_RUBA = P['traka_od_ruba']   # 3,8 (sredina trake od vanjskog ruba)
MIN_CRNO = P['najmanje_crnog']       # 0,6 mm crnog između dva proreza

SEAM = T / 2
Z_B_LICE = LICE                       # unutarnja ploha stražnjeg lica
Z_F_LICE = T - LICE                   # unutarnja ploha prednjeg lica
# slojevi umetaka (od stražnje prema prednjoj strani)
ZB_BL = (Z_B_LICE, Z_B_LICE + PLOCA_BLIZU)             # plavi stražnji
ZB_DA = (ZB_BL[1], SEAM - Z_OS)                         # magenta stražnji
ZF_DA = (SEAM + Z_OS, Z_F_LICE - PLOCA_BLIZU)           # magenta prednji
ZF_BL = (ZF_DA[1], Z_F_LICE)                            # plavi prednji


# ---------------------------------------------------------------- 2D
def obris():
    """Zaobljeni „oblutak” s ušicom gore lijevo i S-krivuljom desno (prema fotografiji)."""
    t = [(-7.0, 30.0), (-1.5, 29.4), (2.6, 27.0), (6.2, 23.2), (11.5, 21.0), (17.5, 18.4), (21.6, 12.5),
         (23.0, 2.0), (22.8, -11.0), (21.0, -22.5), (15.0, -29.0), (1.0, -30.0), (-13.5, -29.4),
         (-20.6, -24.0), (-22.9, -12.0), (-22.8, 1.0), (-21.4, 11.0), (-18.4, 18.8), (-14.6, 25.4),
         (-11.4, 29.0)]
    g = Polygon(catmull_rom(t, 32)).buffer(0)
    minx, miny, maxx, maxy = g.bounds
    g = affinity.scale(g, W / (maxx - minx), H / (maxy - miny), origin=((minx + maxx) / 2, (miny + maxy) / 2))
    minx, miny, maxx, maxy = g.bounds
    return affinity.translate(g, -(minx + maxx) / 2, -(miny + maxy) / 2).simplify(0.003)


def otvor(o):
    r = P['otvor_zaobljenje']
    s = P['otvor_sirina'] + 2 * r * (np.sqrt(3) - 1) + 0.6
    h = s * np.sqrt(3) / 2
    tri = Polygon([(-s / 2, -h / 3), (s / 2, -h / 3), (0, 2 * h / 3)]).buffer(-r, quad_segs=16).buffer(r, quad_segs=16)
    vrh = max(o.exterior.coords, key=lambda c: c[1])
    for y in np.arange(H / 2, 0, -0.05):
        t = affinity.translate(tri, vrh[0] + 0.3, y)
        if o.contains(t) and o.exterior.distance(t) >= P['materijal_oko_otvora']:
            return t
    raise RuntimeError('otvor ne stane')


def traka(prsten, tocke):
    """Dio krivulje 'prsten' od prve do zadnje točke, kroz srednje (smjer se bira sam)."""
    L = prsten.length
    s = [prsten.project(Point(p)) for p in tocke]
    a, b = s[0], s[-1]
    for smjer in (1, -1):
        def unutra(x):
            if smjer == 1:
                return (x - a) % L <= (b - a) % L
            return (a - x) % L <= (a - b) % L
        if all(unutra(x) for x in s[1:-1]):
            break
    if smjer == 1:
        dijelovi = [substring(prsten, a, b)] if b > a else [substring(prsten, a, L), substring(prsten, 0, b)]
    else:
        dijelovi = [substring(prsten, b, a)] if a > b else [substring(prsten, b, L), substring(prsten, 0, a)]
    crta = unary_union(dijelovi)
    return crta.buffer(TRAKA_S / 2, cap_style='flat', join_style='round', quad_segs=12)


def nfc_lukovi():
    """Mali luk + tri luka (simbol beskontaktnog očitanja), kao na fotografiji."""
    kut = np.radians(P['nfc_kut'])
    dijelovi = []
    for r0, r1 in P['nfc_polumjeri']:
        t = np.linspace(-kut, kut, 64)
        vanj = np.c_[r1 * np.cos(t), r1 * np.sin(t)]
        unu = np.c_[r0 * np.cos(t[::-1]), r0 * np.sin(t[::-1])]
        luk = Polygon(np.vstack([vanj, unu]))
        z = (r1 - r0) / 2 * 0.95
        dijelovi.append(luk.buffer(-z, quad_segs=12).buffer(z, quad_segs=12))
    g = unary_union(dijelovi)
    minx, miny, maxx, maxy = g.bounds
    return affinity.translate(g, -(minx + maxx) / 2, -(miny + maxy) / 2)


def tekst(s, visina):
    from matplotlib.textpath import TextPath
    from matplotlib.font_manager import FontProperties
    fp = FontProperties(fname=P['font'])
    rez = Polygon()
    for v in TextPath((0, 0), s, size=1.0, prop=fp).to_polygons():
        if len(v) >= 3:
            rez = rez.symmetric_difference(Polygon(v).buffer(0))
    minx, miny, maxx, maxy = rez.bounds
    m = visina / (maxy - miny)
    rez = affinity.scale(rez, m, m, origin=(0, 0))
    # razmak između slova (da između proreza ostane dovoljno crnog)
    slova = sorted(dijelovi(rez), key=lambda p: p.bounds[0])
    rez = unary_union([affinity.translate(p, i * P['natpis_razmak'], 0) for i, p in enumerate(slova)])
    minx, miny, maxx, maxy = rez.bounds
    return affinity.translate(rez, -(minx + maxx) / 2, -(miny + maxy) / 2)


def dijelovi(g):
    if g.is_empty:
        return []
    return [p for p in getattr(g, 'geoms', [g]) if p.area > 1e-6]


def razmakni(lista, d):
    """Manji dio se odreže od većeg tako da razmak bude barem d (crni rub između ukrasa)."""
    lista = sorted(lista, key=lambda g: -g.area)
    out = []
    for g in lista:
        for v in out:
            if g.distance(v) < d:
                g = g.difference(v.buffer(d + 0.02, quad_segs=16))
        g = max(dijelovi(g.buffer(0)), key=lambda p: p.area)
        out.append(g)
    return out


def zrcali(g):
    return affinity.scale(g, -1, 1, origin=(0, 0))


def umjesti(g, mjesto, dozvoljeno, ime):
    minx, miny, maxx, maxy = g.bounds
    g = affinity.translate(g, mjesto[0] - (minx + maxx) / 2, mjesto[1] - (miny + maxy) / 2)
    if not dozvoljeno.contains(g):
        raise RuntimeError(f'{ime} ne stane u dopušteno područje (premalo mjesta do ruba)')
    return g


# ---------------------------------------------------------------- 3D
def cq_poligon(g, z0, h):
    """shapely (Multi)Polygon → CadQuery solid ekstrudiran od z0 za h."""
    tijela = []
    for p in dijelovi(g):
        p = orient(p.simplify(0.003, preserve_topology=True), 1.0)   # bez gotovo istih susjednih točaka
        vanj = cq.Wire.makePolygon([cq.Vector(x, y, z0) for x, y in p.exterior.coords[:-1]], close=True)
        unut = [cq.Wire.makePolygon([cq.Vector(x, y, z0) for x, y in r.coords[:-1]], close=True) for r in p.interiors]
        f = cq.Face.makeFromWires(vanj, unut)
        tijela.append(cq.Solid.extrudeLinear(f, cq.Vector(0, 0, h)))
    if not tijela:
        return None
    s = tijela[0]
    for t in tijela[1:]:
        s = s.fuse(t)
    return s


def cq_obris_spline(o, z0, h):
    pts = [cq.Vector(x, y, z0) for x, y in list(o.exterior.coords)[:-1][::3]]
    e = cq.Edge.makeSpline(pts, periodic=True)
    f = cq.Face.makeFromWires(cq.Wire.assembleEdges([e]))
    return cq.Solid.extrudeLinear(f, cq.Vector(0, 0, h))


def zaobli(s, z, r):
    """Zaobljuje vanjski rub na visini z (samo bridove koji leže na toj ravnini)."""
    wp = cq.Workplane().add(s)
    return wp.edges(cq.selectors.BoxSelector((-100, -100, z - 1e-3), (100, 100, z + 1e-3))).fillet(r).val()


def skosi(s, z, c):
    wp = cq.Workplane().add(s)
    return wp.edges(cq.selectors.BoxSelector((-100, -100, z - 1e-3), (100, 100, z + 1e-3))).chamfer(c).val()


def main():
    o = obris()
    rupa = otvor(o)
    supljina = o.buffer(-ZID, quad_segs=16).difference(rupa.buffer(ZID, quad_segs=16))
    supljina = max(dijelovi(supljina), key=lambda p: p.area)
    ravno_lice = o.buffer(-R_RUB, quad_segs=16)
    # ukras (s prorezom) mora biti na ravnom dijelu lica i iznad šupljine
    dozvoljeno = ravno_lice.intersection(supljina.buffer(-(Z_UKRAS + 0.3)))

    prsten = o.buffer(-TRAKA_OD_RUBA, quad_segs=24).exterior
    def traka_ok(tocke):
        t = traka(prsten, tocke).intersection(dozvoljeno.buffer(-0.05))
        t = t.difference(rupa.buffer(ZID + 1.2))
        return max(dijelovi(t), key=lambda p: p.area)

    # ---- prednja strana (gledano sprijeda) ----
    lp, lm = svg_logo(os.path.join(os.path.dirname(AQ), 'logo', 'logo-jednostavni.svg'))
    m = P['logo_sirina'] / 30.0
    lp, lm = affinity.scale(lp, m, m, origin=(0, 0)), affinity.scale(lm, m, m, origin=(0, 0))
    sve = unary_union([lp, lm])
    minx, miny, maxx, maxy = sve.bounds
    dx, dy = P['logo_mjesto'][0] - (minx + maxx) / 2, P['logo_mjesto'][1] - (miny + maxy) / 2
    lp, lm = affinity.translate(lp, dx, dy), affinity.translate(lm, dx, dy)
    d_min = MIN_CRNO + 2 * Z_UKRAS
    komadi = [('plavo', g) for g in dijelovi(lp)] + [('magenta', g) for g in dijelovi(lm)]
    razm = razmakni([g for _, g in komadi], d_min)
    lp = unary_union([g for g in razm if any(g.intersects(x) for x in dijelovi(lp))])
    lm = unary_union([g for g in razm if any(g.intersects(x) for x in dijelovi(lm))])
    if not dozvoljeno.contains(unary_union([lp, lm])):
        raise RuntimeError('logo ne stane')

    F_pl_traka = traka_ok(P['traka_sprijeda_plava'])
    F_mg_traka = traka_ok(P['traka_sprijeda_magenta'])
    F_plavo = unary_union([lp, F_pl_traka])
    F_magenta = unary_union([lm, F_mg_traka])

    # ---- stražnja strana: crta se gledano straga, zatim zrcali (model gledamo sprijeda) ----
    dozv_straga = zrcali(dozvoljeno)
    prsten_s = zrcali(o).buffer(-TRAKA_OD_RUBA, quad_segs=24).exterior
    def traka_s(tocke):
        t = traka(prsten_s, tocke).intersection(dozv_straga.buffer(-0.05))
        t = t.difference(zrcali(rupa).buffer(ZID + 1.2))
        return max(dijelovi(t), key=lambda p: p.area)
    luk = umjesti(nfc_lukovi(), P['nfc_mjesto'], dozv_straga, 'NFC znak')
    natpis = umjesti(tekst('PLAN A', P['natpis_visina']), P['natpis_mjesto'], dozv_straga, 'natpis')
    B_plavo = zrcali(unary_union([luk, traka_s(P['traka_straga_plava_gore']), traka_s(P['traka_straga_plava_dolje'])]))
    B_magenta = zrcali(unary_union([natpis, traka_s(P['traka_straga_magenta'])]))

    # provjere razmaka između ukrasa (crni rub ≥ MIN_CRNO)
    for ime, g in (('sprijeda', dijelovi(F_plavo) + dijelovi(F_magenta)), ('straga', dijelovi(B_plavo) + dijelovi(B_magenta))):
        for i in range(len(g)):
            for j in range(i + 1, len(g)):
                d = g[i].distance(g[j])
                assert d >= d_min - 0.01, f'{ime}: ukrasi preblizu ({d:.2f} mm)'

    # ---- središta slova (crni otočići) → stupići s prednje polovice ----
    otocici = []
    for p in dijelovi(B_magenta):
        for r in p.interiors:
            otok = Polygon(r).buffer(-Z_UKRAS, join_style='mitre')
            if not otok.is_empty and otok.area > 0.1:
                otocici.append(otok)
    stupici = unary_union(otocici) if otocici else Polygon()

    # ---- vodilice: dvije, daleko jedna od druge, dalje od svih ukrasa ----
    zauzeto = unary_union([F_plavo, F_magenta, B_plavo, B_magenta, stupici]).buffer(VOD_RUPA / 2 + Z_UKRAS + 0.9)
    slobodno = supljina.buffer(-(VOD_RUPA / 2 + 1.0)).difference(zauzeto)
    kand = [Point(x, y) for x in np.arange(-22, 22, 0.5) for y in np.arange(-28, 28, 0.5)
            if slobodno.contains(Point(x, y))]
    if len(kand) < 2:
        raise RuntimeError('nema mjesta za vodilice')
    a = max(kand, key=lambda p: p.x + p.y)                      # gore desno
    b = max(kand, key=lambda p: p.distance(a))                  # što dalje od prve
    vodilice = [a, b]

    # ---- ploče umetaka ----
    rupe_vod = unary_union([v.buffer(VOD_RUPA / 2, quad_segs=24) for v in vodilice])
    rupe_stup = stupici.buffer(Z_PLOCA, join_style='mitre') if otocici else Polygon()
    baza = supljina.buffer(-Z_PLOCA, quad_segs=16).difference(rupe_vod).difference(rupe_stup)

    def ploca(dodatne_rupe, ukrasi):
        g = baza.difference(dodatne_rupe) if dodatne_rupe is not None else baza
        g = g.buffer(-0.4, quad_segs=8).buffer(0.4, quad_segs=8).intersection(g)  # bez tankih trakica
        g = max(dijelovi(g), key=lambda p: p.area)
        for u in dijelovi(ukrasi):                                   # svaki ukras mora sjediti na ploči
            assert g.intersection(u).area > 0.6 * u.area, 'ukras ne sjedi na ploči'
        return g

    pl_F_bl = ploca(F_magenta.buffer(Z_PLOCA, quad_segs=8), F_plavo)
    pl_F_da = ploca(None, F_magenta)
    pl_B_bl = ploca(B_magenta.buffer(Z_PLOCA, quad_segs=8), B_plavo)
    pl_B_da = ploca(None, B_magenta)

    if os.environ.get('SAMO_2D'):
        import pickle
        pickle.dump(dict(o=o, rupa=rupa, supljina=supljina, F_plavo=F_plavo, F_magenta=F_magenta, B_plavo=B_plavo,
                         B_magenta=B_magenta, stupici=stupici, vodilice=vodilice, pl_F_bl=pl_F_bl, pl_F_da=pl_F_da,
                         pl_B_bl=pl_B_bl, pl_B_da=pl_B_da), open(os.environ['SAMO_2D'], 'wb'))
        return
    # ---- CAD tijela ----
    print('gradim CAD tijela …')
    prorezi_F = unary_union([F_plavo, F_magenta]).buffer(Z_UKRAS, join_style='mitre')
    prorezi_B = unary_union([Polygon(p.exterior) for p in dijelovi(unary_union([B_plavo, B_magenta]))]).buffer(Z_UKRAS, join_style='mitre')

    # prednja polovica
    pr = cq_obris_spline(o, SEAM, T - SEAM)
    pr = zaobli(pr, T, R_RUB)
    pr = skosi(pr, SEAM, P['skosenje_spoja'])
    pr = pr.cut(cq_poligon(rupa, -1, T + 2))
    pr = pr.cut(cq_poligon(supljina, SEAM - 1, Z_F_LICE - SEAM + 1))
    pr = pr.cut(cq_poligon(prorezi_F, Z_F_LICE - 0.01, LICE + 1))
    for v in vodilice:
        pr = pr.fuse(cq.Solid.makeCylinder(VOD_D / 2, Z_F_LICE - P['vodilica_kraj'], cq.Vector(v.x, v.y, P['vodilica_kraj'])))
    if otocici:
        pr = pr.fuse(cq_poligon(stupici, 0.0, Z_F_LICE))
    pr = pr.clean()

    # stražnja polovica
    st = cq_obris_spline(o, 0, SEAM)
    st = zaobli(st, 0, R_RUB)
    st = skosi(st, SEAM, P['skosenje_spoja'])
    st = st.cut(cq_poligon(rupa, -1, T + 2))
    st = st.cut(cq_poligon(supljina, Z_B_LICE, SEAM - Z_B_LICE + 1))
    st = st.cut(cq_poligon(prorezi_B, -1, LICE + 1.01))
    for v in vodilice:
        st = st.cut(cq.Solid.makeCylinder(VOD_RUPA / 2, LICE, cq.Vector(v.x, v.y, P['vodilica_dno'])))
    st = st.clean()

    def umetak(pl, z_pl, ukrasi, z_od, z_do):
        s = cq_poligon(pl, z_pl[0], z_pl[1] - z_pl[0])
        s = s.fuse(cq_poligon(ukrasi, z_od, z_do - z_od))
        return s.clean()

    u_F_bl = umetak(pl_F_bl, ZF_BL, F_plavo, ZF_BL[1] - 0.01, T + IZBOC)
    u_F_da = umetak(pl_F_da, ZF_DA, F_magenta, ZF_DA[1] - 0.01, T + IZBOC)
    u_B_bl = umetak(pl_B_bl, ZB_BL, B_plavo, -IZBOC, ZB_BL[0] + 0.01)
    u_B_da = umetak(pl_B_da, ZB_DA, B_magenta, -IZBOC, ZB_DA[0] + 0.01)

    tijela = [
        ('1_crna_prednja_polovica', pr, 'crno', 'gore'),
        ('2_crna_straznja_polovica', st, 'crno', 'dolje'),
        ('3_plavi_prednji_umetak', u_F_bl, 'plavo', 'dolje'),
        ('4_magenta_prednji_umetak', u_F_da, 'magenta', 'dolje'),
        ('5_plavi_straznji_umetak', u_B_bl, 'plavo', 'gore'),
        ('6_magenta_straznji_umetak', u_B_da, 'magenta', 'gore'),
    ]
    os.makedirs(os.path.join(AQ, 'brep'), exist_ok=True)
    for ime, sol, _, _ in tijela:
        sol.exportBrep(os.path.join(AQ, 'brep', f'{ime}.brep'))
    izvjestaj = provjere(tijela)
    izvoz(tijela, izvjestaj, vodilice, otocici)
    print(json.dumps(izvjestaj, ensure_ascii=False, indent=1))


def provjere(tijela):
    rez = {'tijela': {}, 'preklapanja_mm3': {}}
    for ime, s, _, _ in tijela:
        rez['tijela'][ime] = {'valjano': s.isValid(), 'broj_tijela': len(s.Solids()),
                              'volumen_mm3': round(s.Volume(), 2)}
        assert s.isValid() and len(s.Solids()) == 1, f'{ime}: nije jedno valjano tijelo'
    for i in range(len(tijela)):
        for j in range(i + 1, len(tijela)):
            v = tijela[i][1].intersect(tijela[j][1]).Volume()
            rez['preklapanja_mm3'][f'{tijela[i][0]} / {tijela[j][0]}'] = round(v, 5)
            assert v < 1e-3, f'preklapanje {tijela[i][0]} / {tijela[j][0]}: {v}'
    return rez


def izvoz(tijela, izvjestaj, vodilice, otocici):
    import trimesh
    for d in ('step', 'stl', 'ispis'):
        os.makedirs(os.path.join(AQ, d), exist_ok=True)
    sklop = cq.Assembly(name='plan_a_privjesak')
    boje = {'crno': cq.Color(0.10, 0.10, 0.11), 'plavo': cq.Color(0.0, 0.72, 0.91), 'magenta': cq.Color(0.83, 0.08, 0.35)}
    mreze = {}
    for ime, s, boja, smjer in tijela:
        cq.exporters.export(cq.Workplane().add(s), os.path.join(AQ, 'step', f'{ime}.step'))
        sklop.add(s, name=ime, color=boje[boja])
        cq.exporters.export(cq.Workplane().add(s), os.path.join(AQ, 'stl', f'{ime}.stl'), tolerance=0.01, angularTolerance=0.1)
        t = trimesh.load(os.path.join(AQ, 'stl', f'{ime}.stl'))
        if not t.is_watertight:                      # spoji šavove tesselacije (iste točke, ±1 µm)
            t.merge_vertices(digits_vertex=3)
            t.update_faces(t.unique_faces())
            t.remove_unreferenced_vertices()
            if not t.is_watertight:
                trimesh.repair.fill_holes(t)
                trimesh.repair.fix_normals(t)
            t.export(os.path.join(AQ, 'stl', f'{ime}.stl'))
        assert t.is_watertight, ime
        izvjestaj['tijela'][ime]['stl_zatvoren'] = bool(t.is_watertight)
        mreze[ime] = (t, boja, smjer)
    sklop.save(os.path.join(AQ, 'step', '00_sklop_6_dijelova.step'))

    # povratni uvoz STEP-a
    for ime, *_ in tijela:
        r = cq.importers.importStep(os.path.join(AQ, 'step', f'{ime}.step'))
        sol = r.solids().vals()
        izvjestaj['tijela'][ime]['step_povratni_uvoz'] = f'{len(sol)} tijelo, valjano={all(x.isValid() for x in sol)}'
        assert len(sol) == 1 and sol[0].isValid(), f'STEP {ime}'

    # ploče za ispis: jedna boja = jedan ispis, dijelovi okrenuti tako da ravna ploha leži na podlozi
    for boja, ime_ploce in (('crno', '1_ispis_crno'), ('plavo', '2_ispis_plavo'), ('magenta', '3_ispis_magenta')):
        dij = []
        x = 0.0
        for ime, (t, b, smjer) in mreze.items():
            if b != boja:
                continue
            t = t.copy()
            if smjer == 'gore':          # ravna ploha je gore → okreni
                t.apply_transform(trimesh.transformations.rotation_matrix(np.pi, [0, 1, 0]))
            t.apply_translation([-t.bounds[0][0] + x, -t.bounds[0][1], -t.bounds[0][2]])
            x = t.bounds[1][0] + 5.0
            dij.append(t)
        pl = trimesh.util.concatenate(dij)
        pl.apply_translation([-pl.bounds.mean(0)[0], -pl.bounds.mean(0)[1], 0])
        pl.export(os.path.join(AQ, 'ispis', f'{ime_ploce}.stl'))
    izvjestaj['vodilice'] = [[round(v.x, 2), round(v.y, 2)] for v in vodilice]
    izvjestaj['stupici_za_sredista_slova'] = len(otocici)
    json.dump(izvjestaj, open(os.path.join(AQ, 'provjere.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)


if __name__ == '__main__':
    main()
