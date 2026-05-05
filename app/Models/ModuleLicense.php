<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * لایسنس ماژول برای یک tenant (سایت).
 * فعال‌سازی ماژول = چک‌باکس فعال + لایسنس معتبر.
 */
class ModuleLicense extends Model
{
    protected $table = 'module_licenses';

    public const TYPE_ONE_TIME = 'one_time';
    public const TYPE_SUBSCRIPTION = 'subscription';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'site_id',
        'module_id',
        'license_type',
        'purchased_at',
        'expires_at',
        'status',
        'support_eligibility',
        'invoice_id',
        'invoice_item_id',
        'module_purchase_id',
        'granted_by',
        'revoked_at',
    ];

    protected $casts = [
        'purchased_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'support_eligibility' => 'boolean',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class, 'invoice_item_id');
    }

    public function modulePurchase()
    {
        return $this->belongsTo(ModulePurchase::class, 'module_purchase_id');
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function isActive(): bool
    {
        if ($this->status === self::STATUS_REVOKED) {
            return false;
        }
        if ($this->status === self::STATUS_EXPIRED) {
            return false;
        }
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }
        return true;
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED || $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * به‌روزرسانی وضعیت بر اساس تاریخ انقضا.
     */
    public function refreshStatus(): void
    {
        if ($this->status === self::STATUS_REVOKED) {
            return;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            $this->update(['status' => self::STATUS_EXPIRED]);
        }
    }
}
