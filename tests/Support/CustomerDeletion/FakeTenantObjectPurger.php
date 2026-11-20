<?php

namespace Tests\Support\CustomerDeletion;

use App\Services\CustomerDeletion\Guards\ObjectPrefixGuard;
use App\Services\CustomerDeletion\Purge\TenantObjectPurger;

/**
 * ADR-090 test double: an in-memory object store per target. Prefix
 * semantics are plain str_starts_with, exactly like S3 — so a missing
 * delimiter in a prefix would visibly over-delete in the assertions.
 */
class FakeTenantObjectPurger extends TenantObjectPurger
{
    /** @var array<string, array<string, int>> target => key => size */
    public array $objects = ['backups' => [], 'media' => []];

    /** @var array<string, string> target => exception message for the next purge */
    public array $failNext = [];

    /** @var list<array{target: string, prefix: string}> */
    public array $purged = [];

    public function __construct()
    {
        parent::__construct(fn () => throw new \LogicException('fake purger never builds a client'));
    }

    public function put(string $target, string $key, int $size = 100): self
    {
        $this->objects[$target][$key] = $size;

        return $this;
    }

    public function keys(string $target): array
    {
        return array_keys($this->objects[$target]);
    }

    public function inventory(string $target, string $prefix): array
    {
        $matching = $this->matching($target, $prefix);

        return ['objects' => count($matching), 'bytes' => array_sum($matching), 'versioning' => 'Unversioned'];
    }

    public function purge(string $target, string $prefix): array
    {
        if (isset($this->failNext[$target])) {
            $message = $this->failNext[$target];
            unset($this->failNext[$target]);
            throw new \RuntimeException($message);
        }

        $matching = $this->matching($target, $prefix);
        foreach (array_keys($matching) as $key) {
            ObjectPrefixGuard::assertKeyWithin($key, $prefix);
            unset($this->objects[$target][$key]);
        }
        $this->purged[] = ['target' => $target, 'prefix' => $prefix];

        return ['prefix' => $prefix, 'deleted' => count($matching), 'bytes' => array_sum($matching), 'versioning' => 'Unversioned'];
    }

    private function matching(string $target, string $prefix): array
    {
        return array_filter(
            $this->objects[$target],
            fn ($key) => str_starts_with((string) $key, $prefix),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
