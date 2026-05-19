<?php

namespace App\Contracts\Repositories;

use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CustomerRepositoryInterface
{
    public function search(array $filters, string $sortBy, string $sortDir, int $perPage = 50): LengthAwarePaginator;

    public function findById(int $id, array $relations = []): ?Customer;

    public function create(array $data): Customer;

    public function update(Customer $customer, array $data): Customer;

    public function softDelete(Customer $customer): bool;

    public function bulkSoftDelete(array $ids): int;

    public function bulkRestore(array $ids): int;

    public function getAllForExport(array $filters): Collection;

    public function getStats(): array;
}