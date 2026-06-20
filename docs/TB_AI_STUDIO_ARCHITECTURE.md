# TB-AI Studio — Architecture Document

> Version: 1.0  
> Date: 2026-06-20  
> Status: CERTIFIED

---

## 1. Overview

**TB-AI Studio** is a standalone local PHP web application that runs on the developer's machine.  
It generates professional TB3-compatible Theme Packs using multiple AI providers and uploads them to the SaaS Platform.

**Key principle:** No AI credentials are ever stored on the production VPS.  
All AI generation happens locally. Only the final Theme Pack ZIP is transmitted to the server.

---

## 2. System Context

```
┌─────────────────────────────────────────────────┐
│  DEVELOPER MACHINE                              │
│                                                 │
│  ┌─────────────────────────────────────────┐   │
│  │         TB-AI Studio (PHP)              │   │
│  │                                         │   │
│  │  Input    →  Analyzer  →  Generator     │   │
│  │  (4 modes)   (signals)    (AI call)     │   │
│  │                              ↓          │   │
│  │              ZipExporter (theme.zip)    │   │
│  │                              ↓          │   │
│  │              SaasPlatformUploader       │   │
│  └───────────────────┬─────────────────────┘   │
│                      │ HTTPS POST /api/v1/studio/import
└──────────────────────┼──────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────┐
│  VPS (87.107.12.81:9011)                        │
│                                                  │
│  ┌──────────────────────────────────────────┐   │
│  │  SaaS Platform (Laravel)                 │   │
│  │                                          │   │
│  │  AuthenticateStudioApi (middleware)      │   │
│  │  ThemePackStudioController               │   │
│  │    ├─ import()   → ThemePack record      │   │
│  │    ├─ validate() → format check          │   │
│  │    ├─ preview()  → content summary       │   │
│  │    ├─ install()  → is_public=true        │   │
│  │    └─ publish()  → ThemeMarketplace      │   │
│  └──────────────────────────────────────────┘   │
│                       ↓                         │
│  Admin Panel → /admin/studio-imports            │
│  Marketplace → /admin/theme-marketplace         │
│  Customer    → /admin/theme-marketplace (buy)   │
└──────────────────────────────────────────────────┘
```

---

## 3. Directory Structure

```
c:\laragon\www\tb-ai-studio\
│
├── index.php                    ← Main entry point + router
├── config.php                   ← .env loader + config array
├── composer.json                ← Dependencies (Guzzle)
├── .env.example                 ← Configuration template
├── .env                         ← Local secrets (not committed)
│
├── src/
│   ├── Providers/
│   │   ├── ProviderInterface.php    ← complete() + vision() contract
│   │   ├── ProviderFactory.php      ← creates provider from config
│   │   ├── ClaudeProvider.php       ← Anthropic Claude API
│   │   ├── OpenAiProvider.php       ← OpenAI GPT-4o API
│   │   ├── DeepSeekProvider.php     ← DeepSeek chat API
│   │   └── CustomProvider.php       ← Any OpenAI-compatible API
│   │
│   ├── Analyzer/
│   │   ├── HtmlAnalyzer.php         ← DOM parsing, signal extraction
│   │   ├── SectionDetector.php      ← Maps signals → section list
│   │   └── VisionAnalyzer.php       ← Screenshot → AI vision → signals
│   │
│   ├── Generator/
│   │   ├── ThemePackGenerator.php   ← AI prompt → theme pack JSON
│   │   └── ZipExporter.php          ← pack JSON → ZIP file
│   │
│   └── Uploader/
│       └── SaasPlatformUploader.php ← ZIP → platform API
│
└── views/
    ├── layout.php               ← HTML shell, Tailwind CDN, Alpine.js
    └── home.php                 ← 3-step Alpine.js UI (all steps inline)
```

---

## 4. AI Provider Layer

### 4.1 Provider Interface

```php
interface ProviderInterface {
    public function isAvailable(): bool;
    public function getLabel(): string;
    public function supportsVision(): bool;
    public function complete(string $system, string $user): string;
    public function vision(string $system, string $userText, string $base64Image, string $mimeType): string;
}
```

### 4.2 Provider Comparison

