<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

use SaaSPlatform\Modules\Education\Dto\UserLearningProfileDto;

interface UserLearningProfileServiceInterface
{
    /**
     * Build (and cache 15 min) the full learning profile for a user.
     */
    public function buildProfile(int $userId, ?int $siteId): UserLearningProfileDto;

    /**
     * Invalidate the cached profile so it is rebuilt on next call.
     */
    public function invalidate(int $userId, ?int $siteId): void;
}
