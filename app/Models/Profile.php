<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Profile extends Model
{
    protected $fillable = [
        'user_id',
        'full_name',
        'postal_code',
        'address',
        'mobile',
        'lat',
        'lng',
        'map_link',
        'map_address',
        'avatar',
        'extra',
        'is_complete',
    ];

    protected $casts = [
        'extra' => 'array',   // برای فیلدهای داینامیک پروفایل
        'is_complete' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
