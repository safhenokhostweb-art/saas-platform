<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemeMarketplace extends Model
{
    protected $table = 'theme_marketplace';

    protected $fillable = [
        'theme_id',
        'marketplace_description',
        'screenshots',
        'requirements',
        'version_notes',
        'is_featured',
        'sales_count',
        // scoring
        'design_score',
        'commerce_score',
        'performance_score',
        'completeness_score',
        'marketplace_score',
        'quality_issues',
        'last_scored_at',
        // commercial
        'annual_price',
        'lifetime_price',
        'is_new_release',
        'is_bestseller',
        'preview_url',
        'category_tags',
        'supported_modules_json',
    ];

    protected $casts = [
        'screenshots'        => 'array',
        'requirements'       => 'array',
        'quality_issues'     => 'array',
        'category_tags'      => 'array',
        'is_featured'        => 'boolean',
        'is_new_release'     => 'boolean',
        'is_bestseller'      => 'boolean',
        'sales_count'        => 'integer',
        'design_score'       => 'integer',
        'commerce_score'     => 'integer',
        'performance_score'  => 'integer',
        'completeness_score' => 'integer',
        'marketplace_score'  => 'integer',
        'annual_price'       => 'integer',
        'lifetime_price'     => 'integer',
        'last_scored_at'     => 'datetime',
    ];

    public function theme()
    {
        return $this->belongsTo(Theme::class);
    }

    public function getOverallScore(): int
    {
        $scores = [
            $this->design_score,
            $this->commerce_score,
            $this->performance_score,
            $this->completeness_score,
            $this->marketplace_score,
        ];
        $non_zero = array_filter($scores);
        return count($non_zero) ? (int) round(array_sum($non_zero) / count($non_zero)) : 0;
    }

    public function getScoreBadge(): string
    {
        $score = $this->getOverallScore();
        if ($score >= 90) return 'excellent';
        if ($score >= 75) return 'good';
        if ($score >= 60) return 'fair';
        return 'needs-work';
    }
}
