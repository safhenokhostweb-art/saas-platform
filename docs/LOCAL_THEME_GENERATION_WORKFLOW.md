# Local Theme Generation Workflow

> Version: 1.0  
> Date: 2026-06-20  
> Tool: TB-AI Studio (c:\laragon\www\tb-ai-studio)

---

## 1. Why Local Generation?

The production VPS (`87.107.12.81:9011`) has limited or no access to international AI services (Claude, OpenAI, DeepSeek).

**Solution:** Run AI generation entirely on the developer machine.  
Only the final output (a ZIP file) is transmitted to the VPS.

| On Developer Machine | On VPS |
|----------------------|--------|
| AI API keys          | No AI keys |
| URL crawling         | No outbound crawl |
| HTML/screenshot analysis | — |
| AI provider calls    | — |
| Theme Pack generation | Theme Pack storage |
| ZIP export           | ZIP import + validation |
| Upload to platform   | Admin review + publish |

---

## 2. End-to-End Workflow

### Phase 1 — Setup (one-time)

```
1. Clone or download TB-AI Studio to developer machine
   → c:\laragon\www\tb-ai-studio\

2. Install PHP dependencies
   → composer install

3. Configure AI providers
   → copy .env.example to .env
   → set AI_PROVIDER=claude (or openai/deepseek/custom)
   → set CLAUDE_API_KEY=sk-ant-...

4. Configure platform upload (optional, for auto-upload)
   → set SAAS_PLATFORM_URL=https://87.107.12.81:9011
   → set SAAS_PLATFORM_API_KEY=... (must match STUDIO_API_KEY on server)

5. On production server (.env):
   → STUDIO_API_KEY=same-key-as-above
   → STUDIO_SECRET=optional-hmac-secret

6. Start TB-AI Studio
   → php -S localhost:8888 (or use Laragon)
   → Open http://localhost:8888
```

---

### Phase 2 — Theme Analysis (Step 1 in UI)

Choose one of 4 input modes:

#### Mode A: URL Analysis
```
Developer enters target URL (e.g., https://competitor.com)
    ↓
TB-AI Studio crawls the URL (HTTP GET with browser User-Agent)
    ↓
HtmlAnalyzer extracts:
  - Page title, meta description, keywords
  - H1, H2, H3 headings
  - Navigation links, footer links
  - Meta theme-color, OG image
  - Inline style color hints
  - Content signals (hasPricing, hasFaq, hasTestimonials...)
    ↓
SectionDetector maps signals → enabled/disabled sections
```

#### Mode B: Screenshot Analysis
```
Developer takes a screenshot of any website
    ↓
Uploads PNG/JPG to TB-AI Studio
    ↓
VisionAnalyzer sends image (base64) to AI provider vision API
  - Claude: claude-sonnet-4-6 with vision (recommended)
  - OpenAI: gpt-4o with vision
  - DeepSeek: falls back to text-only analysis
    ↓
AI returns JSON with detected sections, colors, style
    ↓
SectionDetector::detectFromVisionResult()
```

#### Mode C: HTML Analysis
```
Developer saves a webpage as HTML (or pastes HTML code)
    ↓
Uploads .html file or pastes in textarea
    ↓
HtmlAnalyzer parses DOM (same as URL mode, no HTTP request)
    ↓
SectionDetector maps signals → section list
```

#### Mode D: Prompt Analysis
```
Developer describes the desired website in Persian or English
  Example: "سایت شرکت نرم‌افزاری با رنگ آبی، قیمت‌گذاری، نظرات مشتریان"
    ↓
SectionDetector::detectFromPrompt() — keyword-based section detection
  - Persian/English keywords matched to section types
    ↓
Section list based on keywords found in description
```

**Step 1 Output:** A list of 11 potential sections (header, hero, features, services,
pricing, faq, testimonials, blog, products, cta, footer) with enabled/disabled status.

---

### Phase 3 — Section Selection (Step 2 in UI)

```
Developer reviews detected sections
    ↓
Checks/unchecks sections via interactive UI
    ↓
Edits pack name (e.g., "قالب شرکت نوآوری")
    ↓
Clicks "تولید قالب با هوش مصنوعی"
```

---

### Phase 4 — AI Theme Generation (Step 2 → Step 3)

```
Selected sections + page signals + color hints
    ↓
ThemePackGenerator::generate()
    ↓
BuildSystemPrompt: Rules (RTL, Persian, Vazirmatn, no copying source)
BuildUserPrompt:   Section list + context JSON + global styles JSON
    ↓
ProviderInterface::complete(system, user)
  → Claude API: POST https://api.anthropic.com/v1/messages
  → OpenAI API: POST https://api.openai.com/v1/chat/completions
  → DeepSeek:   POST https://api.deepseek.com/v1/chat/completions
  → Custom:     POST {CUSTOM_BASE_URL}/chat/completions
    ↓
AI returns JSON string
    ↓
tryParseJson() — extract valid JSON from response
  → If valid: normalize() → canonical ThemePack structure
  → If invalid: buildFallback() — PHP-generated placeholder content
    ↓
ZipExporter::export() → /tmp/theme-pack-{name}-{uuid}.zip
```

