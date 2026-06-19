# AI Theme Generator — Runtime Audit

**Date:** 2026-06-20
**Sprint:** TB3 Sprint 8
**Auditor:** Claude Code (automated, code-derived)

---

## 1. Active Provider Configuration

| Setting | Config Key | ENV Variable | Current Default |
|---------|-----------|--------------|-----------------|
| Provider | `services.theme_ai.provider` | `THEME_AI_PROVIDER` | **`gapgpt`** |
| Model | `services.theme_ai.model` | `THEME_AI_MODEL` | **`gpt-4o-mini`** |
| Claude Key | `services.anthropic.key` | `ANTHROPIC_API_KEY` | _(empty)_ |
| GapGPT Key | `services.gapgpt.key` | `GAPGPT_API_KEY` | _(empty)_ |
| GapGPT Base URL | `services.gapgpt.base_url` | `GAPGPT_BASE_URL` | `https://api.gapgpt.app/v1` |

**Source:** `config/services.php` lines 95–107.

**To switch providers:** Set `THEME_AI_PROVIDER=claude` and `ANTHROPIC_API_KEY=sk-ant-...` in the VPS `.env`.

---

## 2. Execution Environment

| Dimension | Value |
|-----------|-------|
| Execution host | **VPS — 87.107.12.81:9011** |
| PHP runtime | PHP-FPM (HTTP worker process) |
| Background jobs | **None — all calls are synchronous** |
| Queue workers | Not used by this module |
| Request timeout | **90 seconds** (both providers) |
| Max tokens | 8000 (generation) / 1024 (vision) |

> **No Queue Jobs exist for theme generation.**
> Confirmed: `app/Jobs/*Theme*.php` — empty glob result.
> Every AI HTTP call blocks the PHP-FPM worker thread for the full response duration.

---

## 3. Runtime Flow — STEP 1: Section Detection (per input type)

### Flow A — URL Input (no AI call)

```
Browser
  └─ POST /admin/theme-builder/ai-generator/detect
       └─ ThemeAiGeneratorController::detect()          [app/Http/Controllers/Admin/ThemeAiGeneratorController.php]
            └─ ThemeAiGeneratorService::fromUrl()        [app/Services/ThemeBuilder/ThemeAiGeneratorService.php:80]
                 ├─ fetchAndExtractUrl()                  → Http::get(url)  ← plain HTTP, NO AI
                 │    └─ DOMXPath / DOMDocument parse
                 │    └─ extract: title, headings, nav items, meta description, price/blog signals
                 └─ ThemeSectionDetectorService::detect() → pure PHP keyword scoring
                      └─ AiGenerationJob saved (STATUS_AWAITING)
                           └─ JSON: { success, job_id, detected_sections }
```

**AI calls made:** 0

---

### Flow B — Screenshot Input (CLAUDE → Vision API; GAPGPT → no API call)

```
Browser
  └─ POST /admin/theme-builder/ai-generator/detect  (multipart/form-data)
       └─ ThemeAiGeneratorController::detect()
            └─ base64_encode(file_get_contents($file->getRealPath()))
            └─ ThemeAiGeneratorService::fromScreenshot(imageData, mimeType, notes)
                 └─ analyzeScreenshot()                [line 370]
                      │
                      ├─ IF provider = claude:
                      │    └─ Http::withHeaders([x-api-key, anthropic-version])
                      │         .timeout(90)
                      │         .post('https://api.anthropic.com/v1/messages', {
                      │             model:      'claude-sonnet-4-6',   ← CLAUDE_VISION_MODEL
                      │             max_tokens: 1024,
                      │             messages: [{ role: user, content: [
                      │                 { type: image, source: { type: base64, media_type, data } },
                      │                 { type: text,  text: <vision prompt> }
                      │             ]}]
                      │         })
                      │         └─ Response: JSON with sections_detected, colors, site_type, ...
                      │         └─ ThemeSectionDetectorService::detectFromAiVisionResult()
                      │
                      └─ IF provider = gapgpt:
                           └─ analyzeScreenshotViaPrompt()  ← NO API CALL
                                └─ returns hardcoded structure:
                                   sections = [header, hero, features, cta, footer]
                                   primary_color = '#3b82f6'
                      └─ AiGenerationJob saved (STATUS_AWAITING)
                           └─ JSON: { success, job_id, detected_sections }
```

