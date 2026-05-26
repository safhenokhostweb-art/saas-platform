<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Sandbox;

use Illuminate\Support\Facades\Cache;

class ModuleResourceLimiter
{
    private const WINDOW_SECONDS = 60;
    private const BUDGET_PREFIX  = 'plugin.resource.budget';

    private const DEFAULT_LIMITS = [
        'queue_jobs_per_minute'   => 10,
        'http_requests_per_minute'=> 20,
        'storage_uploads_per_hour'=> 5,
        'max_execution_ms'        => 30000,
    ];

    public function __construct(
        private array $limits = []
    ) {
        $this->limits = array_merge(self::DEFAULT_LIMITS, $limits);
    }

    public function canDispatchJob(string $moduleId): bool
    {
        return $this->checkBudget($moduleId, 'queue_jobs', $this->limits['queue_jobs_per_minute']);
    }

    public function canMakeHttpRequest(string $moduleId): bool
    {
        return $this->checkBudget($moduleId, 'http_requests', $this->limits['http_requests_per_minute']);
    }

    public function canUpload(string $moduleId): bool
    {
        $window = floor(time() / 3600);
        $key    = self::BUDGET_PREFIX . ".{$moduleId}.storage_uploads.{$window}";
        $count  = (int) Cache::get($key, 0);
        return $count < $this->limits['storage_uploads_per_hour'];
    }

    public function recordJobDispatched(string $moduleId): void
    {
        $this->increment($moduleId, 'queue_jobs');
    }

    public function recordHttpRequest(string $moduleId): void
    {
        $this->increment($moduleId, 'http_requests');
    }

    public function recordUpload(string $moduleId): void
    {
        $window = floor(time() / 3600);
        $key    = self::BUDGET_PREFIX . ".{$moduleId}.storage_uploads.{$window}";
        Cache::increment($key);
        Cache::put($key, (int) Cache::get($key, 1), 3600);
    }

    public function snapshot(string $moduleId): array
    {
        $window = floor(time() / self::WINDOW_SECONDS);

        return [
            'queue_jobs'    => (int) Cache::get(self::BUDGET_PREFIX . ".{$moduleId}.queue_jobs.{$window}", 0),
            'http_requests' => (int) Cache::get(self::BUDGET_PREFIX . ".{$moduleId}.http_requests.{$window}", 0),
            'limits'        => $this->limits,
        ];
    }

    private function checkBudget(string $moduleId, string $resource, int $limit): bool
    {
        $window = floor(time() / self::WINDOW_SECONDS);
        $key    = self::BUDGET_PREFIX . ".{$moduleId}.{$resource}.{$window}";
        return (int) Cache::get($key, 0) < $limit;
    }

    private function increment(string $moduleId, string $resource): void
    {
        $window = floor(time() / self::WINDOW_SECONDS);
        $key    = self::BUDGET_PREFIX . ".{$moduleId}.{$resource}.{$window}";
        Cache::increment($key);
        Cache::put($key, (int) Cache::get($key, 1), self::WINDOW_SECONDS + 10);
    }
}
