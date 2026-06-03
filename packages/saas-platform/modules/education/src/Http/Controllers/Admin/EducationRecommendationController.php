<?php

namespace SaaSPlatform\Modules\Education\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationRecommendationServiceInterface;

class EducationRecommendationController extends Controller
{
    public function __construct(
        private readonly EducationRecommendationServiceInterface $recs,
    ) {}

    public function index(Request $request)
    {
        $userId = $request->input('user_id', auth()->id());
        $active = $this->recs->activeForUser($userId, null, 20);

        return view('education::admin.education.recommendations.index', compact('active', 'userId'));
    }

    public function generate(Request $request)
    {
        $userId = auth()->id();
        $count  = $this->recs->generateForUser($userId, null);

        return back()->with('success', "{$count} توصیه جدید تولید شد.");
    }

    public function dismiss(Request $request, int $id)
    {
        $this->recs->dismiss($id, auth()->id());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'توصیه رد شد.');
    }

    public function complete(Request $request, int $id)
    {
        $this->recs->complete($id, auth()->id());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'توصیه تکمیل شد.');
    }
}
