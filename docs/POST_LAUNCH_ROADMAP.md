# POST_LAUNCH_ROADMAP
> SaaS Platform — Post-Launch Feature Roadmap  
> Base: RC1 (2026-06-22)  
> Scope: Future sprints only. No implementation in this document.

---

## H01 — Two Theme Systems Bridge (HIGH)

**Status:** DEFERRED BY DESIGN  
**Background:** Two active theme systems exist — the legacy Theme engine (ThemeProfile/ThemeTokenRenderer) and the new Canvas-based Theme Builder V3. They are independent. A tenant can activate either but not both simultaneously.

**What's needed:**
- Migration utility: convert a legacy ThemeProfile into a Canvas ThemePack
- `LegacyThemeBridgeService::convertToCanvas(ThemeProfile $profile): ThemePack`
- Admin UI: "Migrate to Canvas Builder" button on legacy theme detail page
- Preserve all design tokens (colors, fonts, spacing) and reconstruct as Canvas blocks
- Estimated effort: 2 sprints

**Success criteria:**
- Any existing legacy theme can be migrated to Canvas in one click
- All 8 ThemeProfile presets (Ocean Teal, Executive Gold, etc.) have Canvas equivalents

---

## Collaborative Editing (MEDIUM)

**Background:** GAP-L10 — Canvas editor has no conflict detection. Two admin users editing simultaneously can overwrite each other.

**What's needed:**
- `canvas_editor_locked_by` + `canvas_editor_locked_at` columns in `settings` table (or dedicated table)
- On `canvasEdit()`: check if another user holds the lock → redirect with warning toast
- Auto-release lock: on `canvasPublish()`, `canvasDiscard()`, or 30-min TTL
- Optional: upgrade to WebSocket-based collaborative editing (Reverb/Soketi) for real-time cursor presence

**Phases:**
1. **Phase CE-1 (Lock Model):** Simple pessimistic locking — 1 sprint
2. **Phase CE-2 (Realtime Presence):** WebSocket cursor + live block highlights — 2 sprints

**Success criteria:**
- Second user sees "در حال ویرایش توسط [نام]" message and is blocked from editing
- Lock auto-releases after 30 minutes of inactivity

---

## Advanced Analytics (MEDIUM)

**Background:** Basic analytics exist (`AnalyticsDashboardController` + Chart.js). Platform-level and tenant-level insights need to go deeper.

**What's needed:**
- **Platform Analytics:** MRR trend chart, churn cohort analysis, new-site-per-day funnel, subscription plan distribution
- **Tenant Analytics:** Per-page view heatmap, conversion funnel (visit → enroll → complete), product view-to-purchase rate
- **Education Analytics:** Course completion rate per course, average time-to-complete, dropout point detection
- **Theme Analytics:** Which blocks are most used, which pages have highest engagement
- **Scheduled Reports:** Weekly email/SMS digest of top KPIs to platform admin

**Phases:**
1. **Phase ANA-1 (Platform KPI):** MRR / churn / new sites — 1 sprint
2. **Phase ANA-2 (Tenant Heatmap):** Page-level analytics with session replay stub — 2 sprints
3. **Phase ANA-3 (Education + Theme):** Completion + block usage — 1 sprint

---

## Marketplace Expansion (MEDIUM)

**Background:** Marketplace has 25+ themes across 15 categories. Next growth phase:

### Marketplace Expansion Items

| Item | Description | Effort |
|------|-------------|--------|
| ME-1 | Third-party theme submission portal (external designers can submit) | 2 sprints |
| ME-2 | Theme rating and review system (stars + text review per tenant) | 1 sprint |
| ME-3 | Theme collections / curated lists (e.g. "Best for Restaurants") | 1 sprint |
| ME-4 | Marketplace revenue sharing: 70/30 split to external designers | 2 sprints |
| ME-5 | Automated theme screenshot generation (headless Playwright) | 1 sprint |
| ME-6 | Seasonal / trending themes feed (featured rotation by admin) | 0.5 sprint |

**Priority order:** ME-5 → ME-2 → ME-3 → ME-1 → ME-4 → ME-6

---

## AI Enhancements (MEDIUM)

**Background:** AI is deeply embedded in the platform (Education module, Content Assistant, Theme Generator, RE pipeline, LANG module). Next layer:

### AI Enhancement Items

| Item | Description | Priority |
|------|-------------|----------|
| AI-1 | SSE streaming for theme generation (real-time block-by-block preview) | High |
| AI-2 | AI page layout recommender (suggest block order based on site type) | High |
| AI-3 | AI SEO audit (analyze published pages, suggest meta improvements) | Medium |
| AI-4 | AI customer support auto-reply draft (ticket assistant → auto-draft) | Medium |
| AI-5 | Education content quality grader (score AI-generated articles before review) | Medium |
| AI-6 | AI-powered CRM lead scoring (predict conversion likelihood) | Low |
| AI-7 | Hallucination reporter UI for AI article reviews | Low |
| AI-8 | Multi-provider fallback chain (Claude → OpenAI → DeepSeek → local) | Medium |

**Priority order:** AI-1 → AI-2 → AI-8 → AI-3 → AI-5 → AI-4 → AI-6 → AI-7

**AI-1 detail:**
```
# SSE streaming endpoint
POST /admin/theme-builder/ai-generate/stream
Content-Type: text/event-stream

data: {"block": "hero", "status": "generating"}
data: {"block": "hero", "status": "done", "html": "..."}
data: {"block": "features", "status": "generating"}
```

---

## Infrastructure Roadmap

| Item | Description | Priority |
|------|-------------|----------|
| INFRA-SSL | ArvanCloud CDN real SSL activation (replace self-signed) | Critical — do immediately |
| INFRA-BACKUP | Offsite backup (S3-compatible) for database dumps | High |
| INFRA-MULTI | Multi-node VPS with Redis Sentinel (HA) | Medium |
| INFRA-CDN | Static asset CDN (JS/CSS/images) | Medium |
| INFRA-OPT | DB query optimizer pass (add missing indexes KI-003) | Medium |

---

## Mobile App Roadmap

| Item | Description | Priority |
|------|-------------|----------|
| M-APP-1 | Customer portal mobile view (subscriptions, lifecycle status) | High |
| M-APP-2 | Push notifications for lifecycle events (grace/suspended) | High |
| M-APP-3 | Education module mobile learner UI | Medium |
| M-APP-4 | AppImage packaging (Linux desktop) M2.1-e | Low |
| M-APP-5 | System tray icon assets M2.1-f | Low |

---

## Technical Debt Paydown

| ID | Item | Effort | Priority |
|----|------|--------|----------|
| KI-003 | Add DB indexes on `site_id` columns across all tables | 1 day | Medium |
| TD-003 | Retrofit Forms module frontend to canonical components | 2 sprints | Low |
| M-01 | `education:validate` EDU-17 component checks | 0.5 day | Low |
| TW-003 | IP allowlist on `/health/release` endpoint | 0.5 day | Medium |

---

## Sprint Priority Queue (Recommended Order)

| # | Sprint | Value | Effort |
|---|--------|-------|--------|
| 1 | INFRA-SSL (ArvanCloud real SSL) | Critical UX | 1 day |
| 2 | H01 Theme Bridge Phase 1 (convert legacy → canvas) | Unblocks migrations | 2 sprints |
| 3 | AI-1 SSE Streaming Theme Gen | Revenue driver | 1 sprint |
| 4 | ME-5 Automated marketplace screenshots | Marketplace quality | 1 sprint |
| 5 | ME-2 Rating + review system | Social proof | 1 sprint |
| 6 | ANA-1 Platform KPI dashboard | Operator insight | 1 sprint |
| 7 | CE-1 Canvas locking | Data safety | 1 sprint |
| 8 | AI-2 Layout recommender | AI differentiation | 1 sprint |
| 9 | KI-003 DB index migration | Performance | 1 day |
| 10 | ANA-2 Tenant heatmap | Tenant retention | 2 sprints |

---

## NOT IN ROADMAP (Deferred Indefinitely)

- GAP-M06 Admin accessible during suspension — intentional documented behavior
- FI-001 WebSocket realtime forms collaboration — depends on Reverb infrastructure decision
- Firebase FCM (TD-005) — Reverb + Redis covers push notifications adequately

---

*POST_LAUNCH_ROADMAP — generated 2026-06-22 for RC1 baseline*
