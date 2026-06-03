<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

use Illuminate\Support\Collection;

interface FeatureUsageServiceInterface
{
    /** Track a feature visit for the given user. Returns false when the route maps to no known feature. */
    public function trackByRoute(string $routeName, int $userId, ?int $siteId): bool;

    /** Track by explicit module/feature slugs. */
    public function track(int $userId, ?int $siteId, string $moduleSlug, string $featureSlug): void;

    /** Features this user has never visited. */
    public function neverUsedFor(int $userId, ?int $siteId): Collection;

    /** Adoption statistics across all users for a site (or platform-wide). */
    public function adoptionStats(?int $siteId = null): array;
}
