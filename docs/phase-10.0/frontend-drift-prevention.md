# Frontend Drift Prevention — Phase 10.0

## What is Frontend Drift?

Frontend drift occurs when `saas-platform` and `customer-site` render the same module view differently. Drift is detected by `FrontendDriftDetector` and reported by `php artisan frontend:validate-authority`.

## Drift Taxonomy

| Category | Examples | Severity |
|---|---|---|
| Token divergence | Each project defines its own CSS vars | CRITICAL |
| Component divergence | One project uses `x-core-ui::modal`, the other uses a local modal | CRITICAL |
| Animation divergence | `@keyframes` defined in `@push('head')` in one project only | CRITICAL |
| Color divergence | Hardcoded `#4f46e5` in one project, `var(--ui-indigo)` in the other | HIGH |
| Spacing divergence | `p-5` in one, `padding: 20px` in the other | MEDIUM |
| Icon divergence | Different SVG sizes/viewboxes per project | LOW |

## Structural Guarantees

Phase 10.0 makes the following drift types **structurally impossible**:

1. **Token drift** — impossible because both projects import the same `design-tokens.css`
2. **Core component drift** — impossible because core-utils components are the canonical source and both projects use the same package
3. **Animation drift** — impossible because all `@keyframes` are in `design-tokens.css` and local definitions are forbidden

## Remaining Drift Risks

| Risk | Mitigation |
|---|---|
| Tailwind v3 vs v4 utility class names | Use CSS custom properties; avoid Tailwind utility classes in Blade where possible |
| Per-project layout differences | Layouts may differ structurally but module view content is canonical |
| Customer-site published views | Monitor with `php artisan frontend:validate-authority` |

## Detection Tools

```bash
# Full drift report
php artisan frontend:validate-authority

# JSON drift data for CI
php artisan frontend:validate-authority --json | jq '.drift'

# Automated healing
php artisan frontend:recover
```

## Prevention Checklist (code review gate)

Before merging any PR that touches Blade views:

- [ ] No new `@keyframes` defined in `@push('head')` blocks
- [ ] No new `style=` attributes with values (not variables)
- [ ] No new Tailwind color utilities (bg-*, text-*, border-*)
- [ ] All new components use `x-core-ui::*` not local HTML
- [ ] `php artisan frontend:validate-authority` passes (exit code 0)
- [ ] No new CSS files added to module `resources/css/`

## Monitoring

Run authority validation weekly or on every deployment:

```yaml
# In deployment pipeline
- php artisan frontend:validate-authority --json --fail-on-warning
```

If score drops below 85, block the deployment and alert the team.
