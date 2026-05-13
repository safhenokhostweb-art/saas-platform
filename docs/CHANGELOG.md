# Changelog

تمام ورودی‌ها به‌صورت معکوس زمانی (جدیدترین بالا). فقط هنگام تغییر واقعی روی ماژول‌ها/قابلیت‌ها به‌روز شود.

## [Unreleased]

### Phase 2 / Wave 7.0c — UX Uniformity Sweep (customer-site only) — ۱۴۰۵/۰۲/۲۳

- این wave صرفاً در مخزن **customer-site** اجرا شد؛ هیچ تغییر کد، migration، middleware، service، route، policy، یا view روی saas-platform وارد نشد. جزئیات کامل در `customer-site/docs/CHANGELOG.md` (Wave 7.0c).
- خلاصه برای ارجاع:
  - **Breadcrumb partial** جدید قابل استفاده مجدد در `resources/views/admin/partials/breadcrumb.blade.php` (opt-in)، استفاده در ۵ صفحه: `dashboard`، `forms/index`، `orders/index`، `products/index`، `posts/index`. صفحهٔ coupons عمداً خارج از scope.
  - **۴ راهنمای جدید ماژول‌ها** با الگوی `<details>` و تاریخ شمسی `۱۴۰۵/۰۲/۲۳` در عنوان: `admin/orders/guide`، `admin/products/guide`، `admin/posts/guide`، `admin/coupons/guide`. include در صدر هر index متناظر. راهنمای `forms` که در Wave 7.0b ساخته شد همچنان فعال.
  - **«به‌زودی» badge** برای ماژول‌های placeholder: banner در `admin/platform-modules/show.blade.php` و pill کوچک در tile grid `admin/modules/index.blade.php` برای ۲۶ slug placeholder موجود در `PlatformModuleController::MODULE_GUIDES` (منبع حقیقت controller است؛ آرایهٔ slugs در view یک passive mirror با کامنت صریح).
  - **۹ تست feature جدید** (`Wave70cUxUniformityTest` — ۴۱ assertion) برای پوشش breadcrumb، guide partial، Coming Soon banner، و قراردادهای صریح (مثل عدم وجود breadcrumb روی coupons).
- نتیجهٔ کل suite کاستومرسایت پس از این wave: **۱۸۳ pass، ۲ risky، ۰ failure** (همان ۲ risky output-buffer hygiene از Wave 6.2/7.0a). صفر مایگریشن. صفر تغییر controller/middleware/service/route. صفر تغییر entitlement.
- این ورودی صرفاً برای حفظ هم‌خوانی روایی بین دو CHANGELOG قرار داده شده.

### Phase 2 / Wave 7.0b — Forms Hardening (customer-site only) — ۱۴۰۵/۰۲/۲۳

- این wave صرفاً در مخزن **customer-site** اجرا شد؛ هیچ تغییر کد، migration، middleware، service، route، policy، یا view روی saas-platform وارد نشد. جزئیات کامل در `customer-site/docs/CHANGELOG.md` (Wave 7.0b).
- خلاصه برای ارجاع:
  - **Output sanitization** برای `intro_text` در public + admin-preview با allowlist گستردهٔ TinyMCE (`<p><br><strong><em><u><a><ul><ol><li><h2><h3><h4><blockquote><span><img><table><thead><tbody><tr><td><th>`)، افزایش `intro_text` validation max از ۵۰۰۰ به ۶۵۰۰۰.
  - **Rate-limit جدید `forms-submit`** با محدودیت ۱۰/دقیقه per `(user_id ?: ip) + slug`، middleware `throttle:forms-submit` روی مسیر `POST /forms/{slug}` ⇒ HTTP 429 پس از overage.
  - **راهنمای ادمین** جدید `resources/views/admin/forms/guide.blade.php` (فارسی، تاریخ شمسی ۱۴۰۵/۰۲/۲۳ در عنوان)، include شده در `admin.forms.index`.
  - **P0 production fix #۱:** Blade `@json` compile bug در `resources/views/forms/show.blade.php` که هر بازدید عمومی `/forms/{slug}` را به HTTP 500 می‌رساند (`ParseError`). ریشه: directive `@json` با `explode(',', ...)` ناقص، آرگومان‌های `strip_tags($pageDesc, 300)` را غلط می‌شکست. جایگزینی با `<?php echo json_encode([...], JSON_UNESCAPED_UNICODE) ?>` خام ⇒ payload JSON-LD bit-for-bit unchanged.
  - **Test-data hygiene fix** در ۴ تست جدید: ارسال empty string برای فیلدهای nullable optional در payload‌های ادمین برای شبیه‌سازی رفتار واقعی browser. این یک latent production bug در `Admin\FormController::store/storeField` را آشکار کرد (pattern `$data['key'] ?: null` بدون `?? null` در request data ناقص ⇒ «Undefined array key») که در UI بروز نمی‌کند چون browser همیشه فیلدها را با مقدار خالی می‌فرستد؛ fix controller در wave بعدی (پس از تأیید صریح) قابل افزودن است.
- نتیجهٔ کل suite کاستومرسایت پس از این wave: **۱۷۴ pass، ۲ risky، ۰ failure** (همان ۲ risky output-buffer hygiene از Wave 6.2/7.0a). صفر مایگریشن. صفر تغییر entitlement.
- این ورودی صرفاً برای حفظ هم‌خوانی روایی بین دو CHANGELOG قرار داده شده.

