<?php

namespace SaaSPlatform\Modules\Education\Services;

use Illuminate\Support\Str;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationFeatureRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\DocumentationGeneratorServiceInterface;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationFeature;

class DocumentationGeneratorService implements DocumentationGeneratorServiceInterface
{
    public function __construct(
        private readonly EducationFeatureRepositoryInterface $features,
    ) {}

    public function generateMissing(?int $siteId = null): int
    {
        $created = 0;

        foreach ($this->features->all($siteId) as $feature) {
            if ($this->hasDocumentation($feature)) {
                continue;
            }

            $article = $this->generateForFeature($feature);
            if ($article) {
                $created++;
            }
        }

        return $created;
    }

    public function generateForFeature(EducationFeature $feature): ?EducationArticle
    {
        if ($this->hasDocumentation($feature)) {
            return null;
        }

        return EducationArticle::create([
            'edu_category_id' => null,
            'author_id'       => null,
            'site_id'         => $feature->site_id,
            'title'           => $this->buildTitle($feature),
            'slug'            => Str::slug($this->buildTitle($feature)) . '-' . Str::random(4),
            'excerpt'         => $this->buildExcerpt($feature),
            'body'            => $this->buildBody($feature),
            'status'          => EducationArticle::STATUS_DRAFT,
            'content_type'    => 'reference',
            'module_slug'     => $feature->module_slug,
            'route_pattern'   => $feature->route_name,
            'auto_generated'  => true,
            'published_at'    => null,
        ]);
    }

    public function regenerateForFeature(EducationFeature $feature): EducationArticle
    {
        $existing = EducationArticle::where('module_slug', $feature->module_slug)
            ->where('route_pattern', $feature->route_name)
            ->where('auto_generated', true)
            ->first();

        $data = [
            'title'   => $this->buildTitle($feature),
            'excerpt' => $this->buildExcerpt($feature),
            'body'    => $this->buildBody($feature),
        ];

        if ($existing) {
            $existing->update($data);
            return $existing->fresh();
        }

        return $this->generateForFeature($feature) ?? EducationArticle::where('module_slug', $feature->module_slug)
            ->where('auto_generated', true)->first();
    }

    private function hasDocumentation(EducationFeature $feature): bool
    {
        return EducationArticle::where('module_slug', $feature->module_slug)
            ->where('auto_generated', false)
            ->exists()
            ||
            EducationArticle::where('route_pattern', $feature->route_name)
            ->where('auto_generated', false)
            ->exists();
    }

    private function buildTitle(EducationFeature $feature): string
    {
        return "راهنمای {$feature->feature_name}";
    }

    private function buildExcerpt(EducationFeature $feature): string
    {
        return "مستندات خودکار برای ماژول {$feature->module_slug} — بخش {$feature->feature_name}. این راهنما به‌صورت خودکار تولید شده است و می‌توان آن را ویرایش کرد.";
    }

    private function buildBody(EducationFeature $feature): string
    {
        $name   = e($feature->feature_name);
        $module = e($feature->module_slug);
        $route  = e($feature->route_name ?? '—');
        $loc    = e($feature->menu_location ?? '—');

        $useCases = $this->inferUseCases($feature);
        $ucHtml   = implode('', array_map(fn($uc) => "<li>{$uc}</li>", $useCases));

        return <<<HTML
<h2>بررسی اجمالی</h2>
<p>این صفحه مربوط به <strong>{$name}</strong> در ماژول <code>{$module}</code> است.</p>

<h2>هدف</h2>
<p>این بخش به مدیران پلتفرم امکان مدیریت <strong>{$name}</strong> را فراهم می‌کند.</p>

<h2>کاربردهای رایج</h2>
<ul>
{$ucHtml}
</ul>

<h2>اطلاعات پیکربندی</h2>
<ul>
    <li><strong>ماژول:</strong> <code>{$module}</code></li>
    <li><strong>مسیر روت:</strong> <code>{$route}</code></li>
    <li><strong>محل منو:</strong> {$loc}</li>
</ul>

<h2>توجه</h2>
<p class="text-amber-700 bg-amber-50 p-3 rounded">این محتوا به‌صورت خودکار تولید شده است. برای به‌روزرسانی با محتوای دقیق‌تر، این مقاله را ویرایش کنید.</p>
HTML;
    }

    private function inferUseCases(EducationFeature $feature): array
    {
        $slug = $feature->feature_slug;
        $name = $feature->feature_name;

        $defaults = [
            "مشاهده و مدیریت {$name}",
            "ایجاد رکوردهای جدید در {$name}",
            "ویرایش و بروزرسانی اطلاعات",
            "حذف رکوردهای غیرضروری",
        ];

        // Module-aware enrichment
        if (str_contains($slug, 'user')) {
            return ["افزودن کاربر ادمین جدید", "تغییر نقش کاربر", "غیرفعال کردن دسترسی", "مشاهده لاگ ورود"];
        }
        if (str_contains($slug, 'site')) {
            return ["ایجاد سایت زیرمجموعه", "تخصیص ماژول به سایت", "مدیریت لایسنس سایت", "تنظیم دامنه"];
        }
        if (str_contains($slug, 'ticket') || str_contains($slug, 'support')) {
            return ["مشاهده تیکت‌های دریافتی", "پاسخ به تیکت", "تغییر وضعیت تیکت", "تخصیص تیکت به اپراتور"];
        }
        if (str_contains($slug, 'product') || str_contains($slug, 'store')) {
            return ["افزودن محصول جدید", "مدیریت قیمت و موجودی", "تنظیم نوع محصول (ماژول/پلن/لایسنس)"];
        }
        if (str_contains($slug, 'customer')) {
            return ["افزودن مشتری جدید", "مدیریت سایت‌های مشتری", "مشاهده سوابق خرید"];
        }

        return $defaults;
    }
}
