# Custom/Bespoke Motion — Audit & Architecture Decision

## 1. Audit of current architecture

Before writing any code, the following was confirmed by reading the real files (not assumed):

**The module already models per-site ownership.** `SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic` has `ownership_type` (`global` / `customer_specific`) + `owner_site_identifier`, enforced in `assertOwnership()`. `MotionGraphicVersion` is already a full, immutable, non-deletable version history (`booted()` blocks both `updating` on immutable fields and `deleting` outright), with `status`, `approved_by_actor_identifier`/`published_by_actor_identifier`, and `source_checksum`. This is **not new** — it predates this phase and has never been wired to commerce or a per-site assignment/rollback mechanism.

**Ownership is already enforced at the read boundary, not re-implementable.** `MotionRuntimeProjectionReadContract::projectAsset(assetId, siteId)` enforces `customer_specific` ownership internally (`docs/motion-persistence/OWNERSHIP_RULES.md`): "A site can activate a global asset, or a customer-specific asset that it owns; it can never activate a customer-specific asset owned by another site — enforced by the module, not re-implemented here." `DatabaseMotionAssetReader` never reads hidden/forbidden columns (`source_definition_json`, `ai_metadata_json`, etc.) — Master Asset protection is structural, already proven by Phases 1-8's `ForbiddenFieldGuard` tests, and untouched by this phase.

