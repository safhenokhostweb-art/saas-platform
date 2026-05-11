# Changelog

تمام ورودی‌ها به‌صورت معکوس زمانی (جدیدترین بالا). فقط هنگام تغییر واقعی روی ماژول‌ها/قابلیت‌ها به‌روز شود.

## [Unreleased]

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
