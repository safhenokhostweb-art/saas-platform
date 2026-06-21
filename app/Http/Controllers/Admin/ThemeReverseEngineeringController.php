<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReverseEngineeringJob;
use App\Models\Theme;
use App\Models\ThemePack;
use App\Services\ThemeBuilder\ReverseEngineering\VisualReverseEngineeringService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * TB3 Sprint 9.5 — Visual Reverse Engineering Engine Controller
 *
 * Flow:
 *   1. GET  /form          → input selection (URL / Screenshot / HTML / Prompt)
 *   2. POST /analyze       → AJAX: run all 7 phases → return job + scores
 *   3. GET  /{job}/result  → detailed result page
 *   4. GET  /{job}/export  → download JSON pack
 *   5. POST /{job}/install → create Theme from pack → open canvas
 */
class ThemeReverseEngineeringController extends Controller
{
    public function __construct(
        protected VisualReverseEngineeringService $service
    ) {}

    // ── Step 0: Form ──────────────────────────────────────────────────────────

    /** GET /admin/theme-builder/reverse-engineer */
    public function form()
    {
        $enabled         = true;
        $hasAiKey        = $this->service->isEnabled();
        $headlessReady   = $this->service->isHeadlessAvailable();
        $headlessReport  = $this->service->headlessAvailabilityReport();
        $recentJobs      = ReverseEngineeringJob::latest()->limit(6)->get();

        return view('admin.theme-builder.reverse-engineer', compact(
            'enabled', 'hasAiKey', 'headlessReady', 'headlessReport', 'recentJobs'
        ));
    }

    // ── Step 1: Analyze (AJAX, runs all 7 phases) ────────────────────────────

    /** POST /admin/theme-builder/reverse-engineer/analyze */
    public function analyze(Request $request)
    {
        $data = $request->validate([
            'input_type'     => 'required|in:url,screenshot,html,prompt',
            'url'            => 'required_if:input_type,url|nullable|url|max:500',
            'screenshot'     => 'required_if:input_type,screenshot|nullable|file|image|max:10240',
            'html_file'      => 'required_if:input_type,html|nullable|file|mimes:html,htm,txt|max:5120',
            'prompt'         => 'required_if:input_type,prompt|nullable|string|max:2000',
            'notes'          => 'nullable|string|max:1000',
            'name'           => 'nullable|string|max:255',
            'force_headless' => 'nullable|boolean',
        ]);

        try {
            $type         = $data['input_type'];
            $notes        = $data['notes']          ?? '';
            $name         = $data['name']           ?? '';
            $forceHeadless= (bool) ($data['force_headless'] ?? false);

            $job = match ($type) {
                'url' => $this->service->fromUrl($data['url'], $name, $notes, $forceHeadless),

                'screenshot' => (function () use ($request, $name, $notes) {
                    $file   = $request->file('screenshot');
                    $raw    = base64_encode(file_get_contents($file->getRealPath()));
                    $mime   = $file->getMimeType() ?? 'image/jpeg';
                    return $this->service->fromScreenshot($raw, $mime, $name, $notes);
                })(),

                'html' => (function () use ($request, $name, $notes) {
                    $content = file_get_contents($request->file('html_file')->getRealPath());
                    return $this->service->fromHtml($content, $name, $notes);
                })(),

                'prompt' => $this->service->fromPrompt($data['prompt'] ?? '', $name),
            };

            $scores = $job->similarity_scores ?? [];

            return response()->json([
                'success'          => true,
                'job_id'           => $job->id,
                'status'           => $job->status,
                'suggested_name'   => $job->suggested_name,
                'best_match'       => $scores['best_match_label'] ?? '',
                'best_score'       => $scores['best_match_score'] ?? 0,
                'overall_score'    => $scores['overall'] ?? 0,
                'quality_verdict'  => $job->quality_report['verdict'] ?? 'UNKNOWN',
                'quality_score'    => $job->quality_report['score'] ?? 0,
                'extraction_mode'  => $job->extraction_mode,
                'is_headless'      => $job->isHeadless(),
                'result_url'       => route('admin.theme-builder.reverse-engineer.result', $job),
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'خطا در تحلیل: ' . $e->getMessage(),
            ], 502);
        }
    }