### Phase 2 / Wave 7.0a — Test Bootstrap Stabilization (customer-site only) — ۱۴۰۵/۰۲/۲۳

- این wave صرفاً در مخزن **customer-site** اجرا شد؛ هیچ تغییر کد، migration، middleware، service، route یا policy روی saas-platform وارد نشد. جزئیات کامل در `customer-site/docs/CHANGELOG.md` (Wave 7.0a).
- خلاصه برای ارجاع: اتصال trait موجود `Tests\\Concerns\\SeedsValidLicenseWithShop` (که در Wave 6.2 ساخته شد) به ۴ فایل تست قدیمی (`BlogProfessionalUpliftTest`، `MenuModuleFeatureTest`، `PhaseOneTwoThreeTest`، `StageFourValidationTest`) ⇒ پاکسازی ۱۰ failure باقی‌ماندهٔ baseline به ۰ failure و ۲ risky (هر دو hygiene output-buffer در viewهای frontend که `@push('json-ld')` می‌زنند — assertion سبز). علاوه بر آن، test-data hygiene فقط در `PhaseOneTwoThreeTest`: افزودن `editorial_status => Post::EDITORIAL_PUBLISHED` به ۳ `Post::create` که با `scopePublished` همگام نبودند. صفر تغییر در `app/**`، `routes/**`، `config/**`، `database/migrations/**`، `resources/views/**` کاستومرسایت؛ صفر migration؛ صفر تأثیر بر entitlement مشتری؛ صفر تأثیر روی saas-platform.
- نتیجهٔ کل suite کاستومرسایت پس از این wave: **۱۶۳ تست ⇒ ۱۶۱ pass، ۲ risky، ۰ failure**.
- این ورودی صرفاً برای حفظ هم‌خوانی روایی بین دو CHANGELOG قرار داده شده تا اگر کسی فقط CHANGELOG پلتفرم را می‌خواند بداند روی customer-site چه گذشته.

### Phase 6 / Wave 6.2 — Shop Test-Suite Stabilization (customer-site only) — ۱۴۰۵/۰۲/۲۳

- این wave صرفاً در مخزن **customer-site** اجرا شد؛ هیچ تغییر کد، migration، middleware، service، route یا policy روی saas-platform وارد نشد. جزئیات کامل در `customer-site/docs/CHANGELOG.md` (Wave 6.2).
- خلاصه برای ارجاع: trait جدید `Tests\\Concerns\\SeedsValidLicenseWithShop` (host-aware) برای پاکسازی bootstrap قدیمی `ShopModuleFeatureTest` ⇒ ۶ failure baseline متعلق به Shop سبز شد. مایگریشن additive `2026_05_13_110000_add_timestamps_to_shop_pivots` ⇒ افزودن `created_at/updated_at` به pivotهای `product_product_tag` و `product_product_attribute_value` (fix یک latent bug پروداکشن که در eager-load `Product::tags()->withTimestamps()` کرش می‌کرد). `docs/KNOWN-TEST-FAILURES.md` با baseline جدید **۱۰** (همگی non-Shop) به‌روز شد. صفر تغییر در منطق production لایسنس/shop، صفر breaking change، صفر تأثیر بر entitlement مشتری.
- این ورودی صرفاً برای حفظ هم‌خوانی روایی بین دو CHANGELOG قرار داده شده تا اگر کسی فقط CHANGELOG پلتفرم را می‌خواند بداند روی customer-site چه گذشته.

### Phase 6 / Wave 6.1 — Shop Data-Integrity Hardening (customer-site only) — ۱۴۰۵/۰۲/۲۳

- این wave صرفاً در مخزن **customer-site** اجرا شد؛ هیچ تغییر کد یا migration روی saas-platform وارد نشد. جزئیات کامل در `customer-site/docs/CHANGELOG.md` (Wave 6.1).
- خلاصه برای ارجاع: nullable شدن `coupons.discount_percent`، advisory lock + pad ۶ رقمی برای `Order::generateNumber`، قفل پایدار parent/bundle-child/variant در checkout، حذف شاخهٔ email-match در `Account\\OrderController` (IDOR fix)، جدول جدید `order_status_history` و سرویس `App\\Services\\OrderStatusService` به‌عنوان تنها مسیر تغییر وضعیت سفارش (FSM + history + audit + restock اتمیک در گذار به cancelled). ۲۲ تست رگرسیون جدید سبز؛ baseline failureها بدون تغییر.
- این ورودی صرفاً برای حفظ هم‌خوانی روایی بین دو CHANGELOG قرار داده شده تا اگر کسی فقط CHANGELOG پلتفرم را می‌خواند بداند روی customer-site چه گذشته.

### Phase 5 / Wave 5.2 — ۱۴۰۵/۰۲/۲۲

