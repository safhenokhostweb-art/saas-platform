<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Connection state of one domain of a site (Phase 3, custom domains).
 * See SiteDomainService for the transitions.
 */
class SiteDomain extends Model
{
    public const ROLE_PRIMARY = 'primary';
    public const ROLE_PENDING = 'pending';
    public const ROLE_ALIAS = 'alias';

    public const STATUS_AWAITING_DNS = 'awaiting_dns';
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_ISSUING = 'issuing';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REDIRECT = 'redirect';

    public const STATUS_LABELS = [
        self::STATUS_AWAITING_DNS => 'در انتظار DNS',
        self::STATUS_CONNECTED    => 'DNS متصل (بدون SSL)',
        self::STATUS_ISSUING      => 'در حال اتصال و صدور SSL',
        self::STATUS_ACTIVE       => 'فعال با SSL',
        self::STATUS_FAILED       => 'ناموفق',
        self::STATUS_REDIRECT     => 'ریدایرکت به دامنهٔ اصلی',
    ];

    protected $fillable = [
        'site_id',
        'domain',
        'role',
        'status',
        'www_in_certificate',
        'dns_report',
        'dns_checked_at',
        'ssl_expires_at',
        'last_error',
        'last_job_at',
    ];

    protected $casts = [
        'www_in_certificate' => 'boolean',
        'dns_report'         => 'array',
        'dns_checked_at'     => 'datetime',
        'ssl_expires_at'     => 'date',
        'last_job_at'        => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isPrimary(): bool
    {
        return $this->role === self::ROLE_PRIMARY;
    }

    public function isPending(): bool
    {
        return $this->role === self::ROLE_PENDING;
    }
}
