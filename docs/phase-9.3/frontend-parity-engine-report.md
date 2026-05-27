# Phase 9.3 — Frontend Visual Parity Engine Report
**Date:** 2026-05-27  
**Service:** `FrontendVisualParityEngine`  
**Command:** `php artisan frontend:validate-parity`

---

## OVERVIEW

`FrontendVisualParityEngine` is a 5-dimension validator that proves the canonical UI authority (package views) is the ONLY source of rendered HTML for both saas-platform and customer-site. It detects visual divergence before it reaches production.

---

## ARCHITECTURE

```
FrontendVisualParityEngine::validate()
  ├── checkForbiddenOverrides()      [30 pts] — no blade files in host app views/admin/{forms,tickets}
  ├── checkCssVariableDrift()        [20 pts] — no legacy --c-* vars or bare hex colors
  ├── checkComponentNamespace()      [20 pts] — core-ui:: registered in both FormsServiceProvider + TicketsServiceProvider
  ├── checkLayoutResolution()        [15 pts] — view composers registered for dynamic layout
  └── checkViewAuthorityIntegrity()  [15 pts] — package view directories exist, no host-app overrides

score() → 0-100 (integer)
isParity() → score >= 90
```

---

## COMMAND USAGE

```bash
# Human-readable output
php artisan frontend:validate-parity

# JSON output (CI-friendly)
php artisan frontend:validate-parity --json

# Show fix hints for each failure
php artisan frontend:validate-parity --fix-hints

# Strict mode (fail on any non-passing check)
php artisan frontend:validate-parity --strict
```

---

## DIMENSION DETAILS

### D1: Forbidden Override Detection (30 pts)

**What it checks:**
- `saas-platform/resources/views/admin/forms/*.blade.php` — MUST be empty
- `saas-platform/resources/views/admin/tickets/*.blade.php` — MUST be empty
- `customer-site/resources/views/admin/forms/*.blade.php` — MUST be empty
- `customer-site/resources/views/admin/tickets/*.blade.php` — MUST be empty
- `*/resources/views/vendor/forms/*.blade.php` — MUST be empty
- `*/resources/views/vendor/tickets/*.blade.php` — MUST be empty

**Fix:** Delete all blade files from the above paths.

---

### D2: CSS Variable Design Token Compliance (20 pts)

**What it checks:**
- No `--c-indigo`, `--c-red`, etc. (legacy variable pattern)
- No bare hex colors outside design token comments

**Expected:** All CSS values use `--forms-space-*`, `--forms-radius-*`, `--forms-shadow-*`, etc.

**Tolerance:** ≤3 violations (some views still transition from Phase 8 patterns)

---

### D3: Component Namespace Registration (20 pts)

**What it checks:**
- `FormsServiceProvider.php` contains `anonymousComponentNamespace`
- `TicketsServiceProvider.php` contains `anonymousComponentNamespace` or `registerBladeDirectives`

---

### D4: Layout Resolution Per-Context (15 pts)

**What it checks:**
- `forms.php` config has `layouts` key
- `tickets.php` config has `layouts` key
- `FormsServiceProvider` has `view()->composer()` registration

---

### D5: View Authority Integrity (15 pts)

**What it checks:**
- `packages/saas-platform/modules/forms/resources/views` directory exists
- `packages/saas-platform/modules/tickets/Resources/views` directory exists
- No `.blade.php` files in `resources/views/admin/forms` or `resources/views/admin/tickets`

---

## INTEGRATION WITH CI

Add to your CI pipeline:

```yaml
- name: Validate Frontend Parity
  run: php artisan frontend:validate-parity --json --strict
```

Exit code 0 = parity confirmed  
Exit code 1 = parity violations detected

---

## SERVICE REGISTRATION

```php
// FormsServiceProvider::register()
$this->app->singleton(FrontendVisualParityEngine::class);

// Available as:
app(FrontendVisualParityEngine::class)->validate()
app(FrontendVisualParityEngine::class)->score()
app(FrontendVisualParityEngine::class)->isParity()
```
