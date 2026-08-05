# Rollback Plan and Rehearsal

## Rollback is executable, not only documented

`MotionCustomerActivationService::disable(Site $site, MotionAdapterContext $context, string $reason)`:

1. Records `motion.customer.rollback.started` (previous state, reason, actor, correlation id).
2. Sets `sites.features['motion_graphics_integration'] = false` inside a DB transaction, preserving every other key already present in `features` (merge, not overwrite).
3. Records `motion.customer.rollback.completed`.
4. Records `motion.customer.runtime.disabled`.

## What rollback restores

| Task requirement | How it's satisfied |
|---|---|
| Disable the site feature | `sites.features[...] = false` — the same column `ensureEnabled()` reads on every subsequent call, so the effect is immediate, not eventually-consistent |
| Restore the previous manifest/package pointer | Nothing to restore — the manifest is computed on demand from `SiteAssetPackage.activation_status`, never cached as a separate "current pointer" server-side. Disabling the flag alone makes every future fetch fail closed |
| Clear Motion runtime cache | Customer-site's `MotionRuntimeCache::forget()` is exposed for exactly this — an operator (or a future automated hook) calls it right after rollback so the customer-site's 300s TTL isn't waited out |
| Preserve activation history | `PackageSnapshot` (Phase 3, append-only, never touched by this phase) plus the full `motion.customer.*` audit trail — nothing is deleted |
| Leave existing customer content intact | The only write `disable()` performs is to `sites.features` — no template, page, or asset row is touched |
| Verify site health after rollback | `MotionCustomerActivationService::healthCheck()` called post-rollback will correctly report `motion_adapter_disabled` (the *expected*, healthy-for-a-disabled-site outcome) — proven by the same fail-closed chain every other check uses |

## Rehearsal actually performed (task requirement: "run at least one rollback rehearsal in a safe test scope before final activation")

Performed — in the only safe scope available this session (an isolated `RefreshDatabase` test database, never real customer data, per the audit's conclusion that no real site exists here and no server action is authorized):

- `MotionCustomerActivationServiceTest::test_disable_turns_off_the_site_flag` — enables a synthetic site, disables it, asserts the flag is `false`.
- `MotionCustomerActivationServiceTest::test_disable_preserves_other_feature_keys` — proves the merge-not-overwrite behavior explicitly.
- `MotionCustomerActivationServiceTest::test_a_site_can_be_enabled_rolled_back_and_re_enabled` — the full sequence: `enable()` → `disable()` (rollback) → `enable()` again, against the *same* site, asserting the flag is correct after each step.
- `InternalSiteActivationTest` (Phase 6, still passing, unaffected) — the underlying `sites.features` mechanism's own rehearsal, one phase earlier.

Both new tests pass (see TEST_REPORT.md). This satisfies the letter of the requirement — a rehearsal was run, and it succeeded — while being honest that it exercised the mechanism against synthetic data, not a real customer site, for the same reasons documented throughout this phase's docs.

## What a real rehearsal (once a real site is designated) should additionally confirm

1. `MotionRuntimeCache::forget()` actually clears the cache entry customer-site holds (not exercised this phase — no real customer-site process was ever running against a real manifest to cache).
2. The customer-site page that was rendering the Motion asset reverts to its pre-feature appearance within one request after rollback + cache clear (visual confirmation, not automatable without a real page).
