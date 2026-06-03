<?php

namespace SaaSPlatform\Modules\Education\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationFeatureUsageRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\FeatureUsageServiceInterface;
use SaaSPlatform\Modules\Education\Models\EducationFeature;

class EducationAdoptionController extends Controller
{
    public function __construct(
        private readonly EducationFeatureUsageRepositoryInterface $usageRepo,
        private readonly FeatureUsageServiceInterface             $usageService,
    ) {}

    public function index()
    {
        $stats        = $this->usageRepo->adoptionStats(null);
        $topFeatures  = $this->usageRepo->topFeaturesByUsage(null, 15);
        $unusedFeatures = $this->usageRepo->unusedFeatures(null);
        $totalFeatures  = EducationFeature::count();

        return view('education::admin.education.adoption.index', compact(
            'stats', 'topFeatures', 'unusedFeatures', 'totalFeatures'
        ));
    }
}
