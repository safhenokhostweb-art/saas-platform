# AI HANDOVER PROMPT
> Copy-paste this entire file to hand off project context to another AI agent.
> Generated: 2026-06-24 | Line limit: ≤300

---

```
You are now working on the SaaS Platform project.

## PROJECT IDENTITY
- Name: SaaS Platform (Laravel 10, PHP 8.4, MySQL 8, Redis 7, Tailwind v4, Alpine.js, Flutter)
- Two repos: `saas-platform` (admin/API) + `customer-site` (tenant-facing)
- Tenant: Domain-based `site_id` scoping
- Working dir: c:\laragon\www\saas-platform (primary) + c:\laragon\www\customer-site

## CURRENT STATE (2026-06-24)
- LAUNCH FREEZE ACTIVE — No new features, no new modules, no new DB tables
- Allowed: BUG FIX | UX IMPROVEMENT | PERFORMANCE | SECURITY | DOCUMENTATION | MONITORING
- Last fix: CANVAS_THEME_BUGFIX (2026-06-23) — 2 critical canvas bugs resolved
- Status: POST_RC1_BUGFIX — RC1 passed (15/15 systems), pre-launch hardening complete
- Launch freeze policy: docs/LAUNCH_FREEZE_RULES.md

## 5 CRITICAL RULES YOU MUST FOLLOW

1. **NEVER create migrations** without checking `Schema::hasColumn()` guards
2. **NEVER use Bootstrap, jQuery, or raw CSS** — only Tailwind CSS v4 + Alpine.js
3. **ALWAYS use Service classes in controllers** — no direct DB::table() or Model:: queries
4. **ALWAYS use FormRequest for validation** — never put rules() in controllers
5. **ALWAYS return errors in Persian** (Farsi) — never English

## FRONTEND STANDARDS
- Icons: `<svg class="w-5 h-5 flex-shrink-0">` (default), w-4 (small), w-6 (headers)
- Fields: `px-4 py-3 rounded-xl` | Cards: `p-6 rounded-2xl shadow-sm`
- Primary button: `bg-gradient-to-r from-indigo-600 to-purple-600`
- Colors: Primary=indigo-600, Success=emerald-600, Danger=red-600, Warning=amber-500
- Gaps: gap-4/gap-6 | Sections: mb-8 | Mobile responsive required

## SESSION RESUME ORDER (if context lost)
1. docs/project-memory/HANDOVER.md — complete state
2. docs/project-memory/PROJECT_STATE.md — phase/sprint
3. docs/AI_CONTEXT/CLAUDE_MASTER_CONTEXT.md — master overview
4. docs/project-memory/OPEN_TASKS.md — pending tasks
5. docs/AI_CONTEXT/PROJECT_KNOWLEDGE_GRAPH.md — codebase map

## KEY COMPLETED MODULES (don't rebuild)
- SMS Campaign Manager — customer-site (2026-06-21)
- JSON Import Engine — saas-platform (2026-06-21)
- ZIP Template Import Engine — saas-platform (2026-06-22)
- Playwright Headless Engine — saas-platform (2026-06-21)
- Visual Reverse Engineering Pipeline (89.4% accuracy) — saas-platform (2026-06-20)
- 5 enterprise modules: Installment, AI Translation, Video Player, Access List, Smart Image
- Lifecycle System (5-state) + Resource Governance — saas-platform (2026-06-19)
- Monitoring & Health Center (MHC) — saas-platform (2026-06-15)
- Date Localization — 57 fixes across 46 files (2026-06-20)

## INFRASTRUCTURE
- Production: 87.107.12.81 (Nginx, PHP 8.4, MySQL 8, Redis 7)
- CDN: ArvanCloud (pending activation)
- Health: /health/ready (cache/DB/queue/scheduler)
- Queue: Horizon (supervisor)
- Mobile: Flutter 3.x (Android + Windows) with OTA update server

## OPEN ACTIONS (non-code)
1. ArvanCloud CDN activation (DevOps)
2. Manual DB backup offsite copy before DNS cutover

## KEY FILES TO REFERENCE
- .clinerules — full platform rules
- docs/LAUNCH_FREEZE_RULES.md — freeze policy
- docs/POST_LAUNCH_ROADMAP.md — future roadmap
- docs/project-memory/KNOWN_ISSUES.md — bug tracker (all resolved)
- docs/RELEASE_CANDIDATE_1.md — RC1 audit results
- docs/GO_LIVE_CHECKLIST.md — launch sequence

## COMPACT REPO STRUCTURE

saas-platform (admin):
  app/Contracts/     — Interfaces
  app/Http/Controllers/ — Web + API
  app/Http/Requests/    — Form validation
  app/Models/           — Eloquent (+SoftDeletes, HasUuid, HasUserStamps)
  app/Repositories/     — DB only
  app/Services/         — Business logic
  app/Services/ThemeBuilder/ — RE, import, marketplace
  packages/saas-platform/    — Module packages
  docs/                     — All documentation

customer-site (tenant):
  app/Http/Controllers/ — Front + Admin
  app/Http/Middleware/   — Tenant context, locale
  app/Models/            — Tenant models
  app/Services/          — Business logic
  resources/views/       — Blades
  docs/                  — Docs

## PROHIBITIONS (violation = system failure)
- ❌ DB::table() in controllers
- ❌ Queries in controllers
- ❌ $guarded in models
- ❌ Missing type hints
- ❌ dd()/var_dump() in final code
- ❌ English error messages
- ❌ Bootstrap or jQuery
- ❌ Non-standard icon sizes

## DOCUMENTATION MAP
All docs at docs/AI_CONTEXT/:
- CLAUDE_MASTER_CONTEXT.md — full overview (~400 lines)
- PROJECT_MEMORY_INDEX.md — fast-find index (~250 lines)
- PROJECT_KNOWLEDGE_GRAPH.md — feature→code→db mapping (~300 lines)
- DOCUMENT_INVENTORY.md — complete catalog (~250 lines)
- MASTER_CONTEXT_AUDIT.md — coverage audit (~100 lines)

Generated: 2026-06-24
