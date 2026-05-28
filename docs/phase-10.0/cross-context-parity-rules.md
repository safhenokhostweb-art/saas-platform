# Cross-Context Parity Rules — Phase 10.0

## The Parity Contract

saas-platform and customer-site MUST render the same visual output for any given module view.

```
Same view file → Same tokens → Same components → Identical output
```

The ONLY variables allowed between contexts are:
1. **Capability gates** — `@capability('feature')` blocks (runtime boolean)
2. **Context directives** — `@platform` / `@tenant` (routing context)
3. **Policy filtering** — navigation items hidden based on permissions
4. **Tenant content** — data that differs per tenant (form titles, ticket subjects)

## Parity Test Protocol

For each module view, verify:
- [ ] Renders without error in saas-platform admin context
- [ ] Renders without error in customer-site tenant context
- [ ] Button styles are identical
- [ ] Card styles are identical
- [ ] Table styles are identical
- [ ] Modal behavior is identical
- [ ] Animation timings are identical (all from `--ui-duration-*`)
- [ ] Spacing is identical (all from `--ui-space-*`)
- [ ] Typography scale is identical (all from `--ui-text-*`)

## Forms Module Parity

Forms module views render identically in both contexts. Context-switching is handled by:

```php
// FormsServiceProvider boot()
view()->composer('forms::admin.*', function ($view) {
    $contextService = app(PlatformContextService::class);
    if ($contextService->isTenantAdmin()) {
        config(['forms.layouts.admin' => 'admin.layout']); // customer-site layout
    }
    // Layout changes, visual output stays identical
});
```

## Tickets Module Parity

Tickets module follows the same pattern:
```blade
@extends(config('tickets.layouts.admin', 'admin.layout'))
```

The layout changes per context, but the view's CSS is now entirely token-driven, so visual output is identical regardless of which layout wraps it.

## Validation Command

```bash
# Validate cross-context parity
php artisan frontend:validate-authority --strict

# JSON output for CI
php artisan frontend:validate-authority --json --fail-on-warning
```

## CI Integration

Add to `.github/workflows/ci.yml`:
```yaml
- name: Validate Frontend Authority
  run: php artisan frontend:validate-authority --strict
```

## Drift Recovery

If drift is detected:
```bash
php artisan frontend:recover --dry-run  # preview changes
php artisan frontend:recover            # apply safe recoveries
```
