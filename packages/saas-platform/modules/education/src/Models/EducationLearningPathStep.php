<?php

namespace SaaSPlatform\Modules\Education\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EducationLearningPathStep extends Model
{
    protected $table = 'edu_learning_path_steps';

    protected $fillable = [
        'edu_learning_path_id', 'step_number',
        'title', 'description', 'step_type',
        'reference_id', 'reference_type',
        'is_required', 'meta',
    ];

    protected $casts = [
        'step_number' => 'integer',
        'is_required' => 'boolean',
        'meta'        => 'array',
    ];

    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(EducationLearningPath::class, 'edu_learning_path_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo('reference');
    }
}
