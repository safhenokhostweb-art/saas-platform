<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiGenerationJob;
use App\Models\ThemePack;
use App\Services\ThemeBuilder\ThemeAiGeneratorService;
use Illuminate\Http\Request;

class ThemeAiGeneratorController extends Controller
{
    public function __construct(protected ThemeAiGeneratorService $generator) {}

    // ── Step 0: Form ──────────────────────────────────────────────────────────

    /** GET /admin/theme-builder/ai-generator */
    public function form()
    {
        $enabled       = $this->generator->isEnabled();
        $providerLabel = $this->generator->getProviderLabel();
        $envKeyName    = $this->generator->getEnvKeyName();
        $recentJobs    = AiGenerationJob::latest()->limit(5)->get();

        return view('admin.theme-builder.ai-generator', compact(
            'enabled', 'providerLabel', 'envKeyName', 'recentJobs'
        ));
    }

    // ── Step 1: Detect (AJAX) ─────────────────────────────────────────────────

    /**
     * POST /admin/theme-builder/ai-generator/detect
     * Receives the raw input, detects sections, returns job + detected sections.
     */
    public function detect(Request $request)
    {
        // URL / HTML / Prompt detection is pure PHP — no API key needed.
        // Only screenshot detection needs the AI key (Claude Vision).
        if ($request->input('input_type') === 'screenshot' && !$this->generator->isEnabled()) {
            return response()->json([
                'success' => false,
                'error'   => $this->generator->getEnvKeyName() . ' تنظیم نشده — تحلیل تصویر نیاز به API key دارد.',
            ], 503);
        }

        $data = $request->validate([
            'input_type'  => 'required|in:url,screenshot,html,prompt',
            'url'         => 'required_if:input_type,url|nullable|url|max:500',
            'screenshot'  => 'required_if:input_type,screenshot|nullable|file|image|max:10240', // 10MB
            'html_file'   => 'required_if:input_type,html|nullable|file|mimes:html,htm,txt|max:5120', // 5MB
            'prompt'      => 'required_if:input_type,prompt|nullable|string|max:2000',
            'notes'       => 'nullable|string|max:1000',
            'name'        => 'nullable|string|max:255',
        ]);

        try {
            $type  = $data['input_type'];
            $notes = $data['notes'] ?? '';
            $name  = $data['name'] ?? '';

            $job = match ($type) {
                'url' => $this->generator->fromUrl(
                    $data['url'],
                    $notes,
                    $name,
                ),

                'screenshot' => (function () use ($request, $notes, $name) {
                    $file     = $request->file('screenshot');
                    $raw      = base64_encode(file_get_contents($file->getRealPath()));
                    $mimeType = $file->getMimeType() ?? 'image/jpeg';
                    return $this->generator->fromScreenshot($raw, $mimeType, $notes, $name);
                })(),

                'html' => (function () use ($request, $notes, $name) {
                    $file    = $request->file('html_file');
                    $content = file_get_contents($file->getRealPath());
                    return $this->generator->fromHtml($content, $notes, $name);
                })(),

                'prompt' => $this->generator->fromPrompt(
                    $data['prompt'] ?? '',
                    $name,
                ),
            };

            return response()->json([
                'success'          => true,
                'job_id'           => $job->id,
                'suggested_name'   => $job->suggested_name,
                'detected_sections'=> $job->detected_sections,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'خطا در تحلیل: ' . $e->getMessage(),
            ], 502);
        }
    }

    // ── Step 2: Generate (AJAX) ───────────────────────────────────────────────

