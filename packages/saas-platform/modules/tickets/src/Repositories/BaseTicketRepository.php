<?php

namespace SaaSPlatform\Modules\Tickets\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\BaseTicketRepositoryInterface;

/**
 * PHASE 7.1 — TASK 4: Base Ticket Repository
 *
 * Provides shared implementations for all ticket repositories.
 * Subclasses bind to a specific Eloquent model via $modelClass.
 */
abstract class BaseTicketRepository implements BaseTicketRepositoryInterface
{
    protected string $modelClass;

    public function all(array $relations = []): Collection
    {
        return $this->modelClass::with($relations)->get();
    }

    public function findById(int $id, array $relations = []): ?object
    {
        return $this->modelClass::with($relations)->find($id);
    }

    public function create(array $data): object
    {
        return $this->modelClass::create($data);
    }

    public function update(object $model, array $data): object
    {
        $model->update($data);
        return $model->fresh();
    }

    public function delete(object $model): bool
    {
        return (bool) $model->delete();
    }

    public function paginate(int $perPage = 20, array $relations = []): LengthAwarePaginator
    {
        return $this->modelClass::with($relations)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    protected function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
