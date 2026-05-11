# Changelog

تمام ورودی‌ها به‌صورت معکوس زمانی (جدیدترین بالا). فقط هنگام تغییر واقعی روی ماژول‌ها/قابلیت‌ها به‌روز شود.

## [Unreleased]

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
