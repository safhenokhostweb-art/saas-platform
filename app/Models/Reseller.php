<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reseller extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'commission_rate',
        'wholesale_discount_percent',
        'bank_info',
        'is_white_label',
        'brand_name',
        'logo_path',
        'custom_domain',
        'subdomain',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'wholesale_discount_percent' => 'decimal:2',
        'is_white_label' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class, 'reseller_id');
    }

    public function commissions()
    {
        return $this->hasMany(ResellerCommission::class);
    }

    public function payoutRequests()
    {
        return $this->hasMany(PayoutRequest::class);
    }
}
