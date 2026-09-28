<?php

namespace SaaSPlatform\ThemeRuntime\DataBinding;

use Closure;
use Throwable;

/**
 * Per-renderer data coordinator. Hosts inject controlled resolvers; this
 * package never performs arbitrary network or tenant-database access.
 */
final class DataFetcher
{
    private ?Closure $apiResolver;
    private ?Closure $internalResolver;
    private array $results = [];

    public function __construct(?callable $apiResolver = null, ?callable $internalResolver = null)
    {
        $this->apiResolver = $apiResolver !== null ? Closure::fromCallable($apiResolver) : null;
        $this->internalResolver = $internalResolver !== null ? Closure::fromCallable($internalResolver) : null;
    }

    public function fetch(array $rawConfig, string $widgetId = ''): DataFetchResult
    {
        try {
            $config = DataSourceConfig::fromArray($rawConfig);
        } catch (Throwable) {
            return DataFetchResult::error();
        }

        $cacheKey = hash('sha256', $widgetId.'|'.json_encode($config->toArray(), JSON_UNESCAPED_SLASHES));
        if (isset($this->results[$cacheKey])) {
            return $this->results[$cacheKey];
        }

        if ($config->sourceType === 'static') {
            return $this->results[$cacheKey] = DataFetchResult::success($this->staticItems($rawConfig['static_items'] ?? []));
        }

        $resolver = $config->sourceType === 'api' ? $this->apiResolver : $this->internalResolver;
        if ($resolver === null) {
            return $this->results[$cacheKey] = DataFetchResult::loading();
        }

        try {
            $resolved = $resolver($config, $widgetId);
            if ($resolved instanceof DataFetchResult) {
                return $this->results[$cacheKey] = $resolved;
            }
            return $this->results[$cacheKey] = is_array($resolved)
                ? DataFetchResult::success($resolved)
                : DataFetchResult::error();
        } catch (Throwable) {
            return $this->results[$cacheKey] = DataFetchResult::error();
        }
    }

    private function staticItems(mixed $items): array
    {
        if (is_string($items)) {
            try {
                $items = json_decode($items, true, 4, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                return [];
            }
        }
        if (!is_array($items) || !array_is_list($items)) {
            return [];
        }
        return array_values(array_filter(array_slice($items, 0, 50), 'is_array'));
    }
}
