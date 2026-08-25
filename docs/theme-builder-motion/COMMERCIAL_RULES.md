# Commercial Rules — Motion as a Template Differentiator

## Reused, not duplicated

- **Payment**: the existing Theme marketplace purchase flow (unchanged) is the only way money changes hands for a Motion-bearing template.
- **Entitlement**: Motion's own gate — `RealMotionLicenseGateway` → `ModuleLicenseAdapter::checkEntitlement($site, 'motion-graphics')` — is coarse and **module-level**, not per-asset (confirmed by reading `RealMotionLicenseGateway`'s own docblock). The `motion-graphics` module is seeded `free_assignable = true`, `license_required = false` — there is exactly **one** gate, not two.

## How Motion is sold with Clinic Premium

**Bundled with template**, not a separate purchase:

- `Theme.settings.motion.mode = "bundled"` — the customer who buys Clinic Premium does not see or complete a second Motion purchase.
- Because there is only one entitlement gate (the site's own `motion-graphics` module access, already `free_assignable`), there is no double-selling risk to avoid — there is nothing to double-sell against.
- What Motion actually needs beyond the theme purchase is **Platform Admin activation** (`/admin/motion-graphics/{site}/themes` → assign → activate), because `installTemplateForSite()`'s authorization is Platform-Admin-only by design (see `INTEGRATION_ARCHITECTURE.md`). This is a fulfillment step, not a commercial gate — the customer has already paid for everything they need at the moment of theme purchase.

## The three modes this contract supports (for future templates)

| Mode | Meaning | Where declared |
|---|---|---|
| `bundled` | Included in the template price, no separate step for the customer | `settings.motion.mode` |
| `addon` | Template works without it; customer can request/purchase extra motion via the existing Phase 11 bespoke flow (`dashboard/motion-graphics`, already real, unmodified) | same |
| `required` | Template is not commercially complete without it (not used by Clinic Premium; reserved for a future fully-animated template) | same |

Only `bundled` is exercised by this integration's proof template. `addon`/`required` reuse the exact same declaration field — no new commercial code path needed when a template author chooses one of them.
