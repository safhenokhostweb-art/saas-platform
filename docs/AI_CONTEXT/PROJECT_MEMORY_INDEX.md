# PROJECT MEMORY INDEX
> Fast-find index for all project documents. Links to existing files, no content duplication.
> Generated: 2026-06-24 | Line limit: ≤500

---

## HOW TO USE THIS INDEX

1. Find what you need in the table below
2. Open the linked file for full content
3. Files marked ✅ are stable; files marked ⚠️ may need review

---

## A. CORE MEMORY FILES (docs/project-memory/)

| # | File | Lines | Updated | Area | Priority |
|---|------|-------|---------|------|----------|
| 1 | `HANDOVER.md` | 784 | 2026-06-23 | Complete session state | 🔴 Read first |
| 2 | `PROJECT_STATE.md` | 213 | 2026-06-23 | Phase/sprint | 🔴 Read 2nd |
| 3 | `OPEN_TASKS.md` | 895 | 2026-06-22 | Pending epics/tasks | 🔴 Read 3rd |
| 4 | `KNOWN_ISSUES.md` | 254 | 2026-06-06 | Bug tracker | 🟡 Reference |
| 5 | `ARCHITECTURE.md` | ~200 | Prior | System architecture | 🟡 Reference |
| 6 | `DECISIONS.md` | ~150 | Prior | Architecture decisions | 🟡 Reference |
| 7 | `AUDIT_HISTORY.md` | ~100 | Prior | Audit results log | 🟢 Reference |
| 8 | `CHANGELOG_AI.md` | ~200 | Prior | AI session log | 🟢 Reference |
| 9 | `HANDOVER_EDU.md` | ~100 | Prior | Education module | 🟢 Reference |
| 10 | `HANDOVER_SUBSCRIPTION.md` | ~100 | Prior | Subscription module | 🟢 Reference |
| 11 | `RECOVERY_PROTOCOL.md` | ~50 | Prior | Crash recovery | 🟡 Reference |
| 12 | `HEALTH_ENDPOINT_SECURITY_REPORT.md` | ~50 | Prior | Health endpoint | 🟢 Reference |
| 13 | `INFRA2_AUDIT_REPORT.md` | ~80 | Prior | Infra v2 audit | 🟢 Reference |
| 14 | `MHC_MIGRATION_VALIDATION.md` | ~60 | Prior | MHC migration | 🟢 Reference |
| 15 | `PRODUCTION_MIGRATION_AUDIT.md` | ~70 | Prior | DB migration audit | 🟢 Reference |
| 16 | `PRODUCTION_SAFETY_PHASE_REPORT.md` | ~80 | 2026-06-16 | PROD safety | 🟢 Reference |

---

## B. LAUNCH & RELEASE

| File | Lines | Key Content | Priority |
|------|-------|-------------|----------|
| `LAUNCH_FREEZE_RULES.md` | ~50 | Freeze policy, 5 categories | 🔴 Current |
| `LAUNCH_DAY_RUNBOOK.md` | ~150 | T-60→T+48h sequence | 🔴 Current |
| `GO_LIVE_CHECKLIST.md` | ~80 | Pre-launch + launch day | 🔴 Current |
| `GO_LIVE_VERDICT.md` | ~30 | Go-live verdict | 🟡 Historical |
| `RELEASE_CANDIDATE_1.md` | ~200 | RC1 identity, 15 systems | 🔴 Current |
| `POST_LAUNCH_ROADMAP.md` | ~100 | H01-H05 roadmap | 🔴 Current |
| `PRODUCTION_CERTIFICATION_AUDIT.md` | ~100 | PROD cert audit | 🟡 Historical |

---

## C. PRE-LAUNCH AUDITS

| File | Score/Result | Key Fix | Priority |
|------|-------------|---------|----------|
| `PRE_LAUNCH_CUSTOMER_UX_REPORT.md` | 89→92 | CUX-006/008/009 | 🟡 Historical |
| `PRE_LAUNCH_ADMIN_UX_REPORT.md` | 90→93 | AUX-008 state bar | 🟡 Historical |
| `PRE_LAUNCH_CLEANUP_REPORT.md` | PASS | 3 minor findings | 🟢 Reference |
| `SSL_HARDENING_REPORT.md` | PASS | ArvanCloud plan | 🟡 Open action |
| `OFFSITE_BACKUP_AUDIT.md` | CRITICAL GAP | Offsite copy needed | 🔴 Open action |
| `MONITORING_HARDENING_REPORT.md` | 3 gaps fixed | ScanRuntimeAnomalies | 🟡 Historical |
| `OPERATIONAL_HEALTH_REPORT.md` | VERIFIED | 24 schedulers ok | 🟡 Historical |
| `CUSTOMER_UX_REVIEW.md` | 89/100 | 10 improvements | 🟢 Reference |
| `ADMIN_UX_REVIEW.md` | 90/100 | 12 improvements | 🟢 Reference |

