# Health Check Results

## Two independent health-check implementations, one per side

- **Platform side**: `MotionCustomerActivationService::healthCheck(Site $site, MotionAdapterContext $context)` — two independent `prepare()` calls (each re-running the full gate chain), comparing checksums.
- **Customer-site side**: `MotionRuntimeHealthCheck::check()` — two independent `MotionRuntimeManifestReader::readFresh()` calls (bypassing cache), same comparison.

Both return the same shape: `{healthy: bool, manifest_checksum: ?string, asset_count: int, reason: ?string}`, and both record `motion.customer.healthcheck.failed` (platform side only — customer-site has no audit-event system to record into, per the audit) with the same failure taxonomy: `manifest_unavailable` / `no_active_package` / `site_inactive` / `license_denied` / `motion_adapter_disabled` (whatever `prepare()`/`fetchAndValidate()` actually failed on), `checksum_unstable`, `empty_manifest`/`empty_package`.

## Pre-activation checks (task section 9) — logical equivalents actually implemented

| Task's check | What was actually built and tested |
|---|---|
| Site responds normally | Not executed — would require a real HTTP request to a real site, out of scope this session (see AUDIT_REPORT.md §6) |
| Existing homepage renders | Same |
| Current deployment healthy | Delegated to the user's own independent Deployment System — out of this session's authority |
| Database reachable if required | Implicitly proven by every test in this phase running against a real MySQL connection |
| Target storage writable if required | Not applicable — this phase writes nothing to any filesystem, only `sites.features` (a DB column) |

## Post-activation checks (task section 9) — logical equivalents actually implemented and passing

| Task's check | Implementation | Test |
|---|---|---|
| Homepage still responds / no new server error | Not executed against a real site (see above) — but proven for the API endpoint itself: every gate failure returns a clean `200 {"available": false, ...}`, never a 500, across 9 HTTP-level test cases | `CustomerRuntimeManifestApiTest` (9/9 passing) |
| Motion manifest loads | `MotionRuntimeManifestReaderTest::test_it_accepts_a_valid_manifest_and_resolves_the_reference` | Passing |
| Checksum is valid | `MotionCustomerRuntimeServiceTest::test_checksum_is_stable_across_two_builds_of_the_same_state` + `MotionRuntimeManifestReaderTest`'s checksum-pattern rejection tests | Passing |
| Selected Motion asset renders | `MotionRuntimeRendererTest` (7 tests: html/web-component/svg/lottie-stub paths) | Passing |
| No source/AI metadata appears | `CustomerRuntimeManifestApiTest::test_the_response_never_contains_forbidden_fields` (server) + `MotionRuntimeManifestReaderTest::test_it_rejects_a_manifest_carrying_a_forbidden_field` (client, independent second layer) | Passing |
| Page remains functional without JavaScript where applicable | The `svg` render path (`<img>`) and the `html` iframe path both work without any customer-site-authored JavaScript; only the `web-component`/`canvas` path requires the SRI-checked `<script type="module">` to execute — an honest, documented limitation of that specific asset type, not a gap in this phase's own code |
| Page load regression within an explicitly documented acceptable threshold | **Not measured — no real page exists to measure.** Per the task's own instruction ("Do not claim performance success without measured results"), no performance claim is made here. The manifest fetch itself is a single cached (300s) HTTP call with an 8s timeout (`MotionRuntimeManifestReader::fetchAndValidate()`), which is the only new network operation introduced — its real-world latency impact is unmeasured and should be the first thing checked in a real rehearsal |

## Honest summary

Every check that can be verified without a real customer site and without server/deployment execution has been verified, by an automated test, in this phase. Every check that inherently requires either was not performed and is not claimed to have been.
