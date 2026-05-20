<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginAttempt extends Model
{
    const UPDATED_AT = null; // only created_at timestamp

    protected $fillable = [
        'mobile',
        'ip_address',
        'user_agent',
        'is_successful',
        'failure_reason',
        'user_id',
    ];

    protected $casts = [
        'is_successful' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Count failed attempts for a mobile/IP within a time window.
     */
    public static function scopeRecentFailed($query, string $mobile, int $minutes = 15)
    {
        return $query->where('mobile', $mobile)
            ->where('is_successful', false)
            ->where('created_at', '>=', now()->subMinutes($minutes));
    }
}
