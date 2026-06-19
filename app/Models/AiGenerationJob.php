<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AiGenerationJob extends Model
{
    public const INPUT_URL        = 'url';
    public const INPUT_SCREENSHOT = 'screenshot';
    public const INPUT_HTML       = 'html';
    public const INPUT_PROMPT     = 'prompt';

    public const STATUS_PENDING           = 'pending';
    public const STATUS_DETECTING         = 'detecting';
    public const STATUS_AWAITING          = 'awaiting_selection';
    public const STATUS_GENERATING        = 'generating';
    public const STATUS_COMPLETE          = 'complete';
    public const STATUS_FAILED            = 'failed';

    protected $fillable = [
        'uuid', 'input_type', 'input_source', 'input_notes', 'suggested_name',
        'page_signals', 'detected_sections', 'selected_sections',
        'theme_pack_id', 'status', 'error_message', 'meta',
    ];

    protected $casts = [
        'page_signals'       => 'array',
        'detected_sections'  => 'array',
        'selected_sections'  => 'array',
        'meta'               => 'array',
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

    public function isComplete(): bool
    {
        return $this->status === self::STATUS_COMPLETE;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function enabledSections(): array
    {
        $sections = $this->selected_sections ?? $this->detected_sections ?? [];
        return array_filter($sections, fn ($s) => ($s['enabled'] ?? false));
    }
}
