<?php

namespace SaaSPlatform\Modules\Crm\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use SaaSPlatform\Modules\Crm\Services\CrmCsvExportService;
use SaaSPlatform\Modules\Crm\Services\CrmCsvImportService;
use SaaSPlatform\Modules\Crm\Services\CrmSalesReportService;

class CrmImportExportController extends Controller
{
    private const VALID_ENTITIES = ['contact', 'company', 'lead'];

    public function showImport(int $siteId, string $entity, CrmCsvImportService $service)
    {
        abort_unless(in_array($entity, self::VALID_ENTITIES, true), 404);

        return view('crm::admin.import.show', [
            'siteId' => $siteId, 'entity' => $entity, 'columns' => $service->requiredColumns($entity),
        ]);
    }

    /**
     * Phase 20 — Part 5: uploads the file to a temp disk path and shows
     * a Preview — nothing is written to crm_* tables at this step.
     */
    public function preview(Request $request, int $siteId, string $entity, CrmCsvImportService $service)
    {
        abort_unless(in_array($entity, self::VALID_ENTITIES, true), 404);

        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $path = $request->file('file')->store('crm-imports', 'local');
        $result = $service->preview($entity, $path, $siteId);

        return view('crm::admin.import.preview', [
            'siteId' => $siteId, 'entity' => $entity, 'path' => $path,
            'header' => $result['header'], 'rows' => $result['rows'], 'error' => $result['error'] ?? null,
        ]);
    }

    public function commit(Request $request, int $siteId, string $entity, CrmCsvImportService $service)
    {
        abort_unless(in_array($entity, self::VALID_ENTITIES, true), 404);

        $data = $request->validate(['path' => 'required|string']);
        abort_unless(Storage::disk('local')->exists($data['path']), 404, 'فایل موقت یافت نشد — دوباره آپلود کنید.');

        $counts = $service->commit($entity, $data['path'], $siteId);
        Storage::disk('local')->delete($data['path']);

        $routeName = match ($entity) {
            'contact' => 'crm.admin.contacts.index',
            'company' => 'crm.admin.companies.index',
            'lead' => 'crm.admin.leads.index',
        };

        return redirect()->route($routeName, $siteId)->with(
            'success',
            "وارد شد: {$counts['created']} — تکراری رد شد: {$counts['skipped_duplicate']} — نامعتبر رد شد: {$counts['skipped_invalid']}"
        );
    }

    public function exportContacts(int $siteId, CrmCsvExportService $service)
    {
        return $this->csvDownload($service->exportContacts($siteId), 'crm-contacts.csv');
    }

    public function exportOpportunities(int $siteId, CrmCsvExportService $service)
    {
        return $this->csvDownload($service->exportOpportunities($siteId), 'crm-opportunities.csv');
    }

    public function exportReport(int $siteId, Request $request, CrmSalesReportService $reportService, CrmCsvExportService $exportService)
    {
        $report = $reportService->build($siteId, $request->integer('pipeline_id') ?: null);

        return $this->csvDownload($exportService->exportReport($report), 'crm-sales-report.csv');
    }

    private function csvDownload(string $csv, string $filename)
    {
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
