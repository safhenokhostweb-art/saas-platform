<?php

namespace App\Services\CustomerInstallation\Dto;

class PackageManifest
{
    public function __construct(
        public readonly string $version,
        public readonly string $sha256,
        public readonly int    $sizeBytes,
        public readonly string $releasedAt,
        public readonly string $minPhp,
        public readonly string $minMysql,
        public readonly array  $requiredExtensions,
        public readonly string $arvanKey,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            version:            $data['version'],
            sha256:             $data['sha256'],
            sizeBytes:          (int) $data['size_bytes'],
            releasedAt:         $data['released_at'],
            minPhp:             $data['min_php'] ?? '8.1',
            minMysql:           $data['min_mysql'] ?? '8.0',
            requiredExtensions: $data['required_extensions'] ?? [],
            arvanKey:           $data['arvan_key'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'version'             => $this->version,
            'sha256'              => $this->sha256,
            'size_bytes'          => $this->sizeBytes,
            'released_at'         => $this->releasedAt,
            'min_php'             => $this->minPhp,
            'min_mysql'           => $this->minMysql,
            'required_extensions' => $this->requiredExtensions,
            'arvan_key'           => $this->arvanKey,
        ];
    }
}
