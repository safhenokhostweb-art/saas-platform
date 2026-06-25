# CLAUDE MASTER CONTEXT
> Handover package for AI agents. Read this first, then reference linked docs.
> Generated: 2026-06-24 | Line limit: ≤1500

---

## 1. PROJECT IDENTITY

| Field | Value |
|-------|-------|
| Name | SaaS Platform |
| Stack | Laravel 10, PHP 8.4, MySQL 8, Redis 7, Tailwind CSS v4, Alpine.js 3.14+, Flutter 3.x |
| Repos | `saas-platform` (admin/API) + `customer-site` (tenant-facing) |
| Package ns | `saas-platform/modules-*` |
| Tenant | Domain-based `site_id` scoping |
| State | **LAUNCH FREEZE ACTIVE** (2026-06-22) — bugs only, no new features |
| Latest fix | CANVAS_THEME_BUGFIX (2026-06-23) — 2 critical canvas bugs resolved |

**Full story:** `docs/project-memory/PROJECT_STATE.md`

---

## 2. QUICK START — SESSION RESUMPTION

If context lost, read in this exact order:
1. `docs/project-memory/HANDOVER.md` — complete state (784 lines)
2. `docs/project-memory/PROJECT_STATE.md` — phase/sprint (213 lines)
3. `docs/project-memory/OPEN_TASKS.md` — what's pending (895 lines)
4. `docs/AI_CONTEXT/PROJECT_KNOWLEDGE_GRAPH.md` — codebase map
5. `docs/AI_CONTEXT/PROJECT_MEMORY_INDEX.md` — doc index

---

## 3. CURRENT STATE SUMMARY

### Status: POST_RC1_BUGFIX — LAUNCH FREEZE ACTIVE

**Last session (2026-06-23):** 2 critical canvas theme bugs fixed:
- Bug 1: Deactivated canvas → empty page (blocks→sections normalization)
- Bug 2: Theme install → 3600s cache delay (direct DB read + 60s TTL)

**Deployed fixes to production (87.107.12.81):**
- customer-site-worker FATAL → RUNNING
- PLATFORM_URL http→https on customer-site
- LOG_LEVEL debug→error on customer-site
- VITE_APP_URL corrected
- deploy.sh hardened (FATAL worker restart)

**Previous phase:** PRE_LAUNCH_POLISH_AND_HARDENING — 6 phases DONE:
1. Customer UX (89→92) → `docs/PRE_LAUNCH_CUSTOMER_UX_REPORT.md`
2. Admin UX (90→93) → `docs/PRE_LAUNCH_ADMIN_UX_REPORT.md`
3. SSL Hardening → `docs/SSL_HARDENING_REPORT.md`
4. Offsite Backup (CRITICAL gap) → `docs/OFFSITE_BACKUP_AUDIT.md`
5. Monitoring (3 gaps fixed) → `docs/MONITORING_HARDENING_REPORT.md`
6. Launch Day Runbook → `docs/LAUNCH_DAY_RUNBOOK.md`

**Pre-launch actions remaining (non-code):**
- ArvanCloud CDN activation (DevOps)
- Manual DB backup offsite copy before DNS cutover

### RC1 Status: RELEASE_CANDIDATE_1 — All 15 systems AUDIT_PASS
- `docs/RELEASE_CANDIDATE_1.md` — RC1 identity, audit results, module inventory
- `docs/GO_LIVE_CHECKLIST.md` — pre-launch + launch-day sequence + rollback
- `docs/POST_LAUNCH_ROADMAP.md` — H01, Collaborative Editing, Analytics, Marketplace, AI
- `docs/LAUNCH_FREEZE_RULES.md` — full freeze policy + roadmap gate

---

## 4. BUSINESS FLOW GAPS — ALL RESOLVED

**Batch 1 (C01-C05):** 5 critical blockers — ALL RESOLVED
**Batch 2 (H04-H07, M01-M04, M07-M09):** 11 gaps — ALL RESOLVED
**Batch 3 (M10, L01-L03, L05-L06, L08-L09):** 8 gaps — ALL RESOLVED

**Deferred by design:**
- GAP-H01: Two theme systems without bridge (post-launch)
- GAP-M06: Admin panel accessible during suspension (documented behavior)
- GAP-L04: Campaign stuck recovery (resolved in earlier session)

**Full detail:** `docs/BUSINESS_FLOW_GAP_ANALYSIS.md`

---

## 5. KEY COMPLETED MODULES

### SMS Campaign Manager (customer-site, 2026-06-21)
- Campaign lifecycle: draft→scheduled→running→completed/failed/cancelled
- 4 audience types, Kavenegar+Ghasedak providers, delivery tracking
- `customer-site/app/Services/SmsCampaignService.php`
- **Full:** `docs/project-memory/HANDOVER.md` (lines 135-165)

