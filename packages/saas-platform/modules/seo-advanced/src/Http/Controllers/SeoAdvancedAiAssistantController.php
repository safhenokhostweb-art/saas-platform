<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\SeoAdvanced\Services\AiSeo\AiSeoAssistantService;

/**
 * Phase 18-E — Part 4: "Analyze Page" admin UI. With the default
 * NullAiSeoProvider, analyzePage() always returns available=false — the
 * view shows a clear "AI not configured" state rather than empty fields
 * that look broken.
 */
class SeoAdvancedAiAssistantController extends Controller
{
    public function show(int $siteId)
    {
        return view('seo-advanced::admin.ai-assistant', ['siteId' => $siteId, 'result' => null]);
    }

    public function analyze(Request $request, int $siteId, AiSeoAssistantService $service)
    {
        $data = $request->validate([
            'page_content' => 'required|string',
            'current_title' => 'nullable|string',
            'current_description' => 'nullable|string',
        ]);

        $result = $service->analyzePage(
            $data['page_content'],
            $data['current_title'] ?? null,
            $data['current_description'] ?? null,
        );

        return view('seo-advanced::admin.ai-assistant', ['siteId' => $siteId, 'result' => $result] + $data);
    }
}
