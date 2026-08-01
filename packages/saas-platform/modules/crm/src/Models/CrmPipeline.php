<?php

namespace SaaSPlatform\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SaaSPlatform\Modules\Crm\Models\Concerns\HasSiteTenantScope;

class CrmPipeline extends Model
{
    use HasSiteTenantScope;

    protected $table = 'crm_pipelines';

    protected $fillable = [
        'site_id',
        'name',
        'is_default',
        'position',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function stages(): HasMany
    {
        return $this->hasMany(CrmStage::class, 'pipeline_id')->orderBy('position');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(CrmOpportunity::class, 'pipeline_id');
    }
}
