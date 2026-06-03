<?php

namespace SaaSPlatform\Modules\Education\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EducationRecommendation extends Model
{
    protected $table = 'edu_recommendations';

    public const TYPE_ARTICLE       = 'article';
    public const TYPE_WALKTHROUGH   = 'walkthrough';
    public const TYPE_LEARNING_PATH = 'learning_path';
    public const TYPE_FEATURE_INTRO = 'feature_intro';
    public const TYPE_USAGE_TIP     = 'usage_tip';

    protected $fillable = [
        'user_id', 'site_id', 'recommendation_type',
        'article_id', 'walkthrough_id', 'learning_path_id',
        'priority', 'reason',
        'dismissed_at', 'completed_at',
    ];

    protected $casts = [
        'priority'      => 'integer',
        'dismissed_at'  => 'datetime',
        'completed_at'  => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(EducationArticle::class, 'article_id');
    }

    public function walkthrough(): BelongsTo
    {
        return $this->belongsTo(EducationWalkthrough::class, 'walkthrough_id');
    }

    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(EducationLearningPath::class, 'learning_path_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('dismissed_at')->whereNull('completed_at');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSite($query, ?int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    public function isActive(): bool
    {
        return $this->dismissed_at === null && $this->completed_at === null;
    }
}