**The existing per-site assignment mechanism (Phase 3) is template-shaped, not a fit for direct bespoke sales.** `motion_site_asset_packages` requires a `template_asset_package_id` (a required FK to a `TemplateAssetPackage`, which declares a template's *required/optional asset bundle*). A one-off bespoke asset commissioned for a single customer, unrelated to any template, does not fit that shape — forcing it through would misuse "template requirements" to mean "one bespoke purchase," and blur an already-tested, documented concept. Its append-only rollback log (`AssetActivation`, `activation_type` install/upgrade/rollback) is the right *pattern*, reused (not duplicated) below.

**The Phase 9 commerce pipeline (`Module`→`Product`→`Invoice`/`InvoiceItem`→`OrderFulfillmentService`→`ModulePurchase`/`ModuleLicense`) is module-granularity, not asset-granularity.** `ModuleLicense` answers "does this site have Motion Graphics at all" (one row per site+module); it cannot express "which specific bespoke asset." `Product` has `module_id`/`support_plan_id` sibling nullable FKs for its existing 3 types — an extensible, additive pattern, not a fixed shape.

**No existing per-customer bespoke-commerce precedent exists elsewhere on the platform** except `ThemeLicense` (`site_id`+`theme_id` pairs, permanent, no versioning) — informative as a "per-asset license" precedent, but it has no version pinning, no business-lifecycle status, and no purchase-before-assignment gate, so it's a partial precedent, not a reusable model.

## 2. Final architecture decision

| Question (task §5-6) | Decision | Why |
|---|---|---|
| Independent Product, variant, or Module extension? | **New Product type** (`Product::TYPE_MOTION_ASSET`), same model, no new commerce system | Fulfillment logic must differ from `module_addon` (no auto `site_modules` activation) — reusing `TYPE_MODULE_ADDON` would route it through `fulfillModuleAddon()`, which auto-activates. A 4th sibling type is the same extensible pattern already used 3 times, not a parallel system. |
| New Entitlement or reuse `ModuleLicense`? Need a separate `AssetLicense`? | **Both layers, no duplication.** `ModuleLicense` stays the *prerequisite* gate (a site must have Motion Graphics runtime enabled at all — reused unmodified). A new, thin `MotionBespokeAssignment` is the *asset-granular* gate `ModuleLicense` structurally cannot express. | `ModuleLicense` is one row per site+module; it cannot say "yes to asset A, no to asset B." Defense in depth: both checks run in `MotionBespokeAssignmentService::assign()`. |
| `MotionAsset`/`MotionVersion` — build new? | **No. Reused as-is.** A bespoke asset is just a `MotionGraphic` row with `ownership_type=customer_specific`, already built, already ownership-enforced at the read boundary. | Building new `MotionAsset`/`MotionVersion` models would duplicate an existing, tested, more mature system. |
| `MotionAssignment` — build new? | **Yes**, `motion_bespoke_assignments` (+ append-only `motion_bespoke_assignment_events`) | The existing Phase 3 mechanism is template-shaped (see audit); this applies the *same* current-pointer + append-only-history pattern to the direct, non-templated case — not a competing design. |
| `MotionPurchase` — build new? | **Yes**, `motion_bespoke_purchases` | No existing table records a purchase against one specific asset (only against a whole module). |
| `MotionProductLink` — build new? | **No separate table.** `motion_bespoke_commissions.product_id` (nullable FK to `products`) | A commission-to-product relationship is naturally 1:1; a whole extra pivot model for that is unjustified. `products` table itself is **not modified at all** — zero schema risk to existing commerce. |

**Net new tables: 4** (`motion_bespoke_commissions`, `motion_bespoke_assignments`, `motion_bespoke_assignment_events`, `motion_bespoke_purchases`). **Net changes to existing tables: 0.** Net new Product type constant: 1. Net new `OrderFulfillmentService` branch: 1 (additive `case`, no existing branch touched).

### Business-status lifecycle (task §3: draft→designing→review→approved→published→assigned→archived)

`motion_bespoke_commissions.status` owns 6 of the 7 states (`draft|designing|review|approved|published|archived`) as a **business/commercial workflow**, deliberately distinct from the module's own per-version **content-readiness** status (`draft|review|approved|published|retired`) — these answer different questions ("is this commission ready to sell" vs. "is this specific version's content safe to read") and conflating them would have forced a schema change on the module's own table, which is out of scope. `"assigned"` (state 7) is **not a stored status** — it is the existence of an active `motion_bespoke_assignments` row for the commission, exactly mirroring how Phase 3 treats "activated" as a row's existence, not a flag to keep in sync.

### Commerce flow (task §2-3)

`Product (type=motion_asset, linked via motion_bespoke_commissions.product_id)` → `Invoice`/`InvoiceItem` (unmodified) → `OrderFulfillmentService::fulfillMotionAsset()` (new, additive branch) → `MotionBespokePurchase` row **only** → *(deliberate stop — no assignment, no activation)* → Platform Admin calls `MotionBespokeAssignmentService::assign()` **only if** the commission is `published`, a `MotionBespokePurchase` exists for that site, and the base `ModuleLicense` entitlement is valid → `MotionBespokeAssignment` created/updated + `MotionBespokeAssignmentEvent` logged (install/upgrade/rollback/revoke, append-only, never deleted).

## 3. Files changed/added

**Migration (additive only, 0 existing tables touched):**
- `database/migrations/2026_08_09_100000_create_motion_bespoke_commercialization_tables.php`

**Models (new, `app/Modules/MotionGraphics/Models/`):**
- `MotionBespokeCommission.php`, `MotionBespokeAssignment.php`, `MotionBespokeAssignmentEvent.php`, `MotionBespokePurchase.php`

**Services (new, `app/Modules/MotionGraphics/Services/`):**
- `MotionBespokeCommissionService.php` — admin-only create/transition/attachAsset/linkProduct/sitesUsing
- `MotionBespokeAssignmentService.php` — admin-only assign/rollback/revoke, purchase- and entitlement-gated
- `MotionBespokeCustomerRuntimeService.php` — customer-safe bespoke manifest + commission status list

**Existing files extended (additive changes only):**
- `app/Models/Product.php` — `+1` type constant (`TYPE_MOTION_ASSET`) and label
- `app/Services/OrderFulfillmentService.php` — `+1 case` in the existing type-dispatch switch, `+1` new private method (`fulfillMotionAsset()`); no existing branch modified
- `app/Http/Controllers/Api/MotionCustomerRuntimeApiController.php` — `+2` methods (`bespokeManifest`, `bespokeCommissions`), constructor gains 1 new injected service; Phase 8's `manifest()` method is **untouched**
- `routes/api.php` — `+2` routes in the existing `tenant.api` group
- `routes/web.php` — `+1` new nested route group under the existing admin `motion-graphics/{site}` prefix

**New (admin controller + view):**
- `app/Http/Controllers/Admin/MotionBespokeAdminController.php`
- `resources/views/admin/motion-graphics/bespoke/index.blade.php`

## 4. Migrations

One migration, four tables, fully additive and fully reversible (`down()` drops exactly what `up()` created, in dependency order). No existing table's schema changes. See file for full column list and FK policy (restrictOnDelete to `sites`/`products`/`invoices`/`users`; cascadeOnDelete only between the new tables' own parent/child rows, matching Phase 3's established convention). Verified applying cleanly against `saas_platform_test`.

## 5. Tests

`tests/Feature/Modules/MotionGraphics/BespokeCommercialization/`:
- `CommissionLifecycleTest.php` — status transition graph (valid/invalid moves), admin-only gate, asset-lock after publish
- `PurchaseAndAssignmentFlowTest.php` — the full task §2-4 flow: purchase creates only a `MotionBespokePurchase` (never an assignment); assignment rejected without a purchase even when published; assignment rejected pre-publish; install → upgrade → rollback preserves full history; revoke never deletes the row
- `SecurityTest.php` — task §10's four checks: Site A never sees Site B's bespoke asset via the real `tenant.api` HTTP channel; direct API access denied with no entitlement / flag off; an unpublished commission can never be assigned by guessing the admin route; a version the module never marked readable can never be assigned; a customer actor gets 403 on every admin bespoke route; the commission-status endpoint is hard-scoped per site

## 6. Possible risks

- **Two pre-existing, environment-level test issues were found and confirmed unrelated to this phase** (reproduced identically on untouched files):
  1. Redis was not running locally, causing 500s on any code path touching cache/session-backed features — fixed by starting the bundled Laragon Redis service (a local environment action, no code/config changed). Reproduced against Phase 9's own untouched, previously-passing `SecurityValidationTest.php` before the fix; confirmed passing after.
  2. A CSRF/419 regression affects **every** `web`-group admin POST route under `php artisan test` on this machine right now, including Phase 6/7's own untouched `AccessControlTest`, `AdminWorkflowSuccessTest`, `AdminSecurityTest`, and `InternalSiteActivationTest` (9 of the full regression run's 12 failures — none of these files were touched by this phase). Framework-level `runningUnitTests()` should bypass CSRF automatically during tests but currently doesn't on this environment; root cause not fully isolated within this phase's scope. Worked around **only inside this phase's own new test files** with a documented `withoutMiddleware(VerifyCsrfToken::class)` so they exercise real logic; the production route registrations themselves are untouched and still require a real CSRF token (`@csrf` is present in the new Blade view). Recommend a follow-up outside this phase's scope.
