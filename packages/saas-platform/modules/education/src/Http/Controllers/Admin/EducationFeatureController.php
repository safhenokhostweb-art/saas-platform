<?php

namespace SaaSPlatform\Modules\Education\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationFeatureUsageRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\DocumentationGeneratorServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\FeatureDiscoveryServiceInterface;
use SaaSPlatform\Modules\Education\Models\EducationFeature;

class EducationFeatureController extends Controller
{
    public function __construct(
        private readonly FeatureDiscoveryServiceInterface        $discovery,
        private readonly DocumentationGeneratorServiceInterface   $docGen,
        private readonly EducationFeatureUsageRepositoryInterface $usage,
    ) {}

    public function index()
    {
        $features      = EducationFeature::orderBy('module_slug')->orderBy('feature_slug')->paginate(30);
        $adoptionStats = $this->usage->adoptionStats(null);

        return view('education::admin.education.features.index', compact(
            'features', 'adoptionStats'
        ));
    }

    public function runDiscovery()
    {
        $count = $this->discovery->discover(null);

        return back()->with('success', "کشف ویژگی‌ها انجام شد: {$count} رکورد ذخیره شد.");
    }

    public function generateDoc(EducationFeature $feature)
    {
        $article = $this->docGen->generateForFeature($feature);

        if (! $article) {
            return back()->with('info', 'مستندات برای این ویژگی از قبل وجود دارد.');
        }

        return back()->with('success', "پیش‌نویس مقاله برای «{$feature->feature_name}» ایجاد شد.");
    }

    public function regenerateDoc(EducationFeature $feature)
    {
        $article = $this->docGen->regenerateForFeature($feature);

        return back()->with('success', "مقاله خودکار برای «{$feature->feature_name}» بازتولید شد.");
    }
}
