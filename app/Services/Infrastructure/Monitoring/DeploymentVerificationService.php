<?php
namespace App\Services\Infrastructure\Monitoring;

use App\Models\Infrastructure\InfraIncident;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * After every deployment, verifies that critical pages return 2xx.
 * Creates a critical incident if any endpoint returns 500/502/503.
 */
class DeploymentVerificationService
{
    private InfrastructureErrorCollector $collector;

    public function __construct(InfrastructureErrorCollector $collector)
    {
        $this->collector = $collector;
    }

    public function verify(string $baseUrl, ?int $siteId = null): array
    {
        $baseUrl  = rtrim($baseUrl, '/');
        $paths    = ['/', '/login', '/api/health'];
        $results  = [];
        $failures = [];

        foreach ($paths as $path) {
            $url = $baseUrl . $path;
            try {
                $response = Http::timeout(10)->withoutVerifying()->get($url);
                $status   = $response->status();
                $ok       = $status < 400;

                $results[$path] = ['status' => $status, 'ok' => $ok];

                if (in_array($status, [500, 502, 503], true)) {
                    $failures[] = "{$path} → HTTP {$status}";
                }
            } catch (\Throwable $e) {
                $results[$path] = ['status' => 0, 'ok' => false, 'error' => $e->getMessage()];
                $failures[]     = "{$path} → " . $e->getMessage();
            }
        }

        if (! empty($failures)) {
            $this->collector->collect(
                type:     'deploy_failure',
                title:    'Deploy Verification Failed',
                message:  'Failing endpoints: ' . implode(', ', $failures),
                siteId:   $siteId,
                severity: 'critical'
            );

            Log::critical('DeploymentVerification: failures detected', [
                'base_url' => $baseUrl,
                'failures' => $failures,
            ]);
        }

        return [
            'base_url' => $baseUrl,
            'results'  => $results,
            'failures' => $failures,
            'passed'   => empty($failures),
        ];
    }
}
