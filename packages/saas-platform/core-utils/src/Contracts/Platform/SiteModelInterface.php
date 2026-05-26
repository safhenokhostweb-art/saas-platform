<?php

namespace SaaSPlatform\CoreUtils\Contracts\Platform;

/**
 * Contract for the platform Site model.
 * Packages import this interface; the host app binds its concrete Site model
 * to this interface in InfrastructureServiceProvider.
 */
interface SiteModelInterface
{
    public function getId(): int;
    public function getKey();
    public function getDomain(): ?string;
    public function getTitle(): ?string;
    public function isActive(): bool;
}
