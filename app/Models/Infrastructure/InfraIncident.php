<?php
namespace App\Models\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class InfraIncident extends Model
{
    protected $fillable = [
        'site_id', 'severity', 'type', 'title', 'message',
        'resolved_at', 'sms_sent_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'sms_sent_at' => 'datetime',
    ];

    public function site()
    {
        return $this->belongsTo(\App\Models\Site::class);
    }

    public function isResolved(): bool  { return $this->resolved_at !== null; }
    public function isOpen(): bool      { return $this->resolved_at === null; }
    public function isCritical(): bool  { return $this->severity === 'critical'; }

    public function needsSmsAlert(): bool
    {
        if ($this->sms_sent_at === null) {
            return true;
        }
        return $this->sms_sent_at->diffInMinutes(now()) >= 30;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', 'critical');
    }

    public function scopeForSite(Builder $query, int $siteId): Builder
    {
        return $query->where('site_id', $siteId);
    }

    public function markResolved(): void
    {
        $this->update(['resolved_at' => now()]);
    }

    public function markSmsSent(): void
    {
        $this->update(['sms_sent_at' => now()]);
    }
}
