<?php

namespace SaaSPlatform\CoreUtils\Search;

use Illuminate\Support\Facades\Cache;

class ContextualSearchIndex
{
    private const INDEX_PREFIX = 'search:index:';
    private const INDEX_TTL    = 3600;

    public function index(string $tenantId, string $resource, array $document): void
    {
        $indexKey = self::INDEX_PREFIX . "{$tenantId}:{$resource}";
        $index    = Cache::get($indexKey, []);

        $id = $document['id'] ?? null;
        if (! $id) {
            return;
        }

        $index[(string) $id] = [
            'id'         => $id,
            'text'       => $this->buildSearchText($document),
            'meta'       => $document['meta'] ?? [],
            'url'        => $document['url'] ?? null,
            'label'      => $document['label'] ?? $document['title'] ?? '',
            'indexed_at' => now()->toIso8601String(),
        ];

        if (count($index) > 1000) {
            $index = array_slice($index, -1000, 1000, true);
        }

        Cache::put($indexKey, $index, self::INDEX_TTL);
    }

    public function remove(string $tenantId, string $resource, int|string $id): void
    {
        $indexKey = self::INDEX_PREFIX . "{$tenantId}:{$resource}";
        $index    = Cache::get($indexKey, []);
        unset($index[(string) $id]);
        Cache::put($indexKey, $index, self::INDEX_TTL);
    }

    public function search(string $tenantId, string $resource, string $query, int $limit = 10): array
    {
        $indexKey = self::INDEX_PREFIX . "{$tenantId}:{$resource}";
        $index    = Cache::get($indexKey, []);

        if (empty($query) || strlen($query) < 2) {
            return array_slice(array_values($index), 0, $limit);
        }

        $lower   = mb_strtolower($query);
        $results = [];

        foreach ($index as $doc) {
            $text  = mb_strtolower($doc['text'] ?? '');
            $score = 0;

            if (str_starts_with($text, $lower)) {
                $score = 10;
            } elseif (str_contains($text, $lower)) {
                $score = 5;
            }

            if ($score > 0) {
                $results[] = array_merge($doc, ['relevance' => $score]);
            }
        }

        usort($results, fn ($a, $b) => $b['relevance'] <=> $a['relevance']);

        return array_slice($results, 0, $limit);
    }

    public function searchAll(string $tenantId, string $query, array $resources = [], int $limit = 20): array
    {
        $allResults = [];

        $indexedResources = $this->getIndexedResources($tenantId);
        $targets = empty($resources) ? $indexedResources : array_intersect($indexedResources, $resources);

        foreach ($targets as $resource) {
            $results = $this->search($tenantId, $resource, $query, (int) ceil($limit / max(count($targets), 1)));
            foreach ($results as $r) {
                $allResults[] = array_merge($r, ['resource_type' => $resource]);
            }
        }

        usort($allResults, fn ($a, $b) => ($b['relevance'] ?? 0) <=> ($a['relevance'] ?? 0));

        return array_slice($allResults, 0, $limit);
    }

    public function invalidate(string $tenantId, string $resource): void
    {
        Cache::forget(self::INDEX_PREFIX . "{$tenantId}:{$resource}");
    }

    public function getStats(string $tenantId): array
    {
        $resources = $this->getIndexedResources($tenantId);
        $stats     = [];

        foreach ($resources as $resource) {
            $index = Cache::get(self::INDEX_PREFIX . "{$tenantId}:{$resource}", []);
            $stats[$resource] = count($index);
        }

        return $stats;
    }

    private function buildSearchText(array $document): string
    {
        $parts = array_filter([
            $document['title']   ?? null,
            $document['label']   ?? null,
            $document['content'] ?? null,
            $document['tags']    ? implode(' ', (array) $document['tags']) : null,
        ]);

        return mb_strtolower(implode(' ', $parts));
    }

    private function getIndexedResources(string $tenantId): array
    {
        // Discover indexed resources via known prefix pattern
        $known = ['tickets', 'forms', 'submissions', 'pages', 'products'];
        return array_filter($known, fn ($r) =>
            Cache::has(self::INDEX_PREFIX . "{$tenantId}:{$r}")
        );
    }
}