- **P0-1 (Admin\\ModuleController — صفحهٔ تأیید per-site قبل از هرگونه revoke):** هر مسیر مخرب در `Admin\\ModuleController` که می‌توانست به‌صورت ضمنی دسترسی سایت‌های مشتری را قطع کند، اکنون به یک **flow دو-مرحله‌ای با granularity per-site** تبدیل شده است (مطابق global-rules §۴ Immutable Customer Entitlement Protection و §۵ Manual Revocation Only). **هیچ revoke خودکار اتفاق نمی‌افتد** — برای هر سایت ادمین به‌صراحت تصمیم می‌گیرد.
  - **مسیرهای پوشش‌شده:**
    - `update()` با تغییر نوع به `internal` (که قبلاً non-internal بود) **و** وجود حداقل یک ردیف `site_modules.is_active=true` روی این ماژول.
    - `bulkUpdate()` با action `set_type_internal` و حداقل یک جفت (سایت × ماژول) فعال.
  - **مرحلهٔ ۱ — صفحهٔ تأیید (هیچ mutation):** اولین POST به جای اعمال تغییر، یک view interstitial رندر می‌کند:
    - `resources/views/admin/modules/confirm-destructive.blade.php` (تک ماژول)
    - `resources/views/admin/modules/confirm-bulk-destructive.blade.php` (گروهی)

    این view فهرست همهٔ سایت‌های (یا جفت‌های سایت × ماژول) تحت‌تأثیر را با یک چک‌باکس «نگه‌دار فعال» برای هر کدام نمایش می‌دهد. تمام فیلدهای فرم اولیه (`title`, `description`, ... ) به‌عنوان `hidden` بازفرستاده می‌شوند تا در submit نهایی همه با هم اعمال شوند.
  - **Pre-check «نگه‌دار فعال»:** سایت‌هایی که برای این ماژول حداقل یک ردیف در `site_module_purchases` (هر خرید موفق ⇒ commercial attachment) **یا** یک ردیف در `module_licenses` با `status=active` و `revoked_at IS NULL` دارند، در view به‌طور پیش‌فرض pre-checked هستند تا entitlement خریداری‌شده/لایسنسی به‌اشتباه از دست نرود. ادمین می‌تواند هر کدام را override کند.
  - **مرحلهٔ ۲ — apply (با `__confirm_step=apply`):** دومین POST عملیات را اعمال می‌کند و فقط در این مرحله ولیداسیون سرور اجباری می‌شود:
    - `revoke_reason` (string, min:5, max:1000)
    - `confirmed_destructive` (must be accepted)
    - `keep_active_site_ids[]` (single) یا `keep_active_pair_keys[]` (bulk) — لیست سایت‌ها/جفت‌هایی که ادمین آن‌ها را tick زده تا فعال بمانند. هر شناسهٔ بیرونی نسبت به لیست affected نادیده گرفته می‌شود (defense-in-depth).
  - **منطق revoke (فقط سایت‌های UNCHECKED):** برای هر سایت در `affected − keep`:
    - `site_modules.is_active` به `false` فلیپ می‌شود (ردیف حذف نمی‌شود).
    - `EntitlementLedger::revokeBySiteModule()` با `revoked_by=Auth::id()` و reason ترکیبی (پیشوند سیستم + reason ادمین) صدا زده می‌شود ⇒ entitlement active به `status=revoked` با `revoked_at` می‌رود. سایت‌های kept هیچ تغییری نمی‌بینند — entitlement آن‌ها همچنان `active`. هیچ ردیفی از هیچ جدولی پاک نمی‌شود. فراخوانی ledger درون try/catch + `Schema::hasTable` guard ⇒ امن حتی پیش از مایگریشن.
  - **audit log:** ردیف `module.updated` (single) و `module.bulk_updated` (aggregate) در `activity_logs` اکنون شامل این فیلدهاست:
    - single: `properties.side_effects.revoke_reason`, `affected_site_ids`, `revoked_site_ids`, `kept_site_ids`, `site_modules_disabled_count`.
    - bulk: `properties.side_effects.revoke_reason`, `affected_pairs_count`, `site_modules_disabled_count`, `site_modules_kept_active_count`, `modules_switched_to_internal`.
  - **UI:** کادرهای inline «منطقهٔ خطرناک» در فرم edit/index حذف شدند (به interstitial page منتقل شده‌اند). به‌جای آن یک کادر amber هشدار سبک نشان داده می‌شود: «این تغییر می‌تواند N سایت فعال را تحت‌تأثیر قرار دهد؛ پس از ذخیره به صفحهٔ تأیید per-site منتقل می‌شوید — هیچ revoke خودکار نخواهد بود.» دکمهٔ submit در فرم اصلی فقط یک confirm سبک JS می‌گیرد و هدایت به صفحهٔ تأیید را اعلام می‌کند.
  - **رفتارهای محافظت‌شده (backward compatibility):**
    - تغییر نوع از paid به license یا برعکس بدون نمایش صفحهٔ تأیید (non-destructive).
    - تغییر نوع به internal روی ماژولی که هیچ سایت فعالی ندارد، تک‌مرحله‌ای انجام می‌شود (no-op برای entitlement).
    - actionهای bulk غیرمخرب (`marketplace_on/off`, `free_assignable_*`, `license_required_*`, `set_type_license`, `set_type_paid`) بدون تغییر و بدون reason.
    - بازگشت دادن نوع به paid/license باعث **خودکار grant** نمی‌شود؛ ردیف‌های revoked در ledger به‌عنوان تاریخ باقی می‌مانند و هر grant جدید باید از طریق purchase/license/admin grant صریح اتفاق بیفتد.
    - شناسه‌های `keep_active_site_ids`/`keep_active_pair_keys` که با affected list match نکنند، نادیده گرفته می‌شوند ⇒ ادمین نمی‌تواند با inject هر سایت دلخواه کاری کند.
  - **خارج از scope این wave (planned for later):** `AdminModuleLicenseController::revoke`، `PlatformLicenseController::revoke`، `AccessMenuController::update (section=modules)` — این‌ها نقاط revoke مستقل هستند و در یک wave مجزا (احتمالاً 5.5) با همان قرارداد پوشش داده می‌شوند.
  - **تست‌ها:** `tests/Feature/Wave52DestructiveConfirmationTest.php` — **۱۵ تست، ۱۱۰ assertion، همه pass.** سناریوها:
    - مرحلهٔ ۱: اولین POST مخرب confirm view را رندر می‌کند بدون هیچ mutation (ماژول/site_modules/ledger/audit).
    - Pre-check بر اساس `site_module_purchases` کار می‌کند.
    - Pre-check بر اساس `module_licenses` فعال کار می‌کند.
    - مرحلهٔ ۲: بدون reason ⇒ session errors؛ reason کوتاه ⇒ session error؛ بدون چک‌باکس ⇒ session error.
    - revoke فقط سایت‌های unchecked، سایت‌های kept کاملاً دست‌نخورده.
    - تمام سایت‌ها kept ⇒ صفر revoke، entitlement‌ها همگی active؛ ولی module type عوض می‌شود.
    - شناسه‌های بیرونی keep ⇒ نادیده گرفته می‌شوند.
    - تغییر به internal بدون سایت فعال ⇒ تک‌مرحله‌ای.
    - paid → license ⇒ تک‌مرحله‌ای، سایت‌ها دست‌نخورده.
    - Bulk: اولین POST confirm view؛ apply با per-pair keep؛ بدون reason ⇒ errors؛ غیرمخرب tarball؛ already-internal بدون confirm.
  - **اثر روی Wave 5.4 tests:** `Wave3AuditAndCastsTest::test_module_update_records_side_effect_when_switching_to_internal` نیاز به اضافه‌شدن `__confirm_step=apply` به payload داشت تا flow apply مرحلهٔ ۲ را شبیه‌سازی کند. هیچ تست دیگری تحت‌تأثیر قرار نگرفت. کل suite Wave 3 + 5.1 + 5.2 پس از این تغییر: **۳۴ تست، ۲۰۳ assertion، همه pass**.

