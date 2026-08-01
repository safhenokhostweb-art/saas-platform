<?php

namespace SaaSPlatform\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use SaaSPlatform\Modules\Crm\Models\Concerns\Archivable;
use SaaSPlatform\Modules\Crm\Models\Concerns\Favoritable;
use SaaSPlatform\Modules\Crm\Models\Concerns\HasActivitiesAndTasks;
use SaaSPlatform\Modules\Crm\Models\Concerns\HasSiteTenantScope;
use SaaSPlatform\Modules\Crm\Models\Concerns\Taggable;

/**
 * Phase 19 — Part 2/4: a Lead is unqualified until convert()ed into a
 * real Contact (+ optional Company + Opportunity) — see the conversion
 * logic in CrmLeadConversionService (Part 4), which is what actually
 * calls the setters below inside a DB transaction.
 */
class CrmLead extends Model
{
    use Archivable;
    use Favoritable;
    use HasActivitiesAndTasks;
    use HasSiteTenantScope;
    use SoftDeletes;
    use Taggable;

    public const STATUS_NEW = 'new';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_QUALIFIED = 'qualified';
    public const STATUS_UNQUALIFIED = 'unqualified';
    public const STATUS_CONVERTED = 'converted';

    protected $table = 'crm_leads';

    protected $attributes = [
        'status' => self::STATUS_NEW,
    ];

    protected $fillable = [
        'site_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'company_name',
        'source',
        'status',
        'owner_user_id',
        'converted_contact_id',
        'converted_company_id',
        'converted_opportunity_id',
        'converted_at',
        'is_favorite',
        'archived_at',
    ];

    protected $casts = [
        'converted_at' => 'datetime',
        'is_favorite' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function convertedContact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'converted_contact_id');
    }

    public function convertedCompany(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'converted_company_id');
    }

    public function convertedOpportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'converted_opportunity_id');
    }

    public function isConverted(): bool
    {
        return $this->status === self::STATUS_CONVERTED;
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('company_name', 'like', "%{$term}%");
        });
    }
}