**Generated Content Includes:**
- `global_styles` — primary/secondary/accent colors, font, direction
- `responsive_settings` — desktop/tablet/mobile breakpoints
- `headers` — navigation header with links, logo, CTA button
- `footers` — footer with columns, links, copyright
- `pages` — home page with 6-10 blocks covering selected sections
- `templates` — one reusable section template per non-structural section
- `demo_data` — site name, tagline

**Safe Mode Guarantees:**
- All text is original Persian placeholder content (never copied from source)
- All images are SVG placeholders (no external image URLs)
- No source code or proprietary assets are included

---

### Phase 5 — Export & Upload (Step 3 in UI)

Developer has two options:

#### Option A: Manual Download → Manual Upload
```
1. Click "⬇ دانلود ZIP قالب"
   → Browser downloads theme-pack-{name}.zip

2. Go to SaaS Platform admin panel
   → /admin/theme-packs → "وارد کردن ZIP"
   → Upload the ZIP file
   → ThemePack record created

3. Go to /admin/studio-imports
   → Review the imported pack
   → Click "انتشار در مارکت‌پلیس"
```

#### Option B: Automatic Upload (if platform configured)
```
1. Click "☁ آپلود به پلتفرم"
   → SaasPlatformUploader::upload()
   → POST /api/v1/studio/import
   → Authorization: Bearer {SAAS_PLATFORM_API_KEY}
   → Response: { pack_uuid, preview_url }

2. Platform auto-creates ThemePack record
   → Tagged with 'studio-import'
   → Status: draft (is_public=false)

3. Developer gets preview URL in UI
   → Clicks to open admin panel
```

---

### Phase 6 — Platform Review & Publish

```
Admin opens /admin/studio-imports
    ↓
Reviews pack: colors, pages, templates, global styles
    ↓
Downloads ZIP for local testing if needed
    ↓
Clicks "انتشار در مارکت‌پلیس"
    ↓
ThemePack.is_public = true
ThemeMarketplace record created (price=0 or custom)
    ↓
Pack appears in /admin/theme-marketplace
    ↓
Tenant browses marketplace → installs pack
    ↓
Pack becomes active theme for tenant site
```

---

## 3. Provider Selection Guide

### When to use Claude
- Best for Persian content generation (native RTL support)
- Use for Screenshot mode (Vision API)
- Most accurate JSON output
- Recommended provider

### When to use OpenAI
- Alternative when Claude is unavailable
- Also supports Vision (GPT-4o)
- Good JSON output quality

### When to use DeepSeek
- Most cost-effective for high-volume generation
- Good for URL/HTML/Prompt modes (no vision)
- Text quality slightly lower than Claude/OpenAI

### When to use Custom
- Ollama (local AI, no API cost, offline)
- Groq (fast inference)
- LM Studio (local)
- Any OpenAI-compatible endpoint

---

## 4. Switching Providers

No code change needed. Edit `.env` and restart:

```env
# Switch to DeepSeek
AI_PROVIDER=deepseek
DEEPSEEK_API_KEY=sk-...

# Switch to OpenAI
AI_PROVIDER=openai
OPENAI_API_KEY=sk-...

# Switch to local Ollama
AI_PROVIDER=custom
CUSTOM_BASE_URL=http://localhost:11434/v1
CUSTOM_MODEL=llama3.2
# CUSTOM_API_KEY= (leave empty for Ollama)
```

---

## 5. Typical Session (10–15 minutes)

| Minute | Action |
|--------|--------|
| 0–2    | Start TB-AI Studio, open http://localhost:8888 |
| 2–3    | Enter target URL or upload screenshot |
| 3–5    | Wait for AI analysis (URL crawl + section detection) |
| 5–6    | Review and adjust section selection |
| 6–10   | AI generates theme pack (90 second timeout) |
| 10–11  | Review result summary |
| 11–12  | Download ZIP or auto-upload to platform |
| 12–15  | Admin reviews in /admin/studio-imports → publish |

---

## 6. Troubleshooting

### AI call times out (90s limit)
- Check internet connection from developer machine
- Try a smaller section selection (fewer sections)
- Switch to DeepSeek (faster response)
- Increase `STUDIO_TIMEOUT` in `.env`

### JSON parsing fails → fallback used
- AI returned invalid JSON (rare with Claude/OpenAI)
- Fallback uses PHP placeholder generator (still produces valid pack)
- Result is usable but less AI-personalized

### Upload to platform fails
- Check `SAAS_PLATFORM_URL` is correct and accessible
- Check `SAAS_PLATFORM_API_KEY` matches `STUDIO_API_KEY` on server
- Check server has `STUDIO_API_KEY` in `.env`
- Check `upload_max_filesize` PHP setting on server (must be ≥ 50M)

### Vision analysis returns minimal data (screenshot mode)
- Provider doesn't support vision → falls back to text analysis
- Use Claude or OpenAI for screenshot mode
- Add notes in the "یادداشت اضافی" field to help the fallback

---

## 7. Architecture Compliance

- **ADR-001** complied: No DB writes inside generation — ZipExporter only writes to `/tmp`
- **ADR-004** complied: Platform import uses `whereNull('site_id')` correctly
- **Key Platform Rule #1**: No `App\` imports in TB-AI Studio (separate project)
- **Key Platform Rule #6**: All generated content is Persian RTL, Vazirmatn font
- **Key Platform Rule #10**: All views are professional — 3-step Alpine.js UI with Tailwind