### Phase 5 / Wave 5.4 — ۱۴۰۵/۰۲/۲۲

- **P3-1/P3-2 (regression test suite — saas-platform):** افزودن مجموعهٔ کامل feature testهای regression برای رفتارهای حساس waveهای ۱ تا ۵.۱. هیچ تغییر رفتاری در کد production انجام نشد؛ فقط تست. مجموعاً **۵۲ تست جدید + ۲۳ تست baseline = ۷۵ تست با ۲۶۴ assertion، همه pass**.
  - `tests/Feature/Wave5EntitlementLedgerTest.php` (P1-1، ۱۱ تست): schema `module_entitlements`، idempotency `EntitlementLedger::grant()`، metadata merge، `revoke()` با reason صریح و no-op در double-revoke، re-grant بعد از revoke، snapshot شدن `module_slug` در rename ماژول، command `entitlements:backfill` (idempotent + `--dry-run`)، invariant: هر `site_modules.is_active` ⇒ یک active entitlement، روابط Module/Site/Customer. `commit 6028aea`
  - `tests/Feature/Wave4SchemaHardeningTest.php` (P0-2 + P1-6، ۱۱ تست): وجود ستون `modules.retired_at` و helperهای `retire()/unretire()`، scope exclusion برای ماژول‌های retired، **FK restrictOnDelete** برای `site_modules`/`module_licenses`/`site_module_purchases` (با helper `assertModuleDeleteRejected` که `QueryException` را در try/catch می‌گیرد تا transaction RefreshDatabase نشکند)، حذف ماژول unreferenced با موفقیت، `sites.support_starts_at` وجود، `fulfillSupportPlan` در پرداخت اول `support_starts_at` را می‌نویسد و در تمدید فعال آن را دست‌نخورده نگه می‌دارد (مقایسهٔ `toDateString()`). `commit 2537d15`
  - `tests/Feature/Wave2LicenseSecurityTest.php` (P0-3 + P0-5، ۱۳ تست): allow-list خالی=any (backward-compat)، match case-insensitive و trim پروتکل/slash، چند دامنه با comma، rejection دامنهٔ ناشناس، `validate()` با `domain_mismatch`؛ HMAC-SHA256 hashing روی issue (نه SHA256 لگاسی)، `findByKey()` HMAC، fallback به SHA256 لگاسی + **lazy upgrade** خودکار به HMAC، `validate()` با کلید لگاسی، خطاهای `invalid_key`/`revoked`/`expired`. `commit 8c45491`
  - `tests/Feature/Wave3ApiAndAliasTest.php` (P0-6 + P0-7، ۹ تست): `GET /api/updates/module-history` → ۴۰۳ `invalid_key`/`domain_mismatch`/`support_expired`، tenant-scoping (فقط releases از `site_modules` این سایت برمی‌گردد؛ leak از سایت دیگر ممنوع)، clamp `limit` (1..200؛ >200 ⇒ 422)، history خالی برای سایت بدون ماژول؛ slug `whatsapp` همچنان موجود (backward-compat برای entitlement مشتریان)، title rebrand شده به «پاسخگویی شبکه‌ها»، **هیچ row با slug=`messaging` ایجاد نشد** (تا entitlement‌ها split نشود). `commit b1196fe`
  - `tests/Feature/Wave3AuditAndCastsTest.php` (P1-3 + P1-4، ۸ تست): `PUT admin.modules.update` ⇒ activity_log row با action=`module.updated` + diff فقط فیلدهای تغییر کرده؛ سوئیچ paid→internal باعث ست شدن `side_effects.site_modules_disabled_count` + `reason=type_switched_to_internal` و واقعاً `site_modules` غیرفعال می‌شود؛ no-op submit بدون log؛ `POST admin.modules.bulk-update` ⇒ یک aggregate row با `bulk_action`/`module_ids`/`count`؛ Persian labels؛ User password BCrypt cast (هیچ‌گاه plaintext)، double-hash نمی‌شود، لگاسی hash همچنان از طریق `Hash::check` کار می‌کند. `commit 7fe6710`

