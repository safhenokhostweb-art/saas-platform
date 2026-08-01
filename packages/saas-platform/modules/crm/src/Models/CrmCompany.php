<?php

namespace SaaSPlatform\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use SaaSPlatform\Modules\Crm\Models\Concerns\Archivable;
use SaaSPlatform\Modules\Crm\Models\Concerns\Favoritable;
use SaaSPlatform\Modules\Crm\Models\Concerns\HasActivitiesAndTasks;
use SaaSPlatform\Modules\Crm\Models\Concerns\HasSiteTenantScope;
use SaaSPlatform\Modules\Crm\Models\Concerns\Taggable;

class CrmCompany extends Model
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

    protected $table = 'crm_companies';

    /**
     * Mirrors the migration's DB-level default — without this, a freshly
     * created in-memory model reports lifecycle_stage=null until
     * reloaded (Eloquent's create() never hydrates DB-side column
     * defaults back into the instance it returns).
     */
    protected $attributes = [
        'lifecycle_stage' => self::STAGE_LEAD,
    ];

    protected $fillable = [
        'site_id',
        'name',
        'domain',
        'industry',
        'phone',
        'address',
        'lifecycle_stage',
        'owner_user_id',
        'is_favorite',
        'archived_at',
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(CrmContact::class, 'company_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(CrmOpportunity::class, 'company_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('domain', 'like', "%{$term}%");
        });
    }
}
