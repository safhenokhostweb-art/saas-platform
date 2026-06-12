<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ThemeBuilder\ThemeAiAnalyzerService;
use Illuminate\Http\Request;

class ThemeAiController extends Controller
{
    public function __construct(protected ThemeAiAnalyzerService $analyzer) {}

    /** GET /admin/theme-builder/ai-analyze */
    public function form()
    {
        $enabled = $this->analyzer->isEnabled();
        return view('admin.theme-builder.ai-analyze', compact('enabled'));
    }

    /**
     * POST /admin/theme-builder/ai-analyze
     * Fetch URL + call Claude → return preview JSON (AJAX or redirect).
     */
    public function analyze(Request $request)
    {
        $data = $request->validate([
            'url'   => 'required|url|max:500',
            'notes' => 'nullable|string|max:1000',
            'name'  => 'nullable|string|max:255',
        ]);

        if (!$this->analyzer->isEnabled()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'error' => 'ANTHROPIC_API_KEY تنظیم نشده است.'], 503);
            }
            return back()->with('error', 'ANTHROPIC_API_KEY تنظیم نشده است.');
        }

        try {
            $themeJson = $this->analyzer->analyzeUrl($data['url'], $data['notes'] ?? '');

            // If name override provided, apply it
            if (!empty($data['name'])) {
                $themeJson['suggested_name'] = $data['name'];
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success'    => true,
                    'theme_json' => $themeJson,
                ]);
            }

            // Store in session for confirmation step
            session(['tb3_pending_theme' => $themeJson]);

            return redirect()->route('admin.theme-builder.ai-confirm');

        } catch (\RuntimeException $e) {
            $msg = 'خطا در تحلیل: ' . $e->getMessage();
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'error' => $msg], 502);
            }
            return back()->with('error', $msg)->withInput();
        }
    }

    /** GET /admin/theme-builder/ai-confirm — shows the generated JSON for review */
    public function confirm()
    {
        $themeJson = session('tb3_pending_theme');
        if (!$themeJson) {
            return redirect()->route('admin.theme-builder.ai-form')->with('error', 'داده‌ای برای تأیید یافت نشد. دوباره تحلیل کنید.');
        }

        return view('admin.theme-builder.ai-confirm', compact('themeJson'));
    }

    /** POST /admin/theme-builder/ai-generate — create Theme model and open canvas */
    public function generate(Request $request)
    {
        $themeJson = session('tb3_pending_theme');

        if (!$themeJson) {
            return redirect()->route('admin.theme-builder.ai-form')->with('error', 'جلسه منقضی شد. دوباره تحلیل کنید.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            $theme = $this->analyzer->createFromJson($themeJson, $data['name']);

            session()->forget('tb3_pending_theme');

            return redirect()
                ->route('admin.theme-builder.canvas', $theme)
                ->with('success', "قالب «{$theme->name}» از تحلیل هوش مصنوعی ساخته شد. اکنون می‌توانید آن را ویرایش کنید.");

        } catch (\Throwable $e) {
            return back()->with('error', 'ایجاد قالب ناموفق بود: ' . $e->getMessage());
        }
    }

    /** POST /admin/theme-builder/ai-analyze-ajax — pure JSON for Alpine.js live UI */
    public function analyzeAjax(Request $request)
    {
        return $this->analyze($request);
    }
}
