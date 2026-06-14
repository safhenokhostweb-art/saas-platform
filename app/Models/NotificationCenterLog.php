<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NotificationCenterLog extends Model
{
    protected $table = 'notification_center_logs';

    protected $fillable = [
        'type', 'priority', 'title', 'message', 'data', 'link',
        'channel', 'audience_type', 'site_id', 'created_by',
        'read_at', 'sms_sent_at',
    ];

    protected $casts = [
        'data'        => 'array',
        'read_at'     => 'datetime',
        'sms_sent_at' => 'datetime',
    ];

    // ── Constants ──────────────────────────────────────────────────────────

    const TYPES = [
        'system'          => 'سیستمی',
        'infrastructure'  => 'زیرساختی',
        'site_down'       => 'خرابی سایت',
        'site_recovery'   => 'بازیابی سایت',
        'update'          => 'بروزرسانی',
        'deploy'          => 'استقرار',
        'maintenance'     => 'نگهداری',
        'purchase'        => 'خرید',
        'renewal'         => 'تمدید',
        'support_ticket'  => 'تیکت پشتیبانی',
        'custom'          => 'سفارشی',
    ];

    const PRIORITIES = [
        'critical' => 'بحرانی',
        'high'     => 'بالا',
        'medium'   => 'متوسط',
        'low'      => 'پایین',
    ];

    const CHANNELS = [
        'in_app'  => 'درون‌سیستمی',
        'sms'     => 'پیامک',
        'both'    => 'درون‌سیستمی + پیامک',
        'silent'  => 'بدون ارسال',
    ];

    const AUDIENCES = [
        'platform_admin'   => 'مدیران پلتفرم',
        'site_admin'       => 'مدیر سایت خاص',
        'all_site_admins'  => 'همه مدیران سایت',
        'broadcast'        => 'همه کاربران',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isRead(): bool  { return $this->read_at !== null; }
    public function isUnread(): bool { return $this->read_at === null; }

    public function typeFa(): string      { return self::TYPES[$this->type] ?? $this->type; }
    public function priorityFa(): string  { return self::PRIORITIES[$this->priority] ?? $this->priority; }
    public function channelFa(): string   { return self::CHANNELS[$this->channel] ?? $this->channel; }
    public function audienceFa(): string  { return self::AUDIENCES[$this->audience_type] ?? $this->audience_type; }

    public function priorityColor(): string
    {
        return match ($this->priority) {
            'critical' => 'red',
            'high'     => 'orange',
            'medium'   => 'amber',
            default    => 'blue',
        };
    }

    public function priorityHex(): string
    {
        return match ($this->priority) {
            'critical' => '#dc2626',
            'high'     => '#ea580c',
            'medium'   => '#d97706',
            default    => '#2563eb',
        };
    }

    public function typeIcon(): string
    {
        return match ($this->type) {
            'site_down'       => 'x-circle',
            'site_recovery'   => 'check-circle',
            'infrastructure'  => 'server',
            'update'          => 'arrow-up',
            'deploy'          => 'cloud-upload',
            'maintenance'     => 'wrench',
            'purchase'        => 'shopping-bag',
            'renewal'         => 'refresh',
            'support_ticket'  => 'chat',
            default           => 'bell',
        };
    }

    public function markRead(): void
    {
        if ($this->isUnread()) {
            $this->update(['read_at' => now()]);
        }
    }

    public function markSmsSent(): void
    {
        $this->update(['sms_sent_at' => now()]);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeUnread(Builder $q): Builder { return $q->whereNull('read_at'); }
    public function scopeRead(Builder $q): Builder   { return $q->whereNotNull('read_at'); }
    public function scopeCritical(Builder $q): Builder { return $q->where('priority', 'critical'); }

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type);
    }

    public function scopeOfPriority(Builder $q, string $priority): Builder
    {
        return $q->where('priority', $priority);
    }

    public function scopeForSite(Builder $q, int $siteId): Builder
    {
        return $q->where('site_id', $siteId);
    }

    public function scopeForAudience(Builder $q, string $type): Builder
    {
        return $q->where('audience_type', $type);
    }

    public function scopeToday(Builder $q): Builder
    {
        return $q->whereDate('created_at', today());
    }

    public function scopeThisWeek(Builder $q): Builder
    {
        return $q->where('created_at', '>=', now()->startOfWeek());
    }
}
