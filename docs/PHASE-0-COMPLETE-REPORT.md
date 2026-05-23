# PHASE 0 — ARCHITECTURE & RULE AUDIT: Complete Report

> **Date:** 2026-05-22
> **Status:** ✅ COMPLETE — Analysis Only, No Refactoring

---

## 1. DUPLICATE BLADE MAP

### 1.1 Identical Blade Files (Same Name, Same Purpose)

| Blade File | saas-platform | customer-site | Notes |
|---|---|---|---|
| `components/persian-captcha-challenge.blade.php` | ✅ | ✅ | Near-identical |
| `components/persian-captcha-math.blade.php` | ✅ | ✅ | Near-identical |
| `components/persian-date-input.blade.php` | ✅ | ✅ | Near-identical |
| `components/module-features.blade.php` | ✅ | ✅ | Different content (saas has Copy.php) |
| `partials/auth-captcha.blade.php` | ✅ | ✅ | Near-identical |
| `partials/guide-widget.blade.php` | ✅ | ✅ | Near-identical |
| `partials/vazirmatn-font-faces-inline.blade.php` | ✅ | ✅ | Near-identical |
| `partials/platform-styles.blade.php` | ✅ | ✅ | Different per-project |
| `layouts/auth-login.blade.php` | ✅ | ✅ | Similar structure |
| `layouts/auth.blade.php` | ✅ | ✅ | Similar structure |
| `auth/login.blade.php` | ✅ | ✅ | Similar structure |
| `install/step1.blade.php` | ✅ | ✅ | Different content |
| `install/step3.blade.php` | ✅ | ✅ | Different content |
| `install/done.blade.php` | ✅ | ✅ | Different content |

### 1.2 Similar Admin Views (Same Concept, Different Implementation)

