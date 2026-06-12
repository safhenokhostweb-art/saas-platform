<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemePurchaseOrder extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_PAID      = 'paid';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'site_id',
        'theme_id',
        'amount_rial',
        'status',
        'gateway',
        'gateway_authority',
        'gateway_ref',
        'redirect_back_url',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function theme()
    {
        return $this->belongsTo(Theme::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
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
