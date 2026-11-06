# Anvil: brand assets

The logo set of `nyoncode/laravel-package-console`. Everything in `logo/`, `favicon/` and
`social/` is generated from `src/` by `render.sh`. The published files contain no text
elements and depend on no installed font.

---

## The mark

An **anvil drawn as a terminal glyph**: six rows of cells on a 10-column grid, the way a
character in a monospaced font is built. It reads as an anvil at every size, and it looks
as if the console itself had printed it.

The idea the mark carries: **a tool that lives in the terminal.**

### Construction

| Value | Units |
| --- | --- |
| Artboard | 64 × 64u |
| Cell | 5 × 5u |
| Grid | 10 columns × 6 rows |
| Drawn size | 50 × 30u |
| Optical lift | 1u up from centre |

Row by row, in cells, from the left (`█` filled, `·` empty):

```
██████████   face, with the horn on the left
·█████████
···██████·   taper
····████··   waist
···██████·   flare
··████████   base
```

The mark is a single path:

```
M7 16H57V26H52V31H47V36H52V41H57V46H17V41H22V36H27V31H22V26H12V21H7Z
```

There are no curves, gradients, strokes or seams. Every edge lies on the 5u grid, so the
mark stays sharp wherever a cell maps to whole pixels. That is why the favicon artboard is
cut so that one cell is exactly 3px at 32px (see [Favicon](#favicon)).

---

## Palette: Phosphor

| Role | Hex | Notes |
| --- | --- | --- |
| Signal, dark grounds | `#34d399` | The mark, the cursor `_`, accents in dark UI. 10.1:1 on `#0b0d0e` |
| Signal, light grounds | `#047857` | The mark and accents on white. 5.5:1 on `#ffffff` |
| Favicon PNG | `#059669` | Reads on both light and dark browser chrome (3.8:1 on white, 4.5:1 on `#1a1d1f`) |
| Ground | `#0b0d0e` | Dark background, touch icons |
| Surface | `#111416` | Cards, panels |
| Border | `#222a2d` | |
| Ink, dark grounds | `#e8ecea` | |
| Ink, light grounds | `#0b0d0e` | |
| Muted, dark grounds | `#8b958f` | Sublines |
| Muted, light grounds | `#58625d` | |

**One signal colour, used sparingly.** On a page, the signal colour marks the mark, the
cursor and whatever needs attention, and nothing else.

`#34d399` is too light for white backgrounds (1.9:1), so there are two marks rather than one.
Use `mark.svg` on dark grounds and `mark-on-light.svg` on light ones.

---

## Typography

| Use | Face | Licence |
| --- | --- | --- |
| Wordmark `anvil_` | **JetBrains Mono** ExtraBold, tracking −0.03em | SIL OFL 1.1 |
| Subline, code | **JetBrains Mono** Regular | SIL OFL 1.1 |
| Body copy | **Inter** Regular / Medium | SIL OFL 1.1 |

**The wordmark.** It is lowercase, like a command you type. The trailing underscore is the
cursor, set in the signal colour.

**The subline.** `for laravel packages` is set at 0.3× the wordmark, and runs the same
width. Laravel stays a descriptor, never the leading word.

---

## Files

### Logo: `logo/`

| File | Use |
| --- | --- |
| `mark.svg` | The mark in `#34d399`, for **dark** backgrounds |
| `mark-on-light.svg` | The mark in `#047857`, for **light** backgrounds |
| `mark-mono.svg` | One colour via `currentColor`; set `color` on it or its parent |
| `lockup-horizontal.svg` | Mark + wordmark + subline, **light** backgrounds |
| `lockup-horizontal-dark.svg` | The same, **dark** backgrounds |
| `lockup-stacked.svg` | Mark above wordmark, **light** backgrounds |
| `lockup-stacked-dark.svg` | The same, **dark** backgrounds |

> `mark-mono.svg` renders **black** when opened on its own, because `currentColor` has
> nothing to inherit outside a document.

### Favicon: `favicon/`

| File | Size | Form |
| --- | --- | --- |
| `favicon.svg` | vector | Bare mark. `#047857` by default, `#34d399` under `prefers-color-scheme: dark` |
| `favicon-32.png` | 32 × 32 | Bare mark in `#059669`, transparent |
| `favicon-180.png` | 180 × 180 | Full-bleed `#0b0d0e` square, mark at 70% (apple-touch) |
| `favicon-512.png` | 512 × 512 | The same (PWA / maskable) |

**The favicon artboard.** It is `viewBox="5.3333 4.3333 53.3333 53.3333"`, cut so that one 5u
cell is exactly **3px** at 32px. The mark is then 30 × 18 whole pixels, with no
anti-aliased edges. On 2× screens, where a 16px tab icon is drawn at 32 device pixels, the
same holds.

**The touch icons.** These are full bleed, without a radius or transparency. iOS and Android
apply their own corner mask.

```html
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="/favicon-180.png">
```

### Social card: `social/`

| File | Size | Use |
| --- | --- | --- |
| `og-image.png` | 1280 × 640 | GitHub's social preview and `og:image` |

To use it on GitHub, upload it under **Settings → General → Social preview**.

---

## Using the assets

### Clear space

Leave **10u (two cells)** clear on every side of the mark, at the mark's scale. For lockups, measure the mark inside the lockup.

### Minimum sizes

| Asset | Minimum |
| --- | --- |
| `mark*.svg` | 16px wide |
| `lockup-horizontal*.svg` | 200px wide |
| `lockup-stacked*.svg` | 150px wide |

Below a lockup's minimum, the subline is unreadable. Use the mark on its own.

### Which variant on which background

| Background | Mark | Lockup |
| --- | --- | --- |
| Dark, `#0b0d0e`–`#1a1d1f` | `mark.svg` | `lockup-*-dark.svg` |
| White / light | `mark-on-light.svg` | `lockup-*.svg` (without `-dark`) |
| Signal green, photo, busy | `mark-mono.svg` in `#0b0d0e` or white | — |
| Print, single ink | `mark-mono.svg` | — |

### Do

- Keep the mark on its grid, and scale it in whole multiples where pixels matter.
- Use the signal colour for the mark and the cursor, and sparingly elsewhere.
- Use the `-dark` lockups on dark grounds.

### Don't

- **Don't** round the cells, add gaps between them, or smooth the steps. The steps are the mark.
- **Don't** use `#34d399` on white. Use `mark-on-light.svg`.
- **Don't** add gradients, glows, shadows or outlines.
- **Don't** re-typeset or re-space the wordmark, or drop the cursor.
- **Don't** stretch a lockup.
- **Don't** pair the mark with Laravel's own branding: no `#F53003`, no Laravel logo. This is
  an unaffiliated third-party package.
- **Don't** edit `logo/`, `favicon/` or `social/` by hand. They are generated.

---

## Re-rendering

```bash
./art/render.sh
```

`render.sh` needs `resvg` and `usvg` ([resvg](https://github.com/linebender/resvg)) and
`python3`. It reads fonts only from `art/fonts/`, never from the system, so the output is
the same on every machine.

The fonts are not committed. Put these files in `art/fonts/` before rendering:

- `JetBrainsMono-ExtraBold.ttf`, `JetBrainsMono-Bold.ttf` and `JetBrainsMono-Regular.ttf`,
  from [JetBrains Mono](https://github.com/JetBrains/JetBrainsMono/tree/master/fonts/ttf);
- `Inter-Regular.ttf` and `Inter-Medium.ttf`, from [Inter](https://github.com/rsms/inter).

Make design changes in `src/`, then re-render.

`art/` is excluded from the Composer archive (`.gitattributes`).

---

## What was explored, and what was rejected

**Round 1: a sibling of the toolkit's mark.** Three anvils in the toolkit's language:

- an isometric anvil of three boxes and a wedge;
- a flat profile in three bands;
- a profile with `>_` cut out.

All used per-face gradients and an amber palette. They were rejected: too close to the
toolkit, too illustrative, and the wrong colour. The isometric anvil also turned to mud
below 32px without a second, simplified drawing.

**Round 2: a terminal-first identity.** Three marks with one signal colour:

| Concept | Outcome |
| --- | --- |
| **Glyph**: the anvil as a 10 × 6 cell character | **Chosen.** Distinctive, native to the terminal, sharp at 16px |
| Prompt: an app tile with `>` and a tiny anvil as the cursor | The cursor anvil is too small to read below 64px |
| Caret A: an `^`-shaped A with a block-cursor crossbar | Clean, but says nothing about anvils or packages |

Three palettes were compared: Phosphor (mint), Signal (lime) and Ultraviolet (violet).
**Phosphor** was chosen.

---

## Licensing

The artwork is part of this repository and carries its licence (MIT).

No font files are distributed. The lockups and the social card carry outlined glyphs, which
the OFL permits. Attribution is still owed:

- **JetBrains Mono**: © The JetBrains Mono Project Authors, [SIL Open Font License 1.1](https://github.com/JetBrains/JetBrainsMono)
- **Inter**: © The Inter Project Authors, [SIL Open Font License 1.1](https://github.com/rsms/inter)

Laravel is a trademark of Taylor Otwell. This is an unaffiliated third-party package.
