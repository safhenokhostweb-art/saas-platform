# ZIP Import Engine — Architecture

> Version: 1.0.0 | Date: 2026-06-22 | Status: OPERATIONAL

---

## Overview

The ZIP Template Import Engine enables platform administrators to import commercial
template ZIP packages from TemplateGoat, ThemeForest, Envato, Elementor Libraries,
and Bricks Libraries — without manual conversion.

A ZIP is uploaded, analyzed, its JSON is extracted and parsed through the existing
JSON Import Pipeline, screenshots are saved, and a ThemePack is generated ready
for Canvas editing or Marketplace publishing.

---

## System Architecture

```
UploadedFile (.zip)
        │
        ▼
┌─────────────────────┐
│ ZipTemplateExtractor │  Phase 1: Safe extraction
│  - path traversal   │  - Blocks .php/.exe/.sh
│  - 500 files max    │  - 100 MB max
│  - temp dir cleanup │  - discovers json/screenshot/asset/metadata files
└─────────┬───────────┘
          │
          ▼
┌──────────────────────┐
│ ZipTemplateAnalyzer  │  Phase 2: Content analysis
│  - detectOrigin()    │  - TemplateGoat / ThemeForest / Envato / Elementor / Bricks
│  - choosePrimaryJson │  - picks best JSON candidate
│  - extractMetadata   │  - name/version/author from README/package.json/style.css
│  - choosePrimaryShot │  - best screenshot for display
└──────────┬───────────┘
           │
           ▼
┌──────────────────────────────┐
│ JsonImportPipelineService    │  Phase 3: JSON processing (REUSE)
│  → ThemeStructureAnalyzer   │  - detects Elementor/Bricks/Shopify/Webflow/Custom
│  → Adapter (5 adapters)     │  - converts to normalized structure
│  → WidgetMapper             │  - maps 90+ widget types to platform blocks
│  → ThemePackGenerator       │  - creates ThemePack in DB
└──────────┬───────────────────┘
           │
           ▼
┌──────────────────────────────┐
│ Screenshot Processing        │  Phase 4: Visual capture
│  - up to 3 screenshots saved │  - stored under public/storage/zip-import/screenshots/
│  - image dimensions captured │  - attached to pack_data for result display
└──────────┬───────────────────┘
           │
           ▼
┌──────────────────────────────┐
│ ThemePack (DB record)        │  Phase 5/6: Persistence
│  + optional Theme entry      │  - marketplace publish optional
│  + ThemeMarketplace entry    │
└──────────┬───────────────────┘
           │
           ▼
┌──────────────────────────────┐
│ Similarity Scoring           │  Phase 7: Quality validation
│  - 5-dimension breakdown     │  - target: 90%+
│  - EXCELLENT/PASS/PARTIAL    │
└──────────────────────────────┘
```

---

## Service Classes

### ZipTemplateExtractor

**File:** `app/Services/ThemeBuilder/ZipImport/ZipTemplateExtractor.php`

**Responsibilities:**
- Open and validate ZIP archive (max 500 files, max 100MB extracted)
- Block path traversal attacks (`..` in filenames, absolute paths)
- Block execution-capable extensions: `.php`, `.phtml`, `.phar`, `.exe`, `.sh`, `.bat`, `.cmd`, `.ps1`
- Extract to a temporary directory using a per-request unique name
- Discover and classify all files into: `json_files`, `screenshot_files`, `asset_files`, `metadata_files`
- Provide `cleanup(string $tempDir)` to remove temp files after processing

**Key method:** `extract(UploadedFile $file): array`

Returns discovery map:
```php
[
  'temp_dir'         => '/tmp/zip-import-abc123/',
  'original_name'    => 'my-template.zip',
  'json_files'       => ['/tmp/.../content.json'],        // sorted: largest first
  'screenshot_files' => ['/tmp/.../preview.png'],         // sorted: largest first
  'asset_files'      => ['/tmp/.../style.css'],
  'metadata_files'   => ['/tmp/.../README.md'],
  'all_files'        => ['content.json', 'assets/style.css', ...],
]
```

### ZipTemplateAnalyzer

**File:** `app/Services/ThemeBuilder/ZipImport/ZipTemplateAnalyzer.php`

**Responsibilities:**
- Detect commercial origin (6 origins: templategoat, themeforest, envato, elementor_lib, bricks_lib, unknown)
- Choose the primary JSON file from multiple candidates using priority keywords
- Detect JSON builder format by delegating to `ThemeStructureAnalyzer`
- Extract metadata: name, version, description, author, tags from package.json / composer.json / README / style.css (WordPress theme header)
- Choose the best screenshot for display

**Origin detection hierarchy:**
1. Read metadata files for branding strings (templategoat, themeforest, envato, etc.)
2. Check file path strings for branding
3. WordPress `style.css` Theme Name + Version header → ThemeForest
4. Path contains 'elementor' → Elementor Library
5. Path contains 'bricks' → Bricks Library
6. Default: unknown

**JSON priority selection:**
```
elementor-export > elementor-template > elementor >
bricks-export > bricks-template > bricks >
webflow > shopify > sections >
template > export > content > theme > data > page >
[fallback: largest file by size]
```

### ZipTemplateImportService

**File:** `app/Services/ThemeBuilder/ZipImport/ZipTemplateImportService.php`

