<?php

namespace App\Repositories;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Services\ActivityLogger;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CustomerRepository implements CustomerRepositoryInterface
{
    private const CACHE_TTL = 900;
    private const PER_PAGE = 50;

    private const RELATIONS_DEFAULT = ['user.profile', 'sites', 'reseller.user.profile', 'activeEntitlements'];

    public function search(array $filters, string $sortBy, string $sortDir, int $perPage = 50): LengthAwarePaginator
    {
        $cacheKey = 'customers_search_' . md5(serialize($filters) . $sortBy . $sortDir . request('page', 1) . $perPage);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters, $sortBy, $sortDir, $perPage) {
            $query = Customer::with(self::RELATIONS_DEFAULT)
                ->select('customers.*')
                ->orderBy($sortBy, $sortDir);

            if (!empty($filters['name'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('name', 'like', '%' . $filters['name'] . '%')
                      ->orWhere('company_name', 'like', '%' . $filters['name'] . '%');
                });
            }

            if (!empty($filters['mobile'])) {
                $query->where('mobile', 'like', '%' . $filters['mobile'] . '%');
            }

            if (!empty($filters['email'])) {
                $query->where('email', 'like', '%' . $filters['email'] . '%');
            }

            if (!empty($filters['has_site'])) {
                if ($filters['has_site'] === 'yes') {
                    $query->has('sites');
                } elseif ($filters['has_site'] === 'no') {
                    $query->doesntHave('sites');
                }
            }

            if (!empty($filters['reseller_id'])) {
                $query->where('reseller_id', $filters['reseller_id']);
            }

            if (!empty($filters['created_from'])) {
                $query->whereDate('created_at', '>=', $filters['created_from']);
            }
            if (!empty($filters['created_to'])) {
                $query->whereDate('created_at', '<=', $filters['created_to']);
            }

            if (!empty($filters['q'])) {
                $q = $filters['q'];
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                          ->orWhere('company_name', 'like', "%{$q}%")
                          ->orWhere('mobile', 'like', "%{$q}%")
                          ->orWhere('email', 'like', "%{$q}%");
                });
            }

            return $query->paginate($perPage);
        });
    }

    public function findById(int $id, array $relations = []): ?Customer
    {
        $relations = $relations ?: self::RELATIONS_DEFAULT;
        return Cache::remember("customer_{$id}_" . md5(implode(',', $relations)), self::CACHE_TTL, function () use ($id, $relations) {
            return Customer::with($relations)->find($id);
        });
    }

    public function create(array $data): Customer
    {
        $customer = Customer::create([
            'name'         => $data['name'],
            'email'        => $data['email'] ?? null,
            'mobile'       => $data['mobile'],
            'company_name' => $data['company_name'] ?? null,
            'notes'        => $data['notes'] ?? null,
            'user_id'      => $data['user_id'] ?? null,
            'reseller_id'  => $data['reseller_id'] ?? null,
        ]);
        $this->clearCache();
        return $customer;
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update([
            'name'         => $data['name'] ?? $customer->name,
            'email'        => $data['email'] ?? $customer->email,
            'mobile'       => $data['mobile'] ?? $customer->mobile,
            'company_name' => $data['company_name'] ?? $customer->company_name,
            'notes'        => $data['notes'] ?? $customer->notes,
            'user_id'      => $data['user_id'] ?? $customer->user_id,
            'reseller_id'  => $data['reseller_id'] ?? $customer->reseller_id,
        ]);
        $this->clearCache();
        return $customer->fresh(self::RELATIONS_DEFAULT);
    }

    public function softDelete(Customer $customer): bool
    {
        $result = $customer->delete();
        $this->clearCache();
        return $result;
    }

    public function bulkSoftDelete(array $ids): int
    {
        $count = 0;
        foreach (array_chunk($ids, 50) as $chunk) {
            $customers = Customer::whereIn('id', $chunk)->get();
            foreach ($customers as $customer) {
                $customer->delete();
                $count++;
            }
        }
        $this->clearCache();
        return $count;
    }

    public function bulkRestore(array $ids): int
    {
        $count = Customer::withTrashed()->whereIn('id', $ids)->restore();
        $this->clearCache();
        return $count;
    }

    public function getAllForExport(array $filters): Collection
    {
        $query = Customer::with(self::RELATIONS_DEFAULT)->orderBy('created_at', 'desc');

        if (!empty($filters['name'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['name'] . '%')
                  ->orWhere('company_name', 'like', '%' . $filters['name'] . '%');
            });
        }
        if (!empty($filters['mobile'])) {
            $query->where('mobile', 'like', '%' . $filters['mobile'] . '%');
        }
        if (!empty($filters['email'])) {
            $query->where('email', 'like', '%' . $filters['email'] . '%');
        }

        return $query->get();
    }

    public function getStats(): array
    {
        return Cache::remember('customer_stats', self::CACHE_TTL, function () {
            return [
                'total'          => Customer::count(),
                'with_sites'     => Customer::has('sites')->count(),
                'without_sites'  => Customer::doesntHave('sites')->count(),
                'with_reseller'  => Customer::whereNotNull('reseller_id')->count(),
                'new_this_month' => Customer::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)->count(),
            ];
        });
    }

    public function clearCache(): void
    {
        Cache::flush();
    }
}