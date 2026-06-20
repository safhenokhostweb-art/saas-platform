# Theme Pack Import Pipeline

> Version: 1.0  
> Date: 2026-06-20  
> Scope: SaaS Platform — how theme packs from TB-AI Studio are imported, validated, previewed, installed, and published.

---

## 1. Pipeline Overview

```
TB-AI Studio (local)
     │
     │  POST /api/v1/studio/import
     │  Authorization: Bearer {STUDIO_API_KEY}
     │  Content-Type: multipart/form-data
     │  Body: pack=theme-pack.zip, meta={"name":"...","tags":[...]}
     │
     ▼
AuthenticateStudioApi middleware
     │  ✓ Check Bearer token → config('services.studio.api_key')
     │  ✓ Optional HMAC signature → X-Studio-Signature header
     │
     ▼
ThemePackStudioController::import()
     │  ✓ Validate: file required, zip type, max 50MB
     │  ✓ ThemeTemplateImportExportService::importPackZip()
     │     ├─ Open ZIP
     │     ├─ Extract pack.json
     │     └─ ThemePack::create() → site_id=null
     │  ✓ Tag pack with 'studio-import'
     │  ✓ Apply meta (name, description, author, version)
     │
     ▼
Response: { pack_uuid, preview_url, install_url }
     │
     ▼
Admin Panel: /admin/studio-imports
     │
     ├── Preview → show pack contents, colors, pages, templates
     ├── Install → POST /admin/studio-imports/{pack}/publish
     │             → is_public=true, ThemeMarketplace::create()
     └── Export  → ZIP / JSON download
```

---

## 2. API Endpoints

All endpoints are under `/api/v1/studio/` with `studio.api` middleware.

### 2.1 Import

```
POST /api/v1/studio/import
Content-Type: multipart/form-data
Authorization: Bearer {STUDIO_API_KEY}
X-Studio-Signature: {optional HMAC}

Fields:
  pack  : (file)   Theme pack ZIP — required, max 50MB
  meta  : (string) JSON metadata — optional
          { "name": "...", "description": "...", "tags": [...], "version": "...", "author": "..." }

Response 200:
{
  "message": "Pack imported successfully.",
  "data": {
    "id": 42,
    "uuid": "a1b2c3d4-...",
    "name": "My Theme Pack",
    "preview_url": "https://platform/admin/theme-packs/42",
    "install_url":  "https://platform/api/v1/studio/install/a1b2c3d4-..."
  }
}

Response 422:
{ "message": "Import failed: ..." }

Response 401:
{ "message": "Invalid Studio API key." }
```

### 2.2 Validate (dry run)

```
POST /api/v1/studio/validate
Content-Type: multipart/form-data
Authorization: Bearer {STUDIO_API_KEY}

Fields:
  pack : (file) ZIP file to validate

Response 200:
{
  "valid": true,
  "issues": [],
  "info": {
    "manifest": { "format": "theme-pack-v1", "name": "...", "uuid": "..." },
    "pack": { "name": "...", "version": "...", "pages": 2, "templates": 5, "headers": 1, "footers": 1 }
  }
}

Response 200 (with issues):
{
  "valid": false,
  "issues": ["manifest.json missing.", "pack.json: name field missing."],
  "info": {}
}
```

### 2.3 Preview

```
GET /api/v1/studio/preview/{uuid}
Authorization: Bearer {STUDIO_API_KEY}

Response 200:
{
  "data": {
    "uuid": "...",
    "name": "...",
    "description": "...",
    "version": "1.0.0",
    "author": "TB-AI Studio",
    "tags": ["ai-generated", "studio-import"],
    "summary": { "global_styles": true, "headers": 1, "footers": 1, "pages": 2, "templates": 5, "demo_data": true },
    "global_styles": { "primary_color": "#3b82f6", ... },
    "pages": [{ "title": "صفحه اصلی", "slug": "", "block_count": 8 }],
    "templates": [{ "name": "هیرو اصلی", "category": "hero" }]
  }
}
```

