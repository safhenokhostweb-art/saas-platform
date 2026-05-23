# PHASE 5 — MINIMAL ANTI-DRIFT AUTOMATION

**Status:** ✅ COMPLETE  
**Date:** 2026-05-22  
**Objective:** Lightweight executable enforcement layer to prevent code drift between saas-platform and customer-site.

---

## Architecture Overview

```
saas-platform/
├── scripts/
│   ├── drift-detect.php          # Main enforcement engine (651 lines)
│   └── .drift-whitelist.json     # Exception/waiver system
├── .githooks/
│   └── pre-commit                # Pre-commit drift check hook
├── .github/workflows/
│   └── drift-detection.yml       # CI/CD drift detection workflow
└── docs/phase-5/
    └── ANTI-DRIFT-AUTOMATION.md  # This file

customer-site/
├── .githooks/
│   └── pre-commit                # Pre-commit drift check hook
```

---

## Task 1: Duplicate Blade Detection

**Engine:** `checkDuplicateBlades()` in `scripts/drift-detect.php`

- Scans all `.blade.php` files in both projects
- Compares same-named files using `similar_text()` with content normalization
- Normalization removes: whitespace, Blade comments, variable-specific content, `@lang()`/`@json()` calls
- **Threshold:** 85% similarity = violation
- **Severity:** ≥95% = high, ≥85% = medium
- Whitelisted files are skipped

**CLI:** `php scripts/drift-detect.php --check-blade`

---

## Task 2: Duplicate Controller Detection

**Engine:** `checkDuplicateControllers()` in `scripts/drift-detect.php`

- Scans all `.php` files in `app/Http/Controllers/` in both projects
- Compares same-named controllers using `similar_text()`
- Same threshold and severity as Blade detection
- Whitelisted files are skipped

**CLI:** `php scripts/drift-detect.php --check-controllers`

---

## Task 3: Shared UI Ownership Enforcement

**Engine:** `checkSharedUiOwnership()` in `scripts/drift-detect.php`

- Compares `resources/views/components/` between both projects
- Detects components that exist in both with high similarity
- These should be loaded from `saas-platform/ui-kit` instead of duplicated
- Severity is always **high** for UI duplication

**CLI:** `php scripts/drift-detect.php --check-ownership`

---

## Task 4: Package Ownership Validation

**Engine:** `checkPackageOwnership()` in `scripts/drift-detect.php`

- Scans `packages/` and `vendor/saas-platform/` directories
- Validates that all PHP files use `SaasPlatform\` namespace prefix
- Detects unauthorized namespace violations
- Severity: medium

**CLI:** `php scripts/drift-detect.php --check-ownership`

---

## Task 5: CI/CD Enforcement

**Workflow:** `.github/workflows/drift-detection.yml`

**Triggers:**
- Pull requests touching views, controllers, packages, or drift scripts
- Pushes to main/master with same path filters
- Weekly schedule (every Monday 06:00 UTC)

**Jobs:**

1. **drift-check** — Runs full drift detection in CI mode
   - Checks out both `saas-platform` and `customer-site`
   - Executes `php scripts/drift-detect.php --ci --report`
   - Uploads drift report as artifact (30-day retention)
   - Fails if high-severity violations detected

2. **drift-report-weekly** — Creates GitHub Issue with weekly summary
   - Only runs on scheduled trigger
   - Downloads drift report artifact
   - Creates issue with `drift-report` and `automated` labels

---

## Task 6: Pre-Commit Safety Checks

**Hooks:**
- `saas-platform/.githooks/pre-commit`
- `customer-site/.githooks/pre-commit`

**Behavior:**
- Runs on staged `.php` and `.blade.php` files
- Executes: `--check-blade --check-controllers --check-ownership`
- Exits with error if violations found
- Provides bypass instructions and exception management

**Installation:**
```bash
git config core.hooksPath .githooks
```

---

## Task 7: Shared Contract Validation

**Engine:** `checkContracts()` in `scripts/drift-detect.php`

- Scans all `module.json` files in `packages/`
- Validates required fields: `module_slug`, `version`, `update_type`, `compatibility`
- Detects invalid JSON manifests
- Severity: high for missing/invalid fields

**CLI:** `php scripts/drift-detect.php --check-contracts`

---

## Task 8: Drift Reporting

**Engine:** `generateReport()` in `scripts/drift-detect.php`

**Report format:**
```
═══════════════════════════════════════════════════════
  PHASE 5 — ANTI-DRIFT REPORT
  Generated: 2026-05-22 23:00:00
