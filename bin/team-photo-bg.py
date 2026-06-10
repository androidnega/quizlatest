#!/usr/bin/env python3
"""
Replace the wall background of a centred portrait with an on-brand
QuizSnap teal backdrop, plus shallow-depth-of-field blur and bokeh.

Doesn't try to do a pixel-perfect cut-out of the silhouette — instead
uses a soft, generously-sized vertical ellipse that protects the whole
subject column. The wall area visible outside that ellipse (mainly the
upper corners around the head, plus the bottom corners below the
shoulders) gets the teal treatment. Result is artifact-free because
the mask never crosses skin/clothing.

Usage:
    python3 bin/team-photo-bg.py SRC DST [--cx 0.5 --cy 0.55 --rx 0.55 --ry 0.78 --feather 70]
"""

import argparse
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw, ImageFilter


# ---------- background plate ----------

def teal_gradient(w: int, h: int) -> np.ndarray:
    """Diagonal QuizSnap teal: deep top-left, lifted bottom-right."""
    top_left  = np.array([0x0d, 0x26, 0x2a], dtype=np.float32)   # very deep teal
    bot_right = np.array([0x35, 0x6c, 0x74], dtype=np.float32)   # mid teal
    yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
    t = (xx + yy) / (w + h - 2)
    return top_left * (1 - t)[..., None] + bot_right * t[..., None]


def add_bokeh(canvas: np.ndarray, n: int = 8, seed: int = 11) -> np.ndarray:
    h, w, _ = canvas.shape
    layer = Image.new("RGB", (w, h), (0, 0, 0))
    draw = ImageDraw.Draw(layer)
    rng = np.random.default_rng(seed)
    for _ in range(n):
        cx = int(rng.integers(-50, w + 50))
        cy = int(rng.integers(-50, h + 50))
        r  = int(rng.integers(80, 200))
        v  = int(rng.integers(20, 38))
        # cool teal-cyan highlight
        draw.ellipse(
            (cx - r, cy - r, cx + r, cy + r),
            fill=(int(v * 0.5), int(v * 1.3), int(v * 1.55)),
        )
    layer = layer.filter(ImageFilter.GaussianBlur(95))
    return np.clip(canvas + np.array(layer).astype(np.float32), 0, 255)


# ---------- subject mask (soft head + shoulders shape) ----------

def _ellipse(w: int, h: int, cx_rel, cy_rel, rx_rel, ry_rel) -> np.ndarray:
    cx, cy = w * cx_rel, h * cy_rel
    rx, ry = w * rx_rel, h * ry_rel
    yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
    d = ((xx - cx) / rx) ** 2 + ((yy - cy) / ry) ** 2
    return (d <= 1.0).astype(np.float32)


def subject_mask(
    w: int, h: int,
    head=(0.49, 0.27, 0.24, 0.32),       # cx, cy, rx, ry — relative
    torso=(0.50, 0.80, 0.45, 0.35),
    feather: int = 70,
) -> np.ndarray:
    """Soft 0..1 mask shaped like head + shoulders (snowman). 1 inside
    the protected zone, 0 well outside."""
    head_mask = _ellipse(w, h, *head)
    torso_mask = _ellipse(w, h, *torso)
    raw = np.maximum(head_mask, torso_mask)
    m = Image.fromarray((raw * 255).astype(np.uint8))
    m = m.filter(ImageFilter.GaussianBlur(feather))
    return np.array(m).astype(np.float32) / 255.0


# ---------- finishing ----------

def vignette(arr: np.ndarray, strength: float = 0.32, gamma: float = 2.2) -> np.ndarray:
    h, w, _ = arr.shape
    yy, xx = np.mgrid[0:h, 0:w].astype(np.float32)
    cx, cy = (w - 1) / 2, (h - 1) / 2
    r = np.sqrt((xx - cx) ** 2 + (yy - cy) ** 2)
    r /= r.max()
    falloff = (1 - strength * (r ** gamma))[..., None]
    return np.clip(arr * falloff, 0, 255)


def add_grain(arr: np.ndarray, sigma: float = 2.2, seed: int = 7) -> np.ndarray:
    rng = np.random.default_rng(seed)
    return np.clip(arr + rng.normal(0.0, sigma, size=arr.shape).astype(np.float32), 0, 255)


# ---------- main ----------

def _f4(arg: str) -> tuple:
    parts = [float(x) for x in arg.split(",")]
    if len(parts) != 4:
        raise argparse.ArgumentTypeError("expected cx,cy,rx,ry (4 floats)")
    return tuple(parts)


def main() -> int:
    p = argparse.ArgumentParser()
    p.add_argument("src", type=Path)
    p.add_argument("dst", type=Path)
    p.add_argument("--head",  type=_f4, default=(0.49, 0.27, 0.24, 0.32),
                   help="head ellipse cx,cy,rx,ry (0-1)")
    p.add_argument("--torso", type=_f4, default=(0.50, 0.80, 0.45, 0.35),
                   help="torso ellipse cx,cy,rx,ry (0-1)")
    p.add_argument("--feather", type=int, default=80)
    args = p.parse_args()

    img = Image.open(args.src).convert("RGB")
    arr_uint8 = np.array(img)
    arr = arr_uint8.astype(np.float32)
    h, w, _ = arr.shape

    # 1. Soft head+torso mask covering the subject column.
    mask = subject_mask(
        w, h,
        head=args.head, torso=args.torso,
        feather=args.feather,
    )[..., None]

    # 2. New background = teal gradient + bokeh.
    bg = teal_gradient(w, h)
    bg = add_bokeh(bg)

    # 3. Composite sharp subject inside ellipse, teal bg outside.
    composite = arr * mask + bg * (1 - mask)

    # 4. Cinematic finish.
    composite = vignette(composite, strength=0.30, gamma=2.2)
    composite = add_grain(composite, sigma=2.0)

    out = np.clip(composite, 0, 255).astype(np.uint8)
    Image.fromarray(out).save(args.dst, quality=88, optimize=True, progressive=True)
    print(f"wrote {args.dst} ({args.dst.stat().st_size / 1024:.0f} KB)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
