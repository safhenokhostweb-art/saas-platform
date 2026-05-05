<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * قالب اعلان خوش‌آمدگویی — برای ارسال خودکار به سایت‌های جدید.
 */
class PlatformNotificationTemplate extends Model
{
    protected $fillable = [
        'title', 'body', 'type', 'link', 'link_text', 'image_path',
        'position', 'image_position', 'audience', 'order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
