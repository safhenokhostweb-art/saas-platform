<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\RobotsBuilderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\SchemaBuilderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\SitemapBuilderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedAudit;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedKeyword;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedRedirect;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedSetting;
use SaaSPlatform\Modules\SeoAdvanced\Services\SchemaBuilder;
use SaaSPlatform\Modules\SeoAdvanced\Services\SeoAuditor;
use SaaSPlatform\Modules\SeoAdvanced\Services\SeoReportService;
use SaaSPlatform\Modules\SeoAdvanced\Services\SitemapUrlCollector;

/**
 * Phase 18 — Part 7: the module's own, fully self-contained admin panel.
 * Deliberately takes a plain int $siteId route parameter rather than an
 * App\Models\Site binding — this module never references any Core
 * Eloquent model (see forms/tickets for the same convention), so it stays
 * usable standalone once packaged and deployed to a customer site where
 * Core's Site model doesn't even exist in that process.
 */
class SeoAdvancedAdminController extends Controller
{
    public function dashboard(int $siteId, SeoAuditor $auditor)
    {
        $settings = SeoAdvancedSetting::where('site_id', $siteId)->first();
        $redirectCount = SeoAdvancedRedirect::where('site_id', $siteId)->count();
        $latestAudit = SeoAdvancedAudit::where('site_id', $siteId)->latest('audited_at')->first();
        $technical = $auditor->technicalChecks($siteId);

        return view('seo-advanced::admin.dashboard', compact('siteId', 'settings', 'redirectCount', 'latestAudit', 'technical'));
    }

    public function metaEdit(int $siteId)
    {
        $settings = SeoAdvancedSetting::where('site_id', $siteId)->first();

        return view('seo-advanced::admin.meta', compact('siteId', 'settings'));
    }

    public function metaUpdate(Request $request, int $siteId)
    {
        $data = $request->validate([
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:500',
            'robots_directive' => 'nullable|string|max:100',
            'canonical_url' => 'nullable|string|max:500',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string|max:500',
            'og_image_url' => 'nullable|string|max:500',
            'twitter_card_type' => 'nullable|in:summary,summary_large_image',
            'canonical_strategy' => 'nullable|in:auto,force_https,custom',
            'title_min_length' => 'nullable|integer|min:1|max:65535',
            'title_max_length' => 'nullable|integer|min:1|max:65535|gte:title_min_length',
            'description_min_length' => 'nullable|integer|min:1|max:65535',
            'description_max_length' => 'nullable|integer|min:1|max:65535|gte:description_min_length',
            'schema_type' => 'required|in:organization,local_business,website',
            'organization_name' => 'nullable|string|max:255',
            'organization_logo_url' => 'nullable|string|max:500',
            'business_address' => 'nullable|string|max:500',
            'business_phone' => 'nullable|string|max:64',
        ]);

        SeoAdvancedSetting::updateOrCreate(['site_id' => $siteId], $data);

        return redirect()->route('seo-advanced.admin.meta', $siteId)->with('success', 'تنظیمات SEO ذخیره شد.');
    }

    public function sitemap(int $siteId, SitemapBuilderInterface $sitemapBuilder, SitemapUrlCollector $collector)
    {
        $urls = $collector->collect($siteId);
        $xml = $sitemapBuilder->generate($urls);

        return view('seo-advanced::admin.sitemap', compact('siteId', 'xml', 'urls'));
    }

    public function robotsPreview(int $siteId, RobotsBuilderInterface $robotsBuilder)
    {
        $content = $robotsBuilder->generate($siteId, url("/seo-advanced/{$siteId}/sitemap.xml"));

        return view('seo-advanced::admin.robots', compact('siteId', 'content'));
    }

    public function schemaPreview(int $siteId, SchemaBuilderInterface $schemaBuilder)
    {
        $schema = $schemaBuilder->build($siteId);
        $supportedTypes = SchemaBuilder::SUPPORTED_TYPES;

        return view('seo-advanced::admin.schema', compact('siteId', 'schema', 'supportedTypes'));
    }

    /**
     * Phase 18-E — Part 5: lets an admin try any of the 8 supported
     * schema types against arbitrary JSON data — the same buildType()
     * a future module would call programmatically.
     */
    public function schemaBuilderPreview(Request $request, int $siteId, SchemaBuilderInterface $schemaBuilder)
    {
        $data = $request->validate([
            'type' => 'required|string',
            'payload' => 'nullable|string',
        ]);

        $payload = json_decode($data['payload'] ?? '{}', true);
        $error = null;
        $built = [];

        if (!is_array($payload)) {
            $error = 'JSON نامعتبر است.';
        } else {
            $built = $schemaBuilder->buildType($data['type'], $payload);
            if (empty($built)) {
                $error = 'فیلدهای الزامی این نوع Schema کامل نیست.';
            }
        }

        $schema = $schemaBuilder->build($siteId);
        $supportedTypes = SchemaBuilder::SUPPORTED_TYPES;

        return view('seo-advanced::admin.schema', compact('siteId', 'schema', 'supportedTypes', 'built', 'error'))
            ->with(['selectedType' => $data['type'], 'payloadInput' => $data['payload'] ?? '']);
    }

    public function redirects(int $siteId)
    {
        $redirects = SeoAdvancedRedirect::where('site_id', $siteId)->latest()->get();

        return view('seo-advanced::admin.redirects', compact('siteId', 'redirects'));
    }

    public function storeRedirect(Request $request, int $siteId)
    {
        $data = $request->validate([
            'from_path' => 'required|string|max:500',
            'to_path' => 'required|string|max:500',
            'status_code' => 'required|integer|in:301,302',
        ]);
        $data['site_id'] = $siteId;
        $data['is_active'] = true;

        SeoAdvancedRedirect::create($data);

        return redirect()->route('seo-advanced.admin.redirects', $siteId)->with('success', 'ریدایرکت ثبت شد.');
    }

