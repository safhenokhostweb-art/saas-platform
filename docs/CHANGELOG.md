# Changelog

تمام ورودی‌ها به‌صورت معکوس زمانی (جدیدترین بالا). فقط هنگام تغییر واقعی روی ماژول‌ها/قابلیت‌ها به‌روز شود.

## [Unreleased]

- `global-rules.mdc`: بخش‌های ۱۴–۱۷ (مدیریت خودکار قانون ماژول، حفظ entitlement، ایمنی ارتقا، رفتار معماری SaaS)؛ بند Laravel به بخش ۱۸ منتقل شد.
- قانون سراسری Cursor (`.cursor/rules/global-rules.mdc`): توسعهٔ امن، لایسنس، انتشار قابلیت بین پنل‌ها، حفظ entitlement مشتری، ابطال فقط دستی، مدل دادهٔ پیشنهادی entitlement، ایمنی migration و تست.
- همگام‌سازی قوانین Cursor: پشتیبانی از مخزن مشتری روی مسیر/هاست دیگر از طریق `CURSOR_RULES_*` و `cursor-rules-sync.local.json`؛ نمونه `cursor-rules-sync.local.example.json`؛ به‌روزرسانی `docs/CURSOR-RULES-SYNC.md`.
- همگام‌سازی قوانین Cursor: اسکریپت `scripts/sync-cursor-rules.ps1`، دستورات Composer (`sync-cursor-rules` / `sync-cursor-rules:reverse`)، قلاب Git `.githooks/post-commit` و راهنما `docs/CURSOR-RULES-SYNC.md`.
- همسان‌سازی محتوای `.cursor/rules/*.mdc` بین **saas-platform** و **customer-site** (شامل `persian-date-only`, `project-guide-update`, `site-modules-from-platform`, `saas-platform-main-admin`, `saas-module-guide-update`, `content-modules-jalali-editor`, `global-rules`).
- قانون Cursor «Billing and Payment»: `globs` به مسیرهای واقعی Laravel (`app/Services/Payment/**`, مدل‌های فاکتور/درگاه) هم‌راستا شد؛ حفظ درگاه‌های ایرانی و IRR صریح شد.
- راه‌اندازی مستندات ماژول‌ها (`docs/modules/`) و پرامپت استاندارد (`docs/PROMPT_FOR_CURSOR.md`).
