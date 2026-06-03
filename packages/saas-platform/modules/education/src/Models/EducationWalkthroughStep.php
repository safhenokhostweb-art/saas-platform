<?php

namespace SaaSPlatform\Modules\Education\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EducationWalkthroughStep extends Model
{
    protected $table = 'edu_walkthrough_steps';

    protected $fillable = [
        'edu_walkthrough_id', 'step_number',
        'title', 'body', 'target_selector',
        'placement', 'action', 'media_id',
    ];

    protected $casts = [
        'step_number' => 'integer',
    ];

    public function walkthrough(): BelongsTo
    {
        return $this->belongsTo(EducationWalkthrough::class, 'edu_walkthrough_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(EducationMedia::class, 'media_id');
    }
}
