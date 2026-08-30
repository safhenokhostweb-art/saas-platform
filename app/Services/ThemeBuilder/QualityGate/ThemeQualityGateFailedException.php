<?php
namespace App\Services\ThemeBuilder\QualityGate;

use App\Models\ThemeQualityReport;
use RuntimeException;

/**
 * Thrown by ThemeCommercialQualityGate::publishIfCompliant() when a
 * blocking check fails. Carries the full report so a caller (an Admin
 * approval UI, a CI job, a console command) can show exactly what failed
 * without re-running the gate.
 */
class ThemeQualityGateFailedException extends RuntimeException
{
    public function __construct(public readonly ThemeQualityReport $report)
    {
        $codes = implode(', ', array_column($report->blockingFailures(), 'code'));
        parent::__construct("Theme quality gate failed for version {$report->version}: {$codes}");
    }
}