| Concept | saas-platform | customer-site | Drift Level |
|---|---|---|---|
| `admin/forms/` | create, edit, guide, index, preview, sites, submissions | create, edit, guide, index, preview, submissions | **Medium** — saas has `sites.blade.php` extra |
| `admin/menus/` | create, edit, guide, index, partials/item | create, edit, index, preview, partials/item, partials/item-sortable, partials/go-live-at-*, partials/preview-tree | **High** — customer-site has richer menu features |
| `admin/pages/` | create, edit, form, index, partials/* | create, edit, index | **Medium** — saas has form.blade.php + partials |
| `admin/captcha/` | guide, index, site | index | **Low** — saas has more views |
| `admin/activity-log/` | guide, index | guide, index | **Low** — similar |
| `admin/settings/` | index | index | **Low** — similar |
| `admin/modules/` | index, edit, confirm-* | index, show, changelog | **High** — completely different purpose |
| `admin/support-tickets/` (saas) vs `admin/support/` (customer) | create, guide, index, settings, show | create, guide, index, show, history | **Medium** — similar CRUD, different naming |
| `admin/licenses/` | index | index | **Low** — similar |
| `admin/notifications/` (saas: sites/notifications/) | create, edit, index, _form, create-bulk | create, edit, index, archive, send-users | **Medium** — different scope |

### 1.3 Unique Admin Views (saas-platform only)

- `admin/dashboard.blade.php`, `admin/dashboard/guide.blade.php`
- `admin/access/guide.blade.php`, `admin/access/index.blade.php`
- `admin/customers/create.blade.php`, `edit.blade.php`, `form.blade.php`, `guide.blade.php`, `index.blade.php`
- `admin/module-labels/guide.blade.php`, `index.blade.php`
- `admin/module-licenses/index.blade.php`
- `admin/payment-gateways/create.blade.php`, `edit.blade.php`, `guide.blade.php`, `index.blade.php`, `_form.blade.php`
- `admin/payout-requests/index.blade.php`
- `admin/platform-settings/guide.blade.php`, `index.blade.php`
- `admin/products/create.blade.php`, `edit.blade.php`, `guide.blade.php`, `_form.blade.php`
- `admin/profile-fields/admin.blade.php`, `create.blade.php`, `dashboard.blade.php`, `edit.blade.php`, `guide.blade.php`, `index.blade.php`
- `admin/reseller-commissions/guide.blade.php`, `index.blade.php`
- `admin/resellers/create.blade.php`, `edit.blade.php`, `guide.blade.php`, `index.blade.php`, `_form.blade.php`
- `admin/seo/index.blade.php`
- `admin/sites/create.blade.php`, `edit.blade.php`, `guide.blade.php`, `index.blade.php`, `license-create.blade.php`, `license-show.blade.php`, `modules.blade.php`, `updates.blade.php`
- `admin/support-plans/create.blade.php`, `edit.blade.php`, `index.blade.php`
- `admin/theme-builder/clone.blade.php`, `create.blade.php`, `edit.blade.php`, `index.blade.php`, `preview-frame.blade.php`
- `admin/updates/index.blade.php`
- `admin/users/create.blade.php`, `edit.blade.php`, `guide.blade.php`, `index.blade.php`, `_bulk_modal.blade.php`, `_delete_modal.blade.php`, `_filters.blade.php`, `_table.blade.php`, `_toast.blade.php`

### 1.4 Unique Admin Views (customer-site only)

- `admin/accounting/index.blade.php`
- `admin/advertisements/create.blade.php`, `edit.blade.php`, `index.blade.php`
- `admin/builder-health/index.blade.php`
- `admin/business-cards/create.blade.php`, `edit.blade.php`, `index.blade.php`
- `admin/categories/create.blade.php`, `edit.blade.php`, `index.blade.php`
- `admin/coupons/create.blade.php`, `edit.blade.php`, `guide.blade.php`, `index.blade.php`
- `admin/courses/create.blade.php`, `edit.blade.php`, `index.blade.php`
- `admin/currency-reform/index.blade.php`
- `admin/invoices/create.blade.php`, `index.blade.php`, `show.blade.php`
- `admin/messaging/edit.blade.php`, `index.blade.php`
- `admin/module-store/index.blade.php`
- `admin/notifications/archive.blade.php`, `send-users.blade.php`
- `admin/orders/guide.blade.php`, `index.blade.php`, `show.blade.php`
- `admin/platform-modules/show.blade.php`
- `admin/post-series/create.blade.php`, `edit.blade.php`, `index.blade.php`
- `admin/posts/create.blade.php`, `edit.blade.php`, `guide.blade.php`, `index.blade.php`, `revisions.blade.php`, `_editor_scripts.blade.php`
- `admin/product-attributes/create.blade.php`, `edit.blade.php`, `index.blade.php`
- `admin/product-categories/create.blade.php`, `edit.blade.php`, `index.blade.php`
- `admin/products/create.blade.php`, `edit.blade.php`, `form.blade.php`, `guide.blade.php`, `index.blade.php`, `_editor_scripts.blade.php`
- `admin/referrals/index.blade.php`
- `admin/reviews/index.blade.php`
- `admin/seo-advanced/index.blade.php`
- `admin/shop-product-reviews/index.blade.php`
- `admin/sms/index.blade.php`
- `admin/social-links/create.blade.php`, `edit.blade.php`, `index.blade.php`
- `admin/subscriptions/create.blade.php`, `edit.blade.php`, `index.blade.php`
- `admin/theme-design/index.blade.php`
- `admin/whatsapp/index.blade.php`

---

## 2. DUPLICATE CONTROLLER MAP

### 2.1 Same-Name Controllers (Different Implementations)

| Controller | saas-platform | customer-site | Drift |
|---|---|---|---|
| `FormController` | Admin CRUD + site assignment | Admin CRUD (simpler) | **Medium** |
| `MenuController` | Admin CRUD | Admin CRUD + preview | **Medium** |
| `PageController` | Admin CRUD + front show | Admin CRUD + front show | **Low** |
| `CaptchaController` | Admin settings | Admin settings | **Low** |
| `ModuleController` | Full CRUD + bulk + destructive confirm | Display-only + settings | **High** |
| `SettingController` | Platform settings | Site settings | **Medium** |
| `DashboardController` | Admin dashboard | Admin dashboard | **Low** |
| `FormFrontendController` (site/) | Site form frontend | Site form frontend | **Low** |

### 2.2 Similar Concept Controllers (Different Names)

| Concept | saas-platform | customer-site |
|---|---|---|
| Activity Log | `Admin\ActivityLogController` | `Admin\AdminActivityLogController` |
| Support Tickets | `Admin\SupportTicketController` | `Admin\SupportController` |
| Notifications | `Admin\SiteNotificationController` | `Admin\NotificationController` |
| License | `Admin\PlatformLicenseController` | `Admin\LicenseController` |
| Products | `Admin\ProductController` (platform store) | `Admin\ProductController` (site shop) |

### 2.3 Unique Controllers (saas-platform)

- `ApiController`, `CheckoutController`, `StoreController`
- `Admin\AccessMenuController`, `Admin\CustomerController`, `Admin\FormFieldController`, `Admin\FormSiteController`, `Admin\FormSubmissionController`
- `Admin\GuideSearchController`, `Admin\ModuleLabelController`, `Admin\ModuleLicenseController`
- `Admin\PaymentGatewayController`, `Admin\PayoutRequestController`, `Admin\PlatformLicenseController`
- `Admin\PlatformNotificationTemplateController`, `Admin\PlatformSettingsController`
- `Admin\ProfileFieldController`, `Admin\ResellerCommissionController`, `Admin\ResellerController`
- `Admin\SeoController`, `Admin\SiteController`, `Admin\SiteLicenseController`, `Admin\SiteModuleController`
- `Admin\SiteNotificationController`, `Admin\SiteUpdateController`, `Admin\SupportPlanController`
- `Admin\SupportTicketController`, `Admin\SupportTicketSettingsController`, `Admin\ThemeBuilderController`
- `Admin\UpdateDashboardController`, `Admin\UserController`
- `Api\GroupApiController`, `Api\LicenseController`, `Api\ProductController`, `Api\SiteConfigController`
- `Api\SupportTicketController`, `Api\TagApiController`, `Api\TenantMarketplaceController`, `Api\UpdateController`, `Api\UserApiController`
- `Customer\CustomerFormController`, `Customer\CustomerSupportTicketController`
- `Reseller\DashboardController`, `Reseller\PayoutRequestController`

### 2.4 Unique Controllers (customer-site)

- `GuideSearchController`, `InstallController`, `PaymentCallbackController`
- `Account\DashboardController`, `Account\FormController`, `Account\NotificationController`, `Account\OrderController`
- `Account\ProfileActivityController`, `Account\ProfileAddressController`, `Account\ProfileAvatarController`
- `Account\ProfileController`, `Account\ProfileDeletionController`, `Account\SecurityController`, `Account\SubscriptionController`
- `Admin\AccountingController`, `Admin\AdvertisementController`, `Admin\BuilderHealthController`
- `Admin\BusinessCardController`, `Admin\CategoryController`, `Admin\CouponController`, `Admin\CourseController`
- `Admin\CurrencyReformController`, `Admin\ImageUploadController`, `Admin\InvoiceController`
- `Admin\MessagingController`, `Admin\ModuleChangeLogController`, `Admin\ModuleStoreController`
- `Admin\PlatformModuleController`, `Admin\PostController`, `Admin\PostSeriesController`
- `Admin\ProductAttributeController`, `Admin\ProductCategoryController`, `Admin\ReferralController`
- `Admin\ReviewController`, `Admin\SeoAdvancedController`, `Admin\ShopProductReviewController`
- `Admin\SmsController`, `Admin\SocialLinkController`, `Admin\SubscriptionPlanController`
- `Admin\ThemeDesignController`, `Admin\WhatsappController`
- `Api\SiteMenuApiController`
- `Front\AdvancedSearchController`, `Front\BlogController`, `Front\BusinessCardController`
- `Front\ContactController`, `Front\FormController`, `Front\HomeController`, `Front\InvoiceController`
- `Front\PageBuilderController`, `Front\PageController`, `Front\PlatformProductsController`
- `Front\ShopController`, `Front\SitemapController`

---

## 3. DUPLICATE COMPONENT MAP

### 3.1 Shared Blade Components (Same Name)

| Component | saas-platform | customer-site | Notes |
|---|---|---|---|
| `components/alert.blade.php` | ✅ | ❌ | saas only |
| `components/errors.blade.php` | ✅ | ❌ | saas only |
| `components/flash.blade.php` | ✅ | ❌ | saas only |
| `components/module-features.blade.php` | ✅ | ✅ | Different content |
| `components/persian-captcha-challenge.blade.php` | ✅ | ✅ | Near-identical |
| `components/persian-captcha-math.blade.php` | ✅ | ✅ | Near-identical |
| `components/persian-date-input.blade.php` | ✅ | ✅ | Near-identical |
| `components/seo.blade.php` | ✅ | ❌ | saas only |
| `components/validation-errors.blade.php` | ✅ | ❌ | saas only |
| `components/image-picker.blade.php` | ❌ | ✅ | customer only |

### 3.2 Shared Partials (Same Name)

| Partial | saas-platform | customer-site | Notes |
|---|---|---|---|
| `partials/auth-captcha.blade.php` | ✅ | ✅ | Near-identical |
| `partials/guide-widget.blade.php` | ✅ | ✅ | Near-identical |
| `partials/vazirmatn-font-faces-inline.blade.php` | ✅ | ✅ | Near-identical |
| `partials/platform-styles.blade.php` | ✅ | ✅ | Different per-project |
| `partials/navbar.blade.php` | ✅ | ❌ | saas only |
| `partials/pages-menu.blade.php` | ✅ | ❌ | saas only |
| `partials/platform-admin-sidebar-theme.blade.php` | ✅ | ❌ | saas only |
| `partials/dashboard-stat-tiles-css.blade.php` | ✅ | ❌ | saas only |
| `partials/sidebar-icon.blade.php` (admin/partials/) | ✅ | ✅ | Different icons |

### 3.3 Shared Layouts

| Layout | saas-platform | customer-site | Notes |
|---|---|---|---|
| `layouts/admin.blade.php` | ✅ | ❌ | saas only |
| `layouts/app.blade.php` | ✅ | ❌ | saas only |
| `layouts/auth-login.blade.php` | ✅ | ✅ | Similar |
| `layouts/auth.blade.php` | ✅ | ✅ | Similar |

---

## 4. SHARED MODULE CANDIDATES

### 4.1 High-Priority Candidates (Already Duplicated)

| Module | saas-platform | customer-site | Extraction Complexity |
|---|---|---|---|
| **Forms** | Form/FormField/FormSubmission models, FormController, FormFieldController, FormSiteController, FormSubmissionController, views | Form/FormField/FormSubmission models, FormController, FormSubmissionController, views | **Medium** — different features (saas has site assignment) |
| **Menus** | Menu/MenuItem models, MenuController, views | Menu/MenuItem/MenuChangeLog/MenuRevision models, MenuController, MenuHierarchyService, views | **High** — customer-site has richer features (go-live, preview, revisions) |
| **Pages** | Page model, PageController, views | Page/PageTemplate models, PageController, views | **Medium** — different features |
| **Captcha** | CaptchaController, views | CaptchaController, views | **Low** — simple settings |
| **Activity Log** | ActivityLog model, ActivityLogController, views | AdminActivityLog model, AdminActivityLogController, views | **Low** — similar concept |
| **Settings** | Setting model, SettingController, views | Setting model, SettingController, views | **Low** — similar concept |
| **Support Tickets** | SupportTicket/SupportTicketReply models, SupportTicketController, views | SupportController, views (API-based) | **High** — different architecture (saas is native, customer is API client) |

### 4.2 Medium-Priority Candidates (Similar Concepts)

| Concept | saas-platform | customer-site | Notes |
|---|---|---|---|
| **Notifications** | PlatformSiteNotification model, SiteNotificationController | SiteNotification/NotificationBatch/UserNotification models, NotificationController | Different scope |
| **SEO** | SeoMeta/SeoKeyword/SeoRedirect/SeoPageScore models, SeoController | SeoService, SeoAdvancedController | Different complexity |
| **Roles/Permissions** | Role/Permission models | Role/Permission models | Similar but different usage |
| **Tags** | Tag model | Tag model | Simple, could be shared |

### 4.3 Low-Priority Candidates (Different Context)

| Concept | saas-platform | customer-site | Notes |
|---|---|---|---|
| **Products** | Platform store products | Site shop products | Different purpose |
| **Invoices** | Invoice/InvoiceItem (platform) | Invoice/InvoiceItem (site) | Different context |
| **Users** | User model (platform admins) | User model (site users) | Different roles |

---

## 5. ANTI-MODULAR RULES

### 5.1 Architecture Conflicts Preventing Modularization

| # | Rule/Pattern | Project | Problem | Severity |
|---|---|---|---|---|
| 1 | **Direct model coupling** | Both | Controllers reference models directly instead of through repositories in many cases | 🔴 High |
| 2 | **No shared package registry** | Both | No Composer package or Git submodule for shared code | 🔴 High |
| 3 | **Config-based module activation** | customer-site | `config('modules.modules')` is hardcoded, not synced from platform dynamically | 🟡 Medium |
| 4 | **Blade component duplication** | Both | Shared components (captcha, date-input) are copy-pasted | 🔴 High |
| 5 | **Helper duplication** | Both | `Jalali.php`, `NumberHelper.php` exist in both projects | 🟡 Medium |
| 6 | **Rule duplication** | Both | `IranianMobile.php` rule exists in both | 🟡 Medium |
| 7 | **No interface-first design** | saas-platform | Services don't consistently use interfaces | 🟡 Medium |
| 8 | **Tenant context divergence** | Both | Different TenantContext implementations with different APIs | 🔴 High |
| 9 | **AdminMenu divergence** | Both | Completely different AdminMenu implementations | 🟡 Medium |
| 10 | **No shared test suite** | Both | Tests are project-specific, no cross-project testing | 🟡 Medium |

### 5.2 Rules That Would Break If Modularized

| # | Rule | Why It Would Break |
|---|---|---|
| 1 | `Route::resource` in web.php | Routes are project-specific; shared modules need prefixable routes |
| 2 | Direct view paths like `admin.forms.index` | Views are in project-specific directories |
| 3 | `config('modules.modules')` hardcoded | Would need to be dynamic from platform |
| 4 | `AdminMenu::MENU_KEYS` hardcoded | Menu keys are project-specific |
| 5 | Direct `use App\Models\X` in controllers | Would need to reference shared package namespace |

---

## 6. RISKY WAVES

### 6.1 Waves That Could Cause Migration Issues

| Wave | Description | Risk Level | Reason |
|---|---|---|---|
| **Wave 1** | Security hardening + cleanup | 🟢 Low | Mostly additive changes |
| **Wave 2** | License security hardening (HMAC-SHA256) | 🟢 Low | Well-tested, backward compatible |
| **Wave 3** | Module history API + module catalog audit | 🟢 Low | API additions |
| **Wave 4** | Schema hardening (FKs, support_starts_at) | 🟡 Medium | Schema changes could conflict with modularization |
| **Wave 5.1** | Customer Module Entitlements Ledger | 🟢 Low | Append-only, safe |
| **Wave 5.2** | Per-site destructive confirmation | 🟢 Low | UI-only change |
| **Wave 5.4** | Regression test suite | 🟢 Low | Tests only |
| **Wave 6.1** | Shop data-integrity hardening | 🟡 Medium | Shop-specific, could conflict if shop becomes shared module |
| **Wave 6.2** | Shop test-suite stabilization | 🟢 Low | Tests only |
| **Wave 7.0a** | Test Bootstrap Stabilization | 🟢 Low | Tests only |
| **Wave 7.0b** | Forms Hardening | 🟡 Medium | Forms is a shared module candidate |
| **Wave 7.0c** | UX Uniformity sweep | 🟡 Medium | Changes to Blade views that would need to be replicated |

### 6.2 Waves That Need Reversal/Adaptation

| Wave | Issue | Action Needed |
|---|---|---|
| Wave 7.0c (UX Uniformity) | Changes applied to both projects independently | Need to ensure shared components stay in sync |
| Wave 7.0b (Forms Hardening) | Forms hardening in both projects | Should be extracted to shared module first |

---

## 7. DEPENDENCY GRAPH

### 7.1 saas-platform Internal Dependencies

```
ModuleController
  ├── ModuleAssignmentService
  │     ├── ModulePurchaseService
  │     └── ModuleLicenseService
  ├── EntitlementLedger
  └── Module Model

SiteController
  ├── Site Model
  ├── ModuleAssignmentService
  ├── SupportLicenseService
  └── LicenseServerService

OrderFulfillmentService
  ├── EntitlementLedger
  ├── ModuleLicenseService
  └── SupportLicenseService

UpdateController (API)
  ├── ModuleUpdateEligibilityService
  │     └── SupportLicenseService
  └── Module Model

LicenseController (API)
  └── LicenseServerService

TenantContext
  ├── TenantResolver
  │     └── ResellerDetectionService
  └── Request attributes
```

### 7.2 customer-site Internal Dependencies

```
ModuleController
  ├── License Model
  ├── ModuleVersion Model
  ├── ModuleChangeLog Model
  └── UpdateCheckService

UpdateCheckService
  └── Platform API (HTTP)

LicenseActivationService
  └── Platform API (HTTP)

SupportController
  └── SupportApiClient
        └── Platform API (HTTP)

TenantContext
  └── License Model
```

### 7.3 Cross-Project Dependencies

```
customer-site ──HTTP──▶ saas-platform API
  ├── POST /api/license/validate
  ├── GET  /api/updates/check
  ├── GET  /api/updates/modules
  ├── GET  /api/updates/module-history
  ├── POST /api/updates/report-versions
  ├── GET  /api/site-config/{license}
  ├── GET  /api/products/
  ├── POST /api/support-tickets/
  └── GET  /api/tenant-marketplace/modules
```

---

## 8. MODULE OWNERSHIP MAP

### 8.1 Owned by saas-platform (Central Hub)

| Module/Feature | Type | Description |
|---|---|---|
| License Server | Core | PlatformLicense management, HMAC validation |
| Update Server | Core | Module/site update distribution |
| Module Catalog | Core | Module definitions, types, distribution modes |
| Entitlement Ledger | Core | Append-only grant/revoke audit |
| Store/Checkout | Core | Product sales, cart, payment |
| Reseller System | Core | Reseller management, commissions |
| Support Plans | Core | Support plan products, SLA |
| Theme Builder | Core | Theme management, cloning |
| Platform Settings | Core | Global platform configuration |
| Payment Gateways | Core | Payment gateway management |
| Payout Requests | Core | Reseller payout management |
| Platform Notifications | Core | Notification templates, bulk sending |
| Profile Fields | Core | Custom profile field management |
| Module Labels | Core | Module display name management |

### 8.2 Owned by customer-site (Tenant Site)

| Module/Feature | Type | Description |
|---|---|---|
| Blog/Posts | Core | Blog management, categories, series |
| Shop/Products | Core | E-commerce, orders, cart |
| Courses | Module | Online course management |
| Business Cards | Module | Digital business card |
| Social Links | Module | Social media link management |
| Advertisements | Module | Ad management |
| Coupons | Module | Discount coupon management |
| Reviews | Module | Product/service reviews |
| Referrals | Module | Referral program |
| Subscriptions | Module | Subscription plans |
| Invoices | Module | Online invoicing |
| Messaging/WhatsApp | Module | Social messaging |
| SMS | Module | SMS management |
| Page Builder | Core | Drag-and-drop page builder |
| Theme Design | Core | Site theme customization |
| Builder Health | Core | Builder element health check |
| Accounting | Module | Accounting module |
| SEO Advanced | Module | Advanced SEO tools |

### 8.3 Shared (Duplicated in Both)

| Module/Feature | Current Owner | Target |
|---|---|---|
| Forms | Both (duplicated) | Shared package |
| Menus | Both (duplicated) | Shared package |
| Pages | Both (duplicated) | Shared package |
| Captcha | Both (duplicated) | Shared package |
| Activity Log | Both (duplicated) | Shared package |
| Settings | Both (duplicated) | Shared package |
| Support Tickets | Both (different arch) | API-based (already) |
| Persian Components | Both (duplicated) | Shared package |
| Helpers (Jalali, Number) | Both (duplicated) | Shared package |

---

## 9. UPDATE COMPATIBILITY MAP

### 9.1 Update Flow

```
saas-platform (source of truth)
  │
  ├── Site Template Updates
  │     └── customer-site checks: GET /api/updates/check
  │           └── UpdateCheckService::check()
  │
  ├── Module Updates
  │     └── customer-site checks: GET /api/updates/modules
  │           └── UpdateCheckService::checkModuleUpdates()
  │
  ├── Module Version History
  │     └── customer-site syncs: GET /api/updates/module-history
  │           └── UpdateCheckService::syncModuleChangeLogs()
  │
  └── Version Reporting
        └── customer-site reports: POST /api/updates/report-versions
              └── UpdateCheckService::reportModuleVersions()
```

### 9.2 Update Eligibility

```
saas-platform:
  ModuleUpdateEligibilityService
    ├── isEligibleForModuleUpdates(Site) → SupportLicenseService::isEligibleForUpdates()
    ├── getAvailableModuleUpdates(Site) → filters by eligibility
    └── getUpdateType() → semver-based (patch/minor/major)

customer-site:
  UpdateCheckService
    ├── check() → GET /api/updates/check
    ├── checkModuleUpdates() → GET /api/updates/modules
    ├── syncModuleChangeLogs() → GET /api/updates/module-history
    └── reportModuleVersions() → POST /api/updates/report-versions
```

### 9.3 Compatibility Risks

| Risk | Description | Severity |
|---|---|---|
| **API versioning** | No API versioning (no `/v1/` prefix) | 🟡 Medium |
| **No backward compatibility guarantees** | API changes could break customer-site | 🟡 Medium |
| **No offline fallback** | customer-site requires platform connectivity for updates | 🟡 Medium |
| **No update rollback** | No mechanism to rollback failed updates | 🔴 High |
| **No update staging** | Updates applied directly, no staging environment | 🔴 High |

---

## 10. DANGEROUS ARCHITECTURE CONFLICTS

### 10.1 Critical Conflicts

| # | Conflict | Description | Impact |
|---|---|---|---|
| 1 | **Dual TenantContext** | Both projects have `App\Services\TenantContext` with different APIs | Cannot share services between projects |
| 2 | **Dual AdminMenu** | Both projects have `App\Helpers\AdminMenu` with different structures | Cannot share menu logic |
| 3 | **Dual Module Model** | saas-platform has `App\Models\Module` (catalog), customer-site has `config('modules.modules')` (activation) | Different module concepts |
| 4 | **Dual User Model** | Different User models with different fields/relationships | Cannot share auth logic |
| 5 | **Dual Form Model** | Both have `App\Models\Form` with different schemas | Cannot share form logic |
| 6 | **Dual Page Model** | Both have `App\Models\Page` with different schemas | Cannot share page logic |
| 7 | **Dual Menu Model** | Both have `App\Models\Menu` with different schemas | Cannot share menu logic |

### 10.2 Medium Conflicts

| # | Conflict | Description |
|---|---|---|
| 8 | **Different middleware stacks** | Different tenant resolution, auth, module checking |
| 9 | **Different route structures** | Different route naming conventions |
| 10 | **Different config structures** | Different config files for similar concepts |
| 11 | **No shared exception handling** | Different error handling approaches |
| 12 | **No shared validation rules** | Different validation rule implementations |

---

## 11. TIGHTLY COUPLED MODULES

### 11.1 saas-platform Tight Coupling

| Module | Coupled To | Coupling Type |
|---|---|---|
| ModuleAssignmentService | ModulePurchaseService, ModuleLicenseService | Constructor injection |
| OrderFulfillmentService | EntitlementLedger, ModuleLicenseService, SupportLicenseService | Constructor injection |
| ModuleUpdateEligibilityService | SupportLicenseService | Constructor injection |
| SiteController | ModuleAssignmentService, SupportLicenseService, LicenseServerService | Constructor injection |
| ModuleController | ModuleAssignmentService, EntitlementLedger | Constructor injection |

### 11.2 customer-site Tight Coupling

| Module | Coupled To | Coupling Type |
|---|---|---|
| UpdateCheckService | HTTP client to platform API | External API dependency |
| LicenseActivationService | HTTP client to platform API | External API dependency |
| SupportApiClient | HTTP client to platform API | External API dependency |
| AdminMenu | License model, SiteHelper | Static method calls |
| ModuleController | License model, ModuleVersion, ModuleChangeLog | Direct model usage |

---

## 12. FRONTEND DRIFT AREAS

### 12.1 High Drift

| Area | saas-platform | customer-site | Drift |
|---|---|---|---|
| Admin layout | `layouts/admin.blade.php` | `admin/layout.blade.php` | Different structure |
| Sidebar | `partials/platform-admin-sidebar-theme.blade.php` | `partials/customer-admin-sidebar-theme.blade.php` | Different |
| Dashboard | `admin/dashboard.blade.php` | `admin/dashboard.blade.php` | Different content |
| Module management | `admin/modules/` (5 views) | `admin/modules/` (3 views) | Completely different |
| Form management | `admin/forms/` (7 views) | `admin/forms/` (5 views) | Different features |
| Menu management | `admin/menus/` (5 views) | `admin/menus/` (8 views) | Different features |

### 12.2 Low Drift (Could Be Unified)

| Area | Notes |
|---|---|
| Auth pages | Similar structure, could share layout |
| Captcha settings | Simple, could be shared |
| Activity log | Similar table structure |
| Settings page | Similar form structure |
| Persian components | Already near-identical |

---

## 13. RECOMMENDATIONS

### 13.1 Immediate Actions (Phase 1)

1. **Create shared package repository** — Extract Persian components (captcha, date-input, font faces) into a shared Composer package
2. **Unify TenantContext** — Create a common interface/contract for tenant context
3. **Extract Forms module** — Forms is the simplest shared module candidate with least drift
4. **Version the API** — Add `/v1/` prefix to all API routes

### 13.2 Short-Term (Phase 2)

5. **Extract Menus module** — Higher complexity but high value
6. **Extract Pages module** — Medium complexity
7. **Create shared helpers package** — Jalali, NumberHelper, IranianMobile rule
8. **Standardize AdminMenu** — Create common interface

### 13.3 Long-Term (Phase 3)

9. **Full module extraction** — Extract all shared modules to independent packages
10. **Unified test suite** — Cross-project testing
11. **Event-driven architecture** — Replace HTTP API calls with events
12. **Offline capability** — Graceful degradation when platform is unreachable

---

## 14. SUMMARY STATISTICS

| Metric | Value |
|---|---|
| Total Blade files (saas-platform) | ~150 |
| Total Blade files (customer-site) | ~170 |
| Identical/near-identical Blade files | ~13 |
| Same-name controllers | ~8 |
| Shared module candidates (high priority) | 6 |
| Shared module candidates (medium priority) | 4 |
| Anti-modular rules identified | 10 |
| Risky Waves | 3 |
| Critical architecture conflicts | 7 |
| Tightly coupled modules (saas) | 5 |
| Tightly coupled modules (customer) | 5 |
| Frontend high-drift areas | 6 |
| Frontend low-drift areas | 6 |

---

*End of Phase 0 Report — Ready for Phase 1 Planning*
