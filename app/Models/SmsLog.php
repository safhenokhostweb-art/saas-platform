<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class SmsLog extends Model
{
    protected $fillable = [
        'provider', 'recipient', 'message', 'success', 'error_message', 'response_time_ms',
    ];

    protected $casts = [
        'success'          => 'boolean',
        'response_time_ms' => 'integer',
    ];

    public function scopeSuccess(Builder $q): Builder
    {
        return $q->where('success', true);
    }

    public function scopeFailed(Builder $q): Builder
    {
        return $q->where('success', false);
    }

    public function scopeProvider(Builder $q, string $provider): Builder
    {
        return $q->where('provider', $provider);
    }

    public static function successRateFor(string $provider, int $days = 30): float
    {
        $total = static::provider($provider)
            ->where('created_at', '>=', now()->subDays($days))
            ->count();

        if ($total === 0) {
            return 0.0;
        }

        $success = static::provider($provider)->success()
            ->where('created_at', '>=', now()->subDays($days))
            ->count();

        return round($success / $total * 100, 1);
    }
}
