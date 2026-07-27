# Motion Graphics Design Playground

Standalone, read-only visual prototype for evaluating the existing Motion Graphics Asset Library before future SaaS integration.

The Playground is not a Laravel application, customer-site component, asset editor, AI integration, or publishing system. It loads canonical assets directly from the existing library and creates previews only in browser memory.

## Start

From the repository root:

```text
npm start --prefix tools/motion-design-playground
```

Open:

```text
http://127.0.0.1:4173/tools/motion-design-playground/
```

The included Node.js server is dependency-free, binds to localhost by default, serves the repository read-only, disables directory listing, blocks path traversal, and adds restrictive browser security headers.

Optional environment settings:

```text
PLAYGROUND_HOST=127.0.0.1
PLAYGROUND_PORT=4173
```

Do not open `index.html` directly through `file://`; browsers block the required JSON and SVG fetches. Use the provided static server.

## Data sources

`data/data-sources.json` references, but does not duplicate:

- `library-index.json`;
- `tags.json`;
- `asset-relations.json`; and
- the canonical Asset Library root.

The current library contains 601 indexed assets. Preview paths are derived from each canonical primary path by replacing `animation.svg`, `icon.svg`, or `asset.svg` with `preview.svg`.

## Features

### Asset Explorer

- Motion Graphics, Icons, Objects, and Visual Assets;
- Persian/English search across names, IDs, slugs, keywords, and synonyms;
- type, category, industry, usage, and tag filtering;
- paginated rendering in groups of 24;
- SVG preview fallback from `preview.svg` to the primary file;
- Theme and AI readiness badges; and
- Technical, Visual, Reuse, and AI scores from canonical metadata.

### Theme Playground

The editor controls:

- `--asset-primary`;
- `--asset-secondary`;
- `--asset-accent`;
- `--asset-background`; and
- `--asset-border`.

Compatible SVG files are fetched as text, sanitized, imported inline, and updated with CSS custom properties. No source file is written. Four theme presets are presentation configuration only and contain no duplicated asset metadata.

### Brand Analyzer Prototype

- accepts a local PNG, JPG, WebP, or SVG logo up to 5 MB;
- draws it into an in-memory canvas;
- quantizes visible pixels to find dominant colors;
- proposes a five-token Brand Theme; and
- performs no upload, network AI request, or persistence.

This deterministic adapter is a visual prototype, not brand-science or AI analysis.

### Preview modes

1. **Original Master:** sanitized SVG with its original theme declarations.
2. **Theme Applied:** current manual theme applied in browser memory.
3. **Brand Variant Preview:** mock logo-derived palette applied in browser memory.

None of these modes creates a real Variant Asset.

### Template Quality Preview

The selected asset can be evaluated in:

- Hero;
- Features;
- Services;
- Product Card;
- About Section; and
- CTA Section.

### Future AI Editing placeholder

The “Edit with AI” modal prepares a local UI contract with provider, prompt, asset identity, version, Brand Theme, and `publish=false`. OpenAI, Claude, Gemini, and Other are labels only. No SDK, endpoint, token, or API call exists.

## Browser-side SVG safety

Before inline display, the Playground:

- rejects malformed XML;
- removes scripts, `foreignObject`, embedded images, frames, objects, media, canvas, and embeds;
- removes event-handler attributes;
- removes non-fragment `href` references;
- removes unsafe schemes and styles containing external/data URLs or imports; and
- removes fixed width/height so the preview remains responsive.

This is defense in depth for already governed library assets. It is not a replacement for the independent Asset Quality Scanner.

## Tests

```text
npm test --prefix tools/motion-design-playground
```

The test suite verifies:

- all 601 canonical assets load;
- all primary and preview paths exist;
- search works with real repository data;
- category, industry, usage, tag, and type filters work;
- all five Theme variables are mapped;
- the fake brand adapter returns a valid palette;
- the AI placeholder cannot publish; and
- `library-index.json` remains byte-for-byte unchanged during tests.

## Library analysis

```text
npm run analyze --prefix tools/motion-design-playground
```

This read-only command generates [reports/playground-analysis.md](reports/playground-analysis.md) with loaded assets, categories, missing files/previews, Theme readiness, AI readiness, tags, and relation counts.

## Architecture

- `index.html`: semantic RTL application shell.
- `assets/styles.css`: responsive premium visual system.
- `components/`: reusable DOM components for cards, filters, template preview, and AI modal.
- `scripts/core.js`: environment-neutral search, filter, palette, scoring, and contract functions.
- `scripts/app.js`: browser orchestration, SVG loading/sanitization, live Theme and logo analysis.
- `scripts/server.js`: read-only local static server.
- `scripts/test.js`: dependency-free Node.js tests.
- `scripts/analyze-library.js`: read-only report generator.
- `themes/presets.json`: Playground-only theme presets.
- `data/data-sources.json`: canonical source references.

## Future integration points

A separately authorized phase may migrate reusable pieces into Platform Admin UI, bind real authorization and site/customer context, use approved storage and preview services, connect a provider-neutral proposal contract, and add version/approval/publication workflows. This prototype grants no authority for those integrations.

## Safety

- No automatic fixes.
- No asset writes or copies.
- No Laravel or database bootstrap.
- No external dependencies, fonts, libraries, URLs, or AI providers.
- No customer-site or main-platform integration.
- Uploaded logos remain local to the current browser session.