    public function editRedirect(int $siteId, SeoAdvancedRedirect $redirect)
    {
        abort_unless($redirect->site_id === $siteId, 404);

        return view('seo-advanced::admin.redirect-edit', compact('siteId', 'redirect'));
    }

    public function updateRedirect(Request $request, int $siteId, SeoAdvancedRedirect $redirect)
    {
        abort_unless($redirect->site_id === $siteId, 404);

        $data = $request->validate([
            'from_path' => 'required|string|max:500',
            'to_path' => 'required|string|max:500',
            'status_code' => 'required|integer|in:301,302',
            'is_active' => 'nullable|boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        $redirect->update($data);

        return redirect()->route('seo-advanced.admin.redirects', $siteId)->with('success', 'ریدایرکت بروزرسانی شد.');
    }

    public function destroyRedirect(int $siteId, SeoAdvancedRedirect $redirect)
    {
        abort_unless($redirect->site_id === $siteId, 404);
        $redirect->delete();

        return redirect()->route('seo-advanced.admin.redirects', $siteId)->with('success', 'ریدایرکت حذف شد.');
    }

    public function audit(int $siteId)
    {
        $audits = SeoAdvancedAudit::where('site_id', $siteId)->latest('audited_at')->paginate(20);

        return view('seo-advanced::admin.audit', compact('siteId', 'audits'));
    }

    public function runAudit(Request $request, int $siteId, SeoAuditor $auditor)
    {
        $data = $request->validate([
            'page_url' => 'required|string|max:500',
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'heading' => 'nullable|string',
            'canonical' => 'nullable|string',
            'image_alt_texts' => 'nullable|array',
            'headings_raw' => 'nullable|string',
            'og_title' => 'nullable|string',
            'og_description' => 'nullable|string',
            'og_image' => 'nullable|string',
            'check_url_structure' => 'nullable|boolean',
        ]);

        $headings = $this->parseHeadingsInput($data['headings_raw'] ?? null);

        $og = null;
        if (!empty($data['og_title']) || !empty($data['og_description']) || !empty($data['og_image'])) {
            $og = [
                'title' => $data['og_title'] ?? null,
                'description' => $data['og_description'] ?? null,
                'image' => $data['og_image'] ?? null,
            ];
        }

        $urlPath = $request->boolean('check_url_structure') ? parse_url($data['page_url'], PHP_URL_PATH) ?? $data['page_url'] : null;

        $auditor->auditAndStore(
            $siteId,
            $data['page_url'],
            $data['title'] ?? null,
            $data['description'] ?? null,
            $data['image_alt_texts'] ?? [],
            $data['heading'] ?? null,
            $data['canonical'] ?? null,
            $headings,
            $og,
            $urlPath,
        );

        return redirect()->route('seo-advanced.admin.audit', $siteId)->with('success', 'ارزیابی SEO ثبت شد.');
    }

    public function report(int $siteId, SeoReportService $reportService)
    {
        $report = $reportService->build($siteId);

        return view('seo-advanced::admin.report', array_merge(['siteId' => $siteId], $report));
    }

    public function keywords(int $siteId)
    {
        $keywords = SeoAdvancedKeyword::where('site_id', $siteId)->latest()->paginate(20);

        return view('seo-advanced::admin.keywords', compact('siteId', 'keywords'));
    }

    public function storeKeyword(Request $request, int $siteId)
    {
        $data = $request->validate([
            'keyword' => 'required|string|max:255',
            'target_url' => 'nullable|string|max:500',
            'status' => 'nullable|in:active,paused,archived',
        ]);
        $data['site_id'] = $siteId;
        $data['status'] = $data['status'] ?? SeoAdvancedKeyword::STATUS_ACTIVE;

        SeoAdvancedKeyword::create($data);

        return redirect()->route('seo-advanced.admin.keywords', $siteId)->with('success', 'کلمه کلیدی ثبت شد.');
    }

    public function recordKeywordPosition(Request $request, int $siteId, SeoAdvancedKeyword $keyword)
    {
        abort_unless($keyword->site_id === $siteId, 404);

        $data = $request->validate([
            'position' => 'required|integer|min:1|max:1000',
            'date' => 'nullable|date',
        ]);

        $keyword->recordPosition($data['position'], $data['date'] ?? null);

        return redirect()->route('seo-advanced.admin.keywords', $siteId)->with('success', 'رتبه کلمه کلیدی ثبت شد.');
    }

    public function destroyKeyword(int $siteId, SeoAdvancedKeyword $keyword)
    {
        abort_unless($keyword->site_id === $siteId, 404);
        $keyword->delete();

        return redirect()->route('seo-advanced.admin.keywords', $siteId)->with('success', 'کلمه کلیدی حذف شد.');
    }

    /**
     * Phase 18-D — Part 3: admins paste one heading per line as "level|text"
     * (e.g. "1|عنوان اصلی") — a simple textarea rather than a full HTML
     * parser, matching this engine's deliberately deterministic, non-crawler
     * design (see SeoAuditor's docblock).
     *
     * @return array<int, array{level: int, text: string}>|null
     */
    private function parseHeadingsInput(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $headings = [];
        foreach (preg_split('/\r?\n/', trim($raw)) as $line) {
            if (trim($line) === '') {
                continue;
            }
            [$level, $text] = array_pad(explode('|', $line, 2), 2, '');
            $headings[] = ['level' => (int) trim($level), 'text' => trim($text)];
        }

        return $headings;
    }
}
