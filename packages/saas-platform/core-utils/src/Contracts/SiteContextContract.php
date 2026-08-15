<?php

namespace SaaSPlatform\CoreUtils\Contracts;

/**
 * Canonical context for an authenticated customer installation.
 *
 * This intentionally represents only a platform Site. It never represents a
 * reseller, a platform administrator, or an implicit null/platform context.
 */
interface SiteContextContract
{
    public function siteId(): ?int;

    public function hasSite(): bool;

    /** @throws \LogicException when no authenticated site context exists. */
    public function requireSiteId(): int;

    public function setSiteId(int $siteId): void;

    public function clear(): void;

    /** @return array{site_id: int}|null Safe to serialize into tenant jobs. */
    public function snapshot(): ?array;
}
