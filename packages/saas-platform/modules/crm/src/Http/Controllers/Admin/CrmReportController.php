<?php

namespace SaaSPlatform\Modules\Crm\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Crm\Models\CrmPipeline;
use SaaSPlatform\Modules\Crm\Services\CrmSalesReportService;

class CrmReportController extends Controller
{
    public function show(int $siteId, Request $request, CrmSalesReportService $service)
    {
        $pipelineId = $request->integer('pipeline_id') ?: null;
        $report = $service->build($siteId, $pipelineId);

        $pipelines = CrmPipeline::where('site_id', $siteId)->orderBy('position')->get();

        return view('crm::admin.reports.show', array_merge(
            ['siteId' => $siteId, 'pipelines' => $pipelines, 'pipelineId' => $pipelineId],
            $report
        ));
    }
}
