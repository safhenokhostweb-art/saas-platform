<?php

namespace SaaSPlatform\ThemeRuntime\DataBinding;

use InvalidArgumentException;

/** Immutable, fail-closed data-source configuration for one widget render. */
final class DataSourceConfig
{
    private const TYPES = ['static', 'api', 'internal'];
    private const FORBIDDEN_KEYS = ['__proto__', 'prototype', 'constructor'];

    public function __construct(
        public readonly string $sourceType,
        public readonly string $mode,
        public readonly string $endpointUrl,
        public readonly array $queryParams,
    ) {
    }

    public static function fromArray(array $value): self
    {
        $sourceType = is_string($value['source_type'] ?? null)
            ? strtolower(trim($value['source_type']))
            : 'static';
        if (!in_array($sourceType, self::TYPES, true)) {
            throw new InvalidArgumentException('Unknown data-source type.');
        }

        $endpoint = is_string($value['endpoint_url'] ?? null) ? trim($value['endpoint_url']) : '';
        if (strlen($endpoint) > 2048) {
            throw new InvalidArgumentException('Data-source endpoint is too long.');
        }
        if ($sourceType === 'api') {
            self::assertSafeApiEndpoint($endpoint);
        } elseif ($sourceType === 'internal') {
            if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $endpoint) !== 1) {
                throw new InvalidArgumentException('Internal data-source identifier is invalid.');
            }
        } else {
            $endpoint = '';
        }

        $query = self::normalizeQuery($value['query_params'] ?? []);

        return new self(
            $sourceType,
            match ($sourceType) {
                'api' => 'api_endpoint',
                'internal' => 'internal_query',
                default => 'static',
            },
            $endpoint,
            $query,
        );
    }

    public function toArray(): array
    {
        return [
            'source_type' => $this->sourceType,
            'endpoint_url' => $this->endpointUrl,
            'query_params' => $this->queryParams,
        ];
    }

    private static function normalizeQuery(mixed $value): array
    {
        if (is_string($value)) {
            if (strlen($value) > 8192) {
                throw new InvalidArgumentException('Data-source query is too long.');
            }
            if (trim($value) === '') {
                return [];
            }
            try {
                $value = json_decode($value, true, 4, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new InvalidArgumentException('Data-source query must be a JSON object.');
            }
        }
        // json_decode('{}', true) is the same empty PHP array as decoded
        // '[]'; accept the empty value as the canonical empty object while
        // still rejecting every non-empty JSON list.
        if (!is_array($value) || ($value !== [] && array_is_list($value)) || count($value) > 32) {
            throw new InvalidArgumentException('Data-source query must be a bounded JSON object.');
        }

        return self::sanitizeObject($value, 0);
    }

    private static function sanitizeObject(array $value, int $depth): array
    {
        if ($depth > 3) {
            throw new InvalidArgumentException('Data-source query is too deeply nested.');
        }
        $safe = [];
        foreach ($value as $key => $item) {
            if (!is_string($key) || preg_match('/^[a-z][a-z0-9_]{0,63}$/i', $key) !== 1
                || in_array(strtolower($key), self::FORBIDDEN_KEYS, true)) {
                throw new InvalidArgumentException('Data-source query contains an unsafe key.');
            }
            if (is_array($item)) {
                if (count($item) > 50) {
                    throw new InvalidArgumentException('Data-source query list is too large.');
                }
                $safe[$key] = array_is_list($item)
                    ? array_map(static fn (mixed $entry): string|int|float|bool|null => self::sanitizeScalar($entry), $item)
                    : self::sanitizeObject($item, $depth + 1);
            } else {
                $safe[$key] = self::sanitizeScalar($item);
            }
        }
        return $safe;
    }

    private static function sanitizeScalar(mixed $value): string|int|float|bool|null
    {
        if (!is_scalar($value) && $value !== null) {
            throw new InvalidArgumentException('Data-source query contains a non-scalar value.');
        }
        if (is_string($value)) {
            if (strlen($value) > 1024 || preg_match('/[<>]/', $value) === 1) {
                throw new InvalidArgumentException('Data-source query contains unsafe text.');
            }
        }
        return $value;
    }

    private static function assertSafeApiEndpoint(string $endpoint): void
    {
        $parts = parse_url($endpoint);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('API endpoints must be credential-free HTTPS URLs.');
        }
        $host = strtolower(rtrim((string) $parts['host'], '.'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            throw new InvalidArgumentException('Local API endpoints are not allowed.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new InvalidArgumentException('Private or reserved API endpoints are not allowed.');
        }
    }
}
