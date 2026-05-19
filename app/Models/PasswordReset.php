<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    protected $fillable = [
        'mobile',
        'token',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /**
     * Find a valid (non-expired, non-used) reset token.
     */
    public static function scopeValid($query, string $mobile, string $token)
    {
        return $query->where('mobile', $mobile)
            ->where('token', $token)
            ->where('used_at', null)
            ->where('expires_at', '>', now());
    }
}
