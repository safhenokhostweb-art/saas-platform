<?php

namespace SaaSPlatform\Modules\Crm\Services;

use SaaSPlatform\Modules\Crm\Models\CrmContact;
use SaaSPlatform\Modules\Crm\Models\CrmOpportunity;

/**
 * Phase 20 — Part 5: CSV Export for Contacts/Opportunities/Reports.
 * Returns a plain string (caller decides how to stream it) — kept
 * simple and dependency-free rather than pulling in a CSV library.
 */
class CrmCsvExportService
{
    public function exportContacts(int $siteId): string
    {
        $rows = CrmContact::where('site_id', $siteId)->with('company')->get();

        return $this->toCsv(
            ['first_name', 'last_name', 'email', 'phone', 'job_title', 'company_name', 'lifecycle_stage'],
            $rows->map(fn (CrmContact $c) => [
                $c->first_name, $c->last_name, $c->email, $c->phone, $c->job_title, $c->company?->name, $c->lifecycle_stage,
            ])
        );
    }

    public function exportOpportunities(int $siteId): string
    {
        $rows = CrmOpportunity::where('site_id', $siteId)->with(['contact', 'company', 'stage'])->get();

        return $this->toCsv(
            ['name', 'contact', 'company', 'stage', 'amount', 'status', 'expected_close_date'],
            $rows->map(fn (CrmOpportunity $o) => [
                $o->name, $o->contact?->full_name, $o->company?->name, $o->stage?->name, $o->amount, $o->status,
                optional($o->expected_close_date)->format('Y-m-d'),
            ])
        );
    }

    public function exportReport(array $report): string
    {
        $rows = [
            ['win_rate', $report['win_rate']],
            ['lost_rate', $report['lost_rate']],
            ['conversion_rate', $report['conversion_rate']],
            ['pipeline_value', $report['pipeline_value']],
            ['revenue_forecast', $report['revenue_forecast']],
            ['won_count', $report['won_count']],
            ['lost_count', $report['lost_count']],
            ['won_amount', $report['won_amount']],
        ];

        return $this->toCsv(['metric', 'value'], $rows);
    }

    private function toCsv(array $header, iterable $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $header);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