### 2.4 Install

```
POST /api/v1/studio/install/{uuid}
Authorization: Bearer {STUDIO_API_KEY}

Response 200:
{ "message": "Pack installed and made public.", "data": { "uuid": "...", "name": "..." } }
```

### 2.5 Publish to Marketplace

```
POST /api/v1/studio/publish/{uuid}
Authorization: Bearer {STUDIO_API_KEY}
Content-Type: application/json

Body:
{
  "price": 0,
  "is_featured": false,
  "preview_url": "https://demo.example.com"
}

Response 200:
{
  "message": "Pack published to marketplace.",
  "marketplace_id": 12,
  "marketplace_url": "https://platform/admin/theme-marketplace"
}
```

---

## 3. ZIP Format Specification

A valid Theme Pack ZIP (`theme-pack-v1`) must contain:

### 3.1 Required Files

| File | Description | Required |
|------|-------------|----------|
| `manifest.json` | Machine-readable metadata | ✓ |
| `pack.json` | Full theme pack data | ✓ |

### 3.2 Optional Files

| File | Description |
|------|-------------|
| `templates/template_N.json` | Individual section templates |
| `pages/{slug}.json` | Individual page data |
| `marketplace.json` | Marketplace listing metadata |
| `assets/thumbnail.png` | Preview thumbnail (future) |
| `assets/preview.jpg` | Full preview image (future) |

### 3.3 manifest.json Schema

```json
{
  "format":       "theme-pack-v1",
  "generator":    "tb-ai-studio",
  "name":         "Theme Name",
  "uuid":         "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx",
  "version":      "1.0.0",
  "generated_at": "2026-06-20T12:00:00+00:00"
}
```

Validation rules:
- `format` must be exactly `"theme-pack-v1"`
- `uuid` must be present

### 3.4 pack.json Schema

```json
{
  "uuid":         "string",
  "name":         "string (required)",
  "description":  "string",
  "version":      "string",
  "author":       "string",
  "license_type": "free|premium|commercial|mit",
  "tags":         ["string"],
  "includes":     ["global_styles","headers","footers","pages","templates","demo_data"],
  "pack_data": {
    "global_styles": {
      "primary_color":    "#hex",
      "secondary_color":  "#hex",
      "accent_color":     "#hex",
      "background_color": "#hex",
      "text_color":       "#hex",
      "font_family":      "string",
      "border_radius":    "sm|md|lg|xl",
      "direction":        "rtl|ltr"
    },
    "responsive_settings": {
      "desktop": { "base_font_size": "16px", "container_max_width": "1280px" },
      "tablet":  { "base_font_size": "15px", "container_max_width": "768px" },
      "mobile":  { "base_font_size": "14px", "container_max_width": "100%" }
    },
    "headers":   [{ "name": "string", "blocks": [...] }],
    "footers":   [{ "name": "string", "blocks": [...] }],
    "pages":     [{ "title": "string", "slug": "string", "blocks": [...] }],
    "templates": [{ "name": "string", "type": "section|page|component", "category": "string", "blocks": [...] }],
    "demo_data": { "site_name": "string", "tagline": "string" }
  },
  "marketplace":      {},
  "preview_image_url": null,
  "exported_at":      "ISO8601",
  "exporter":         "tb-ai-studio",
  "format":           "theme-pack-v1"
}
```

### 3.5 Block Schema (inside pages/headers/footers/templates)

```json
{
  "id":   "uuid-v4",
  "type": "hero|heading|text|image|button|...",
  "data": { ...type-specific props... }
}
```

Valid block types:
`hero`, `heading`, `text`, `image`, `button`, `divider`, `spacer`, `card`, `card_grid`,
`feature_list`, `icon_box`, `testimonial`, `faq`, `stats`, `team`, `cta`, `banner`,
`logo_bar`, `newsletter`, `contact_form`, `nav_header`, `nav_footer`, `nav_logo`,
`nav_links`, `pricing_cards`, `features_grid`, `hero_split`, `hero_gradient`,
`stats_row`, `testimonials_slider`, `bento_grid`, `services_cards`

