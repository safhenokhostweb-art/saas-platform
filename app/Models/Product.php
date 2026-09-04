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
    public const TYPE_MOTION_ASSET = 'motion_asset';
    /**
     * Phase 3G-B-1 (ADR-045): domain vocabulary only. No dedicated FK column
     * exists on Product for this type, matching TYPE_MOTION_ASSET's own
     * precedent — the master commercial identity lives on App\Models\
     * Extension/ExtensionRelease, never on Product. OrderFulfillmentService
     * deliberately has no case for this type yet; fulfillment remains
     * unsupported until entitlement authority is settled (a later phase).
     */
    public const TYPE_EXTENSION = 'extension';

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
            self::TYPE_MOTION_ASSET => 'موشن گرافیک اختصاصی',
            self::TYPE_EXTENSION => 'افزونه سازنده قالب',
            default => $this->type,
        };
    }
}
