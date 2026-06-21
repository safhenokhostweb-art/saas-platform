<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReverseEngineeringJob extends Model
{
    public const INPUT_URL        = 'url';
    public const INPUT_SCREENSHOT = 'screenshot';
    public const INPUT_HTML       = 'html';
    public const INPUT_PROMPT     = 'prompt';

    public const STATUS_PENDING        = 'pending';
    public const STATUS_EXTRACTING     = 'extracting';
    public const STATUS_ANALYZING      = 'analyzing';
    public const STATUS_SCORING        = 'scoring';
    public const STATUS_RECONSTRUCTING = 'reconstructing';
    public const STATUS_VALIDATING     = 'validating';
    public const STATUS_COMPLETE       = 'complete';
    public const STATUS_FAILED         = 'failed';

    protected $table = 'reverse_engineering_jobs';

    public const EXTRACTION_HTTP     = 'http';
    public const EXTRACTION_HEADLESS = 'headless';

    protected $fillable = [
        'uuid', 'input_type', 'input_source', 'input_notes', 'suggested_name',
        'dom_structure', 'headless_data', 'layout_map', 'visual_profile', 'commerce_profile',
        'similarity_scores', 'theme_pack_id', 'quality_report',
        'status', 'extraction_mode', 'force_headless', 'error_message', 'meta',
    ];

    protected $casts = [
        'dom_structure'    => 'array',
        'headless_data'    => 'array',
        'layout_map'       => 'array',
        'visual_profile'   => 'array',
        'commerce_profile' => 'array',
        'similarity_scores'=> 'array',
        'quality_report'   => 'array',
        'meta'             => 'array',
        'force_headless'   => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (empty($m->uuid)) {
                $m->uuid = (string) Str::uuid();
            }
        });
    }

    public function themePack()
    {
        return $this->belongsTo(ThemePack::class, 'theme_pack_id');
    }

    public function isComplete(): bool
    {
        return $this->status === self::STATUS_COMPLETE;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function overallScore(): int
    {
        $scores = $this->similarity_scores ?? [];
        return (int) ($scores['overall'] ?? 0);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING        => 'در انتظار',
            self::STATUS_EXTRACTING     => 'در حال استخراج DOM',
            self::STATUS_ANALYZING      => 'در حال تحلیل بصری',
            self::STATUS_SCORING        => 'در حال محاسبه امتیاز',
            self::STATUS_RECONSTRUCTING => 'در حال بازسازی قالب',
            self::STATUS_VALIDATING     => 'در حال اعتبارسنجی',
            self::STATUS_COMPLETE       => 'تکمیل',
            self::STATUS_FAILED         => 'ناموفق',
            default                     => $this->status,
        };
    }

    public function inputTypeLabel(): string
    {
        return match ($this->input_type) {
            self::INPUT_URL        => 'آدرس سایت',
            self::INPUT_SCREENSHOT => 'تصویر',
            self::INPUT_HTML       => 'کد HTML',
            self::INPUT_PROMPT     => 'توضیح متنی',
            default                => $this->input_type,
        };
    }

    public function qualityPassed(): bool
    {
        $report = $this->quality_report ?? [];
        return ($report['verdict'] ?? '') === 'PASS';
    }

    public function isHeadless(): bool
    {
        return $this->extraction_mode === self::EXTRACTION_HEADLESS;
    }

    public function extractionModeLabel(): string
    {
        return $this->isHeadless() ? 'Headless (Playwright)' : 'HTTP';
    }

    public function extractionModeBadge(): string
    {
        return $this->isHeadless()
            ? 'از Playwright (رندر واقعی)'
            : 'از HTTP (static HTML)';
    }
}