---

## D. SECURITY

| File | Type | Status |
|------|------|--------|
| `CORS_SECURITY_AUDIT.md` | CORS config | ✅ Done |
| `SSL_HARDENING_AUDIT.md` | SSL audit | ✅ Done |
| `SSL_HARDENING_REPORT.md` | SSL hardening | ✅ Done |
| `PRODUCTION_STORAGE_AUDIT.md` | Storage audit | ✅ Done |
| `PRODUCTION_STORAGE_SECURITY_REPORT.md` | Storage security | ✅ Done |

---

## E. ARCHITECTURE

| File | Area | Key Pages |
|------|------|-----------|
| `ARCHITECTURE.md` | System-wide | Full stack |
| `TENANT-ARCHITECTURE.md` | Multi-tenant | Site scoping |
| `CUSTOMER_SITE_LIFECYCLE_ARCHITECTURE.md` | Lifecycle | 5-state machine |
| `CUSTOMER_THEME_CUSTOMIZATION_ARCHITECTURE.md` | Theme | Canvas system |
| `NOTIFICATION_ARCHITECTURE.md` | Notifications | SMS + dashboard |
| `FAQ-ARCHITECTURE.md` | FAQ module | CRUD |
| `DESIGN-SYSTEM.md` | UI/UX | Components |
| `RESOURCE_GOVERNANCE_ARCHITECTURE.md` | Resource limits | 4 tables |
| `infrastructure/DEPLOYMENT_ARCHITECTURE.md` | Deploy | Server topology |
| `infrastructure/INFRASTRUCTURE_READINESS_REPORT.md` | Infra | Readiness |
| `infrastructure/MONITORING_CENTER_AUDIT.md` | Monitoring | Center audit |
| `architecture/distributed-module-delivery.md` | Module delivery | Distributed |
| `architecture/update-safety-rules.md` | Updates | Safety rules |

---

## F. THEME BUILDER / TB3

| File | Area | Status |
|------|------|--------|
| `TB_AI_STUDIO_ARCHITECTURE.md` | AI Studio | ✅ Certified |
| `TB_AI_STUDIO_CERTIFICATION.md` | AI Studio cert | ✅ Certified |
| `THEME_PACK_IMPORT_PIPELINE.md` | Import pipeline | ✅ Stable |
| `THEME_BUILDER_V3_ROADMAP.md` | TB3 roadmap | ✅ Planned |
| `THEME_CATALOG.md` | Theme catalog | ✅ Stable |
| `JSON_IMPORT_ENGINE_CERTIFICATION.md` | JSON import | ✅ Certified |
| `JSON_IMPORT_VALIDATION_REPORT.md` | JSON validation | ✅ Done |
| `ZIP_IMPORT_ENGINE_ARCHITECTURE.md` | ZIP engine | ✅ Operational |
| `ZIP_IMPORT_ENGINE_CERTIFICATION.md` | ZIP cert | ✅ Operational |
| `ZIP_IMPORT_VALIDATION_REPORT.md` | ZIP validation | ✅ Done |
| `PLAYWRIGHT_ENGINE_ARCHITECTURE.md` | Headless browser | ✅ Operational |
| `VISUAL_REVERSE_ENGINEERING_ARCHITECTURE.md` | RE pipeline | ✅ v2 Certified |
| `MARKETPLACE_THEME_SEED_PLAN.md` | Marketplace | ✅ Planned |
| `THEME_RECONSTRUCTION_PIPELINE.md` | Reconstruction | ✅ Stable |
| `theme-engine/theme-architecture.md` | Engine arch | ✅ Stable |
| `theme-engine/theme-governance.md` | Engine governance | ✅ Stable |
| `theme-engine/theme-parity-report.md` | Engine parity | ✅ Done |
| `theme-engine/theme-presets.md` | Engine presets | ✅ Stable |
| `theme-engine/FINAL-THEME-IMPLEMENTATION-REPORT.md` | Final theme | ✅ Done |

---

## G. SPRINT CERTIFICATIONS