**AI calls made:**
- Claude provider: **1 Vision API call** (synchronous, blocks up to 90s)
- GapGPT provider: **0** (hardcoded fallback, no vision support)

---

### Flow C — HTML Upload (no AI call)

```
Browser
  └─ POST /admin/theme-builder/ai-generator/detect  (multipart/form-data)
       └─ ThemeAiGeneratorController::detect()
            └─ file_get_contents($file->getRealPath())
            └─ ThemeAiGeneratorService::fromHtml(htmlContent, notes)
                 └─ extractFromHtml()                  → DOMDocument::loadHTML(), NO AI
                      └─ DOMXPath parse: title, headings, nav, meta, signals
                 └─ ThemeSectionDetectorService::detect() → pure PHP keyword scoring
                      └─ AiGenerationJob saved (STATUS_AWAITING)
                           └─ JSON: { success, job_id, detected_sections }
```

**AI calls made:** 0

---

### Flow D — Prompt / Description (no AI call)

```
Browser
  └─ POST /admin/theme-builder/ai-generator/detect
       └─ ThemeAiGeneratorController::detect()
            └─ ThemeAiGeneratorService::fromPrompt(description, name)
                 └─ ThemeSectionDetectorService::detectFromPrompt(description)
                      └─ builds synthetic pageData from free-text description, NO AI
                      └─ pure PHP keyword scoring
                 └─ AiGenerationJob saved (STATUS_AWAITING)
                      └─ JSON: { success, job_id, detected_sections }
```

**AI calls made:** 0

---

## 4. Runtime Flow — STEP 2: ThemePack Generation (always fires)

```
Browser
  └─ POST /admin/theme-builder/ai-generator/{job}/generate
       └─ ThemeAiGeneratorController::generate()         [ThemeAiGeneratorController.php]
            └─ ThemeAiGeneratorService::generateThemePack(job, selectedKeys, name)
                 ├─ job status → STATUS_GENERATING
                 ├─ inferGlobalStyles()                  → pure PHP color extraction from page_signals
                 └─ callAiForThemePack(name, sections, context, globalStyles)  [line 476]
                      └─ builds system + user prompt (8000-token budget)
                      └─ callAi(system, user)            [line 599]
                           │
                           ├─ IF provider = claude:
                           │    └─ callClaude()           [line 607]
                           │         └─ Http::withHeaders([
                           │                x-api-key:         ANTHROPIC_API_KEY,
                           │                anthropic-version: '2023-06-01',
                           │                content-type:      'application/json'
                           │            ])
                           │            .timeout(90)
                           │            .post('https://api.anthropic.com/v1/messages', {
                           │                model:      this.model,  ← 'claude-sonnet-4-6'
                           │                max_tokens: 8000,
                           │                system:     <system prompt>,
                           │                messages:   [{ role: user, content: <user prompt> }]
                           │            })
                           │            returns: content[0].text
                           │
                           └─ IF provider = gapgpt:
                                └─ callGapGpt()          [line 626]
                                     └─ Http::withHeaders([
                                            Authorization: 'Bearer ' + GAPGPT_API_KEY,
                                            Content-Type:  'application/json'
                                        ])
                                        .timeout(90)
                                        .post('https://api.gapgpt.app/v1/chat/completions', {
                                            model:      this.model,  ← 'gpt-4o-mini'
                                            max_tokens: 8000,
                                            messages: [
                                                { role: system,  content: <system prompt> },
                                                { role: user,    content: <user prompt>   }
                                            ]
                                        })
                                        returns: choices[0].message.content

                      └─ stripJsonFences() + json_decode()
                      └─ IF json invalid → buildFallbackPackData()  ← NO AI, uses ThemePlaceholderService
                      └─ normalizePackData()
                 ├─ buildThemePack()  → ThemePack::create()
                 ├─ createSectionTemplates() → ThemeTemplate::create() × N
                 └─ job status → STATUS_COMPLETE
                      └─ JSON: { success, theme_pack_id, pack_name, summary, result_url }
```

**AI calls made:** **1 generation call** (mandatory, synchronous, blocks up to 90s)

---

## 5. API Call Summary Matrix

