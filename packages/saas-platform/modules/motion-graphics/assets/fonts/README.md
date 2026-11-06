# Motion library fonts

## `vazirmatn-variable.woff2` (library v3 and later, ADR-080)

The complete Vazirmatn 33.003 variable font, byte-identical to the
project's `public/assets/fonts/vazirmatn/Vazirmatn[wght].woff2`
(SHA-256 `4e3fa217d38fdafc1fea4414ceb58ca5e662cf0ab5fa735a8c8c20e8b42cad92`):
every glyph (811 code points), `wght` axis 100–900. It is not modified,
only renamed (no brackets in the path). © 2015 The Vazirmatn Project
Authors, SIL Open Font License 1.1 (`OFL.txt`).

A library version selects it with metadata
`typography.embedded_font: "vazirmatn-variable"`; the platform embeds it as
`@font-face{…font-weight:100 900…}`. A version that names no font gets the
subset below, so files published before ADR-080 keep their exact bytes.

## `vazirmatn-motion.woff2` (library v2, ADR-079)

`vazirmatn-motion.woff2` is a Modified Version (subset + axis-limited
instance) of Vazirmatn 33.003, © 2015 The Vazirmatn Project Authors,
licensed under the SIL Open Font License 1.1 (see `OFL.txt`; Vazirmatn
declares no Reserved Font Name).

It is embedded as an `@font-face` `data:` URI into every motion SVG the
platform serves (ADR-079), because an SVG shown through `<img>` cannot fetch
external fonts. Built from `public/assets/fonts/vazirmatn/Vazirmatn[wght].woff2`
with fontTools 4.68:

```
pyftsubset "Vazirmatn[wght].woff2" --layout-features='*' --flavor=woff2 \
  --unicodes="U+0020-007E,U+00A0,U+00AB,U+00B7,U+00BB,U+00D7,U+060C,U+061B,U+061F,U+0621-063A,U+0640-0655,U+0660-066D,U+0670,U+067E,U+0686,U+0698,U+06A9,U+06AF,U+06BE,U+06C0-06C3,U+06CC,U+06D0,U+06F0-06F9,U+200C-200F,U+2010-2014,U+2018-201F,U+2026,U+2066-2069" \
  --output-file=subset.woff2
fonttools varLib.instancer subset.woff2 wght=400:800 -o vazirmatn-motion.ttf   # then saved as woff2
```

Coverage: basic Latin, Persian letters and Arabic-script letters used in
Persian, Persian and Arabic digits, Persian punctuation, ZWNJ and bidi
controls; weights 400–800 (variable).
