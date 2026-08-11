# Customer Flow

`GET /dashboard/motion-graphics` (`customer.motion-graphics.index`, `App\Http\Controllers\Customer\MotionGraphicsController::index()`), inside the existing authenticated customer dashboard (`layouts.admin`, the same shell `customer/forms` and `customer/support-tickets` already use).

One page, four sections, matching the task's requested nav (kept as sections rather than separate pages/tabs — same information, fewer new routes):

| Section | Data source | Task requirement covered |
|---|---|---|
| **وضعیت لایسنس** (License Status) | `ModuleLicenseService::hasValidLicenseBySlug($site, 'motion-graphics')` (Phase 9, unmodified) | License status, entitlement status |
| **موشن‌های فعال روی سایت** (My Assets / runtime) | `MotionBespokeCustomerRuntimeService::bespokeAssetsForSite()` (Phase X, unmodified) — published + assigned only | Assigned motion assets, published runtime status |
| **موشن‌های سفارشی من** (Custom Requests / Purchased Motions) | `MotionBespokeCustomerRuntimeService::commissionsStatusForSite()` (Phase X, unmodified) | Purchased motion assets, design status |
| **درخواست موشن سفارشی جدید** (request form) | new `MotionBespokeCommissionService::requestFromCustomer()` | Customer request submission |

Multi-site customers get a `?site=` selector (mirrors `StoreController::modules()`'s `preselectedSiteId` pattern); every section is scoped to the selected site only, re-verified against `auth()->user()->customer->sites` on every request.

When Motion Graphics isn't licensed for the site: the License Status section shows a clear "not purchased" state with a direct link into the **existing** `/store/modules` page — no new purchase mechanism, matching `docs/motion-commercialization/CUSTOMER_ACCESS_MODEL.md`'s "upgrade suggestion" pattern from Phase 9.