    /**
     * POST /admin/theme-builder/ai-generator/{job}/generate
     * Receives selected sections + name → generates ThemePack.
     */
    public function generate(Request $request, AiGenerationJob $job)
    {
        if (!$this->generator->isEnabled()) {
            return response()->json([
                'success' => false,
                'error'   => $this->generator->getEnvKeyName() . ' تنظیم نشده — برای تولید قالب API key لازم است.',
            ], 503);
        }

        if (!in_array($job->status, [
            AiGenerationJob::STATUS_AWAITING,
            AiGenerationJob::STATUS_FAILED,
        ])) {
            return response()->json(['success' => false, 'error' => 'وضعیت job معتبر نیست.'], 422);
        }

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'sections' => 'required|array|min:1',
            'sections.*' => 'string|in:header,hero,features,services,pricing,faq,testimonials,blog,products,cta,footer',
        ]);

        try {
            $themePack = $this->generator->generateThemePack(
                $job,
                $data['sections'],
                $data['name'],
            );

            return response()->json([
                'success'       => true,
                'theme_pack_id' => $themePack->id,
                'pack_name'     => $themePack->name,
                'summary'       => $themePack->contentSummary(),
                'canvas_url'    => null, // ThemePack doesn't open in canvas; handled via Theme
                'result_url'    => route('admin.theme-builder.ai-generator.result', $job),
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'خطا در تولید: ' . $e->getMessage(),
            ], 502);
        }
    }

    // ── Step 3: Result page ───────────────────────────────────────────────────

    /** GET /admin/theme-builder/ai-generator/{job}/result */
    public function result(AiGenerationJob $job)
    {
        $themePack = $job->theme_pack_id
            ? ThemePack::find($job->theme_pack_id)
            : null;

        return view('admin.theme-builder.ai-generator-result', compact('job', 'themePack'));
    }

    // ── Export ────────────────────────────────────────────────────────────────

    /** GET /admin/theme-builder/ai-generator/{job}/export */
    public function export(AiGenerationJob $job)
    {
        if (!$job->isComplete() || !$job->theme_pack_id) {
            return back()->with('error', 'تولید هنوز کامل نشده است.');
        }

        $themePack = ThemePack::findOrFail($job->theme_pack_id);
        $export    = $themePack->toExportArray();
        $filename  = 'tb3-pack-' . $themePack->uuid . '.json';

        return response()->json($export, 200, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ── Install as Theme ──────────────────────────────────────────────────────

    /**
     * POST /admin/theme-builder/ai-generator/{job}/install
     * Creates a Theme from the ThemePack and opens it in the canvas editor.
     */
    public function install(AiGenerationJob $job)
    {
        if (!$job->isComplete() || !$job->theme_pack_id) {
            return back()->with('error', 'پک آماده نیست.');
        }

        $themePack = ThemePack::findOrFail($job->theme_pack_id);
        $packData  = $themePack->pack_data ?? [];

        // Extract first header/footer/page from pack
        $headerBlocks = $packData['headers'][0]['blocks'] ?? [];
        $footerBlocks = $packData['footers'][0]['blocks'] ?? [];
        $pages        = [];
        foreach ($packData['pages'] ?? [] as $page) {
            $slug         = $page['slug'] === '' ? 'home' : ($page['slug'] ?? 'home');
            $pages[$slug] = [
                'title'  => $page['title'] ?? 'صفحه اصلی',
                'slug'   => $page['slug'] ?? '',
                'blocks' => $page['blocks'] ?? [],
            ];
        }

        if (empty($pages)) {
            $pages['home'] = ['title' => 'صفحه اصلی', 'slug' => '', 'blocks' => []];
        }

        $theme = \App\Models\Theme::create([
            'name'          => $themePack->name,
            'slug'          => \Illuminate\Support\Str::slug($themePack->name) . '-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)),
            'description'   => $themePack->description,
            'source'        => \App\Models\Theme::SOURCE_GENERATOR,
            'status'        => \App\Models\Theme::STATUS_DRAFT,
            'site_id'       => null,
            'header_blocks' => $headerBlocks,
            'footer_blocks' => $footerBlocks,
            'pages'         => $pages,
            'global_styles' => $packData['global_styles'] ?? [],
            'settings'      => ['responsive' => $packData['responsive_settings'] ?? []],
        ]);

        return redirect()
            ->route('admin.theme-builder.canvas', $theme)
            ->with('success', "قالب «{$theme->name}» از پک AI نصب شد. اکنون می‌توانید ویرایش کنید.");
    }
}
