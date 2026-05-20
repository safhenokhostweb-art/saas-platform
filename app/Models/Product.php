<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'sku',
        'name_fa',
        'name_en',
        'type',
        'price_irr',
        'is_one_time',
        'module_id',
        'support_plan_id',
        'is_active',
        'description',
    ];

    protected $casts = [
        'price_irr' => 'integer',
        'is_one_time' => 'boolean',
        'is_active' => 'boolean',
    ];

    public const TYPE_LICENSE_TEMPLATE = 'license_template';
    public const TYPE_MODULE_ADDON = 'module_addon';
    public const TYPE_SUPPORT_PLAN = 'support_plan';

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function supportPlan()
    {
        return $this->belongsTo(SupportPlan::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_LICENSE_TEMPLATE => 'لایسنس قالب سایت',
            self::TYPE_MODULE_ADDON => 'ماژول افزودنی',
            self::TYPE_SUPPORT_PLAN => 'پلن پشتیبانی',
            default => $this->type,
        };
    }
}
