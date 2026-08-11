# Module Positioning

## Classification

**Motion Graphics Engine** — `type = paid` (`App\Models\Module::TYPE_PAID`), sold as a `Product::TYPE_MODULE_ADDON`.

| Field | Value | Why |
|---|---|---|
| Category | Design Enhancement (`design_enhancement`) | Matches the task's own example exactly |
| Pricing mode | One-time (`ModuleLicense::TYPE_ONE_TIME`), configurable price (`price_irr`) | No subscription/recurring billing exists elsewhere on this platform to model after; one-time purchase is the real, existing pattern every other paid module uses |
| Availability | `marketplace_visible = true`, discoverable via `Module::paidMarketplace()` | The real, only-live customer marketplace scope |
| License requirement | `license_required = false` (so `getDistributionMode()` resolves to `PAID_MARKETPLACE`, not `LICENSE_ONLY`) | See AUDIT_REPORT.md §5 — this is the specific combination that reaches `ModuleLicenseAdapter::checkEntitlement()`'s real per-site `ModuleLicense` check |
| Dependencies | None | Motion Graphics has no hard dependency on another module; the standalone module's own asset library is independent infrastructure, not a platform `Module` dependency |
| Compatibility | Documented, not schema-enforced | No `Module` column exists for structured compatibility data (verified — not adding one, per "do not redesign architecture"); compatibility is asset-level, already handled by `TemplateBuilderCompatibilityValidator` (Phase 4, unchanged) |

## Why `type = paid`, not a new `type = premium`/`type = addon`

The task suggests "premium or add-on" as the classification. Verified directly: `Module` has exactly two types, `TYPE_CORE` and `TYPE_PAID` (`app/Models/Module.php:9-10`) — there is no `premium` or `addon` `Module` type anywhere in the codebase, and "add-on" is a `Product` type (`Product::TYPE_MODULE_ADDON`), not a `Module` type (AUDIT_REPORT.md §1/§3). Introducing a new `Module::TYPE_PREMIUM` would be exactly the kind of "duplicate the Module system" the STRICT RULES forbid — a third type nothing else in the platform understands, requiring new branching in `getDistributionMode()`/`checkEntitlement()`/every scope that switches on type. `type = paid` + `Product::TYPE_MODULE_ADDON` **is** how "premium add-on module" is already expressed on this platform; Motion Graphics uses that expression unmodified.

## Support matrix (task requirement: "Must support")

| Requirement | How |
|---|---|
| Included in plans | No Plan model exists (PLAN_INTEGRATION.md) — not applicable as a literal bundle; conceptually, a future Plan layer would reference this same `Product`/`Module` row, nothing Motion-specific to add |
| Optional purchase | `Product` row + `Invoice`/`InvoiceItem` + `OrderFulfillmentService::fulfill()` — real, unmodified, proven in SiteActivationFlowTest |
| Temporary license assignment | `ModuleLicense.expires_at` (nullable) — an admin (or, per platform convention, a future purchase flow selling a term license) sets an expiry; `hasValidLicense()` already treats `expires_at` correctly, proven in EntitlementRulesTest |
| Free internal assignment | `ModuleLicenseService::grantByAdmin()` (existing, unmodified) — no purchase required, proven in EntitlementRulesTest |

## What changed from Phase 6, and why that's not a regression

Phase 6 set `type = core, marketplace_visible = false` specifically so Motion Graphics had zero commercial/customer-facing surface while its technical integration was being proven safe. That goal is now met (Phases 6-8 complete, tested, documented). Phase 9's goal is different: make it sellable. Reversing that one seeder classification is the intended mechanism for that goal, not an accidental undoing of Phase 6's work — every technical mechanism Phase 6-8 built (`RealMotionLicenseGateway`, `MotionCustomerRuntimeService`, `MotionCustomerActivationService`, the manifest contract, the customer-site consumer) is reused completely unmodified in its actual logic; only the `Module` row's classification and the addition of a `Product` row changed.
