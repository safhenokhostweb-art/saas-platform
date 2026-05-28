# Frontend Recovery Runtime — Phase 10.0

## FrontendRuntimeRecoveryService

**Location:** `packages/saas-platform/core-utils/src/Frontend/FrontendRuntimeRecoveryService.php`

Diagnoses and auto-heals frontend runtime failures. Runs safe, reversible recovery actions only.

### Diagnostic Dimensions

| Dimension | What it checks | Auto-recovery? |
|---|---|---|
| `design-tokens-css` | design-tokens.css exists and is readable | No (package integrity) |
| `required-components` | All 13 canonical components exist | Directory creation only |
| `vite-manifest` | public/build/manifest.json is valid JSON | No (requires build) |
| `core-utils-components` | Component directory has files | No |
| `forms-core-ui-dir` | Forms module component directory exists | No |

### Usage

```bash
# Diagnose only (no changes)
php artisan frontend:recover --dry-run

# Diagnose and auto-recover safe issues
php artisan frontend:recover

# JSON output
php artisan frontend:recover --json
```

### Integration in Health Checks

```php
// In a health check endpoint or scheduled command:
$result = app(FrontendRuntimeRecoveryService::class)->diagnoseAndRecover();

if (! $result['healthy']) {
    Log::critical('Frontend runtime unhealthy', $result['unrecoverable']);
    // Alert team
}
```

### When to Run

- After every deployment
- When frontend errors are reported
- After `composer update` on core-utils package
- Before customer demo sessions

## FrontendAuthorityValidationService

**Location:** `packages/saas-platform/core-utils/src/Frontend/FrontendAuthorityValidationService.php`

Validates the full frontend authority system. Returns a score 0–100.

### Validation Checks

| Check | Description | Weight |
|---|---|---|
| `design-token-file` | design-tokens.css exists | Pass/Fail |
| `token-import` | app.css imports design-tokens.css | Warning |
| `core-ui-namespace` | core-ui component directory exists | Pass/Fail |
| `required-components` | All 13 canonical components present | Pass/Fail |
| `no-inline-styles` | No `style=` attributes in Blade views | Warning |
| `no-forbidden-colors` | No raw Tailwind color utilities | Warning |
| `no-duplicate-modals` | No non-canonical modal implementations | Warning |
| `no-duplicate-buttons` | No legacy .btn-* CSS system | Warning |
| `no-customer-site-overrides` | No admin/forms or admin/tickets in customer-site | Pass/Fail |
| `forms-module-canonical` | FormsServiceProvider registers core-ui path | Warning |

### Exit Codes

| Code | Meaning |
|---|---|
| 0 | All checks pass (or warnings allowed) |
| 1 | Critical failures, or warnings with `--strict`/`--fail-on-warning` |

## DesignTokenGovernanceService

**Location:** `packages/saas-platform/core-utils/src/Frontend/DesignTokenGovernanceService.php`

Audits the design-tokens.css file for completeness and correctness.

```php
$service = app(DesignTokenGovernanceService::class);

// Full token audit
$audit = $service->auditTokenFile();
// Returns: total_tokens, token_groups, has_dark_mode, has_rtl, has_keyframes, etc.

// Token coverage score 0-100
$score = $service->tokenCoverageScore();

// Detect hardcoded hex colors in a directory
$violations = $service->detectHardcodedColors(resource_path('views'));
```

## CanonicalComponentRegistry

**Location:** `packages/saas-platform/core-utils/src/Frontend/CanonicalComponentRegistry.php`

```php
$registry = app(CanonicalComponentRegistry::class);

// Auto-discover all registered components
$report = $registry->report();

// Check if a specific component is canonical
$registry->has('button');     // true
$registry->has('my-custom');  // false

// List canonical vs module components
$canonical    = $registry->canonical();
$nonCanonical = $registry->nonCanonical();
```

## FrontendDriftDetector

**Location:** `packages/saas-platform/core-utils/src/Frontend/FrontendDriftDetector.php`

```php
$detector = app(FrontendDriftDetector::class);
$report = $detector->detect();

if (! $report['drift_free']) {
    foreach ($report['critical'] as $key => $drift) {
        // Handle critical drift
    }
}
```
