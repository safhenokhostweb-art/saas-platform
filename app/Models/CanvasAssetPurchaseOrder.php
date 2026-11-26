<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CanvasAssetPurchaseOrder extends Model
{
    use HasFactory;

    protected $table = 'canvas_asset_purchase_orders';

    protected $fillable = [
        'site_id',
        'customer_id',
        'invoice_id',
        'asset_kind',
        'asset_key',
        'asset_version',
        'amount_toman',
        'amount_rial',
        'status',
        'gateway',
        'gateway_authority',
        'gateway_ref',
        'redirect_back_url',
        'paid_at',
    ];

    protected $casts = [
        'amount_toman' => 'integer',
        'amount_rial'  => 'integer',
        'paid_at'      => 'datetime',
    ];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_PAID      = 'paid';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
