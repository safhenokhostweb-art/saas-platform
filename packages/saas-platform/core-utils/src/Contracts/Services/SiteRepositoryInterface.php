<?php

namespace SaaSPlatform\CoreUtils\Contracts\Services;

use Illuminate\Support\Collection;
use SaaSPlatform\CoreUtils\Contracts\Platform\SiteModelInterface;

/**
 * Contract for site data access.
 * Packages inject this instead of querying App\Models\Site directly.
 * The host app implements all Eloquent logic in SiteRepositoryAdapter.
 */
interface SiteRepositoryInterface
{
    /** All sites ordered by title, with id/title/domain only. */
    public function allForDisplay(): Collection;

    /** All active sites ordered by name. */
    public function allActive(): Collection;

    /** All site IDs. */
    public function allIds(): Collection;

    public function find(int $id): ?SiteModelInterface;

    /** Total count of sites. Returns 0 if sites table does not exist. */
    public function count(): int;
}
