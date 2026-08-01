<?php

namespace SaaSPlatform\Modules\Crm\Http\Controllers\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Phase 19 — Part 4: search/filter/sort/pagination mechanics are
 * identical across Company/Contact/Lead/Opportunity listings — shared
 * here instead of repeated in 4 controllers. Entity-specific fields
 * (e.g. Opportunity's stage/status vs Lead's status) are passed in as
 * $extraFilter, not hardcoded.
 */
trait FiltersCrmListings
{
    protected function applyListFilters(
        Builder $query,
        Request $request,
        array $sortable,
        string $defaultSort = 'created_at',
        ?\Closure $extraFilter = null,
    ): LengthAwarePaginator {
        $query->search($request->string('q')->toString() ?: null);

        if ($request->boolean('favorites')) {
            $query->favorites();
        }

        if ($request->filled('archived') && $request->boolean('archived')) {
            $query->archived();
        } else {
            $query->active();
        }

        if ($request->filled('tag')) {
            $tagId = $request->integer('tag');
            $query->whereHas('tags', fn (Builder $t) => $t->where('crm_tags.id', $tagId));
        }

        if ($extraFilter !== null) {
            $extraFilter($query, $request);
        }

        $sort = $request->string('sort', $defaultSort)->toString();
        $direction = $request->string('direction', 'desc')->toString() === 'asc' ? 'asc' : 'desc';
        if (!in_array($sort, $sortable, true)) {
            $sort = $defaultSort;
        }
        $query->orderBy($sort, $direction);

        return $query->paginate((int) config('crm.pagination', 20))->withQueryString();
    }

    /**
     * Phase 19 — Part 4: $siteId is always required and explicitly
     * applied — never rely on SiteTenantScope alone here. That scope is
     * a no-op outside the API/tenant_site context (see Tickets'
     * SiteTenantScope precedent: "site scoping enforced in services, not
     * middleware"), so a bulk action must filter by site_id itself or a
     * crafted request could touch another site's records.
     *
     * @param  int[]  $ids
     */
    protected function applyBulkAction(string $modelClass, int $siteId, array $ids, string $action): int
    {
        $query = $modelClass::where('site_id', $siteId)->whereIn('id', $ids);

        return match ($action) {
            'archive' => $query->get()->each->archive()->count(),
            'unarchive' => $query->get()->each->unarchive()->count(),
            'favorite' => $query->update(['is_favorite' => true]),
            'unfavorite' => $query->update(['is_favorite' => false]),
            'delete' => $query->delete(),
            default => 0,
        };
    }
}