| Sprint | File | Status |
|--------|------|--------|
| Sprint 2 | `TB3_SPRINT2_PLAN.md` | Plan |
| Sprint 3 | `TB3_SPRINT3_CERTIFICATION.md` | ✅ Certified |
| Sprint 4 | `TB3_SPRINT4_CERTIFICATION.md` | ✅ Certified |
| Sprint 5 | `TB3_SPRINT5_CERTIFICATION.md` | ✅ Certified |
| Sprint 6 | `TB3_SPRINT6_CERTIFICATION.md` | ✅ Certified |
| Sprint 8 | `TB3_SPRINT8_CERTIFICATION.md` | ✅ Certified |
| Sprint 9.5 | `TB3_SPRINT9_5_CERTIFICATION.md` | ✅ Certified |
| Sprint 10 | `TB3_SPRINT10_AUDIT.md` | ✅ Audited |
| Sprint 10 Gap | `TB3_SPRINT10_GAP_ANALYSIS.md` | ✅ Done |
| Sprint 11 | `TB3_SPRINT11_CERTIFICATION.md` | ✅ Certified |
| Sprint 11.5 | `TB3_SPRINT11_5_CERTIFICATION.md` | ✅ Certified |

---

## H. VALIDATION REPORTS

| File | Target | Score | Status |
|------|--------|-------|--------|
| `APPLE_VALIDATION_REPORT.md` | apple.com | 96% | ✅ Validated |
| `MALLTINA_VALIDATION_REPORT.md` | malltina.com | 70% | ✅ Validated |
| `DIGIKALA_VALIDATION_REPORT.md` | digikala.com | 70% | ✅ Validated |
| `HEADLESS_BROWSER_VALIDATION_REPORT.md` | 6 sites | 90%+ | ✅ Operational |
| `REAL_WORLD_VALIDATION_REPORT.md` | 5 real sites | V1/V2 | ✅ Validated |
| `REVERSE_ENGINEERING_ACCURACY_REPORT.md` | RE v1 | V1 | ✅ Done |
| `REVERSE_ENGINEERING_ACCURACY_V2.md` | RE v2 | 89.4% | ✅ Certified |
| `SIMILARITY_SCORE_REPORT_V2.md` | Similarity v2 | V2 | ✅ Done |
| `TOP_FAILURE_PATTERNS.md` | Patterns v1 | 4 patterns | ✅ Done |
| `TOP_FAILURE_PATTERNS_V2.md` | Patterns v2 | Enhanced | ✅ Done |

---

## I. CUSTOMER-SITE DOCS

| File | Area | Status |
|------|------|--------|
| `BUILDER-QA.md` | Builder QA | ✅ Exists |
| `BUILDER-TEST-CHECKLIST.md` | Builder tests | ✅ Exists |
| `BUILDER-VISUAL-ROADMAP.md` | Builder roadmap | ✅ Exists |
| `CHANGELOG.md` | Changelog | ✅ Exists |
| `CUSTOMER_MENU_AUDIT.md` | 54 items audit | ✅ Done 2026-06-22 |
| `CUSTOMER_MENU_BEFORE_AFTER.md` | Menu before/after | ✅ Done |
| `CUSTOMER_MENU_CERTIFICATION.md` | Menu certification | ✅ Certified |
| `CUSTOMER_MENU_REFACTOR_REPORT.md` | Menu refactor | ✅ Done |
| `KNOWN-TEST-FAILURES.md` | Test failures | ✅ Exists |
| `TEST-PHASES.md` | Test plan | ✅ Exists |
| `UX-ROADMAP.md` | UX plan | ✅ Exists |
| `MASTER-DEVELOPMENT-DIRECTIVE.md` | Dev directive | ✅ Exists |
| `PROJECT-GUIDE.md` | Project guide | ✅ Exists |
| `PROMPT_FOR_CURSOR.md` | Cursor prompt | ✅ Exists |
| `modules/billing-module.md` | Billing spec | ✅ Exists |
| `modules/module-template.md` | Module template | ✅ Exists |

---

## J. IMPLEMENTATION GUIDES

| # | Guide | Area | Type |
|---|-------|------|------|
| 01 | `01-license-server-and-validation.md` | License | Enterprise |
| 02 | `02-one-time-purchase-and-gateway.md` | Purchase | Enterprise |
| 03 | `03-support-as-product.md` | Support | Enterprise |
| 04 | `04-update-server-and-customer-site-check.md` | Updates | Enterprise |
| 05 | `05-site-config-api.md` | Config | Enterprise |
| 06 | `06-reseller-system.md` | Reseller | Enterprise |
| 07 | `07-module-marketplace.md` | Marketplace | Enterprise |
| 08 | `08-white-label-and-advanced-reseller.md` | White label | Enterprise |
| 09 | `09-license-flow-and-module-deployment.md` | License flow | Enterprise |
| 10 | `10-module-synchronization.md` | Module sync | Enterprise |

---