- **P3-1/P3-2 (regression test suite — customer-site):** افزودن ۲۳ feature test جدید در شاخهٔ `phase5-wave5` این مخزن. **همه pass.** خلاصه کل suite: ۱۱۹ تست (۹۶ baseline + ۲۳ جدید Wave 5.4) با ۲۷۸ assertion؛ ۱۶ failure در تست‌های pre-existing baseline (بدون ارتباط با waveهای فاز ۵؛ مرتبط با shop/blog/menu/stage4 routing داخلی).
  - `tests/Feature/Wave2EncryptedLicenseKeyTest.php` (P0-4، ۶ تست): مقدار encrypted در دیتابیس ذخیره می‌شود (Crypt::decryptString روی row خام تأیید شد)، Eloquent decrypt شفاف، row legacy plaintext خوانده می‌شود (DecryptException fallback)، در save بعدی به‌طور خودکار lazy-upgrade به encrypted (بدون مایگریشن backfill)، null/empty pass-through، `maskedKey()` روی plaintext decrypted کار می‌کند. **(یک fix testability همراه: نام FK در migration `2026_05_08_130200_shop_attributes_tags_related_bundles` از ۶۷ کاراکتر به `ppav_attr_value_fk` کوتاه شد. سمنتیک FK (cascadeOnDelete + جدول/ستون مرجع) دست‌نخورده است؛ فقط نام index. این fix به‌خاطر اجرای ممکن `migrate:fresh` در MySQL ضروری بود — قبلاً همهٔ تست‌های RefreshDatabase fail می‌شدند.)** `commit 24ee103`
  - `tests/Feature/Wave3AdminActivityLogTest.php` (P1-2، ۹ تست): وجود جدول `admin_activity_log` و ستون‌های کامل، `AdminActivityLogger::log()` با action/description/properties/Auth::id() خودکار، default description از `actionLabel($action)` در حالت omit، schema-guard: نبود جدول ⇒ null بدون exception، Persian labels برای ۷ اکشن شناخته‌شده + fallback به action key برای ناشناخته، properties array-cast، رابطهٔ `adminUser()`. `commit a3f8d60`
  - `tests/Feature/Wave4MarketplaceAndBannerTest.php` (P2-5 + P2-6، ۸ تست): سه badge متمایز برای `installed`/`owned_disabled`/`available` در view؛ backward-compat با APIای که فقط `installed`/`owned` می‌دهد؛ ماژول installed بدون buy button. transient cache key پایدار `license.transient_error.{id}` و license-scoped؛ 5xx ⇒ cache+`sync_skipped=true`+ license معتبر می‌ماند؛ ConnectionException ⇒ cache `error=connection_error`+ license معتبر؛ validation موفق ⇒ Cache::forget و platform_valid=true؛ rejection قطعی (`revoked`/...) ⇒ بدون cache و `platform_valid=false`. `commit 10d518e`

- **اثر جانبی positive روی baseline:** قبل از Wave 5.4 تست‌های customer-site به دلیل migration FK overflow (نام پیش‌فرض ۶۷ کاراکتر > حد ۶۴ کاراکتر MySQL) ۴۵ خطا داشتند که هیچ‌کدام به assertion نمی‌رسید. fix نام FK در `2026_05_08_130200_shop_attributes_tags_related_bundles` این مسیر را باز کرد و **۲۹ تست baseline که قبلاً به‌خاطر FK error می‌خوردند، حالا اجرا می‌شوند**؛ failure از ۴۵ به ۱۶ کاهش یافت. ۱۶ failure باقی‌مانده مربوط به منطق shop/blog/menu routing داخلی است و در waveهای فاز ۵ هیچ سهمی ندارد — این‌ها در waveهای آینده (یا یک wave رفع باگ مجزا) قابل رسیدگی هستند، اما به دلیل قاعدهٔ «only fill gaps، خوداضافه‌نکن» در Wave 5.4 فقط noted شدند.

### Phase 5 / Wave 5.1 — ۱۴۰۵/۰۲/۲۲

