<?php
namespace App\Console\Commands;

use App\Services\Infrastructure\OtelExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportTelemetryOtlpCommand extends Command
{
    protected $signature = 'telemetry:export-otlp
                            {--from=   : Export events from this datetime (Y-m-d or Y-m-d H:i:s)}
                            {--limit=500 : Max events per run}
                            {--batch=100 : Events per HTTP POST}
                            {--dry-run  : Print what would be exported without sending}';

    protected $description = 'Export telemetry_events to OpenTelemetry collector (Jaeger/Tempo) via OTLP/HTTP';

    public function handle(OtelExportService $otel): int
    {
        $dryRun  = $this->option('dry-run');
        $limit   = max(1, (int) $this->option('limit'));
        $batch   = max(1, (int) $this->option('batch'));
        $from    = $this->option('from');

        if (!$otel->isEnabled() && !$dryRun) {
            $this->warn('OTEL_EXPORTER_OTLP_ENDPOINT is not set. Nothing to export.');
            $this->line('  Add OTEL_EXPORTER_OTLP_ENDPOINT=http://jaeger:4318 to .env');
            return self::SUCCESS;
        }

        $query = DB::table('telemetry_events')
            ->orderBy('created_at')
            ->limit($limit);

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        $events = $query->get()->toArray();

        if (empty($events)) {
            $this->info('No telemetry events found.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d events to export (batch size: %d)', count($events), $batch));

        if ($dryRun) {
            $this->table(
                ['ID', 'Module', 'Event', 'Severity', 'Timestamp'],
                array_map(fn ($e) => [$e->id, $e->module, $e->event, $e->severity, $e->timestamp], array_slice($events, 0, 10))
            );
            $this->line(count($events) > 10 ? '... and ' . (count($events) - 10) . ' more' : '');
            return self::SUCCESS;
        }

        $chunks  = array_chunk($events, $batch);
        $total   = 0;

        foreach ($chunks as $i => $chunk) {
            $shipped = $otel->exportBatch($chunk);
            $total  += $shipped;
            $this->line(sprintf('  Batch %d/%d: %d/%d shipped', $i + 1, count($chunks), $shipped, count($chunk)));
        }

        $this->info(sprintf('Export complete: %d/%d events shipped to OTLP endpoint.', $total, count($events)));
        return self::SUCCESS;
    }
}
