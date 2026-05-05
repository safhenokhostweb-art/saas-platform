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
}