- **P1-1 (ledger schema):** افزودن جدول جدید `module_entitlements` به‌عنوان دفترکل (ledger) دائمی اعطا/ابطال ماژول‌ها. مایگریشن `2026_05_12_010000_create_module_entitlements_table.php` با ستون‌های `site_id`, `customer_id`, `module_id`, `module_slug` (snapshot برای مقاومت در برابر rename), `source` (purchase|license|admin_grant|free_assign|seed|import|sync), `status` (active|revoked|suspended), `granted_at`, `granted_by`, `revoked_at`, `revoked_by`, `revoke_reason`, لینک به `invoice_id`/`invoice_item_id`/`module_purchase_id`/`module_license_id`، `metadata` (JSON) و timestamps. **FK های شکن (`restrictOnDelete`) به `sites`/`customers`/`modules`** و **FK های نرم (`nullOnDelete`) به `users`/`invoices`/`invoice_items`/`site_module_purchases`/`module_licenses`** → هیچ حذف cascade از این جدول به جدولی دیگر و بالعکس امکان ندارد. مدل `App\Models\ModuleEntitlement` با ثابت‌های `SOURCE_*` / `STATUS_*`، scopeهای `active`/`revoked`/`forSite`/`forCustomer`/`forModule`/`forSlug` و relations کامل. روابط `entitlements()` و `activeEntitlements()` روی `Site` / `Customer` / `Module` افزوده شد. `commit 02ec2cf`
- **P1-1 (service):** سرویس `App\Services\EntitlementLedger` با متدهای `grant(Site, Module, source, context)` (idempotent — اگر active قبلی موجود است، metadata را merge می‌کند و رکورد جدید نمی‌سازد)، `recordSync(...)` (برای backfill با حفظ تاریخ تاریخی)، `revoke(entitlementId, revokedBy, reason)` (انتقال status به `revoked` با timestamp و reason صریح؛ نهایتاً قابل revoke فقط یک بار)، `revokeBySiteModule(...)` (helper)، `currentEntitlement`/`activeForSite`/`historyForSite`. تمام write ها در DB transaction + `lockForUpdate` (جلوگیری از race به ایجاد چندین active). `Schema::hasTable` guard در همه متدها → امن برای فراخوانی قبل از migrate. **هیچ متد سرویس به‌طور خودکار revoke نمی‌کند**؛ هر revoke باید صریح و audited باشد (global-rules §۴، §۵). `commit 193b274`
- **P1-1 (CLI backfill):** Artisan command `php artisan entitlements:backfill [--dry-run] [--site=ID]` در `App\Console\Commands\BackfillModuleEntitlementsCommand`. سه گذر idempotent: (۱) `site_modules` با `is_active=true` → source=`seed` با `granted_at=activated_at?? created_at`، (۲) `site_module_purchases` → source=`purchase` با لینک کامل invoice/purchase و amount در metadata، (۳) `module_licenses` با `status=active` → source=`license` با `expires_at`/`license_type` در metadata. به‌خاطر `grant()` idempotent، هر گذر بعدی فقط metadata را روی رکورد active موجود merge می‌کند (پاس license معتبرترین منبع است). `--dry-run` فقط چاپ می‌کند، چیزی نمی‌نویسد. `--site=ID` به سایت خاص محدود می‌کند. این command فقط **اعطا** می‌نویسد؛ هیچ revoke‌ای انجام نمی‌دهد. `commit c9211ff`
- **P1-1 (write-path integration):** فراخوانی `EntitlementLedger::grant()` پس از `recordPurchaseFromInvoice()` در `OrderFulfillmentService::fulfillModuleAddon` با source=`purchase` و لینک کامل به invoice/invoice_item/module_purchase. درون try/catch + `Schema::hasTable` در سرویس → ledger امن: حتی اگر جدول وجود نداشته باشد یا transient error باشد، payment flow اصلاً مختل نمی‌شود. **نقاط revoke موجود (Admin\ModuleController::update/bulkUpdate, AccessMenuController) دست‌نخورده** باقی ماندند — این‌ها در Wave 5.2 (P0-1 confirmation modal) integrate خواهند شد. `commit 80796f3`
- **Phase A scope:** سایت_modules.is_active + لایسنس معتبر هنوز منبع حقیقت runtime برای visibility ماژول است (`ModuleLicenseService`، `SiteHelper`). ledger در این wave فقط audit/history را اضافه می‌کند بدون تغییر رفتاری در run-time gating → ریسک صفر برای entitlement مشتریان موجود (global-rules §۴ Immutable Customer Entitlement Protection). اجرای backfill اختیاری است؛ بدون backfill، ledger فقط برای پرداخت‌های جدید پر می‌شود. اجرای آن پیشنهاد می‌شود برای سایت‌های قدیمی: ابتدا `--dry-run` و سپس `--site=ID` به‌صورت staged.

### Phase 5 / Wave 4 — ۱۴۰۵/۰۲/۲۱

