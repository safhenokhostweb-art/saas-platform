<?php

namespace SaaSPlatform\ThemeRuntime\DataBinding;

final class DataFetchResult
{
    public const LOADING = 'loading';
    public const EMPTY = 'empty';
    public const ERROR = 'error';
    public const SUCCESS = 'success';

    private function __construct(
        public readonly string $status,
        public readonly array $items = [],
        public readonly string $message = '',
    ) {
    }

    public static function loading(): self { return new self(self::LOADING); }
    public static function empty(): self { return new self(self::EMPTY); }
    public static function error(): self { return new self(self::ERROR, [], 'dynamic_data_unavailable'); }
    public static function success(array $items): self { return $items === [] ? self::empty() : new self(self::SUCCESS, array_values($items)); }
}