### JSON Import Engine (saas-platform, 2026-06-21)
- 5 sources: Elementor, Bricks, Shopify, Webflow, Custom
- Quality scoring (100 pts), admin UI at `/admin/theme-builder/json-import`
- **Full:** `docs/JSON_IMPORT_ENGINE_CERTIFICATION.md`

### ZIP Template Import Engine (saas-platform, 2026-06-22)
- Safe extraction (100MB/500 file limits, path traversal guard)
- 7-phase orchestrator, 5-dim similarity scoring
- **Full:** `docs/ZIP_IMPORT_ENGINE_ARCHITECTURE.md`

### Playwright Headless Engine (saas-platform, 2026-06-21)
- Multi-viewport (D/T/M), 4-scroll lazy load, SPA hydration, framework detection
- RE accuracy 70-75% → 90%+ for React/Next.js/Vue/Angular
- **Full:** `docs/PLAYWRIGHT_ENGINE_ARCHITECTURE.md`

### Visual Reverse Engineering Pipeline (saas-platform, 2026-06-20)
- 7-phase: DOM Extraction→Layout→Visual→Commerce→Similarity→Reconstruction→Validation
- 6 reference sites, AI enrichment (Claude sonnet-4-6), safe mode
- RE accuracy V2: avg 89.4% (with headless): 96% apple, 90% stripe, 76% tailwindcss
- **Full:** `docs/VISUAL_REVERSE_ENGINEERING_ARCHITECTURE.md`

### Installment/Purchase System (saas-platform + customer-site, 2026-06-13)
- `installment_plans`, `installment_orders`, `installment_payments` tables
- Compound interest, admin CRUD, customer panel
- **Full:** `docs/project-memory/HANDOVER.md` (lines 438-471)

### AI Content Translation (saas-platform, 2026-06-13)
- `content_translations` table (hash check) + `translation_queue`
- Claude Haiku-4-5, lazy queue, hash invalidation
- `SetLocale` middleware + `language-switcher` component

### Smart Image Upload (customer-site, 2026-06-13)
- `SmartImageUploadService` — 15 contexts, WebP GD, AI hook
- `smart-image-picker` Blade component (drag-drop, XHR progress)

### Lifecycle System (saas-platform, 2026-06-19)
- 5 states: active→grace_period→suspended→archived→deleted
- State machine + ZIP archive + SMS notifications
- **Full:** `docs/CUSTOMER_SITE_LIFECYCLE_ARCHITECTURE.md`

### Resource Governance (saas-platform, 2026-06-19)
- 4 tables: site_resource_usage/limits/bandwidth_usage/storage_snapshots
- Dashboard at `/admin/resource-governance`
- **Full:** `docs/RESOURCE_GOVERNANCE_ARCHITECTURE.md`

### Monitoring & Health Center (saas-platform, 2026-06-15)
- 6 migrations, 5 models, 7 services
- Incident lifecycle: open→investigating→resolved→closed
- **Full:** `docs/project-memory/HANDOVER.md` (lines 380-396)

### Date Localization (both repos, 2026-06-20)
- 72 findings, 57 CRITICAL/HIGH fixed across 46 files
- `diffForHumansFaStatic()` — Persian relative time
- **Full:** `docs/DATE_LOCALIZATION_FIX_REPORT.md`

---

## 6. PLATFORM RULES (from .clinerules)

### Frontend Standards
- **Icons:** SVG with `w-5 h-5 flex-shrink-0` (default), `w-4` (small buttons), `w-6` (headers)
- **Fields:** `px-4 py-3 rounded-xl` | **Cards:** `p-6 rounded-2xl shadow-sm`
- **Buttons:** Primary = `bg-gradient-to-r from-indigo-600 to-purple-600`
- **Gaps:** `gap-4` or `gap-6` | **Sections:** `mb-8`
- **Colors:** Primary=indigo-600, Success=emerald-600, Danger=red-600, Warning=amber-500
- **No Bootstrap, no jQuery, no raw CSS** — only Tailwind CSS v4 + Alpine.js

### Backend Standards
- Controllers use Service classes (no direct queries)
- Validation in FormRequest (not controller)
- Models: `SoftDeletes, HasUuid, HasUserStamps` | `protected $fillable = []` | `protected $casts = []`
- Type hinting required everywhere
- Error messages in Persian
- Routes with `Route::resource`

### Prohibitions
- ❌ `DB::table()` in controllers | ❌ Queries in controllers
- ❌ `$guarded` in models | ❌ Missing type hints
- ❌ `dd()`/`var_dump()` in final code | ❌ English error messages

---

## 7. INFRASTRUCTURE

| Component | Detail |
|-----------|--------|
| Production server | 87.107.12.81 |
| Web server | Nginx |
| PHP | 8.4 |
| Database | MySQL 8 (remote: 87.107.12.81:3306) |
| Cache | Redis 7 |
| Queue | Horizon (supervisor-managed) |
| CDN | ArvanCloud (pending activation) |
| SSL | Let's Encrypt (production), ArvanCloud planned |
| Mobile | Flutter 3.x (Android + Windows) |
| OTA | Self-hosted update server |

