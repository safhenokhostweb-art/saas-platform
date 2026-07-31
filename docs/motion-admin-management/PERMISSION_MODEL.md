# Permission Model

## The four admin abilities

| Ability | Used by |
|---|---|
| `motion.view_library` | `dashboardSummary()`, `listTemplates()` |
| `motion.manage_template_package` | `previewCompatibility()`, `assignPackageToTemplate()` |
| `motion.activate_site_package` | `activateForInternalSite()` |
| `motion.view_runtime_projection` | `listActivatedPackagesForSite()`, `previewRuntimeProjection()` |

Every `MotionGraphicsAdminService` method calls a single private `authorizeAdmin(context, ability)` helper, which — in this exact order — calls `ensureEnabled()` (feature flag), `MotionAuthorizationInterface::authorize()` (the ability check), then `assertSiteMatches()` (tenant re-verification). This is the same three-step fail-closed sequence every Motion adapter has used since Phase 1; nothing new was introduced.

## Why no change was needed to the permission gateway

`App\Modules\MotionGraphics\Bindings\PermissionGatewayAdapter` (Phase 1, unchanged) is ability-string-agnostic for Platform Admins:

```php
if ($actor->is_admin === true && $actor->site_id === null) {
    return true; // any ability
}
return $actor->site_id !== null && in_array($ability, self::CUSTOMER_ABILITIES, true);
```

A Platform Admin is granted *any* ability string, including all four new ones — there was nothing to register or configure. A Customer is checked against a fixed allowlist (`motion.view`, `motion.use`) that does **not** include any of the four admin abilities, so a customer is denied automatically, with zero new code. This was verified directly (`Tests\Unit\Modules\MotionGraphics\Services\MotionGraphicsAdminServiceTest`, ability-mismatch tests) rather than assumed.

## Two independent layers of denial for a Customer

1. **Route-level**: every `admin.motion-graphics.*` route is inside the existing `admin.` route group (`['auth', 'admin']` middleware). `App\Http\Middleware\AdminMiddleware` calls `AuthorizationService::canAccessAdmin()`, which requires `is_admin` (or a Staff/Super Admin role) — a Customer never reaches the controller at all (`403`, confirmed by `AccessControlTest`/`AdminSecurityTest`).
2. **Service-level**: even if that middleware were bypassed, `MotionAuthorizationAdapter::authorize()` still denies any of the four abilities for a non-admin actor via the same `PermissionGatewayAdapter` check above (confirmed directly in `MotionGraphicsAdminServiceTest`, independent of the HTTP layer).

Neither layer alone is trusted as sufficient — this mirrors the "two independent tenant checks, deliberately" pattern documented since Phase 5.

## Task scope item 2, verified against actual behavior

| Requirement | How it's satisfied |
|---|---|
| Platform Admin: `motion.view_library` | Dashboard + template listing reachable, denied without the flag/ability |
| Platform Admin: `motion.manage_template_package` | Compatibility preview + package assignment reachable |
| Platform Admin: `motion.activate_site_package` | Site activation reachable |
| Platform Admin: `motion.view_runtime_projection` | Package listing + runtime preview reachable |
| Customer: create package | Denied — `AccessControlTest::test_a_customer_cannot_create_a_package_even_by_guessing_the_route` |
| Customer: modify package | Denied — no update/edit route exists for any package at all, admin or customer |
| Customer: activate package | Denied — `AdminSecurityTest::test_a_customer_cannot_modify_a_package_through_any_admin_motion_graphics_route` |
| Customer: access Master management | Denied — no Master Asset editing route exists anywhere in this phase (out of scope by design; see SECURITY_RULES.md) |
