<?php

namespace SaaSPlatform\Modules\Education\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationFeatureUsageRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationRecommendationRepositoryInterface;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Models\EducationLearningPath;
use SaaSPlatform\Modules\Education\Models\EducationUserProgress;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;
use SaaSPlatform\Modules\Education\Services\EducationCoverageTracker;

class EducationCenterController extends Controller
{
    public function __construct(
        private readonly EducationCoverageTracker                    $coverage,
        private readonly EducationFeatureUsageRepositoryInterface     $usageRepo,
        private readonly EducationRecommendationRepositoryInterface   $recsRepo,
    ) {}

    public function index()
    {
        // --- content stats ---
        $totalArticles     = EducationArticle::count();
        $publishedArticles = EducationArticle::published()->count();
        $draftArticles     = EducationArticle::where('status', EducationArticle::STATUS_DRAFT)->count();
        $autoGenArticles   = EducationArticle::where('auto_generated', true)->count();
        $manualArticles    = EducationArticle::where('auto_generated', false)->count();

        $stats = [
            'articles'              => $publishedArticles,
            'drafts'                => $draftArticles,
            'auto_generated'        => $autoGenArticles,
            'manual'                => $manualArticles,
            'walkthroughs'          => EducationWalkthrough::where('is_active', true)->count(),
            'learning_paths'        => EducationLearningPath::where('is_active', true)->count(),
        ];

        // --- feature discovery stats ---
        $totalFeatures       = EducationFeature::count();
        $documentedFeatures  = EducationFeature::whereExists(function ($q) {
            $q->from('edu_articles')
              ->whereColumn('edu_articles.module_slug', 'edu_features.module_slug')
              ->where('edu_articles.status', EducationArticle::STATUS_PUBLISHED)
              ->whereNull('edu_articles.deleted_at');
        })->count();
        $undocumentedFeatures = $totalFeatures - $documentedFeatures;

        $featureStats = [
            'total'        => $totalFeatures,
            'documented'   => $documentedFeatures,
            'undocumented' => $undocumentedFeatures,
        ];

        // --- adoption stats ---
        $adoptionStats = $this->usageRepo->adoptionStats(null);

        // --- learning progress ---
        $progressStats = [
            'total_progress_records' => EducationUserProgress::count(),
        ];

        // --- recommendations ---
        $recommendationCount = \SaaSPlatform\Modules\Education\Models\EducationRecommendation::active()->count();

        // --- module coverage ---
        $coverage = $this->coverage->report();

        return view('education::admin.education.index', compact(
            'stats',
            'featureStats',
            'adoptionStats',
            'progressStats',
            'recommendationCount',
            'coverage'
        ));
    }
}