---

## 4. Validation Rules

The `ThemePackStudioController::validate()` endpoint checks:

| Check | Rule | Error if fails |
|-------|------|----------------|
| ZIP is valid | ZipArchive::open() succeeds | "Invalid ZIP file." |
| manifest.json present | zip->getFromName() != false | "manifest.json missing." |
| format field | must equal "theme-pack-v1" | "Unsupported format: ..." |
| pack.json present | zip->getFromName() != false | "pack.json missing." |
| name field | not empty | "pack.json: name field missing." |
| pack_data field | not empty | "pack.json: pack_data missing." |

---

## 5. Admin Import Pipeline UI

### 5.1 Studio Imports Index (`/admin/studio-imports`)

- Lists all ThemePack records tagged `studio-import`
- Shows: name, version, page count, template count, status (draft/published), date
- Shows API key configuration status
- Shows setup guide for TB-AI Studio

### 5.2 Pack Detail (`/admin/studio-imports/{pack}`)

- Full preview: description, global styles color swatches, page list, template list
- Content summary: pages / templates / headers / footers counters
- Actions: Download ZIP, Download JSON, Publish to Marketplace, Delete

### 5.3 Publish Flow

1. Admin clicks "انتشار در مارکت‌پلیس"
2. `POST /admin/studio-imports/{pack}/publish`
3. Sets `ThemePack.is_public = true`, `ThemePack.is_active = true`
4. Creates or updates `ThemeMarketplace` record
5. Pack appears in `/admin/theme-marketplace`
6. Tenants can browse and install via their admin panel

---

## 6. Server Configuration

### 6.1 Required ENV vars on SaaS Platform

```env
# TB-AI Studio API — required for upload to work
STUDIO_API_KEY=your-very-long-random-secret-key-here

# Optional: HMAC signature verification for extra security
STUDIO_SECRET=another-random-secret-for-hmac-signing
```

Generate a secure key:
```bash
php artisan tinker --execute="echo Str::random(64);"
# or
openssl rand -hex 32
```

### 6.2 PHP Upload Limits

Ensure these are set in `php.ini` or `.htaccess` for large ZIP uploads:

```ini
upload_max_filesize = 50M
post_max_size       = 55M
max_execution_time  = 120
memory_limit        = 256M
```

---

## 7. Data Flow: ThemePack Record Lifecycle

```
[Studio Upload]
   ThemePack::create({
     uuid:        generated,
     name:        from meta or pack.json,
     site_id:     NULL  (platform-level),
     is_public:   false (draft),
     is_active:   true,
     tags:        [..., 'studio-import'],
     pack_data:   { global_styles, headers, footers, pages, templates, demo_data }
   })

[Admin Review]
   → /admin/studio-imports/{pack}
   → Preview contents

[Publish]
   ThemePack::update({ is_public: true })
   ThemeMarketplace::create({
     theme_pack_uuid: pack.uuid,
     name:            pack.name,
     price:           0 (or custom),
     is_active:       true
   })

[Tenant Install]
   → Tenant browses /admin/theme-marketplace
   → Purchases or gets free license
   → ThemeLicense::grant()
   → Tenant installs pack to their canvas
```

---

## 8. Security Invariants

1. `site_id = null` — Studio-imported packs are platform-level, never tenant-scoped at import time.
2. `is_public = false` — All imports start as drafts; admin must explicitly publish.
3. Bearer token is hashed with `hash_equals()` — timing-safe comparison.
4. HMAC signature uses a 1-hour sliding window (current + previous hour).
5. ZIP size limit is 50MB — prevents DoS via large uploads.
6. Rate limit: 30 req/min on studio endpoints.
