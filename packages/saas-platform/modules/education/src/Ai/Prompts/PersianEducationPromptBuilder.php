<?php

namespace SaaSPlatform\Modules\Education\Ai\Prompts;

use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;

/**
 * Builds Persian-language prompts for the Anthropic Messages API.
 *
 * Returns an array with two keys consumed by ClaudeAiContentProvider:
 *   'system' — system prompt (persona + language + output rules)
 *   'user'   — user prompt  (content-type-specific instructions)
 *
 * Each content type gets a fully differentiated prompt structure:
 *   articles      — comprehensive knowledge article
 *   walkthroughs  — numbered step-by-step guide
 *   learning_path — structured three-level curriculum
 *   remediation   — targeted knowledge-gap filler
 */
class PersianEducationPromptBuilder
{
    private const MODULE_LABELS = [
        'invoices'      => 'فاکتورها',
        'payments'      => 'پرداخت‌ها',
        'tickets'       => 'تیکت‌های پشتیبانی',
        'customers'     => 'مشتریان',
        'products'      => 'محصولات',
        'reports'       => 'گزارش‌ها',
        'settings'      => 'تنظیمات',
        'users'         => 'کاربران',
        'forms'         => 'فرم‌ها',
        'dashboard'     => 'داشبورد',
        'subscriptions' => 'اشتراک‌ها',
        'messaging'     => 'پیام‌رسانی',
        'education'     => 'آموزش',
        'analytics'     => 'آمار و تحلیل',
        'notifications' => 'اعلان‌ها',
    ];

    /**
     * Build system + user prompt pair for the given generation job.
     *
     * @return array{system: string, user: string}
     */
    public function build(ContentGenerationJobDto $job): array
    {
        return match ($job->contentType) {
            'walkthroughs'  => $this->buildWalkthrough($job),
            'learning_path' => $this->buildLearningPath($job),
            'remediation'   => $this->buildRemediation($job),
            default         => $this->buildArticle($job),
        };
    }

    // -------------------------------------------------------------------------
    // Content-type builders
    // -------------------------------------------------------------------------

    private function buildArticle(ContentGenerationJobDto $job): array
    {
        $module = $this->moduleLabel($job->moduleSlug);

        return [
            'system' => $this->systemPrompt(),
            'user'   => <<<TEXT
یک مقاله آموزشی جامع برای بخش «{$module}» پلتفرم SaaS بنویسید.

هدف: آموزش کامل استفاده از این بخش به اپراتورها و کاربران تجاری.
اولویت محتوا: {$job->priority}

ساختار پیشنهادی:
- مقدمه و معرفی کلی بخش
- ویژگی‌ها و قابلیت‌های اصلی
- راهنمای گام‌به‌گام استفاده
- نکات مهم، محدودیت‌ها و هشدارها

{$this->jsonInstructions()}
TEXT,
        ];
    }

    private function buildWalkthrough(ContentGenerationJobDto $job): array
    {
        $module = $this->moduleLabel($job->moduleSlug);

        return [
            'system' => $this->systemPrompt(),
            'user'   => <<<TEXT
یک راهنمای گام‌به‌گام عملی برای انجام یک وظیفه در بخش «{$module}» پلتفرم SaaS بنویسید.

هدف: کاربر باید بتواند دقیقاً دستورالعمل را دنبال کرده و وظیفه را تکمیل کند.
اولویت محتوا: {$job->priority}

ساختار اجباری:
- هر گام با تگ <h2> شماره‌گذاری شود — مثال: <h2>گام ۱: ورود به بخش</h2>
- هر گام شامل یک عمل مشخص و قابل اجرا باشد
- از <ul> برای نکات فرعی و توضیحات اضافی هر گام استفاده شود
- با یک نتیجه‌گیری کوتاه پایان یابد

{$this->jsonInstructions()}
TEXT,
        ];
    }

