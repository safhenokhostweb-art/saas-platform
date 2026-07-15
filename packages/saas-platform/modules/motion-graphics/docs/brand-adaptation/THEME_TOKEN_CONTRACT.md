# Brand Theme Token Contract

Status: **Architecture only.**

## Canonical tokens

Every brand variant uses:

```css
--brand-primary
--brand-secondary
--brand-accent
--brand-background
--brand-border
```

The normalized palette also records contrast-safe text recommendations and provenance, but only the five tokens above are required in the public rendering contract.

## Palette extraction

Logo analysis produces a proposal, not an approved palette. The pipeline must:

1. sanitize and decode the logo;
2. remove irrelevant transparent pixels;
3. detect dominant and supporting colors;
4. identify low-frequency accents;
5. propose background and border colors;
6. calculate contrast and collision risks;
7. normalize colors to an approved format; and
8. wait for Platform Admin review.

The system must not assume that the most frequent logo color is readable as a primary interface color.

## Token validation

- tokens must use an allowlisted color syntax;
- no `url()`, CSS expressions, external references, or arbitrary declarations;
- primary/background and border/background combinations must meet the configured contrast profile where they carry meaning;
- transparent values are allowed only when the editable schema permits them;
- gradients must be derived from approved tokens rather than introduce hidden colors; and
- fallback values must be deterministic.

## Mapping existing assets

The adaptation compiler maps master tokens without editing the master:

```css
--asset-primary: var(--brand-primary);
--asset-secondary: var(--brand-secondary);
--asset-accent: var(--brand-accent);
--asset-background: var(--brand-background);
--asset-border: var(--brand-border);
color: var(--brand-border);
```

For motion assets with another editable schema, the mapping is validated against that schema and renderer. Unknown color fields are not guessed.

## Output behavior

A customer variant should remain self-contained. Its sanitized SVG may reference `--brand-*` variables with approved fallbacks. A package-level `brand-theme.css` and `theme-palette.json` may be included for inline or component rendering.

CSS custom properties do not inherit into an SVG loaded through a plain `<img>` document boundary. Automatic live theme inheritance therefore requires a sanitized inline SVG, an approved component renderer, or a compiled fixed-palette variant. The runtime choice must be explicit.

