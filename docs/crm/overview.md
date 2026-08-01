# CRM — Overview

ماژول تجاری **CRM** (`saas-platform/modules-crm`, DB slug: `crm-suite`) اولین ماژول تجاری بزرگ پلتفرم است — مدیریت کامل مشتریان، سرنخ‌ها، معاملات و خط لوله فروش.

## قابلیت‌ها

| حوزه | توضیح |
|---|---|
| Entities | Company، Contact، Lead، Opportunity، Pipeline/Stage، Activity، Task، Attachment، Tag |
| Dashboard | آمار لحظه‌ای (سرنخ، مشتری، معامله باز، ارزش خط لوله، برد/باخت، وظایف امروز) — Admin و Customer |
| Sales Kanban | نمای Drag & Drop مراحل معامله، بدون Refresh، با ثبت خودکار Activity |
| Sales Reports | Win/Lost/Conversion Rate، Revenue Forecast، فروش به تفکیک مرحله و دوره |
| Import/Export | CSV Import برای Contact/Company/Lead (با Preview)، CSV Export برای Contacts/Opportunities/Reports |
| Task Reminder | یادآوری خودکار سررسید/تأخیر وظیفه و معامله بدون فعالیت — از طریق زیرساخت Notification موجود پلتفرم |
| اتصال به سایر ماژول‌ها | فرم تماس (Forms) → Lead خودکار؛ تیکت مهم (Tickets) → Activity خودکار روی مخاطب منطبق |

## معماری

- **استقلال کامل**: کد داخل `src/` هرگز به `App\Models\*` وابسته نیست. مدل‌های User/Site میزبان از طریق `config('crm.user_model')`/`config('crm.site_model')` resolve می‌شوند (مطابق قرارداد ماژول Tickets).
- **بررسی مکانیکی استقلال**: `php artisan crm:validate-isolation` — اسکن تمام فایل‌های `src/` برای ارجاع مستقیم به `App\`.
- **Multi-Tenant**: هر Entity ستون `site_id` واقعی (Foreign Key به جدول `sites`) دارد. برخلاف `SiteTenantScope` (که فقط در context API فعال است)، تمام کنترلرهای Admin/Site به‌صورت صریح روی `site_id` فیلتر می‌کنند — نه صرفاً یک Global Scope.
- **Extension Points عمومی** (بدون وابستگی مستقیم بین ماژول‌ها):
  - `SiteNotificationDispatcherInterface` (در `core-utils`) — هر ماژولی می‌تواند بدون شناخت جزئیات Core، به مدیر سایت اطلاع‌رسانی کند.
  - رویدادهای Laravel (`FormSubmissionCreated`, `TicketCreated`) — CRM با `class_exists()` guard به آن‌ها گوش می‌دهد؛ هیچ‌کدام از ماژول‌ها به یکدیگر import مستقیم ندارند.

## وضعیت فروش (Marketplace Readiness)

| مورد | وضعیت |
|---|---|
| Module (Catalog) | ✅ `slug=crm-suite`, `price_irr=990000`, `marketplace_visible=true` |
| Product | ✅ `sku=module-crm-suite` |
| Release منتشرشده | ✅ نسخه `1.0.0`, `status=published` |
| License Flow | ✅ از طریق زیرساخت عمومی Module Ecosystem — بدون منطق اختصاصی licensing |

## اسناد مرتبط

- [installation.md](installation.md)
- [admin-guide.md](admin-guide.md)
- [customer-guide.md](customer-guide.md)