## K. PHASE DIRECTORIES (FINAL REPORTS)

| Phase | Final Report | Key Topic |
|-------|-------------|-----------|
| 10.2.8 | `FINAL-TAILWIND-UNIFICATION-REPORT.md` | Tailwind unification |
| 10.2.7 | `FINAL-RUNTIME-PERFECTION-REPORT.md` | Runtime perfection |
| 10.2.6 | `FINAL-STABILIZATION-REPORT.md` | Stabilization |
| 10.2.5 | `enterprise-frontend-lockdown-report.md` | Frontend lockdown |
| 10.2 | `FINAL-ENTERPRISE-FRONTEND-GOVERNANCE-REPORT.md` | Frontend governance |
| 10.1 | `FINAL-IMPLEMENTATION-REPORT.md` | Phase 10.1 |
| 9.5-B | `phase-9.5-b-final-report.md` | Phase 9.5-B |
| 9.4 | `final-enterprise-maturity-report.md` | Enterprise maturity |
| 9.3 | `phase-9.3-final-report.md` | Phase 9.3 |
| 8.6 | `plugin-governance-readiness-report.md` | Plugin governance |
| 8.5 | `horizontal-scale-readiness-report.md` | Horizontal scaling |
| 8.4 | `operational-autonomy-report.md` | Operational autonomy |
| 8.3 | `observability-readiness-report.md` | Observability |
| 8.2 | `production-readiness-report.md` | Production readiness |
| 8.1 | `distributable-packages-report.md` | Package boundaries |
| 8.0 | `security-hardening-report.md` | Security hardening |
| 7.3 | `EXECUTIVE-ARCHITECTURE-STATUS.md` | Architecture status |
| M3.0 | `FINAL-DEPLOYMENT-REPORT.md` | Mobile deployment |
| M2.1 | `FINAL-IMPLEMENTATION-REPORT.md` | Mobile implementation |
| M1.0 | `FINAL-FOUNDATION-REPORT.md` | Mobile foundation |

---

## L. AI CONTEXT FILES (THIS DIRECTORY)

| File | Lines | Purpose | Priority |
|------|-------|---------|----------|
| `CLAUDE_MASTER_CONTEXT.md` | ~400 | Full context for AI agents | 🔴 Read first |
| `PROJECT_MEMORY_INDEX.md` | ~250 | Fast-find for all docs | 🔴 Reference |
| `AI_HANDOVER_PROMPT.md` | ~200 | Copy-paste handover prompt | 🔴 Handoff |
| `PROJECT_KNOWLEDGE_GRAPH.md` | ~300 | Feature→Code→Doc mapping | 🔴 Reference |
| `DOCUMENT_INVENTORY.md` | ~250 | Complete doc catalog | 🟡 Reference |
| `MASTER_CONTEXT_AUDIT.md` | ~100 | Coverage audit | 🟡 Reference |

---

## M. OTHER REPORTS

| File | Area | Status |
|------|------|--------|
| `DUPLICATE_NOTIFICATION_AUDIT.md` | Notifications | ✅ Done |
| `NOTIFICATION_FIX_REPORT.md` | Notification fix | ✅ Done |
| `DATE_LOCALIZATION_FIX_REPORT.md` | Date localization | ✅ 57 fixes |
| `GLOBAL_DATE_LOCALIZATION_AUDIT.md` | Date audit | ✅ 72 findings |
| `AI_THEME_RUNTIME_AUDIT.md` | AI theme audit | ✅ Done |
| `APP_URL_AUDIT.md` | APP_URL audit | ✅ Done |
| `SMS-TEMPLATE-EXPIRY.md` | SMS templates | ✅ Done |
| `DEPLOY-CHECKLIST.md` | Deploy | ✅ Done |
| `COMMIT-PHASES.md` | Commit plan | ✅ Done |
| `MIGRATION-SAFETY-RULES.md` | Migration safety | ✅ Done |
| `CLAUDE.md` | Project config | ✅ Done |
| `ui/BUTTON_CONTRAST_AUDIT.md` | Button contrast | ✅ Done |
| `SIDEBAR_LAYOUT_FIX_REPORT.md` | Sidebar fix | ✅ Done |
| `SIDEBAR_UI_AUDIT.md` | Sidebar UI | ✅ Done |
| `CUSTOMER_DASHBOARD_BEFORE_AFTER.md` | Dashboard | ✅ Done |
| `CUSTOMER_DASHBOARD_REFACTOR_CERTIFICATION.md` | Dashboard cert | ✅ Done |
| `CUSTOMER_DASHBOARD_UX_AUDIT.md` | Dashboard UX | ✅ Done |
