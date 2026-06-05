<?php

namespace SaaSPlatform\Modules\Education\Services;

use Illuminate\Support\Str;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;
use SaaSPlatform\Modules\Education\Models\EducationWalkthroughStep;

/**
 * Generates step-by-step guided walkthroughs per feature.
 * Rule 6: each walkthrough linked to route_pattern, with actionable steps.
 */
class EducationWalkthroughGeneratorService
{
    public function generateMissing(?int $siteId = null): int
    {
        $created = 0;
        $query = EducationFeature::query();
        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        foreach ($query->get() as $feature) {
            $exists = EducationWalkthrough::where('module_slug', $feature->module_slug)
                ->where('trigger_route', $feature->route_name)
                ->when($siteId, fn($q) => $q->where('site_id', $siteId))
                ->exists();

            if (! $exists && $feature->route_name) {
                $this->generateForFeature($feature, $siteId);
                $created++;
            }
        }
        return $created;
    }

    public function generateForFeature(EducationFeature $feature, ?int $siteId = null): ?EducationWalkthrough
    {
        if (empty($feature->route_name)) {
            return null;
        }

        $steps = $this->buildSteps($feature);
        if (empty($steps)) {
            return null;
        }

        $hash = hash('sha256', $feature->feature_slug . '|' . json_encode($steps));

        $walkthrough = EducationWalkthrough::create([
            'title'                 => "راهنمای گام‌به‌گام: {$feature->feature_name}",
            'slug'                  => 'walkthrough-' . Str::slug($feature->feature_name) . '-' . Str::random(4),
            'description'           => "راهنمای تعاملی برای استفاده از {$feature->feature_name} — گام‌به‌گام از ابتدا تا انتها.",
            'site_id'               => $siteId ?? $feature->site_id,
            'trigger_route'         => $feature->route_name,
            'module_slug'           => $feature->module_slug,
            'feature_key'           => $feature->feature_slug,
            'auto_generated'        => true,
            'is_active'             => true,
            'sort_order'            => 0,
            'documentation_hash'    => $hash,
            'last_generated_at'     => now(),
            'documentation_version' => 1,
            'needs_regeneration'    => false,
        ]);

        foreach ($steps as $index => $step) {
            EducationWalkthroughStep::create([
                'edu_walkthrough_id' => $walkthrough->id,
                'step_number'        => $index + 1,
                'title'              => $step['title'],
                'body'               => $step['content'],
                'target_selector'    => $step['selector'] ?? null,
                'action'             => $step['action'] ?? 'tooltip',
            ]);
        }

        return $walkthrough;
    }

