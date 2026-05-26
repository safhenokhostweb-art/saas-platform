<?php

namespace SaaSPlatform\CoreUtils\NodeCoordination;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NodeRegistry
{
    private const NODES_KEY    = 'node.registry.nodes';
    private const HEARTBEAT_PREFIX = 'node.heartbeat.';
    private const NODE_TTL     = 86400;
    private const HEARTBEAT_TTL = 120;
    private const STALE_THRESHOLD = 90; // seconds

    public function register(string $nodeId, array $metadata = []): void
    {
        $nodes = $this->all();
        $nodes[$nodeId] = array_merge($metadata, [
            'node_id'      => $nodeId,
            'registered_at' => now()->toIso8601String(),
            'hostname'     => $metadata['hostname'] ?? gethostname(),
            'role'         => $metadata['role'] ?? 'worker',
        ]);
        Cache::put(self::NODES_KEY, $nodes, self::NODE_TTL);
        $this->heartbeat($nodeId);
    }

    public function heartbeat(string $nodeId): void
    {
        Cache::put(self::HEARTBEAT_PREFIX . $nodeId, time(), self::HEARTBEAT_TTL);
    }

    public function deregister(string $nodeId): void
    {
        $nodes = $this->all();
        unset($nodes[$nodeId]);
        Cache::put(self::NODES_KEY, $nodes, self::NODE_TTL);
        Cache::forget(self::HEARTBEAT_PREFIX . $nodeId);
    }

    public function get(string $nodeId): ?array
    {
        return $this->all()[$nodeId] ?? null;
    }

    public function all(): array
    {
        return Cache::get(self::NODES_KEY, []);
    }

    public function activeNodes(): array
    {
        $threshold = time() - self::STALE_THRESHOLD;
        return array_filter($this->all(), function ($node) use ($threshold) {
            $lastSeen = Cache::get(self::HEARTBEAT_PREFIX . $node['node_id']);
            return $lastSeen !== null && $lastSeen >= $threshold;
        });
    }

    public function staleNodes(): array
    {
        $threshold = time() - self::STALE_THRESHOLD;
        return array_filter($this->all(), function ($node) use ($threshold) {
            $lastSeen = Cache::get(self::HEARTBEAT_PREFIX . $node['node_id']);
            return $lastSeen === null || $lastSeen < $threshold;
        });
    }

    public function count(): int
    {
        return count($this->all());
    }

    public function activeCount(): int
    {
        return count($this->activeNodes());
    }

    public static function currentNodeId(): string
    {
        return Cache::rememberForever('node.self_id', fn() => gethostname() . '-' . Str::uuid());
    }
}