- `fulfillMotionAsset()` fails silently (logs and returns) if no commission is linked to the purchased product at fulfillment time — mirrors the existing codebase's convention of not throwing mid-invoice-fulfillment, but means an admin must link the Product to a commission *before* selling it, or a real payment could land with no matching commission. Documented, not silently fixed with an assumption.
- The business-status lifecycle and the module's own content-readiness status are two independent state machines by design (§2) — an admin could theoretically mark a commission `published` while its linked module-side version is still `draft`; `assign()` still fails closed in that case (`findByCanonicalId()` returns null for unreadable versions), so this cannot produce a live security gap, only a confusing admin-facing state worth a future UI hint.

## 6b. Full regression

`php artisan test --filter=MotionGraphics`: **298 passed, 12 failed** (up from Phase 9's 286/289). All 12 failures confirmed pre-existing and unrelated: 3 are the same `MotionGraphicsOwnershipTest` "Unknown column 'email'" gap documented in every phase's report since before this session began; 9 are the CSRF environment issue in §6, all in files this phase never touched. This phase's own 21 new tests: 21/21 passing.

## 7. Commit/deploy confirmation

No commit, no push, no deploy was performed by this phase. All changes are additive and reversible (a plain migration rollback removes 100% of the new schema; no existing table, model, route, or service method was modified in a breaking way).