| Flow | Phase | Provider=claude | Provider=gapgpt |
|------|-------|----------------|-----------------|
| URL detect | Step 1 | 0 API calls | 0 API calls |
| Screenshot detect | Step 1 | **1** Vision call (`/v1/messages`, 1024 tok) | 0 API calls (hardcoded fallback) |
| HTML detect | Step 1 | 0 API calls | 0 API calls |
| Prompt detect | Step 1 | 0 API calls | 0 API calls |
| Generate ThemePack | Step 2 | **1** text call (`/v1/messages`, 8000 tok) | **1** chat call (`/v1/chat/completions`, 8000 tok) |

**Worst case (Screenshot + Generate, provider=claude):** 2 sequential synchronous API calls, total blocking time up to 180 seconds.

---

## 6. Provider-Specific API Details

### Claude (provider = `claude`)

| Field | Value |
|-------|-------|
| Endpoint (text) | `https://api.anthropic.com/v1/messages` |
| Endpoint (vision) | `https://api.anthropic.com/v1/messages` (same) |
| Model (text) | `claude-sonnet-4-6` |
| Model (vision) | `claude-sonnet-4-6` |
| Auth header | `x-api-key: {ANTHROPIC_API_KEY}` |
| API version header | `anthropic-version: 2023-06-01` |
| Max tokens (gen) | 8000 |
| Max tokens (vision) | 1024 |
| Timeout | 90s |
| Response field | `content[0].text` |

### GapGPT (provider = `gapgpt`, **current default**)

| Field | Value |
|-------|-------|
| Endpoint | `https://api.gapgpt.app/v1/chat/completions` |
| Model | `gpt-4o-mini` |
| Auth header | `Authorization: Bearer {GAPGPT_API_KEY}` |
| Max tokens | 8000 |
| Timeout | 90s |
| Response field | `choices[0].message.content` |
| Vision support | **None** (falls back to hardcoded section list) |

---

## 7. Fallback Safety Net

If the AI returns malformed/non-JSON output:

```
json_decode() throws JsonException
  └─ Log::warning('TB8: AI returned invalid JSON, using fallback')
  └─ buildFallbackPackData(name, sections, globalStyles)
       └─ ThemePlaceholderService::forSection() per enabled section
       └─ returns complete pack_data using Persian placeholder banks
       └─ NO AI call — pure PHP generation
```

The fallback guarantees generation always completes, even with a broken or unavailable AI API.

---

## 8. Key Files

| File | Role |
|------|------|
| `app/Services/ThemeBuilder/ThemeAiGeneratorService.php` | All AI calls (callClaude, callGapGpt, analyzeScreenshot) |
| `app/Http/Controllers/Admin/ThemeAiGeneratorController.php` | HTTP entry point, orchestration |
| `app/Services/ThemeBuilder/ThemeSectionDetectorService.php` | Pure PHP section detection (no AI) |
| `app/Services/ThemeBuilder/ThemePlaceholderService.php` | Pure PHP fallback content generation |
| `app/Models/AiGenerationJob.php` | Job lifecycle tracking |
| `config/services.php` lines 93–107 | Provider routing configuration |

---

## 9. Findings

| ID | Severity | Finding |
|----|----------|---------|
| F-01 | LOW | Generation is fully synchronous — a 90s blocking HTTP worker holds a PHP-FPM slot. Under concurrent usage this will exhaust the worker pool. Mitigation: move `generateThemePack()` to a Queue Job for production scale. |
| F-02 | LOW | GapGPT screenshot detection returns a hardcoded 5-section list (no vision). Users using GapGPT will always see the same 5 default sections regardless of actual screenshot content. |
| F-03 | INFO | The PHP-level constructor fallback to `'claude'` (`config('services.theme_ai.provider', 'claude')`) is shadowed by `config/services.php` which defaults to `'gapgpt'` via `env('THEME_AI_PROVIDER', 'gapgpt')`. The actual default is **gapgpt**. |
| F-04 | INFO | `isEnabled()` returns `false` when the API key is empty — the UI should gate the generate button on this to avoid unhelpful 90s timeouts. |

---

**Verdict:** AUDIT COMPLETE — no CRITICAL or HIGH findings. All API call paths are traceable. Provider switching works correctly via env var.
