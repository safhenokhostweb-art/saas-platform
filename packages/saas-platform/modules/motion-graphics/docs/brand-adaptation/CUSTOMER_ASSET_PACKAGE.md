# Customer Brand Asset Package

Status: **Architecture only.**

## Package structure

```text
customer-assets/
├── manifest.json
├── theme-palette.json
├── brand-theme.css
├── icons/
├── objects/
├── illustrations/
└── motion/
```

Each class contains only approved, compiled, optimized, customer-scoped variants. Private sources, editable schemas, prompts, provider payloads, raw logos, AI metadata, and draft previews are excluded.

## Private authoring manifest

Every generated version retains the required private fields:

- `source_asset_id`;
- `customer_id`;
- `theme_palette`;
- `AI_prompt`;
- `AI_provider`;
- `generated_date`; and
- `version`.

It should additionally retain `source_asset_version`, `variant_id`, lineage, checksums, pipeline versions, and approval metadata.

## Public package manifest

The published `manifest.json` contains only allowlisted runtime information:

```json
{
  "package_id": "opaque-package-id",
  "customer_scope": "opaque-runtime-scope",
  "version": 3,
  "published_at": "RFC-3339 timestamp",
  "theme": {
    "primary": "#...",
    "secondary": "#...",
    "accent": "#...",
    "background": "#...",
    "border": "#..."
  },
  "assets": [
    {
      "slug": "company-icon",
      "class": "icons",
      "version": 2,
      "path": "icons/company-icon.svg",
      "sha256": "..."
    }
  ]
}
```

Whether public manifests expose literal palette values is a deployment-policy choice. They must never expose logo-analysis provenance or another customer's information.

## Integrity

- normalize relative paths and reject traversal;
- use immutable versioned directories;
- include SHA-256 for every compiled asset;
- generate the manifest after optimization;
- sign manifests or URLs when required;
- set safe MIME and content-disposition headers;
- enforce customer-scoped authorization for private packages; and
- verify the package before publication and consumption.

## Reuse

A package is never reassigned to another customer. Reuse creates a new manifest and ownership lineage, even when compiled bytes are deduplicated internally.

## Rendering modes

1. **Fixed compiled palette:** maximum portability; theme values are embedded as approved fallbacks.
2. **Theme-token package:** includes `brand-theme.css`; used with sanitized inline/component rendering.
3. **Hybrid:** fixed safe fallbacks plus runtime `--brand-*` overrides.

The package declares its rendering mode so customer templates do not guess.

