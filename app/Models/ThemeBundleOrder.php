<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemeBundleOrder extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID    = 'paid';
    public const STATUS_FAILED  = 'failed';

    protected $fillable = [
        'site_id',
        'bundle_id',
        'amount_rial',
        'status',
        'gateway',
        'gateway_authority',
        'redirect_back_url',
    ];

    protected $casts = [
        'amount_rial' => 'integer',
    ];

    public function bundle()
    {
        return $this->belongsTo(ThemeBundle::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
