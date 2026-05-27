<?php

namespace SaaSPlatform\Modules\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FormAutomation extends Model
{
    use SoftDeletes;

    protected $table = 'form_automations';

    protected $fillable = [
        'uuid',
        'form_id',
        'name',
        'type',
        'config',
        'conditions',
        'is_active',
    ];

    protected $casts = [
        'config'     => 'array',
        'conditions' => 'array',
        'is_active'  => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'email'        => 'ایمیل',
            'webhook'      => 'Webhook',
            'slack'        => 'Slack',
            'telegram'     => 'Telegram',
            'autoresponder' => 'پاسخ خودکار',
            default        => $type,
        };
    }

    public static function typeIcon(string $type): string
    {
        return match ($type) {
            'email'        => '✉️',
            'webhook'      => '🔗',
            'slack'        => '💬',
            'telegram'     => '📨',
            'autoresponder' => '↩️',
            default        => '⚙️',
        };
    }
}
