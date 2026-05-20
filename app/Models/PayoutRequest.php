<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutRequest extends Model
{
    protected $fillable = ['reseller_id', 'amount_irr', 'status', 'admin_note', 'processed_at'];

    protected $casts = [
        'amount_irr' => 'integer',
        'processed_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';
    public const STATUS_REJECTED = 'rejected';

    public function reseller()
    {
        return $this->belongsTo(Reseller::class);
    }
}