    // ── Step 2: Result page ───────────────────────────────────────────────────

    /** GET /admin/theme-builder/reverse-engineer/{job}/result */
    public function result(ReverseEngineeringJob $job)
    {
        $themePack = $job->theme_pack_id
            ? ThemePack::find($job->theme_pack_id)
            : null;

        return view('admin.theme-builder.reverse-engineer-result', compact('job', 'themePack'));
    }

    // ── Export ────────────────────────────────────────────────────────────────

    /** GET /admin/theme-builder/reverse-engineer/{job}/export */
    public function export(ReverseEngineeringJob $job)
    {
        if (!$job->isComplete() || !$job->theme_pack_id) {
            return back()->with('error', 'تکمیل نشده است.');
        }

        $pack     = ThemePack::findOrFail($job->theme_pack_id);
        $export   = $pack->toExportArray();
        $filename = 're-pack-' . ($pack->uuid ?? Str::random(8)) . '.json';

        // Append RE metadata
        $export['_re_metadata'] = [
            'engine'       => 'Visual Reverse Engineering Engine v1.0 (TB3 Sprint 9.5)',
            'input_type'   => $job->input_type,
            'best_match'   => $job->similarity_scores['best_match'] ?? '',
            'best_score'   => $job->similarity_scores['best_match_score'] ?? 0,
            'quality'      => $job->quality_report['verdict'] ?? 'UNKNOWN',
            'generated_at' => now()->toISOString(),
        ];

        return response()->json($export, 200, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ── Install as Theme ──────────────────────────────────────────────────────

    /** POST /admin/theme-builder/reverse-engineer/{job}/install */
    public function install(ReverseEngineeringJob $job)
    {
        if (!$job->isComplete() || !$job->theme_pack_id) {
            return back()->with('error', 'پک آماده نیست.');
        }

        $pack     = ThemePack::findOrFail($job->theme_pack_id);
        $packData = $pack->pack_data ?? [];

        $headerBlocks = $packData['headers'][0]['blocks'] ?? [];
        $footerBlocks = $packData['footers'][0]['blocks'] ?? [];
        $pages = [];

        foreach ($packData['pages'] ?? [] as $page) {
            $slug        = $page['slug'] === '' ? 'home' : ($page['slug'] ?? 'home');
            $pages[$slug] = [
                'title'  => $page['title'] ?? 'صفحه اصلی',
                'slug'   => $page['slug'] ?? '',
                'blocks' => $page['blocks'] ?? [],
            ];
        }

        if (empty($pages)) {
            $pages['home'] = ['title' => 'صفحه اصلی', 'slug' => '', 'blocks' => []];
        }

        $bestMatch = $job->similarity_scores['best_match_label'] ?? 'RE';
        $theme = Theme::create([
            'name'          => $pack->name,
            'slug'          => Str::slug($pack->name) . '-re-' . Str::lower(Str::random(6)),
            'description'   => "بازسازی‌شده با مهندسی معکوس — شباهت با: {$bestMatch}",
            'source'        => Theme::SOURCE_GENERATOR,
            'status'        => Theme::STATUS_DRAFT,
            'site_id'       => null,
            'header_blocks' => $headerBlocks,
            'footer_blocks' => $footerBlocks,
            'pages'         => $pages,
            'global_styles' => $packData['global_styles'] ?? [],
            'settings'      => ['responsive' => $packData['responsive_settings'] ?? []],
        ]);

        return redirect()
            ->route('admin.theme-builder.canvas', $theme)
            ->with('success', "قالب «{$theme->name}» از موتور مهندسی معکوس نصب شد. شباهت با {$bestMatch}.");
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    /** DELETE /admin/theme-builder/reverse-engineer/{job} */
    public function destroy(ReverseEngineeringJob $job)
    {
        if ($job->theme_pack_id) {
            ThemePack::find($job->theme_pack_id)?->delete();
        }
        $job->delete();

        return response()->json(['success' => true]);
    }
}
