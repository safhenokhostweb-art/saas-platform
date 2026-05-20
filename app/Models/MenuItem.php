<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = [
        'menu_id', 'parent_id', 'title', 'url', 'type', 'target',
        'icon', 'css_class', 'role_visibility', 'order', 'is_active',
    ];

    protected $casts = [
        'role_visibility' => 'array',
        'order' => 'integer',
        'is_active' => 'boolean',
    ];

    public const TYPE_LINK = 'link';
    public const TYPE_PAGE = 'page';
    public const TYPE_POST = 'post';
    public const TYPE_PRODUCT = 'product';
    public const TYPE_CATEGORY = 'category';
    public const TYPE_CUSTOM = 'custom';

    public static function types(): array
    {
        return [
            self::TYPE_LINK => 'لینک سفارشی',
            self::TYPE_PAGE => 'صفحه',
            self::TYPE_POST => 'مطلب',
            self::TYPE_PRODUCT => 'محصول',
            self::TYPE_CATEGORY => 'دسته‌بندی',
            self::TYPE_CUSTOM => 'سفارشی',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('order');
    }
}
