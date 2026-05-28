# Frontend Drift Prevention — Phase 10.1

## WHAT IS FRONTEND DRIFT?

Frontend drift occurs when a module, plugin, or developer introduces UI code that diverges from the canonical design system. Drift is cumulative — small deviations compound into major visual inconsistency.

### Types of Drift
1. **Component Drift** — Custom buttons, inputs, modals instead of `x-core-ui::*`
2. **Token Drift** — Hardcoded colors, spacing, z-index values
3. **Animation Drift** — Custom animation timings/easings not from `--ui-*` tokens
4. **State Drift** — Custom loading/error/success patterns outside canonical system
5. **Layout Drift** — Custom page structures instead of canonical layout components
6. **Responsive Drift** — Missing mobile breakpoints or fixed pixel widths

---

## DRIFT PREVENTION MECHANISMS

### 1. Automated Validation (CI)
Add to CI pipeline:
```bash
php artisan frontend:validate-enterprise-governance --fail-below=80
php artisan frontend:validate-components --strict
php artisan frontend:validate-responsive
```

Reject PRs that fail governance validation.

### 2. Component Namespace Enforcement
`CoreUtilsServiceProvider` registers `x-core-ui::` namespace FIRST, giving it authority priority. Modules that register the same namespace will have their components resolved after core-utils.

### 3. Design Token Import Chain
```
app.css → @import design-tokens.css → all --ui-* vars available globally
```
No view can use tokens that aren't imported — missing imports surface immediately.

### 4. Service Provider Registration Order
```php
// CoreUtilsServiceProvider boots first
Blade::anonymousComponentPath('...core-utils/resources/views/components', 'core-ui');

// FormsServiceProvider boots second (additive only)
Blade::anonymousComponentPath('...forms/resources/views/core-ui/components', 'core-ui');
```

Core-utils components always win name conflicts.

---

## DRIFT DETECTION COMMANDS

```bash
# Full audit
php artisan frontend:validate-enterprise-governance

# Component-level scan
php artisan frontend:validate-components
php artisan frontend:validate-components --json | jq '.findings[] | select(.severity=="CRITICAL")'

# Recovery check (dry-run)
php artisan frontend:recover-normalization --dry-run
```

---

## DRIFT SEVERITY CLASSIFICATION

| Severity | Examples | Response |
|----------|---------|----------|
| CRITICAL | Hardcoded hex colors, inline z-index, window.confirm() | Block merge, immediate fix |
| HIGH | Raw `<button>`, raw `<input>`, custom modals | Fix before merge |
| MEDIUM | Non-canonical Tailwind grays, missing touch targets | Fix within sprint |
| LOW | Minor class ordering, extra whitespace | Accepted tech debt |

---

## FOR NEW MODULE DEVELOPERS

When creating a new module:

1. Never create a `resources/css/` folder in your module — use canonical tokens
2. Never define your own color palette
3. Use ONLY `x-core-ui::*` components in all views
4. Register your views namespace but NOT your own `core-ui` component path
5. Run `frontend:validate-enterprise-governance` before opening a PR
6. Check `docs/phase-10.1/future-module-ui-rules.md` for the complete checklist

---

## HISTORICAL DRIFT LOG

| Phase | Drift Found | Remediated |
|-------|------------|-----------|
| Pre-10.0 | 100% custom UI per module | N/A |
| Phase 10.0 | Design token system created | Partial migration |
| Phase 10.1 | 76 violations audited | Full canonical component suite created |
| Phase 10.2 (planned) | Retrofitting remaining views | Enforcement commands in CI |