| Provider    | Model Default     | Vision | Chat | Notes |
|-------------|-------------------|--------|------|-------|
| **Claude**  | claude-sonnet-4-6 | ✓      | ✓    | Best for Persian content + Vision |
| **OpenAI**  | gpt-4o            | ✓      | ✓    | Strong alternative |
| **DeepSeek**| deepseek-chat     | ✗      | ✓    | Cost-effective for text generation |
| **Custom**  | configurable      | ✗      | ✓    | Ollama, Groq, LM Studio, etc. |

### 4.3 Provider Selection

Set `AI_PROVIDER` in `.env`:

```env
AI_PROVIDER=claude     # or: openai | deepseek | custom
```

The `ProviderFactory::create($config)` reads this and instantiates the correct adapter.

---

## 5. Input Modes

### 5.1 URL Mode
```
URL → HTTP GET (Guzzle) → HTML → DOMDocument parse
   → HtmlAnalyzer::analyze() → page signals
   → SectionDetector::detectFromSignals() → section list
```

Extracts: title, description, h1/h2/h3, nav links, footer links, theme-color meta, OG image, inline colors.

### 5.2 Screenshot Mode
```
Image file → base64 encode
   → VisionAnalyzer::analyze() → ProviderInterface::vision()
   → Claude/OpenAI Vision API → JSON response
   → SectionDetector::detectFromVisionResult() → section list
```

Falls back to text-only analysis if provider doesn't support vision (DeepSeek, Custom).

### 5.3 HTML Mode
```
HTML file upload OR textarea paste
   → HtmlAnalyzer::analyze() → page signals (same as URL)
   → SectionDetector::detectFromSignals() → section list
```

### 5.4 Prompt Mode
```
Persian/English description text
   → SectionDetector::detectFromPrompt() → keyword matching → section list
   → ThemePackGenerator receives description as context
```

---

## 6. Theme Pack Generation

### 6.1 Pipeline

```
section keys (user selection)
   + page signals (from Step 1)
   + color hints (extracted from source)
   → ThemePackGenerator::generate()
      → buildSystemPrompt() + buildUserPrompt()
      → ProviderInterface::complete() → AI response
      → tryParseJson() → pack array
      → [fallback] buildFallback() if JSON invalid
      → normalize() → canonical structure
   → ZipExporter::export() → theme-pack-{name}.zip
```

### 6.2 Generated ZIP Structure

```
theme-pack-{name}-{uuid}.zip
├── manifest.json          ← machine-readable metadata
├── pack.json              ← full ThemePack export (platform-compatible)
├── templates/
│   ├── template_0.json    ← individual section templates
│   ├── template_1.json
│   └── ...
└── pages/
    ├── home.json          ← individual page data
    └── ...
```

### 6.3 ThemePack JSON Schema (pack.json)

```json
{
  "uuid": "...",
  "name": "Theme Name",
  "description": "...",
  "version": "1.0.0",
  "author": "TB-AI Studio",
  "license_type": "free",
  "tags": ["ai-generated", "tb-ai-studio"],
  "includes": ["global_styles","headers","footers","pages","templates"],
  "pack_data": {
    "global_styles": {
      "primary_color": "#3b82f6",
      "secondary_color": "#8b5cf6",
      "accent_color": "#f59e0b",
      "background_color": "#ffffff",
      "text_color": "#111827",
      "font_family": "Vazirmatn",
      "border_radius": "md",
      "direction": "rtl"
    },
    "responsive_settings": {
      "desktop": { "base_font_size": "16px", "container_max_width": "1280px" },
      "tablet":  { "base_font_size": "15px", "container_max_width": "768px" },
      "mobile":  { "base_font_size": "14px", "container_max_width": "100%" }
    },
    "headers": [...],
    "footers": [...],
    "pages":   [...],
    "templates": [...],
    "demo_data": { "site_name": "...", "tagline": "..." }
  },
  "marketplace": {},
  "exported_at": "2026-06-20T...",
  "exporter": "tb-ai-studio",
  "format": "theme-pack-v1"
}
```

---

## 7. Section Detection

11 sections are supported, each with an auto-enable rule:

| Section Key    | Auto-enabled when...                       | Default |
|----------------|-------------------------------------------|---------|
| `header`       | Always                                    | ✓       |
| `hero`         | Always                                    | ✓       |
| `features`     | Always                                    | ✓       |
| `services`     | `hasServices` signal detected             | ✗       |
| `pricing`      | `hasPricing` signal detected              | ✗       |
| `faq`          | `hasFaq` signal detected                  | ✗       |
| `testimonials` | `hasTestimonials` signal detected         | ✗       |
| `blog`         | `hasBlog` signal detected                 | ✗       |
| `products`     | `hasProducts` signal detected             | ✗       |
| `cta`          | Always                                    | ✓       |
| `footer`       | Always                                    | ✓       |

