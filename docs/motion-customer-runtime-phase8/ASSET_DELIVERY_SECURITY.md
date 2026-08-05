# Asset Delivery and Security

## Chosen strategy: deployment-packaged optimized runtime files, referenced by root-relative URL

Of the task's three suggested options (immutable CDN/object-storage URLs, signed-download + local immutable cache, deployment-packaged optimized runtime files), this phase uses the third, matching what already existed: `optimized_reference` is a root-relative path (e.g. `/motion/motion-1/v1/output.json`) that customer-site resolves against `platform_url` to form the fetch URL. The underlying asset files themselves are served by whatever already serves `packages/saas-platform/modules/motion-graphics`'s optimized output today — unchanged by this phase (no asset-serving route was added or modified; only the *manifest* describing which references are approved is new).

## Requirement-by-requirement

| Requirement | How it's met |
|---|---|
| Checksum validation | Every asset carries `checksum` (sha256); `MotionRuntimeManifestReader::isStructurallyValid()` requires it to match `^[a-f0-9]{64}$` before accepting the manifest at all |
| MIME validation | `asset_type` is restricted to a fixed enum (`KNOWN_ASSET_TYPES`: lottie, svg, html, canvas, web-component, motion-json) mirroring the platform's own `allowed_asset_types` config; an unknown type is rejected outright |
| File-size limit | Inherited unchanged from the platform's existing `motion-graphics.php` config (`limits.max_asset_bytes` = 5MB) — Phase 8 adds no new upload path, so no new size-limit surface exists |
| SVG sanitization or pre-optimized trusted output | **No SVG sanitizer exists in customer-site (confirmed in the audit — a real, pre-existing gap)**, so the design deliberately never asks customer-site to sanitize anything: `optimized_reference` is always a platform-served, already-optimized/trusted output, and `MotionRuntimeRenderer` renders an `svg` asset type as a plain `<img src="...">` tag — `<img>` cannot execute embedded `<script>`/`onload` handlers even in a worst-case compromised-source scenario, so this is safe without needing sanitization infrastructure that doesn't exist |
| CSP compatibility | Customer-site has **no CSP configured at all** (confirmed in the audit). This phase does not add one (out of scope — a platform-wide security posture change, not a Motion Graphics concern) but the renderer's choices are CSP-friendly by construction if one is added later: `sandbox="allow-scripts"` iframes, SRI-checked `<script type="module" integrity="...">` tags, and plain `<img>` — no inline `<script>`, no `javascript:` URLs, no `eval` |
| Path traversal prevention | `MotionRuntimeManifestReader::isSafeRelativeReference()` requires the reference to start with `/` and explicitly rejects `//` (protocol-relative) — combined with root-relative resolution against a single trusted `platform_url`, there is no way for a reference to escape to an arbitrary path or host. Proven by `MotionRuntimeManifestReaderTest::test_it_rejects_a_protocol_relative_optimized_reference` |
| No executable upload | Phase 8 adds no upload endpoint anywhere, in either repository |
| No arbitrary remote URL acceptance | `isSafeRelativeReference()` rejects any `http://`/`https://` absolute reference — only root-relative paths resolved against the single configured `platform_url` are ever fetched. Proven by `test_it_rejects_an_absolute_remote_optimized_reference` |

## Trust boundary

Customer-site trusts exactly one origin (`config('site.platform_url')`) and exactly one credential (the site's own `License::current()->license_key`) — both pre-existing, both unchanged by this phase. No other origin, token, or asset source is ever consulted.
