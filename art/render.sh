#!/usr/bin/env bash
#
# Regenerate the Anvil brand set from art/src: the marks, the lockups, the
# favicons and the social card. Deterministic and idempotent.
#
#   ./art/render.sh
#
# Requires (system binaries):
#   resvg  — SVG -> PNG               https://github.com/linebender/resvg
#   usvg   — outlines <text> to paths (ships with resvg)
#
# Fonts are read from art/fonts, never from the system, so the output is the
# same on every machine.

set -euo pipefail

ART="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SRC="$ART/src"

for bin in resvg usvg python3; do
  command -v "$bin" >/dev/null || { echo "error: $bin not found on PATH" >&2; exit 1; }
done

FONTS=(--skip-system-fonts --use-fonts-dir "$ART/fonts")

mkdir -p "$ART/logo" "$ART/favicon" "$ART/social"

# usvg writes width/height but drops the viewBox, which stops an inlined SVG
# from scaling with CSS. Put it back from the source file.
restore_viewbox() {
  python3 - "$1" "$2" <<'PY'
import re, sys
src, out = sys.argv[1], sys.argv[2]
vb = re.search(r'viewBox="([^"]+)"', open(src).read()).group(1)
svg = open(out).read()
if 'viewBox=' not in svg:
    svg = svg.replace('<svg ', f'<svg viewBox="{vb}" ', 1)
open(out, 'w').write(svg)
PY
}

echo "==> logo"
for f in mark mark-on-light mark-mono; do
  cp "$SRC/$f.svg" "$ART/logo/$f.svg"
done
for f in lockup-horizontal lockup-horizontal-dark lockup-stacked lockup-stacked-dark; do
  usvg "${FONTS[@]}" "$SRC/$f.svg" "$ART/logo/$f.svg"
  restore_viewbox "$SRC/$f.svg" "$ART/logo/$f.svg"
done

echo "==> favicon"
cp "$SRC/favicon.svg" "$ART/favicon/favicon.svg"
resvg -w 32 "$SRC/favicon-png.svg" "$ART/favicon/favicon-32.png"
resvg -w 180 "$SRC/touch-icon.svg" "$ART/favicon/favicon-180.png"
resvg -w 512 "$SRC/touch-icon.svg" "$ART/favicon/favicon-512.png"

echo "==> social card"
resvg "${FONTS[@]}" -w 1280 "$SRC/og.svg" "$ART/social/og-image.png"

echo "done"
