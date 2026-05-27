<?php

namespace SaaSPlatform\CoreUtils\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class GlobalSearchEngine
{
    private array $searchDrivers = [];

    public function registerDriver(string $resource, callable $driver): void
    {
        $this->searchDrivers[$resource] = $driver;
    }

    public function search(string $query, string $tenantId, array $types = [], int $limit = 20): array
    {
        if (strlen(trim($query)) < 2) {
            return ['results' => [], 'total' => 0, 'query' => $query];
        }

        $cacheKey = "search:{$tenantId}:" . md5($query . serialize($types));
        return Cache::remember($cacheKey, 60, function () use ($query, $tenantId, $types, $limit) {
            return $this->executeSearch($query, $tenantId, $types, $limit);
        });
    }

    public function suggest(string $partial, string $tenantId): array
    {
        if (strlen($partial) < 2) {
            return [];
        }

        $results = [];

        // Search tickets
        if (class_exists(\SaaSPlatform\Modules\Tickets\Models\Ticket::class)) {
            try {
                $tickets = \SaaSPlatform\Modules\Tickets\Models\Ticket::where('site_id', $tenantId)
                    ->where(fn ($q) => $q->where('subject', 'like', "%{$partial}%")->orWhere('ticket_number', 'like', "%{$partial}%"))
                    ->limit(3)
                    ->get(['id', 'subject', 'ticket_number', 'status']);

                foreach ($tickets as $t) {
                    $results[] = [
                        'type'  => 'ticket',
                        'id'    => $t->id,
                        'label' => $t->subject,
                        'meta'  => '#' . $t->ticket_number,
                        'url'   => route('admin.tickets.show', $t->id),
                    ];
                }
            } catch (\Throwable) {}
        }

        // Search forms
        if (class_exists(\SaaSPlatform\Modules\Forms\Models\Form::class)) {
            try {
                $forms = \SaaSPlatform\Modules\Forms\Models\Form::where('site_id', $tenantId)
                    ->where('title', 'like', "%{$partial}%")
                    ->limit(3)
                    ->get(['id', 'title', 'slug']);

                foreach ($forms as $f) {
                    $results[] = [
                        'type'  => 'form',
                        'id'    => $f->id,
                        'label' => $f->title,
                        'meta'  => $f->slug,
                        'url'   => route('admin.forms.show', $f->id),
                    ];
                }
            } catch (\Throwable) {}
        }

        return $results;
    }

    private function executeSearch(string $query, string $tenantId, array $types, int $limit): array
    {
        $allResults = [];
        $activeTypes = empty($types)
            ? array_keys($this->searchDrivers)
            : array_intersect(array_keys($this->searchDrivers), $types);

        foreach ($activeTypes as $type) {
            try {
                $driver  = $this->searchDrivers[$type];
                $results = $driver($query, $tenantId, (int) ceil($limit / max(count($activeTypes), 1)));
                foreach ($results as $r) {
                    $allResults[] = array_merge($r, ['resource_type' => $type]);
                }
            } catch (\Throwable) {
                // Individual driver failures do not fail the entire search
            }
        }

        usort($allResults, fn ($a, $b) => ($b['relevance'] ?? 0) <=> ($a['relevance'] ?? 0));

        return [
            'results' => array_slice($allResults, 0, $limit),
            'total'   => count($allResults),
            'query'   => $query,
        ];
    }
}
