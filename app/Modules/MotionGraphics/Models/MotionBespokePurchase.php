<?php

namespace App\Modules\MotionGraphics\Models;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A real purchase (or admin grant) of one bespoke commission by one site.
 * Mirrors ModulePurchase's shape (Phase 9). Created either by
 * OrderFulfillmentService::fulfillMotionAsset() (real invoice) or an
 * admin grant (invoice_id/invoice_item_id null, granted_by set) — never
 * by anything else. Its existence is a PREREQUISITE for
 * MotionBespokeAssignmentService::assign(), never activation itself.
 */
final class MotionBespokePurchase extends Model
{
    protected $table = 'motion_bespoke_purchases';

    protected $fillable = [
        'uuid', 'commission_id', 'site_id', 'invoice_id', 'invoice_item_id',
        'amount_irr', 'purchased_at', 'granted_by',
    ];

    protected $casts = [
        'amount_irr' => 'integer',
        'purchased_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $purchase): void {
            $purchase->uuid ??= (string) Str::uuid();
        });
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(MotionBespokeCommission::class, 'commission_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