    private function buildLearningPath(ContentGenerationJobDto $job): array
    {
        $module = $this->moduleLabel($job->moduleSlug);

        return [
            'system' => $this->systemPrompt(),
            'user'   => <<<TEXT
یک مسیر یادگیری ساختارمند برای تسلط بر بخش «{$module}» پلتفرم SaaS طراحی کنید.

هدف: کاربر جدید باید بداند از کجا شروع کند، چه مراحلی را طی کند، و چگونه پیشرفت کند.
اولویت محتوا: {$job->priority}

ساختار اجباری (سه سطح):
۱. سطح مقدماتی — مفاهیم پایه و آشنایی با رابط کاربری
۲. سطح میانی — عملکردهای اصلی و سناریوهای رایج
۳. سطح پیشرفته — ویژگی‌های تخصصی و بهینه‌سازی

هر سطح باید شامل موضوعات، زمان تخمینی و پیش‌نیازها باشد.

{$this->jsonInstructions()}
TEXT,
        ];
    }

    private function buildRemediation(ContentGenerationJobDto $job): array
    {
        $module   = $this->moduleLabel($job->moduleSlug);
        $gapScore = $job->metadata['gap_score'] ?? null;
        $gapHint  = $gapScore !== null
            ? "\nسطح شکاف دانشی: {$gapScore}٪ — محتوا باید متناسب با این سطح باشد."
            : '';

        return [
            'system' => $this->systemPrompt(),
            'user'   => <<<TEXT
یک محتوای هدفمند برای رفع شکاف دانشی در بخش «{$module}» پلتفرم SaaS بنویسید.
{$gapHint}

هدف: کاربر یک مفهوم یا قابلیت خاص را به درستی درک نکرده — این محتوا باید آن را به سادگی و با مثال رفع کند.
اولویت محتوا: {$job->priority}

ساختار اجباری:
- چه مشکلی وجود دارد؟ (توضیح اشتباه رایج)
- توضیح صحیح به زبان ساده
- مثال عملی از یک سناریوی واقعی در پلتفرم
- سوالات متداول (FAQ) با پاسخ‌های کوتاه

{$this->jsonInstructions()}
TEXT,
        ];
    }

    // -------------------------------------------------------------------------
    // Shared prompt components
    // -------------------------------------------------------------------------

    private function systemPrompt(): string
    {
        return <<<'SYSTEM'
شما یک نویسنده فنی حرفه‌ای فارسی‌زبان برای یک پلتفرم نرم‌افزار SaaS در ایران هستید.

وظیفه شما: تولید محتوای آموزشی با کیفیت بالا برای اپراتورها و کاربران نهایی پلتفرم.

قوانین ثابت:
- تمام متن باید به زبان فارسی (فارسی معیار ایران) باشد.
- لحن: حرفه‌ای، واضح، و قابل فهم برای کاربران تجاری.
- ساختار HTML باید برای نمایش راست‌به‌چپ (RTL) مناسب باشد.
- از اصطلاحات فنی رایج فارسی استفاده کنید.
- پاسخ را فقط به صورت یک JSON object معتبر برگردانید.
- هیچ متنی خارج از JSON object نباشد — بدون توضیح، بدون کد fence.
SYSTEM;
    }

    private function jsonInstructions(): string
    {
        return <<<'SCHEMA'
خروجی باید دقیقاً این JSON object باشد:
{
  "title":            "عنوان فارسی (۱۰ تا ۱۲۰ کاراکتر)",
  "summary":          "خلاصه ۲ تا ۳ جمله‌ای به فارسی",
  "body":             "متن کامل به HTML فارسی — تگ‌های <h2>، <p>، <ul>، <li> — حداقل ۳۰۰ کلمه — بدون تگ خالی",
  "tags":             ["تگ فارسی ۱", "تگ فارسی ۲", "تگ فارسی ۳"],
  "confidence_score": 85
}

قوانین خروجی:
- تمام مقادیر متنی به فارسی.
- confidence_score: عدد صحیح بین ۰ تا ۱۰۰.
- body: HTML معتبر، حداقل ۳۰۰ کلمه فارسی، بدون تگ‌های خالی.
- فقط JSON object — بدون متن اضافه، بدون markdown fence.
SCHEMA;
    }

    private function moduleLabel(string $slug): string
    {
        return self::MODULE_LABELS[$slug] ?? $slug;
    }
}
