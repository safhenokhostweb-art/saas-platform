# Real Customer Test Plan

## Status: documented and ready to run — not executed this phase

Per the audit's conclusion (AUDIT_REPORT.md §6) and the user's explicit mid-phase instruction ("Do NOT connect to any server... Do NOT execute any deployment"), no real customer site was activated or tested against in this phase. This document is the procedure an operator runs once a real, explicitly-designated site is available and the two audit blockers (no Site/CustomerInstallation records exist in this environment; no server/deployment execution is authorized in this session) no longer apply.

## Pre-flight checklist (task section "Test Customer Selection")

Run every check before proceeding — all are read-only:

```php
$site = Site::find($SELECTED_SITE_ID); // never guessed — operator-supplied
abort_unless($site !== null, 'site does not exist');
abort_unless($site->is_active, 'site is not active');
// belongs to the intended customer: operator confirms $site->customer_id manually
abort_unless(
    Site::where('id', '!=', $site->id)->where('features->motion_graphics_integration', true)->doesntExist(),
    'another site is already enabled'
);
$health = app(MotionCustomerActivationService::class)->healthCheck($site, $context);
abort_unless($health['healthy'], 'pre-activation health check failed: '.$health['reason']);
```

Backup/rollback-point and "current deployment healthy" checks are the responsibility of the user's own independent Deployment System (out of this session's authority per the explicit correction) — an operator confirms these through that system before proceeding.

## Activation

```php
$manifest = app(MotionCustomerActivationService::class)->enable($site, $context);
// audit trail: motion.customer.deployment.started -> .completed -> motion.customer.runtime.enabled
```

## Scope of the real test (task section 10)

- One simple Motion asset (single `asset_id`, `lottie` or `svg` type).
- One themed variant if a second `theme_tokens` set is available for it.
- Desktop and mobile rendering — verified by loading the specific page/section carrying `<x-motion.graphics name="...">` in both viewport sizes.
- Asset failure fallback — temporarily point the manifest at a nonexistent `asset_id` (or use `MotionRuntimeAssetResolver::resolve()` with a wrong name) and confirm the page renders with no visible error, matching `MotionRuntimeRendererTest`'s and the Blade component's `@if($html)` guard.
- Cache invalidation — call `MotionRuntimeCache::forget()` (already exposed) after a rollback and confirm the next page load reflects the new (disabled) state immediately rather than waiting out the 300s TTL.
- Disable/re-enable — run `MotionCustomerActivationService::disable()` then `enable()` again, confirming the page reverts to pre-feature behavior and then recovers.

**Explicitly not in scope (task section 10): the entire Motion asset library.** Test exactly one asset, as specified.

## Recommended safe surface

Prefer a non-critical page, a hidden preview route, or an admin-visible preview page over a public production page — the task's own preference order. This phase does not designate a specific page (no real site to designate one on); the operator picks one appropriate to the actual selected site at execution time.

## Why this document stops here

Every step above is a real, already-built, already-unit-tested capability (`MotionCustomerActivationService::prepare/enable/disable/healthCheck`, `MotionRuntimeManifestReader/AssetResolver/Renderer/HealthCheck/Cache`) — nothing here is aspirational. What's deliberately absent is *running* these commands against a real site's data, because no real site exists in this environment and the user's explicit instruction ruled out any server-facing action this session.
