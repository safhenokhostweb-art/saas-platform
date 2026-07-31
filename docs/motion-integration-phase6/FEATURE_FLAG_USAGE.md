# Feature Flag Usage (Phase 6)

`motion_graphics_integration` — same flag since Phase 1, same fail-closed consumers (`AbstractMotionAdapter::ensureEnabled()`), same three-tier precedence (`SaaSPlatform\CoreUtils\Traits\HasTenantContext::hasFeature()`):

```text
1. config('feature-flags.motion_graphics_integration')   — global override, short-circuits if non-null
2. $tenant->features['motion_graphics_integration']       — per-tenant JSON override (sites.features / resellers.features)
3. $tenant->motion_graphics_integration                   — direct column (unused here)
4. false                                                   — final fallback
```

## What changed this phase

`packages/saas-platform/core-utils/config/feature-flags.php`:

```php
// Before (Phases 1-5):
'motion_graphics_integration' => env('FEATURE_MOTION_GRAPHICS_INTEGRATION', false),

// After (Phase 6):
'motion_graphics_integration' => env('FEATURE_MOTION_GRAPHICS_INTEGRATION', null),
```

With the env var unset (the default, and the only supported state outside of a deliberate override), step 1 no longer short-circuits, so step 2 — the per-site override — becomes reachable. **This does not change the effective default for any existing site.** No site has ever had `motion_graphics_integration` set in its `features` JSON, so every site still falls through to step 4: disabled. This is asserted directly in `tests/Feature/Modules/MotionGraphics/Phase6/InternalTestSiteCommandTest::test_the_global_default_is_null_not_a_forced_value` and in the (updated) Phase 1-5 flag tests.

## The only way to turn it on: `motion:internal-test-site`

```bash
php artisan motion:internal-test-site {site_id}              # enable for exactly this site
php artisan motion:internal-test-site {site_id} --disable    # revert
```

This writes `$site->features['motion_graphics_integration'] = true|false` and saves — nothing else. It:

- Never touches the global config.
- Never touches any other site's `features` JSON (merges into the existing array rather than replacing it).
- Is fully reversible (`--disable`, or manually clearing the key).
- Fails closed for an unknown site id (`app/Console/Commands/MotionGraphicsInternalTestSiteCommand.php`).

## Rules this satisfies

- **"Keep feature flag `motion_graphics_integration`, default `false`"** — effectively true for every site (global `null` + no per-site override resolves the same as the old forced `false`).
- **"Enable only for: Internal test site, Admin testing"** — the command is the only lever, and it is scoped to one site id per invocation.
- **"Never enable globally"** — the global config value is never set to `true` anywhere in this phase; `env('FEATURE_MOTION_GRAPHICS_INTEGRATION', null)` still allows an operator to force a global value via env var if they explicitly choose to, exactly as the flag already permitted in Phases 1-5 — this phase does not add that capability, it was already there.

## State at the end of this phase

- Global config: `null` (unset) in the codebase; no `.env` entry added.
- No site has been enabled by this phase's own changes — enabling a specific internal test site is an operational step for whoever runs Phase 6's rollout, not something baked into a migration or seeder.