**Full architecture:** `docs/infrastructure/DEPLOYMENT_ARCHITECTURE.md`
**Health:** `/health/ready` — checks cache, DB, queue, scheduler

---

## 8. REPO STRUCTURE (top-level)

### saas-platform (admin/API)
```
app/
  Contracts/       — Service + Repository interfaces
  Exceptions/      — Custom exceptions
  Http/
    Controllers/   — Web + API controllers
    Requests/      — Form validation requests
  Models/          — Eloquent models
  Repositories/    — DB operations only
  Services/        — Business logic
    ThemeBuilder/  — RE, import, marketplace
  Traits/          — HasUuid, HasUserStamps, ApiResponse
packages/
  saas-platform/   — Module packages (core-ui, modules-*)
resources/views/   — Blade templates
docs/              — All documentation
```

### customer-site (tenant-facing)
```
app/
  Http/
    Controllers/   — Front + Admin controllers
    Middleware/     — Tenant context, locale
  Models/          — Tenant models
  Services/        — Business logic
resources/views/   — Customer-facing blades
docs/              — Customer-site docs
```

---

## 9. KNOWLEDGE GRAPH REFERENCE

See `docs/AI_CONTEXT/PROJECT_KNOWLEDGE_GRAPH.md` for complete:
- Feature → Controller → Service → Model → DB → Documentation mapping
- All modules with file paths

---

## 10. MEMORY FILE INDEX

See `docs/AI_CONTEXT/PROJECT_MEMORY_INDEX.md` for:
- All 16 core memory files with line counts and last update
- All launch/pre-launch/security/architecture files
- Fast-find table for 200+ documents

---

## 11. AI HANDOVER PROMPT

See `docs/AI_CONTEXT/AI_HANDOVER_PROMPT.md` — paste this to hand off to another AI:
- Compact prompt (≤300 lines)
- Contains: project identity, current state, 5 critical rules, session resume sequence, open actions
- Ready to copy-paste into any AI chat

---

## 12. KNOWN ISSUES — ALL RESOLVED (2026-06-06+)

| Issue | Severity | Status | Resolution |
|-------|----------|--------|------------|
| CANVAS-BUG-01 | CRITICAL | RESOLVED 2026-06-23 | blocks→sections normalization |
| CANVAS-BUG-02 | CRITICAL | RESOLVED 2026-06-23 | All 4 settings + direct DB read + cache forget |
| RE-FP-003 | HIGH | RESOLVED 2026-06-22 | nav_labels injection |
| RE-FP-007 | MEDIUM | RESOLVED 2026-06-22 | breakpoints in global_styles |
| RE-FP-004 | MEDIUM | RESOLVED 2026-06-22 | TAILWIND_COLOR_MAP + mergeTailwindColors |
| KI-007 | CRITICAL | RESOLVED 2026-06-13 | Local icon fallback |
| All GAP-L07 | — | RESOLVED 2026-06-22 | Renewal URL in SMS |
| All BFA gaps | — | ALL RESOLVED | See HANDOVER.md |

**Full:** `docs/project-memory/KNOWN_ISSUES.md`

---

## 13. LAUNCH FREEZE POLICY

| Category | Rule |
|----------|------|
| ✅ Allowed | BUG FIX, UX IMPROVEMENT, PERFORMANCE, SECURITY, DOCUMENTATION, MONITORING |
| ❌ Prohibited | New features, new modules, new DB tables, new architecture, new services |
| 🛑 Approval required | Route changes, middleware changes, config changes, composer/npm changes |
| 🚨 Emergency | Hotfix bypass with customer notify within 24h |
| 🚪 Gate | All FEATURE/ARCHITECTURE → `docs/POST_LAUNCH_ROADMAP.md` only |

**Full policy:** `docs/LAUNCH_FREEZE_RULES.md`

---

## 14. POST-LAUNCH ROADMAP

| Priority | Feature | Target |
|----------|---------|--------|
| H01 | Marketplace theme installation from customer-site | Post-launch |
| H02 | Collaborative editing (WebSocket/Canvas) | Post-launch |
| H03 | Operational analytics dashboard | Post-launch |
| H04 | Module marketplace for third-party devs | Post-launch |
| H05 | AI theme generation enhancements | Post-launch |

**Full roadmap:** `docs/POST_LAUNCH_ROADMAP.md`

---

## 15. DOCUMENT INVENTORY

See `docs/AI_CONTEXT/DOCUMENT_INVENTORY.md` for complete catalog of:
- ~320 files in saas-platform/docs/
- ~16 files in customer-site/docs/
- 14 categories with status for each document
- Gap analysis: 99% reusable, no critical documentation gaps
