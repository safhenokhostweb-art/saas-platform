# LAUNCH_FREEZE_RULES
> RC1 Launch Freeze Policy  
> Activated: 2026-06-22  
> Status: **LAUNCH_FREEZE_ACTIVE**

---

## Mission Statement

Platform RC1 is production-ready (93/100 score). The launch freeze halts all feature expansion and architectural experimentation. Only changes that improve stability, security, performance, or user experience of **existing** functionality are permitted.

---

## Allowed Changes

Any of the following may be implemented without additional approval:

| Category | Examples |
|----------|---------|
| **BUG FIX** | Null pointer exceptions, incorrect data display, broken form submissions, failed queue jobs, 500 errors, route mismatches |
| **UX IMPROVEMENT** | Better error messages, clearer labels, improved RTL alignment, missing loading states, empty-state guidance, confusing navigation flows |
| **PERFORMANCE** | Query optimization, N+1 resolution, cache warming, Redis key TTL tuning, response time reduction |
| **SECURITY** | Patch CVEs, fix XSS/CSRF/SQLi vulnerabilities, tighten authorization gates, fix missing policy checks, rate-limit abuse vectors |
| **DOCUMENTATION** | Update project-memory files, add inline docs, update GO_LIVE_CHECKLIST, update KNOWN_ISSUES |
| **MONITORING** | Add health check assertions, fix alert thresholds, fix false-positive incident creation, add Slack notification coverage |
| **DEPLOYMENT** | Fix deploy.ps1 edge cases, fix config caching issues, fix migration order problems |

---

## Prohibited Changes

The following are **blocked** until after production launch and stabilization (minimum 30 days post-launch):

| Category | Examples |
|----------|---------|
| **NEW FEATURES** | Any new user-facing capability that didn't exist in RC1 |
| **NEW MODULES** | New packages, new service layers, new module directories |
| **NEW DATABASE TABLES** | Any `create_*` migration. New columns on existing tables require approval. |
| **NEW ARCHITECTURAL SYSTEMS** | New service providers, new middleware stacks, new queue channels, new cache stores |
| **EXPERIMENTAL DEVELOPMENT** | Prototypes, spikes, "let me try this idea" implementations |
| **ROADMAP ITEMS** | Anything in POST_LAUNCH_ROADMAP.md, no matter how small it appears |
| **DEPENDENCY ADDITIONS** | New Composer packages or NPM packages (security patches excepted) |

---

## Approval Required Changes

These changes require explicit user approval before implementation:

| Category | Reason |
|----------|--------|
| Adding a column to an existing production table | Risk of migration failure on live data |
| Removing or renaming any existing route | Risk of breaking bookmarks, CDN caches, customer integrations |
| Changing any existing API response shape | Risk of breaking mobile app or customer-site API clients |
| Altering any scheduler timing | Risk of missed lifecycle events or double-billing |
| Changing any payment flow | Financial risk requires explicit sign-off |
| Modifying any `.env` variable name | Requires deployment coordination |

---

## Emergency Fix Protocol

If a CRITICAL production issue is discovered post-launch:

1. **Classify** the issue: BUG or SECURITY
2. **Scope the fix**: minimum change to resolve the issue only
3. **Implement**: in isolation, do not bundle with unrelated cleanup
4. **Deploy**: via `.\deploy.ps1` immediately
5. **Document**: update KNOWN_ISSUES.md and AUDIT_HISTORY.md
6. **Post-mortem**: add finding to CHANGELOG_AI.md

Emergency fixes are exempt from the 30-day waiting period but must remain minimal scope.

---

## Roadmap Gate (Phase 6 Rule)

Every future request must be classified before any work begins:

| Label | Description | Action |
|-------|-------------|--------|
| **BUG** | Something that worked before and now doesn't | IMPLEMENT immediately |
| **UX** | Improvement to existing screens without new capability | IMPLEMENT after brief review |
| **PERFORMANCE** | Measurable speed/resource improvement to existing code | IMPLEMENT after profiling |
| **SECURITY** | Closes a vulnerability in existing code | IMPLEMENT immediately |
| **FEATURE** | New capability that doesn't exist in RC1 | **DO NOT IMPLEMENT** — append to POST_LAUNCH_ROADMAP.md |
| **ARCHITECTURE** | Changes to system design, patterns, or infrastructure topology | **DO NOT IMPLEMENT** — append to POST_LAUNCH_ROADMAP.md |

When a request is ambiguous, apply the most restrictive classification.

---

## Freeze Duration

- **Minimum freeze:** 30 days from production go-live
- **Review gate:** After 30 days, conduct a post-launch stability audit
- **Unfreeze condition:** All P0/P1 incidents resolved + 7 consecutive days with zero critical alerts

---

*LAUNCH_FREEZE_RULES — activated 2026-06-22 — RC1 baseline*