User can override any selection in Step 2 UI.

---

## 8. Upload to SaaS Platform

### 8.1 Authentication

The uploader sends:
```
Authorization: Bearer {SAAS_PLATFORM_API_KEY}
X-Studio-Signature: {HMAC-SHA256 of file hash + timestamp window}
X-Studio-Version: 1.0
```

The platform middleware (`AuthenticateStudioApi`) validates the Bearer key against `STUDIO_API_KEY` env var.

### 8.2 Upload Flow

```
SaasPlatformUploader::upload(zipPath, meta)
   → POST /api/v1/studio/import
   → multipart: pack (ZIP file) + meta (JSON)
   → Response: { pack_uuid, preview_url, install_url }
```

### 8.3 Post-Upload Actions (via Studio UI)

| Action  | Endpoint                          | Effect |
|---------|-----------------------------------|--------|
| Preview | GET /api/v1/studio/preview/{uuid} | Returns pack summary |
| Install | POST /api/v1/studio/install/{uuid}| Sets is_public=true |
| Publish | POST /api/v1/studio/publish/{uuid}| Creates marketplace listing |

---

## 9. Configuration Reference

### TB-AI Studio (.env)

| Variable                  | Required | Description |
|---------------------------|----------|-------------|
| `AI_PROVIDER`             | ✓        | claude \| openai \| deepseek \| custom |
| `CLAUDE_API_KEY`          | If Claude | Anthropic API key |
| `CLAUDE_MODEL`            | No       | Default: claude-sonnet-4-6 |
| `OPENAI_API_KEY`          | If OpenAI | OpenAI API key |
| `OPENAI_MODEL`            | No       | Default: gpt-4o |
| `DEEPSEEK_API_KEY`        | If DeepSeek | DeepSeek API key |
| `DEEPSEEK_BASE_URL`       | If DeepSeek | Default: https://api.deepseek.com/v1 |
| `CUSTOM_API_KEY`          | If Custom | Custom provider key |
| `CUSTOM_BASE_URL`         | If Custom | Custom provider URL |
| `CUSTOM_MODEL`            | If Custom | Custom model name |
| `SAAS_PLATFORM_URL`       | For upload | Platform URL (e.g. https://panel.saas.ir) |
| `SAAS_PLATFORM_API_KEY`   | For upload | Must match STUDIO_API_KEY on server |
| `SAAS_PLATFORM_STUDIO_SECRET` | Optional | HMAC secret for signature verification |
| `STUDIO_MAX_TOKENS`       | No       | Default: 8000 |
| `STUDIO_TIMEOUT`          | No       | Default: 90 seconds |
| `STUDIO_DEFAULT_LANGUAGE` | No       | Default: fa (Persian) |
| `STUDIO_SAFE_MODE`        | No       | Default: true (no source copying) |

### SaaS Platform (.env additions)

| Variable           | Description |
|--------------------|-------------|
| `STUDIO_API_KEY`   | API key for TB-AI Studio upload authentication |
| `STUDIO_SECRET`    | Optional HMAC secret for ZIP signature verification |

---

## 10. Security Model

1. **No AI keys on VPS** — All provider keys stay on developer machine only.
2. **Bearer token auth** — Studio API endpoints require `STUDIO_API_KEY` that matches server.
3. **Optional HMAC** — ZIP signature prevents replay attacks and ensures file integrity.
4. **Rate limiting** — Studio API limited to 30 req/min.
5. **ZIP validation** — All uploaded ZIPs are validated before import.
6. **Safe mode** — Generator only creates original placeholder content, never copies source.
7. **Platform isolation** — Imported packs have `site_id=null` (platform-level, not tenant).

---

## 11. Local Installation

```bash
# 1. Clone or extract TB-AI Studio
cd c:\laragon\www\tb-ai-studio

# 2. Install dependencies
composer install

# 3. Configure
cp .env.example .env
# Edit .env with your API keys

# 4. Start web server (Laragon or PHP built-in)
php -S localhost:8888

# 5. Open in browser
# http://localhost:8888
```
