<?php

namespace SaaSPlatform\Modules\Education\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationFeatureRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\DocumentationGeneratorServiceInterface;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationFeature;

/**
 * Generates structured, professional documentation articles for each feature.
 * Rule 5: 12 sections, 1500–3000 words, enterprise-grade quality.
 */
class DocumentationGeneratorService implements DocumentationGeneratorServiceInterface
{
    public function __construct(
        private readonly EducationFeatureRepositoryInterface $features,
    ) {}

    // ──────────────────────────────────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────────────────────────────────

    public function generateMissing(?int $siteId = null): int
    {
        $created = 0;
        foreach ($this->features->all($siteId) as $feature) {
            if ($this->hasDocumentation($feature)) {
                continue;
            }
            if ($this->generateForFeature($feature, $siteId)) {
                $created++;
            }
        }
        return $created;
    }

    public function generateForFeature(EducationFeature $feature, ?int $siteId = null): ?EducationArticle
    {
        $targetSiteId = $siteId ?? $feature->site_id;

        // F-08: Wrap in a transaction with SELECT ... FOR UPDATE to prevent duplicate articles
        // when two queue workers or concurrent web requests race on the same feature.
        return DB::transaction(function () use ($feature, $targetSiteId) {
            // Re-check inside the lock — another process may have created it since our outer check
            $locked = EducationArticle::where('module_slug', $feature->module_slug)
                ->where('route_pattern', $feature->route_name)
                ->where('auto_generated', true)
                ->when($targetSiteId, fn($q) => $q->where('site_id', $targetSiteId))
                ->lockForUpdate()
                ->first();

            if ($locked) {
                return null;
            }

            $title   = $this->buildTitle($feature);
            $body    = $this->buildBody($feature);
            $excerpt = $this->buildExcerpt($feature);
            $hash    = EducationArticle::computeHashFromParts($title, $body, $excerpt);

            return EducationArticle::create([
                'edu_category_id'       => null,
                'author_id'             => null,
                'site_id'               => $targetSiteId,
                'title'                 => $title,
                'slug'                  => Str::slug($title) . '-' . Str::random(4),
                'excerpt'               => $excerpt,
                'body'                  => $body,
                'status'                => EducationArticle::STATUS_DRAFT,
                'content_type'          => 'guide',
                'module_slug'           => $feature->module_slug,
                'route_pattern'         => $feature->route_name,
                'feature_key'           => $feature->feature_slug,
                'auto_generated'        => true,
                'documentation_hash'    => $hash,
                'last_generated_at'     => now(),
                'documentation_version' => 1,
                'needs_regeneration'    => false,
                'published_at'          => null,
            ]);
        });
    }

