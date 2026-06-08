<?php
namespace App\Services;

use App\Models\Customer;
use App\Models\Site;
use App\Services\ActivityLogger;
use App\Services\Infrastructure\StorageManager;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerService
{
    private const CACHE_TTL = 900; // 15 دقیقه
    private const PER_PAGE = 50;

    public function __construct(private StorageManager $storage) {}

    /**
     * جستجوی مشتریان با فیلترهای پیشرفته و کش هوشمند
     */
    public function search(array $filters = [], string $sortBy = 'created_at', string $sortDir = 'desc'): LengthAwarePaginator
    {
        $cacheKey = 'customers_search_' . md5(serialize($filters) . $sortBy . $sortDir . request('page', 1));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters, $sortBy, $sortDir) {
            $query = Customer::with(['user.profile', 'sites', 'reseller.user.profile', 'activeEntitlements'])
                ->select('customers.*')
                ->orderBy($sortBy, $sortDir);

            // فیلتر نام / شرکت
            if (!empty($filters['name'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('name', 'like', '%' . $filters['name'] . '%')
                      ->orWhere('company_name', 'like', '%' . $filters['name'] . '%');
                });
            }

            // فیلتر موبایل
            if (!empty($filters['mobile'])) {
                $query->where('mobile', 'like', '%' . $filters['mobile'] . '%');
            }

            // فیلتر ایمیل
            if (!empty($filters['email'])) {
                $query->where('email', 'like', '%' . $filters['email'] . '%');
            }

            // فیلتر وضعیت (از sites)
            if (!empty($filters['has_site'])) {
                if ($filters['has_site'] === 'yes') {
                    $query->has('sites');
                } elseif ($filters['has_site'] === 'no') {
                    $query->doesntHave('sites');
                }
            }

            // فیلتر نماینده
            if (!empty($filters['reseller_id'])) {
                $query->where('reseller_id', $filters['reseller_id']);
            }

            // فیلتر تاریخ
            if (!empty($filters['created_from'])) {
                $query->whereDate('created_at', '>=', $filters['created_from']);
            }
            if (!empty($filters['created_to'])) {
                $query->whereDate('created_at', '<=', $filters['created_to']);
            }

            // فیلتر جستجوی کلی
            if (!empty($filters['q'])) {
                $q = $filters['q'];
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                          ->orWhere('company_name', 'like', "%{$q}%")
                          ->orWhere('mobile', 'like', "%{$q}%")
                          ->orWhere('email', 'like', "%{$q}%");
                });
            }

            return $query->paginate(self::PER_PAGE);
        });
    }

    /**
     * ایجاد مشتری جدید
     */
    public function create(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::create([
                'name'         => $data['name'],
                'email'        => $data['email'] ?? null,
                'mobile'       => $data['mobile'],
                'company_name' => $data['company_name'] ?? null,
                'notes'        => $data['notes'] ?? null,
                'user_id'      => $data['user_id'] ?? null,
                'reseller_id'  => $data['reseller_id'] ?? null,
            ]);

            ActivityLogger::log('customer.created', "مشتری جدید: {$customer->name}", $customer);
            $this->clearCache();

            return $customer;
        });
    }

    /**
     * به‌روزرسانی مشتری
     */
    public function update(Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($customer, $data) {
            $customer->update([
                'name'         => $data['name'] ?? $customer->name,
                'mobile'       => $data['mobile'] ?? $customer->mobile,
                'company_name' => $data['company_name'] ?? $customer->company_name,
                'notes'        => $data['notes'] ?? $customer->notes,
                'user_id'      => $data['user_id'] ?? $customer->user_id,
                'reseller_id'  => $data['reseller_id'] ?? $customer->reseller_id,
            ]);

            ActivityLogger::log('customer.updated', "ویرایش مشتری #{$customer->id}", $customer);
            $this->clearCache();

            return $customer->fresh(['user.profile', 'sites', 'reseller']);
        });
    }

    /**
     * Soft Delete مشتری
     */
    public function softDelete(Customer $customer): void
    {
        $name = $customer->company_name ?: $customer->name;
        $customer->delete();
        ActivityLogger::log('customer.deleted', "حذف نرم مشتری: {$name}", $customer);
        $this->clearCache();
    }

    /**
     * Bulk soft delete مشتریان
     */
    public function bulkSoftDelete(array $customerIds): int
    {
        $count = 0;
        foreach (array_chunk($customerIds, 50) as $chunk) {
            $customers = Customer::whereIn('id', $chunk)->get();
            foreach ($customers as $customer) {
                $customer->delete();
                ActivityLogger::log('customer.deleted', "حذف نرم گروهی مشتری #{$customer->id}", $customer);
                $count++;
            }
            $this->clearCache();
        }
        return $count;
    }

    /**
     * بازیابی مشتریان حذف‌شده
     */
    public function bulkRestore(array $customerIds): int
    {
        $count = Customer::withTrashed()->whereIn('id', $customerIds)->restore();
        foreach ($customerIds as $id) {
            ActivityLogger::log('customer.restored', "بازیابی مشتری #{$id}");
        }
        $this->clearCache();
        return $count;
    }

    /**
     * Export مشتریان به CSV
     */
    public function exportCsv(array $filters): string
    {
        $customers = Customer::with(['sites', 'user.profile'])
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'customers_export_' . now()->format('Ymd_His') . '.csv';

        $rows = $customers->map(fn ($c) => [
            $c->id,
            $c->name,
            $c->company_name ?? '',
            $c->mobile,
            $c->sites->count(),
            $c->reseller?->company_name ?? '',
            $c->created_at->format('Y-m-d H:i'),
        ]);

        $this->storage->writeCsv('', $filename, ['#', 'نام', 'شرکت', 'موبایل', 'تعداد سایت', 'نماینده', 'تاریخ ثبت'], $rows, 'exports');

        return $filename;
    }

    /**
     * آمار مشتریان برای داشبورد
     */
    public function getStats(): array
    {
        return Cache::remember('customer_stats', self::CACHE_TTL, function () {
            return [
                'total'            => Customer::count(),
                'with_sites'       => Customer::has('sites')->count(),
                'without_sites'    => Customer::doesntHave('sites')->count(),
                'with_reseller'    => Customer::whereNotNull('reseller_id')->count(),
                'new_this_month'   => Customer::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)->count(),
            ];
        });
    }

    /**
     * پاک کردن کش
     */
    public function clearCache(): void
    {
        Cache::flush();
    }
}