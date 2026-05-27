<?php

namespace SaaSPlatform\Modules\Forms\Http\Controllers;

use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\Http\BaseController as Controller;
use SaaSPlatform\Modules\Forms\Models\Form;
use SaaSPlatform\Modules\Forms\Services\FormAnalyticsService;

class FormAnalyticsController extends Controller
{
    public function __construct(
        protected FormAnalyticsService $analyticsService
    ) {}

    public function show(Request $request, Form $form)
    {
        $days      = (int) $request->input('days', 30);
        $analytics = $this->analyticsService->getFormAnalytics($form, $days);

        if ($request->wantsJson()) {
            return response()->json($analytics);
        }

        return view('forms::admin.forms.analytics', compact('form', 'analytics', 'days'));
    }
}
