<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'mobile',
        'company_name',
        'notes',
        'user_id',
        'reseller_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sites()
    {
        return $this->hasMany(Site::class);
    }

    public function reseller()
    {
        return $this->belongsTo(Reseller::class);
    }

    public function entitlements()
    {
        return $this->hasMany(\App\Models\ModuleEntitlement::class, 'customer_id');
    }

    public function activeEntitlements()
    {
        return $this->entitlements()
            ->where('status', \App\Models\ModuleEntitlement::STATUS_ACTIVE);
    }
}