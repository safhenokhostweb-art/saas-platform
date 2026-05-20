<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wave 5.1 / P1-1 — Customer Module Entitlements Ledger.
 *
 * One row per grant/revoke event. The ledger is append-only in spirit:
 * - "grant" inserts a new row with status = active.
 * - "revoke" mutates that same row to status = revoked and stamps
 *   revoked_at / revoked_by / revoke_reason. The historical grant
 *   metadata is preserved.
 *
 * This model NEVER controls run-time access by itself in Phase A. The
 * runtime gate stays at site_modules.is_active + license validity. The
 * ledger only adds an immutable audit trail so that a revocation is
 * always intentional, attributable, and recoverable.
 */
class ModuleEntitlement extends Model
{
    public const SOURCE_PURCHASE = 'purchase';
    public const SOURCE_LICENSE = 'license';
    public const SOURCE_ADMIN_GRANT = 'admin_grant';
    public const SOURCE_FREE_ASSIGN = 'free_assign';
    public const SOURCE_SEED = 'seed';
    public const SOURCE_IMPORT = 'import';
    public const SOURCE_SYNC = 'sync';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'site_id',
        'customer_id',
        'module_id',
        'module_slug',
        'source',
        'status',
        'granted_at',
        'granted_by',
        'revoked_at',
        'revoked_by',
        'revoke_reason',
        'invoice_id',
        'invoice_item_id',
        'module_purchase_id',
        'module_license_id',
        'metadata',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function modulePurchase(): BelongsTo
    {
        return $this->belongsTo(ModulePurchase::class, 'module_purchase_id');
    }

    public function moduleLicense(): BelongsTo
    {
        return $this->belongsTo(ModuleLicense::class, 'module_license_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeRevoked(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REVOKED);
    }

    public function scopeForSite(Builder $query, int $siteId): Builder
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForModule(Builder $query, int $moduleId): Builder
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeForSlug(Builder $query, string $slug): Builder
    {
        return $query->where('module_slug', $slug);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }
}