    public function regenerateForFeature(EducationFeature $feature, EducationWalkthrough $existing): void
    {
        $steps    = $this->buildSteps($feature);
        $hash     = hash('sha256', $feature->feature_slug . '|' . json_encode($steps));
        $newTitle = "راهنمای گام‌به‌گام: {$feature->feature_name}";

        $existing->update([
            'title'                 => $newTitle,
            'description'           => "راهنمای تعاملی برای استفاده از {$feature->feature_name} — گام‌به‌گام از ابتدا تا انتها.",
            'documentation_hash'    => $hash,
            'last_generated_at'     => now(),
            'documentation_version' => ($existing->documentation_version ?? 1) + 1,
            'needs_regeneration'    => false,
        ]);

        // Replace steps
        $existing->steps()->delete();
        foreach ($steps as $index => $step) {
            EducationWalkthroughStep::create([
                'edu_walkthrough_id' => $existing->id,
                'step_number'        => $index + 1,
                'title'              => $step['title'],
                'body'               => $step['content'],
                'target_selector'    => $step['selector'] ?? null,
                'action'             => $step['action'] ?? 'tooltip',
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Step definitions — Rule 6
    // ──────────────────────────────────────────────────────────────────────────

    private function buildSteps(EducationFeature $feature): array
    {
        $slug = strtolower($feature->module_slug . '.' . $feature->feature_slug);

        return match(true) {
            str_contains($slug, 'forms.forms') && ! str_contains($slug, 'submission') => $this->stepsForForms($feature),
            str_contains($slug, 'forms') && str_contains($slug, 'submission')          => $this->stepsForFormSubmissions($feature),
            str_contains($slug, 'tickets') || str_contains($slug, 'support')           => $this->stepsForTickets($feature),
            str_contains($slug, 'users')                                               => $this->stepsForUsers($feature),
            str_contains($slug, 'site')                                                => $this->stepsForSites($feature),
            str_contains($slug, 'customer')                                            => $this->stepsForCustomers($feature),
            str_contains($slug, 'product') || str_contains($slug, 'store')             => $this->stepsForProducts($feature),
            str_contains($slug, 'theme')                                               => $this->stepsForTheme($feature),
            str_contains($slug, 'education')                                           => $this->stepsForEducation($feature),
            str_contains($slug, 'access') || str_contains($slug, 'permission')        => $this->stepsForAccess($feature),
            str_contains($slug, 'setting')                                             => $this->stepsForSettings($feature),
            default                                                                    => $this->stepsDefault($feature),
        };
    }

    private function stepsForForms(EducationFeature $feature): array
    {
        return [
            ['title' => 'ورود به بخش فرم‌ساز', 'content' => 'از منوی سمت راست، روی «فرم‌ساز» کلیک کنید. لیست فرم‌های موجود نمایش داده می‌شود.', 'selector' => '[data-menu-key="forms"]', 'action' => 'highlight'],
            ['title' => 'ایجاد فرم جدید', 'content' => 'روی دکمه «+ فرم جدید» در بالای صفحه کلیک کنید.', 'selector' => '[data-action="form-create"]', 'action' => 'click'],
            ['title' => 'تعیین عنوان فرم', 'content' => 'عنوان فارسی فرم را در فیلد «عنوان» وارد کنید. این عنوان در پنل و فرانت‌اند نمایش داده می‌شود.', 'selector' => 'input[name="title"]', 'action' => 'tooltip'],
            ['title' => 'افزودن فیلدها', 'content' => 'از پنل چپ، نوع فیلد مورد نظر را انتخاب کنید (متن، انتخابی، فایل، تاریخ). برای مرتب‌سازی فیلدها، آن‌ها را drag & drop کنید.', 'selector' => '.field-palette', 'action' => 'tooltip'],
            ['title' => 'تنظیم اعتبارسنجی', 'content' => 'روی هر فیلد کلیک کنید و از پنل تنظیمات، قوانین اعتبارسنجی (اجباری، حداقل طول و ...) را فعال کنید.', 'selector' => '.field-settings-panel', 'action' => 'tooltip'],
            ['title' => 'تنظیمات ارسال', 'content' => 'در تب «ارسال»، پیام تأیید و ایمیل دریافت‌کننده پاسخ‌ها را وارد کنید.', 'selector' => '[data-tab="submission"]', 'action' => 'click'],
            ['title' => 'ذخیره و انتشار', 'content' => 'روی دکمه «ذخیره» کلیک کنید. برای فعال‌سازی فرم، وضعیت را به «فعال» تغییر دهید.', 'selector' => '[data-action="save-form"]', 'action' => 'click'],
        ];
    }

    private function stepsForFormSubmissions(EducationFeature $feature): array
    {
        return [
            ['title' => 'مشاهده ارسال‌های فرم', 'content' => 'از منوی «گزارش ارسال فرم‌ها» را انتخاب کنید تا لیست تمام پاسخ‌ها نمایش داده شود.', 'selector' => '[data-menu-key="forms_submissions"]', 'action' => 'highlight'],
            ['title' => 'فیلتر کردن', 'content' => 'از فیلترهای بالای صفحه برای محدود کردن نتایج به یک فرم خاص یا بازه زمانی استفاده کنید.', 'selector' => '.submissions-filter', 'action' => 'tooltip'],
            ['title' => 'مشاهده جزئیات', 'content' => 'روی هر ارسال کلیک کنید تا جزئیات کامل پاسخ‌ها را ببینید.', 'selector' => '.submission-row', 'action' => 'click'],
            ['title' => 'صادرکردن', 'content' => 'روی دکمه «صادرکردن» کلیک کنید و فرمت CSV یا Excel را انتخاب کنید.', 'selector' => '[data-action="export"]', 'action' => 'click'],
        ];
    }

    private function stepsForTickets(EducationFeature $feature): array
    {
        return [
            ['title' => 'ورود به صندوق پشتیبانی', 'content' => 'از منوی پشتیبانی، «صندوق پشتیبانی» را انتخاب کنید. لیست تیکت‌های دریافتی نمایش داده می‌شود.', 'selector' => '[data-menu-key="support"]', 'action' => 'highlight'],
            ['title' => 'فیلتر تیکت‌ها', 'content' => 'از فیلترهای بالای صفحه، وضعیت (باز/بسته)، اولویت و اپراتور را انتخاب کنید.', 'selector' => '.ticket-filters', 'action' => 'tooltip'],
            ['title' => 'باز کردن تیکت', 'content' => 'روی عنوان تیکت کلیک کنید تا صفحه جزئیات باز شود.', 'selector' => '.ticket-row-title', 'action' => 'click'],
            ['title' => 'پاسخ دادن', 'content' => 'متن پاسخ را در کادر پایین صفحه وارد کنید. می‌توانید از «پیام آماده» استفاده کنید.', 'selector' => '.reply-editor', 'action' => 'tooltip'],
            ['title' => 'تغییر وضعیت', 'content' => 'پس از پاسخ‌دهی، وضعیت تیکت را از «باز» به «در انتظار» یا «حل‌شده» تغییر دهید.', 'selector' => '[data-action="change-status"]', 'action' => 'click'],
            ['title' => 'تخصیص به اپراتور', 'content' => 'از منوی کشویی «تخصیص به»، اپراتور مناسب را انتخاب کنید.', 'selector' => '[data-action="assign-operator"]', 'action' => 'tooltip'],
        ];
    }

    private function stepsForUsers(EducationFeature $feature): array
    {
        return [
            ['title' => 'ورود به مدیریت کاربران', 'content' => 'از منوی «کاربران» را انتخاب کنید.', 'selector' => '[data-menu-key="users"]', 'action' => 'highlight'],
            ['title' => 'افزودن کاربر جدید', 'content' => 'روی «+ کاربر جدید» کلیک کنید.', 'selector' => '[data-action="user-create"]', 'action' => 'click'],
            ['title' => 'اطلاعات کاربر', 'content' => 'نام، شماره موبایل (برای OTP) و نقش را وارد کنید.', 'selector' => 'form.user-form', 'action' => 'tooltip'],
            ['title' => 'تخصیص نقش', 'content' => 'از لیست کشویی نقش‌ها، سطح دسترسی مناسب را انتخاب کنید.', 'selector' => 'select[name="role"]', 'action' => 'click'],
            ['title' => 'ذخیره', 'content' => 'روی «ذخیره» کلیک کنید. کاربر لینک تأیید از طریق SMS دریافت می‌کند.', 'selector' => '[data-action="save-user"]', 'action' => 'click'],
        ];
    }

    private function stepsForSites(EducationFeature $feature): array
    {
        return [
            ['title' => 'بخش سایت‌ها', 'content' => 'از منوی «سایت‌ها» را انتخاب کنید.', 'selector' => '[data-menu-key="sites"]', 'action' => 'highlight'],
            ['title' => 'ایجاد سایت جدید', 'content' => 'روی «+ سایت جدید» کلیک کنید.', 'selector' => '[data-action="site-create"]', 'action' => 'click'],
            ['title' => 'اطلاعات اتصال', 'content' => 'نام سایت، دامنه و اطلاعات دیتابیس را وارد کنید.', 'selector' => '.site-connection-form', 'action' => 'tooltip'],
            ['title' => 'انتخاب مشتری', 'content' => 'صاحب سایت را از لیست مشتریان انتخاب کنید.', 'selector' => 'select[name="customer_id"]', 'action' => 'click'],
            ['title' => 'انتخاب ماژول‌ها', 'content' => 'ماژول‌هایی که در لایسنس این سایت هستند را تیک بزنید.', 'selector' => '.modules-selector', 'action' => 'tooltip'],
            ['title' => 'تاریخ لایسنس', 'content' => 'تاریخ انقضای پشتیبانی و هاست را تنظیم کنید.', 'selector' => 'input[name="support_expires_at"]', 'action' => 'tooltip'],
            ['title' => 'ذخیره', 'content' => 'روی «ذخیره» کلیک کنید. سایت در لیست نمایش داده می‌شود.', 'selector' => '[data-action="save-site"]', 'action' => 'click'],
        ];
    }

    private function stepsForCustomers(EducationFeature $feature): array
    {
        return [
            ['title' => 'بخش مشتریان', 'content' => 'از منوی «مشتریان» را انتخاب کنید.', 'selector' => '[data-menu-key="customers"]', 'action' => 'highlight'],
            ['title' => 'افزودن مشتری', 'content' => 'روی «+ مشتری جدید» کلیک کنید.', 'selector' => '[data-action="customer-create"]', 'action' => 'click'],
            ['title' => 'اطلاعات مشتری', 'content' => 'نام، موبایل، نوع (حقیقی/حقوقی) و اطلاعات تماس را وارد کنید.', 'selector' => '.customer-form', 'action' => 'tooltip'],
            ['title' => 'ذخیره', 'content' => 'روی «ذخیره» کلیک کنید. مشتری در لیست اضافه می‌شود.', 'selector' => '[data-action="save-customer"]', 'action' => 'click'],
        ];
    }

    private function stepsForProducts(EducationFeature $feature): array
    {
        return [
            ['title' => 'بخش محصولات', 'content' => 'از منوی فروشگاه، «محصولات» را انتخاب کنید.', 'selector' => '[data-menu-key="products"]', 'action' => 'highlight'],
            ['title' => 'ایجاد محصول', 'content' => 'روی «+ محصول جدید» کلیک کنید.', 'selector' => '[data-action="product-create"]', 'action' => 'click'],
            ['title' => 'نوع محصول', 'content' => 'نوع را انتخاب کنید: ماژول، پلن اشتراک، یا خدمت یکبار مصرف.', 'selector' => 'select[name="type"]', 'action' => 'click'],
            ['title' => 'قیمت و دوره', 'content' => 'قیمت پایه و دوره صورت‌حساب (ماهانه/سالانه/یکبار) را وارد کنید.', 'selector' => '.pricing-section', 'action' => 'tooltip'],
            ['title' => 'ماژول‌های شامل', 'content' => 'ماژول‌هایی که با خرید این محصول فعال می‌شوند را انتخاب کنید.', 'selector' => '.included-modules', 'action' => 'tooltip'],
            ['title' => 'انتشار', 'content' => 'وضعیت را به «فعال» تغییر دهید و ذخیره کنید.', 'selector' => '[data-action="save-product"]', 'action' => 'click'],
        ];
    }

    private function stepsForTheme(EducationFeature $feature): array
    {
        return [
            ['title' => 'بخش تم', 'content' => 'از منوی «تم‌ها» را انتخاب کنید.', 'selector' => '[data-menu-key="theme_manager"]', 'action' => 'highlight'],
            ['title' => 'انتخاب تم پایه', 'content' => 'از ۸ تم پیش‌ساخته، تم نزدیک‌تر به هویت بصری مورد نظر را انتخاب کنید.', 'selector' => '.theme-presets', 'action' => 'tooltip'],
            ['title' => 'شخصی‌سازی رنگ‌ها', 'content' => 'روی «ویرایش توکن‌ها» کلیک کنید و رنگ‌های اصلی، ثانویه و accent را تنظیم کنید.', 'selector' => '[data-action="edit-tokens"]', 'action' => 'click'],
            ['title' => 'پیش‌نمایش', 'content' => 'تغییرات را در پنجره پیش‌نمایش زنده بررسی کنید.', 'selector' => '.theme-preview', 'action' => 'tooltip'],
            ['title' => 'تخصیص به سایت', 'content' => 'از دکمه «تخصیص به سایت»، سایت مورد نظر را انتخاب کنید.', 'selector' => '[data-action="assign-theme"]', 'action' => 'click'],
        ];
    }

    private function stepsForEducation(EducationFeature $feature): array
    {
        return [
            ['title' => 'بخش آموزش', 'content' => 'از منوی «آموزش» را انتخاب کنید.', 'selector' => '[data-menu-key="education"]', 'action' => 'highlight'],
            ['title' => 'همگام‌سازی خودکار', 'content' => 'روی «همگام‌سازی» کلیک کنید تا مستندات برای ویژگی‌های فعال سایت تولید شود.', 'selector' => '[data-action="sync-education"]', 'action' => 'click'],
            ['title' => 'مرور مقالات', 'content' => 'از بخش «مقالات»، مقالات پیش‌نویس را بررسی و ویرایش کنید.', 'selector' => '[data-menu-item="articles"]', 'action' => 'click'],
            ['title' => 'انتشار مقاله', 'content' => 'پس از بررسی محتوا، روی «انتشار» کلیک کنید تا مقاله برای کاربران نمایش داده شود.', 'selector' => '[data-action="publish-article"]', 'action' => 'click'],
        ];
    }

    private function stepsForAccess(EducationFeature $feature): array
    {
        return [
            ['title' => 'بخش دسترسی', 'content' => 'از منوی «دسترسی» را انتخاب کنید.', 'selector' => '[data-menu-key="access"]', 'action' => 'highlight'],
            ['title' => 'ایجاد نقش', 'content' => 'روی «+ نقش جدید» کلیک کنید.', 'selector' => '[data-action="role-create"]', 'action' => 'click'],
            ['title' => 'نامگذاری نقش', 'content' => 'نام نمایشی و اسلاگ نقش را وارد کنید.', 'selector' => 'input[name="name"]', 'action' => 'tooltip'],
            ['title' => 'انتخاب مجوزها', 'content' => 'از لیست مجوزها، عملیات مجاز برای این نقش را تیک بزنید.', 'selector' => '.permissions-list', 'action' => 'tooltip'],
            ['title' => 'ذخیره', 'content' => 'روی «ذخیره» کلیک کنید. نقش قابل تخصیص به کاربران است.', 'selector' => '[data-action="save-role"]', 'action' => 'click'],
        ];
    }

    private function stepsForSettings(EducationFeature $feature): array
    {
        return [
            ['title' => 'بخش تنظیمات', 'content' => 'از منوی «تنظیمات» را انتخاب کنید.', 'selector' => '[data-menu-key="settings"]', 'action' => 'highlight'],
            ['title' => 'انتخاب دسته تنظیمات', 'content' => 'از تب‌های بالای صفحه، دسته مورد نظر را انتخاب کنید (عمومی، ایمیل، امنیت و ...).', 'selector' => '.settings-tabs', 'action' => 'tooltip'],
            ['title' => 'ویرایش مقادیر', 'content' => 'مقادیر جدید را وارد کنید. فیلدهای حساس (API key) با *** پنهان‌سازی می‌شوند.', 'selector' => '.settings-form', 'action' => 'tooltip'],
            ['title' => 'ذخیره تغییرات', 'content' => 'روی «ذخیره» کلیک کنید. برای اعمال برخی تنظیمات ممکن است نیاز به پاک‌سازی کش باشد.', 'selector' => '[data-action="save-settings"]', 'action' => 'click'],
        ];
    }

    private function stepsDefault(EducationFeature $feature): array
    {
        $name = $feature->feature_name;
        return [
            ['title' => "ورود به {$name}", 'content' => "از منوی اصلی، گزینه «{$name}» را انتخاب کنید.", 'action' => 'highlight'],
            ['title' => 'بررسی لیست', 'content' => 'لیست رکوردهای موجود را مشاهده کنید. از فیلترها برای جستجو استفاده کنید.', 'action' => 'tooltip'],
            ['title' => 'ایجاد رکورد جدید', 'content' => 'روی دکمه «+ افزودن» کلیک کنید و فرم را پر کنید.', 'action' => 'click'],
            ['title' => 'ذخیره', 'content' => 'تغییرات را ذخیره کنید. رکورد جدید در لیست نمایش داده می‌شود.', 'action' => 'click'],
        ];
    }
}
