<?php
namespace App\Models\Infrastructure;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class InfraIncident extends Model
{
    // Severity levels
    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_HIGH     = 'high';
    public const SEVERITY_MEDIUM   = 'medium';
    public const SEVERITY_LOW      = 'low';

    // Lifecycle states
    public const STATUS_OPEN          = 'open';
    public const STATUS_INVESTIGATING = 'investigating';
    public const STATUS_RESOLVED      = 'resolved';
    public const STATUS_CLOSED        = 'closed';

    protected $fillable = [
        'site_id', 'status', 'severity', 'type', 'title', 'message',
        'assigned_to_id', 'investigating_at', 'resolved_at', 'closed_at',
        'support_ticket_id', 'customer_notified_at', 'sms_sent_at',
    ];

    protected $casts = [
        'investigating_at'    => 'datetime',
        'resolved_at'         => 'datetime',
        'closed_at'           => 'datetime',
        'sms_sent_at'         => 'datetime',
        'customer_notified_at'=> 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function site()
    {
        return $this->belongsTo(\App\Models\Site::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function notes()
    {
        return $this->hasMany(InfraIncidentNote::class, 'incident_id')->orderBy('created_at');
    }

    // ── State helpers ─────────────────────────────────────────────────────

    public function isOpen(): bool          { return $this->status === self::STATUS_OPEN; }
    public function isInvestigating(): bool { return $this->status === self::STATUS_INVESTIGATING; }
    public function isResolved(): bool      { return $this->status === self::STATUS_RESOLVED; }
    public function isClosed(): bool        { return $this->status === self::STATUS_CLOSED; }
    public function isActive(): bool        { return in_array($this->status, [self::STATUS_OPEN, self::STATUS_INVESTIGATING], true); }

    public function isCritical(): bool { return $this->severity === self::SEVERITY_CRITICAL; }
    public function isHigh(): bool     { return $this->severity === self::SEVERITY_HIGH; }
    public function isMedium(): bool   { return $this->severity === self::SEVERITY_MEDIUM; }
    public function isLow(): bool      { return $this->severity === self::SEVERITY_LOW; }

    public function needsSmsAlert(): bool
    {
        if ($this->sms_sent_at === null) {
            return true;
        }
        return $this->sms_sent_at->diffInMinutes(now()) >= 30;
    }

    // ── Mutation helpers ──────────────────────────────────────────────────

    public function markInvestigating(?int $userId = null): void
    {
        $this->update([
            'status'           => self::STATUS_INVESTIGATING,
            'investigating_at' => now(),
            'assigned_to_id'   => $userId ?? $this->assigned_to_id,
        ]);
    }

    public function markResolved(): void
    {
        $this->update([
            'status'      => self::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);
    }

    public function markClosed(): void
    {
        $this->update([
            'status'    => self::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
    }

    public function markSmsSent(): void
    {
        $this->update(['sms_sent_at' => now()]);
    }

    public function markCustomerNotified(): void
    {
        $this->update(['customer_notified_at' => now()]);
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeInvestigating(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_INVESTIGATING);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_INVESTIGATING]);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RESOLVED);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', self::SEVERITY_CRITICAL);
    }

    public function scopeBySeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    public function scopeForSite(Builder $query, int $siteId): Builder
    {
        return $query->where('site_id', $siteId);
    }

    // ── Label helpers ─────────────────────────────────────────────────────

    public function severityLabel(): string
    {
        return match ($this->severity) {
            self::SEVERITY_CRITICAL => 'بحرانی',
            self::SEVERITY_HIGH     => 'بالا',
            self::SEVERITY_MEDIUM   => 'متوسط',
            self::SEVERITY_LOW      => 'پایین',
            default                 => $this->severity,
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN          => 'باز',
            self::STATUS_INVESTIGATING => 'در حال بررسی',
            self::STATUS_RESOLVED      => 'برطرف شد',
            self::STATUS_CLOSED        => 'بسته شد',
            default                    => $this->status,
        };
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'site_down'         => 'سایت از دسترس خارج',
            'db_error'          => 'خطای دیتابیس',
            'redis_error'       => 'خطای Redis',
            'queue_dead'        => 'صف پردازش متوقف',
            'ssl_expiring'      => 'SSL رو به انقضا',
            'deploy_failure'    => 'خطای استقرار',
            'scheduler_failure' => 'خرابی زمان‌بند',
            'sms_failure'       => 'خرابی سرویس SMS',
            'ai_failure'        => 'خرابی سرویس AI',
            'storage_failure'   => 'خرابی فضای ذخیره',
            'disk_critical'     => 'دیسک پر',
            'backup_failure'    => 'خرابی پشتیبان‌گیری',
            default             => $this->type,
        };
    }
}