    public function regenerateForFeature(EducationFeature $feature, ?int $siteId = null): EducationArticle
    {
        $targetSiteId = $siteId ?? $feature->site_id;

        $existing = EducationArticle::where('module_slug', $feature->module_slug)
            ->where('route_pattern', $feature->route_name)
            ->where('auto_generated', true)
            ->when($targetSiteId, fn($q) => $q->where('site_id', $targetSiteId))
            ->first();

        $title   = $this->buildTitle($feature);
        $body    = $this->buildBody($feature);
        $excerpt = $this->buildExcerpt($feature);
        $hash    = EducationArticle::computeHashFromParts($title, $body, $excerpt);

        $data = [
            'title'                 => $title,
            'excerpt'               => $excerpt,
            'body'                  => $body,
            'documentation_hash'    => $hash,
            'last_generated_at'     => now(),
            'needs_regeneration'    => false,
        ];

        if ($existing) {
            $existing->update(array_merge($data, [
                'documentation_version' => ($existing->documentation_version ?? 1) + 1,
            ]));
            return $existing->fresh();
        }

        return $this->generateForFeature($feature, $targetSiteId)
            ?? EducationArticle::where('module_slug', $feature->module_slug)->where('auto_generated', true)->first();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Content builders — Rule 5: 12 sections, 1500–3000 words
    // ──────────────────────────────────────────────────────────────────────────

    private function buildTitle(EducationFeature $feature): string
    {
        return "راهنمای {$feature->feature_name}";
    }

    private function buildExcerpt(EducationFeature $feature): string
    {
        $knowledge = $this->getModuleKnowledge($feature);
        return $knowledge['excerpt'] ?? "راهنمای جامع بخش {$feature->feature_name} در ماژول {$feature->module_slug}. این مقاله شامل نحوه پیکربندی، استفاده روزانه، بهترین شیوه‌ها و رفع اشکال است.";
    }

    private function buildBody(EducationFeature $feature): string
    {
        $k    = $this->getModuleKnowledge($feature);
        $name = e($feature->feature_name);
        $mod  = e($feature->module_slug);
        $route = e($feature->route_name ?? '—');
        $loc  = e($feature->menu_location ?? '—');
        $today = Carbon::now()->format('Y-m-d');

        $overview       = $k['overview']       ?? "بخش <strong>{$name}</strong> در ماژول <code>{$mod}</code> ابزاری قدرتمند برای مدیریت و کنترل این بخش از سیستم است. این ابزار به مدیران پلتفرم اجازه می‌دهد تمام جنبه‌های {$name} را مشاهده، ایجاد، ویرایش و حذف کنند.";
        $useCases       = $k['use_cases']       ?? $this->defaultUseCases($feature);
        $whenToUse      = $k['when_to_use']     ?? $this->defaultWhenToUse($feature);
        $configGuide    = $k['config_guide']    ?? $this->defaultConfigGuide($feature);
        $fieldRef       = $k['field_reference'] ?? $this->defaultFieldReference($feature);
        $bestPractices  = $k['best_practices']  ?? $this->defaultBestPractices($feature);
        $mistakes       = $k['mistakes']        ?? $this->defaultMistakes($feature);
        $security       = $k['security']        ?? $this->defaultSecurity($feature);
        $faq            = $k['faq']             ?? $this->defaultFaq($feature);
        $related        = $k['related']         ?? $this->defaultRelated($feature);
        $troubleshooting = $k['troubleshooting'] ?? $this->defaultTroubleshooting($feature);

        return <<<HTML
<div class="edu-article" dir="rtl">

<div class="edu-meta-bar">
  <span class="edu-badge edu-badge--module">ماژول: {$mod}</span>
  <span class="edu-badge edu-badge--route">مسیر: {$route}</span>
  <span class="edu-badge edu-badge--updated">آخرین بروزرسانی: {$today}</span>
</div>

<h2 id="overview">۱. بررسی اجمالی</h2>
<p>{$overview}</p>
<p>این بخش به‌طور کامل با سیستم مجوزهای نقش‌محور (RBAC) پلتفرم یکپارچه است و تمام تغییرات در لاگ فعالیت ثبت می‌شوند. دسترسی به این بخش از طریق مسیر <code>{$route}</code> در پنل ادمین انجام می‌شود.</p>

<h2 id="use-cases">۲. کاربردهای کسب‌وکار</h2>
{$useCases}

<h2 id="when-to-use">۳. چه زمانی از این بخش استفاده کنیم</h2>
{$whenToUse}

<h2 id="config-guide">۴. راهنمای پیکربندی</h2>
{$configGuide}

<h2 id="field-reference">۵. مرجع فیلدها</h2>
{$fieldRef}

<h2 id="best-practices">۶. بهترین شیوه‌ها</h2>
{$bestPractices}

<h2 id="mistakes">۷. اشتباهات رایج</h2>
{$mistakes}

<h2 id="security">۸. نکات امنیتی</h2>
{$security}

<h2 id="faq">۹. سوالات متداول</h2>
{$faq}

<h2 id="related">۱۰. ویژگی‌های مرتبط</h2>
{$related}

<h2 id="troubleshooting">۱۱. رفع اشکال</h2>
{$troubleshooting}

<h2 id="changelog">۱۲. تاریخچه تغییرات</h2>
<table class="edu-changelog-table">
  <thead><tr><th>تاریخ</th><th>نسخه</th><th>تغییر</th></tr></thead>
  <tbody>
    <tr><td>{$today}</td><td>v1</td><td>مستندات اولیه تولید شد.</td></tr>
  </tbody>
</table>

</div>
HTML;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Module-specific knowledge base
    // ──────────────────────────────────────────────────────────────────────────

    private function getModuleKnowledge(EducationFeature $feature): array
    {
        $slug = strtolower($feature->module_slug . '.' . $feature->feature_slug);

        return match(true) {
            str_contains($slug, 'forms')      => $this->knowledgeForms($feature),
            str_contains($slug, 'tickets') || str_contains($slug, 'support') => $this->knowledgeTickets($feature),
            str_contains($slug, 'users')      => $this->knowledgeUsers($feature),
            str_contains($slug, 'site')       => $this->knowledgeSites($feature),
            str_contains($slug, 'customer')   => $this->knowledgeCustomers($feature),
            str_contains($slug, 'reseller')   => $this->knowledgeResellers($feature),
            str_contains($slug, 'product') || str_contains($slug, 'store') => $this->knowledgeStore($feature),
            str_contains($slug, 'theme')      => $this->knowledgeTheme($feature),
            str_contains($slug, 'education') || str_contains($slug, 'kb') => $this->knowledgeEducation($feature),
            str_contains($slug, 'license') || str_contains($slug, 'module') => $this->knowledgeModules($feature),
            str_contains($slug, 'access') || str_contains($slug, 'role') || str_contains($slug, 'permission') => $this->knowledgeAccess($feature),
            str_contains($slug, 'setting')    => $this->knowledgeSettings($feature),
            default                            => [],
        };
    }

    private function knowledgeForms(EducationFeature $feature): array
    {
        $name = $feature->feature_name;
        return [
            'excerpt' => 'راهنمای جامع فرم‌ساز — ساخت فرم‌های سفارشی، تنظیم فیلدها، قوانین اعتبارسنجی و مدیریت پاسخ‌ها.',
            'overview' => "بخش <strong>{$name}</strong> یک موتور فرم‌ساز کامل است که به مدیران اجازه می‌دهد فرم‌های سفارشی را بدون کدنویسی بسازند. فرم‌ها می‌توانند در صفحات، ایمیل‌ها و ویجت‌های ساید‌بار جاسازی شوند. تمام ارسال‌ها در پایگاه داده ذخیره و قابل صادرکردن به CSV/Excel هستند.",
            'use_cases' => '<ul>
<li><strong>ثبت‌نام رویداد:</strong> ساخت فرم ثبت‌نام با فیلدهای نام، تلفن و انتخاب رویداد</li>
<li><strong>درخواست مشاوره:</strong> فرم چندمرحله‌ای با بارگذاری فایل و تأیید OTP</li>
<li><strong>نظرسنجی مشتریان:</strong> فرم با فیلدهای رتبه‌بندی ستاره‌ای و توضیحات آزاد</li>
<li><strong>تیکت پشتیبانی داخلی:</strong> فرم درخواست پشتیبانی با اولویت‌بندی</li>
<li><strong>جذب رزومه:</strong> فرم استخدام با بارگذاری CV و فیلدهای مخصوص</li>
</ul>',
            'when_to_use' => '<p>زمانی از فرم‌ساز استفاده کنید که:</p>
<ul>
<li>نیاز به جمع‌آوری داده از کاربران دارید بدون اینکه توسعه‌دهنده نیاز باشد</li>
<li>فرآیندهای تجاری به ورودی‌های ساختاریافته نیاز دارند</li>
<li>می‌خواهید پاسخ‌ها را فیلتر، مرتب‌سازی و صادر کنید</li>
<li>به اعلان‌های ایمیل/پیامک پس از ارسال فرم نیاز دارید</li>
</ul>',
            'config_guide' => '<ol>
<li><strong>ایجاد فرم جدید:</strong> روی دکمه «+ فرم جدید» کلیک کنید و عنوان فارسی وارد کنید.</li>
<li><strong>افزودن فیلدها:</strong> از پنل فیلدها، نوع فیلد را انتخاب کنید (متن، انتخابی، فایل، تاریخ، امتیاز). فیلدها را با دَرَگ و دراپ مرتب کنید.</li>
<li><strong>اعتبارسنجی:</strong> برای هر فیلد قوانین اعتبارسنجی تنظیم کنید (اجباری، حداقل/حداکثر طول، عبارت منظم).</li>
<li><strong>تنظیمات ارسال:</strong> URL ریدایرکت، پیام تأیید و ارسال ایمیل به ادمین را تنظیم کنید.</li>
<li><strong>انتشار:</strong> فرم را ذخیره و کد جاسازی را در صفحات استفاده کنید.</li>
</ol>',
            'field_reference' => '<table class="edu-field-table">
<thead><tr><th>فیلد</th><th>نوع</th><th>توضیح</th></tr></thead>
<tbody>
<tr><td>عنوان فرم</td><td>رشته</td><td>نام نمایشی فرم در پنل و فرانت‌اند</td></tr>
<tr><td>اسلاگ</td><td>رشته</td><td>شناسه URL-safe برای جاسازی (خودکار تولید می‌شود)</td></tr>
<tr><td>وضعیت</td><td>انتخابی</td><td>فعال / غیرفعال / پیش‌نویس</td></tr>
<tr><td>ایمیل اعلان</td><td>ایمیل</td><td>آدرس دریافت پاسخ‌های جدید</td></tr>
<tr><td>پیام تأیید</td><td>متن</td><td>پیامی که پس از ارسال موفق به کاربر نمایش داده می‌شود</td></tr>
<tr><td>حداکثر ارسال</td><td>عدد</td><td>محدودیت تعداد ارسال؛ صفر = نامحدود</td></tr>
</tbody>
</table>',
            'best_practices' => '<ul>
<li>همیشه اسلاگ منحصربه‌فرد و معنادار انتخاب کنید تا URL فرم قابل خواندن باشد.</li>
<li>فرم‌های طولانی را به چند مرحله تقسیم کنید تا نرخ تکمیل بالاتر باشد.</li>
<li>برای فیلدهای حساس (کارت ملی، تلفن) اعتبارسنجی regex اضافه کنید.</li>
<li>ایمیل اعلان را به یک آدرس گروهی تنظیم کنید تا اطلاعات از دست نرود.</li>
<li>ارسال‌های فرم را هفتگی بررسی و فایل CSV صادر کنید.</li>
</ul>',
            'mistakes' => '<ul>
<li><strong>فیلد اجباری بدون توضیح:</strong> همیشه در برچسب فیلد مشخص کنید که چرا این اطلاعات نیاز است.</li>
<li><strong>فراموشی تنظیم حداکثر ارسال:</strong> برای فرم‌های رویداد، همیشه محدودیت تنظیم کنید.</li>
<li><strong>عدم آزمایش فرم:</strong> پس از انتشار، فرم را از حساب کاربری عادی آزمایش کنید.</li>
<li><strong>عدم پشتیبان‌گیری:</strong> پاسخ‌های مهم را به‌صورت دوره‌ای صادر کنید.</li>
</ul>',
            'security' => '<ul>
<li>ارسال فرم‌ها با CSRF token محافظت می‌شود.</li>
<li>فایل‌های آپلودی در پوشه ایمن ذخیره و نوع آن‌ها بررسی می‌شود.</li>
<li>برای فرم‌های عمومی (بدون لاگین) کپچا فعال کنید.</li>
<li>دسترسی به پاسخ‌ها فقط برای ادمین‌های دارای مجوز <code>forms.view</code> امکان‌پذیر است.</li>
</ul>',
            'faq' => '<dl>
<dt>آیا می‌توان فیلدهای مشروط اضافه کرد؟</dt>
<dd>بله، از امکان «منطق شرطی» در تنظیمات هر فیلد استفاده کنید.</dd>
<dt>پاسخ‌ها تا چه مدت نگه داشته می‌شوند؟</dt>
<dd>پاسخ‌ها نامحدود نگه داشته می‌شوند؛ برای پاک‌سازی از فیلتر تاریخ استفاده کنید.</dd>
<dt>آیا می‌توان فرم را در اپلیکیشن موبایل استفاده کرد؟</dt>
<dd>بله، فرم‌ها از طریق Web View در موبایل هم نمایش داده می‌شوند.</dd>
</dl>',
            'related' => '<ul>
<li><a href="#" class="edu-link">ماژول پشتیبانی (تیکت)</a> — می‌توانید پاسخ فرم را به تیکت تبدیل کنید.</li>
<li><a href="#" class="edu-link">اعلان‌ها</a> — ارسال اعلان پس از تکمیل فرم</li>
<li><a href="#" class="edu-link">مدیریت کاربران</a> — اتصال پاسخ‌ها به پروفایل کاربران</li>
</ul>',
            'troubleshooting' => '<dl>
<dt>فرم ذخیره نمی‌شود</dt>
<dd>بررسی کنید که تمام فیلدهای اجباری پر باشند. خطاهای اعتبارسنجی در بالای صفحه نمایش داده می‌شوند.</dd>
<dt>ایمیل اعلان ارسال نمی‌شود</dt>
<dd>تنظیمات SMTP در پنل تنظیمات سیستم را بررسی کنید. از <code>php artisan queue:work</code> برای پردازش صف مطمئن شوید.</dd>
<dt>ارسال‌های تکراری</dt>
<dd>در تنظیمات فرم، گزینه «جلوگیری از ارسال تکراری بر اساس IP» را فعال کنید.</dd>
</dl>',
        ];
    }

    private function knowledgeTickets(EducationFeature $feature): array
    {
        $name = $feature->feature_name;
        return [
            'excerpt' => 'راهنمای جامع سیستم تیکت پشتیبانی — مدیریت درخواست‌ها، پاسخ‌دهی، تخصیص به اپراتور و گزارش‌گیری.',
            'overview' => "بخش <strong>{$name}</strong> مرکز مدیریت درخواست‌های پشتیبانی مشتریان است. این سیستم جریان کامل تیکت را از دریافت تا بستن پوشش می‌دهد: دریافت درخواست، دسته‌بندی، تخصیص، پاسخ‌دهی، و تأیید حل مشکل. تمام مکالمات ثبت و قابل جستجو هستند.",
            'use_cases' => '<ul>
<li><strong>پشتیبانی فنی:</strong> دریافت گزارش باگ و مشکلات سیستم از مشتریان</li>
<li><strong>استعلام قیمت:</strong> مدیریت درخواست‌های قیمت‌گذاری و پیشنهادیه</li>
<li><strong>مشکلات حساب کاربری:</strong> بازیابی دسترسی، تغییر اطلاعات</li>
<li><strong>بازگشت کالا:</strong> مدیریت فرآیند مرجوعی و استرداد وجه</li>
<li><strong>پشتیبانی تخصصی:</strong> ارجاع تیکت‌های پیچیده به تیم فنی</li>
</ul>',
            'when_to_use' => '<p>سیستم تیکت زمانی کاربرد دارد که:</p>
<ul>
<li>به سابقه مکالمات با مشتریان نیاز دارید</li>
<li>تیم پشتیبانی چند نفره دارید و نیاز به تخصیص تیکت است</li>
<li>می‌خواهید SLA و زمان پاسخ‌دهی را اندازه‌گیری کنید</li>
<li>حجم درخواست‌ها از توان مدیریت دستی خارج است</li>
</ul>',
            'config_guide' => '<ol>
<li><strong>دسته‌بندی‌ها:</strong> دسته‌های موضوعی تیکت را در تنظیمات > تیکت تعریف کنید.</li>
<li><strong>اپراتورها:</strong> کاربران با نقش «اپراتور پشتیبانی» می‌توانند تیکت پاسخ دهند.</li>
<li><strong>پیام‌های آماده:</strong> قالب‌های پاسخ سریع را از بخش «پیام‌های آماده» اضافه کنید.</li>
<li><strong>اعلان‌ها:</strong> اعلان SMS/Push هنگام دریافت تیکت جدید را از تنظیمات فعال کنید.</li>
<li><strong>ساعات کاری:</strong> ساعات پاسخ‌دهی را تنظیم کنید تا SLA درست محاسبه شود.</li>
</ol>',
            'field_reference' => '<table class="edu-field-table">
<thead><tr><th>فیلد</th><th>نوع</th><th>توضیح</th></tr></thead>
<tbody>
<tr><td>موضوع</td><td>رشته</td><td>عنوان کوتاه درخواست (حداکثر ۱۲۰ کاراکتر)</td></tr>
<tr><td>دسته‌بندی</td><td>انتخابی</td><td>نوع درخواست برای دسته‌بندی و مسیریابی</td></tr>
<tr><td>اولویت</td><td>انتخابی</td><td>پایین / معمولی / بالا / فوری</td></tr>
<tr><td>وضعیت</td><td>انتخابی</td><td>باز / در انتظار / بسته / حل‌شده</td></tr>
<tr><td>تخصیص به</td><td>کاربر</td><td>اپراتور پشتیبانی مسئول پاسخ‌دهی</td></tr>
<tr><td>پیوست</td><td>فایل</td><td>تصویر یا فایل مرتبط با مشکل</td></tr>
</tbody>
</table>',
            'best_practices' => '<ul>
<li>همیشه به تیکت‌های با اولویت فوری در کمتر از ۱ ساعت پاسخ دهید.</li>
<li>برای هر دسته یک اپراتور پیش‌فرض تعریف کنید.</li>
<li>از برچسب (Tag) برای دسته‌بندی مشکلات تکراری استفاده کنید.</li>
<li>تیکت‌های حل‌شده را پس از تأیید مشتری ببندید، نه قبل از آن.</li>
</ul>',
            'mistakes' => '<ul>
<li><strong>بستن زودهنگام تیکت:</strong> قبل از تأیید مشتری، تیکت را نبندید.</li>
<li><strong>عدم استفاده از اولویت:</strong> همه تیکت‌ها را «معمولی» نگذارید؛ فوری‌ها را علامت‌گذاری کنید.</li>
<li><strong>پاسخ بدون اطلاع‌رسانی:</strong> مطمئن شوید اعلان ایمیل/SMS برای پاسخ فعال است.</li>
</ul>',
            'security' => '<ul>
<li>کاربران فقط تیکت‌های خودشان را می‌بینند.</li>
<li>اپراتورها به تمام تیکت‌های تخصیص‌یافته دسترسی دارند.</li>
<li>پیوست‌های تیکت در مسیر ایمن و خارج از webroot ذخیره می‌شوند.</li>
<li>دسترسی به گزارش‌ها نیاز به مجوز <code>tickets.reports</code> دارد.</li>
</ul>',
            'faq' => '<dl>
<dt>آیا مشتری می‌تواند به تیکت خود پیام اضافه کند؟</dt>
<dd>بله، از طریق لینک تیکت در ایمیل اطلاع‌رسانی یا از پنل حساب کاربری.</dd>
<dt>آیا می‌توان تیکت را به تیم دیگری انتقال داد؟</dt>
<dd>بله، از دکمه «انتقال» در صفحه تیکت استفاده کنید.</dd>
</dl>',
            'related' => '<ul>
<li><a href="#" class="edu-link">مدیریت کاربران</a> — مشاهده پروفایل مشتری از صفحه تیکت</li>
<li><a href="#" class="edu-link">گزارش فعالیت</a> — سابقه کامل تعاملات پشتیبانی</li>
<li><a href="#" class="edu-link">فرم‌ساز</a> — ایجاد فرم‌های پشتیبانی سفارشی</li>
</ul>',
            'troubleshooting' => '<dl>
<dt>اعلان تیکت ارسال نمی‌شود</dt>
<dd>تنظیمات اعلان در پنل تنظیمات > اعلان‌ها را بررسی کنید. مطمئن شوید queue worker در حال اجراست.</dd>
<dt>تیکت در لیست نمایش داده نمی‌شود</dt>
<dd>فیلترهای فعال (وضعیت، اولویت) را بررسی و پاک کنید.</dd>
</dl>',
        ];
    }

    private function knowledgeUsers(EducationFeature $feature): array
    {
        $name = $feature->feature_name;
        return [
            'excerpt' => 'راهنمای مدیریت کاربران — ایجاد، ویرایش، تخصیص نقش و کنترل دسترسی کاربران پنل ادمین.',
            'overview' => "بخش <strong>{$name}</strong> مدیریت کامل حساب‌های کاربری پنل ادمین را فراهم می‌کند. از اینجا می‌توانید کاربران جدید ادمین ایجاد کنید، نقش‌های مختلف تخصیص دهید، دسترسی‌ها را کنترل کنید و لاگ فعالیت هر کاربر را مشاهده کنید.",
            'use_cases' => '<ul>
<li><strong>افزودن ادمین جدید:</strong> ایجاد حساب برای عضو جدید تیم</li>
<li><strong>تغییر نقش:</strong> ارتقا یا کاهش سطح دسترسی کاربر</li>
<li><strong>تعلیق حساب:</strong> غیرفعال کردن موقت دسترسی بدون حذف</li>
<li><strong>بازیابی رمز:</strong> ارسال لینک تنظیم مجدد رمز به کاربر</li>
</ul>',
            'config_guide' => '<ol>
<li>روی «+ کاربر جدید» کلیک کنید.</li>
<li>نام، شماره موبایل (جایگزین ایمیل) و نقش را وارد کنید.</li>
<li>برای ورود دو مرحله‌ای OTP را فعال کنید.</li>
<li>ذخیره کنید. کاربر لینک تأیید از طریق SMS دریافت می‌کند.</li>
</ol>',
            'field_reference' => '<table class="edu-field-table">
<thead><tr><th>فیلد</th><th>توضیح</th></tr></thead>
<tbody>
<tr><td>نام کامل</td><td>نام نمایشی کاربر در سیستم</td></tr>
<tr><td>موبایل</td><td>شماره برای ورود OTP (جایگزین ایمیل)</td></tr>
<tr><td>نقش</td><td>مجموعه مجوزهای تخصیص‌یافته به کاربر</td></tr>
<tr><td>وضعیت</td><td>فعال / غیرفعال / تعلیق</td></tr>
</tbody>
</table>',
            'best_practices' => '<ul>
<li>از اصل حداقل مجوز استفاده کنید: هر کاربر فقط به آنچه نیاز دارد دسترسی داشته باشد.</li>
<li>حساب‌های بلااستفاده را پس از ۳ ماه غیرفعال کنید.</li>
<li>هرگز اطلاعات ورود را از طریق چت به اشتراک نگذارید.</li>
</ul>',
            'security' => '<ul>
<li>ورود از طریق OTP موبایل — رمزهای ثابت پشتیبانی نمی‌شوند.</li>
<li>لاگ تمام ورودها و تغییرات ثبت می‌شود.</li>
<li>تلاش‌های ناموفق ورود پس از ۵ بار، حساب را قفل می‌کند.</li>
</ul>',
            'faq' => '<dl>
<dt>آیا می‌توان چند نقش به یک کاربر داد؟</dt>
<dd>خیر، هر کاربر دقیقاً یک نقش دارد. نقش‌ها ترکیب مجوزها هستند.</dd>
</dl>',
            'mistakes' => '<ul>
<li>عدم حذف دسترسی کارمندان پس از خروج از تیم.</li>
<li>استفاده از حساب ادمین اصلی برای کارهای روزمره — یک حساب شخصی با نقش مناسب بسازید.</li>
</ul>',
            'related' => '<ul><li>مدیریت نقش‌ها و مجوزها</li><li>لاگ فعالیت</li></ul>',
            'troubleshooting' => '<dl>
<dt>کاربر نمی‌تواند وارد شود</dt>
<dd>وضعیت حساب را بررسی کنید (باید «فعال» باشد). شماره موبایل را تأیید کنید.</dd>
</dl>',
        ];
    }

    private function knowledgeSites(EducationFeature $feature): array
    {
        return [
            'excerpt' => 'راهنمای مدیریت سایت‌ها — ایجاد، پیکربندی، تخصیص ماژول و مدیریت لایسنس سایت‌های زیرمجموعه.',
            'overview' => "بخش <strong>{$feature->feature_name}</strong> مرکز مدیریت تمام سایت‌های مشتریان است. از اینجا می‌توانید سایت جدید ایجاد کنید، دامنه و دیتابیس را پیکربندی کنید، ماژول‌های فعال را تعیین کنید و وضعیت لایسنس را کنترل کنید.",
            'use_cases' => '<ul>
<li>ایجاد سایت جدید برای مشتری</li>
<li>تمدید یا تغییر لایسنس سایت</li>
<li>اضافه یا حذف ماژول از لایسنس سایت</li>
<li>مشاهده آمار و وضعیت سایت‌های فعال</li>
</ul>',
            'config_guide' => '<ol>
<li>روی «+ سایت جدید» کلیک کنید.</li>
<li>نام سایت، دامنه، اطلاعات دیتابیس را وارد کنید.</li>
<li>مشتری صاحب سایت را تخصیص دهید.</li>
<li>ماژول‌های لایسنس را انتخاب کنید.</li>
<li>تاریخ انقضای پشتیبانی و هاست را تنظیم کنید.</li>
</ol>',
            'field_reference' => '<table class="edu-field-table">
<thead><tr><th>فیلد</th><th>توضیح</th></tr></thead>
<tbody>
<tr><td>عنوان</td><td>نام داخلی سایت در پنل پلتفرم</td></tr>
<tr><td>دامنه</td><td>آدرس سایت مشتری</td></tr>
<tr><td>دیتابیس</td><td>نام، کاربر و رمز دیتابیس مجزا برای این سایت</td></tr>
<tr><td>ماژول‌ها</td><td>لیست ماژول‌های فعال در لایسنس این سایت</td></tr>
<tr><td>پشتیبانی تا</td><td>تاریخ انقضای دوره پشتیبانی</td></tr>
</tbody>
</table>',
            'best_practices' => '<ul>
<li>هر سایت دیتابیس مجزا داشته باشد برای ایزولاسیون کامل.</li>
<li>تاریخ انقضا را ۲ هفته زودتر در تقویم علامت بزنید تا تمدید را فراموش نکنید.</li>
<li>پیش از تغییر ماژول‌ها، با مشتری هماهنگ کنید.</li>
</ul>',
            'security' => '<ul>
<li>اطلاعات دیتابیس در پایگاه داده رمزگذاری‌شده ذخیره می‌شوند.</li>
<li>دسترسی به این بخش نیاز به مجوز <code>sites.manage</code> دارد.</li>
</ul>',
            'faq' => '<dl>
<dt>آیا می‌توان یک ماژول را بدون خاموش کردن سایت حذف کرد؟</dt>
<dd>بله، حذف ماژول از لایسنس بلافاصله اثر می‌گذارد اما داده‌ها پاک نمی‌شوند.</dd>
</dl>',
            'mistakes' => '<ul>
<li>وارد کردن اطلاعات دیتابیس اشتباه — پیش از ذخیره تست اتصال را اجرا کنید.</li>
<li>حذف ماژول بدون اطلاع مشتری.</li>
</ul>',
            'related' => '<ul><li>مشتریان</li><li>مدیریت لایسنس</li><li>ماژول‌ها</li></ul>',
            'troubleshooting' => '<dl>
<dt>سایت پس از ذخیره در لیست نمایش داده نمی‌شود</dt>
<dd>صفحه را رفرش کنید. اگر مشکل ادامه داشت، لاگ خطا را بررسی کنید.</dd>
</dl>',
        ];
    }

    private function knowledgeCustomers(EducationFeature $feature): array
    {
        return [
            'excerpt' => 'راهنمای مدیریت مشتریان — ثبت، مشاهده تاریخچه خرید، سایت‌ها و پیگیری وضعیت هر مشتری.',
            'overview' => "بخش <strong>{$feature->feature_name}</strong> پروفایل کامل مشتریان را مدیریت می‌کند. هر مشتری می‌تواند چندین سایت، قراردادها و سابقه تعامل داشته باشد.",
            'use_cases' => '<ul>
<li>ثبت مشتری جدید و اطلاعات تماس</li>
<li>مشاهده سایت‌های متعلق به مشتری</li>
<li>پیگیری وضعیت قرارداد و لایسنس</li>
<li>یادداشت‌گذاری برای تیم پشتیبانی</li>
</ul>',
            'config_guide' => '<ol>
<li>روی «+ مشتری جدید» کلیک کنید.</li>
<li>نام، موبایل، شرکت و آدرس را وارد کنید.</li>
<li>سایت‌های مشتری را از بخش «سایت‌ها» اتصال دهید.</li>
</ol>',
            'field_reference' => '<table class="edu-field-table">
<thead><tr><th>فیلد</th><th>توضیح</th></tr></thead>
<tbody>
<tr><td>نام کامل / شرکت</td><td>نام نمایشی مشتری</td></tr>
<tr><td>موبایل</td><td>شماره اصلی تماس</td></tr>
<tr><td>نوع</td><td>حقیقی / حقوقی</td></tr>
<tr><td>یادداشت داخلی</td><td>اطلاعات حساس که فقط تیم ادمین می‌بیند</td></tr>
</tbody>
</table>',
            'best_practices' => '<ul>
<li>هر مشتری یک کد منحصربه‌فرد داشته باشد.</li>
<li>یادداشت‌های مهم درباره نیازهای خاص مشتری را ثبت کنید.</li>
</ul>',
            'security' => '<ul>
<li>اطلاعات مشتریان فقط برای ادمین‌های دارای مجوز <code>customers.view</code> قابل مشاهده است.</li>
</ul>',
            'faq' => '<dl>
<dt>آیا مشتری می‌تواند به پنل ادمین دسترسی داشته باشد؟</dt>
<dd>خیر، مشتریان پلتفرم از طریق پورتال مشتری (customer portal) دسترسی دارند، نه پنل ادمین.</dd>
</dl>',
            'mistakes' => '<ul>
<li>ثبت اطلاعات تکراری — قبل از ثبت جستجو کنید.</li>
</ul>',
            'related' => '<ul><li>سایت‌ها</li><li>تیکت پشتیبانی</li><li>گزارش‌گیری</li></ul>',
            'troubleshooting' => '<dl>
<dt>مشتری در لیست نمایش داده نمی‌شود</dt>
<dd>فیلتر «وضعیت» را از حالت «فعال» به «همه» تغییر دهید.</dd>
</dl>',
        ];
    }

    private function knowledgeResellers(EducationFeature $feature): array
    {
        return [
            'excerpt' => 'راهنمای مدیریت نمایندگان فروش — ثبت، تخصیص کمیسیون و پیگیری فروش هر نماینده.',
            'overview' => "بخش <strong>{$feature->feature_name}</strong> شبکه نمایندگان فروش را مدیریت می‌کند. نمایندگان می‌توانند مشتریان جدید جذب کنند و کمیسیون خود را دریافت کنند.",
            'use_cases' => '<ul>
<li>ثبت نماینده جدید با درصد کمیسیون</li>
<li>مشاهده فروش و کمیسیون هر نماینده</li>
<li>پردازش درخواست برداشت کمیسیون</li>
</ul>',
            'config_guide' => '<ol>
<li>روی «+ نماینده جدید» کلیک کنید.</li>
<li>اطلاعات تماس و درصد کمیسیون را وارد کنید.</li>
<li>لینک اختصاصی نماینده را به او بدهید.</li>
</ol>',
            'field_reference' => '<table class="edu-field-table">
<thead><tr><th>فیلد</th><th>توضیح</th></tr></thead>
<tbody>
<tr><td>نام نماینده</td><td>نام کامل یا نام شرکت</td></tr>
<tr><td>درصد کمیسیون</td><td>درصد از هر فروش (۰–۱۰۰)</td></tr>
<tr><td>کد معرف</td><td>کد منحصربه‌فرد برای ردیابی فروش</td></tr>
</tbody>
</table>',
            'best_practices' => '<ul>
<li>قراردادهای نمایندگی را در پیوست هر نماینده آپلود کنید.</li>
<li>کمیسیون‌ها را ماهانه محاسبه و تسویه کنید.</li>
</ul>',
            'security' => '<ul>
<li>نمایندگان فقط آمار فروش خودشان را می‌بینند.</li>
<li>دسترسی به اطلاعات مالی نیاز به مجوز <code>resellers.finance</code> دارد.</li>
</ul>',
            'faq' => '<dl>
<dt>آیا نماینده می‌تواند مشتری ایجاد کند؟</dt>
<dd>بله، از طریق پورتال نماینده با لینک اختصاصی خود.</dd>
</dl>',
            'mistakes' => '<ul>
<li>فراموشی تعریف سقف درصد کمیسیون برای جلوگیری از خطای محاسبه.</li>
</ul>',
            'related' => '<ul><li>مشتریان</li><li>گزارش مالی</li></ul>',
            'troubleshooting' => '<dl>
<dt>کمیسیون محاسبه نمی‌شود</dt>
<dd>مطمئن شوید که سفارش به کد معرف نماینده لینک شده است.</dd>
</dl>',
        ];
    }

    private function knowledgeStore(EducationFeature $feature): array
    {
        return [
            'excerpt' => 'راهنمای فروشگاه و مدیریت محصولات — ایجاد پلن‌ها، قیمت‌گذاری و تنظیمات درگاه پرداخت.',
            'overview' => "بخش <strong>{$feature->feature_name}</strong> مدیریت کامل محصولات قابل فروش پلتفرم را شامل می‌شود: پلن‌های اشتراک، لایسنس‌های ماژول و خدمات پشتیبانی.",
            'use_cases' => '<ul>
<li>تعریف پلن‌های اشتراک ماهانه/سالانه</li>
<li>مدیریت قیمت و تخفیف‌های فصلی</li>
<li>تنظیم درگاه پرداخت و روش‌های پرداخت</li>
</ul>',
            'config_guide' => '<ol>
<li>روی «+ محصول جدید» کلیک کنید.</li>
<li>نوع محصول (ماژول / پلن / خدمت) را انتخاب کنید.</li>
<li>قیمت، دوره و ویژگی‌ها را وارد کنید.</li>
<li>درگاه پرداخت را از لیست فعال‌ها انتخاب کنید.</li>
</ol>',
            'field_reference' => '<table class="edu-field-table">
<thead><tr><th>فیلد</th><th>توضیح</th></tr></thead>
<tbody>
<tr><td>نام محصول</td><td>عنوان نمایشی در صفحه فروش</td></tr>
<tr><td>قیمت</td><td>قیمت پایه به تومان</td></tr>
<tr><td>نوع</td><td>یکبار / ماهانه / سالانه</td></tr>
<tr><td>ماژول‌های شامل</td><td>لیست ماژول‌هایی که با این پلن فعال می‌شوند</td></tr>
</tbody>
</table>',
            'best_practices' => '<ul>
<li>همیشه پلن‌ها را با توضیح واضح درباره ماژول‌های شامل منتشر کنید.</li>
<li>تخفیف‌های سالانه ۲۰٪ نسبت به ماهانه ارائه دهید تا وفاداری بیشتر شود.</li>
</ul>',
            'security' => '<ul>
<li>اطلاعات پرداخت مستقیماً از طریق درگاه بانکی پردازش می‌شود.</li>
<li>هیچ اطلاعات کارت در سرور ذخیره نمی‌شود.</li>
</ul>',
            'faq' => '<dl>
<dt>آیا می‌توان کد تخفیف اضافه کرد؟</dt>
<dd>بله، از بخش «کوپن‌ها» در پنل فروش.</dd>
</dl>',
            'mistakes' => '<ul>
<li>فراموشی اضافه کردن ماژول‌ها به پلن پیش از فروش.</li>
</ul>',
            'related' => '<ul><li>درگاه پرداخت</li><li>سایت‌ها</li><li>مشتریان</li></ul>',
            'troubleshooting' => '<dl>
<dt>پرداخت موفق اما لایسنس فعال نشد</dt>
<dd>وضعیت وب‌هوک درگاه بانکی را بررسی کنید. لاگ پرداخت را در پنل بانک و پنل پلتفرم تطابق دهید.</dd>
</dl>',
        ];
    }

    private function knowledgeTheme(EducationFeature $feature): array
    {
        return [
            'excerpt' => 'راهنمای موتور تم و مدیریت ظاهر — انتخاب تم، شخصی‌سازی رنگ‌ها، فونت‌ها و پیش‌نمایش زنده.',
            'overview' => "بخش <strong>{$feature->feature_name}</strong> ظاهر کامل پنل و سایت‌های مشتری را کنترل می‌کند. سیستم تم شامل ۸ تم پیش‌ساخته، ویرایشگر توکن رنگ و پشتیبانی از RTL کامل است.",
            'use_cases' => '<ul>
<li>تطبیق رنگ سایت با هویت بصری برند مشتری</li>
<li>سفارشی‌سازی فونت برای پشتیبانی بهتر از زبان فارسی</li>
<li>ایجاد تم اختصاصی برای مشتریان Enterprise</li>
</ul>',
            'config_guide' => '<ol>
<li>از منوی «تم» گزینه مدیریت تم‌ها را انتخاب کنید.</li>
<li>روی «+ تم جدید» کلیک کنید یا از یک تم موجود فورک بزنید.</li>
<li>توکن‌های رنگ اصلی، ثانویه و accent را تنظیم کنید.</li>
<li>پیش‌نمایش زنده را در پنجره کناری بررسی کنید.</li>
<li>برای اعمال، تم را به سایت مورد نظر تخصیص دهید.</li>
</ol>',
            'field_reference' => '<table class="edu-field-table">
<thead><tr><th>توکن</th><th>توضیح</th></tr></thead>
<tbody>
<tr><td>primary</td><td>رنگ اصلی دکمه‌ها و لینک‌ها</td></tr>
<tr><td>secondary</td><td>رنگ ثانویه sidebar و header</td></tr>
<tr><td>accent</td><td>رنگ برجسته‌سازی آیتم‌های فعال</td></tr>
<tr><td>bg-base</td><td>رنگ پس‌زمینه اصلی صفحه</td></tr>
<tr><td>text-primary</td><td>رنگ متن اصلی</td></tr>
</tbody>
</table>',
            'best_practices' => '<ul>
<li>کنتراست رنگ متن با پس‌زمینه را برای دسترسی‌پذیری بررسی کنید (WCAG AA).</li>
<li>تم سفارشی را ابتدا روی یک سایت آزمایشی امتحان کنید.</li>
</ul>',
            'security' => '<ul>
<li>تغییر تم به ادمین‌های دارای مجوز <code>theme.manage</code> محدود است.</li>
</ul>',
            'faq' => '<dl>
<dt>آیا تم‌ها روی موبایل هم اعمال می‌شوند؟</dt>
<dd>بله، توکن‌های CSS به اپلیکیشن Flutter هم ارسال می‌شوند.</dd>
</dl>',
            'mistakes' => '<ul>
<li>استفاده از رنگ‌های با کنتراست پایین که خوانایی را کاهش می‌دهند.</li>
</ul>',
            'related' => '<ul><li>تنظیمات سایت</li><li>سایت‌ها</li></ul>',
            'troubleshooting' => '<dl>
<dt>تغییر رنگ اعمال نمی‌شود</dt>
<dd>کش مرورگر را پاک کنید. مطمئن شوید تم به سایت تخصیص یافته است.</dd>
</dl>',
        ];
    }

    private function knowledgeEducation(EducationFeature $feature): array
    {
        return [
            'excerpt' => 'راهنمای مرکز آموزش — مدیریت مقالات، راهنماهای گام‌به‌گام، مسیرهای یادگیری و یادداشت‌های انتشار.',
            'overview' => "بخش <strong>{$feature->feature_name}</strong> یک اکوسیستم آموزشی کامل را مدیریت می‌کند. این سیستم شامل مقالات راهنما، walkthroughهای تعاملی، مسیرهای یادگیری نقش‌محور و یادداشت‌های انتشار نسخه است.",
            'use_cases' => '<ul>
<li>تولید مستندات خودکار برای هر ویژگی</li>
<li>ساخت راهنمای گام‌به‌گام برای فرآیندهای پیچیده</li>
<li>تعریف مسیر یادگیری برای ادمین‌های جدید</li>
<li>اطلاع‌رسانی درباره ویژگی‌های جدید</li>
</ul>',
            'config_guide' => '<ol>
<li>از منوی آموزش، مقالات را انتخاب کنید.</li>
<li>برای تولید خودکار: دستور <code>education:sync-customer</code> را اجرا کنید.</li>
<li>مقالات پیش‌نویس را بررسی و منتشر کنید.</li>
</ol>',
            'field_reference' => '<table class="edu-field-table">
<thead><tr><th>فیلد</th><th>توضیح</th></tr></thead>
<tbody>
<tr><td>عنوان</td><td>عنوان فارسی مقاله</td></tr>
<tr><td>ماژول</td><td>ماژول مرتبط برای فیلتر و همگام‌سازی</td></tr>
<tr><td>نقش‌های هدف</td><td>مقاله برای چه نقش‌هایی نمایش داده شود</td></tr>
<tr><td>وضعیت</td><td>پیش‌نویس / منتشر‌شده / بایگانی</td></tr>
</tbody>
</table>',
            'best_practices' => '<ul>
<li>مقالات را پس از هر بروزرسانی ماژول بازبینی کنید.</li>
<li>راهنماهای walkthrough را برای فرآیندهای پرتکرار بسازید.</li>
</ul>',
            'security' => '<ul>
<li>مقالات بایگانی‌شده از دید کاربران پنهان می‌مانند.</li>
<li>ویرایش مقالات نیاز به مجوز <code>education.edit</code> دارد.</li>
</ul>',
            'faq' => '<dl>
<dt>آیا کاربران عادی می‌توانند مقالات را ببینند؟</dt>
<dd>بله، مقالات منتشر‌شده برای کاربران دارای دسترسی نمایش داده می‌شوند.</dd>
</dl>',
            'mistakes' => '<ul>
<li>رها کردن مقالات در وضعیت پیش‌نویس — حتماً پس از بررسی منتشر کنید.</li>
</ul>',
            'related' => '<ul><li>تمام ماژول‌های پلتفرم</li></ul>',
            'troubleshooting' => '<dl>
<dt>مقاله در جستجو ظاهر نمی‌شود</dt>
<dd>وضعیت مقاله باید «منتشر‌شده» باشد. ایندکس FULLTEXT را با <code>education:validate</code> بررسی کنید.</dd>
</dl>',
        ];
    }

    private function knowledgeModules(EducationFeature $feature): array
    {
        return [
            'excerpt' => 'راهنمای مدیریت ماژول‌ها و لایسنس‌ها — نصب، فعال‌سازی، بروزرسانی و مدیریت لایسنس ماژول‌ها.',
            'overview' => "بخش <strong>{$feature->feature_name}</strong> مدیریت چرخه کامل ماژول‌های سیستم را فراهم می‌کند: از خرید تا نصب، فعال‌سازی، بروزرسانی و غیرفعال‌سازی.",
            'use_cases' => '<ul>
<li>نصب ماژول جدید از فروشگاه پلتفرم</li>
<li>فعال‌سازی ماژول برای سایت‌های مشتری</li>
<li>مشاهده وضعیت لایسنس ماژول‌ها</li>
<li>بروزرسانی ماژول به نسخه جدید</li>
</ul>',
            'config_guide' => '<ol>
<li>از فروشگاه ماژول، ماژول مورد نظر را انتخاب کنید.</li>
<li>لایسنس خریداری و کلید لایسنس را وارد کنید.</li>
<li>نصب را تأیید کنید — سیستم به‌صورت خودکار migration اجرا می‌کند.</li>
<li>ماژول را از صفحه مدیریت ماژول‌ها فعال کنید.</li>
</ol>',
            'field_reference' => '<table class="edu-field-table">
<thead><tr><th>فیلد</th><th>توضیح</th></tr></thead>
<tbody>
<tr><td>اسلاگ ماژول</td><td>شناسه یکتای ماژول (مثال: forms, tickets)</td></tr>
<tr><td>نسخه</td><td>نسخه نصب‌شده فعلی</td></tr>
<tr><td>وضعیت لایسنس</td><td>فعال / منقضی / نامعتبر</td></tr>
</tbody>
</table>',
            'best_practices' => '<ul>
<li>پیش از بروزرسانی ماژول، از دیتابیس پشتیبان‌گیری کنید.</li>
<li>بروزرسانی‌ها را ابتدا روی محیط staging آزمایش کنید.</li>
</ul>',
            'security' => '<ul>
<li>ماژول‌ها از منبع رسمی پلتفرم نصب می‌شوند — نصب ماژول third-party پشتیبانی نمی‌شود.</li>
<li>کلید لایسنس رمزگذاری‌شده ذخیره می‌شود.</li>
</ul>',
            'faq' => '<dl>
<dt>آیا حذف ماژول داده‌ها را پاک می‌کند؟</dt>
<dd>خیر، داده‌ها در جداول باقی می‌مانند. برای پاک‌سازی کامل، rollback migration را اجرا کنید.</dd>
</dl>',
            'mistakes' => '<ul>
<li>بروزرسانی بدون پشتیبان‌گیری.</li>
<li>فعال کردن ماژول برای سایت‌هایی که لایسنس ندارند.</li>
</ul>',
            'related' => '<ul><li>سایت‌ها</li><li>فروشگاه ماژول</li><li>لاگ فعالیت</li></ul>',
            'troubleshooting' => '<dl>
<dt>ماژول پس از نصب نمایش داده نمی‌شود</dt>
<dd>دستور <code>php artisan package:discover</code> و <code>php artisan config:clear</code> را اجرا کنید.</dd>
</dl>',
        ];
    }

    private function knowledgeAccess(EducationFeature $feature): array
    {
        return [
            'excerpt' => 'راهنمای مدیریت دسترسی — تعریف نقش‌ها، مجوزها و کنترل سطوح دسترسی کاربران پنل.',
            'overview' => "بخش <strong>{$feature->feature_name}</strong> سیستم کنترل دسترسی نقش‌محور (RBAC) پلتفرم را مدیریت می‌کند. می‌توانید نقش‌های جدید تعریف کنید، مجوزها را گروه‌بندی کنید و به هر کاربر یک نقش تخصیص دهید.",
            'use_cases' => '<ul>
<li>تعریف نقش «اپراتور پشتیبانی» با دسترسی محدود به تیکت‌ها</li>
<li>تعریف نقش «ادمین محتوا» فقط با دسترسی به فرم‌ها و صفحات</li>
<li>ایجاد نقش «مشاهده‌گر» بدون توانایی ویرایش</li>
</ul>',
            'config_guide' => '<ol>
<li>از منوی دسترسی، نقش‌ها را انتخاب کنید.</li>
<li>روی «+ نقش جدید» کلیک کنید.</li>
<li>مجوزها را از لیست تیک بزنید.</li>
<li>نقش را به کاربران مورد نظر تخصیص دهید.</li>
</ol>',
            'field_reference' => '<table class="edu-field-table">
<thead><tr><th>فیلد</th><th>توضیح</th></tr></thead>
<tbody>
<tr><td>نام نقش</td><td>عنوان نمایشی (مثال: اپراتور پشتیبانی)</td></tr>
<tr><td>اسلاگ</td><td>شناسه کدی (مثال: support_operator)</td></tr>
<tr><td>مجوزها</td><td>لیست عملیات مجاز برای این نقش</td></tr>
</tbody>
</table>',
            'best_practices' => '<ul>
<li>نقش‌ها را بر اساس وظیفه‌محوری (نه شخص‌محوری) تعریف کنید.</li>
<li>مجوزهای حساس (حذف، تنظیمات) را فقط به ادمین اصلی بدهید.</li>
</ul>',
            'security' => '<ul>
<li>تمام تغییرات نقش‌ها در لاگ ثبت می‌شود.</li>
<li>حذف نقشی که کاربر دارد، آن کاربر را به «بدون دسترسی» تغییر می‌دهد.</li>
</ul>',
            'faq' => '<dl>
<dt>آیا می‌توان مجوزهای سفارشی تعریف کرد؟</dt>
<dd>بله، از طریق رجیستر کردن permission جدید در کد.</dd>
</dl>',
            'mistakes' => '<ul>
<li>دادن مجوز «super-admin» به کاربران عادی.</li>
</ul>',
            'related' => '<ul><li>کاربران</li><li>لاگ فعالیت</li></ul>',
            'troubleshooting' => '<dl>
<dt>کاربر پس از تغییر نقش همچنان به بخش قدیمی دسترسی دارد</dt>
<dd>از session پاک‌سازی کنید: <code>php artisan auth:clear-resets</code>. از کاربر بخواهید logout و login کند.</dd>
</dl>',
        ];
    }

    private function knowledgeSettings(EducationFeature $feature): array
    {
        return [
            'excerpt' => 'راهنمای تنظیمات پلتفرم — پیکربندی عمومی، اعلان‌ها، دامنه، امنیت و یکپارچه‌سازی‌ها.',
            'overview' => "بخش <strong>{$feature->feature_name}</strong> تمام تنظیمات سیستمی پلتفرم را در یک مکان متمرکز کرده است. از جزئیات سازمانی تا تنظیمات SMTP، کپچا، و یکپارچه‌سازی‌های خارجی.",
            'use_cases' => '<ul>
<li>تنظیم اطلاعات تماس و برند پلتفرم</li>
<li>پیکربندی سرور ایمیل (SMTP)</li>
<li>فعال/غیرفعال‌سازی کپچا</li>
<li>تنظیم URL های مهم مثل آدرس پلتفرم</li>
</ul>',
            'config_guide' => '<ol>
<li>از منوی تنظیمات سیستم را انتخاب کنید.</li>
<li>هر بخش را جداگانه ویرایش و ذخیره کنید.</li>
<li>پس از ذخیره تنظیمات مهم، کش را پاک کنید.</li>
</ol>',
            'field_reference' => '<table class="edu-field-table">
<thead><tr><th>تنظیم</th><th>توضیح</th></tr></thead>
<tbody>
<tr><td>نام پلتفرم</td><td>عنوان نمایشی در پنل و ایمیل‌ها</td></tr>
<tr><td>آدرس پلتفرم</td><td>URL اصلی برای لینک‌سازی</td></tr>
<tr><td>ایمیل پشتیبانی</td><td>آدرس دریافت خطاها و اعلان‌ها</td></tr>
<tr><td>کلید API SMTP</td><td>اطلاعات سرور ارسال ایمیل</td></tr>
</tbody>
</table>',
            'best_practices' => '<ul>
<li>هرگز کلیدهای API را در تنظیمات مرئی ننویسید؛ از .env استفاده کنید.</li>
<li>پس از تغییر تنظیمات SMTP، یک ایمیل آزمایشی ارسال کنید.</li>
</ul>',
            'security' => '<ul>
<li>دسترسی به تنظیمات نیاز به مجوز <code>settings.manage</code> دارد.</li>
<li>تغییرات حساس در لاگ فعالیت ثبت می‌شوند.</li>
</ul>',
            'faq' => '<dl>
<dt>تنظیمات در .env یا پنل؟</dt>
<dd>تنظیمات زیرساختی (دیتابیس، Redis) در .env؛ تنظیمات کسب‌وکاری (برند، SMTP) در پنل.</dd>
</dl>',
            'mistakes' => '<ul>
<li>تغییر تنظیمات در production بدون بررسی اثر آن‌ها.</li>
</ul>',
            'related' => '<ul><li>امنیت</li><li>کپچا</li><li>لاگ فعالیت</li></ul>',
            'troubleshooting' => '<dl>
<dt>تنظیمات ذخیره‌شده اعمال نمی‌شوند</dt>
<dd>دستور <code>php artisan config:clear && php artisan cache:clear</code> را اجرا کنید.</dd>
</dl>',
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Generic fallback content builders
    // ──────────────────────────────────────────────────────────────────────────

    private function defaultUseCases(EducationFeature $feature): string
    {
        $name = e($feature->feature_name);
        return "<ul>
<li>مشاهده و مرور لیست {$name}</li>
<li>ایجاد رکورد جدید با اطلاعات کامل</li>
<li>ویرایش و بروزرسانی اطلاعات موجود</li>
<li>جستجو و فیلتر بر اساس معیارهای مختلف</li>
<li>صادرکردن گزارش به فرمت CSV/Excel</li>
</ul>";
    }

    private function defaultWhenToUse(EducationFeature $feature): string
    {
        $name = e($feature->feature_name);
        return "<p>از بخش <strong>{$name}</strong> زمانی استفاده کنید که:</p>
<ul>
<li>نیاز به مدیریت مستقیم داده‌های مرتبط با این بخش دارید</li>
<li>می‌خواهید اطلاعات را بررسی، بروزرسانی یا حذف کنید</li>
<li>نیاز به گزارش یا خروجی از این داده‌ها دارید</li>
</ul>";
    }

    private function defaultConfigGuide(EducationFeature $feature): string
    {
        $route = e($feature->route_name ?? '—');
        return "<ol>
<li>از منوی ادمین به بخش مربوطه بروید (مسیر: <code>{$route}</code>).</li>
<li>برای ایجاد رکورد جدید روی دکمه «+ افزودن» کلیک کنید.</li>
<li>فرم را با اطلاعات کامل پر کنید.</li>
<li>روی دکمه «ذخیره» کلیک کنید.</li>
<li>برای ویرایش، روی آیکن مداد کنار رکورد کلیک کنید.</li>
<li>برای حذف، از آیکن سطل آشغال استفاده کنید (این عمل برگشت‌ناپذیر است).</li>
</ol>";
    }

    private function defaultFieldReference(EducationFeature $feature): string
    {
        $name = e($feature->feature_name);
        return "<p>فیلدهای این بخش بسته به نوع رکورد متفاوت هستند. فیلدهای ستاره‌دار (<span class='text-red-500'>*</span>) اجباری هستند.</p>
<p>برای توضیح هر فیلد، نشانگر ماوس را روی آیکن سوال (?) کنار فیلد نگه دارید.</p>";
    }

    private function defaultBestPractices(EducationFeature $feature): string
    {
        return '<ul>
<li>پیش از حذف رکوردها، مطمئن شوید به سایر بخش‌های سیستم وابستگی ندارند.</li>
<li>تغییرات مهم را در ساعات کم‌ترافیک انجام دهید.</li>
<li>از ابزار جستجو برای یافتن رکوردها قبل از ایجاد تکراری استفاده کنید.</li>
<li>فیلدهای متنی را با داده‌های واقعی و کامل پر کنید.</li>
</ul>';
    }

    private function defaultMistakes(EducationFeature $feature): string
    {
        return '<ul>
<li><strong>حذف بدون بررسی:</strong> قبل از حذف هر رکورد، وابستگی‌های آن را بررسی کنید.</li>
<li><strong>داده‌های ناقص:</strong> فیلدهای اجباری را کامل پر کنید تا خطاهای بعدی کاهش یابد.</li>
<li><strong>عدم بروزرسانی:</strong> اطلاعات منقضی را به‌موقع بروزرسانی کنید.</li>
</ul>';
    }

    private function defaultSecurity(EducationFeature $feature): string
    {
        $mod = e($feature->module_slug);
        return "<ul>
<li>دسترسی به این بخش از طریق سیستم RBAC کنترل می‌شود.</li>
<li>تمام تغییرات در لاگ فعالیت ثبت می‌شوند.</li>
<li>برای دسترسی، نقش مناسب باید به کاربر تخصیص یافته باشد (مجوز <code>{$mod}.*</code>).</li>
</ul>";
    }

    private function defaultFaq(EducationFeature $feature): string
    {
        $name = e($feature->feature_name);
        return "<dl>
<dt>آیا رکوردهای حذف‌شده قابل بازیابی هستند؟</dt>
<dd>بستگی به تنظیمات سیستم دارد. در صورت فعال بودن Soft Delete، رکورد به مدت ۳۰ روز قابل بازیابی است.</dd>
<dt>آیا می‌توان چند رکورد را همزمان حذف کرد؟</dt>
<dd>بله، با تیک‌زدن رکوردها و استفاده از عملیات گروهی.</dd>
<dt>چگونه خروجی Excel بگیرم؟</dt>
<dd>از دکمه «صادرکردن» در بالای لیست استفاده کنید و فرمت Excel را انتخاب کنید.</dd>
</dl>";
    }

    private function defaultRelated(EducationFeature $feature): string
    {
        $mod = e($feature->module_slug);
        return "<p>این بخش با سایر ماژول‌های پلتفرم یکپارچه است. برای اطلاعات بیشتر، مستندات ماژول <code>{$mod}</code> را مطالعه کنید.</p>";
    }

    private function defaultTroubleshooting(EducationFeature $feature): string
    {
        return '<dl>
<dt>صفحه خطای ۴۰۳ (دسترسی ممنوع)</dt>
<dd>مطمئن شوید نقش کاربر شما مجوز مشاهده این بخش را دارد. با ادمین اصلی تماس بگیرید.</dd>
<dt>داده‌ها نمایش داده نمی‌شوند</dt>
<dd>فیلترهای فعال را پاک کنید. اگر مشکل ادامه داشت، صفحه را reload کنید.</dd>
<dt>خطای ذخیره‌سازی</dt>
<dd>پیام خطا را بخوانید — معمولاً یک فیلد اجباری خالی است. اگر خطای سرور دریافت کردید، لاگ‌ها را بررسی کنید.</dd>
</dl>';
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helper
    // ──────────────────────────────────────────────────────────────────────────

    private function hasDocumentation(EducationFeature $feature): bool
    {
        $siteScope = fn($q) => $q->where(function ($inner) use ($feature) {
            $inner->whereNull('site_id')->orWhere('site_id', $feature->site_id);
        });

        // A feature is considered documented if any article (manual OR auto-generated) exists
        // scoped to the same site as this feature record.
        return EducationArticle::where('module_slug', $feature->module_slug)
            ->where($siteScope)
            ->exists()
            ||
            (! empty($feature->route_name) && EducationArticle::where('route_pattern', $feature->route_name)
            ->where($siteScope)
            ->exists());
    }
}
