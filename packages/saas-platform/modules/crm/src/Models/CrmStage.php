<?php

namespace SaaSPlatform\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmStage extends Model
{
    public const TYPE_OPEN = 'open';
    public const TYPE_WON = 'won';
    public const TYPE_LOST = 'lost';

    protected $table = 'crm_stages';

    protected $attributes = [
        'type' => self::TYPE_OPEN,
        'probability' => 0,
    ];

    protected $fillable = [
        'pipeline_id',
        'name',
        'position',
        'probability',
        'type',
    ];

    protected $casts = [
        'probability' => 'integer',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'pipeline_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(CrmOpportunity::class, 'stage_id');
    }
}
