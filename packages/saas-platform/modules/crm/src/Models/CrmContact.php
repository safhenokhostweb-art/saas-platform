<?php

namespace SaaSPlatform\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use SaaSPlatform\Modules\Crm\Models\Concerns\Archivable;
use SaaSPlatform\Modules\Crm\Models\Concerns\Favoritable;
use SaaSPlatform\Modules\Crm\Models\Concerns\HasActivitiesAndTasks;
use SaaSPlatform\Modules\Crm\Models\Concerns\HasSiteTenantScope;
use SaaSPlatform\Modules\Crm\Models\Concerns\Taggable;

class CrmContact extends Model
{
    use Archivable;
    use Favoritable;
    use HasActivitiesAndTasks;
    use HasSiteTenantScope;
    use SoftDeletes;
    use Taggable;

    public const STAGE_LEAD = 'lead';
    public const STAGE_PROSPECT = 'prospect';
    public const STAGE_CUSTOMER = 'customer';

    // Phase X: 'crm_suite_contacts', not 'crm_contacts' — that name is taken
    // by customer-site's own, unrelated, pre-existing local CRM table.
    protected $table = 'crm_suite_contacts';

    protected $attributes = [
        'lifecycle_stage' => self::STAGE_LEAD,
    ];

    protected $fillable = [
        'site_id',
        'company_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'job_title',
        'lifecycle_stage',
        'owner_user_id',
        'is_favorite',
        'archived_at',
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'company_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(CrmOpportunity::class, 'contact_id');
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
                ->orWhere('phone', 'like', "%{$term}%");
        });
    }
}
