<?php

namespace SaaSPlatform\CoreUtils\Payment\Connection;

final readonly class CountryResolution
{
    public function __construct(
        public ?string $country,
        public bool $trusted,
        public string $reason,
    ) {}

    public static function trusted(string $country): self
    {
        return new self($country, true, 'trusted');
    }

    public static function unknown(string $reason): self
    {
        return new self(null, false, $reason);
    }
}
