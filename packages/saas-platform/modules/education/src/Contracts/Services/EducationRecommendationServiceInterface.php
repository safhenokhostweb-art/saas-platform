<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

use Illuminate\Support\Collection;

interface EducationRecommendationServiceInterface
{
    /** Build and persist new recommendations for the user based on current usage. Returns count created. */
    public function generateForUser(int $userId, ?int $siteId): int;

    /** Return currently active (not dismissed, not completed) recommendations. */
    public function activeForUser(int $userId, ?int $siteId, int $limit = 5): Collection;

    /** Dismiss a recommendation. */
    public function dismiss(int $recommendationId, int $userId): bool;

    /** Mark a recommendation completed. */
    public function complete(int $recommendationId, int $userId): bool;
}