═══════════════════════════════════════════════════════

  📊 Summary:
     Total violations: 5
     🔴 High severity:  3
     🟡 Medium severity: 2

  ─── 📄 Duplicate Blade Files ───

  🔴 [high]
     customer-site: resources/views/admin/some-file.blade.php
     saas-platform: resources/views/admin/some-file.blade.php
     similarity: 97.5%

  ─── Recommendations ───

  🔴 High severity violations MUST be resolved before deployment.
  📄 Move duplicate Blade files to shared packages.
```

**CLI:** `php scripts/drift-detect.php --report`

Reports saved to: `storage/logs/drift-report-{date}.txt`

---

## Task 9: Exception/Waiver System

**Whitelist:** `scripts/.drift-whitelist.json`

**Structure:**
```json
{
    "files": [
        "resources/views/components/module-features.blade.php",
        "resources/views/admin/forms/*"
    ],
    "metadata": {
        "description": "Whitelist for shared module files under active migration",
        "last_updated": "2026-05-22",
        "policy": "Files listed here are shared modules under active migration."
    }
}
```

**Features:**
- Exact file matching
- Wildcard support (`admin/forms/*`)
- Metadata tracking (description, last_updated, policy)

**CLI:**
```bash
# Add exception
php scripts/drift-detect.php --add-exception "resources/views/admin/forms/create.blade.php" "Under migration" "2026-06-22"

# List exceptions
php scripts/drift-detect.php --list-exceptions
```

---

## Usage Reference

```bash
# Full scan with report
php scripts/drift-detect.php --report

# CI mode (exits with code 1 on high-severity violations)
php scripts/drift-detect.php --ci

# Individual checks
php scripts/drift-detect.php --check-blade
php scripts/drift-detect.php --check-controllers
php scripts/drift-detect.php --check-ownership
php scripts/drift-detect.php --check-contracts

# Exception management
php scripts/drift-detect.php --add-exception "<file>" "<reason>" [expires_at]
php scripts/drift-detect.php --list-exceptions
```

---

## Quality Checklist

- [x] Task 1: Duplicate Blade Detection — `checkDuplicateBlades()`
- [x] Task 2: Duplicate Controller Detection — `checkDuplicateControllers()`
- [x] Task 3: Shared UI Ownership Enforcement — `checkSharedUiOwnership()`
- [x] Task 4: Package Ownership Validation — `checkPackageOwnership()`
- [x] Task 5: CI/CD Enforcement — `.github/workflows/drift-detection.yml`
- [x] Task 6: Pre-Commit Safety Checks — `.githooks/pre-commit` (both projects)
- [x] Task 7: Shared Contract Validation — `checkContracts()`
- [x] Task 8: Drift Reporting — `generateReport()`
- [x] Task 9: Exception/Waiver System — `.drift-whitelist.json`

---

## Files Created

| # | File | Lines | Purpose |
|---|------|-------|---------|
| 1 | `scripts/drift-detect.php` | 651 | Main enforcement engine (Tasks 1-4, 7-9) |
| 2 | `scripts/.drift-whitelist.json` | 20 | Exception/waiver system (Task 9) |
| 3 | `.githooks/pre-commit` | 35 | Pre-commit drift check (Task 6) |
| 4 | `customer-site/.githooks/pre-commit` | 35 | Pre-commit drift check (Task 6) |
| 5 | `.github/workflows/drift-detection.yml` | 105 | CI/CD enforcement (Task 5) |
| 6 | `docs/phase-5/ANTI-DRIFT-AUTOMATION.md` | 195 | This documentation |

**Total: 6 files, ~1,041 lines**

---

## Next Steps (Phase 6+)

The following are explicitly **OUT OF SCOPE** for Phase 5 and should be addressed in future phases:

- Tickets module migration
- Marketplace implementation
- Module auto-discovery enhancements
- Real-time drift monitoring dashboard
- Automated drift resolution (auto-PRs)
