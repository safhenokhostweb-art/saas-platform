<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * رکورد خرید ماژول PAID برای یک سایت.
 * یک‌بار خرید = مالکیت دائمی.
 */
class ModulePurchase extends Model
{
    protected $table = 'site_module_purchases';

    protected $fillable = [
        'site_id',
        'module_id',
        'invoice_id',
        'invoice_item_id',
        'amount_irr',
        'granted_by',
        'purchased_at',
    ];

    protected $casts = [
        'amount_irr' => 'integer',
        'purchased_at' => 'datetime',
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

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * آیا این خرید از طریق پرداخت است؟
     */
    public function isPaidPurchase(): bool
    {
        return $this->invoice_id !== null;
    }

    /**
     * آیا این اعطای دستی توسط ادمین است؟
     */
    public function isAdminGrant(): bool
    {
        return $this->granted_by !== null;
    }
}
