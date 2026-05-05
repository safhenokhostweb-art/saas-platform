<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * لایسنس صادرشده از پلتفرم برای هر سایت.
 * کلید فقط یک‌بار هنگام صدور برگردانده می‌شود؛ بعد فقط با hash مقایسه می‌شود.
 */
class PlatformLicense extends Model
{
    protected $table = 'platform_licenses';

    protected $fillable = [
        'site_id',
        'license_key_prefix',
        'license_key_hash',
        'domain_allowed',
        'expires_at',
        'product_sku',
        'issued_at',
        'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'issued_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * آیا دامنهٔ درخواست در لیست مجاز است؟
     */
    public function isDomainAllowed(string $domain): bool
    {
        if (empty($this->domain_allowed)) {
            return true;
        }
        $domain = $this->normalizeDomain($domain);
        $allowed = array_map(function ($d) {
            return $this->normalizeDomain(trim($d));
        }, explode(',', $this->domain_allowed));
        return in_array($domain, $allowed, true);
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);
        if (str_contains($domain, '://')) {
            $parsed = parse_url($domain);
            $domain = $parsed['host'] ?? $domain;
        }
        $domain = rtrim($domain, '/');
        return strtolower($domain);
    }
}