- **P0-2:** schema hardening + module retirement. مایگریشن جدید `2026_05_12_000000_protect_module_fks_and_add_retired_at.php` با دو تغییر additive: (الف) FK های `module_id` در `site_modules` / `site_module_purchases` / `module_licenses` از `cascadeOnDelete` به `restrictOnDelete` تبدیل می‌شوند → حذف فیزیکی ماژول دارای استفاده در DB-level مسدود می‌شود (مسیر data-loss بسته شد)؛ (ب) ستون `retired_at` (nullable timestamp + index) به جدول `modules` افزوده می‌شود. مدل `App\Models\Module`: ثابت جدید `MODE_RETIRED`، `retired_at` در `$fillable` و `$casts=datetime`، scopeهای جدید `scopeNotRetired()`/`scopeRetired()`، scope های `scopeAssignableToSite` / `scopeFreeAssignable` / `scopePaidMarketplace` با `whereNull('retired_at')` تقویت شدند (با `Schema::hasColumn` guard)، متد `getDistributionMode()` ابتدا `MODE_RETIRED` را برمی‌گرداند، متدهای helper `isRetired()` / `retire(?Carbon)` / `unretire()` جدید. **اصل ایمنی:** هیچ ردیفی در هیچ جدول پاک نمی‌شود؛ مشتری که قبلاً ماژول بازنشسته را داشت، `site_modules`، `module_licenses` و `site_module_purchases` آن دست‌نخورده باقی می‌مانند (per global-rules §۴، §۱۵). migration کاملاً reversible. `commit d21a4cf`
- **P1-6:** ستون `sites.support_starts_at` (از migration `2026_02_27_100000`) در fulfillment ست می‌شد ولی هرگز توسط `OrderFulfillmentService::fulfillSupportPlan` آپدیت نمی‌شد → همیشه NULL باقی می‌ماند. اکنون با `Schema::hasColumn` guard: اگر site پشتیبانی فعال (`support_expires_at` آینده) ندارد یا `support_starts_at` خالی است، در پرداخت موفق `support_starts_at = now()` ثبت می‌شود. در تمدید پلان فعال (continuous subscription) دست‌نخورده باقی می‌ماند. `support_expires_at` logic قبلی و `site_support_subscriptions.starts_at` (همان `now()`) برای backward-compat حفظ شدند. `commit e9e0185`
- **P2-5:** افزایش پاسخ `GET /api/tenant-marketplace/modules` با دو فیلد additive: `owned: bool` و `status: 'installed'|'owned_disabled'|'available'`. فیلد قدیمی `installed` بدون تغییر معنایی حفظ شد (backward-compat). منبع `owned`: `site_module_purchases.module_id` (هر خرید موفق) + `module_licenses.module_id` با `status=active` و `revoked_at=null` برای site جاری. `Schema::hasTable` guard برای هر دو جدول. این تغییر به سایت مشتری اجازه می‌دهد ماژول‌های قبلاً خریداری‌شده ولی موقتاً غیرفعال را به‌جای دکمهٔ خرید با وضعیت «خریداری‌شده — غیرفعال» نمایش دهد (جلوگیری از خرید مجدد همان ماژول). `commit 480e245`

### Phase 5 — Wave-tip tags — ۱۴۰۵/۰۲/۲۱

- افزوده شدن سه **lightweight tag** برای نام‌گذاری دائمی tip شاخه‌های phase5-waveN قبل از حذف احتمالی feature branchها (محلی، بدون push به GitHub):
  - `phase5-wave1-tip` → `03092ae`
  - `phase5-wave2-tip` → `a8d32cd`
  - `phase5-wave3-tip` → `95e4c61`
- مستندسازی کامل تگ‌ها، تفاوت با `deploy-backup-*` و `branch`، و الگوی استفادهٔ روزمره در `DEPLOY-fa.md` §۹ (فارسی) و `DEPLOY.md` §9 (انگلیسی). شامل سناریوهای checkout/diff/log/show، روال wave آینده، بازیابی از merge commit، و push اختیاری به GitHub.

### Phase 5 / Wave 3 — ۱۴۰۵/۰۲/۲۱

- **P0-6:** افزودن endpoint `GET /api/updates/module-history` به `App\Http\Controllers\Api\UpdateController` (متد `moduleHistory`). این endpoint قبلاً در سمت customer-site (`UpdateCheckService::syncModuleChangeLogs`) درخواست می‌شد ولی در پلتفرم تعریف نشده بود → silent failure. حالا با reuse از `LicenseServerService::validate` + `findByKey` (مسیر HMAC + lazy rehash Wave 2 / P0-5) و gating با `ModuleUpdateEligibilityService::isEligibleForModuleUpdates`، فقط ماژول‌هایی که سایت به‌صورت فعال در `site_modules.is_active=true` دارد (یا fallback از `license->modules`) برگردانده می‌شود. پاسخ سازگار با کلاینت موجود: `{ allowed:true, history:[{ module_slug, version, release_type, title, changelog, released_at }] }`. پارامتر `limit` بازه ۱..۲۰۰ (پیش‌فرض ۱۰۰). با `Schema::hasTable('module_releases')` guard ایمن. additive و backward-compatible. `commit 8a78a35`
- **P1-3:** افزودن audit logging به `App\Http\Controllers\Admin\ModuleController`. `update()` تغییرات کاتالوگ ماژول را در `activity_logs` با action `module.updated` و properties شامل `changes` (diff old/new روی فیلدهای watch: type, title, short_description, price_irr, price_promo_irr, currency, license_type_default, marketplace_visible, free_assignable, license_required, category) و در صورت تغییر type به `INTERNAL`، `side_effects.site_modules_disabled_count` ثبت می‌کند (شمارش قبل از soft-deactivation). `bulkUpdate()` یک ردیف aggregate با action `module.bulk_updated` و properties `{ bulk_action, module_ids, count, side_effects? }` می‌نویسد. action labels جدید در `ActivityLog::actionLabel()` و فیلتر `ActivityLogController::index()` ثبت شد. هیچ تغییری در منطق update یا transaction نیست — فقط ثبت رویداد. `commit 5b004fc`

