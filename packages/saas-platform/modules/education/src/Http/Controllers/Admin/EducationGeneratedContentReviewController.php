<?php

namespace SaaSPlatform\Modules\Education\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationDraftReviewServiceInterface;
use SaaSPlatform\Modules\Education\Models\EducationArticle;

class EducationGeneratedContentReviewController extends Controller
{
    public function __construct(
        private readonly EducationDraftReviewServiceInterface $reviewService,
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('viewGeneratedContent', EducationArticle::class);

        $siteId = $request->attributes->get('tenant_site')?->id;

        $filters = $request->only(['module_slug', 'content_type', 'search']);

        $pending  = $this->reviewService->pendingQueue($siteId, $filters, 20);
        $analytics = $this->reviewService->reviewAnalytics($siteId);

        return view('education::admin.education.generated-content.review', compact('pending', 'analytics'));
    }
}
