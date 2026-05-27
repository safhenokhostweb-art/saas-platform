<?php

namespace SaaSPlatform\CoreUtils\AI\Observability;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AiInteractionTimeline
{
    private const TIMELINE_KEY = 'ai:timeline:';
    private const MAX_ENTRIES  = 200;
    private const TTL_DAYS     = 7;

    public function record(
        string $tenantId,
        string $interactionId,
        string $type,
        array  $data
    ): void {
        $key      = self::TIMELINE_KEY . $tenantId;
        $timeline = Cache::get($key, []);

        $timeline[] = [
            'id'        => $interactionId,
            'type'      => $type,
            'data'      => $data,
            'ts'        => now()->toISOString(),
        ];

        if (count($timeline) > self::MAX_ENTRIES) {
            $timeline = array_slice($timeline, -self::MAX_ENTRIES);
        }

        Cache::put($key, $timeline, 86400 * self::TTL_DAYS);
    }

    public function startInteraction(string $tenantId, string $domain, array $context = []): string
    {
        $id = Str::uuid()->toString();
        $this->record($tenantId, $id, 'started', [
            'domain'  => $domain,
            'context' => $context,
        ]);
        return $id;
    }

    public function completeInteraction(string $tenantId, string $id, bool $success, array $result = []): void
    {
        $this->record($tenantId, $id, 'completed', [
            'success'  => $success,
            'provider' => $result['provider'] ?? 'unknown',
            'tokens'   => $result['total_tokens'] ?? 0,
            'latency'  => $result['latency_ms'] ?? 0,
        ]);
    }

    public function recordUserAction(string $tenantId, string $id, string $action, array $meta = []): void
    {
        $this->record($tenantId, $id, "user:{$action}", $meta);
    }

    public function getTimeline(string $tenantId, int $limit = 50): array
    {
        $timeline = Cache::get(self::TIMELINE_KEY . $tenantId, []);
        return array_slice(array_reverse($timeline), 0, $limit);
    }

    public function getInteraction(string $tenantId, string $interactionId): array
    {
        $timeline = Cache::get(self::TIMELINE_KEY . $tenantId, []);
        return array_values(array_filter($timeline, fn ($e) => $e['id'] === $interactionId));
    }
}
