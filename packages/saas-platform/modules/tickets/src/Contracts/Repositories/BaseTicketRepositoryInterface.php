<?php

namespace SaaSPlatform\Modules\Tickets\Contracts\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * PHASE 7.1 — TASK 4: Base Repository Interface
 *
 * Common methods shared across all ticket-related repositories.
 */
interface BaseTicketRepositoryInterface
{
    public function all(array $relations = []): Collection;
    public function findById(int $id, array $relations = []): ?object;
    public function create(array $data): object;
    public function update(object $model, array $data): object;
    public function delete(object $model): bool;
    public function paginate(int $perPage = 20, array $relations = []): LengthAwarePaginator;
}