**Responsibilities:** Full 7-phase pipeline orchestration.

**Constructor dependencies:**
```php
ZipTemplateExtractor
ZipTemplateAnalyzer
JsonImportPipelineService  (from existing JSON Import Engine)
```

**Key method:** `import(UploadedFile $file, string $name, array $options): array`

Always calls `extractor->cleanup()` in a `finally` block — temp files are never left behind.

**Similarity scoring (5 dimensions):**

| Dimension              | Max Points | Weight |
|------------------------|------------|--------|
| JSON quality score     | 40         | 40%    |
| Format confidence      | 20         | 20%    |
| Screenshot coverage    | 20         | 20%    |
| Metadata completeness  | 10         | 10%    |
| Asset richness         | 10         | 10%    |
| **Total**              | **100**    |        |

**Grade thresholds:**
- EXCELLENT: ≥ 90
- PASS:      ≥ 70
- PARTIAL:   ≥ 50
- FAIL:      < 50

---

## Controller

**File:** `app/Http/Controllers/Admin/ThemeZipImportController.php`

| Action    | Method | URL                                       | Description                          |
|-----------|--------|-------------------------------------------|--------------------------------------|
| `form`    | GET    | `/admin/theme-builder/zip-import`         | Upload form                          |
| `detect`  | POST   | `/admin/theme-builder/zip-import/detect`  | AJAX: analyze ZIP, return summary    |
| `import`  | POST   | `/admin/theme-builder/zip-import/import`  | Full import → ThemePack              |
| `result`  | GET    | `/admin/theme-builder/zip-import/{pack}/result` | Result page               |
| `install` | POST   | `/admin/theme-builder/zip-import/{pack}/install` | Create Theme, open Canvas   |
| `export`  | GET    | `/admin/theme-builder/zip-import/{pack}/export`  | Download normalized JSON    |

---

## Views

| File | Purpose |
|------|---------|
| `resources/views/admin/theme-builder/zip-import.blade.php` | Upload form with AJAX detection banner |
| `resources/views/admin/theme-builder/zip-import-result.blade.php` | Result dashboard |

**Upload form features:**
- Drag-and-drop zone with AlpineJS
- AJAX auto-detection on file select (sends to `/detect`)
- Detection banner: shows origin, format, file counts (JSON / screenshots / assets / total)
- Step indicator (۱/۲/۳)
- Import options: name, category, description, force_source override, marketplace publish toggle
- Recent imports list

**Result page features:**
- Similarity score + Quality score + ZIP contents card (side-by-side)
- Origin badge + format badge
- ThemePack stats (pages, blocks, variety, header/footer)
- Similarity breakdown (5-dimension bars)
- Processing phase timeline
- Quality findings + Similarity findings
- Screenshots gallery
- Actions: Install to Canvas / Export JSON / Re-import / Marketplace

---

## Security

| Threat | Mitigation |
|--------|------------|
| Path traversal | Blocks `..` and absolute paths in ZIP entry names |
| Code execution | Blocks `.php`, `.phtml`, `.phar`, `.exe`, `.sh`, `.bat`, `.cmd`, `.ps1` |
| ZIP bomb | Max 500 files, max 100MB extracted total |
| Second traversal | `realpath()` check: extracted path must start with `realpath(targetDir)` |
| Orphaned temp files | `finally` block always calls `extractor->cleanup()` |

---

## Reused Existing Services

| Service | Usage in ZIP Import |
|---------|---------------------|
| `ThemeStructureAnalyzer` | JSON builder format detection (Elementor/Bricks/Shopify/Webflow/Custom) |
| `WidgetMapper` | Block type mapping (90+ widget types) |
| `JsonImportPipelineService` | Full JSON→ThemePack pipeline |
| `ElementorAdapter` + 4 others | Builder-specific normalization |
| `ThemePackGenerator` | ThemePack DB record creation |
| `ThemeMarketplace` | Optional marketplace publish |

---

## Data Flow

```
ZIP uploaded →
  [Extractor] temp dir created, files classified →
  [Analyzer] origin detected, JSON selected, metadata extracted →
  [JsonPipeline] JSON parsed, adapter chosen, blocks mapped, ThemePack created →
  [Screenshots] up to 3 saved to public/storage/zip-import/screenshots/ →
  [SimilarityScorer] 5-dimension score, EXCELLENT/PASS/PARTIAL/FAIL grade →
  [Cleanup] temp dir removed →
  redirect to /result/{pack}
```

---

## Configuration

No new config keys required. Uses existing:
- `ANTHROPIC_API_KEY` — optional, only used if Marketplace publish triggers AI naming
- `APP_URL` — for asset URLs in result page

Storage: `Storage::disk('public')` — screenshots saved to `public/storage/zip-import/screenshots/`

---

## Known Limitations

| Limitation | Impact |
|-----------|--------|
| Elementor zip typically only includes JSON (no CSS/assets) | Low: blocks still map correctly |
| ThemeForest zips may contain multiple themes — largest JSON chosen | Medium: user can use force_source override |
| Bricks .brx format (binary) not supported — only .json exports | Low: Bricks JSON export is the standard sharing format |
| Screenshot visual analysis uses only dimensions, not color extraction | Low: color data comes from JSON global_styles |
| ZIP without any JSON generates a stub pack (score ≈ 10) | Medium: user should re-export from builder |
