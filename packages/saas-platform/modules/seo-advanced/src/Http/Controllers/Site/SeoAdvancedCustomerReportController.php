<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Http\Controllers\Site;

use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\SeoAdvanced\Services\SeoReportService;

/**
 * Phase 18-D — Part 5: customer-facing, read-only SEO report. Deliberately
 * takes NO site_id route parameter — matching the Tickets module's own
 * Site (customer portal) convention (see Routes/site.php) — so there is no
 * URL a customer could edit to view another site's report. Just like the
 * runtime adapters (see SeoOverrideProviderAdapter's own docblock), this
 * fixed constant is the single point that would need to change if
 * customer-site ever became multi-site itself.
 */
class SeoAdvancedCustomerReportController extends Controller
{
    private const SINGLE_TENANT_SITE_ID = 1;

    public function show(SeoReportService $reportService)
    {
        $report = $reportService->build(self::SINGLE_TENANT_SITE_ID);

        return view('seo-advanced::site.report', $report);
    }
}
