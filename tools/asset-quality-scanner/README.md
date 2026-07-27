# Asset Quality Scanner

Independent, dependency-free, read-only auditing tool for the Motion Graphics Asset Library. It scans motion graphics, icons, visual assets, and the object library without loading Laravel or connecting to a database, customer-site, platform runtime, or AI provider.

## Requirements

- Node.js 20 or newer.
- Read access to the asset library.
- Write access only to this tool's `reports/` directory when report generation is enabled.

No npm install is required. The scanner uses only Node.js standard-library modules.

## Run

From the repository root:

```text
node tools/asset-quality-scanner/scanner
```

Audit without writing reports:

```text
node tools/asset-quality-scanner/scanner --no-write
```

Use another read-only library root:

```text
node tools/asset-quality-scanner/scanner --library path/to/library --no-write
```

Show all options:

```text
node tools/asset-quality-scanner/scanner --help
```

The `--output` option is intentionally restricted to a directory inside `tools/asset-quality-scanner/reports`. The scanner rejects output paths inside the audited library.

## Exit codes

| Code | Meaning |
|---:|---|
| `0` | No errors were detected. Warnings are allowed unless `--strict` is used. |
| `1` | Validation errors exist, or warnings exist with `--strict`. |
| `2` | Configuration, safety, or scanner execution failed. |

An exit code of `1` is an audit result, not an automatic-fix request.

## Package discovery

The scanner discovers every directory containing `metadata.json` or a primary asset file. One family-specific primary file is required:

| Family | Primary file |
|---|---|
| Motion Graphics | `animation.svg` |
| Icon | `icon.svg` |
| Visual Asset | `asset.svg` |
| Object Library | `asset.svg` |

Every package also requires:

- `preview.svg`
- `metadata.json`
- `README.md`
- `AI_EDITING_INSTRUCTIONS.txt`

This family-aware rule avoids incorrectly requiring all three primary filenames in one package.

## Checks

### Structure

- missing or multiple primary SVG files;
- wrong primary filename for the family;
- missing package files;
- malformed metadata JSON; and
- broken or escaping paths in indexes.

### SVG and security

- XML well-formedness and matching tags;
- SVG root validation;
- file-size limit;
- scripts, `foreignObject`, embedded images, active elements, event attributes, DTD/entity declarations;
- external/non-fragment `href` values;
- unsafe URL schemes, CSS imports, and external `url()` references; and
- `title`, `desc`, `role="img"`, plus `aria-label` or `aria-labelledby`.

The built-in XML validator is intentionally bounded and dependency-free. It performs structural well-formedness and security checks; it is not an SVG XSD renderer or browser.

### Theme support

- all five `--asset-*` variables;
- `currentColor` usage;
- `theme_support=true`; and
- fixed presentation colors outside approved token declarations or `var()` fallbacks.

Fallback colors in `--asset-*` declarations and `var(--token, fallback)` are permitted. Fixed `fill`, `stroke`, color, background, border, and stop-color values elsewhere are reported.

### Metadata, AI, and ownership

- all required governance fields and basic types;
- stable slug and `library_id` formats;
- quality score fields in the range 0–100;
- non-empty AI editing instructions;
- `ai_editable=true` and non-empty `editable_properties`;
- `asset_role=master`; and
- `ownership.type=platform`, with no customer/site ownership on a Master Asset.

### Indexes and duplicates

- canonical and family index existence, JSON shape, declared counts, paths, metadata/preview references, slug alignment, and canonical coverage;
- canonical `asset_types` counts;
- duplicate `library_id` and index IDs;
- duplicate slugs;
- exact primary SVG byte hashes; and
- identical SVG element/attribute structure hashes.

Duplicate findings are reports only. The scanner never deletes or consolidates assets.

## Configuration and rules

- `config.json`: library location, required indexes, report and safety settings.
- `rules/svg-rules.json`: package, SVG, security, accessibility, and size rules.
- `rules/metadata-rules.json`: required fields, types, IDs, and quality-score rules.
- `rules/theme-rules.json`: theme tokens, currentColor, and fixed-color checks.
- `rules/ai-rules.json`: AI instructions and editability requirements.

Paths in `config.json` are resolved relative to the configuration file. Rules are data-driven, while safety invariants are also enforced in the scanner.

## Reports

Each normal run atomically replaces only these files:

- `reports/latest-report.json`: complete machine-readable result and issue list;
- `reports/latest-report.md`: readable detailed audit; and
- `reports/QUALITY_REPORT.md`: compact scorecard.

`valid` and `valid_assets` count packages with no asset-scoped errors. `passed` also includes global checks such as index integrity, so a library can have all packages valid while still failing because an index is inconsistent.

## Tests

Run the isolated fixtures:

```text
node tools/asset-quality-scanner/tests/run-tests.js
```

The five cases are stored under `examples/test-cases/`:

1. valid asset;
2. missing metadata;
3. invalid SVG;
4. hardcoded color; and
5. broken index.

Fixtures are standalone miniature libraries. Tests use `writeReports=false` and never touch the real asset library.

## Safety guarantees

- No scanner code writes to the library root.
- No automatic fixing, deletion, formatting, or normalization exists.
- Report output is rejected if it resolves inside the audited library.
- No Laravel bootstrap, database, network, provider SDK, browser, or external command is used.
- Existing assets are opened read-only.