### Phase 5 / Wave 2 — ۱۴۰۵/۰۲/۲۱

- **P0-5:** هش کلید لایسنس از `hash('sha256', $key)` ساده به `hash_hmac('sha256', $key, config('app.key'))` ارتقا یافت در `LicenseServerService`. متد جدید `findByKey()` به‌عنوان منبع واحد جستجوی لایسنس از کلید کامل اضافه شد که dual lookup (HMAC اول، سپس SHA256 legacy) و lazy rehash را encapsulate می‌کند. `UpdateController` در سه نقطه (check، checkModules، reportVersions) از hash مستقیم به `findByKey()` refactor شد. **هیچ مایگریشن دیتابیس لازم نیست** (طول HMAC-SHA256 = SHA256 = 64 hex). لایسنس‌های موجود در اولین استفاده به‌طور خاموش به HMAC ارتقا می‌یابند. `commit 0dfbfac`
- **P1-4:** افزودن `'password' => 'hashed'` به casts در `App\Models\User`. مدل user در customer-site از قبل داشت ولی در saas-platform نبود. Laravel با `Hash::needsRehash` اطمینان می‌دهد پسوردهای از قبل hashed دوباره hash نشوند → کاملاً backward-compatible. `commit 47dacd9`

### Phase 5 / Wave 1 — ۱۴۰۵/۰۲/۲۱

- **P4-6:** افزودن `docs/PROJECT-GUIDE.md` به این مخزن (هم‌محتوا با customer-site؛ هش SHA256 یکسان). `commit c8624c4`
- **P4-5:** حذف middleware مردهٔ `app/Http/Middleware/IsAdmin.php` (در `Kernel.php` ثبت نشده بود و هیچ ارجاعی نداشت؛ alias `admin` فقط به `AdminMiddleware` متصل است). `commit f22e798`
- **P4-4b:** حذف فایل‌های garbage از root مخزن (همگی artifactهای shell-pipe ناقص یا artifact آزمایشی): `$user`, `FETCH_HEAD`, `environmentFilePath())`, `error('Unable`, `getControllerClass()))`, `getFileName()`, `isFrameworkController($route))`, `keyReplacementPattern()`, `origin)`, `prepareBindings($bindings)`, `profile`, `structure.txt` (854 KB tree dump), `test-connection.txt`. `commit d06d059`
- **P4-4a (CRITICAL):** حذف سه فایل حاوی credential افشا شده از HEAD: `1234567890` (OpenSSH ed25519 private key)، `1234567890.pub` (public key همراه)، `test_gapgpt.py` (GapGPT/OpenAI API key hard-coded). `.gitignore` با الگوهای `id_rsa*`, `id_ed25519*`, `*.pem`, `/*.key`, `test_*_local.py` تقویت شد. **هشدار:** این delete فقط از HEAD حذف می‌کند؛ تاریخ git همچنان رازها را نگه می‌دارد. **اقدامات مورد نیاز شما:** (۱) کلید SSH را در GitHub → SSH and GPG keys حذف و کلید جدید جایگزین کنید. (۲) GapGPT API key را در پنل ارائه‌دهنده revoke کنید. سپس می‌توان history rewrite با `git filter-repo` را با تأیید صریح انجام داد (Wave 1 / مرحلهٔ ۳ از گزینهٔ A — هنوز اجرا نشده). `commit 17e0cf4`

### قبلی

- `global-rules.mdc`: بخش‌های ۱۴–۱۷ (مدیریت خودکار قانون ماژول، حفظ entitlement، ایمنی ارتقا، رفتار معماری SaaS)؛ بند Laravel به بخش ۱۸ منتقل شد.
- قانون سراسری Cursor (`.cursor/rules/global-rules.mdc`): توسعهٔ امن، لایسنس، انتشار قابلیت بین پنل‌ها، حفظ entitlement مشتری، ابطال فقط دستی، مدل دادهٔ پیشنهادی entitlement، ایمنی migration و تست.
- همگام‌سازی قوانین Cursor: پشتیبانی از مخزن مشتری روی مسیر/هاست دیگر از طریق `CURSOR_RULES_*` و `cursor-rules-sync.local.json`؛ نمونه `cursor-rules-sync.local.example.json`؛ به‌روزرسانی `docs/CURSOR-RULES-SYNC.md`.
- همگام‌سازی قوانین Cursor: اسکریپت `scripts/sync-cursor-rules.ps1`، دستورات Composer (`sync-cursor-rules` / `sync-cursor-rules:reverse`)، قلاب Git `.githooks/post-commit` و راهنما `docs/CURSOR-RULES-SYNC.md`.
- همسان‌سازی محتوای `.cursor/rules/*.mdc` بین **saas-platform** و **customer-site** (شامل `persian-date-only`, `project-guide-update`, `site-modules-from-platform`, `saas-platform-main-admin`, `saas-module-guide-update`, `content-modules-jalali-editor`, `global-rules`).
- قانون Cursor «Billing and Payment»: `globs` به مسیرهای واقعی Laravel (`app/Services/Payment/**`, مدل‌های فاکتور/درگاه) هم‌راستا شد؛ حفظ درگاه‌های ایرانی و IRR صریح شد.
- راه‌اندازی مستندات ماژول‌ها (`docs/modules/`) و پرامپت استاندارد (`docs/PROMPT_FOR_CURSOR.md`).
