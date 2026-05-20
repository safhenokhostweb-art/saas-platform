<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResellerCommission extends Model
{
    protected $fillable = [
        'reseller_id',
        'invoice_id',
        'amount_irr',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount_irr' => 'integer',
        'paid_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';

    public function reseller()
    {
        return $this->belongsTo(Reseller::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
