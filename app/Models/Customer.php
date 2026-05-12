<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'email',
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

    /**
     * Wave 5.1 / P1-1 — append-only ledger of every module grant/revoke
     * across all sites owned by this customer.
     */
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
