# Glazbena podloga (vlastita, sintetizirana): topla, smirena, ~92 BPM, stereo 44,1 kHz
import sys, wave
import numpy as np

SR = 44100
TRAJANJE = float(sys.argv[2]) if len(sys.argv) > 2 else 80.0
BPM = 92
B = 60 / BPM                      # jedan udarac
TAKT = 4 * B
rng = np.random.default_rng(7)
N = int(TRAJANJE * SR) + SR * 4
L = np.zeros(N)
D = np.zeros(N)


def hz(m):
    return 440.0 * 2 ** ((m - 69) / 12)


def dodaj(sig, t, pan=0.0, g=1.0):
    i = int(t * SR)
    if i >= N:
        return
    sig = sig[: N - i] * g
    L[i:i + len(sig)] += sig * np.sqrt(0.5 * (1 - pan))
    D[i:i + len(sig)] += sig * np.sqrt(0.5 * (1 + pan))


def pad(note, dur):
    t = np.arange(int(dur * SR)) / SR
    s = np.zeros_like(t)
    for m in note:
        f = hz(m)
        for det in (-0.12, 0.0, 0.12):
            ff = f * 2 ** (det / 12)
            s += np.sin(2 * np.pi * ff * t) + 0.25 * np.sin(2 * np.pi * 2 * ff * t)
    env = np.minimum(1, t / 0.9) * np.minimum(1, (dur - t) / 0.9)
    return s * env / (len(note) * 3)


def pluck(m, dur=1.6):
    f = hz(m)
    n = int(dur * SR)
    p = max(2, int(SR / f))
    buf = rng.uniform(-1, 1, p)
    out = np.empty(n)
    for i in range(n):
        out[i] = buf[i % p]
        buf[i % p] = 0.4965 * (buf[i % p] + buf[(i + 1) % p])
    return out * np.exp(-np.arange(n) / SR * 1.8)


def bas(m, dur):
    t = np.arange(int(dur * SR)) / SR
    f = hz(m)
    s = np.sin(2 * np.pi * f * t) + 0.3 * np.sin(2 * np.pi * 2 * f * t)
    return s * np.minimum(1, t / 0.01) * np.exp(-t * 2.2)


def kick():
    t = np.arange(int(0.35 * SR)) / SR
    f = 110 * np.exp(-t * 18) + 45
    return np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t * 9)


def shaker():
    n = int(0.09 * SR)
    s = rng.normal(0, 1, n)
    s = np.diff(np.concatenate([[0], s]))          # visoki prolaz
    return s * np.exp(-np.arange(n) / SR * 45) * 0.5


# Am – F – C – G (Am9, Fmaj7, Cadd9, G6) – toplo, bez napetosti
AKORDI = [
    (57, [57, 60, 64, 67, 71]),
    (53, [53, 57, 60, 64]),
    (48, [55, 60, 64, 62]),
    (55, [55, 59, 62, 64]),
]
taktova = int(TRAJANJE / TAKT) + 1
kp = {}
for k in range(taktova):
    t0 = k * TAKT
    korijen, ak = AKORDI[k % 4]
    dodaj(pad(ak, TAKT + 0.9), t0, 0.0, 0.55)
    if k >= 1:
        for o in (0, 2):
            dodaj(bas(korijen - 12, 1.4), t0 + o * B, 0.0, 0.55)
    if k >= 2:
        uzorak = [ak[0] + 12, ak[1] + 12, ak[2] + 12, ak[1] + 12, ak[3] + 12 if len(ak) > 3 else ak[2] + 12, ak[2] + 12, ak[1] + 12, ak[2] + 12]
        for j, m in enumerate(uzorak):
            if m not in kp:
                kp[m] = pluck(m)
            dodaj(kp[m], t0 + j * B / 2, (-0.35 if j % 2 else 0.35), 0.22 if j % 4 else 0.3)
    if 4 <= k < taktova - 2:
        for o in (0, 2):
            dodaj(kick(), t0 + o * B, 0.0, 0.35)
        for j in range(8):
            dodaj(shaker(), t0 + j * B / 2 + (0.02 if j % 2 else 0), 0.25, 0.10 if j % 2 else 0.05)

# jeka (konvolucija s kratkim repom šuma)
rep = rng.normal(0, 1, int(1.6 * SR)) * np.exp(-np.arange(int(1.6 * SR)) / SR * 3.2)
rep /= np.sqrt((rep ** 2).sum())


def jeka(x):
    n = len(x) + len(rep)
    m = 1 << (n - 1).bit_length()
    y = np.fft.irfft(np.fft.rfft(x, m) * np.fft.rfft(rep, m), m)[: len(x)]
    return x + 0.28 * y


L, D = jeka(L), jeka(D)
n = int(TRAJANJE * SR)
L, D = L[:n], D[:n]
t = np.arange(n) / SR
env = np.minimum(1, t / 1.5) * np.minimum(1, (TRAJANJE - t) / 3.5)
L *= env
D *= env
vrh = max(np.abs(L).max(), np.abs(D).max())
L, D = L / vrh * 0.8, D / vrh * 0.8
st = (np.stack([L, D], 1) * 32767).astype('<i2')
with wave.open(sys.argv[1], 'wb') as w:
    w.setnchannels(2)
    w.setsampwidth(2)
    w.setframerate(SR)
    w.writeframes(st.tobytes())
print('glazba', sys.argv[1], round(TRAJANJE, 1), 's')
